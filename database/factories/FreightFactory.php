<?php

namespace Database\Factories;

use App\Models\Freight;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Freight>
 */
class FreightFactory extends Factory
{
    protected $model = Freight::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $latOrigin = fake()->latitude();
        $lngOrigin = fake()->longitude();
        
        $latDest = fake()->latitude();
        $lngDest = fake()->longitude();

        return [
            'tenant_id' => Tenant::factory(),
            'driver_id' => User::factory()->state(['role' => 'driver']),
            
            'cargo_name' => fake()->sentence(3),
            'weight'     => fake()->randomFloat(2, 1, 30), 
            'status'     => 'pending',
            
            'origin'      => DB::raw("ST_GeomFromText('POINT($lngOrigin $latOrigin)', 4326)"),
            'destination' => DB::raw("ST_GeomFromText('POINT($lngDest $latDest)', 4326)"),
            
            'checklist_completed' => false,
            'driver_rating'       => null,
            'driver_notes'        => null,
            'started_at'          => null,
            'completed_at'        => null,
        ];
    }

    /**
     * Estado para quando a viagem já está em trânsito.
     */
    public function inTransit(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'              => 'in_transit',
            'checklist_completed' => true,
            'started_at'          => now(),
        ]);
    }
}