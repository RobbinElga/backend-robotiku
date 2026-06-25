<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('e_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->enum('semester', ['1', '2']);
            $table->integer('year');

            // Skill (A-E)
            $table->enum('skill_building', ['A', 'B', 'C', 'D', 'E']);
            $table->enum('skill_imagination', ['A', 'B', 'C', 'D', 'E']);
            $table->enum('skill_creativity', ['A', 'B', 'C', 'D', 'E']);
            $table->enum('skill_logic', ['A', 'B', 'C', 'D', 'E']);

            // Behaviour (A-E)
            $table->enum('behavior_punctual', ['A', 'B', 'C', 'D', 'E']);
            $table->enum('behavior_stay', ['A', 'B', 'C', 'D', 'E']);
            $table->enum('behavior_communication', ['A', 'B', 'C', 'D', 'E']);
            $table->enum('behavior_responsibility', ['A', 'B', 'C', 'D', 'E']);

            $table->text('comments')->nullable();
            $table->string('signature_image')->nullable();   // path TTD trainer
            $table->timestamps();

            // satu baris per siswa per semester per tahun
            $table->unique(['student_id', 'semester', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_reports');
    }
};
