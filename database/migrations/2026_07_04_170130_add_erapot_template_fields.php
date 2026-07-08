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
        Schema::table('e_reports', function (Blueprint $t) {
            $t->json('topics')->nullable()->after('comments');            // [{topic, activity}]
            $t->string('report_place', 80)->nullable()->after('topics');   // "Pontianak"
            $t->date('report_date')->nullable()->after('report_place');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('signature_image')->nullable()->after('remember_token'); // TTD trainer
        });
    }
    public function down(): void
    {
        Schema::table('e_reports', fn(Blueprint $t) => $t->dropColumn(['topics', 'report_place', 'report_date']));
        Schema::table('users', fn(Blueprint $t) => $t->dropColumn('signature_image'));
    }
};
