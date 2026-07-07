<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SystemLog */
class SystemLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'level'              => $this->level->value,
            'channel'            => $this->channel,
            'message'            => $this->message,
            'context'            => $this->context,
            'exception_class'    => $this->exception_class,
            'exception_message'  => $this->exception_message,
            'trace'              => $this->when($request->boolean('include_trace'), $this->trace),
            'request_id'         => $this->request_id,
            'method'             => $this->method,
            'url'                => $this->url,
            'ip'                 => $this->ip,
            'resolved_at'        => $this->resolved_at?->toISOString(),
            'user'               => $this->whenLoaded('user', fn () => [
                'id'    => $this->user?->id,
                'name'  => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'created_at'         => $this->created_at?->toISOString(),
        ];
    }
}
