<?php

namespace Database\Factories;

use App\Enums\FreightStatus;
use App\Models\Freight;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Freight>
 */
class FreightFactory extends Factory
{
    protected $model = Freight::class;

    public function definition(): array
    {
        $latOrigin = fake()->latitude();
        $lngOrigin = fake()->longitude();

        $latDest = fake()->latitude();
        $lngDest = fake()->longitude();

        return [
            'tenant_id' => Tenant::factory(),
            'driver_id' => User::factory()->driver(),

            'cargo_name' => fake()->sentence(3),
            'weight'     => fake()->randomFloat(2, 1, 30),
            'status'     => FreightStatus::Pending,

            'origin'      => DB::raw("ST_GeomFromText('POINT($lngOrigin $latOrigin)', 4326)"),
            'destination' => DB::raw("ST_GeomFromText('POINT($lngDest $latDest)', 4326)"),

            'checklist_completed' => false,
            'driver_rating'       => null,
            'driver_notes'        => null,
            'started_at'          => null,
            'completed_at'        => null,
        ];
    }

    public function inTransit(): static
    {
        return $this->state(fn () => [
            'status'              => FreightStatus::InTransit,
            'checklist_completed' => true,
            'started_at'          => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status'              => FreightStatus::Completed,
            'checklist_completed' => true,
            'started_at'          => now()->subHours(5),
            'completed_at'        => now(),
        ]);
    }
}