<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverProfile extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'phone',
        'cpf',
        'birth_date',
        'cnh_number',
        'cnh_category',
        'cnh_expiry',
        'cnh_file_path',
        'cnh_uploaded_at',
        'address',
        'city',
        'state',
        'zip_code',
        'emergency_contact_name',
        'emergency_contact_phone',
        'is_available',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'birth_date'      => 'date',
            'cnh_expiry'      => 'date',
            'cnh_uploaded_at' => 'datetime',
            'is_available'    => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasCnhDocument(): bool
    {
        return filled($this->cnh_file_path);
    }

    /**
     * URL pública da foto de perfil (ou null se não houver).
     */
    public function photoUrl(): ?string
    {
        return $this->photo_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->photo_path)
            : null;
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
