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
        Schema::create('periods', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->enum('scope', ['sekolah', 'mandiri']);
            $t->foreignId('school_id')->nullable()->constrained('schools')->cascadeOnDelete(); // wajib jika scope=sekolah
            $t->unsignedInteger('number')->default(1);   // urutan periode (1,2,3…)
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
