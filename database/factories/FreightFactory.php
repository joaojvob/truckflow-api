<?php

namespace Database\Factories;

use App\Enums\FreightStatus;
use App\Enums\TrailerType;
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
        $latOrigin = fake()->latitude(-33.75, -3.38);
        $lngOrigin = fake()->longitude(-73.99, -34.79);

        $latDest = fake()->latitude(-33.75, -3.38);
        $lngDest = fake()->longitude(-73.99, -34.79);

        $distanceKm     = fake()->randomFloat(1, 50, 3000);
        $pricePerKm     = fake()->randomFloat(2, 2.50, 8.00);
        $pricePerTon    = fake()->randomFloat(2, 50, 300);
        $weight         = fake()->randomFloat(2, 1, 30);
        $tollCost       = fake()->randomFloat(2, 0, 500);
        $fuelCost       = fake()->randomFloat(2, 100, 3000);

        return [
            'tenant_id' => Tenant::factory(),
            'driver_id' => User::factory()->driver(),

            'cargo_name'             => fake()->randomElement([
                'Soja em grãos', 'Combustível', 'Eletrônicos', 'Material de construção',
                'Alimentos perecíveis', 'Madeira', 'Fertilizantes', 'Automóveis',
                'Gado vivo', 'Produtos químicos', 'Carga geral', 'Grãos de milho',
            ]),
            'cargo_description'      => fake()->optional()->sentence(),
            'weight'                 => $weight,
            'is_hazardous'           => fake()->boolean(15),
            'is_fragile'             => fake()->boolean(20),
            'requires_refrigeration' => fake()->boolean(10),
            'status'                 => FreightStatus::Pending,

            'origin'      => DB::raw("ST_GeomFromText('POINT($lngOrigin $latOrigin)', 4326)"),
            'destination' => DB::raw("ST_GeomFromText('POINT($lngDest $latDest)', 4326)"),

            'origin_address'      => fake()->address(),
            'destination_address' => fake()->address(),

            'required_trailer_type' => fake()->optional()->randomElement(TrailerType::cases()),
            'required_hitch_type'   => fake()->optional()->randomElement(['fifth_wheel', 'pintle', 'gooseneck']),

            'distance_km'      => $distanceKm,
            'estimated_hours'  => round($distanceKm / fake()->numberBetween(60, 90), 1),
            'price_per_km'     => $pricePerKm,
            'price_per_ton'    => $pricePerTon,
            'toll_cost'        => $tollCost,
            'fuel_cost'        => $fuelCost,
            'total_price'      => ($pricePerKm * $distanceKm) + ($pricePerTon * $weight) + $tollCost + $fuelCost,

            'checklist_completed' => false,
            'driver_rating'       => null,
            'driver_notes'        => null,
            'started_at'          => null,
            'completed_at'        => null,
            'deadline_at'         => fake()->optional()->dateTimeBetween('+1 day', '+30 days'),
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

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => FreightStatus::Cancelled,
        ]);
    }

    public function hazardous(): static
    {
        return $this->state(fn () => [
            'is_hazardous'    => true,
            'cargo_name'      => 'Produtos químicos perigosos',
        ]);
    }

    public function refrigerated(): static
    {
        return $this->state(fn () => [
            'requires_refrigeration' => true,
            'required_trailer_type'  => TrailerType::Refrigerated,
            'cargo_name'             => 'Alimentos perecíveis',
        ]);
    }
}