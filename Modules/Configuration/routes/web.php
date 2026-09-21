<?php

use Illuminate\Support\Facades\Route;
use Modules\Configuration\Http\Controllers\ConfigurationController;
use Modules\Configuration\Http\Controllers\DenominationController;
use Modules\Configuration\Http\Controllers\CounterController;
use Modules\Configuration\Http\Controllers\OutletController;
use Modules\Configuration\Http\Controllers\PrinterController;
use Modules\Configuration\Http\Controllers\MultipleCurrencyController;
use Modules\Configuration\Http\Controllers\DeliveryPartnerController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('configurations', ConfigurationController::class)->only(['index'])->names('configuration');

    // Denomination CRUD Routes
    Route::controller(DenominationController::class)->group(function () {
        Route::get('denomination', 'index')->name('denomination.index')->middleware('permission:denomination-list');
        Route::get('denomination/create', 'create')->name('denomination.create')->middleware('permission:denomination-create');
        Route::post('denomination', 'store')->name('denomination.store')->middleware('permission:denomination-create');
        Route::get('denomination/{denomination}', 'show')->name('denomination.show')->middleware('permission:denomination-show');
        Route::get('denomination/{denomination}/edit', 'edit')->name('denomination.edit')->middleware('permission:denomination-edit');
        Route::put('denomination/{denomination}', 'update')->name('denomination.update')->middleware('permission:denomination-edit');
        Route::delete('denomination/{denomination}', 'destroy')->name('denomination.destroy')->middleware('permission:denomination-destroy');
    });

    // Counter CRUD Routes
    Route::controller(CounterController::class)->group(function () {
        Route::get('counter', 'index')->name('counter.index')->middleware('permission:counter-list');
        Route::get('counter/create', 'create')->name('counter.create')->middleware('permission:counter-create');
        Route::post('counter', 'store')->name('counter.store')->middleware('permission:counter-create');
        Route::get('counter/{counter}', 'show')->name('counter.show')->middleware('permission:counter-show');
        Route::get('counter/{counter}/edit', 'edit')->name('counter.edit')->middleware('permission:counter-edit');
        Route::put('counter/{counter}', 'update')->name('counter.update')->middleware('permission:counter-edit');
        Route::delete('counter/{counter}', 'destroy')->name('counter.destroy')->middleware('permission:counter-destroy');
    });

    // Outlet CRUD Routes
    Route::controller(OutletController::class)->group(function () {
        Route::get('outlet', 'index')->name('outlet.index')->middleware('permission:outlet-list');
        Route::get('outlet/create', 'create')->name('outlet.create')->middleware('permission:outlet-create');
        Route::post('outlet', 'store')->name('outlet.store')->middleware('permission:outlet-create');
        Route::get('outlet/{id}', 'show')->name('outlet.show')->middleware('permission:outlet-show');
        Route::get('outlet/{id}/edit', 'edit')->name('outlet.edit')->middleware('permission:outlet-edit');
        Route::put('outlet/{id}', 'update')->name('outlet.update')->middleware('permission:outlet-edit');
        Route::delete('outlet/{id}', 'destroy')->name('outlet.destroy')->middleware('permission:outlet-destroy');
        Route::get('outlet/{id}/enter', 'enter')->name('outlet.enter')->middleware('permission:outlet-enter');
    });

    // Printer CRUD Routes
    Route::controller(PrinterController::class)->group(function () {
        Route::get('printer', 'index')->name('printer.index')->middleware('permission:printer-list');
        Route::get('printer/create', 'create')->name('printer.create')->middleware('permission:printer-create');
        Route::post('printer', 'store')->name('printer.store')->middleware('permission:printer-create');
        Route::get('printer/{printer}', 'show')->name('printer.show')->middleware('permission:printer-show');
        Route::get('printer/{printer}/edit', 'edit')->name('printer.edit')->middleware('permission:printer-edit');
        Route::put('printer/{printer}', 'update')->name('printer.update')->middleware('permission:printer-edit');
        Route::delete('printer/{printer}', 'destroy')->name('printer.destroy')->middleware('permission:printer-destroy');
    });

    // Multiple Currency CRUD Routes
    Route::controller(MultipleCurrencyController::class)->group(function () {
        Route::get('multiple-currency', 'index')->name('multiple-currency.index')->middleware('permission:multiple_currency-list');
        Route::get('multiple-currency/create', 'create')->name('multiple-currency.create')->middleware('permission:multiple_currency-create');
        Route::post('multiple-currency', 'store')->name('multiple-currency.store')->middleware('permission:multiple_currency-create');
        Route::get('multiple-currency/{multiple_currency}', 'show')->name('multiple-currency.show')->middleware('permission:multiple_currency-show');
        Route::get('multiple-currency/{multiple_currency}/edit', 'edit')->name('multiple-currency.edit')->middleware('permission:multiple_currency-edit');
        Route::put('multiple-currency/{multiple_currency}', 'update')->name('multiple-currency.update')->middleware('permission:multiple_currency-edit');
        Route::delete('multiple-currency/{multiple_currency}', 'destroy')->name('multiple-currency.destroy')->middleware('permission:multiple_currency-destroy');
    });

    // Delivery Partner CRUD Routes
    Route::controller(DeliveryPartnerController::class)->group(function () {
        Route::get('delivery-partner', 'index')->name('delivery-partner.index')->middleware('permission:delivery_partner-list');
        Route::get('delivery-partner/create', 'create')->name('delivery-partner.create')->middleware('permission:delivery_partner-create');
        Route::post('delivery-partner', 'store')->name('delivery-partner.store')->middleware('permission:delivery_partner-create');
        Route::get('delivery-partner/{delivery_partner}', 'show')->name('delivery-partner.show')->middleware('permission:delivery_partner-show');
        Route::get('delivery-partner/{delivery_partner}/edit', 'edit')->name('delivery-partner.edit')->middleware('permission:delivery_partner-edit');
        Route::put('delivery-partner/{delivery_partner}', 'update')->name('delivery-partner.update')->middleware('permission:delivery_partner-edit');
        Route::delete('delivery-partner/{delivery_partner}', 'destroy')->name('delivery-partner.destroy')->middleware('permission:delivery_partner-destroy');
    });
});
