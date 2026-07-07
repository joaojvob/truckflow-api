<?php

namespace App\Services;

use App\Enums\IncidentType;
use App\Events\SosTriggered;
use App\Models\Freight;
use App\Models\Incident;
use Illuminate\Support\Facades\DB;

/**
 * Registra incidentes e alertas SOS durante fretes em andamento.
 */
class IncidentService
{
    /**
     * Cria um incidente georreferenciado e dispara broadcast se for crítico (SOS).
     *
     * @param  Freight  $freight  Frete em andamento.
     * @param  IncidentType  $type  Tipo do incidente (SOS, avaria, acidente, etc.).
     * @param  float  $lat  Latitude WGS84.
     * @param  float  $lng  Longitude WGS84.
     * @param  string|null  $description  Texto livre; usa descrição padrão se omitido.
     * @return Incident Registro criado.
     */
    public function create(Freight $freight, IncidentType $type, float $lat, float $lng, ?string $description = null): Incident
    {
        return DB::transaction(function () use ($freight, $type, $lat, $lng, $description) {
            $incident = $freight->incidents()->create([
                'tenant_id'   => $freight->tenant_id,
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

            if ($type->isCritical()) {
                event(new SosTriggered($incident));
            }

            return $incident;
        });
    }

    /**
     * Retorna descrição padrão em português para cada tipo de incidente.
     *
     * @param  IncidentType  $type  Tipo do incidente.
     */
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
