<?php

namespace App\Services;

use App\Enums\CargoType;
use App\Enums\FreightStatus;
use App\Models\Freight;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agrega métricas operacionais e financeiras para dashboards e relatórios.
 *
 * O escopo dos dados respeita o papel do usuário: gestor vê fretes que criou,
 * motorista vê fretes atribuídos, admin vê todo o tenant.
 */
class ReportService
{
    /**
     * Monta métricas gerais para o dashboard (fretes, receita, distância).
     *
     * @param  User|null  $user  Usuário de referência; usa auth()->user() se omitido.
     * @return array{
     *     freights: array{total: int, active: int, in_transit: int, completed: int, by_status: \Illuminate\Support\Collection},
     *     financial: array{revenue_total: float, revenue_this_month: float, avg_freight_value: float},
     *     distance: array{km_completed: float}
     * }
     */
    public function dashboard(?User $user = null): array
    {
        $user ??= auth()->user();

        $query = Freight::query();

        if ($user->isManager()) {
            $query->where('created_by', $user->id);
        } elseif ($user->isDriver()) {
            $query->where('driver_id', $user->id);
        }

        $statusCounts = (clone $query)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $completedQuery = (clone $query)->where('status', FreightStatus::Completed);

        return [
            'freights' => [
                'total'  => (clone $query)->count(),
                'active' => (clone $query)->whereIn('status', [
                    FreightStatus::Assigned,
                    FreightStatus::Accepted,
                    FreightStatus::Ready,
                    FreightStatus::InTransit,
                ])->count(),
                'in_transit' => (clone $query)->where('status', FreightStatus::InTransit)->count(),
                'completed'  => (int) ($statusCounts[FreightStatus::Completed->value] ?? 0),
                'by_status'  => $statusCounts,
            ],
            'financial' => [
                'revenue_total'      => (float) ((clone $completedQuery)->sum('total_price') ?? 0),
                'revenue_this_month' => (float) ((clone $completedQuery)
                    ->whereMonth('completed_at', now()->month)
                    ->whereYear('completed_at', now()->year)
                    ->sum('total_price') ?? 0),
                'avg_freight_value' => (float) ((clone $completedQuery)->avg('total_price') ?? 0),
            ],
            'distance' => [
                'km_completed' => (float) ((clone $completedQuery)->sum('distance_km') ?? 0),
            ],
        ];
    }

    /**
     * Gera relatório financeiro detalhado para um período.
     *
     * @param  array{from?: string, to?: string}  $filters  Datas ISO; padrão = mês atual.
     * @return array{
     *     period: array{from: string, to: string},
     *     summary: array{freight_count: int, revenue: float, distance_km: float, avg_value: float},
     *     by_driver: \Illuminate\Support\Collection<int, array{driver_id: int, driver_name: string|null, freights: int, revenue: float}>
     * }
     */
    public function financial(array $filters = []): array
    {
        $user = auth()->user();
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        $query = Freight::query()
            ->where('status', FreightStatus::Completed)
            ->whereBetween('completed_at', [$from, $to]);

        if ($user->isManager()) {
            $query->where('created_by', $user->id);
        } elseif ($user->isDriver()) {
            $query->where('driver_id', $user->id);
        }

        $totals = (clone $query)->selectRaw('
            count(*) as freight_count,
            coalesce(sum(total_price), 0) as revenue,
            coalesce(sum(distance_km), 0) as distance_km,
            coalesce(avg(total_price), 0) as avg_value
        ')->first();

        $byDriver = (clone $query)
            ->select('driver_id', DB::raw('count(*) as freights'), DB::raw('coalesce(sum(total_price), 0) as revenue'))
            ->groupBy('driver_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $driver = User::query()->find($row->driver_id);

                return [
                    'driver_id'   => $row->driver_id,
                    'driver_name' => $driver?->name,
                    'freights'    => (int) $row->freights,
                    'revenue'     => (float) $row->revenue,
                ];
            });

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'summary' => [
                'freight_count' => (int) ($totals->freight_count ?? 0),
                'revenue'       => (float) ($totals->revenue ?? 0),
                'distance_km'   => (float) ($totals->distance_km ?? 0),
                'avg_value'     => (float) ($totals->avg_value ?? 0),
            ],
            'by_driver' => $byDriver,
        ];
    }

    /**
     * Relatório analítico combinando visão operacional e financeira do período.
     *
     * @param  array{from?: string, to?: string}  $filters  Datas ISO; padrão = últimos 6 meses.
     * @return array<string, mixed>
     */
    public function analytics(array $filters = []): array
    {
        $user = auth()->user();
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subMonths(6)->startOfMonth();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        $base = fn () => $this->scopeForUser(Freight::query(), $user)
            ->whereBetween('created_at', [$from, $to]);

        // Por status
        $byStatus = $base()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Fretes concluídos no período (financeiro por completed_at)
        $completed = $this->scopeForUser(Freight::query(), $user)
            ->where('status', FreightStatus::Completed)
            ->whereBetween('completed_at', [$from, $to]);

        $financialTotals = (clone $completed)->selectRaw('
            count(*) as freight_count,
            coalesce(sum(total_price), 0) as revenue,
            coalesce(sum(toll_cost), 0) as toll,
            coalesce(sum(fuel_cost), 0) as fuel,
            coalesce(sum(distance_km), 0) as distance_km,
            coalesce(avg(total_price), 0) as avg_value
        ')->first();

        // Receita por mês (série temporal)
        $revenueByMonth = (clone $completed)
            ->selectRaw("to_char(completed_at, 'YYYY-MM') as month, count(*) as freights, coalesce(sum(total_price), 0) as revenue")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month'    => $row->month,
                'freights' => (int) $row->freights,
                'revenue'  => (float) $row->revenue,
            ]);

        // Top tipos de carga (todos os fretes criados no período)
        $byCargoType = $base()
            ->select('cargo_type', DB::raw('count(*) as total'), DB::raw('coalesce(sum(total_price), 0) as revenue'))
            ->whereNotNull('cargo_type')
            ->groupBy('cargo_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'cargo_type' => $row->cargo_type,
                'label'      => CargoType::tryFrom($row->cargo_type)?->label() ?? $row->cargo_type,
                'total'      => (int) $row->total,
                'revenue'    => (float) $row->revenue,
            ]);

        // Desempenho por motorista (concluídos)
        $byDriver = (clone $completed)
            ->select('driver_id', DB::raw('count(*) as freights'), DB::raw('coalesce(sum(total_price), 0) as revenue'), DB::raw('coalesce(sum(distance_km), 0) as distance_km'), DB::raw('coalesce(avg(driver_rating), 0) as avg_rating'))
            ->groupBy('driver_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $driver = User::query()->withoutGlobalScope('tenant')->find($row->driver_id);

                return [
                    'driver_id'   => $row->driver_id,
                    'driver_name' => $driver?->name,
                    'freights'    => (int) $row->freights,
                    'revenue'     => (float) $row->revenue,
                    'distance_km' => (float) $row->distance_km,
                    'avg_rating'  => round((float) $row->avg_rating, 1),
                ];
            });

        $revenue = (float) ($financialTotals->revenue ?? 0);
        $costs = (float) ($financialTotals->toll ?? 0) + (float) ($financialTotals->fuel ?? 0);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'summary' => [
                'freight_count' => (int) ($financialTotals->freight_count ?? 0),
                'revenue'       => $revenue,
                'costs'         => round($costs, 2),
                'net'           => round($revenue - $costs, 2),
                'distance_km'   => (float) ($financialTotals->distance_km ?? 0),
                'avg_value'     => (float) ($financialTotals->avg_value ?? 0),
            ],
            'by_status'       => $byStatus,
            'revenue_by_month' => $revenueByMonth,
            'by_cargo_type'   => $byCargoType,
            'by_driver'       => $byDriver,
        ];
    }

    /**
     * Aplica o escopo por papel numa query de fretes.
     */
    private function scopeForUser($query, User $user)
    {
        if ($user->isManager()) {
            $query->where('created_by', $user->id);
        } elseif ($user->isDriver()) {
            $query->where('driver_id', $user->id);
        }

        return $query;
    }
}
