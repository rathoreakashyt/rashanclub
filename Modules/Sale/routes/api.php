<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Sale\Http\Controllers\CustomerController;
use Modules\Sale\Http\Controllers\CustomerReceiveController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Customer and CustomerReceive API Routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('customer', CustomerController::class);
    Route::apiResource('customer-receive', CustomerReceiveController::class);
});