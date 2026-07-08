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
        Schema::table('classes', function (Blueprint $t) {
            $t->foreignId('school_id')->nullable()->after('program_id')->constrained('schools')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $t) {
            $t->dropForeign(['school_id']);
            $t->dropColumn('school_id');
        });
    }
};
