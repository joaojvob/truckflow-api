<?php

namespace App\Models;

use App\Enums\SystemLogLevel;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'level',
        'channel',
        'message',
        'context',
        'exception_class',
        'exception_message',
        'trace',
        'request_id',
        'method',
        'url',
        'ip',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'level'       => SystemLogLevel::class,
            'context'     => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Garante que admins só acessem logs do próprio tenant via route binding.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $query = static::query()->where($field ?? $this->getRouteKeyName(), $value);

        if ($tenantId = auth()->user()?->tenant_id) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->first();
    }
}
