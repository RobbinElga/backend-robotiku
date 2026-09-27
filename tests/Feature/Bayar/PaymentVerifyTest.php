<?php

namespace Tests\Feature\Bayar;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolAdmin;
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

    private function actingAsSchoolAdmin(School $school): SchoolAdmin
    {
        $admin = SchoolAdmin::create([
            'school_id' => $school->id,
            'name'      => 'Admin Sekolah',
            'email'     => 'admin-' . uniqid() . '@sekolah.id',
            'phone'     => '08' . rand(1000000000, 9999999999),
            'password'  => bcrypt('x'),
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);
        return $admin;
    }

    private function makeSchoolPayment(School $school): Payment
    {
        $parent = StudentParent::create(['name' => 'Ortu Sekolah', 'phone' => '081299998888']);
        $student = Student::create([
            'student_code' => 'ROBO-SCH-' . uniqid(),
            'name' => 'Siswa Sekolah',
            'gender' => 'L',
            'school_id' => $school->id,
            'parent_id' => $parent->id,
            'status' => 'aktif',
            'registration_type' => 'instansi',
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-SCH-' . uniqid(),
            'student_id' => $student->id,
            'base_amount' => 200000,
            'total_amount' => 200000,
            'status' => 'menunggu_verifikasi',
        ]);
        return Payment::create([
            'invoice_id' => $invoice->id,
            'proof_file' => 'payments/bukti-sekolah.jpg',
            'uploader_type' => 'parent',
            'uploader_id' => $parent->id,
            'status' => 'menunggu_verifikasi',
        ]);
    }

    public function test_lihat_bukti_stream(): void
    {
        Storage::fake('local');
        $this->actingAsRole('admin_keuangan');
        $payment = $this->makePayment();
        Storage::disk('local')->put($payment->proof_file, 'isi-file');

        $res = $this->get("/api/v1/bayar/payments/{$payment->id}/proof");
        $res->assertOk();
        $this->assertStringContainsString('inline', $res->headers->get('content-disposition'));

        $resDownload = $this->get("/api/v1/bayar/payments/{$payment->id}/proof?download=1");
        $resDownload->assertOk();
        $this->assertStringContainsString('attachment', $resDownload->headers->get('content-disposition'));
    }

    public function test_lihat_bukti_404_jika_file_hilang(): void
    {
        Storage::fake('local');
        $this->actingAsRole('admin_keuangan');
        $payment = $this->makePayment();

        $this->get("/api/v1/bayar/payments/{$payment->id}/proof")->assertStatus(404);
    }

    public function test_admin_sekolah_lihat_bukti_pembayaran_murid_sendiri(): void
    {
        Storage::fake('local');
        $school = School::create(['name' => 'SD Negeri 01']);
        $this->actingAsSchoolAdmin($school);

        $payment = $this->makeSchoolPayment($school);
        Storage::disk('local')->put($payment->proof_file, 'isi-file-sekolah');

        // endpoint bahasa indonesia
        $res1 = $this->get("/api/v1/sekolah/pembayaran/{$payment->id}/proof");
        $res1->assertOk();
        $this->assertStringContainsString('inline', $res1->headers->get('content-disposition'));

        // endpoint bahasa inggris (alias)
        $res2 = $this->get("/api/v1/school/payments/{$payment->id}/proof");
        $res2->assertOk();
        $this->assertStringContainsString('inline', $res2->headers->get('content-disposition'));

        // download option
        $resDownload = $this->get("/api/v1/sekolah/pembayaran/{$payment->id}/proof?download=1");
        $resDownload->assertOk();
        $this->assertStringContainsString('attachment', $resDownload->headers->get('content-disposition'));
    }

    public function test_admin_sekolah_tidak_bisa_lihat_bukti_sekolah_lain(): void
    {
        Storage::fake('local');
        $schoolA = School::create(['name' => 'SD Negeri 01']);
        $schoolB = School::create(['name' => 'SD Negeri 02']);

        $paymentSchoolA = $this->makeSchoolPayment($schoolA);
        Storage::disk('local')->put($paymentSchoolA->proof_file, 'isi-file-sekolah-a');

        // Login as School B admin
        $this->actingAsSchoolAdmin($schoolB);

        $this->get("/api/v1/sekolah/pembayaran/{$paymentSchoolA->id}/proof")->assertStatus(403);
        $this->get("/api/v1/school/payments/{$paymentSchoolA->id}/proof")->assertStatus(403);
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
