<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'role'       => $this->role,
            'role_label' => $this->role->label(),
            'tenant'     => TenantResource::make($this->whenLoaded('tenant')),
            'created_at' => $this->created_at,
        ];
    }
}
