<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe rotas globais (cross-tenant) a super administradores.
 */
class EnsureUserIsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isSuperAdmin()) {
            return response()->json([
                'message' => 'Acesso restrito a super administradores.',
            ], 403);
        }

        return $next($request);
    }
}
