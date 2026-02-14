<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTruckRequest;
use App\Http\Requests\UpdateTruckRequest;
use App\Http\Resources\TruckResource;
use App\Models\Truck;
use App\Services\TruckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TruckController extends Controller
{
    public function __construct(
        protected TruckService $truckService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Truck::class);

        $trucks = Truck::with('driver')
            ->latest()
            ->paginate(15);

        return TruckResource::collection($trucks);
    }

    public function store(StoreTruckRequest $request): JsonResponse
    {
        $this->authorize('create', Truck::class);

        $truck = $this->truckService->create($request->validated());

        return response()->json([
            'data'    => TruckResource::make($truck),
            'message' => 'Caminhão registrado com sucesso!',
        ], 201);
    }

    public function show(Truck $truck): JsonResponse
    {
        $this->authorize('view', $truck);

        $truck->load(['driver', 'freights']);

        return response()->json([
            'data' => TruckResource::make($truck),
        ]);
    }

    public function update(UpdateTruckRequest $request, Truck $truck): JsonResponse
    {
        $this->authorize('update', $truck);

        $truck = $this->truckService->update($truck, $request->validated());

        return response()->json([
            'data'    => TruckResource::make($truck),
            'message' => 'Caminhão atualizado com sucesso!',
        ]);
    }

    public function destroy(Truck $truck): JsonResponse
    {
        $this->authorize('delete', $truck);

        $truck->delete();

        return response()->json([
            'message' => 'Caminhão excluído com sucesso!',
        ]);
    }
}
