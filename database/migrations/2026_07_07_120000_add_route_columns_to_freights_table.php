<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freights', function (Blueprint $table) {
            $table->text('route_polyline')->nullable()->after('enforce_route');
            $table->unsignedInteger('route_distance_meters')->nullable()->after('route_polyline');
            $table->unsignedInteger('route_duration_seconds')->nullable()->after('route_distance_meters');
            $table->timestamp('route_calculated_at')->nullable()->after('route_duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('freights', function (Blueprint $table) {
            $table->dropColumn([
                'route_polyline',
                'route_distance_meters',
                'route_duration_seconds',
                'route_calculated_at',
            ]);
        });
    }
};
