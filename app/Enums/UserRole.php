<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Driver = 'driver';

    public function label(): string
    {
        return match ($this) {
            self::Admin   => 'Administrador',
            self::Manager => 'Gerente',
            self::Driver  => 'Motorista',
        };
    }
}
