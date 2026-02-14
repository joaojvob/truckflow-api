<?php

namespace Database\Factories;

use App\Enums\TrailerType;
use App\Enums\TruckStatus;
use App\Models\Tenant;
use App\Models\Trailer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trailer>
 */
class TrailerFactory extends Factory
{
    protected $model = Trailer::class;

    public function definition(): array
    {
        $brands = ['Randon', 'Librelato', 'Facchini', 'Guerra', 'Noma', 'Rodofort'];
        $type   = fake()->randomElement(TrailerType::cases());

        return [
            'tenant_id'  => Tenant::factory(),
            'driver_id'  => User::factory()->driver(),
            'plate'      => strtoupper(fake()->bothify('???-#?##')),
            'renavam'    => fake()->numerify('###########'),
            'type'       => $type,
            'brand'      => fake()->randomElement($brands),
            'model'      => fake()->word() . ' ' . fake()->numberBetween(100, 900),
            'year'       => fake()->numberBetween(2010, 2026),
            'axle_count' => fake()->randomElement([2, 3]),
            'max_weight' => $type->maxWeightTons(),
            'length'     => fake()->randomFloat(2, 12, 20),
            'hitch_type' => fake()->randomElement(['fifth_wheel', 'pintle', 'drawbar']),
            'status'     => TruckStatus::Available,
            'is_loaded'  => false,
        ];
    }

    public function refrigerated(): static
    {
        return $this->state(fn () => [
            'type'       => TrailerType::Refrigerated,
            'max_weight' => TrailerType::Refrigerated->maxWeightTons(),
        ]);
    }

    public function tanker(): static
    {
        return $this->state(fn () => [
            'type'       => TrailerType::Tanker,
            'max_weight' => TrailerType::Tanker->maxWeightTons(),
        ]);
    }

    public function flatbed(): static
    {
        return $this->state(fn () => [
            'type'       => TrailerType::Flatbed,
            'max_weight' => TrailerType::Flatbed->maxWeightTons(),
        ]);
    }

    public function loaded(): static
    {
        return $this->state(fn () => ['is_loaded' => true]);
    }
}
