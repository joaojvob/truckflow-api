<?php

namespace App\Services;

use App\Models\Freight;
use App\Models\Waypoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaypointService
{
    /**
     * Cria um waypoint para um frete.
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
     * Cria múltiplos waypoints de uma vez (batch na criação do frete).
     */
    public function createBatch(Freight $freight, array $waypointsData): void
    {
        foreach ($waypointsData as $index => $data) {
            $data['order'] = $data['order'] ?? $index;
            $this->create($freight, $data);
        }
    }

    /**
     * Atualiza um waypoint existente.
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
     * Remove um waypoint.
     */
    public function delete(Waypoint $waypoint): void
    {
        $freight = $waypoint->freight;
        $name = $waypoint->name;

        $waypoint->delete();

        // Reordenar os waypoints restantes
        $freight->waypoints()->orderBy('order')->get()->each(function ($wp, $index) {
            $wp->update(['order' => $index]);
        });

        $freight->recordActivity(
            action: 'waypoint_deleted',
            description: "Waypoint removido: {$name}",
        );
    }

    /**
     * Motorista faz check-in (chegou no waypoint).
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
     * Motorista faz check-out (saiu do waypoint).
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
     * Reordena os waypoints de um frete.
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
