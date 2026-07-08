<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $t) {
            $t->boolean('is_verified')->default(false)->after('status');
        });
        // siswa yang sudah ada dianggap sudah terverifikasi agar tetap tampil
        DB::table('students')->update(['is_verified' => true]);
    }
    public function down(): void
    {
        Schema::table('students', fn(Blueprint $t) => $t->dropColumn('is_verified'));
    }
};
