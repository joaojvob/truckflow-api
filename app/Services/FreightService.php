<?php

namespace App\Services;

use App\Models\Freight;
use Illuminate\Support\Facades\DB;

class FreightService
{
    /**
     * Inicia uma viagem após validar o checklist.
     */
    public function startTrip(int $freightId, array $checklistData): Freight
    {
        return DB::transaction(function () use ($freightId, $checklistData) {
            $freight = Freight::findOrFail($freightId);

            $freight->checklists()->create([
                'items' => $checklistData
            ]);

            $freight->update([
                'status'              => 'in_transit',
                'checklist_completed' => true,
                'started_at'          => now()
            ]);

            return $freight;
        });
    }
}