<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    /**
     * Registra uma atividade vinculada a este modelo.
     * * @param string $action Ex: 'trip_started', 'sos_triggered'
     * @param string $description Descrição amigável do evento
     * @param array $payload Dados extras (ex: dados do checklist ou localização)
     */
    public function recordActivity(string $action, string $description, array $payload = []): void
    {
        ActivityLog::create([
            'tenant_id'      => Auth::user()?->tenant_id, // Captura o tenant do usuário logado
            'user_id'        => Auth::id(),               // Quem fez a ação
            'action'         => $action,
            'description'    => $description,
            'auditable_type' => static::class,            // O nome da classe do Model (ex: App\Models\Freight)
            'auditable_id'   => $this->id,                // O ID do registro
            'payload'        => $payload,                 // Dados JSON
        ]);
    }
}