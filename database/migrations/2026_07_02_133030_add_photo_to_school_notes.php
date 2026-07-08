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
        Schema::table('school_notes', function (Blueprint $t) {
            $t->string('photo')->nullable()->after('note');
            $t->enum('kind', ['audit', 'pertemuan'])->default('audit')->after('photo');
        });
    }
    public function down(): void
    {
        Schema::table('school_notes', fn(Blueprint $t) => $t->dropColumn(['photo', 'kind']));
    }
};
