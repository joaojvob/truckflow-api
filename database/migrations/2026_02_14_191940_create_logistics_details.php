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
        Schema::create('checklists', function (Blueprint $table) {
        $table->id();
        $table->foreignId('freight_id')->constrained();
        $table->json('items'); // Ex: {"pneus": true, "oleo": true, "luzes": false}
        $table->timestamps();
    });

    Schema::create('incidents', function (Blueprint $table) {
        $table->id();
        $table->foreignId('freight_id')->constrained();
        $table->enum('type', ['breakdown', 'accident', 'robbery', 'sos']); // O botão de SOS
        $table->text('description')->nullable();
        $table->geography('location', 'point', 4326); // Onde aconteceu
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('checklists');
    }
};
