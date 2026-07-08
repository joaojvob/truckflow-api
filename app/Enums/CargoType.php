<?php

namespace App\Enums;

enum CargoType: string
{
    case DryBulk = 'dry_bulk';
    case LiquidBulk = 'liquid_bulk';
    case GeneralCargo = 'general_cargo';
    case Refrigerated = 'refrigerated';
    case Hazardous = 'hazardous';
    case Containerized = 'containerized';
    case Vehicles = 'vehicles';
    case LiveAnimals = 'live_animals';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DryBulk       => 'Granéis sólidos',
            self::LiquidBulk    => 'Granéis líquidos',
            self::GeneralCargo  => 'Carga geral',
            self::Refrigerated  => 'Frigorificada',
            self::Hazardous     => 'Perigosa',
            self::Containerized => 'Conteinerizada',
            self::Vehicles      => 'Veículos',
            self::LiveAnimals   => 'Animais vivos',
            self::Other         => 'Outros',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
