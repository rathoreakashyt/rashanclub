<?php

use App\Http\Controllers\InstallController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\UpdateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Serve language JSON files from resources/lang/ (for JS frontend)
|--------------------------------------------------------------------------
*/
Route::get('resources/lang/{locale}.json', function (string $locale) {
    $path = resource_path('lang/' . $locale . '.json');
    if (!is_file($path)) {
        abort(404);
    }
    return response(file_get_contents($path), 200, [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->where('locale', '[a-z]{2}');

/*
|--------------------------------------------------------------------------
| LP favicon/logo image (when LP=BD) - served as PNG so it always displays
|--------------------------------------------------------------------------
*/
Route::get('lp-favicon-logo.png', function () {
    if (!defined('LP') || LP !== 'BD' || !defined('LP_BD_FAVICON_LOGO_DATA_URI')) {
        abort(404);
    }
    $dataUri = LP_BD_FAVICON_LOGO_DATA_URI;
    if (strpos($dataUri, 'base64,') === false) {
        abort(404);
    }
    $base64 = substr($dataUri, strpos($dataUri, 'base64,') + 7);
    $binary = base64_decode($base64, true);
    if ($binary === false) {
        abort(404);
    }
    return response($binary, 200, [
        'Content-Type' => 'image/png',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->name('lp.favicon_logo');

/*
|--------------------------------------------------------------------------
| Installer routes (when app is not installed)
|--------------------------------------------------------------------------
*/
Route::middleware('redirect.if.installed')
    ->prefix('install')
    ->name('install.')
    ->group(function () {
        Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
        Route::get('/environment', [InstallController::class, 'environment'])->name('environment');
        Route::get('/purchase', [InstallController::class, 'purchase'])->name('purchase');
        Route::post('/purchase', [InstallController::class, 'verifyPurchase'])->name('purchase.verify');
        Route::get('/database', [InstallController::class, 'database'])->name('database');
        Route::post('/database', [InstallController::class, 'storeDatabase'])->name('database.store');
        Route::get('/complete', [InstallController::class, 'complete'])->name('complete');
});

/*
|--------------------------------------------------------------------------
| Application routes (only when app is installed)
|--------------------------------------------------------------------------
*/
Route::middleware(['redirect.if.not.installed', 'verify.installation.integrity'])->group(function () {
    // PWA (public, no auth required)
    Route::get('pwa/manifest.json', [PwaController::class, 'manifest'])->name('pwa.manifest');
    Route::get('pwa/serviceworker.js', [PwaController::class, 'serviceWorker'])->name('pwa.serviceworker');

    require __DIR__.'/auth.php';
    require __DIR__.'/backend.php';



    Route::get('/', function () {
        return redirect()->route('login');
    })->middleware('guest');

    Route::get('lang/{locale}', [LanguageController::class, 'switch'])
        ->name('language.switch');
});

// Uninstall license (when app is installed, auth required)
Route::middleware('auth')->group(function () {
    Route::get('uninstall-license', [InstallController::class, 'uninstall'])->name('uninstall.license');
    Route::post('uninstall-license', [InstallController::class, 'uninstallLicense'])->name('uninstall.submit');
});

/*
|--------------------------------------------------------------------------
| Update routes (when app is installed, auth required)
|--------------------------------------------------------------------------
*/
Route::middleware(['redirect.if.not.installed', 'verify.installation.integrity', 'auth'])
    ->prefix('update')
    ->name('update.')
    ->group(function () {
        Route::get('verification', [UpdateController::class, 'updateVerification'])->name('verification');
        Route::post('verification', [UpdateController::class, 'verifyUpdate'])->name('verify');
        Route::get('/', [UpdateController::class, 'index'])->name('index');
        Route::post('do-update', [UpdateController::class, 'doUpdate'])->name('do');
        Route::post('install', [UpdateController::class, 'installUpdate'])->name('install');
        Route::get('upgrade-license', [UpdateController::class, 'upgradeLicense'])->name('upgrade-license');
        Route::post('upgrade-license', [UpdateController::class, 'submitUpgradeLicense'])->name('upgrade.submit');
    });

