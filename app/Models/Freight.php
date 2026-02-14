<?php

namespace App\Models;

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
        'cargo_name', 
        'weight', 
        'status', 
        'origin', 
        'destination', 
        'checklist_completed'
    ];

    protected $casts = [
        'checklist_completed' => 'boolean',
        'started_at'          => 'datetime',
        'completed_at'        => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}