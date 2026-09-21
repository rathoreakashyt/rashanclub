<?php

use Illuminate\Support\Facades\Route;
use Modules\BusinessClub\Http\Controllers\BusinessClubDashboardController;
use Modules\BusinessClub\Http\Controllers\BusinessClubSettingsController;
use Modules\BusinessClub\Http\Controllers\WalletController;
use Modules\BusinessClub\Http\Controllers\MemberController;

Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::controller(BusinessClubDashboardController::class)->group(function () {
        Route::get('business-club', 'index')->name('businessclub.dashboard')->middleware('permission:businessclub-list');
    });

    // Settings
    Route::controller(BusinessClubSettingsController::class)->group(function () {
        Route::get('business-club/settings', 'index')->name('businessclub.settings')->middleware('permission:businessclub-settings');
        Route::post('business-club/settings', 'update')->name('businessclub.settings.update')->middleware('permission:businessclub-settings');
    });

    // Members (new controller)
    Route::controller(MemberController::class)->group(function () {
        Route::get('business-club/members', 'index')->name('businessclub.members')->middleware('permission:businessclub-wallets');
        Route::post('business-club/members/register', 'register')->name('businessclub.members.register')->middleware('permission:businessclub-wallets');
        Route::post('business-club/members/redeem', 'redeem')->name('businessclub.members.redeem')->middleware('permission:businessclub-wallets');
        Route::get('business-club/members/{memberId}/transactions', 'transactions')->name('businessclub.members.transactions')->middleware('permission:businessclub-wallets');
        Route::get('business-club/members/{customerId}/profile', 'profile')->name('businessclub.members.profile')->middleware('permission:businessclub-wallets');
        Route::get('business-club/api/check-membership/{customerId}', 'checkMembership')->name('businessclub.api.check-membership');
        Route::get('business-club/api/member-balance/{customerId}', 'getBalance')->name('businessclub.api.member-balance');
        Route::get('business-club/members/{customerId}/id-card', 'idCard')->name('businessclub.members.id-card')->middleware('permission:businessclub-wallets');
    });

    // Legacy wallet routes (backward compat - delegates to same new tables)
    Route::controller(WalletController::class)->group(function () {
        Route::get('business-club/wallets', 'index')->name('businessclub.wallets')->middleware('permission:businessclub-wallets');
        Route::get('business-club/wallets/{memberId}/transactions', 'transactions')->name('businessclub.wallets.transactions')->middleware('permission:businessclub-wallets');
        Route::get('business-club/partner/{customerId}/profile', 'profile')->name('businessclub.partner.profile')->middleware('permission:businessclub-wallets');
        Route::get('business-club/api/wallet-balance/{customerId}', 'getBalance')->name('businessclub.api.wallet-balance');
    });
});
