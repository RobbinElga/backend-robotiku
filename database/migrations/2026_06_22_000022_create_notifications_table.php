<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->enum('recipient_type', ['user', 'school_admin']);
            $table->unsignedBigInteger('recipient_id');
            $table->string('title');
            $table->text('message');
            $table->enum('type', ['pendaftaran_baru', 'pembayaran_baru', 'tagihan_jatuh_tempo']);
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
