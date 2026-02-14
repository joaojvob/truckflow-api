<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FreightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'cargo_name'          => $this->cargo_name,
            'weight'              => $this->weight,
            'status'              => $this->status,
            'status_label'        => $this->status->label(),
            'checklist_completed' => $this->checklist_completed,
            'driver_rating'       => $this->driver_rating,
            'driver_notes'        => $this->driver_notes,
            'started_at'          => $this->started_at,
            'completed_at'        => $this->completed_at,
            'driver'              => UserResource::make($this->whenLoaded('driver')),
            'checklists'          => ChecklistResource::collection($this->whenLoaded('checklists')),
            'created_at'          => $this->created_at,
        ];
    }
}
