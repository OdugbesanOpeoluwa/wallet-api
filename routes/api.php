<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\WebhookController;

// rate limiters
RateLimiter::for('auth', function ($request) {
    return Limit::perMinute(5)->by($request->ip());
});

RateLimiter::for('api', function ($request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('financial', function ($request) {
    return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
});

// public
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');

// webhook
Route::post('/webhooks/crypto', [WebhookController::class, 'crypto']);

// protected
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/wallets', [WalletController::class, 'index']);
    Route::post('/wallets', [WalletController::class, 'store']);
    Route::get('/wallets/{id}/transactions', [WalletController::class, 'transactions']);
});

// financial
Route::middleware(['auth:sanctum', 'throttle:financial'])->group(function () {
    Route::post('/transfers', [TransferController::class, 'store']);
    Route::post('/bills/pay', [BillController::class, 'store']);
    Route::post('/withdrawals', [WithdrawalController::class, 'store']);
});