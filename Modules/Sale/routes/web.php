<?php

use Illuminate\Support\Facades\Route;
use Modules\Sale\Http\Controllers\CustomerController;
use Modules\Sale\Http\Controllers\CustomerReceiveController;
use Modules\Sale\Http\Controllers\InstallmentCustomerController;
use Modules\Sale\Http\Controllers\InstallmentSaleController;
use Modules\Sale\Http\Controllers\PromotionController;
use Modules\Sale\Http\Controllers\QuotationController;
use Modules\Sale\Http\Controllers\BookingController;
use Modules\Sale\Http\Controllers\POS\POSController;
use Modules\Sale\Http\Controllers\SaleController;
use Modules\Sale\Http\Controllers\SaleReturnController;
use Modules\Sale\Http\Controllers\RegisterController;
use Modules\Sale\Http\Controllers\ServicingController;
use Modules\Sale\Http\Controllers\WarrantyController;
use Modules\Sale\Http\Controllers\PrintServerController;

// Booking Routes
Route::controller(BookingController::class)->group(function () {
    Route::get('booking', 'index')->name('booking.index')->middleware('permission:booking-list');
    Route::get('booking/get-bookings', 'getBookings')->name('booking.get-bookings')->middleware('permission:booking-list');
    Route::get('booking/get-booking-list', 'getBookingList')->name('booking.get-booking-list')->middleware('permission:booking-list');
    Route::post('booking', 'store')->name('booking.store')->middleware('permission:booking-create');
    Route::put('booking/{id}', 'update')->name('booking.update')->middleware('permission:booking-edit');
    Route::delete('booking/{id}', 'destroy')->name('booking.destroy')->middleware('permission:booking-destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Print server: get content_data and printer URL for live receipt print
    Route::post('call-print-server', [PrintServerController::class, 'callPrintServer'])
        ->name('call-print-server')
        ->middleware(['outlet_set', 'register_open']);
    // Print server endpoint: receive content_data and send to printer (same app or CORS-enabled)
    Route::post('print-server/receive', [PrintServerController::class, 'receivePrint'])
        ->name('print-server.receive');

    // Register Routes
    Route::controller(RegisterController::class)->group(function () {
        Route::get('register/create', 'create')->name('register.create');
        Route::get('register/check-status', 'checkStatus')->name('register.check-status');
        Route::get('register/form-data', 'getFormData')->name('register.form-data');
        Route::get('register/summary', 'getRegisterSummary')->name('register.summary');
        Route::post('register/open', 'open')->name('register.open');
        Route::post('register/close', 'close')->name('register.close');
    });

    // POS Customer AJAX Routes (must be before general POS route to avoid conflicts)
    Route::controller(CustomerController::class)->group(function () {
        Route::post('pos/customer', 'posStore')->name('pos.customer.store')->middleware(['permission:customer-create']);
        Route::put('pos/customer/{id}', 'posUpdate')->name('pos.customer.update')->middleware(['permission:customer-edit']);
        Route::get('pos/get-customer/{id}', 'posGetCustomer')->name('pos.customer.get');
        Route::get('pos/customers', 'posGetCustomers')->name('pos.customers.get');
        Route::get('pos/customer/{id}/credit-info', 'posGetCustomerCreditInfo')->name('pos.customer.credit-info');
        Route::get('pos/lookup-customer-by-phone', 'posLookupByPhone')->name('pos.customer.lookup-phone');
    });
    
    // POS Routes
    Route::controller(POSController::class)->group(function () {
        Route::get('pos', 'index')->name('pos.index')->middleware(['permission:sale-pos', 'outlet_set', 'register_open']);
        Route::get('pos/busy', 'busyMode')->name('pos.busy')->middleware(['permission:sale-pos', 'outlet_set', 'register_open']);
        Route::get('pos/customer-display', 'customerDisplay')->name('pos.customer-display');
        Route::get('pos/products/batch', 'getProductsBatch')->name('pos.products.batch');
        Route::get('pos/products/{productId}/variations', 'getProductVariations')->name('pos.products.variations');
        Route::get('pos/sale/{saleId}/zatca-status', 'getZatcaStatus')->name('pos.sale.zatca-status');
        Route::post('pos/sale/{saleId}/zatca-retry', 'retryZatcaSubmission')->name('pos.sale.zatca-retry');
        Route::post('pos/payment-gateway/initialize', 'initializeGatewayPayment')->name('pos.payment.gateway.initialize');
        Route::post('pos/sale', 'saveSale')->name('pos.sale.save')->middleware(['permission:sale-create', 'outlet_set', 'register_open']);
        Route::get('pos/sale/{encryptedId}/for-edit', 'getSaleForEdit')->name('pos.sale.for-edit')->middleware(['permission:sale-edit', 'outlet_set', 'register_open']);
        Route::get('pos/hold/next-hold-no', 'getNextHoldNo')->name('pos.hold.next-hold-no');
        Route::post('pos/hold', 'saveHold')->name('pos.hold.save');
        Route::get('pos/holds', 'getHolds')->name('pos.holds.get');
        Route::get('pos/hold/{id}', 'getHold')->name('pos.hold.get');
        Route::get('pos/hold/{id}/convert', 'convertHoldToSale')->name('pos.hold.convert');
        Route::delete('pos/hold/{id}', 'deleteHold')->name('pos.hold.delete');
        Route::get('pos/last-sale', 'getLastSale')->name('pos.last-sale.get')->middleware(['permission:sale-pos', 'outlet_set', 'register_open']);
        
        // PayPal payment callbacks
        Route::get('pos/payment/paypal/return', 'handlePayPalReturn')->name('pos.payment.paypal.return');
        Route::get('pos/payment/paypal/cancel', 'handlePayPalCancel')->name('pos.payment.paypal.cancel');
        Route::post('pos/payment/paypal/status', 'checkPayPalPaymentStatus')->name('pos.payment.paypal.status');
        
        // Paytm payment callbacks
        Route::post('pos/payment/paytm/callback', 'handlePaytmCallback')->name('pos.payment.paytm.callback');
        Route::get('pos/payment/paytm/return', 'handlePaytmReturn')->name('pos.payment.paytm.return');
        
        // Paystack payment callbacks
        Route::get('pos/payment/paystack/callback', 'handlePaystackCallback')->name('pos.payment.paystack.callback');
        Route::get('pos/payment/paystack/return', 'handlePaystackReturn')->name('pos.payment.paystack.return');
        
        // Flutterwave payment callbacks
        Route::get('pos/payment/flutterwave/callback', 'handleFlutterwaveCallback')->name('pos.payment.flutterwave.callback');
        Route::get('pos/payment/flutterwave/return', 'handleFlutterwaveReturn')->name('pos.payment.flutterwave.return');
        
        // MyFatoorah payment callbacks
        Route::get('pos/payment/myfatoorah/callback', 'handleMyFatoorahCallback')->name('pos.payment.myfatoorah.callback');
        Route::get('pos/payment/myfatoorah/return', 'handleMyFatoorahReturn')->name('pos.payment.myfatoorah.return');
        
        // Mpesa payment callbacks
        Route::post('pos/payment/mpesa/callback', 'handleMpesaCallback')->name('pos.payment.mpesa.callback');
        Route::get('pos/payment/mpesa/return', 'handleMpesaReturn')->name('pos.payment.mpesa.return');
    });
    
    // Sale CRUD Routes
    Route::controller(SaleController::class)->group(function () {
        Route::get('sale', 'index')->name('sale.index')->middleware(['permission:sale-list', 'outlet_set', 'register_open']);
        Route::get('sale/{sale}/show', 'show')->name('sale.show')->middleware(['permission:sale-show', 'outlet_set']);
        Route::get('sale/{sale}/edit', 'edit')->name('sale.edit')->middleware(['permission:sale-edit', 'outlet_set']);
        Route::put('sale/{sale}', [POSController::class, 'updateSale'])->name('sale.update')->middleware(['permission:sale-edit', 'outlet_set', 'register_open']);
        Route::delete('sale/{sale}', 'destroy')->name('sale.destroy')->middleware(['permission:sale-destroy', 'outlet_set']);
        Route::get('sale/{sale}/print', 'printInvoice')->name('sale.print-invoice')->middleware(['permission:sale-show', 'outlet_set']);
        Route::get('sale/{sale}/print-challan', 'printChallan')->name('sale.print-challan')->middleware(['permission:sale-show', 'outlet_set']);
        Route::get('sale/{sale}/pdf', 'generatePdf')->name('sale.generate-pdf')->middleware(['permission:sale-show', 'outlet_set']);
    });
    
    // Sale Return CRUD Routes
    Route::controller(SaleReturnController::class)->group(function () {
        // Static routes must come before dynamic routes to avoid conflicts
        Route::get('sale-return', 'index')->name('sale-return.index')->middleware(['permission:sale_return-list', 'outlet_set', 'register_open']);
        Route::get('sale-return/create', 'create')->name('sale-return.create')->middleware(['permission:sale_return-create', 'outlet_set', 'register_open']);
        Route::post('sale-return', 'store')->name('sale-return.store')->middleware(['permission:sale_return-create', 'outlet_set', 'register_open']);
        
        // AJAX Routes for Sale Return (must be before dynamic routes)
        Route::get('sale-return/get-customer-sale-invoices', 'getCustomerSaleInvoices')->name('sale-return.get-customer-sale-invoices');
        Route::get('sale-return/get-sale-invoice-items', 'getSaleInvoiceItems')->name('sale-return.get-sale-invoice-items');
        Route::get('sale-return/get-pos-form-data', 'getPosFormData')->name('sale-return.get-pos-form-data');
        
        // Dynamic routes (with parameters)
        Route::get('sale-return/{sale_return}', 'show')->name('sale-return.show')->middleware(['permission:sale_return-show', 'outlet_set']);
        Route::get('sale-return/{sale_return}/edit', 'edit')->name('sale-return.edit')->middleware(['permission:sale_return-edit', 'outlet_set']);
        Route::put('sale-return/{sale_return}', 'update')->name('sale-return.update')->middleware(['permission:sale_return-edit', 'outlet_set']);
        Route::delete('sale-return/{sale_return}', 'destroy')->name('sale-return.destroy')->middleware(['permission:sale_return-destroy', 'outlet_set']);
        Route::get('sale-return/{sale_return}/print', 'printInvoice')->name('sale-return.print-invoice')->middleware(['permission:sale_return-show', 'outlet_set']);
        Route::get('sale-return/{sale_return}/pdf', 'generatePdf')->name('sale-return.generate-pdf')->middleware(['permission:sale_return-show', 'outlet_set']);
    });
    
    // Installment Sale CRUD Routes
    Route::controller(InstallmentSaleController::class)->group(function () {
        // Static routes must come before dynamic routes to prevent conflicts
        Route::get('installment-sale', 'index')->name('installment-sale.index')->middleware(['permission:installment_sale-list', 'outlet_set', 'register_open']);
        Route::get('installment-sale/create', 'create')->name('installment-sale.create')->middleware(['permission:installment_sale-create', 'outlet_set', 'register_open']);
        Route::post('installment-sale', 'store')->name('installment-sale.store')->middleware(['permission:installment_sale-create', 'outlet_set', 'register_open']);
        Route::get('installment-collection', 'installmentCollection')->name('installment-collection.index')->middleware(['permission:installment_sale-list', 'outlet_set', 'register_open']);
        Route::get('installment-collection/get-customer-installments', 'getCustomerInstallments')->name('installment-collection.get-customer-installments')->middleware(['permission:installment_sale-list', 'outlet_set', 'register_open']);
        Route::get('installment-collection/get-installment-items', 'getInstallmentItems')->name('installment-collection.get-installment-items')->middleware(['permission:installment_sale-list', 'outlet_set', 'register_open']);
        Route::get('installment-collection/get-due-installments', 'getDueInstallments')->name('installment-collection.get-due-installments')->middleware(['permission:installment_sale-list', 'outlet_set', 'register_open']);
        Route::get('installment-collection/payment/{detail_id}', 'showPaymentPage')->name('installment-collection.payment')->middleware(['permission:installment_sale-edit', 'outlet_set', 'register_open']);
        Route::post('installment-collection/send-due-notifications', 'sendDueNotifications')->name('installment-collection.send-due-notifications')->middleware(['permission:installment_sale-edit', 'outlet_set', 'register_open']);
        Route::get('due-installment', 'dueInstallment')->name('due-installment.index')->middleware(['permission:installment_sale-list', 'outlet_set']);

        
        // AJAX Routes for Installment Sale (must be before dynamic routes)
        Route::get('installment-sale/get-item-details', 'getItemDetails')->name('installment-sale.get-item-details');
        Route::post('installment-sale/get-imei-serial', 'getImeiSerial')->name('installment-sale.get-imei-serial');
        Route::get('installment-sale/overdue-installments', 'overdueInstallments')->name('installment-sale.overdue-installments');
        Route::get('installment-sale/statistics', 'statistics')->name('installment-sale.statistics');
        Route::post('installment-sale/record-payment/{detail_id}', 'recordPayment')->name('installment-sale.record-payment');
        
        // Dynamic routes (with parameters) - using {id} to avoid model binding since we use encrypted IDs
        Route::get('installment-sale/{id}', 'show')->name('installment-sale.show')->middleware(['permission:installment_sale-show', 'outlet_set']);
        Route::get('installment-sale/{id}/edit', 'edit')->name('installment-sale.edit')->middleware(['permission:installment_sale-edit', 'outlet_set']);
        Route::get('installment-sale/{id}/print', 'printInvoice')->name('installment-sale.print')->middleware(['permission:installment_sale-show', 'outlet_set']);
        Route::get('installment-sale/{id}/pdf', 'generatePdf')->name('installment-sale.pdf')->middleware(['permission:installment_sale-show', 'outlet_set']);
        Route::put('installment-sale/{id}', 'update')->name('installment-sale.update')->middleware(['permission:installment_sale-edit', 'outlet_set']);
        Route::delete('installment-sale/{id}', 'destroy')->name('installment-sale.destroy')->middleware(['permission:installment_sale-destroy', 'outlet_set']);
    });
    // Customer CRUD Routes
    Route::controller(CustomerController::class)->group(function () {
        Route::get('customer', 'index')->name('customer.index')->middleware('permission:customer-list');
        Route::get('customer/create', 'create')->name('customer.create')->middleware('permission:customer-create');
        Route::post('customer', 'store')->name('customer.store')->middleware('permission:customer-create');
        Route::get('customer/{customer}', 'show')->name('customer.show')->middleware('permission:customer-show');
        Route::get('customer/{customer}/edit', 'edit')->name('customer.edit')->middleware('permission:customer-edit');
        Route::put('customer/{customer}', 'update')->name('customer.update')->middleware('permission:customer-edit');
        Route::delete('customer/{customer}', 'destroy')->name('customer.destroy')->middleware('permission:customer-destroy');
        Route::get('customer/{customer}/balance', 'getBalance')->name('customer.get-balance');
        Route::get('customer/get-balance-by-id/{id}', 'getBalanceById')->name('customer.get-balance-by-id');
    });

    // Installment Customer CRUD Routes
    Route::controller(InstallmentCustomerController::class)->group(function () {
        Route::get('installment-customer', 'index')->name('installment-customer.index')->middleware('permission:customer-list');
        Route::get('installment-customer/create', 'create')->name('installment-customer.create')->middleware('permission:customer-create');
        Route::post('installment-customer', 'store')->name('installment-customer.store')->middleware('permission:customer-create'); 
        Route::get('installment-customer/{installment_customer}', 'show')->name('installment-customer.show')->middleware('permission:customer-show');
        Route::get('installment-customer/{installment_customer}/edit', 'edit')->name('installment-customer.edit')->middleware('permission:customer-edit');
        Route::put('installment-customer/{installment_customer}', 'update')->name('installment-customer.update')->middleware('permission:customer-edit');
        Route::delete('installment-customer/{installment_customer}', 'destroy')->name('installment-customer.destroy')->middleware('permission:customer-destroy');
    });

    // Customer Receive CRUD Routes
    Route::controller(CustomerReceiveController::class)->group(function () {
        Route::get('customer-receive', 'index')->name('customer-receive.index')->middleware(['permission:customer_receive-list', 'outlet_set', 'register_open']);
        Route::get('customer-receive/create', 'create')->name('customer-receive.create')->middleware(['permission:customer_receive-create', 'outlet_set', 'register_open']);
        Route::post('customer-receive', 'store')->name('customer-receive.store')->middleware(['permission:customer_receive-create', 'outlet_set', 'register_open']);
        Route::get('customer-receive/{customer_receive}', 'show')->name('customer-receive.show')->middleware('permission:customer_receive-show');
        Route::get('customer-receive/{customer_receive}/edit', 'edit')->name('customer-receive.edit')->middleware(['permission:customer_receive-edit', 'outlet_set', 'register_open']);
        Route::put('customer-receive/{customer_receive}', 'update')->name('customer-receive.update')->middleware(['permission:customer_receive-edit', 'outlet_set', 'register_open']);
        Route::delete('customer-receive/{customer_receive}', 'destroy')->name('customer-receive.destroy')->middleware(['permission:customer_receive-destroy', 'outlet_set', 'register_open']);
    });

    // Quotation CRUD Routes
    Route::controller(QuotationController::class)->group(function () {
        // Static routes must come before dynamic routes to prevent conflicts
        Route::get('quotation', 'index')->name('quotation.index')->middleware('permission:quotation-list');
        Route::get('quotation/create', 'create')->name('quotation.create')->middleware('permission:quotation-create');
        Route::post('quotation', 'store')->name('quotation.store')->middleware('permission:quotation-create');
        
        // AJAX Routes for Quotation (must be before dynamic routes)
        Route::get('quotation/variation-child-items', 'getVariationChildItems')->name('quotation.variation-child-items');
        
        // Dynamic routes (with parameters)
        Route::get('quotation/{quotation}', 'show')->name('quotation.show')->middleware('permission:quotation-show');
        Route::get('quotation/{quotation}/edit', 'edit')->name('quotation.edit')->middleware('permission:quotation-edit');
        Route::put('quotation/{quotation}', 'update')->name('quotation.update')->middleware('permission:quotation-edit');
        Route::delete('quotation/{quotation}', 'destroy')->name('quotation.destroy')->middleware('permission:quotation-destroy');
        Route::get('quotation/{quotation}/print', 'printInvoice')->name('quotation.print-invoice')->middleware('permission:quotation-show');
        Route::get('quotation/{quotation}/pdf', 'generatePdf')->name('quotation.generate-pdf')->middleware('permission:quotation-show');
    });

    // Promotion CRUD Routes
    Route::controller(PromotionController::class)->group(function () {
        Route::get('promotion', 'index')->name('promotion.index')->middleware(['permission:promotion-list', 'outlet_set']);
        Route::get('promotion/create', 'create')->name('promotion.create')->middleware(['permission:promotion-create', 'outlet_set']);
        Route::post('promotion', 'store')->name('promotion.store')->middleware(['permission:promotion-create', 'outlet_set']);
        Route::get('promotion/{promotion}', 'show')->name('promotion.show')->middleware(['permission:promotion-show', 'outlet_set']);
        Route::get('promotion/{promotion}/edit', 'edit')->name('promotion.edit')->middleware(['permission:promotion-edit', 'outlet_set']);
        Route::put('promotion/{promotion}', 'update')->name('promotion.update')->middleware(['permission:promotion-edit', 'outlet_set']);
        Route::delete('promotion/{promotion}', 'destroy')->name('promotion.destroy')->middleware(['permission:promotion-destroy', 'outlet_set']);
    });

    // Servicing CRUD Routes
    Route::controller(ServicingController::class)->group(function () {
        Route::get('servicing', 'index')->name('servicing.index')->middleware(['permission:servicing-list', 'outlet_set', 'register_open']);
        Route::get('servicing/create', 'create')->name('servicing.create')->middleware(['permission:servicing-create', 'outlet_set', 'register_open']);
        Route::post('servicing', 'store')->name('servicing.store')->middleware(['permission:servicing-create', 'outlet_set', 'register_open']);
        Route::get('servicing/{servicing}', 'show')->name('servicing.show')->middleware(['permission:servicing-show', 'outlet_set']);
        Route::get('servicing/{servicing}/edit', 'edit')->name('servicing.edit')->middleware(['permission:servicing-edit', 'outlet_set', 'register_open']);
        Route::put('servicing/{servicing}', 'update')->name('servicing.update')->middleware(['permission:servicing-edit', 'outlet_set', 'register_open']);
        Route::delete('servicing/{servicing}', 'destroy')->name('servicing.destroy')->middleware(['permission:servicing-destroy', 'outlet_set', 'register_open']);
    });

    // Warranty CRUD Routes
    Route::controller(WarrantyController::class)->group(function () {
        Route::get('warranty', 'index')->name('warranty.index')->middleware(['permission:warranty-list', 'outlet_set']);
        Route::get('warranty/create', 'create')->name('warranty.create')->middleware(['permission:warranty-create', 'outlet_set']);
        Route::post('warranty', 'store')->name('warranty.store')->middleware(['permission:warranty-create', 'outlet_set']);
        Route::get('warranty/search-product', 'searchProductByImeiSerial')->name('warranty.search-product')->middleware(['permission:warranty-checking', 'outlet_set']);
        Route::post('warranty/update-status', 'updateStatus')->name('warranty.update-status')->middleware(['permission:warranty-edit', 'outlet_set']);
        Route::get('warranty/checking', 'checking')->name('warranty.checking')->middleware(['permission:warranty-checking', 'outlet_set']);
        Route::get('warranty/checking/search', 'searchForWarranty')->name('warranty.checking.search')->middleware('permission:warranty-checking');
        Route::get('warranty/checking/invoice/{id}', 'warrantyInvoice')->name('warranty.checking.invoice')->middleware(['permission:warranty-checking', 'outlet_set']);
        Route::get('warranty/{warranty}', 'show')->name('warranty.show')->middleware(['permission:warranty-show', 'outlet_set']);
        Route::get('warranty/{warranty}/edit', 'edit')->name('warranty.edit')->middleware(['permission:warranty-edit', 'outlet_set']);
        Route::put('warranty/{warranty}', 'update')->name('warranty.update')->middleware(['permission:warranty-edit', 'outlet_set']);
        Route::delete('warranty/{warranty}', 'destroy')->name('warranty.destroy')->middleware(['permission:warranty-destroy', 'outlet_set']);
    });
});