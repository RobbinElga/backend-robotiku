<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Super Admin',    'email' => 'superadmin@robotiku.id', 'role' => 'super_admin'],
            ['name' => 'Admin',          'email' => 'admin@robotiku.id',      'role' => 'admin'],
            ['name' => 'Marketing',      'email' => 'marketing@robotiku.id',  'role' => 'marketing'],
            ['name' => 'Trainer',        'email' => 'trainer@robotiku.id',    'role' => 'trainer'],
            ['name' => 'Admin Keuangan', 'email' => 'keuangan@robotiku.id',   'role' => 'admin_keuangan'],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'role' => $u['role'], 'password' => Hash::make('password'), 'is_active' => true]
            );
        }
    }
}
