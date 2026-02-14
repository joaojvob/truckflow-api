<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverProfile extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'phone',
        'cpf',
        'birth_date',
        'cnh_number',
        'cnh_category',
        'cnh_expiry',
        'address',
        'city',
        'state',
        'zip_code',
        'emergency_contact_name',
        'emergency_contact_phone',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'birth_date'   => 'date',
            'cnh_expiry'   => 'date',
            'is_available' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCnhExpired(): bool
    {
        return $this->cnh_expiry && $this->cnh_expiry->isPast();
    }

    public function isCnhExpiringSoon(int $days = 30): bool
    {
        return $this->cnh_expiry && $this->cnh_expiry->between(now(), now()->addDays($days));
    }
}
