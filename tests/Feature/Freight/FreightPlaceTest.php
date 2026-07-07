<?php

namespace Tests\Feature\Freight;

use App\Enums\PlaceType;
use App\Models\Freight;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FreightPlaceTest extends TestCase
{
    protected Tenant $tenant;

    protected User $manager;

    protected Freight $freight;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google_maps.api_key' => 'test-key']);

        $this->tenant = Tenant::factory()->create();
        $this->manager = User::factory()->manager()->create(['tenant_id' => $this->tenant->id]);
        $this->freight = Freight::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'created_by' => $this->manager->id,
        ]);
    }

    public function test_manager_can_search_places_near_freight(): void
    {
        Sanctum::actingAs($this->manager);

        Http::fake([
            'maps.googleapis.com/maps/api/place/nearbysearch/json*' => Http::response([
                'status'  => 'OK',
                'results' => [[
                    'place_id' => 'abc123',
                    'name'     => 'Posto Shell',
                    'vicinity' => 'Rodovia BR-116',
                    'geometry' => ['location' => ['lat' => -23.5, 'lng' => -46.6]],
                    'rating'   => 4.2,
                ]],
            ]),
        ]);

        $response = $this->postJson("/api/v1/freights/{$this->freight->id}/places/search", [
            'lat'  => -23.55,
            'lng'  => -46.63,
            'type' => PlaceType::GasStation->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Posto Shell')
            ->assertJsonPath('data.0.place_id', 'abc123');
    }
}
