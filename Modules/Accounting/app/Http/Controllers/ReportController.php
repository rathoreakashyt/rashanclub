<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Sale\Models\Servicing;
use Modules\Sale\Models\SaleReturn;
use Illuminate\Support\Facades\Auth;
use Modules\Sale\Models\SalePayment;
use Modules\Accounting\Models\Income;
use Modules\Purchase\Models\Purchase;
use Modules\Accounting\Models\Expense;
use Modules\Configuration\Models\Outlet;
use Modules\Sale\Models\CustomerReceive;
use Modules\Sale\Models\InstallmentSale;
use Modules\Purchase\Models\PurchaseReturn;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Purchase\Models\PurchasePayment;
use Modules\Purchase\Models\SupplierPayment;
use Modules\Accounting\Models\DepositWithdraw;
use Modules\Sale\Models\InstallmentSaleDetail;
use Modules\Administrator\Models\SalaryPayment;
use Modules\Administrator\Models\EmployeeAdvancePayment;
use Modules\Stock\Services\StockService;

class ReportController extends Controller
{
    /**
     * Get Account Balance Report
     * Current balance = Opening + Sale + Installment Sale (down) + Installment Collection + Customer Receive
     *   + Income + Purchase Return + Deposit
     *   - Sale Return - Purchase - Supplier Payment - Expense - Servicing - Withdraw - Salary - Employee Advance
     */
    public function accountBalance(Request $request)
    {
        $companyId = session('company.company_id');
        $outletId = $request->get('outlet_id');

        $paymentMethods = PaymentMethod::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('status', 'Enable')
            ->where('account_type', '!=', 'Loyalty Point')
            ->get();

        $accounts = [];
        $totalBalanceRaw = 0;

        foreach ($paymentMethods as $key => $paymentMethod) {
            $pmId = $paymentMethod->id;

            // Opening balance (credit)
            $openingBalance = (float) ($paymentMethod->current_balance ?? 0);

            // Sale (credit) – sale_payments.payment_id
            $sale = (float) SalePayment::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('payment_id', $pmId)
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->sum('amount');

            // Sale Return (debit) – money paid back to customer
            $saleReturn = (float) SaleReturn::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('payment_method_id', $pmId)
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->sum('paid');

            // Installment Sale – down payment (credit)
            $installmentSale = (float) InstallmentSale::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->sum('down_payment');

            // Installment Collection (credit)
            $installmentCollection = (float) InstallmentSaleDetail::whereHas('installmentSale', function ($q) use ($companyId, $outletId) {
                $q->where('del_status', 'Live')->where('company_id', $companyId);
                if ($outletId) {
                    $q->where('outlet_id', $outletId);
                }
            })
                ->where('payment_method_id', $pmId)
                ->where('del_status', 'Live')
                ->whereIn('paid_status', ['Paid', 'Partial'])
                ->sum('paid_amount');

            // Customer Receive (credit)
            $customerReceive = (float) CustomerReceive::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('payment_method_id', $pmId)
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->sum('amount');

            // Income (credit)
            $income = (float) Income::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('payment_method_id', $pmId)
                ->sum('amount');

            // Purchase Return (credit) – money received from supplier
            $purchaseReturn = (float) PurchaseReturn::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('payment_method_id', $pmId)
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->sum('total_return_amount');

            // Deposit (credit)
            $deposit = (float) DepositWithdraw::where('del_status', 'Live')
                ->where('payment_method_id', $pmId)
                ->where('type', 'Deposit')
                ->where('company_id', $companyId)
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->sum('amount');

            // Purchase (debit) – purchase_payments.payment_id
            $purchase = (float) PurchasePayment::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('payment_id', $pmId)
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->sum('amount');

            // Supplier Payment (debit)
            $supplierPayment = (float) SupplierPayment::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('payment_method_id', $pmId)
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->sum('amount');

            // Expense (debit)
            $expense = (float) Expense::where('del_status', 'Live')
                ->where('payment_method_id', $pmId)
                ->where('company_id', $companyId)
                ->sum('amount');

            // Servicing (debit) – paid_amount
            $servicing = (float) Servicing::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('payment_method_id', $pmId)
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->sum('paid_amount');

            // Withdraw (debit)
            $withdraw = (float) DepositWithdraw::where('del_status', 'Live')
                ->where('payment_method_id', $pmId)
                ->where('type', 'Withdraw')
                ->where('company_id', $companyId)
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->sum('amount');

            // Salary (debit)
            $salary = (float) SalaryPayment::where('del_status', 'Live')
                ->where('payment_method_id', $pmId)
                ->where('company_id', $companyId)
                ->sum('amount');

            // Employee Advance Payment (debit)
            $employeeAdvance = (float) EmployeeAdvancePayment::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('payment_method_id', $pmId)
                ->sum('amount');

            $currentBalance = $openingBalance
                + $sale - $saleReturn
                + $installmentSale + $installmentCollection
                + $customerReceive + $income + $purchaseReturn + $deposit
                - $purchase - $supplierPayment - $expense - $servicing - $withdraw - $salary - $employeeAdvance;

            $totalBalanceRaw += $currentBalance;

            $accounts[] = [
                'sn' => $key + 1,
                'account_name' => $paymentMethod->name,
                'balance' => formatAmount($currentBalance),
            ];
        }

        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'address', 'phone')
            ->get();

        $summary = [
            'total_accounts' => count($accounts),
            'total_balance' => formatAmount($totalBalanceRaw),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'accounts' => $accounts,
                'outlets' => $outlets,
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * Compute opening balance for a payment method using all modules (transactions before date_from).
     */
    protected function computeOpeningBalanceBeforeDate(
        int $paymentMethodId,
        int $companyId,
        ?int $outletId,
        string $dateFrom
    ): float {
        $pmId = $paymentMethodId;
        $base = (float) (PaymentMethod::find($pmId)->current_balance ?? 0);

        $sale = (float) SalePayment::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('payment_id', $pmId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->where(function ($q) use ($dateFrom) {
                $q->whereDate('date', '<', $dateFrom)
                    ->orWhere(function ($q2) use ($dateFrom) {
                        $q2->whereNull('date')->whereDate('date', '<', $dateFrom);
                    });
            })
            ->sum('amount');

        $saleReturn = (float) SaleReturn::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('payment_method_id', $pmId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereDate('date', '<', $dateFrom)
            ->sum('paid');

        $installmentSale = (float) InstallmentSale::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereDate('date', '<', $dateFrom)
            ->sum('down_payment');

        $installmentCollection = (float) InstallmentSaleDetail::whereHas('installmentSale', function ($q) use ($companyId, $outletId) {
            $q->where('del_status', 'Live')->where('company_id', $companyId);
            if ($outletId) {
                $q->where('outlet_id', $outletId);
            }
        })
            ->where('payment_method_id', $pmId)
            ->where('del_status', 'Live')
            ->whereIn('paid_status', ['Paid', 'Partial'])
            ->where(function ($q) use ($dateFrom) {
                $q->whereDate('paid_date', '<', $dateFrom)
                    ->orWhere(function ($q2) use ($dateFrom) {
                        $q2->whereNull('paid_date')->whereDate('created_at', '<', $dateFrom);
                    });
            })
            ->sum('paid_amount');

        $customerReceive = (float) CustomerReceive::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('payment_method_id', $pmId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereDate('date', '<', $dateFrom)
            ->sum('amount');

        $income = (float) Income::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('payment_method_id', $pmId)
            ->whereDate('date', '<', $dateFrom)
            ->sum('amount');

        $purchaseReturn = (float) PurchaseReturn::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('payment_method_id', $pmId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereDate('date', '<', $dateFrom)
            ->sum('total_return_amount');

        $deposit = (float) DepositWithdraw::where('del_status', 'Live')
            ->where('payment_method_id', $pmId)
            ->where('type', 'Deposit')
            ->where('company_id', $companyId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereDate('date', '<', $dateFrom)
            ->sum('amount');

        $purchase = (float) PurchasePayment::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('payment_id', $pmId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereDate('date', '<', $dateFrom)
            ->sum('amount');

        $supplierPayment = (float) SupplierPayment::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('payment_method_id', $pmId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereDate('date', '<', $dateFrom)
            ->sum('amount');

        $expense = (float) Expense::where('del_status', 'Live')
            ->where('payment_method_id', $pmId)
            ->where('company_id', $companyId)
            ->whereDate('date', '<', $dateFrom)
            ->sum('amount');

        $servicing = (float) Servicing::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('payment_method_id', $pmId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereDate('date', '<', $dateFrom)
            ->sum('paid_amount');

        $withdraw = (float) DepositWithdraw::where('del_status', 'Live')
            ->where('payment_method_id', $pmId)
            ->where('type', 'Withdraw')
            ->where('company_id', $companyId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereDate('date', '<', $dateFrom)
            ->sum('amount');

        $salary = (float) SalaryPayment::where('del_status', 'Live')
            ->where('payment_method_id', $pmId)
            ->where('company_id', $companyId)
            ->whereHas('salary', fn ($q) => $q->whereDate('generated_date', '<', $dateFrom))
            ->sum('amount');

        $employeeAdvance = (float) EmployeeAdvancePayment::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('payment_method_id', $pmId)
            ->whereDate('date', '<', $dateFrom)
            ->sum('amount');

        return $base
            + $sale - $saleReturn
            + $installmentSale + $installmentCollection
            + $customerReceive + $income + $purchaseReturn + $deposit
            - $purchase - $supplierPayment - $expense - $servicing - $withdraw - $salary - $employeeAdvance;
    }

    /**
     * Get Account Statement Report
     * Shows payment method wise transaction history with Debit, Credit, Balance.
     * Modules: Opening Balance, Sale, Sale Return, Installment Sale, Installment Collection,
     * Customer Receive, Income, Purchase, Purchase Return, Supplier Payment, Expense,
     * Servicing, Deposit, Withdraw, Salary, Employee Advance Payment.
     * When date range is set, Opening Balance = all module amounts before date_from.
     */
    public function accountStatement(Request $request)
    {
        $companyId = session('company.company_id');
        $paymentMethodId = (int) $request->get('payment_method_id');
        $outletId = $request->get('outlet_id') ? (int) $request->get('outlet_id') : null;
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if (!$paymentMethodId) {
            return response()->json([
                'success' => false,
                'message' => 'Payment Method is required',
            ], 400);
        }

        $paymentMethod = PaymentMethod::find($paymentMethodId);
        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment Method not found',
            ], 404);
        }

        $statements = [];
        $runningBalance = (float) ($paymentMethod->current_balance ?? 0);
        $openingBalanceForSummary = null;

        if ($dateFrom) {
            $runningBalance = $this->computeOpeningBalanceBeforeDate(
                $paymentMethodId,
                $companyId,
                $outletId,
                $dateFrom
            );
            $openingBalanceForSummary = $runningBalance;
            $statements[] = [
                'id' => 'opening',
                'date' => $dateFrom,
                'title' => 'Opening Balance',
                'reference_no' => '-',
                'type' => 'Opening Balance',
                'debit' => 0,
                'credit' => 0,
                'balance' => formatAmount($runningBalance),
                'created_at' => null,
                'created_by' => null,
            ];
        } else {
            // No date filter: show payment method opening balance (e.g. Cash 500) — use early date so it sorts first
            $openingBalanceForSummary = $runningBalance;
            $statements[] = [
                'id' => 'opening',
                'date' => '',
                'title' => 'Opening Balance',
                'reference_no' => '-',
                'type' => 'Opening Balance',
                'debit' => 0,
                'credit' => 0,
                'balance' => formatAmount($runningBalance),
                'created_at' => null,
                'created_by' => null,
            ];
        }

        // Sale (SalePayment)
        $salePaymentQuery = SalePayment::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('payment_id', $paymentMethodId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        if ($dateFrom) {
            $salePaymentQuery->where(function ($q) use ($dateFrom) {
                $q->whereDate('date', '>=', $dateFrom)->orWhere(fn ($q2) => $q2->whereNull('date')->whereDate('date', '>=', $dateFrom));
            });
        }
        if ($dateTo) {
            $salePaymentQuery->where(function ($q) use ($dateTo) {
                $q->whereDate('date', '<=', $dateTo)->orWhere(fn ($q2) => $q2->whereNull('date')->whereDate('date', '<=', $dateTo));
            });
        }
        $salePayments = $salePaymentQuery->with('sale')->orderByRaw('COALESCE(date, date) ASC')->orderBy('id', 'asc')->get();
        foreach ($salePayments as $sp) {
            $d = $sp->date ?? $sp->date;
            $runningBalance += $sp->amount;
            $statements[] = [
                'id' => 'sale_' . $sp->id,
                'date' => $d,
                'title' => 'Sale - ' . ($sp->sale ? ($sp->sale->reference_no ?? $sp->sale->invoice_no ?? 'N/A') : 'N/A'),
                'reference_no' => $sp->sale->reference_no ?? '-',
                'type' => 'Sale',
                'debit' => 0,
                'credit' => formatAmount($sp->amount),
                'balance' => formatAmount($runningBalance),
                'created_at' => $sp->created_at,
                'created_by' => $sp->user_id ? \App\Models\User::find($sp->user_id)?->name : null,
            ];
        }

        // Sale Return
        $saleReturnQuery = SaleReturn::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('payment_method_id', $paymentMethodId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        if ($dateFrom) {
            $saleReturnQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $saleReturnQuery->whereDate('date', '<=', $dateTo);
        }
        $saleReturns = $saleReturnQuery->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        foreach ($saleReturns as $sr) {
            $runningBalance -= $sr->paid;
            $statements[] = [
                'id' => 'sale_return_' . $sr->id,
                'date' => $sr->date,
                'title' => 'Sale Return - ' . ($sr->reference_no ?? 'N/A'),
                'reference_no' => $sr->reference_no ?? '-',
                'type' => 'Sale Return',
                'debit' => formatAmount($sr->paid),
                'credit' => 0,
                'balance' => formatAmount($runningBalance),
                'created_at' => $sr->created_at,
                'created_by' => $sr->user_id ? \App\Models\User::find($sr->user_id)?->name : null,
            ];
        }

        // Installment Sale (down payment)
        $instSaleQuery = InstallmentSale::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('down_payment', '>', 0)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        if ($dateFrom) {
            $instSaleQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $instSaleQuery->whereDate('date', '<=', $dateTo);
        }
        $instSales = $instSaleQuery->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        foreach ($instSales as $is) {
            $runningBalance += $is->down_payment;
            $statements[] = [
                'id' => 'installment_sale_' . $is->id,
                'date' => $is->date,
                'title' => 'Installment Sale (Down) - ' . ($is->reference_no ?? 'N/A'),
                'reference_no' => $is->reference_no ?? '-',
                'type' => 'Installment Sale',
                'debit' => 0,
                'credit' => formatAmount($is->down_payment),
                'balance' => formatAmount($runningBalance),
                'created_at' => $is->created_at,
                'created_by' => $is->user_id ? \App\Models\User::find($is->user_id)?->name : null,
            ];
        }

        // Installment Collection (InstallmentSaleDetail paid_amount)
        $instDetailQuery = InstallmentSaleDetail::whereHas('installmentSale', function ($q) use ($companyId, $outletId) {
            $q->where('del_status', 'Live')->where('company_id', $companyId);
            if ($outletId) {
                $q->where('outlet_id', $outletId);
            }
        })
            ->where('payment_method_id', $paymentMethodId)
            ->where('del_status', 'Live')
            ->whereIn('paid_status', ['Paid', 'Partial'])
            ->where('paid_amount', '>', 0);
        if ($dateFrom) {
            $instDetailQuery->where(function ($q) use ($dateFrom) {
                $q->whereDate('paid_date', '>=', $dateFrom)->orWhere(fn ($q2) => $q2->whereNull('paid_date')->whereDate('created_at', '>=', $dateFrom));
            });
        }
        if ($dateTo) {
            $instDetailQuery->where(function ($q) use ($dateTo) {
                $q->whereDate('paid_date', '<=', $dateTo)->orWhere(fn ($q2) => $q2->whereNull('paid_date')->whereDate('created_at', '<=', $dateTo));
            });
        }
        $instDetails = $instDetailQuery->orderByRaw('COALESCE(paid_date, created_at) ASC')->orderBy('id', 'asc')->get();
        foreach ($instDetails as $id) {
            $d = $id->paid_date ?? $id->created_at;
            $runningBalance += $id->paid_amount;
            $statements[] = [
                'id' => 'installment_collection_' . $id->id,
                'date' => $d,
                'title' => 'Installment Collection - ' . ($id->installmentSale->reference_no ?? 'N/A'),
                'reference_no' => $id->installmentSale->reference_no ?? '-',
                'type' => 'Installment Collection',
                'debit' => 0,
                'credit' => formatAmount($id->paid_amount),
                'balance' => formatAmount($runningBalance),
                'created_at' => $id->created_at,
                'created_by' => $id->user_id ? \App\Models\User::find($id->user_id)?->name : null,
            ];
        }

        // Customer Receive
        $customerReceiveQuery = CustomerReceive::where('del_status', 'Live')
            ->where('payment_method_id', $paymentMethodId)
            ->where('company_id', $companyId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        if ($dateFrom) {
            $customerReceiveQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $customerReceiveQuery->whereDate('date', '<=', $dateTo);
        }
        $customerReceives = $customerReceiveQuery->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        foreach ($customerReceives as $receive) {
            $runningBalance += $receive->amount;
            $statements[] = [
                'id' => 'customer_receive_' . $receive->id,
                'date' => $receive->date,
                'title' => 'Customer Receive - ' . ($receive->reference_no ?? 'N/A'),
                'reference_no' => $receive->reference_no ?? '-',
                'type' => 'Customer Receive',
                'debit' => 0,
                'credit' => formatAmount($receive->amount),
                'balance' => formatAmount($runningBalance),
                'created_at' => $receive->created_at,
                'created_by' => $receive->user_id ? \App\Models\User::find($receive->user_id)?->name : null,
            ];
        }

        // Income
        $incomeQuery = Income::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('payment_method_id', $paymentMethodId);
        if ($dateFrom) {
            $incomeQuery->where(function ($q) use ($dateFrom) {
                $q->whereDate('date', '>=', $dateFrom)->orWhereNull('date');
            });
        }
        if ($dateTo) {
            $incomeQuery->where(function ($q) use ($dateTo) {
                $q->whereDate('date', '<=', $dateTo)->orWhereNull('date');
            });
        }
        $incomes = $incomeQuery->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        foreach ($incomes as $inc) {
            $runningBalance += $inc->amount;
            $statements[] = [
                'id' => 'income_' . $inc->id,
                'date' => $inc->date ?? $inc->created_at,
                'title' => $inc->note ?? 'Income',
                'reference_no' => $inc->reference_no ?? '-',
                'type' => 'Income',
                'debit' => 0,
                'credit' => $inc->amount,
                'balance' => formatAmount($runningBalance),
                'created_at' => $inc->created_at,
                'credit' => formatAmount($inc->amount),
                'created_by' => $inc->employee_id ? \App\Models\User::find($inc->employee_id)?->name : null,
            ];
        }

        // Purchase Return
        $purchaseReturnQuery = PurchaseReturn::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('payment_method_id', $paymentMethodId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        if ($dateFrom) {
            $purchaseReturnQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $purchaseReturnQuery->whereDate('date', '<=', $dateTo);
        }
        $purchaseReturns = $purchaseReturnQuery->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        foreach ($purchaseReturns as $pr) {
            $amt = $pr->total_return_amount ?? 0;
            if ($amt > 0) {
                $runningBalance += $amt;
                $statements[] = [
                    'id' => 'purchase_return_' . $pr->id,
                    'date' => $pr->date,
                    'title' => 'Purchase Return - ' . ($pr->reference_no ?? 'N/A'),
                    'reference_no' => $pr->reference_no ?? '-',
                    'type' => 'Purchase Return',
                    'debit' => 0,
                    'credit' => formatAmount($amt),
                    'balance' => formatAmount($runningBalance),
                    'created_at' => $pr->created_at ?? null,
                    'created_by' => $pr->user_id ? \App\Models\User::find($pr->user_id)?->name : null,
                ];
            }
        }

        // Deposit
        $depositQuery = DepositWithdraw::where('del_status', 'Live')
            ->where('payment_method_id', $paymentMethodId)
            ->where('type', 'Deposit')
            ->where('company_id', $companyId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        if ($dateFrom) {
            $depositQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $depositQuery->whereDate('date', '<=', $dateTo);
        }
        $deposits = $depositQuery->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        foreach ($deposits as $deposit) {
            $runningBalance += $deposit->amount;
            $statements[] = [
                'id' => 'deposit_' . $deposit->id,
                'date' => $deposit->date,
                'title' => $deposit->note ?? 'Deposit',
                'reference_no' => $deposit->reference_no ?? '-',
                'type' => 'Deposit',
                'debit' => 0,
                'credit' => formatAmount($deposit->amount),
                'balance' => formatAmount($runningBalance),
                'created_at' => $deposit->created_at,
                'created_by' => $deposit->user_id ? \App\Models\User::find($deposit->user_id)?->name : null,
            ];
        }

        // Purchase (PurchasePayment)
        $purchasePaymentQuery = PurchasePayment::where('del_status', 'Live')
            ->where('payment_id', $paymentMethodId)
            ->where('company_id', $companyId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        if ($dateFrom) {
            $purchasePaymentQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $purchasePaymentQuery->whereDate('date', '<=', $dateTo);
        }
        $purchasePayments = $purchasePaymentQuery->with('purchase')->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        foreach ($purchasePayments as $payment) {
            $runningBalance -= $payment->amount;
            $statements[] = [
                'id' => 'purchase_payment_' . $payment->id,
                'date' => $payment->date,
                'title' => 'Purchase Payment - ' . ($payment->purchase ? ($payment->purchase->reference_no ?? $payment->purchase->invoice_no ?? 'N/A') : 'N/A'),
                'reference_no' => $payment->purchase->reference_no ?? '-',
                'type' => 'Purchase',
                'debit' => formatAmount($payment->amount),
                'credit' => 0,
                'balance' => formatAmount($runningBalance),
                'created_at' => $payment->created_at,
                'created_by' => $payment->user_id ? \App\Models\User::find($payment->user_id)?->name : null,
            ];
        }

        // Supplier Payment
        $supplierPaymentQuery = SupplierPayment::where('del_status', 'Live')
            ->where('payment_method_id', $paymentMethodId)
            ->where('company_id', $companyId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        if ($dateFrom) {
            $supplierPaymentQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $supplierPaymentQuery->whereDate('date', '<=', $dateTo);
        }
        $supplierPayments = $supplierPaymentQuery->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        foreach ($supplierPayments as $payment) {
            $runningBalance -= $payment->amount;
            $statements[] = [
                'id' => 'supplier_payment_' . $payment->id,
                'date' => $payment->date,
                'title' => 'Supplier Payment - ' . ($payment->reference_no ?? 'N/A'),
                'reference_no' => $payment->reference_no ?? '-',
                'type' => 'Supplier Payment',
                'debit' => formatAmount($payment->amount),
                'credit' => 0,
                'balance' => formatAmount($runningBalance),
                'created_at' => $payment->created_at,
                'created_by' => $payment->user_id ? \App\Models\User::find($payment->user_id)?->name : null,
            ];
        }

        // Expense
        $expenseQuery = Expense::where('del_status', 'Live')
            ->where('payment_method_id', $paymentMethodId)
            ->where('company_id', $companyId);
        if ($dateFrom) {
            $expenseQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $expenseQuery->whereDate('date', '<=', $dateTo);
        }
        $expenses = $expenseQuery->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        foreach ($expenses as $expense) {
            $runningBalance -= $expense->amount;
            $statements[] = [
                'id' => 'expense_' . $expense->id,
                'date' => $expense->date,
                'title' => $expense->note ?? 'Expense',
                'reference_no' => $expense->reference_no ?? '-',
                'type' => 'Expense',
                'debit' => formatAmount($expense->amount),
                'credit' => 0,
                'balance' => formatAmount($runningBalance),
                'created_at' => $expense->created_at,
                'created_by' => $expense->user_id ? \App\Models\User::find($expense->user_id)?->name : null,
            ];
        }

        // Servicing
        $servicingQuery = Servicing::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('payment_method_id', $paymentMethodId)
            ->where('paid_amount', '>', 0)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        if ($dateFrom) {
            $servicingQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $servicingQuery->whereDate('date', '<=', $dateTo);
        }
        $servicings = $servicingQuery->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        foreach ($servicings as $svc) {
            $runningBalance -= $svc->paid_amount;
            $statements[] = [
                'id' => 'servicing_' . $svc->id,
                'date' => $svc->date,
                'title' => 'Servicing - ' . ($svc->reference_no ?? 'N/A'),
                'reference_no' => $svc->reference_no ?? '-',
                'type' => 'Servicing',
                'debit' => formatAmount($svc->paid_amount),
                'credit' => 0,
                'balance' => formatAmount($runningBalance),
                'created_at' => $svc->created_at,
                'created_by' => $svc->user_id ? \App\Models\User::find($svc->user_id)?->name : null,
            ];
        }

        // Withdraw
        $withdrawQuery = DepositWithdraw::where('del_status', 'Live')
            ->where('payment_method_id', $paymentMethodId)
            ->where('type', 'Withdraw')
            ->where('company_id', $companyId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        if ($dateFrom) {
            $withdrawQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $withdrawQuery->whereDate('date', '<=', $dateTo);
        }
        $withdrawals = $withdrawQuery->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        foreach ($withdrawals as $withdrawal) {
            $runningBalance -= $withdrawal->amount;
            $statements[] = [
                'id' => 'withdraw_' . $withdrawal->id,
                'date' => $withdrawal->date,
                'title' => $withdrawal->note ?? 'Withdrawal',
                'reference_no' => $withdrawal->reference_no ?? '-',
                'type' => 'Withdraw',
                'debit' => formatAmount($withdrawal->amount),
                'credit' => 0,
                'balance' => formatAmount($runningBalance),
                'created_at' => $withdrawal->created_at,
                'created_by' => $withdrawal->user_id ? \App\Models\User::find($withdrawal->user_id)?->name : null,
            ];
        }

        // Salary
        $salaryPaymentQuery = SalaryPayment::where('del_status', 'Live')
            ->where('payment_method_id', $paymentMethodId)
            ->where('company_id', $companyId)
            ->with('salary');
        if ($dateFrom) {
            $salaryPaymentQuery->whereHas('salary', fn ($q) => $q->whereDate('generated_date', '>=', $dateFrom));
        }
        if ($dateTo) {
            $salaryPaymentQuery->whereHas('salary', fn ($q) => $q->whereDate('generated_date', '<=', $dateTo));
        }
        $salaryPayments = $salaryPaymentQuery->get()->sortBy(function ($sp) {
            return $sp->salary ? $sp->salary->generated_date : $sp->created_at;
        })->values();
        foreach ($salaryPayments as $sp) {
            $d = $sp->salary ? $sp->salary->generated_date : $sp->created_at;
            $runningBalance -= $sp->amount;
            $statements[] = [
                'id' => 'salary_' . $sp->id,
                'date' => $d,
                'title' => 'Salary - ' . ($sp->salary ? $sp->salary->reference_no : 'N/A'),
                'reference_no' => $sp->salary->reference_no ?? '-',
                'type' => 'Salary',
                'debit' => formatAmount($sp->amount),
                'credit' => 0,
                'balance' => formatAmount($runningBalance),
                'created_at' => $sp->created_at,
                'created_by' => $sp->user_id ? \App\Models\User::find($sp->user_id)?->name : null,
            ];
        }

        // Employee Advance Payment
        $empAdvanceQuery = EmployeeAdvancePayment::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('payment_method_id', $paymentMethodId);
        if ($dateFrom) {
            $empAdvanceQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $empAdvanceQuery->whereDate('date', '<=', $dateTo);
        }
        $empAdvances = $empAdvanceQuery->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        foreach ($empAdvances as $ea) {
            $runningBalance -= $ea->amount;
            $statements[] = [
                'id' => 'employee_advance_' . $ea->id,
                'date' => $ea->date,
                'title' => 'Employee Advance - ' . ($ea->reference_no ?? 'N/A'),
                'reference_no' => $ea->reference_no ?? '-',
                'type' => 'Employee Advance Payment',
                'debit' => formatAmount($ea->amount),
                'credit' => 0,
                'balance' => formatAmount($runningBalance),
                'created_at' => $ea->created_at,
                'created_by' => $ea->user_id ? \App\Models\User::find($ea->user_id)?->name : null,
            ];
        }

        // Sort all statements date-wise (by date, then by id)
        usort($statements, function ($a, $b) {
            $da = $a['date'] ?? null;
            $db = $b['date'] ?? null;
            if (! $da && ! $db) {
                return (string) ($a['id'] ?? '') <=> (string) ($b['id'] ?? '');
            }
            if (! $da) {
                return -1;
            }
            if (! $db) {
                return 1;
            }
            $tda = $da instanceof \Carbon\Carbon ? $da->format('Y-m-d H:i:s') : (is_string($da) ? $da : '');
            $tdb = $db instanceof \Carbon\Carbon ? $db->format('Y-m-d H:i:s') : (is_string($db) ? $db : '');
            if ($tda === $tdb) {
                return (string) ($a['id'] ?? '') <=> (string) ($b['id'] ?? '');
            }
            return strcmp($tda, $tdb);
        });

        // Recompute running balance in date order
        $running = (float) ($paymentMethod->current_balance ?? 0);
        foreach ($statements as &$statement) {
            if (($statement['id'] ?? '') === 'opening') {
                $running = (float) ($statement['balance'] ?? 0);
            } else {
                $running += (float) ($statement['credit'] ?? 0) - (float) ($statement['debit'] ?? 0);
                $statement['balance'] = $running;
            }
        }
        unset($statement);
        $runningBalance = $running;

        $sn = 1;
        foreach ($statements as &$statement) {
            $statement['sn'] = $sn++;
            $statement['added_by'] = $statement['created_by'] ?? 'N/A';
            // Format date and time using company format (date-wise display)
            $rawDate = $statement['date'] ?? null;
            $statement['date'] = $rawDate ? formatDateTime($rawDate) : '-';
            $statement['added_date_time'] = ! empty($statement['created_at'])
                ? formatDateTime($statement['created_at'])
                : 'N/A';
        }

        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'address', 'phone')
            ->get();

        $paymentMethods = PaymentMethod::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('status', 'Enable')
            ->select('id', 'name')
            ->get();

        $summary = [
            'total_debit' => array_sum(array_column($statements, 'debit')),
            'total_credit' => array_sum(array_column($statements, 'credit')),
            'closing_balance' => $runningBalance,
            'opening_balance' => $openingBalanceForSummary !== null ? $openingBalanceForSummary : ($dateFrom ? null : ($paymentMethod->current_balance ?? 0)),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'statements' => $statements,
                'outlets' => $outlets,
                'payment_methods' => $paymentMethods,
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * Get Transaction History Report
     * Payment method wise transaction history from all modules: Sale, Sale Return, Installment Sale,
     * Installment Collection, Customer Receive, Income, Purchase, Purchase Return, Supplier Payment,
     * Expense, Servicing, Deposit, Withdraw, Salary, Employee Advance Payment.
     */
    public function transactionHistory(Request $request)
    {
        $companyId = session('company.company_id');
        $paymentMethodId = $request->get('payment_method_id');
        $outletId = $request->get('outlet_id') ? (int) $request->get('outlet_id') : null;
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if (! $paymentMethodId) {
            return response()->json([
                'success' => true,
                'data' => [
                    'transactions' => [],
                    'summary' => ['total_transactions' => 0, 'total_amount' => formatAmount(0)],
                ],
            ]);
        }

        $pmId = (int) $paymentMethodId;
        $paymentMethod = PaymentMethod::find($pmId);
        $paymentMethodName = $paymentMethod ? $paymentMethod->name : 'N/A';
        $transactions = [];
        $base = ['payment_method' => $paymentMethodName, 'payment_method_id' => $pmId];

        $dateFilter = function ($q) use ($dateFrom, $dateTo) {
            if ($dateFrom) {
                $q->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $q->whereDate('date', '<=', $dateTo);
            }
        };

        // Sale (SalePayment: payment_id)
        $salePayments = SalePayment::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('payment_id', $pmId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        if ($dateFrom) {
            $salePayments->where(function ($q) use ($dateFrom) {
                $q->whereDate('date', '>=', $dateFrom)->orWhere(fn ($q2) => $q2->whereNull('date')->whereDate('date', '>=', $dateFrom));
            });
        }
        if ($dateTo) {
            $salePayments->where(function ($q) use ($dateTo) {
                $q->whereDate('date', '<=', $dateTo)->orWhere(fn ($q2) => $q2->whereNull('date')->whereDate('date', '<=', $dateTo));
            });
        }
        foreach ($salePayments->with('sale')->get() as $sp) {
            $d = $sp->date ?? $sp->date;
            $transactions[] = array_merge($base, [
                'date_raw' => $d,
                'reference_no' => $sp->sale ? ($sp->sale->reference_no ?? '-') : '-',
                'type' => 'Sale',
                'amount_raw' => $sp->amount,
                'created_at_raw' => $sp->created_at,
            ]);
        }

        // Sale Return
        $saleReturns = SaleReturn::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_method_id', $pmId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        $dateFilter($saleReturns);
        foreach ($saleReturns->get() as $sr) {
            $transactions[] = array_merge($base, [
                'date_raw' => $sr->date,
                'reference_no' => $sr->reference_no ?? '-',
                'type' => 'Sale Return',
                'amount_raw' => $sr->paid,
                'created_at_raw' => $sr->created_at,
            ]);
        }

        // Installment Sale (down payment)
        $instSales = InstallmentSale::where('del_status', 'Live')->where('company_id', $companyId)->where('down_payment', '>', 0)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        $dateFilter($instSales);
        foreach ($instSales->get() as $is) {
            $transactions[] = array_merge($base, [
                'date_raw' => $is->date,
                'reference_no' => $is->reference_no ?? '-',
                'type' => 'Installment Sale',
                'amount_raw' => $is->down_payment,
                'created_at_raw' => $is->created_at,
            ]);
        }

        // Installment Collection
        $instDetails = InstallmentSaleDetail::whereHas('installmentSale', function ($q) use ($companyId, $outletId) {
            $q->where('del_status', 'Live')->where('company_id', $companyId);
            if ($outletId) {
                $q->where('outlet_id', $outletId);
            }
        })->where('payment_method_id', $pmId)->where('del_status', 'Live')->whereIn('paid_status', ['Paid', 'Partial'])->where('paid_amount', '>', 0);
        if ($dateFrom) {
            $instDetails->where(function ($q) use ($dateFrom) {
                $q->whereDate('paid_date', '>=', $dateFrom)->orWhere(fn ($q2) => $q2->whereNull('paid_date')->whereDate('created_at', '>=', $dateFrom));
            });
        }
        if ($dateTo) {
            $instDetails->where(function ($q) use ($dateTo) {
                $q->whereDate('paid_date', '<=', $dateTo)->orWhere(fn ($q2) => $q2->whereNull('paid_date')->whereDate('created_at', '<=', $dateTo));
            });
        }
        foreach ($instDetails->with('installmentSale')->get() as $id) {
            $d = $id->paid_date ?? $id->created_at;
            $transactions[] = array_merge($base, [
                'date_raw' => $d,
                'reference_no' => $id->installmentSale ? ($id->installmentSale->reference_no ?? '-') : '-',
                'type' => 'Installment Collection',
                'amount_raw' => $id->paid_amount,
                'created_at_raw' => $id->created_at,
            ]);
        }

        // Customer Receive
        $custRecv = CustomerReceive::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_method_id', $pmId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        $dateFilter($custRecv);
        foreach ($custRecv->get() as $r) {
            $transactions[] = array_merge($base, [
                'date_raw' => $r->date,
                'reference_no' => $r->reference_no ?? '-',
                'type' => 'Customer Receive',
                'amount_raw' => $r->amount,
                'created_at_raw' => $r->created_at,
            ]);
        }

        // Income
        $incomes = Income::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_method_id', $pmId);
        $dateFilter($incomes);
        foreach ($incomes->get() as $inc) {
            $transactions[] = array_merge($base, [
                'date_raw' => $inc->date ?? $inc->created_at,
                'reference_no' => $inc->reference_no ?? '-',
                'type' => 'Income',
                'amount_raw' => $inc->amount,
                'created_at_raw' => $inc->created_at,
            ]);
        }

        // Purchase Return
        $purReturns = PurchaseReturn::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_method_id', $pmId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        $dateFilter($purReturns);
        foreach ($purReturns->get() as $pr) {
            $amt = $pr->total_return_amount ?? 0;
            if ($amt > 0) {
                $transactions[] = array_merge($base, [
                    'date_raw' => $pr->date,
                    'reference_no' => $pr->reference_no ?? '-',
                    'type' => 'Purchase Return',
                    'amount_raw' => $amt,
                    'created_at_raw' => $pr->created_at ?? null,
                ]);
            }
        }

        // Deposit
        $deposits = DepositWithdraw::where('del_status', 'Live')->where('payment_method_id', $pmId)->where('type', 'Deposit')->where('company_id', $companyId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        $dateFilter($deposits);
        foreach ($deposits->get() as $dw) {
            $transactions[] = array_merge($base, [
                'date_raw' => $dw->date,
                'reference_no' => $dw->reference_no ?? '-',
                'type' => 'Deposit',
                'amount_raw' => $dw->amount,
                'created_at_raw' => $dw->created_at,
            ]);
        }

        // Purchase (PurchasePayment: payment_id)
        $purPayments = PurchasePayment::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_id', $pmId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        if ($dateFrom) {
            $purPayments->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $purPayments->whereDate('date', '<=', $dateTo);
        }
        foreach ($purPayments->with('purchase')->get() as $pp) {
            $transactions[] = array_merge($base, [
                'date_raw' => $pp->date,
                'reference_no' => $pp->purchase ? ($pp->purchase->reference_no ?? $pp->purchase->invoice_no ?? '-') : '-',
                'type' => 'Purchase',
                'amount_raw' => $pp->amount,
                'created_at_raw' => $pp->created_at,
            ]);
        }

        // Supplier Payment
        $supPayments = SupplierPayment::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_method_id', $pmId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        $dateFilter($supPayments);
        foreach ($supPayments->get() as $sp) {
            $transactions[] = array_merge($base, [
                'date_raw' => $sp->date,
                'reference_no' => $sp->reference_no ?? '-',
                'type' => 'Supplier Payment',
                'amount_raw' => $sp->amount,
                'created_at_raw' => $sp->created_at,
            ]);
        }

        // Expense
        $expenses = Expense::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_method_id', $pmId);
        $dateFilter($expenses);
        foreach ($expenses->get() as $exp) {
            $transactions[] = array_merge($base, [
                'date_raw' => $exp->date,
                'reference_no' => $exp->reference_no ?? '-',
                'type' => 'Expense',
                'amount_raw' => $exp->amount,
                'created_at_raw' => $exp->created_at,
            ]);
        }

        // Servicing
        $servicings = Servicing::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_method_id', $pmId)->where('paid_amount', '>', 0)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        $dateFilter($servicings);
        foreach ($servicings->get() as $svc) {
            $transactions[] = array_merge($base, [
                'date_raw' => $svc->date,
                'reference_no' => $svc->reference_no ?? '-',
                'type' => 'Servicing',
                'amount_raw' => $svc->paid_amount,
                'created_at_raw' => $svc->created_at,
            ]);
        }

        // Withdraw
        $withdraws = DepositWithdraw::where('del_status', 'Live')->where('payment_method_id', $pmId)->where('type', 'Withdraw')->where('company_id', $companyId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
        $dateFilter($withdraws);
        foreach ($withdraws->get() as $dw) {
            $transactions[] = array_merge($base, [
                'date_raw' => $dw->date,
                'reference_no' => $dw->reference_no ?? '-',
                'type' => 'Withdraw',
                'amount_raw' => $dw->amount,
                'created_at_raw' => $dw->created_at,
            ]);
        }

        // Salary
        $salaryPayments = SalaryPayment::where('del_status', 'Live')->where('payment_method_id', $pmId)->where('company_id', $companyId)->with('salary');
        if ($dateFrom) {
            $salaryPayments->whereHas('salary', fn ($q) => $q->whereDate('generated_date', '>=', $dateFrom));
        }
        if ($dateTo) {
            $salaryPayments->whereHas('salary', fn ($q) => $q->whereDate('generated_date', '<=', $dateTo));
        }
        foreach ($salaryPayments->get() as $sp) {
            $d = $sp->salary ? $sp->salary->generated_date : $sp->created_at;
            $transactions[] = array_merge($base, [
                'date_raw' => $d,
                'reference_no' => $sp->salary ? ($sp->salary->reference_no ?? '-') : '-',
                'type' => 'Salary',
                'amount_raw' => $sp->amount,
                'created_at_raw' => $sp->created_at,
            ]);
        }

        // Employee Advance Payment
        $empAdvances = EmployeeAdvancePayment::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_method_id', $pmId);
        $dateFilter($empAdvances);
        foreach ($empAdvances->get() as $ea) {
            $transactions[] = array_merge($base, [
                'date_raw' => $ea->date,
                'reference_no' => $ea->reference_no ?? '-',
                'type' => 'Employee Advance Payment',
                'amount_raw' => $ea->amount,
                'created_at_raw' => $ea->created_at,
            ]);
        }

        // Sort by date desc, then by created_at
        usort($transactions, function ($a, $b) {
            $da = $a['date_raw'] ?? null;
            $db = $b['date_raw'] ?? null;
            if (! $da || ! $db) {
                return 0;
            }
            $tda = $da instanceof \DateTimeInterface ? $da->format('Y-m-d H:i:s') : (is_string($da) ? $da : '');
            $tdb = $db instanceof \DateTimeInterface ? $db->format('Y-m-d H:i:s') : (is_string($db) ? $db : '');
            $cmp = strcmp($tdb, $tda);
            if ($cmp !== 0) {
                return $cmp;
            }
            $ca = $a['created_at_raw'] ?? null;
            $cb = $b['created_at_raw'] ?? null;
            if (! $ca || ! $cb) {
                return 0;
            }
            $tca = $ca instanceof \DateTimeInterface ? $ca->format('Y-m-d H:i:s') : (string) $ca;
            $tcb = $cb instanceof \DateTimeInterface ? $cb->format('Y-m-d H:i:s') : (string) $cb;
            return strcmp($tcb, $tca);
        });

        $totalAmount = 0;
        foreach ($transactions as $i => &$t) {
            $t['sn'] = $i + 1;
            $t['date'] = $t['date_raw'] ? formatDate($t['date_raw']) : '-';
            $t['amount'] = formatAmount($t['amount_raw'] ?? 0);
            $t['created_at'] = ! empty($t['created_at_raw']) ? formatDateTime($t['created_at_raw']) : 'N/A';
            $totalAmount += (float) ($t['amount_raw'] ?? 0);
            unset($t['date_raw'], $t['amount_raw'], $t['created_at_raw']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions,
                'summary' => [
                    'total_transactions' => count($transactions),
                    'total_amount' => formatAmount($totalAmount),
                ],
            ],
        ]);
    }

    
    /**
     * Show Account Balance Report View
     */
    public function showAccountBalance()
    {
        $companyId = session('company.company_id');
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'address', 'phone')
            ->get();
        return view('accounting::reports.account-balance', compact('outlets'));
    }
    /**
     * Show Account Statement Report View
     */
    public function showAccountStatement()
    {
        return view('accounting::reports.account-statement');
    }
    /**
     * Show Transaction History Report View
     */
    public function showTransactionHistory()
    {
        $companyId = session('company.company_id');
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'address', 'phone')
            ->get();
        $paymentMethods = PaymentMethod::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('status', 'Enable')
            ->select('id', 'name')
            ->get();
        return view('accounting::reports.transaction-history', compact('outlets', 'paymentMethods'));
    }

    /**
     * Get filter options for reports
     */
    public function getFilterOptions()
    {
        $companyId = session('company.company_id');
        
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'address', 'phone')
            ->get();
        
        $paymentMethods = PaymentMethod::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('status', 'Enable')
            ->select('id', 'name')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'outlets' => $outlets,
                'payment_methods' => $paymentMethods,
            ],
        ]);
    }

    /**
     * Get Trial Balance Report (classic format).
     * Each account appears once with balance in Debit OR Credit column. Total Debit = Total Credit.
     * Debit accounts: Payment methods (Cash, Bank, etc.), Customer Due, Installment Receivable,
     * Employee Advance, Purchase, Expense, Salary Expense, Withdraw.
     * Credit accounts: Sales, Service Income, Customer Advance, Supplier Due, Owner Capital (balancing).
     */
    public function trialBalance(Request $request)
    {
        $companyId = session('company.company_id');
        $outletId = $request->get('outlet_id') ? (int) $request->get('outlet_id') : null;
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $trialBalance = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $sn = 1;

        $baseQuery = function ($table) use ($companyId, $outletId, $dateFrom, $dateTo) {
            $q = DB::table($table)->where('del_status', 'Live')->where('company_id', $companyId);
            if ($outletId && in_array($table, ['sales', 'purchases', 'installment_sales'], true)) {
                $q->where('outlet_id', $outletId);
            }
            $dateCol = $table === 'sales' ? 'sale_date' : 'date';
            if ($dateFrom) {
                $q->whereDate($dateCol, '>=', $dateFrom);
            }
            if ($dateTo) {
                $q->whereDate($dateCol, '<=', $dateTo);
            }
            return $q;
        };

        // --- Debit accounts (one balance per account, put in Debit column) ---

        // 1. Payment methods (Cash, bKash, Bank, etc.)
        $paymentMethods = PaymentMethod::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('status', 'Enable')
            ->where('account_type', '!=', 'Loyalty Point')
            ->orderBy('name')
            ->get();

        foreach ($paymentMethods as $pm) {
            $balance = (float) $this->getPaymentMethodCurrentBalance((int) $pm->id, $companyId, $outletId);
            if ($balance > 0) {
                $trialBalance[] = ['sn' => $sn++, 'title' => $pm->name, 'debit' => formatAmount(round($balance, 2)), 'credit' => ''];
                $totalDebit += $balance;
            } elseif ($balance < 0) {
                $trialBalance[] = ['sn' => $sn++, 'title' => $pm->name, 'debit' => '', 'credit' => formatAmount(round(abs($balance), 2))];
                $totalCredit += abs($balance);
            }
        }

        // 2. Customer Due (asset)
        $customerDue = 0.0;
        if (DB::getSchemaBuilder()->hasTable('sales')) {
            $customerDue = (float) (clone $baseQuery('sales'))->sum('due_amount');
        }
        if ($customerDue > 0) {
            $trialBalance[] = ['sn' => $sn++, 'title' => 'Customer Due', 'debit' => formatAmount(round($customerDue, 2)), 'credit' => ''];
            $totalDebit += $customerDue;
        }

        // 3. Installment Receivable (asset): total - down_payment - sum(installment_sale_details.paid_amount) per sale
        $installmentReceivable = (float) $this->computeInstallmentReceivable($companyId, $outletId, $dateFrom, $dateTo);
        if ($installmentReceivable > 0) {
            $trialBalance[] = ['sn' => $sn++, 'title' => 'Installment Receivable', 'debit' => formatAmount(round($installmentReceivable, 2)), 'credit' => ''];
            $totalDebit += $installmentReceivable;
        }

        // 4. Employee Advance (asset – amount given to employees)
        $employeeAdvance = (float) EmployeeAdvancePayment::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->when($dateFrom, fn ($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('date', '<=', $dateTo))
            ->sum('amount');
        if ($employeeAdvance > 0) {
            $trialBalance[] = ['sn' => $sn++, 'title' => 'Employee Advance', 'debit' => formatAmount(round($employeeAdvance, 2)), 'credit' => ''];
            $totalDebit += $employeeAdvance;
        }

        // 5. Purchase (expense)
        $purchase = (float) PurchasePayment::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->when($dateFrom, fn ($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('date', '<=', $dateTo))
            ->sum('amount');
        if ($purchase > 0) {
            $trialBalance[] = ['sn' => $sn++, 'title' => 'Purchase', 'debit' => formatAmount(round($purchase, 2)), 'credit' => ''];
            $totalDebit += $purchase;
        }

        // 6. Expense
        $expense = (float) Expense::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->when($dateFrom, fn ($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('date', '<=', $dateTo))
            ->sum('amount');
        if ($expense > 0) {
            $trialBalance[] = ['sn' => $sn++, 'title' => 'Expense', 'debit' => formatAmount(round($expense, 2)), 'credit' => ''];
            $totalDebit += $expense;
        }

        // 7. Salary Expense
        $salaryExpense = (float) SalaryPayment::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->when($dateFrom, fn ($q) => $q->whereHas('salary', fn ($q) => $q->whereDate('generated_date', '>=', $dateFrom)))
            ->when($dateTo, fn ($q) => $q->whereHas('salary', fn ($q) => $q->whereDate('generated_date', '<=', $dateTo)))
            ->sum('amount');
        if ($salaryExpense > 0) {
            $trialBalance[] = ['sn' => $sn++, 'title' => 'Salary Expense', 'debit' => formatAmount(round($salaryExpense, 2)), 'credit' => ''];
            $totalDebit += $salaryExpense;
        }

        // 8. Withdraw
        $withdraw = (float) DepositWithdraw::where('del_status', 'Live')
            ->where('type', 'Withdraw')
            ->where('company_id', $companyId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->when($dateFrom, fn ($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('date', '<=', $dateTo))
            ->sum('amount');
        if ($withdraw > 0) {
            $trialBalance[] = ['sn' => $sn++, 'title' => 'Withdraw', 'debit' => formatAmount(round($withdraw, 2)), 'credit' => ''];
            $totalDebit += $withdraw;
        }

        // --- Credit accounts (balance in Credit column) ---

        // 9. Sales (revenue)
        $sales = 0.0;
        if (DB::getSchemaBuilder()->hasTable('sales')) {
            $sales = (float) (clone $baseQuery('sales'))->sum('total_payable');
        }
        if ($sales > 0) {
            $trialBalance[] = ['sn' => $sn++, 'title' => 'Sales', 'debit' => '', 'credit' => formatAmount(round($sales, 2))];
            $totalCredit += $sales;
        }

        // 10. Service Income (Income module)
        $serviceIncome = (float) Income::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->when($dateFrom, fn ($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('date', '<=', $dateTo))
            ->sum('amount');
        if ($serviceIncome > 0) {
            $trialBalance[] = ['sn' => $sn++, 'title' => 'Service Income', 'debit' => '', 'credit' => formatAmount(round($serviceIncome, 2))];
            $totalCredit += $serviceIncome;
        }

        // 11. Customer Advance (liability – if no data, omit or 0)
        $customerAdvance = 0.0;
        if ($customerAdvance > 0) {
            $trialBalance[] = ['sn' => $sn++, 'title' => 'Customer Advance', 'debit' => '', 'credit' => formatAmount(round($customerAdvance, 2))];
            $totalCredit += $customerAdvance;
        }

        // 12. Supplier Due (liability)
        $supplierDue = (float) Purchase::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->when($dateFrom, fn ($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('date', '<=', $dateTo))
            ->sum('due_amount');
        if ($supplierDue > 0) {
            $trialBalance[] = ['sn' => $sn++, 'title' => 'Supplier Due', 'debit' => '', 'credit' => formatAmount(round($supplierDue, 2))];
            $totalCredit += $supplierDue;
        }

        // 13. Owner Capital (balancing figure so Total Debit = Total Credit)
        $ownerCapital = $totalDebit - $totalCredit;
        if ($ownerCapital > 0) {
            $trialBalance[] = ['sn' => $sn++, 'title' => 'Owner Capital', 'debit' => '', 'credit' => formatAmount(round($ownerCapital, 2))];
            $totalCredit += $ownerCapital;
        } elseif ($ownerCapital < 0) {
            $trialBalance[] = ['sn' => $sn++, 'title' => 'Owner Capital', 'debit' => formatAmount(round(abs($ownerCapital), 2)), 'credit' => ''];
            $totalDebit += abs($ownerCapital);
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        return response()->json([
            'success' => true,
            'data' => [
                'trialBalance' => $trialBalance,
                'summary' => [
                    'totalDebit' => formatAmount($totalDebit),
                    'totalCredit' => formatAmount($totalCredit),
                ],
            ],
        ]);
    }

    /**
     * Installment Receivable = amount still to be collected from customers.
     * For each installment_sale: (total - down_payment) - SUM(installment_sale_details.paid_amount).
     * installment_sales: total, down_payment, remaining.
     * installment_sale_details: amount_of_payment, paid_amount (per schedule line).
     */
    private function computeInstallmentReceivable(int $companyId, ?int $outletId, ?string $dateFrom, ?string $dateTo): float
    {
        $sales = InstallmentSale::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->when($dateFrom, fn ($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('date', '<=', $dateTo))
            ->get();

        $receivable = 0.0;
        foreach ($sales as $sale) {
            $totalMinusDown = (float) $sale->total - (float) $sale->down_payment;
            $paidFromDetails = (float) $sale->installmentDetails()->where('del_status', 'Live')->sum('paid_amount');
            $due = $totalMinusDown - $paidFromDetails;
            if ($due > 0) {
                $receivable += $due;
            }
        }

        return $receivable;
    }

    /**
     * Get current balance for one payment method (same formula as accountBalance).
     */
    private function getPaymentMethodCurrentBalance(int $pmId, int $companyId, ?int $outletId): float
    {
        $openingBalance = (float) (PaymentMethod::find($pmId)->current_balance ?? 0);
        $sale = (float) SalePayment::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_id', $pmId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))->sum('amount');
        $saleReturn = (float) SaleReturn::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_method_id', $pmId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))->sum('paid');
        $installmentSale = (float) InstallmentSale::where('del_status', 'Live')->where('company_id', $companyId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))->sum('down_payment');
        $installmentCollection = (float) InstallmentSaleDetail::whereHas('installmentSale', function ($q) use ($companyId, $outletId) {
            $q->where('del_status', 'Live')->where('company_id', $companyId);
            if ($outletId) { $q->where('outlet_id', $outletId); }
        })->where('payment_method_id', $pmId)->where('del_status', 'Live')->whereIn('paid_status', ['Paid', 'Partial'])->sum('paid_amount');
        $customerReceive = (float) CustomerReceive::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_method_id', $pmId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))->sum('amount');
        $income = (float) Income::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_method_id', $pmId)->sum('amount');
        $purchaseReturn = (float) PurchaseReturn::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_method_id', $pmId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))->sum('total_return_amount');
        $deposit = (float) DepositWithdraw::where('del_status', 'Live')->where('payment_method_id', $pmId)->where('type', 'Deposit')->where('company_id', $companyId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))->sum('amount');
        $purchase = (float) PurchasePayment::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_id', $pmId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))->sum('amount');
        $supplierPayment = (float) SupplierPayment::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_method_id', $pmId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))->sum('amount');
        $expense = (float) Expense::where('del_status', 'Live')->where('payment_method_id', $pmId)->where('company_id', $companyId)->sum('amount');
        $servicing = (float) Servicing::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_method_id', $pmId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))->sum('paid_amount');
        $withdraw = (float) DepositWithdraw::where('del_status', 'Live')->where('payment_method_id', $pmId)->where('type', 'Withdraw')->where('company_id', $companyId)->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))->sum('amount');
        $salary = (float) SalaryPayment::where('del_status', 'Live')->where('payment_method_id', $pmId)->where('company_id', $companyId)->sum('amount');
        $employeeAdvance = (float) EmployeeAdvancePayment::where('del_status', 'Live')->where('company_id', $companyId)->where('payment_method_id', $pmId)->sum('amount');
        return $openingBalance + $sale - $saleReturn + $installmentSale + $installmentCollection + $customerReceive + $income + $purchaseReturn + $deposit - $purchase - $supplierPayment - $expense - $servicing - $withdraw - $salary - $employeeAdvance;
    }

    /**
     * Get Balance Sheet Report
     */
    public function balanceSheet(Request $request)
    {
        $companyId = session('company.company_id');
        $outletId = $request->get('outlet_id') ? (int) $request->get('outlet_id') : null;
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        // Assets
        $assets = [];
        $totalAssets = 0;

        // 1. Customer Due (Asset)
        $customerDue = 0;
        if (DB::getSchemaBuilder()->hasTable('sales')) {
            $customerDueQuery = DB::table('sales')
                ->where('del_status', 'Live')
                ->where('company_id', $companyId);
            if ($outletId) {
                $customerDueQuery->where('outlet_id', $outletId);
            }
            if ($dateFrom) {
                $customerDueQuery->whereDate('order_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $customerDueQuery->whereDate('order_date', '<=', $dateTo);
            }
            $customerDue = $customerDueQuery->sum('due_amount') ?? 0;
        }
        $assets[] = ['sn' => 1, 'title' => 'Customer Due', 'amount' => formatAmount($customerDue)];
        $totalAssets += $customerDue;

        // 2. Current Stock (Asset) – StockService::getStockEvaluation
        $currentStock = 0;
        try {
            $stockService = app(StockService::class);
            $evaluation = $stockService->getStockEvaluation(['outlet_id' => $outletId]);
            $currentStock = (float) ($evaluation['stock_value'] ?? 0);
        } catch (\Throwable $e) {
            $currentStock = 0;
        }
        $assets[] = ['sn' => 2, 'title' => 'Current Stock', 'amount' => formatAmount($currentStock)];
        $totalAssets += $currentStock;

        // 3. Payment Methods (accountBalance structure)
        $paymentMethods = PaymentMethod::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('status', 'Enable')
            ->where('account_type', '!=', 'Loyalty Point')
            ->get();
        $sn = 3;
        foreach ($paymentMethods as $paymentMethod) {
            $balance = $this->getPaymentMethodCurrentBalance($paymentMethod->id, $companyId, $outletId);
            if ($balance != 0) {
                $assets[] = ['sn' => $sn++, 'title' => $paymentMethod->name, 'amount' => formatAmount($balance)];
                $totalAssets += $balance;
            }
        }

        // Liabilities
        $liabilities = [];
        $totalLiabilities = 0;
        $supplierDueQuery = Purchase::where('del_status', 'Live')->where('company_id', $companyId);
        if ($outletId) {
            $supplierDueQuery->where('outlet_id', $outletId);
        }
        if ($dateFrom) {
            $supplierDueQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $supplierDueQuery->whereDate('date', '<=', $dateTo);
        }
        $supplierDue = $supplierDueQuery->sum('due_amount') ?? 0;
        $liabilities[] = ['sn' => 1, 'title' => 'Supplier Due', 'amount' => formatAmount($supplierDue)];
        $totalLiabilities += $supplierDue;

        $summary = [
            'totalAssets' => formatAmount($totalAssets),
            'totalLiabilities' => formatAmount($totalLiabilities),
            'netWorth' => formatAmount($totalAssets - $totalLiabilities),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'assets' => $assets,
                'liabilities' => $liabilities,
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * Show Trial Balance Report View
     */
    public function showTrialBalance()
    {
        return view('accounting::reports.trial-balance');
    }

    /**
     * Show Balance Sheet Report View
     */
    public function showBalanceSheet()
    {
        return view('accounting::reports.balance-sheet');
    }
}

