<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'description',
        'auditable_type',
        'auditable_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relacionamento polimórfico (pode ser Freight, User, etc).
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
