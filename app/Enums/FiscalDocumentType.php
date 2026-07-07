<?php

namespace App\Enums;

enum FiscalDocumentType: string
{
    case Cte = 'cte';

    public function label(): string
    {
        return match ($this) {
            self::Cte => 'CT-e (Conhecimento de Transporte Eletrônico)',
        };
    }
}
