<?php

namespace Database\Factories;

use App\Enums\WaypointType;
use App\Models\Freight;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Waypoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Waypoint>
 */
class WaypointFactory extends Factory
{
    protected $model = Waypoint::class;

    public function definition(): array
    {
        $lat = fake()->latitude(-33.75, -3.38);
        $lng = fake()->longitude(-73.99, -34.79);

        return [
            'tenant_id'              => Tenant::factory(),
            'freight_id'             => Freight::factory(),
            'created_by'             => User::factory()->manager(),
            'name'                   => fake()->randomElement([
                'Posto Shell BR-116', 'Parada de descanso', 'Pedágio Ecovia',
                'Balança DNIT km 340', 'Posto BR Distribuidora', 'Área de descanso',
            ]),
            'description'            => fake()->optional()->sentence(),
            'type'                   => fake()->randomElement(WaypointType::cases()),
            'location'               => DB::raw("ST_GeomFromText('POINT($lng $lat)', 4326)"),
            'address'                => fake()->optional()->address(),
            'order'                  => fake()->numberBetween(0, 10),
            'mandatory'              => fake()->boolean(30),
            'estimated_stop_minutes' => fake()->optional()->numberBetween(10, 120),
        ];
    }

    public function fuelStop(): static
    {
        return $this->state(fn () => [
            'type' => WaypointType::FuelStop,
            'name' => 'Posto de Combustível ' . fake()->company(),
        ]);
    }

    public function restStop(): static
    {
        return $this->state(fn () => [
            'type'                   => WaypointType::RestStop,
            'name'                   => 'Ponto de Descanso ' . fake()->city(),
            'estimated_stop_minutes' => fake()->numberBetween(30, 480),
        ]);
    }

    public function mandatory(): static
    {
        return $this->state(fn () => [
            'mandatory' => true,
        ]);
    }
}
