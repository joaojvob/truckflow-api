<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GoogleMapsService
{
    private const DIRECTIONS_URL = 'https://maps.googleapis.com/maps/api/directions/json';

    /**
     * @param  array<int, array{lat: float, lng: float}>  $waypoints
     * @return array{
     *     polyline: string,
     *     distance_meters: int,
     *     duration_seconds: int,
     *     bounds: array<string, mixed>|null
     * }
     *
     * @throws ValidationException
     */
    public function getDirections(
        float $originLat,
        float $originLng,
        float $destinationLat,
        float $destinationLng,
        array $waypoints = [],
    ): array {
        $apiKey = config('services.google_maps.api_key');

        if (! $apiKey) {
            throw ValidationException::withMessages([
                'google_maps' => 'A chave GOOGLE_MAPS_API_KEY não está configurada.',
            ]);
        }

        $params = [
            'origin'      => "{$originLat},{$originLng}",
            'destination' => "{$destinationLat},{$destinationLng}",
            'key'         => $apiKey,
            'language'    => 'pt-BR',
            'region'      => 'br',
        ];

        if ($waypoints !== []) {
            $params['waypoints'] = collect($waypoints)
                ->map(fn (array $point) => "{$point['lat']},{$point['lng']}")
                ->implode('|');
        }

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->get(self::DIRECTIONS_URL, $params)
                ->throw();
        } catch (RequestException $exception) {
            throw ValidationException::withMessages([
                'google_maps' => 'Não foi possível consultar a Google Directions API.',
            ]);
        }

        $data = $response->json();

        if (($data['status'] ?? null) !== 'OK' || empty($data['routes'][0])) {
            $message = $this->translateDirectionsStatus($data['status'] ?? 'UNKNOWN_ERROR');

            throw ValidationException::withMessages([
                'google_maps' => $message,
            ]);
        }

        $route = $data['routes'][0];

        return [
            'polyline'          => $route['overview_polyline']['points'],
            'distance_meters'   => (int) collect($route['legs'])->sum('distance.value'),
            'duration_seconds'  => (int) collect($route['legs'])->sum('duration.value'),
            'bounds'            => $route['bounds'] ?? null,
        ];
    }

    private function translateDirectionsStatus(string $status): string
    {
        return match ($status) {
            'ZERO_RESULTS'     => 'Nenhuma rota encontrada entre origem e destino.',
            'NOT_FOUND'        => 'Origem ou destino não encontrados.',
            'OVER_QUERY_LIMIT' => 'Limite de requisições da Google Maps API excedido.',
            'REQUEST_DENIED'   => 'Requisição negada pela Google Maps API. Verifique a chave e as APIs habilitadas.',
            'INVALID_REQUEST'  => 'Requisição inválida para a Google Directions API.',
            default            => 'Erro ao calcular a rota na Google Maps API.',
        };
    }
}
