<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FreightController;
use App\Http\Controllers\Api\IncidentController;
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

    // Fretes
    Route::post('/freights/{id}/start', [FreightController::class, 'start']);
    Route::post('/freights/{id}/complete', [FreightController::class, 'complete']);

    // Incidentes / SOS
    Route::post('/freights/{id}/sos', [IncidentController::class, 'triggerSos']);
    Route::post('/freights/{id}/incidents', [IncidentController::class, 'store']);
});