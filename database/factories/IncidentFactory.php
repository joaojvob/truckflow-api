<?php

namespace Database\Factories;

use App\Enums\IncidentType;
use App\Models\Freight;
use App\Models\Incident;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Incident>
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
            'type'        => fake()->randomElement(IncidentType::cases()),
            'description' => fake()->sentence(),
            'location'    => DB::raw("ST_GeomFromText('POINT($lng $lat)', 4326)"),
        ];
    }

    public function sos(): static
    {
        return $this->state(fn () => ['type' => IncidentType::Sos]);
    }

    public function breakdown(): static
    {
        return $this->state(fn () => ['type' => IncidentType::Breakdown]);
    }
}
