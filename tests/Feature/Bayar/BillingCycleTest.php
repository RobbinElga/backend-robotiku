<?php

namespace Tests\Feature\Bayar;

use App\Models\Attendance;
use App\Models\BillingSetting;
use App\Models\Kelas;
use App\Models\Student;
use App\Models\User;
use App\Services\BillingCycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingCycleTest extends TestCase
{
    use RefreshDatabase;

    private User $trainer;
    private Kelas $kelas;

    private function makeStudent(string $status = 'aktif'): Student
    {
        $this->trainer = User::create(['name' => 'T', 'email' => 'tr' . uniqid() . '@r.id', 'password' => bcrypt('x'), 'role' => 'trainer', 'is_active' => true]);
        $this->kelas = Kelas::create(['name' => 'Robo', 'trainer_id' => $this->trainer->id]);
        BillingSetting::create(['class_id' => $this->kelas->id, 'registration_fee' => 150000, 'price_per_cycle' => 200000]);

        $student = Student::create([
            'student_code' => 'ROBO-MDR' . uniqid(),
            'name' => 'Andi',
            'gender' => 'L',
            'status' => $status,
            'registration_type' => 'mandiri',
        ]);
        $student->classes()->attach($this->kelas->id, ['joined_at' => now()]);

        return $student;
    }

    private function hadir(Student $student, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            Attendance::create([
                'class_id' => $this->kelas->id,
                'student_id' => $student->id,
                'trainer_id' => $this->trainer->id,
                'status' => 'hadir',
                'attended_at' => now(),
            ]);
        }
    }

    public function test_4_hadir_membuat_siklus_dan_invoice(): void
    {
        $student = $this->makeStudent();
        $this->hadir($student, 4);

        $invoice = app(BillingCycleService::class)->handleAttendance($student);

        $this->assertNotNull($invoice);
        $this->assertEquals('200000.00', $invoice->total_amount);
        $this->assertNull($invoice->registration_fee);
        $this->assertDatabaseHas('billing_months', ['student_id' => $student->id, 'cycle_number' => 2]);
    }

    public function test_belum_4_hadir_tidak_membuat_invoice(): void
    {
        $student = $this->makeStudent();
        $this->hadir($student, 3);

        $this->assertNull(app(BillingCycleService::class)->handleAttendance($student));
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_idempoten_tidak_dobel(): void
    {
        $student = $this->makeStudent();
        $this->hadir($student, 4);

        $svc = app(BillingCycleService::class);
        $svc->handleAttendance($student);   // buat
        $second = $svc->handleAttendance($student); // panggil lagi

        $this->assertNull($second);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_siswa_cuti_tidak_ditagih(): void
    {
        $student = $this->makeStudent('cuti');
        $this->hadir($student, 4);

        $this->assertNull(app(BillingCycleService::class)->handleAttendance($student));
        $this->assertDatabaseCount('invoices', 0);
    }
}
