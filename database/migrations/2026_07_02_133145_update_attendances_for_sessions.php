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
        Schema::table('attendances', function (Blueprint $t) {
            $t->foreignId('session_id')->nullable()->after('class_id')->constrained('class_sessions')->nullOnDelete();
            $t->enum('score', ['A', 'B', 'C', 'D', 'E'])->nullable()->after('status');
        });

        // status: hadir|izin|tidak_hadir → hadir|izin|sakit|tanpa_keterangan
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE attendances MODIFY status ENUM('hadir','izin','tidak_hadir','sakit','tanpa_keterangan') NOT NULL");
        }
        DB::table('attendances')->where('status', 'tidak_hadir')->update(['status' => 'tanpa_keterangan']);
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE attendances MODIFY status ENUM('hadir','izin','sakit','tanpa_keterangan') NOT NULL");
        }
    }
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $t) {
            $t->dropForeign(['session_id']);
            $t->dropColumn(['session_id', 'score']);
        });
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE attendances MODIFY status ENUM('hadir','izin','tidak_hadir') NOT NULL");
        }
    }
};
