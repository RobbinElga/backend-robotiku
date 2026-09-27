<?php

namespace Tests\Feature\Admin;

use App\Models\Program;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): User
    {
        $user = User::create([
            'name'      => ucfirst($role) . ' User',
            'email'     => $role . '-' . uniqid() . '@robotiku.id',
            'password'  => bcrypt('password123'),
            'role'      => $role,
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);
        return $user;
    }

    /* -------------------------------------------------------------
     * 1. Status Harmonization & Audit Trail Tests
     * ------------------------------------------------------------- */

    public function test_validates_status_enum(): void
    {
        $student = Student::create([
            'student_code'      => 'STD-001',
            'name'              => 'Murid Test',
            'gender'            => 'L',
            'status'            => 'aktif',
            'registration_type' => 'mandiri',
            'is_verified'       => true,
        ]);

        $this->actingAsRole('admin');

        $response = $this->patchJson("/api/v1/siswa/{$student->id}/status", [
            'status' => 'invalid_status',
            'note'   => 'Coba status asal',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_harmonizes_berhenti_to_nonaktif_and_writes_status_log(): void
    {
        $student = Student::create([
            'student_code'      => 'STD-002',
            'name'              => 'Siswa Berhenti',
            'gender'            => 'P',
            'status'            => 'aktif',
            'registration_type' => 'mandiri',
            'is_verified'       => true,
        ]);

        $admin = $this->actingAsRole('admin');

        $response = $this->patchJson("/api/v1/siswa/{$student->id}/status", [
            'status' => 'berhenti',
            'note'   => 'Pindah domisili luar kota',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'nonaktif');

        $this->assertDatabaseHas('students', [
            'id'     => $student->id,
            'status' => 'nonaktif',
        ]);

        $this->assertDatabaseHas('student_status_logs', [
            'student_id'      => $student->id,
            'old_status'      => 'aktif',
            'new_status'      => 'nonaktif',
            'note'            => 'Pindah domisili luar kota',
            'changed_by_type' => 'user',
            'changed_by'      => $admin->id,
        ]);
    }

    public function test_status_cuti_and_nonaktif_allowed(): void
    {
        $student = Student::create([
            'student_code'      => 'STD-003',
            'name'              => 'Siswa Cuti',
            'gender'            => 'L',
            'status'            => 'aktif',
            'registration_type' => 'mandiri',
            'is_verified'       => true,
        ]);

        $this->actingAsRole('admin');

        $response = $this->patchJson("/api/v1/siswa/{$student->id}/status", [
            'status' => 'cuti',
            'note'   => 'Sakit 1 bulan',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'cuti');

        $this->assertDatabaseHas('students', [
            'id'     => $student->id,
            'status' => 'cuti',
        ]);
    }

    public function test_changing_to_same_status_fails_with_422(): void
    {
        $student = Student::create([
            'student_code'      => 'STD-004',
            'name'              => 'Siswa Tetap',
            'gender'            => 'L',
            'status'            => 'aktif',
            'registration_type' => 'mandiri',
            'is_verified'       => true,
        ]);

        $this->actingAsRole('admin');

        $response = $this->patchJson("/api/v1/siswa/{$student->id}/status", [
            'status' => 'aktif',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Status murid sudah aktif.');
    }

    public function test_regular_admin_cannot_change_status_to_lulus(): void
    {
        $student = Student::create([
            'student_code'      => 'STD-005',
            'name'              => 'Siswa Lulus Test',
            'gender'            => 'L',
            'status'            => 'aktif',
            'registration_type' => 'mandiri',
            'is_verified'       => true,
        ]);

        $this->actingAsRole('admin');

        $response = $this->patchJson("/api/v1/siswa/{$student->id}/status", [
            'status' => 'lulus',
        ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_change_status_to_lulus(): void
    {
        $student = Student::create([
            'student_code'      => 'STD-006',
            'name'              => 'Siswa Super Lulus',
            'gender'            => 'P',
            'status'            => 'aktif',
            'registration_type' => 'mandiri',
            'is_verified'       => true,
        ]);

        $superAdmin = $this->actingAsRole('super_admin');

        $response = $this->patchJson("/api/v1/siswa/{$student->id}/status", [
            'status' => 'lulus',
            'note'   => 'Menyelesaikan modul tingkat akhir',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'lulus');

        $this->assertDatabaseHas('students', [
            'id'     => $student->id,
            'status' => 'lulus',
        ]);

        $this->assertDatabaseHas('student_status_logs', [
            'student_id'      => $student->id,
            'old_status'      => 'aktif',
            'new_status'      => 'lulus',
            'note'            => 'Menyelesaikan modul tingkat akhir',
            'changed_by_type' => 'user',
            'changed_by'      => $superAdmin->id,
        ]);
    }

    /* -------------------------------------------------------------
     * 2. Edit Biodata Murid Tests
     * ------------------------------------------------------------- */

    public function test_admin_can_update_student_biodata_and_parent(): void
    {
        $parent = StudentParent::create([
            'name'     => 'Ayah Budi',
            'phone'    => '081234567890',
            'greeting' => 'ayah',
        ]);

        $program = Program::create([
            'name'        => 'Robotics Starter',
            'description' => 'Level dasar',
            'is_active'   => true,
        ]);

        $student = Student::create([
            'student_code'      => 'STD-BIO-1',
            'name'              => 'Budi Asli',
            'birth_date'        => '2015-05-10',
            'gender'            => 'L',
            'shirt_size'        => 'S',
            'school_origin'     => 'SD 1 Baru',
            'school_grade'      => '3',
            'address'           => 'Jl. Lama No. 1',
            'allergy_notes'     => 'Tidak ada',
            'photo_permission'  => true,
            'parent_id'         => $parent->id,
            'status'            => 'aktif',
            'registration_type' => 'mandiri',
            'is_verified'       => true,
        ]);

        $this->actingAsRole('admin');

        $payload = [
            'name'             => 'Budi Prakoso',
            'birth_date'       => '2015-05-12',
            'gender'           => 'L',
            'shirt_size'       => 'M',
            'school_origin'    => 'SD 1 Sukamaju',
            'school_grade'     => '4',
            'address'          => 'Jl. Anyar No. 99',
            'allergy_notes'    => 'Alergi kacang',
            'photo_permission' => false,
            'program_id'       => $program->id,
            'parent_name'      => 'Ayah Budi Prakoso',
            'phone'            => '081299998888',
            'greeting'         => 'ayah',
            'phone_alt'        => '081377776666',
        ];

        // Test via PUT /api/v1/siswa/{student}
        $response = $this->putJson("/api/v1/siswa/{$student->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Budi Prakoso')
            ->assertJsonPath('data.shirt_size', 'M')
            ->assertJsonPath('data.school_grade', '4')
            ->assertJsonPath('data.address', 'Jl. Anyar No. 99')
            ->assertJsonPath('data.allergy_notes', 'Alergi kacang')
            ->assertJsonPath('data.photo_permission', false)
            ->assertJsonPath('data.parent.name', 'Ayah Budi Prakoso')
            ->assertJsonPath('data.parent.phone', '081299998888')
            ->assertJsonPath('data.program.name', 'Robotics Starter');

        $this->assertDatabaseHas('students', [
            'id'            => $student->id,
            'name'          => 'Budi Prakoso',
            'shirt_size'    => 'M',
            'school_grade'  => '4',
            'allergy_notes' => 'Alergi kacang',
            'program_id'    => $program->id,
        ]);

        $this->assertDatabaseHas('parents', [
            'id'        => $parent->id,
            'name'      => 'Ayah Budi Prakoso',
            'phone'     => '081299998888',
            'phone_alt' => '081377776666',
        ]);

        // Also test the alias route /api/v1/siswa/{student}/biodata
        $aliasResponse = $this->putJson("/api/v1/siswa/{$student->id}/biodata", [
            'name' => 'Budi Prakoso Alias',
        ]);
        $aliasResponse->assertOk()
            ->assertJsonPath('data.name', 'Budi Prakoso Alias');
    }

    public function test_biodata_update_validation_errors(): void
    {
        $student = Student::create([
            'student_code'      => 'STD-BIO-VAL',
            'name'              => 'Test Val',
            'gender'            => 'L',
            'status'            => 'aktif',
            'registration_type' => 'mandiri',
            'is_verified'       => true,
        ]);

        $this->actingAsRole('admin');

        $response = $this->putJson("/api/v1/siswa/{$student->id}", [
            'gender'     => 'InvalidGender',
            'birth_date' => 'not-a-date',
            'program_id' => 999999,
            'greeting'   => 'uncle', // must be ayah or bunda
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gender', 'birth_date', 'program_id', 'greeting']);
    }

    public function test_non_admin_cannot_update_biodata(): void
    {
        $student = Student::create([
            'student_code'      => 'STD-BIO-ROLES',
            'name'              => 'Student Secret',
            'gender'            => 'P',
            'status'            => 'aktif',
            'registration_type' => 'mandiri',
            'is_verified'       => true,
        ]);

        $this->actingAsRole('trainer');

        $response = $this->putJson("/api/v1/siswa/{$student->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(403);
    }

    /* -------------------------------------------------------------
     * 3. Visibility Filter Tests
     * ------------------------------------------------------------- */

    public function test_admin_filtered_defaults_to_verified_only(): void
    {
        Student::create([
            'student_code'      => 'V-001',
            'name'              => 'Verified Student 1',
            'gender'            => 'L',
            'status'            => 'aktif',
            'registration_type' => 'mandiri',
            'is_verified'       => true,
        ]);
        Student::create([
            'student_code'      => 'UV-001',
            'name'              => 'Unverified Student 1',
            'gender'            => 'P',
            'status'            => 'aktif',
            'registration_type' => 'mandiri',
            'is_verified'       => false,
        ]);

        $this->actingAsRole('admin');

        // Default: only verified
        $resDefault = $this->getJson('/api/v1/siswa');
        $resDefault->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.student_code', 'V-001');

        // Explicit verification_status=verified
        $resVerified = $this->getJson('/api/v1/siswa?verification_status=verified');
        $resVerified->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.student_code', 'V-001');

        // Explicit verification_status=unverified
        $resUnverified = $this->getJson('/api/v1/siswa?verification_status=unverified');
        $resUnverified->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.student_code', 'UV-001');

        // Explicit verification_status=all
        $resAll = $this->getJson('/api/v1/siswa?verification_status=all');
        $resAll->assertOk()
            ->assertJsonPath('data.total', 2);
    }
}
