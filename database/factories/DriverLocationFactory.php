<?php

namespace Database\Factories;

use App\Models\DriverLocation;
use App\Models\Freight;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<DriverLocation>
 */
class DriverLocationFactory extends Factory
{
    protected $model = DriverLocation::class;

    public function definition(): array
    {
        $lat = fake()->latitude(-33.75, -3.38);
        $lng = fake()->longitude(-73.99, -34.79);

        return [
            'tenant_id'   => Tenant::factory(),
            'freight_id'  => Freight::factory()->inTransit(),
            'driver_id'   => User::factory()->driver(),
            'location'    => DB::raw("ST_GeomFromText('POINT($lng $lat)', 4326)"),
            'speed_kmh'   => fake()->randomFloat(2, 40, 90),
            'heading'     => fake()->randomFloat(2, 0, 359),
            'recorded_at' => fake()->dateTimeBetween('-2 hours', 'now'),
        ];
    }
}
