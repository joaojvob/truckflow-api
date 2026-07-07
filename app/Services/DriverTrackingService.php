<?php

namespace App\Services;

use App\Enums\FreightStatus;
use App\Events\DriverLocationUpdated;
use App\Models\DriverLocation;
use App\Models\Freight;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverTrackingService
{
    public function record(Freight $freight, array $data): DriverLocation
    {
        if ($freight->status !== FreightStatus::InTransit) {
            throw ValidationException::withMessages([
                'status' => 'Tracking GPS disponível apenas com frete em trânsito.',
            ]);
        }

        if ($freight->driver_id !== auth()->id()) {
            throw ValidationException::withMessages([
                'freight' => 'Apenas o motorista atribuído pode enviar posição GPS.',
            ]);
        }

        $lat = $data['lat'];
        $lng = $data['lng'];

        $location = DriverLocation::create([
            'tenant_id'   => $freight->tenant_id,
            'freight_id'  => $freight->id,
            'driver_id'   => auth()->id(),
            'location'    => DB::raw("ST_GeomFromText('POINT($lng $lat)', 4326)"),
            'speed_kmh'   => $data['speed_kmh'] ?? null,
            'heading'     => $data['heading'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);

        event(new DriverLocationUpdated($location));

        return $location;
    }

    public function latest(Freight $freight): ?DriverLocation
    {
        return $freight->driverLocations()
            ->latest('recorded_at')
            ->first();
    }

    public function history(Freight $freight, int $limit = 50): Collection
    {
        return $freight->driverLocations()
            ->latest('recorded_at')
            ->limit($limit)
            ->get();
    }
}
