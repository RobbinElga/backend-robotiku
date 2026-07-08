<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */ public function up(): void
    {
        Schema::table('users', fn(Blueprint $t) => $t->string('phone')->nullable()->after('email'));
    }
    public function down(): void
    {
        Schema::table('users', fn(Blueprint $t) => $t->dropColumn('phone'));
    }
};
