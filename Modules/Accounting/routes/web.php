<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\AccountingController;
use Modules\Accounting\Http\Controllers\PaymentMethodController;
use Modules\Accounting\Http\Controllers\DepositWithdrawController;
use Modules\Accounting\Http\Controllers\IncomeController;
use Modules\Accounting\Http\Controllers\IncomeCategoryController;
use Modules\Accounting\Http\Controllers\ExpenseController;
use Modules\Accounting\Http\Controllers\ExpenseCategoryController;
use Modules\Accounting\Http\Controllers\ReportController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('accountings', AccountingController::class)->only(['index'])->names('accounting');

    // Payment Method CRUD Routes
    Route::controller(PaymentMethodController::class)->group(function () {
        Route::get('payment-method', 'index')->name('payment-method.index')->middleware('permission:payment_method-list');
        Route::get('payment-method/create', 'create')->name('payment-method.create')->middleware('permission:payment_method-create');
        Route::post('payment-method', 'store')->name('payment-method.store')->middleware('permission:payment_method-create');
        // Static routes must come before dynamic routes to prevent route conflicts
        Route::get('payment-method/sort-payment-method', 'sortPaymentMethod')->name('payment-method.sort-payment-method')->middleware('permission:payment_method-sort');
        Route::post('payment-method/update-sort-order', 'updateSortOrder')->name('payment-method.update-sort-order')->middleware('permission:payment_method-sort');
        // Dynamic routes (with parameters) - must come after static routes
        Route::get('payment-method/{payment_method}', 'show')->name('payment-method.show')->middleware('permission:payment_method-show');
        Route::get('payment-method/{payment_method}/edit', 'edit')->name('payment-method.edit')->middleware('permission:payment_method-edit');
        Route::put('payment-method/{payment_method}', 'update')->name('payment-method.update')->middleware('permission:payment_method-edit');
        Route::delete('payment-method/{payment_method}', 'destroy')->name('payment-method.destroy')->middleware('permission:payment_method-destroy');
    });
    

    // Deposit Withdraw CRUD Routes
    Route::controller(DepositWithdrawController::class)->group(function () {
        Route::get('deposit-withdraw', 'index')->name('deposit-withdraw.index')->middleware(['permission:deposit_withdraw-list', 'outlet_set', 'register_open']);
        Route::get('deposit-withdraw/create', 'create')->name('deposit-withdraw.create')->middleware(['permission:deposit_withdraw-create', 'outlet_set', 'register_open']);
        Route::post('deposit-withdraw', 'store')->name('deposit-withdraw.store')->middleware(['permission:deposit_withdraw-create', 'outlet_set', 'register_open']);
        Route::get('deposit-withdraw/{deposit_withdraw}', 'show')->name('deposit-withdraw.show')->middleware(['permission:deposit_withdraw-show', 'outlet_set']);
        Route::get('deposit-withdraw/{deposit_withdraw}/edit', 'edit')->name('deposit-withdraw.edit')->middleware(['permission:deposit_withdraw-edit', 'outlet_set']);
        Route::put('deposit-withdraw/{deposit_withdraw}', 'update')->name('deposit-withdraw.update')->middleware(['permission:deposit_withdraw-edit', 'outlet_set']);
        Route::delete('deposit-withdraw/{deposit_withdraw}', 'destroy')->name('deposit-withdraw.destroy')->middleware(['permission:deposit_withdraw-destroy', 'outlet_set']);
    });

    // Income Category CRUD Routes
    Route::controller(IncomeCategoryController::class)->group(function () {
        Route::get('income-category', 'index')->name('income-category.index')->middleware('permission:income_category-list');
        Route::get('income-category/create', 'create')->name('income-category.create')->middleware('permission:income_category-create');
        Route::post('income-category', 'store')->name('income-category.store')->middleware('permission:income_category-create');
        Route::get('income-category/{income}', 'show')->name('income-category.show')->middleware('permission:income_category-show');
        Route::get('income-category/{income}/edit', 'edit')->name('income-category.edit')->middleware('permission:income_category-edit');
        Route::put('income-category/{income}', 'update')->name('income-category.update')->middleware('permission:income_category-edit');
        Route::delete('income-category/{income}', 'destroy')->name('income-category.destroy')->middleware('permission:income_category-destroy');
    });

    // Income CRUD Routes
    Route::controller(IncomeController::class)->group(function () {
        Route::get('income', 'index')->name('income.index')->middleware(['permission:income-list', 'outlet_set', 'register_open']);
        Route::get('income/create', 'create')->name('income.create')->middleware(['permission:income-create', 'outlet_set', 'register_open']);
        Route::post('income', 'store')->name('income.store')->middleware(['permission:income-create', 'outlet_set', 'register_open']);
        Route::get('income/{income}', 'show')->name('income.show')->middleware(['permission:income-show', 'outlet_set']);
        Route::get('income/{income}/edit', 'edit')->name('income.edit')->middleware(['permission:income-edit', 'outlet_set']);
        Route::put('income/{income}', 'update')->name('income.update')->middleware(['permission:income-edit', 'outlet_set']);
        Route::delete('income/{income}', 'destroy')->name('income.destroy')->middleware(['permission:income-destroy', 'outlet_set']);
    });

    // Expense Category CRUD Routes
    Route::controller(ExpenseCategoryController::class)->group(function () {
        Route::get('expense-category', 'index')->name('expense-category.index')->middleware('permission:expense_category-list');
        Route::get('expense-category/create', 'create')->name('expense-category.create')->middleware('permission:expense_category-create');
        Route::post('expense-category', 'store')->name('expense-category.store')->middleware('permission:expense_category-create');
        Route::get('expense-category/{expense}', 'show')->name('expense-category.show')->middleware('permission:expense_category-show');
        Route::get('expense-category/{expense}/edit', 'edit')->name('expense-category.edit')->middleware('permission:expense_category-edit');
        Route::put('expense-category/{expense}', 'update')->name('expense-category.update')->middleware('permission:expense_category-edit');
        Route::delete('expense-category/{expense}', 'destroy')->name('expense-category.destroy')->middleware('permission:expense_category-destroy');
    });

    // Expense CRUD Routes
    Route::controller(ExpenseController::class)->group(function () {
        Route::get('expense', 'index')->name('expense.index')->middleware(['permission:expense-list', 'outlet_set', 'register_open']);
        Route::get('expense/create', 'create')->name('expense.create')->middleware(['permission:expense-create', 'outlet_set', 'register_open']);
        Route::post('expense', 'store')->name('expense.store')->middleware(['permission:expense-create', 'outlet_set', 'register_open']);
        Route::get('expense/{expense}', 'show')->name('expense.show')->middleware(['permission:expense-show', 'outlet_set']);
        Route::get('expense/{expense}/edit', 'edit')->name('expense.edit')->middleware(['permission:expense-edit', 'outlet_set']);
        Route::put('expense/{expense}', 'update')->name('expense.update')->middleware(['permission:expense-edit', 'outlet_set']);
        Route::delete('expense/{expense}', 'destroy')->name('expense.destroy')->middleware(['permission:expense-destroy', 'outlet_set']);
    });

    // Report Routes
    Route::controller(ReportController::class)->group(function () {
        // View Routes
        Route::get('account-reports/account-balance/view', 'showAccountBalance')->name('accounting.reports.account-balance.view')->middleware('permission:accounting-account_balance');
        Route::get('account-reports/account-statement/view', 'showAccountStatement')->name('accounting.reports.account-statement.view')->middleware('permission:accounting-account_statement');
        Route::get('account-reports/transaction-history/view', 'showTransactionHistory')->name('accounting.reports.transaction-history.view')->middleware('permission:accounting-transaction_history');
        Route::get('account-reports/trial-balance/view', 'showTrialBalance')->name('accounting.reports.trial-balance.view')->middleware('permission:accounting-trial_balance');
        Route::get('account-reports/balance-sheet/view', 'showBalanceSheet')->name('accounting.reports.balance-sheet.view')->middleware('permission:accounting-balancesheet');
        
        // API Routes (no permission middleware - used by desktop app)
        Route::get('account-reports/account-balance', 'accountBalance')->name('accounting.reports.account-balance');
        Route::get('account-reports/account-statement', 'accountStatement')->name('accounting.reports.account-statement');
        Route::get('account-reports/transaction-history', 'transactionHistory')->name('accounting.reports.transaction-history');
        Route::get('account-reports/trial-balance', 'trialBalance')->name('accounting.reports.trial-balance');
        Route::get('account-reports/balance-sheet', 'balanceSheet')->name('accounting.reports.balance-sheet');
        Route::get('account-reports/filter-options', 'getFilterOptions')->name('accounting.reports.filter-options');
    });
});

// Desktop App API routes - auth via Sanctum token, company from token user
Route::prefix('api/desktop')->middleware('auth:sanctum')->group(function () {
    Route::get('account-balance', [ReportController::class, 'accountBalanceDesktop']);
    Route::get('account-statement', [ReportController::class, 'accountStatementDesktop']);
    Route::get('transaction-history', [ReportController::class, 'transactionHistoryDesktop']);
    Route::get('trial-balance', [ReportController::class, 'trialBalanceDesktop']);
    Route::get('balance-sheet', [ReportController::class, 'balanceSheetDesktop']);
    Route::get('filter-options', [ReportController::class, 'getFilterOptionsDesktop']);
});
