<?php

namespace Tests\Feature\Waypoint;

use App\Enums\FreightStatus;
use App\Enums\WaypointType;
use App\Models\Freight;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Waypoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WaypointCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $admin;
    protected User $manager;
    protected User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant  = Tenant::factory()->create();
        $this->admin   = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->manager = User::factory()->manager()->create(['tenant_id' => $this->tenant->id]);
        $this->driver  = User::factory()->driver()->create(['tenant_id' => $this->tenant->id]);

        $this->manager->drivers()->attach($this->driver->id, ['tenant_id' => $this->tenant->id]);
    }

    // ─── Index ────────────────────────────────────────────────

    public function test_manager_can_list_waypoints(): void
    {
        Sanctum::actingAs($this->manager);

        $freight = Freight::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        Waypoint::factory(3)->create([
            'tenant_id'  => $this->tenant->id,
            'freight_id' => $freight->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->getJson("/api/v1/freights/{$freight->id}/waypoints");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_driver_can_list_waypoints_of_own_freight(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->assigned()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        Waypoint::factory(2)->create([
            'tenant_id'  => $this->tenant->id,
            'freight_id' => $freight->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->getJson("/api/v1/freights/{$freight->id}/waypoints");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // ─── Store ────────────────────────────────────────────────

    public function test_manager_can_create_waypoint(): void
    {
        Sanctum::actingAs($this->manager);

        $freight = Freight::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/waypoints", [
            'name'                   => 'Posto Shell BR-116 km 230',
            'type'                   => 'fuel_stop',
            'lat'                    => -24.1234,
            'lng'                    => -47.5678,
            'address'                => 'BR-116, km 230',
            'mandatory'              => true,
            'estimated_stop_minutes' => 30,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Posto Shell BR-116 km 230')
            ->assertJsonPath('data.type', WaypointType::FuelStop->value)
            ->assertJsonPath('data.mandatory', true)
            ->assertJsonPath('message', 'Waypoint adicionado com sucesso!');

        $this->assertDatabaseHas('waypoints', [
            'freight_id' => $freight->id,
            'name'       => 'Posto Shell BR-116 km 230',
            'mandatory'  => true,
        ]);
    }

    public function test_driver_can_create_waypoint_when_enforce_route_is_false(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->accepted()->create([
            'tenant_id'     => $this->tenant->id,
            'driver_id'     => $this->driver->id,
            'created_by'    => $this->manager->id,
            'enforce_route' => false,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/waypoints", [
            'name' => 'Meu posto preferido',
            'type' => 'fuel_stop',
            'lat'  => -24.5,
            'lng'  => -47.5,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Meu posto preferido');
    }

    public function test_driver_cannot_create_waypoint_when_enforce_route_is_true(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->accepted()->create([
            'tenant_id'     => $this->tenant->id,
            'driver_id'     => $this->driver->id,
            'created_by'    => $this->manager->id,
            'enforce_route' => true,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/waypoints", [
            'name' => 'Posto',
            'type' => 'fuel_stop',
            'lat'  => -24.5,
            'lng'  => -47.5,
        ]);

        $response->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────

    public function test_manager_can_update_waypoint(): void
    {
        Sanctum::actingAs($this->manager);

        $freight = Freight::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $waypoint = Waypoint::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'freight_id' => $freight->id,
            'created_by' => $this->manager->id,
            'name'       => 'Posto antigo',
        ]);

        $response = $this->putJson("/api/v1/freights/{$freight->id}/waypoints/{$waypoint->id}", [
            'name'      => 'Posto atualizado',
            'mandatory' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Posto atualizado')
            ->assertJsonPath('message', 'Waypoint atualizado com sucesso!');
    }

    // ─── Destroy ──────────────────────────────────────────────

    public function test_manager_can_delete_waypoint(): void
    {
        Sanctum::actingAs($this->manager);

        $freight = Freight::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $waypoint = Waypoint::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'freight_id' => $freight->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->deleteJson("/api/v1/freights/{$freight->id}/waypoints/{$waypoint->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Waypoint removido com sucesso!');

        $this->assertDatabaseMissing('waypoints', ['id' => $waypoint->id]);
    }

    // ─── Check-in / Check-out ─────────────────────────────────

    public function test_driver_can_checkin_to_waypoint(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->inTransit()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $waypoint = Waypoint::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'freight_id' => $freight->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/waypoints/{$waypoint->id}/checkin");

        $response->assertOk()
            ->assertJsonPath('data.is_visited', true)
            ->assertJsonPath('message', 'Check-in realizado com sucesso!');

        $waypoint->refresh();
        $this->assertNotNull($waypoint->arrived_at);
    }

    public function test_driver_can_checkout_from_waypoint(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->inTransit()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $waypoint = Waypoint::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'freight_id' => $freight->id,
            'created_by' => $this->manager->id,
            'arrived_at' => now()->subMinutes(30),
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/waypoints/{$waypoint->id}/checkout");

        $response->assertOk()
            ->assertJsonPath('data.is_completed', true)
            ->assertJsonPath('message', 'Check-out realizado com sucesso!');

        $waypoint->refresh();
        $this->assertNotNull($waypoint->departed_at);
    }

    public function test_driver_cannot_checkin_if_freight_not_in_transit(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->accepted()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $waypoint = Waypoint::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'freight_id' => $freight->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/waypoints/{$waypoint->id}/checkin");

        $response->assertForbidden();
    }

    public function test_cannot_checkin_twice(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->inTransit()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $waypoint = Waypoint::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'freight_id' => $freight->id,
            'created_by' => $this->manager->id,
            'arrived_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/waypoints/{$waypoint->id}/checkin");

        $response->assertUnprocessable();
    }

    // ─── Waypoints inline na criação do frete ─────────────────

    public function test_manager_can_create_freight_with_waypoints(): void
    {
        Sanctum::actingAs($this->manager);

        $payload = [
            'driver_id'           => $this->driver->id,
            'cargo_name'          => 'Soja com rota',
            'weight'              => 25.5,
            'origin_lat'          => -23.5505,
            'origin_lng'          => -46.6333,
            'destination_lat'     => -25.4284,
            'destination_lng'     => -49.2733,
            'origin_address'      => 'São Paulo, SP',
            'destination_address' => 'Curitiba, PR',
            'distance_km'         => 408,
            'price_per_km'        => 4.50,
            'enforce_route'       => true,
            'waypoints'           => [
                [
                    'name'                   => 'Posto Shell BR-116 km 230',
                    'type'                   => 'fuel_stop',
                    'lat'                    => -24.1234,
                    'lng'                    => -47.5678,
                    'mandatory'              => true,
                    'estimated_stop_minutes' => 30,
                ],
                [
                    'name' => 'Parada de descanso Registro',
                    'type' => 'rest_stop',
                    'lat'  => -24.4872,
                    'lng'  => -47.8432,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/freights', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.cargo_name', 'Soja com rota')
            ->assertJsonPath('data.enforce_route', true);

        $freight = Freight::where('cargo_name', 'Soja com rota')->first();

        $this->assertNotNull($freight);
        $this->assertTrue($freight->enforce_route);
        $this->assertCount(2, $freight->waypoints);
        $this->assertEquals('Posto Shell BR-116 km 230', $freight->waypoints->first()->name);
    }

    // ─── Reorder ──────────────────────────────────────────────

    public function test_manager_can_reorder_waypoints(): void
    {
        Sanctum::actingAs($this->manager);

        $freight = Freight::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $wp1 = Waypoint::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'freight_id' => $freight->id,
            'created_by' => $this->manager->id,
            'order'      => 0,
            'name'       => 'Primeiro',
        ]);

        $wp2 = Waypoint::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'freight_id' => $freight->id,
            'created_by' => $this->manager->id,
            'order'      => 1,
            'name'       => 'Segundo',
        ]);

        // Inverter a ordem
        $response = $this->postJson("/api/v1/freights/{$freight->id}/waypoints/reorder", [
            'waypoint_ids' => [$wp2->id, $wp1->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Waypoints reordenados com sucesso!');

        $wp1->refresh();
        $wp2->refresh();

        $this->assertEquals(1, $wp1->order);
        $this->assertEquals(0, $wp2->order);
    }

    // ─── Unauthenticated ──────────────────────────────────────

    public function test_unauthenticated_cannot_access_waypoints(): void
    {
        $freight = Freight::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->getJson("/api/v1/freights/{$freight->id}/waypoints");

        $response->assertUnauthorized();
    }
}
