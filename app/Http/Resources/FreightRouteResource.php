<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FreightRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'freight_id'       => $this->id,
            'polyline'         => $this->route_polyline,
            'distance_meters'  => $this->route_distance_meters,
            'distance_km'      => $this->distance_km,
            'duration_seconds' => $this->route_duration_seconds,
            'estimated_hours'  => $this->estimated_hours,
            'calculated_at'    => $this->route_calculated_at,
            'origin'           => $this->getOriginCoordinates(),
            'destination'      => $this->getDestinationCoordinates(),
            'waypoints'        => WaypointResource::collection($this->whenLoaded('waypoints')),
        ];
    }
}
