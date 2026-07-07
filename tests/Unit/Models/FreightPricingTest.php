<?php

namespace Tests\Unit\Models;

use App\Models\Freight;
use Tests\TestCase;

class FreightPricingTest extends TestCase
{
    public function test_calculate_total_price_sums_distance_weight_and_costs(): void
    {
        $freight = new Freight([
            'price_per_km'  => 2.5,
            'distance_km'   => 100,
            'price_per_ton' => 50,
            'weight'        => 10,
            'toll_cost'     => 150,
            'fuel_cost'     => 800,
        ]);

        $this->assertSame(1700.0, $freight->calculateTotalPrice());
    }

    public function test_calculate_total_price_treats_null_components_as_zero(): void
    {
        $freight = new Freight;

        $this->assertSame(0.0, $freight->calculateTotalPrice());
    }
}
