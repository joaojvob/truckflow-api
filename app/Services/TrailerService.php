<?php

namespace App\Services;

use App\Models\Trailer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro, carga/descarga e documentação (CRLV) de reboques/semirreboques.
 */
class TrailerService
{
    public function __construct(
        protected DocumentStorageService $documentStorage,
    ) {}

    /**
     * Registra um novo reboque no tenant do usuário autenticado.
     *
     * @param  array<string, mixed>  $data  Placa, tipo, capacidade, engate e demais campos validados.
     * @return Trailer Reboque criado com status `available`.
     */
    public function create(array $data): Trailer
    {
        return DB::transaction(function () use ($data) {
            $trailer = Trailer::create([
                'tenant_id'  => auth()->user()->tenant_id,
                'driver_id'  => $data['driver_id'] ?? auth()->id(),
                'plate'      => strtoupper($data['plate']),
                'renavam'    => $data['renavam'] ?? null,
                'type'       => $data['type'],
                'brand'      => $data['brand'] ?? null,
                'model'      => $data['model'] ?? null,
                'year'       => $data['year'] ?? null,
                'axle_count' => $data['axle_count'] ?? 2,
                'max_weight' => $data['max_weight'],
                'length'     => $data['length'] ?? null,
                'hitch_type' => $data['hitch_type'],
                'status'     => 'available',
                'is_loaded'  => false,
            ]);

            $trailer->recordActivity(
                action: 'trailer_registered',
                description: "Reboque registrado: {$trailer->type->label()} - {$trailer->plate}",
            );

            return $trailer->fresh('driver');
        });
    }

    /**
     * Atualiza dados cadastrais do reboque.
     *
     * @param  Trailer  $trailer  Reboque a editar.
     * @param  array<string, mixed>  $data  Campos validados.
     * @return Trailer Reboque atualizado.
     */
    public function update(Trailer $trailer, array $data): Trailer
    {
        return DB::transaction(function () use ($trailer, $data) {
            if (isset($data['plate'])) {
                $data['plate'] = strtoupper($data['plate']);
            }

            $trailer->update($data);

            $trailer->recordActivity(
                action: 'trailer_updated',
                description: "Reboque atualizado: {$trailer->plate}",
                payload: ['changes' => array_keys($data)],
            );

            return $trailer->fresh('driver');
        });
    }

    /**
     * Vincula ou desvincula um motorista ao reboque.
     *
     * @param  Trailer  $trailer  Reboque alvo.
     * @param  int|null  $driverId  ID do motorista ou null para remover vínculo.
     * @return Trailer Reboque com relação `driver` carregada.
     */
    public function assignDriver(Trailer $trailer, ?int $driverId): Trailer
    {
        $trailer->update(['driver_id' => $driverId]);

        $trailer->recordActivity(
            action: 'trailer_driver_assigned',
            description: $driverId
                ? "Motorista #{$driverId} atribuído ao reboque {$trailer->plate}"
                : "Motorista removido do reboque {$trailer->plate}",
        );

        return $trailer->fresh('driver');
    }

    /**
     * Marca o reboque como carregado ou descarregado.
     *
     * @param  Trailer  $trailer  Reboque alvo.
     * @param  bool  $isLoaded  true = carregado, false = vazio.
     * @return Trailer Reboque com flag `is_loaded` atualizada.
     *
     * @throws ValidationException Se o estado informado já for o atual.
     */
    public function toggleLoaded(Trailer $trailer, bool $isLoaded): Trailer
    {
        if ($trailer->is_loaded === $isLoaded) {
            $state = $isLoaded ? 'carregado' : 'descarregado';
            throw ValidationException::withMessages([
                'is_loaded' => "Reboque já está {$state}.",
            ]);
        }

        $trailer->update(['is_loaded' => $isLoaded]);

        return $trailer->fresh();
    }

    /**
     * Anexa ou substitui o CRLV no storage privado.
     *
     * @param  Trailer  $trailer  Reboque dono do documento.
     * @param  UploadedFile  $file  Imagem ou PDF do CRLV.
     * @param  string|null  $crlvExpiry  Data de validade (formato aceito pelo banco).
     * @return Trailer Reboque com campos de CRLV atualizados.
     */
    public function uploadCrlv(Trailer $trailer, UploadedFile $file, ?string $crlvExpiry = null): Trailer
    {
        return DB::transaction(function () use ($trailer, $file, $crlvExpiry) {
            $path = $this->documentStorage->replace(
                $file,
                "vehicle-documents/{$trailer->tenant_id}/trailers/{$trailer->id}/crlv",
                $trailer->crlv_file_path,
            );

            $trailer->update([
                'crlv_file_path'   => $path,
                'crlv_expiry'      => $crlvExpiry,
                'crlv_uploaded_at' => now(),
            ]);

            $trailer->recordActivity(
                action: 'trailer_crlv_uploaded',
                description: "CRLV do reboque {$trailer->plate} atualizado.",
            );

            return $trailer->fresh('driver');
        });
    }
}
