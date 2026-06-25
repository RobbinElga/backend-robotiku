<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): User
    {
        $u = User::create(['name' => $role, 'email' => $role . '-' . uniqid() . '@r.id', 'password' => bcrypt('x'), 'role' => $role, 'is_active' => true]);
        Sanctum::actingAs($u);
        return $u;
    }

    public function test_super_admin_buat_akun(): void
    {
        $this->actingAsRole('super_admin');

        $this->postJson('/api/v1/akun', [
            'name' => 'Trainer Baru',
            'email' => 'trainer@r.id',
            'password' => 'rahasia123',
            'role' => 'trainer',
        ])->assertStatus(201)->assertJsonPath('data.role', 'trainer');

        $this->assertDatabaseHas('users', ['email' => 'trainer@r.id', 'role' => 'trainer']);
    }

    public function test_admin_biasa_tidak_boleh(): void
    {
        $this->actingAsRole('admin');
        $this->getJson('/api/v1/akun')->assertStatus(403);
    }

    public function test_email_harus_unik(): void
    {
        $this->actingAsRole('super_admin');
        User::create(['name' => 'X', 'email' => 'dobel@r.id', 'password' => bcrypt('x'), 'role' => 'admin', 'is_active' => true]);

        $this->postJson('/api/v1/akun', ['name' => 'Y', 'email' => 'dobel@r.id', 'password' => 'rahasia123', 'role' => 'admin'])
            ->assertStatus(422);
    }

    public function test_reset_password_berfungsi(): void
    {
        $this->actingAsRole('super_admin');
        $target = User::create(['name' => 'T', 'email' => 'target@r.id', 'password' => bcrypt('lama'), 'role' => 'trainer', 'is_active' => true]);

        $this->patchJson("/api/v1/akun/{$target->id}/password", ['password' => 'passwordbaru'])->assertOk();

        // login pakai password baru harus berhasil
        $this->postJson('/api/v1/auth/login', ['email' => 'target@r.id', 'password' => 'passwordbaru'])->assertOk();
    }

    public function test_nonaktifkan_akun(): void
    {
        $this->actingAsRole('super_admin');
        $target = User::create(['name' => 'T', 'email' => 'target@r.id', 'password' => bcrypt('x'), 'role' => 'marketing', 'is_active' => true]);

        $this->patchJson("/api/v1/akun/{$target->id}/status", ['is_active' => false])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
    }

    public function test_tidak_bisa_nonaktifkan_diri_sendiri(): void
    {
        $me = $this->actingAsRole('super_admin');
        $this->patchJson("/api/v1/akun/{$me->id}/status", ['is_active' => false])->assertStatus(422);
    }
}
