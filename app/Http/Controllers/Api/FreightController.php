<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartTripRequest;
use App\Services\FreightService;
use Illuminate\Http\JsonResponse;

class FreightController extends Controller
{
    public function __construct(
        protected FreightService $freightService
    ) {}

    /**
     * Endpoint para o motorista iniciar uma viagem.
     */
    public function start(StartTripRequest $request, int $id): JsonResponse
    {
        try {
            $freight = $this->freightService->startTrip($id, $request->validated()['items']);

            return response()->json([
                'message' => 'Viagem iniciada com sucesso! Boa jornada.',
                'data'    => $freight
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Não foi possível iniciar a viagem.',
                'details' => $e->getMessage()
            ], 422);
        }
    }
}