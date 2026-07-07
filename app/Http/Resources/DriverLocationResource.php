<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $coordinates = $this->getCoordinates();

        return [
            'id'          => $this->id,
            'freight_id'  => $this->freight_id,
            'driver_id'   => $this->driver_id,
            'lat'         => $coordinates['lat'] ?? null,
            'lng'         => $coordinates['lng'] ?? null,
            'speed_kmh'   => $this->speed_kmh,
            'heading'     => $this->heading,
            'recorded_at' => $this->recorded_at,
            'created_at'  => $this->created_at,
        ];
    }
}
