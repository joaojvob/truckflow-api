<?php

namespace App\Services;

use App\Models\Trailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrailerService
{
    /**
     * Registra um novo reboque/engate.
     */
    public function create(array $data): Trailer
    {
        return DB::transaction(function () use ($data) {
            $trailer = Trailer::create([
                'tenant_id'  => auth()->user()->tenant_id,
                'driver_id'  => $data['driver_id'] ?? auth()->id(),
                'plate'      => strtoupper($data['plate']),
                'renavam'    => $data['renavam'] ?? null,
                'type'       => $data['type'],
                'brand'      => $data['brand'] ?? null,
                'model'      => $data['model'] ?? null,
                'year'       => $data['year'] ?? null,
                'axle_count' => $data['axle_count'] ?? 2,
                'max_weight' => $data['max_weight'],
                'length'     => $data['length'] ?? null,
                'hitch_type' => $data['hitch_type'],
                'status'     => 'available',
                'is_loaded'  => false,
            ]);

            $trailer->recordActivity(
                action: 'trailer_registered',
                description: "Reboque registrado: {$trailer->type->label()} - {$trailer->plate}",
            );

            return $trailer->fresh('driver');
        });
    }

    /**
     * Atualiza os dados de um reboque.
     */
    public function update(Trailer $trailer, array $data): Trailer
    {
        return DB::transaction(function () use ($trailer, $data) {
            if (isset($data['plate'])) {
                $data['plate'] = strtoupper($data['plate']);
            }

            $trailer->update($data);

            $trailer->recordActivity(
                action: 'trailer_updated',
                description: "Reboque atualizado: {$trailer->plate}",
                payload: ['changes' => array_keys($data)],
            );

            return $trailer->fresh('driver');
        });
    }

    /**
     * Atribui ou reatribui um motorista ao reboque.
     */
    public function assignDriver(Trailer $trailer, ?int $driverId): Trailer
    {
        $trailer->update(['driver_id' => $driverId]);

        $trailer->recordActivity(
            action: 'trailer_driver_assigned',
            description: $driverId
                ? "Motorista #{$driverId} atribuído ao reboque {$trailer->plate}"
                : "Motorista removido do reboque {$trailer->plate}",
        );

        return $trailer->fresh('driver');
    }

    /**
     * Marca o reboque como carregado ou descarregado.
     *
     * @throws ValidationException
     */
    public function toggleLoaded(Trailer $trailer, bool $isLoaded): Trailer
    {
        if ($trailer->is_loaded === $isLoaded) {
            $state = $isLoaded ? 'carregado' : 'descarregado';
            throw ValidationException::withMessages([
                'is_loaded' => "Reboque já está {$state}.",
            ]);
        }

        $trailer->update(['is_loaded' => $isLoaded]);

        return $trailer->fresh();
    }
}
