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
        // 1) longgarkan enum agar bisa memetakan data lama
        DB::statement("ALTER TABLE students MODIFY status ENUM('aktif','cuti','berhenti','nonaktif','lulus') NOT NULL DEFAULT 'aktif'");
        // 2) petakan 'berhenti' → 'nonaktif'
        DB::table('students')->where('status', 'berhenti')->update(['status' => 'nonaktif']);
        // 3) kunci enum final
        DB::statement("ALTER TABLE students MODIFY status ENUM('aktif','nonaktif','lulus','cuti') NOT NULL DEFAULT 'aktif'");

        Schema::table('students', function (Blueprint $t) {
            $t->unsignedInteger('period_quota')->nullable()->after('registration_type'); // instansi: dari MoU; mandiri: null (unlimited)
            $t->date('joined_at')->nullable()->after('period_quota');                     // basis hitung periode
        });
    }
    public function down(): void
    {
        Schema::table('students', fn(Blueprint $t) => $t->dropColumn(['period_quota', 'joined_at']));
        DB::statement("ALTER TABLE students MODIFY status ENUM('aktif','cuti','berhenti') NOT NULL DEFAULT 'aktif'");
    }
};
