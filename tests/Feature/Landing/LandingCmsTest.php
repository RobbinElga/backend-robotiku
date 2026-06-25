<?php

namespace Tests\Feature\Landing;

use App\Models\LandingContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LandingCmsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): void
    {
        $u = User::create(['name' => $role, 'email' => $role . '-' . uniqid() . '@r.id', 'password' => bcrypt('x'), 'role' => $role, 'is_active' => true]);
        Sanctum::actingAs($u);
    }

    public function test_publik_lihat_semua_section(): void
    {
        LandingContent::create(['section' => 'hero', 'content' => ['title' => 'Halo']]);
        $this->getJson('/api/v1/landing')->assertOk()->assertJsonPath('data.hero.title', 'Halo');
    }

    public function test_publik_lihat_satu_section(): void
    {
        LandingContent::create(['section' => 'hero', 'content' => ['title' => 'Halo']]);
        $this->getJson('/api/v1/landing/hero')->assertOk()->assertJsonPath('data.content.title', 'Halo');
    }

    public function test_section_tidak_dikenal_404(): void
    {
        $this->getJson('/api/v1/landing/ngawur')->assertStatus(404);
    }

    public function test_admin_update_hero(): void
    {
        $this->actingAsRole('admin');

        $this->putJson('/api/v1/landing/hero', ['content' => [
            'title' => 'Judul Baru',
            'subtitle' => 'Sub',
            'primary_cta' => ['label' => 'Daftar', 'href' => '/daftar'],
        ]])->assertOk()->assertJsonPath('data.content.title', 'Judul Baru');

        $this->assertDatabaseHas('landing_contents', ['section' => 'hero']);
    }

    public function test_update_invalid_tanpa_title(): void
    {
        $this->actingAsRole('admin');
        $this->putJson('/api/v1/landing/hero', ['content' => ['subtitle' => 'tanpa judul']])
            ->assertStatus(422);
    }

    public function test_marketing_tidak_boleh_update(): void
    {
        $this->actingAsRole('marketing');
        $this->putJson('/api/v1/landing/hero', ['content' => ['title' => 'X']])->assertStatus(403);
    }

    public function test_upload_gambar(): void
    {
        Storage::fake('public');
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/landing-upload', ['image' => UploadedFile::fake()->image('hero.jpg')])
            ->assertOk()->assertJsonStructure(['data' => ['url', 'path']]);
    }
}
