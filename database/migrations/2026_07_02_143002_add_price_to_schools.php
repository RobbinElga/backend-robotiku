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
            $t->unsignedInteger('registration_fee')->default(0)->after('commission_percent');
            $t->unsignedInteger('price_per_cycle')->default(0)->after('registration_fee');
        });
    }
    public function down(): void
    {
        Schema::table('schools', fn(Blueprint $t) => $t->dropColumn(['registration_fee', 'price_per_cycle']));
    }
};
