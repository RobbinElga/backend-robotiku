<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): void
    {
        $u = User::create(['name' => $role, 'email' => $role . '-' . uniqid() . '@r.id', 'password' => bcrypt('x'), 'role' => $role, 'is_active' => true]);
        Sanctum::actingAs($u);
    }

    public function test_buat_artikel_publish_set_slug_dan_tanggal(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/admin/artikel', [
            'title' => 'Belajar Coding Sejak Dini',
            'content' => 'isi',
            'status' => 'publish',
        ])->assertStatus(201)
            ->assertJsonPath('data.slug', 'belajar-coding-sejak-dini')
            ->assertJsonPath('data.status', 'publish');

        $this->assertDatabaseHas('articles', ['slug' => 'belajar-coding-sejak-dini']);
        $this->assertNotNull(Article::first()->published_at);
    }

    public function test_slug_unik_otomatis(): void
    {
        $this->actingAsRole('admin');
        Article::create(['title' => 'Sama', 'slug' => 'sama', 'status' => 'draft']); // tanpa created_by

        $this->postJson('/api/v1/admin/artikel', ['title' => 'Sama', 'status' => 'draft'])
            ->assertStatus(201)->assertJsonPath('data.slug', 'sama-1');
    }

    public function test_publik_hanya_lihat_publish(): void
    {
        Article::create(['title' => 'Pub', 'slug' => 'pub', 'status' => 'publish', 'published_at' => now()]);
        Article::create(['title' => 'Draf', 'slug' => 'draf', 'status' => 'draft']);

        $this->getJson('/api/v1/artikel')->assertOk()->assertJsonCount(1, 'data.data');
        $this->getJson('/api/v1/artikel/pub')->assertOk()->assertJsonPath('data.title', 'Pub');
        $this->getJson('/api/v1/artikel/draf')->assertStatus(404);
    }

    public function test_trainer_tidak_boleh_crud(): void
    {
        $this->actingAsRole('trainer');
        $this->postJson('/api/v1/admin/artikel', ['title' => 'X', 'status' => 'draft'])->assertStatus(403);
    }
}
