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
        Schema::table('class_sessions', function (Blueprint $t) {
            $t->foreignId('period_id')->nullable()->after('trainer_id')->constrained('periods')->nullOnDelete();
            $t->unsignedTinyInteger('week')->nullable()->after('period_id'); // 1..4
        });
    }
    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $t) {
            $t->dropForeign(['period_id']);
            $t->dropColumn(['period_id', 'week']);
        });
    }
};
