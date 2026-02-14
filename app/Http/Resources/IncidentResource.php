<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'type'        => $this->type,
            'type_label'  => $this->type->label(),
            'description' => $this->description,
            'freight'     => FreightResource::make($this->whenLoaded('freight')),
            'reporter'    => UserResource::make($this->whenLoaded('reporter')),
            'created_at'  => $this->created_at,
        ];
    }
}
