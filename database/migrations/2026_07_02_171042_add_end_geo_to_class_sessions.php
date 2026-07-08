<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // add_end_geo_to_class_sessions
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $t) {
            $t->decimal('end_latitude', 10, 7)->nullable()->after('start_photo');
            $t->decimal('end_longitude', 10, 7)->nullable()->after('end_latitude');
            $t->string('end_photo')->nullable()->after('end_longitude');
        });
    }
    public function down(): void
    {
        Schema::table('class_sessions', fn(Blueprint $t) => $t->dropColumn(['end_latitude', 'end_longitude', 'end_photo']));
    }
};
