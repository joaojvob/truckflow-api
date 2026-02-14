<?php

namespace App\Models;

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

    /**
     * Tipos válidos de incidentes.
     */
    public const TYPES = ['breakdown', 'accident', 'robbery', 'sos'];

    /**
     * Relacionamento: O incidente pertence a um frete.
     */
    public function freight(): BelongsTo
    {
        return $this->belongsTo(Freight::class);
    }

    /**
     * Relacionamento: Quem reportou o incidente.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Helper: verifica se é um SOS.
     */
    public function isSos(): bool
    {
        return $this->type === 'sos';
    }
}
