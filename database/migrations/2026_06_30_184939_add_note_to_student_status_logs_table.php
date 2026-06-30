<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_status_logs', function (Blueprint $t) {
            $t->text('note')->nullable()->after('new_status');
        });
    }
    public function down(): void
    {
        Schema::table('student_status_logs', fn(Blueprint $t) => $t->dropColumn('note'));
    }
};
