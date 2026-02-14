<?php

namespace App\Enums;

enum FreightStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case InTransit = 'in_transit';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Pendente',
            self::Ready     => 'Pronto',
            self::InTransit => 'Em Trânsito',
            self::Completed => 'Concluído',
            self::Cancelled => 'Cancelado',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending   => in_array($next, [self::Ready, self::Cancelled]),
            self::Ready     => in_array($next, [self::InTransit, self::Cancelled]),
            self::InTransit => in_array($next, [self::Completed]),
            self::Completed, self::Cancelled => false,
        };
    }
}
