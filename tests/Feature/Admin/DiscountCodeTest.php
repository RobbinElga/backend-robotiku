<?php

namespace Tests\Feature\Admin;

use App\Models\DiscountCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiscountCodeTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): void
    {
        $u = User::create(['name' => $role, 'email' => $role . '-' . uniqid() . '@r.id', 'password' => bcrypt('x'), 'role' => $role, 'is_active' => true]);
        Sanctum::actingAs($u);
    }

    public function test_buat_promo_disimpan_uppercase(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/promo', [
            'code' => 'hemat50',
            'type' => 'percentage',
            'value' => 50,
            'quota' => 0,
        ])->assertStatus(201)->assertJsonPath('data.code', 'HEMAT50');

        $this->assertDatabaseHas('discount_codes', ['code' => 'HEMAT50']);
    }

    public function test_percentage_maks_100(): void
    {
        $this->actingAsRole('admin');
        $this->postJson('/api/v1/promo', ['code' => 'X', 'type' => 'percentage', 'value' => 150, 'quota' => 0])
            ->assertStatus(422);
    }

    public function test_kode_duplikat_ditolak(): void
    {
        $this->actingAsRole('admin');
        DiscountCode::create(['code' => 'DOBEL', 'type' => 'nominal', 'value' => 1000, 'quota' => 0, 'is_active' => true]);

        $this->postJson('/api/v1/promo', ['code' => 'dobel', 'type' => 'nominal', 'value' => 5000, 'quota' => 0])
            ->assertStatus(422);
    }

    public function test_update_nonaktifkan(): void
    {
        $this->actingAsRole('admin');
        $code = DiscountCode::create(['code' => 'AKTIF', 'type' => 'nominal', 'value' => 1000, 'quota' => 0, 'is_active' => true]);

        $this->putJson("/api/v1/promo/{$code->id}", ['is_active' => false])->assertOk();
        $this->assertDatabaseHas('discount_codes', ['id' => $code->id, 'is_active' => false]);
    }

    public function test_tidak_bisa_hapus_yang_sudah_dipakai(): void
    {
        $this->actingAsRole('admin');
        $code = DiscountCode::create(['code' => 'DIPAKAI', 'type' => 'nominal', 'value' => 1000, 'quota' => 0, 'used_count' => 3, 'is_active' => true]);

        $this->deleteJson("/api/v1/promo/{$code->id}")->assertStatus(422);
    }

    public function test_marketing_tidak_boleh(): void
    {
        $this->actingAsRole('marketing');
        $this->getJson('/api/v1/promo')->assertStatus(403);
    }
}
