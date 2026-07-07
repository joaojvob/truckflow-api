<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\ExtractsGeographyCoordinates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverLocation extends Model
{
    use BelongsToTenant, ExtractsGeographyCoordinates;

    protected $fillable = [
        'tenant_id',
        'freight_id',
        'driver_id',
        'location',
        'speed_kmh',
        'heading',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'speed_kmh'   => 'decimal:2',
            'heading'   => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function freight(): BelongsTo
    {
        return $this->belongsTo(Freight::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function getCoordinates(): ?array
    {
        return $this->coordinatesFromGeography('location');
    }
}
