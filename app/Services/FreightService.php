<?php

namespace App\Services;

use App\Enums\FreightStatus;
use App\Models\Freight;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fluxo legado de início/fim de viagem com checklist embutido.
 *
 * Preferir {@see FreightWorkflowService} para o workflow completo gestor ↔ motorista.
 */
class FreightService
{
    /**
     * Inicia a viagem após validar o checklist pré-viagem.
     *
     * @param  Freight  $freight  Frete em status `pending`.
     * @param  array<string, bool>  $checklistData  Itens do checklist (true = ok).
     * @return Freight Frete com status `in_transit`.
     *
     * @throws ValidationException Status inválido ou itens reprovados no checklist.
     */
    public function startTrip(Freight $freight, array $checklistData): Freight
    {
        return DB::transaction(function () use ($freight, $checklistData) {
            if ($freight->status !== FreightStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => "Frete não pode ser iniciado. Status atual: {$freight->status->label()}",
                ]);
            }

            $failedItems = collect($checklistData)->filter(fn ($value) => ! $value)->keys();

            if ($failedItems->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'checklist' => 'Itens do checklist reprovados: ' . $failedItems->implode(', '),
                ]);
            }

            $freight->checklists()->create([
                'items' => $checklistData,
            ]);

            $freight->update([
                'status'              => FreightStatus::InTransit,
                'checklist_completed' => true,
                'started_at'          => now(),
            ]);

            $freight->recordActivity(
                action: 'trip_started',
                description: "Motorista iniciou a viagem para a carga: {$freight->cargo_name}",
                payload: ['checklist' => $checklistData],
            );

            return $freight->fresh();
        });
    }

    /**
     * Finaliza uma viagem em trânsito.
     *
     * @param  Freight  $freight  Frete em status `in_transit`.
     * @param  int|null  $rating  Avaliação opcional do frete (1-5).
     * @param  string|null  $notes  Observações finais do motorista.
     * @return Freight Frete com status `completed`.
     *
     * @throws ValidationException Se o frete não estiver em trânsito.
     */
    public function completeTrip(Freight $freight, ?int $rating = null, ?string $notes = null): Freight
    {
        return DB::transaction(function () use ($freight, $rating, $notes) {
            if ($freight->status !== FreightStatus::InTransit) {
                throw ValidationException::withMessages([
                    'status' => "Frete não pode ser finalizado. Status atual: {$freight->status->label()}",
                ]);
            }

            $freight->update([
                'status'        => FreightStatus::Completed,
                'completed_at'  => now(),
                'driver_rating' => $rating,
                'driver_notes'  => $notes,
            ]);

            $freight->recordActivity(
                action: 'trip_completed',
                description: "Motorista finalizou a viagem da carga: {$freight->cargo_name}",
                payload: ['rating' => $rating, 'notes' => $notes],
            );

            return $freight->fresh();
        });
    }
}
