<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('billing_month_id')->nullable()->constrained('billing_months')->nullOnDelete();
            $table->decimal('base_amount', 12, 2);                    // = price_per_cycle
            $table->decimal('registration_fee', 12, 2)->nullable();  // hanya invoice pertama
            $table->decimal('discount_amount', 12, 2)->default(0);   // diskon utk registration_fee
            $table->decimal('total_amount', 12, 2);
            $table->date('due_date')->nullable();
            $table->enum('status', ['belum_bayar', 'menunggu_verifikasi', 'lunas'])->default('belum_bayar');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
