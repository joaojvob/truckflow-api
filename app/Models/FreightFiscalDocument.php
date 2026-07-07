<?php

namespace App\Models;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreightFiscalDocument extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'freight_id',
        'created_by',
        'type',
        'status',
        'access_key',
        'protocol_number',
        'xml_path',
        'pdf_path',
        'rejection_reason',
        'payload',
        'authorized_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'type'          => FiscalDocumentType::class,
            'status'        => FiscalDocumentStatus::class,
            'payload'       => 'array',
            'authorized_at' => 'datetime',
            'cancelled_at'  => 'datetime',
        ];
    }

    public function freight(): BelongsTo
    {
        return $this->belongsTo(Freight::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isAuthorized(): bool
    {
        return $this->status === FiscalDocumentStatus::Authorized;
    }

    public function canBeCancelled(): bool
    {
        return $this->status === FiscalDocumentStatus::Authorized;
    }
}
