<?php

namespace App\Http\Controllers\Api;

use App\Enums\IncidentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\TriggerSosRequest;
use App\Http\Resources\IncidentResource;
use App\Models\Freight;
use App\Services\IncidentService;
use Illuminate\Http\JsonResponse;

class IncidentController extends Controller
{
    public function __construct(
        protected IncidentService $incidentService,
    ) {}

    public function triggerSos(TriggerSosRequest $request, Freight $freight): JsonResponse
    {
        $incident = $this->incidentService->create(
            freight: $freight,
            type: IncidentType::Sos,
            lat: $request->validated('latitude'),
            lng: $request->validated('longitude'),
            description: $request->validated('description'),
        );

        return response()->json([
            'data'    => IncidentResource::make($incident),
            'message' => 'SOS acionado com sucesso!',
        ], 201);
    }

    public function store(StoreIncidentRequest $request, Freight $freight): JsonResponse
    {
        $incident = $this->incidentService->create(
            freight: $freight,
            type: IncidentType::from($request->validated('type')),
            lat: $request->validated('latitude'),
            lng: $request->validated('longitude'),
            description: $request->validated('description'),
        );

        return response()->json([
            'data'    => IncidentResource::make($incident),
            'message' => 'Incidente registrado com sucesso!',
        ], 201);
    }
}
