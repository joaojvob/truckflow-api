<?php

namespace App\Contracts;

/**
 * Contrato para geocodificação de CEP (endereço + coordenadas).
 *
 * Implementação padrão: {@see \App\Services\Geocoding\BrasilApiGeocodingProvider}.
 */
interface GeocodingProvider
{
    /**
     * Resolve um CEP em endereço estruturado e coordenadas aproximadas.
     *
     * @return array{
     *     cep: string,
     *     street: string|null,
     *     neighborhood: string|null,
     *     city: string|null,
     *     state: string|null,
     *     lat: float|null,
     *     lng: float|null,
     *     source: string
     * }
     */
    public function lookupCep(string $cep): array;
}
