<?php

namespace App\Services;

use App\Enums\FreightStatus;
use App\Models\Freight;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
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
                'total'      => (clone $query)->count(),
                'active'     => (clone $query)->whereIn('status', [
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
                'revenue_total'     => (float) ((clone $completedQuery)->sum('total_price') ?? 0),
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

    public function financial(array $filters = []): array
    {
        $user = auth()->user();
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfMonth();
        $to   = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

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
}
