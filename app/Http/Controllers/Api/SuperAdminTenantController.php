<?php

namespace App\Http\Controllers\Api;

use App\Enums\FreightStatus;
use App\Http\Controllers\Controller;
use App\Models\Freight;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Visão global (cross-tenant) para super administradores.
 */
class SuperAdminTenantController extends Controller
{
    /**
     * Lista todas as empresas com métricas agregadas.
     * GET /admin/tenants
     */
    public function index(): JsonResponse
    {
        $freightCounts = Freight::query()
            ->withoutGlobalScope('tenant')
            ->selectRaw('tenant_id, count(*) as total, coalesce(sum(case when status = ? then total_price else 0 end), 0) as revenue', [FreightStatus::Completed->value])
            ->groupBy('tenant_id')
            ->get()
            ->keyBy('tenant_id');

        $userCounts = User::query()
            ->withoutGlobalScope('tenant')
            ->selectRaw('tenant_id, count(*) as total')
            ->whereNotNull('tenant_id')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $tenants = Tenant::query()->orderBy('name')->get()->map(function (Tenant $tenant) use ($freightCounts, $userCounts) {
            $freight = $freightCounts->get($tenant->id);

            return [
                'id'            => $tenant->id,
                'name'          => $tenant->name,
                'slug'          => $tenant->slug,
                'logo_url'      => $tenant->logoUrl(),
                'users_count'   => (int) ($userCounts[$tenant->id] ?? 0),
                'freights_count' => (int) ($freight->total ?? 0),
                'revenue_total' => (float) ($freight->revenue ?? 0),
                'has_fiscal'    => ! empty($tenant->settings['fiscal']['cnpj'] ?? null),
            ];
        });

        return response()->json([
            'data' => $tenants,
            'summary' => [
                'tenants'  => $tenants->count(),
                'users'    => (int) $userCounts->sum(),
                'freights' => (int) $freightCounts->sum('total'),
                'revenue'  => (float) $freightCounts->sum('revenue'),
            ],
        ]);
    }

    /**
     * Detalhe de uma empresa específica.
     * GET /admin/tenants/{tenant}
     */
    public function show(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'data' => [
                'id'       => $tenant->id,
                'name'     => $tenant->name,
                'slug'     => $tenant->slug,
                'settings' => $tenant->settings,
            ],
        ]);
    }
}
