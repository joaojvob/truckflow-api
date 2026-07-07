<?php

namespace App\Services;

use App\Contracts\RoutingProvider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Cliente HTTP para Google Maps Platform (Directions e Places Nearby Search).
 *
 * Requer `GOOGLE_MAPS_API_KEY` em config/services.php.
 */
class GoogleMapsService implements RoutingProvider
{
    private const DIRECTIONS_URL = 'https://maps.googleapis.com/maps/api/directions/json';

    private const PLACES_NEARBY_URL = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';

    public function __construct(
        protected SystemLogger $systemLogger,
    ) {}

    /**
     * Consulta a Google Directions API e retorna polyline, distância e duração.
     *
     * @param  float  $originLat  Latitude da origem.
     * @param  float  $originLng  Longitude da origem.
     * @param  float  $destinationLat  Latitude do destino.
     * @param  float  $destinationLng  Longitude do destino.
     * @param  array<int, array{lat: float, lng: float}>  $waypoints  Paradas intermediárias opcionais.
     * @return array{
     *     polyline: string,
     *     distance_meters: int,
     *     duration_seconds: int,
     *     bounds: array<string, mixed>|null
     * }
     *
     * @throws ValidationException Chave ausente ou erro na API.
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
            $this->systemLogger->warning(
                'Google Directions API indisponível.',
                ['channel' => 'google_maps', 'origin' => "{$originLat},{$originLng}"],
                $exception,
                'google_maps',
            );

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
            'polyline'         => $route['overview_polyline']['points'] ?? '',
            'distance_meters'  => (int) collect($route['legs'] ?? [])->sum('distance.value'),
            'duration_seconds' => (int) collect($route['legs'] ?? [])->sum('duration.value'),
            'bounds'           => $route['bounds'] ?? null,
        ];
    }

    /**
     * Traduz códigos de status da Directions API para mensagens em português.
     *
     * @param  string  $status  Código retornado pela API (ex.: ZERO_RESULTS).
     */
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

    /**
     * Busca estabelecimentos próximos via Google Places Nearby Search.
     *
     * @param  float  $lat  Latitude do ponto de referência.
     * @param  float  $lng  Longitude do ponto de referência.
     * @param  string  $type  Tipo Google Places (ex.: gas_station, restaurant).
     * @param  int  $radiusMeters  Raio de busca em metros (padrão: 5000).
     * @return array<int, array{
     *     place_id: string,
     *     name: string,
     *     address: string|null,
     *     lat: float,
     *     lng: float,
     *     rating: float|null,
     *     open_now: bool|null
     * }>
     *
     * @throws ValidationException Chave ausente ou erro na API.
     */
    public function searchNearbyPlaces(
        float $lat,
        float $lng,
        string $type,
        int $radiusMeters = 5000,
    ): array {
        $apiKey = config('services.google_maps.api_key');

        if (! $apiKey) {
            throw ValidationException::withMessages([
                'google_maps' => 'A chave GOOGLE_MAPS_API_KEY não está configurada.',
            ]);
        }

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->get(self::PLACES_NEARBY_URL, [
                    'location' => "{$lat},{$lng}",
                    'radius'   => $radiusMeters,
                    'type'     => $type,
                    'key'      => $apiKey,
                    'language' => 'pt-BR',
                ])
                ->throw();
        } catch (RequestException $exception) {
            $this->systemLogger->warning(
                'Google Places API indisponível.',
                ['channel' => 'google_maps', 'location' => "{$lat},{$lng}", 'type' => $type],
                $exception,
                'google_maps',
            );

            throw ValidationException::withMessages([
                'google_maps' => 'Não foi possível consultar a Google Places API.',
            ]);
        }

        $data = $response->json();
        $status = $data['status'] ?? 'UNKNOWN_ERROR';

        if (! in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            throw ValidationException::withMessages([
                'google_maps' => $this->translatePlacesStatus($status),
            ]);
        }

        return collect($data['results'] ?? [])
            ->take(20)
            ->map(fn (array $place) => [
                'place_id' => $place['place_id'] ?? '',
                'name'     => $place['name'] ?? 'Sem nome',
                'address'  => $place['vicinity'] ?? null,
                'lat'      => (float) ($place['geometry']['location']['lat'] ?? 0),
                'lng'      => (float) ($place['geometry']['location']['lng'] ?? 0),
                'rating'   => isset($place['rating']) ? (float) $place['rating'] : null,
                'open_now' => $place['opening_hours']['open_now'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Traduz códigos de status da Places API para mensagens em português.
     *
     * @param  string  $status  Código retornado pela API.
     */
    private function translatePlacesStatus(string $status): string
    {
        return match ($status) {
            'OVER_QUERY_LIMIT' => 'Limite de requisições da Google Places API excedido.',
            'REQUEST_DENIED'   => 'Requisição negada pela Google Places API. Verifique a chave e as APIs habilitadas.',
            'INVALID_REQUEST'  => 'Requisição inválida para a Google Places API.',
            default            => 'Erro ao buscar locais na Google Places API.',
        };
    }
}
