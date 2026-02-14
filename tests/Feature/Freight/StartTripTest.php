<?php

namespace Tests\Feature\Freight;

use App\Enums\FreightStatus;
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
        $this->driver = User::factory()->driver()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_driver_can_start_trip_after_checklist(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
            'status'    => FreightStatus::Pending,
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

        $response->assertOk()
            ->assertJsonPath('data.status', FreightStatus::InTransit->value)
            ->assertJsonPath('message', 'Viagem iniciada com sucesso!');

        $this->assertDatabaseHas('freights', [
            'id'     => $freight->id,
            'status' => FreightStatus::InTransit->value,
        ]);

        $this->assertDatabaseHas('checklists', [
            'freight_id' => $freight->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action'         => 'trip_started',
            'auditable_id'   => $freight->id,
            'auditable_type' => Freight::class,
        ]);
    }

    public function test_driver_cannot_start_trip_with_failed_checklist(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
            'status'    => FreightStatus::Pending,
        ]);

        $payload = [
            'items' => [
                'pneus'        => true,
                'oleo'         => false,
                'luzes'        => true,
                'documentacao' => true,
            ],
        ];

        $response = $this->postJson("/api/v1/freights/{$freight->id}/start", $payload);

        $response->assertUnprocessable();

        $this->assertDatabaseHas('freights', [
            'id'     => $freight->id,
            'status' => FreightStatus::Pending->value,
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

        $response->assertUnprocessable();
    }

    public function test_admin_cannot_start_trip(): void
    {
        $admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($admin);

        $freight = Freight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
            'status'    => FreightStatus::Pending,
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

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_start_trip(): void
    {
        $freight = Freight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
            'status'    => FreightStatus::Pending,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/start", [
            'items' => ['pneus' => true, 'oleo' => true, 'luzes' => true, 'documentacao' => true],
        ]);

        $response->assertUnauthorized();
    }

    public function test_driver_can_complete_trip(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->inTransit()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/complete", [
            'rating' => 5,
            'notes'  => 'Viagem tranquila.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', FreightStatus::Completed->value)
            ->assertJsonPath('message', 'Viagem finalizada com sucesso!');

        $this->assertDatabaseHas('freights', [
            'id'     => $freight->id,
            'status' => FreightStatus::Completed->value,
        ]);
    }

    public function test_driver_cannot_complete_pending_trip(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
            'status'    => FreightStatus::Pending,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/complete");

        $response->assertUnprocessable();
    }
}