<?php

namespace App\Enums;

enum FiscalDocumentStatus: string
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Authorized = 'authorized';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft      => 'Rascunho',
            self::Processing => 'Processando',
            self::Authorized => 'Autorizado',
            self::Rejected   => 'Rejeitado',
            self::Cancelled  => 'Cancelado',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft      => in_array($next, [self::Processing, self::Rejected], true),
            self::Processing => in_array($next, [self::Authorized, self::Rejected], true),
            self::Authorized => $next === self::Cancelled,
            self::Rejected, self::Cancelled => false,
        };
    }
}
