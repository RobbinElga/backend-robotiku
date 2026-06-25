<?php

namespace Tests\Feature\Bayar;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentVerifyTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): User
    {
        $user = User::create([
            'name' => $role,
            'email' => $role . '-' . uniqid() . '@r.id',
            'password' => bcrypt('x'),
            'role' => $role,
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function makePayment(string $status = 'menunggu_verifikasi'): Payment
    {
        $parent = StudentParent::create(['name' => 'Budi', 'phone' => '081200000001']);
        $student = Student::create([
            'student_code' => 'ROBO-MDR001',
            'name' => 'Andi',
            'gender' => 'L',
            'parent_id' => $parent->id,
            'status' => 'aktif',
            'registration_type' => 'mandiri',
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-1',
            'student_id' => $student->id,
            'base_amount' => 200000,
            'total_amount' => 350000,
            'status' => 'menunggu_verifikasi',
        ]);
        return Payment::create([
            'invoice_id' => $invoice->id,
            'proof_file' => 'payments/dummy.pdf',
            'uploader_type' => 'parent',
            'uploader_id' => $parent->id,
            'status' => $status,
        ]);
    }

    public function test_approve_jadikan_lunas(): void
    {
        $this->actingAsRole('admin_keuangan');
        $payment = $this->makePayment();

        $this->postJson("/api/v1/bayar/payments/{$payment->id}/verify", ['action' => 'approve'])
            ->assertOk()->assertJsonPath('data.invoice_status', 'lunas');

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'diverifikasi']);
        $this->assertDatabaseHas('payment_status_logs', ['payment_id' => $payment->id, 'new_status' => 'diverifikasi']);
    }

    public function test_reject_butuh_alasan_dan_kembalikan_belum_bayar(): void
    {
        $this->actingAsRole('admin_keuangan');
        $payment = $this->makePayment();

        // tanpa notes → 422
        $this->postJson("/api/v1/bayar/payments/{$payment->id}/verify", ['action' => 'reject'])
            ->assertStatus(422);

        // dengan notes → ditolak
        $this->postJson("/api/v1/bayar/payments/{$payment->id}/verify", ['action' => 'reject', 'notes' => 'Bukti buram'])
            ->assertOk()->assertJsonPath('data.invoice_status', 'belum_bayar');

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'ditolak']);
    }

    public function test_lihat_bukti_stream(): void
    {
        Storage::fake('local');
        $this->actingAsRole('admin_keuangan');
        $payment = $this->makePayment();
        Storage::disk('local')->put($payment->proof_file, 'isi-file');

        $this->get("/api/v1/bayar/payments/{$payment->id}/proof")->assertOk();
    }

    public function test_wa_link_terbentuk(): void
    {
        Setting::create(['key' => 'wa_message_template', 'value' => 'Halo {nama_ortu}, tagihan {invoice} untuk {nama_anak} sebesar {total}.']);
        $this->actingAsRole('admin_keuangan');
        $payment = $this->makePayment();

        $res = $this->getJson("/api/v1/bayar/invoices/{$payment->invoice_id}/wa")->assertOk();
        $res->assertJsonPath('data.phone', '6281200000001');
        $this->assertStringContainsString('wa.me/6281200000001', $res->json('data.url'));
        $this->assertStringContainsString('INV-1', $res->json('data.message'));
    }

    public function test_trainer_tidak_boleh_verifikasi(): void
    {
        $this->actingAsRole('trainer');
        $payment = $this->makePayment();

        $this->postJson("/api/v1/bayar/payments/{$payment->id}/verify", ['action' => 'approve'])
            ->assertStatus(403);
    }
}
