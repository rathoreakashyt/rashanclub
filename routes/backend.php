<?php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\DamageController;
use App\Http\Controllers\Backend\MasterController;
use App\Http\Controllers\Backend\SearchController;
use App\Http\Controllers\Backend\ProfileController;
use App\Http\Controllers\PaymentGatewayController;
use Modules\Report\Http\Controllers\DashboardController;
use Modules\Configuration\Http\Controllers\SettingController;
use Modules\Configuration\Http\Controllers\TaxSettingMigrationController;
use Modules\Configuration\Http\Controllers\EmailMarketingController;
use Modules\Configuration\Http\Controllers\SmsMarketingController;
use Modules\Configuration\Http\Controllers\WhatsAppMarketingController;

Route::middleware(['auth'])->group(function () {

    Route::get('demo-mode-restricted', function () {
        return view('errors.demo_mode_restricted');
    })->name('demo-mode.restricted');

    // User Home Routes
    Route::controller(DashboardController::class)->group(function () {
        Route::get('user-sales', 'getUserSales')->name('user.sales');
        Route::get('/user-home', 'userHome')->name('userhome');
        Route::get('this-week-statistics', 'getThisWeekStatistics')->name('this.week.statistics');
    });
    
    // Search Route
    Route::get('search-menu-data', [SearchController::class, 'getSearchData'])->name('search.menu.data');

    // Master Operation Routes
    Route::controller(MasterController::class)->group(function () {
        Route::post('store-supplier', 'storeSupplier')->name('store.supplier');
        Route::get('get-suppliers', 'getSuppliers')->name('get.suppliers');
        Route::post('store-brand', 'storeBrand')->name('store.brand');
        Route::get('get-brands', 'getBrands')->name('get.brands');
        Route::post('store-category', 'storeCategory')->name('store.category');
        Route::get('get-categories', 'getCategories')->name('get.categories');
        Route::post('store-rack', 'storeRack')->name('store.rack');
        Route::get('get-racks', 'getRacks')->name('get.racks');
        Route::post('store-unit', 'storeUnit')->name('store.unit');
        Route::get('get-units', 'getUnits')->name('get.units');
        Route::get('get-variations', 'getVariations')->name('get.variations');
    });
    Route::controller(SettingController::class)->group(function () {
        Route::get('setting/{tab?}', 'setting')->name('setting');
        Route::post('business-setting', 'businessSetting')->name('business.setting');
        Route::post('pos-setting', 'posSetting')->name('pos.setting');
        Route::post('pos-layout', 'posLayout')->name('pos.layout');
        Route::post('tax-setting', 'taxSetting')->name('tax.setting');
        Route::post('tax-setting-migrate', [TaxSettingMigrationController::class, 'migrate'])->name('tax.setting.migrate');
        Route::post('tax', 'storeTax')->name('tax.store');
        Route::put('tax/{id}', 'updateTax')->name('tax.update');
        Route::delete('tax/{id}', 'deleteTax')->name('tax.delete');
        Route::post('invoice-setting', 'invoiceSetting')->name('invoice.setting');
        Route::post('email-setting', 'emailSetting')->name('email.setting');
        Route::post('test-email', 'testEmail')->name('test.email');
        Route::post('test-sms', 'testSMS')->name('test.sms');
        Route::post('test-whatsapp', 'testWhatsapp')->name('test.whatsapp');
        Route::post('whitelabel-setting', 'whitelabelSetting')->name('whitelabel.setting');
        Route::post('pwa-setting', 'pwaSetting')->name('pwa.setting');
        Route::post('whatsapp-setting', 'whatsappSetting')->name('whatsapp.setting');
        Route::post('payment-setting', 'paymentSetting')->name('payment.setting');
        Route::post('sms-setting', 'smsSetting')->name('sms.setting');
        Route::post('zatca-setting', 'zatcaSetting')->name('zatca.setting');
        Route::post('zatca-generate-directory', 'generateZatcaDirectory')->name('zatca.generate.directory');
        Route::post('zatca-generate-csr', 'generateCsr')->name('zatca.generate.csr');
        Route::get('zatca-download/{file}', 'downloadZatcaFile')->name('zatca.download');
        // Marketing Route
        Route::get('email-marketing', 'emailMarketing')->name('email.marketing')->middleware('permission:marketing-email');
        Route::get('sms-marketing', 'smsMarketing')->name('sms.marketing')->middleware('permission:marketing-sms');
        Route::get('whatsapp-marketing', 'whatsappMarketing')->name('whatsapp.marketing')->middleware('permission:marketing-whatsapp');
    });

    // Email Marketing API (stats, recipients, send)
    Route::prefix('marketing/email')->middleware('permission:marketing-email')->group(function () {
        Route::controller(EmailMarketingController::class)->group(function () {
            Route::get('stats', 'stats')->name('marketing.email.stats');
            Route::get('recipients', 'recipients')->name('marketing.email.recipients');
            Route::get('recommended-message/{type}', 'recommendedMessage')->name('marketing.email.recommended');
            Route::post('send/birthday', 'sendBirthday')->name('marketing.email.send.birthday');
            Route::post('send/anniversary', 'sendAnniversary')->name('marketing.email.send.anniversary');
            Route::post('send/custom', 'sendCustom')->name('marketing.email.send.custom');
        });
    });

    // SMS Marketing API (stats, recipients, send)
    Route::prefix('marketing/sms')->middleware('permission:marketing-sms')->group(function () {
        Route::controller(SmsMarketingController::class)->group(function () {
            Route::get('stats', 'stats')->name('marketing.sms.stats');
            Route::get('recipients', 'recipients')->name('marketing.sms.recipients');
            Route::get('recommended-message/{type}', 'recommendedMessage')->name('marketing.sms.recommended');
            Route::post('send/birthday', 'sendBirthday')->name('marketing.sms.send.birthday');
            Route::post('send/anniversary', 'sendAnniversary')->name('marketing.sms.send.anniversary');
            Route::post('send/custom', 'sendCustom')->name('marketing.sms.send.custom');
        });
    });

    // WhatsApp Marketing API (stats, recipients, send)
    Route::prefix('marketing/whatsapp')->middleware('permission:marketing-whatsapp')->group(function () {
        Route::controller(WhatsAppMarketingController::class)->group(function () {
            Route::get('stats', 'stats')->name('marketing.whatsapp.stats');
            Route::get('recipients', 'recipients')->name('marketing.whatsapp.recipients');
            Route::get('recommended-message/{type}', 'recommendedMessage')->name('marketing.whatsapp.recommended');
            Route::post('send/birthday', 'sendBirthday')->name('marketing.whatsapp.send.birthday');
            Route::post('send/anniversary', 'sendAnniversary')->name('marketing.whatsapp.send.anniversary');
            Route::post('send/custom', 'sendCustom')->name('marketing.whatsapp.send.custom');
        });
    });

    // Cache clear routes (via browser URL - requires auth)
    Route::prefix('cache')->name('cache.')->group(function () {
        Route::get('clear-software', function () {
            Artisan::call('cache:clear-software');
            $output = trim(Artisan::output());
            return response()->view('backend.cache-result', [
                'title' => 'Software Cache Cleared',
                'message' => 'Laravel cache (config, view, route, app) has been cleared.',
                'output' => $output,
            ]);
        })->name('clear-software');
        Route::get('clear-pwa', function () {
            Artisan::call('cache:clear-pwa');
            $output = trim(Artisan::output());
            return response()->view('backend.cache-result', [
                'title' => 'PWA Cache Cleared',
                'message' => 'Service worker cache version bumped. Close all app tabs and reopen, or hard refresh (Ctrl+Shift+R).',
                'output' => $output,
            ]);
        })->name('clear-pwa');
        Route::get('generate-key', function () {
            Artisan::call('app:generate-key');
            $output = trim(Artisan::output());
            return response()->view('backend.cache-result', [
                'title' => 'Application Key Generated',
                'message' => 'APP_KEY has been generated and written to .env',
                'output' => $output,
            ]);
        })->name('generate-key');
    });

    // Payment Gateway Routes
    Route::prefix('payment-gateway')->name('payment.gateway.')->group(function () {
        Route::controller(PaymentGatewayController::class)->group(function () {
            Route::post('initialize', 'initializePayment')->name('initialize');
            Route::post('{gateway}/verify', 'verifyPayment')->name('verify');
            Route::get('{gateway}/status', 'getPaymentStatus')->name('status');
            Route::post('{gateway}/refund', 'processRefund')->name('refund');
            Route::get('available', 'getAvailableGateways')->name('available');
            Route::get('{gateway}/config', 'getGatewayConfig')->name('config');
        });
    });

});

Route::get('refresh-stock-view', function () {
    try {
        DB::statement("TRUNCATE TABLE view_stock_detail");
        DB::statement("INSERT INTO view_stock_detail SELECT item_id, 1 AS type, quantity_amount AS stock_quantity, outlet_id, company_id, del_status FROM purchase_details WHERE del_status = 'Live' AND quantity_amount > 0 UNION ALL SELECT item_id, 2 AS type, qty AS stock_quantity, outlet_id, company_id, del_status FROM sale_details WHERE del_status = 'Live' AND qty > 0 UNION ALL SELECT item_id, 1 AS type, stock_quantity, outlet_id, company_id, 'Live' AS del_status FROM set_opening_stocks WHERE stock_quantity > 0");
        return response()->json(['status' => 'success', 'message' => 'Stock view refreshed successfully']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
})->middleware(['auth'])->name('refresh.stock.view');


