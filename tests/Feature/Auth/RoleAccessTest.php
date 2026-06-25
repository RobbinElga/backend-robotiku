<?php

namespace Tests\Feature\Auth;

use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userToken(string $role): string
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => "$role@robotiku.id",
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => true,
        ]);

        return $user->createToken('test')->plainTextToken;
    }

    public function test_role_diizinkan_bisa_akses(): void
    {
        $token = $this->userToken('admin');

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/ping/internal')
            ->assertOk()->assertJsonPath('data', 'pong');
    }

    public function test_role_tidak_diizinkan_ditolak(): void
    {
        $token = $this->userToken('marketing'); // tidak ada di daftar role route

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/ping/internal')
            ->assertStatus(403);
    }

    public function test_tanpa_token_ditolak(): void
    {
        $this->getJson('/api/v1/ping/internal')->assertStatus(401);
    }

    public function test_school_admin_bukan_internal_ditolak(): void
    {
        $school = School::create(['name' => 'SD X', 'pipeline_status' => 'sudah_mou', 'is_mou' => true]);
        $admin = SchoolAdmin::create([
            'school_id' => $school->id,
            'name' => 'A',
            'email' => 'a@x.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/ping/internal')
            ->assertStatus(403);
    }
}
