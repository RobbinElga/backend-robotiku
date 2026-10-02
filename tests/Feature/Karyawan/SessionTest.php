<?php

namespace Tests\Feature\Karyawan;

use App\Http\Controllers\Api\V1\Karyawan\SessionController as KaryawanSessionController;
use App\Models\Kelas;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    private function createTrainer(): User
    {
        $trainer = User::create([
            'name' => 'Trainer Test',
            'email' => 'trainer_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'trainer',
            'is_active' => true,
        ]);

        Sanctum::actingAs($trainer);

        return $trainer;
    }

    private function createClassForTrainer(User $trainer): Kelas
    {
        return Kelas::create([
            'name' => 'Kelas Robotika',
            'trainer_id' => $trainer->id,
            'meetings_per_period' => 4,
        ]);
    }

    public function test_cannot_start_live_session_when_class_has_no_students(): void
    {
        Storage::fake('local');
        $trainer = $this->createTrainer();
        $kelas = $this->createClassForTrainer($trainer);

        $response = $this->postJson('/api/v1/sesi/mulai', [
            'class_id' => $kelas->id,
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Kelas belum memiliki murid aktif. Sesi tidak dapat dimulai.');

        $this->assertDatabaseCount('class_sessions', 0);
    }

    public function test_cannot_start_live_session_when_class_has_only_inactive_students(): void
    {
        Storage::fake('local');
        $trainer = $this->createTrainer();
        $kelas = $this->createClassForTrainer($trainer);

        $student = Student::create([
            'student_code' => 'STD-' . uniqid(),
            'name' => 'Murid Cuti',
            'gender' => 'L',
            'status' => 'cuti',
            'registration_type' => 'mandiri',
        ]);
        $kelas->students()->attach($student->id, ['joined_at' => now()]);

        $response = $this->postJson('/api/v1/sesi/mulai', [
            'class_id' => $kelas->id,
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Kelas belum memiliki murid aktif. Sesi tidak dapat dimulai.');

        $this->assertDatabaseCount('class_sessions', 0);
    }

    public function test_can_start_live_session_when_class_has_active_student(): void
    {
        Storage::fake('local');
        $trainer = $this->createTrainer();
        $kelas = $this->createClassForTrainer($trainer);

        $student = Student::create([
            'student_code' => 'STD-' . uniqid(),
            'name' => 'Murid Aktif',
            'gender' => 'L',
            'status' => 'aktif',
            'registration_type' => 'mandiri',
        ]);
        $kelas->students()->attach($student->id, ['joined_at' => now()]);

        $response = $this->postJson('/api/v1/sesi/mulai', [
            'class_id' => $kelas->id,
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->assertDatabaseCount('class_sessions', 1);
    }

    public function test_cannot_create_manual_session_when_class_has_no_students(): void
    {
        $trainer = $this->createTrainer();
        $kelas = $this->createClassForTrainer($trainer);

        $response = $this->postJson('/api/v1/sesi/manual', [
            'class_id' => $kelas->id,
            'date' => '2026-10-02',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Kelas belum memiliki murid aktif. Sesi tidak dapat dimulai.');

        $this->assertDatabaseCount('class_sessions', 0);
    }

    public function test_cannot_create_manual_session_when_class_has_only_inactive_students(): void
    {
        $trainer = $this->createTrainer();
        $kelas = $this->createClassForTrainer($trainer);

        $student = Student::create([
            'student_code' => 'STD-' . uniqid(),
            'name' => 'Murid Berhenti',
            'gender' => 'P',
            'status' => 'berhenti',
            'registration_type' => 'mandiri',
        ]);
        $kelas->students()->attach($student->id, ['joined_at' => now()]);

        $response = $this->postJson('/api/v1/sesi/manual', [
            'class_id' => $kelas->id,
            'date' => '2026-10-02',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Kelas belum memiliki murid aktif. Sesi tidak dapat dimulai.');

        $this->assertDatabaseCount('class_sessions', 0);
    }

    public function test_can_create_manual_session_when_class_has_active_student(): void
    {
        $trainer = $this->createTrainer();
        $kelas = $this->createClassForTrainer($trainer);

        $student = Student::create([
            'student_code' => 'STD-' . uniqid(),
            'name' => 'Murid Aktif',
            'gender' => 'L',
            'status' => 'aktif',
            'registration_type' => 'mandiri',
        ]);
        $kelas->students()->attach($student->id, ['joined_at' => now()]);

        $response = $this->postJson('/api/v1/sesi/manual', [
            'class_id' => $kelas->id,
            'date' => '2026-10-02',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->assertDatabaseCount('class_sessions', 1);
    }

    public function test_karyawan_session_controller_start_fails_when_class_has_no_active_students(): void
    {
        Storage::fake('local');
        $trainer = $this->createTrainer();
        $kelas = $this->createClassForTrainer($trainer);

        $controller = new KaryawanSessionController();
        $request = Request::create('/api/v1/karyawan/sesi/mulai', 'POST', [
            'class_id' => $kelas->id,
            'latitude' => -6.200000,
            'longitude' => 106.816666,
        ]);
        $request->setUserResolver(fn () => $trainer);
        $request->files->set('photo', UploadedFile::fake()->image('selfie.jpg'));

        $response = $controller->start($request);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('Kelas belum memiliki murid aktif. Sesi tidak dapat dimulai.', $response->getData()->message);
        $this->assertDatabaseCount('class_sessions', 0);
    }
}
