<?php

namespace App\Notifications;

use App\Models\Freight;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FreightStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        protected Freight $freight,
        protected string $action,
        protected string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'freight_status_changed',
            'action'      => $this->action,
            'freight_id'  => $this->freight->id,
            'cargo_name'  => $this->freight->cargo_name,
            'status'      => $this->freight->status->value,
            'driver_name' => $this->freight->driver->name,
            'message'     => $this->message,
        ];
    }
}
