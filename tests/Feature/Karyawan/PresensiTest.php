<?php

namespace Tests\Feature\Karyawan;

use App\Models\EmployeeAttendance;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PresensiTest extends TestCase
{
    use RefreshDatabase;

    // titik pusat untuk test
    private const LAT = -0.026330;
    private const LNG = 109.342503;

    private function setupGeofence(int $radius = 500): void
    {
        Setting::create(['key' => 'office_latitude', 'value' => (string) self::LAT]);
        Setting::create(['key' => 'office_longitude', 'value' => (string) self::LNG]);
        Setting::create(['key' => 'geofencing_radius_meters', 'value' => (string) $radius]);
    }

    private function trainer(): User
    {
        $t = User::create(['name' => 'T', 'email' => 'tr' . uniqid() . '@r.id', 'password' => bcrypt('x'), 'role' => 'trainer', 'is_active' => true]);
        Sanctum::actingAs($t);
        return $t;
    }

    public function test_presensi_dalam_radius(): void
    {
        Storage::fake('local');
        $this->setupGeofence();
        $this->trainer();

        $this->postJson('/api/v1/absensi-karyawan', [
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'photo' => UploadedFile::fake()->image('w.jpg'),
        ])->assertStatus(201);

        $this->assertDatabaseCount('employee_attendances', 1);
    }

    public function test_presensi_di_luar_radius_ditolak(): void
    {
        Storage::fake('local');
        $this->setupGeofence(500);
        $this->trainer();

        // ~3 km dari titik (geser longitude ~0.03 derajat)
        $this->postJson('/api/v1/absensi-karyawan', [
            'latitude' => self::LAT,
            'longitude' => self::LNG + 0.03,
            'photo' => UploadedFile::fake()->image('w.jpg'),
        ])->assertStatus(422);
    }

    public function test_tidak_bisa_presensi_dua_kali_sehari(): void
    {
        Storage::fake('local');
        $this->setupGeofence();
        $trainer = $this->trainer();
        EmployeeAttendance::create([
            'trainer_id' => $trainer->id,
            'photo' => 'x.webp',
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'attendance_date' => today()->toDateString(),
        ]);

        $this->postJson('/api/v1/absensi-karyawan', [
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'photo' => UploadedFile::fake()->image('w.jpg'),
        ])->assertStatus(422);
    }

    public function test_non_trainer_ditolak(): void
    {
        $u = User::create(['name' => 'A', 'email' => 'a@r.id', 'password' => bcrypt('x'), 'role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($u);

        $this->postJson('/api/v1/absensi-karyawan', [
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'photo' => UploadedFile::fake()->image('w.jpg'),
        ])->assertStatus(403);
    }

    public function test_admin_lihat_rekap(): void
    {
        $trainer = User::create(['name' => 'T', 'email' => 't@r.id', 'password' => bcrypt('x'), 'role' => 'trainer', 'is_active' => true]);
        EmployeeAttendance::create(['trainer_id' => $trainer->id, 'photo' => 'x.webp', 'latitude' => self::LAT, 'longitude' => self::LNG, 'attendance_date' => today()->toDateString()]);

        $admin = User::create(['name' => 'A', 'email' => 'a@r.id', 'password' => bcrypt('x'), 'role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/absensi-karyawan/rekap')->assertOk()->assertJsonCount(1, 'data.data');
    }
}
