<?php

namespace App\Models;

use App\Enums\DriverResponse;
use App\Enums\FreightStatus;
use App\Enums\TrailerType;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Freight extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'driver_id',
        'truck_id',
        'trailer_id',
        'cargo_name',
        'cargo_description',
        'weight',
        'is_hazardous',
        'is_fragile',
        'requires_refrigeration',
        'status',
        'origin',
        'destination',
        'origin_address',
        'destination_address',
        'required_trailer_type',
        'required_hitch_type',
        'distance_km',
        'estimated_hours',
        'price_per_km',
        'price_per_ton',
        'toll_cost',
        'fuel_cost',
        'total_price',
        'driver_response',
        'rejection_reason',
        'driver_responded_at',
        'checklist_completed',
        'doping_approved',
        'manager_approved',
        'approved_by',
        'approved_at',
        'driver_rating',
        'driver_notes',
        'started_at',
        'completed_at',
        'deadline_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status'                 => FreightStatus::class,
            'required_trailer_type'  => TrailerType::class,
            'checklist_completed'    => 'boolean',
            'is_hazardous'           => 'boolean',
            'is_fragile'             => 'boolean',
            'requires_refrigeration' => 'boolean',
            'weight'                 => 'decimal:2',
            'distance_km'            => 'decimal:2',
            'price_per_km'           => 'decimal:4',
            'price_per_ton'          => 'decimal:2',
            'toll_cost'              => 'decimal:2',
            'fuel_cost'              => 'decimal:2',
            'total_price'            => 'decimal:2',
            'driver_response'        => DriverResponse::class,
            'doping_approved'        => 'boolean',
            'manager_approved'       => 'boolean',
            'started_at'             => 'datetime',
            'completed_at'           => 'datetime',
            'deadline_at'            => 'datetime',
            'driver_responded_at'    => 'datetime',
            'approved_at'            => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Trailer::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function dopingTests(): HasMany
    {
        return $this->hasMany(DopingTest::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ─── Workflow Helpers ─────────────────────────────────

    public function isReadyToStart(): bool
    {
        return $this->status === FreightStatus::Ready
            && $this->manager_approved
            && $this->doping_approved
            && $this->checklist_completed;
    }

    public function isWaitingDriverResponse(): bool
    {
        return $this->status === FreightStatus::Assigned
            && $this->driver_response === DriverResponse::Pending;
    }

    // ─── Helpers ──────────────────────────────────────────────

    /**
     * Calcula o preço total do frete baseado nos componentes.
     */
    public function calculateTotalPrice(): float
    {
        $distancePrice = ($this->price_per_km ?? 0) * ($this->distance_km ?? 0);
        $weightPrice   = ($this->price_per_ton ?? 0) * ($this->weight ?? 0);
        $costs         = ($this->toll_cost ?? 0) + ($this->fuel_cost ?? 0);

        return $distancePrice + $weightPrice + $costs;
    }

    /**
     * Verifica se o trailer atende os requisitos do frete.
     */
    public function isTrailerCompatible(?Trailer $trailer): bool
    {
        if (! $this->required_trailer_type || ! $trailer) {
            return true;
        }

        return $trailer->type === $this->required_trailer_type;
    }
}