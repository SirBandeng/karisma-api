<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\DailySerialController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TariffController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TerminalController;

// ─── Public ───────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);

// ─── Authenticated ────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // ─── Health Check ─────────────────────────────────────
    Route::get('/ping', fn() => response()->json(['status' => 'ok']));

    Route::get('/tariffs',        [TariffController::class, 'index']);

    // No. Seri — bisa diakses petugas & admin
    Route::get('/daily-serials',  [DailySerialController::class, 'index']);
    Route::post('/daily-serials', [DailySerialController::class, 'store']);

    // ─── Petugas ──────────────────────────────────────
    Route::middleware('role:petugas')->group(function () {
        Route::post('/transactions',              [TransactionController::class, 'store']);
        Route::get('/transactions/today/summary', [TransactionController::class, 'todaySummary']);
    });

    // ─── Admin (+ superadmin via hierarchy) ───────────
    Route::middleware('role:admin')->group(function () {
        // Laporan terminal sendiri
        Route::get('/reports/daily',   [ReportController::class, 'daily']);
        Route::get('/reports/monthly', [ReportController::class, 'monthly']);
        Route::get('/reports/export',  [ReportController::class, 'exportExcel']);

        // Tarif
        Route::post('/tariffs',        [TariffController::class, 'store']);
        Route::get('/tariffs/history', [TariffController::class, 'history']);

        // Manajemen petugas (admin hanya bisa manage terminal sendiri)
        Route::get('/users',                  [UserController::class, 'index']);
        Route::post('/users',                 [UserController::class, 'store']);
        Route::put('/users/{user}',           [UserController::class, 'update']);
        Route::patch('/users/{user}/toggle',  [UserController::class, 'toggle']);
    });

    // ─── Superadmin only ──────────────────────────────
    Route::middleware('role:superadmin')->group(function () {
        // Manage terminal
        Route::get('/terminals',             [TerminalController::class, 'index']);
        Route::post('/terminals',            [TerminalController::class, 'store']);
        Route::put('/terminals/{terminal}',  [TerminalController::class, 'update']);

        // Manage admin
        Route::get('/admin-users', [UserController::class, 'adminIndex']);

        // Laporan gabungan semua terminal
        Route::get('/reports/all-terminals', [ReportController::class, 'allTerminals']);
    });
});
