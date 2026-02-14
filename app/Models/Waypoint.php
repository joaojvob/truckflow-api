<?php

namespace App\Models;

use App\Enums\WaypointType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Waypoint extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'freight_id',
        'created_by',
        'name',
        'description',
        'type',
        'location',
        'address',
        'order',
        'mandatory',
        'estimated_stop_minutes',
        'arrived_at',
        'departed_at',
    ];

    protected function casts(): array
    {
        return [
            'type'                   => WaypointType::class,
            'mandatory'              => 'boolean',
            'estimated_stop_minutes' => 'integer',
            'order'                  => 'integer',
            'arrived_at'             => 'datetime',
            'departed_at'            => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────

    public function freight(): BelongsTo
    {
        return $this->belongsTo(Freight::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Helpers ──────────────────────────────────────────────

    public function isVisited(): bool
    {
        return $this->arrived_at !== null;
    }

    public function isCompleted(): bool
    {
        return $this->arrived_at !== null && $this->departed_at !== null;
    }

    public function markArrival(): void
    {
        $this->update(['arrived_at' => now()]);
    }

    public function markDeparture(): void
    {
        $this->update(['departed_at' => now()]);
    }
}
