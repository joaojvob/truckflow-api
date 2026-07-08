<?php

namespace App\Services\Geocoding;

use App\Contracts\GeocodingProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Geocodificação gratuita via BrasilAPI (com fallback ViaCEP).
 *
 * A BrasilAPI CEP v2 costuma retornar coordenadas (lat/lng); quando ausentes,
 * o endereço ainda é resolvido e as coordenadas ficam nulas (o front pode
 * pedir ajuste manual). Nenhuma chave de API é necessária.
 */
class BrasilApiGeocodingProvider implements GeocodingProvider
{
    private const BRASILAPI_URL = 'https://brasilapi.com.br/api/cep/v2/';

    private const VIACEP_URL = 'https://viacep.com.br/ws/';

    public function lookupCep(string $cep): array
    {
        $cep = preg_replace('/\D/', '', $cep);

        if (strlen($cep) !== 8) {
            throw ValidationException::withMessages([
                'cep' => 'CEP inválido. Informe 8 dígitos.',
            ]);
        }

        $result = $this->fromBrasilApi($cep) ?? $this->fromViaCep($cep);

        if ($result === null) {
            throw ValidationException::withMessages([
                'cep' => 'CEP não encontrado.',
            ]);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromBrasilApi(string $cep): ?array
    {
        try {
            $response = Http::timeout(10)->acceptJson()->get(self::BRASILAPI_URL.$cep);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $coordinates = $data['location']['coordinates'] ?? [];

        return [
            'cep'          => $this->formatCep($cep),
            'street'       => $data['street'] ?? null,
            'neighborhood' => $data['neighborhood'] ?? null,
            'city'         => $data['city'] ?? null,
            'state'        => $data['state'] ?? null,
            'lat'          => isset($coordinates['latitude']) ? (float) $coordinates['latitude'] : null,
            'lng'          => isset($coordinates['longitude']) ? (float) $coordinates['longitude'] : null,
            'source'       => 'brasilapi',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromViaCep(string $cep): ?array
    {
        try {
            $response = Http::timeout(10)->acceptJson()->get(self::VIACEP_URL.$cep.'/json/');
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        if (! is_array($data) || ($data['erro'] ?? false)) {
            return null;
        }

        return [
            'cep'          => $this->formatCep($cep),
            'street'       => $data['logradouro'] ?? null,
            'neighborhood' => $data['bairro'] ?? null,
            'city'         => $data['localidade'] ?? null,
            'state'        => $data['uf'] ?? null,
            'lat'          => null,
            'lng'          => null,
            'source'       => 'viacep',
        ];
    }

    private function formatCep(string $cep): string
    {
        return substr($cep, 0, 5).'-'.substr($cep, 5);
    }
}
