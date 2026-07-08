<?php

namespace App\Support;

/**
 * Mantém o tenant "ativo" da requisição atual.
 *
 * Para usuários normais, o contexto é o próprio tenant. Para super admins,
 * o contexto é definido via header X-Tenant-Id quando eles "entram" em uma empresa.
 */
class TenantContext
{
    private ?int $tenantId = null;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Tenant efetivo da requisição: o do contexto (super admin dentro de uma
     * empresa) ou o do próprio usuário autenticado.
     */
    public function effectiveId(): ?int
    {
        return $this->tenantId ?? auth()->user()?->tenant_id;
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }
}
