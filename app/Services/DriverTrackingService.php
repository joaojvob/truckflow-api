<?php

namespace App\Services;

use App\Enums\FreightStatus;
use App\Events\DriverLocationUpdated;
use App\Models\DriverLocation;
use App\Models\Freight;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra e consulta posições GPS do motorista durante fretes em trânsito.
 *
 * Cada registro dispara o evento {@see DriverLocationUpdated} para broadcast via Reverb.
 */
class DriverTrackingService
{
    /**
     * Persiste uma nova posição GPS e notifica clientes em tempo real.
     *
     * @param  Freight  $freight  Frete em trânsito com motorista atribuído.
     * @param  array{lat: float, lng: float, speed_kmh?: float, heading?: float, recorded_at?: \Carbon\Carbon|string}  $data
     * @return DriverLocation Registro criado com geometria PostGIS.
     *
     * @throws ValidationException Se o frete não estiver em trânsito ou o usuário não for o motorista.
     */
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

    /**
     * Retorna a posição mais recente do frete.
     *
     * @param  Freight  $freight  Frete monitorado.
     * @return DriverLocation|null Último registro ou null se nenhum ponto foi enviado.
     */
    public function latest(Freight $freight): ?DriverLocation
    {
        return $freight->driverLocations()
            ->latest('recorded_at')
            ->first();
    }

    /**
     * Lista o histórico de posições ordenado da mais recente para a mais antiga.
     *
     * @param  Freight  $freight  Frete monitorado.
     * @param  int  $limit  Quantidade máxima de registros (padrão: 50).
     * @return Collection<int, DriverLocation>
     */
    public function history(Freight $freight, int $limit = 50): Collection
    {
        return $freight->driverLocations()
            ->latest('recorded_at')
            ->limit($limit)
            ->get();
    }
}
