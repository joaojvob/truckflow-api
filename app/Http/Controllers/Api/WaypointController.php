<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWaypointRequest;
use App\Http\Requests\UpdateWaypointRequest;
use App\Http\Resources\WaypointResource;
use App\Models\Freight;
use App\Models\Waypoint;
use App\Services\WaypointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WaypointController extends Controller
{
    public function __construct(
        protected WaypointService $waypointService,
    ) {}

    /**
     * Listar waypoints de um frete (ordenados).
     * GET /freights/{freight}/waypoints
     */
    public function index(Freight $freight): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Waypoint::class, $freight]);

        $waypoints = $freight->waypoints()->with('creator')->get();

        return WaypointResource::collection($waypoints);
    }

    /**
     * Criar waypoint em um frete.
     * POST /freights/{freight}/waypoints
     */
    public function store(StoreWaypointRequest $request, Freight $freight): JsonResponse
    {
        $this->authorize('create', [Waypoint::class, $freight]);

        $waypoint = $this->waypointService->create($freight, $request->validated());

        return response()->json([
            'data'    => WaypointResource::make($waypoint),
            'message' => 'Waypoint adicionado com sucesso!',
        ], 201);
    }

    /**
     * Ver detalhes de um waypoint.
     * GET /freights/{freight}/waypoints/{waypoint}
     */
    public function show(Freight $freight, Waypoint $waypoint): JsonResponse
    {
        $this->authorize('viewAny', [Waypoint::class, $freight]);

        $waypoint->load('creator');

        return response()->json([
            'data' => WaypointResource::make($waypoint),
        ]);
    }

    /**
     * Atualizar um waypoint.
     * PUT /freights/{freight}/waypoints/{waypoint}
     */
    public function update(UpdateWaypointRequest $request, Freight $freight, Waypoint $waypoint): JsonResponse
    {
        $this->authorize('update', $waypoint);

        $waypoint = $this->waypointService->update($waypoint, $request->validated());

        return response()->json([
            'data'    => WaypointResource::make($waypoint),
            'message' => 'Waypoint atualizado com sucesso!',
        ]);
    }

    /**
     * Remover um waypoint.
     * DELETE /freights/{freight}/waypoints/{waypoint}
     */
    public function destroy(Freight $freight, Waypoint $waypoint): JsonResponse
    {
        $this->authorize('delete', $waypoint);

        $this->waypointService->delete($waypoint);

        return response()->json([
            'message' => 'Waypoint removido com sucesso!',
        ]);
    }

    /**
     * Motorista registra chegada ao waypoint.
     * POST /freights/{freight}/waypoints/{waypoint}/checkin
     */
    public function checkin(Freight $freight, Waypoint $waypoint): JsonResponse
    {
        $this->authorize('checkin', $waypoint);

        $waypoint = $this->waypointService->checkin($waypoint);

        return response()->json([
            'data'    => WaypointResource::make($waypoint),
            'message' => 'Check-in realizado com sucesso!',
        ]);
    }

    /**
     * Motorista registra saída do waypoint.
     * POST /freights/{freight}/waypoints/{waypoint}/checkout
     */
    public function checkout(Freight $freight, Waypoint $waypoint): JsonResponse
    {
        $this->authorize('checkin', $waypoint);

        $waypoint = $this->waypointService->checkout($waypoint);

        return response()->json([
            'data'    => WaypointResource::make($waypoint),
            'message' => 'Check-out realizado com sucesso!',
        ]);
    }

    /**
     * Reordenar waypoints de um frete.
     * POST /freights/{freight}/waypoints/reorder
     */
    public function reorder(Freight $freight): JsonResponse
    {
        $this->authorize('create', [Waypoint::class, $freight]);

        $validated = request()->validate([
            'waypoint_ids'   => ['required', 'array', 'min:1'],
            'waypoint_ids.*' => ['required', 'integer', 'exists:waypoints,id'],
        ]);

        $this->waypointService->reorder($freight, $validated['waypoint_ids']);

        $waypoints = $freight->waypoints()->with('creator')->get();

        return response()->json([
            'data'    => WaypointResource::collection($waypoints),
            'message' => 'Waypoints reordenados com sucesso!',
        ]);
    }
}
