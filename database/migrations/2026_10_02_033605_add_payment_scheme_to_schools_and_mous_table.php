<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('payment_scheme', 30)->default('v1_direct')->after('self_managed');
        });

        Schema::table('mous', function (Blueprint $table) {
            $table->string('payment_scheme', 30)->default('v1_direct')->after('self_managed');
        });

        DB::table('schools')->where('self_managed', true)->update(['payment_scheme' => 'v3_collective']);
        DB::table('schools')->where(function ($q) {
            $q->where('self_managed', false)->orWhereNull('self_managed');
        })->update(['payment_scheme' => 'v1_direct']);

        DB::table('mous')->where('self_managed', true)->update(['payment_scheme' => 'v3_collective']);
        DB::table('mous')->where(function ($q) {
            $q->where('self_managed', false)->orWhereNull('self_managed');
        })->update(['payment_scheme' => 'v1_direct']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('payment_scheme');
        });

        Schema::table('mous', function (Blueprint $table) {
            $table->dropColumn('payment_scheme');
        });
    }
};
