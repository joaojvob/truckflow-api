<?php

namespace App\Services;

use App\Enums\FreightStatus;
use App\Models\Freight;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD de fretes pelo gestor: criação, edição e cancelamento.
 *
 * Waypoints inline na criação são delegados ao {@see WaypointService}.
 */
class FreightManagementService
{
    public function __construct(
        protected WaypointService $waypointService,
    ) {}

    /**
     * Cria um frete com origem/destino PostGIS e cálculo automático de preço.
     *
     * @param  array<string, mixed>  $data  Dados validados (coordenadas, carga, motorista, waypoints opcionais).
     * @return Freight Frete persistido com relações carregadas.
     */
    public function create(array $data): Freight
    {
        return DB::transaction(function () use ($data) {
            $originLat = $data['origin_lat'];
            $originLng = $data['origin_lng'];
            $destLat = $data['destination_lat'];
            $destLng = $data['destination_lng'];

            $freight = Freight::create([
                'driver_id'              => $data['driver_id'],
                'truck_id'               => $data['truck_id'] ?? null,
                'trailer_id'             => $data['trailer_id'] ?? null,
                'cargo_name'             => $data['cargo_name'] ?? null,
                'cargo_type'             => $data['cargo_type'] ?? null,
                'cargo_description'      => $data['cargo_description'] ?? null,
                'weight'                 => $data['weight'],
                'is_hazardous'           => $data['is_hazardous'] ?? false,
                'is_fragile'             => $data['is_fragile'] ?? false,
                'requires_refrigeration' => $data['requires_refrigeration'] ?? false,
                'status'                 => FreightStatus::Pending,
                'origin'                 => DB::raw("ST_GeomFromText('POINT($originLng $originLat)', 4326)"),
                'destination'            => DB::raw("ST_GeomFromText('POINT($destLng $destLat)', 4326)"),
                'origin_address'         => $data['origin_address'] ?? $this->composeAddress($data, 'origin'),
                'destination_address'    => $data['destination_address'] ?? $this->composeAddress($data, 'destination'),
                'origin_cep'             => $data['origin_cep'] ?? null,
                'origin_street'          => $data['origin_street'] ?? null,
                'origin_number'          => $data['origin_number'] ?? null,
                'origin_complement'      => $data['origin_complement'] ?? null,
                'origin_neighborhood'    => $data['origin_neighborhood'] ?? null,
                'origin_city'            => $data['origin_city'] ?? null,
                'origin_state'           => $data['origin_state'] ?? null,
                'destination_cep'          => $data['destination_cep'] ?? null,
                'destination_street'       => $data['destination_street'] ?? null,
                'destination_number'       => $data['destination_number'] ?? null,
                'destination_complement'   => $data['destination_complement'] ?? null,
                'destination_neighborhood' => $data['destination_neighborhood'] ?? null,
                'destination_city'         => $data['destination_city'] ?? null,
                'destination_state'        => $data['destination_state'] ?? null,
                'required_trailer_type'  => $data['required_trailer_type'] ?? null,
                'required_hitch_type'    => $data['required_hitch_type'] ?? null,
                'distance_km'            => $data['distance_km'] ?? null,
                'estimated_hours'        => $data['estimated_hours'] ?? null,
                'price_per_km'           => $data['price_per_km'] ?? null,
                'price_per_ton'          => $data['price_per_ton'] ?? null,
                'toll_cost'              => $data['toll_cost'] ?? 0,
                'fuel_cost'              => $data['fuel_cost'] ?? 0,
                'deadline_at'            => $data['deadline_at'] ?? null,
                'created_by'             => auth()->id(),
                'enforce_route'          => $data['enforce_route'] ?? false,
            ]);

            $freight->update([
                'total_price' => $freight->calculateTotalPrice(),
            ]);

            if (! empty($data['waypoints'])) {
                $this->waypointService->createBatch($freight, $data['waypoints']);
            }

            $freight->recordActivity(
                action: 'freight_created',
                description: 'Frete criado: '.$this->cargoLabel($freight),
                payload: [
                    'weight'      => $freight->weight,
                    'total_price' => $freight->total_price,
                    'driver_id'   => $freight->driver_id,
                ],
            );

            return $freight->fresh(['driver', 'truck', 'trailer', 'creator', 'waypoints']);
        });
    }

    /**
     * Atualiza campos editáveis de um frete e recalcula preço se necessário.
     *
     * @param  Freight  $freight  Frete não finalizado/cancelado.
     * @param  array<string, mixed>  $data  Campos a atualizar (coordenadas opcionais).
     * @return Freight Frete atualizado.
     *
     * @throws ValidationException Se o frete estiver concluído ou cancelado.
     */
    public function update(Freight $freight, array $data): Freight
    {
        if ($freight->status === FreightStatus::Completed || $freight->status === FreightStatus::Cancelled) {
            throw ValidationException::withMessages([
                'status' => "Frete {$freight->status->label()} não pode ser editado.",
            ]);
        }

        return DB::transaction(function () use ($freight, $data) {
            $updateData = collect($data)->except([
                'origin_lat', 'origin_lng', 'destination_lat', 'destination_lng',
            ])->toArray();

            if (isset($data['origin_lat'], $data['origin_lng'])) {
                $lat = $data['origin_lat'];
                $lng = $data['origin_lng'];
                $updateData['origin'] = DB::raw("ST_GeomFromText('POINT($lng $lat)', 4326)");
            }

            if (isset($data['destination_lat'], $data['destination_lng'])) {
                $lat = $data['destination_lat'];
                $lng = $data['destination_lng'];
                $updateData['destination'] = DB::raw("ST_GeomFromText('POINT($lng $lat)', 4326)");
            }

            $freight->update($updateData);

            if (array_intersect(array_keys($data), ['price_per_km', 'price_per_ton', 'distance_km', 'weight', 'toll_cost', 'fuel_cost'])) {
                $freight->update(['total_price' => $freight->calculateTotalPrice()]);
            }

            $freight->recordActivity(
                action: 'freight_updated',
                description: 'Frete atualizado: '.$this->cargoLabel($freight),
                payload: ['changes' => array_keys($data)],
            );

            return $freight->fresh(['driver', 'truck', 'trailer', 'creator']);
        });
    }

    /**
     * Rótulo legível da carga (tipo ou nome).
     */
    private function cargoLabel(Freight $freight): string
    {
        return $freight->cargo_name
            ?: ($freight->cargo_type?->label() ?? 'Sem descrição');
    }

    /**
     * Monta um endereço legível a partir dos campos estruturados.
     *
     * @param  array<string, mixed>  $data
     */
    private function composeAddress(array $data, string $prefix): ?string
    {
        $parts = array_filter([
            $data["{$prefix}_street"] ?? null,
            $data["{$prefix}_number"] ?? null,
            $data["{$prefix}_neighborhood"] ?? null,
            $data["{$prefix}_city"] ?? null,
            $data["{$prefix}_state"] ?? null,
            $data["{$prefix}_cep"] ?? null,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    /**
     * Cancela um frete respeitando as transições válidas de status.
     *
     * @param  Freight  $freight  Frete elegível para cancelamento.
     * @return Freight Frete com status `cancelled`.
     *
     * @throws ValidationException Se a transição para cancelado não for permitida.
     */
    public function cancel(Freight $freight): Freight
    {
        if (! $freight->status->canTransitionTo(FreightStatus::Cancelled)) {
            throw ValidationException::withMessages([
                'status' => "Frete {$freight->status->label()} não pode ser cancelado.",
            ]);
        }

        $freight->update(['status' => FreightStatus::Cancelled]);

        $freight->recordActivity(
            action: 'freight_cancelled',
            description: 'Frete cancelado: '.$this->cargoLabel($freight),
        );

        return $freight->fresh();
    }
}
