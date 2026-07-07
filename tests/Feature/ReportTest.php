<?php

namespace Tests\Feature;

use App\Enums\FreightStatus;
use App\Models\Freight;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant  = Tenant::factory()->create();
        $this->manager = User::factory()->manager()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_manager_can_view_dashboard_metrics(): void
    {
        Sanctum::actingAs($this->manager);

        Freight::factory()->completed()->create([
            'tenant_id'   => $this->tenant->id,
            'created_by'  => $this->manager->id,
            'total_price' => 5000,
            'distance_km' => 400,
        ]);

        Freight::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'created_by' => $this->manager->id,
            'status'     => FreightStatus::InTransit,
        ]);

        $response = $this->getJson('/api/v1/reports/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.freights.total', 2)
            ->assertJsonPath('data.freights.completed', 1)
            ->assertJsonPath('data.financial.revenue_total', 5000);
    }

    public function test_manager_can_view_financial_report(): void
    {
        Sanctum::actingAs($this->manager);

        $driver = User::factory()->driver()->create(['tenant_id' => $this->tenant->id]);

        Freight::factory()->completed()->create([
            'tenant_id'    => $this->tenant->id,
            'created_by'   => $this->manager->id,
            'driver_id'    => $driver->id,
            'total_price'  => 3000,
            'completed_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/reports/financial?from='.now()->startOfMonth()->toDateString());

        $response->assertOk()
            ->assertJsonPath('data.summary.freight_count', 1)
            ->assertJsonPath('data.summary.revenue', 3000);
    }

    public function test_driver_cannot_view_financial_report(): void
    {
        $driver = User::factory()->driver()->create(['tenant_id' => $this->tenant->id]);

        Sanctum::actingAs($driver);

        $this->getJson('/api/v1/reports/financial')->assertForbidden();
    }
}
