<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ActivityLog */
class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'action'         => $this->action,
            'description'    => $this->description,
            'auditable_type' => $this->auditable_type,
            'auditable_id'   => $this->auditable_id,
            'payload'        => $this->payload,
            'user'           => $this->whenLoaded('user', fn () => [
                'id'    => $this->user?->id,
                'name'  => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}
