<?php

namespace App\Models;

use App\Enums\DopingStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DopingTest extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'freight_id',
        'driver_id',
        'file_path',
        'status',
        'reviewer_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status'      => DopingStatus::class,
            'reviewed_at' => 'datetime',
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === DopingStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === DopingStatus::Approved;
    }
}
