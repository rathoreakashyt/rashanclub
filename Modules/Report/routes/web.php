<?php

use Illuminate\Support\Facades\Route;
use Modules\Report\Http\Controllers\ReportController;
use Modules\Report\Http\Controllers\DashboardController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard Route
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');
    
    // Report View Routes - Define specific routes BEFORE resource route to avoid conflicts
    Route::controller(ReportController::class)->group(function () {
        // Register Report
        Route::get('reports/register-report', 'registerReport')
            ->name('report.register-report')
            ->middleware('permission:report-register_report');

        // Z Report
        Route::get('reports/z-report', 'zReport')
            ->name('report.z-report')
            ->middleware('permission:report-z_report');

        // Daily Summary Report
        Route::get('reports/daily-summary-report', 'dailySummaryReport')
            ->name('report.daily-summary-report')
            ->middleware('permission:report-daily_summary_report');

        // Sale Report - Combined route for both view and API
        Route::get('reports/sale-report', 'saleReport')
            ->name('report.sale-report')
            ->middleware('permission:report-sale_report');

        // Due Sale Report
        Route::get('reports/due-sale-report', 'dueSaleReport')
            ->name('report.due-sale-report')
            ->middleware('permission:report-due_sale_report');

        // Final Invoice Due Report
        Route::get('reports/final-invoice-due-report', 'finalInvoiceDueReport')
            ->name('report.final-invoice-due-report')
            ->middleware('permission:report-final_invoice_due_report');

        // Service Sale Report
        Route::get('reports/service-sale-report', 'serviceSaleReport')
            ->name('report.service-sale-report')
            ->middleware('permission:report-service_sale_report');

        // Combo Service Report
        Route::get('reports/combo-service-report', 'comboServiceReport')
            ->name('report.combo-service-report')
            ->middleware('permission:report-combo_service_report');

        // Stock Report
        Route::get('reports/stock-report', 'stockReport')
            ->name('report.stock-report')
            ->middleware('permission:report-stock_report');

        // Low Stock Report
        Route::get('reports/low-stock-report', 'lowStockReport')
            ->name('report.low-stock-report')
            ->middleware('permission:report-low_stock_report');

        // AJAX: Search items for report filters (by name/code) – used by stock & low-stock reports
        Route::get('reports/search-items', 'searchReportItems')
            ->name('report.search-items');

        // Expire Soon Report
        Route::get('reports/expire-soon-report', 'expireSoonReport')
            ->name('report.expire-soon-report')
            ->middleware('permission:report-expire_soon_report');

        // Employee Sale Report
        Route::get('reports/employee-sale-report', 'employeeSaleReport')
            ->name('report.employee-sale-report')
            ->middleware('permission:report-employee_sale_report');

        // Customer Receive Report
        Route::get('reports/customer-receive-report', 'customerReceiveReport')
            ->name('report.customer-receive-report')
            ->middleware('permission:report-customer_receive_report');

        // Attendance Report
        Route::get('reports/attendance-report', 'attendanceReport')
            ->name('report.attendance-report')
            ->middleware('permission:report-attendance_report');

        // Product Profit Report
        Route::get('reports/product-profit-report', 'productProfitReport')
            ->name('report.product-profit-report')
            ->middleware('permission:report-product_profit_report');

        // Supplier Ledger Report
        Route::get('reports/supplier-ledger-report', 'supplierLedgerReport')
            ->name('report.supplier-ledger-report')
            ->middleware('permission:report-supplier_ledger_report');

        // Supplier Balance Report
        Route::get('reports/supplier-balance-report', 'supplierBalanceReport')
            ->name('report.supplier-balance-report')
            ->middleware('permission:report-supplier_balance_report');

        // Customer Ledger Report
        Route::get('reports/customer-ledger-report', 'customerLedgerReport')
            ->name('report.customer-ledger-report')
            ->middleware('permission:report-customer_ledger_report');

        // Customer Balance Report
        Route::get('reports/customer-balance-report', 'customerBalanceReport')
            ->name('report.customer-balance-report')
            ->middleware('permission:report-customer_balance_report');

        // Servicing Report
        Route::get('reports/servicing-report', 'servicingReport')
            ->name('report.servicing-report')
            ->middleware('permission:report-servicing_report');

        // Product Sale Report
        Route::get('reports/product-sale-report', 'productSaleReport')
            ->name('report.product-sale-report')
            ->middleware('permission:report-product_sale_report');

        // Tax Report
        Route::get('reports/tax-report', 'taxReport')
            ->name('report.tax-report')
            ->middleware('permission:report-tax_report');

        // GST Reports (8 formats: tax-summary, monthly-summary, rate-wise, b2b, b2c-small, b2c-large, hsn-summary, gstr3b)
        Route::get('reports/gst-report', 'gstReport')
            ->name('report.gst-report')
            ->middleware('permission:report-tax_report');

        // Detailed Sale Report
        Route::get('reports/detailed-sale-report', 'detailedSaleReport')
            ->name('report.detailed-sale-report')
            ->middleware('permission:report-detailed_sale_report');

        // Profit Loss Report
        Route::get('reports/profit-loss-report', 'profitLossReport')
            ->name('report.profit-loss-report')
            ->middleware('permission:report-profit_loss_report');

        // Purchase Report
        Route::get('reports/purchase-report', 'purchaseReport')
            ->name('report.purchase-report')
            ->middleware('permission:report-purchase_report');

        // Expense Report
        Route::get('reports/expense-report', 'expenseReport')
            ->name('report.expense-report')
            ->middleware('permission:report-expense_report');

        // Income Report
        Route::get('reports/income-report', 'incomeReport')
            ->name('report.income-report')
            ->middleware('permission:report-income_report');

        // Salary Report
        Route::get('reports/salary-report', 'salaryReport')
            ->name('report.salary-report')
            ->middleware('permission:report-salary_report');

        // Purchase Return Report
        Route::get('reports/purchase-return-report', 'purchaseReturnReport')
            ->name('report.purchase-return-report')
            ->middleware('permission:report-purchase_return_report');

        // Sale Return Report
        Route::get('reports/sale-return-report', 'saleReturnReport')
            ->name('report.sale-return-report')
            ->middleware('permission:report-sale_return_report');

        // Damage Report
        Route::get('reports/damage-report', 'damageReport')
            ->name('report.damage-report')
            ->middleware('permission:report-damage_report');

        // Installment Report
        Route::get('reports/installment-report', 'installmentReport')
            ->name('report.installment-report')
            ->middleware('permission:report-installment_report');

        // Installment Due Report
        Route::get('reports/installment-due-report', 'installmentDueReport')
            ->name('report.installment-due-report')
            ->middleware('permission:report-installment_due_report');

        // Item Tracking Report
        Route::get('reports/item-tracking-report', 'itemTrackingReport')
            ->name('report.item-tracking-report')
            ->middleware('permission:report-item_tracking_report');

        // Price History Report
        Route::get('reports/price-history-report', 'priceHistoryReport')
            ->name('report.price-history-report')
            ->middleware('permission:report-price_history_report');

        // Cash Flow Report
        Route::get('reports/cash-flow-report', 'cashFlowReport')
            ->name('report.cash-flow-report')
            ->middleware('permission:report-cash_flow_report');

        

        // Available Loyalty Point Report
        Route::get('reports/available-loyalty-point-report', 'availableLoyaltyPointReport')
            ->name('report.available-loyalty-point-report')
            ->middleware('permission:report-available_loyalty_point_report');

        // Usage Loyalty Point Report
        Route::get('reports/usage-loyalty-point-report', 'usageLoyaltyPointReport')
            ->name('report.usage-loyalty-point-report')
            ->middleware('permission:report-usage_loyalty_point_report');

        // Scheme Report
        Route::get('reports/scheme-report', 'schemeReport')
            ->name('report.scheme-report')
            ->middleware('permission:report-scheme_report');
    });

    // Resource route - Define AFTER specific routes to avoid conflicts
    Route::resource('reports', ReportController::class)->only(['index'])->names('report');
});
