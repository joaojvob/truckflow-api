<?php

namespace App\Services;

use App\Enums\FreightStatus;
use App\Models\Freight;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FreightManagementService
{
    /**
     * Cria um novo frete com cálculo automático de preço.
     */
    public function create(array $data): Freight
    {
        return DB::transaction(function () use ($data) {
            $originLat = $data['origin_lat'];
            $originLng = $data['origin_lng'];
            $destLat   = $data['destination_lat'];
            $destLng   = $data['destination_lng'];

            $freight = Freight::create([
                'tenant_id'              => auth()->user()->tenant_id,
                'driver_id'              => $data['driver_id'],
                'truck_id'               => $data['truck_id'] ?? null,
                'trailer_id'             => $data['trailer_id'] ?? null,
                'cargo_name'             => $data['cargo_name'],
                'cargo_description'      => $data['cargo_description'] ?? null,
                'weight'                 => $data['weight'],
                'is_hazardous'           => $data['is_hazardous'] ?? false,
                'is_fragile'             => $data['is_fragile'] ?? false,
                'requires_refrigeration' => $data['requires_refrigeration'] ?? false,
                'status'                 => FreightStatus::Pending,
                'origin'                 => DB::raw("ST_GeomFromText('POINT($originLng $originLat)', 4326)"),
                'destination'            => DB::raw("ST_GeomFromText('POINT($destLng $destLat)', 4326)"),
                'origin_address'         => $data['origin_address'],
                'destination_address'    => $data['destination_address'],
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
            ]);

            // Calcular preço total automaticamente
            $freight->update([
                'total_price' => $freight->calculateTotalPrice(),
            ]);

            $freight->recordActivity(
                action: 'freight_created',
                description: "Frete criado: {$freight->cargo_name}",
                payload: [
                    'weight'      => $freight->weight,
                    'total_price' => $freight->total_price,
                    'driver_id'   => $freight->driver_id,
                ],
            );

            return $freight->fresh(['driver', 'truck', 'trailer', 'creator']);
        });
    }

    /**
     * Atualiza um frete existente.
     *
     * @throws ValidationException
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

            // Atualizar coordenadas se enviadas
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

            // Recalcular preço se algum componente mudou
            if (array_intersect(array_keys($data), ['price_per_km', 'price_per_ton', 'distance_km', 'weight', 'toll_cost', 'fuel_cost'])) {
                $freight->update(['total_price' => $freight->calculateTotalPrice()]);
            }

            $freight->recordActivity(
                action: 'freight_updated',
                description: "Frete atualizado: {$freight->cargo_name}",
                payload: ['changes' => array_keys($data)],
            );

            return $freight->fresh(['driver', 'truck', 'trailer', 'creator']);
        });
    }

    /**
     * Cancela um frete.
     *
     * @throws ValidationException
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
            description: "Frete cancelado: {$freight->cargo_name}",
        );

        return $freight->fresh();
    }
}
