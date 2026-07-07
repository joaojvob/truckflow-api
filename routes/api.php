<?php

use App\Http\Controllers\Api\Admin\AdminPanelController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DriverProfileController;
use App\Http\Controllers\Api\FreightController;
use App\Http\Controllers\Api\FreightPlaceController;
use App\Http\Controllers\Api\FreightRouteController;
use App\Http\Controllers\Api\FreightTrackingController;
use App\Http\Controllers\Api\FreightWorkflowController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\ManagerDriverController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\TrailerController;
use App\Http\Controllers\Api\TruckController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WaypointController;
use Illuminate\Support\Facades\Route;

// ─── Rotas Públicas (sem autenticação) ───────────────────────
Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// ─── Rotas Protegidas (Sanctum) ──────────────────────────────
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Empresa (Tenant)
    Route::post('/tenant', [TenantController::class, 'store']);
    Route::get('/tenant', [TenantController::class, 'show']);
    Route::put('/tenant', [TenantController::class, 'update']);

    // Perfil do Motorista
    Route::get('/driver-profile', [DriverProfileController::class, 'show']);
    Route::put('/driver-profile', [DriverProfileController::class, 'update']);
    Route::post('/driver-profile/cnh', [DriverProfileController::class, 'uploadCnh']);
    Route::get('/driver-profile/cnh', [DriverProfileController::class, 'downloadCnh']);

    // Usuários (gestão pelo admin/manager)
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::get('/users/{user}/cnh', [DriverProfileController::class, 'downloadCnhForUser']);
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole']);

    // Fretes — CRUD
    Route::apiResource('freights', FreightController::class);
    Route::post('/freights/{freight}/cancel', [FreightController::class, 'cancel']);

    // Fretes — Rota (Google Directions API)
    Route::get('/freights/{freight}/route', [FreightRouteController::class, 'show']);
    Route::post('/freights/{freight}/route', [FreightRouteController::class, 'calculate']);

    // Fretes — Places (Google Places API)
    Route::post('/freights/{freight}/places/search', [FreightPlaceController::class, 'search']);

    // Fretes — Tracking GPS
    Route::get('/freights/{freight}/tracking', [FreightTrackingController::class, 'show']);
    Route::get('/freights/{freight}/tracking/history', [FreightTrackingController::class, 'history']);
    Route::post('/freights/{freight}/tracking', [FreightTrackingController::class, 'store']);

    // Relatórios
    Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);
    Route::get('/reports/financial', [ReportController::class, 'financial']);
    Route::get('/reports/financial/export', [ReportController::class, 'exportFinancial']);

    // Fretes — Workflow (gestor↔motorista)
    Route::prefix('freights/{freight}')->group(function () {
        Route::post('/assign', [FreightWorkflowController::class, 'assign']);
        Route::post('/accept', [FreightWorkflowController::class, 'accept']);
        Route::post('/reject', [FreightWorkflowController::class, 'reject']);
        Route::post('/doping', [FreightWorkflowController::class, 'submitDoping']);
        Route::post('/doping/{dopingTest}/review', [FreightWorkflowController::class, 'reviewDoping']);
        Route::post('/checklist', [FreightWorkflowController::class, 'submitChecklist']);
        Route::post('/approve', [FreightWorkflowController::class, 'approve']);
        Route::post('/start', [FreightWorkflowController::class, 'start']);
        Route::post('/complete', [FreightWorkflowController::class, 'complete']);
    });

    // Incidentes / SOS
    Route::post('/freights/{freight}/sos', [IncidentController::class, 'triggerSos']);
    Route::post('/freights/{freight}/incidents', [IncidentController::class, 'store']);

    // Waypoints (pontos de parada na rota)
    Route::prefix('freights/{freight}/waypoints')->group(function () {
        Route::get('/', [WaypointController::class, 'index']);
        Route::post('/', [WaypointController::class, 'store']);
        Route::get('/{waypoint}', [WaypointController::class, 'show']);
        Route::put('/{waypoint}', [WaypointController::class, 'update']);
        Route::delete('/{waypoint}', [WaypointController::class, 'destroy']);
        Route::post('/{waypoint}/checkin', [WaypointController::class, 'checkin']);
        Route::post('/{waypoint}/checkout', [WaypointController::class, 'checkout']);
        Route::post('/reorder', [WaypointController::class, 'reorder']);
    });

    // Gestão Gestor ↔ Motorista
    Route::get('/manager/drivers', [ManagerDriverController::class, 'index']);
    Route::post('/manager/drivers', [ManagerDriverController::class, 'store']);
    Route::delete('/manager/drivers/{driver}', [ManagerDriverController::class, 'destroy']);

    // Notificações
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Caminhões — CRUD
    Route::post('/trucks/{truck}/crlv', [TruckController::class, 'uploadCrlv']);
    Route::get('/trucks/{truck}/crlv', [TruckController::class, 'downloadCrlv']);
    Route::apiResource('trucks', TruckController::class);

    // Reboques — CRUD
    Route::post('/trailers/{trailer}/crlv', [TrailerController::class, 'uploadCrlv']);
    Route::get('/trailers/{trailer}/crlv', [TrailerController::class, 'downloadCrlv']);
    Route::apiResource('trailers', TrailerController::class);

    // Painel administrativo (telemetria, logs, auditoria)
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/telemetry/summary', [AdminPanelController::class, 'summary']);
        Route::get('/system-logs', [AdminPanelController::class, 'systemLogs']);
        Route::get('/system-logs/{systemLog}', [AdminPanelController::class, 'showSystemLog']);
        Route::post('/system-logs/{systemLog}/resolve', [AdminPanelController::class, 'resolveSystemLog']);
        Route::get('/request-logs', [AdminPanelController::class, 'requestLogs']);
        Route::get('/activity-logs', [AdminPanelController::class, 'activityLogs']);
    });
});