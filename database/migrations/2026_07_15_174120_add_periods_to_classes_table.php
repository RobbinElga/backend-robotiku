<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $t) {
            $t->unsignedInteger('meetings_per_period')->default(4)->after('capacity'); // pertemuan = 1 periode
            $t->unsignedInteger('total_periods')->nullable()->after('meetings_per_period'); // jumlah periode (null = tak terbatas / legacy)
        });
    }
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $t) {
            $t->dropColumn(['meetings_per_period', 'total_periods']);
        });
    }
};
