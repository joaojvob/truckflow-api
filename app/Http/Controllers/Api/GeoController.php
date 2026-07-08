<?php

namespace App\Http\Controllers\Api;

use App\Contracts\GeocodingProvider;
use App\Contracts\RoutingProvider;
use App\Enums\CargoType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Geocodificação de CEP, cálculo de rota/frete e catálogos auxiliares.
 */
class GeoController extends Controller
{
    public function __construct(
        protected GeocodingProvider $geocoding,
        protected RoutingProvider $routing,
    ) {}

    /**
     * Resolve um CEP em endereço + coordenadas.
     * GET /geo/cep/{cep}
     */
    public function cep(string $cep): JsonResponse
    {
        return response()->json([
            'data' => $this->geocoding->lookupCep($cep),
        ]);
    }

    /**
     * Calcula distância, duração e preço estimado do frete.
     * POST /geo/calculate
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'origin_lat'      => ['required', 'numeric', 'between:-90,90'],
            'origin_lng'      => ['required', 'numeric', 'between:-180,180'],
            'destination_lat' => ['required', 'numeric', 'between:-90,90'],
            'destination_lng' => ['required', 'numeric', 'between:-180,180'],
            'weight'          => ['nullable', 'numeric', 'min:0'],
            'price_per_km'    => ['nullable', 'numeric', 'min:0'],
            'price_per_ton'   => ['nullable', 'numeric', 'min:0'],
            'toll_cost'       => ['nullable', 'numeric', 'min:0'],
            'fuel_cost'       => ['nullable', 'numeric', 'min:0'],
            'waypoints'       => ['nullable', 'array'],
            'waypoints.*.lat' => ['required_with:waypoints', 'numeric', 'between:-90,90'],
            'waypoints.*.lng' => ['required_with:waypoints', 'numeric', 'between:-180,180'],
        ]);

        $route = $this->routing->getDirections(
            (float) $validated['origin_lat'],
            (float) $validated['origin_lng'],
            (float) $validated['destination_lat'],
            (float) $validated['destination_lng'],
            $validated['waypoints'] ?? [],
        );

        $distanceKm = round($route['distance_meters'] / 1000, 2);
        $estimatedHours = round($route['duration_seconds'] / 3600, 1);

        $pricePerKm = (float) ($validated['price_per_km'] ?? 0);
        $pricePerTon = (float) ($validated['price_per_ton'] ?? 0);
        $weight = (float) ($validated['weight'] ?? 0);
        $tollCost = (float) ($validated['toll_cost'] ?? 0);
        $fuelCost = (float) ($validated['fuel_cost'] ?? 0);

        $distancePrice = $pricePerKm * $distanceKm;
        $weightPrice = $pricePerTon * $weight;
        $totalPrice = $distancePrice + $weightPrice + $tollCost + $fuelCost;

        return response()->json([
            'data' => [
                'distance_km'      => $distanceKm,
                'estimated_hours'  => $estimatedHours,
                'route' => [
                    'polyline'         => $route['polyline'],
                    'distance_meters'  => $route['distance_meters'],
                    'duration_seconds' => $route['duration_seconds'],
                    'bounds'           => $route['bounds'],
                ],
                'pricing' => [
                    'distance_price' => round($distancePrice, 2),
                    'weight_price'   => round($weightPrice, 2),
                    'toll_cost'      => round($tollCost, 2),
                    'fuel_cost'      => round($fuelCost, 2),
                    'total_price'    => round($totalPrice, 2),
                ],
                'provider' => config('services.geo.driver'),
            ],
        ]);
    }

    /**
     * Catálogo de tipos de carga.
     * GET /geo/cargo-types
     */
    public function cargoTypes(): JsonResponse
    {
        return response()->json([
            'data' => CargoType::options(),
        ]);
    }
}
