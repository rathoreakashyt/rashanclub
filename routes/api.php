<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SendBillController;
use App\Http\Controllers\Api\SyncController;

/*
|--------------------------------------------------------------------------
| API Routes (RashanKiDukan WPF Desktop App)
|--------------------------------------------------------------------------
| Offline-first two-way sync endpoints consumed by the WPF POS software.
| All routes except login require a Sanctum Bearer token.
*/

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('api.auth.login');
    Route::post('logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('api.auth.logout');
    Route::get('me', [AuthController::class, 'me'])
        ->middleware('auth:sanctum')
        ->name('api.auth.me');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('health', [SyncController::class, 'health'])->name('api.health');
    Route::get('meta', [SyncController::class, 'meta'])->name('api.meta');
    Route::get('sync/pull', [SyncController::class, 'pull'])->name('api.sync.pull');
    Route::post('sync/push', [SyncController::class, 'push'])->name('api.sync.push');
    Route::post('sync/push-entity', [SyncController::class, 'pushEntity'])->name('api.sync.push.entity');
    Route::get('send-bill/settings', [SendBillController::class, 'settings'])->name('api.sendbill.settings');
    Route::post('send-bill', [SendBillController::class, 'send'])->name('api.sendbill.send');
});
