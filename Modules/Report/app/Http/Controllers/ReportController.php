<?php

namespace Modules\Report\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sale\Models\Sale;
use Modules\Sale\Models\Customer;
use Modules\Sale\Models\CustomerReceive;
use Modules\Sale\Models\Servicing;
use Modules\Configuration\Models\Outlet;
use Modules\Administrator\Models\Attendance;
use App\Models\User;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Models\Supplier;
use Modules\Purchase\Models\SupplierPayment;
use Modules\Purchase\Models\PurchaseReturn;
use Modules\Purchase\Models\PurchasePayment;
use Modules\Sale\Models\SaleReturn;
use Modules\Sale\Models\InstallmentSale;
use Modules\Sale\Models\InstallmentSaleDetail;
use Modules\Sale\Models\ComboSale;
use Modules\Sale\Models\SalePayment;
use Modules\Sale\Models\SaleDetail;
use Modules\Accounting\Models\Expense;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\Models\Income;
use Modules\Stock\Models\Damage;
use Modules\Sale\Models\Register;
use Modules\Report\Services\GstReportService;

class ReportController extends Controller
{
    // ==================== Report View Methods ====================

    /**
     * Register Report - View + API (Outlet*, Date*, Register optional; closed registers for date)
     */
    public function registerReport(Request $request)
    {
        $companyId = session('company.company_id');

        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();

        // API: return closed registers list for dropdown (when outlet + date selected)
        if ($request->ajax() || $request->wantsJson()) {
            $action = $request->get('action');
            $outletId = $request->get('outlet_id');
            $date = $request->get('date');
            $registerId = $request->get('register_id');

            if ($action === 'registers') {
                if (!$outletId || !$date) {
                    return response()->json(['success' => true, 'registers' => []]);
                }
                $registers = Register::with('user')
                    ->where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->where('outlet_id', $outletId)
                    ->where('register_status', 2)
                    ->whereRaw('DATE(closing_balance_date_time) = ?', [$date])
                    ->orderBy('closing_balance_date_time', 'desc')
                    ->get(['id', 'user_id', 'opening_balance_date_time', 'closing_balance_date_time']);
                $list = [];
                foreach ($registers as $r) {
                    $empName = $r->user ? $r->user->name : __('N/A');
                    $openDt = $r->opening_balance_date_time ? formatDateTime($r->opening_balance_date_time) : '-';
                    $list[] = ['id' => $r->id, 'label' => $empName . ' - ' . $openDt];
                }
                return response()->json(['success' => true, 'registers' => $list]);
            }

            // Report data: outlet + date required
            if (!$outletId || !$date) {
                return response()->json(['success' => false, 'message' => __('Outlet and Date are required')], 400);
            }

            $query = Register::with('user')
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('outlet_id', $outletId)
                ->where('register_status', 2)
                ->whereRaw('DATE(closing_balance_date_time) = ?', [$date])
                ->orderBy('closing_balance_date_time', 'desc');

            if ($registerId) {
                $query->where('id', $registerId);
            }

            $registers = $query->get();

            $selectedOutlet = Outlet::where('id', $outletId)->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')->first();

            $selectedRegister = null;
            if ($registerId) {
                $selectedRegister = $registers->first();
            }

            $rows = [];
            $sn = 1;
            foreach ($registers as $r) {
                $empName = $r->user ? $r->user->name : '-';
                $openDt = $r->opening_balance_date_time ? formatDateTime($r->opening_balance_date_time) : '-';
                $closeDt = $r->closing_balance_date_time ? formatDateTime($r->closing_balance_date_time) : '-';
                $paymentMethodsSale = $r->payment_methods_sale;
                if (is_string($paymentMethodsSale)) {
                    $decoded = @json_decode($paymentMethodsSale, true);
                    $paymentMethodsSale = is_array($decoded) ? $decoded : [];
                }
                $saleInPaymentMethod = is_array($paymentMethodsSale)
                    ? implode(', ', array_map(function ($k, $v) { return $k . ': ' . formatAmount($v); }, array_keys($paymentMethodsSale), array_values($paymentMethodsSale)))
                    : (string) $paymentMethodsSale;

                $rows[] = [
                    'sn' => $sn++,
                    'employee' => $empName,
                    'opening_date_time' => $openDt,
                    'opening_balance' => formatAmount($r->opening_balance ?? 0),
                    'sale_paid_amount' => formatAmount($r->sale_paid_amount ?? 0),
                    'sale_return' => formatAmount($r->refund_amount ?? 0),
                    'customer_receive' => formatAmount($r->customer_due_receive ?? 0),
                    'purchase' => formatAmount($r->total_purchase ?? 0),
                    'purchase_return' => formatAmount($r->total_purchase_return ?? 0),
                    'supplier_payment' => formatAmount($r->total_due_payment ?? 0),
                    'expense' => formatAmount($r->total_expense ?? 0),
                    'down_payment' => formatAmount($r->total_downpayment ?? 0),
                    'installment_collection' => formatAmount($r->total_installmentcollection ?? 0),
                    'servicing' => formatAmount($r->total_servicing ?? 0),
                    'closing_balance' => formatAmount($r->closing_balance ?? 0),
                    'closing_date_time' => $closeDt,
                    'sale_in_payment_method' => $saleInPaymentMethod ?: '-',
                ];
            }

            $filterInfo = [
                'outlet' => $selectedOutlet ? [
                    'name' => $selectedOutlet->outlet_name,
                    'phone' => $selectedOutlet->phone,
                    'address' => $selectedOutlet->address,
                ] : null,
                'date' => formatDate($date) ?? $date,
                'register' => $selectedRegister ? [
                    'id' => $selectedRegister->id,
                    'label' => ($selectedRegister->user ? $selectedRegister->user->name : '') . ' - ' . ($selectedRegister->opening_balance_date_time ? formatDateTime($selectedRegister->opening_balance_date_time) : ''),
                ] : null,
                'generated_at' => formatDateTime(now()),
                'generated_by' => auth()->user() ? [
                    'name' => auth()->user()->name ?? '',
                    'phone' => auth()->user()->phone ?? '',
                ] : null,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'registers' => $rows,
                    'filter_info' => $filterInfo,
                ],
            ]);
        }

        return view('report::register-report', compact('outlets'));
    }

    /**
     * Get Z Report Data - Combined view and API (single date + outlet required)
     */
    public function zReport(Request $request)
    {
        $companyId = session('company.company_id');
        $date = $request->get('date');
        $outletId = $request->get('outlet_id');
        $dateFrom = $date;
        $dateTo = $date;

        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        $companyName = session('company.company_name', config('app.name'));

        if (!$request->ajax() && !$request->wantsJson()) {
            return view('report::z-report', compact('outlets', 'companyName'));
        }

        if (!$date || !$outletId) {
            return response()->json(['success' => false, 'message' => __('Date and Outlet are required')], 400);
        }

        $selectedOutlet = Outlet::where('id', $outletId)
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name', 'phone', 'address')
            ->first();

        $saleQuery = Sale::where('del_status', 'Live')->where('company_id', $companyId)->whereBetween('sale_date', [$dateFrom, $dateTo]);
        if ($outletId) {
            $saleQuery->where('outlet_id', $outletId);
        }
        $salesAgg = $saleQuery->selectRaw('
            COALESCE(SUM(sub_total), 0) as total_sub_total,
            COALESCE(SUM(total_discount_amount), 0) as total_discount,
            COALESCE(SUM(due_amount), 0) as total_due,
            COALESCE(SUM(delivery_charge), 0) as total_delivery_charge,
            COALESCE(SUM(paid_amount), 0) as total_paid,
            COALESCE(SUM(vat), 0) as total_vat,
            COALESCE(SUM(total_payable), 0) as total_payable
        ')->first();

        $saleReturnQuery = SaleReturn::where('del_status', 'Live')->where('company_id', $companyId)->whereBetween('date', [$dateFrom, $dateTo]);
        if ($outletId) {
            $saleReturnQuery->where('outlet_id', $outletId);
        }
        $saleReturnTotal = (float) $saleReturnQuery->sum('total_return_amount');

        $purchaseQuery = Purchase::where('del_status', 'Live')->where('company_id', $companyId)->whereBetween('date', [$dateFrom, $dateTo]);
        if ($outletId) {
            $purchaseQuery->where('outlet_id', $outletId);
        }
        $purchaseAgg = $purchaseQuery->selectRaw('COALESCE(SUM(grand_total), 0) as total_grand, COALESCE(SUM(paid), 0) as total_paid')->first();
        $purchaseTotal = (float) ($purchaseAgg->total_grand ?? 0);
        $purchasePaidTotal = (float) ($purchaseAgg->total_paid ?? 0);

        $purchaseReturnQuery = PurchaseReturn::where('del_status', 'Live')->where('company_id', $companyId)->whereBetween('date', [$dateFrom, $dateTo]);
        if ($outletId) {
            $purchaseReturnQuery->where('outlet_id', $outletId);
        }
        $purchaseReturnTotal = (float) $purchaseReturnQuery->sum('total_return_amount');

        $installmentQuery = InstallmentSale::where('del_status', 'Live')->where('company_id', $companyId)->whereBetween('date', [$dateFrom, $dateTo]);
        if ($outletId) {
            $installmentQuery->where('outlet_id', $outletId);
        }
        $installmentDownTotal = (float) $installmentQuery->sum('down_payment');
        $installmentCollectionTotal = (float) InstallmentSaleDetail::where('installment_sale_details.del_status', 'Live')
            ->join('installment_sales', 'installment_sale_details.installment_sale_id', '=', 'installment_sales.id')
            ->where('installment_sales.company_id', $companyId)
            ->whereBetween('installment_sales.date', [$dateFrom, $dateTo])
            ->when($outletId, function ($q) use ($outletId) {
                $q->where('installment_sales.outlet_id', $outletId);
            })
            ->sum('installment_sale_details.paid_amount');

        $customerReceiveQuery = CustomerReceive::where('del_status', 'Live')->where('company_id', $companyId)->whereBetween('date', [$dateFrom, $dateTo]);
        if ($outletId) {
            $customerReceiveQuery->where('outlet_id', $outletId);
        }
        $customerReceiveTotal = (float) $customerReceiveQuery->sum('amount');

        $expenseQuery = Expense::where('del_status', 'Live')->where('company_id', $companyId)->whereBetween('date', [$dateFrom, $dateTo]);
        if ($outletId) {
            $expenseQuery->where('outlet_id', $outletId);
        }
        $expenseTotal = (float) $expenseQuery->sum('amount');

        $supplierPaymentQuery = SupplierPayment::where('del_status', 'Live')->where('company_id', $companyId)->whereBetween('date', [$dateFrom, $dateTo]);
        if ($outletId) {
            $supplierPaymentQuery->where('outlet_id', $outletId);
        }
        $supplierPaymentTotal = (float) $supplierPaymentQuery->sum('amount');

        $totalItemSalesWithoutTax = (float) ($salesAgg->total_sub_total ?? 0) - (float) ($salesAgg->total_discount ?? 0);
        $saleDueAmount = (float) ($salesAgg->total_due ?? 0);
        $discountAmount = (float) ($salesAgg->total_discount ?? 0);
        $totalAmountCalc = $totalItemSalesWithoutTax - $saleDueAmount - $saleReturnTotal - $purchaseTotal + $purchaseReturnTotal
            + $installmentDownTotal + $installmentCollectionTotal + (float) ($salesAgg->total_delivery_charge ?? 0)
            + $customerReceiveTotal - $discountAmount;

        $salesAndTaxesSummary = [
            ['label' => __('Total Item Sales (Without Tax) (Incl. Discount)'), 'amount' => formatAmount($totalItemSalesWithoutTax)],
            ['label' => __('Sale Due Amount') . ' (-)', 'amount' => formatAmount($saleDueAmount)],
            ['label' => __('Sale Return Amount') . ' (-)', 'amount' => formatAmount($saleReturnTotal)],
            ['label' => __('Purchase') . ' (-)', 'amount' => formatAmount($purchaseTotal)],
            ['label' => __('Purchase Return') . ' (+)', 'amount' => formatAmount($purchaseReturnTotal)],
            ['label' => __('Installment Down payment & Collection') . ' (+)', 'amount' => formatAmount($installmentDownTotal + $installmentCollectionTotal)],
            ['label' => __('Delivery Charge') . ' (+)', 'amount' => formatAmount($salesAgg->total_delivery_charge ?? 0)],
            ['label' => __('Customer Due Receive') . ' (+)', 'amount' => formatAmount($customerReceiveTotal)],
            ['label' => __('Discount') . ' (-)', 'amount' => formatAmount($discountAmount)],
            ['label' => __('Total Amount'), 'amount' => formatAmount($totalAmountCalc)],
        ];

        $saleIds = Sale::where('del_status', 'Live')->where('company_id', $companyId)->whereBetween('sale_date', [$dateFrom, $dateTo]);
        if ($outletId) {
            $saleIds->where('outlet_id', $outletId);
        }
        $saleIds = $saleIds->pluck('id')->toArray();

        $paymentMethodBreakdown = [];
        if (!empty($saleIds)) {
            $byPayment = SalePayment::where('del_status', 'Live')
                ->whereIn('sale_id', $saleIds)
                ->selectRaw('payment_id, SUM(amount) as total')
                ->groupBy('payment_id')
                ->get();
            $pmIds = $byPayment->pluck('payment_id')->filter()->unique()->toArray();
            $paymentMethods = PaymentMethod::whereIn('id', $pmIds)->where('company_id', $companyId)->pluck('name', 'id')->toArray();
            foreach ($byPayment as $row) {
                $name = $paymentMethods[$row->payment_id] ?? __('Payment Method') . ' #' . $row->payment_id;
                $paymentMethodBreakdown[] = ['name' => $name, 'amount' => formatAmount($row->total)];
            }
        }

        $itemWiseSalesQty = 0;
        $itemWiseSalesAmount = 0;
        if (!empty($saleIds)) {
            $itemWise = SaleDetail::where('del_status', 'Live')->whereIn('sales_id', $saleIds)
                ->selectRaw('COALESCE(SUM(qty), 0) as total_qty, COALESCE(SUM(qty * menu_unit_price), 0) as total_amount')
                ->first();
            $itemWiseSalesQty = (float) ($itemWise->total_qty ?? 0);
            $itemWiseSalesAmount = (float) ($itemWise->total_amount ?? 0);
        }

        $paymentMethodsAll = PaymentMethod::where('company_id', $companyId)->orderBy('id')->get();
        $totalInHandByMethod = [];
        $inHandSummary = [];
        foreach ($paymentMethodsAll as $pm) {
            $saleP = (float) SalePayment::where('del_status', 'Live')->where('payment_id', $pm->id)->whereIn('sale_id', $saleIds ?: [0])->sum('amount');
            $saleRetP = 0;
            $purchaseIdsInRange = Purchase::where('del_status', 'Live')->where('company_id', $companyId)->whereBetween('date', [$dateFrom, $dateTo])
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->pluck('id')->toArray();
            $purchP = empty($purchaseIdsInRange) ? 0 : (float) PurchasePayment::where('del_status', 'Live')->where('payment_id', $pm->id)->whereIn('purchase_id', $purchaseIdsInRange)->sum('amount');
            $purchRetP = (float) PurchaseReturn::where('del_status', 'Live')->where('company_id', $companyId)->whereBetween('date', [$dateFrom, $dateTo])
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->where('payment_method_id', $pm->id)->sum('total_return_amount');
            $instP = (float) InstallmentSaleDetail::where('installment_sale_details.del_status', 'Live')
                ->where('installment_sale_details.payment_method_id', $pm->id)
                ->join('installment_sales', 'installment_sale_details.installment_sale_id', '=', 'installment_sales.id')
                ->where('installment_sales.company_id', $companyId)
                ->whereRaw('DATE(COALESCE(installment_sale_details.paid_date, installment_sale_details.payment_date)) BETWEEN ? AND ?', [$dateFrom, $dateTo])
                ->when($outletId, fn ($q) => $q->where('installment_sales.outlet_id', $outletId))
                ->sum('installment_sale_details.paid_amount');
            $dueRecP = (float) CustomerReceive::where('del_status', 'Live')->where('company_id', $companyId)->whereBetween('date', [$dateFrom, $dateTo])
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->where('payment_method_id', $pm->id)->sum('amount');
            $duePayP = (float) SupplierPayment::where('del_status', 'Live')->where('company_id', $companyId)->whereBetween('date', [$dateFrom, $dateTo])
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->where('payment_method_id', $pm->id)->sum('amount');
            $expP = (float) Expense::where('del_status', 'Live')->where('company_id', $companyId)->whereBetween('date', [$dateFrom, $dateTo])
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->where('payment_method_id', $pm->id)->sum('amount');
            $balance = $saleP - $saleRetP - $purchP + $purchRetP + $instP + $dueRecP - $duePayP - $expP;
            $totalInHandByMethod[] = [
                'payment_method' => $pm->name,
                'transactions' => [
                    ['name' => __('Sale') . ' (+)', 'amount' => formatAmount($saleP)],
                    ['name' => __('Sale Return') . ' (-)', 'amount' => formatAmount($saleRetP)],
                    ['name' => __('Purchase') . ' (-)', 'amount' => formatAmount($purchP)],
                    ['name' => __('Purchase Return') . ' (+)', 'amount' => formatAmount($purchRetP)],
                    ['name' => __('Installment Down payment & Collection') . ' (+)', 'amount' => formatAmount($instP)],
                    ['name' => __('Due Receive') . ' (+)', 'amount' => formatAmount($dueRecP)],
                    ['name' => __('Due Payment') . ' (-)', 'amount' => formatAmount($duePayP)],
                    ['name' => __('Expense') . ' (-)', 'amount' => formatAmount($expP)],
                    ['name' => __('Balance'), 'amount' => formatAmount($balance)],
                ],
            ];
            $inHandSummary[] = ['name' => $pm->name, 'amount' => formatAmount($balance)];
        }

        $filterInfo = [
            'outlet' => $selectedOutlet ? [
                'name' => $selectedOutlet->outlet_name,
                'phone' => $selectedOutlet->phone,
                'address' => $selectedOutlet->address,
            ] : null,
            'date' => formatDate($date) ?? $date,
            'generated_at' => formatDateTime(now()),
            'generated_by' => auth()->user() ? [
                'name' => auth()->user()->name ?? '',
                'phone' => auth()->user()->phone ?? '',
            ] : null,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'company_name' => $companyName,
                'date' => formatDate($date) ?? $date,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'filter_info' => $filterInfo,
                'sales_and_taxes_summary' => $salesAndTaxesSummary,
                'payment_method_breakdown' => $paymentMethodBreakdown,
                'payment_other_currencies' => [],
                'item_wise_sales' => [
                    'quantity_total' => $itemWiseSalesQty,
                    'amount_total' => formatAmount($itemWiseSalesAmount),
                ],
                'purchase_paid' => [['label' => __('Total'), 'amount' => formatAmount($purchasePaidTotal)]],
                'expense' => [['label' => __('Total'), 'amount' => formatAmount($expenseTotal)]],
                'supplier_payment' => [['label' => __('Total'), 'amount' => formatAmount($supplierPaymentTotal)]],
                'customer_due_receives' => [['label' => __('Total'), 'amount' => formatAmount($customerReceiveTotal)]],
                'total_in_hand' => $totalInHandByMethod,
                'in_hand_summary' => $inHandSummary,
            ],
        ]);
    }


    // ==================== Report API Methods ====================


    /**
     * Get Daily Summary Report Data
     */
    /**
     * Daily Summary Report - Combined method for both view and API
     * Shows summary of all transactions for a specific date and outlet
     */
    public function dailySummaryReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters (both required)
        $date = $request->get('date');
        $outletId = $request->get('outlet_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        // Get selected outlet details for header
        $selectedOutlet = null;
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Validate required filters
            if (!$date || !$outletId) {
                return response()->json([
                    'success' => false,
                    'message' => __('Date and Outlet are required')
                ], 400);
            }
            
            $formattedTransactions = [];
            $index = 1;
            $totalAmount = 0;
            
            // 1. Purchase transactions
            $purchases = Purchase::with(['supplier'])
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('outlet_id', $outletId)
                ->whereDate('date', $date)
                ->get();
            
            if ($purchases->count() > 0) {
                // Add section header
                $formattedTransactions[] = [
                    'is_section_header' => true,
                    'section_name' => 'Purchase',
                    'col1' => 'SN',
                    'col2' => 'Reference No',
                    'col3' => 'Supplier',
                    'col4' => 'Grand Total',
                    'col5' => 'Paid',
                    'col6' => 'Due',
                    'col7' => '',
                    'col8' => '',
                    'col9' => '',
                    'col10' => '',
                    'col11' => '',
                    'col12' => '',
                ];
                
                foreach ($purchases as $purchase) {
                    $grandTotal = floatval($purchase->grand_total ?? 0);
                    $paid = floatval($purchase->paid_amount ?? 0);
                    $due = floatval($purchase->due_amount ?? 0);
                    
                    $supplierName = $purchase->supplier ? ($purchase->supplier->phone ? $purchase->supplier->name . ' (' . $purchase->supplier->phone . ')' : $purchase->supplier->name) : '-';
                    
                    $formattedTransactions[] = [
                        'is_section_header' => false,
                        'transaction_type' => 'Purchase',
                        'sn' => $index++,
                        'reference_no' => $purchase->reference_no ?? '-',
                        'supplier' => $supplierName,
                        'grand_total' => formatAmount($grandTotal),
                        'paid' => formatAmount($paid),
                        'due' => formatAmount($due),
                    ];
                    $totalAmount += $grandTotal;
                }
            }
            
            // 2. Purchase Return transactions
            $purchaseReturns = PurchaseReturn::with(['supplier'])
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('outlet_id', $outletId)
                ->whereDate('date', $date)
                ->get();
            
            if ($purchaseReturns->count() > 0) {
                // Add section header
                $formattedTransactions[] = [
                    'is_section_header' => true,
                    'section_name' => 'Purchase Return',
                    'col1' => 'SN',
                    'col2' => 'Reference No',
                    'col3' => 'Supplier',
                    'col4' => 'Amount',
                    'col5' => '',
                    'col6' => '',
                    'col7' => '',
                    'col8' => '',
                    'col9' => '',
                    'col10' => '',
                    'col11' => '',
                    'col12' => '',
                ];
                
                foreach ($purchaseReturns as $purchaseReturn) {
                    $amount = floatval($purchaseReturn->total_return_amount ?? 0);
                    
                    $supplierName = $purchaseReturn->supplier ? ($purchaseReturn->supplier->phone ? $purchaseReturn->supplier->name . ' (' . $purchaseReturn->supplier->phone . ')' : $purchaseReturn->supplier->name) : '-';
                    
                    $formattedTransactions[] = [
                        'is_section_header' => false,
                        'transaction_type' => 'Purchase Return',
                        'sn' => $index++,
                        'reference_no' => $purchaseReturn->reference_no ?? '-',
                        'supplier' => $supplierName,
                        'amount' => formatAmount($amount),
                    ];
                    $totalAmount += $amount;
                }
            }
            
            // 3. Supplier Due Payment transactions
            $supplierPayments = SupplierPayment::with(['supplier'])
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('outlet_id', $outletId)
                ->whereDate('date', $date)
                ->get();
            
            if ($supplierPayments->count() > 0) {
                // Add section header
                $formattedTransactions[] = [
                    'is_section_header' => true,
                    'section_name' => 'Supplier Due Payment',
                    'col1' => 'SN',
                    'col2' => 'Reference No',
                    'col3' => 'Supplier',
                    'col4' => 'Amount',
                    'col5' => '',
                    'col6' => '',
                    'col7' => '',
                    'col8' => '',
                    'col9' => '',
                    'col10' => '',
                    'col11' => '',
                    'col12' => '',
                ];
                
                foreach ($supplierPayments as $supplierPayment) {
                    $amount = floatval($supplierPayment->amount ?? 0);
                    
                    $supplierName = $supplierPayment->supplier ? ($supplierPayment->supplier->phone ? $supplierPayment->supplier->name . ' (' . $supplierPayment->supplier->phone . ')' : $supplierPayment->supplier->name) : '-';
                    
                    $formattedTransactions[] = [
                        'is_section_header' => false,
                        'transaction_type' => 'Supplier Due Payment',
                        'sn' => $index++,
                        'reference_no' => $supplierPayment->reference_no ?? '-',
                        'supplier' => $supplierName,
                        'amount' => formatAmount($amount),
                    ];
                    $totalAmount += $amount;
                }
            }
            
            // 4. Sale transactions
            $sales = Sale::with(['customer'])
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('outlet_id', $outletId)
                ->whereDate('sale_date', $date)
                ->get();
            
            if ($sales->count() > 0) {
                // Add section header
                $formattedTransactions[] = [
                    'is_section_header' => true,
                    'section_name' => 'Sale',
                    'col1' => 'SN',
                    'col2' => 'Invoice No',
                    'col3' => 'Customer',
                    'col4' => 'Sub Total',
                    'col5' => 'Tax',
                    'col6' => 'Charge',
                    'col7' => 'Discount',
                    'col8' => 'Total Payable',
                    'col9' => 'Paid',
                    'col10' => 'Due',
                    'col11' => '',
                    'col12' => '',
                ];
                
                foreach ($sales as $sale) {
                    $subTotal = floatval($sale->sub_total ?? 0);
                    $tax = floatval($sale->vat ?? 0);
                    $charge = floatval($sale->delivery_charge ?? 0);
                    $discount = floatval($sale->total_discount_amount ?? 0);
                    $totalPayable = floatval($sale->total_payable ?? 0);
                    $paid = floatval($sale->paid_amount ?? 0);
                    $due = floatval($sale->due_amount ?? 0);
                    
                    $customerName = $sale->customer ? ($sale->customer->phone ? $sale->customer->name . ' (' . $sale->customer->phone . ')' : $sale->customer->name) : __('Walk-in Customer');
                    
                    $formattedTransactions[] = [
                        'is_section_header' => false,
                        'transaction_type' => 'Sale',
                        'sn' => $index++,
                        'invoice_no' => $sale->sale_no ?? '-',
                        'customer' => $customerName,
                        'sub_total' => formatAmount($subTotal),
                        'tax' => formatAmount($tax),
                        'charge' => formatAmount($charge),
                        'discount' => formatAmount($discount),
                        'total_payable' => formatAmount($totalPayable),
                        'paid' => formatAmount($paid),
                        'due' => formatAmount($due),
                    ];
                    $totalAmount += $totalPayable;
                }
            }
            
            // 5. Sale Return transactions
            $saleReturns = SaleReturn::with(['customer', 'sale'])
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('outlet_id', $outletId)
                ->whereDate('date', $date)
                ->get();
            
            if ($saleReturns->count() > 0) {
                // Add section header
                $formattedTransactions[] = [
                    'is_section_header' => true,
                    'section_name' => 'Sale Return',
                    'col1' => 'SN',
                    'col2' => 'Reference No',
                    'col3' => 'Customer',
                    'col4' => 'Return Amount',
                    'col5' => 'Paid',
                    'col6' => 'Due',
                    'col7' => '',
                    'col8' => '',
                    'col9' => '',
                    'col10' => '',
                    'col11' => '',
                    'col12' => '',
                ];
                
                foreach ($saleReturns as $saleReturn) {
                    $returnAmount = floatval($saleReturn->total_return_amount ?? 0);
                    $paid = floatval($saleReturn->paid_amount ?? 0);
                    $due = floatval($saleReturn->due_amount ?? 0);
                    
                    $customerName = $saleReturn->customer ? ($saleReturn->customer->phone ? $saleReturn->customer->name . ' (' . $saleReturn->customer->phone . ')' : $saleReturn->customer->name) : '-';
                    
                    $formattedTransactions[] = [
                        'is_section_header' => false,
                        'transaction_type' => 'Sale Return',
                        'sn' => $index++,
                        'reference_no' => $saleReturn->reference_no ?? '-',
                        'customer' => $customerName,
                        'return_amount' => formatAmount($returnAmount),
                        'paid' => formatAmount($paid),
                        'due' => formatAmount($due),
                    ];
                    $totalAmount += $returnAmount;
                }
            }
            
            // 6. Customer Due Receive transactions
            $customerReceives = CustomerReceive::with(['customer'])
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('outlet_id', $outletId)
                ->whereDate('date', $date)
                ->get();
            
            if ($customerReceives->count() > 0) {
                // Add section header
                $formattedTransactions[] = [
                    'is_section_header' => true,
                    'section_name' => 'Customer Due Receive',
                    'col1' => 'SN',
                    'col2' => 'Reference No',
                    'col3' => 'Customer',
                    'col4' => 'Amount',
                    'col5' => '',
                    'col6' => '',
                    'col7' => '',
                    'col8' => '',
                    'col9' => '',
                    'col10' => '',
                    'col11' => '',
                    'col12' => '',
                ];
                
                foreach ($customerReceives as $customerReceive) {
                    $amount = floatval($customerReceive->amount ?? 0);
                    
                    $customerName = $customerReceive->customer ? ($customerReceive->customer->phone ? $customerReceive->customer->name . ' (' . $customerReceive->customer->phone . ')' : $customerReceive->customer->name) : '-';
                    
                    $formattedTransactions[] = [
                        'is_section_header' => false,
                        'transaction_type' => 'Customer Due Receive',
                        'sn' => $index++,
                        'reference_no' => $customerReceive->reference_no ?? '-',
                        'customer' => $customerName,
                        'amount' => formatAmount($amount),
                    ];
                    $totalAmount += $amount;
                }
            }
            
            // 7. Expense transactions
            $expenses = Expense::with(['category', 'employee', 'account'])
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->whereDate('date', $date)
                ->get();
            
            if ($expenses->count() > 0) {
                // Add section header
                $formattedTransactions[] = [
                    'is_section_header' => true,
                    'section_name' => 'Expense',
                    'col1' => 'SN',
                    'col2' => 'Reference No',
                    'col3' => 'Expense Category',
                    'col4' => 'Responsible Person',
                    'col5' => 'Amount',
                    'col6' => 'Payment Method',
                    'col7' => '',
                    'col8' => '',
                    'col9' => '',
                    'col10' => '',
                    'col11' => '',
                    'col12' => '',
                ];
                
                foreach ($expenses as $expense) {
                    $amount = floatval($expense->amount ?? 0);
                    
                    $categoryName = $expense->category ? ($expense->category->name ?? '-') : '-';
                    $responsiblePerson = $expense->employee ? ($expense->employee->name ?? '-') : '-';
                    if ($expense->employee && $expense->employee->phone) {
                        $responsiblePerson .= ' (' . $expense->employee->phone . ')';
                    }
                    $paymentMethod = $expense->account ? ($expense->account->name ?? '-') : '-';
                    
                    $formattedTransactions[] = [
                        'is_section_header' => false,
                        'transaction_type' => 'Expense',
                        'sn' => $index++,
                        'reference_no' => $expense->reference_no ?? '-',
                        'expense_category' => $categoryName,
                        'responsible_person' => $responsiblePerson,
                        'amount' => formatAmount($amount),
                        'payment_method' => $paymentMethod,
                    ];
                    $totalAmount += $amount;
                }
            }
            
            // 8. Income transactions
            $incomes = Income::with(['category', 'employee', 'account'])
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->whereDate('date', $date)
                ->get();
            
            if ($incomes->count() > 0) {
                // Add section header
                $formattedTransactions[] = [
                    'is_section_header' => true,
                    'section_name' => 'Income',
                    'col1' => 'SN',
                    'col2' => 'Reference No',
                    'col3' => 'Income Category',
                    'col4' => 'Responsible Person',
                    'col5' => 'Amount',
                    'col6' => 'Payment Method',
                    'col7' => '',
                    'col8' => '',
                    'col9' => '',
                    'col10' => '',
                    'col11' => '',
                    'col12' => '',
                ];
                
                foreach ($incomes as $income) {
                    $amount = floatval($income->amount ?? 0);
                    
                    $categoryName = $income->category ? ($income->category->name ?? '-') : '-';
                    $responsiblePerson = $income->employee ? ($income->employee->name ?? '-') : '-';
                    if ($income->employee && $income->employee->phone) {
                        $responsiblePerson .= ' (' . $income->employee->phone . ')';
                    }
                    $paymentMethod = $income->account ? ($income->account->name ?? '-') : '-';
                    
                    $formattedTransactions[] = [
                        'is_section_header' => false,
                        'transaction_type' => 'Income',
                        'sn' => $index++,
                        'reference_no' => $income->reference_no ?? '-',
                        'income_category' => $categoryName,
                        'responsible_person' => $responsiblePerson,
                        'amount' => formatAmount($amount),
                        'payment_method' => $paymentMethod,
                    ];
                    $totalAmount += $amount;
                }
            }
            
            // 9. Damage transactions
            $damages = Damage::with(['employee', 'outlet', 'damageDetails'])
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('outlet_id', $outletId)
                ->whereDate('date', $date)
                ->get();
            
            if ($damages->count() > 0) {
                // Add section header
                $formattedTransactions[] = [
                    'is_section_header' => true,
                    'section_name' => 'Damage',
                    'col1' => 'SN',
                    'col2' => 'Reference No',
                    'col3' => 'Damage Amount',
                    'col4' => 'Responsible Person',
                    'col5' => 'Items',
                    'col6' => '',
                    'col7' => '',
                    'col8' => '',
                    'col9' => '',
                    'col10' => '',
                    'col11' => '',
                    'col12' => '',
                ];
                
                foreach ($damages as $damage) {
                    $damageAmount = floatval($damage->total_loss ?? 0);
                    
                    $responsiblePerson = $damage->employee ? ($damage->employee->name ?? '-') : '-';
                    if ($damage->employee && $damage->employee->phone) {
                        $responsiblePerson .= ' (' . $damage->employee->phone . ')';
                    }
                    $items = $damage->damageDetails ? $damage->damageDetails->count() : 0;
                    
                    $formattedTransactions[] = [
                        'is_section_header' => false,
                        'transaction_type' => 'Damage',
                        'sn' => $index++,
                        'reference_no' => $damage->reference_no ?? '-',
                        'damage_amount' => formatAmount($damageAmount),
                        'responsible_person' => $responsiblePerson,
                        'items' => $items,
                    ];
                    $totalAmount += $damageAmount;
                }
            }
            
            // 10. Installment Collection transactions (InstallmentSaleDetail with paid_status = Paid or Partial)
            $installmentCollections = InstallmentSaleDetail::with(['installmentSale.customer'])
                ->where('del_status', 'Live')
                ->whereIn('paid_status', ['Paid', 'Partial'])
                ->whereDate('paid_date', $date)
                ->whereHas('installmentSale', function ($q) use ($companyId, $outletId, $date) {
                    $q->where('company_id', $companyId);
                    $q->whereDate('date', $date);
                    if ($outletId) {
                        $q->where('outlet_id', $outletId);
                    }
                })
                ->get();
            
            if ($installmentCollections->count() > 0) {
                // Add section header
                $formattedTransactions[] = [
                    'is_section_header' => true,
                    'section_name' => 'Installment Collection',
                    'col1' => 'SN',
                    'col2' => 'Invoice No',
                    'col3' => 'Customer',
                    'col4' => 'Amount',
                    'col5' => '',
                    'col6' => '',
                    'col7' => '',
                    'col8' => '',
                    'col9' => '',
                    'col10' => '',
                    'col11' => '',
                    'col12' => '',
                ];
                
                foreach ($installmentCollections as $collection) {
                    $amount = floatval($collection->paid_amount ?? 0);
                    
                    if ($amount <= 0) continue;
                    
                    $customerName = '-';
                    if ($collection->installmentSale && $collection->installmentSale->customer) {
                        $customer = $collection->installmentSale->customer;
                        $customerName = $customer->phone ? $customer->name . ' (' . $customer->phone . ')' : $customer->name;
                    }
                    $referenceNo = $collection->installmentSale ? ($collection->installmentSale->reference_no ?? '-') : '-';
                    
                    $formattedTransactions[] = [
                        'is_section_header' => false,
                        'transaction_type' => 'Installment Collection',
                        'sn' => $index++,
                        'invoice_no' => $referenceNo,
                        'customer' => $customerName,
                        'amount' => formatAmount($amount),
                    ];
                    $totalAmount += $amount;
                }
            }
            
            // 11. Installment Down Payment transactions (from InstallmentSale table where down_payment > 0)
            $installmentDownPayments = InstallmentSale::with(['customer'])
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('outlet_id', $outletId)
                ->where('down_payment', '>', 0)
                ->whereDate('date', $date)
                ->get();
            
            if ($installmentDownPayments->count() > 0) {
                // Add section header
                $formattedTransactions[] = [
                    'is_section_header' => true,
                    'section_name' => 'Installment Down Payment',
                    'col1' => 'SN',
                    'col2' => 'Reference No',
                    'col3' => 'Customer',
                    'col4' => 'Amount',
                    'col5' => '',
                    'col6' => '',
                    'col7' => '',
                    'col8' => '',
                    'col9' => '',
                    'col10' => '',
                    'col11' => '',
                    'col12' => '',
                ];
                
                foreach ($installmentDownPayments as $installmentSale) {
                    $amount = floatval($installmentSale->down_payment ?? 0);
                    
                    if ($amount <= 0) continue;
                    
                    $customerName = '-';
                    if ($installmentSale->customer) {
                        $customer = $installmentSale->customer;
                        $customerName = $customer->phone ? $customer->name . ' (' . $customer->phone . ')' : $customer->name;
                    }
                    
                    $formattedTransactions[] = [
                        'is_section_header' => false,
                        'transaction_type' => 'Installment Down Payment',
                        'sn' => $index++,
                        'reference_no' => $installmentSale->reference_no ?? '-',
                        'customer' => $customerName,
                        'amount' => formatAmount($amount),
                    ];
                    $totalAmount += $amount;
                }
            }
            
            // 12. Servicing transactions - Check if there's a servicing table or if it's part of sales
            // For now, we'll skip servicing as the table structure is not clear
            // Note: Servicing might be part of sales or a separate table - adjust as needed
            
            return response()->json([
                'success' => true,
                'data' => [
                    'transactions' => $formattedTransactions,
                    'summary' => [
                        'total_amount' => formatAmount($totalAmount),
                        'total_transactions' => count($formattedTransactions),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'date' => formatDate($date) ?? '',
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }
        
        // Return view for normal requests
        return view('report::daily-summary-report', compact('outlets'));
    }

    /**
     * Sale Report - Combined method for both view and API
     */
    public function saleReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $customerId = $request->get('customer_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'address')
            ->get();
        
        // Build query
        $query = Sale::with(['customer', 'outlet', 'employee'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);
        
        // Apply filters
        if ($dateFrom) {
            $query->whereDate('sale_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('sale_date', '<=', $dateTo);
        }
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        
        // Get all sales
        $sales = $query->orderBy('sale_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        
        // Get selected outlet and customer details for header
        $selectedOutlet = null;
        $selectedCustomer = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($customerId) {
            $selectedCustomer = Customer::where('id', $customerId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Format data for DataTable
            $formattedSales = [];
            $index = 1;
            $totalSubtotal = 0;
            $totalVat = 0;
            $totalCharge = 0;
            $totalDiscount = 0;
            $totalPayable = 0;
            $totalPaid = 0;
            $totalDue = 0;
            
            foreach ($sales as $sale) {
                $subtotal = floatval($sale->sub_total ?? 0);
                $vat = floatval($sale->vat ?? 0);
                $charge = floatval($sale->delivery_charge ?? 0);
                $discount = floatval($sale->total_discount_amount ?? 0);
                $payable = floatval($sale->total_payable ?? 0);
                $paid = floatval($sale->paid_amount ?? 0);
                $due = floatval($sale->due_amount ?? 0);
                
                $totalSubtotal += $subtotal;
                $totalVat += $vat;
                $totalCharge += $charge;
                $totalDiscount += $discount;
                $totalPayable += $payable;
                $totalPaid += $paid;
                $totalDue += $due;
                
                $formattedSales[] = [
                    'sn' => $index++,
                    'sale_no' => $sale->sale_no ?? '-',
                    'date' => $sale->sale_date ? formatDate($sale->sale_date) : null,
                    'customer' => $sale->customer ? ($sale->customer->phone ? $sale->customer->name . ' (' . $sale->customer->phone . ')' : $sale->customer->name) : __('Walk-in Customer'),
                    'items' => $sale->total_items ?? 0,
                    'subtotal' => formatAmount($subtotal),
                    'vat' => formatAmount($vat),
                    'charge' => formatAmount($charge),
                    'discount' => formatAmount($discount),
                    'total_payable' => formatAmount($payable),
                    'paid_amount' => formatAmount($paid),
                    'due_amount' => formatAmount($due),
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'sales' => $formattedSales,
                    'summary' => [
                        'total_subtotal' => formatAmount($totalSubtotal),
                        'total_vat' => formatAmount($totalVat),
                        'total_charge' => formatAmount($totalCharge),
                        'total_discount' => formatAmount($totalDiscount),
                        'total_payable' => formatAmount($totalPayable),
                        'total_paid' => formatAmount($totalPaid),
                        'total_due' => formatAmount($totalDue),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'customer' => $selectedCustomer ? [
                            'name' => $selectedCustomer->name,
                            'phone' => $selectedCustomer->phone,
                            'address' => $selectedCustomer->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::sale-report', compact('sales', 'outlets', 'customers'));
    }

    /**
     * Due Sale Report - Combined method for both view and API
     */
    public function dueSaleReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $customerId = $request->get('customer_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'address')
            ->get();
        
        // Build query - only sales with due amount > 0
        $query = Sale::with(['customer', 'outlet', 'employee'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('due_amount', '>', 0);
        
        // Apply filters
        if ($dateFrom) {
            $query->whereDate('sale_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('sale_date', '<=', $dateTo);
        }
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        
        // Get all sales with due amount
        $sales = $query->orderBy('date_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        
        // Get selected outlet and customer details for header
        $selectedOutlet = null;
        $selectedCustomer = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($customerId) {
            $selectedCustomer = Customer::where('id', $customerId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Format data for DataTable
            $formattedSales = [];
            $index = 1;
            $totalPayable = 0;
            $totalPaid = 0;
            $totalDue = 0;
            
            foreach ($sales as $sale) {
                $payable = floatval($sale->total_payable ?? 0);
                $paid = floatval($sale->paid_amount ?? 0);
                $due = floatval($sale->due_amount ?? 0);
                
                $totalPayable += $payable;
                $totalPaid += $paid;
                $totalDue += $due;
                
                // Format date time
                $dateTime = '';
                if ($sale->date_time) {
                    $dateTime = formatDateTime($sale->date_time);
                } elseif ($sale->sale_date) {
                    $dateTime = formatDate($sale->sale_date);
                }
                
                $formattedSales[] = [
                    'sn' => $index++,
                    'sale_no' => $sale->sale_no ?? '-',
                    'date_time' => $dateTime,
                    'customer' => $sale->customer ? ($sale->customer->phone ? $sale->customer->name . ' (' . $sale->customer->phone . ')' : $sale->customer->name) : __('Walk-in Customer'),
                    'items' => $sale->total_items ?? 0,
                    'total_payable' => formatAmount($payable),
                    'paid_amount' => formatAmount($paid),
                    'due_amount' => formatAmount($due),
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'sales' => $formattedSales,
                    'summary' => [
                        'total_payable' => formatAmount($totalPayable),
                        'total_paid' => formatAmount($totalPaid),
                        'total_due' => formatAmount($totalDue),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'customer' => $selectedCustomer ? [
                            'name' => $selectedCustomer->name,
                            'phone' => $selectedCustomer->phone,
                            'address' => $selectedCustomer->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }
        
        // Return view for normal requests
        return view('report::due-sale-report', compact('outlets', 'customers'));
    }

    /**
     * Get Final Invoice Due Report Data
     */
    /**
     * Final Invoice Due Report - Combined method for both view and API
     * Shows sales with due amounts (after considering sale returns)
     */
    public function finalInvoiceDueReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $customerId = $request->get('customer_id');
        $outletId = $request->get('outlet_id');
        
        // Get filter options (for view)
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'address')
            ->get();
        
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        // Get selected customer and outlet details for header
        $selectedCustomer = null;
        $selectedOutlet = null;
        
        if ($customerId) {
            $selectedCustomer = Customer::where('id', $customerId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address')
                ->first();
        }
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Build query to get sales with sale return totals using subquery
            $query = Sale::with(['customer', 'outlet'])
                ->select(
                    'sales.*',
                    DB::raw('(SELECT COALESCE(SUM(sale_returns.total_return_amount), 0) 
                        FROM sale_returns 
                        WHERE sale_returns.sale_id = sales.id 
                        AND sale_returns.company_id = sales.company_id 
                        AND sale_returns.del_status = "Live") as total_return_amount')
                )
                ->where('sales.del_status', 'Live')
                ->where('sales.company_id', $companyId);
            
            // Apply filters
            if ($dateFrom) {
                $query->whereDate('sales.sale_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('sales.sale_date', '<=', $dateTo);
            }
            if ($customerId) {
                $query->where('sales.customer_id', $customerId);
            }
            if ($outletId) {
                $query->where('sales.outlet_id', $outletId);
            }
            
            // Get sales
            $sales = $query->orderBy('sales.sale_date', 'desc')
                ->orderBy('sales.id', 'desc')
                ->get();
            
            // Calculate final_due for each sale and filter
            $sales = $sales->map(function($sale) {
                $totalReturnAmount = floatval($sale->total_return_amount ?? 0);
                $totalPayable = floatval($sale->total_payable ?? 0);
                $paidAmount = floatval($sale->paid_amount ?? 0);
                $netPayable = $totalPayable - $totalReturnAmount;
                $finalDue = $netPayable - $paidAmount;
                $sale->final_due = $finalDue;
                return $sale;
            })->filter(function($sale) {
                return $sale->final_due > 0;
            });
            
            // Format data for DataTable
            $formattedSales = [];
            $index = 1;
            $totalDue = 0;
            
            foreach ($sales as $sale) {
                $finalDue = floatval($sale->final_due ?? 0);
                $totalDue += $finalDue;
                
                // Format customer name
                $customerName = '-';
                if ($sale->customer) {
                    $customerName = $sale->customer->name ?? '-';
                    if ($sale->customer->phone) {
                        $customerName .= ' (' . $sale->customer->phone . ')';
                    }
                }
                
                $formattedSales[] = [
                    'sn' => $index++,
                    'invoice_no' => $sale->sale_no ?? '-',
                    'customer_name' => $customerName,
                    'due' => formatAmount($finalDue),
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'sales' => $formattedSales,
                    'summary' => [
                        'total_due' => formatAmount($totalDue),
                        'total_sales' => count($formattedSales),
                    ],
                    'filter_info' => [
                        'customer' => $selectedCustomer ? [
                            'name' => $selectedCustomer->name,
                            'phone' => $selectedCustomer->phone,
                            'address' => $selectedCustomer->address,
                        ] : null,
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }
        
        // Return view for normal requests
        return view('report::final-invoice-due-report', compact('customers', 'outlets'));
    }

    /**
     * Get Service Sale Report Data
     */
    /**
     * Service Sale Report - Combined method for both view and API
     * Shows service product sales (items with type = 'Service_Product')
     */
    public function serviceSaleReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $itemId = $request->get('item_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        // Get only service products for item filter
        $items = \Modules\Stock\Models\Item::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('type', 'Service_Product')
            ->select('id', 'name', 'code', 'parent_id')
            ->get();
        
        // Get selected outlet and item details for header
        $selectedOutlet = null;
        $selectedItem = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($itemId) {
            $selectedItem = \Modules\Stock\Models\Item::where('id', $itemId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'code')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Build query - get sale details with relationships, filter for service products only
            $query = \Modules\Sale\Models\SaleDetail::with(['sale', 'sale.customer', 'item', 'sale.outlet'])
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->whereHas('item', function($q) {
                    $q->where('type', 'Service_Product');
                });
            
            // Apply filters
            if ($dateFrom) {
                $query->whereHas('sale', function($q) use ($dateFrom) {
                    $q->whereDate('sale_date', '>=', $dateFrom);
                });
            }
            if ($dateTo) {
                $query->whereHas('sale', function($q) use ($dateTo) {
                    $q->whereDate('sale_date', '<=', $dateTo);
                });
            }
            if ($outletId) {
                $query->where('outlet_id', $outletId);
            }
            if ($itemId) {
                $query->where('item_id', $itemId);
            }
            
            // Get all sale details
            $saleDetails = $query->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->get();
            
            // Format data for DataTable
            $formattedServiceSales = [];
            $index = 1;
            $totalAmount = 0;
            
            foreach ($saleDetails as $saleDetail) {
                $quantity = floatval($saleDetail->qty ?? 0);
                $unitPrice = floatval($saleDetail->menu_price_with_discount ?? $saleDetail->menu_unit_price ?? 0);
                $lineTotal = $quantity * $unitPrice;
                $totalAmount += $lineTotal;
                
                // Format date time (from sale)
                $dateTime = '';
                if ($saleDetail->sale) {
                    if ($saleDetail->sale->date_time) {
                        $dateTime = formatDateTime($saleDetail->sale->date_time);
                    } elseif ($saleDetail->sale->created_at) {
                        $dateTime = formatDateTime($saleDetail->sale->created_at);
                    }
                }
                
                // Get invoice no from sale
                $invoiceNo = '-';
                if ($saleDetail->sale) {
                    $invoiceNo = $saleDetail->sale->sale_no ?? '-';
                }
                
                // Format customer
                $customerText = '-';
                if ($saleDetail->sale && $saleDetail->sale->customer) {
                    $customerText = $saleDetail->sale->customer->name ?? '-';
                    if ($saleDetail->sale->customer->phone) {
                        $customerText .= ' (' . $saleDetail->sale->customer->phone . ')';
                    }
                } else {
                    $customerText = __('Walk-in Customer');
                }
                
                // Format item
                $itemText = '-';
                if ($saleDetail->item) {
                    $itemText = $saleDetail->item->name ?? '-';
                    if ($saleDetail->item->code) {
                        $itemText .= ' (' . $saleDetail->item->code . ')';
                    }
                }
                
                $formattedServiceSales[] = [
                    'sn' => $index++,
                    'invoice_no' => $invoiceNo,
                    'date_time' => $dateTime,
                    'customer' => $customerText,
                    'item' => $itemText,
                    'quantity' => number_format($quantity, 2),
                    'unit_price' => formatAmount($unitPrice),
                    'total' => formatAmount($lineTotal),
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'service_sales' => $formattedServiceSales,
                    'summary' => [
                        'total_amount' => formatAmount($totalAmount),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'item' => $selectedItem ? [
                            'name' => $selectedItem->name . ' (' . $selectedItem->code . ')',
                            'id' => $selectedItem->id,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }
        
        // Return view for normal requests
        return view('report::service-sale-report', compact('outlets', 'items'));
    }

    /**
     * Get Combo Service Report Data
     */
    public function comboServiceReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $customerId = $request->get('customer_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'address')
            ->orderBy('name', 'asc')
            ->get();
        
        // Get selected outlet and customer details for header
        $selectedOutlet = null;
        $selectedCustomer = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($customerId) {
            $selectedCustomer = Customer::where('id', $customerId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            $comboData = [];
            $index = 1;
            
            // Query ComboSale with relationships - only for Combo_Product items
            $query = ComboSale::with([
                'sale.customer',
                'comboItem'
            ])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->whereHas('sale', function($q) use ($dateFrom, $dateTo, $outletId, $customerId, $companyId) {
                $q->where('del_status', 'Live')
                  ->where('company_id', $companyId);
                
                if ($outletId) {
                    $q->where('outlet_id', $outletId);
                }
                
                if ($customerId) {
                    $q->where('customer_id', $customerId);
                }
                
                if ($dateFrom) {
                    $q->whereDate('sale_date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $q->whereDate('sale_date', '<=', $dateTo);
                }
            })
            ->whereHas('comboItem', function($q) {
                $q->where('type', 'Combo_Product')
                  ->where('del_status', 'Live');
            });
            
            // Filter by outlet
            if ($outletId) {
                $query->where('outlet_id', $outletId);
            }
            
            $comboSales = $query->orderBy('created_at', 'desc')->get();
            
            foreach ($comboSales as $comboSale) {
                $sale = $comboSale->sale;
                $comboItem = $comboSale->comboItem;
                
                if (!$sale) {
                    continue;
                }
                
                // Get customer info
                $customer = $sale->customer;
                $customerDisplay = '-';
                if ($customer) {
                    $customerDisplay = $customer->name ?? '-';
                }
                
                // Get item code
                $itemCode = '-';
                if ($comboItem) {
                    $itemCode = $comboItem->code ?? '-';
                }
                
                // Format date & time
                $dateTime = '-';
                if ($sale->date_time) {
                    $dateTime = formatDateTime($sale->date_time);
                } elseif ($sale->created_at) {
                    $dateTime = formatDateTime($sale->created_at);
                }
                
                // Format amounts
                $quantity = formatAmount($comboSale->combo_item_qty ?? 0);
                $unitPrice = formatAmount($comboSale->combo_item_price ?? 0);
                $total = formatAmount(($comboSale->combo_item_qty ?? 0) * ($comboSale->combo_item_price ?? 0));
                
                $comboData[] = [
                    'sn' => $index++,
                    'invoice_no' => $sale->sale_no ?? '-',
                    'date_time' => $dateTime,
                    'customer' => $customerDisplay,
                    'items_code' => $itemCode,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $total
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'combo_sales' => $comboData,
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'customer' => $selectedCustomer ? [
                            'name' => $selectedCustomer->name,
                            'phone' => $selectedCustomer->phone,
                            'address' => $selectedCustomer->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }
        
        // Return view for normal requests
        return view('report::combo-service-report', compact('outlets', 'customers'));
    }

    /**
     * Stock Report - Combined method for both view and API
     */
    public function stockReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $itemCode = $request->get('item_code', '');
        $categoryId = $request->get('category_id', '');
        $brandId = $request->get('brand_id', '');
        $itemId = $request->get('item_id', '');
        $genericName = $request->get('generic_name', '');
        $supplierId = $request->get('supplier_id', '');
        
        // Filter options (for view). Items list not loaded - use AJAX search in filter.
        $item_categories = \Modules\Stock\Models\ItemCategory::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->select('id', 'name')
            ->get();
        
        $brands = \Modules\Stock\Models\Brand::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->select('id', 'name')
            ->get();
        
        $suppliers = \Modules\Purchase\Models\Supplier::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->select('id', 'name')
            ->get();
        
        // Use StockService to get data
        $stockService = app(\Modules\Stock\Services\StockService::class);
        
        $filters = [
            'item_id' => $itemId,
            'item_code' => $itemCode,
            'brand_id' => $brandId,
            'category_id' => $categoryId,
            'supplier_id' => $supplierId,
            'generic_name' => $genericName,
        ];
        
        // Chunked loading: report requests use offset/limit (no change to stock module behavior)
        $offset = (int) $request->get('offset', 0);
        $limit = (int) $request->get('limit', 2000);
        $limit = min(max(1, $limit), 5000); // clamp 1–5000 per request

        $result = $stockService->getDataTableData($filters, $offset, $limit);
        $stockEvaluation = $stockService->getStockEvaluation($filters);
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Format data for DataTable (convert HTML arrays to objects)
            $formattedStocks = [];
            $index = 1;
            
            foreach ($result['data'] as $row) {
                // Extract text from HTML for each column
                $sn = strip_tags($row[0] ?? '');
                $itemCodeVal = strip_tags($row[1] ?? '');
                $category = strip_tags($row[2] ?? '');
                $stockDetails = $row[3] ?? ''; // Keep HTML for buttons
                $totalStockQty = strip_tags($row[4] ?? '');
                $lpp = strip_tags($row[5] ?? '');
                $total = strip_tags($row[6] ?? '');
                
                $formattedStocks[] = [
                    'sn' => $sn,
                    'item_code' => $itemCodeVal,
                    'category' => $category,
                    'stock_details' => $stockDetails,
                    'total_stock_qty' => $totalStockQty,
                    'lpp' => $lpp,
                    'total' => $total,
                ];
            }
            
            $payload = [
                'success' => true,
                'data' => [
                    'stocks' => $formattedStocks,
                    'total_count' => (int) ($result['recordsTotal'] ?? 0),
                    'offset' => $offset,
                    'limit' => $limit,
                ]
            ];

            // Include summary and filter_info only on first chunk (offset=0) for smaller payloads
            if ($offset === 0) {
                $selectedCategory = null;
                $selectedBrand = null;
                $selectedItem = null;
                $selectedSupplier = null;

                if ($categoryId) {
                    $selectedCategory = \Modules\Stock\Models\ItemCategory::where('id', $categoryId)
                        ->where('company_id', $companyId)
                        ->select('id', 'name')
                        ->first();
                }
                if ($brandId) {
                    $selectedBrand = \Modules\Stock\Models\Brand::where('id', $brandId)
                        ->where('company_id', $companyId)
                        ->select('id', 'name')
                        ->first();
                }
                if ($itemId) {
                    $selectedItem = \Modules\Stock\Models\Item::where('id', $itemId)
                        ->where('company_id', $companyId)
                        ->select('id', 'name', 'code')
                        ->first();
                }
                if ($supplierId) {
                    $selectedSupplier = \Modules\Purchase\Models\Supplier::where('id', $supplierId)
                        ->where('company_id', $companyId)
                        ->select('id', 'name')
                        ->first();
                }
                $payload['data']['summary'] = [
                    'stock_value' => formatAmount($stockEvaluation['stock_value'] ?? 0),
                    'stock_count' => number_format($stockEvaluation['stock_count'] ?? 0, 2),
                ];
                $payload['data']['filter_info'] = [
                    'item_code' => $itemCode ?: '',
                    'category' => $selectedCategory ? ['name' => $selectedCategory->name] : null,
                    'brand' => $selectedBrand ? ['name' => $selectedBrand->name] : null,
                    'item' => $selectedItem ? ['name' => $selectedItem->name . ' (' . $selectedItem->code . ')', 'id' => $selectedItem->id] : null,
                    'generic_name' => $genericName ?: '',
                    'supplier' => $selectedSupplier ? ['name' => $selectedSupplier->name] : null,
                ];
            }

            return response()->json($payload);
        }
        
        // Selected item for filter pre-fill (when item_id in request)
        $selected_item = null;
        if ($request->get('item_id')) {
            $selected_item = \Modules\Stock\Models\Item::where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where('id', $request->get('item_id'))
                ->select('id', 'name', 'code')
                ->first();
        }
        return view('report::stock-report', compact('item_categories', 'brands', 'suppliers', 'selected_item'));
    }

    /**
     * AJAX: Search items by name or code (for report filter Select2).
     */
    public function searchReportItems(Request $request)
    {
        $companyId = session('company.company_id');
        $q = trim((string) $request->get('q', ''));
        if (strlen($q) < 1) {
            return response()->json(['results' => []]);
        }
        $items = \Modules\Stock\Models\Item::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                    ->orWhere('code', 'like', '%' . $q . '%');
            })
            ->whereNull('parent_id')
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'code']);
        $results = $items->map(function ($item) {
            return [
                'id' => (string) $item->id,
                'text' => $item->name . ' (' . $item->code . ')',
                'code' => $item->code,
            ];
        })->values()->all();
        return response()->json(['results' => $results]);
    }

    /**
     * Low Stock Report - Combined method for both view and API
     */
    public function lowStockReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $itemCode = $request->get('item_code', '');
        $categoryId = $request->get('category_id', '');
        $brandId = $request->get('brand_id', '');
        $itemId = $request->get('item_id', '');
        $genericName = $request->get('generic_name', '');
        $supplierId = $request->get('supplier_id', '');
        
        // Filter options (for view). Items list not loaded - use AJAX search in filter.
        $item_categories = \Modules\Stock\Models\ItemCategory::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->select('id', 'name')
            ->get();
        
        $brands = \Modules\Stock\Models\Brand::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->select('id', 'name')
            ->get();
        
        $suppliers = \Modules\Purchase\Models\Supplier::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->select('id', 'name')
            ->get();
        
        // Use StockService to get low stock data
        $stockService = app(\Modules\Stock\Services\StockService::class);
        
        $filters = [
            'item_id' => $itemId,
            'item_code' => $itemCode,
            'brand_id' => $brandId,
            'category_id' => $categoryId,
            'supplier_id' => $supplierId,
            'generic_name' => $genericName,
        ];
        
        // Chunked loading: report requests use offset/limit (no change to low-stock module behavior)
        $offset = (int) $request->get('offset', 0);
        $limit = (int) $request->get('limit', 2000);
        $limit = min(max(1, $limit), 5000); // clamp 1–5000 per request

        $result = $stockService->getLowStockDataTableData($filters, $offset, $limit);
        $stockEvaluation = $stockService->getStockEvaluation($filters);
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Format data for DataTable (convert HTML arrays to objects)
            $formattedStocks = [];
            
            foreach ($result['data'] as $row) {
                $sn = strip_tags($row[0] ?? '');
                $itemCodeVal = strip_tags($row[1] ?? '');
                $category = strip_tags($row[2] ?? '');
                $stockDetails = $row[3] ?? '';
                $totalStockQty = strip_tags($row[4] ?? '');
                $lpp = strip_tags($row[5] ?? '');
                $total = strip_tags($row[6] ?? '');
                
                $formattedStocks[] = [
                    'sn' => $sn,
                    'item_code' => $itemCodeVal,
                    'category' => $category,
                    'stock_details' => $stockDetails,
                    'total_stock_qty' => $totalStockQty,
                    'lpp' => $lpp,
                    'total' => $total,
                ];
            }
            
            $payload = [
                'success' => true,
                'data' => [
                    'stocks' => $formattedStocks,
                    'total_count' => (int) ($result['recordsTotal'] ?? 0),
                    'offset' => $offset,
                    'limit' => $limit,
                ]
            ];

            if ($offset === 0) {
                $selectedCategory = null;
                $selectedBrand = null;
                $selectedItem = null;
                $selectedSupplier = null;

                if ($categoryId) {
                    $selectedCategory = \Modules\Stock\Models\ItemCategory::where('id', $categoryId)
                        ->where('company_id', $companyId)
                        ->select('id', 'name')
                        ->first();
                }
                if ($brandId) {
                    $selectedBrand = \Modules\Stock\Models\Brand::where('id', $brandId)
                        ->where('company_id', $companyId)
                        ->select('id', 'name')
                        ->first();
                }
                if ($itemId) {
                    $selectedItem = \Modules\Stock\Models\Item::where('id', $itemId)
                        ->where('company_id', $companyId)
                        ->select('id', 'name', 'code')
                        ->first();
                }
                if ($supplierId) {
                    $selectedSupplier = \Modules\Purchase\Models\Supplier::where('id', $supplierId)
                        ->where('company_id', $companyId)
                        ->select('id', 'name')
                        ->first();
                }
                $payload['data']['summary'] = [
                    'stock_value' => formatAmount($stockEvaluation['stock_value'] ?? 0),
                    'stock_count' => number_format($stockEvaluation['stock_count'] ?? 0, 2),
                    'alert_sum' => $result['alertSum'] ?? 0,
                ];
                $payload['data']['filter_info'] = [
                    'item_code' => $itemCode ?: '',
                    'category' => $selectedCategory ? ['name' => $selectedCategory->name] : null,
                    'brand' => $selectedBrand ? ['name' => $selectedBrand->name] : null,
                    'item' => $selectedItem ? ['name' => $selectedItem->name . ' (' . $selectedItem->code . ')', 'id' => $selectedItem->id] : null,
                    'generic_name' => $genericName ?: '',
                    'supplier' => $selectedSupplier ? ['name' => $selectedSupplier->name] : null,
                ];
            }

            return response()->json($payload);
        }
        
        // Selected item for filter pre-fill
        $selected_item = null;
        if ($request->get('item_id')) {
            $selected_item = \Modules\Stock\Models\Item::where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where('id', $request->get('item_id'))
                ->select('id', 'name', 'code')
                ->first();
        }
        return view('report::low-stock-report', compact('item_categories', 'brands', 'suppliers', 'selected_item'));
    }

    /**
     * Get Expire Soon Report Data
     */
    public function expireSoonReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $genericName = $request->get('generic_name');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        // Get selected outlet details for header
        $selectedOutlet = null;
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            $expireData = [];
            $index = 1;
            
            // Query items where type = 'Medicine_Product'
            $itemsQuery = \Modules\Stock\Models\Item::with('category')
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('type', 'Medicine_Product');
            
            // Filter by generic name
            if ($genericName) {
                $itemsQuery->where('generic_name', 'like', '%' . $genericName . '%');
            }
            
            $items = $itemsQuery->get();
            
            foreach ($items as $item) {
                // Get stock by expiry date from view_stock_detail2
                $stockQuery = DB::table('view_stock_detail2')
                    ->where('item_id', $item->id)
                    ->whereNotNull('expiry_imei_serial')
                    ->where('expiry_imei_serial', '!=', '');
                
                // Filter by outlet
                if ($outletId) {
                    $stockQuery->where('outlet_id', $outletId);
                }
                
                // Filter by expiry date range
                if ($dateFrom) {
                    $stockQuery->whereDate('expiry_imei_serial', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $stockQuery->whereDate('expiry_imei_serial', '<=', $dateTo);
                }
                
                // Group by expiry date and calculate stock
                $stockByExpiry = $stockQuery
                    ->select('expiry_imei_serial', DB::raw('SUM(stock_quantity) as quantity'))
                    ->groupBy('expiry_imei_serial')
                    ->orderBy('expiry_imei_serial', 'asc')
                    ->get();
                
                // Filter out expiry dates with zero or negative stock
                $stockByExpiry = $stockByExpiry->filter(function($stock) {
                    return floatval($stock->quantity) > 0;
                });
                
                // If no stock found for this item, skip it
                if ($stockByExpiry->isEmpty()) {
                    continue;
                }
                
                // Build stock segmentation (Date and Quantity)
                $stockSegmentation = [];
                $totalStockQuantity = 0;
                
                foreach ($stockByExpiry as $stock) {
                    $expiryDate = $stock->expiry_imei_serial;
                    $quantity = floatval($stock->quantity);
                    $totalStockQuantity += $quantity;
                    
                    $formattedDate = formatDate($expiryDate);
                    // Use numberFormat for quantity (without currency), formatAmount for prices
                    $formattedQuantity = function_exists('numberFormat') ? numberFormat($quantity) : number_format($quantity, 2);
                    $stockSegmentation[] = $formattedDate . ': ' . $formattedQuantity;
                }
                
                $stockSegmentationText = implode(', ', $stockSegmentation);
                
                // Get category name
                $categoryName = '-';
                if ($item->category) {
                    $categoryName = $item->category->name ?? '-';
                }
                
                // Get last purchase price
                $lastPurchasePrice = floatval($item->last_purchase_price ?? 0);
                
                // Calculate total
                $total = $totalStockQuantity * $lastPurchasePrice;
                
                $expireData[] = [
                    'sn' => $index++,
                    'item_code' => $item->code ?? '-',
                    'category' => $categoryName,
                    'stock_segmentation' => $stockSegmentationText,
                    'total_stock_quantity' => formatAmount($totalStockQuantity),
                    'last_purchase_price' => formatAmount($lastPurchasePrice),
                    'total' => formatAmount($total)
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'expire_soon' => $expireData,
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'generic_name' => $genericName ?? '',
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }
        
        // Return view for normal requests
        return view('report::expire-soon-report', compact('outlets'));
    }

    /**
     * Get Employee Sale Report Data
     */
    /**
     * Employee Sale Report - Combined method for both view and API
     * Supports two types: Sale Wise (based on sales.user_id) and Item Wise (based on sale_details.item_seller_id)
     */
    public function employeeSaleReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $employeeId = $request->get('employee_id');
        $type = $request->get('type', 'sale_wise'); // sale_wise or item_wise
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $employees = User::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'email')
            ->orderBy('name', 'asc')
            ->get();
        
        // Get selected outlet and employee details for header
        $selectedOutlet = null;
        $selectedEmployee = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($employeeId) {
            $selectedEmployee = User::where('id', $employeeId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'email')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            $formattedEmployeeSales = [];
            $index = 1;
            $totalSubtotal = 0;
            $totalCommissionAmount = 0;
            
            if ($type === 'sale_wise') {
                // Sale Wise: Filter by sales.user_id
                $query = Sale::with(['customer', 'outlet', 'user'])
                    ->withCount(['saleDetails as total_items' => function($q) {
                        $q->where('del_status', 'Live');
                    }])
                    ->where('del_status', 'Live')
                    ->where('company_id', $companyId);
                
                // Apply filters
                if ($dateFrom) {
                    $query->whereDate('sale_date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $query->whereDate('sale_date', '<=', $dateTo);
                }
                if ($outletId) {
                    $query->where('outlet_id', $outletId);
                }
                if ($employeeId) {
                    $query->where('user_id', $employeeId);
                }
                
                $sales = $query->orderBy('sale_date', 'desc')
                    ->orderBy('id', 'desc')
                    ->get();
                
                foreach ($sales as $sale) {
                    $subtotal = floatval($sale->sub_total ?? 0);
                    $totalSubtotal += $subtotal;
                    
                    // Get commission from user (employee) who created the sale
                    $commission = 0;
                    if ($sale->user_id && $sale->user) {
                        $commission = floatval($sale->user->commission ?? 0);
                    } elseif ($sale->user_id) {
                        // Fallback if relationship didn't load
                        $user = User::where('id', $sale->user_id)
                            ->where('company_id', $companyId)
                            ->select('id', 'commission')
                            ->first();
                        if ($user) {
                            $commission = floatval($user->commission ?? 0);
                        }
                    }
                    $commissionAmount = ($subtotal * $commission) / 100;
                    $totalCommissionAmount += $commissionAmount;
                    
                    // Format date time
                    $dateTime = '';
                    if ($sale->date_time) {
                        $dateTime = formatDateTime($sale->date_time);
                    } elseif ($sale->created_at) {
                        $dateTime = formatDateTime($sale->created_at);
                    }
                    
                    // Format customer
                    $customerText = '-';
                    if ($sale->customer) {
                        $customerText = $sale->customer->name ?? '-';
                        if ($sale->customer->phone) {
                            $customerText .= ' (' . $sale->customer->phone . ')';
                        }
                    } else {
                        $customerText = __('Walk-in Customer');
                    }
                    
                    $formattedEmployeeSales[] = [
                        'sn' => $index++,
                        'invoice_no' => $sale->sale_no ?? '-',
                        'date_time' => $dateTime,
                        'customer' => $customerText,
                        'items' => $sale->total_items ?? 0,
                        'subtotal' => formatAmount($subtotal),
                        'commission' => number_format($commission, 2) . '%',
                        'commission_amount' => formatAmount($commissionAmount),
                    ];
                }
            } else {
                // Item Wise: Filter by sale_details.item_seller_id, group by sale
                $query = \Modules\Sale\Models\SaleDetail::with(['sale', 'sale.customer', 'sale.outlet', 'itemSeller'])
                    ->where('del_status', 'Live')
                    ->where('company_id', $companyId);
                
                // Apply filters
                if ($dateFrom) {
                    $query->whereHas('sale', function($q) use ($dateFrom) {
                        $q->whereDate('sale_date', '>=', $dateFrom);
                    });
                }
                if ($dateTo) {
                    $query->whereHas('sale', function($q) use ($dateTo) {
                        $q->whereDate('sale_date', '<=', $dateTo);
                    });
                }
                if ($outletId) {
                    $query->where('outlet_id', $outletId);
                }
                if ($employeeId) {
                    $query->where('item_seller_id', $employeeId);
                }
                
                $saleDetails = $query->orderBy('created_at', 'desc')
                    ->orderBy('id', 'desc')
                    ->get();
                
                // Group by sale_id to aggregate data per sale
                $groupedBySale = $saleDetails->groupBy('sales_id');
                
                foreach ($groupedBySale as $saleId => $details) {
                    $sale = $details->first()->sale;
                    if (!$sale) continue;
                    
                    // Calculate subtotal from items sold by this employee in this sale
                    $subtotal = 0;
                    $itemsCount = 0;
                    foreach ($details as $detail) {
                        $lineTotal = floatval($detail->qty ?? 0) * floatval($detail->menu_price_with_discount ?? $detail->menu_unit_price ?? 0);
                        $subtotal += $lineTotal;
                        $itemsCount++;
                    }
                    
                    $totalSubtotal += $subtotal;
                    
                    // Get commission from itemSeller (employee) who sold the items
                    $commission = 0;
                    $firstDetail = $details->first();
                    if ($firstDetail->item_seller_id && $firstDetail->itemSeller) {
                        $commission = floatval($firstDetail->itemSeller->commission ?? 0);
                    } elseif ($firstDetail->item_seller_id) {
                        // Fallback if relationship didn't load
                        $itemSeller = User::where('id', $firstDetail->item_seller_id)
                            ->where('company_id', $companyId)
                            ->select('id', 'commission')
                            ->first();
                        if ($itemSeller) {
                            $commission = floatval($itemSeller->commission ?? 0);
                        }
                    }
                    $commissionAmount = ($subtotal * $commission) / 100;
                    $totalCommissionAmount += $commissionAmount;
                    
                    // Format date time
                    $dateTime = '';
                    if ($sale->date_time) {
                        $dateTime = formatDateTime($sale->date_time);
                    } elseif ($sale->created_at) {
                        $dateTime = formatDateTime($sale->created_at);
                    }
                    
                    // Format customer
                    $customerText = '-';
                    if ($sale->customer) {
                        $customerText = $sale->customer->name ?? '-';
                        if ($sale->customer->phone) {
                            $customerText .= ' (' . $sale->customer->phone . ')';
                        }
                    } else {
                        $customerText = __('Walk-in Customer');
                    }
                    
                    $formattedEmployeeSales[] = [
                        'sn' => $index++,
                        'invoice_no' => $sale->sale_no ?? '-',
                        'date_time' => $dateTime,
                        'customer' => $customerText,
                        'items' => $itemsCount,
                        'subtotal' => formatAmount($subtotal),
                        'commission' => number_format($commission, 2) . '%',
                        'commission_amount' => formatAmount($commissionAmount),
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'employee_sales' => $formattedEmployeeSales,
                    'summary' => [
                        'total_subtotal' => formatAmount($totalSubtotal),
                        'total_commission_amount' => formatAmount($totalCommissionAmount),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'employee' => $selectedEmployee ? [
                            'name' => $selectedEmployee->name,
                            'phone' => $selectedEmployee->phone,
                            'email' => $selectedEmployee->email,
                        ] : null,
                        'type' => $type === 'sale_wise' ? 'Sale Wise' : 'Item Wise',
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }
        
        // Return view for normal requests
        return view('report::employee-sale-report', compact('outlets', 'employees'));
    }

    /**
     * Customer Receive Report - Combined method for both view and API
     */
    public function customerReceiveReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $customerId = $request->get('customer_id');
        
        // Validate required fields for AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
            if (!$customerId) {
                return response()->json([
                    'success' => false,
                    'message' => __('Customer is required')
                ], 422);
            }
        }
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'address')
            ->get();
        
        // Build query
        $query = CustomerReceive::with(['customer', 'outlet', 'user'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);
        
        // Apply filters
        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        
        // Get all customer receives
        $receives = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        
        // Get selected outlet and customer details for header
        $selectedOutlet = null;
        $selectedCustomer = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($customerId) {
            $selectedCustomer = Customer::where('id', $customerId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Format data for DataTable
            $formattedReceives = [];
            $index = 1;
            $totalAmount = 0;
            
            foreach ($receives as $receive) {
                $amount = floatval($receive->amount ?? 0);
                $totalAmount += $amount;
                
                // Format date time
                $dateTime = '';
                if ($receive->date) {
                    // If date contains time, use formatDateTime, otherwise formatDate
                    if (strpos($receive->date, ' ') !== false || strpos($receive->date, ':') !== false) {
                        $dateTime = formatDateTime($receive->date);
                    } else {
                        $dateTime = formatDate($receive->date);
                    }
                }
                
                $formattedReceives[] = [
                    'sn' => $index++,
                    'reference_no' => $receive->reference_no ?? '-',
                    'date_time' => $dateTime,
                    'customer' => $receive->customer ? ($receive->customer->phone ? $receive->customer->name . ' (' . $receive->customer->phone . ')' : $receive->customer->name) : '-',
                    'amount' => formatAmount($amount),
                    'note' => truncateText($receive->note) ?? '-',
                    'receive_by' => $receive->user ? $receive->user->name : '-',
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'receives' => $formattedReceives,
                    'summary' => [
                        'total_amount' => formatAmount($totalAmount),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'customer' => $selectedCustomer ? [
                            'name' => $selectedCustomer->name,
                            'phone' => $selectedCustomer->phone,
                            'address' => $selectedCustomer->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }
        
        // Return view for normal requests
        return view('report::customer-receive-report', compact('outlets', 'customers'));
    }

    /**
     * Attendance Report - Combined method for both view and API
     */
    public function attendanceReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $employeeId = $request->get('employee_id');
        
        // Get filter options (for view) - get all employees for the company
        $employees = User::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'email')
            ->orderBy('name', 'asc')
            ->get();
        
        // Build query
        $query = Attendance::with(['employee'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);
        
        // Apply filters
        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }
        
        // Get all attendances
        $attendances = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        
        // Get selected employee details for header
        $selectedEmployee = null;
        
        if ($employeeId) {
            $selectedEmployee = User::where('id', $employeeId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'email')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Format data for DataTable
            $formattedAttendances = [];
            $index = 1;
            
            foreach ($attendances as $attendance) {
                // Format date time
                $dateTime = '';
                if ($attendance->date) {
                    $dateTime = formatDate($attendance->date);
                }
                
                // Format in time
                $inTime = '-';
                if ($attendance->in_time) {
                    $inTime = \Carbon\Carbon::parse($attendance->in_time)->format('H:i');
                }
                
                // Format out time
                $outTime = '-';
                if ($attendance->out_time) {
                    $outTime = \Carbon\Carbon::parse($attendance->out_time)->format('H:i');
                }
                
                // Calculate time count (difference between in_time and out_time)
                $timeCount = '-';
                if ($attendance->in_time && $attendance->out_time) {
                    $inTimeCarbon = \Carbon\Carbon::parse($attendance->in_time);
                    $outTimeCarbon = \Carbon\Carbon::parse($attendance->out_time);
                    $diff = $inTimeCarbon->diff($outTimeCarbon);
                    
                    $hours = $diff->h;
                    $minutes = $diff->i;
                    
                    if ($hours > 0) {
                        $timeCount = $hours . 'h ' . $minutes . 'm';
                    } else {
                        $timeCount = $minutes . 'm';
                    }
                }
                
                // Format employee with phone
                $employeeText = '-';
                if ($attendance->employee) {
                    $employeeText = $attendance->employee->name;
                    if ($attendance->employee->phone) {
                        $employeeText .= ' (' . $attendance->employee->phone . ')';
                    }
                }
                
                $formattedAttendances[] = [
                    'sn' => $index++,
                    'reference_no' => $attendance->reference_no ?? '-',
                    'date_time' => $dateTime,
                    'employee' => $employeeText,
                    'in_time' => $inTime,
                    'out_time' => $outTime,
                    'time_count' => $timeCount,
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'attendances' => $formattedAttendances,
                    'summary' => [],
                    'filter_info' => [
                        'employee' => $selectedEmployee ? [
                            'name' => $selectedEmployee->name,
                            'phone' => $selectedEmployee->phone,
                            'email' => $selectedEmployee->email,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }
        
        // Return view for normal requests
        return view('report::attendance-report', compact('employees'));
    }

    /**
     * Get Product Profit Report Data
     */
    /**
     * Product Profit Report - Combined method for both view and API
     * Shows individual product/item sales with profit calculations
     */
    public function productProfitReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $itemId = $request->get('item_id');
        $calculateFormula = $request->get('calculate_formula', 'PP_Price'); // AVG or PP_Price
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $items = \Modules\Stock\Models\Item::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->select('id', 'name', 'code', 'parent_id')
            ->get();
        
        // Get selected outlet and item details for header
        $selectedOutlet = null;
        $selectedItem = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($itemId) {
            $selectedItem = \Modules\Stock\Models\Item::where('id', $itemId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'code', 'last_three_purchase_avg', 'last_purchase_price')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Build query - get sale details with relationships
            $query = \Modules\Sale\Models\SaleDetail::with(['sale', 'item', 'sale.outlet'])
                ->where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            // Apply filters
            if ($dateFrom) {
                $query->whereHas('sale', function($q) use ($dateFrom) {
                    $q->whereDate('sale_date', '>=', $dateFrom);
                });
            }
            if ($dateTo) {
                $query->whereHas('sale', function($q) use ($dateTo) {
                    $q->whereDate('sale_date', '<=', $dateTo);
                });
            }
            if ($outletId) {
                $query->where('outlet_id', $outletId);
            }
            if ($itemId) {
                $query->where('item_id', $itemId);
            }
            
            // Get all sale details
            $saleDetails = $query->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->get();
            
            // Format data for DataTable
            $formattedProductProfits = [];
            $index = 1;
            $totalSale = 0;
            $totalCost = 0;
            $totalProfit = 0;
            
            foreach ($saleDetails as $saleDetail) {
                $quantity = floatval($saleDetail->qty ?? 0);
                $saleUnitPrice = floatval($saleDetail->menu_price_with_discount ?? $saleDetail->menu_unit_price ?? 0);
                $discount = floatval($saleDetail->discount_amount ?? 0);
                $totalSaleAmount = $quantity * $saleUnitPrice;
                
                // Get costing price based on formula
                $costingPrice = 0;
                if ($saleDetail->item) {
                    if ($calculateFormula === 'AVG') {
                        $costingPrice = floatval($saleDetail->item->last_three_purchase_avg ?? 0);
                    } else {
                        // PP_Price
                        $costingPrice = floatval($saleDetail->item->last_purchase_price ?? 0);
                    }
                }
                
                $totalCostAmount = $quantity * $costingPrice;
                $profit = $totalSaleAmount - $totalCostAmount;
                
                $totalSale += $totalSaleAmount;
                $totalCost += $totalCostAmount;
                $totalProfit += $profit;
                
                // Format date time (from sale)
                $dateTime = '';
                if ($saleDetail->sale) {
                    if ($saleDetail->sale->date_time) {
                        $dateTime = formatDateTime($saleDetail->sale->date_time);
                    } elseif ($saleDetail->sale->created_at) {
                        $dateTime = formatDateTime($saleDetail->sale->created_at);
                    }
                }
                
                // Get invoice no from sale
                $invoiceNo = '-';
                if ($saleDetail->sale) {
                    $invoiceNo = $saleDetail->sale->sale_no ?? '-';
                }
                
                $formattedProductProfits[] = [
                    'sn' => $index++,
                    'invoice_no' => $invoiceNo,
                    'date_time' => $dateTime,
                    'sale_unit_price' => formatAmount($saleUnitPrice),
                    'quantity' => number_format($quantity, 2),
                    'discount' => formatAmount($discount),
                    'total_sale' => formatAmount($totalSaleAmount),
                    'costing_price' => formatAmount($costingPrice),
                    'total_cost' => formatAmount($totalCostAmount),
                    'profit' => formatAmount($profit),
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'product_profits' => $formattedProductProfits,
                    'summary' => [
                        'total_sale' => formatAmount($totalSale),
                        'total_cost' => formatAmount($totalCost),
                        'total_profit' => formatAmount($totalProfit),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'item' => $selectedItem ? [
                            'name' => $selectedItem->name . ' (' . $selectedItem->code . ')',
                            'id' => $selectedItem->id,
                        ] : null,
                        'calculate_formula' => $calculateFormula === 'AVG' ? 'AVG (Last 3 Purchase Average)' : 'PP_Price (Last Purchase Price)',
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }
        
        // Return view for normal requests
        return view('report::product-profit-report', compact('outlets', 'items'));
    }

    /**
     * Get Supplier Ledger Report Data
     */
    /**
     * Supplier Ledger Report - Combined method for both view and API
     * Shows supplier transactions with opening balance calculation
     */
    public function supplierLedgerReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $supplierId = $request->get('supplier_id');
        $outletId = $request->get('outlet_id');
        $type = $request->get('type', 'All'); // All, Debit, Credit
        
        // Get filter options (for view)
        $suppliers = Supplier::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'address')
            ->get();
        
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        // Get selected supplier and outlet details for header
        $selectedSupplier = null;
        $selectedOutlet = null;
        
        if ($supplierId) {
            $selectedSupplier = Supplier::where('id', $supplierId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address', 'opening_balance', 'opening_balance_type')
                ->first();
        }
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            if (!$supplierId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier is required'
                ], 400);
            }
            
            // Get supplier opening balance
            $openingBalance = floatval($selectedSupplier->opening_balance ?? 0);
            $openingBalanceType = $selectedSupplier->opening_balance_type ?? 'Debit';
            
            // Calculate opening balance from transactions before date_from
            $openingBalanceFromTransactions = 0;
            if ($dateFrom) {
                // Purchases before date_from (Debit)
                $purchasesBefore = Purchase::where('supplier_id', $supplierId)
                    ->where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->whereDate('date', '<', $dateFrom);
                
                if ($outletId) {
                    $purchasesBefore->where('outlet_id', $outletId);
                }
                
                $totalPurchasesBefore = $purchasesBefore->sum('grand_total');
                
                // Supplier Payments before date_from (Credit)
                $paymentsBefore = SupplierPayment::where('supplier_id', $supplierId)
                    ->where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->whereDate('date', '<', $dateFrom);
                
                if ($outletId) {
                    $paymentsBefore->where('outlet_id', $outletId);
                }
                
                $totalPaymentsBefore = $paymentsBefore->sum('amount');
                
                // Purchase Returns before date_from (Credit)
                $returnsBefore = PurchaseReturn::where('supplier_id', $supplierId)
                    ->where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->whereDate('date', '<', $dateFrom);
                
                if ($outletId) {
                    $returnsBefore->where('outlet_id', $outletId);
                }
                
                $totalReturnsBefore = $returnsBefore->sum('total_return_amount');
                
                // Calculate opening balance: Base opening balance + transactions before date
                // Base opening balance
                $baseOpeningBalance = ($openingBalanceType == 'Debit') ? $openingBalance : -$openingBalance;
                
                // Transactions before date_from: Purchases (Debit) - Payments (Credit) - Returns (Credit)
                $transactionsBefore = $totalPurchasesBefore - $totalPaymentsBefore - $totalReturnsBefore;
                
                // Final opening balance at date_from
                $openingBalanceFromTransactions = $baseOpeningBalance + $transactionsBefore;
            } else {
                // If no date filter, opening balance is just the supplier opening balance
                // (transactions will be shown separately, so opening balance is the base)
                if ($openingBalanceType == 'Debit') {
                    $openingBalanceFromTransactions = $openingBalance;
                } else {
                    // Credit means supplier owes us, so it's negative in our ledger
                    $openingBalanceFromTransactions = -$openingBalance;
                }
            }
            
            // Collect all transactions
            $transactions = collect();
            
            // Get Purchases (Debit)
            $purchasesQuery = Purchase::with(['outlet'])
                ->where('supplier_id', $supplierId)
                ->where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            if ($dateFrom) {
                $purchasesQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $purchasesQuery->whereDate('date', '<=', $dateTo);
            }
            if ($outletId) {
                $purchasesQuery->where('outlet_id', $outletId);
            }
            
            $purchases = $purchasesQuery->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
            
            foreach ($purchases as $purchase) {
                $transactions->push([
                    'date' => $purchase->date,
                    'date_time' => $purchase->created_at,
                    'transaction_type' => 'Purchase',
                    'transaction_no' => $purchase->reference_no ?? '-',
                    'debit' => floatval($purchase->grand_total ?? 0),
                    'credit' => 0,
                    'outlet' => $purchase->outlet ? $purchase->outlet->outlet_name : '-',
                    'outlet_id' => $purchase->outlet_id,
                ]);
            }
            
            // Get Supplier Payments (Credit)
            $paymentsQuery = SupplierPayment::with(['supplier', 'account'])
                ->where('supplier_id', $supplierId)
                ->where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            // Get outlets map for supplier payments
            $outletsMap = [];
            if ($outletId) {
                $outlet = Outlet::find($outletId);
                if ($outlet) {
                    $outletsMap[$outletId] = $outlet->outlet_name;
                }
            } else {
                $allOutlets = Outlet::where('company_id', $companyId)->get();
                foreach ($allOutlets as $outlet) {
                    $outletsMap[$outlet->id] = $outlet->outlet_name;
                }
            }
            
            if ($dateFrom) {
                $paymentsQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $paymentsQuery->whereDate('date', '<=', $dateTo);
            }
            if ($outletId) {
                $paymentsQuery->where('outlet_id', $outletId);
            }
            
            $payments = $paymentsQuery->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
            
            foreach ($payments as $payment) {
                $outletName = '-';
                if ($payment->outlet_id && isset($outletsMap[$payment->outlet_id])) {
                    $outletName = $outletsMap[$payment->outlet_id];
                }
                
                $transactions->push([
                    'date' => $payment->date,
                    'date_time' => $payment->created_at,
                    'transaction_type' => 'Payment',
                    'transaction_no' => $payment->reference_no ?? '-',
                    'debit' => 0,
                    'credit' => floatval($payment->amount ?? 0),
                    'outlet' => $outletName,
                    'outlet_id' => $payment->outlet_id,
                ]);
            }
            
            // Get Purchase Returns (Credit)
            $returnsQuery = PurchaseReturn::with(['outlet'])
                ->where('supplier_id', $supplierId)
                ->where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            if ($dateFrom) {
                $returnsQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $returnsQuery->whereDate('date', '<=', $dateTo);
            }
            if ($outletId) {
                $returnsQuery->where('outlet_id', $outletId);
            }
            
            $returns = $returnsQuery->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
            
            foreach ($returns as $return) {
                $transactions->push([
                    'date' => $return->date,
                    'date_time' => $return->created_at,
                    'transaction_type' => 'Purchase Return',
                    'transaction_no' => $return->reference_no ?? '-',
                    'debit' => 0,
                    'credit' => floatval($return->total_return_amount ?? 0),
                    'outlet' => $return->outlet ? $return->outlet->outlet_name : '-',
                    'outlet_id' => $return->outlet_id,
                ]);
            }
            
            // Sort all transactions by date and time
            $transactions = $transactions->sortBy(function ($transaction) {
                return $transaction['date'] . ' ' . ($transaction['date_time'] ? $transaction['date_time']->format('H:i:s') : '00:00:00');
            })->values();
            
            // Apply type filter
            if ($type == 'Debit') {
                $transactions = $transactions->filter(function ($transaction) {
                    return $transaction['debit'] > 0;
                })->values();
            } elseif ($type == 'Credit') {
                $transactions = $transactions->filter(function ($transaction) {
                    return $transaction['credit'] > 0;
                })->values();
            }
            
            // Format data for DataTable
            $formattedTransactions = [];
            $index = 1;
            $runningBalance = $openingBalanceFromTransactions;
            $totalDebit = 0;
            $totalCredit = 0;
            
            // Add opening balance row as first row (always show)
            $openingBalanceDate = $dateFrom ? formatDate($dateFrom) : 'Opening Balance';
            $formattedTransactions[] = [
                'sn' => $index++,
                'date_time' => $openingBalanceDate . ' (Opening Balance)',
                'transaction_type' => 'Opening Balance',
                'transaction_no' => '-',
                'debit' => $openingBalanceFromTransactions >= 0 ? formatAmount($openingBalanceFromTransactions) : '-',
                'credit' => $openingBalanceFromTransactions < 0 ? formatAmount(abs($openingBalanceFromTransactions)) : '-',
                'outlet' => '-',
            ];
            
            foreach ($transactions as $transaction) {
                $runningBalance += $transaction['debit'] - $transaction['credit'];
                $totalDebit += $transaction['debit'];
                $totalCredit += $transaction['credit'];
                
                // Format date time
                $dateTime = '';
                if ($transaction['date_time']) {
                    $dateTime = formatDateTime($transaction['date_time']);
                } elseif ($transaction['date']) {
                    $dateTime = formatDate($transaction['date']);
                }
                
                $formattedTransactions[] = [
                    'sn' => $index++,
                    'date_time' => $dateTime,
                    'transaction_type' => $transaction['transaction_type'],
                    'transaction_no' => $transaction['transaction_no'],
                    'debit' => $transaction['debit'] > 0 ? formatAmount($transaction['debit']) : '-',
                    'credit' => $transaction['credit'] > 0 ? formatAmount($transaction['credit']) : '-',
                    'outlet' => $transaction['outlet'],
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'transactions' => $formattedTransactions,
                    'summary' => [
                        'opening_balance' => formatAmount($openingBalanceFromTransactions),
                        'total_debit' => formatAmount($totalDebit),
                        'total_credit' => formatAmount($totalCredit),
                        'closing_balance' => formatAmount($runningBalance),
                    ],
                    'filter_info' => [
                        'supplier' => $selectedSupplier ? [
                            'name' => $selectedSupplier->name,
                            'phone' => $selectedSupplier->phone,
                            'address' => $selectedSupplier->address,
                        ] : null,
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'type' => $type,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::supplier-ledger-report', compact('suppliers', 'outlets'));
    }

    /**
     * Get Supplier Balance Report Data
     */
    /**
     * Supplier Balance Report - Combined method for both view and API
     * Shows current balance for all suppliers
     */
    public function supplierBalanceReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $type = $request->get('type', 'All'); // All, Debit, Credit
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Get all suppliers
            $suppliers = Supplier::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address', 'opening_balance', 'opening_balance_type')
                ->get();
            
            $formattedSuppliers = [];
            $index = 1;
            $totalBalance = 0;
            
            foreach ($suppliers as $supplier) {
                // Get supplier opening balance
                $openingBalance = floatval($supplier->opening_balance ?? 0);
                $openingBalanceType = $supplier->opening_balance_type ?? 'Debit';
                
                // Base opening balance
                $baseOpeningBalance = ($openingBalanceType == 'Debit') ? $openingBalance : -$openingBalance;
                
                // Calculate total purchases (Debit - we owe supplier)
                $totalPurchases = Purchase::where('supplier_id', $supplier->id)
                    ->where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->sum('grand_total');
                
                // Calculate total supplier payments (Credit - we pay supplier)
                $totalPayments = SupplierPayment::where('supplier_id', $supplier->id)
                    ->where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->sum('amount');
                
                // Calculate total purchase returns (Credit - we return money/goods to supplier)
                $totalReturns = PurchaseReturn::where('supplier_id', $supplier->id)
                    ->where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->sum('total_return_amount');
                
                // Current balance = Opening Balance + Purchases - Payments - Returns
                $currentBalance = $baseOpeningBalance + $totalPurchases - $totalPayments - $totalReturns;
                
                // Apply type filter
                if ($type == 'Debit' && $currentBalance <= 0) {
                    continue; // Skip if not Debit (positive balance - we owe supplier)
                }
                if ($type == 'Credit' && $currentBalance >= 0) {
                    continue; // Skip if not Credit (negative balance - supplier owes us)
                }
                
                $totalBalance += $currentBalance;
                
                // Format supplier name
                $supplierName = $supplier->name ?? '-';
                if ($supplier->phone) {
                    $supplierName .= ' (' . $supplier->phone . ')';
                }
                
                $formattedSuppliers[] = [
                    'sn' => $index++,
                    'supplier_name' => $supplierName,
                    'current_balance' => formatAmount($currentBalance),
                    'balance_value' => $currentBalance, // For sorting/filtering
                ];
            }
            
            // Sort by balance (highest to lowest)
            usort($formattedSuppliers, function($a, $b) {
                return $b['balance_value'] <=> $a['balance_value'];
            });
            
            // Re-number SN after sorting
            foreach ($formattedSuppliers as $key => $supplier) {
                $formattedSuppliers[$key]['sn'] = $key + 1;
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'suppliers' => $formattedSuppliers,
                    'summary' => [
                        'total_balance' => formatAmount($totalBalance),
                        'total_suppliers' => count($formattedSuppliers),
                    ],
                    'filter_info' => [
                        'type' => $type,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::supplier-balance-report');
    }

    /**
     * Get Customer Ledger Report Data
     */
    /**
     * Customer Ledger Report - Combined method for both view and API
     * Shows customer transactions with opening balance calculation
     */
    public function customerLedgerReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $customerId = $request->get('customer_id');
        $outletId = $request->get('outlet_id');
        $type = $request->get('type', 'All'); // All, Debit, Credit
        
        // Get filter options (for view)
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'address')
            ->get();
        
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        // Get selected customer and outlet details for header
        $selectedCustomer = null;
        $selectedOutlet = null;
        
        if ($customerId) {
            $selectedCustomer = Customer::where('id', $customerId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address', 'opening_balance', 'opening_balance_type')
                ->first();
        }
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            if (!$customerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer is required'
                ], 400);
            }
            
            // Get customer opening balance
            $openingBalance = floatval($selectedCustomer->opening_balance ?? 0);
            $openingBalanceType = $selectedCustomer->opening_balance_type ?? 'Debit';
            
            // Calculate opening balance from transactions before date_from
            $openingBalanceFromTransactions = 0;
            if ($dateFrom) {
                // Sales before date_from (Credit - customer owes us)
                $salesBefore = Sale::where('customer_id', $customerId)
                    ->where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->whereDate('sale_date', '<', $dateFrom);
                
                if ($outletId) {
                    $salesBefore->where('outlet_id', $outletId);
                }
                
                $totalSalesBefore = $salesBefore->sum('grand_total');
                
                // Customer Receives before date_from (Debit - customer pays us)
                $receivesBefore = CustomerReceive::where('customer_id', $customerId)
                    ->where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->whereDate('date', '<', $dateFrom);
                
                if ($outletId) {
                    $receivesBefore->where('outlet_id', $outletId);
                }
                
                $totalReceivesBefore = $receivesBefore->sum('amount');
                
                // Sale Returns before date_from (Debit - we return money/goods to customer)
                $returnsBefore = SaleReturn::where('customer_id', $customerId)
                    ->where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->whereDate('date', '<', $dateFrom);
                
                if ($outletId) {
                    $returnsBefore->where('outlet_id', $outletId);
                }
                
                $totalReturnsBefore = $returnsBefore->sum('total_return_amount');
                
                // Calculate opening balance: Base opening balance + transactions before date
                // Base opening balance
                $baseOpeningBalance = ($openingBalanceType == 'Debit') ? $openingBalance : -$openingBalance;
                
                // Transactions before date_from: Sales (Credit) - Receives (Debit) - Returns (Debit)
                // For customer: Credit increases what customer owes, Debit decreases what customer owes
                $transactionsBefore = $totalSalesBefore - $totalReceivesBefore - $totalReturnsBefore;
                
                // Final opening balance at date_from
                $openingBalanceFromTransactions = $baseOpeningBalance + $transactionsBefore;
            } else {
                // If no date filter, opening balance is just the customer opening balance
                // (transactions will be shown separately, so opening balance is the base)
                if ($openingBalanceType == 'Debit') {
                    $openingBalanceFromTransactions = $openingBalance;
                } else {
                    // Credit means we owe customer, so it's negative in our ledger
                    $openingBalanceFromTransactions = -$openingBalance;
                }
            }
            
            // Collect all transactions
            $transactions = collect();
            
            // Get Sales (Credit - customer owes us)
            $salesQuery = Sale::with(['outlet'])
                ->where('customer_id', $customerId)
                ->where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            if ($dateFrom) {
                $salesQuery->whereDate('sale_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $salesQuery->whereDate('sale_date', '<=', $dateTo);
            }
            if ($outletId) {
                $salesQuery->where('outlet_id', $outletId);
            }
            
            $sales = $salesQuery->orderBy('sale_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
            
            foreach ($sales as $sale) {
                $transactions->push([
                    'date' => $sale->sale_date,
                    'date_time' => $sale->date_time ?? $sale->created_at,
                    'transaction_type' => 'Sale',
                    'transaction_no' => $sale->sale_no ?? '-',
                    'debit' => 0,
                    'credit' => floatval($sale->grand_total ?? $sale->total_payable ?? 0),
                    'outlet' => $sale->outlet ? $sale->outlet->outlet_name : '-',
                    'outlet_id' => $sale->outlet_id,
                ]);
            }
            
            // Get Customer Receives (Debit - customer pays us)
            $receivesQuery = CustomerReceive::with(['customer', 'payment'])
                ->where('customer_id', $customerId)
                ->where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            // Get outlets map for customer receives
            $outletsMap = [];
            if ($outletId) {
                $outlet = Outlet::find($outletId);
                if ($outlet) {
                    $outletsMap[$outletId] = $outlet->outlet_name;
                }
            } else {
                $allOutlets = Outlet::where('company_id', $companyId)->get();
                foreach ($allOutlets as $outlet) {
                    $outletsMap[$outlet->id] = $outlet->outlet_name;
                }
            }
            
            if ($dateFrom) {
                $receivesQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $receivesQuery->whereDate('date', '<=', $dateTo);
            }
            if ($outletId) {
                $receivesQuery->where('outlet_id', $outletId);
            }
            
            $receives = $receivesQuery->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
            
            foreach ($receives as $receive) {
                $outletName = '-';
                if ($receive->outlet_id && isset($outletsMap[$receive->outlet_id])) {
                    $outletName = $outletsMap[$receive->outlet_id];
                }
                
                $transactions->push([
                    'date' => $receive->date,
                    'date_time' => $receive->created_at,
                    'transaction_type' => 'Payment',
                    'transaction_no' => $receive->reference_no ?? '-',
                    'debit' => floatval($receive->amount ?? 0),
                    'credit' => 0,
                    'outlet' => $outletName,
                    'outlet_id' => $receive->outlet_id,
                ]);
            }
            
            // Get Sale Returns (Debit - we return money/goods to customer)
            $returnsQuery = SaleReturn::with(['outlet'])
                ->where('customer_id', $customerId)
                ->where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            if ($dateFrom) {
                $returnsQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $returnsQuery->whereDate('date', '<=', $dateTo);
            }
            if ($outletId) {
                $returnsQuery->where('outlet_id', $outletId);
            }
            
            $returns = $returnsQuery->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
            
            foreach ($returns as $return) {
                $transactions->push([
                    'date' => $return->date,
                    'date_time' => $return->created_at,
                    'transaction_type' => 'Sale Return',
                    'transaction_no' => $return->reference_no ?? '-',
                    'debit' => floatval($return->total_return_amount ?? 0),
                    'credit' => 0,
                    'outlet' => $return->outlet ? $return->outlet->outlet_name : '-',
                    'outlet_id' => $return->outlet_id,
                ]);
            }
            
            // Sort all transactions by date and time
            $transactions = $transactions->sortBy(function ($transaction) {
                return $transaction['date'] . ' ' . ($transaction['date_time'] ? $transaction['date_time']->format('H:i:s') : '00:00:00');
            })->values();
            
            // Apply type filter
            if ($type == 'Debit') {
                $transactions = $transactions->filter(function ($transaction) {
                    return $transaction['debit'] > 0;
                })->values();
            } elseif ($type == 'Credit') {
                $transactions = $transactions->filter(function ($transaction) {
                    return $transaction['credit'] > 0;
                })->values();
            }
            
            // Format data for DataTable
            $formattedTransactions = [];
            $index = 1;
            $runningBalance = $openingBalanceFromTransactions;
            $totalDebit = 0;
            $totalCredit = 0;
            
            // Add opening balance row as first row (always show)
            $openingBalanceDate = $dateFrom ? formatDate($dateFrom) : 'Opening Balance';
            $formattedTransactions[] = [
                'sn' => $index++,
                'date_time' => $openingBalanceDate . ' (Opening Balance)',
                'transaction_type' => 'Opening Balance',
                'transaction_no' => '-',
                'debit' => $openingBalanceFromTransactions >= 0 ? formatAmount($openingBalanceFromTransactions) : '-',
                'credit' => $openingBalanceFromTransactions < 0 ? formatAmount(abs($openingBalanceFromTransactions)) : '-',
                'outlet' => '-',
            ];
            
            foreach ($transactions as $transaction) {
                $runningBalance += $transaction['debit'] - $transaction['credit'];
                $totalDebit += $transaction['debit'];
                $totalCredit += $transaction['credit'];
                
                // Format date time
                $dateTime = '';
                if ($transaction['date_time']) {
                    $dateTime = formatDateTime($transaction['date_time']);
                } elseif ($transaction['date']) {
                    $dateTime = formatDate($transaction['date']);
                }
                
                $formattedTransactions[] = [
                    'sn' => $index++,
                    'date_time' => $dateTime,
                    'transaction_type' => $transaction['transaction_type'],
                    'transaction_no' => $transaction['transaction_no'],
                    'debit' => $transaction['debit'] > 0 ? formatAmount($transaction['debit']) : '-',
                    'credit' => $transaction['credit'] > 0 ? formatAmount($transaction['credit']) : '-',
                    'outlet' => $transaction['outlet'],
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'transactions' => $formattedTransactions,
                    'summary' => [
                        'opening_balance' => formatAmount($openingBalanceFromTransactions),
                        'total_debit' => formatAmount($totalDebit),
                        'total_credit' => formatAmount($totalCredit),
                        'closing_balance' => formatAmount($runningBalance),
                    ],
                    'filter_info' => [
                        'customer' => $selectedCustomer ? [
                            'name' => $selectedCustomer->name,
                            'phone' => $selectedCustomer->phone,
                            'address' => $selectedCustomer->address,
                        ] : null,
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'type' => $type,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::customer-ledger-report', compact('customers', 'outlets'));
    }

    /**
     * Get Customer Balance Report Data
     */
    /**
     * Customer Balance Report - Combined method for both view and API
     * Shows current balance for all customers
     */
    public function customerBalanceReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $type = $request->get('type', 'All'); // All, Debit, Credit
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Get all customers
            $customers = Customer::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address', 'opening_balance', 'opening_balance_type')
                ->get();
            
            $formattedCustomers = [];
            $index = 1;
            $totalBalance = 0;
            
            foreach ($customers as $customer) {
                // Get customer opening balance
                $openingBalance = floatval($customer->opening_balance ?? 0);
                $openingBalanceType = $customer->opening_balance_type ?? 'Debit';
                
                // Base opening balance
                $baseOpeningBalance = ($openingBalanceType == 'Debit') ? $openingBalance : -$openingBalance;
                
                // Calculate total sales (Credit - customer owes us)
                $totalSales = Sale::where('customer_id', $customer->id)
                    ->where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->sum('grand_total');
                
                // Calculate total customer receives (Debit - customer pays us)
                $totalReceives = CustomerReceive::where('customer_id', $customer->id)
                    ->where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->sum('amount');
                
                // Calculate total sale returns (Debit - we return money/goods to customer)
                $totalReturns = SaleReturn::where('customer_id', $customer->id)
                    ->where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->sum('total_return_amount');
                
                // Current balance = Opening Balance + Sales - Receives - Returns
                $currentBalance = $baseOpeningBalance + $totalSales - $totalReceives - $totalReturns;
                
                // Apply type filter
                if ($type == 'Debit' && $currentBalance <= 0) {
                    continue; // Skip if not Debit (positive balance)
                }
                if ($type == 'Credit' && $currentBalance >= 0) {
                    continue; // Skip if not Credit (negative balance)
                }
                
                $totalBalance += $currentBalance;
                
                // Format customer name
                $customerName = $customer->name ?? '-';
                if ($customer->phone) {
                    $customerName .= ' (' . $customer->phone . ')';
                }
                
                $formattedCustomers[] = [
                    'sn' => $index++,
                    'customer_name' => $customerName,
                    'current_balance' => formatAmount($currentBalance),
                    'balance_value' => $currentBalance, // For sorting/filtering
                ];
            }
            
            // Sort by balance (highest to lowest)
            usort($formattedCustomers, function($a, $b) {
                return $b['balance_value'] <=> $a['balance_value'];
            });
            
            // Re-number SN after sorting
            foreach ($formattedCustomers as $key => $customer) {
                $formattedCustomers[$key]['sn'] = $key + 1;
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'customers' => $formattedCustomers,
                    'summary' => [
                        'total_balance' => formatAmount($totalBalance),
                        'total_customers' => count($formattedCustomers),
                    ],
                    'filter_info' => [
                        'type' => $type,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::customer-balance-report');
    }

    /**
     * Servicing Report - Combined method for both view and API
     */
    public function servicingReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $customerId = $request->get('customer_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'address')
            ->get();
        
        // Build query
        $query = Servicing::with(['customer', 'outlet', 'employee'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);
        
        // Apply filters
        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        
        // Get all servicings
        $servicings = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        
        // Get selected outlet and customer details for header
        $selectedOutlet = null;
        $selectedCustomer = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($customerId) {
            $selectedCustomer = Customer::where('id', $customerId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Format data for DataTable
            $formattedServicings = [];
            $index = 1;
            $totalServicingCharge = 0;
            $totalPaidAmount = 0;
            $totalDueAmount = 0;
            
            foreach ($servicings as $servicing) {
                $servicingCharge = floatval($servicing->servicing_charge ?? 0);
                $paidAmount = floatval($servicing->paid_amount ?? 0);
                $dueAmount = floatval($servicing->due_amount ?? 0);
                
                $totalServicingCharge += $servicingCharge;
                $totalPaidAmount += $paidAmount;
                $totalDueAmount += $dueAmount;
                
                // Build details string
                $details = [];
                if ($servicing->product_name) {
                    $details[] = $servicing->product_name;
                }
                if ($servicing->product_model) {
                    $details[] = '(' . $servicing->product_model . ')';
                }
                if ($servicing->problem_description) {
                    $details[] = '- ' . $servicing->problem_description;
                }
                $detailsText = !empty($details) ? implode(' ', $details) : '-';
                
                // Format customer name and mobile
                $customerText = '-';
                if ($servicing->customer) {
                    $customerText = $servicing->customer->name ?? '-';
                    if ($servicing->customer->phone) {
                        $customerText .= ' (' . $servicing->customer->phone . ')';
                    }
                }
                
                $formattedServicings[] = [
                    'sn' => $index++,
                    'date' => $servicing->date ? formatDate($servicing->date) : null,
                    'delivery_date' => $servicing->delivery_date ? formatDate($servicing->delivery_date) : '-',
                    'customer' => $customerText,
                    'details' => $detailsText,
                    'servicing_charge' => formatAmount($servicingCharge),
                    'paid_amount' => formatAmount($paidAmount),
                    'due_amount' => formatAmount($dueAmount),
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'servicings' => $formattedServicings,
                    'summary' => [
                        'total_servicing_charge' => formatAmount($totalServicingCharge),
                        'total_paid_amount' => formatAmount($totalPaidAmount),
                        'total_due_amount' => formatAmount($totalDueAmount),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'customer' => $selectedCustomer ? [
                            'name' => $selectedCustomer->name,
                            'phone' => $selectedCustomer->phone,
                            'address' => $selectedCustomer->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }
        
        // Return view for normal requests
        return view('report::servicing-report', compact('outlets', 'customers'));
    }

    /**
     * Get Product Sale Report Data
     */
    /**
     * Product Sale Report - Combined method for both view and API
     * Shows individual product/item sales from sale details
     */
    public function productSaleReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $itemId = $request->get('item_id');
        $customerId = $request->get('customer_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $items = \Modules\Stock\Models\Item::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->select('id', 'name', 'code', 'parent_id')
            ->get();
        
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'address')
            ->get();
        
        // Build query - get sale details with relationships
        $query = \Modules\Sale\Models\SaleDetail::with(['sale', 'item', 'sale.customer', 'sale.outlet'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);
        
        // Apply filters
        if ($dateFrom) {
            $query->whereHas('sale', function($q) use ($dateFrom) {
                $q->whereDate('sale_date', '>=', $dateFrom);
            });
        }
        if ($dateTo) {
            $query->whereHas('sale', function($q) use ($dateTo) {
                $q->whereDate('sale_date', '<=', $dateTo);
            });
        }
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }
        if ($itemId) {
            $query->where('item_id', $itemId);
        }
        if ($customerId) {
            $query->whereHas('sale', function($q) use ($customerId) {
                $q->where('customer_id', $customerId);
            });
        }
        
        // Get all sale details
        $saleDetails = $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        
        // Get sale IDs to count items per sale
        $saleIds = $saleDetails->pluck('sales_id')->unique()->toArray();
        $saleItemCounts = [];
        if (!empty($saleIds)) {
            $counts = \Modules\Sale\Models\SaleDetail::whereIn('sales_id', $saleIds)
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->selectRaw('sales_id, COUNT(*) as item_count')
                ->groupBy('sales_id')
                ->get();
            
            foreach ($counts as $count) {
                $saleItemCounts[$count->sales_id] = $count->item_count;
            }
        }
        
        // Get selected outlet, item, and customer details for header
        $selectedOutlet = null;
        $selectedItem = null;
        $selectedCustomer = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($itemId) {
            $selectedItem = \Modules\Stock\Models\Item::where('id', $itemId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'code')
                ->first();
        }
        
        if ($customerId) {
            $selectedCustomer = Customer::where('id', $customerId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Format data for DataTable
            $formattedProductSales = [];
            $index = 1;
            $totalAmount = 0;
            
            foreach ($saleDetails as $saleDetail) {
                $quantity = floatval($saleDetail->qty ?? 0);
                $unitPrice = floatval($saleDetail->menu_price_with_discount ?? $saleDetail->menu_unit_price ?? 0);
                $lineTotal = $quantity * $unitPrice;
                $totalAmount += $lineTotal;
                
                // Format date time (from sale)
                $dateTime = '';
                if ($saleDetail->sale) {
                    if ($saleDetail->sale->date_time) {
                        $dateTime = formatDateTime($saleDetail->sale->date_time);
                    } elseif ($saleDetail->sale->created_at) {
                        $dateTime = formatDateTime($saleDetail->sale->created_at);
                    }
                }
                
                // Get invoice no from sale
                $invoiceNo = '-';
                if ($saleDetail->sale) {
                    $invoiceNo = $saleDetail->sale->sale_no ?? '-';
                }
                
                // Get items count for this sale
                $itemsCount = $saleItemCounts[$saleDetail->sales_id] ?? 0;
                
                // Format item/product
                $itemProduct = '-';
                if ($saleDetail->item) {
                    $itemProduct = $saleDetail->item->name ?? '-';
                    if ($saleDetail->item->code) {
                        $itemProduct .= ' (' . $saleDetail->item->code . ')';
                    }
                }
                
                $formattedProductSales[] = [
                    'sn' => $index++,
                    'invoice_no' => $invoiceNo,
                    'date_time' => $dateTime,
                    'items' => $itemsCount,
                    'item_product' => $itemProduct,
                    'quantity' => number_format($quantity, 2),
                    'unit_price' => formatAmount($unitPrice),
                    'total' => formatAmount($lineTotal),
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'product_sales' => $formattedProductSales,
                    'summary' => [
                        'total_amount' => formatAmount($totalAmount),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'item' => $selectedItem ? [
                            'name' => $selectedItem->name . ' (' . $selectedItem->code . ')',
                            'id' => $selectedItem->id,
                        ] : null,
                        'customer' => $selectedCustomer ? [
                            'name' => $selectedCustomer->name,
                            'phone' => $selectedCustomer->phone,
                            'address' => $selectedCustomer->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }
        
        // Return view for normal requests
        return view('report::product-sale-report', compact('outlets', 'items', 'customers'));
    }

    /**
     * Get Tax Report Data
     */
    /**
     * Tax Report - Combined method for both view and API
     * Shows sale-wise tax information from sale_vat_objects
     */
    public function taxReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        // Get selected outlet details for header
        $selectedOutlet = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Build query - get sales with relationships
            $query = Sale::with(['outlet'])
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->whereNotNull('sale_vat_objects')
                ->where('sale_vat_objects', '!=', '');
            
            // Apply filters
            if ($dateFrom) {
                $query->whereDate('sale_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('sale_date', '<=', $dateTo);
            }
            if ($outletId) {
                $query->where('outlet_id', $outletId);
            }
            
            // Get all sales
            $sales = $query->orderBy('sale_date', 'desc')
                ->orderBy('id', 'desc')
                ->get();
            
            // Format data for DataTable
            $formattedTaxes = [];
            $index = 1;
            $totalSale = 0;
            $totalTax = 0;
            
            foreach ($sales as $sale) {
                $totalSaleAmount = floatval($sale->total_payable ?? $sale->total_payable ?? 0);
                $totalSale += $totalSaleAmount;
                
                // Parse sale_vat_objects JSON
                $appliedTaxAmount = '-';
                $saleTotalTax = 0;
                
                if ($sale->sale_vat_objects) {
                    try {
                        $vatObjects = json_decode($sale->sale_vat_objects, true);
                        if (is_array($vatObjects) && !empty($vatObjects)) {
                            $taxParts = [];
                            foreach ($vatObjects as $vatObj) {
                                $taxType = $vatObj['tax_field_type'] ?? '';
                                $taxAmount = floatval($vatObj['tax_field_amount'] ?? 0);
                                $saleTotalTax += $taxAmount;
                                
                                if ($taxType && $taxAmount > 0) {
                                    $taxParts[] = $taxType . ':' . number_format($taxAmount, 2);
                                }
                            }
                            $appliedTaxAmount = !empty($taxParts) ? implode(', ', $taxParts) : '-';
                        }
                    } catch (\Exception $e) {
                        // If JSON decode fails, set default
                        $appliedTaxAmount = '-';
                    }
                }
                
                $totalTax += $saleTotalTax;
                
                // Format date time
                $dateTime = '';
                if ($sale->date_time) {
                    $dateTime = formatDateTime($sale->date_time);
                } elseif ($sale->created_at) {
                    $dateTime = formatDateTime($sale->created_at);
                }
                
                $formattedTaxes[] = [
                    'sn' => $index++,
                    'invoice_no' => $sale->sale_no ?? '-',
                    'date_time' => $dateTime,
                    'total_sale' => formatAmount($totalSaleAmount),
                    'applied_tax_amount' => $appliedTaxAmount,
                    'total_tax' => formatAmount($saleTotalTax),
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'taxes' => $formattedTaxes,
                    'summary' => [
                        'total_sale' => formatAmount($totalSale),
                        'total_tax' => formatAmount($totalTax),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::tax-report', compact('outlets'));
    }

    /**
     * GST Reports - Unified method for 8 GST report formats
     * report_type: tax-summary, monthly-summary, rate-wise, b2b, b2c-small, b2c-large, hsn-summary, gstr3b
     */
    public function gstReport(Request $request)
    {
        $companyId = session('company.company_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $reportType = $request->get('report_type', 'tax-summary');

        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();

        $selectedOutlet = $outletId ? Outlet::where('id', $outletId)->where('company_id', $companyId)
            ->select('id', 'outlet_name', 'phone', 'address')->first() : null;

        if ($request->ajax() || $request->wantsJson()) {
            $data = null;
            switch ($reportType) {
                case 'tax-summary':
                    $data = GstReportService::getGstTaxSummaryReport($companyId, $dateFrom, $dateTo, $outletId);
                    break;
                case 'monthly-summary':
                    $data = GstReportService::getGstMonthlySummaryReport($companyId, $dateFrom, $dateTo, $outletId);
                    break;
                case 'rate-wise':
                    $data = GstReportService::getGstRateWiseSummaryReport($companyId, $dateFrom, $dateTo, $outletId);
                    break;
                case 'b2b':
                    $data = GstReportService::getB2BReport($companyId, $dateFrom, $dateTo, $outletId);
                    break;
                case 'b2c-small':
                    $data = GstReportService::getB2CSmallReport($companyId, $dateFrom, $dateTo, $outletId);
                    break;
                case 'b2c-large':
                    $data = GstReportService::getB2CLargeReport($companyId, $dateFrom, $dateTo, $outletId);
                    break;
                case 'hsn-summary':
                    $data = GstReportService::getHsnSummaryReport($companyId, $dateFrom, $dateTo, $outletId);
                    break;
                case 'gstr3b':
                    $data = GstReportService::getGstr3bSummaryReport($companyId, $dateFrom, $dateTo, $outletId);
                    break;
                default:
                    $data = GstReportService::getGstTaxSummaryReport($companyId, $dateFrom, $dateTo, $outletId);
            }

            $filterInfo = [
                'outlet' => $selectedOutlet ? ['name' => $selectedOutlet->outlet_name, 'phone' => $selectedOutlet->phone, 'address' => $selectedOutlet->address] : null,
                'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                'date_to' => $dateTo ? formatDate($dateTo) : null,
                'generated_at' => formatDateTime(now()),
                'generated_by' => auth()->user() ? ['name' => auth()->user()->name ?? '', 'phone' => auth()->user()->phone ?? ''] : null,
            ];

            $data = $this->formatGstReportAmounts($data, $reportType);

            return response()->json([
                'success' => true,
                'report_type' => $reportType,
                'data' => $data,
                'filter_info' => $filterInfo,
            ]);
        }

        return view('report::gst-report', compact('outlets'));
    }

    /**
     * Format amount fields in GST report data (controller-side formatting)
     */
    private function formatGstReportAmounts(array $data, string $reportType): array
    {
        $format = fn ($val) => formatAmount($val ?? 0);

        if ($reportType === 'monthly-summary' && isset($data['particulars'])) {
            foreach ($data['particulars'] as $i => &$p) {
                if ($i === 0) {
                    $p['amount'] = (string) ($p['amount'] ?? 0);
                } else {
                    $p['amount'] = $format($p['amount'] ?? 0);
                }
            }
            return $data;
        }

        if (isset($data['rows'])) {
            $amountKeys = match ($reportType) {
                'tax-summary', 'rate-wise' => ['taxable_value', 'cgst', 'sgst', 'igst', 'total_tax'],
                'b2b' => ['taxable_value', 'cgst', 'sgst', 'igst', 'total_invoice_value'],
                'b2c-small' => ['taxable_value', 'cgst', 'sgst', 'igst', 'total'],
                'b2c-large' => ['taxable_value', 'total_invoice_value'],
                'hsn-summary' => ['total_qty', 'taxable_value'],
                'gstr3b' => ['taxable_value', 'total_tax'],
                default => [],
            };
            foreach ($data['rows'] as &$row) {
                foreach ($amountKeys as $key) {
                    if (isset($row[$key])) {
                        $row[$key] = $format($row[$key]);
                    }
                }
            }
        }

        if (isset($data['totals'])) {
            $totalsKeys = match ($reportType) {
                'tax-summary', 'rate-wise' => ['taxable_value', 'cgst', 'sgst', 'igst', 'total_tax'],
                'b2b' => ['taxable_value', 'cgst', 'sgst', 'igst', 'total_invoice_value'],
                'b2c-small' => ['taxable_value', 'cgst', 'sgst', 'igst', 'total'],
                'b2c-large' => ['taxable_value', 'total_invoice_value'],
                'hsn-summary' => ['total_qty', 'taxable_value'],
                'gstr3b' => ['taxable_value', 'total_tax'],
                default => [],
            };
            foreach ($totalsKeys as $key) {
                if (isset($data['totals'][$key])) {
                    $data['totals'][$key] = $format($data['totals'][$key]);
                }
            }
        }

        return $data;
    }

    /**
     * Get Detailed Sale Report Data
     */
    /**
     * Detailed Sale Report - Combined method for both view and API
     * Shows individual sale details (items) with payment information
     */
    public function detailedSaleReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $employeeId = $request->get('employee_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $employees = User::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'email')
            ->orderBy('name', 'asc')
            ->get();
        
        // Get selected outlet and employee details for header
        $selectedOutlet = null;
        $selectedEmployee = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($employeeId) {
            $selectedEmployee = User::where('id', $employeeId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'email')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Build query - get sale details with relationships
            $query = \Modules\Sale\Models\SaleDetail::with([
                'sale', 
                'sale.customer', 
                'sale.outlet', 
                'sale.user',
                'sale.salePayments.paymentMethod',
                'item'
            ])
                ->where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            // Apply filters
            if ($dateFrom) {
                $query->whereHas('sale', function($q) use ($dateFrom) {
                    $q->whereDate('sale_date', '>=', $dateFrom);
                });
            }
            if ($dateTo) {
                $query->whereHas('sale', function($q) use ($dateTo) {
                    $q->whereDate('sale_date', '<=', $dateTo);
                });
            }
            if ($outletId) {
                $query->where('outlet_id', $outletId);
            }
            if ($employeeId) {
                $query->whereHas('sale', function($q) use ($employeeId) {
                    $q->where('user_id', $employeeId);
                });
            }
            
            // Get all sale details
            $saleDetails = $query->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->get();
            
            // Get sale IDs to count items per sale
            $saleIds = $saleDetails->pluck('sales_id')->unique()->toArray();
            $saleItemCounts = [];
            if (!empty($saleIds)) {
                $counts = \Modules\Sale\Models\SaleDetail::whereIn('sales_id', $saleIds)
                    ->where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->selectRaw('sales_id, COUNT(*) as item_count')
                    ->groupBy('sales_id')
                    ->get();
                
                foreach ($counts as $count) {
                    $saleItemCounts[$count->sales_id] = $count->item_count;
                }
            }
            
            // Format data for DataTable
            $formattedDetailedSales = [];
            $index = 1;
            $totalSubtotal = 0;
            $totalDiscount = 0;
            $totalTax = 0;
            $totalGrandTotal = 0;
            $totalPaid = 0;
            $totalDue = 0;
            
            // Track which sales we've already counted to avoid double counting
            $countedSales = [];
            
            foreach ($saleDetails as $saleDetail) {
                $sale = $saleDetail->sale;
                if (!$sale) continue;
                
                // Get sale-level totals (for this sale)
                $saleSubtotal = floatval($sale->sub_total ?? 0);
                $saleDiscount = floatval($sale->total_discount_amount ?? 0);
                $saleTax = floatval($sale->vat ?? 0);
                $saleGrandTotal = floatval($sale->grand_total ?? $sale->total_payable ?? 0);
                $salePaid = floatval($sale->paid_amount ?? 0);
                $saleDue = floatval($sale->due_amount ?? 0);
                
                // Accumulate totals (using sale totals, not line totals, to avoid double counting)
                // We'll only add once per sale, so we need to track which sales we've counted
                if (!in_array($sale->id, $countedSales)) {
                    $totalSubtotal += $saleSubtotal;
                    $totalDiscount += $saleDiscount;
                    $totalTax += $saleTax;
                    $totalGrandTotal += $saleGrandTotal;
                    $totalPaid += $salePaid;
                    $totalDue += $saleDue;
                    $countedSales[] = $sale->id;
                }
                
                // Format date time (from sale)
                $dateTime = '';
                if ($sale->date_time) {
                    $dateTime = formatDateTime($sale->date_time);
                } elseif ($sale->created_at) {
                    $dateTime = formatDateTime($sale->created_at);
                }
                
                // Get invoice no from sale
                $invoiceNo = '-';
                if ($sale) {
                    $invoiceNo = $sale->sale_no ?? '-';
                }
                
                // Get total items for this sale
                $totalItems = $saleItemCounts[$sale->id] ?? 0;
                
                // Format item
                $itemText = '-';
                if ($saleDetail->item) {
                    $itemText = $saleDetail->item->name ?? '-';
                    if ($saleDetail->item->code) {
                        $itemText .= ' (' . $saleDetail->item->code . ')';
                    }
                }
                
                // Get payment method(s) from sale payments
                $paymentMethods = [];
                if ($sale->salePayments && $sale->salePayments->count() > 0) {
                    foreach ($sale->salePayments as $payment) {
                        if ($payment->paymentMethod) {
                            $paymentMethods[] = $payment->paymentMethod->name ?? '-';
                        } else {
                            $paymentMethods[] = $payment->payment_name ?? '-';
                        }
                    }
                }
                $paymentMethodText = !empty($paymentMethods) ? implode(', ', array_unique($paymentMethods)) : '-';
                
                $formattedDetailedSales[] = [
                    'sn' => $index++,
                    'invoice_no' => $invoiceNo,
                    'date_time' => $dateTime,
                    'total_items' => $totalItems,
                    'item' => $itemText,
                    'subtotal' => formatAmount($saleSubtotal), // Show sale subtotal for consistency
                    'discount' => formatAmount($saleDiscount), // Show sale discount
                    'tax' => formatAmount($saleTax), // Show sale tax
                    'grand_total' => formatAmount($saleGrandTotal),
                    'paid_amount' => formatAmount($salePaid),
                    'due_amount' => formatAmount($saleDue),
                    'payment_method' => $paymentMethodText,
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'detailed_sales' => $formattedDetailedSales,
                    'summary' => [
                        'total_subtotal' => formatAmount($totalSubtotal),
                        'total_discount' => formatAmount($totalDiscount),
                        'total_tax' => formatAmount($totalTax),
                        'total_grand_total' => formatAmount($totalGrandTotal),
                        'total_paid' => formatAmount($totalPaid),
                        'total_due' => formatAmount($totalDue),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'employee' => $selectedEmployee ? [
                            'name' => $selectedEmployee->name,
                            'phone' => $selectedEmployee->phone,
                            'email' => $selectedEmployee->email,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }
        
        // Return view for normal requests
        return view('report::detailed-sale-report', compact('outlets', 'employees'));
    }

    /**
     * Get Profit Loss Report Data
     */
    public function profitLossReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $costingMethod = $request->get('costing_method', 'last_purchase_price'); // Default to last_purchase_price
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        // Get selected outlet details for header
        $selectedOutlet = null;
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Initialize all values
            $totalSales = 0;
            $totalCostOfSale = 0;
            $totalTax = 0;
            $totalDeliveryService = 0;
            $totalDiscount = 0;
            $totalInstallmentSale = 0;
            $totalIncome = 0;
            $totalSaleReturn = 0;
            $totalCostOfSaleReturn = 0;
            $totalServicing = 0;
            $totalSalaries = 0;
            $totalExpense = 0;
            
            // 1. Total Sales (Paid & Unpaid) (Incl. Tax & Discount)
            $salesQuery = Sale::where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            if ($outletId) {
                $salesQuery->where('outlet_id', $outletId);
            }
            
            if ($dateFrom) {
                $salesQuery->whereDate('sale_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $salesQuery->whereDate('sale_date', '<=', $dateTo);
            }
            
            $totalSales = floatval($salesQuery->sum('total_payable'));
            
            // 2. Total Cost of Sale (using costing method)
            $saleDetailsQuery = \Modules\Sale\Models\SaleDetail::with('item')
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->whereHas('sale', function($q) use ($dateFrom, $dateTo, $outletId, $companyId) {
                    $q->where('del_status', 'Live')
                      ->where('company_id', $companyId);
                    
                    if ($outletId) {
                        $q->where('outlet_id', $outletId);
                    }
                    
                    if ($dateFrom) {
                        $q->whereDate('sale_date', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $q->whereDate('sale_date', '<=', $dateTo);
                    }
                });
            
            if ($outletId) {
                $saleDetailsQuery->where('outlet_id', $outletId);
            }
            
            $saleDetails = $saleDetailsQuery->get();
            
            foreach ($saleDetails as $saleDetail) {
                $quantity = floatval($saleDetail->qty ?? 0);
                $costingPrice = 0;
                
                if ($saleDetail->item) {
                    if ($costingMethod === 'last_three_purchase_avg') {
                        $costingPrice = floatval($saleDetail->item->last_three_purchase_avg ?? 0);
                    } else {
                        // last_purchase_price
                        $costingPrice = floatval($saleDetail->item->last_purchase_price ?? 0);
                    }
                }
                
                $totalCostOfSale += $quantity * $costingPrice;
            }
            
            // 3. Tax
            $totalTax = floatval($salesQuery->sum('vat'));
            
            // 4. Delivery/Service
            $totalDeliveryService = floatval($salesQuery->sum('delivery_charge'));
            
            // 5. Discount
            $totalDiscount = floatval($salesQuery->sum('total_discount_amount'));
            
            // 6. Installment Sale (Incl. (Delivery Charge + Percentage of Interest) - Discount)
            $installmentSalesQuery = InstallmentSale::where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            if ($outletId) {
                $installmentSalesQuery->where('outlet_id', $outletId);
            }
            
            if ($dateFrom) {
                $installmentSalesQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $installmentSalesQuery->whereDate('date', '<=', $dateTo);
            }
            
            $installmentSales = $installmentSalesQuery->get();
            
            foreach ($installmentSales as $installmentSale) {
                $total = floatval($installmentSale->total ?? 0);
                $shippingOther = floatval($installmentSale->shipping_other ?? 0);
                $interestAmount = floatval($installmentSale->interest_amount ?? 0);
                $discountAmount = floatval($installmentSale->discount_amount ?? 0);
                
                $totalInstallmentSale += ($total + $shippingOther + $interestAmount - $discountAmount);
            }
            
            // 7. Income
            $incomeQuery = Income::where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            if ($dateFrom) {
                $incomeQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $incomeQuery->whereDate('date', '<=', $dateTo);
            }
            
            $totalIncome = floatval($incomeQuery->sum('amount'));
            
            // 8. Sale Return
            $saleReturnQuery = SaleReturn::where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            if ($outletId) {
                $saleReturnQuery->where('outlet_id', $outletId);
            }
            
            if ($dateFrom) {
                $saleReturnQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $saleReturnQuery->whereDate('date', '<=', $dateTo);
            }
            
            $totalSaleReturn = floatval($saleReturnQuery->sum('total_return_amount'));
            
            // 9. Cost Of Sale Return (using costing method)
            $saleReturnDetailsQuery = \Modules\Sale\Models\SaleReturnDetail::with('item')
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->whereHas('saleReturn', function($q) use ($dateFrom, $dateTo, $outletId, $companyId) {
                    $q->where('del_status', 'Live')
                      ->where('company_id', $companyId);
                    
                    if ($outletId) {
                        $q->where('outlet_id', $outletId);
                    }
                    
                    if ($dateFrom) {
                        $q->whereDate('date', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $q->whereDate('date', '<=', $dateTo);
                    }
                });
            
            if ($outletId) {
                $saleReturnDetailsQuery->where('outlet_id', $outletId);
            }
            
            $saleReturnDetails = $saleReturnDetailsQuery->get();
            
            foreach ($saleReturnDetails as $saleReturnDetail) {
                $quantity = floatval($saleReturnDetail->return_quantity_amount ?? 0);
                $costingPrice = 0;
                
                if ($saleReturnDetail->item) {
                    if ($costingMethod === 'last_three_purchase_avg') {
                        $costingPrice = floatval($saleReturnDetail->item->last_three_purchase_avg ?? 0);
                    } else {
                        // last_purchase_price
                        $costingPrice = floatval($saleReturnDetail->item->last_purchase_price ?? 0);
                    }
                }
                
                $totalCostOfSaleReturn += $quantity * $costingPrice;
            }
            
            // 10. Servicing
            $servicingQuery = Servicing::where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            if ($outletId) {
                $servicingQuery->where('outlet_id', $outletId);
            }
            
            if ($dateFrom) {
                $servicingQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $servicingQuery->whereDate('date', '<=', $dateTo);
            }
            
            $totalServicing = floatval($servicingQuery->sum('servicing_charge'));
            
            // 11. Gross Profit = (1+4+6+7+10) - (2+3+5+8+9)
            $grossProfit = ($totalSales + $totalDeliveryService + $totalInstallmentSale + $totalIncome + $totalServicing) 
                         - ($totalCostOfSale + $totalTax + $totalDiscount + $totalSaleReturn + $totalCostOfSaleReturn);
            
            // 12. Total Salaries
            $salaryQuery = \Modules\Administrator\Models\Salary::where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            if ($dateFrom) {
                $salaryQuery->whereDate('generated_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $salaryQuery->whereDate('generated_date', '<=', $dateTo);
            }
            
            $totalSalaries = floatval($salaryQuery->sum('total_amount'));
            
            // 13. Expense
            $expenseQuery = Expense::where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            if ($dateFrom) {
                $expenseQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $expenseQuery->whereDate('date', '<=', $dateTo);
            }
            
            $totalExpense = floatval($expenseQuery->sum('amount'));
            
            // 14. Net Profit = (11) - (12+13)
            $netProfit = $grossProfit - ($totalSalaries + $totalExpense);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_sales' => formatAmount($totalSales),
                    'total_cost_of_sale' => formatAmount($totalCostOfSale),
                    'tax' => formatAmount($totalTax),
                    'delivery_service' => formatAmount($totalDeliveryService),
                    'discount' => formatAmount($totalDiscount),
                    'installment_sale' => formatAmount($totalInstallmentSale),
                    'income' => formatAmount($totalIncome),
                    'sale_return' => formatAmount($totalSaleReturn),
                    'cost_of_sale_return' => formatAmount($totalCostOfSaleReturn),
                    'servicing' => formatAmount($totalServicing),
                    'gross_profit' => formatAmount($grossProfit),
                    'total_salaries' => formatAmount($totalSalaries),
                    'expense' => formatAmount($totalExpense),
                    'net_profit' => formatAmount($netProfit),
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'costing_method' => $costingMethod === 'last_three_purchase_avg' ? 'Last 3 Purchase AVG' : 'Last Purchase Price',
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }
        
        // Return view for normal requests
        return view('report::profit-loss-report', compact('outlets'));
    }

    /**
     * Purchase Report - Combined method for both view and API
     */
    public function purchaseReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $supplierId = $request->get('supplier_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $suppliers = \Modules\Purchase\Models\Supplier::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'address')
            ->get();
        
        // Build query
        $query = \Modules\Purchase\Models\Purchase::with(['supplier', 'outlet', 'user'])
            ->withCount(['purchaseDetails as total_items' => function($q) {
                $q->where('del_status', 'Live');
            }])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);
        
        // Apply filters
        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        
        // Get all purchases
        $purchases = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        
        // Get selected outlet and supplier details for header
        $selectedOutlet = null;
        $selectedSupplier = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($supplierId) {
            $selectedSupplier = \Modules\Purchase\Models\Supplier::where('id', $supplierId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Format data for DataTable
            $formattedPurchases = [];
            $index = 1;
            $totalGrandTotal = 0;
            $totalPaid = 0;
            $totalDue = 0;
            
            foreach ($purchases as $purchase) {
                $grandTotal = floatval($purchase->grand_total ?? 0);
                $paid = floatval($purchase->paid ?? 0);
                $due = floatval($purchase->due_amount ?? 0);
                
                $totalGrandTotal += $grandTotal;
                $totalPaid += $paid;
                $totalDue += $due;
                
                // Format date time (created_at)
                $dateTime = '';
                if ($purchase->created_at) {
                    $dateTime = formatDateTime($purchase->created_at);
                }
                
                // Format purchase date
                $purchaseDate = '';
                if ($purchase->date) {
                    $purchaseDate = formatDate($purchase->date);
                }
                
                // Format supplier
                $supplierText = '-';
                if ($purchase->supplier) {
                    $supplierText = $purchase->supplier->name ?? '-';
                    if ($purchase->supplier->phone) {
                        $supplierText .= ' (' . $purchase->supplier->phone . ')';
                    }
                }
                
                // Format purchase by (user) - name with phone number, same format as supplier
                $purchaseBy = '-';
                if ($purchase->user_id && $purchase->user) {
                    $purchaseBy = $purchase->user->name ?? '-';
                    if ($purchase->user->phone) {
                        $purchaseBy .= ' (' . $purchase->user->phone . ')';
                    }
                } elseif ($purchase->user_id) {
                    // If user_id exists but user relationship didn't load, try to load it
                    $user = User::where('id', $purchase->user_id)
                        ->where('company_id', $companyId)
                        ->select('id', 'name', 'phone')
                        ->first();
                    if ($user) {
                        $purchaseBy = $user->name ?? '-';
                        if ($user->phone) {
                            $purchaseBy .= ' (' . $user->phone . ')';
                        }
                    }
                }
                
                $formattedPurchases[] = [
                    'sn' => $index++,
                    'reference_no' => $purchase->reference_no ?? '-',
                    'date_time' => $dateTime,
                    'purchase_date' => $purchaseDate,
                    'supplier' => $supplierText,
                    'items' => $purchase->total_items ?? 0,
                    'grand_total' => formatAmount($grandTotal),
                    'paid' => formatAmount($paid),
                    'due' => formatAmount($due),
                    'purchase_by' => $purchaseBy,
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'purchases' => $formattedPurchases,
                    'summary' => [
                        'total_grand_total' => formatAmount($totalGrandTotal),
                        'total_paid' => formatAmount($totalPaid),
                        'total_due' => formatAmount($totalDue),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'supplier' => $selectedSupplier ? [
                            'name' => $selectedSupplier->name,
                            'phone' => $selectedSupplier->phone,
                            'address' => $selectedSupplier->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::purchase-report', compact('outlets', 'suppliers'));
    }

    /**
     * Expense Report - Combined method for both view and API
     */
    public function expenseReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $categoryId = $request->get('category_id');
        $employeeId = $request->get('employee_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $categories = \Modules\Accounting\Models\ExpenseCategory::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();
        
        $employees = User::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'email')
            ->orderBy('name', 'asc')
            ->get();
        
        // Build query
        $query = \Modules\Accounting\Models\Expense::with(['category', 'employee'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);
        
        // Apply filters
        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }
        // Note: Expenses table doesn't have outlet_id field, so we skip outlet filter
        // if ($outletId) {
        //     $query->where('outlet_id', $outletId);
        // }
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }
        
        // Get all expenses
        $expenses = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        
        // Get selected outlet, category, and employee details for header
        $selectedOutlet = null;
        $selectedCategory = null;
        $selectedEmployee = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($categoryId) {
            $selectedCategory = \Modules\Accounting\Models\ExpenseCategory::where('id', $categoryId)
                ->where('company_id', $companyId)
                ->select('id', 'name')
                ->first();
        }
        
        if ($employeeId) {
            $selectedEmployee = User::where('id', $employeeId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'email')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Format data for DataTable
            $formattedExpenses = [];
            $index = 1;
            $totalAmount = 0;
            
            foreach ($expenses as $expense) {
                $amount = floatval($expense->amount ?? 0);
                $totalAmount += $amount;
                
                // Format date time
                $dateTime = '';
                if ($expense->date) {
                    // If date contains time, use formatDateTime, otherwise formatDate
                    if (strpos($expense->date, ' ') !== false || strpos($expense->date, ':') !== false) {
                        $dateTime = formatDateTime($expense->date);
                    } else {
                        $dateTime = formatDate($expense->date);
                    }
                }
                
                // Format category
                $categoryName = '-';
                if ($expense->category) {
                    $categoryName = $expense->category->name ?? '-';
                }
                
                // Format responsible person (employee)
                $responsiblePerson = '-';
                if ($expense->employee) {
                    $responsiblePerson = $expense->employee->name ?? '-';
                    if ($expense->employee->phone) {
                        $responsiblePerson .= ' (' . $expense->employee->phone . ')';
                    }
                }
                
                $formattedExpenses[] = [
                    'sn' => $index++,
                    'reference_no' => $expense->reference_no ?? '-',
                    'date_time' => $dateTime,
                    'amount' => formatAmount($amount),
                    'category' => $categoryName,
                    'responsible_person' => $responsiblePerson,
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'expenses' => $formattedExpenses,
                    'summary' => [
                        'total_amount' => formatAmount($totalAmount),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'category' => $selectedCategory ? [
                            'name' => $selectedCategory->name,
                        ] : null,
                        'employee' => $selectedEmployee ? [
                            'name' => $selectedEmployee->name,
                            'phone' => $selectedEmployee->phone,
                            'email' => $selectedEmployee->email,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::expense-report', compact('outlets', 'categories', 'employees'));
    }

    /**
     * Income Report - Combined method for both view and API
     */
    public function incomeReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $categoryId = $request->get('category_id');
        $employeeId = $request->get('employee_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $categories = \Modules\Accounting\Models\IncomeCategory::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();
        
        $employees = User::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'email')
            ->orderBy('name', 'asc')
            ->get();
        
        // Build query
        $query = \Modules\Accounting\Models\Income::with(['category', 'employee'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);
        
        // Apply filters
        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }
        // Note: Incomes table doesn't have outlet_id field, so we skip outlet filter
        // if ($outletId) {
        //     $query->where('outlet_id', $outletId);
        // }
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }
        
        // Get all incomes
        $incomes = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        
        // Get selected outlet, category, and employee details for header
        $selectedOutlet = null;
        $selectedCategory = null;
        $selectedEmployee = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($categoryId) {
            $selectedCategory = \Modules\Accounting\Models\IncomeCategory::where('id', $categoryId)
                ->where('company_id', $companyId)
                ->select('id', 'name')
                ->first();
        }
        
        if ($employeeId) {
            $selectedEmployee = User::where('id', $employeeId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'email')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Format data for DataTable
            $formattedIncomes = [];
            $index = 1;
            $totalAmount = 0;
            
            foreach ($incomes as $income) {
                $amount = floatval($income->amount ?? 0);
                $totalAmount += $amount;
                
                // Format date time
                $dateTime = '';
                if ($income->date) {
                    // If date contains time, use formatDateTime, otherwise formatDate
                    if (strpos($income->date, ' ') !== false || strpos($income->date, ':') !== false) {
                        $dateTime = formatDateTime($income->date);
                    } else {
                        $dateTime = formatDate($income->date);
                    }
                }
                
                // Format category
                $categoryName = '-';
                if ($income->category) {
                    $categoryName = $income->category->name ?? '-';
                }
                
                // Format responsible person (employee)
                $responsiblePerson = '-';
                if ($income->employee) {
                    $responsiblePerson = $income->employee->name ?? '-';
                    if ($income->employee->phone) {
                        $responsiblePerson .= ' (' . $income->employee->phone . ')';
                    }
                }
                
                $formattedIncomes[] = [
                    'sn' => $index++,
                    'reference_no' => $income->reference_no ?? '-',
                    'date_time' => $dateTime,
                    'amount' => formatAmount($amount),
                    'category' => $categoryName,
                    'responsible_person' => $responsiblePerson,
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'incomes' => $formattedIncomes,
                    'summary' => [
                        'total_amount' => formatAmount($totalAmount),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'category' => $selectedCategory ? [
                            'name' => $selectedCategory->name,
                        ] : null,
                        'employee' => $selectedEmployee ? [
                            'name' => $selectedEmployee->name,
                            'phone' => $selectedEmployee->phone,
                            'email' => $selectedEmployee->email,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::income-report', compact('outlets', 'categories', 'employees'));
    }

    /**
     * Get Salary Report Data
     */
    /**
     * Salary Report - Combined method for both view and API
     * Shows salary records with payment information
     */
    public function salaryReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $fromMonth = $request->get('from_month');
        $toMonth = $request->get('to_month');
        $fromYear = $request->get('from_year');
        $toYear = $request->get('to_year');
        $outletId = $request->get('outlet_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        // Get selected outlet details for header
        $selectedOutlet = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Build query - get salaries with relationships
            $query = \Modules\Administrator\Models\Salary::with(['salaryPayments.paymentMethod', 'user'])
                ->where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            // Apply filters - Year range
            if ($fromYear) {
                $query->where('year', '>=', $fromYear);
            }
            if ($toYear) {
                $query->where('year', '<=', $toYear);
            }
            
            // Apply filters - Month range (within year range)
            if ($fromMonth || $toMonth) {
                $query->where(function($q) use ($fromMonth, $toMonth, $fromYear, $toYear) {
                    if ($fromYear && $toYear && $fromYear == $toYear) {
                        // Same year - filter by month range within that year
                        $q->where('year', $fromYear);
                        if ($fromMonth) {
                            $q->where('month', '>=', $fromMonth);
                        }
                        if ($toMonth) {
                            $q->where('month', '<=', $toMonth);
                        }
                    } elseif ($fromYear && $toYear && $toYear > $fromYear) {
                        // Different years - handle start year, middle years, end year
                        $q->where(function($q2) use ($fromMonth, $toMonth, $fromYear, $toYear) {
                            // Start year - from month onwards
                            $q2->where(function($q3) use ($fromMonth, $fromYear) {
                                $q3->where('year', $fromYear);
                                if ($fromMonth) {
                                    $q3->where('month', '>=', $fromMonth);
                                }
                            });
                            
                            // Middle years - all months
                            if ($toYear > $fromYear + 1) {
                                $q2->orWhereBetween('year', [$fromYear + 1, $toYear - 1]);
                            }
                            
                            // End year - up to month
                            $q2->orWhere(function($q3) use ($toMonth, $toYear) {
                                $q3->where('year', $toYear);
                                if ($toMonth) {
                                    $q3->where('month', '<=', $toMonth);
                                }
                            });
                        });
                    } else {
                        // Only one year or no year filter
                        if ($fromYear) {
                            $q->where('year', $fromYear);
                            if ($fromMonth) {
                                $q->where('month', '>=', $fromMonth);
                            }
                        }
                        if ($toYear && !$fromYear) {
                            $q->where('year', $toYear);
                            if ($toMonth) {
                                $q->where('month', '<=', $toMonth);
                            }
                        }
                        if (!$fromYear && !$toYear) {
                            // No year filter, just filter by month
                            if ($fromMonth) {
                                $q->where('month', '>=', $fromMonth);
                            }
                            if ($toMonth) {
                                $q->where('month', '<=', $toMonth);
                            }
                        }
                    }
                });
            }
            
            // Filter by outlet through salary items -> employee -> outlet_id
            if ($outletId) {
                $query->whereHas('salaryItems.employee', function($q) use ($outletId) {
                    // User outlet_id is stored as comma-separated string
                    $q->where('del_status', 'Live')
                      ->where(function($q2) use ($outletId) {
                          $q2->where('outlet_id', 'LIKE', '%' . $outletId . '%')
                             ->orWhere('outlet_id', $outletId);
                      });
                });
            }
            
            // Get all salaries
            $salaries = $query->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->orderBy('generated_date', 'desc')
                ->orderBy('id', 'desc')
                ->get();
            
            // Format data for DataTable
            $formattedSalaries = [];
            $index = 1;
            $totalAmount = 0;
            
            foreach ($salaries as $salary) {
                $amount = floatval($salary->total_amount ?? 0);
                $totalAmount += $amount;
                
                // Format date time (from generated_date)
                $dateTime = '';
                if ($salary->generated_date) {
                    $dateTime = formatDateTime($salary->generated_date . ' ' . ($salary->created_at ? $salary->created_at->format('H:i:s') : '00:00:00'));
                } elseif ($salary->created_at) {
                    $dateTime = formatDateTime($salary->created_at);
                }
                
                // Get month name
                $monthName = '';
                if ($salary->month) {
                    $monthNames = [
                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                    ];
                    $monthName = $monthNames[$salary->month] ?? $salary->month;
                }
                
                // Get payment method(s) from salary payments
                $paymentMethods = [];
                if ($salary->salaryPayments && $salary->salaryPayments->count() > 0) {
                    foreach ($salary->salaryPayments as $payment) {
                        if ($payment->paymentMethod) {
                            $paymentMethods[] = $payment->paymentMethod->name ?? '-';
                        }
                    }
                }
                $paymentMethodText = !empty($paymentMethods) ? implode(', ', array_unique($paymentMethods)) : '-';
                
                $formattedSalaries[] = [
                    'sn' => $index++,
                    'reference_no' => $salary->reference_no ?? '-',
                    'date_time' => $dateTime,
                    'year' => $salary->year ?? '-',
                    'month' => $monthName,
                    'amount' => formatAmount($amount),
                    'payment_method' => $paymentMethodText,
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'salaries' => $formattedSalaries,
                    'summary' => [
                        'total_amount' => formatAmount($totalAmount),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'from_month' => $fromMonth ? ($fromMonth < 10 ? '0' . $fromMonth : $fromMonth) : '',
                        'to_month' => $toMonth ? ($toMonth < 10 ? '0' . $toMonth : $toMonth) : '',
                        'from_year' => $fromYear ?? '',
                        'to_year' => $toYear ?? '',
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::salary-report', compact('outlets'));
    }

    /**
     * Get Purchase Return Report Data
     */
    /**
     * Purchase Return Report - Combined method for both view and API
     */
    public function purchaseReturnReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $supplierId = $request->get('supplier_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $suppliers = \Modules\Purchase\Models\Supplier::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'address')
            ->get();
        
        // Build query with relationships
        $query = \Modules\Purchase\Models\PurchaseReturn::with(['supplier', 'outlet', 'paymentMethod'])
            ->withCount(['purchaseReturnDetails as total_items' => function($q) {
                $q->where('del_status', 'Live');
            }])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);
        
        // Apply filters
        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        
        // Get all purchase returns
        $purchaseReturns = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        
        // Get selected outlet and supplier details for header
        $selectedOutlet = null;
        $selectedSupplier = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($supplierId) {
            $selectedSupplier = \Modules\Purchase\Models\Supplier::where('id', $supplierId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Format data for DataTable
            $formattedPurchaseReturns = [];
            $index = 1;
            $totalAmount = 0;
            
            foreach ($purchaseReturns as $purchaseReturn) {
                $amount = floatval($purchaseReturn->total_return_amount ?? 0);
                $totalAmount += $amount;
                
                // Format date time (created_at)
                $dateTime = '';
                if ($purchaseReturn->created_at) {
                    $dateTime = formatDateTime($purchaseReturn->created_at);
                }
                
                // Format supplier
                $supplierText = '-';
                if ($purchaseReturn->supplier) {
                    $supplierText = $purchaseReturn->supplier->name ?? '-';
                    if ($purchaseReturn->supplier->phone) {
                        $supplierText .= ' (' . $purchaseReturn->supplier->phone . ')';
                    }
                }
                
                // Format payment method
                $paymentMethodText = '-';
                if ($purchaseReturn->payment_method_id && $purchaseReturn->paymentMethod) {
                    $paymentMethodText = $purchaseReturn->paymentMethod->name ?? '-';
                } elseif ($purchaseReturn->payment_method_id) {
                    // Fallback if relationship didn't load
                    $paymentMethod = \Modules\Accounting\Models\PaymentMethod::where('id', $purchaseReturn->payment_method_id)
                        ->where('company_id', $companyId)
                        ->select('id', 'name')
                        ->first();
                    if ($paymentMethod) {
                        $paymentMethodText = $paymentMethod->name ?? '-';
                    }
                }
                
                $formattedPurchaseReturns[] = [
                    'sn' => $index++,
                    'reference_no' => $purchaseReturn->reference_no ?? '-',
                    'date_time' => $dateTime,
                    'supplier' => $supplierText,
                    'items' => $purchaseReturn->total_items ?? 0,
                    'payment_method_id' => $paymentMethodText,
                    'amount' => formatAmount($amount),
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'purchase_returns' => $formattedPurchaseReturns,
                    'summary' => [
                        'total_amount' => formatAmount($totalAmount),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'supplier' => $selectedSupplier ? [
                            'name' => $selectedSupplier->name,
                            'phone' => $selectedSupplier->phone,
                            'address' => $selectedSupplier->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::purchase-return-report', compact('outlets', 'suppliers'));
    }

    /**
     * Get Sale Return Report Data
     */
    /**
     * Sale Return Report - Combined method for both view and API
     */
    public function saleReturnReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $customerId = $request->get('customer_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'address')
            ->get();
        
        // Build query with relationships
        $query = \Modules\Sale\Models\SaleReturn::with(['customer', 'outlet', 'sale', 'paymentMethod'])
            ->withCount(['saleReturnDetails as total_items' => function($q) {
                $q->where('del_status', 'Live');
            }])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);
        
        // Apply filters
        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        
        // Get all sale returns
        $saleReturns = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        
        // Get selected outlet and customer details for header
        $selectedOutlet = null;
        $selectedCustomer = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($customerId) {
            $selectedCustomer = Customer::where('id', $customerId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Format data for DataTable
            $formattedSaleReturns = [];
            $index = 1;
            $totalAmount = 0;
            
            foreach ($saleReturns as $saleReturn) {
                $amount = floatval($saleReturn->total_return_amount ?? 0);
                $totalAmount += $amount;
                
                // Format date time (created_at)
                $dateTime = '';
                if ($saleReturn->created_at) {
                    $dateTime = formatDateTime($saleReturn->created_at);
                }
                
                // Format customer
                $customerText = '-';
                if ($saleReturn->customer) {
                    $customerText = $saleReturn->customer->name ?? '-';
                    if ($saleReturn->customer->phone) {
                        $customerText .= ' (' . $saleReturn->customer->phone . ')';
                    }
                }
                
                // Format sale invoice no
                $saleInvoiceNo = '-';
                if ($saleReturn->sale) {
                    $saleInvoiceNo = $saleReturn->sale->sale_no ?? '-';
                }
                
                // Format payment method
                $paymentMethodText = '-';
                if ($saleReturn->payment_method_id && $saleReturn->paymentMethod) {
                    $paymentMethodText = $saleReturn->paymentMethod->name ?? '-';
                } elseif ($saleReturn->payment_method_id) {
                    // Fallback if relationship didn't load
                    $paymentMethod = \Modules\Accounting\Models\PaymentMethod::where('id', $saleReturn->payment_method_id)
                        ->where('company_id', $companyId)
                        ->select('id', 'name')
                        ->first();
                    if ($paymentMethod) {
                        $paymentMethodText = $paymentMethod->name ?? '-';
                    }
                }
                
                $formattedSaleReturns[] = [
                    'sn' => $index++,
                    'reference_no' => $saleReturn->reference_no ?? '-',
                    'date_time' => $dateTime,
                    'customer' => $customerText,
                    'sale_invoice_no' => $saleInvoiceNo,
                    'items' => $saleReturn->total_items ?? 0,
                    'payment_method' => $paymentMethodText,
                    'amount' => formatAmount($amount),
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'sale_returns' => $formattedSaleReturns,
                    'summary' => [
                        'total_amount' => formatAmount($totalAmount),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'customer' => $selectedCustomer ? [
                            'name' => $selectedCustomer->name,
                            'phone' => $selectedCustomer->phone,
                            'address' => $selectedCustomer->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::sale-return-report', compact('outlets', 'customers'));
    }

    /**
     * Get Damage Report Data
     */
    /**
     * Damage Report - Combined method for both view and API
     */
    public function damageReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $employeeId = $request->get('employee_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $employees = User::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'email')
            ->orderBy('name', 'asc')
            ->get();
        
        // Build query with relationships
        $query = \Modules\Stock\Models\Damage::with(['outlet', 'employee'])
            ->withCount(['damageDetails as total_items' => function($q) {
                $q->where('del_status', 'Live');
            }])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);
        
        // Apply filters
        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }
        
        // Get all damages
        $damages = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        
        // Get selected outlet and employee details for header
        $selectedOutlet = null;
        $selectedEmployee = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($employeeId) {
            $selectedEmployee = User::where('id', $employeeId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'email')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            // Format data for DataTable
            $formattedDamages = [];
            $index = 1;
            $totalLoss = 0;
            
            foreach ($damages as $damage) {
                $loss = floatval($damage->total_loss ?? 0);
                $totalLoss += $loss;
                
                // Format date time (created_at)
                $dateTime = '';
                if ($damage->created_at) {
                    $dateTime = formatDateTime($damage->created_at);
                }
                
                // Format responsible person (employee) - name with phone number
                $responsiblePerson = '-';
                if ($damage->employee_id && $damage->employee) {
                    $responsiblePerson = $damage->employee->name ?? '-';
                    if ($damage->employee->phone) {
                        $responsiblePerson .= ' (' . $damage->employee->phone . ')';
                    }
                } elseif ($damage->employee_id) {
                    // Fallback if relationship didn't load
                    $employee = User::where('id', $damage->employee_id)
                        ->where('company_id', $companyId)
                        ->select('id', 'name', 'phone')
                        ->first();
                    if ($employee) {
                        $responsiblePerson = $employee->name ?? '-';
                        if ($employee->phone) {
                            $responsiblePerson .= ' (' . $employee->phone . ')';
                        }
                    }
                }
                
                $formattedDamages[] = [
                    'sn' => $index++,
                    'reference_no' => $damage->reference_no ?? '-',
                    'date_time' => $dateTime,
                    'items' => $damage->total_items ?? 0,
                    'total_loss' => formatAmount($loss),
                    'responsible_person' => $responsiblePerson,
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'damages' => $formattedDamages,
                    'summary' => [
                        'total_loss' => formatAmount($totalLoss),
                    ],
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'employee' => $selectedEmployee ? [
                            'name' => $selectedEmployee->name,
                            'phone' => $selectedEmployee->phone,
                            'email' => $selectedEmployee->email,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::damage-report', compact('outlets', 'employees'));
    }

    /**
     * Get Installment Report Data
     */
    public function installmentReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $customerId = $request->get('customer_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'address')
            ->orderBy('name', 'asc')
            ->get();
        
        // Get selected outlet and customer details for header
        $selectedOutlet = null;
        $selectedCustomer = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($customerId) {
            $selectedCustomer = Customer::where('id', $customerId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            $installmentData = [];
            $index = 1;
            
            // Query InstallmentSaleDetail with relationships
            $query = InstallmentSaleDetail::with([
                'installmentSale.customer',
                'installmentSale.item'
            ])
            ->where('del_status', 'Live')
            ->whereHas('installmentSale', function ($q) use ($companyId, $outletId) {
                $q->where('company_id', $companyId);
                if ($outletId) {
                    $q->where('outlet_id', $outletId);
                }
            });
            
            // Filter by customer (through installmentSale relationship)
            if ($customerId) {
                $query->whereHas('installmentSale', function($q) use ($customerId) {
                    $q->where('customer_id', $customerId);
                });
            }
            
            // Filter by date range (on installment payment date)
            if ($dateFrom) {
                $query->whereDate('payment_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('payment_date', '<=', $dateTo);
            }
            
            $installmentDetails = $query->orderBy('payment_date', 'asc')->get();
            
            foreach ($installmentDetails as $detail) {
                $installmentSale = $detail->installmentSale;
                
                if (!$installmentSale) {
                    continue;
                }
                
                // Get customer info
                $customer = $installmentSale->customer;
                $customerDisplay = '-';
                if ($customer) {
                    $customerPhone = $customer->phone ?? '';
                    $customerDisplay = $customerPhone 
                        ? $customer->name . ' (' . $customerPhone . ')' 
                        : $customer->name;
                }
                
                // Get product/item info
                $item = $installmentSale->item;
                $productName = $item ? ($item->name ?? '-') : '-';
                
                // Format dates
                $saleDate = $installmentSale->date ? formatDate($installmentSale->date) : '-';
                $installmentDate = $detail->payment_date ? formatDate($detail->payment_date) : '-';
                $paidDate = $detail->paid_date ? formatDate($detail->paid_date) : '-';
                
                // Format amounts
                $amountOfInstallment = formatAmount($detail->amount_of_payment ?? 0);
                $paidAmount = $detail->paid_amount > 0 ? formatAmount($detail->paid_amount) : '-';
                
                // Paid status
                $paidStatus = $detail->paid_status ?? 'Unpaid';
                
                $installmentData[] = [
                    'sn' => $index++,
                    'invoice_no' => $installmentSale->reference_no ?? '-',
                    'date' => $saleDate,
                    'customer' => $customerDisplay,
                    'product' => $productName,
                    'amount_of_installment' => $amountOfInstallment,
                    'installment_date' => $installmentDate,
                    'paid_amount' => $paidAmount,
                    'paid_date' => $paidDate,
                    'paid_status' => $paidStatus
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'installments' => $installmentData,
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'customer' => $selectedCustomer ? [
                            'name' => $selectedCustomer->name,
                            'phone' => $selectedCustomer->phone,
                            'address' => $selectedCustomer->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::installment-report', compact('outlets', 'customers'));
    }

    /**
     * Get Installment Due Report Data
     */
    public function installmentDueReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $customerId = $request->get('customer_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'address')
            ->orderBy('name', 'asc')
            ->get();
        
        // Get selected outlet and customer details for header
        $selectedOutlet = null;
        $selectedCustomer = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($customerId) {
            $selectedCustomer = Customer::where('id', $customerId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            $installmentData = [];
            $index = 1;
            
            // Query InstallmentSale with relationships - only those with due installments
            $query = InstallmentSale::with([
                'customer',
                'item',
                'installmentDetails' => function($q) {
                    $q->where('del_status', 'Live');
                }
            ])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->whereHas('installmentDetails', function($q) {
                $q->where('del_status', 'Live')
                  ->whereIn('paid_status', ['Unpaid', 'Partial']); // Only show sales with due installments
            });
            
            // Filter by outlet
            if ($outletId) {
                $query->where('outlet_id', $outletId);
            }
            
            // Filter by customer
            if ($customerId) {
                $query->where('customer_id', $customerId);
            }
            
            // Filter by date range (on sale date)
            if ($dateFrom) {
                $query->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('date', '<=', $dateTo);
            }
            
            $installmentSales = $query->orderBy('date', 'desc')->get();
            
            foreach ($installmentSales as $installmentSale) {
                // Get customer info
                $customer = $installmentSale->customer;
                $customerDisplay = '-';
                if ($customer) {
                    $customerPhone = $customer->phone ?? '';
                    $customerDisplay = $customerPhone 
                        ? $customer->name . ' (' . $customerPhone . ')' 
                        : $customer->name;
                }
                
                // Get product/item info
                $item = $installmentSale->item;
                $productName = $item ? ($item->name ?? '-') : '-';
                
                // Format sale date & time
                $saleDate = $installmentSale->date ? formatDate($installmentSale->date) : '-';
                $saleTime = $installmentSale->created_at ? $installmentSale->created_at->format('H:i:s') : '-';
                $saleDateAndTime = $saleDate . ' ' . $saleTime;
                
                // Get installment details for calculations
                $installmentDetails = $installmentSale->installmentDetails->where('del_status', 'Live');
                
                // Calculate Total Installment (sum of amount_of_payment)
                $totalInstallment = $installmentDetails->sum('amount_of_payment');
                
                // Calculate Total Paid (sum of paid_amount)
                $totalPaid = $installmentDetails->sum('paid_amount');
                
                // Get Last Payment Date (max paid_date where paid_amount > 0)
                $lastPaymentDate = $installmentDetails
                    ->where('paid_amount', '>', 0)
                    ->max('paid_date');
                $lastPaymentDateFormatted = $lastPaymentDate ? formatDate($lastPaymentDate) : '-';
                
                // Calculate Current Due
                $currentDue = floatval($totalInstallment) - floatval($totalPaid);
                
                $installmentData[] = [
                    'sn' => $index++,
                    'invoice_no' => $installmentSale->reference_no ?? '-',
                    'sale_date_time' => $saleDateAndTime,
                    'customer' => $customerDisplay,
                    'product' => $productName,
                    'price' => formatAmount($installmentSale->price ?? 0),
                    'percentage_of_interest' => formatAmount($installmentSale->percentage_of_interest ?? 0) . '%',
                    'amount_of_interest' => formatAmount($installmentSale->interest_amount ?? 0),
                    'total' => formatAmount($installmentSale->total ?? 0),
                    'down_payment' => formatAmount($installmentSale->down_payment ?? 0),
                    'total_installment' => formatAmount($totalInstallment),
                    'total_paid' => formatAmount($totalPaid),
                    'last_payment_date' => $lastPaymentDateFormatted,
                    'current_due' => formatAmount($currentDue)
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'installments' => $installmentData,
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'customer' => $selectedCustomer ? [
                            'name' => $selectedCustomer->name,
                            'phone' => $selectedCustomer->phone,
                            'address' => $selectedCustomer->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::installment-due-report', compact('outlets', 'customers'));
    }

    /**
     * Get Item Tracking Report Data
     */
    public function itemTrackingReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $itemId = $request->get('item_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        
        
        // Get filter options (for view)
        $items = \Modules\Stock\Models\Item::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->select('id', 'name', 'code', 'parent_id')
            ->orderBy('name', 'asc')
            ->get();
        
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        // Get selected item and outlet details for header
        $selectedItem = \Modules\Stock\Models\Item::where('id', $itemId)
            ->where('company_id', $companyId)
            ->select('id', 'name', 'code')
            ->first();
        
        $selectedOutlet = null;
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            $trackingData = [];
            $index = 1;
            $runningBalance = 0;
            
            // Helper function to format date for sorting
            $formatDateForSort = function($date) {
                return $date ? date('Y-m-d', strtotime($date)) : '';
            };
            
            // Calculate opening balance (before date_from if provided)
            $openingBalance = 0;
            if ($dateFrom) {
                // Opening Stock
                $openingStockQuery = \Modules\Stock\Models\SetOpeningStock::where('item_id', $itemId)
                    ->where('company_id', $companyId)
                    ->whereDate('created_at', '<', $dateFrom);
                
                if ($outletId) {
                    $openingStockQuery->where('outlet_id', $outletId);
                }
                
                $openingStocks = $openingStockQuery->get();
                foreach ($openingStocks as $opening) {
                    $openingBalance += floatval($opening->stock_quantity ?? 0);
                }
                
                // Purchases before date_from
                $purchaseBeforeQuery = \Modules\Purchase\Models\PurchaseDetail::where('item_id', $itemId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->whereHas('purchase', function($q) use ($dateFrom) {
                        $q->whereDate('date', '<', $dateFrom);
                    });
                
                if ($outletId) {
                    $purchaseBeforeQuery->where('outlet_id', $outletId);
                }
                
                $purchasesBefore = $purchaseBeforeQuery->get();
                foreach ($purchasesBefore as $purchase) {
                    $item = \Modules\Stock\Models\Item::find($itemId);
                    $conversionRate = $item ? floatval($item->conversion_rate ?? 1) : 1;
                    $openingBalance += floatval($purchase->quantity_amount ?? 0) * $conversionRate;
                }
                
                // Purchase Returns before date_from (reduce stock)
                $purchaseReturnBeforeQuery = \Modules\Purchase\Models\PurchaseReturnDetail::where('item_id', $itemId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->whereHas('purchaseReturn', function($q) use ($dateFrom) {
                        $q->whereDate('date', '<', $dateFrom);
                    });
                
                if ($outletId) {
                    $purchaseReturnBeforeQuery->where('outlet_id', $outletId);
                }
                
                $purchaseReturnsBefore = $purchaseReturnBeforeQuery->get();
                foreach ($purchaseReturnsBefore as $return) {
                    $openingBalance -= floatval($return->return_quantity_amount ?? 0);
                }
                
                // Sales before date_from (reduce stock)
                $saleBeforeQuery = \Modules\Sale\Models\SaleDetail::where('item_id', $itemId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->whereHas('sale', function($q) use ($dateFrom) {
                        $q->whereDate('sale_date', '<', $dateFrom);
                    });
                
                if ($outletId) {
                    $saleBeforeQuery->where('outlet_id', $outletId);
                }
                
                $salesBefore = $saleBeforeQuery->get();
                foreach ($salesBefore as $sale) {
                    $openingBalance -= floatval($sale->qty ?? 0);
                }
                
                // Sale Returns before date_from (increase stock)
                $saleReturnBeforeQuery = \Modules\Sale\Models\SaleReturnDetail::where('item_id', $itemId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->whereHas('saleReturn', function($q) use ($dateFrom) {
                        $q->whereDate('date', '<', $dateFrom);
                    });
                
                if ($outletId) {
                    $saleReturnBeforeQuery->whereHas('saleReturn.sale', function($q) use ($outletId) {
                        $q->where('outlet_id', $outletId);
                    });
                }
                
                $saleReturnsBefore = $saleReturnBeforeQuery->get();
                foreach ($saleReturnsBefore as $return) {
                    $openingBalance += floatval($return->return_quantity_amount ?? 0);
                }
                
                // Damages before date_from (reduce stock)
                $damageBeforeQuery = \Modules\Stock\Models\DamageDetail::where('item_id', $itemId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->whereDate('date', '<', $dateFrom);
                
                if ($outletId) {
                    $damageBeforeQuery->where('outlet_id', $outletId);
                }
                
                $damagesBefore = $damageBeforeQuery->get();
                foreach ($damagesBefore as $damage) {
                    $openingBalance -= floatval($damage->damage_quantity ?? 0);
                }
                
                // Transfers Out before date_from (reduce stock)
                $transferOutBeforeQuery = \Modules\Stock\Models\TransferDetail::where('item_id', $itemId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->whereHas('transfer', function($q) use ($dateFrom) {
                        $q->whereDate('date', '<', $dateFrom);
                    });
                
                if ($outletId) {
                    $transferOutBeforeQuery->whereHas('transfer', function ($q) use ($outletId) {
                        $q->where('from_outlet_id', $outletId);
                    });
                }
                
                $transfersOutBefore = $transferOutBeforeQuery->get();
                foreach ($transfersOutBefore as $transfer) {
                    $openingBalance -= floatval($transfer->quantity_amount ?? 0);
                }
                
                // Transfers In before date_from (increase stock)
                $transferInBeforeQuery = \Modules\Stock\Models\TransferDetail::where('item_id', $itemId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->whereHas('transfer', function($q) use ($dateFrom) {
                        $q->whereDate('date', '<', $dateFrom);
                    });
                
                if ($outletId) {
                    $transferInBeforeQuery->whereHas('transfer', function ($q) use ($outletId) {
                        $q->where('to_outlet_id', $outletId);
                    });
                }
                
                $transfersInBefore = $transferInBeforeQuery->get();
                foreach ($transfersInBefore as $transfer) {
                    $openingBalance += floatval($transfer->quantity_amount ?? 0);
                }
                
                // Add opening balance as first entry
                $runningBalance = $openingBalance;
                if ($openingBalance != 0) {
                    $trackingData[] = [
                        'sn' => $index++,
                        'date' => formatDate($dateFrom) ?? $formatDateForSort($dateFrom),
                        'transaction_type' => 'Opening Balance',
                        'reference_no' => '-',
                        'details' => 'Opening Balance',
                        'quantity_in' => '0.00',
                        'quantity_out' => '0.00',
                        'balance' => $runningBalance,
                        'sort_date' => strtotime($dateFrom) - 1 // Show before date_from
                    ];
                }
            } else {
                // If no date_from, show opening stock entries
                $openingStockQuery = \Modules\Stock\Models\SetOpeningStock::where('item_id', $itemId)
                    ->where('company_id', $companyId);
                
                if ($outletId) {
                    $openingStockQuery->where('outlet_id', $outletId);
                }
                
                $openingStocks = $openingStockQuery->get();
                foreach ($openingStocks as $opening) {
                    $qty = floatval($opening->stock_quantity ?? 0);
                    $runningBalance += $qty;
                    $trackingData[] = [
                        'sn' => $index++,
                        'date' => formatDate($opening->created_at) ?? $formatDateForSort($opening->created_at),
                        'transaction_type' => 'Opening Stock',
                        'reference_no' => '-',
                        'details' => 'Opening Stock',
                        'quantity_in' => $qty,
                        'quantity_out' => '0.00',
                        'balance' => $runningBalance,
                        'sort_date' => $opening->created_at ? strtotime($opening->created_at) : 0
                    ];
                }
            }
            
            // 2. Purchases
            $purchaseQuery = \Modules\Purchase\Models\PurchaseDetail::with(['purchase.supplier', 'purchase.outlet'])
                ->where('item_id', $itemId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $purchaseQuery->whereHas('purchase', function($q) use ($dateFrom) {
                    $q->whereDate('date', '>=', $dateFrom);
                });
            }
            if ($dateTo) {
                $purchaseQuery->whereHas('purchase', function($q) use ($dateTo) {
                    $q->whereDate('date', '<=', $dateTo);
                });
            }
            if ($outletId) {
                $purchaseQuery->where('outlet_id', $outletId);
            }
            
            $purchases = $purchaseQuery->get();
            foreach ($purchases as $purchase) {
                $item = \Modules\Stock\Models\Item::find($itemId);
                $conversionRate = $item ? floatval($item->conversion_rate ?? 1) : 1;
                $qty = floatval($purchase->quantity_amount ?? 0) * $conversionRate;
                $runningBalance += $qty;
                $supplierName = $purchase->purchase && $purchase->purchase->supplier 
                    ? $purchase->purchase->supplier->name 
                    : '-';
                $trackingData[] = [
                    'sn' => $index++,
                    'date' => formatDate($purchase->purchase ? $purchase->purchase->date : null) ?? $formatDateForSort($purchase->purchase ? $purchase->purchase->date : null),
                    'transaction_type' => 'Purchase',
                    'reference_no' => $purchase->purchase ? ($purchase->purchase->reference_no ?? '-') : '-',
                    'details' => 'Purchase from ' . $supplierName,
                    'quantity_in' => $qty,
                    'quantity_out' => '0.00',
                    'balance' => $runningBalance,
                    'sort_date' => $purchase->purchase && $purchase->purchase->date ? strtotime($purchase->purchase->date) : 0
                ];
            }
            
            // 3. Purchase Returns
            $purchaseReturnQuery = \Modules\Purchase\Models\PurchaseReturnDetail::with(['purchaseReturn.supplier', 'purchaseReturn.outlet'])
                ->where('item_id', $itemId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $purchaseReturnQuery->whereHas('purchaseReturn', function($q) use ($dateFrom) {
                    $q->whereDate('date', '>=', $dateFrom);
                });
            }
            if ($dateTo) {
                $purchaseReturnQuery->whereHas('purchaseReturn', function($q) use ($dateTo) {
                    $q->whereDate('date', '<=', $dateTo);
                });
            }
            if ($outletId) {
                $purchaseReturnQuery->where('outlet_id', $outletId);
            }
            
            $purchaseReturns = $purchaseReturnQuery->get();
            foreach ($purchaseReturns as $return) {
                $qty = floatval($return->return_quantity_amount ?? 0);
                $runningBalance -= $qty; // Purchase return reduces stock
                $supplierName = $return->purchaseReturn && $return->purchaseReturn->supplier 
                    ? $return->purchaseReturn->supplier->name 
                    : '-';
                $trackingData[] = [
                    'sn' => $index++,
                    'date' => formatDate($return->purchaseReturn ? $return->purchaseReturn->date : null) ?? $formatDateForSort($return->purchaseReturn ? $return->purchaseReturn->date : null),
                    'transaction_type' => 'Purchase Return',
                    'reference_no' => $return->purchaseReturn ? ($return->purchaseReturn->reference_no ?? '-') : '-',
                    'details' => 'Purchase Return to ' . $supplierName,
                    'quantity_in' => '0.00',
                    'quantity_out' => $qty,
                    'balance' => $runningBalance,
                    'sort_date' => $return->purchaseReturn && $return->purchaseReturn->date ? strtotime($return->purchaseReturn->date) : 0
                ];
            }
            
            // 4. Sales
            $saleQuery = \Modules\Sale\Models\SaleDetail::with(['sale.customer', 'sale.outlet'])
                ->where('item_id', $itemId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $saleQuery->whereHas('sale', function($q) use ($dateFrom) {
                    $q->whereDate('sale_date', '>=', $dateFrom);
                });
            }
            if ($dateTo) {
                $saleQuery->whereHas('sale', function($q) use ($dateTo) {
                    $q->whereDate('sale_date', '<=', $dateTo);
                });
            }
            if ($outletId) {
                $saleQuery->where('outlet_id', $outletId);
            }
            
            $sales = $saleQuery->get();
            foreach ($sales as $sale) {
                $qty = floatval($sale->qty ?? 0);
                $runningBalance -= $qty; // Sale reduces stock
                $customerName = $sale->sale && $sale->sale->customer 
                    ? $sale->sale->customer->name 
                    : '-';
                $trackingData[] = [
                    'sn' => $index++,
                    'date' => formatDate($sale->sale ? $sale->sale->sale_date : null) ?? $formatDateForSort($sale->sale ? $sale->sale->sale_date : null),
                    'transaction_type' => 'Sale',
                    'reference_no' => $sale->sale ? ($sale->sale->sale_no ?? '-') : '-',
                    'details' => 'Sale to ' . $customerName,
                    'quantity_in' => '0.00',
                    'quantity_out' => $qty,
                    'balance' => $runningBalance,
                    'sort_date' => $sale->sale && $sale->sale->sale_date ? strtotime($sale->sale->sale_date) : 0
                ];
            }
            
            // 5. Sale Returns
            $saleReturnQuery = \Modules\Sale\Models\SaleReturnDetail::with(['saleReturn.customer', 'saleReturn.sale.outlet'])
                ->where('item_id', $itemId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $saleReturnQuery->whereHas('saleReturn', function($q) use ($dateFrom) {
                    $q->whereDate('date', '>=', $dateFrom);
                });
            }
            if ($dateTo) {
                $saleReturnQuery->whereHas('saleReturn', function($q) use ($dateTo) {
                    $q->whereDate('date', '<=', $dateTo);
                });
            }
            if ($outletId) {
                $saleReturnQuery->whereHas('saleReturn.sale', function($q) use ($outletId) {
                    $q->where('outlet_id', $outletId);
                });
            }
            
            $saleReturns = $saleReturnQuery->get();
            foreach ($saleReturns as $return) {
                $qty = floatval($return->return_quantity_amount ?? 0);
                $runningBalance += $qty; // Sale return increases stock
                $customerName = $return->saleReturn && $return->saleReturn->customer 
                    ? $return->saleReturn->customer->name 
                    : '-';
                $trackingData[] = [
                    'sn' => $index++,
                    'date' => formatDate($return->saleReturn ? $return->saleReturn->date : null) ?? $formatDateForSort($return->saleReturn ? $return->saleReturn->date : null),
                    'transaction_type' => 'Sale Return',
                    'reference_no' => $return->saleReturn ? ($return->saleReturn->reference_no ?? '-') : '-',
                    'details' => 'Sale Return from ' . $customerName,
                    'quantity_in' => $qty,
                    'quantity_out' => '0.00',
                    'balance' => $runningBalance,
                    'sort_date' => $return->saleReturn && $return->saleReturn->date ? strtotime($return->saleReturn->date) : 0
                ];
            }
            
            // 6. Damages
            $damageQuery = \Modules\Stock\Models\DamageDetail::with(['damage.outlet'])
                ->where('item_id', $itemId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $damageQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $damageQuery->whereDate('date', '<=', $dateTo);
            }
            if ($outletId) {
                $damageQuery->where('outlet_id', $outletId);
            }
            
            $damages = $damageQuery->get();
            foreach ($damages as $damage) {
                $qty = floatval($damage->damage_quantity ?? 0);
                $runningBalance -= $qty; // Damage reduces stock
                $trackingData[] = [
                    'sn' => $index++,
                    'date' => formatDate($damage->date) ?? $formatDateForSort($damage->date),
                    'transaction_type' => 'Damage',
                    'reference_no' => $damage->damage ? ($damage->damage->reference_no ?? '-') : '-',
                    'details' => 'Damage',
                    'quantity_in' => '0.00',
                    'quantity_out' => $qty,
                    'balance' => $runningBalance,
                    'sort_date' => $damage->date ? strtotime($damage->date) : 0
                ];
            }
            
            // 7. Transfers (Out - from outlet)
            $transferOutQuery = \Modules\Stock\Models\TransferDetail::with(['transfer.fromOutlet', 'transfer.toOutlet'])
                ->where('item_id', $itemId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $transferOutQuery->whereHas('transfer', function($q) use ($dateFrom) {
                    $q->whereDate('date', '>=', $dateFrom);
                });
            }
            if ($dateTo) {
                $transferOutQuery->whereHas('transfer', function($q) use ($dateTo) {
                    $q->whereDate('date', '<=', $dateTo);
                });
            }
            if ($outletId) {
                $transferOutQuery->whereHas('transfer', function ($q) use ($outletId) {
                $q->where('from_outlet_id', $outletId);
            });
            }
            
            $transfersOut = $transferOutQuery->get();
            foreach ($transfersOut as $transfer) {
                $qty = floatval($transfer->quantity_amount ?? 0);
                $runningBalance -= $qty; // Transfer out reduces stock
                $toOutletName = $transfer->transfer && $transfer->transfer->toOutlet 
                    ? $transfer->transfer->toOutlet->outlet_name 
                    : '-';
                $trackingData[] = [
                    'sn' => $index++,
                    'date' => formatDate($transfer->transfer ? $transfer->transfer->date : null) ?? $formatDateForSort($transfer->transfer ? $transfer->transfer->date : null),
                    'transaction_type' => 'Transfer Out',
                    'reference_no' => $transfer->transfer ? ($transfer->transfer->reference_no ?? '-') : '-',
                    'details' => 'Transfer to ' . $toOutletName,
                    'quantity_in' => '0.00',
                    'quantity_out' => $qty,
                    'balance' => $runningBalance,
                    'sort_date' => $transfer->transfer && $transfer->transfer->date ? strtotime($transfer->transfer->date) : 0
                ];
            }
            
            // 8. Transfers (In - to outlet)
            $transferInQuery = \Modules\Stock\Models\TransferDetail::with(['transfer.fromOutlet', 'transfer.toOutlet'])
                ->where('item_id', $itemId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $transferInQuery->whereHas('transfer', function($q) use ($dateFrom) {
                    $q->whereDate('date', '>=', $dateFrom);
                });
            }
            if ($dateTo) {
                $transferInQuery->whereHas('transfer', function($q) use ($dateTo) {
                    $q->whereDate('date', '<=', $dateTo);
                });
            }
            if ($outletId) {
                $transferInQuery->whereHas('transfer', function ($q) use ($outletId) {
                $q->where('to_outlet_id', $outletId);
            });
            }
            
            $transfersIn = $transferInQuery->get();
            foreach ($transfersIn as $transfer) {
                $qty = floatval($transfer->quantity_amount ?? 0);
                $runningBalance += $qty; // Transfer in increases stock
                $fromOutletName = $transfer->transfer && $transfer->transfer->fromOutlet 
                    ? $transfer->transfer->fromOutlet->outlet_name 
                    : '-';
                $trackingData[] = [
                    'sn' => $index++,
                    'date' => formatDate($transfer->transfer ? $transfer->transfer->date : null) ?? $formatDateForSort($transfer->transfer ? $transfer->transfer->date : null),
                    'transaction_type' => 'Transfer In',
                    'reference_no' => $transfer->transfer ? ($transfer->transfer->reference_no ?? '-') : '-',
                    'details' => 'Transfer from ' . $fromOutletName,
                    'quantity_in' => $qty,
                    'quantity_out' => '0.00',
                    'balance' => $runningBalance,
                    'sort_date' => $transfer->transfer && $transfer->transfer->date ? strtotime($transfer->transfer->date) : 0
                ];
            }
            
            // Sort by date
            usort($trackingData, function($a, $b) {
                if ($a['sort_date'] == $b['sort_date']) {
                    return 0;
                }
                return ($a['sort_date'] < $b['sort_date']) ? -1 : 1;
            });
            
            // Re-number SN after sorting
            foreach ($trackingData as $key => $data) {
                $trackingData[$key]['sn'] = $key + 1;
                unset($trackingData[$key]['sort_date']); // Remove sort_date from final output
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'tracking' => $trackingData,
                    'filter_info' => [
                        'item' => $selectedItem ? [
                            'name' => $selectedItem->name,
                            'code' => $selectedItem->code,
                        ] : null,
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::item-tracking-report', compact('items', 'outlets'));
    }

    /**
     * Get Price History Report Data
     */
    public function priceHistoryReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $itemId = $request->get('item_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        
        // Get filter options (for view)
        $items = \Modules\Stock\Models\Item::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->select('id', 'name', 'code', 'parent_id')
            ->orderBy('name', 'asc')
            ->get();
        
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        // Get selected item and outlet details for header
        $selectedItem = \Modules\Stock\Models\Item::where('id', $itemId)
            ->where('company_id', $companyId)
            ->select('id', 'name', 'code')
            ->first();
        
        $selectedOutlet = null;
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            $priceData = [];
            $index = 1;
            
            // Helper function to format date for sorting
            $formatDateForSort = function($date) {
                return $date ? date('Y-m-d', strtotime($date)) : '';
            };
            
            // 1. Purchases
            $purchaseQuery = \Modules\Purchase\Models\PurchaseDetail::with(['purchase.supplier', 'purchase.outlet'])
                ->where('item_id', $itemId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $purchaseQuery->whereHas('purchase', function($q) use ($dateFrom) {
                    $q->whereDate('date', '>=', $dateFrom);
                });
            }
            if ($dateTo) {
                $purchaseQuery->whereHas('purchase', function($q) use ($dateTo) {
                    $q->whereDate('date', '<=', $dateTo);
                });
            }
            if ($outletId) {
                $purchaseQuery->where('outlet_id', $outletId);
            }
            
            $purchases = $purchaseQuery->get();
            foreach ($purchases as $purchase) {
                $qty = floatval($purchase->quantity_amount ?? 0);
                $unitPrice = floatval($purchase->unit_price ?? 0);
                $totalAmount = floatval($purchase->total ?? 0);
                // If total is not set, calculate it
                if ($totalAmount == 0) {
                    $totalAmount = $qty * $unitPrice;
                }
                $supplierName = $purchase->purchase && $purchase->purchase->supplier 
                    ? $purchase->purchase->supplier->name 
                    : '-';
                $priceData[] = [
                    'sn' => $index++,
                    'date' => formatDate($purchase->purchase ? $purchase->purchase->date : null) ?? $formatDateForSort($purchase->purchase ? $purchase->purchase->date : null),
                    'transaction_type' => 'Purchase',
                    'reference_no' => $purchase->purchase ? ($purchase->purchase->reference_no ?? '-') : '-',
                    'details' => 'Purchase from ' . $supplierName,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_amount' => $totalAmount,
                    'sort_date' => $purchase->purchase && $purchase->purchase->date ? strtotime($purchase->purchase->date) : 0
                ];
            }
            
            // 2. Purchase Returns
            $purchaseReturnQuery = \Modules\Purchase\Models\PurchaseReturnDetail::with(['purchaseReturn.supplier', 'purchaseReturn.outlet'])
                ->where('item_id', $itemId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $purchaseReturnQuery->whereHas('purchaseReturn', function($q) use ($dateFrom) {
                    $q->whereDate('date', '>=', $dateFrom);
                });
            }
            if ($dateTo) {
                $purchaseReturnQuery->whereHas('purchaseReturn', function($q) use ($dateTo) {
                    $q->whereDate('date', '<=', $dateTo);
                });
            }
            if ($outletId) {
                $purchaseReturnQuery->where('outlet_id', $outletId);
            }
            
            $purchaseReturns = $purchaseReturnQuery->get();
            foreach ($purchaseReturns as $return) {
                $qty = floatval($return->return_quantity_amount ?? 0);
                $unitPrice = floatval($return->unit_price ?? 0);
                $totalAmount = floatval($return->total ?? 0);
                // If total is not set, calculate it
                if ($totalAmount == 0) {
                    $totalAmount = $qty * $unitPrice;
                }
                $supplierName = $return->purchaseReturn && $return->purchaseReturn->supplier 
                    ? $return->purchaseReturn->supplier->name 
                    : '-';
                $priceData[] = [
                    'sn' => $index++,
                    'date' => formatDate($return->purchaseReturn ? $return->purchaseReturn->date : null) ?? $formatDateForSort($return->purchaseReturn ? $return->purchaseReturn->date : null),
                    'transaction_type' => 'Purchase Return',
                    'reference_no' => $return->purchaseReturn ? ($return->purchaseReturn->reference_no ?? '-') : '-',
                    'details' => 'Purchase Return to ' . $supplierName,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_amount' => $totalAmount,
                    'sort_date' => $return->purchaseReturn && $return->purchaseReturn->date ? strtotime($return->purchaseReturn->date) : 0
                ];
            }
            
            // 3. Sales
            $saleQuery = \Modules\Sale\Models\SaleDetail::with(['sale.customer', 'sale.outlet'])
                ->where('item_id', $itemId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $saleQuery->whereHas('sale', function($q) use ($dateFrom) {
                    $q->whereDate('sale_date', '>=', $dateFrom);
                });
            }
            if ($dateTo) {
                $saleQuery->whereHas('sale', function($q) use ($dateTo) {
                    $q->whereDate('sale_date', '<=', $dateTo);
                });
            }
            if ($outletId) {
                $saleQuery->where('outlet_id', $outletId);
            }
            
            $sales = $saleQuery->get();
            foreach ($sales as $sale) {
                $qty = floatval($sale->qty ?? 0);
                $unitPrice = floatval($sale->menu_price_with_discount ?? $sale->menu_unit_price ?? 0);
                // Total amount = quantity * unit price (with discount)
                $totalAmount = $qty * $unitPrice;
                $customerName = $sale->sale && $sale->sale->customer 
                    ? $sale->sale->customer->name 
                    : '-';
                $priceData[] = [
                    'sn' => $index++,
                    'date' => formatDate($sale->sale ? $sale->sale->sale_date : null) ?? $formatDateForSort($sale->sale ? $sale->sale->sale_date : null),
                    'transaction_type' => 'Sale',
                    'reference_no' => $sale->sale ? ($sale->sale->sale_no ?? '-') : '-',
                    'details' => 'Sale to ' . $customerName,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_amount' => $totalAmount,
                    'sort_date' => $sale->sale && $sale->sale->sale_date ? strtotime($sale->sale->sale_date) : 0
                ];
            }
            
            // 4. Sale Returns
            $saleReturnQuery = \Modules\Sale\Models\SaleReturnDetail::with(['saleReturn.customer', 'saleReturn.sale.outlet'])
                ->where('item_id', $itemId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $saleReturnQuery->whereHas('saleReturn', function($q) use ($dateFrom) {
                    $q->whereDate('date', '>=', $dateFrom);
                });
            }
            if ($dateTo) {
                $saleReturnQuery->whereHas('saleReturn', function($q) use ($dateTo) {
                    $q->whereDate('date', '<=', $dateTo);
                });
            }
            if ($outletId) {
                $saleReturnQuery->whereHas('saleReturn.sale', function($q) use ($outletId) {
                    $q->where('outlet_id', $outletId);
                });
            }
            
            $saleReturns = $saleReturnQuery->get();
            foreach ($saleReturns as $return) {
                $qty = floatval($return->return_quantity_amount ?? 0);
                $unitPrice = floatval($return->unit_price_in_return ?? 0);
                // Total amount = quantity * unit price
                $totalAmount = $qty * $unitPrice;
                $customerName = $return->saleReturn && $return->saleReturn->customer 
                    ? $return->saleReturn->customer->name 
                    : '-';
                $priceData[] = [
                    'sn' => $index++,
                    'date' => formatDate($return->saleReturn ? $return->saleReturn->date : null) ?? $formatDateForSort($return->saleReturn ? $return->saleReturn->date : null),
                    'transaction_type' => 'Sale Return',
                    'reference_no' => $return->saleReturn ? ($return->saleReturn->reference_no ?? '-') : '-',
                    'details' => 'Sale Return from ' . $customerName,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_amount' => $totalAmount,
                    'sort_date' => $return->saleReturn && $return->saleReturn->date ? strtotime($return->saleReturn->date) : 0
                ];
            }
            
            // Sort by date
            usort($priceData, function($a, $b) {
                if ($a['sort_date'] == $b['sort_date']) {
                    return 0;
                }
                return ($a['sort_date'] < $b['sort_date']) ? -1 : 1;
            });
            
            // Re-number SN after sorting
            foreach ($priceData as $key => $data) {
                $priceData[$key]['sn'] = $key + 1;
                unset($priceData[$key]['sort_date']); // Remove sort_date from final output
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'prices' => $priceData,
                    'filter_info' => [
                        'item' => $selectedItem ? [
                            'name' => $selectedItem->name,
                            'code' => $selectedItem->code,
                        ] : null,
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::price-history-report', compact('items', 'outlets'));
    }

    /**
     * Get Cash Flow Report Data
     */
    public function cashFlowReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        // Get selected outlet details for header
        $selectedOutlet = null;
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            $cashFlowData = [];
            $index = 1;
            
            // Helper function to format date for sorting
            $formatDateForSort = function($date) {
                return $date ? date('Y-m-d', strtotime($date)) : '';
            };
            
            // 1. Sales (Credit)
            $saleQuery = Sale::with(['customer', 'outlet'])
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $saleQuery->whereDate('sale_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $saleQuery->whereDate('sale_date', '<=', $dateTo);
            }
            if ($outletId) {
                $saleQuery->where('outlet_id', $outletId);
            }
            
            $sales = $saleQuery->get();
            foreach ($sales as $sale) {
                $amount = floatval($sale->total_payable);
                $customerName = $sale->customer ? $sale->customer->name : 'Walk-in Customer';
                $outletName = $sale->outlet ? $sale->outlet->outlet_name : '-';
                $cashFlowData[] = [
                    'sn' => $index++,
                    'date' => formatDate($sale->sale_date) ?? $formatDateForSort($sale->sale_date),
                    'transaction_type' => 'Sale',
                    'reference_no' => $sale->sale_no ?? '-',
                    'details' => 'Sale to ' . $customerName,
                    'outlet' => $outletName,
                    'credit' => formatAmount($amount),
                    'debit' => '-',
                    'sort_date' => $sale->sale_date ? strtotime($sale->sale_date) : 0
                ];
            }
            
            // 2. Purchases (Debit)
            $purchaseQuery = Purchase::with(['supplier', 'outlet'])
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $purchaseQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $purchaseQuery->whereDate('date', '<=', $dateTo);
            }
            if ($outletId) {
                $purchaseQuery->where('outlet_id', $outletId);
            }
            
            $purchases = $purchaseQuery->get();
            foreach ($purchases as $purchase) {
                $amount = floatval($purchase->grand_total ?? $purchase->total_payable ?? 0);
                $supplierName = $purchase->supplier ? $purchase->supplier->name : '-';
                $outletName = $purchase->outlet ? $purchase->outlet->outlet_name : '-';
                $cashFlowData[] = [
                    'sn' => $index++,
                    'date' => formatDate($purchase->date) ?? $formatDateForSort($purchase->date),
                    'transaction_type' => 'Purchase',
                    'reference_no' => $purchase->reference_no ?? '-',
                    'details' => 'Purchase from ' . $supplierName,
                    'outlet' => $outletName,
                    'credit' => '-',
                    'debit' => formatAmount($amount),
                    'sort_date' => $purchase->date ? strtotime($purchase->date) : 0
                ];
            }
            
            // 3. Purchase Returns (Credit)
            $purchaseReturnQuery = PurchaseReturn::with(['supplier', 'outlet'])
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $purchaseReturnQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $purchaseReturnQuery->whereDate('date', '<=', $dateTo);
            }
            if ($outletId) {
                $purchaseReturnQuery->where('outlet_id', $outletId);
            }
            
            $purchaseReturns = $purchaseReturnQuery->get();
            foreach ($purchaseReturns as $return) {
                $amount = floatval($return->total_return_amount ?? 0);
                $supplierName = $return->supplier ? $return->supplier->name : '-';
                $outletName = $return->outlet ? $return->outlet->outlet_name : '-';
                $cashFlowData[] = [
                    'sn' => $index++,
                    'date' => formatDate($return->date) ?? $formatDateForSort($return->date),
                    'transaction_type' => 'Purchase Return',
                    'reference_no' => $return->reference_no ?? '-',
                    'details' => 'Purchase Return from ' . $supplierName,
                    'outlet' => $outletName,
                    'credit' => formatAmount($amount),
                    'debit' => '-',
                    'sort_date' => $return->date ? strtotime($return->date) : 0
                ];
            }
            
            // 4. Sale Returns (Debit)
            $saleReturnQuery = SaleReturn::with(['customer', 'sale.outlet'])
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $saleReturnQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $saleReturnQuery->whereDate('date', '<=', $dateTo);
            }
            if ($outletId) {
                $saleReturnQuery->whereHas('sale', function($q) use ($outletId) {
                    $q->where('outlet_id', $outletId);
                });
            }
            
            $saleReturns = $saleReturnQuery->get();
            foreach ($saleReturns as $return) {
                $amount = floatval($return->total_return_amount ?? 0);
                $customerName = $return->customer ? $return->customer->name : '-';
                $outletName = $return->sale && $return->sale->outlet 
                    ? $return->sale->outlet->outlet_name 
                    : '-';
                $cashFlowData[] = [
                    'sn' => $index++,
                    'date' => formatDate($return->date) ?? $formatDateForSort($return->date),
                    'transaction_type' => 'Sale Return',
                    'reference_no' => $return->reference_no ?? '-',
                    'details' => 'Sale Return to ' . $customerName,
                    'outlet' => $outletName,
                    'credit' => '-',
                    'debit' => formatAmount($amount),
                    'sort_date' => $return->date ? strtotime($return->date) : 0
                ];
            }
            
            // 5. Income (Credit)
            $incomeQuery = \Modules\Accounting\Models\Income::with(['category'])
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $incomeQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $incomeQuery->whereDate('date', '<=', $dateTo);
            }
            
            $incomes = $incomeQuery->get();
            foreach ($incomes as $income) {
                $amount = floatval($income->amount ?? 0);
                $categoryName = $income->category ? $income->category->name : '-';
                $cashFlowData[] = [
                    'sn' => $index++,
                    'date' => formatDate($income->date) ?? $formatDateForSort($income->date),
                    'transaction_type' => 'Income',
                    'reference_no' => $income->reference_no ?? '-',
                    'details' => 'Income - ' . $categoryName,
                    'outlet' => '-', // Income doesn't have outlet
                    'credit' => formatAmount($amount),
                    'debit' => '-',
                    'sort_date' => $income->date ? strtotime($income->date) : 0
                ];
            }
            
            // 6. Expense (Debit)
            $expenseQuery = \Modules\Accounting\Models\Expense::with(['category'])
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $expenseQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $expenseQuery->whereDate('date', '<=', $dateTo);
            }
            
            $expenses = $expenseQuery->get();
            foreach ($expenses as $expense) {
                $amount = floatval($expense->amount ?? 0);
                $categoryName = $expense->category ? $expense->category->name : '-';
                $cashFlowData[] = [
                    'sn' => $index++,
                    'date' => formatDate($expense->date) ?? $formatDateForSort($expense->date),
                    'transaction_type' => 'Expense',
                    'reference_no' => $expense->reference_no ?? '-',
                    'details' => 'Expense - ' . $categoryName,
                    'outlet' => '-', // Expense doesn't have outlet
                    'credit' => '-',
                    'debit' => formatAmount($amount),
                    'sort_date' => $expense->date ? strtotime($expense->date) : 0
                ];
            }
            
            // 7. Servicing (Credit)
            $servicingQuery = \Modules\Sale\Models\Servicing::with(['customer', 'outlet'])
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $servicingQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $servicingQuery->whereDate('date', '<=', $dateTo);
            }
            if ($outletId) {
                $servicingQuery->where('outlet_id', $outletId);
            }
            
            $servicings = $servicingQuery->get();
            foreach ($servicings as $servicing) {
                $amount = floatval($servicing->servicing_charge ?? $servicing->paid_amount ?? 0);
                $customerName = $servicing->customer ? $servicing->customer->name : '-';
                $outletName = $servicing->outlet ? $servicing->outlet->outlet_name : '-';
                $cashFlowData[] = [
                    'sn' => $index++,
                    'date' => formatDate($servicing->date) ?? $formatDateForSort($servicing->date),
                    'transaction_type' => 'Servicing',
                    'reference_no' => 'SRV-' . $servicing->id ?? '-',
                    'details' => 'Servicing - ' . $customerName,
                    'outlet' => $outletName,
                    'credit' => formatAmount($amount),
                    'debit' => '-',
                    'sort_date' => $servicing->date ? strtotime($servicing->date) : 0
                ];
            }
            
            // 8. Salary (Debit)
            $salaryQuery = \Modules\Administrator\Models\Salary::with(['user'])
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($dateFrom) {
                $salaryQuery->whereDate('generated_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $salaryQuery->whereDate('generated_date', '<=', $dateTo);
            }
            // Note: Salary doesn't have outlet_id, so we show all
            
            $salaries = $salaryQuery->get();
            foreach ($salaries as $salary) {
                $amount = floatval($salary->total_amount ?? 0);
                $cashFlowData[] = [
                    'sn' => $index++,
                    'date' => formatDate($salary->generated_date) ?? $formatDateForSort($salary->generated_date),
                    'transaction_type' => 'Salary',
                    'reference_no' => $salary->reference_no ?? '-',
                    'details' => 'Salary - ' . ($salary->year ?? '') . '/' . ($salary->month ?? ''),
                    'outlet' => '-', // Salary doesn't have outlet
                    'credit' => '-',
                    'debit' => formatAmount($amount),
                    'sort_date' => $salary->generated_date ? strtotime($salary->generated_date) : 0
                ];
            }
            
            // Sort by date
            usort($cashFlowData, function($a, $b) {
                if ($a['sort_date'] == $b['sort_date']) {
                    return 0;
                }
                return ($a['sort_date'] < $b['sort_date']) ? -1 : 1;
            });
            
            // Re-number SN after sorting
            foreach ($cashFlowData as $key => $data) {
                $cashFlowData[$key]['sn'] = $key + 1;
                unset($cashFlowData[$key]['sort_date']); // Remove sort_date from final output
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'cash_flow' => $cashFlowData,
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::cash-flow-report', compact('outlets'));
    }

    /**
     * Get Available Loyalty Point Report Data
     */
    public function availableLoyaltyPointReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $customerId = $request->get('customer_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'address')
            ->orderBy('name', 'asc')
            ->get();
        
        // Get selected outlet and customer details for header
        $selectedOutlet = null;
        $selectedCustomer = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($customerId) {
            $selectedCustomer = Customer::where('id', $customerId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            $availableData = [];
            $index = 1;
            
            // Get customers to calculate points for
            $customerQuery = Customer::where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            if ($customerId) {
                $customerQuery->where('id', $customerId);
            }
            
            $customersList = $customerQuery->get();
            
            foreach ($customersList as $customer) {
                // Calculate total points earned from sale_details
                // If date range provided, calculate within range; otherwise calculate all time
                $pointsEarnedQuery = \Modules\Sale\Models\SaleDetail::whereHas('sale', function($q) use ($customer, $dateFrom, $dateTo, $outletId, $companyId) {
                    $q->where('customer_id', $customer->id)
                      ->where('del_status', 'Live')
                      ->where('company_id', $companyId);
                    
                    // For points earned, filter by sale date
                    if ($dateFrom) {
                        $q->whereDate('sale_date', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $q->whereDate('sale_date', '<=', $dateTo);
                    }
                    if ($outletId) {
                        $q->where('outlet_id', $outletId);
                    }
                })
                ->where('del_status', 'Live')
                ->where('company_id', $companyId);
                
                if ($outletId) {
                    $pointsEarnedQuery->where('outlet_id', $outletId);
                }
                
                $totalPointsEarned = $pointsEarnedQuery->sum('loyalty_point_earn');
                
                // Calculate total points used from sale_payments
                // If date range provided, calculate within range; otherwise calculate all time
                $pointsUsedQuery = \Modules\Sale\Models\SalePayment::whereHas('sale', function($q) use ($customer, $outletId, $companyId) {
                    $q->where('customer_id', $customer->id)
                      ->where('del_status', 'Live')
                      ->where('company_id', $companyId);
                    
                    if ($outletId) {
                        $q->where('outlet_id', $outletId);
                    }
                })
                ->where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('usage_point', '>', 0);
                
                if ($outletId) {
                    $pointsUsedQuery->where('outlet_id', $outletId);
                }
                
                // For points used, filter by payment date
                if ($dateFrom) {
                    $pointsUsedQuery->whereDate('date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $pointsUsedQuery->whereDate('date', '<=', $dateTo);
                }
                
                $totalPointsUsed = $pointsUsedQuery->sum('usage_point');
                
                // Calculate available points
                $availablePoints = floatval($totalPointsEarned) - floatval($totalPointsUsed);
                
                // Only show customers with available points > 0, or if filtering by customer
                if ($availablePoints > 0 || $customerId) {
                    $customerPhone = $customer->phone ?? '';
                    $customerDisplay = $customerPhone 
                        ? $customer->name . ' (' . $customerPhone . ')' 
                        : $customer->name;
                    
                    $availableData[] = [
                        'sn' => $index++,
                        'customer' => $customerDisplay,
                        'available_points' => $availablePoints
                    ];
                }
            }
            
            // Sort by customer name
            usort($availableData, function($a, $b) {
                return strcmp($a['customer'], $b['customer']);
            });
            
            // Re-number SN after sorting
            foreach ($availableData as $key => $data) {
                $availableData[$key]['sn'] = $key + 1;
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'available' => $availableData,
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'customer' => $selectedCustomer ? [
                            'name' => $selectedCustomer->name,
                            'phone' => $selectedCustomer->phone,
                            'address' => $selectedCustomer->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::available-loyalty-point-report', compact('outlets', 'customers'));
    }

    /**
     * Get Usage Loyalty Point Report Data
     */
    public function usageLoyaltyPointReport(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $customerId = $request->get('customer_id');
        
        // Get filter options (for view)
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name as name', 'phone', 'address')
            ->get();
        
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'address')
            ->orderBy('name', 'asc')
            ->get();
        
        // Get selected outlet and customer details for header
        $selectedOutlet = null;
        $selectedCustomer = null;
        
        if ($outletId) {
            $selectedOutlet = Outlet::where('id', $outletId)
                ->where('company_id', $companyId)
                ->select('id', 'outlet_name', 'phone', 'address')
                ->first();
        }
        
        if ($customerId) {
            $selectedCustomer = Customer::where('id', $customerId)
                ->where('company_id', $companyId)
                ->select('id', 'name', 'phone', 'address')
                ->first();
        }
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            $usageData = [];
            $index = 1;
            
            // Helper function to format date for sorting
            $formatDateForSort = function($date) {
                return $date ? date('Y-m-d', strtotime($date)) : '';
            };
            
            // Get Sale Payments where usage_point > 0 (loyalty points redeemed)
            $salePaymentQuery = \Modules\Sale\Models\SalePayment::with(['sale.customer', 'sale.outlet'])
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where('usage_point', '>', 0);
            
            if ($dateFrom) {
                $salePaymentQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $salePaymentQuery->whereDate('date', '<=', $dateTo);
            }
            if ($outletId) {
                $salePaymentQuery->where('outlet_id', $outletId);
            }
            if ($customerId) {
                $salePaymentQuery->whereHas('sale', function($q) use ($customerId) {
                    $q->where('customer_id', $customerId);
                });
            }
            
            $salePayments = $salePaymentQuery->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->get();
            
            foreach ($salePayments as $payment) {
                $redeemedAmount = floatval($payment->usage_point ?? 0);
                $saleNo = $payment->sale ? ($payment->sale->sale_no ?? '-') : '-';
                $customerName = $payment->sale && $payment->sale->customer 
                    ? $payment->sale->customer->name 
                    : 'Walk-in Customer';
                $customerPhone = $payment->sale && $payment->sale->customer 
                    ? ($payment->sale->customer->phone ?? '') 
                    : '';
                $customerDisplay = $customerPhone 
                    ? $customerName . ' (' . $customerPhone . ')' 
                    : $customerName;
                
                $usageData[] = [
                    'sn' => $index++,
                    'date' => formatDate($payment->date) ?? $formatDateForSort($payment->date),
                    'sale_no' => $saleNo,
                    'customer' => $customerDisplay,
                    'redeemed_amount' => formatAmount($redeemedAmount),
                    'sort_date' => $payment->date ? strtotime($payment->date) : 0
                ];
            }
            
            // Sort by date
            usort($usageData, function($a, $b) {
                if ($a['sort_date'] == $b['sort_date']) {
                    return 0;
                }
                return ($a['sort_date'] < $b['sort_date']) ? -1 : 1;
            });
            
            // Re-number SN after sorting
            foreach ($usageData as $key => $data) {
                $usageData[$key]['sn'] = $key + 1;
                unset($usageData[$key]['sort_date']); // Remove sort_date from final output
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'usage' => $usageData,
                    'filter_info' => [
                        'outlet' => $selectedOutlet ? [
                            'name' => $selectedOutlet->outlet_name,
                            'phone' => $selectedOutlet->phone,
                            'address' => $selectedOutlet->address,
                        ] : null,
                        'customer' => $selectedCustomer ? [
                            'name' => $selectedCustomer->name,
                            'phone' => $selectedCustomer->phone,
                            'address' => $selectedCustomer->address,
                        ] : null,
                        'date_from' => $dateFrom ? formatDate($dateFrom) : null,
                        'date_to' => $dateTo ? formatDate($dateTo) : null,
                        'generated_at' => formatDateTime(now()),
                        'generated_by' => auth()->user() ? [
                            'name' => auth()->user()->name ?? '',
                            'phone' => auth()->user()->phone ?? '',
                        ] : null,
                    ]
                ]
            ]);
        }

        // Return view for normal requests
        return view('report::usage-loyalty-point-report', compact('outlets', 'customers'));
    }

    /**
     * Scheme/Promotion Report
     */
    public function schemeReport(Request $request)
    {
        $companyId = session('company.company_id');

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $type = $request->get('type');

        if ($request->ajax() || $request->wantsJson()) {
            $query = \Modules\Sale\Models\Promotion::with(['employee'])
                ->where('del_status', 'Live')
                ->where('company_id', $companyId);

            if ($dateFrom) $query->where('start_date', '>=', $dateFrom);
            if ($dateTo) $query->where('end_date', '<=', $dateTo);
            if ($type) $query->where('type', $type);

            $promotions = $query->orderBy('id', 'desc')->get();
            $formatted = [];
            $index = 1;
            foreach ($promotions as $p) {
                $typeLabel = $p->type == 1 ? 'Discount' : ($p->type == 2 ? 'Coupon Discount' : 'Free Item');
                $formatted[] = [
                    'id' => $index++,
                    'title' => $p->title,
                    'type' => $typeLabel,
                    'scheme_basis' => $p->scheme_basis ?? 'Item',
                    'start_date' => formatDate($p->start_date),
                    'end_date' => formatDate($p->end_date),
                    'start_time' => $p->start_time,
                    'end_time' => $p->end_time,
                    'min_purchase' => (float)($p->min_purchase_amount ?? 0),
                    'max_discount' => (float)($p->max_discount_amount ?? 0),
                    'discount' => $p->discount ?? '-',
                    'status' => $p->status == 1 ? 'Active' : 'Inactive',
                ];
            }
            return response()->json(['data' => $formatted]);
        }

        return view('report::scheme-report');
    }
}
