<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ChecklistSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        protected \App\Models\Freight $freight,
        protected array $items,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'checklist_submitted',
            'freight_id'  => $this->freight->id,
            'cargo_name'  => $this->freight->cargo_name,
            'driver_name' => $this->freight->driver->name,
            'items'       => $this->items,
            'message'     => "Motorista {$this->freight->driver->name} enviou o checklist do frete \"{$this->freight->cargo_name}\".",
        ];
    }
}
