<?php

namespace App\Services;

use App\Enums\FreightStatus;
use App\Models\Freight;
use Illuminate\Validation\ValidationException;

class FreightRouteService
{
    public function __construct(
        protected GoogleMapsService $googleMapsService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function calculate(Freight $freight): Freight
    {
        if (in_array($freight->status, [FreightStatus::Completed, FreightStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'status' => "Frete {$freight->status->label()} não pode ter a rota recalculada.",
            ]);
        }

        $freight->load('waypoints');

        $origin = $freight->getOriginCoordinates();
        $destination = $freight->getDestinationCoordinates();

        if (! $origin || ! $destination) {
            throw ValidationException::withMessages([
                'route' => 'Origem e destino precisam estar definidos para calcular a rota.',
            ]);
        }

        $waypoints = $freight->waypoints
            ->map(fn ($waypoint) => $waypoint->getCoordinates())
            ->filter()
            ->values()
            ->all();

        $route = $this->googleMapsService->getDirections(
            originLat: $origin['lat'],
            originLng: $origin['lng'],
            destinationLat: $destination['lat'],
            destinationLng: $destination['lng'],
            waypoints: $waypoints,
        );

        $distanceKm = round($route['distance_meters'] / 1000, 2);
        $estimatedHours = round($route['duration_seconds'] / 3600, 1);

        $freight->update([
            'route_polyline'         => $route['polyline'],
            'route_distance_meters'  => $route['distance_meters'],
            'route_duration_seconds' => $route['duration_seconds'],
            'route_calculated_at'    => now(),
            'distance_km'            => $distanceKm,
            'estimated_hours'        => $estimatedHours,
        ]);

        $freight->update([
            'total_price' => $freight->calculateTotalPrice(),
        ]);

        $freight->recordActivity(
            action: 'route_calculated',
            description: "Rota calculada via Google Directions: {$distanceKm} km",
            payload: [
                'distance_meters'  => $route['distance_meters'],
                'duration_seconds' => $route['duration_seconds'],
                'waypoints_count'  => count($waypoints),
            ],
        );

        return $freight->fresh(['driver', 'waypoints']);
    }
}
