<?php

namespace Tests\Feature\Freight;

use App\Enums\FreightStatus;
use App\Events\DriverLocationUpdated;
use App\Models\Freight;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FreightTrackingTest extends TestCase
{

    protected Tenant $tenant;

    protected User $driver;

    protected User $manager;

    protected Freight $freight;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([DriverLocationUpdated::class]);

        $this->tenant  = Tenant::factory()->create();
        $this->driver  = User::factory()->driver()->create(['tenant_id' => $this->tenant->id]);
        $this->manager = User::factory()->manager()->create(['tenant_id' => $this->tenant->id]);
        $this->freight = Freight::factory()->inTransit()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);
    }

    public function test_driver_can_send_gps_location(): void
    {
        Sanctum::actingAs($this->driver);

        $response = $this->postJson("/api/v1/freights/{$this->freight->id}/tracking", [
            'lat'       => -24.0,
            'lng'       => -47.0,
            'speed_kmh' => 80,
            'heading'   => 180,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.speed_kmh', '80.00');

        Event::assertDispatched(DriverLocationUpdated::class);
    }

    public function test_driver_cannot_track_when_freight_not_in_transit(): void
    {
        $freight = Freight::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
            'status'     => FreightStatus::Pending,
        ]);

        Sanctum::actingAs($this->driver);

        $this->postJson("/api/v1/freights/{$freight->id}/tracking", [
            'lat' => -24.0,
            'lng' => -47.0,
        ])->assertUnprocessable();
    }

    public function test_manager_can_view_latest_location(): void
    {
        Sanctum::actingAs($this->driver);

        $this->postJson("/api/v1/freights/{$this->freight->id}/tracking", [
            'lat' => -24.0,
            'lng' => -47.0,
        ])->assertCreated();

        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/freights/{$this->freight->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.lat', -24);
    }
}
