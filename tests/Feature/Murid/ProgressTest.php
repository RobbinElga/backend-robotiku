<?php

namespace Tests\Feature\Murid;

use App\Models\Attendance;
use App\Models\Kelas;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgressTest extends TestCase
{
    use RefreshDatabase;

    private function studentWithAttendance(array $studentAttr = []): array
    {
        $trainer = User::create(['name' => 'T', 'email' => 'tr' . uniqid() . '@r.id', 'password' => bcrypt('x'), 'role' => 'trainer', 'is_active' => true]);
        $kelas = Kelas::create(['name' => 'Robo', 'trainer_id' => $trainer->id]);

        $student = Student::create(array_merge([
            'student_code' => 'ROBO-MDR' . uniqid(),
            'name' => 'Andi',
            'gender' => 'L',
            'status' => 'aktif',
            'registration_type' => 'mandiri',
        ], $studentAttr));
        $student->classes()->attach($kelas->id, ['joined_at' => now()]);

        foreach (['hadir', 'hadir', 'izin'] as $st) {
            Attendance::create(['class_id' => $kelas->id, 'student_id' => $student->id, 'trainer_id' => $trainer->id, 'status' => $st, 'attended_at' => now()]);
        }

        return [$student, $trainer, $kelas];
    }

    public function test_ortu_lihat_progress(): void
    {
        $parent = StudentParent::create(['name' => 'Budi', 'phone' => '081200000001']);
        [$student] = $this->studentWithAttendance(['parent_id' => $parent->id]);

        $this->postJson('/api/v1/murid/progress', ['student_id' => $student->id, 'phone' => '6281200000001'])
            ->assertOk()
            ->assertJsonPath('data.summary.hadir', 2)
            ->assertJsonPath('data.summary.izin', 1)
            ->assertJsonPath('data.summary.total_sesi', 3);
    }

    public function test_ortu_hp_salah_ditolak(): void
    {
        $parent = StudentParent::create(['name' => 'Budi', 'phone' => '081200000001']);
        [$student] = $this->studentWithAttendance(['parent_id' => $parent->id]);

        $this->postJson('/api/v1/murid/progress', ['student_id' => $student->id, 'phone' => '0899'])
            ->assertStatus(403);
    }

    public function test_admin_sekolah_lihat_murid_sekolahnya(): void
    {
        $school = School::create(['name' => 'SD A', 'pipeline_status' => 'sudah_mou', 'is_mou' => true]);
        [$student] = $this->studentWithAttendance(['school_id' => $school->id, 'registration_type' => 'instansi']);

        $admin = SchoolAdmin::create(['school_id' => $school->id, 'name' => 'AS', 'email' => 'as@a.id', 'password' => bcrypt('x'), 'is_active' => true]);
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/sekolah/murid/{$student->id}/progress")->assertOk();

        // sekolah lain → 403
        $other = SchoolAdmin::create(['school_id' => School::create(['name' => 'SD B'])->id, 'name' => 'X', 'email' => 'x@b.id', 'password' => bcrypt('x'), 'is_active' => true]);
        Sanctum::actingAs($other);
        $this->getJson("/api/v1/sekolah/murid/{$student->id}/progress")->assertStatus(403);
    }

    public function test_trainer_hanya_kelasnya(): void
    {
        [$student, $trainer] = $this->studentWithAttendance();
        Sanctum::actingAs($trainer);
        $this->getJson("/api/v1/manajemen/murid/{$student->id}/progress")->assertOk();

        $trainerLain = User::create(['name' => 'TL', 'email' => 'tl@r.id', 'password' => bcrypt('x'), 'role' => 'trainer', 'is_active' => true]);
        Sanctum::actingAs($trainerLain);
        $this->getJson("/api/v1/manajemen/murid/{$student->id}/progress")->assertStatus(403);
    }

    public function test_admin_lihat_semua(): void
    {
        [$student] = $this->studentWithAttendance();
        $admin = User::create(['name' => 'A', 'email' => 'a@r.id', 'password' => bcrypt('x'), 'role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/manajemen/murid/{$student->id}/progress")->assertOk()
            ->assertJsonPath('data.summary.total_sesi', 3);
    }
}
