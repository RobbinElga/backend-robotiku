<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaTest extends TestCase
{
    public function test_media_stream_inline(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payments/bukti.jpg', 'isi-gambar');

        $res = $this->get('/api/v1/media/payments/bukti.jpg');
        $res->assertOk();
        $this->assertStringContainsString('inline', $res->headers->get('content-disposition'));
    }

    public function test_media_download_attachment(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payments/bukti.jpg', 'isi-gambar');

        $res = $this->get('/api/v1/media/payments/bukti.jpg?download=1');
        $res->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('content-disposition'));
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
