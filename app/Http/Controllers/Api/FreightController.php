<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteTripRequest;
use App\Http\Requests\StartTripRequest;
use App\Http\Requests\StoreFreightRequest;
use App\Http\Requests\UpdateFreightRequest;
use App\Http\Resources\FreightResource;
use App\Models\Freight;
use App\Services\FreightManagementService;
use App\Services\FreightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FreightController extends Controller
{
    public function __construct(
        protected FreightService $freightService,
        protected FreightManagementService $managementService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Freight::class);

        $freights = Freight::with(['driver', 'truck', 'trailer', 'creator'])
            ->latest()
            ->paginate(15);

        return FreightResource::collection($freights);
    }

    public function store(StoreFreightRequest $request): JsonResponse
    {
        $freight = $this->managementService->create($request->validated());

        return response()->json([
            'data'    => FreightResource::make($freight),
            'message' => 'Frete criado com sucesso!',
        ], 201);
    }

    public function show(Freight $freight): JsonResponse
    {
        $this->authorize('view', $freight);

        $freight->load(['driver', 'truck', 'trailer', 'creator', 'checklists', 'incidents']);

        return response()->json([
            'data' => FreightResource::make($freight),
        ]);
    }

    public function update(UpdateFreightRequest $request, Freight $freight): JsonResponse
    {
        $freight = $this->managementService->update($freight, $request->validated());

        return response()->json([
            'data'    => FreightResource::make($freight),
            'message' => 'Frete atualizado com sucesso!',
        ]);
    }

    public function destroy(Freight $freight): JsonResponse
    {
        $this->authorize('delete', $freight);

        $freight->delete();

        return response()->json([
            'message' => 'Frete excluído com sucesso!',
        ]);
    }

    public function cancel(Freight $freight): JsonResponse
    {
        $this->authorize('update', $freight);

        $freight = $this->managementService->cancel($freight);

        return response()->json([
            'data'    => FreightResource::make($freight),
            'message' => 'Frete cancelado com sucesso!',
        ]);
    }

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