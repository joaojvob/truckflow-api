<?php

namespace App\Enums;

enum WaypointType: string
{
    case FuelStop      = 'fuel_stop';
    case RestStop      = 'rest_stop';
    case Toll          = 'toll';
    case DeliveryPoint = 'delivery_point';
    case WeighStation  = 'weigh_station';
    case Custom        = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::FuelStop      => 'Posto de Combustível',
            self::RestStop      => 'Ponto de Descanso',
            self::Toll          => 'Pedágio',
            self::DeliveryPoint => 'Ponto de Entrega',
            self::WeighStation  => 'Balança',
            self::Custom        => 'Personalizado',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::FuelStop      => '⛽',
            self::RestStop      => '🛏️',
            self::Toll          => '🔄',
            self::DeliveryPoint => '📦',
            self::WeighStation  => '⚖️',
            self::Custom        => '📍',
        };
    }
}
