<?php

namespace App\Enums;

enum IncidentType: string
{
    case Breakdown = 'breakdown';
    case Accident = 'accident';
    case Robbery = 'robbery';
    case Sos = 'sos';

    public function label(): string
    {
        return match ($this) {
            self::Breakdown => 'Avaria',
            self::Accident  => 'Acidente',
            self::Robbery   => 'Roubo',
            self::Sos       => 'SOS',
        };
    }

    public function isCritical(): bool
    {
        return in_array($this, [self::Sos, self::Robbery]);
    }
}
