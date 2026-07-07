<?php

namespace Tests\Feature\Freight;

use App\Models\Freight;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Waypoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FreightRouteTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected User $manager;

    protected User $driver;

    protected Freight $freight;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google_maps.api_key' => 'test-google-maps-key']);

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->manager = User::factory()->manager()->create(['tenant_id' => $this->tenant->id]);
        $this->driver = User::factory()->driver()->create(['tenant_id' => $this->tenant->id]);

        $this->freight = Freight::factory()->create([
            'tenant_id'           => $this->tenant->id,
            'driver_id'           => $this->driver->id,
            'created_by'          => $this->manager->id,
            'origin'              => DB::raw("ST_GeomFromText('POINT(-46.6333 -23.5505)', 4326)"),
            'destination'         => DB::raw("ST_GeomFromText('POINT(-49.2733 -25.4284)', 4326)"),
            'origin_address'      => 'São Paulo, SP',
            'destination_address' => 'Curitiba, PR',
            'price_per_km'        => 3.50,
            'price_per_ton'       => 100,
            'weight'              => 10,
        ]);
    }

    public function test_manager_can_calculate_route(): void
    {
        Sanctum::actingAs($this->manager);

        $this->fakeGoogleDirectionsResponse();

        $response = $this->postJson("/api/v1/freights/{$this->freight->id}/route");

        $response->assertOk()
            ->assertJsonPath('data.polyline', 'encoded_polyline_sp_curitiba')
            ->assertJsonPath('data.distance_meters', 408000)
            ->assertJsonPath('data.duration_seconds', 14400)
            ->assertJsonPath('message', 'Rota calculada com sucesso via Google Directions API.');

        $this->freight->refresh();

        $this->assertSame('encoded_polyline_sp_curitiba', $this->freight->route_polyline);
        $this->assertSame(408000, $this->freight->route_distance_meters);
        $this->assertSame(408.0, (float) $this->freight->distance_km);
        $this->assertNotNull($this->freight->route_calculated_at);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'maps.googleapis.com/maps/api/directions/json')
                && $request['origin'] === '-23.5505,-46.6333'
                && $request['destination'] === '-25.4284,-49.2733'
                && $request['key'] === 'test-google-maps-key';
        });
    }

    public function test_calculate_route_includes_waypoints_in_google_request(): void
    {
        Sanctum::actingAs($this->manager);

        Waypoint::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'freight_id' => $this->freight->id,
            'created_by' => $this->manager->id,
            'name'       => 'Posto Shell',
            'location'   => DB::raw("ST_GeomFromText('POINT(-47.5678 -24.1234)', 4326)"),
            'order'      => 0,
        ]);

        $this->fakeGoogleDirectionsResponse();

        $this->postJson("/api/v1/freights/{$this->freight->id}/route")->assertOk();

        Http::assertSent(function ($request) {
            return $request['waypoints'] === '-24.1234,-47.5678';
        });
    }

    public function test_driver_can_view_calculated_route(): void
    {
        $this->freight->update([
            'route_polyline'         => 'stored_polyline',
            'route_distance_meters'  => 100000,
            'route_duration_seconds' => 3600,
            'route_calculated_at'    => now(),
        ]);

        Sanctum::actingAs($this->driver);

        $response = $this->getJson("/api/v1/freights/{$this->freight->id}/route");

        $response->assertOk()
            ->assertJsonPath('data.polyline', 'stored_polyline')
            ->assertJsonPath('data.distance_meters', 100000);
    }

    public function test_driver_cannot_calculate_route(): void
    {
        Sanctum::actingAs($this->driver);

        $this->fakeGoogleDirectionsResponse();

        $this->postJson("/api/v1/freights/{$this->freight->id}/route")
            ->assertForbidden();
    }

    public function test_show_returns_404_when_route_not_calculated(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/freights/{$this->freight->id}/route")
            ->assertNotFound()
            ->assertJsonPath('message', 'Rota ainda não calculada para este frete.');
    }

    public function test_calculate_returns_error_when_google_api_fails(): void
    {
        Sanctum::actingAs($this->manager);

        Http::fake([
            'maps.googleapis.com/maps/api/directions/json*' => Http::response([
                'status' => 'ZERO_RESULTS',
                'routes' => [],
            ]),
        ]);

        $this->postJson("/api/v1/freights/{$this->freight->id}/route")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['google_maps']);
    }

    public function test_calculate_returns_error_when_api_key_missing(): void
    {
        config(['services.google_maps.api_key' => null]);

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/freights/{$this->freight->id}/route")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['google_maps']);
    }

    public function test_freight_show_includes_route_summary_when_calculated(): void
    {
        $this->freight->update([
            'route_polyline'         => 'summary_polyline',
            'route_distance_meters'  => 200000,
            'route_duration_seconds' => 7200,
            'route_calculated_at'    => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/freights/{$this->freight->id}")
            ->assertOk()
            ->assertJsonPath('data.route.polyline', 'summary_polyline')
            ->assertJsonPath('data.route.distance_meters', 200000);
    }

    private function fakeGoogleDirectionsResponse(): void
    {
        Http::fake([
            'maps.googleapis.com/maps/api/directions/json*' => Http::response([
                'status' => 'OK',
                'routes' => [[
                    'overview_polyline' => [
                        'points' => 'encoded_polyline_sp_curitiba',
                    ],
                    'legs' => [[
                        'distance' => ['value' => 408000, 'text' => '408 km'],
                        'duration' => ['value' => 14400, 'text' => '4 horas'],
                    ]],
                    'bounds' => [
                        'northeast' => ['lat' => -23.0, 'lng' => -46.0],
                        'southwest' => ['lat' => -26.0, 'lng' => -50.0],
                    ],
                ]],
            ]),
        ]);
    }
}
