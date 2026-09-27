<?php

namespace Tests\Feature\Sekolah;

use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchoolStudentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createSchoolAdmin(School $school): SchoolAdmin
    {
        return SchoolAdmin::create([
            'school_id' => $school->id,
            'name'      => 'Admin ' . $school->name,
            'email'     => 'admin-' . uniqid() . '@sekolah.id',
            'phone'     => '0812' . rand(10000000, 99999999),
            'password'  => bcrypt('password123'),
            'is_active' => true,
        ]);
    }

    public function test_school_admin_can_update_own_school_student_biodata(): void
    {
        $school = School::create(['name' => 'SD Teladan 01']);
        $schoolAdmin = $this->createSchoolAdmin($school);

        $parent = StudentParent::create([
            'name'     => 'Ibu Siti',
            'phone'    => '081122334455',
            'greeting' => 'bunda',
        ]);

        $student = Student::create([
            'student_code'      => 'SCH-001',
            'name'              => 'Bambang Asli',
            'birth_date'        => '2016-01-01',
            'gender'            => 'L',
            'shirt_size'        => 'S',
            'school_origin'     => 'SD Teladan 01',
            'school_grade'      => '1',
            'address'           => 'Alamat Lama',
            'allergy_notes'     => 'Tidak ada',
            'photo_permission'  => true,
            'parent_id'         => $parent->id,
            'school_id'         => $school->id,
            'status'            => 'aktif',
            'registration_type' => 'instansi',
            'is_verified'       => true,
        ]);

        Sanctum::actingAs($schoolAdmin);

        $payload = [
            'name'             => 'Bambang Pamungkas Jr',
            'birth_date'       => '2016-01-15',
            'gender'           => 'L',
            'shirt_size'       => 'M',
            'school_origin'    => 'SD Teladan 01',
            'school_grade'     => '2',
            'address'          => 'Jl. Pahlawan No. 10',
            'allergy_notes'    => 'Alergi dingin',
            'photo_permission' => true,
            'parent_name'      => 'Ibu Siti Khadijah',
            'phone'            => '081122339999',
            'greeting'         => 'bunda',
        ];

        // Test PUT /api/v1/sekolah/murid/{student}
        $response = $this->putJson("/api/v1/sekolah/murid/{$student->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Bambang Pamungkas Jr')
            ->assertJsonPath('data.school_grade', '2')
            ->assertJsonPath('data.address', 'Jl. Pahlawan No. 10')
            ->assertJsonPath('data.allergy_notes', 'Alergi dingin')
            ->assertJsonPath('data.parent.name', 'Ibu Siti Khadijah')
            ->assertJsonPath('data.parent.phone', '081122339999');

        $this->assertDatabaseHas('students', [
            'id'            => $student->id,
            'name'          => 'Bambang Pamungkas Jr',
            'school_grade'  => '2',
            'allergy_notes' => 'Alergi dingin',
        ]);

        $this->assertDatabaseHas('parents', [
            'id'    => $parent->id,
            'name'  => 'Ibu Siti Khadijah',
            'phone' => '081122339999',
        ]);

        // Test alias route /api/v1/sekolah/murid/{student}/biodata
        $aliasResponse = $this->putJson("/api/v1/sekolah/murid/{$student->id}/biodata", [
            'name' => 'Bambang Alias',
        ]);
        $aliasResponse->assertOk()
            ->assertJsonPath('data.name', 'Bambang Alias');
    }

    public function test_school_admin_cannot_update_other_school_student(): void
    {
        $schoolA = School::create(['name' => 'SD Alpha']);
        $schoolB = School::create(['name' => 'SD Beta']);

        $schoolAdminA = $this->createSchoolAdmin($schoolA);

        $studentB = Student::create([
            'student_code'      => 'SCH-B-001',
            'name'              => 'Murid Sekolah Beta',
            'gender'            => 'P',
            'school_id'         => $schoolB->id,
            'status'            => 'aktif',
            'registration_type' => 'instansi',
            'is_verified'       => true,
        ]);

        Sanctum::actingAs($schoolAdminA);

        $response = $this->putJson("/api/v1/sekolah/murid/{$studentB->id}", [
            'name' => 'Nama Bajakan',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Murid bukan dari sekolah Anda.');
    }

    public function test_non_school_admin_cannot_access_school_update(): void
    {
        $school = School::create(['name' => 'SD Gamma']);
        $student = Student::create([
            'student_code'      => 'SCH-G-001',
            'name'              => 'Murid Gamma',
            'gender'            => 'L',
            'school_id'         => $school->id,
            'status'            => 'aktif',
            'registration_type' => 'instansi',
            'is_verified'       => true,
        ]);

        $internalUser = User::create([
            'name'      => 'Admin Staff',
            'email'     => 'admin@robotiku.id',
            'password'  => bcrypt('password123'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($internalUser);

        $response = $this->putJson("/api/v1/sekolah/murid/{$student->id}", [
            'name' => 'Nama Baru',
        ]);

        $response->assertStatus(403);
    }

    public function test_school_admin_gets_filtered_students_by_verification_status(): void
    {
        $schoolA = School::create(['name' => 'Sekolah Kita']);
        $schoolB = School::create(['name' => 'Sekolah Sebelah']);

        $schoolAdminA = $this->createSchoolAdmin($schoolA);

        // School A students
        Student::create([
            'student_code'      => 'SCH-A-VER',
            'name'              => 'Siswa A Verified',
            'gender'            => 'L',
            'school_id'         => $schoolA->id,
            'status'            => 'aktif',
            'registration_type' => 'instansi',
            'is_verified'       => true,
        ]);

        Student::create([
            'student_code'      => 'SCH-A-UNV',
            'name'              => 'Siswa A Unverified',
            'gender'            => 'P',
            'school_id'         => $schoolA->id,
            'status'            => 'aktif',
            'registration_type' => 'instansi',
            'is_verified'       => false,
        ]);

        // School B students (should never leak to School A)
        Student::create([
            'student_code'      => 'SCH-B-VER',
            'name'              => 'Siswa B Verified',
            'gender'            => 'L',
            'school_id'         => $schoolB->id,
            'status'            => 'aktif',
            'registration_type' => 'instansi',
            'is_verified'       => true,
        ]);

        Student::create([
            'student_code'      => 'SCH-B-UNV',
            'name'              => 'Siswa B Unverified',
            'gender'            => 'P',
            'school_id'         => $schoolB->id,
            'status'            => 'aktif',
            'registration_type' => 'instansi',
            'is_verified'       => false,
        ]);

        Sanctum::actingAs($schoolAdminA);

        // 1. Default (no param): only verified of School A
        $resDefault = $this->getJson('/api/v1/sekolah/murid');
        $resDefault->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.student_code', 'SCH-A-VER');

        // 2. verification_status=verified: only verified of School A
        $resVerified = $this->getJson('/api/v1/sekolah/murid?verification_status=verified');
        $resVerified->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.student_code', 'SCH-A-VER');

        // 3. verification_status=unverified: only unverified of School A
        $resUnverified = $this->getJson('/api/v1/sekolah/murid?verification_status=unverified');
        $resUnverified->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.student_code', 'SCH-A-UNV');

        // 4. verification_status=all: both verified and unverified of School A (School B never returned)
        $resAll = $this->getJson('/api/v1/sekolah/murid?verification_status=all');
        $resAll->assertOk()
            ->assertJsonPath('data.total', 2);

        $codes = collect($resAll->json('data.data'))->pluck('student_code')->all();
        $this->assertContains('SCH-A-VER', $codes);
        $this->assertContains('SCH-A-UNV', $codes);
        $this->assertNotContains('SCH-B-VER', $codes);
        $this->assertNotContains('SCH-B-UNV', $codes);
    }
}
