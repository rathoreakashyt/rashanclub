<?php

use Illuminate\Support\Facades\Route;
use Modules\BusinessClub\Http\Controllers\BusinessClubDashboardController;
use Modules\BusinessClub\Http\Controllers\BusinessClubSettingsController;
use Modules\BusinessClub\Http\Controllers\WalletController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::controller(BusinessClubDashboardController::class)->group(function () {
        Route::get('business-club', 'index')->name('businessclub.dashboard')->middleware('permission:businessclub-list');
    });

    Route::controller(BusinessClubSettingsController::class)->group(function () {
        Route::get('business-club/settings', 'index')->name('businessclub.settings')->middleware('permission:businessclub-settings');
        Route::post('business-club/settings', 'update')->name('businessclub.settings.update')->middleware('permission:businessclub-settings');
    });

    Route::controller(WalletController::class)->group(function () {
        Route::get('business-club/wallets', 'index')->name('businessclub.wallets')->middleware('permission:businessclub-wallets');
        Route::get('business-club/wallets/{walletId}/transactions', 'transactions')->name('businessclub.wallets.transactions')->middleware('permission:businessclub-wallets');
        Route::get('business-club/partner/{customerId}/profile', 'profile')->name('businessclub.partner.profile')->middleware('permission:businessclub-wallets');
        Route::get('business-club/api/wallet-balance/{customerId}', 'getBalance')->name('businessclub.api.wallet-balance');
    });
});
