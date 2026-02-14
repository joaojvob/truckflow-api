<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteTripRequest;
use App\Http\Requests\StartTripRequest;
use App\Http\Resources\FreightResource;
use App\Models\Freight;
use App\Services\FreightService;
use Illuminate\Http\JsonResponse;

class FreightController extends Controller
{
    public function __construct(
        protected FreightService $freightService,
    ) {}

    public function start(StartTripRequest $request, Freight $freight): JsonResponse
    {
        $freight = $this->freightService->startTrip($freight, $request->validated()['items']);

        return response()->json([
            'data'    => FreightResource::make($freight),
            'message' => 'Viagem iniciada com sucesso!',
        ]);
    }

    public function complete(CompleteTripRequest $request, Freight $freight): JsonResponse
    {
        $freight = $this->freightService->completeTrip(
            $freight,
            $request->validated('rating'),
            $request->validated('notes'),
        );

        return response()->json([
            'data'    => FreightResource::make($freight),
            'message' => 'Viagem finalizada com sucesso!',
        ]);
    }
}