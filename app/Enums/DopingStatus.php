<?php

namespace App\Enums;

enum DopingStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Aguardando análise',
            self::Approved => 'Aprovado',
            self::Rejected => 'Reprovado',
        };
    }
}
