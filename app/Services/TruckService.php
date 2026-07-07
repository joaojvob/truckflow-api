<?php

namespace App\Services;

use App\Enums\TruckStatus;
use App\Models\Truck;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro, status e documentação (CRLV) de caminhões da frota.
 */
class TruckService
{
    public function __construct(
        protected DocumentStorageService $documentStorage,
    ) {}

    /**
     * Registra um novo caminhão no tenant do usuário autenticado.
     *
     * @param  array<string, mixed>  $data  Placa, marca, modelo, capacidade e demais campos validados.
     * @return Truck Caminhão criado com status `available`.
     */
    public function create(array $data): Truck
    {
        return DB::transaction(function () use ($data) {
            $truck = Truck::create([
                'tenant_id'         => auth()->user()->tenant_id,
                'driver_id'         => $data['driver_id'] ?? auth()->id(),
                'plate'             => strtoupper($data['plate']),
                'renavam'           => $data['renavam'] ?? null,
                'brand'             => $data['brand'],
                'model'             => $data['model'],
                'year'              => $data['year'],
                'color'             => $data['color'] ?? null,
                'axle_count'        => $data['axle_count'] ?? 2,
                'max_weight'        => $data['max_weight'],
                'has_trailer_hitch' => $data['has_trailer_hitch'] ?? false,
                'hitch_type'        => $data['hitch_type'] ?? null,
                'status'            => TruckStatus::Available,
                'odometer'          => $data['odometer'] ?? 0,
            ]);

            $truck->recordActivity(
                action: 'truck_registered',
                description: "Caminhão registrado: {$truck->brand} {$truck->model} - {$truck->plate}",
            );

            return $truck->fresh('driver');
        });
    }

    /**
     * Atualiza dados cadastrais do caminhão.
     *
     * @param  Truck  $truck  Caminhão a editar.
     * @param  array<string, mixed>  $data  Campos validados.
     * @return Truck Caminhão atualizado.
     */
    public function update(Truck $truck, array $data): Truck
    {
        return DB::transaction(function () use ($truck, $data) {
            if (isset($data['plate'])) {
                $data['plate'] = strtoupper($data['plate']);
            }

            $truck->update($data);

            $truck->recordActivity(
                action: 'truck_updated',
                description: "Caminhão atualizado: {$truck->plate}",
                payload: ['changes' => array_keys($data)],
            );

            return $truck->fresh('driver');
        });
    }

    /**
     * Altera o status operacional do caminhão.
     *
     * @param  Truck  $truck  Caminhão a atualizar.
     * @param  TruckStatus  $newStatus  Novo status (available, in_use, maintenance, etc.).
     * @return Truck Caminhão com status atualizado.
     *
     * @throws ValidationException Status igual ao atual ou frete em trânsito impedindo disponibilidade.
     */
    public function updateStatus(Truck $truck, TruckStatus $newStatus): Truck
    {
        if ($truck->status === $newStatus) {
            throw ValidationException::withMessages([
                'status' => "Caminhão já está com status {$newStatus->label()}.",
            ]);
        }

        if ($newStatus === TruckStatus::Available && $truck->freights()->whereIn('status', ['in_transit'])->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Caminhão não pode ficar disponível enquanto há frete em trânsito.',
            ]);
        }

        $truck->update(['status' => $newStatus]);

        $truck->recordActivity(
            action: 'truck_status_changed',
            description: "Status do caminhão alterado para {$newStatus->label()}",
            payload: ['new_status' => $newStatus->value],
        );

        return $truck->fresh();
    }

    /**
     * Vincula ou desvincula um motorista ao caminhão.
     *
     * @param  Truck  $truck  Caminhão alvo.
     * @param  int|null  $driverId  ID do motorista ou null para remover vínculo.
     * @return Truck Caminhão com relação `driver` carregada.
     */
    public function assignDriver(Truck $truck, ?int $driverId): Truck
    {
        $truck->update(['driver_id' => $driverId]);

        $truck->recordActivity(
            action: 'truck_driver_assigned',
            description: $driverId
                ? "Motorista #{$driverId} atribuído ao caminhão {$truck->plate}"
                : "Motorista removido do caminhão {$truck->plate}",
        );

        return $truck->fresh('driver');
    }

    /**
     * Anexa ou substitui o CRLV no storage privado.
     *
     * @param  Truck  $truck  Caminhão dono do documento.
     * @param  UploadedFile  $file  Imagem ou PDF do CRLV.
     * @param  string|null  $crlvExpiry  Data de validade (formato aceito pelo banco).
     * @return Truck Caminhão com campos de CRLV atualizados.
     */
    public function uploadCrlv(Truck $truck, UploadedFile $file, ?string $crlvExpiry = null): Truck
    {
        return DB::transaction(function () use ($truck, $file, $crlvExpiry) {
            $path = $this->documentStorage->replace(
                $file,
                "vehicle-documents/{$truck->tenant_id}/trucks/{$truck->id}/crlv",
                $truck->crlv_file_path,
            );

            $truck->update([
                'crlv_file_path'   => $path,
                'crlv_expiry'      => $crlvExpiry,
                'crlv_uploaded_at' => now(),
            ]);

            $truck->recordActivity(
                action: 'truck_crlv_uploaded',
                description: "CRLV do caminhão {$truck->plate} atualizado.",
            );

            return $truck->fresh('driver');
        });
    }
}
