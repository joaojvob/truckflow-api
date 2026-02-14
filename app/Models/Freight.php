<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Freight extends Model
{
    use BelongsToTenant; 

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
}