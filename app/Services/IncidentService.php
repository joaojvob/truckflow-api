<?php

namespace App\Services;

use App\Enums\IncidentType;
use App\Models\Freight;
use App\Models\Incident;
use Illuminate\Support\Facades\DB;

class IncidentService
{
    /**
     * Registra um incidente vinculado a um frete.
     */
    public function create(Freight $freight, IncidentType $type, float $lat, float $lng, ?string $description = null): Incident
    {
        return DB::transaction(function () use ($freight, $type, $lat, $lng, $description) {
            $incident = $freight->incidents()->create([
                'tenant_id'   => auth()->user()->tenant_id,
                'user_id'     => auth()->id(),
                'type'        => $type,
                'description' => $description ?? $this->defaultDescription($type),
                'location'    => DB::raw("ST_GeomFromText('POINT($lng $lat)', 4326)"),
            ]);

            $freight->recordActivity(
                action: $type === IncidentType::Sos ? 'sos_triggered' : 'incident_reported',
                description: "{$type->label()} reportado durante o frete.",
                payload: [
                    'type'        => $type->value,
                    'latitude'    => $lat,
                    'longitude'   => $lng,
                    'incident_id' => $incident->id,
                ],
            );

            // TODO: Disparar evento para WebSocket (Laravel Reverb)
            // if ($type->isCritical()) {
            //     event(new \App\Events\SosTriggered($incident));
            // }

            return $incident;
        });
    }

    private function defaultDescription(IncidentType $type): string
    {
        return match ($type) {
            IncidentType::Sos       => 'Alerta SOS acionado pelo motorista.',
            IncidentType::Breakdown => 'Avaria mecânica reportada.',
            IncidentType::Accident  => 'Acidente reportado.',
            IncidentType::Robbery   => 'Roubo reportado.',
        };
    }
}
