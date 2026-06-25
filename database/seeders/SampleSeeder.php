<?php

namespace Database\Seeders;

use App\Models\BillingSetting;
use App\Models\DiscountCode;
use App\Models\Kelas;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Database\Seeder;

class SampleSeeder extends Seeder
{
    public function run(): void
    {
        $admin   = User::where('role', 'admin')->first();
        $trainer = User::where('role', 'trainer')->first();

        // 1 sekolah MOU (muncul di dropdown daftar instansi)
        $school = School::factory()->mou()->create([
            'name' => 'SD IT Bawamai',
            'created_by' => $admin?->id,
        ]);

        // Kelas + harga
        $kelas = Kelas::factory()->create(['trainer_id' => $trainer?->id]);
        BillingSetting::create([
            'class_id'         => $kelas->id,
            'registration_fee' => 150000,
            'price_per_cycle'  => 200000,
            'updated_by'       => $admin?->id,
        ]);

        // 5 siswa mandiri + orang tua
        Student::factory(5)->create()->each(function (Student $s) {
            $parent = StudentParent::create([
                'name'  => 'Ortu ' . $s->name,
                'phone' => '08' . fake()->unique()->numerify('##########'),
            ]);
            $s->update(['parent_id' => $parent->id]);
        });

        // Kode promo (registration_fee, jalur mandiri)
        DiscountCode::create([
            'code' => 'ROBOTIKU2026',
            'type' => 'percentage',
            'value' => 50,
            'quota' => 0,
            'valid_from' => now(),
            'valid_until' => now()->addMonths(6),
            'is_active' => true,
            'created_by' => $admin?->id,
        ]);
    }
}
