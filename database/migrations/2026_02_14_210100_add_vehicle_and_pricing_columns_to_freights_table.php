<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freights', function (Blueprint $table) {
            // ─── Veículos vinculados ──────────────────────────
            $table->foreignId('truck_id')
                ->nullable()
                ->after('driver_id')
                ->constrained()
                ->onDelete('set null');

            $table->foreignId('trailer_id')
                ->nullable()
                ->after('truck_id')
                ->constrained()
                ->onDelete('set null');

            // ─── Requisitos do frete ──────────────────────────
            $table->string('required_trailer_type')
                ->nullable()
                ->after('destination')
                ->comment('Tipo de engate/reboque exigido pelo frete');

            $table->string('required_hitch_type')
                ->nullable()
                ->after('required_trailer_type')
                ->comment('Tipo de acoplamento exigido');

            // ─── Detalhes da carga ────────────────────────────
            $table->text('cargo_description')
                ->nullable()
                ->after('cargo_name');

            $table->boolean('is_hazardous')
                ->default(false)
                ->after('weight')
                ->comment('Carga perigosa?');

            $table->boolean('is_fragile')
                ->default(false)
                ->after('is_hazardous')
                ->comment('Carga frágil?');

            $table->boolean('requires_refrigeration')
                ->default(false)
                ->after('is_fragile');

            // ─── Endereços legíveis ───────────────────────────
            $table->string('origin_address')->nullable()->after('origin');
            $table->string('destination_address')->nullable()->after('destination');

            // ─── Distância e tempo ────────────────────────────
            $table->decimal('distance_km', 10, 2)
                ->nullable()
                ->after('destination_address')
                ->comment('Distância estimada em km');

            $table->decimal('estimated_hours', 6, 1)
                ->nullable()
                ->after('distance_km')
                ->comment('Tempo estimado em horas');

            // ─── Preço e financeiro ───────────────────────────
            $table->decimal('price_per_km', 8, 4)
                ->nullable()
                ->after('estimated_hours')
                ->comment('Preço por km rodado');

            $table->decimal('price_per_ton', 8, 2)
                ->nullable()
                ->after('price_per_km')
                ->comment('Preço por tonelada');

            $table->decimal('toll_cost', 10, 2)
                ->default(0)
                ->after('price_per_ton')
                ->comment('Custo estimado de pedágio');

            $table->decimal('fuel_cost', 10, 2)
                ->default(0)
                ->after('toll_cost')
                ->comment('Custo estimado de combustível');

            $table->decimal('total_price', 12, 2)
                ->nullable()
                ->after('fuel_cost')
                ->comment('Preço total do frete');

            // ─── Gestão ───────────────────────────────────────
            $table->foreignId('created_by')
                ->nullable()
                ->after('completed_at')
                ->constrained('users')
                ->onDelete('set null')
                ->comment('Gestor que criou o frete');

            $table->timestamp('deadline_at')
                ->nullable()
                ->after('completed_at')
                ->comment('Prazo de entrega');
        });
    }

    public function down(): void
    {
        Schema::table('freights', function (Blueprint $table) {
            $table->dropForeign(['truck_id']);
            $table->dropForeign(['trailer_id']);
            $table->dropForeign(['created_by']);

            $table->dropColumn([
                'truck_id', 'trailer_id',
                'required_trailer_type', 'required_hitch_type',
                'cargo_description', 'is_hazardous', 'is_fragile', 'requires_refrigeration',
                'origin_address', 'destination_address',
                'distance_km', 'estimated_hours',
                'price_per_km', 'price_per_ton', 'toll_cost', 'fuel_cost', 'total_price',
                'created_by', 'deadline_at',
            ]);
        });
    }
};
