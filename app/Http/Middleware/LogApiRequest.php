<?php

namespace App\Http\Middleware;

use App\Models\RequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra telemetria de cada requisição API (rota, método, usuário, duração).
 */
class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('telemetry.enabled') || $this->shouldSkip($request)) {
            return $next($request);
        }

        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        $startedAt = microtime(true);

        $response = $next($request);

        $this->persist($request, $response, $startedAt, $requestId);

        return $response;
    }

    private function shouldSkip(Request $request): bool
    {
        $path = $request->path();

        foreach (config('telemetry.skip_paths', []) as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        foreach (config('telemetry.skip_prefixes', []) as $prefix) {
            if (str_starts_with($path, 'api/v1/'.$prefix) || str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return ! $request->is('api/*');
    }

    private function persist(Request $request, Response $response, float $startedAt, string $requestId): void
    {
        $route = $request->route();
        $action = $route?->getActionName();

        if ($action === 'Closure') {
            $action = null;
        }

        RequestLog::withoutGlobalScopes()->create([
            'tenant_id'   => $request->user()?->tenant_id,
            'user_id'     => $request->user()?->id,
            'request_id'  => $requestId,
            'method'      => $request->method(),
            'route_name'  => $route?->getName(),
            'uri'         => $route?->uri() ?? '/'.$request->path(),
            'action'      => $action,
            'status_code' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'ip'          => $request->ip(),
            'user_agent'  => Str::limit((string) $request->userAgent(), 500),
        ]);
    }
}
