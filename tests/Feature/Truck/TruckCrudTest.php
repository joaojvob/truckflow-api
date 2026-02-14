<?php

namespace Tests\Feature\Truck;

use App\Enums\TruckStatus;
use App\Models\Tenant;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TruckCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $admin;
    protected User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->admin  = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->driver = User::factory()->driver()->create(['tenant_id' => $this->tenant->id]);
    }

    // ─── Index ────────────────────────────────────────────────

    public function test_admin_can_list_trucks(): void
    {
        Sanctum::actingAs($this->admin);

        Truck::factory(3)->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
        ]);

        $response = $this->getJson('/api/v1/trucks');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    // ─── Store ────────────────────────────────────────────────

    public function test_driver_can_register_own_truck(): void
    {
        Sanctum::actingAs($this->driver);

        $payload = [
            'plate'             => 'ABC-1D23',
            'brand'             => 'Scania',
            'model'             => 'R450',
            'year'              => 2022,
            'max_weight'        => 45.0,
            'has_trailer_hitch' => true,
            'hitch_type'        => 'fifth_wheel',
            'axle_count'        => 3,
        ];

        $response = $this->postJson('/api/v1/trucks', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.plate', 'ABC-1D23')
            ->assertJsonPath('data.brand', 'Scania')
            ->assertJsonPath('message', 'Caminhão registrado com sucesso!');

        $this->assertDatabaseHas('trucks', [
            'plate'     => 'ABC-1D23',
            'driver_id' => $this->driver->id,
        ]);
    }

    public function test_cannot_register_truck_with_duplicate_plate(): void
    {
        Sanctum::actingAs($this->driver);

        Truck::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
            'plate'     => 'XYZ-9A99',
        ]);

        $payload = [
            'plate'      => 'XYZ-9A99',
            'brand'      => 'Volvo',
            'model'      => 'FH540',
            'year'       => 2023,
            'max_weight' => 40.0,
        ];

        $response = $this->postJson('/api/v1/trucks', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['plate']);
    }

    // ─── Show ─────────────────────────────────────────────────

    public function test_driver_can_view_own_truck(): void
    {
        Sanctum::actingAs($this->driver);

        $truck = Truck::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
        ]);

        $response = $this->getJson("/api/v1/trucks/{$truck->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $truck->id);
    }

    // ─── Update ───────────────────────────────────────────────

    public function test_driver_can_update_own_truck(): void
    {
        Sanctum::actingAs($this->driver);

        $truck = Truck::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
        ]);

        $response = $this->putJson("/api/v1/trucks/{$truck->id}", [
            'color'    => 'Azul',
            'odometer' => 150000,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Caminhão atualizado com sucesso!');
    }

    // ─── Destroy ──────────────────────────────────────────────

    public function test_admin_can_delete_truck(): void
    {
        Sanctum::actingAs($this->admin);

        $truck = Truck::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
        ]);

        $response = $this->deleteJson("/api/v1/trucks/{$truck->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Caminhão excluído com sucesso!');

        $this->assertDatabaseMissing('trucks', ['id' => $truck->id]);
    }

    public function test_driver_cannot_delete_other_drivers_truck(): void
    {
        $otherDriver = User::factory()->driver()->create(['tenant_id' => $this->tenant->id]);

        Sanctum::actingAs($this->driver);

        $truck = Truck::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $otherDriver->id,
        ]);

        $response = $this->deleteJson("/api/v1/trucks/{$truck->id}");

        $response->assertForbidden();
    }
}
