<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checklist extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'freight_id',
        'items',
    ];

    /**
     * Cast para garantir que o JSON vire um array PHP automaticamente.
     */
    protected $casts = [
        'items' => 'array',
    ];

    /**
     * Relacionamento: O checklist pertence a um frete específico.
     */
    public function freight(): BelongsTo
    {
        return $this->belongsTo(Freight::class);
    }
}
