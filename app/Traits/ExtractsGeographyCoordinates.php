<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Extrai latitude e longitude de colunas PostGIS do tipo geography/point.
 */
trait ExtractsGeographyCoordinates
{
    /**
     * Converte uma coluna geography em array associativo lat/lng.
     *
     * @param  string  $column  Nome da coluna PostGIS (ex.: origin, destination, location).
     * @return array{lat: float, lng: float}|null Coordenadas ou null se a coluna estiver vazia.
     */
    public function coordinatesFromGeography(string $column): ?array
    {
        if (! $this->getAttribute($column)) {
            return null;
        }

        $result = DB::selectOne(
            "SELECT ST_Y({$column}::geometry) AS lat, ST_X({$column}::geometry) AS lng FROM {$this->getTable()} WHERE id = ?",
            [$this->getKey()],
        );

        if (! $result) {
            return null;
        }

        return [
            'lat' => (float) $result->lat,
            'lng' => (float) $result->lng,
        ];
    }
}
