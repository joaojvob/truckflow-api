<?php

namespace App\Events;

use App\Models\Incident;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando um incidente crítico (SOS) é registrado durante um frete.
 *
 * Alerta gestores e painéis conectados via WebSocket em tempo real.
 */
class SosTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  Incident  $incident  Incidente criado (tipo SOS, avaria crítica, etc.).
     */
    public function __construct(
        public Incident $incident,
    ) {}

    /**
     * Define o canal privado de broadcast (tenant + frete).
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->incident->tenant_id}.freight.{$this->incident->freight_id}"),
        ];
    }

    /**
     * Nome do evento no cliente Echo (ex.: `.freight.sos.triggered`).
     */
    public function broadcastAs(): string
    {
        return 'freight.sos.triggered';
    }

    /**
     * Payload enviado ao frontend com dados do alerta.
     *
     * @return array{incident_id: int, freight_id: int, type: string, message: string|null, created_at: string|null}
     */
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
