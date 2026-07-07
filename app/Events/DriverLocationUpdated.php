<?php

namespace App\Events;

use App\Models\DriverLocation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DriverLocation $driverLocation,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->driverLocation->tenant_id}.freight.{$this->driverLocation->freight_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'driver.location.updated';
    }

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
