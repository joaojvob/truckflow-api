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
        Schema::create('freights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('driver_id')->constrained('users');
            $table->string('cargo_name');
            $table->decimal('weight', 8, 2);
            
            $table->enum('status', ['pending', 'assigned', 'accepted', 'ready', 'in_transit', 'completed', 'cancelled', 'rejected'])->default('pending');
            
            $table->geography('origin', 'point', 4326);
            $table->geography('destination', 'point', 4326);
            
            $table->boolean('checklist_completed')->default(false);
            $table->integer('driver_rating')->nullable();
            $table->text('driver_notes')->nullable();
            
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
        Schema::dropIfExists('freights');
    }
};
