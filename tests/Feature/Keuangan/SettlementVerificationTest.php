<?php

namespace Tests\Feature\Keuangan;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolSettlement;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettlementVerificationTest extends TestCase
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

    public function test_admin_keuangan_can_approve_settlement_and_invoices_become_lunas(): void
    {
        $admin = $this->actingAsRole('admin_keuangan');

        $school = School::create(['name' => 'SDN Cipta', 'commission_percent' => 20]);
        $student = Student::create([
            'student_code' => 'STU-001',
            'name' => 'Ahmad',
            'gender' => 'L',
            'status' => 'aktif',
            'is_verified' => false,
            'school_id' => $school->id,
            'registration_type' => 'instansi',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'student_id' => $student->id,
            'base_amount' => 250000,
            'total_amount' => 250000,
            'status' => 'belum_bayar',
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'status' => 'menunggu_verifikasi',
            'uploader_type' => 'parent',
            'uploader_id' => 1,
        ]);

        $settlement = SchoolSettlement::create([
            'school_id' => $school->id,
            'gross_amount' => 250000,
            'commission_percent' => 20,
            'commission_amount' => 50000,
            'net_amount' => 200000,
            'proof_file' => 'settlements/test-proof.jpg',
            'status' => 'menunggu_verifikasi',
        ]);
        $settlement->invoices()->attach($invoice->id);

        $res = $this->postJson("/api/v1/keuangan/setoran/{$settlement->id}/verifikasi", [
            'action' => 'approve',
            'note' => 'Setoran valid dan dana sudah masuk.',
        ]);

        $res->assertOk();
        $res->assertJson([
            'status' => true,
            'message' => 'Setoran diproses.',
        ]);

        $settlement->refresh();
        $this->assertEquals('diverifikasi', $settlement->status);
        $this->assertEquals($admin->id, $settlement->verified_by);
        $this->assertNotNull($settlement->verified_at);
        $this->assertEquals('Setoran valid dan dana sudah masuk.', $settlement->note);

        $invoice->refresh();
        $this->assertEquals('lunas', $invoice->status);

        $student->refresh();
        $this->assertTrue((bool) $student->is_verified);

        $payment->refresh();
        $this->assertEquals('diverifikasi', $payment->status);
        $this->assertEquals($admin->id, $payment->verified_by);
        $this->assertNotNull($payment->verified_at);
    }

    public function test_admin_keuangan_can_reject_settlement(): void
    {
        $admin = $this->actingAsRole('admin_keuangan');

        $school = School::create(['name' => 'SDN Merdeka', 'commission_percent' => 15]);
        $student = Student::create([
            'student_code' => 'STU-002',
            'name' => 'Budi',
            'gender' => 'L',
            'status' => 'aktif',
            'is_verified' => false,
            'school_id' => $school->id,
            'registration_type' => 'instansi',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-002',
            'student_id' => $student->id,
            'base_amount' => 200000,
            'total_amount' => 200000,
            'status' => 'belum_bayar',
        ]);

        $settlement = SchoolSettlement::create([
            'school_id' => $school->id,
            'gross_amount' => 200000,
            'commission_percent' => 15,
            'commission_amount' => 30000,
            'net_amount' => 170000,
            'proof_file' => 'settlements/test-proof-2.jpg',
            'status' => 'menunggu_verifikasi',
        ]);
        $settlement->invoices()->attach($invoice->id);

        $res = $this->postJson("/api/v1/keuangan/setoran/{$settlement->id}/verifikasi", [
            'action' => 'reject',
            'note' => 'Nominal transfer tidak sesuai bukti transfer.',
        ]);

        $res->assertOk();

        $settlement->refresh();
        $this->assertEquals('ditolak', $settlement->status);
        $this->assertEquals($admin->id, $settlement->verified_by);
        $this->assertEquals('Nominal transfer tidak sesuai bukti transfer.', $settlement->note);

        $invoice->refresh();
        $this->assertEquals('belum_bayar', $invoice->status);
    }

    public function test_non_finance_user_cannot_verify_settlement(): void
    {
        $this->actingAsRole('marketing');

        $school = School::create(['name' => 'SDN Harapan', 'commission_percent' => 10]);
        $settlement = SchoolSettlement::create([
            'school_id' => $school->id,
            'gross_amount' => 100000,
            'commission_percent' => 10,
            'commission_amount' => 10000,
            'net_amount' => 90000,
            'proof_file' => 'settlements/test-proof.jpg',
            'status' => 'menunggu_verifikasi',
        ]);

        $this->postJson("/api/v1/keuangan/setoran/{$settlement->id}/verifikasi", [
            'action' => 'approve',
        ])->assertStatus(403);
    }

    public function test_validation_requires_valid_action(): void
    {
        $this->actingAsRole('admin_keuangan');

        $school = School::create(['name' => 'SDN Harapan', 'commission_percent' => 10]);
        $settlement = SchoolSettlement::create([
            'school_id' => $school->id,
            'gross_amount' => 100000,
            'commission_percent' => 10,
            'commission_amount' => 10000,
            'net_amount' => 90000,
            'proof_file' => 'settlements/test-proof.jpg',
            'status' => 'menunggu_verifikasi',
        ]);

        $this->postJson("/api/v1/keuangan/setoran/{$settlement->id}/verifikasi", [
            'action' => 'invalid_action',
        ])->assertStatus(422);
    }

    public function test_already_processed_settlement_cannot_be_verified_again(): void
    {
        $this->actingAsRole('admin_keuangan');

        $school = School::create(['name' => 'SDN Harapan', 'commission_percent' => 10]);
        $settlement = SchoolSettlement::create([
            'school_id' => $school->id,
            'gross_amount' => 100000,
            'commission_percent' => 10,
            'commission_amount' => 10000,
            'net_amount' => 90000,
            'proof_file' => 'settlements/test-proof.jpg',
            'status' => 'diverifikasi',
        ]);

        $res = $this->postJson("/api/v1/keuangan/setoran/{$settlement->id}/verifikasi", [
            'action' => 'approve',
        ]);

        $res->assertStatus(422);
        $res->assertJson([
            'status' => false,
            'message' => 'Setoran sudah diproses sebelumnya.',
        ]);
    }
}
