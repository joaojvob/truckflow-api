<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FreightRouteResource;
use App\Models\Freight;
use App\Services\FreightRouteService;
use Illuminate\Http\JsonResponse;

class FreightRouteController extends Controller
{
    public function __construct(
        protected FreightRouteService $routeService,
    ) {}

    /**
     * Exibir rota calculada do frete.
     * GET /freights/{freight}/route
     */
    public function show(Freight $freight): JsonResponse
    {
        $this->authorize('view', $freight);

        if (! $freight->hasCalculatedRoute()) {
            return response()->json([
                'message' => 'Rota ainda não calculada para este frete.',
            ], 404);
        }

        return response()->json([
            'data' => FreightRouteResource::make($freight),
        ]);
    }

    /**
     * Calcular rota via Google Directions API e persistir no frete.
     * POST /freights/{freight}/route
     */
    public function calculate(Freight $freight): JsonResponse
    {
        $this->authorize('calculateRoute', $freight);

        $freight = $this->routeService->calculate($freight);

        return response()->json([
            'data'    => FreightRouteResource::make($freight),
            'message' => 'Rota calculada com sucesso via Google Directions API.',
        ]);
    }
}
