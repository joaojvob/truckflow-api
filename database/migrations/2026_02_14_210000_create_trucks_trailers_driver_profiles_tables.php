<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Perfil do Motorista ──────────────────────────────────────
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            // Dados pessoais
            $table->string('phone')->nullable();
            $table->string('cpf', 14)->unique()->nullable();
            $table->date('birth_date')->nullable();

            // CNH
            $table->string('cnh_number', 20)->unique()->nullable();
            $table->string('cnh_category', 5)->nullable(); // A, B, C, D, E
            $table->date('cnh_expiry')->nullable();

            // Endereço
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip_code', 10)->nullable();

            // Contato de emergência
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();

            // Status
            $table->boolean('is_available')->default(true);

            $table->timestamps();

            $table->unique(['user_id', 'tenant_id']);
        });

        // ─── Caminhões ────────────────────────────────────────────────
        Schema::create('trucks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('set null');

            // Identificação
            $table->string('plate', 10)->unique();
            $table->string('renavam', 20)->nullable();

            // Veículo
            $table->string('brand');        // Scania, Volvo, Mercedes, DAF, MAN
            $table->string('model');        // R 450, FH 540, Actros, XF
            $table->integer('year');
            $table->string('color')->nullable();

            // Capacidade
            $table->integer('axle_count')->default(3);          // Número de eixos (2, 3, 4, 6)
            $table->decimal('max_weight', 8, 2)->default(0);    // PBT em toneladas
            $table->boolean('has_trailer_hitch')->default(false); // Tem engate para reboque?
            $table->string('hitch_type')->nullable();            // Tipo de engate: fifth_wheel, pintle, drawbar

            // Status
            $table->string('status')->default('available');      // available, in_use, maintenance, inactive
            $table->integer('odometer')->default(0);             // km rodados

            $table->timestamps();
        });

        // ─── Reboques / Engates ───────────────────────────────────────
        Schema::create('trailers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('set null');

            // Identificação
            $table->string('plate', 10)->unique();
            $table->string('renavam', 20)->nullable();

            // Tipo e capacidade (inspirado no ETS2)
            $table->string('type');                             // flatbed, refrigerated, dry_van, tanker, sider, hopper...
            $table->string('brand')->nullable();                // Randon, Librelato, Facchini, Guerra
            $table->string('model')->nullable();
            $table->integer('year')->nullable();
            $table->integer('axle_count')->default(3);
            $table->decimal('max_weight', 8, 2)->default(0);    // Capacidade máxima em toneladas
            $table->decimal('length', 5, 2)->nullable();        // Comprimento em metros

            // Engate
            $table->string('hitch_type');                       // fifth_wheel, pintle, drawbar (tipo de acoplamento)

            // Status
            $table->string('status')->default('available');
            $table->boolean('is_loaded')->default(false);       // Carregado ou vazio?

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trailers');
        Schema::dropIfExists('trucks');
        Schema::dropIfExists('driver_profiles');
    }
};
