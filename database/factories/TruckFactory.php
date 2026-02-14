<?php

namespace Database\Factories;

use App\Enums\TruckStatus;
use App\Models\Tenant;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Truck>
 */
class TruckFactory extends Factory
{
    protected $model = Truck::class;

    public function definition(): array
    {
        $brands = [
            'Scania'   => ['R 450', 'R 500', 'R 540', 'S 500', 'P 360'],
            'Volvo'    => ['FH 460', 'FH 540', 'FM 380', 'VM 330'],
            'Mercedes' => ['Actros 2651', 'Actros 2546', 'Axor 2544'],
            'DAF'      => ['XF 530', 'XF 480', 'CF 410'],
            'MAN'      => ['TGX 29.480', 'TGX 33.480', 'TGS 28.440'],
            'Iveco'    => ['S-Way 480', 'Hi-Way 440', 'Tector 240'],
        ];

        $brand  = fake()->randomElement(array_keys($brands));
        $model  = fake()->randomElement($brands[$brand]);
        $hitch  = fake()->randomElement(['fifth_wheel', 'pintle', 'drawbar']);

        return [
            'tenant_id'         => Tenant::factory(),
            'driver_id'         => User::factory()->driver(),
            'plate'             => strtoupper(fake()->bothify('???-#?##')),
            'renavam'           => fake()->numerify('###########'),
            'brand'             => $brand,
            'model'             => $model,
            'year'              => fake()->numberBetween(2015, 2026),
            'color'             => fake()->randomElement(['Branco', 'Prata', 'Preto', 'Vermelho', 'Azul']),
            'axle_count'        => fake()->randomElement([2, 3, 4, 6]),
            'max_weight'        => fake()->randomFloat(2, 15, 45),
            'has_trailer_hitch' => true,
            'hitch_type'        => $hitch,
            'status'            => TruckStatus::Available,
            'odometer'          => fake()->numberBetween(0, 500000),
        ];
    }

    public function inUse(): static
    {
        return $this->state(fn () => ['status' => TruckStatus::InUse]);
    }

    public function maintenance(): static
    {
        return $this->state(fn () => ['status' => TruckStatus::Maintenance]);
    }

    public function withoutHitch(): static
    {
        return $this->state(fn () => [
            'has_trailer_hitch' => false,
            'hitch_type'        => null,
        ]);
    }
}
