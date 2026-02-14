<?php

namespace App\Notifications;

use App\Models\DopingTest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DopingTestSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        protected DopingTest $dopingTest,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'doping_test_submitted',
            'freight_id'  => $this->dopingTest->freight_id,
            'driver_name' => $this->dopingTest->driver->name,
            'cargo_name'  => $this->dopingTest->freight->cargo_name,
            'message'     => "Motorista {$this->dopingTest->driver->name} enviou o exame de doping para o frete \"{$this->dopingTest->freight->cargo_name}\".",
        ];
    }
}
