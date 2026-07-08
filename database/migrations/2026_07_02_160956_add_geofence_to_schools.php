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
        Schema::table('schools', function (Blueprint $t) {
            $t->decimal('latitude', 10, 7)->nullable()->after('address');
            $t->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $t->unsignedInteger('geofence_radius')->default(500)->after('longitude'); // meter
        });
    }
    public function down(): void
    {
        Schema::table('schools', fn(Blueprint $t) => $t->dropColumn(['latitude', 'longitude', 'geofence_radius']));
    }
};
