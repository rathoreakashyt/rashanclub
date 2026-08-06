<?php

use Illuminate\Support\Facades\Route;
use Modules\Configuration\Http\Controllers\ConfigurationController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('configurations', ConfigurationController::class)->names('configuration');
});
