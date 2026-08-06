<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchase\Http\Controllers\PurchaseController;
use Modules\Purchase\Http\Controllers\PurchaseReturnController;
use Modules\Purchase\Http\Controllers\SupplierController;
use Modules\Purchase\Http\Controllers\SupplierPaymentController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Purchase CRUD Routes
    Route::controller(PurchaseController::class)->group(function () {
        Route::get('purchase', 'index')->name('purchase.index')->middleware(['permission:purchase-list', 'outlet_set', 'register_open']);
        Route::get('purchase/create', 'create')->name('purchase.create')->middleware(['permission:purchase-create', 'outlet_set', 'register_open']);
        Route::post('purchase', 'store')->name('purchase.store')->middleware(['permission:purchase-create', 'outlet_set', 'register_open']);
        Route::get('purchase/{purchase}/show', 'show')->name('purchase.show')->middleware(['permission:purchase-show', 'outlet_set']);
        Route::get('purchase/{purchase}/edit', 'edit')->name('purchase.edit')->middleware(['permission:purchase-edit', 'outlet_set', 'register_open']);
        Route::put('purchase/{purchase}', 'update')->name('purchase.update')->middleware(['permission:purchase-edit', 'outlet_set', 'register_open']);
        Route::delete('purchase/{purchase}', 'destroy')->name('purchase.destroy')->middleware(['permission:purchase-destroy', 'outlet_set', 'register_open']);
        Route::get('purchase/{purchase}/print', 'printInvoice')->name('purchase.print-invoice')->middleware(['permission:purchase-show', 'outlet_set']);
        Route::get('purchase/{purchase}/pdf', 'generatePdf')->name('purchase.generate-pdf')->middleware(['permission:purchase-show', 'outlet_set']);
        Route::get('api/item-details', 'getItemDetails')->name('purchase.item-details');
        Route::get('api/variation-child-items', 'getVariationChildItems')->name('purchase.variation-child-items');
        
        // Purchase Payment Routes
        // Route::post('purchase/{purchase}/payment', 'addPayment')->name('purchase.payment.add')->middleware(['permission:payment-create', 'outlet_set']);
        // Route::post('purchase/{purchase}/payments', 'addMultiplePayments')->name('purchase.payments.add')->middleware(['permission:payment-create', 'outlet_set']);
        // Route::delete('purchase/payment/{payment}', 'deletePayment')->name('purchase.payment.delete')->middleware(['permission:payment-delete', 'outlet_set']);

        Route::post('purchase/check-stock-availability', 'checkStockAvailability')->name('purchase.check-stock-availability');
        Route::post('purchase/check-existing-imei-serial', 'checkExistingIMEISerial')->name('purchase.check-existing-imei-serial');

    });

    // Supplier CRUD Routes
    Route::controller(SupplierController::class)->group(function () {
        Route::get('supplier', 'index')->name('supplier.index')->middleware('permission:supplier-list');
        Route::get('supplier/create', 'create')->name('supplier.create')->middleware('permission:supplier-create');
        Route::post('supplier', 'store')->name('supplier.store')->middleware('permission:supplier-create');
        Route::get('supplier/{supplier}', 'show')->name('supplier.show')->middleware('permission:supplier-show');
        Route::get('supplier/{supplier}/edit', 'edit')->name('supplier.edit')->middleware('permission:supplier-edit');
        Route::put('supplier/{supplier}', 'update')->name('supplier.update')->middleware('permission:supplier-edit');
        Route::delete('supplier/{supplier}', 'destroy')->name('supplier.destroy')->middleware('permission:supplier-destroy');
        Route::get('supplier/get-balance-by-id/{id}', 'getBalanceById')->name('supplier.get-balance-by-id');
    });

    // Supplier Payment CRUD Routes
    Route::controller(SupplierPaymentController::class)->group(function () {
        Route::get('supplier-payment', 'index')->name('supplier-payment.index')->middleware(['permission:supplier_payment-list', 'outlet_set', 'register_open']);
        Route::get('supplier-payment/create', 'create')->name('supplier-payment.create')->middleware(['permission:supplier_payment-create', 'outlet_set', 'register_open']);
        Route::post('supplier-payment', 'store')->name('supplier-payment.store')->middleware(['permission:supplier_payment-create', 'outlet_set', 'register_open']);
        Route::get('supplier-payment/{supplier_payment}', 'show')->name('supplier-payment.show')->middleware(['permission:supplier_payment-show', 'outlet_set', 'register_open']);
        Route::get('supplier-payment/{supplier_payment}/edit', 'edit')->name('supplier-payment.edit')->middleware(['permission:supplier_payment-edit', 'outlet_set', 'register_open']);
        Route::put('supplier-payment/{supplier_payment}', 'update')->name('supplier-payment.update')->middleware(['permission:supplier_payment-edit', 'outlet_set', 'register_open']);
        Route::delete('supplier-payment/{supplier_payment}', 'destroy')->name('supplier-payment.destroy')->middleware(['permission:supplier_payment-destroy', 'outlet_set', 'register_open']);
    });

    // Purchase Return CRUD Routes
    Route::controller(PurchaseReturnController::class)->group(function () {
        Route::get('purchase-return', 'index')->name('purchase-return.index')->middleware(['permission:purchase_return-list', 'outlet_set', 'register_open']);
        Route::get('purchase-return/create', 'create')->name('purchase-return.create')->middleware(['permission:purchase_return-create', 'outlet_set', 'register_open']);
        Route::post('purchase-return', 'store')->name('purchase-return.store')->middleware(['permission:purchase_return-create', 'outlet_set', 'register_open']);
        Route::get('purchase-return/{purchase_return}/show', 'show')->name('purchase-return.show')->middleware(['permission:purchase_return-show', 'outlet_set']);
        Route::get('purchase-return/{purchase_return}/edit', 'edit')->name('purchase-return.edit')->middleware(['permission:purchase_return-edit', 'outlet_set', 'register_open']);
        Route::put('purchase-return/{purchase_return}', 'update')->name('purchase-return.update')->middleware(['permission:purchase_return-edit', 'outlet_set', 'register_open']);
        Route::delete('purchase-return/{purchase_return}', 'destroy')->name('purchase-return.destroy')->middleware(['permission:purchase_return-destroy', 'outlet_set', 'register_open']);
        Route::get('purchase-return/{purchase_return}/print', 'printInvoice')->name('purchase-return.print-invoice')->middleware(['permission:purchase_return-show', 'outlet_set']);
        Route::get('purchase-return/{purchase_return}/pdf', 'generatePdf')->name('purchase-return.generate-pdf')->middleware(['permission:purchase_return-show', 'outlet_set']);
        Route::get('api/purchase-return/item-details', 'getItemDetails')->name('purchase-return.item-details');
        Route::get('api/purchase-return/variation-child-items', 'getVariationChildItems')->name('purchase-return.variation-child-items');
        Route::get('api/purchase-return/item-current-stock', 'getItemCurrentStock')->name('purchase-return.item-current-stock');
    });
});
