<?php

namespace Tests\Feature\Murid;

use App\Models\EReport;
use App\Models\Kelas;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EReportTest extends TestCase
{
    use RefreshDatabase;

    private function setupTrainerStudent(): array
    {
        $trainer = User::create(['name' => 'T', 'email' => 'tr' . uniqid() . '@r.id', 'password' => bcrypt('x'), 'role' => 'trainer', 'is_active' => true]);
        $kelas = Kelas::create(['name' => 'Robo', 'trainer_id' => $trainer->id]);
        $parent = StudentParent::create(['name' => 'Budi', 'phone' => '081200000001']);
        $student = Student::create(['student_code' => 'ROBO-M' . uniqid(), 'name' => 'Andi', 'gender' => 'L', 'parent_id' => $parent->id, 'status' => 'aktif', 'registration_type' => 'mandiri']);
        $student->classes()->attach($kelas->id, ['joined_at' => now()]);

        return [$trainer, $kelas, $student];
    }

    private function payload(Kelas $kelas, Student $student): array
    {
        return [
            'student_id' => $student->id,
            'class_id' => $kelas->id,
            'semester' => '1',
            'year' => 2026,
            'skill_building' => 'A',
            'skill_imagination' => 'B',
            'skill_creativity' => 'A',
            'skill_logic' => 'B',
            'behavior_punctual' => 'A',
            'behavior_stay' => 'A',
            'behavior_communication' => 'B',
            'behavior_responsibility' => 'A',
            'comments' => 'Berkembang baik.',
        ];
    }

    public function test_trainer_buat_erapot(): void
    {
        [$trainer, $kelas, $student] = $this->setupTrainerStudent();
        Sanctum::actingAs($trainer);

        $this->postJson('/api/v1/e-rapot', $this->payload($kelas, $student))->assertStatus(201);
        $this->assertDatabaseHas('e_reports', ['student_id' => $student->id, 'semester' => '1', 'year' => 2026]);
    }

    public function test_trainer_tidak_bisa_nilai_murid_kelas_lain(): void
    {
        [, $kelas, $student] = $this->setupTrainerStudent();
        $lain = User::create(['name' => 'TL', 'email' => 'tl@r.id', 'password' => bcrypt('x'), 'role' => 'trainer', 'is_active' => true]);
        Sanctum::actingAs($lain);

        $this->postJson('/api/v1/e-rapot', $this->payload($kelas, $student))->assertStatus(403);
    }

    public function test_duplikat_semester_tahun_ditolak(): void
    {
        [$trainer, $kelas, $student] = $this->setupTrainerStudent();
        Sanctum::actingAs($trainer);

        $this->postJson('/api/v1/e-rapot', $this->payload($kelas, $student))->assertStatus(201);
        $this->postJson('/api/v1/e-rapot', $this->payload($kelas, $student))->assertStatus(422);
    }

    public function test_cetak_pdf(): void
    {
        [$trainer, $kelas, $student] = $this->setupTrainerStudent();
        Sanctum::actingAs($trainer);

        $this->postJson('/api/v1/e-rapot', $this->payload($kelas, $student));
        $report = EReport::first();

        $res = $this->get("/api/v1/e-rapot/{$report->id}/pdf");
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }

    public function test_ortu_lihat_erapot(): void
    {
        [$trainer, $kelas, $student] = $this->setupTrainerStudent();
        Sanctum::actingAs($trainer);
        $this->postJson('/api/v1/e-rapot', $this->payload($kelas, $student));

        $this->postJson('/api/v1/e-rapot/parent', ['student_id' => $student->id, 'phone' => '081200000001'])
            ->assertOk()->assertJsonCount(1, 'data');
    }
}
