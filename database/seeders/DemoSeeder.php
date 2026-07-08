<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\Kelas;
use App\Models\Mou;
use App\Models\Program;
use App\Models\School;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Users (semua password: "password") ----
        $users = [
            ['Super Admin', 'superadmin@robotiku.id', 'super_admin', '081234500001'],
            ['Admin', 'admin@robotiku.id', 'admin', '081234500002'],
            ['Admin Keuangan', 'keuangan@robotiku.id', 'admin_keuangan', '081234500003'],
            ['Marketing', 'marketing@robotiku.id', 'marketing', '081234500004'],
            ['Trainer Budi', 'trainer@robotiku.id', 'trainer', '081234500005'],
        ];
        foreach ($users as [$name, $email, $role, $phone]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'role' => $role,
                'phone' => $phone,
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);
        }
        $trainer = User::where('email', 'trainer@robotiku.id')->first();
        $superId = User::where('email', 'superadmin@robotiku.id')->value('id');

        // ---- Program ----
        $roboKids = Program::updateOrCreate(['name' => 'Robo Kids'], ['level' => 'Usia 5-7', 'registration_fee' => 150000, 'price_per_cycle' => 200000, 'is_active' => true]);
        Program::updateOrCreate(['name' => 'IoT Junior'], ['level' => 'Usia 8-10', 'registration_fee' => 200000, 'price_per_cycle' => 250000, 'is_active' => true]);

        // ---- Sekolah MOU (lokasi + harga + komisi) ----
        $school = School::updateOrCreate(['name' => 'SD IT Bawamai'], [
            'address' => 'Jl. Pendidikan No.1, Pontianak',
            'latitude' => -0.0263,
            'longitude' => 109.3425,
            'geofence_radius' => 500,
            'pic_name' => 'Ustadz Ahmad',
            'contact' => '081200000001',
            'bank_account' => 'BCA 1234567890 a.n. SD IT Bawamai',
            'commission_percent' => 10,
            'registration_fee' => 100000,
            'price_per_cycle' => 200000,
            'pipeline_status' => 'sudah_mou',
            'is_mou' => true,
            'created_by' => $superId,
        ]);
        Mou::updateOrCreate(['school_id' => $school->id], ['file' => 'mou/demo.pdf', 'periods' => 5, 'created_by' => $superId]);

        // ---- Lokasi kantor (untuk kelas mandiri) ----
        Setting::updateOrCreate(['key' => 'office_latitude'], ['value' => '-0.0300']);
        Setting::updateOrCreate(['key' => 'office_longitude'], ['value' => '109.3300']);
        Setting::updateOrCreate(['key' => 'office_radius'], ['value' => '500']);

        // ---- Rekening Robotiku ----
        BankAccount::updateOrCreate(['account_number' => '8889990001'], ['bank_name' => 'BSI', 'account_holder' => 'Yayasan Tadika Cikal Mulia', 'is_active' => true]);

        // ---- Kelas ----
        $kelasIns = Kelas::updateOrCreate(['name' => 'Kelas Bawamai A'], ['program_id' => $roboKids->id, 'school_id' => $school->id, 'schedule' => 'Sabtu, 09.00', 'capacity' => 15, 'trainer_id' => $trainer->id]);
        $kelasMdr = Kelas::updateOrCreate(['name' => 'Kelas Kantor Mandiri'], ['program_id' => $roboKids->id, 'school_id' => null, 'schedule' => 'Minggu, 10.00', 'capacity' => 15, 'trainer_id' => $trainer->id]);
        $kelasIns->trainers()->syncWithoutDetaching([$trainer->id => ['role' => 'utama']]);
        $kelasMdr->trainers()->syncWithoutDetaching([$trainer->id => ['role' => 'utama']]);

        // ---- Murid instansi ----
        foreach (['Andi', 'Budi', 'Caca', 'Dinda'] as $i => $nm) {
            $parent = StudentParent::updateOrCreate(['phone' => '08121111' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)], ['name' => 'Ortu ' . $nm, 'greeting' => $i % 2 ? 'bunda' : 'ayah']);
            $st = Student::updateOrCreate(['student_code' => 'ROBO-INS' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)], [
                'name' => $nm,
                'birth_date' => '2017-01-0' . ($i + 1),
                'gender' => $i % 2 ? 'P' : 'L',
                'shirt_size' => 'M',
                'school_origin' => $school->name,
                'school_grade' => '2' . chr(65 + $i),
                'parent_id' => $parent->id,
                'school_id' => $school->id,
                'program_id' => $roboKids->id,
                'period_quota' => 5,
                'joined_at' => now()->toDateString(),
                'status' => 'aktif',
                'registration_type' => 'instansi',
                'photo_permission' => true,
            ]);
            $kelasIns->students()->syncWithoutDetaching([$st->id => ['joined_at' => now()]]);
        }

        // ---- Murid mandiri ----
        foreach (['Eka', 'Fajar', 'Gita'] as $i => $nm) {
            $parent = StudentParent::updateOrCreate(['phone' => '08132222' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)], ['name' => 'Ortu ' . $nm, 'greeting' => $i % 2 ? 'ayah' : 'bunda']);
            $st = Student::updateOrCreate(['student_code' => 'ROBO-MDR' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)], [
                'name' => $nm,
                'birth_date' => '2016-05-0' . ($i + 1),
                'gender' => $i % 2 ? 'L' : 'P',
                'shirt_size' => 'L',
                'school_origin' => 'SD Umum',
                'school_grade' => '3' . chr(65 + $i),
                'parent_id' => $parent->id,
                'school_id' => null,
                'program_id' => $roboKids->id,
                'status' => 'aktif',
                'registration_type' => 'mandiri',
                'photo_permission' => true,
            ]);
            $kelasMdr->students()->syncWithoutDetaching([$st->id => ['joined_at' => now()]]);
        }
    }
}
