<?php

namespace Tests\Feature\Freight;

use App\Enums\FreightStatus;
use App\Models\Freight;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FreightCrudTest extends TestCase
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
    }

    // ─── Index ────────────────────────────────────────────────

    public function test_admin_can_list_freights(): void
    {
        Sanctum::actingAs($this->admin);

        Freight::factory(3)->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
        ]);

        $response = $this->getJson('/api/v1/freights');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_manager_only_sees_own_freights(): void
    {
        Sanctum::actingAs($this->manager);

        // Fretes criados por este gestor
        Freight::factory(2)->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        // Frete criado por outro gestor (não deve aparecer)
        $otherManager = User::factory()->manager()->create(['tenant_id' => $this->tenant->id]);
        Freight::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $otherManager->id,
        ]);

        $response = $this->getJson('/api/v1/freights');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_driver_only_sees_own_freights(): void
    {
        Sanctum::actingAs($this->driver);

        // Fretes atribuídos a este driver
        Freight::factory(2)->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
        ]);

        // Frete de outro driver (não deve aparecer)
        $otherDriver = User::factory()->driver()->create(['tenant_id' => $this->tenant->id]);
        Freight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $otherDriver->id,
        ]);

        $response = $this->getJson('/api/v1/freights');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // ─── Store ────────────────────────────────────────────────

    public function test_manager_can_create_freight(): void
    {
        Sanctum::actingAs($this->manager);

        $payload = [
            'driver_id'           => $this->driver->id,
            'cargo_name'          => 'Soja em grãos',
            'weight'              => 25.5,
            'origin_lat'          => -23.5505,
            'origin_lng'          => -46.6333,
            'destination_lat'     => -22.9068,
            'destination_lng'     => -43.1729,
            'origin_address'      => 'São Paulo, SP',
            'destination_address' => 'Rio de Janeiro, RJ',
            'distance_km'         => 429.5,
            'estimated_hours'     => 6,
            'price_per_km'        => 4.50,
            'price_per_ton'       => 120.00,
            'toll_cost'           => 85.00,
            'fuel_cost'           => 650.00,
        ];

        $response = $this->postJson('/api/v1/freights', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.cargo_name', 'Soja em grãos')
            ->assertJsonPath('message', 'Frete criado com sucesso!');

        $this->assertDatabaseHas('freights', [
            'cargo_name' => 'Soja em grãos',
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);
    }

    public function test_driver_cannot_create_freight(): void
    {
        Sanctum::actingAs($this->driver);

        $payload = [
            'driver_id'           => $this->driver->id,
            'cargo_name'          => 'Teste',
            'weight'              => 10,
            'origin_lat'          => -23.5,
            'origin_lng'          => -46.6,
            'destination_lat'     => -22.9,
            'destination_lng'     => -43.1,
            'origin_address'      => 'São Paulo',
            'destination_address' => 'Rio de Janeiro',
        ];

        $response = $this->postJson('/api/v1/freights', $payload);

        $response->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────

    public function test_admin_can_view_freight(): void
    {
        Sanctum::actingAs($this->admin);

        $freight = Freight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
        ]);

        $response = $this->getJson("/api/v1/freights/{$freight->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $freight->id);
    }

    // ─── Update ───────────────────────────────────────────────

    public function test_manager_can_update_freight(): void
    {
        Sanctum::actingAs($this->manager);

        $freight = Freight::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->putJson("/api/v1/freights/{$freight->id}", [
            'cargo_name' => 'Carga atualizada',
            'weight'     => 15.0,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.cargo_name', 'Carga atualizada')
            ->assertJsonPath('message', 'Frete atualizado com sucesso!');
    }

    public function test_cannot_update_completed_freight(): void
    {
        Sanctum::actingAs($this->manager);

        $freight = Freight::factory()->completed()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->putJson("/api/v1/freights/{$freight->id}", [
            'cargo_name' => 'Tentativa',
        ]);

        $response->assertUnprocessable();
    }

    // ─── Destroy ──────────────────────────────────────────────

    public function test_admin_can_delete_freight(): void
    {
        Sanctum::actingAs($this->admin);

        $freight = Freight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
        ]);

        $response = $this->deleteJson("/api/v1/freights/{$freight->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Frete excluído com sucesso!');

        $this->assertDatabaseMissing('freights', ['id' => $freight->id]);
    }

    public function test_manager_cannot_delete_freight(): void
    {
        Sanctum::actingAs($this->manager);

        $freight = Freight::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->deleteJson("/api/v1/freights/{$freight->id}");

        $response->assertForbidden();
    }

    // ─── Cancel ───────────────────────────────────────────────

    public function test_manager_can_cancel_freight(): void
    {
        Sanctum::actingAs($this->manager);

        $freight = Freight::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
            'status'     => FreightStatus::Pending,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', FreightStatus::Cancelled->value)
            ->assertJsonPath('message', 'Frete cancelado com sucesso!');
    }

    public function test_cannot_cancel_completed_freight(): void
    {
        Sanctum::actingAs($this->manager);

        $freight = Freight::factory()->completed()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/cancel");

        $response->assertUnprocessable();
    }
}
