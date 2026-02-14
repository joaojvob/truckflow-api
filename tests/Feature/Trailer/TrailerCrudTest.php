<?php

namespace Tests\Feature\Trailer;

use App\Enums\TrailerType;
use App\Models\Tenant;
use App\Models\Trailer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrailerCrudTest extends TestCase
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

    public function test_admin_can_list_trailers(): void
    {
        Sanctum::actingAs($this->admin);

        Trailer::factory(3)->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
        ]);

        $response = $this->getJson('/api/v1/trailers');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    // ─── Store ────────────────────────────────────────────────

    public function test_driver_can_register_own_trailer(): void
    {
        Sanctum::actingAs($this->driver);

        $payload = [
            'plate'      => 'TRL-1A23',
            'type'       => TrailerType::Flatbed->value,
            'brand'      => 'Randon',
            'model'      => 'SR GR',
            'year'       => 2021,
            'max_weight' => 30.0,
            'axle_count' => 3,
            'hitch_type' => 'fifth_wheel',
        ];

        $response = $this->postJson('/api/v1/trailers', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.plate', 'TRL-1A23')
            ->assertJsonPath('message', 'Reboque registrado com sucesso!');

        $this->assertDatabaseHas('trailers', [
            'plate'     => 'TRL-1A23',
            'driver_id' => $this->driver->id,
        ]);
    }

    // ─── Show ─────────────────────────────────────────────────

    public function test_driver_can_view_own_trailer(): void
    {
        Sanctum::actingAs($this->driver);

        $trailer = Trailer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
        ]);

        $response = $this->getJson("/api/v1/trailers/{$trailer->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $trailer->id);
    }

    // ─── Update ───────────────────────────────────────────────

    public function test_driver_can_update_own_trailer(): void
    {
        Sanctum::actingAs($this->driver);

        $trailer = Trailer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
        ]);

        $response = $this->putJson("/api/v1/trailers/{$trailer->id}", [
            'max_weight' => 35.0,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Reboque atualizado com sucesso!');
    }

    // ─── Destroy ──────────────────────────────────────────────

    public function test_admin_can_delete_trailer(): void
    {
        Sanctum::actingAs($this->admin);

        $trailer = Trailer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
        ]);

        $response = $this->deleteJson("/api/v1/trailers/{$trailer->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Reboque excluído com sucesso!');

        $this->assertDatabaseMissing('trailers', ['id' => $trailer->id]);
    }

    public function test_driver_cannot_delete_other_drivers_trailer(): void
    {
        $otherDriver = User::factory()->driver()->create(['tenant_id' => $this->tenant->id]);

        Sanctum::actingAs($this->driver);

        $trailer = Trailer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $otherDriver->id,
        ]);

        $response = $this->deleteJson("/api/v1/trailers/{$trailer->id}");

        $response->assertForbidden();
    }
}
