<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDriverLocationRequest;
use App\Http\Resources\DriverLocationResource;
use App\Models\Freight;
use App\Services\DriverTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FreightTrackingController extends Controller
{
    public function __construct(
        protected DriverTrackingService $trackingService,
    ) {}

    public function store(StoreDriverLocationRequest $request, Freight $freight): JsonResponse
    {
        $this->authorize('track', $freight);

        $location = $this->trackingService->record($freight, $request->validated());

        return response()->json([
            'data'    => DriverLocationResource::make($location),
            'message' => 'Posição GPS registrada com sucesso.',
        ], 201);
    }

    public function show(Freight $freight): JsonResponse
    {
        $this->authorize('view', $freight);

        $location = $this->trackingService->latest($freight);

        if (! $location) {
            return response()->json([
                'message' => 'Nenhuma posição GPS registrada para este frete.',
            ], 404);
        }

        return response()->json([
            'data' => DriverLocationResource::make($location),
        ]);
    }

    public function history(Freight $freight): AnonymousResourceCollection
    {
        $this->authorize('view', $freight);

        $locations = $this->trackingService->history($freight);

        return DriverLocationResource::collection($locations);
    }
}
