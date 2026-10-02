<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\Kelas;
use App\Models\Mou;
use App\Models\Program;
use App\Models\School;
use App\Models\Session;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use App\Services\BillingCycleService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchoolPaymentSchemesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('s3');
    }

    private function seedFinanceAdmin(): User
    {
        return User::create([
            'name'      => 'Admin Keuangan',
            'email'     => 'finance@robotiku.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin_keuangan',
            'is_active' => true,
        ]);
    }

    private function createStudentWithSchool(string $scheme, array $schoolAttributes = []): array
    {
        $school = School::create(array_merge([
            'name'            => 'Sekolah ' . strtoupper($scheme),
            'pipeline_status' => 'sudah_mou',
            'is_mou'          => true,
            'payment_scheme'  => $scheme,
            'bank_account'    => $scheme === School::SCHEME_V2_SCHOOL ? 'BCA 1234567890 an Sekolah Mitra' : null,
            'qris_image'      => $scheme === School::SCHEME_V2_SCHOOL ? 'schools/qris_sample.png' : null,
            'price_per_cycle' => 250000,
        ], $schoolAttributes));

        $parent = StudentParent::create([
            'name'  => 'Wali ' . $school->name,
            'phone' => '0812' . rand(10000000, 99999999),
        ]);

        $student = Student::create([
            'student_code'      => 'ROBO-' . rand(1000, 9999),
            'name'              => 'Siswa ' . $school->name,
            'gender'            => 'L',
            'parent_id'         => $parent->id,
            'school_id'         => $school->id,
            'status'            => 'aktif',
            'registration_type' => 'instansi',
        ]);

        return [$school, $parent, $student];
    }

    public function test_model_helpers_and_backward_compatibility(): void
    {
        $v1 = School::create(['name' => 'SD V1', 'payment_scheme' => School::SCHEME_V1_DIRECT]);
        $this->assertTrue($v1->isV1());
        $this->assertFalse($v1->isV2());
        $this->assertFalse($v1->isV3());
        $this->assertTrue($v1->requiresParentPayment());
        $this->assertFalse($v1->self_managed);

        $v2 = School::create(['name' => 'SD V2', 'payment_scheme' => School::SCHEME_V2_SCHOOL]);
        $this->assertFalse($v2->isV1());
        $this->assertTrue($v2->isV2());
        $this->assertFalse($v2->isV3());
        $this->assertTrue($v2->requiresParentPayment());
        $this->assertFalse($v2->self_managed);

        $v3 = School::create(['name' => 'SD V3', 'payment_scheme' => School::SCHEME_V3_COLLECTIVE]);
        $this->assertFalse($v3->isV1());
        $this->assertFalse($v3->isV2());
        $this->assertTrue($v3->isV3());
        $this->assertFalse($v3->requiresParentPayment());
        $this->assertTrue($v3->self_managed);

        // Setter synchronization from legacy self_managed attribute
        $legacy = School::create(['name' => 'SD Legacy', 'self_managed' => true]);
        $this->assertSame(School::SCHEME_V3_COLLECTIVE, $legacy->payment_scheme);
        $this->assertTrue($legacy->isV3());

        $legacy->self_managed = false;
        $this->assertSame(School::SCHEME_V1_DIRECT, $legacy->payment_scheme);
        $this->assertTrue($legacy->isV1());

        // Same behavior on Mou model
        $mou = Mou::create([
            'school_id'      => $v1->id,
            'file'           => 'mou/test.pdf',
            'periods'        => 6,
            'payment_scheme' => Mou::SCHEME_V2_SCHOOL,
        ]);
        $this->assertTrue($mou->isV2());
        $this->assertFalse($mou->self_managed);

        $mou->self_managed = true;
        $this->assertSame(Mou::SCHEME_V3_COLLECTIVE, $mou->payment_scheme);
        $this->assertTrue($mou->isV3());
    }

    public function test_parent_dashboard_branches_per_scheme(): void
    {
        BankAccount::create([
            'bank_name'      => 'BCA Robotiku',
            'account_number' => '1112223334',
            'account_holder' => 'PT Robotiku Indonesia',
            'is_active'      => true,
        ]);

        // 1. Skema V1 Direct: Tagihan ada, info rekening Robotiku
        [$s1, $p1, $st1] = $this->createStudentWithSchool(School::SCHEME_V1_DIRECT);
        Invoice::create([
            'invoice_number' => 'INV-V1-001',
            'student_id'     => $st1->id,
            'base_amount'    => 200000,
            'total_amount'   => 200000,
            'status'         => 'belum_bayar',
            'due_date'       => now()->addDays(7),
        ]);

        $resV1 = $this->postJson('/api/v1/ortu/dashboard', ['student_id' => $st1->id])->assertOk();
        $resV1->assertJsonPath('data.payment_scheme', 'v1_direct');
        $resV1->assertJsonPath('data.self_managed', false);
        $resV1->assertJsonPath('data.payment_info.scheme', 'v1_direct');
        $resV1->assertJsonPath('data.payment_info.type', 'direct_robotiku');
        $resV1->assertJsonCount(1, 'data.payment_info.bank_accounts');
        $resV1->assertJsonCount(1, 'data.invoices');
        $resV1->assertJsonPath('data.kpi.tagihan_belum', 1);

        // 2. Skema V2 School: Tagihan ada, info rekening & QRIS sekolah
        [$s2, $p2, $st2] = $this->createStudentWithSchool(School::SCHEME_V2_SCHOOL);
        Invoice::create([
            'invoice_number' => 'INV-V2-001',
            'student_id'     => $st2->id,
            'base_amount'    => 250000,
            'total_amount'   => 250000,
            'status'         => 'belum_bayar',
            'due_date'       => now()->addDays(7),
        ]);

        $resV2 = $this->postJson('/api/v1/ortu/dashboard', ['student_id' => $st2->id])->assertOk();
        $resV2->assertJsonPath('data.payment_scheme', 'v2_school');
        $resV2->assertJsonPath('data.self_managed', false);
        $resV2->assertJsonPath('data.payment_info.scheme', 'v2_school');
        $resV2->assertJsonPath('data.payment_info.type', 'school_managed');
        $resV2->assertJsonPath('data.payment_info.bank_account', 'BCA 1234567890 an Sekolah Mitra');
        $resV2->assertJsonCount(1, 'data.invoices');
        $resV2->assertJsonPath('data.kpi.tagihan_belum', 1);

        // 3. Skema V3 Collective: Tagihan kosong, banner kolektif tampil, tagihan_belum = 0
        [$s3, $p3, $st3] = $this->createStudentWithSchool(School::SCHEME_V3_COLLECTIVE);
        Invoice::create([
            'invoice_number' => 'INV-V3-001',
            'student_id'     => $st3->id,
            'base_amount'    => 300000,
            'total_amount'   => 300000,
            'status'         => 'lunas',
            'due_date'       => now()->addDays(7),
        ]);

        $resV3 = $this->postJson('/api/v1/ortu/dashboard', ['student_id' => $st3->id])->assertOk();
        $resV3->assertJsonPath('data.payment_scheme', 'v3_collective');
        $resV3->assertJsonPath('data.self_managed', true);
        $resV3->assertJsonPath('data.payment_info.scheme', 'v3_collective');
        $resV3->assertJsonPath('data.payment_info.type', 'collective');
        $resV3->assertJsonCount(0, 'data.invoices');
        $resV3->assertJsonPath('data.kpi.tagihan_belum', 0);
        $this->assertStringContainsString('Pembiayaan ekstrakurikuler dikelola langsung secara kolektif', $resV3->json('data.payment_info.banner'));
    }

    public function test_payment_rejection_under_v3_and_acceptance_under_v1_v2(): void
    {
        $this->seedFinanceAdmin();

        // V3: Pembayaran ditolak pada /ortu/bayar dan /bayar/upload
        [$s3, $p3, $st3] = $this->createStudentWithSchool(School::SCHEME_V3_COLLECTIVE);
        $invV3 = Invoice::create([
            'invoice_number' => 'INV-V3-BLOCK',
            'student_id'     => $st3->id,
            'base_amount'    => 100000,
            'total_amount'   => 100000,
            'status'         => 'belum_bayar',
        ]);

        $this->postJson('/api/v1/ortu/bayar', [
            'invoice_id' => $invV3->id,
            'proof'      => UploadedFile::fake()->create('proof.pdf', 100),
        ])->assertStatus(422)
          ->assertJsonPath('message', 'Pembayaran untuk sekolah ini dikelola langsung secara kolektif oleh pihak sekolah.');

        $this->postJson('/api/v1/bayar/upload', [
            'invoice_id' => $invV3->id,
            'phone'      => $p3->phone,
            'file'       => UploadedFile::fake()->create('proof.pdf', 100),
        ])->assertStatus(422)
          ->assertJsonPath('message', 'Pembayaran untuk sekolah ini dikelola langsung secara kolektif oleh pihak sekolah.');

        // V2: Pembayaran diterima pada /ortu/bayar dan /bayar/upload
        [$s2, $p2, $st2] = $this->createStudentWithSchool(School::SCHEME_V2_SCHOOL);
        $invV2 = Invoice::create([
            'invoice_number' => 'INV-V2-ALLOW',
            'student_id'     => $st2->id,
            'base_amount'    => 100000,
            'total_amount'   => 100000,
            'status'         => 'belum_bayar',
        ]);

        $this->postJson('/api/v1/ortu/bayar', [
            'invoice_id' => $invV2->id,
            'proof'      => UploadedFile::fake()->create('proof.pdf', 100),
        ])->assertOk();
        $this->assertDatabaseHas('invoices', ['id' => $invV2->id, 'status' => 'menunggu_verifikasi']);

        // V1: Pembayaran diterima pada /bayar/upload
        [$s1, $p1, $st1] = $this->createStudentWithSchool(School::SCHEME_V1_DIRECT);
        $invV1 = Invoice::create([
            'invoice_number' => 'INV-V1-ALLOW',
            'student_id'     => $st1->id,
            'base_amount'    => 100000,
            'total_amount'   => 100000,
            'status'         => 'belum_bayar',
        ]);

        $this->postJson('/api/v1/bayar/upload', [
            'invoice_id' => $invV1->id,
            'phone'      => $p1->phone,
            'file'       => UploadedFile::fake()->create('proof.pdf', 100),
        ])->assertStatus(201);
        $this->assertDatabaseHas('invoices', ['id' => $invV1->id, 'status' => 'menunggu_verifikasi']);
    }

    public function test_billing_cycle_creates_lunas_for_v3_and_belum_bayar_for_v1_v2(): void
    {
        $this->seedFinanceAdmin();
        $billingService = app(BillingCycleService::class);

        $trainer = User::create([
            'name'      => 'Trainer 1',
            'email'     => 'tr1@robotiku.com',
            'password'  => bcrypt('password'),
            'role'      => 'trainer',
            'is_active' => true,
        ]);
        $program = Program::create([
            'name'            => 'Robo Program',
            'level'           => 'beginner',
            'price_per_cycle' => 200000,
            'is_active'       => true,
            'is_visible'      => true,
        ]);

        // Kelas dengan meetings_per_period = 2
        $kelas = Kelas::create([
            'name'                => 'Kelas Robotika',
            'trainer_id'          => $trainer->id,
            'program_id'          => $program->id,
            'meetings_per_period' => 2,
        ]);

        [$sV3, , $stV3] = $this->createStudentWithSchool(School::SCHEME_V3_COLLECTIVE);
        [$sV2, , $stV2] = $this->createStudentWithSchool(School::SCHEME_V2_SCHOOL);
        [$sV1, , $stV1] = $this->createStudentWithSchool(School::SCHEME_V1_DIRECT);

        $kelas->students()->attach([$stV3->id, $stV2->id, $stV1->id]);

        // Simulasikan 2 sesi selesai (ended)
        Session::create([
            'class_id'   => $kelas->id,
            'week'       => 1,
            'date'       => now()->toDateString(),
            'status'     => 'ended',
            'trainer_id' => $trainer->id,
            'started_at' => now()->subHours(3),
            'ended_at'   => now()->subHours(2),
        ]);
        $session2 = Session::create([
            'class_id'   => $kelas->id,
            'week'       => 2,
            'date'       => now()->toDateString(),
            'status'     => 'ended',
            'trainer_id' => $trainer->id,
            'started_at' => now()->subHours(1),
            'ended_at'   => now(),
        ]);

        $invoices = $billingService->triggerPeriodCompletionForClass($kelas, $session2);

        $this->assertCount(3, $invoices);

        // V3 invoice langsung 'lunas'
        $invV3 = Invoice::where('student_id', $stV3->id)->first();
        $this->assertNotNull($invV3);
        $this->assertSame('lunas', $invV3->status);

        // V2 invoice 'belum_bayar'
        $invV2 = Invoice::where('student_id', $stV2->id)->first();
        $this->assertNotNull($invV2);
        $this->assertSame('belum_bayar', $invV2->status);

        // V1 invoice 'belum_bayar'
        $invV1 = Invoice::where('student_id', $stV1->id)->first();
        $this->assertNotNull($invV1);
        $this->assertSame('belum_bayar', $invV1->status);

        // Hanya 2 notifikasi (untuk V1 dan V2, V3 dilewati)
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_daftar_instansi_public_registration_blocked_for_v3_allowed_for_v1_v2(): void
    {
        $regService = app(RegistrationService::class);

        $program = Program::create([
            'name'            => 'Program Coding',
            'level'           => 'intermediate',
            'price_per_cycle' => 200000,
            'is_active'       => true,
            'is_visible'      => true,
        ]);

        [$sV3] = $this->createStudentWithSchool(School::SCHEME_V3_COLLECTIVE);
        [$sV2] = $this->createStudentWithSchool(School::SCHEME_V2_SCHOOL);

        // Public registration endpoint blocks V3
        $this->postJson('/api/v1/daftar/instansi', [
            'school_id'   => $sV3->id,
            'program_id'  => $program->id,
            'name'        => 'Anak Daftar V3',
            'birth_date'  => '2015-05-05',
            'gender'      => 'L',
            'phone'       => '081299990001',
            'parent_name' => 'Ayah V3',
        ])->assertStatus(422)
          ->assertJsonPath('message', 'Pendaftaran untuk sekolah ini dilakukan langsung melalui pihak sekolah, bukan melalui sistem.');

        // Public registration endpoint accepts V2
        $this->postJson('/api/v1/daftar/instansi', [
            'school_id'   => $sV2->id,
            'program_id'  => $program->id,
            'name'        => 'Anak Daftar V2',
            'birth_date'  => '2015-05-05',
            'gender'      => 'L',
            'phone'       => '081299990002',
            'parent_name' => 'Ayah V2',
        ])->assertStatus(201);

        // Internal service registerInstansi creates verified student and lunas invoice for V3
        $resV3 = $regService->registerInstansi([
            'name'        => 'Anak Internal V3',
            'birth_date'  => '2016-01-01',
            'gender'      => 'P',
        ], $sV3->id);

        $this->assertTrue((bool) $resV3['student']->is_verified);
        $this->assertSame('lunas', $resV3['invoice']->status);
    }

    public function test_parent_tagihan_endpoint_returns_empty_invoices_for_v3_and_active_for_v1_v2(): void
    {
        [$sV3, $pV3, $stV3] = $this->createStudentWithSchool(School::SCHEME_V3_COLLECTIVE);
        Invoice::create([
            'invoice_number' => 'INV-TAG-V3',
            'student_id'     => $stV3->id,
            'base_amount'    => 100000,
            'total_amount'   => 100000,
            'status'         => 'lunas',
        ]);

        $resV3 = $this->postJson('/api/v1/bayar/tagihan', [
            'student_id' => $stV3->id,
            'phone'      => $pV3->phone,
        ])->assertOk();
        $resV3->assertJsonCount(0, 'data.invoices');
        $resV3->assertJsonPath('data.payment_info.scheme', 'v3_collective');

        [$sV2, $pV2, $stV2] = $this->createStudentWithSchool(School::SCHEME_V2_SCHOOL);
        Invoice::create([
            'invoice_number' => 'INV-TAG-V2',
            'student_id'     => $stV2->id,
            'base_amount'    => 200000,
            'total_amount'   => 200000,
            'status'         => 'belum_bayar',
        ]);

        $resV2 = $this->postJson('/api/v1/bayar/tagihan', [
            'student_id' => $stV2->id,
            'phone'      => $pV2->phone,
        ])->assertOk();
        $resV2->assertJsonCount(1, 'data.invoices');
        $resV2->assertJsonPath('data.payment_info.scheme', 'v2_school');
        $resV2->assertJsonPath('data.payment_info.bank_account', 'BCA 1234567890 an Sekolah Mitra');
    }
}
