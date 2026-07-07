<?php

namespace App\Events;

use App\Models\DriverLocation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando o motorista envia uma nova posição GPS.
 *
 * Transmitido via Reverb no canal privado do frete para atualização do mapa em tempo real.
 */
class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  DriverLocation  $driverLocation  Registro recém-criado com coordenadas PostGIS.
     */
    public function __construct(
        public DriverLocation $driverLocation,
    ) {}

    /**
     * Define o canal privado de broadcast (tenant + frete).
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->driverLocation->tenant_id}.freight.{$this->driverLocation->freight_id}"),
        ];
    }

    /**
     * Nome do evento no cliente Echo (ex.: `.driver.location.updated`).
     */
    public function broadcastAs(): string
    {
        return 'driver.location.updated';
    }

    /**
     * Payload enviado ao frontend — sem dados sensíveis além de posição e velocidade.
     *
     * @return array{freight_id: int, driver_id: int, lat: float|null, lng: float|null, speed_kmh: string|null, heading: string|null, recorded_at: string|null}
     */
    public function broadcastWith(): array
    {
        $coordinates = $this->driverLocation->getCoordinates();

        return [
            'freight_id'  => $this->driverLocation->freight_id,
            'driver_id'   => $this->driverLocation->driver_id,
            'lat'         => $coordinates['lat'] ?? null,
            'lng'         => $coordinates['lng'] ?? null,
            'speed_kmh'   => $this->driverLocation->speed_kmh,
            'heading'     => $this->driverLocation->heading,
            'recorded_at' => $this->driverLocation->recorded_at?->toISOString(),
        ];
    }
}
