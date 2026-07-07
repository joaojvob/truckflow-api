<?php

namespace App\Events;

use App\Models\Incident;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SosTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Incident $incident,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->incident->tenant_id}.freight.{$this->incident->freight_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'freight.sos.triggered';
    }

    public function broadcastWith(): array
    {
        return [
            'incident_id' => $this->incident->id,
            'freight_id'  => $this->incident->freight_id,
            'type'        => $this->incident->type->value,
            'message'     => $this->incident->description,
            'created_at'  => $this->incident->created_at?->toISOString(),
        ];
    }
}
