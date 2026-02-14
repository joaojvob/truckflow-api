<?php

namespace App\Models;

use App\Enums\TrailerType;
use App\Enums\TruckStatus;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trailer extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'driver_id',
        'plate',
        'renavam',
        'type',
        'brand',
        'model',
        'year',
        'axle_count',
        'max_weight',
        'length',
        'hitch_type',
        'status',
        'is_loaded',
    ];

    protected function casts(): array
    {
        return [
            'type'       => TrailerType::class,
            'status'     => TruckStatus::class,
            'is_loaded'  => 'boolean',
            'max_weight' => 'decimal:2',
            'length'     => 'decimal:2',
            'year'       => 'integer',
            'axle_count' => 'integer',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function freights(): HasMany
    {
        return $this->hasMany(Freight::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === TruckStatus::Available;
    }

    public function canCarry(float $weightTons): bool
    {
        return $weightTons <= $this->max_weight;
    }
}
