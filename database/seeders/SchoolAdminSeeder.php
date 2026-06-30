<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\SchoolAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SchoolAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Sekolah mitra (sudah MOU → muncul di dropdown daftar instansi)
        $school = School::updateOrCreate(
            ['name' => 'SD IT Bawamai'],
            [
                'address'         => 'Jl. Pendidikan No. 1, Pontianak',
                'pic_name'        => 'Bu Sinta',
                'contact'         => '081200000001',
                'bank_account'    => '1234567890 (BNI)',
                'pipeline_status' => 'sudah_mou',
                'is_mou'          => true,
            ]
        );

        // Akun Admin Sekolah — bisa login pakai email ATAU nomor HP
        SchoolAdmin::updateOrCreate(
            ['email' => 'sekolah@robotiku.id'],
            [
                'school_id' => $school->id,
                'name'      => 'Admin SD IT Bawamai',
                'phone'     => '081299990001',
                'password'  => Hash::make('password'),
                'is_active' => true,
            ]
        );
    }
}
