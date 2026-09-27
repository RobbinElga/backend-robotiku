<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_payments_accessible_without_token(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payments/bukti.jpg', 'isi-gambar-bukti');

        $res = $this->get('/api/v1/media/payments/bukti.jpg');
        $res->assertOk();
        $this->assertStringContainsString('inline', $res->headers->get('content-disposition'));
    }

    public function test_media_download_attachment(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payments/bukti.jpg', 'isi-gambar-bukti');

        $res = $this->get('/api/v1/media/payments/bukti.jpg?download=1');
        $res->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('content-disposition'));
    }

    public function test_sensitive_staff_folders_require_authentication(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('signatures/ttd.png', 'tanda-tangan');
        Storage::disk('local')->put('attendances/foto.jpg', 'foto-absen');
        Storage::disk('local')->put('settlements/setoran.jpg', 'bukti-setoran');
        Storage::disk('local')->put('school_notes/catatan.pdf', 'catatan-rahasia');

        // Unauthenticated -> 401
        $this->get('/api/v1/media/signatures/ttd.png')->assertStatus(401);
        $this->get('/api/v1/media/attendances/foto.jpg')->assertStatus(401);
        $this->get('/api/v1/media/settlements/setoran.jpg')->assertStatus(401);
        $this->get('/api/v1/media/school_notes/catatan.pdf')->assertStatus(401);
    }

    public function test_sensitive_staff_folders_accessible_with_token(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('signatures/ttd.png', 'tanda-tangan');

        $user = User::create([
            'name' => 'Trainer',
            'email' => 'trainer@r.id',
            'password' => bcrypt('x'),
            'role' => 'trainer',
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);

        $res = $this->get('/api/v1/media/signatures/ttd.png');
        $res->assertOk();
        $this->assertStringContainsString('inline', $res->headers->get('content-disposition'));
    }

    public function test_media_path_traversal_blocked(): void
    {
        $this->get('/api/v1/media/payments/../secret.txt')->assertStatus(404);
    }

    public function test_media_unauthorized_folder_blocked(): void
    {
        $this->get('/api/v1/media/database/seeders.php')->assertStatus(404);
    }

    public function test_public_media_stream(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('landing/banner.jpg', 'isi-banner');

        $res = $this->get('/api/v1/public-media/landing/banner.jpg');
        $res->assertOk();
        $this->assertStringContainsString('inline', $res->headers->get('content-disposition'));
    }
}
