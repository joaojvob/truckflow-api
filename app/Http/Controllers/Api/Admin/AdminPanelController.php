<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\RequestLogResource;
use App\Http\Resources\SystemLogResource;
use App\Models\SystemLog;
use App\Services\AdminTelemetryService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminPanelController extends Controller
{
    public function __construct(
        protected AdminTelemetryService $telemetryService,
        protected TenantContext $tenantContext,
    ) {}

    /**
     * Resumo de telemetria: rotas mais usadas, usuários ativos, erros.
     */
    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->telemetryService->summary($request->only(['from', 'to'])),
        ]);
    }

    /**
     * Lista erros e eventos técnicos do sistema.
     */
    public function systemLogs(Request $request): AnonymousResourceCollection
    {
        $logs = $this->telemetryService->systemLogs($request->only([
            'from', 'to', 'level', 'channel', 'search', 'per_page',
        ]));

        return SystemLogResource::collection($logs);
    }

    /**
     * Detalhe de um log de sistema.
     */
    public function showSystemLog(Request $request, SystemLog $systemLog): JsonResponse
    {
        abort_unless($systemLog->tenant_id === $this->tenantContext->effectiveId(), 404);

        $systemLog->load('user:id,name,email');

        return response()->json([
            'data' => SystemLogResource::make($systemLog),
        ]);
    }

    /**
     * Marca log de sistema como resolvido.
     */
    public function resolveSystemLog(SystemLog $systemLog): JsonResponse
    {
        abort_unless($systemLog->tenant_id === $this->tenantContext->effectiveId(), 404);

        $log = $this->telemetryService->resolveSystemLog($systemLog);

        return response()->json([
            'data' => SystemLogResource::make($log),
        ]);
    }

    /**
     * Telemetria de requisições HTTP (quem chamou o quê e quando).
     */
    public function requestLogs(Request $request): AnonymousResourceCollection
    {
        $logs = $this->telemetryService->requestLogs($request->only([
            'from', 'to', 'method', 'user_id', 'uri', 'status_code', 'per_page',
        ]));

        return RequestLogResource::collection($logs);
    }

    /**
     * Auditoria de negócio (ações em fretes, veículos, etc.).
     */
    public function activityLogs(Request $request): AnonymousResourceCollection
    {
        $logs = $this->telemetryService->activityLogs($request->only([
            'from', 'to', 'action', 'user_id', 'search', 'per_page',
        ]));

        return ActivityLogResource::collection($logs);
    }
}
