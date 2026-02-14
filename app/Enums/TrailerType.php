<?php

namespace App\Enums;

enum TrailerType: string
{
    case Flatbed      = 'flatbed';           // Prancha / Plataforma
    case Refrigerated = 'refrigerated'; // Baú Frigorífico
    case DryVan       = 'dry_van';            // Baú Seco
    case Tanker       = 'tanker';             // Tanque (líquidos/gases)
    case Sider        = 'sider';               // Sider (lona lateral)
    case Hopper       = 'hopper';             // Graneleiro
    case Container    = 'container';       // Porta-contêiner
    case Logging      = 'logging';           // Florestal (toras)
    case Lowboy       = 'lowboy';             // Prancha rebaixada (máquinas pesadas)
    case Livestock    = 'livestock';       // Boiadeiro

    public function label(): string
    {
        return match ($this) {
            self::Flatbed      => 'Prancha',
            self::Refrigerated => 'Baú Frigorífico',
            self::DryVan       => 'Baú Seco',
            self::Tanker       => 'Tanque',
            self::Sider        => 'Sider',
            self::Hopper       => 'Graneleiro',
            self::Container    => 'Porta-Contêiner',
            self::Logging      => 'Florestal',
            self::Lowboy       => 'Prancha Rebaixada',
            self::Livestock    => 'Boiadeiro',
        };
    }

    /**
     * Carga máxima recomendada em toneladas para cada tipo.
     */
    public function maxWeightTons(): float
    {
        return match ($this) {
            self::Flatbed      => 28.0,
            self::Refrigerated => 24.0,
            self::DryVan       => 26.0,
            self::Tanker       => 30.0,
            self::Sider        => 26.0,
            self::Hopper       => 32.0,
            self::Container    => 28.0,
            self::Logging      => 35.0,
            self::Lowboy       => 40.0,
            self::Livestock    => 20.0,
        };
    }
}
