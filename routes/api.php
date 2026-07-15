<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RouterController;
use App\Http\Controllers\Api\TorchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NETSIGHT v2.1 API Routes
|--------------------------------------------------------------------------
|
| Semua endpoint mengikuti spesifikasi SRD.md Section 3.
| RBAC enforcement di layer backend via RbacMiddleware.
| Audit logging via AuditLogMiddleware.
|
| @see SRD.md Section 3 — Spesifikasi API Endpoint
| @see SECURITY.md Section 3.3 — RBAC Matriks
| @see AGENT.md Section 5 — Jangan bypass middleware RBAC
*/

// ============================================================
// PUBLIC ROUTES — Login & TOTP verification
// ============================================================
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1'); // 10 attempts per minute

    Route::post('/totp-verify', [AuthController::class, 'verifyTotp'])
        ->middleware('auth:sanctum');
});

Route::post('/demo/start', [\App\Http\Controllers\Api\DemoController::class, 'start'])
    ->middleware('throttle:2,1440');

// ============================================================
// AUTHENTICATED ROUTES
// ============================================================
Route::middleware(['auth:sanctum', 'audit'])->group(function () {

    // Auth info & logout
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // ----------------------------------------------------------
    // TIER_1+ — Daftar router & cache user PPPoE
    // ----------------------------------------------------------
    Route::middleware('rbac:TIER_1')->group(function () {
        Route::get('/routers', [RouterController::class, 'index']);
        Route::get('/routers/{id}/users', [RouterController::class, 'users'])
            ->where('id', '[0-9]+');
    });

    // ----------------------------------------------------------
    // TIER_2+ — Torch, force-sync, health-check
    // ----------------------------------------------------------
    Route::middleware('rbac:TIER_2')->group(function () {
        // Force sync
        Route::post('/routers/{id}/force-sync', [RouterController::class, 'forceSync'])
            ->where('id', '[0-9]+');

        // Health check (pre-flight)
        Route::get('/routers/{id}/health-check', [RouterController::class, 'healthCheck'])
            ->where('id', '[0-9]+');

        // Torch endpoints
        Route::post('/torch/inspect', [TorchController::class, 'inspect']);
        Route::get('/torch/history', [TorchController::class, 'history']);
        Route::get('/torch/history/{id}', [TorchController::class, 'show'])->where('id', '[0-9]+');
        Route::post('/torch/{tag}/cancel', [TorchController::class, 'cancel']);
        Route::post('/torch/{tag}/heartbeat', [TorchController::class, 'heartbeat']);
        Route::get('/torch/{tag}/ping', [TorchController::class, 'ping']);
        Route::get('/torch/{tag}/logs', [TorchController::class, 'logs']);
        Route::get('/torch/{tag}/traceroute', [TorchController::class, 'traceroute']);
        Route::get('/torch/{tag}/stream', [\App\Http\Controllers\Api\TorchStreamController::class, 'stream']);
    });

    // ----------------------------------------------------------
    // ADMIN — Audit logs, TOTP setup, management
    // ----------------------------------------------------------
    Route::middleware('rbac:ADMIN')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::post('/auth/setup-totp', [AuthController::class, 'setupTotp']);
        
        // Torch History Management
        Route::delete('/torch/history/{id}', [TorchController::class, 'destroy'])->where('id', '[0-9]+');
        
        // Router Management (CRUD)
        Route::post('/routers', [RouterController::class, 'store']);
        Route::put('/routers/{id}', [RouterController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/routers/{id}', [RouterController::class, 'destroy'])->where('id', '[0-9]+');

        // Staff Management (CRUD)
        Route::get('/staff', [\App\Http\Controllers\Api\StaffController::class, 'index']);
        Route::post('/staff', [\App\Http\Controllers\Api\StaffController::class, 'store']);
        Route::put('/staff/{id}', [\App\Http\Controllers\Api\StaffController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/staff/{id}', [\App\Http\Controllers\Api\StaffController::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/staff/{id}/reset-totp', [\App\Http\Controllers\Api\StaffController::class, 'resetTotp'])->where('id', '[0-9]+');
    });
});
