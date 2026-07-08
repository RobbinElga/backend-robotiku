<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_settlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $t->unsignedBigInteger('gross_amount');          // total invoice
            $t->decimal('commission_percent', 5, 2);
            $t->unsignedBigInteger('commission_amount');     // jatah sekolah
            $t->unsignedBigInteger('net_amount');            // disetor ke Robotiku
            $t->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $t->string('proof_file');
            $t->enum('status', ['menunggu_verifikasi', 'diverifikasi', 'ditolak'])->default('menunggu_verifikasi');
            $t->foreignId('created_by')->nullable()->constrained('school_admins')->nullOnDelete();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
        });

        Schema::create('school_settlement_invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_settlement_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $t->unique(['school_settlement_id', 'invoice_id'], 'ssi_settlement_invoice_unique'); // ← nama pendek
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('school_settlement_invoices');
        Schema::dropIfExists('school_settlements');
    }
};
