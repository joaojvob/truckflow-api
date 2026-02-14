<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrailerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'plate'        => $this->plate,
            'renavam'      => $this->renavam,
            'type'         => $this->type,
            'type_label'   => $this->type->label(),
            'brand'        => $this->brand,
            'model'        => $this->model,
            'year'         => $this->year,
            'axle_count'   => $this->axle_count,
            'max_weight'   => $this->max_weight,
            'length'       => $this->length,
            'hitch_type'   => $this->hitch_type,
            'status'       => $this->status,
            'status_label' => $this->status->label(),
            'is_loaded'    => $this->is_loaded,
            'driver'       => UserResource::make($this->whenLoaded('driver')),
            'created_at'   => $this->created_at,
        ];
    }
}
