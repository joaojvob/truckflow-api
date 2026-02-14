<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waypoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('freight_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');

            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['fuel_stop', 'rest_stop', 'toll', 'delivery_point', 'weigh_station', 'custom'])
                  ->default('custom');
            $table->geography('location', 'point', 4326);
            $table->string('address')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('mandatory')->default(false);
            $table->integer('estimated_stop_minutes')->nullable();

            // Tracking: motorista passou por este ponto?
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('departed_at')->nullable();

            $table->timestamps();

            $table->index(['freight_id', 'order']);
        });

        // Adicionar enforce_route ao freights
        Schema::table('freights', function (Blueprint $table) {
            $table->boolean('enforce_route')->default(false)->after('destination_address');
        });
    }

    public function down(): void
    {
        Schema::table('freights', function (Blueprint $table) {
            $table->dropColumn('enforce_route');
        });

        Schema::dropIfExists('waypoints');
    }
};
