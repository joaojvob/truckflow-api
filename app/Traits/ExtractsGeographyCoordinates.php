<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait ExtractsGeographyCoordinates
{
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
