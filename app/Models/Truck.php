<?php

namespace App\Models;

use App\Enums\TruckStatus;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Truck extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'driver_id',
        'plate',
        'renavam',
        'brand',
        'model',
        'year',
        'color',
        'axle_count',
        'max_weight',
        'has_trailer_hitch',
        'hitch_type',
        'status',
        'odometer',
    ];

    protected function casts(): array
    {
        return [
            'status'            => TruckStatus::class,
            'has_trailer_hitch' => 'boolean',
            'max_weight'        => 'decimal:2',
            'odometer'          => 'integer',
            'year'              => 'integer',
            'axle_count'        => 'integer',
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

    public function canAttachTrailer(Trailer $trailer): bool
    {
        if (! $this->has_trailer_hitch) {
            return false;
        }

        return $this->hitch_type === $trailer->hitch_type;
    }
}
