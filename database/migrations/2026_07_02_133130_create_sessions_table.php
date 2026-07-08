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
        Schema::create('class_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $t->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $t->decimal('start_latitude', 10, 7);
            $t->decimal('start_longitude', 10, 7);
            $t->string('start_photo');
            $t->timestamp('started_at');
            $t->timestamp('ended_at')->nullable();
            $t->enum('status', ['started', 'ended'])->default('started');
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
