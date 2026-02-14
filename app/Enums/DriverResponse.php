<?php

namespace App\Enums;

enum DriverResponse: string
{
    case Pending  = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Aguardando resposta',
            self::Accepted => 'Aceito',
            self::Rejected => 'Recusado',
        };
    }
}
