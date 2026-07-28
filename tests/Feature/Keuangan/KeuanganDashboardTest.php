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

class KeuanganDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): void
    {
        $u = User::create(['name' => $role, 'email' => $role.'-'.uniqid().'@r.id', 'password' => bcrypt('x'), 'role' => $role, 'is_active' => true]);
        Sanctum::actingAs($u);
    }

    public function test_dashboard_returns_all_keys(): void
    {
        $school = School::create(['name' => 'SDN 1', 'commission_percent' => 20]);
        $student = Student::create(['student_code' => 'S1', 'name' => 'A', 'gender' => 'L', 'status' => 'aktif', 'school_id' => $school->id, 'registration_type' => 'instansi']);
        $invoice = Invoice::create(['invoice_number' => 'INV-1', 'student_id' => $student->id, 'base_amount' => 200000, 'total_amount' => 200000, 'status' => 'lunas']);
        Payment::create(['invoice_id' => $invoice->id, 'status' => 'diverifikasi', 'verified_at' => now(), 'uploader_type' => 'school_admin', 'uploader_id' => 1]);

        $this->actingAsRole('admin_keuangan');
        $res = $this->getJson('/api/v1/keuangan/dashboard')->assertOk();

        $res->assertJsonStructure([
            'status', 'data' => [
                'pendapatan_bulan_ini',
                'pendapatan_bulan_ini_label',
                'pendapatan_tahun_ini',
                'tagihan_outstanding',
                'rata_rata_komisi',
                'sekolah_aktif',
                'menunggu_verifikasi',
                'menunggu_verifikasi_route',
                'pendapatan_per_bulan',
                'status_pembayaran_global',
                'tagihan_outstanding_per_sekolah',
                'komisi_per_sekolah',
                'setoran_terbaru',
            ],
        ]);

        $this->assertTrue($res->json('status'));
        $this->assertEquals(200000, $res->json('data.pendapatan_bulan_ini'));
        $this->assertEquals(200000, $res->json('data.pendapatan_tahun_ini'));
        $this->assertEquals('Pendapatan Bulan Ini', $res->json('data.pendapatan_bulan_ini_label'));
    }

    public function test_dashboard_with_period_filter(): void
    {
        $school = School::create(['name' => 'SDN 2', 'commission_percent' => 10]);
        $student = Student::create(['student_code' => 'S2', 'name' => 'B', 'gender' => 'L', 'status' => 'aktif', 'school_id' => $school->id, 'registration_type' => 'instansi']);

        $oldInvoice = Invoice::create(['invoice_number' => 'INV-OLD', 'student_id' => $student->id, 'base_amount' => 50000, 'total_amount' => 50000, 'status' => 'lunas']);
        Payment::create(['invoice_id' => $oldInvoice->id, 'status' => 'diverifikasi', 'verified_at' => now()->subMonths(6), 'uploader_type' => 'school_admin', 'uploader_id' => 1]);

        $this->actingAsRole('admin_keuangan');

        $res = $this->getJson('/api/v1/keuangan/dashboard?period=3_bulan')->assertOk();
        $this->assertEquals(0, $res->json('data.pendapatan_bulan_ini'));
        $this->assertEquals(50000, $res->json('data.pendapatan_tahun_ini'));
        $this->assertEquals('Pendapatan 3 Bulan Terakhir', $res->json('data.pendapatan_bulan_ini_label'));
    }

    public function test_dashboard_with_custom_date_range(): void
    {
        $school = School::create(['name' => 'SDN 3', 'commission_percent' => 15]);
        $student = Student::create(['student_code' => 'S3', 'name' => 'C', 'gender' => 'L', 'status' => 'aktif', 'school_id' => $school->id, 'registration_type' => 'instansi']);

        $inv = Invoice::create(['invoice_number' => 'INV-DATE', 'student_id' => $student->id, 'base_amount' => 100000, 'total_amount' => 100000, 'status' => 'lunas']);
        Payment::create(['invoice_id' => $inv->id, 'status' => 'diverifikasi', 'verified_at' => '2026-06-15 10:00:00', 'uploader_type' => 'school_admin', 'uploader_id' => 1]);

        $this->actingAsRole('admin_keuangan');

        $res = $this->getJson('/api/v1/keuangan/dashboard?start_date=2026-06-01&end_date=2026-06-30')->assertOk();
        $this->assertEquals(100000, $res->json('data.pendapatan_bulan_ini'));
        $this->assertEquals('Pendapatan 01 Jun 2026 - 30 Jun 2026', $res->json('data.pendapatan_bulan_ini_label'));
    }

    public function test_outstanding_invoice_counted(): void
    {
        $school = School::create(['name' => 'SDN 4', 'commission_percent' => 0]);
        $student = Student::create(['student_code' => 'S4', 'name' => 'D', 'gender' => 'L', 'status' => 'aktif', 'school_id' => $school->id, 'registration_type' => 'instansi']);
        Invoice::create(['invoice_number' => 'INV-UNPAID', 'student_id' => $student->id, 'base_amount' => 300000, 'total_amount' => 300000, 'status' => 'belum_bayar']);

        $this->actingAsRole('admin_keuangan');
        $res = $this->getJson('/api/v1/keuangan/dashboard')->assertOk();

        $this->assertEquals(300000, $res->json('data.tagihan_outstanding'));
        $this->assertEquals(1, $res->json('data.status_pembayaran_global.belum_bayar'));
    }

    public function test_menunggu_verifikasi_counted(): void
    {
        $school = School::create(['name' => 'SDN 5', 'commission_percent' => 5]);
        $student = Student::create(['student_code' => 'S5', 'name' => 'E', 'gender' => 'L', 'status' => 'aktif', 'school_id' => $school->id, 'registration_type' => 'instansi']);
        $inv = Invoice::create(['invoice_number' => 'INV-PEND', 'student_id' => $student->id, 'base_amount' => 150000, 'total_amount' => 150000, 'status' => 'menunggu_verifikasi']);
        Payment::create(['invoice_id' => $inv->id, 'status' => 'menunggu_verifikasi', 'uploader_type' => 'parent', 'uploader_id' => 1]);

        $this->actingAsRole('admin_keuangan');
        $res = $this->getJson('/api/v1/keuangan/dashboard')->assertOk();

        $this->assertEquals(1, $res->json('data.menunggu_verifikasi'));
        $this->assertEquals('/admin/keuangan/verifikasi', $res->json('data.menunggu_verifikasi_route'));
    }

    public function test_setoran_terbaru_returns_latest_5(): void
    {
        $school = School::create(['name' => 'SDN 6', 'commission_percent' => 10]);
        for ($i = 0; $i < 6; $i++) {
            SchoolSettlement::create([
                'school_id' => $school->id,
                'gross_amount' => 100000,
                'commission_percent' => 10,
                'commission_amount' => 10000,
                'net_amount' => 90000,
                'proof_file' => 'proof.jpg',
                'status' => 'diverifikasi',
            ]);
        }

        $this->actingAsRole('admin_keuangan');
        $res = $this->getJson('/api/v1/keuangan/dashboard')->assertOk();

        $this->assertCount(5, $res->json('data.setoran_terbaru'));
        $this->assertEquals('SDN 6', $res->json('data.setoran_terbaru.0.school_name'));
    }

    public function test_komisi_per_sekolah(): void
    {
        School::create(['name' => 'SMA 1', 'commission_percent' => 25]);
        School::create(['name' => 'SMA 2', 'commission_percent' => 30]);

        $this->actingAsRole('admin_keuangan');
        $res = $this->getJson('/api/v1/keuangan/dashboard')->assertOk();

        $komisi = $res->json('data.komisi_per_sekolah');
        $this->assertCount(2, $komisi);
        $this->assertEquals(27.5, $res->json('data.rata_rata_komisi'));
    }

    public function test_trend_endpoint(): void
    {
        $school = School::create(['name' => 'SDN 7', 'commission_percent' => 0]);
        $student = Student::create(['student_code' => 'S6', 'name' => 'F', 'gender' => 'L', 'status' => 'aktif', 'school_id' => $school->id, 'registration_type' => 'instansi']);

        $invCurrent = Invoice::create(['invoice_number' => 'INV-CUR', 'student_id' => $student->id, 'base_amount' => 200000, 'total_amount' => 200000, 'status' => 'lunas']);
        Payment::create(['invoice_id' => $invCurrent->id, 'status' => 'diverifikasi', 'verified_at' => now(), 'uploader_type' => 'school_admin', 'uploader_id' => 1]);

        $invPrev = Invoice::create(['invoice_number' => 'INV-PREV', 'student_id' => $student->id, 'base_amount' => 100000, 'total_amount' => 100000, 'status' => 'lunas']);
        Payment::create(['invoice_id' => $invPrev->id, 'status' => 'diverifikasi', 'verified_at' => now()->subMonth(), 'uploader_type' => 'school_admin', 'uploader_id' => 1]);

        $this->actingAsRole('admin_keuangan');
        $res = $this->getJson('/api/v1/keuangan/dashboard/trend')->assertOk();

        $this->assertTrue($res->json('status'));
        $trend = $res->json('data.pendapatan_bulan_ini');
        $this->assertArrayHasKey('current', $trend);
        $this->assertArrayHasKey('previous', $trend);
        $this->assertArrayHasKey('percent_change', $trend);
    }

    public function test_unauthorized_without_token(): void
    {
        $this->getJson('/api/v1/keuangan/dashboard')->assertStatus(401);
    }

    public function test_super_admin_can_access(): void
    {
        $this->actingAsRole('super_admin');
        $this->getJson('/api/v1/keuangan/dashboard')->assertOk();
    }

    public function test_admin_can_access(): void
    {
        $this->actingAsRole('admin');
        $this->getJson('/api/v1/keuangan/dashboard')->assertOk();
    }

    public function test_marketing_cannot_access(): void
    {
        $this->actingAsRole('marketing');
        $this->getJson('/api/v1/keuangan/dashboard')->assertStatus(403);
    }

    public function test_trainer_cannot_access(): void
    {
        $this->actingAsRole('trainer');
        $this->getJson('/api/v1/keuangan/dashboard')->assertStatus(403);
    }

    public function test_invalid_period_returns_422(): void
    {
        $this->actingAsRole('admin_keuangan');
        $this->getJson('/api/v1/keuangan/dashboard?period=invalid')->assertStatus(422);
    }

    public function test_tagihan_outstanding_per_sekolah(): void
    {
        $school = School::create(['name' => 'SDN TOP', 'commission_percent' => 10]);
        $student = Student::create(['student_code' => 'S7', 'name' => 'G', 'gender' => 'L', 'status' => 'aktif', 'school_id' => $school->id, 'registration_type' => 'instansi']);
        Invoice::create(['invoice_number' => 'INV-TOP', 'student_id' => $student->id, 'base_amount' => 500000, 'total_amount' => 500000, 'status' => 'belum_bayar']);

        $this->actingAsRole('admin_keuangan');
        $res = $this->getJson('/api/v1/keuangan/dashboard')->assertOk();

        $outstanding = $res->json('data.tagihan_outstanding_per_sekolah');
        $this->assertCount(1, $outstanding);
        $this->assertEquals('SDN TOP', $outstanding[0]['school_name']);
        $this->assertEquals(500000, $outstanding[0]['total']);
    }
}
