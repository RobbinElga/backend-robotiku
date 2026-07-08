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
        Schema::table('payments', function (Blueprint $t) {
            $t->foreignId('verified_by_school_admin')->nullable()->after('verified_by')
                ->constrained('school_admins')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            $t->dropForeign(['verified_by_school_admin']);
            $t->dropColumn('verified_by_school_admin');
        });
    }
};
