<?php

namespace Database\Seeders;

use App\Enums\FreightStatus;
use App\Enums\TrailerType;
use App\Enums\TruckStatus;
use App\Models\DriverProfile;
use App\Models\Freight;
use App\Models\Tenant;
use App\Models\Trailer;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ── Tenant: Transportadora Alpha ─────────────────────────
        $tenantAlpha = Tenant::create([
            'name'     => 'Transportadora Alpha',
            'slug'     => 'transportadora-alpha',
            'settings' => ['max_drivers' => 50],
        ]);

        // Admin
        $admin = User::factory()->admin()->create([
            'name'      => 'Admin Alpha',
            'email'     => 'admin@alpha.com',
            'tenant_id' => $tenantAlpha->id,
        ]);

        // Managers
        $manager = User::factory()->manager()->create([
            'name'      => 'Gerente Alpha',
            'email'     => 'gerente@alpha.com',
            'tenant_id' => $tenantAlpha->id,
        ]);

        // Drivers com perfil completo
        $drivers = User::factory(5)->driver()->create([
            'tenant_id' => $tenantAlpha->id,
        ]);

        foreach ($drivers as $driver) {
            DriverProfile::factory()->create([
                'user_id'   => $driver->id,
                'tenant_id' => $tenantAlpha->id,
            ]);

            // Cada motorista com 1 caminhão
            $truck = Truck::factory()->create([
                'tenant_id' => $tenantAlpha->id,
                'driver_id' => $driver->id,
            ]);

            // 60% chance de ter reboque
            if (fake()->boolean(60)) {
                Trailer::factory()->create([
                    'tenant_id' => $tenantAlpha->id,
                    'driver_id' => $driver->id,
                ]);
            }
        }

        // Fretes variados
        foreach ($drivers as $driver) {
            // 2-3 fretes por motorista
            $count = fake()->numberBetween(2, 3);
            Freight::factory($count)->create([
                'tenant_id'  => $tenantAlpha->id,
                'driver_id'  => $driver->id,
                'created_by' => $manager->id,
            ]);
        }

        // Um frete em trânsito
        Freight::factory()->inTransit()->create([
            'tenant_id'  => $tenantAlpha->id,
            'driver_id'  => $drivers->first()->id,
            'created_by' => $manager->id,
        ]);

        // Um frete completo
        Freight::factory()->completed()->create([
            'tenant_id'  => $tenantAlpha->id,
            'driver_id'  => $drivers->last()->id,
            'created_by' => $admin->id,
            'driver_rating' => 5,
            'driver_notes'  => 'Entrega realizada sem problemas.',
        ]);

        // ── Tenant: Logística Beta ───────────────────────────────
        $tenantBeta = Tenant::create([
            'name'     => 'Logística Beta',
            'slug'     => 'logistica-beta',
            'settings' => ['max_drivers' => 20],
        ]);

        User::factory()->admin()->create([
            'name'      => 'Admin Beta',
            'email'     => 'admin@beta.com',
            'tenant_id' => $tenantBeta->id,
        ]);

        $betaDrivers = User::factory(2)->driver()->create([
            'tenant_id' => $tenantBeta->id,
        ]);

        foreach ($betaDrivers as $driver) {
            DriverProfile::factory()->create([
                'user_id'   => $driver->id,
                'tenant_id' => $tenantBeta->id,
            ]);

            Truck::factory()->create([
                'tenant_id' => $tenantBeta->id,
                'driver_id' => $driver->id,
            ]);
        }

        Freight::factory(3)->create([
            'tenant_id' => $tenantBeta->id,
            'driver_id' => $betaDrivers->first()->id,
        ]);

        $this->command->info('✅ Seed completo: 2 empresas, usuários, veículos e fretes criados.');
    }
}
