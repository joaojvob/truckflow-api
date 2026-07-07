<?php

namespace App\Services;

use App\Enums\PlaceType;
use App\Models\Freight;
use Illuminate\Validation\ValidationException;

/**
 * Busca pontos de interesse (postos, restaurantes, etc.) próximos a um frete.
 *
 * Delega a consulta à Google Places API via {@see GoogleMapsService}.
 */
class PlaceSearchService
{
    public function __construct(
        protected GoogleMapsService $googleMapsService,
    ) {}

    /**
     * Busca locais próximos às coordenadas informadas ou à origem do frete.
     *
     * @param  Freight  $freight  Frete de referência para fallback de coordenadas.
     * @param  array{lat?: float, lng?: float, type: PlaceType|string, radius_meters?: int}  $data
     * @return array<int, array{place_id: string, name: string, address: string|null, lat: float, lng: float, rating: float|null, open_now: bool|null}>
     *
     * @throws ValidationException Se lat/lng não estiverem disponíveis.
     */
    public function searchNearFreight(Freight $freight, array $data): array
    {
        $origin = $freight->getOriginCoordinates();

        $lat = $data['lat'] ?? $origin['lat'] ?? null;
        $lng = $data['lng'] ?? $origin['lng'] ?? null;

        if ($lat === null || $lng === null) {
            throw ValidationException::withMessages([
                'location' => 'Informe lat/lng ou defina origem no frete.',
            ]);
        }

        /** @var PlaceType $type */
        $type = $data['type'] instanceof PlaceType
            ? $data['type']
            : PlaceType::from($data['type']);

        return $this->googleMapsService->searchNearbyPlaces(
            lat: (float) $lat,
            lng: (float) $lng,
            type: $type->googleType(),
            radiusMeters: (int) ($data['radius_meters'] ?? 5000),
        );
    }
}
