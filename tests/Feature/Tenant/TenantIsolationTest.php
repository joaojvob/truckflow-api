<?php

namespace Tests\Feature\Tenant;

use App\Models\Freight;
use App\Models\Tenant;
use App\Models\Truck;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Garante isolamento total entre tenants (requisito crítico de SaaS).
 */
class TenantIsolationTest extends TestCase
{
    public function test_manager_cannot_view_freight_from_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $managerA = User::factory()->manager()->create(['tenant_id' => $tenantA->id]);
        $managerB = User::factory()->manager()->create(['tenant_id' => $tenantB->id]);

        $freightB = Freight::factory()->create([
            'tenant_id'  => $tenantB->id,
            'created_by' => $managerB->id,
        ]);

        Sanctum::actingAs($managerA);

        $this->getJson("/api/v1/freights/{$freightB->id}")->assertNotFound();
    }

    public function test_driver_cannot_view_freight_assigned_to_driver_in_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $driverA = User::factory()->driver()->create(['tenant_id' => $tenantA->id]);
        $driverB = User::factory()->driver()->create(['tenant_id' => $tenantB->id]);

        $freightB = Freight::factory()->create([
            'tenant_id' => $tenantB->id,
            'driver_id' => $driverB->id,
        ]);

        Sanctum::actingAs($driverA);

        $this->getJson("/api/v1/freights/{$freightB->id}")->assertNotFound();
    }

    public function test_admin_lists_only_own_tenant_freights(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $adminA = User::factory()->admin()->create(['tenant_id' => $tenantA->id]);
        $adminB = User::factory()->admin()->create(['tenant_id' => $tenantB->id]);

        Freight::factory()->count(2)->create(['tenant_id' => $tenantA->id, 'created_by' => $adminA->id]);
        Freight::factory()->create(['tenant_id' => $tenantB->id, 'created_by' => $adminB->id]);

        Sanctum::actingAs($adminA);

        $response = $this->getJson('/api/v1/freights');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_manager_cannot_view_truck_from_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $managerA = User::factory()->manager()->create(['tenant_id' => $tenantA->id]);
        $driverB = User::factory()->driver()->create(['tenant_id' => $tenantB->id]);

        $truckB = Truck::factory()->create([
            'tenant_id' => $tenantB->id,
            'driver_id' => $driverB->id,
        ]);

        Sanctum::actingAs($managerA);

        $this->getJson("/api/v1/trucks/{$truckB->id}")->assertNotFound();
    }

    public function test_user_cannot_list_users_from_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $adminA = User::factory()->admin()->create(['tenant_id' => $tenantA->id]);
        User::factory()->count(3)->driver()->create(['tenant_id' => $tenantB->id]);

        Sanctum::actingAs($adminA);

        $response = $this->getJson('/api/v1/users');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
