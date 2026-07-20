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
        Schema::table('mous', function (Blueprint $t) {
            $t->boolean('self_managed')->default(false)->after('periods');
        });
        Schema::table('schools', function (Blueprint $t) {
            $t->boolean('self_managed')->default(false)->after('is_mou');
        });
    }
    public function down(): void
    {
        Schema::table('mous', fn(Blueprint $t) => $t->dropColumn('self_managed'));
        Schema::table('schools', fn(Blueprint $t) => $t->dropColumn('self_managed'));
    }
};
