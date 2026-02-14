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
        Schema::create('jobs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained();
        $table->foreignId('driver_id')->constrained('users');
        $table->string('cargo_name');
        $table->decimal('weight', 8, 2); // Toneladas
        
        // Status do Ciclo de Vida
        $table->enum('status', ['pending', 'ready', 'in_transit', 'completed', 'cancelled'])->default('pending');
        
        // Localizações (Usando PostGIS)
        $table->geography('origin', 'point', 4326);
        $table->geography('destination', 'point', 4326);
        
        // Campos que você pediu:
        $table->boolean('checklist_completed')->default(false);
        $table->integer('driver_rating')->nullable(); // Nota da viagem
        $table->text('driver_notes')->nullable(); // Comentários do motorista
        
        $table->timestamp('started_at')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
