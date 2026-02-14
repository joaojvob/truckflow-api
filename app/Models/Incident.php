<?php

namespace App\Models;

use App\Enums\IncidentType;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'freight_id',
        'user_id',
        'type',
        'description',
        'location',
    ];

    protected function casts(): array
    {
        return [
            'type' => IncidentType::class,
        ];
    }

    public function freight(): BelongsTo
    {
        return $this->belongsTo(Freight::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isSos(): bool
    {
        return $this->type === IncidentType::Sos;
    }
}
