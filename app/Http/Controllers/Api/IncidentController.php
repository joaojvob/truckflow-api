<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Freight;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class IncidentController extends Controller
{
    use ApiResponser;

    /**
     * Dispara um alerta de SOS com a localização do motorista.
     */
    public function triggerSos(Request $request, int $freightId): JsonResponse
    {
        $validated = $request->validate([
            'latitude'    => ['required', 'numeric', 'between:-90,90'],
            'longitude'   => ['required', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $incident = DB::transaction(function () use ($freightId, $validated) {
                $freight = Freight::findOrFail($freightId);

                $lat = $validated['latitude'];
                $lng = $validated['longitude'];

                $incident = Incident::create([
                    'tenant_id'   => auth()->user()->tenant_id,
                    'freight_id'  => $freight->id,
                    'user_id'     => auth()->id(),
                    'type'        => 'sos',
                    'description' => $validated['description'] ?? 'Alerta SOS acionado pelo motorista.',
                    'location'    => DB::raw("ST_GeomFromText('POINT($lng $lat)', 4326)"),
                ]);

                $freight->recordActivity(
                    action: 'sos_triggered',
                    description: 'SOS acionado durante o frete.',
                    payload: [
                        'latitude'  => $lat,
                        'longitude' => $lng,
                        'incident_id' => $incident->id,
                    ]
                );

                // TODO: Disparar evento SosTriggered para WebSocket (Laravel Reverb)
                // event(new \App\Events\SosTriggered($incident));

                return $incident;
            });

            return $this->successResponse($incident, 'SOS acionado com sucesso!', 201);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Não foi possível acionar o SOS.');
        }
    }

    /**
     * Reportar incidente genérico (breakdown, accident, robbery).
     */
    public function store(Request $request, int $freightId): JsonResponse
    {
        $validated = $request->validate([
            'type'        => ['required', 'string', 'in:breakdown,accident,robbery'],
            'latitude'    => ['required', 'numeric', 'between:-90,90'],
            'longitude'   => ['required', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $incident = DB::transaction(function () use ($freightId, $validated) {
                $freight = Freight::findOrFail($freightId);

                $lat = $validated['latitude'];
                $lng = $validated['longitude'];

                $incident = Incident::create([
                    'tenant_id'   => auth()->user()->tenant_id,
                    'freight_id'  => $freight->id,
                    'user_id'     => auth()->id(),
                    'type'        => $validated['type'],
                    'description' => $validated['description'],
                    'location'    => DB::raw("ST_GeomFromText('POINT($lng $lat)', 4326)"),
                ]);

                $freight->recordActivity(
                    action: 'incident_reported',
                    description: "Incidente reportado: {$validated['type']}",
                    payload: [
                        'type'      => $validated['type'],
                        'latitude'  => $lat,
                        'longitude' => $lng,
                    ]
                );

                return $incident;
            });

            return $this->successResponse($incident, 'Incidente registrado com sucesso!', 201);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Não foi possível registrar o incidente.');
        }
    }
}
