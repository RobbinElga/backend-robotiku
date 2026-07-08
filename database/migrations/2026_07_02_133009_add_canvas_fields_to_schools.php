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
        Schema::table('schools', function (Blueprint $t) {
            $t->decimal('commission_percent', 5, 2)->default(10)->after('bank_account'); // % jatah sekolah
            $t->string('qris_image')->nullable()->after('commission_percent');
            $t->string('photo')->nullable()->after('qris_image');
        });
    }
    public function down(): void
    {
        Schema::table('schools', fn(Blueprint $t) => $t->dropColumn(['commission_percent', 'qris_image', 'photo']));
    }
};
