<?php

namespace App\Services;

use App\Models\Freight;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FreightService
{
    /**
     * Inicia uma viagem após validar o checklist.
     *
     * @throws ValidationException
     */
    public function startTrip(int $freightId, array $checklistData): Freight
    {
        return DB::transaction(function () use ($freightId, $checklistData) {
            $freight = Freight::findOrFail($freightId);

            if ($freight->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => "Frete não pode ser iniciado. Status atual: {$freight->status}",
                ]);
            }

            $failedItems = collect($checklistData)->filter(fn ($value) => !$value)->keys();
            
            if ($failedItems->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'checklist' => 'Itens do checklist reprovados: ' . $failedItems->implode(', '),
                ]);
            }

            $freight->checklists()->create([
                'items' => $checklistData,
            ]);

            $freight->update([
                'status'              => 'in_transit',
                'checklist_completed' => true,
                'started_at'          => now(),
            ]);

            $freight->recordActivity(
                action: 'trip_started',
                description: "Motorista iniciou a viagem para a carga: {$freight->cargo_name}",
                payload: ['checklist' => $checklistData]
            );

            return $freight->fresh();
        });
    }

    /**
     * Finaliza uma viagem em trânsito.
     */
    public function completeTrip(int $freightId, ?int $rating = null, ?string $notes = null): Freight
    {
        return DB::transaction(function () use ($freightId, $rating, $notes) {
            $freight = Freight::findOrFail($freightId);

            if ($freight->status !== 'in_transit') {
                throw ValidationException::withMessages([
                    'status' => "Frete não pode ser finalizado. Status atual: {$freight->status}",
                ]);
            }

            $freight->update([
                'status'        => 'completed',
                'completed_at'  => now(),
                'driver_rating' => $rating,
                'driver_notes'  => $notes,
            ]);

            $freight->recordActivity(
                action: 'trip_completed',
                description: "Motorista finalizou a viagem da carga: {$freight->cargo_name}",
                payload: ['rating' => $rating, 'notes' => $notes]
            );

            return $freight->fresh();
        });
    }
}