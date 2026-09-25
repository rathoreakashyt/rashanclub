<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DesktopController extends Controller
{
    private int $companyId;
    private int $outletId;
    private int $userId;

    public function __construct(Request $request)
    {
        $user = $request->user();
        $this->companyId = (int) ($user->company_id ?? 1);
        $this->outletId = (int) ($request->header('X-Outlet-Id') ?: 1);
        $this->userId = (int) $user->id;
    }

    // ═══════════════════════════════════════════════════════════════
    // REPORT APIs — called by desktop app for cloud-computed reports
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/desktop/company-profile
     * Company header info (name/email/phone/address/GSTIN/invoice logo) —
     * desktop invoice PDF header cloud jaisa banane ke liye.
     */
    public function companyProfile(Request $request): JsonResponse
    {
        $company = DB::table('companies')
            ->where('id', $this->companyId)
            ->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        return response()->json([
            'name' => $company->name ?? '',
            'business_name' => $company->business_name ?? '',
            'email' => $company->email ?? '',
            'phone' => $company->phone ?? '',
            'address' => $company->address ?? '',
            'tax_registration_no' => $company->tax_registration_no ?? '',
            'invoice_logo' => $company->invoice_logo ?? '',
            'inv_logo_is_show' => $company->inv_logo_is_show ?? 'Yes',
        ]);
    }

    /**
     * GET /api/desktop/sales-report
     * Params: date_from, date_to, outlet_id (optional), customer_id (optional)
     */
    public function salesReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $customerId = $request->get('customer_id');

        $query = DB::table('sales')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('sales.del_status', 'Live')
            ->where('sales.company_id', $this->companyId);

        if ($dateFrom) $query->whereDate('sales.sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sales.sale_date', '<=', $dateTo);
        if ($outletId) $query->where('sales.outlet_id', $outletId);
        if ($customerId) $query->where('sales.customer_id', $customerId);

        $sales = $query->orderBy('sales.sale_date', 'desc')
            ->orderBy('sales.id', 'desc')
            ->get([
                'sales.id', 'sales.sale_no', 'sales.sale_date', 'sales.sub_total',
                'sales.vat', 'sales.delivery_charge', 'sales.total_discount_amount',
                'sales.total_payable', 'sales.paid_amount', 'sales.due_amount',
                'sales.total_items', 'customers.name as customer_name', 'customers.phone as customer_phone',
            ]);

        $totalSubtotal = 0; $totalVat = 0; $totalCharge = 0; $totalDiscount = 0;
        $totalPayable = 0; $totalPaid = 0; $totalDue = 0;

        $formatted = $sales->map(function ($s) use (&$totalSubtotal, &$totalVat, &$totalCharge, &$totalDiscount, &$totalPayable, &$totalPaid, &$totalDue) {
            $totalSubtotal += (float) $s->sub_total;
            $totalVat += (float) $s->vat;
            $totalCharge += (float) $s->delivery_charge;
            $totalDiscount += (float) $s->total_discount_amount;
            $totalPayable += (float) $s->total_payable;
            $totalPaid += (float) $s->paid_amount;
            $totalDue += (float) $s->due_amount;
            return [
                'sale_no' => $s->sale_no ?? '-',
                'date' => $s->sale_date ? date('d M Y', strtotime($s->sale_date)) : '',
                'customer' => $s->customer_name ? ($s->customer_phone ? $s->customer_name . ' (' . $s->customer_phone . ')' : $s->customer_name) : 'Walk-in',
                'items' => (int) $s->total_items,
                'subtotal' => number_format((float) $s->sub_total, 2),
                'vat' => number_format((float) $s->vat, 2),
                'charge' => number_format((float) $s->delivery_charge, 2),
                'discount' => number_format((float) $s->total_discount_amount, 2),
                'total_payable' => number_format((float) $s->total_payable, 2),
                'paid_amount' => number_format((float) $s->paid_amount, 2),
                'due_amount' => number_format((float) $s->due_amount, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'sales' => $formatted,
                'summary' => [
                    'total_subtotal' => number_format($totalSubtotal, 2),
                    'total_vat' => number_format($totalVat, 2),
                    'total_charge' => number_format($totalCharge, 2),
                    'total_discount' => number_format($totalDiscount, 2),
                    'total_payable' => number_format($totalPayable, 2),
                    'total_paid' => number_format($totalPaid, 2),
                    'total_due' => number_format($totalDue, 2),
                ],
            ],
        ]);
    }

    /**
     * GET /api/desktop/purchase-report
     * Params: date_from, date_to, outlet_id (optional), supplier_id (optional)
     */
    public function purchaseReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $supplierId = $request->get('supplier_id');

        $query = DB::table('purchases')
            ->leftJoin('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->where('purchases.del_status', 'Live')
            ->where('purchases.company_id', $this->companyId);

        if ($dateFrom) $query->whereDate('purchases.date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('purchases.date', '<=', $dateTo);
        if ($outletId) $query->where('purchases.outlet_id', $outletId);
        if ($supplierId) $query->where('purchases.supplier_id', $supplierId);

        $purchases = $query->orderBy('purchases.date', 'desc')->get([
            'purchases.id', 'purchases.reference_no', 'purchases.date',
            'purchases.grand_total', 'purchases.paid', 'purchases.due',
            'suppliers.name as supplier_name', 'suppliers.phone as supplier_phone',
        ]);

        $totalGrand = 0; $totalPaid = 0; $totalDue = 0;
        $formatted = $purchases->map(function ($p) use (&$totalGrand, &$totalPaid, &$totalDue) {
            $totalGrand += (float) $p->grand_total;
            $totalPaid += (float) $p->paid;
            $totalDue += (float) $p->due;
            return [
                'reference_no' => $p->reference_no ?? '-',
                'date' => $p->date ? date('d M Y', strtotime($p->date)) : '',
                'supplier' => $p->supplier_name ? ($p->supplier_phone ? $p->supplier_name . ' (' . $p->supplier_phone . ')' : $p->supplier_name) : '-',
                'grand_total' => number_format((float) $p->grand_total, 2),
                'paid' => number_format((float) $p->paid, 2),
                'due' => number_format((float) $p->due, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'purchases' => $formatted,
                'summary' => [
                    'total_grand' => number_format($totalGrand, 2),
                    'total_paid' => number_format($totalPaid, 2),
                    'total_due' => number_format($totalDue, 2),
                ],
            ],
        ]);
    }

    /**
     * GET /api/desktop/stock-report
     * Params: category_id, brand_id (optional)
     */
    public function stockReport(Request $request): JsonResponse
    {
        $categoryId = $request->get('category_id');
        $brandId = $request->get('brand_id');

        $query = DB::table('items')
            ->leftJoin('item_categories', 'items.category_id', '=', 'item_categories.id')
            ->leftJoin('brands', 'items.brand_id', '=', 'brands.id')
            ->where('items.del_status', 'Live')
            ->where('items.company_id', $this->companyId)
            ->where(function ($q) {
                // Show non-variation items OR variation child items (type='0' with parent_id)
                $q->whereNull('items.parent_id')
                  ->orWhere('items.type', '0');
            })
            ->where(function ($q) {
                // Exclude empty-children (type '0' but no parent = orphaned)
                $q->whereNotNull('items.parent_id')
                  ->orWhere(function ($q2) {
                      $q2->whereNull('items.parent_id')
                         ->where('items.type', '!=', '0');
                  });
            });

        if ($categoryId) $query->where('items.category_id', $categoryId);
        if ($brandId) $query->where('items.brand_id', $brandId);

        $items = $query->orderBy('items.name')->get([
            'items.id', 'items.code', 'items.name', 'items.stock_quantity',
            'items.purchase_price', 'items.sale_price',
            'item_categories.name as category_name', 'brands.name as brand_name',
        ]);

        $totalStock = 0; $totalValue = 0;
        $formatted = $items->map(function ($item) use (&$totalStock, &$totalValue) {
            $stock = (float) $item->stock_quantity;
            $value = $stock * (float) $item->purchase_price;
            $totalStock += $stock;
            $totalValue += $value;
            return [
                'code' => $item->code ?? '-',
                'name' => $item->name ?? '-',
                'category' => $item->category_name ?? '-',
                'brand' => $item->brand_name ?? '-',
                'stock' => number_format($stock, 2),
                'purchase_price' => number_format((float) $item->purchase_price, 2),
                'sale_price' => number_format((float) $item->sale_price, 2),
                'total_value' => number_format($value, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'stocks' => $formatted,
                'summary' => [
                    'total_stock' => number_format($totalStock, 2),
                    'total_value' => number_format($totalValue, 2),
                ],
            ],
        ]);
    }

    /**
     * GET /api/desktop/expense-report
     * Params: date_from, date_to, outlet_id (optional), category_id (optional)
     */
    public function expenseReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $categoryId = $request->get('category_id');

        $query = DB::table('expenses')
            ->leftJoin('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->where('expenses.del_status', 'Live')
            ->where('expenses.company_id', $this->companyId);

        if ($dateFrom) $query->whereDate('expenses.date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('expenses.date', '<=', $dateTo);
        if ($outletId) $query->where('expenses.outlet_id', $outletId);
        if ($categoryId) $query->where('expenses.expense_category_id', $categoryId);

        $expenses = $query->orderBy('expenses.date', 'desc')->get([
            'expenses.id', 'expenses.reference_no', 'expenses.date', 'expenses.amount',
            'expenses.description', 'expense_categories.name as category_name',
        ]);

        $totalAmount = 0;
        $formatted = $expenses->map(function ($e) use (&$totalAmount) {
            $totalAmount += (float) $e->amount;
            return [
                'reference_no' => $e->reference_no ?? '-',
                'date' => $e->date ? date('d M Y', strtotime($e->date)) : '',
                'category' => $e->category_name ?? '-',
                'amount' => number_format((float) $e->amount, 2),
                'description' => $e->description ?? '-',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'expenses' => $formatted,
                'summary' => ['total_amount' => number_format($totalAmount, 2)],
            ],
        ]);
    }

    /**
     * GET /api/desktop/income-report
     */
    public function incomeReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');

        $query = DB::table('incomes')
            ->leftJoin('income_categories', 'incomes.income_category_id', '=', 'income_categories.id')
            ->where('incomes.del_status', 'Live')
            ->where('incomes.company_id', $this->companyId);

        if ($dateFrom) $query->whereDate('incomes.date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('incomes.date', '<=', $dateTo);
        if ($outletId) $query->where('incomes.outlet_id', $outletId);

        $incomes = $query->orderBy('incomes.date', 'desc')->get([
            'incomes.id', 'incomes.reference_no', 'incomes.date', 'incomes.amount',
            'incomes.description', 'income_categories.name as category_name',
        ]);

        $totalAmount = 0;
        $formatted = $incomes->map(function ($i) use (&$totalAmount) {
            $totalAmount += (float) $i->amount;
            return [
                'reference_no' => $i->reference_no ?? '-',
                'date' => $i->date ? date('d M Y', strtotime($i->date)) : '',
                'category' => $i->category_name ?? '-',
                'amount' => number_format((float) $i->amount, 2),
                'description' => $i->description ?? '-',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'incomes' => $formatted,
                'summary' => ['total_amount' => number_format($totalAmount, 2)],
            ],
        ]);
    }

    /**
     * GET /api/desktop/due-report
     * Customer and supplier due amounts
     */
    public function dueReport(Request $request): JsonResponse
    {
        $type = $request->get('type', 'customer'); // customer | supplier

        if ($type === 'supplier') {
            $suppliers = DB::table('suppliers')
                ->where('del_status', 'Live')
                ->where('company_id', $this->companyId)
                ->where('due_amount', '>', 0)
                ->orderBy('due_amount', 'desc')
                ->get(['id', 'name', 'phone', 'due_amount']);

            $totalDue = 0;
            $formatted = $suppliers->map(function ($s) use (&$totalDue) {
                $totalDue += (float) $s->due_amount;
                return [
                    'name' => $s->name ?? '-',
                    'phone' => $s->phone ?? '-',
                    'due_amount' => number_format((float) $s->due_amount, 2),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => ['suppliers' => $formatted, 'summary' => ['total_due' => number_format($totalDue, 2)]],
            ]);
        }

        $customers = DB::table('customers')
            ->where('del_status', 'Live')
            ->where('company_id', $this->companyId)
            ->where('due_amount', '>', 0)
            ->orderBy('due_amount', 'desc')
            ->get(['id', 'name', 'phone', 'due_amount']);

        $totalDue = 0;
        $formatted = $customers->map(function ($c) use (&$totalDue) {
            $totalDue += (float) $c->due_amount;
            return [
                'name' => $c->name ?? '-',
                'phone' => $c->phone ?? '-',
                'due_amount' => number_format((float) $c->due_amount, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => ['customers' => $formatted, 'summary' => ['total_due' => number_format($totalDue, 2)]],
        ]);
    }

    /**
     * GET /api/desktop/employee-sale-report
     * Params: date_from, date_to, employee_id (optional)
     */
    public function employeeSaleReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $employeeId = $request->get('employee_id');

        $query = DB::table('sales')
            ->leftJoin('users', 'sales.user_id', '=', 'users.id')
            ->where('sales.del_status', 'Live')
            ->where('sales.company_id', $this->companyId);

        if ($dateFrom) $query->whereDate('sales.sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sales.sale_date', '<=', $dateTo);
        if ($employeeId) $query->where('sales.user_id', $employeeId);

        $sales = $query->selectRaw('
                sales.user_id,
                users.name as employee_name,
                COUNT(*) as sale_count,
                COALESCE(SUM(sales.total_payable), 0) as total_payable,
                COALESCE(SUM(sales.paid_amount), 0) as total_paid,
                COALESCE(SUM(sales.due_amount), 0) as total_due
            ')
            ->groupBy('sales.user_id', 'users.name')
            ->orderBy('total_payable', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['employees' => $sales],
        ]);
    }

    /**
     * GET /api/desktop/z-report
     * Params: date (required), outlet_id (required)
     */
    public function zReport(Request $request): JsonResponse
    {
        $date = $request->get('date');
        $outletId = $request->get('outlet_id');

        if (!$date || !$outletId) {
            return response()->json(['success' => false, 'message' => 'Date and Outlet are required'], 400);
        }

        $saleQuery = DB::table('sales')
            ->where('del_status', 'Live')
            ->where('company_id', $this->companyId)
            ->where('outlet_id', $outletId)
            ->whereDate('sale_date', $date);

        $salesAgg = $saleQuery->selectRaw('
            COALESCE(SUM(sub_total), 0) as total_sub_total,
            COALESCE(SUM(total_discount_amount), 0) as total_discount,
            COALESCE(SUM(due_amount), 0) as total_due,
            COALESCE(SUM(delivery_charge), 0) as total_delivery_charge,
            COALESCE(SUM(paid_amount), 0) as total_paid,
            COALESCE(SUM(vat), 0) as total_vat,
            COALESCE(SUM(total_payable), 0) as total_payable
        ')->first();

        $saleReturnTotal = (float) DB::table('sale_returns')
            ->where('del_status', 'Live')->where('company_id', $this->companyId)
            ->where('outlet_id', $outletId)->whereDate('date', $date)
            ->sum('total_return_amount');

        $purchaseAgg = DB::table('purchases')
            ->where('del_status', 'Live')->where('company_id', $this->companyId)
            ->where('outlet_id', $outletId)->whereDate('date', $date)
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total_grand, COALESCE(SUM(paid), 0) as total_paid')
            ->first();

        $expenseTotal = (float) DB::table('expenses')
            ->where('del_status', 'Live')->where('company_id', $this->companyId)
            ->where('outlet_id', $outletId)->whereDate('date', $date)
            ->sum('amount');

        $customerReceiveTotal = (float) DB::table('customer_receives')
            ->where('del_status', 'Live')->where('company_id', $this->companyId)
            ->where('outlet_id', $outletId)->whereDate('date', $date)
            ->sum('amount');

        $supplierPaymentTotal = (float) DB::table('supplier_payments')
            ->where('del_status', 'Live')->where('company_id', $this->companyId)
            ->where('outlet_id', $outletId)->whereDate('date', $date)
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'sales_summary' => [
                    'total_sub_total' => number_format((float) $salesAgg->total_sub_total, 2),
                    'total_discount' => number_format((float) $salesAgg->total_discount, 2),
                    'total_due' => number_format((float) $salesAgg->total_due, 2),
                    'total_delivery_charge' => number_format((float) $salesAgg->total_delivery_charge, 2),
                    'total_paid' => number_format((float) $salesAgg->total_paid, 2),
                    'total_vat' => number_format((float) $salesAgg->total_vat, 2),
                    'total_payable' => number_format((float) $salesAgg->total_payable, 2),
                ],
                'sale_return' => number_format($saleReturnTotal, 2),
                'purchase' => [
                    'total' => number_format((float) $purchaseAgg->total_grand, 2),
                    'paid' => number_format((float) $purchaseAgg->total_paid, 2),
                ],
                'expense' => number_format($expenseTotal, 2),
                'customer_receive' => number_format($customerReceiveTotal, 2),
                'supplier_payment' => number_format($supplierPaymentTotal, 2),
            ],
        ]);
    }

    /**
     * GET /api/desktop/daily-summary
     * Params: date (required), outlet_id (required)
     */
    public function dailySummary(Request $request): JsonResponse
    {
        $date = $request->get('date');
        $outletId = $request->get('outlet_id');

        if (!$date || !$outletId) {
            return response()->json(['success' => false, 'message' => 'Date and Outlet are required'], 400);
        }

        $sales = DB::table('sales')->where('del_status', 'Live')
            ->where('company_id', $this->companyId)->where('outlet_id', $outletId)
            ->whereDate('sale_date', $date)->count();
        $totalSales = (float) DB::table('sales')->where('del_status', 'Live')
            ->where('company_id', $this->companyId)->where('outlet_id', $outletId)
            ->whereDate('sale_date', $date)->sum('total_payable');

        $purchases = DB::table('purchases')->where('del_status', 'Live')
            ->where('company_id', $this->companyId)->where('outlet_id', $outletId)
            ->whereDate('date', $date)->count();
        $totalPurchases = (float) DB::table('purchases')->where('del_status', 'Live')
            ->where('company_id', $this->companyId)->where('outlet_id', $outletId)
            ->whereDate('date', $date)->sum('grand_total');

        $expenses = DB::table('expenses')->where('del_status', 'Live')
            ->where('company_id', $this->companyId)->where('outlet_id', $outletId)
            ->whereDate('date', $date)->count();
        $totalExpenses = (float) DB::table('expenses')->where('del_status', 'Live')
            ->where('company_id', $this->companyId)->where('outlet_id', $outletId)
            ->whereDate('date', $date)->sum('amount');

        $totalReceived = (float) DB::table('customer_receives')->where('del_status', 'Live')
            ->where('company_id', $this->companyId)->where('outlet_id', $outletId)
            ->whereDate('date', $date)->sum('amount');

        $totalPaidToSuppliers = (float) DB::table('supplier_payments')->where('del_status', 'Live')
            ->where('company_id', $this->companyId)->where('outlet_id', $outletId)
            ->whereDate('date', $date)->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'sales' => ['count' => $sales, 'total' => number_format($totalSales, 2)],
                'purchases' => ['count' => $purchases, 'total' => number_format($totalPurchases, 2)],
                'expenses' => ['count' => $expenses, 'total' => number_format($totalExpenses, 2)],
                'customer_received' => number_format($totalReceived, 2),
                'supplier_paid' => number_format($totalPaidToSuppliers, 2),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // MARKETING APIs — SMS / WhatsApp / Email sending from desktop
    // ═══════════════════════════════════════════════════════════════

    /**
     * POST /api/desktop/marketing/sms
     * Body: { to: "+91999999999", message: "Hello..." }
     */
    public function sendSms(Request $request): JsonResponse
    {
        $request->validate([
            'to' => 'required|string',
            'message' => 'required|string|max:1600',
        ]);

        $to = $request->input('to');
        $message = $request->input('message');

        // Log the SMS attempt
        DB::table('marketing_logs')->insert([
            'company_id' => $this->companyId,
            'user_id' => $this->userId,
            'channel' => 'sms',
            'to' => $to,
            'message' => $message,
            'status' => 'pending',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        // Integrate with SMS gateway (e.g., Twilio, MSG91, TextLocal)
        // For now, return success — gateway integration is configured separately
        return response()->json([
            'success' => true,
            'message' => 'SMS queued for delivery',
            'channel' => 'sms',
            'to' => $to,
        ]);
    }

    /**
     * POST /api/desktop/marketing/whatsapp
     * Body: { to: "+91999999999", message: "Hello..." }
     */
    public function sendWhatsApp(Request $request): JsonResponse
    {
        $request->validate([
            'to' => 'required|string',
            'message' => 'required|string|max:4000',
        ]);

        $to = $request->input('to');
        $message = $request->input('message');

        DB::table('marketing_logs')->insert([
            'company_id' => $this->companyId,
            'user_id' => $this->userId,
            'channel' => 'whatsapp',
            'to' => $to,
            'message' => $message,
            'status' => 'pending',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'WhatsApp message queued',
            'channel' => 'whatsapp',
            'to' => $to,
        ]);
    }

    /**
     * POST /api/desktop/marketing/email
     * Body: { to: "user@example.com", subject: "...", message: "...", html: "..." }
     */
    public function sendEmail(Request $request): JsonResponse
    {
        $request->validate([
            'to' => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $to = $request->input('to');
        $subject = $request->input('subject');
        $message = $request->input('message');

        DB::table('marketing_logs')->insert([
            'company_id' => $this->companyId,
            'user_id' => $this->userId,
            'channel' => 'email',
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'status' => 'pending',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        try {
            Mail::raw($message, function ($mail) use ($to, $subject) {
                $mail->to($to)->subject($subject);
            });

            // Update status to sent
            DB::table('marketing_logs')
                ->where('company_id', $this->companyId)
                ->where('to', $to)
                ->where('channel', 'email')
                ->latest()
                ->update(['status' => 'sent', 'updated_at' => now()->toDateTimeString()]);

            return response()->json(['success' => true, 'message' => 'Email sent', 'channel' => 'email']);
        } catch (\Throwable $e) {
            DB::table('marketing_logs')
                ->where('company_id', $this->companyId)
                ->where('to', $to)
                ->where('channel', 'email')
                ->latest()
                ->update(['status' => 'failed', 'error' => $e->getMessage(), 'updated_at' => now()->toDateTimeString()]);

            return response()->json(['success' => false, 'message' => 'Email failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/desktop/marketing/bulk-sms
     * Body: { numbers: ["+919999", "+918888"], message: "..." }
     */
    public function bulkSms(Request $request): JsonResponse
    {
        $request->validate([
            'numbers' => 'required|array|min:1|max:500',
            'numbers.*' => 'string',
            'message' => 'required|string|max:1600',
        ]);

        $numbers = $request->input('numbers');
        $message = $request->input('message');
        $sent = 0;

        foreach ($numbers as $number) {
            DB::table('marketing_logs')->insert([
                'company_id' => $this->companyId,
                'user_id' => $this->userId,
                'channel' => 'sms',
                'to' => $number,
                'message' => $message,
                'status' => 'pending',
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);
            $sent++;
        }

        return response()->json([
            'success' => true,
            'message' => "$sent SMS messages queued",
            'sent' => $sent,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // ITEM IMPORT APIs — CSV/JSON import from desktop
    // ═══════════════════════════════════════════════════════════════

    /**
     * POST /api/desktop/items/import
     * Body: { items: [{code, name, category, brand, purchase_price, sale_price, opening_stock, ...}] }
     */
    public function importItems(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.code' => 'required|string',
            'items.*.name' => 'required|string',
        ]);

        $items = $request->input('items');
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($items as $index => $item) {
            try {
                $code = $item['code'];
                $name = $item['name'];

                // Check if item already exists
                $existing = DB::table('items')
                    ->where('code', $code)
                    ->where('company_id', $this->companyId)
                    ->where('del_status', 'Live')
                    ->first();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                // Resolve category
                $categoryId = null;
                if (!empty($item['category'])) {
                    $cat = DB::table('item_categories')
                        ->where('name', $item['category'])
                        ->where('company_id', $this->companyId)
                        ->where('del_status', 'Live')
                        ->first();
                    if ($cat) $categoryId = $cat->id;
                }

                // Resolve brand
                $brandId = null;
                if (!empty($item['brand'])) {
                    $brand = DB::table('brands')
                        ->where('name', $item['brand'])
                        ->where('company_id', $this->companyId)
                        ->where('del_status', 'Live')
                        ->first();
                    if ($brand) $brandId = $brand->id;
                }

                $now = now()->toDateTimeString();
                $itemId = DB::table('items')->insertGetId([
                    'code' => $code,
                    'name' => $name,
                    'generic_name' => $item['generic_name'] ?? null,
                    'item_category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'purchase_price' => $item['purchase_price'] ?? 0,
                    'sale_price' => $item['sale_price'] ?? 0,
                    'wholesale_price' => $item['wholesale_price'] ?? 0,
                    'stock_quantity' => $item['opening_stock'] ?? 0,
                    'type' => $item['type'] ?? 'General_Product',
                    'unit' => $item['unit'] ?? 'pcs',
                    'company_id' => $this->companyId,
                    'outlet_id' => $this->outletId,
                    'user_id' => $this->userId,
                    'del_status' => 'Live',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Row $index: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    /**
     * POST /api/desktop/items/bulk-update
     * Body: { updates: [{code, purchase_price, sale_price, opening_stock, ...}] }
     */
    public function bulkUpdateItems(Request $request): JsonResponse
    {
        $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.code' => 'required|string',
        ]);

        $updates = $request->input('updates');
        $updated = 0;
        $notFound = 0;
        $errors = [];

        foreach ($updates as $index => $update) {
            try {
                $code = $update['code'];
                $existing = DB::table('items')
                    ->where('code', $code)
                    ->where('company_id', $this->companyId)
                    ->where('del_status', 'Live')
                    ->first();

                if (!$existing) {
                    $notFound++;
                    continue;
                }

                $fields = [];
                $allowedFields = ['name', 'generic_name', 'purchase_price', 'sale_price', 'wholesale_price',
                    'stock_quantity', 'tax', 'unit', 'type', 'minimum_stock', 'description'];
                foreach ($allowedFields as $field) {
                    if (array_key_exists($field, $update)) {
                        $fields[$field] = $update[$field];
                    }
                }

                if (empty($fields)) {
                    $notFound++;
                    continue;
                }

                $fields['updated_at'] = now()->toDateTimeString();
                DB::table('items')->where('id', $existing->id)->update($fields);
                $updated++;
            } catch (\Throwable $e) {
                $errors[] = "Row $index: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'not_found' => $notFound,
            'errors' => $errors,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // DASHBOARD API — summary stats for desktop
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/desktop/dashboard
     * Returns today's summary stats
     */
    public function dashboard(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        $todaySales = (float) DB::table('sales')
            ->where('del_status', 'Live')->where('company_id', $this->companyId)
            ->whereDate('sale_date', $today)->sum('total_payable');

        $todayPurchases = (float) DB::table('purchases')
            ->where('del_status', 'Live')->where('company_id', $this->companyId)
            ->whereDate('date', $today)->sum('grand_total');

        $todayExpenses = (float) DB::table('expenses')
            ->where('del_status', 'Live')->where('company_id', $this->companyId)
            ->whereDate('date', $today)->sum('amount');

        $todayReceived = (float) DB::table('customer_receives')
            ->where('del_status', 'Live')->where('company_id', $this->companyId)
            ->whereDate('date', $today)->sum('amount');

        $totalCustomerDue = (float) DB::table('customers')
            ->where('del_status', 'Live')->where('company_id', $this->companyId)
            ->sum('due_amount');

        $totalSupplierDue = (float) DB::table('suppliers')
            ->where('del_status', 'Live')->where('company_id', $this->companyId)
            ->sum('due_amount');

        $lowStockCount = DB::table('items')
            ->where('del_status', 'Live')->where('company_id', $this->companyId)
            ->whereNull('parent_id')
            ->whereColumn('stock_quantity', '<=', 'minimum_stock')
            ->count();

        $totalItems = DB::table('items')
            ->where('del_status', 'Live')->where('company_id', $this->companyId)
            ->whereNull('parent_id')
            ->count();

        $todaySalesCount = DB::table('sales')
            ->where('del_status', 'Live')->where('company_id', $this->companyId)
            ->whereDate('sale_date', $today)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'today' => $today,
                'sales_today' => ['count' => $todaySalesCount, 'total' => number_format($todaySales, 2)],
                'purchases_today' => number_format($todayPurchases, 2),
                'expenses_today' => number_format($todayExpenses, 2),
                'received_today' => number_format($todayReceived, 2),
                'customer_due_total' => number_format($totalCustomerDue, 2),
                'supplier_due_total' => number_format($totalSupplierDue, 2),
                'low_stock_count' => $lowStockCount,
                'total_items' => $totalItems,
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // MARKETING LOGS — retrieve history
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/desktop/marketing/logs
     * Params: channel (optional), date_from, date_to
     */
    public function marketingLogs(Request $request): JsonResponse
    {
        $channel = $request->get('channel');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = DB::table('marketing_logs')
            ->where('company_id', $this->companyId);

        if ($channel) $query->where('channel', $channel);
        if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('created_at', '<=', $dateTo);

        $logs = $query->orderBy('created_at', 'desc')->limit(500)->get();

        return response()->json([
            'success' => true,
            'data' => ['logs' => $logs],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // DETAILED REPORTS — 6 enhanced report variants
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/desktop/detailed-sale-report
     * Params: date_from, date_to, outlet_id, customer_id
     */
    public function detailedSaleReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        $customerId = $request->get('customer_id');

        $query = DB::table('sales')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->leftJoin('users', 'sales.user_id', '=', 'users.id')
            ->where('sales.del_status', 'Live')
            ->where('sales.company_id', $this->companyId);

        if ($dateFrom) $query->whereDate('sales.sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sales.sale_date', '<=', $dateTo);
        if ($outletId) $query->where('sales.outlet_id', $outletId);
        if ($customerId) $query->where('sales.customer_id', $customerId);

        $sales = $query->orderBy('sales.sale_date', 'desc')->get([
            'sales.*', 'customers.name as customer_name', 'customers.phone as customer_phone',
            'users.name as employee_name',
        ]);

        $formatted = $sales->map(function ($s) {
            $saleId = $s->id;
            $items = DB::table('sale_details')
                ->leftJoin('items', 'sale_details.item_id', '=', 'items.id')
                ->where('sale_details.sales_id', $saleId)
                ->where('sale_details.del_status', 'Live')
                ->get(['items.name as item_name', 'items.code as item_code', 'sale_details.qty', 'sale_details.menu_unit_price', 'sale_details.total']);

            return [
                'sale_no' => $s->sale_no ?? '-',
                'date' => $s->sale_date ? date('d M Y', strtotime($s->sale_date)) : '',
                'customer' => $s->customer_name ?? 'Walk-in',
                'employee' => $s->employee_name ?? '-',
                'subtotal' => number_format((float) $s->sub_total, 2),
                'vat' => number_format((float) $s->vat, 2),
                'discount' => number_format((float) $s->total_discount_amount, 2),
                'total_payable' => number_format((float) $s->total_payable, 2),
                'paid' => number_format((float) $s->paid_amount, 2),
                'due' => number_format((float) $s->due_amount, 2),
                'payment_method' => $s->payment_method ?? '-',
                'items' => $items->map(fn($i) => [
                    'name' => $i->item_name ?? '-',
                    'code' => $i->item_code ?? '-',
                    'qty' => (float) $i->qty,
                    'unit_price' => number_format((float) $i->menu_unit_price, 2),
                    'total' => number_format((float) $i->total, 2),
                ])->toArray(),
            ];
        });

        return response()->json(['success' => true, 'data' => ['sales' => $formatted]]);
    }

    /**
     * GET /api/desktop/item-tracking-report
     * Params: item_id, date_from, date_to
     */
    public function itemTrackingReport(Request $request): JsonResponse
    {
        $itemId = $request->get('item_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if (!$itemId) {
            return response()->json(['success' => false, 'message' => 'item_id required'], 400);
        }

        $item = DB::table('items')->where('id', $itemId)->where('company_id', $this->companyId)->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item not found'], 404);
        }

        // Sale transactions
        $saleQuery = DB::table('sale_details')
            ->join('sales', 'sale_details.sales_id', '=', 'sales.id')
            ->where('sale_details.item_id', $itemId)
            ->where('sale_details.del_status', 'Live')
            ->where('sales.del_status', 'Live')
            ->where('sales.company_id', $this->companyId);
        if ($dateFrom) $saleQuery->whereDate('sales.sale_date', '>=', $dateFrom);
        if ($dateTo) $saleQuery->whereDate('sales.sale_date', '<=', $dateTo);
        $saleQty = (float) $saleQuery->sum('sale_details.qty');
        $saleAmount = (float) $saleQuery->sum(DB::raw('sale_details.qty * sale_details.menu_unit_price'));

        // Purchase transactions
        $purchaseQuery = DB::table('purchase_details')
            ->join('purchases', 'purchase_details.purchase_id', '=', 'purchases.id')
            ->where('purchase_details.item_id', $itemId)
            ->where('purchase_details.del_status', 'Live')
            ->where('purchases.del_status', 'Live')
            ->where('purchases.company_id', $this->companyId);
        if ($dateFrom) $purchaseQuery->whereDate('purchases.date', '>=', $dateFrom);
        if ($dateTo) $purchaseQuery->whereDate('purchases.date', '<=', $dateTo);
        $purchaseQty = (float) $purchaseQuery->sum('purchase_details.qty');
        $purchaseAmount = (float) $purchaseQuery->sum(DB::raw('purchase_details.qty * purchase_details.purchase_price'));

        // Sale Return
        $saleReturnQty = (float) DB::table('sale_return_details')
            ->join('sale_returns', 'sale_return_details.sale_return_id', '=', 'sale_returns.id')
            ->where('sale_return_details.item_id', $itemId)
            ->where('sale_return_details.del_status', 'Live')
            ->where('sale_returns.del_status', 'Live')
            ->where('sale_returns.company_id', $this->companyId)
            ->sum('sale_return_details.qty');

        // Purchase Return
        $purchaseReturnQty = (float) DB::table('purchase_return_details')
            ->join('purchase_returns', 'purchase_return_details.purchase_return_id', '=', 'purchase_returns.id')
            ->where('purchase_return_details.item_id', $itemId)
            ->where('purchase_return_details.del_status', 'Live')
            ->where('purchase_returns.del_status', 'Live')
            ->where('purchase_returns.company_id', $this->companyId)
            ->sum('purchase_return_details.qty');

        // Damage
        $damageQty = (float) DB::table('damage_details')
            ->join('damages', 'damage_details.damage_id', '=', 'damages.id')
            ->where('damage_details.item_id', $itemId)
            ->where('damage_details.del_status', 'Live')
            ->where('damages.del_status', 'Live')
            ->where('damages.company_id', $this->companyId)
            ->sum('damage_details.qty');

        return response()->json([
            'success' => true,
            'data' => [
                'item' => ['code' => $item->code, 'name' => $item->name],
                'summary' => [
                    'sale_qty' => $saleQty, 'sale_amount' => number_format($saleAmount, 2),
                    'purchase_qty' => $purchaseQty, 'purchase_amount' => number_format($purchaseAmount, 2),
                    'sale_return_qty' => $saleReturnQty,
                    'purchase_return_qty' => $purchaseReturnQty,
                    'damage_qty' => $damageQty,
                    'net_stock' => $purchaseQty + $purchaseReturnQty - $saleQty - $saleReturnQty - $damageQty,
                ],
            ],
        ]);
    }

    /**
     * GET /api/desktop/price-history-report
     * Params: item_id
     */
    public function priceHistoryReport(Request $request): JsonResponse
    {
        $itemId = $request->get('item_id');
        if (!$itemId) {
            return response()->json(['success' => false, 'message' => 'item_id required'], 400);
        }

        $purchases = DB::table('purchase_details')
            ->join('purchases', 'purchase_details.purchase_id', '=', 'purchases.id')
            ->leftJoin('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->where('purchase_details.item_id', $itemId)
            ->where('purchase_details.del_status', 'Live')
            ->where('purchases.del_status', 'Live')
            ->where('purchases.company_id', $this->companyId)
            ->orderBy('purchases.date', 'desc')
            ->limit(50)
            ->get([
                'purchases.date', 'purchases.reference_no', 'suppliers.name as supplier_name',
                'purchase_details.purchase_price', 'purchase_details.qty',
            ]);

        $formatted = $purchases->map(function ($p, $i) {
            return [
                'sn' => $i + 1,
                'date' => $p->date ? date('d M Y', strtotime($p->date)) : '',
                'reference_no' => $p->reference_no ?? '-',
                'supplier' => $p->supplier_name ?? '-',
                'purchase_price' => number_format((float) $p->purchase_price, 2),
                'qty' => (float) $p->qty,
            ];
        });

        return response()->json(['success' => true, 'data' => ['price_history' => $formatted]]);
    }

    /**
     * GET /api/desktop/detailed-cash-flow-report
     * Params: date_from, date_to, outlet_id
     */
    public function detailedCashFlowReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');

        $baseQuery = function ($table) use ($dateFrom, $dateTo, $outletId) {
            $q = DB::table($table)->where('del_status', 'Live')->where('company_id', $this->companyId);
            if ($dateFrom) $q->whereDate('date', '>=', $dateFrom);
            if ($dateTo) $q->whereDate('date', '<=', $dateTo);
            if ($outletId) $q->where('outlet_id', $outletId);
            return $q;
        };

        // Cash In (inflows)
        $saleReceived = (float) $baseQuery('sales')->sum('paid_amount');
        $installmentDownPayment = (float) $baseQuery('installment_sales')->sum('down_payment');
        $installmentCollection = (float) DB::table('installment_sale_details')
            ->join('installment_sales', 'installment_sale_details.installment_sale_id', '=', 'installment_sales.id')
            ->where('installment_sale_details.del_status', 'Live')
            ->where('installment_sales.company_id', $this->companyId)
            ->when($dateFrom, fn($q) => $q->whereDate('installment_sale_details.paid_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('installment_sale_details.paid_date', '<=', $dateTo))
            ->when($outletId, fn($q) => $q->where('installment_sales.outlet_id', $outletId))
            ->sum('installment_sale_details.paid_amount');
        $customerDueReceived = (float) $baseQuery('customer_receives')->sum('amount');
        $incomeReceived = (float) $baseQuery('incomes')->sum('amount');
        $purchaseReturnReceived = (float) $baseQuery('purchase_returns')->sum('total_return_amount');

        // Cash Out (outflows)
        $purchasePaid = (float) $baseQuery('purchases')->sum('paid');
        $supplierDuePaid = (float) $baseQuery('supplier_payments')->sum('amount');
        $expensePaid = (float) $baseQuery('expenses')->sum('amount');
        $saleReturnPaid = (float) $baseQuery('sale_returns')->sum('paid_amount');

        $totalIn = $saleReceived + $installmentDownPayment + $installmentCollection + $customerDueReceived + $incomeReceived + $purchaseReturnReceived;
        $totalOut = $purchasePaid + $supplierDuePaid + $expensePaid + $saleReturnPaid;
        $netCashFlow = $totalIn - $totalOut;

        return response()->json([
            'success' => true,
            'data' => [
                'cash_in' => [
                    'sale_received' => number_format($saleReceived, 2),
                    'installment_down_payment' => number_format($installmentDownPayment, 2),
                    'installment_collection' => number_format($installmentCollection, 2),
                    'customer_due_received' => number_format($customerDueReceived, 2),
                    'income' => number_format($incomeReceived, 2),
                    'purchase_return' => number_format($purchaseReturnReceived, 2),
                    'total' => number_format($totalIn, 2),
                ],
                'cash_out' => [
                    'purchase_paid' => number_format($purchasePaid, 2),
                    'supplier_due_paid' => number_format($supplierDuePaid, 2),
                    'expense' => number_format($expensePaid, 2),
                    'sale_return' => number_format($saleReturnPaid, 2),
                    'total' => number_format($totalOut, 2),
                ],
                'net_cash_flow' => number_format($netCashFlow, 2),
            ],
        ]);
    }

    /**
     * GET /api/desktop/loyalty-point-report
     * Params: type (available|used)
     */
    public function loyaltyPointReport(Request $request): JsonResponse
    {
        $type = $request->get('type', 'available');

        $customers = DB::table('customers')
            ->where('del_status', 'Live')
            ->where('company_id', $this->companyId);

        if ($type === 'available') {
            $customers = $customers->where('loyalty_point', '>', 0)
                ->orderBy('loyalty_point', 'desc')
                ->get(['id', 'name', 'phone', 'loyalty_point']);

            $total = 0;
            $formatted = $customers->map(function ($c) use (&$total) {
                $total += (float) $c->loyalty_point;
                return ['name' => $c->name, 'phone' => $c->phone ?? '-', 'loyalty_points' => number_format((float) $c->loyalty_point, 0)];
            });

            return response()->json([
                'success' => true,
                'data' => ['customers' => $formatted, 'summary' => ['total_points' => number_format($total, 0)]],
            ]);
        }

        // Used loyalty points - from sales where loyalty_point_used > 0
        $sales = DB::table('sales')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('sales.del_status', 'Live')
            ->where('sales.company_id', $this->companyId)
            ->where('sales.loyalty_point_used', '>', 0)
            ->orderBy('sales.sale_date', 'desc')
            ->limit(200)
            ->get([
                'sales.sale_no', 'sales.sale_date', 'sales.loyalty_point_used',
                'customers.name as customer_name',
            ]);

        $totalUsed = 0;
        $formatted = $sales->map(function ($s) use (&$totalUsed) {
            $used = (float) $s->loyalty_point_used;
            $totalUsed += $used;
            return [
                'sale_no' => $s->sale_no ?? '-',
                'date' => $s->sale_date ? date('d M Y', strtotime($s->sale_date)) : '',
                'customer' => $s->customer_name ?? '-',
                'points_used' => number_format($used, 0),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => ['sales' => $formatted, 'summary' => ['total_used' => number_format($totalUsed, 0)]],
        ]);
    }

    /**
     * GET /api/desktop/scheme-report
     */
    public function schemeReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = DB::table('promotions')
            ->where('del_status', 'Live')
            ->where('company_id', $this->companyId);
        if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('created_at', '<=', $dateTo);

        $promotions = $query->orderBy('created_at', 'desc')->get();

        $formatted = $promotions->map(function ($p) {
            return [
                'name' => $p->name ?? '-',
                'type' => $p->type ?? '-',
                'discount' => number_format((float) ($p->discount ?? 0), 2),
                'start_date' => $p->start_date ? date('d M Y', strtotime($p->start_date)) : '-',
                'end_date' => $p->end_date ? date('d M Y', strtotime($p->end_date)) : '-',
                'status' => $p->status ?? '-',
            ];
        });

        return response()->json(['success' => true, 'data' => ['promotions' => $formatted]]);
    }

    // ═══════════════════════════════════════════════════════════════
    // BARCODE PRINT API
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/desktop/barcode-data
     * Params: item_id, copies (optional, default 1)
     */
    public function barcodeData(Request $request): JsonResponse
    {
        $itemId = $request->get('item_id');
        $copies = max(1, (int) $request->get('copies', 1));

        if (!$itemId) {
            return response()->json(['success' => false, 'message' => 'item_id required'], 400);
        }

        $item = DB::table('items')
            ->where('id', $itemId)
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live')
            ->first();

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item not found'], 404);
        }

        $barcodes = [];
        for ($i = 0; $i < $copies; $i++) {
            $barcodes[] = [
                'code' => $item->code ?? '',
                'name' => $item->name ?? '',
                'price' => number_format((float) ($item->sale_price ?? 0), 2),
                'generic_name' => $item->generic_name ?? '',
            ];
        }

        return response()->json([
            'success' => true,
            'data' => ['item' => ['code' => $item->code, 'name' => $item->name, 'price' => $item->sale_price], 'barcodes' => $barcodes],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // EDIT / DELETE APIs — cloud transactions from desktop
    // ═══════════════════════════════════════════════════════════════

    /**
     * DELETE /api/desktop/sales/{id}
     */
    public function deleteSale(int $id): JsonResponse
    {
        $sale = DB::table('sales')->where('id', $id)->where('company_id', $this->companyId)->first();
        if (!$sale) return response()->json(['success' => false, 'message' => 'Sale not found'], 404);

        DB::table('sales')->where('id', $id)->update(['del_status' => 'Deleted', 'updated_at' => now()]);
        DB::table('sale_details')->where('sales_id', $id)->update(['del_status' => 'Deleted']);
        DB::table('sale_payments')->where('sale_id', $id)->update(['del_status' => 'Deleted']);

        return response()->json(['success' => true, 'message' => 'Sale deleted']);
    }

    /**
     * DELETE /api/desktop/purchases/{id}
     */
    public function deletePurchase(int $id): JsonResponse
    {
        $purchase = DB::table('purchases')->where('id', $id)->where('company_id', $this->companyId)->first();
        if (!$purchase) return response()->json(['success' => false, 'message' => 'Purchase not found'], 404);

        DB::table('purchases')->where('id', $id)->update(['del_status' => 'Deleted', 'updated_at' => now()]);
        DB::table('purchase_details')->where('purchase_id', $id)->update(['del_status' => 'Deleted']);
        DB::table('purchase_payments')->where('purchase_id', $id)->update(['del_status' => 'Deleted']);

        return response()->json(['success' => true, 'message' => 'Purchase deleted']);
    }

    /**
     * DELETE /api/desktop/sale-returns/{id}
     */
    public function deleteSaleReturn(int $id): JsonResponse
    {
        $sr = DB::table('sale_returns')->where('id', $id)->where('company_id', $this->companyId)->first();
        if (!$sr) return response()->json(['success' => false, 'message' => 'Sale Return not found'], 404);

        DB::table('sale_returns')->where('id', $id)->update(['del_status' => 'Deleted', 'updated_at' => now()]);
        DB::table('sale_return_details')->where('sale_return_id', $id)->update(['del_status' => 'Deleted']);

        return response()->json(['success' => true, 'message' => 'Sale Return deleted']);
    }

    /**
     * DELETE /api/desktop/purchase-returns/{id}
     */
    public function deletePurchaseReturn(int $id): JsonResponse
    {
        $pr = DB::table('purchase_returns')->where('id', $id)->where('company_id', $this->companyId)->first();
        if (!$pr) return response()->json(['success' => false, 'message' => 'Purchase Return not found'], 404);

        DB::table('purchase_returns')->where('id', $id)->update(['del_status' => 'Deleted', 'updated_at' => now()]);
        DB::table('purchase_return_details')->where('purchase_return_id', $id)->update(['del_status' => 'Deleted']);

        return response()->json(['success' => true, 'message' => 'Purchase Return deleted']);
    }

    /**
     * DELETE /api/desktop/expenses/{id}
     */
    public function deleteExpense(int $id): JsonResponse
    {
        $expense = DB::table('expenses')->where('id', $id)->where('company_id', $this->companyId)->first();
        if (!$expense) return response()->json(['success' => false, 'message' => 'Expense not found'], 404);
        DB::table('expenses')->where('id', $id)->update(['del_status' => 'Deleted', 'updated_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Expense deleted']);
    }

    /**
     * DELETE /api/desktop/incomes/{id}
     */
    public function deleteIncome(int $id): JsonResponse
    {
        $income = DB::table('incomes')->where('id', $id)->where('company_id', $this->companyId)->first();
        if (!$income) return response()->json(['success' => false, 'message' => 'Income not found'], 404);
        DB::table('incomes')->where('id', $id)->update(['del_status' => 'Deleted', 'updated_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Income deleted']);
    }

    // ═══════════════════════════════════════════════════════════════
    // SHOW / DETAIL APIs
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/desktop/sales/{id}
     */
    public function showSale(int $id): JsonResponse
    {
        $sale = DB::table('sales')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->leftJoin('users', 'sales.user_id', '=', 'users.id')
            ->where('sales.id', $id)
            ->where('sales.company_id', $this->companyId)
            ->where('sales.del_status', 'Live')
            ->first();

        if (!$sale) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        $items = DB::table('sale_details')
            ->leftJoin('items', 'sale_details.item_id', '=', 'items.id')
            ->where('sale_details.sales_id', $id)
            ->where('sale_details.del_status', 'Live')
            ->get(['items.name as item_name', 'items.code', 'sale_details.qty', 'sale_details.menu_unit_price', 'sale_details.total', 'sale_details.discount']);

        $payments = DB::table('sale_payments')
            ->leftJoin('payment_methods', 'sale_payments.payment_id', '=', 'payment_methods.id')
            ->where('sale_payments.sale_id', $id)
            ->where('sale_payments.del_status', 'Live')
            ->get(['payment_methods.name as method', 'sale_payments.amount']);

        return response()->json([
            'success' => true,
            'data' => [
                'sale' => [
                    'sale_no' => $sale->sale_no, 'date' => $sale->sale_date,
                    'customer' => $sale->customer_name ?? 'Walk-in',
                    'employee' => $sale->user->name ?? '-',
                    'subtotal' => $sale->sub_total, 'vat' => $sale->vat,
                    'discount' => $sale->total_discount_amount, 'total_payable' => $sale->total_payable,
                    'paid' => $sale->paid_amount, 'due' => $sale->due_amount,
                ],
                'items' => $items, 'payments' => $payments,
            ],
        ]);
    }

    /**
     * GET /api/desktop/purchases/{id}
     */
    public function showPurchase(int $id): JsonResponse
    {
        $purchase = DB::table('purchases')
            ->leftJoin('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->where('purchases.id', $id)
            ->where('purchases.company_id', $this->companyId)
            ->where('purchases.del_status', 'Live')
            ->first();

        if (!$purchase) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        $items = DB::table('purchase_details')
            ->leftJoin('items', 'purchase_details.item_id', '=', 'items.id')
            ->where('purchase_details.purchase_id', $id)
            ->where('purchase_details.del_status', 'Live')
            ->get(['items.name as item_name', 'items.code', 'purchase_details.qty', 'purchase_details.purchase_price', 'purchase_details.total']);

        return response()->json([
            'success' => true,
            'data' => [
                'purchase' => [
                    'reference_no' => $purchase->reference_no, 'date' => $purchase->date,
                    'supplier' => $purchase->supplier->name ?? '-',
                    'grand_total' => $purchase->grand_total, 'paid' => $purchase->paid, 'due' => $purchase->due,
                ],
                'items' => $items,
            ],
        ]);
    }

    /**
     * GET /api/desktop/customers/{id}
     */
    public function showCustomer(int $id): JsonResponse
    {
        $customer = DB::table('customers')
            ->where('id', $id)->where('company_id', $this->companyId)->where('del_status', 'Live')
            ->first();
        if (!$customer) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        $totalPurchases = (float) DB::table('sales')->where('customer_id', $id)->where('del_status', 'Live')->sum('total_payable');
        $totalPaid = (float) DB::table('sales')->where('customer_id', $id)->where('del_status', 'Live')->sum('paid_amount');
        $totalDue = (float) $customer->due_amount;

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => $customer,
                'stats' => ['total_purchases' => $totalPurchases, 'total_paid' => $totalPaid, 'total_due' => $totalDue],
            ],
        ]);
    }

    /**
     * GET /api/desktop/suppliers/{id}
     */
    public function showSupplier(int $id): JsonResponse
    {
        $supplier = DB::table('suppliers')
            ->where('id', $id)->where('company_id', $this->companyId)->where('del_status', 'Live')
            ->first();
        if (!$supplier) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        $totalPurchases = (float) DB::table('purchases')->where('supplier_id', $id)->where('del_status', 'Live')->sum('grand_total');
        $totalPaid = (float) DB::table('purchases')->where('supplier_id', $id)->where('del_status', 'Live')->sum('paid');
        $totalDue = (float) $supplier->due_amount;

        return response()->json([
            'success' => true,
            'data' => [
                'supplier' => $supplier,
                'stats' => ['total_purchases' => $totalPurchases, 'total_paid' => $totalPaid, 'total_due' => $totalDue],
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // STOCK SEGMENTATION API
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/desktop/stock-segmentation
     * Params: item_id, outlet_id (optional)
     */
    public function stockSegmentation(Request $request): JsonResponse
    {
        $itemId = $request->get('item_id');
        $outletId = $request->get('outlet_id');

        if (!$itemId) return response()->json(['success' => false, 'message' => 'item_id required'], 400);

        $query = DB::table('view_stock_detail2')
            ->where('item_id', $itemId);

        if ($outletId) $query->where('outlet_id', $outletId);

        $stock = $query->select('expiry_imei_serial', DB::raw('SUM(stock_quantity) as quantity'))
            ->groupBy('expiry_imei_serial')
            ->orderBy('expiry_imei_serial', 'asc')
            ->get();

        $totalQty = 0;
        $formatted = $stock->map(function ($s) use (&$totalQty) {
            $qty = (float) $s->quantity;
            $totalQty += $qty;
            return [
                'expiry_or_serial' => $s->expiry_imei_serial ?? '-',
                'quantity' => number_format($qty, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => ['segments' => $formatted, 'total_quantity' => number_format($totalQty, 2)],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // WALLET TRANSACTIONS API
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/desktop/wallet/transactions
     * Params: customer_id (optional)
     */
    public function walletTransactions(Request $request): JsonResponse
    {
        $customerId = $request->get('customer_id');

        $query = DB::table('wallet_transactions')
            ->leftJoin('customers', 'wallet_transactions.customer_id', '=', 'customers.id')
            ->where('wallet_transactions.del_status', 'Live')
            ->where('wallet_transactions.company_id', $this->companyId);

        if ($customerId) $query->where('wallet_transactions.customer_id', $customerId);

        $transactions = $query->orderBy('wallet_transactions.created_at', 'desc')->limit(200)->get([
            'wallet_transactions.*', 'customers.name as customer_name',
        ]);

        $formatted = $transactions->map(function ($t) {
            return [
                'customer' => $t->customer_name ?? '-',
                'type' => $t->type ?? '-',
                'amount' => number_format((float) $t->amount, 2),
                'balance_before' => number_format((float) $t->balance_before, 2),
                'balance_after' => number_format((float) $t->balance_after, 2),
                'description' => $t->description ?? '-',
                'date' => $t->created_at ? date('d M Y H:i', strtotime($t->created_at)) : '',
            ];
        });

        return response()->json(['success' => true, 'data' => ['transactions' => $formatted]]);
    }

    // ═══════════════════════════════════════════════════════════════
    // OVERDUE INSTALLMENT NOTIFICATIONS
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/desktop/installment/overdue
     */
    public function overdueInstallments(Request $request): JsonResponse
    {
        $overdue = DB::table('installment_sale_details')
            ->join('installment_sales', 'installment_sale_details.installment_sale_id', '=', 'installment_sales.id')
            ->leftJoin('customers', 'installment_sales.customer_id', '=', 'customers.id')
            ->where('installment_sale_details.del_status', 'Live')
            ->where('installment_sales.del_status', 'Live')
            ->where('installment_sales.company_id', $this->companyId)
            ->where('installment_sale_details.paid_status', '!=', 'Paid')
            ->whereDate('installment_sale_details.due_date', '<', now()->toDateString())
            ->orderBy('installment_sale_details.due_date', 'asc')
            ->get([
                'installment_sale_details.*',
                'installment_sales.reference_no',
                'customers.name as customer_name', 'customers.phone as customer_phone',
            ]);

        $formatted = $overdue->map(function ($o) {
            return [
                'customer' => $o->customer_name ?? '-',
                'phone' => $o->customer_phone ?? '-',
                'reference_no' => $o->reference_no ?? '-',
                'installment_no' => $o->installment_no ?? '-',
                'due_date' => $o->due_date ? date('d M Y', strtotime($o->due_date)) : '-',
                'due_amount' => number_format((float) $o->due_amount ?? ($o->installment_amount - ($o->paid_amount ?? 0)), 2),
                'status' => $o->paid_status ?? 'Unpaid',
            ];
        });

        return response()->json(['success' => true, 'data' => ['overdue' => $formatted, 'count' => count($formatted)]]);
    }

    /**
     * POST /api/desktop/installment/send-notifications
     * Body: { type: "sms"|"whatsapp"|"email" }
     */
    public function sendInstallmentNotifications(Request $request): JsonResponse
    {
        $type = $request->get('type', 'sms');

        $overdue = DB::table('installment_sale_details')
            ->join('installment_sales', 'installment_sale_details.installment_sale_id', '=', 'installment_sales.id')
            ->leftJoin('customers', 'installment_sales.customer_id', '=', 'customers.id')
            ->where('installment_sale_details.del_status', 'Live')
            ->where('installment_sales.del_status', 'Live')
            ->where('installment_sales.company_id', $this->companyId)
            ->where('installment_sale_details.paid_status', '!=', 'Paid')
            ->whereDate('installment_sale_details.due_date', '<', now()->toDateString())
            ->whereNotNull('customers.phone')
            ->select('customers.name', 'customers.phone', 'installment_sale_details.due_date', 'installment_sale_details.installment_no')
            ->get()
            ->unique('phone');

        $sent = 0;
        foreach ($overdue as $row) {
            $phone = $row->phone;
            $name = $row->name ?? 'Customer';
            $message = "Dear $name, your installment #$row->installment_no was due on " . date('d M Y', strtotime($row->due_date)) . ". Please pay at the earliest. - RashanKiDukan";

            DB::table('marketing_logs')->insert([
                'company_id' => $this->companyId,
                'user_id' => $this->userId,
                'channel' => $type,
                'to' => $phone,
                'message' => $message,
                'status' => 'pending',
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);
            $sent++;
        }

        return response()->json(['success' => true, 'message' => "$sent notifications queued", 'sent' => $sent]);
    }

    // ═══════════════════════════════════════════════════════════════
    // TIER PRICING API
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/desktop/tier-pricing
     * Params: item_id
     */
    public function tierPricing(Request $request): JsonResponse
    {
        $itemId = $request->get('item_id');
        if (!$itemId) return response()->json(['success' => false, 'message' => 'item_id required'], 400);

        $priceLists = DB::table('price_list_items')
            ->join('price_lists', 'price_list_items.price_list_id', '=', 'price_lists.id')
            ->where('price_list_items.item_id', $itemId)
            ->where('price_list_items.del_status', 'Live')
            ->where('price_lists.del_status', 'Live')
            ->where('price_lists.company_id', $this->companyId)
            ->get(['price_lists.name as list_name', 'price_list_items.price']);

        return response()->json([
            'success' => true,
            'data' => ['tiers' => $priceLists],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // SCHEME PERCENT API
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/desktop/scheme-percent
     * Params: item_id
     */
    public function schemePercent(Request $request): JsonResponse
    {
        $itemId = $request->get('item_id');
        if (!$itemId) return response()->json(['success' => false, 'message' => 'item_id required'], 400);

        $item = DB::table('items')->where('id', $itemId)->where('company_id', $this->companyId)->first();
        if (!$item) return response()->json(['success' => false, 'message' => 'Item not found'], 404);

        // Check for active promotion on this item's category or brand
        $promotion = DB::table('promotions')
            ->where('del_status', 'Live')
            ->where('company_id', $this->companyId)
            ->where('status', 'Active')
            ->where(function ($q) use ($item) {
                $q->where('applied_to', 'all')
                  ->orWhere(function ($q2) use ($item) {
                      $q2->where('applied_to', 'category')->where('applied_value', $item->item_category_id);
                  })
                  ->orWhere(function ($q3) use ($item) {
                      $q3->where('applied_to', 'brand')->where('applied_value', $item->brand_id);
                  })
                  ->orWhere(function ($q4) use ($item) {
                      $q4->where('applied_to', 'item')->where('applied_value', $item->id);
                  });
            })
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhereDate('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', now());
            })
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'item_code' => $item->code,
                'has_promotion' => $promotion ? true : false,
                'promotion' => $promotion ? [
                    'name' => $promotion->name,
                    'type' => $promotion->type,
                    'discount' => $promotion->discount,
                ] : null,
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // PAYMENT SETTLEMENT API
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/desktop/payment-settlement
     * Params: date, outlet_id
     */
    public function paymentSettlement(Request $request): JsonResponse
    {
        $date = $request->get('date', now()->toDateString());
        $outletId = $request->get('outlet_id');

        $baseQuery = function ($table) use ($date, $outletId) {
            $q = DB::table($table)->where('del_status', 'Live')->where('company_id', $this->companyId)->whereDate('date', $date);
            if ($outletId) $q->where('outlet_id', $outletId);
            return $q;
        };

        // Group by payment method
        $paymentMethods = DB::table('payment_methods')->where('company_id', $this->companyId)->where('del_status', 'Live')->get();

        $settlement = [];
        foreach ($paymentMethods as $pm) {
            $saleIn = (float) DB::table('sale_payments')
                ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
                ->where('sale_payments.payment_id', $pm->id)
                ->where('sale_payments.del_status', 'Live')
                ->where('sales.del_status', 'Live')
                ->where('sales.company_id', $this->companyId)
                ->whereDate('sales.sale_date', $date)
                ->when($outletId, fn($q) => $q->where('sales.outlet_id', $outletId))
                ->sum('sale_payments.amount');

            $purchaseOut = (float) DB::table('purchase_payments')
                ->join('purchases', 'purchase_payments.purchase_id', '=', 'purchases.id')
                ->where('purchase_payments.payment_id', $pm->id)
                ->where('purchase_payments.del_status', 'Live')
                ->where('purchases.del_status', 'Live')
                ->where('purchases.company_id', $this->companyId)
                ->whereDate('purchases.date', $date)
                ->when($outletId, fn($q) => $q->where('purchases.outlet_id', $outletId))
                ->sum('purchase_payments.amount');

            $expenseOut = (float) DB::table('expenses')
                ->where('payment_method_id', $pm->id)
                ->where('del_status', 'Live')
                ->where('company_id', $this->companyId)
                ->whereDate('date', $date)
                ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
                ->sum('amount');

            $customerReceiveIn = (float) DB::table('customer_receives')
                ->where('payment_method_id', $pm->id)
                ->where('del_status', 'Live')
                ->where('company_id', $this->companyId)
                ->whereDate('date', $date)
                ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
                ->sum('amount');

            $supplierPayOut = (float) DB::table('supplier_payments')
                ->where('payment_method_id', $pm->id)
                ->where('del_status', 'Live')
                ->where('company_id', $this->companyId)
                ->whereDate('date', $date)
                ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
                ->sum('amount');

            $net = $saleIn + $customerReceiveIn - $purchaseOut - $expenseOut - $supplierPayOut;

            if ($saleIn > 0 || $purchaseOut > 0 || $expenseOut > 0 || $customerReceiveIn > 0 || $supplierPayOut > 0) {
                $settlement[] = [
                    'payment_method' => $pm->name,
                    'sale_in' => number_format($saleIn, 2),
                    'purchase_out' => number_format($purchaseOut, 2),
                    'expense_out' => number_format($expenseOut, 2),
                    'customer_receive_in' => number_format($customerReceiveIn, 2),
                    'supplier_pay_out' => number_format($supplierPayOut, 2),
                    'net' => number_format($net, 2),
                ];
            }
        }

        return response()->json(['success' => true, 'data' => ['date' => $date, 'settlement' => $settlement]]);
    }

    // ═══════════════════════════════════════════════════════════════
    // EDIT APIs — update cloud transactions from desktop
    // ═══════════════════════════════════════════════════════════════

    /**
     * PUT /api/desktop/sales/{id}
     * Body: { paid_amount, due_amount, total_payable, ... }
     */
    public function editSale(Request $request, int $id): JsonResponse
    {
        $sale = DB::table('sales')->where('id', $id)->where('company_id', $this->companyId)->where('del_status', 'Live')->first();
        if (!$sale) return response()->json(['success' => false, 'message' => 'Sale not found'], 404);

        $fields = [];
        $allowed = ['paid_amount', 'due_amount', 'total_payable', 'total_discount_amount', 'delivery_charge', 'vat', 'sub_total', 'note'];
        foreach ($allowed as $f) {
            if ($request->has($f)) $fields[$f] = $request->input($f);
        }
        if (empty($fields)) return response()->json(['success' => false, 'message' => 'No fields to update']);

        $fields['updated_at'] = now()->toDateTimeString();
        DB::table('sales')->where('id', $id)->update($fields);

        return response()->json(['success' => true, 'message' => 'Sale updated']);
    }

    /**
     * PUT /api/desktop/purchases/{id}
     */
    public function editPurchase(Request $request, int $id): JsonResponse
    {
        $purchase = DB::table('purchases')->where('id', $id)->where('company_id', $this->companyId)->where('del_status', 'Live')->first();
        if (!$purchase) return response()->json(['success' => false, 'message' => 'Purchase not found'], 404);

        $fields = [];
        $allowed = ['grand_total', 'paid', 'due', 'note', 'reference_no', 'date'];
        foreach ($allowed as $f) {
            if ($request->has($f)) $fields[$f] = $request->input($f);
        }
        if (empty($fields)) return response()->json(['success' => false, 'message' => 'No fields to update']);

        $fields['updated_at'] = now()->toDateTimeString();
        DB::table('purchases')->where('id', $id)->update($fields);

        return response()->json(['success' => true, 'message' => 'Purchase updated']);
    }

    /**
     * PUT /api/desktop/sale-returns/{id}
     */
    public function editSaleReturn(Request $request, int $id): JsonResponse
    {
        $sr = DB::table('sale_returns')->where('id', $id)->where('company_id', $this->companyId)->where('del_status', 'Live')->first();
        if (!$sr) return response()->json(['success' => false, 'message' => 'Sale Return not found'], 404);

        $fields = [];
        $allowed = ['total_return_amount', 'paid_amount', 'due_amount', 'note'];
        foreach ($allowed as $f) {
            if ($request->has($f)) $fields[$f] = $request->input($f);
        }
        if (empty($fields)) return response()->json(['success' => false, 'message' => 'No fields to update']);

        $fields['updated_at'] = now()->toDateTimeString();
        DB::table('sale_returns')->where('id', $id)->update($fields);

        return response()->json(['success' => true, 'message' => 'Sale Return updated']);
    }

    /**
     * PUT /api/desktop/purchase-returns/{id}
     */
    public function editPurchaseReturn(Request $request, int $id): JsonResponse
    {
        $pr = DB::table('purchase_returns')->where('id', $id)->where('company_id', $this->companyId)->where('del_status', 'Live')->first();
        if (!$pr) return response()->json(['success' => false, 'message' => 'Purchase Return not found'], 404);

        $fields = [];
        $allowed = ['total_return_amount', 'note'];
        foreach ($allowed as $f) {
            if ($request->has($f)) $fields[$f] = $request->input($f);
        }
        if (empty($fields)) return response()->json(['success' => false, 'message' => 'No fields to update']);

        $fields['updated_at'] = now()->toDateTimeString();
        DB::table('purchase_returns')->where('id', $id)->update($fields);

        return response()->json(['success' => true, 'message' => 'Purchase Return updated']);
    }

    /**
     * PUT /api/desktop/expenses/{id}
     */
    public function editExpense(Request $request, int $id): JsonResponse
    {
        $expense = DB::table('expenses')->where('id', $id)->where('company_id', $this->companyId)->where('del_status', 'Live')->first();
        if (!$expense) return response()->json(['success' => false, 'message' => 'Expense not found'], 404);

        $fields = [];
        $allowed = ['amount', 'expense_category_id', 'payment_method_id', 'description', 'date', 'reference_no'];
        foreach ($allowed as $f) {
            if ($request->has($f)) $fields[$f] = $request->input($f);
        }
        if (empty($fields)) return response()->json(['success' => false, 'message' => 'No fields to update']);

        $fields['updated_at'] = now()->toDateTimeString();
        DB::table('expenses')->where('id', $id)->update($fields);

        return response()->json(['success' => true, 'message' => 'Expense updated']);
    }

    /**
     * PUT /api/desktop/incomes/{id}
     */
    public function editIncome(Request $request, int $id): JsonResponse
    {
        $income = DB::table('incomes')->where('id', $id)->where('company_id', $this->companyId)->where('del_status', 'Live')->first();
        if (!$income) return response()->json(['success' => false, 'message' => 'Income not found'], 404);

        $fields = [];
        $allowed = ['amount', 'income_category_id', 'payment_method_id', 'description', 'date', 'reference_no'];
        foreach ($allowed as $f) {
            if ($request->has($f)) $fields[$f] = $request->input($f);
        }
        if (empty($fields)) return response()->json(['success' => false, 'message' => 'No fields to update']);

        $fields['updated_at'] = now()->toDateTimeString();
        DB::table('incomes')->where('id', $id)->update($fields);

        return response()->json(['success' => true, 'message' => 'Income updated']);
    }

    /**
     * PUT /api/desktop/customers/{id}
     */
    public function editCustomer(Request $request, int $id): JsonResponse
    {
        $customer = DB::table('customers')->where('id', $id)->where('company_id', $this->companyId)->where('del_status', 'Live')->first();
        if (!$customer) return response()->json(['success' => false, 'message' => 'Customer not found'], 404);

        $fields = [];
        $allowed = ['name', 'phone', 'email', 'address', 'due_amount', 'loyalty_point', 'discount', 'date_of_birth', 'date_of_anniversary'];
        foreach ($allowed as $f) {
            if ($request->has($f)) $fields[$f] = $request->input($f);
        }
        if (empty($fields)) return response()->json(['success' => false, 'message' => 'No fields to update']);

        $fields['updated_at'] = now()->toDateTimeString();
        DB::table('customers')->where('id', $id)->update($fields);

        return response()->json(['success' => true, 'message' => 'Customer updated']);
    }

    /**
     * PUT /api/desktop/suppliers/{id}
     */
    public function editSupplier(Request $request, int $id): JsonResponse
    {
        $supplier = DB::table('suppliers')->where('id', $id)->where('company_id', $this->companyId)->where('del_status', 'Live')->first();
        if (!$supplier) return response()->json(['success' => false, 'message' => 'Supplier not found'], 404);

        $fields = [];
        $allowed = ['name', 'phone', 'email', 'address', 'due_amount', 'company_name', 'state', 'country'];
        foreach ($allowed as $f) {
            if ($request->has($f)) $fields[$f] = $request->input($f);
        }
        if (empty($fields)) return response()->json(['success' => false, 'message' => 'No fields to update']);

        $fields['updated_at'] = now()->toDateTimeString();
        DB::table('suppliers')->where('id', $id)->update($fields);

        return response()->json(['success' => true, 'message' => 'Supplier updated']);
    }

    // ═══════════════════════════════════════════════════════════════
    // CUSTOMER DISPLAY API — send sale data to customer-facing screen
    // ═══════════════════════════════════════════════════════════════

    /**
     * POST /api/desktop/customer-display
     * Body: { items: [{name, qty, price, total}], subtotal, discount, tax, total, paid, change }
     */
    public function customerDisplay(Request $request): JsonResponse
    {
        $data = $request->only(['items', 'subtotal', 'discount', 'tax', 'total', 'paid', 'change']);

        // Store in cache for customer display page to fetch
        $key = "customer_display_{$this->companyId}_{$this->outletId}";
        cache()->put($key, $data, 300); // 5 minutes

        return response()->json(['success' => true, 'message' => 'Customer display updated']);
    }

    /**
     * GET /api/desktop/customer-display
     * Returns current cart for customer-facing screen
     */
    public function getCustomerDisplay(): JsonResponse
    {
        $key = "customer_display_{$this->companyId}_{$this->outletId}";
        $data = cache()->get($key, []);

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ═══════════════════════════════════════════════════════════════
    // CALCULATOR API — simple math operations
    // ═══════════════════════════════════════════════════════════════

    /**
     * POST /api/desktop/calculator
     * Body: { expression: "123 + 456" }
     */
    public function calculator(Request $request): JsonResponse
    {
        $expression = $request->input('expression', '');

        if (empty($expression)) {
            return response()->json(['success' => false, 'message' => 'Expression required']);
        }

        $cleaned = preg_replace('/[^0-9+\-*/().%\s]/', '', $expression);

        if ($cleaned !== $expression) {
            return response()->json(['success' => false, 'message' => 'Invalid characters in expression']);
        }

        try {
            $cleaned = preg_replace('/(\d+)%/', '($1/100)', $cleaned);
            $result = $this->safeMathEval($cleaned);

            return response()->json([
                'success' => true,
                'data' => [
                    'expression' => $expression,
                    'result' => number_format($result, 2),
                    'result_raw' => $result,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Invalid expression']);
        }
    }

    private function safeMathEval(string $expr): float
    {
        $expr = preg_replace('/\s+/', '', $expr);
        $tokens = [];
        preg_match_all('/(\d+\.?\d*|\+|-|\*|\/|\(|\))/', $expr, $matches);
        $tokens = $matches[0];

        if (empty($tokens)) {
            throw new \InvalidArgumentException('No valid tokens');
        }

        $pos = 0;

        function parseExpr(array &$tokens, int &$pos) {
            $result = parseTerm($tokens, $pos);
            while ($pos < count($tokens) && ($tokens[$pos] === '+' || $tokens[$pos] === '-')) {
                $op = $tokens[$pos++];
                $right = parseTerm($tokens, $pos);
                $result = ($op === '+') ? $result + $right : $result - $right;
            }
            return $result;
        }

        function parseTerm(array &$tokens, int &$pos) {
            $result = parseFactor($tokens, $pos);
            while ($pos < count($tokens) && ($tokens[$pos] === '*' || $tokens[$pos] === '/')) {
                $op = $tokens[$pos++];
                $right = parseFactor($tokens, $pos);
                $result = ($op === '*') ? $result * $right : $result / $right;
            }
            return $result;
        }

        function parseFactor(array &$tokens, int &$pos) {
            if ($pos >= count($tokens)) {
                throw new \InvalidArgumentException('Unexpected end of expression');
            }
            if ($tokens[$pos] === '(') {
                $pos++;
                $result = parseExpr($tokens, $pos);
                if ($pos < count($tokens) && $tokens[$pos] === ')') {
                    $pos++;
                }
                return $result;
            }
            if ($tokens[$pos] === '-') {
                $pos++;
                return -parseFactor($tokens, $pos);
            }
            if ($tokens[$pos] === '+') {
                $pos++;
                return parseFactor($tokens, $pos);
            }
            return (float) $tokens[$pos++];
        }

        $result = parseExpr($tokens, $pos);
        if ($pos !== count($tokens)) {
            throw new \InvalidArgumentException('Unexpected token: ' . $tokens[$pos]);
        }
        return (float) $result;
    }

    // ═══════════════════════════════════════════════════════════════
    // BARCODE PRINT HTML — generates printable barcode page
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/desktop/barcode-print
     * Params: item_id, copies, paper_width (mm), paper_height (mm)
     * Returns HTML page ready to print
     */
    public function barcodePrint(Request $request)
    {
        $itemId = $request->get('item_id');
        $copies = max(1, (int) $request->get('copies', 1));
        $paperWidth = (int) $request->get('paper_width', 70);
        $paperHeight = (int) $request->get('paper_height', 30);

        if (!$itemId) {
            return response()->json(['success' => false, 'message' => 'item_id required'], 400);
        }

        $item = DB::table('items')
            ->where('id', $itemId)
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live')
            ->first();

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item not found'], 404);
        }

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Barcodes</title>';
        $html .= '<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>';
        $html .= '<style>';
        $html .= '@media print { body { margin: 0; } .no-print { display: none; } }';
        $html .= '.barcode-grid { display: flex; flex-wrap: wrap; gap: 5mm; padding: 5mm; }';
        $html .= '.barcode-item { width: ' . $paperWidth . 'mm; height: ' . $paperHeight . 'mm; border: 1px solid #ccc; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2mm; box-sizing: border-box; }';
        $html .= '.barcode-item svg { max-width: 100%; max-height: 60%; }';
        $html .= '.barcode-name { font-size: 8pt; text-align: center; margin-top: 1mm; font-weight: bold; }';
        $html .= '.barcode-price { font-size: 7pt; text-align: center; }';
        $html .= '.barcode-code { font-size: 6pt; text-align: center; color: #666; }';
        $html .= '</style></head><body>';
        $html .= '<div class="no-print" style="padding:10px;text-align:center;"><button onclick="window.print()">Print Barcodes</button></div>';
        $html .= '<div class="barcode-grid">';

        for ($i = 0; $i < $copies; $i++) {
            $code = $item->code ?? '00000';
            $html .= '<div class="barcode-item">';
            $html .= '<svg class="barcode-svg" data-code="' . htmlspecialchars($code) . '"></svg>';
            $html .= '<div class="barcode-name">' . htmlspecialchars($item->name ?? '') . '</div>';
            $html .= '<div class="barcode-price">₹' . number_format((float) ($item->sale_price ?? 0), 2) . '</div>';
            $html .= '<div class="barcode-code">' . htmlspecialchars($code) . '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '<script>JsBarcode(".barcode-svg", function(elem) { return elem.getAttribute("data-code"); }, { format: "CODE128", width: 1.5, height: 30, displayValue: false });</script>';
        $html .= '</body></html>';

        return response($html)->header('Content-Type', 'text/html');
    }

    // ═══════════════════════════════════════════════════════════════
    // 26 MISSING REPORT APIs — Cloud web views existed, no desktop API
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/desktop/register-report
     * Params: date, outlet_id, register_id (optional)
     */
    public function registerReport(Request $request): JsonResponse
    {
        $date = $request->get('date');
        $outletId = $request->get('outlet_id');
        $registerId = $request->get('register_id');

        if (!$date || !$outletId) {
            return response()->json(['success' => false, 'message' => 'Date and Outlet required'], 400);
        }

        $query = DB::table('registers')
            ->leftJoin('users', 'registers.user_id', '=', 'users.id')
            ->where('registers.del_status', 'Live')
            ->where('registers.company_id', $this->companyId)
            ->where('registers.outlet_id', $outletId)
            ->where('registers.register_status', 2)
            ->whereRaw('DATE(COALESCE(registers.closing_balance_date_time, registers.updated_at, registers.created_at)) = ?', [$date]);

        if ($registerId) $query->where('registers.id', $registerId);

        $registers = $query->orderBy('registers.closing_balance_date_time', 'desc')->get([
            'registers.*', 'users.name as employee_name',
        ]);

        $rows = $registers->map(function ($r) {
            return [
                'employee' => $r->employee_name ?? '-',
                'opening_balance' => number_format((float) $r->opening_balance, 2),
                'opening_date_time' => $r->opening_balance_date_time ?? '-',
                'sale_paid' => number_format((float) $r->sale_paid_amount, 2),
                'sale_return' => number_format((float) $r->refund_amount, 2),
                'customer_receive' => number_format((float) $r->customer_due_receive, 2),
                'purchase' => number_format((float) $r->total_purchase, 2),
                'purchase_return' => number_format((float) $r->total_purchase_return, 2),
                'supplier_payment' => number_format((float) $r->total_due_payment, 2),
                'expense' => number_format((float) $r->total_expense, 2),
                'closing_balance' => number_format((float) $r->closing_balance, 2),
                'closing_date_time' => $r->closing_balance_date_time ?? '-',
            ];
        });

        return response()->json(['success' => true, 'data' => ['registers' => $rows]]);
    }

    /**
     * GET /api/desktop/final-invoice-due-report
     * Params: date_from, date_to, customer_id, outlet_id
     */
    public function finalInvoiceDueReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $customerId = $request->get('customer_id');
        $outletId = $request->get('outlet_id');

        $query = DB::table('sales')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->select('sales.*', 'customers.name as customer_name', 'customers.phone as customer_phone',
                DB::raw('(SELECT COALESCE(SUM(sr.total_return_amount), 0) FROM sale_returns sr WHERE sr.sale_id = sales.id AND sr.del_status = "Live") as total_return_amount'))
            ->where('sales.del_status', 'Live')
            ->where('sales.company_id', $this->companyId);

        if ($dateFrom) $query->whereDate('sales.sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sales.sale_date', '<=', $dateTo);
        if ($customerId) $query->where('sales.customer_id', $customerId);
        if ($outletId) $query->where('sales.outlet_id', $outletId);

        $sales = $query->orderBy('sales.sale_date', 'desc')->get();

        $formatted = [];
        $totalDue = 0;
        foreach ($sales as $s) {
            $netPayable = (float) $s->total_payable - (float) $s->total_return_amount;
            $finalDue = $netPayable - (float) $s->paid_amount;
            if ($finalDue <= 0) continue;
            $totalDue += $finalDue;
            $formatted[] = [
                'invoice_no' => $s->sale_no ?? '-',
                'customer' => $s->customer_name ?? 'Walk-in',
                'due' => number_format($finalDue, 2),
            ];
        }

        return response()->json(['success' => true, 'data' => ['sales' => $formatted, 'summary' => ['total_due' => number_format($totalDue, 2)]]]);
    }

    /**
     * GET /api/desktop/service-sale-report
     * Params: date_from, date_to, outlet_id
     */
    public function serviceSaleReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');

        $query = DB::table('sale_details')
            ->join('sales', 'sale_details.sales_id', '=', 'sales.id')
            ->leftJoin('items', 'sale_details.item_id', '=', 'items.id')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('sale_details.del_status', 'Live')
            ->where('sales.del_status', 'Live')
            ->where('sales.company_id', $this->companyId)
            ->where('items.type', 'Service_Product');

        if ($dateFrom) $query->whereDate('sales.sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sales.sale_date', '<=', $dateTo);
        if ($outletId) $query->where('sale_details.outlet_id', $outletId);

        $details = $query->orderBy('sales.sale_date', 'desc')->get([
            'sales.sale_no', 'sales.sale_date', 'customers.name as customer_name',
            'items.name as item_name', 'items.code as item_code',
            'sale_details.qty', 'sale_details.menu_unit_price', 'sale_details.total',
        ]);

        $totalAmount = 0;
        $formatted = $details->map(function ($d) use (&$totalAmount) {
            $lineTotal = (float) $d->qty * (float) $d->menu_unit_price;
            $totalAmount += $lineTotal;
            return [
                'sale_no' => $d->sale_no ?? '-',
                'date' => $d->sale_date ? date('d M Y', strtotime($d->sale_date)) : '',
                'customer' => $d->customer_name ?? 'Walk-in',
                'item' => ($d->item_name ?? '-') . ' (' . ($d->item_code ?? '-') . ')',
                'qty' => (float) $d->qty,
                'unit_price' => number_format((float) $d->menu_unit_price, 2),
                'total' => number_format($lineTotal, 2),
            ];
        });

        return response()->json(['success' => true, 'data' => ['service_sales' => $formatted, 'summary' => ['total_amount' => number_format($totalAmount, 2)]]]);
    }

    /**
     * GET /api/desktop/combo-service-report
     */
    public function comboServiceReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');

        $query = DB::table('combo_sales')
            ->join('sales', 'combo_sales.sales_id', '=', 'sales.id')
            ->leftJoin('items', 'combo_sales.combo_item_id', '=', 'items.id')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('combo_sales.del_status', 'Live')
            ->where('sales.del_status', 'Live')
            ->where('sales.company_id', $this->companyId);

        if ($dateFrom) $query->whereDate('sales.sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sales.sale_date', '<=', $dateTo);
        if ($outletId) $query->where('combo_sales.outlet_id', $outletId);

        $combos = $query->orderBy('sales.sale_date', 'desc')->get([
            'sales.sale_no', 'sales.sale_date', 'customers.name as customer_name',
            'items.code as item_code', 'combo_sales.combo_item_qty', 'combo_sales.combo_item_price',
        ]);

        $formatted = $combos->map(function ($c) {
            return [
                'sale_no' => $c->sale_no ?? '-',
                'date' => $c->sale_date ? date('d M Y', strtotime($c->sale_date)) : '',
                'customer' => $c->customer_name ?? '-',
                'item_code' => $c->item_code ?? '-',
                'qty' => (float) $c->combo_item_qty,
                'unit_price' => number_format((float) $c->combo_item_price, 2),
                'total' => number_format((float) $c->combo_item_qty * (float) $c->combo_item_price, 2),
            ];
        });

        return response()->json(['success' => true, 'data' => ['combo_sales' => $formatted]]);
    }

    /**
     * GET /api/desktop/product-sale-report
     * Params: date_from, date_to, item_id
     */
    public function productSaleReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $itemId = $request->get('item_id');

        $query = DB::table('sale_details')
            ->join('sales', 'sale_details.sales_id', '=', 'sales.id')
            ->leftJoin('items', 'sale_details.item_id', '=', 'items.id')
            ->where('sale_details.del_status', 'Live')
            ->where('sales.del_status', 'Live')
            ->where('sales.company_id', $this->companyId);

        if ($dateFrom) $query->whereDate('sales.sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sales.sale_date', '<=', $dateTo);
        if ($itemId) $query->where('sale_details.item_id', $itemId);

        $sales = $query->selectRaw('sale_details.item_id, items.name as item_name, items.code as item_code,
                SUM(sale_details.qty) as total_qty, SUM(sale_details.qty * sale_details.menu_unit_price) as total_amount')
            ->groupBy('sale_details.item_id', 'items.name', 'items.code')
            ->orderBy('total_amount', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => ['products' => $sales]]);
    }

    /**
     * GET /api/desktop/product-profit-report
     * Params: date_from, date_to
     */
    public function productProfitReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = DB::table('sale_details')
            ->join('sales', 'sale_details.sales_id', '=', 'sales.id')
            ->leftJoin('items', 'sale_details.item_id', '=', 'items.id')
            ->where('sale_details.del_status', 'Live')
            ->where('sales.del_status', 'Live')
            ->where('sales.company_id', $this->companyId);

        if ($dateFrom) $query->whereDate('sales.sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sales.sale_date', '<=', $dateTo);

        $products = $query->selectRaw('sale_details.item_id, items.name as item_name, items.code as item_code,
                SUM(sale_details.qty) as total_qty,
                SUM(sale_details.qty * sale_details.menu_unit_price) as sale_amount,
                SUM(sale_details.qty * items.purchase_price) as cost_amount,
                SUM(sale_details.qty * sale_details.menu_unit_price) - SUM(sale_details.qty * items.purchase_price) as profit')
            ->groupBy('sale_details.item_id', 'items.name', 'items.code')
            ->orderBy('profit', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => ['products' => $products]]);
    }

    /**
     * GET /api/desktop/tax-report
     * Params: date_from, date_to, outlet_id
     */
    public function taxReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');

        $query = DB::table('sales')
            ->where('del_status', 'Live')
            ->where('company_id', $this->companyId);
        if ($dateFrom) $query->whereDate('sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sale_date', '<=', $dateTo);
        if ($outletId) $query->where('outlet_id', $outletId);

        $totalVat = (float) $query->sum('vat');
        $totalTaxable = (float) $query->sum('sub_total');

        // Tax breakup from sale_details
        $taxDetails = DB::table('sale_details')
            ->join('sales', 'sale_details.sales_id', '=', 'sales.id')
            ->leftJoin('taxs', 'sale_details.tax_id', '=', 'taxs.id')
            ->where('sale_details.del_status', 'Live')
            ->where('sales.del_status', 'Live')
            ->where('sales.company_id', $this->companyId)
            ->when($dateFrom, fn($q) => $q->whereDate('sales.sale_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('sales.sale_date', '<=', $dateTo))
            ->when($outletId, fn($q) => $q->where('sales.outlet_id', $outletId))
            ->selectRaw('taxs.name as tax_name, SUM(sale_details.tax_amount) as total_tax, COUNT(*) as count')
            ->groupBy('taxs.name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => ['total_vat' => number_format($totalVat, 2), 'total_taxable' => number_format($totalTaxable, 2)],
                'tax_details' => $taxDetails,
            ],
        ]);
    }

    /**
     * GET /api/desktop/sale-return-report
     * Params: date_from, date_to, customer_id
     */
    public function saleReturnReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $customerId = $request->get('customer_id');

        $query = DB::table('sale_returns')
            ->leftJoin('customers', 'sale_returns.customer_id', '=', 'customers.id')
            ->where('sale_returns.del_status', 'Live')
            ->where('sale_returns.company_id', $this->companyId);
        if ($dateFrom) $query->whereDate('sale_returns.date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sale_returns.date', '<=', $dateTo);
        if ($customerId) $query->where('sale_returns.customer_id', $customerId);

        $returns = $query->orderBy('sale_returns.date', 'desc')->get([
            'sale_returns.id', 'sale_returns.reference_no', 'sale_returns.date',
            'sale_returns.total_return_amount', 'sale_returns.paid_amount', 'sale_returns.due_amount',
            'customers.name as customer_name',
        ]);

        $totalReturn = 0;
        $formatted = $returns->map(function ($r) use (&$totalReturn) {
            $totalReturn += (float) $r->total_return_amount;
            return [
                'reference_no' => $r->reference_no ?? '-',
                'date' => $r->date ? date('d M Y', strtotime($r->date)) : '',
                'customer' => $r->customer_name ?? '-',
                'return_amount' => number_format((float) $r->total_return_amount, 2),
                'paid' => number_format((float) $r->paid_amount, 2),
                'due' => number_format((float) $r->due_amount, 2),
            ];
        });

        return response()->json(['success' => true, 'data' => ['returns' => $formatted, 'summary' => ['total_return' => number_format($totalReturn, 2)]]]);
    }

    /**
     * GET /api/desktop/purchase-return-report
     * Params: date_from, date_to, supplier_id
     */
    public function purchaseReturnReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $supplierId = $request->get('supplier_id');

        $query = DB::table('purchase_returns')
            ->leftJoin('suppliers', 'purchase_returns.supplier_id', '=', 'suppliers.id')
            ->where('purchase_returns.del_status', 'Live')
            ->where('purchase_returns.company_id', $this->companyId);
        if ($dateFrom) $query->whereDate('purchase_returns.date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('purchase_returns.date', '<=', $dateTo);
        if ($supplierId) $query->where('purchase_returns.supplier_id', $supplierId);

        $returns = $query->orderBy('purchase_returns.date', 'desc')->get([
            'purchase_returns.reference_no', 'purchase_returns.date',
            'purchase_returns.total_return_amount', 'suppliers.name as supplier_name',
        ]);

        $totalReturn = 0;
        $formatted = $returns->map(function ($r) use (&$totalReturn) {
            $totalReturn += (float) $r->total_return_amount;
            return [
                'reference_no' => $r->reference_no ?? '-',
                'date' => $r->date ? date('d M Y', strtotime($r->date)) : '',
                'supplier' => $r->supplier_name ?? '-',
                'return_amount' => number_format((float) $r->total_return_amount, 2),
            ];
        });

        return response()->json(['success' => true, 'data' => ['returns' => $formatted, 'summary' => ['total_return' => number_format($totalReturn, 2)]]]);
    }

    /**
     * GET /api/desktop/salary-report
     * Params: date_from, date_to, employee_id
     */
    public function salaryReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $employeeId = $request->get('employee_id');

        $query = DB::table('salaries')
            ->leftJoin('users', 'salaries.user_id', '=', 'users.id')
            ->where('salaries.del_status', 'Live')
            ->where('salaries.company_id', $this->companyId);
        if ($dateFrom) $query->whereDate('salaries.date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('salaries.date', '<=', $dateTo);
        if ($employeeId) $query->where('salaries.user_id', $employeeId);

        $salaries = $query->orderBy('salaries.date', 'desc')->get([
            'salaries.*', 'users.name as employee_name',
        ]);

        $totalSalary = 0;
        $formatted = $salaries->map(function ($s) use (&$totalSalary) {
            $totalSalary += (float) $s->total_salary;
            return [
                'employee' => $s->employee_name ?? '-',
                'date' => $s->date ? date('d M Y', strtotime($s->date)) : '',
                'basic' => number_format((float) $s->basic_salary, 2),
                'allowance' => number_format((float) $s->allowance, 2),
                'deduction' => number_format((float) $s->deduction, 2),
                'total' => number_format((float) $s->total_salary, 2),
                'paid' => number_format((float) $s->paid_amount, 2),
                'due' => number_format((float) $s->due_amount, 2),
            ];
        });

        return response()->json(['success' => true, 'data' => ['salaries' => $formatted, 'summary' => ['total_salary' => number_format($totalSalary, 2)]]]);
    }

    /**
     * GET /api/desktop/damage-report
     * Params: date_from, date_to, outlet_id
     */
    public function damageReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');

        $query = DB::table('damages')
            ->leftJoin('users', 'damages.user_id', '=', 'users.id')
            ->leftJoin('outlets', 'damages.outlet_id', '=', 'outlets.id')
            ->where('damages.del_status', 'Live')
            ->where('damages.company_id', $this->companyId);
        if ($dateFrom) $query->whereDate('damages.date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('damages.date', '<=', $dateTo);
        if ($outletId) $query->where('damages.outlet_id', $outletId);

        $damages = $query->orderBy('damages.date', 'desc')->get([
            'damages.reference_no', 'damages.date', 'damages.total_loss',
            'users.name as employee_name', 'outlets.outlet_name',
        ]);

        $totalLoss = 0;
        $formatted = $damages->map(function ($d) use (&$totalLoss) {
            $totalLoss += (float) $d->total_loss;
            return [
                'reference_no' => $d->reference_no ?? '-',
                'date' => $d->date ? date('d M Y', strtotime($d->date)) : '',
                'employee' => $d->employee_name ?? '-',
                'outlet' => $d->outlet_name ?? '-',
                'total_loss' => number_format((float) $d->total_loss, 2),
            ];
        });

        return response()->json(['success' => true, 'data' => ['damages' => $formatted, 'summary' => ['total_loss' => number_format($totalLoss, 2)]]]);
    }

    /**
     * GET /api/desktop/supplier-ledger-report
     * Params: supplier_id, date_from, date_to
     */
    public function supplierLedgerReport(Request $request): JsonResponse
    {
        $supplierId = $request->get('supplier_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if (!$supplierId) return response()->json(['success' => false, 'message' => 'supplier_id required'], 400);

        $supplier = DB::table('suppliers')->where('id', $supplierId)->where('company_id', $this->companyId)->first();

        // Purchases
        $purchases = DB::table('purchases')
            ->where('supplier_id', $supplierId)->where('del_status', 'Live')
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo))
            ->orderBy('date', 'desc')->get(['reference_no', 'date', 'grand_total', 'paid', 'due']);

        // Supplier Payments
        $payments = DB::table('supplier_payments')
            ->where('supplier_id', $supplierId)->where('del_status', 'Live')
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo))
            ->orderBy('date', 'desc')->get(['reference_no', 'date', 'amount']);

        $totalPurchases = (float) $purchases->sum('grand_total');
        $totalPaid = (float) $purchases->sum('paid') + (float) $payments->sum('amount');
        $totalDue = $totalPurchases - $totalPaid;

        return response()->json([
            'success' => true,
            'data' => [
                'supplier' => ['name' => $supplier->name ?? '-', 'phone' => $supplier->phone ?? '-'],
                'purchases' => $purchases,
                'payments' => $payments,
                'summary' => ['total_purchases' => number_format($totalPurchases, 2), 'total_paid' => number_format($totalPaid, 2), 'total_due' => number_format($totalDue, 2)],
            ],
        ]);
    }

    /**
     * GET /api/desktop/supplier-balance-report
     */
    public function supplierBalanceReport(Request $request): JsonResponse
    {
        $suppliers = DB::table('suppliers')
            ->where('del_status', 'Live')
            ->where('company_id', $this->companyId)
            ->where('due_amount', '>', 0)
            ->orderBy('due_amount', 'desc')
            ->get(['id', 'name', 'phone', 'due_amount']);

        $totalDue = 0;
        $formatted = $suppliers->map(function ($s) use (&$totalDue) {
            $totalDue += (float) $s->due_amount;
            return ['name' => $s->name, 'phone' => $s->phone ?? '-', 'due_amount' => number_format((float) $s->due_amount, 2)];
        });

        return response()->json(['success' => true, 'data' => ['suppliers' => $formatted, 'summary' => ['total_due' => number_format($totalDue, 2)]]]);
    }

    /**
     * GET /api/desktop/low-stock-report
     * Params: category_id, brand_id
     */
    public function lowStockReport(Request $request): JsonResponse
    {
        $categoryId = $request->get('category_id');
        $brandId = $request->get('brand_id');

        $query = DB::table('items')
            ->leftJoin('item_categories', 'items.category_id', '=', 'item_categories.id')
            ->leftJoin('brands', 'items.brand_id', '=', 'brands.id')
            ->where('items.del_status', 'Live')
            ->where('items.company_id', $this->companyId)
            ->where(function ($q) {
                $q->whereNull('items.parent_id')
                  ->orWhere('items.type', '0');
            })
            ->whereColumn('items.stock_quantity', '<=', 'items.minimum_stock');

        if ($categoryId) $query->where('items.category_id', $categoryId);
        if ($brandId) $query->where('items.brand_id', $brandId);

        $items = $query->orderBy('items.stock_quantity', 'asc')->get([
            'items.code', 'items.name', 'items.stock_quantity', 'items.minimum_stock',
            'item_categories.name as category_name', 'brands.name as brand_name',
        ]);

        $totalValue = 0;
        $formatted = $items->map(function ($i) use (&$totalValue) {
            $val = (float) $i->stock_quantity * (float) $i->purchase_price;
            $totalValue += $val;
            return [
                'code' => $i->code ?? '-', 'name' => $i->name ?? '-',
                'category' => $i->category_name ?? '-', 'brand' => $i->brand_name ?? '-',
                'stock' => number_format((float) $i->stock_quantity, 2),
                'minimum' => number_format((float) $i->minimum_stock, 2),
            ];
        });

        return response()->json(['success' => true, 'data' => ['items' => $formatted, 'count' => count($formatted)]]);
    }

    /**
     * GET /api/desktop/expire-soon-report
     * Params: date_from, date_to, outlet_id
     */
    public function expireSoonReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');

        $query = DB::table('view_stock_detail2')
            ->whereNotNull('expiry_imei_serial')
            ->where('expiry_imei_serial', '!=', '')
            ->where('expiry_imei_serial', '>=', now()->toDateString())
            ->where('stock_quantity', '>', 0);

        if ($outletId) $query->where('outlet_id', $outletId);
        if ($dateTo) $query->whereDate('expiry_imei_serial', '<=', $dateTo);

        $stock = $query->leftJoin('items', 'view_stock_detail2.item_id', '=', 'items.id')
            ->select('view_stock_detail2.item_id', 'items.name as item_name', 'items.code as item_code',
                'view_stock_detail2.expiry_imei_serial', DB::raw('SUM(view_stock_detail2.stock_quantity) as quantity'))
            ->groupBy('view_stock_detail2.item_id', 'items.name', 'items.code', 'view_stock_detail2.expiry_imei_serial')
            ->orderBy('view_stock_detail2.expiry_imei_serial', 'asc')
            ->get();

        $formatted = $stock->map(function ($s) {
            return [
                'item_code' => $s->item_code ?? '-',
                'item_name' => $s->item_name ?? '-',
                'expiry' => date('d M Y', strtotime($s->expiry_imei_serial)),
                'quantity' => number_format((float) $s->quantity, 2),
            ];
        });

        return response()->json(['success' => true, 'data' => ['expiring' => $formatted, 'count' => count($formatted)]]);
    }

    /**
     * GET /api/desktop/installment-report
     * Params: date_from, date_to
     */
    public function installmentReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = DB::table('installment_sales')
            ->leftJoin('customers', 'installment_sales.customer_id', '=', 'customers.id')
            ->where('installment_sales.del_status', 'Live')
            ->where('installment_sales.company_id', $this->companyId);
        if ($dateFrom) $query->whereDate('installment_sales.date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('installment_sales.date', '<=', $dateTo);

        $sales = $query->orderBy('installment_sales.date', 'desc')->get([
            'installment_sales.reference_no', 'installment_sales.date', 'installment_sales.total_amount',
            'installment_sales.down_payment', 'installment_sales.paid_amount', 'installment_sales.due_amount',
            'customers.name as customer_name',
        ]);

        $totalAmount = 0;
        $formatted = $sales->map(function ($s) use (&$totalAmount) {
            $totalAmount += (float) $s->total_amount;
            return [
                'reference_no' => $s->reference_no ?? '-',
                'date' => $s->date ? date('d M Y', strtotime($s->date)) : '',
                'customer' => $s->customer_name ?? '-',
                'total' => number_format((float) $s->total_amount, 2),
                'down_payment' => number_format((float) $s->down_payment, 2),
                'paid' => number_format((float) $s->paid_amount, 2),
                'due' => number_format((float) $s->due_amount, 2),
            ];
        });

        return response()->json(['success' => true, 'data' => ['installments' => $formatted, 'summary' => ['total' => number_format($totalAmount, 2)]]]);
    }

    /**
     * GET /api/desktop/installment-due-report
     */
    public function installmentDueReport(Request $request): JsonResponse
    {
        $overdue = DB::table('installment_sale_details')
            ->join('installment_sales', 'installment_sale_details.installment_sale_id', '=', 'installment_sales.id')
            ->leftJoin('customers', 'installment_sales.customer_id', '=', 'customers.id')
            ->where('installment_sale_details.del_status', 'Live')
            ->where('installment_sales.del_status', 'Live')
            ->where('installment_sales.company_id', $this->companyId)
            ->where('installment_sale_details.paid_status', '!=', 'Paid')
            ->orderBy('installment_sale_details.due_date', 'asc')
            ->get([
                'installment_sale_details.installment_no', 'installment_sale_details.due_date',
                'installment_sale_details.installment_amount', 'installment_sale_details.paid_amount',
                'installment_sale_details.paid_status', 'installment_sales.reference_no',
                'customers.name as customer_name',
            ]);

        $totalDue = 0;
        $formatted = $overdue->map(function ($o) use (&$totalDue) {
            $due = (float) $o->installment_amount - (float) $o->paid_amount;
            $totalDue += $due;
            return [
                'customer' => $o->customer_name ?? '-',
                'reference_no' => $o->reference_no ?? '-',
                'installment_no' => $o->installment_no ?? '-',
                'due_date' => $o->due_date ? date('d M Y', strtotime($o->due_date)) : '-',
                'amount' => number_format((float) $o->installment_amount, 2),
                'paid' => number_format((float) $o->paid_amount, 2),
                'due' => number_format($due, 2),
                'status' => $o->paid_status ?? 'Unpaid',
            ];
        });

        return response()->json(['success' => true, 'data' => ['installments' => $formatted, 'summary' => ['total_due' => number_format($totalDue, 2)]]]);
    }

    /**
     * GET /api/desktop/installment-collection-report
     * Params: date_from, date_to
     */
    public function installmentCollectionReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = DB::table('installment_sale_details')
            ->join('installment_sales', 'installment_sale_details.installment_sale_id', '=', 'installment_sales.id')
            ->leftJoin('customers', 'installment_sales.customer_id', '=', 'customers.id')
            ->where('installment_sale_details.del_status', 'Live')
            ->where('installment_sales.del_status', 'Live')
            ->where('installment_sales.company_id', $this->companyId)
            ->whereIn('installment_sale_details.paid_status', ['Paid', 'Partial']);
        if ($dateFrom) $query->whereDate('installment_sale_details.paid_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('installment_sale_details.paid_date', '<=', $dateTo);

        $collections = $query->orderBy('installment_sale_details.paid_date', 'desc')->get([
            'installment_sale_details.installment_no', 'installment_sale_details.paid_date',
            'installment_sale_details.paid_amount', 'installment_sale_details.paid_status',
            'installment_sales.reference_no', 'customers.name as customer_name',
        ]);

        $totalCollected = 0;
        $formatted = $collections->map(function ($c) use (&$totalCollected) {
            $totalCollected += (float) $c->paid_amount;
            return [
                'customer' => $c->customer_name ?? '-',
                'reference_no' => $c->reference_no ?? '-',
                'installment_no' => $c->installment_no ?? '-',
                'paid_date' => $c->paid_date ? date('d M Y', strtotime($c->paid_date)) : '-',
                'amount' => number_format((float) $c->paid_amount, 2),
                'status' => $c->paid_status ?? '-',
            ];
        });

        return response()->json(['success' => true, 'data' => ['collections' => $formatted, 'summary' => ['total_collected' => number_format($totalCollected, 2)]]]);
    }

    /**
     * GET /api/desktop/customer-ledger-report
     * Params: customer_id, date_from, date_to
     */
    public function customerLedgerReport(Request $request): JsonResponse
    {
        $customerId = $request->get('customer_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if (!$customerId) return response()->json(['success' => false, 'message' => 'customer_id required'], 400);

        $customer = DB::table('customers')->where('id', $customerId)->where('company_id', $this->companyId)->first();

        $sales = DB::table('sales')
            ->where('customer_id', $customerId)->where('del_status', 'Live')
            ->when($dateFrom, fn($q) => $q->whereDate('sale_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('sale_date', '<=', $dateTo))
            ->orderBy('sale_date', 'desc')->get(['sale_no', 'sale_date', 'total_payable', 'paid_amount', 'due_amount']);

        $receives = DB::table('customer_receives')
            ->where('customer_id', $customerId)->where('del_status', 'Live')
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo))
            ->orderBy('date', 'desc')->get(['reference_no', 'date', 'amount']);

        $totalSales = (float) $sales->sum('total_payable');
        $totalReceived = (float) $receives->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => ['name' => $customer->name ?? '-', 'phone' => $customer->phone ?? '-'],
                'sales' => $sales, 'receives' => $receives,
                'summary' => ['total_sales' => number_format($totalSales, 2), 'total_received' => number_format($totalReceived, 2), 'balance' => number_format($totalSales - $totalReceived, 2)],
            ],
        ]);
    }

    /**
     * GET /api/desktop/customer-balance-report
     */
    public function customerBalanceReport(Request $request): JsonResponse
    {
        $customers = DB::table('customers')
            ->where('del_status', 'Live')
            ->where('company_id', $this->companyId)
            ->where('due_amount', '>', 0)
            ->orderBy('due_amount', 'desc')
            ->get(['id', 'name', 'phone', 'due_amount']);

        $totalDue = 0;
        $formatted = $customers->map(function ($c) use (&$totalDue) {
            $totalDue += (float) $c->due_amount;
            return ['name' => $c->name, 'phone' => $c->phone ?? '-', 'due_amount' => number_format((float) $c->due_amount, 2)];
        });

        return response()->json(['success' => true, 'data' => ['customers' => $formatted, 'summary' => ['total_due' => number_format($totalDue, 2)]]]);
    }

    /**
     * GET /api/desktop/customer-receive-report
     * Params: date_from, date_to, customer_id
     */
    public function customerReceiveReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $customerId = $request->get('customer_id');

        $query = DB::table('customer_receives')
            ->leftJoin('customers', 'customer_receives.customer_id', '=', 'customers.id')
            ->where('customer_receives.del_status', 'Live')
            ->where('customer_receives.company_id', $this->companyId);
        if ($dateFrom) $query->whereDate('customer_receives.date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('customer_receives.date', '<=', $dateTo);
        if ($customerId) $query->where('customer_receives.customer_id', $customerId);

        $receives = $query->orderBy('customer_receives.date', 'desc')->get([
            'customer_receives.reference_no', 'customer_receives.date', 'customer_receives.amount',
            'customers.name as customer_name',
        ]);

        $totalReceived = 0;
        $formatted = $receives->map(function ($r) use (&$totalReceived) {
            $totalReceived += (float) $r->amount;
            return [
                'reference_no' => $r->reference_no ?? '-',
                'date' => $r->date ? date('d M Y', strtotime($r->date)) : '',
                'customer' => $r->customer_name ?? '-',
                'amount' => number_format((float) $r->amount, 2),
            ];
        });

        return response()->json(['success' => true, 'data' => ['receives' => $formatted, 'summary' => ['total_received' => number_format($totalReceived, 2)]]]);
    }

    /**
     * GET /api/desktop/servicing-report
     * Params: date_from, date_to
     */
    public function servicingReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = DB::table('servicings')
            ->leftJoin('customers', 'servicings.customer_id', '=', 'customers.id')
            ->where('servicings.del_status', 'Live')
            ->where('servicings.company_id', $this->companyId);
        if ($dateFrom) $query->whereDate('servicings.date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('servicings.date', '<=', $dateTo);

        $servicings = $query->orderBy('servicings.date', 'desc')->get([
            'servicings.reference_no', 'servicings.date', 'servicings.total_amount',
            'servicings.paid_amount', 'servicings.due_amount', 'servicings.status',
            'customers.name as customer_name',
        ]);

        $totalAmount = 0;
        $formatted = $servicings->map(function ($s) use (&$totalAmount) {
            $totalAmount += (float) $s->total_amount;
            return [
                'reference_no' => $s->reference_no ?? '-',
                'date' => $s->date ? date('d M Y', strtotime($s->date)) : '',
                'customer' => $s->customer_name ?? '-',
                'total' => number_format((float) $s->total_amount, 2),
                'paid' => number_format((float) $s->paid_amount, 2),
                'due' => number_format((float) $s->due_amount, 2),
                'status' => $s->status ?? '-',
            ];
        });

        return response()->json(['success' => true, 'data' => ['servicings' => $formatted, 'summary' => ['total_amount' => number_format($totalAmount, 2)]]]);
    }

    /**
     * GET /api/desktop/gst-report
     * Params: date_from, date_to, format (1-8)
     */
    public function gstReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $format = (int) $request->get('format', 1);

        $query = DB::table('sales')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('sales.del_status', 'Live')
            ->where('sales.company_id', $this->companyId);
        if ($dateFrom) $query->whereDate('sales.sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sales.sale_date', '<=', $dateTo);

        $sales = $query->orderBy('sales.sale_date', 'desc')->get([
            'sales.sale_no', 'sales.sale_date', 'sales.sub_total', 'sales.vat',
            'sales.total_discount_amount', 'sales.total_payable',
            'customers.name as customer_name', 'customers.gstin',
        ]);

        $totalSubtotal = (float) $sales->sum('sub_total');
        $totalVat = (float) $sales->sum('vat');

        return response()->json([
            'success' => true,
            'data' => [
                'format' => $format,
                'sales' => $sales->map(fn($s) => [
                    'sale_no' => $s->sale_no, 'date' => $s->sale_date ? date('d M Y', strtotime($s->sale_date)) : '',
                    'customer' => $s->customer_name ?? '-', 'gstin' => $s->gstin ?? '-',
                    'taxable' => number_format((float) $s->sub_total, 2),
                    'tax' => number_format((float) $s->vat, 2),
                    'total' => number_format((float) $s->total_payable, 2),
                ]),
                'summary' => ['total_taxable' => number_format($totalSubtotal, 2), 'total_tax' => number_format($totalVat, 2)],
            ],
        ]);
    }

    /**
     * GET /api/desktop/profit-loss-report
     * Params: date_from, date_to, outlet_id
     */
    public function profitLossReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');

        $baseQuery = function ($table) use ($dateFrom, $dateTo, $outletId) {
            $q = DB::table($table)->where('del_status', 'Live')->where('company_id', $this->companyId);
            if ($dateFrom) $q->whereDate('date', '>=', $dateFrom);
            if ($dateTo) $q->whereDate('date', '<=', $dateTo);
            if ($outletId) $q->where('outlet_id', $outletId);
            return $q;
        };

        $saleQuery = function () use ($dateFrom, $dateTo, $outletId) {
            $q = DB::table('sales')->where('del_status', 'Live')->where('company_id', $this->companyId);
            if ($dateFrom) $q->whereDate('sale_date', '>=', $dateFrom);
            if ($dateTo) $q->whereDate('sale_date', '<=', $dateTo);
            if ($outletId) $q->where('outlet_id', $outletId);
            return $q;
        };

        $totalSales = (float) $saleQuery()->sum('total_payable');
        $totalSaleReturn = (float) $baseQuery('sale_returns')->sum('total_return_amount');
        $totalPurchase = (float) $baseQuery('purchases')->sum('grand_total');
        $totalPurchaseReturn = (float) $baseQuery('purchase_returns')->sum('total_return_amount');
        $totalExpense = (float) $baseQuery('expenses')->sum('amount');
        $totalIncome = (float) $baseQuery('incomes')->sum('amount');

        $grossProfit = $totalSales - $totalSaleReturn - $totalPurchase + $totalPurchaseReturn;
        $netProfit = $grossProfit - $totalExpense + $totalIncome;

        return response()->json([
            'success' => true,
            'data' => [
                'income' => [
                    'sales' => number_format($totalSales, 2),
                    'sale_return' => number_format($totalSaleReturn, 2),
                    'purchase_return' => number_format($totalPurchaseReturn, 2),
                    'other_income' => number_format($totalIncome, 2),
                ],
                'expense' => [
                    'purchase' => number_format($totalPurchase, 2),
                    'expense' => number_format($totalExpense, 2),
                ],
                'summary' => [
                    'gross_profit' => number_format($grossProfit, 2),
                    'net_profit' => number_format($netProfit, 2),
                ],
            ],
        ]);
    }

    /**
     * GET /api/desktop/attendance-report
     * Params: date_from, date_to, employee_id
     */
    public function attendanceReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $employeeId = $request->get('employee_id');

        $query = DB::table('attendances')
            ->leftJoin('users', 'attendances.user_id', '=', 'users.id')
            ->where('attendances.del_status', 'Live')
            ->where('attendances.company_id', $this->companyId);
        if ($dateFrom) $query->whereDate('attendances.date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('attendances.date', '<=', $dateTo);
        if ($employeeId) $query->where('attendances.user_id', $employeeId);

        $attendances = $query->orderBy('attendances.date', 'desc')->get([
            'attendances.date', 'attendances.status', 'attendances.check_in', 'attendances.check_out',
            'users.name as employee_name',
        ]);

        $present = $attendances->where('status', 'Present')->count();
        $absent = $attendances->where('status', 'Absent')->count();
        $late = $attendances->where('status', 'Late')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'attendances' => $attendances->map(fn($a) => [
                    'employee' => $a->employee_name ?? '-',
                    'date' => $a->date ? date('d M Y', strtotime($a->date)) : '',
                    'status' => $a->status ?? '-',
                    'check_in' => $a->check_in ?? '-',
                    'check_out' => $a->check_out ?? '-',
                ]),
                'summary' => ['present' => $present, 'absent' => $absent, 'late' => $late],
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // 6 MISSING REPORTS — Cloud mein bilkul nahi the
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/desktop/warranty-checking-report
     * Params: imei_serial (optional), customer_id (optional)
     */
    public function warrantyCheckingReport(Request $request): JsonResponse
    {
        $imeiSerial = $request->get('imei_serial');
        $customerId = $request->get('customer_id');

        $query = DB::table('warranties')
            ->leftJoin('customers', 'warranties.customer_id', '=', 'customers.id')
            ->leftJoin('items', 'warranties.item_id', '=', 'items.id')
            ->where('warranties.del_status', 'Live')
            ->where('warranties.company_id', $this->companyId);

        if ($imeiSerial) $query->where('warranties.imei_serial', 'like', "%$imeiSerial%");
        if ($customerId) $query->where('warranties.customer_id', $customerId);

        $warranties = $query->orderBy('warranties.created_at', 'desc')->limit(100)->get([
            'warranties.*', 'customers.name as customer_name', 'customers.phone as customer_phone',
            'items.name as item_name', 'items.code as item_code',
        ]);

        $formatted = $warranties->map(function ($w) {
            $expiryDate = $w->warranty_expiry_date ?? $w->end_date ?? null;
            $isExpired = $expiryDate && strtotime($expiryDate) < time();
            return [
                'item' => ($w->item_name ?? '-') . ' (' . ($w->item_code ?? '-') . ')',
                'imei_serial' => $w->imei_serial ?? '-',
                'customer' => $w->customer_name ?? '-',
                'phone' => $w->customer_phone ?? '-',
                'start_date' => $w->warranty_start_date ?? $w->start_date ?? '-',
                'expiry_date' => $expiryDate ?? '-',
                'status' => $isExpired ? 'Expired' : 'Active',
            ];
        });

        return response()->json(['success' => true, 'data' => ['warranties' => $formatted, 'count' => count($formatted)]]);
    }

    /**
     * GET /api/desktop/detailed-installment-due-report
     */
    public function detailedInstallmentDueReport(Request $request): JsonResponse
    {
        $installments = DB::table('installment_sale_details')
            ->join('installment_sales', 'installment_sale_details.installment_sale_id', '=', 'installment_sales.id')
            ->leftJoin('customers', 'installment_sales.customer_id', '=', 'customers.id')
            ->where('installment_sale_details.del_status', 'Live')
            ->where('installment_sales.del_status', 'Live')
            ->where('installment_sales.company_id', $this->companyId)
            ->where('installment_sale_details.paid_status', '!=', 'Paid')
            ->orderBy('installment_sale_details.due_date', 'asc')
            ->get([
                'installment_sale_details.*', 'installment_sales.reference_no', 'installment_sales.total_amount as sale_total',
                'customers.name as customer_name', 'customers.phone as customer_phone',
            ]);

        $totalDue = 0;
        $formatted = $installments->map(function ($i) use (&$totalDue) {
            $due = (float) $i->installment_amount - (float) $i->paid_amount;
            $totalDue += $due;
            return [
                'customer' => $i->customer_name ?? '-',
                'phone' => $i->customer_phone ?? '-',
                'reference_no' => $i->reference_no ?? '-',
                'installment_no' => $i->installment_no ?? '-',
                'due_date' => $i->due_date ? date('d M Y', strtotime($i->due_date)) : '-',
                'installment_amount' => number_format((float) $i->installment_amount, 2),
                'paid_amount' => number_format((float) $i->paid_amount, 2),
                'due' => number_format($due, 2),
                'status' => $i->paid_status ?? 'Unpaid',
                'days_overdue' => max(0, (int) ((time() - strtotime($i->due_date)) / 86400)),
            ];
        });

        return response()->json(['success' => true, 'data' => ['installments' => $formatted, 'summary' => ['total_due' => number_format($totalDue, 2), 'count' => count($formatted)]]]);
    }

    /**
     * GET /api/desktop/detailed-available-loyalty-point-report
     */
    public function detailedAvailableLoyaltyPointReport(Request $request): JsonResponse
    {
        $customers = DB::table('customers')
            ->where('del_status', 'Live')
            ->where('company_id', $this->companyId)
            ->where('loyalty_point', '>', 0)
            ->orderBy('loyalty_point', 'desc')
            ->get();

        $totalPoints = 0;
        $formatted = $customers->map(function ($c) use (&$totalPoints) {
            $points = (float) $c->loyalty_point;
            $totalPoints += $points;
            $totalPurchases = (float) DB::table('sales')->where('customer_id', $c->id)->where('del_status', 'Live')->sum('total_payable');
            return [
                'name' => $c->name ?? '-',
                'phone' => $c->phone ?? '-',
                'loyalty_points' => number_format($points, 0),
                'total_purchases' => number_format($totalPurchases, 2),
                'estimated_value' => number_format($points * 0.10, 2),
            ];
        });

        return response()->json(['success' => true, 'data' => ['customers' => $formatted, 'summary' => ['total_points' => number_format($totalPoints, 0)]]]);
    }

    /**
     * GET /api/desktop/detailed-usage-loyalty-point-report
     * Params: date_from, date_to
     */
    public function detailedUsageLoyaltyPointReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = DB::table('sales')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('sales.del_status', 'Live')
            ->where('sales.company_id', $this->companyId)
            ->where('sales.loyalty_point_used', '>', 0);
        if ($dateFrom) $query->whereDate('sales.sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sales.sale_date', '<=', $dateTo);

        $sales = $query->orderBy('sales.sale_date', 'desc')->get([
            'sales.sale_no', 'sales.sale_date', 'sales.loyalty_point_used', 'sales.total_payable',
            'customers.name as customer_name',
        ]);

        $totalUsed = 0;
        $formatted = $sales->map(function ($s) use (&$totalUsed) {
            $used = (float) $s->loyalty_point_used;
            $totalUsed += $used;
            return [
                'sale_no' => $s->sale_no ?? '-',
                'date' => $s->sale_date ? date('d M Y', strtotime($s->sale_date)) : '',
                'customer' => $s->customer_name ?? '-',
                'points_used' => number_format($used, 0),
                'sale_amount' => number_format((float) $s->total_payable, 2),
            ];
        });

        return response()->json(['success' => true, 'data' => ['sales' => $formatted, 'summary' => ['total_used' => number_format($totalUsed, 0)]]]);
    }

    /**
     * GET /api/desktop/detailed-scheme-report
     */
    public function detailedSchemeReport(Request $request): JsonResponse
    {
        $promotions = DB::table('promotions')
            ->where('del_status', 'Live')
            ->where('company_id', $this->companyId)
            ->orderBy('created_at', 'desc')
            ->get();

        $formatted = $promotions->map(function ($p) {
            $totalUsage = DB::table('sales')
                ->where('promotion_id', $p->id)
                ->where('del_status', 'Live')
                ->count();
            return [
                'name' => $p->name ?? '-',
                'type' => $p->type ?? '-',
                'discount' => number_format((float) ($p->discount ?? 0), 2),
                'applied_to' => $p->applied_to ?? '-',
                'start_date' => $p->start_date ? date('d M Y', strtotime($p->start_date)) : '-',
                'end_date' => $p->end_date ? date('d M Y', strtotime($p->end_date)) : '-',
                'status' => $p->status ?? '-',
                'total_usage' => $totalUsage,
            ];
        });

        return response()->json(['success' => true, 'data' => ['promotions' => $formatted]]);
    }

    /**
     * GET /api/desktop/detailed-item-tracking-report
     * Params: item_id, date_from, date_to
     */
    public function detailedItemTrackingReport(Request $request): JsonResponse
    {
        $itemId = $request->get('item_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if (!$itemId) return response()->json(['success' => false, 'message' => 'item_id required'], 400);

        $item = DB::table('items')->where('id', $itemId)->where('company_id', $this->companyId)->first();
        if (!$item) return response()->json(['success' => false, 'message' => 'Item not found'], 404);

        // Sale transactions
        $saleQuery = DB::table('sale_details')
            ->join('sales', 'sale_details.sales_id', '=', 'sales.id')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('sale_details.item_id', $itemId)
            ->where('sale_details.del_status', 'Live')
            ->where('sales.del_status', 'Live')
            ->where('sales.company_id', $this->companyId);
        if ($dateFrom) $saleQuery->whereDate('sales.sale_date', '>=', $dateFrom);
        if ($dateTo) $saleQuery->whereDate('sales.sale_date', '<=', $dateTo);
        $sales = $saleQuery->orderBy('sales.sale_date', 'desc')->get([
            'sales.sale_no', 'sales.sale_date', 'customers.name as customer_name',
            'sale_details.qty', 'sale_details.menu_unit_price', 'sale_details.total',
        ]);

        // Purchase transactions
        $purchaseQuery = DB::table('purchase_details')
            ->join('purchases', 'purchase_details.purchase_id', '=', 'purchases.id')
            ->leftJoin('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->where('purchase_details.item_id', $itemId)
            ->where('purchase_details.del_status', 'Live')
            ->where('purchases.del_status', 'Live')
            ->where('purchases.company_id', $this->companyId);
        if ($dateFrom) $purchaseQuery->whereDate('purchases.date', '>=', $dateFrom);
        if ($dateTo) $purchaseQuery->whereDate('purchases.date', '<=', $dateTo);
        $purchases = $purchaseQuery->orderBy('purchases.date', 'desc')->get([
            'purchases.reference_no', 'purchases.date', 'suppliers.name as supplier_name',
            'purchase_details.qty', 'purchase_details.purchase_price', 'purchase_details.total',
        ]);

        // Sale Returns
        $saleReturns = DB::table('sale_return_details')
            ->join('sale_returns', 'sale_return_details.sale_return_id', '=', 'sale_returns.id')
            ->where('sale_return_details.item_id', $itemId)
            ->where('sale_return_details.del_status', 'Live')
            ->where('sale_returns.del_status', 'Live')
            ->where('sale_returns.company_id', $this->companyId)
            ->orderBy('sale_returns.date', 'desc')->get([
                'sale_returns.reference_no', 'sale_returns.date', 'sale_return_details.qty', 'sale_return_details.total',
            ]);

        // Purchase Returns
        $purchaseReturns = DB::table('purchase_return_details')
            ->join('purchase_returns', 'purchase_return_details.purchase_return_id', '=', 'purchase_returns.id')
            ->where('purchase_return_details.item_id', $itemId)
            ->where('purchase_return_details.del_status', 'Live')
            ->where('purchase_returns.del_status', 'Live')
            ->where('purchase_returns.company_id', $this->companyId)
            ->orderBy('purchase_returns.date', 'desc')->get([
                'purchase_returns.reference_no', 'purchase_returns.date', 'purchase_return_details.qty', 'purchase_return_details.total',
            ]);

        // Damages
        $damages = DB::table('damage_details')
            ->join('damages', 'damage_details.damage_id', '=', 'damages.id')
            ->where('damage_details.item_id', $itemId)
            ->where('damage_details.del_status', 'Live')
            ->where('damages.del_status', 'Live')
            ->where('damages.company_id', $this->companyId)
            ->orderBy('damages.date', 'desc')->get([
                'damages.reference_no', 'damages.date', 'damage_details.qty',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'item' => ['code' => $item->code, 'name' => $item->name],
                'sales' => $sales,
                'purchases' => $purchases,
                'sale_returns' => $saleReturns,
                'purchase_returns' => $purchaseReturns,
                'damages' => $damages,
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // BUSY NOTIFY IMPORT APIs
    // Desktop app se trigger hota hai — BusyNotify → Cloud DB → Desktop sync
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/desktop/busy-import/status
     * BusyNotify config check karo — configured hai ya nahi.
     */
    public function busyImportStatus(): JsonResponse
    {
        $isEnabled = \App\Services\BusyNotifyService::isEnabled();

        if (!$isEnabled) {
            return response()->json([
                'success'      => false,
                'configured'   => false,
                'message'      => 'BusyNotify API key not set. Add BUSYNOTIFY_API_KEY in server .env',
            ]);
        }

        // Company info fetch karo
        try {
            $service   = new \App\Services\BusyNotifyService();
            $companies = $service->getCompanies();
            $company   = $companies[0] ?? null;

            return response()->json([
                'success'      => true,
                'configured'   => true,
                'company_id'   => $company['companyId'] ?? null,
                'company_name' => $company['companyName'] ?? 'Unknown',
                'message'      => 'BusyNotify connected',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success'    => false,
                'configured' => true,
                'message'    => 'BusyNotify error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * POST /api/desktop/busy-import/customers
     * BusyNotify se customers import karo → cloud DB mein save.
     * Desktop next sync/pull pe automatically ye customers le aayega.
     */
    public function busyImportCustomers(Request $request): JsonResponse
    {
        if (!\App\Services\BusyNotifyService::isEnabled()) {
            return response()->json(['success' => false, 'message' => 'BusyNotify not configured on server'], 400);
        }

        try {
            $service   = new \App\Services\BusyNotifyService();
            $busyData  = $service->getCustomers();
            $imported  = 0;
            $updated   = 0;
            $skipped   = 0;
            $errors    = [];
            $now       = now()->toDateTimeString();

            // Non-customer account groups skip karo
            $skipGroups = [
                'Bank Accounts', 'Duties & Taxes', 'Expenses (Indirect/Admn.)',
                'Income (Indirect)', 'Current Assets', 'Fixed Assets',
                'Unsecured Loans', 'PAIDUP CAPITAL',
            ];

            foreach ($busyData as $row) {
                try {
                    // Non-customer group skip
                    $groupName = $row['group_name'] ?? '';
                    if (in_array($groupName, $skipGroups)) { $skipped++; continue; }

                    $mapped = \App\Services\BusyNotifyService::mapCustomer($row);
                    if (empty($mapped['name'])) { $skipped++; continue; }

                    $mapped['company_id'] = $this->companyId;
                    $mapped['user_id']    = $this->userId;
                    $mapped['updated_at'] = $now;

                    // Dedup: busy_id → phone → name
                    $existing = null;
                    if (!empty($mapped['busy_id'])) {
                        $existing = DB::table('customers')
                            ->where('busy_id', $mapped['busy_id'])
                            ->where('company_id', $this->companyId)
                            ->where('del_status', 'Live')->first();
                    }
                    if (!$existing && !empty($mapped['phone'])) {
                        $existing = DB::table('customers')
                            ->where('phone', $mapped['phone'])
                            ->where('company_id', $this->companyId)
                            ->where('del_status', 'Live')->first();
                    }
                    if (!$existing && !empty($mapped['name'])) {
                        $existing = DB::table('customers')
                            ->where('name', $mapped['name'])
                            ->where('company_id', $this->companyId)
                            ->where('del_status', 'Live')->first();
                    }

                    if ($existing) {
                        // Sirf non-null values update karo
                        DB::table('customers')->where('id', $existing->id)
                            ->update(array_filter($mapped, fn($v) => $v !== null));
                        $updated++;
                    } else {
                        $mapped['del_status'] = 'Live';
                        $mapped['created_at'] = $now;
                        DB::table('customers')->insert($mapped);
                        $imported++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = ($row['customer_name'] ?? '?') . ': ' . $e->getMessage();
                }
            }

            return response()->json([
                'success'    => true,
                'imported'   => $imported,
                'updated'    => $updated,
                'skipped'    => $skipped,
                'total_rows' => count($busyData),
                'errors'     => array_slice($errors, 0, 10),
                'message'    => "Customers: {$imported} naye, {$updated} update",
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Desktop BusyNotify customers import failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/desktop/busy-import/products
     * BusyNotify se products/items import karo → cloud DB mein save.
     * Desktop next sync/pull pe automatically ye items le aayega (with stock).
     */
    public function busyImportProducts(Request $request): JsonResponse
    {
        if (!\App\Services\BusyNotifyService::isEnabled()) {
            return response()->json(['success' => false, 'message' => 'BusyNotify not configured on server'], 400);
        }

        set_time_limit(300);

        try {
            // EK HI CODE PATH: scheduler wali `busy:import` command chalao.
            // Is endpoint me uska copy-paste tha jo diverge ho chuka tha — usme
            // unit text ID ki jagah raw tha, category resolve nahi hota tha,
            // stock ledger (view_stock_detail) sync hota hi nahi tha aur view
            // rebuild non-atomic (TRUNCATE+INSERT) thi jisse fail par sab stock
            // zero dikhta tha. Ab dono jagah wahi (hardened) logic chalti hai.
            \Illuminate\Support\Facades\Artisan::call('busy:import', [
                '--type'    => 'products',
                '--company' => $this->companyId,
                '--outlet'  => $this->outletId,
                '--user'    => $this->userId,
            ]);
            $output = \Illuminate\Support\Facades\Artisan::output();

            $imported = $updated = $totalRows = 0;
            if (preg_match('/Items: (\d+) new, (\d+) updated/', $output, $m)) {
                $imported = (int) $m[1];
                $updated  = (int) $m[2];
            }
            if (preg_match('/Fetched: (\d+)/', $output, $m)) {
                $totalRows = (int) $m[1];
            }

            return response()->json([
                'success'    => true,
                'imported'   => $imported,
                'updated'    => $updated,
                'skipped'    => 0,
                'total_rows' => $totalRows,
                'errors'     => [],
                'message'    => "Items: {$imported} naye, {$updated} update",
                'log'        => $output,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Desktop BusyNotify products import failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }



    /**
     * POST /api/desktop/busy-import/all
     * Artisan command se run karta hai — HTTP timeout issue nahi.
     * Customers (fast) + Products (CASE batch update, ~60s total).
     */
    public function busyImportAll(Request $request): JsonResponse
    {
        if (!\App\Services\BusyNotifyService::isEnabled()) {
            return response()->json(['success' => false, 'message' => 'BusyNotify API key not configured on server.'], 400);
        }

        set_time_limit(300);

        try {
            // Run via Artisan (avoids FPM worker queue issue, uses CLI path)
            \Illuminate\Support\Facades\Artisan::call('busy:import', [
                '--type'    => 'all',
                '--company' => $this->companyId,
                '--outlet'  => $this->outletId,
                '--user'    => $this->userId,
            ]);
            $output = \Illuminate\Support\Facades\Artisan::output();

            // Parse counts from output
            $custImported = $custUpdated = $prodImported = $prodUpdated = 0;
            if (preg_match('/Customers: (\d+) new, (\d+) updated/', $output, $m)) {
                $custImported = (int)$m[1]; $custUpdated = (int)$m[2];
            }
            if (preg_match('/Items: (\d+) new, (\d+) updated/', $output, $m)) {
                $prodImported = (int)$m[1]; $prodUpdated = (int)$m[2];
            }

            $total = $custImported + $prodImported;
            $totalU = $custUpdated + $prodUpdated;

            return response()->json([
                'success' => true,
                'message' => "BusyNotify import done! {$total} naye, {$totalU} update.",
                'summary' => [
                    'customers_imported' => $custImported,
                    'customers_updated'  => $custUpdated,
                    'products_imported'  => $prodImported,
                    'products_updated'   => $prodUpdated,
                    'total_imported'     => $total,
                    'total_updated'      => $totalU,
                ],
                'log' => $output,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('BusyNotify importAll failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/desktop/busy-import/stock-delta
     *
     * Desktop har 2 minute mein ye endpoint poll karta hai.
     * Returns: sirf wo items jinki stock change hui hai last sync ke baad.
     * Cache se serve hota hai — fast response, no Busy API call on desktop request.
     *
     * Response:
     * {
     *   "synced_at": "2026-09-08 22:00:00",
     *   "updated": 15,
     *   "delta": [
     *     { "item_id": 123, "busy_id": 456, "code": "ABC", "old_stock": 10, "new_stock": 7 }
     *   ]
     * }
     */
    public function busyStockDelta(): JsonResponse
    {
        $cached = \Illuminate\Support\Facades\Cache::get('busy_stock_delta');
        $lastSync = \Illuminate\Support\Facades\Cache::get('busy_last_stock_sync');

        if (!$cached) {
            return response()->json([
                'success'   => true,
                'synced_at' => $lastSync,
                'updated'   => 0,
                'delta'     => [],
                'message'   => 'No stock changes since last sync',
            ]);
        }

        return response()->json([
            'success'   => true,
            'synced_at' => $cached['synced_at'],
            'updated'   => $cached['updated'],
            'delta'     => $cached['delta'],
        ]);
    }

    /**
     * POST /api/desktop/busy-import/sync-stock
     *
     * Desktop startup pe call karo — turant stock sync trigger karo.
     * Server pe busy:sync-stock command chalata hai (background).
     */
    public function busyTriggerStockSync(): JsonResponse
    {
        if (!\App\Services\BusyNotifyService::isEnabled()) {
            return response()->json(['success' => false, 'message' => 'BusyNotify not configured'], 400);
        }

        try {
            // Background mein chalao — response wait nahi karega
            $companyId = $this->companyId;
            $outletId  = $this->outletId;
            dispatch(function () use ($companyId, $outletId) {
                \Illuminate\Support\Facades\Artisan::call('busy:sync-stock', [
                    '--company' => $companyId,
                    '--outlet'  => $outletId,
                ]);
            })->afterResponse();

            return response()->json([
                'success' => true,
                'message' => 'Stock sync started in background',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
