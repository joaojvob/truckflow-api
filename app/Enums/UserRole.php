<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Manager = 'manager';
    case Driver = 'driver';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrador',
            self::Admin      => 'Administrador',
            self::Manager    => 'Gerente',
            self::Driver     => 'Motorista',
        };
    }
}
