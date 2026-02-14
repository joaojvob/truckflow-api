<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Vinculação gestor ↔ motorista ────────────────────
        Schema::create('manager_driver', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('manager_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['manager_id', 'driver_id']);
        });

        // ─── Exame de doping ──────────────────────────────────
        Schema::create('doping_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('freight_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');

            $table->string('file_path')->comment('Caminho do arquivo enviado');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reviewer_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // ─── Notificações do sistema ──────────────────────────
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // ─── Novos status e campos no frete ───────────────────
        Schema::table('freights', function (Blueprint $table) {
            // Resposta do motorista ao frete
            $table->enum('driver_response', ['pending', 'accepted', 'rejected'])
                ->default('pending')
                ->after('status')
                ->comment('Resposta do motorista à atribuição');

            $table->text('rejection_reason')
                ->nullable()
                ->after('driver_response')
                ->comment('Motivo da recusa pelo motorista');

            $table->timestamp('driver_responded_at')
                ->nullable()
                ->after('rejection_reason');

            // Aprovação do gestor (doping + checklist)
            $table->boolean('doping_approved')
                ->default(false)
                ->after('checklist_completed')
                ->comment('Exame de doping aprovado pelo gestor');

            $table->boolean('manager_approved')
                ->default(false)
                ->after('doping_approved')
                ->comment('Gestor liberou a viagem');

            $table->foreignId('approved_by')
                ->nullable()
                ->after('manager_approved')
                ->constrained('users')
                ->onDelete('set null');

            $table->timestamp('approved_at')
                ->nullable()
                ->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('freights', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'driver_response', 'rejection_reason', 'driver_responded_at',
                'doping_approved', 'manager_approved', 'approved_by', 'approved_at',
            ]);
        });

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('doping_tests');
        Schema::dropIfExists('manager_driver');
    }
};
