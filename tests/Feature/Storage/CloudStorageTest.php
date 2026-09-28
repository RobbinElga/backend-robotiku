<?php

namespace Tests\Feature\Storage;

use App\Models\Invoice;
use App\Models\ParentUser;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\SchoolSettlement;
use App\Models\Student;
use App\Models\User;
use App\Support\ImageStorage;
use App\Support\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CloudStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_storage_store_webp_on_local_disk(): void
    {
        Storage::fake('local');
        Config::set('filesystems.default', 'local');

        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);
        $path = ImageStorage::storeWebp($file, 'attendances');

        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_image_storage_store_webp_on_cloud_bucket_storage(): void
    {
        Storage::fake('s3');
        Config::set('filesystems.default', 's3');

        $file = UploadedFile::fake()->image('banner.png', 120, 120);
        $path = ImageStorage::storeWebp($file, 'landing');

        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('s3')->assertExists($path);
    }

    public function test_media_storage_response_on_cloud_bucket_returns_presigned_url_redirect(): void
    {
        Storage::fake('s3');
        Config::set('filesystems.default', 's3');
        Storage::disk('s3')->put('payments/bukti_transfer.webp', 'binary-data');

        $response = $this->get('/api/v1/media/payments/bukti_transfer.webp');

        $response->assertStatus(302);
        $targetUrl = $response->headers->get('Location');
        $this->assertNotEmpty($targetUrl);
        $this->assertStringContainsString('bukti_transfer.webp', $targetUrl);
    }

    public function test_media_storage_response_json_format_on_cloud_bucket(): void
    {
        Storage::fake('s3');
        Config::set('filesystems.default', 's3');
        Storage::disk('s3')->put('payments/bukti_json.webp', 'binary-data');

        $response = $this->getJson('/api/v1/media/payments/bukti_json.webp?json=1');

        $response->assertOk();
        $response->assertJsonStructure(['url', 'expires_at']);
        $this->assertStringContainsString('bukti_json.webp', $response->json('url'));
    }

    public function test_media_storage_dual_fallback_serves_local_file_when_default_is_s3(): void
    {
        Storage::fake('s3');
        Storage::fake('local');
        Config::set('filesystems.default', 's3');

        // File hanya ada di disk lokal (file warisan sebelum migrasi S3)
        Storage::disk('local')->put('payments/legacy_proof.jpg', 'isi-bukti-lama');

        $response = $this->get('/api/v1/media/payments/legacy_proof.jpg');

        // Harus disajikan dengan 200 via stream lokal, bukan 404
        $response->assertOk();
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
        $this->assertSame('isi-bukti-lama', $response->streamedContent());
    }

    public function test_payment_verification_proof_redirects_to_presigned_url_on_s3(): void
    {
        Storage::fake('s3');
        Config::set('filesystems.default', 's3');

        $admin = User::create([
            'name' => 'Admin Keuangan',
            'email' => 'keuangan@r.id',
            'password' => bcrypt('password'),
            'role' => 'admin_keuangan',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        Storage::disk('s3')->put('payments/test_proof.jpg', 'image-bytes');

        $parent = \App\Models\StudentParent::create(['name' => 'Budi', 'phone' => '081200000001']);
        $student = Student::create([
            'student_code' => 'ROBO-TEST01',
            'name' => 'Murid Test',
            'gender' => 'L',
            'parent_id' => $parent->id,
            'status' => 'aktif',
            'registration_type' => 'mandiri',
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'student_id' => $student->id,
            'base_amount' => 100000,
            'total_amount' => 100000,
            'status' => 'menunggu_verifikasi',
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'proof_file' => 'payments/test_proof.jpg',
            'uploader_type' => 'parent',
            'uploader_id' => $parent->id,
            'status' => 'menunggu_verifikasi',
        ]);

        $response = $this->get("/api/v1/bayar/payments/{$payment->id}/proof");
        $response->assertStatus(302);
        $this->assertStringContainsString('test_proof.jpg', $response->headers->get('Location'));
    }

    public function test_school_settlement_proof_redirects_to_presigned_url_on_s3(): void
    {
        Storage::fake('s3');
        Config::set('filesystems.default', 's3');

        $financeAdmin = User::create([
            'name' => 'Staf Keuangan',
            'email' => 'staf@r.id',
            'password' => bcrypt('password'),
            'role' => 'admin_keuangan',
            'is_active' => true,
        ]);
        Sanctum::actingAs($financeAdmin);

        $school = School::create([
            'name' => 'SD Harapan Bangsa',
            'address' => 'Jl. Merdeka No. 1',
            'commission_percent' => 10,
            'pipeline_status' => 'sudah_mou',
        ]);

        Storage::disk('s3')->put('settlements/setoran_s3.png', 'fake-settlement');

        $settlement = SchoolSettlement::create([
            'school_id' => $school->id,
            'gross_amount' => 1000000,
            'commission_percent' => 10,
            'commission_amount' => 100000,
            'net_amount' => 900000,
            'proof_file' => 'settlements/setoran_s3.png',
            'status' => 'menunggu_verifikasi',
            'created_by' => null,
        ]);

        $response = $this->get("/api/v1/keuangan/setoran/{$settlement->id}/proof");
        $response->assertStatus(302);
        $this->assertStringContainsString('setoran_s3.png', $response->headers->get('Location'));
    }

    public function test_local_storage_returns_binary_file_response_for_http_range_support(): void
    {
        Storage::fake('local');
        Config::set('filesystems.default', 'local');

        Storage::disk('local')->put('payments/document.pdf', 'dummy-pdf-content');

        $response = $this->get('/api/v1/media/payments/document.pdf');
        $response->assertOk();
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response->baseResponse);
    }
}
