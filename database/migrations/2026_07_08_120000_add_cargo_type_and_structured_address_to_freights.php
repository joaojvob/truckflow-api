<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freights', function (Blueprint $table) {
            // Tipo de carga (substitui o "nome" genérico)
            $table->string('cargo_type')->nullable()->after('cargo_name');

            // ─── Endereço estruturado de origem ───────────────
            $table->string('origin_cep', 9)->nullable()->after('origin_address');
            $table->string('origin_street')->nullable()->after('origin_cep');
            $table->string('origin_number', 30)->nullable()->after('origin_street');
            $table->string('origin_complement')->nullable()->after('origin_number');
            $table->string('origin_neighborhood')->nullable()->after('origin_complement');
            $table->string('origin_city')->nullable()->after('origin_neighborhood');
            $table->string('origin_state', 2)->nullable()->after('origin_city');

            // ─── Endereço estruturado de destino ──────────────
            $table->string('destination_cep', 9)->nullable()->after('destination_address');
            $table->string('destination_street')->nullable()->after('destination_cep');
            $table->string('destination_number', 30)->nullable()->after('destination_street');
            $table->string('destination_complement')->nullable()->after('destination_number');
            $table->string('destination_neighborhood')->nullable()->after('destination_complement');
            $table->string('destination_city')->nullable()->after('destination_neighborhood');
            $table->string('destination_state', 2)->nullable()->after('destination_city');
        });

        // cargo_name deixa de ser obrigatório (tipo passa a ser o principal)
        Schema::table('freights', function (Blueprint $table) {
            $table->string('cargo_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('freights', function (Blueprint $table) {
            $table->dropColumn([
                'cargo_type',
                'origin_cep', 'origin_street', 'origin_number', 'origin_complement',
                'origin_neighborhood', 'origin_city', 'origin_state',
                'destination_cep', 'destination_street', 'destination_number', 'destination_complement',
                'destination_neighborhood', 'destination_city', 'destination_state',
            ]);
        });
    }
};
