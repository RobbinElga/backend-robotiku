<?php

namespace Tests\Feature\Canvas;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchoolCanvasTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): void
    {
        $user = User::create([
            'name'      => $role,
            'email'     => $role . '-' . uniqid() . '@r.id', // unik, hindari bentrok
            'password'  => bcrypt('x'),
            'role'      => $role,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user); // override user aktif tiap dipanggil
    }

    public function test_marketing_bisa_lihat_dan_tambah(): void
    {
        $this->actingAsRole('marketing');

        $this->postJson('/api/v1/canvas/schools', [
            'name' => 'SD Harapan',
            'pipeline_status' => 'prospek',
        ])->assertStatus(201)->assertJsonPath('data.name', 'SD Harapan');

        $this->getJson('/api/v1/canvas/schools')
            ->assertOk()->assertJsonPath('data.kpi.total', 1);
    }

    public function test_buat_sebagai_mou_set_is_mou_true(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/canvas/schools', [
            'name' => 'SD MOU',
            'pipeline_status' => 'sudah_mou',
        ])->assertStatus(201)->assertJsonPath('data.is_mou', true);
    }

    public function test_filter_status_dan_search(): void
    {
        School::create(['name' => 'SD Alpha', 'pipeline_status' => 'prospek']);
        School::create(['name' => 'SD Beta', 'pipeline_status' => 'sudah_mou']);

        $this->actingAsRole('admin');

        $this->getJson('/api/v1/canvas/schools?status=sudah_mou')
            ->assertOk()->assertJsonCount(1, 'data.schools.data');

        $this->getJson('/api/v1/canvas/schools?search=Alpha')
            ->assertOk()->assertJsonCount(1, 'data.schools.data');
    }

    public function test_admin_bisa_update_marketing_tidak(): void
    {
        $school = School::create(['name' => 'SD Lama', 'pipeline_status' => 'prospek']);

        $this->actingAsRole('admin');
        $this->putJson("/api/v1/canvas/schools/{$school->id}", ['name' => 'SD Baru'])
            ->assertOk()->assertJsonPath('data.name', 'SD Baru');

        $this->actingAsRole('marketing'); // switch role
        $this->putJson("/api/v1/canvas/schools/{$school->id}", ['name' => 'X'])
            ->assertStatus(403);
    }

    public function test_trainer_tidak_boleh_akses_canvas(): void
    {
        $this->actingAsRole('trainer');
        $this->getJson('/api/v1/canvas/schools')->assertStatus(403);
    }
}
