<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define o tenant ativo da requisição.
 *
 * - Usuários comuns: tenant do próprio usuário.
 * - Super admin: tenant informado no header X-Tenant-Id (quando "entra" numa empresa);
 *   sem header, o contexto fica vazio e ele enxerga todos os tenants.
 */
class ResolveTenantContext
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if ($user->isSuperAdmin()) {
                $headerTenant = $request->header('X-Tenant-Id');
                $this->tenantContext->set($headerTenant ? (int) $headerTenant : null);
            } else {
                $this->tenantContext->set($user->tenant_id);
            }
        }

        return $next($request);
    }
}
