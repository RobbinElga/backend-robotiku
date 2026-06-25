<?php

namespace Tests\Feature\Auth;

use App\Models\School;
use App\Models\SchoolAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SchoolAdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $attr = []): SchoolAdmin
    {
        $school = School::create(['name' => 'SD Test', 'pipeline_status' => 'sudah_mou', 'is_mou' => true]);

        return SchoolAdmin::create(array_merge([
            'school_id' => $school->id,
            'name'      => 'Admin Sekolah',
            'email'     => 'adminsekolah@test.id',
            'phone'     => '081234567890',
            'password'  => Hash::make('password'),
            'is_active' => true,
        ], $attr));
    }

    public function test_login_dengan_email(): void
    {
        $this->makeAdmin();

        $this->postJson('/api/v1/auth/school-admin/login', [
            'login' => 'adminsekolah@test.id',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['token', 'admin' => ['school_id']]]);
    }

    public function test_login_dengan_nomor_hp_format_62(): void
    {
        $this->makeAdmin();

        // kirim +62, harus cocok dengan 081234567890 di DB
        $this->postJson('/api/v1/auth/school-admin/login', [
            'login' => '+6281234567890',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('status', true);
    }

    public function test_login_gagal_password_salah(): void
    {
        $this->makeAdmin();

        $this->postJson('/api/v1/auth/school-admin/login', [
            'login' => 'adminsekolah@test.id',
            'password' => 'salah',
        ])->assertStatus(401);
    }

    public function test_akun_nonaktif_ditolak(): void
    {
        $this->makeAdmin(['is_active' => false]);

        $this->postJson('/api/v1/auth/school-admin/login', [
            'login' => 'adminsekolah@test.id',
            'password' => 'password',
        ])->assertStatus(403);
    }
}
