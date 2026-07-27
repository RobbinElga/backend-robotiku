<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Program;
use App\Models\School;
use App\Models\SchoolSettlement;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KeuanganSeeder extends Seeder
{
    public function run(): void
    {
        $keuangan = User::firstOrCreate(
            ['email' => 'keuangan@robotiku.id'],
            ['name' => 'Admin Keuangan', 'role' => 'admin_keuangan', 'password' => bcrypt('password'), 'is_active' => true]
        );

        // --- Program ---
        $program = Program::firstOrCreate(
            ['name' => 'Robo Kids'],
            ['level' => 'Usia 5-7', 'registration_fee' => 150000, 'price_per_cycle' => 200000, 'is_active' => true]
        );

        // --- Schools (hapus duplikat nama "SD IT Bawamai" dari seeder lain) ---
        $dups = School::where('name', 'SD IT Bawamai')->get();
        if ($dups->count() > 1) {
            $keep = $dups->shift();
            foreach ($dups as $d) {
                Student::where('school_id', $d->id)->update(['school_id' => $keep->id]);
                SchoolSettlement::where('school_id', $d->id)->update(['school_id' => $keep->id]);
                $d->delete();
            }
        }

        $schools = [];
        $schoolData = [
            ['SDN Merdeka', 10], ['SMP Cendekia', 15], ['SMA Harapan Bangsa', 20],
            ['MIN 1 Teladan', 8], ['SMP Negeri 5', 18], ['SD Katolik Santa Maria', 25],
            ['MI Al-Falah', 10], ['SMP Muhammadiyah', 15], ['SD Labschool', 22],
            ['MTs Negeri 2', 10], ['SMA Plus', 30],
        ];
        foreach ($schoolData as [$name, $comm]) {
            $schools[] = School::updateOrCreate(['name' => $name], [
                'commission_percent' => $comm,
                'address' => "Jl. $name No.1",
                'pic_name' => 'Kepsek ' . $name,
                'contact' => '08' . str_pad((string) mt_rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT),
                'pipeline_status' => 'sudah_mou',
                'is_mou' => true,
                'created_by' => $keuangan->id,
            ]);
        }

        // --- Bersihkan invoice/payment lama dari seeder sblmnya ---
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Payment::truncate();
        Invoice::truncate();
        SchoolSettlement::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // --- Students per school (dengan parent_id + program_id) ---
        $students = [];
        $now = now();
        $gender = ['L', 'P'];
        $sizes = ['S', 'M', 'L', 'XL'];

        foreach ($schools as $school) {
            $count = mt_rand(5, 10);
            for ($i = 0; $i < $count; $i++) {
                $phone = '08123' . str_pad((string) mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
                $parent = StudentParent::firstOrCreate(
                    ['phone' => $phone],
                    ['name' => 'Orang Tua ' . $school->id . '-' . ($i + 1), 'greeting' => $i % 2 ? 'ayah' : 'bunda']
                );

                $code = 'KWN-' . str_pad((string) $school->id, 2, '0', STR_PAD_LEFT) . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);

                $st = Student::updateOrCreate(['student_code' => $code], [
                    'student_code' => $code,
                    'name' => 'Siswa ' . $school->name . ' #' . ($i + 1),
                    'gender' => $gender[$i % 2],
                    'birth_date' => '2016-01-' . str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT),
                    'shirt_size' => $sizes[$i % 4],
                    'school_origin' => $school->name,
                    'school_grade' => (string) (mt_rand(1, 6)),
                    'parent_id' => $parent->id,
                    'school_id' => $school->id,
                    'program_id' => $program->id,
                    'status' => 'aktif',
                    'registration_type' => 'instansi',
                    'is_verified' => true,
                    'photo_permission' => true,
                    'joined_at' => $now->copy()->subMonths(mt_rand(3, 18))->toDateString(),
                ]);
                $students[] = $st;
            }
        }

        // --- Invoices + Payments spread across last 12 months ---
        $invoiceNumber = 1;
        for ($m = 11; $m >= 0; $m--) {
            $monthDate = $now->copy()->subMonths($m)->startOfMonth();
            $daysInMonth = $monthDate->daysInMonth;

            foreach ($students as $student) {
                $dayOffset = mt_rand(0, min($daysInMonth - 1, $now->day < 5 ? $daysInMonth - 1 : $now->day - 1));
                $createdAt = $monthDate->copy()->addDays($dayOffset);

                if ($createdAt->isFuture()) {
                    continue;
                }

                $isPaid = mt_rand(1, 100) <= 70;
                $status = $isPaid ? 'lunas' : (mt_rand(1, 100) <= 20 ? 'menunggu_verifikasi' : 'belum_bayar');
                $base = mt_rand(150000, 350000);

                $inv = Invoice::create([
                    'invoice_number' => 'KWN-INV-' . str_pad((string) $invoiceNumber++, 5, '0', STR_PAD_LEFT),
                    'student_id' => $student->id,
                    'base_amount' => $base,
                    'total_amount' => $base,
                    'status' => $status,
                    'due_date' => $createdAt->copy()->addDays(14)->format('Y-m-d'),
                    'created_at' => $createdAt->format('Y-m-d H:i:s'),
                    'updated_at' => $createdAt->format('Y-m-d H:i:s'),
                ]);

                if ($status === 'lunas') {
                    $verifiedAt = $createdAt->copy()->addDays(mt_rand(1, 7));
                    if ($verifiedAt->isFuture()) {
                        $verifiedAt = now();
                    }
                    Payment::create([
                        'invoice_id' => $inv->id,
                        'proof_file' => 'seeder/bukti.jpg',
                        'uploader_type' => 'school_admin',
                        'uploader_id' => 1,
                        'verified_by' => $keuangan->id,
                        'verified_at' => $verifiedAt->format('Y-m-d H:i:s'),
                        'status' => 'diverifikasi',
                        'created_at' => $createdAt->format('Y-m-d H:i:s'),
                        'updated_at' => $verifiedAt->format('Y-m-d H:i:s'),
                    ]);
                } elseif ($status === 'menunggu_verifikasi') {
                    Payment::create([
                        'invoice_id' => $inv->id,
                        'proof_file' => 'seeder/bukti.jpg',
                        'uploader_type' => 'parent',
                        'uploader_id' => 1,
                        'status' => 'menunggu_verifikasi',
                        'created_at' => $createdAt->format('Y-m-d H:i:s'),
                        'updated_at' => $createdAt->format('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        // --- School Settlements ---
        foreach ($schools as $school) {
            $settlementCount = mt_rand(2, 5);
            for ($s = 0; $s < $settlementCount; $s++) {
                $settlementDate = $now->copy()->subDays(mt_rand(0, 60))->subHours(mt_rand(0, 23));
                $gross = mt_rand(2000000, 15000000);
                $commPercent = $school->commission_percent;
                $commAmount = (int) round($gross * $commPercent / 100);
                $netAmount = $gross - $commAmount;
                $statusRandom = mt_rand(1, 100);
                $status = $statusRandom <= 70 ? 'diverifikasi' : ($statusRandom <= 90 ? 'menunggu_verifikasi' : 'ditolak');

                $data = [
                    'school_id' => $school->id,
                    'gross_amount' => $gross,
                    'commission_percent' => $commPercent,
                    'commission_amount' => $commAmount,
                    'net_amount' => $netAmount,
                    'proof_file' => 'seeder/setoran.jpg',
                    'status' => $status,
                    'created_by' => 1,
                    'created_at' => $settlementDate->format('Y-m-d H:i:s'),
                    'updated_at' => $settlementDate->format('Y-m-d H:i:s'),
                ];

                if ($status === 'diverifikasi') {
                    $data['verified_by'] = $keuangan->id;
                    $data['verified_at'] = $settlementDate->copy()->addDays(mt_rand(1, 5))->format('Y-m-d H:i:s');
                }

                SchoolSettlement::create($data);
            }
        }

        $this->command->info('KeuanganSeeder: ' . count($students) . ' siswa, ~' . ($invoiceNumber - 1) . ' invoice, ' . count($schools) . ' sekolah.');
    }
}
