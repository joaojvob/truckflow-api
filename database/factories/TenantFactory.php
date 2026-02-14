<?php

namespace Database\Factories;

use App\Models\Tenant; // Importação para boa prática
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class; 

    public function definition(): array
    {
        return [
            'name'     => fake()->company(),
            'slug'     => fake()->slug(),
            'settings' => ['theme' => 'dark'],
        ];
    }
}