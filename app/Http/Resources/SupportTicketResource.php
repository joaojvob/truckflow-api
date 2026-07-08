<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'subject'       => $this->subject,
            'category'      => $this->category,
            'priority'      => $this->priority,
            'status'        => $this->status,
            'tenant_id'     => $this->tenant_id,
            'last_reply_at' => $this->last_reply_at,
            'closed_at'     => $this->closed_at,
            'created_at'    => $this->created_at,
            'user'          => UserResource::make($this->whenLoaded('user')),
            'messages'      => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($m) => [
                'id'         => $m->id,
                'body'       => $m->body,
                'is_staff'   => $m->is_staff,
                'created_at' => $m->created_at,
                'user'       => [
                    'id'   => $m->user?->id,
                    'name' => $m->user?->name,
                ],
            ])),
            'messages_count' => $this->whenCounted('messages'),
        ];
    }
}
