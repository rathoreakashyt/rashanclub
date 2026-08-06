<?php

use Illuminate\Support\Facades\Route;
use Modules\Administrator\Http\Controllers\AdministratorController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('administrators', AdministratorController::class)->names('administrator');
});
