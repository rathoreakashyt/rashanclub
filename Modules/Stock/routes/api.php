<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Stock\Http\Controllers\BrandController;
use Modules\Stock\Http\Controllers\StockController;
use Modules\Stock\Http\Controllers\ItemCategoryController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Brand API Routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('brand', BrandController::class);
    Route::apiResource('stocks', StockController::class);
    Route::apiResource('item-category', ItemCategoryController::class);
});