<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartTripRequest;
use App\Services\FreightService;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class FreightController extends Controller
{
    use ApiResponser;

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

            return $this->successResponse($freight, 'Viagem iniciada com sucesso!');
        } catch (ValidationException $e) {
            return response()->json([
                'status'  => 'Error',
                'message' => 'Validação falhou.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Não foi possível iniciar a viagem.');
        }
    }

    /**
     * Endpoint para o motorista finalizar uma viagem.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $freight = $this->freightService->completeTrip(
                $id,
                $validated['rating'] ?? null,
                $validated['notes'] ?? null
            );

            return $this->successResponse($freight, 'Viagem finalizada com sucesso!');
        } catch (ValidationException $e) {
            return response()->json([
                'status'  => 'Error',
                'message' => 'Validação falhou.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Não foi possível finalizar a viagem.');
        }
    }
}