<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_code')->unique();
            $table->string('name');
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['L', 'P']);
            $table->string('shirt_size')->nullable();
            $table->string('school_origin')->nullable();
            $table->string('school_grade')->nullable();
            $table->text('address')->nullable();
            $table->text('allergy_notes')->nullable();
            $table->boolean('photo_permission')->default(false);
            $table->foreignId('parent_id')->nullable()->constrained('parents')->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->enum('status', ['aktif', 'cuti', 'berhenti'])->default('aktif');
            $table->enum('registration_type', ['mandiri', 'instansi']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
