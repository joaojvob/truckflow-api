<?php

namespace App\Services;

use App\Enums\PlaceType;
use App\Models\Freight;
use Illuminate\Validation\ValidationException;

class PlaceSearchService
{
    public function __construct(
        protected GoogleMapsService $googleMapsService,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    public function searchNearFreight(Freight $freight, array $data): array
    {
        $lat = $data['lat'] ?? $freight->getOriginCoordinates()['lat'] ?? null;
        $lng = $data['lng'] ?? $freight->getOriginCoordinates()['lng'] ?? null;

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
