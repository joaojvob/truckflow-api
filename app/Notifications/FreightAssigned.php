<?php

namespace App\Notifications;

use App\Models\Freight;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class FreightAssigned extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        protected Freight $freight,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'freight_assigned',
            'freight_id' => $this->freight->id,
            'cargo_name' => $this->freight->cargo_name,
            'origin'     => $this->freight->origin_address,
            'destination' => $this->freight->destination_address,
            'weight'     => $this->freight->weight,
            'total_price' => $this->freight->total_price,
            'deadline_at' => $this->freight->deadline_at?->toISOString(),
            'message'    => "Novo frete atribuído a você: {$this->freight->cargo_name}",
        ];
    }
}
