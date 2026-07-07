<?php

namespace App\Services;

use App\Models\Freight;
use App\Models\Waypoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gerencia paradas intermediárias (waypoints) ao longo da rota de um frete.
 */
class WaypointService
{
    /**
     * Cria um waypoint georreferenciado vinculado ao frete.
     *
     * @param  Freight  $freight  Frete dono do waypoint.
     * @param  array<string, mixed>  $data  Nome, tipo, lat/lng, ordem e flags opcionais.
     * @return Waypoint Registro criado.
     */
    public function create(Freight $freight, array $data): Waypoint
    {
        $lat = $data['lat'];
        $lng = $data['lng'];

        $order = $data['order'] ?? $freight->waypoints()->count();

        $waypoint = Waypoint::create([
            'tenant_id'              => $freight->tenant_id,
            'freight_id'             => $freight->id,
            'created_by'             => auth()->id(),
            'name'                   => $data['name'],
            'description'            => $data['description'] ?? null,
            'type'                   => $data['type'],
            'location'               => DB::raw("ST_GeomFromText('POINT($lng $lat)', 4326)"),
            'address'                => $data['address'] ?? null,
            'order'                  => $order,
            'mandatory'              => $data['mandatory'] ?? false,
            'estimated_stop_minutes' => $data['estimated_stop_minutes'] ?? null,
        ]);

        $freight->recordActivity(
            action: 'waypoint_created',
            description: "Waypoint adicionado: {$waypoint->name}",
            payload: [
                'waypoint_id' => $waypoint->id,
                'type'        => $waypoint->type->value,
                'mandatory'   => $waypoint->mandatory,
            ],
        );

        return $waypoint->fresh(['creator']);
    }

    /**
     * Cria múltiplos waypoints em sequência (usado na criação do frete).
     *
     * @param  Freight  $freight  Frete recém-criado.
     * @param  array<int, array<string, mixed>>  $waypointsData  Lista de waypoints validados.
     */
    public function createBatch(Freight $freight, array $waypointsData): void
    {
        foreach ($waypointsData as $index => $data) {
            $data['order'] = $data['order'] ?? $index;
            $this->create($freight, $data);
        }
    }

    /**
     * Atualiza dados e/ou coordenadas de um waypoint existente.
     *
     * @param  Waypoint  $waypoint  Waypoint a editar.
     * @param  array<string, mixed>  $data  Campos a atualizar.
     * @return Waypoint Registro atualizado.
     */
    public function update(Waypoint $waypoint, array $data): Waypoint
    {
        $updateData = collect($data)->except(['lat', 'lng'])->toArray();

        if (isset($data['lat'], $data['lng'])) {
            $lat = $data['lat'];
            $lng = $data['lng'];
            $updateData['location'] = DB::raw("ST_GeomFromText('POINT($lng $lat)', 4326)");
        }

        $waypoint->update($updateData);

        $waypoint->freight->recordActivity(
            action: 'waypoint_updated',
            description: "Waypoint atualizado: {$waypoint->name}",
            payload: ['waypoint_id' => $waypoint->id, 'changes' => array_keys($data)],
        );

        return $waypoint->fresh(['creator']);
    }

    /**
     * Remove um waypoint e reordena os restantes.
     *
     * @param  Waypoint  $waypoint  Waypoint a excluir.
     */
    public function delete(Waypoint $waypoint): void
    {
        $freight = $waypoint->freight;
        $name = $waypoint->name;

        $waypoint->delete();

        $freight->waypoints()->orderBy('order')->get()->each(function ($wp, $index) {
            $wp->update(['order' => $index]);
        });

        $freight->recordActivity(
            action: 'waypoint_deleted',
            description: "Waypoint removido: {$name}",
        );
    }

    /**
     * Motorista registra chegada ao waypoint.
     *
     * @param  Waypoint  $waypoint  Parada a visitar.
     * @return Waypoint Waypoint com `arrived_at` preenchido.
     *
     * @throws ValidationException Se o check-in já foi realizado.
     */
    public function checkin(Waypoint $waypoint): Waypoint
    {
        if ($waypoint->isVisited()) {
            throw ValidationException::withMessages([
                'waypoint' => 'Check-in já realizado neste waypoint.',
            ]);
        }

        $waypoint->markArrival();

        $waypoint->freight->recordActivity(
            action: 'waypoint_checkin',
            description: "Motorista chegou ao waypoint: {$waypoint->name}",
            payload: ['waypoint_id' => $waypoint->id],
        );

        return $waypoint->fresh();
    }

    /**
     * Motorista registra saída do waypoint após o check-in.
     *
     * @param  Waypoint  $waypoint  Parada visitada.
     * @return Waypoint Waypoint com `departed_at` preenchido.
     *
     * @throws ValidationException Se check-in não foi feito ou check-out já realizado.
     */
    public function checkout(Waypoint $waypoint): Waypoint
    {
        if (! $waypoint->isVisited()) {
            throw ValidationException::withMessages([
                'waypoint' => 'Faça o check-in antes do check-out.',
            ]);
        }

        if ($waypoint->isCompleted()) {
            throw ValidationException::withMessages([
                'waypoint' => 'Check-out já realizado neste waypoint.',
            ]);
        }

        $waypoint->markDeparture();

        $waypoint->freight->recordActivity(
            action: 'waypoint_checkout',
            description: "Motorista saiu do waypoint: {$waypoint->name}",
            payload: ['waypoint_id' => $waypoint->id],
        );

        return $waypoint->fresh();
    }

    /**
     * Reordena waypoints conforme a sequência de IDs informada.
     *
     * @param  Freight  $freight  Frete dono dos waypoints.
     * @param  array<int, int>  $orderedIds  IDs na ordem desejada de parada.
     */
    public function reorder(Freight $freight, array $orderedIds): void
    {
        DB::transaction(function () use ($freight, $orderedIds) {
            foreach ($orderedIds as $index => $waypointId) {
                $freight->waypoints()
                    ->where('id', $waypointId)
                    ->update(['order' => $index]);
            }
        });

        $freight->recordActivity(
            action: 'waypoints_reordered',
            description: 'Waypoints reordenados',
        );
    }
}
