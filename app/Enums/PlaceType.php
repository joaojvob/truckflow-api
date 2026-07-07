<?php

namespace App\Enums;

enum PlaceType: string
{
    case GasStation = 'gas_station';
    case Restaurant = 'restaurant';
    case RestStop = 'rest_stop';
    case Lodging = 'lodging';
    case CarRepair = 'car_repair';

    public function label(): string
    {
        return match ($this) {
            self::GasStation => 'Posto de combustível',
            self::Restaurant => 'Restaurante',
            self::RestStop   => 'Parada de descanso',
            self::Lodging    => 'Hotel / pousada',
            self::CarRepair  => 'Oficina mecânica',
        };
    }

    public function googleType(): string
    {
        return match ($this) {
            self::GasStation => 'gas_station',
            self::Restaurant => 'restaurant',
            self::RestStop   => 'rest_stop',
            self::Lodging    => 'lodging',
            self::CarRepair  => 'car_repair',
        };
    }
}
