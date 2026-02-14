<?php

namespace App\Services;

use App\Enums\TruckStatus;
use App\Models\Truck;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TruckService
{
    /**
     * Registra um novo caminhão.
     */
    public function create(array $data): Truck
    {
        return DB::transaction(function () use ($data) {
            $truck = Truck::create([
                'tenant_id'          => auth()->user()->tenant_id,
                'driver_id'          => $data['driver_id'] ?? auth()->id(),
                'plate'              => strtoupper($data['plate']),
                'renavam'            => $data['renavam'] ?? null,
                'brand'              => $data['brand'],
                'model'              => $data['model'],
                'year'               => $data['year'],
                'color'              => $data['color'] ?? null,
                'axle_count'         => $data['axle_count'] ?? 2,
                'max_weight'         => $data['max_weight'],
                'has_trailer_hitch'  => $data['has_trailer_hitch'] ?? false,
                'hitch_type'         => $data['hitch_type'] ?? null,
                'status'             => TruckStatus::Available,
                'odometer'           => $data['odometer'] ?? 0,
            ]);

            $truck->recordActivity(
                action: 'truck_registered',
                description: "Caminhão registrado: {$truck->brand} {$truck->model} - {$truck->plate}",
            );

            return $truck->fresh('driver');
        });
    }

    /**
     * Atualiza os dados de um caminhão.
     */
    public function update(Truck $truck, array $data): Truck
    {
        return DB::transaction(function () use ($truck, $data) {
            if (isset($data['plate'])) {
                $data['plate'] = strtoupper($data['plate']);
            }

            $truck->update($data);

            $truck->recordActivity(
                action: 'truck_updated',
                description: "Caminhão atualizado: {$truck->plate}",
                payload: ['changes' => array_keys($data)],
            );

            return $truck->fresh('driver');
        });
    }

    /**
     * Altera o status de um caminhão.
     *
     * @throws ValidationException
     */
    public function updateStatus(Truck $truck, TruckStatus $newStatus): Truck
    {
        if ($truck->status === $newStatus) {
            throw ValidationException::withMessages([
                'status' => "Caminhão já está com status {$newStatus->label()}.",
            ]);
        }

        if ($newStatus === TruckStatus::Available && $truck->freights()->whereIn('status', ['in_transit'])->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Caminhão não pode ficar disponível enquanto há frete em trânsito.',
            ]);
        }

        $truck->update(['status' => $newStatus]);

        $truck->recordActivity(
            action: 'truck_status_changed',
            description: "Status do caminhão alterado para {$newStatus->label()}",
            payload: ['new_status' => $newStatus->value],
        );

        return $truck->fresh();
    }

    /**
     * Atribui ou reatribui um motorista ao caminhão.
     */
    public function assignDriver(Truck $truck, ?int $driverId): Truck
    {
        $truck->update(['driver_id' => $driverId]);

        $truck->recordActivity(
            action: 'truck_driver_assigned',
            description: $driverId
                ? "Motorista #{$driverId} atribuído ao caminhão {$truck->plate}"
                : "Motorista removido do caminhão {$truck->plate}",
        );

        return $truck->fresh('driver');
    }
}
