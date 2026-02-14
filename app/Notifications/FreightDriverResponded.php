<?php

namespace App\Notifications;

use App\Models\Freight;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FreightDriverResponded extends Notification
{
    use Queueable;

    public function __construct(
        protected Freight $freight,
        protected bool $accepted,
        protected ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $driverName = $this->freight->driver->name;
        $status     = $this->accepted ? 'aceitou' : 'recusou';

        return [
            'type'        => 'freight_driver_responded',
            'freight_id'  => $this->freight->id,
            'cargo_name'  => $this->freight->cargo_name,
            'driver_name' => $driverName,
            'accepted'    => $this->accepted,
            'reason'      => $this->reason,
            'message'     => "Motorista {$driverName} {$status} o frete: {$this->freight->cargo_name}",
        ];
    }
}
