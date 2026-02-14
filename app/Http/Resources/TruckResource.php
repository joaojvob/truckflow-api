<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TruckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'plate'             => $this->plate,
            'renavam'           => $this->renavam,
            'brand'             => $this->brand,
            'model'             => $this->model,
            'year'              => $this->year,
            'color'             => $this->color,
            'axle_count'        => $this->axle_count,
            'max_weight'        => $this->max_weight,
            'has_trailer_hitch' => $this->has_trailer_hitch,
            'hitch_type'        => $this->hitch_type,
            'status'            => $this->status,
            'status_label'      => $this->status->label(),
            'odometer'          => $this->odometer,
            'driver'            => UserResource::make($this->whenLoaded('driver')),
            'created_at'        => $this->created_at,
        ];
    }
}
