<?php

namespace App\Contracts;

/**
 * Contrato para provedores de roteamento e busca de lugares.
 *
 * Implementações: {@see \App\Services\GoogleMapsService} (direto) ou
 * {@see \App\Services\JavaGeoRoutingProvider} (microserviço Java).
 */
interface RoutingProvider
{
    /**
     * @param  array<int, array{lat: float, lng: float}>  $waypoints
     * @return array{
     *     polyline: string,
     *     distance_meters: int,
     *     duration_seconds: int,
     *     bounds: array<string, mixed>|null
     * }
     */
    public function getDirections(
        float $originLat,
        float $originLng,
        float $destinationLat,
        float $destinationLng,
        array $waypoints = [],
    ): array;

    /**
     * @return array<int, array{
     *     place_id: string,
     *     name: string,
     *     address: string|null,
     *     lat: float,
     *     lng: float,
     *     rating: float|null,
     *     open_now: bool|null
     * }>
     */
    public function searchNearbyPlaces(
        float $lat,
        float $lng,
        string $type,
        int $radiusMeters = 5000,
    ): array;
}
