<?php

namespace App\Enums;

enum FreightStatus: string
{
    case Pending   = 'pending';
    case Assigned  = 'assigned';
    case Accepted  = 'accepted';
    case Ready     = 'ready';
    case InTransit = 'in_transit';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rejected  = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Pendente',
            self::Assigned  => 'Atribuído ao motorista',
            self::Accepted  => 'Aceito pelo motorista',
            self::Ready     => 'Liberado para viagem',
            self::InTransit => 'Em Trânsito',
            self::Completed => 'Concluído',
            self::Cancelled => 'Cancelado',
            self::Rejected  => 'Recusado pelo motorista',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending   => in_array($next, [self::Assigned, self::Cancelled]),
            self::Assigned  => in_array($next, [self::Accepted, self::Rejected, self::Cancelled]),
            self::Accepted  => in_array($next, [self::Ready, self::Cancelled]),
            self::Ready     => in_array($next, [self::InTransit, self::Cancelled]),
            self::InTransit => in_array($next, [self::Completed]),
            self::Rejected  => in_array($next, [self::Assigned, self::Cancelled]),
            self::Completed, self::Cancelled => false,
        };
    }
}
