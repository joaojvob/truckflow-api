<?php

namespace Database\Seeders;

use App\Models\DriverLocation;
use App\Models\Freight;
use App\Models\Incident;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Waypoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    /**
     * Dados extras para testar Places, tracking GPS, relatórios e workflow.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'transportadora-alpha')->first();

        if (! $tenant) {
            $this->command->warn('Tenant transportadora-alpha não encontrado. Rode DatabaseSeeder antes.');

            return;
        }

        $manager = User::query()->where('email', 'gerente@alpha.com')->first();
        $driver  = User::query()->where('email', 'motorista@alpha.com')->first()
            ?? User::factory()->driver()->create([
                'name'      => 'João Motorista',
                'email'     => 'motorista@alpha.com',
                'tenant_id' => $tenant->id,
            ]);

        $manager?->drivers()->syncWithoutDetaching([
            $driver->id => ['tenant_id' => $tenant->id],
        ]);

        $this->seedCompletedFreightsForReports($tenant, $manager, $driver);
        $inTransitFreight = $this->seedInTransitFreightWithTracking($tenant, $manager, $driver);
        $this->seedWaypoints($inTransitFreight, $manager);
        $this->seedIncident($inTransitFreight, $driver);

        $this->command->newLine();
        $this->command->info('✅ DemoDataSeeder: dados de teste v2 criados.');
        $this->command->table(
            ['Conta', 'E-mail', 'Senha', 'Uso'],
            [
                ['Gestor', 'gerente@alpha.com', 'password', 'Relatórios, dashboard, places'],
                ['Motorista', 'motorista@alpha.com', 'password', 'Tracking GPS, workflow'],
                ['Admin', 'admin@alpha.com', 'password', 'Visão completa do tenant'],
            ]
        );
        $this->command->info("Frete em trânsito (tracking): ID {$inTransitFreight->id}");
    }

    private function seedCompletedFreightsForReports(Tenant $tenant, ?User $manager, User $driver): void
    {
        $managerId = $manager?->id ?? $driver->id;

        foreach (range(1, 6) as $i) {
            Freight::factory()->completed()->create([
                'tenant_id'    => $tenant->id,
                'driver_id'    => $driver->id,
                'created_by'   => $managerId,
                'total_price'  => fake()->randomFloat(2, 2500, 12000),
                'distance_km'  => fake()->randomFloat(1, 200, 1800),
                'completed_at' => now()->subDays($i * 2)->setHour(18),
                'cargo_name'   => fake()->randomElement([
                    'Soja em grãos', 'Combustível', 'Material de construção', 'Alimentos',
                ]),
            ]);
        }
    }

    private function seedInTransitFreightWithTracking(Tenant $tenant, ?User $manager, User $driver): Freight
    {
        $freight = Freight::factory()->inTransit()->create([
            'tenant_id'               => $tenant->id,
            'driver_id'               => $driver->id,
            'created_by'              => $manager?->id ?? $driver->id,
            'cargo_name'              => 'Carga demo — tracking GPS',
            'origin_address'          => 'São Paulo, SP',
            'destination_address'     => 'Curitiba, PR',
            'route_polyline'          => 'demo_polyline_sp_curitiba',
            'route_distance_meters'   => 408_000,
            'route_duration_seconds'  => 14_400,
            'route_calculated_at'     => now()->subHours(3),
        ]);

        // Trajeto aproximado SP → Curitiba (10 pontos)
        $points = [
            [-23.5505, -46.6333],
            [-23.4200, -46.9500],
            [-23.1000, -47.2000],
            [-22.7500, -47.4500],
            [-22.4000, -47.8000],
            [-22.0000, -48.2000],
            [-21.6000, -48.6000],
            [-21.2000, -49.0000],
            [-24.0000, -49.5000],
            [-25.4284, -49.2733],
        ];

        foreach ($points as $index => [$lat, $lng]) {
            DriverLocation::create([
                'tenant_id'   => $tenant->id,
                'freight_id'  => $freight->id,
                'driver_id'   => $driver->id,
                'location'    => DB::raw("ST_GeomFromText('POINT($lng $lat)', 4326)"),
                'speed_kmh'   => fake()->randomFloat(2, 55, 85),
                'heading'     => 180 + ($index * 5),
                'recorded_at' => now()->subMinutes((count($points) - $index) * 8),
            ]);
        }

        return $freight;
    }

    private function seedWaypoints(Freight $freight, ?User $manager): void
    {
        $creatorId = $manager?->id ?? $freight->created_by;

        Waypoint::factory()->fuelStop()->mandatory()->create([
            'tenant_id'  => $freight->tenant_id,
            'freight_id' => $freight->id,
            'created_by' => $creatorId,
            'order'      => 1,
        ]);

        Waypoint::factory()->restStop()->create([
            'tenant_id'  => $freight->tenant_id,
            'freight_id' => $freight->id,
            'created_by' => $creatorId,
            'order'      => 2,
        ]);
    }

    private function seedIncident(Freight $freight, User $driver): void
    {
        Incident::factory()->breakdown()->create([
            'tenant_id'   => $freight->tenant_id,
            'freight_id'  => $freight->id,
            'user_id'     => $driver->id,
            'description' => 'Pneu furado na BR-116 — aguardando socorro.',
            'location'    => DB::raw("ST_GeomFromText('POINT(-47.5 -22.8)', 4326)"),
        ]);
    }
}
