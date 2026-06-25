<?php

namespace Tests\Feature\Bayar;

use App\Models\Invoice;
use App\Models\SchoolAdmin;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UploadBuktiTest extends TestCase
{
    use RefreshDatabase;

    private function seedFinance(): void
    {
        User::create(['name' => 'AK', 'email' => 'ak@r.id', 'password' => bcrypt('x'), 'role' => 'admin_keuangan', 'is_active' => true]);
    }

    private function makeMandiriInvoice(string $phone = '081200000001'): Invoice
    {
        $parent = StudentParent::create(['name' => 'Budi', 'phone' => $phone]);
        $student = Student::create([
            'student_code' => 'ROBO-MDR001',
            'name' => 'Andi',
            'gender' => 'L',
            'parent_id' => $parent->id,
            'status' => 'aktif',
            'registration_type' => 'mandiri',
        ]);
        return Invoice::create([
            'invoice_number' => 'INV-1',
            'student_id' => $student->id,
            'base_amount' => 200000,
            'total_amount' => 350000,
            'status' => 'belum_bayar',
        ]);
    }

    public function test_ortu_upload_bukti(): void
    {
        Storage::fake('local');
        $this->seedFinance();
        $invoice = $this->makeMandiriInvoice();

        $this->postJson('/api/v1/bayar/upload', [
            'invoice_id' => $invoice->id,
            'phone'      => '6281200000001', // format beda, harus tetap cocok
            'file'       => UploadedFile::fake()->create('bukti.pdf', 200, 'application/pdf'),
        ])->assertStatus(201);

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'menunggu_verifikasi']);
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'uploader_type' => 'parent']);
        $this->assertDatabaseHas('payment_status_logs', ['payment_id' => 1, 'new_status' => 'menunggu_verifikasi']);
        $this->assertDatabaseCount('notifications', 1); // AK
    }

    public function test_ortu_hp_salah_ditolak(): void
    {
        Storage::fake('local');
        $invoice = $this->makeMandiriInvoice();

        $this->postJson('/api/v1/bayar/upload', [
            'invoice_id' => $invoice->id,
            'phone' => '089999999999',
            'file' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ])->assertStatus(403);
    }

    public function test_ortu_lihat_tagihan(): void
    {
        $invoice = $this->makeMandiriInvoice();

        $this->postJson('/api/v1/bayar/tagihan', [
            'student_id' => $invoice->student_id,
            'phone' => '081200000001',
        ])->assertOk()->assertJsonCount(1, 'data.invoices');
    }

    public function test_admin_sekolah_upload_untuk_muridnya(): void
    {
        Storage::fake('local');
        $this->seedFinance();

        $school = School::create(['name' => 'SD A', 'pipeline_status' => 'sudah_mou', 'is_mou' => true]);
        $student = Student::create([
            'student_code' => 'ROBO-INS001',
            'name' => 'Murid',
            'gender' => 'P',
            'school_id' => $school->id,
            'status' => 'aktif',
            'registration_type' => 'instansi',
        ]);
        $invoice = Invoice::create(['invoice_number' => 'INV-2', 'student_id' => $student->id, 'base_amount' => 200000, 'total_amount' => 300000, 'status' => 'belum_bayar']);

        $admin = SchoolAdmin::create(['school_id' => $school->id, 'name' => 'AS', 'email' => 'as@a.id', 'password' => bcrypt('x'), 'is_active' => true]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/bayar/sekolah/invoices/{$invoice->id}/upload", [
            'file' => UploadedFile::fake()->image('bukti.png'),
        ])->assertStatus(201);

        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'uploader_type' => 'school_admin']);
    }
}
