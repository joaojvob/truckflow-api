<?php

namespace Database\Factories;

use App\Models\DriverProfile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverProfile>
 */
class DriverProfileFactory extends Factory
{
    protected $model = DriverProfile::class;

    public function definition(): array
    {
        return [
            'user_id'                  => User::factory()->driver(),
            'tenant_id'               => Tenant::factory(),
            'phone'                    => fake()->phoneNumber(),
            'cpf'                      => fake()->unique()->numerify('###.###.###-##'),
            'birth_date'               => fake()->dateTimeBetween('-50 years', '-21 years'),
            'cnh_number'               => fake()->unique()->numerify('###########'),
            'cnh_category'             => fake()->randomElement(['C', 'D', 'E']),
            'cnh_expiry'               => fake()->dateTimeBetween('+1 month', '+5 years'),
            'address'                  => fake()->streetAddress(),
            'city'                     => fake()->city(),
            'state'                    => fake()->randomElement(['SP', 'RJ', 'MG', 'PR', 'SC', 'RS', 'BA', 'GO', 'MT', 'MS']),
            'zip_code'                 => fake()->numerify('#####-###'),
            'emergency_contact_name'   => fake()->name(),
            'emergency_contact_phone'  => fake()->phoneNumber(),
            'is_available'             => true,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn () => ['is_available' => false]);
    }

    public function expiredCnh(): static
    {
        return $this->state(fn () => [
            'cnh_expiry' => fake()->dateTimeBetween('-2 years', '-1 day'),
        ]);
    }
}
