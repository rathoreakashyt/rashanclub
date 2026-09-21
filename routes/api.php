<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GstController;
use App\Http\Controllers\Api\SendBillController;
use App\Http\Controllers\Api\DesktopController;
use App\Http\Controllers\Api\SyncController;

/*
|--------------------------------------------------------------------------
| API Routes (RashanKiDukan WPF Desktop App)
|--------------------------------------------------------------------------
| Offline-first two-way sync endpoints consumed by the WPF POS software.
| All routes except login require a Sanctum Bearer token.
*/

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('api.auth.login');
    Route::post('logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('api.auth.logout');
    Route::get('me', [AuthController::class, 'me'])
        ->middleware('auth:sanctum')
        ->name('api.auth.me');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('health', [SyncController::class, 'health'])->name('api.health');
    Route::get('meta', [SyncController::class, 'meta'])->name('api.meta');
    Route::get('sync/pull', [SyncController::class, 'pull'])->name('api.sync.pull');
    Route::post('sync/push', [SyncController::class, 'push'])->name('api.sync.push');
    Route::post('sync/push-entity', [SyncController::class, 'pushEntity'])->name('api.sync.push.entity');
    Route::post('sync/push/batch', [SyncController::class, 'pushBatch'])->name('api.sync.push.batch');
    Route::get('sync/dead-letters', [SyncController::class, 'deadLetters'])->name('api.sync.dead.letters');
    Route::get('live-status', [SyncController::class, 'liveStatus'])->name('api.live.status');
    Route::get('send-bill/settings', [SendBillController::class, 'settings'])->name('api.sendbill.settings');
    Route::post('send-bill', [SendBillController::class, 'send'])->name('api.sendbill.send');

    // Feature Activation sync
    Route::get('feature-activations', function (\Illuminate\Http\Request $request) {
        $companyId = $request->user()->company_id ?? 1;
        return response()->json([
            'features' => \App\Models\FeatureActivation::getAllStatus($companyId),
        ]);
    });
    Route::put('feature-activations', function (\Illuminate\Http\Request $request) {
        $companyId = $request->user()->company_id ?? 1;
        $updates = $request->input('features', []);
        foreach ($updates as $item) {
            \App\Models\FeatureActivation::where('company_id', $companyId)
                ->where('feature_key', $item['feature_key'])
                ->update(['is_active' => (bool) $item['is_active']]);
        }
        \App\Models\FeatureActivation::clearCache($companyId);
        return response()->json(['status' => 'ok', 'features' => \App\Models\FeatureActivation::getAllStatus($companyId)]);
    });

    // Business Club membership check
    Route::get('business-club/check-membership/{id}', function (\Illuminate\Http\Request $request, $id) {
        $companyId = $request->user()->company_id ?? 1;
        $member = \App\Models\BusinessClubMember::where('customer_id', $id)
            ->where('company_id', $companyId)->where('status', 'active')->where('del_status', 'Live')->first();
        return response()->json([
            'is_member' => (bool) $member,
            'member' => $member ? $member->toArray() : null,
        ]);
    });

    // Legacy route — desktop app uses this with token
    Route::get('gst-lookup/{gstin}', [GstController::class, 'validateGstin'])->name('api.gst.lookup');

    // ═══ Desktop-specific APIs (reports, marketing, import, bulk update) ═══
    Route::prefix('desktop')->group(function () {
        // Dashboard
        Route::get('dashboard', [DesktopController::class, 'dashboard']);
        Route::get('company-profile', [DesktopController::class, 'companyProfile']);

        // Reports
        Route::get('sales-report', [DesktopController::class, 'salesReport']);
        Route::get('purchase-report', [DesktopController::class, 'purchaseReport']);
        Route::get('stock-report', [DesktopController::class, 'stockReport']);
        Route::get('expense-report', [DesktopController::class, 'expenseReport']);
        Route::get('income-report', [DesktopController::class, 'incomeReport']);
        Route::get('due-report', [DesktopController::class, 'dueReport']);
        Route::get('employee-sale-report', [DesktopController::class, 'employeeSaleReport']);
        Route::get('z-report', [DesktopController::class, 'zReport']);
        Route::get('daily-summary', [DesktopController::class, 'dailySummary']);

        // Marketing
        Route::post('marketing/sms', [DesktopController::class, 'sendSms']);
        Route::post('marketing/whatsapp', [DesktopController::class, 'sendWhatsApp']);
        Route::post('marketing/email', [DesktopController::class, 'sendEmail']);
        Route::post('marketing/bulk-sms', [DesktopController::class, 'bulkSms']);
        Route::get('marketing/logs', [DesktopController::class, 'marketingLogs']);

        // Item Import & Bulk Update
        Route::post('items/import', [DesktopController::class, 'importItems']);
        Route::post('items/bulk-update', [DesktopController::class, 'bulkUpdateItems']);

        // Detailed Reports (6 enhanced variants)
        Route::get('detailed-sale-report', [DesktopController::class, 'detailedSaleReport']);
        Route::get('item-tracking-report', [DesktopController::class, 'itemTrackingReport']);
        Route::get('price-history-report', [DesktopController::class, 'priceHistoryReport']);
        Route::get('detailed-cash-flow-report', [DesktopController::class, 'detailedCashFlowReport']);
        Route::get('loyalty-point-report', [DesktopController::class, 'loyaltyPointReport']);
        Route::get('scheme-report', [DesktopController::class, 'schemeReport']);

        // Barcode Print
        Route::get('barcode-data', [DesktopController::class, 'barcodeData']);

        // Edit / Delete
        Route::delete('sales/{id}', [DesktopController::class, 'deleteSale']);
        Route::delete('purchases/{id}', [DesktopController::class, 'deletePurchase']);
        Route::delete('sale-returns/{id}', [DesktopController::class, 'deleteSaleReturn']);
        Route::delete('purchase-returns/{id}', [DesktopController::class, 'deletePurchaseReturn']);
        Route::delete('expenses/{id}', [DesktopController::class, 'deleteExpense']);
        Route::delete('incomes/{id}', [DesktopController::class, 'deleteIncome']);

        // Show / Detail
        Route::get('sales/{id}', [DesktopController::class, 'showSale']);
        Route::get('purchases/{id}', [DesktopController::class, 'showPurchase']);
        Route::get('customers/{id}', [DesktopController::class, 'showCustomer']);
        Route::get('suppliers/{id}', [DesktopController::class, 'showSupplier']);

        // Stock Segmentation
        Route::get('stock-segmentation', [DesktopController::class, 'stockSegmentation']);

        // Wallet Transactions
        Route::get('wallet/transactions', [DesktopController::class, 'walletTransactions']);

        // Overdue Installments
        Route::get('installment/overdue', [DesktopController::class, 'overdueInstallments']);
        Route::post('installment/send-notifications', [DesktopController::class, 'sendInstallmentNotifications']);

        // Tier Pricing & Scheme Percent
        Route::get('tier-pricing', [DesktopController::class, 'tierPricing']);
        Route::get('scheme-percent', [DesktopController::class, 'schemePercent']);

        // Payment Settlement
        Route::get('payment-settlement', [DesktopController::class, 'paymentSettlement']);

        // Edit APIs
        Route::put('sales/{id}', [DesktopController::class, 'editSale']);
        Route::put('purchases/{id}', [DesktopController::class, 'editPurchase']);
        Route::put('sale-returns/{id}', [DesktopController::class, 'editSaleReturn']);
        Route::put('purchase-returns/{id}', [DesktopController::class, 'editPurchaseReturn']);
        Route::put('expenses/{id}', [DesktopController::class, 'editExpense']);
        Route::put('incomes/{id}', [DesktopController::class, 'editIncome']);
        Route::put('customers/{id}', [DesktopController::class, 'editCustomer']);
        Route::put('suppliers/{id}', [DesktopController::class, 'editSupplier']);

        // Customer Display
        Route::post('customer-display', [DesktopController::class, 'customerDisplay']);
        Route::get('customer-display', [DesktopController::class, 'getCustomerDisplay']);

        // Calculator
        Route::post('calculator', [DesktopController::class, 'calculator']);

        // Barcode Print (HTML)
        Route::get('barcode-print', [DesktopController::class, 'barcodePrint']);

        // ═══ BusyNotify Import (desktop se trigger karo, cloud mein save hoga) ═══
        Route::post('busy-import/all',        [DesktopController::class, 'busyImportAll']);
        Route::post('busy-import/customers',  [DesktopController::class, 'busyImportCustomers']);
        Route::post('busy-import/products',   [DesktopController::class, 'busyImportProducts']);
        Route::get( 'busy-import/status',     [DesktopController::class, 'busyImportStatus']);
        // ── Fast stock-only polling (desktop har 2 min pull karta hai) ──
        Route::get( 'busy-import/stock-delta',  [DesktopController::class, 'busyStockDelta']);
        Route::post('busy-import/sync-stock',   [DesktopController::class, 'busyTriggerStockSync']);

        // 26 Missing Report APIs (Cloud web views existed, no desktop API)
        Route::get('register-report', [DesktopController::class, 'registerReport']);
        Route::get('final-invoice-due-report', [DesktopController::class, 'finalInvoiceDueReport']);
        Route::get('service-sale-report', [DesktopController::class, 'serviceSaleReport']);
        Route::get('combo-service-report', [DesktopController::class, 'comboServiceReport']);
        Route::get('product-sale-report', [DesktopController::class, 'productSaleReport']);
        Route::get('product-profit-report', [DesktopController::class, 'productProfitReport']);
        Route::get('tax-report', [DesktopController::class, 'taxReport']);
        Route::get('sale-return-report', [DesktopController::class, 'saleReturnReport']);
        Route::get('purchase-return-report', [DesktopController::class, 'purchaseReturnReport']);
        Route::get('salary-report', [DesktopController::class, 'salaryReport']);
        Route::get('damage-report', [DesktopController::class, 'damageReport']);
        Route::get('supplier-ledger-report', [DesktopController::class, 'supplierLedgerReport']);
        Route::get('supplier-balance-report', [DesktopController::class, 'supplierBalanceReport']);
        Route::get('low-stock-report', [DesktopController::class, 'lowStockReport']);
        Route::get('expire-soon-report', [DesktopController::class, 'expireSoonReport']);
        Route::get('installment-report', [DesktopController::class, 'installmentReport']);
        Route::get('installment-due-report', [DesktopController::class, 'installmentDueReport']);
        Route::get('installment-collection-report', [DesktopController::class, 'installmentCollectionReport']);
        Route::get('customer-ledger-report', [DesktopController::class, 'customerLedgerReport']);
        Route::get('customer-balance-report', [DesktopController::class, 'customerBalanceReport']);
        Route::get('customer-receive-report', [DesktopController::class, 'customerReceiveReport']);
        Route::get('servicing-report', [DesktopController::class, 'servicingReport']);
        Route::get('gst-report', [DesktopController::class, 'gstReport']);
        Route::get('profit-loss-report', [DesktopController::class, 'profitLossReport']);
        Route::get('attendance-report', [DesktopController::class, 'attendanceReport']);

        // 6 Missing Reports (Cloud mein bilkul nahi the)
        Route::get('warranty-checking-report', [DesktopController::class, 'warrantyCheckingReport']);
        Route::get('detailed-installment-due-report', [DesktopController::class, 'detailedInstallmentDueReport']);
        Route::get('detailed-available-loyalty-point-report', [DesktopController::class, 'detailedAvailableLoyaltyPointReport']);
        Route::get('detailed-usage-loyalty-point-report', [DesktopController::class, 'detailedUsageLoyaltyPointReport']);
        Route::get('detailed-scheme-report', [DesktopController::class, 'detailedSchemeReport']);
        Route::get('detailed-item-tracking-report', [DesktopController::class, 'detailedItemTrackingReport']);
    });
});

// GST/HSN Validation API — public (used by web frontend without API token)
Route::get('gst/validate/{gstin}', [GstController::class, 'validateGstin'])->name('api.gst.validate');
Route::get('hsn/validate/{code}', [GstController::class, 'validateHsn'])->name('api.hsn.validate');
Route::get('hsn/search', [GstController::class, 'searchHsn'])->name('api.hsn.search');
Route::get('gst/states', [GstController::class, 'states'])->name('api.gst.states');
