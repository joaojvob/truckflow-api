<?php

namespace App\Notifications;

use App\Models\Freight;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class FreightApproved extends Notification implements ShouldQueue
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
            'type'       => 'freight_approved',
            'freight_id' => $this->freight->id,
            'cargo_name' => $this->freight->cargo_name,
            'message'    => "Viagem liberada! Seu frete \"{$this->freight->cargo_name}\" foi aprovado pelo gestor. Você já pode iniciar.",
        ];
    }
}
