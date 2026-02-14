<?php

namespace Database\Factories;

use App\Models\Freight;
use App\Models\Incident;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Incident>
 */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        $lat = fake()->latitude();
        $lng = fake()->longitude();

        return [
            'tenant_id'   => Tenant::factory(),
            'freight_id'  => Freight::factory(),
            'user_id'     => User::factory(),
            'type'        => fake()->randomElement(['breakdown', 'accident', 'robbery', 'sos']),
            'description' => fake()->sentence(),
            'location'    => DB::raw("ST_GeomFromText('POINT($lng $lat)', 4326)"),
        ];
    }

    /**
     * Estado para incidentes do tipo SOS.
     */
    public function sos(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'sos',
        ]);
    }
}
