<?php

namespace App\Services\Routing;

use App\Contracts\RoutingProvider;

/**
 * Provedor de rota offline baseado na fórmula de Haversine.
 *
 * Calcula distância em linha reta entre pontos, aplica um fator de sinuosidade
 * (road_factor) para aproximar a distância rodoviária e estima a duração pela
 * velocidade média configurada. Serve como fallback enquanto não há Google Maps.
 *
 * A polyline retornada é uma codificação (Google Encoded Polyline) da sequência
 * de pontos, permitindo desenhar a rota como linha reta sem depender de mapa.
 */
class HaversineRoutingProvider implements RoutingProvider
{
    private const EARTH_RADIUS_METERS = 6371000;

    public function getDirections(
        float $originLat,
        float $originLng,
        float $destinationLat,
        float $destinationLng,
        array $waypoints = [],
    ): array {
        $points = [
            ['lat' => $originLat, 'lng' => $originLng],
            ...$waypoints,
            ['lat' => $destinationLat, 'lng' => $destinationLng],
        ];

        $roadFactor = (float) config('services.geo.road_factor', 1.3);
        $avgSpeed = max(1.0, (float) config('services.geo.avg_speed_kmh', 65));

        $straightMeters = 0.0;
        for ($i = 0; $i < count($points) - 1; $i++) {
            $straightMeters += $this->haversineMeters(
                $points[$i]['lat'], $points[$i]['lng'],
                $points[$i + 1]['lat'], $points[$i + 1]['lng'],
            );
        }

        $distanceMeters = (int) round($straightMeters * $roadFactor);
        $durationSeconds = (int) round(($distanceMeters / 1000) / $avgSpeed * 3600);

        return [
            'polyline'         => $this->encodePolyline($points),
            'distance_meters'  => $distanceMeters,
            'duration_seconds' => $durationSeconds,
            'bounds'           => $this->boundsFor($points),
        ];
    }

    public function searchNearbyPlaces(
        float $lat,
        float $lng,
        string $type,
        int $radiusMeters = 5000,
    ): array {
        // Sem provedor de places no modo offline.
        return [];
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $points
     */
    private function boundsFor(array $points): array
    {
        $lats = array_column($points, 'lat');
        $lngs = array_column($points, 'lng');

        return [
            'northeast' => ['lat' => max($lats), 'lng' => max($lngs)],
            'southwest' => ['lat' => min($lats), 'lng' => min($lngs)],
        ];
    }

    /**
     * Codifica pontos no formato Google Encoded Polyline Algorithm.
     *
     * @param  array<int, array{lat: float, lng: float}>  $points
     */
    private function encodePolyline(array $points): string
    {
        $result = '';
        $prevLat = 0;
        $prevLng = 0;

        foreach ($points as $point) {
            $lat = (int) round($point['lat'] * 1e5);
            $lng = (int) round($point['lng'] * 1e5);

            $result .= $this->encodeValue($lat - $prevLat);
            $result .= $this->encodeValue($lng - $prevLng);

            $prevLat = $lat;
            $prevLng = $lng;
        }

        return $result;
    }

    private function encodeValue(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : ($value << 1);
        $chunks = '';

        while ($value >= 0x20) {
            $chunks .= chr((0x20 | ($value & 0x1f)) + 63);
            $value >>= 5;
        }

        return $chunks.chr($value + 63);
    }
}
