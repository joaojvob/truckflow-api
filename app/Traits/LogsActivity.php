<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

/**
 * Registra ações de auditoria vinculadas ao model que usa o trait.
 */
trait LogsActivity
{
    /**
     * Persiste um registro na tabela activity_logs.
     *
     * @param  string  $action  Identificador da ação (ex.: trip_started, sos_triggered).
     * @param  string  $description  Texto legível para humanos.
     * @param  array<string, mixed>  $payload  Dados extras serializados em JSON.
     */
    public function recordActivity(string $action, string $description, array $payload = []): void
    {
        $tenantId = Auth::user()?->tenant_id ?? $this->tenant_id ?? null;

        if ($tenantId === null) {
            return;
        }

        ActivityLog::create([
            'tenant_id'      => $tenantId,
            'user_id'        => Auth::id(),
            'action'         => $action,
            'description'    => $description,
            'auditable_type' => static::class,
            'auditable_id'   => $this->id,
            'payload'        => $payload,
        ]);
    }
}
