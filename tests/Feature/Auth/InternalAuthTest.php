<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InternalAuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attr = []): User
    {
        return User::create(array_merge([
            'name'      => 'Tester',
            'email'     => 'tester@robotiku.id',
            'password'  => Hash::make('password'),
            'role'      => 'admin',
            'is_active' => true,
        ], $attr));
    }

    public function test_login_berhasil_mengembalikan_token(): void
    {
        $this->makeUser();

        $res = $this->postJson('/api/v1/auth/login', [
            'email'    => 'tester@robotiku.id',
            'password' => 'password',
        ]);

        $res->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['status', 'data' => ['token', 'user' => ['id', 'role']], 'message']);
    }

    public function test_login_gagal_password_salah(): void
    {
        $this->makeUser();

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'tester@robotiku.id',
            'password' => 'salah',
        ])->assertStatus(401)->assertJsonPath('status', false);
    }

    public function test_akun_nonaktif_ditolak(): void
    {
        $this->makeUser(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'tester@robotiku.id',
            'password' => 'password',
        ])->assertStatus(403);
    }

    public function test_me_butuh_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_logout_mencabut_token(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
