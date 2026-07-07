<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\RequestLog;
use App\Models\SystemLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agrega telemetria e métricas para o painel administrativo.
 */
class AdminTelemetryService
{
    /**
     * Resumo consolidado de uso da API e erros do período.
     *
     * @param  array{from?: string, to?: string}  $filters
     * @return array{
     *     period: array{from: string, to: string},
     *     requests: array{total: int, avg_duration_ms: float, error_responses: int},
     *     system_errors: array{total: int, by_level: Collection},
     *     top_routes: Collection,
     *     top_users: Collection,
     *     by_method: Collection
     * }
     */
    public function summary(array $filters = []): array
    {
        [$from, $to] = $this->resolvePeriod($filters);
        $tenantId = auth()->user()->tenant_id;

        $requestQuery = RequestLog::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to]);

        $systemQuery = SystemLog::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to]);

        $totalRequests = (clone $requestQuery)->count();

        $topRoutes = (clone $requestQuery)
            ->select('method', 'uri', DB::raw('count(*) as total'), DB::raw('round(avg(duration_ms)) as avg_duration_ms'))
            ->groupBy('method', 'uri')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $topUsers = (clone $requestQuery)
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('count(*) as total'))
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $user = User::query()->find($row->user_id);

                return [
                    'user_id'    => $row->user_id,
                    'user_name'  => $user?->name,
                    'user_email' => $user?->email,
                    'total'      => (int) $row->total,
                ];
            });

        $byMethod = (clone $requestQuery)
            ->select('method', DB::raw('count(*) as total'))
            ->groupBy('method')
            ->orderByDesc('total')
            ->get();

        $errorResponses = (clone $requestQuery)->where('status_code', '>=', 500)->count();

        $systemErrors = (clone $systemQuery)->count();

        $byLevel = (clone $systemQuery)
            ->select('level', DB::raw('count(*) as total'))
            ->groupBy('level')
            ->pluck('total', 'level');

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'requests' => [
                'total'           => $totalRequests,
                'avg_duration_ms' => (float) ((clone $requestQuery)->avg('duration_ms') ?? 0),
                'error_responses' => $errorResponses,
            ],
            'system_errors' => [
                'total'    => $systemErrors,
                'by_level' => $byLevel,
            ],
            'top_routes' => $topRoutes,
            'top_users'  => $topUsers,
            'by_method'  => $byMethod,
        ];
    }

    /**
     * Lista paginada de logs de sistema com filtros.
     *
     * @param  array<string, mixed>  $filters
     */
    public function systemLogs(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        [$from, $to] = $this->resolvePeriod($filters);

        $tenantId = auth()->user()->tenant_id;

        return SystemLog::query()
            ->where('tenant_id', $tenantId)
            ->with('user:id,name,email')
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['level'] ?? null, fn ($q, $level) => $q->where('level', $level))
            ->when($filters['channel'] ?? null, fn ($q, $channel) => $q->where('channel', $channel))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('message', 'ilike', "%{$search}%")
                        ->orWhere('exception_message', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Lista paginada de telemetria de requisições.
     *
     * @param  array<string, mixed>  $filters
     */
    public function requestLogs(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        [$from, $to] = $this->resolvePeriod($filters);
        $tenantId = auth()->user()->tenant_id;

        return RequestLog::query()
            ->where('tenant_id', $tenantId)
            ->with('user:id,name,email')
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['method'] ?? null, fn ($q, $method) => $q->where('method', strtoupper($method)))
            ->when($filters['user_id'] ?? null, fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['uri'] ?? null, fn ($q, $uri) => $q->where('uri', 'ilike', "%{$uri}%"))
            ->when($filters['status_code'] ?? null, fn ($q, $code) => $q->where('status_code', $code))
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Lista paginada de auditoria de negócio (activity_logs).
     *
     * @param  array<string, mixed>  $filters
     */
    public function activityLogs(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        [$from, $to] = $this->resolvePeriod($filters);
        $tenantId = auth()->user()->tenant_id;

        return ActivityLog::query()
            ->with(['user:id,name,email'])
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($filters['user_id'] ?? null, fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where('description', 'ilike', "%{$search}%");
            })
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Marca um log de sistema como resolvido pelo admin.
     */
    public function resolveSystemLog(SystemLog $systemLog): SystemLog
    {
        $systemLog->update(['resolved_at' => now()]);

        return $systemLog->fresh('user');
    }

    /**
     * @param  array{from?: string, to?: string}  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(array $filters): array
    {
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(7)->startOfDay();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }
}
