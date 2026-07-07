<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RequestLog */
class RequestLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'request_id'   => $this->request_id,
            'method'       => $this->method,
            'route_name'   => $this->route_name,
            'uri'          => $this->uri,
            'action'       => $this->action,
            'status_code'  => $this->status_code,
            'duration_ms'  => $this->duration_ms,
            'ip'           => $this->ip,
            'user_agent'   => $this->user_agent,
            'user'         => $this->whenLoaded('user', fn () => [
                'id'    => $this->user?->id,
                'name'  => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}
