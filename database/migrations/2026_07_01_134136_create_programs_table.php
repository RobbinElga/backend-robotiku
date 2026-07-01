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
        Schema::create('programs', function (Blueprint $t) {
            $t->id();
            $t->string('name');                                  // mis. "Robo Kids", "IoT Junior"
            $t->string('level')->nullable();                     // jenjang, mis. "Usia 5-7 Tahun"
            $t->unsignedInteger('registration_fee')->default(0);
            $t->unsignedInteger('price_per_cycle')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
