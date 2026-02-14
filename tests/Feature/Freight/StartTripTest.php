<?php

namespace Tests\Feature\Freight;

use App\Models\Freight;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StartTripTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->driver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'driver',
        ]);
    }

    public function test_driver_can_start_trip_after_checklist(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
            'status'    => 'pending',
        ]);

        $payload = [
            'items' => [
                'pneus'        => true,
                'oleo'         => true,
                'luzes'        => true,
                'documentacao' => true,
            ],
        ];

        $response = $this->postJson("/api/v1/freights/{$freight->id}/start", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'Success');

        $this->assertDatabaseHas('freights', [
            'id'     => $freight->id,
            'status' => 'in_transit',
        ]);

        $this->assertDatabaseHas('checklists', [
            'freight_id' => $freight->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action'        => 'trip_started',
            'auditable_id'  => $freight->id,
            'auditable_type' => Freight::class,
        ]);
    }

    public function test_driver_cannot_start_trip_with_failed_checklist(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
            'status'    => 'pending',
        ]);

        $payload = [
            'items' => [
                'pneus'        => true,
                'oleo'         => false, // Reprovado
                'luzes'        => true,
                'documentacao' => true,
            ],
        ];

        $response = $this->postJson("/api/v1/freights/{$freight->id}/start", $payload);

        $response->assertStatus(422);

        $this->assertDatabaseHas('freights', [
            'id'     => $freight->id,
            'status' => 'pending', // Não mudou
        ]);
    }

    public function test_driver_cannot_start_trip_already_in_transit(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->inTransit()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
        ]);

        $payload = [
            'items' => [
                'pneus'        => true,
                'oleo'         => true,
                'luzes'        => true,
                'documentacao' => true,
            ],
        ];

        $response = $this->postJson("/api/v1/freights/{$freight->id}/start", $payload);

        $response->assertStatus(422);
    }

    public function test_admin_cannot_start_trip(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $freight = Freight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
            'status'    => 'pending',
        ]);

        $payload = [
            'items' => [
                'pneus'        => true,
                'oleo'         => true,
                'luzes'        => true,
                'documentacao' => true,
            ],
        ];

        $response = $this->postJson("/api/v1/freights/{$freight->id}/start", $payload);

        $response->assertStatus(403); // Forbidden - não é motorista
    }

    public function test_unauthenticated_user_cannot_start_trip(): void
    {
        $freight = Freight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
            'status'    => 'pending',
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/start", [
            'items' => ['pneus' => true, 'oleo' => true, 'luzes' => true, 'documentacao' => true],
        ]);

        $response->assertStatus(401); // Não autenticado
    }
}