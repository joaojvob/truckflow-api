<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaypointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'freight_id'             => $this->freight_id,

            'name'                   => $this->name,
            'description'            => $this->description,
            'type'                   => $this->type,
            'type_label'             => $this->type->label(),
            'type_icon'              => $this->type->icon(),
            'address'                => $this->address,

            'order'                  => $this->order,
            'mandatory'              => $this->mandatory,
            'estimated_stop_minutes' => $this->estimated_stop_minutes,

            // Tracking
            'arrived_at'             => $this->arrived_at,
            'departed_at'            => $this->departed_at,
            'is_visited'             => $this->isVisited(),
            'is_completed'           => $this->isCompleted(),

            'creator'                => UserResource::make($this->whenLoaded('creator')),
            'created_at'             => $this->created_at,
        ];
    }
}
