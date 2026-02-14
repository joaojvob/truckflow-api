<?php

namespace App\Enums;

enum TruckStatus: string
{
    case Available   = 'available';
    case InUse       = 'in_use';
    case Maintenance = 'maintenance';
    case Inactive    = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Available   => 'Disponível',
            self::InUse       => 'Em Uso',
            self::Maintenance => 'Em Manutenção',
            self::Inactive    => 'Inativo',
        };
    }
}
