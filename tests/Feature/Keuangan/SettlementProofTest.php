<?php

namespace Tests\Feature\Keuangan;

use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\SchoolSettlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettlementProofTest extends TestCase
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

    private function makeSettlement(School $school): SchoolSettlement
    {
        return SchoolSettlement::create([
            'school_id' => $school->id,
            'gross_amount' => 500000,
            'commission_percent' => 20,
            'commission_amount' => 100000,
            'net_amount' => 400000,
            'proof_file' => 'settlements/test-proof.jpg',
            'status' => 'menunggu_verifikasi',
        ]);
    }

    public function test_admin_keuangan_lihat_bukti_setoran_stream(): void
    {
        Storage::fake('local');
        $this->actingAsRole('admin_keuangan');

        $school = School::create(['name' => 'SD Harapan']);
        $settlement = $this->makeSettlement($school);
        Storage::disk('local')->put($settlement->proof_file, 'isi-bukti-setoran');

        $res = $this->get("/api/v1/keuangan/setoran/{$settlement->id}/proof");
        $res->assertOk();
        $this->assertStringContainsString('inline', $res->headers->get('content-disposition'));
    }

    public function test_admin_keuangan_download_bukti_setoran(): void
    {
        Storage::fake('local');
        $this->actingAsRole('admin_keuangan');

        $school = School::create(['name' => 'SD Harapan']);
        $settlement = $this->makeSettlement($school);
        Storage::disk('local')->put($settlement->proof_file, 'isi-bukti-setoran');

        $res = $this->get("/api/v1/keuangan/setoran/{$settlement->id}/proof?download=1");
        $res->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('content-disposition'));
    }

    public function test_bukti_setoran_404_jika_file_hilang(): void
    {
        Storage::fake('local');
        $this->actingAsRole('admin_keuangan');

        $school = School::create(['name' => 'SD Harapan']);
        $settlement = $this->makeSettlement($school);

        $this->get("/api/v1/keuangan/setoran/{$settlement->id}/proof")->assertStatus(404);
    }

    public function test_admin_sekolah_lihat_bukti_setoran_sendiri(): void
    {
        Storage::fake('local');
        $school = School::create(['name' => 'SD Harapan']);
        $this->actingAsSchoolAdmin($school);

        $settlement = $this->makeSettlement($school);
        Storage::disk('local')->put($settlement->proof_file, 'isi-bukti-setoran');

        $res = $this->get("/api/v1/sekolah/setoran/{$settlement->id}/proof");
        $res->assertOk();
        $this->assertStringContainsString('inline', $res->headers->get('content-disposition'));
    }

    public function test_admin_sekolah_tidak_bisa_lihat_bukti_setoran_sekolah_lain(): void
    {
        Storage::fake('local');
        $schoolA = School::create(['name' => 'SD Harapan']);
        $schoolB = School::create(['name' => 'SD Bintang']);

        $settlementA = $this->makeSettlement($schoolA);
        Storage::disk('local')->put($settlementA->proof_file, 'isi-bukti-setoran-a');

        $this->actingAsSchoolAdmin($schoolB);

        $this->get("/api/v1/sekolah/setoran/{$settlementA->id}/proof")->assertStatus(403);
    }

    public function test_trainer_tidak_bisa_akses_bukti_setoran(): void
    {
        Storage::fake('local');
        $this->actingAsRole('trainer');

        $school = School::create(['name' => 'SD Harapan']);
        $settlement = $this->makeSettlement($school);
        Storage::disk('local')->put($settlement->proof_file, 'isi-bukti-setoran');

        $this->get("/api/v1/keuangan/setoran/{$settlement->id}/proof")->assertStatus(403);
    }
}
