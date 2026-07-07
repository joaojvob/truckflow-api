<?php

namespace App\Services;

use App\Contracts\FiscalDocumentProvider;
use App\Models\Freight;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Delega emissão de CT-e ao microserviço Java truckflow-fiscal.
 */
class JavaFiscalDocumentProvider implements FiscalDocumentProvider
{
    public function emitCte(Freight $freight, array $fiscalSettings): array
    {
        $freight->loadMissing(['driver', 'truck', 'trailer', 'tenant']);

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->post($this->baseUrl().'/api/v1/cte/emit', [
                    'freight_id'          => $freight->id,
                    'cargo_name'          => $freight->cargo_name,
                    'cargo_weight'        => (float) $freight->weight,
                    'total_value'         => (float) $freight->total_price,
                    'distance_km'         => (float) ($freight->distance_km ?? 0),
                    'origin_address'      => $freight->origin_address,
                    'destination_address' => $freight->destination_address,
                    'completed_at'        => $freight->completed_at?->toIso8601String(),
                    'issuer'              => $fiscalSettings,
                    'carrier'             => [
                        'driver_name'   => $freight->driver?->name,
                        'truck_plate'   => $freight->truck?->plate,
                        'trailer_plate' => $freight->trailer?->plate,
                    ],
                ])
                ->throw();
        } catch (RequestException $exception) {
            app(SystemLogger::class)->warning(
                'Microserviço fiscal indisponível (emit CT-e).',
                ['channel' => 'fiscal_java', 'freight_id' => $freight->id],
                $exception,
                'fiscal_java',
            );

            throw ValidationException::withMessages([
                'fiscal' => 'Não foi possível emitir o CT-e no serviço fiscal.',
            ]);
        }

        $data = $response->json('data');

        if (! is_array($data) || empty($data['access_key'])) {
            throw ValidationException::withMessages([
                'fiscal' => 'Resposta inválida do serviço fiscal.',
            ]);
        }

        return $data;
    }

    public function consultCte(string $accessKey): array
    {
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->get($this->baseUrl().'/api/v1/cte/'.$accessKey)
                ->throw();
        } catch (RequestException $exception) {
            throw ValidationException::withMessages([
                'fiscal' => 'Não foi possível consultar o CT-e.',
            ]);
        }

        return $response->json('data', []);
    }

    public function cancelCte(string $accessKey, string $reason): array
    {
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->post($this->baseUrl().'/api/v1/cte/'.$accessKey.'/cancel', [
                    'reason' => $reason,
                ])
                ->throw();
        } catch (RequestException $exception) {
            throw ValidationException::withMessages([
                'fiscal' => 'Não foi possível cancelar o CT-e.',
            ]);
        }

        return $response->json('data', []);
    }

    private function baseUrl(): string
    {
        return rtrim(config('services.fiscal.java_url'), '/');
    }
}
