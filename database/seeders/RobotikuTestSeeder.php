<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\Program;
use App\Models\Student;
use App\Models\Kelas;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\EReport;
use Carbon\Carbon;

class RobotikuTestSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            /* ---------- 1. USERS internal ---------- */
            $trainer = User::updateOrCreate(
                ['email' => 'trainer@robotiku.test'],
                ['name' => 'Kak Trainer', 'password' => Hash::make('password'), 'role' => 'trainer', 'is_active' => true]
            );
            User::updateOrCreate(
                ['email' => 'keuangan@robotiku.test'],
                ['name' => 'Admin Keuangan', 'password' => Hash::make('password'), 'role' => 'admin_keuangan', 'is_active' => true]
            );
            $super = User::updateOrCreate(
                ['email' => 'super@robotiku.test'],
                ['name' => 'Super Admin', 'password' => Hash::make('password'), 'role' => 'super_admin', 'is_active' => true]
            );

            /* ---------- 2. SCHOOL (MoU + komisi) ---------- */
            $school = School::updateOrCreate(
                ['name' => 'SD IT Bawamai (TES)'],
                [
                    'address'         => 'Jl. Pendidikan No. 1, Pontianak',
                    'pic_name'        => 'Bu Kepala Sekolah',
                    'contact'         => '081200000001',
                    'bank_account'    => 'BCA 1234567890 a.n. SD IT Bawamai',
                    'pipeline_status' => 'sudah_mou',
                    'is_mou'          => true,
                    'commission_percent' => 20,      // ← jatah sekolah 20%  (sesuaikan jika nama kolom beda)
                    'created_by'      => $super->id,
                ]
            );

            /* ---------- 3. SCHOOL ADMIN (login) ---------- */
            SchoolAdmin::updateOrCreate(
                ['email' => 'sekolah@robotiku.test'],
                [
                    'school_id' => $school->id,
                    'name'      => 'Admin Bawamai',
                    'phone'     => '081200000009',
                    'password'  => Hash::make('password'),
                    'is_active' => true,
                ]
            );

            /* ---------- 4. PROGRAMS ---------- */
            $progRobotik = Program::updateOrCreate(
                ['name' => 'Robotik Dasar (TES)'],
                ['registration_fee' => 150000, 'price_per_cycle' => 200000]
            );
            $progCoding = Program::updateOrCreate(
                ['name' => 'Coding Kids (TES)'],
                ['registration_fee' => 150000, 'price_per_cycle' => 250000]
            );

            /* ---------- 5. KELAS + trainer ---------- */
            $kelas = Kelas::updateOrCreate(
                ['name' => 'Kelas Robotik A (TES)'],
                [
                    'schedule'   => 'Sabtu 09:00',
                    'capacity'   => 20,
                    'program_id' => $progRobotik->id,
                    'school_id'  => $school->id,   // ← jika kolom ini ada (P4)
                    'trainer_id' => $trainer->id,  // ← jika masih single trainer; kalau multi-trainer pivot, lihat catatan di bawah
                ]
            );

            /* ---------- 6. MURID + parent + tagihan + absensi + e-rapot ---------- */
            $seq = 1;
            $mkCode = function () use (&$seq) {
                return 'ROBO-TES' . str_pad($seq++, 3, '0', STR_PAD_LEFT);
            };

            // definisi 6 murid: [nama, status, program, jenis_tagihan, jml_hadir, jml_rapor, backdate_bulan]
            $defs = [
                ['Andi Saputra',    'aktif', $progRobotik, 'lunas',                4, 1, 5],
                ['Bella Kirana',    'aktif', $progRobotik, 'lunas',                4, 1, 4],
                ['Citra Dewi',      'aktif', $progCoding,  'menunggu_verifikasi',  3, 0, 3],
                ['Dimas Pratama',   'aktif', $progRobotik, 'belum_bayar',          2, 0, 2],
                ['Eka Wulandari',   'aktif', $progCoding,  'lunas',                6, 2, 1],
                ['Fajar Nugroho',   'cuti',  $progRobotik, 'belum_bayar',          1, 0, 0],
            ];

            $invCounter = 1;
            foreach ($defs as [$nama, $status, $program, $jenisTagihan, $jmlHadir, $jmlRapor, $backdate]) {
                // parent (pakai DB::table agar tak bergantung nama model Parent)
                $parentId = DB::table('parents')->insertGetId([
                    'name'       => 'Ortu ' . $nama,
                    'phone'      => '08' . random_int(1000000000, 1999999999),
                    'greeting'   => 'bunda',   // ← jika kolom greeting ada
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $student = Student::create([
                    'student_code'      => $mkCode(),
                    'name'              => $nama,
                    'birth_date'        => now()->subYears(9)->toDateString(),
                    'gender'            => random_int(0, 1) ? 'L' : 'P',
                    'shirt_size'        => 'M',
                    'school_grade'      => random_int(2, 5) . 'A',
                    'parent_id'         => $parentId,
                    'school_id'         => $school->id,
                    'program_id'        => $program->id,
                    'status'            => $status,
                    'registration_type' => 'instansi',
                    'photo_permission'  => true,
                ]);
                // backdate created_at untuk grafik "pendaftaran per bulan"
                DB::table('students')->where('id', $student->id)
                    ->update(['created_at' => now()->subMonths($backdate)->startOfMonth()->addDays(3)]);

                // assign ke kelas
                DB::table('class_students')->insert([
                    'class_id' => $kelas->id,
                    'student_id' => $student->id,
                    'joined_at' => now()->subMonths($backdate),
                ]);

                // absensi (score A-E hanya untuk hadir)
                $grades = ['A', 'B', 'C'];
                for ($i = 0; $i < $jmlHadir; $i++) {
                    DB::table('attendances')->insert([
                        'class_id'    => $kelas->id,
                        'student_id'  => $student->id,
                        'trainer_id'  => $trainer->id,
                        'status'      => 'hadir',
                        'score'       => $grades[array_rand($grades)],   // ← kolom nilai (sesuaikan jika bernama beda)
                        'report'      => '<p>Ananda <strong>' . e($nama) . '</strong> aktif merakit robot dan memahami instruksi dengan baik.</p>',
                        'photo'       => null,
                        'attended_at' => now()->subDays(($jmlHadir - $i) * 7),
                        'created_at'  => now(),
                        'updated_at' => now(),
                    ]);
                }
                // 1 sesi izin biar variatif
                DB::table('attendances')->insert([
                    'class_id' => $kelas->id,
                    'student_id' => $student->id,
                    'trainer_id' => $trainer->id,
                    'status' => 'izin',
                    'score' => null,
                    'report' => '<p>Izin acara keluarga.</p>',
                    'photo' => null,
                    'attended_at' => now()->subDays(3),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // e-rapot
                for ($s = 0; $s < $jmlRapor; $s++) {
                    EReport::create([
                        'student_id'  => $student->id,
                        'trainer_id'  => $trainer->id,
                        'class_id'    => $kelas->id,
                        'semester'    => $s === 0 ? 1 : 2,
                        'year'        => 2026,
                        'skill_building'          => 'A',
                        'skill_imagination'       => 'B',
                        'skill_creativity'        => 'A',
                        'skill_logic'             => 'B',
                        'behavior_punctual'       => 'A',
                        'behavior_stay'           => 'B',
                        'behavior_communication'  => 'A',
                        'behavior_responsibility' => 'A',
                        'comments'    => 'Perkembangan sangat baik, terus semangat!',
                    ]);
                }

                // billing_month + invoice
                $bmId = DB::table('billing_months')->insertGetId([
                    'student_id'   => $student->id,
                    'cycle_number' => 1,
                    'period_month' => (int) now()->format('n'),
                    'period_year'  => (int) now()->format('Y'),
                    'status'       => $jenisTagihan === 'lunas' ? 'lunas' : 'belum_bayar',
                    'created_at'   => now(),
                    'updated_at' => now(),
                ]);

                $base = (int) $program->price_per_cycle;
                $reg  = (int) $program->registration_fee;
                $total = $base + $reg;
                $invoice = Invoice::create([
                    'invoice_number'  => 'INV-TES-' . str_pad($invCounter++, 4, '0', STR_PAD_LEFT),
                    'student_id'      => $student->id,
                    'billing_month_id' => $bmId,
                    'base_amount'     => $base,
                    'registration_fee' => $reg,           // invoice pertama = daftar + siklus
                    'discount_amount' => 0,
                    'total_amount'    => $total,
                    'due_date'        => now()->addDays(7)->toDateString(),
                    'status'          => $jenisTagihan,
                ]);

                // kalau menunggu verifikasi → buat Payment pending (muncul di Pembayaran Masuk)
                if ($jenisTagihan === 'menunggu_verifikasi') {
                    Payment::create([
                        'invoice_id'    => $invoice->id,
                        'proof_file'    => null,          // belum ada file fisik (ProofView tampil placeholder)
                        'uploader_type' => 'parent',
                        'uploader_id'   => $parentId,
                        'status'        => 'menunggu_verifikasi',
                        'notes'         => null,
                    ]);
                }
            }
        });

        $this->command->info('✅ RobotikuTestSeeder selesai.');
        $this->command->info('   Login Admin Sekolah → email: sekolah@robotiku.test / HP: 081200000009 · password: password');
    }
}
