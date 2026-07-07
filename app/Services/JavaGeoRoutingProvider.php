<?php

namespace App\Services;

use App\Contracts\RoutingProvider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Delega roteamento ao microserviço Java {@see truckflow-geo}.
 *
 * Ativado com `GEO_ROUTING_DRIVER=java` em `.env`.
 */
class JavaGeoRoutingProvider implements RoutingProvider
{
    public function getDirections(
        float $originLat,
        float $originLng,
        float $destinationLat,
        float $destinationLng,
        array $waypoints = [],
    ): array {
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->post($this->baseUrl().'/api/v1/directions', [
                    'origin'      => ['lat' => $originLat, 'lng' => $originLng],
                    'destination' => ['lat' => $destinationLat, 'lng' => $destinationLng],
                    'waypoints'   => $waypoints,
                ])
                ->throw();
        } catch (RequestException $exception) {
            app(SystemLogger::class)->warning(
                'Microserviço geo indisponível (directions).',
                ['channel' => 'geo_java'],
                $exception,
                'geo_java',
            );

            throw ValidationException::withMessages([
                'google_maps' => 'Não foi possível calcular a rota no serviço de geolocalização.',
            ]);
        }

        $data = $response->json('data');

        return [
            'polyline'         => $data['polyline'] ?? '',
            'distance_meters'  => (int) ($data['distance_meters'] ?? 0),
            'duration_seconds' => (int) ($data['duration_seconds'] ?? 0),
            'bounds'           => $data['bounds'] ?? null,
        ];
    }

    public function searchNearbyPlaces(
        float $lat,
        float $lng,
        string $type,
        int $radiusMeters = 5000,
    ): array {
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get($this->baseUrl().'/api/v1/places/nearby', [
                    'lat'    => $lat,
                    'lng'    => $lng,
                    'type'   => $type,
                    'radius' => $radiusMeters,
                ])
                ->throw();
        } catch (RequestException $exception) {
            app(SystemLogger::class)->warning(
                'Microserviço geo indisponível (places).',
                ['channel' => 'geo_java'],
                $exception,
                'geo_java',
            );

            throw ValidationException::withMessages([
                'google_maps' => 'Não foi possível buscar locais no serviço de geolocalização.',
            ]);
        }

        return $response->json('data', []);
    }

    private function baseUrl(): string
    {
        return rtrim(config('services.geo.java_url'), '/');
    }
}
