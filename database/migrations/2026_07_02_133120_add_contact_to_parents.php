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
        Schema::table('parents', function (Blueprint $t) {
            $t->enum('greeting', ['ayah', 'bunda'])->nullable()->after('name');
            $t->string('phone_alt')->nullable()->after('phone');
        });
    }
    public function down(): void
    {
        Schema::table('parents', fn(Blueprint $t) => $t->dropColumn(['greeting', 'phone_alt']));
    }
};
