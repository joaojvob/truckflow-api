<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFreightRequest;
use App\Http\Requests\UpdateFreightRequest;
use App\Http\Resources\FreightResource;
use App\Models\Freight;
use App\Services\FreightManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FreightController extends Controller
{
    public function __construct(
        protected FreightManagementService $managementService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Freight::class);

        $user = auth()->user();

        $query = Freight::with(['driver', 'truck', 'trailer', 'creator']);

        // Escopo por role: gestor só vê os fretes que criou
        if ($user->isManager()) {
            $query->where('created_by', $user->id);
        } elseif ($user->isDriver()) {
            $query->where('driver_id', $user->id);
        }
        // Admin vê tudo do tenant (já filtrado pelo BelongsToTenant)

        $freights = $query->latest()->paginate(15);

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

        $freight->load(['driver', 'truck', 'trailer', 'creator', 'checklists', 'incidents', 'dopingTests']);

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
}