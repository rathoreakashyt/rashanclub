<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\BusinessClub\Services\BusinessClubService;

class SyncController extends Controller
{
    private int $companyId;
    private int $outletId;
    private int $userId;
    private string $deviceId;

    public function __construct(Request $request)
    {
        $user = $request->user();
        $this->companyId = (int) ($user->company_id ?? 1);
        $this->outletId = (int) ($request->header('X-Outlet-Id') ?: 1);
        $this->userId = (int) $user->id;
        // Multi-counter: har machine/counter ka unique device id — mapping isi se
        // key hoti hai taaki 10 counters ke same local_id clash na karein.
        $this->deviceId = (string) ($request->header('X-Device-Id') ?: $request->input('device_id', 'unknown'));
    }

    /**
     * GET /api/health - connection + DB check
     */
    public function health(): JsonResponse
    {
        $dbOk = true;
        try {
            DB::select('SELECT 1');
        } catch (\Throwable) {
            $dbOk = false;
        }

        return response()->json([
            'status' => $dbOk ? 'ok' : 'db_error',
            'server_time' => now()->toDateTimeString(),
            'app' => config('app.name'),
        ]);
    }

    /**
     * GET /api/live-status
     * Web POS page ke liye desktop-sync live badge:
     * per-outlet last desktop sync time (idempotency_keys se) —
     * multi-outlet aware, session outlet optional filter.
     */
    public function liveStatus(Request $request): JsonResponse
    {
        $rows = DB::table('idempotency_keys')
            ->select('device_id', DB::raw('MAX(created_at) as last_sync'))
            ->where('expires_at', '>', now())
            ->groupBy('device_id')
            ->orderByDesc('last_sync')
            ->limit(50)
            ->get();

        $devices = $rows->map(function ($r) {
            $last = $r->last_sync ? \Carbon\Carbon::parse($r->last_sync) : null;
            $secs = $last ? now()->diffInSeconds($last) : null;

            return [
                'device_id' => $r->device_id,
                'last_sync' => $last?->toDateTimeString(),
                'seconds_ago' => $secs !== null ? (int) $secs : null,
                // Live = pichhle 5 min me sync hui
                'live' => $secs !== null && $secs <= 300,
            ];
        })->values();

        return response()->json([
            'server_time' => now()->toDateTimeString(),
            'any_live' => $devices->contains('live', true),
            'devices' => $devices,
        ]);
    }

    /**
     * GET /api/gst-lookup/{gstin}
     * Fetches business details from GST portal (with caching).
     * Uses multiple fallback sources.
     */
    public function gstLookup(string $gstin): JsonResponse
    {
        $gstin = strtoupper(trim($gstin));

        // Validate format first
        if (strlen($gstin) !== 15 || !preg_match('/^[0-3][0-9][A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/', $gstin)) {
            return response()->json(['error' => 'Invalid GSTIN format'], 400);
        }

        // Check cache in DB (avoid hitting external APIs repeatedly)
        try {
            $cached = DB::table('gst_cache')->where('gstin', $gstin)->first();
            if ($cached && $cached->updated_at > now()->subDays(30)->toDateTimeString()) {
                return response()->json([
                    'data' => [
                        'legal_name' => $cached->legal_name,
                        'trade_name' => $cached->trade_name,
                        'status' => $cached->status,
                        'address' => $cached->address,
                    ],
                    'cached' => true,
                ]);
            }
        } catch (\Throwable $e) {}

        $legalName = '';
        $tradeName = '';
        $status = '';
        $address = '';

        // Method 1: Scrape Razorpay GST Search (free, unlimited, no API key)
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://razorpay.com/gst-number-search/{$gstin}/",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                    'Accept: text/html,application/xhtml+xml',
                ],
            ]);
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && strlen($html) > 1000) {
                if (preg_match('/Legal Name of Business.*?<h5[^>]*>(.*?)<\/h5>/s', $html, $m))
                    $legalName = trim(strip_tags($m[1]));
                if (preg_match('/GSTIN Status.*?<h5[^>]*>(.*?)<\/h5>/s', $html, $m))
                    $status = trim(strip_tags($m[1]));
                if (preg_match('/Taxpayer Type.*?<h5[^>]*>(.*?)<\/h5>/s', $html, $m))
                    $tradeName = trim(strip_tags($m[1])); // Use taxpayer type as extra info
                if (preg_match('/State Jurisdiction.*?<h5[^>]*>(.*?)<\/h5>/s', $html, $m))
                    $address = trim(strip_tags($m[1]));

                // Trade name = page title has it: "GST Number of COMPANY NAME is GSTIN"
                if (preg_match('/GST Number of\s+(.*?)\s+is\s+' . preg_quote($gstin, '/') . '/i', $html, $tm))
                    $tradeName = trim($tm[1]);
            }
        } catch (\Throwable $e) {
            \Log::warning("Razorpay GST scrape failed for {$gstin}: " . $e->getMessage());
        }

        // Method 2: Check our own customers/suppliers tables (local data)
        if (empty($legalName)) {
            $customer = DB::table('customers')
                ->where('gst_number', $gstin)
                ->where('company_id', $this->companyId)
                ->first();
            if ($customer) {
                $legalName = $customer->name ?? '';
                $address = $customer->address ?? '';
                $status = 'Active (local)';
            }
        }
        if (empty($legalName)) {
            $supplier = DB::table('suppliers')
                ->where('gst_number', $gstin)
                ->where('company_id', $this->companyId)
                ->first();
            if ($supplier) {
                $legalName = $supplier->name ?? '';
                $address = $supplier->address ?? '';
                $status = 'Active (local)';
            }
        }

        // Cache result
        if (!empty($legalName)) {
            try {
                DB::table('gst_cache')->updateOrInsert(
                    ['gstin' => $gstin],
                    ['legal_name' => $legalName, 'trade_name' => $tradeName,
                     'status' => $status, 'address' => $address,
                     'updated_at' => now()->toDateTimeString()]
                );
            } catch (\Throwable $e) {}
        }

        return response()->json([
            'data' => [
                'legal_name' => $legalName,
                'trade_name' => $tradeName,
                'status' => $status,
                'address' => $address,
            ],
        ]);
    }

    /**
     * GET /api/meta - small lookup/master data for local caching
     */
    public function meta(): JsonResponse
    {
        return response()->json([
            'company' => DB::table('companies')->where('id', $this->companyId)->first(),
            'outlet' => DB::table('outlets')->where('id', $this->outletId)->first(),
            'payment_methods' => DB::table('payment_methods')
                ->where('del_status', 'Live')->where('is_active', 1)
                ->get(['id', 'name', 'type']),
            'counters' => DB::table('counters')
                ->where('del_status', 'Live')
                ->where('company_id', $this->companyId)
                ->get(['id', 'name', 'outlet_id', 'printer_id']),
            'units' => DB::table('units')
                ->get(['id', 'unit_name', 'description', 'del_status']),
            'brands' => DB::table('brands')
                ->get(['id', 'name', 'description', 'del_status']),
            'categories' => DB::table('item_categories')
                ->get(['id', 'name', 'description', 'del_status']),
            'suppliers' => DB::table('suppliers')
                ->where('company_id', $this->companyId)
                ->get(['id', 'name', 'del_status']),
            'racks' => DB::table('racks')
                ->get(['id', 'name', 'description', 'del_status']),
            'variations' => DB::table('variations')
                ->get(['id', 'variation_name', 'variation_value', 'del_status']),
            'expense_categories' => DB::table('expense_categories')
                ->get(['id', 'name', 'description', 'del_status']),
        ]);
    }

    /**
     * GET /api/sync/pull?since=YYYY-MM-DD HH:MM:SS
     * Returns all records changed after `since` (plus current stock).
     */
    public function pull(Request $request): JsonResponse
    {
        $since = $request->query('since');
        $sinceOk = is_string($since) && strtotime($since) !== false;
        $sinceTs = $sinceOk ? $since : null;

        // NOTE: sab tables ka FULL data har pull pe bhejte hain (since filter nahi).
        // Desktop ka SQLite mirror kabhi incomplete na ho — data volumes chhote hain
        // (dozens of rows per table), isliye full dump per sync cheap hai.
        // ═══ ENTERPRISE: Use incremental sync when 'since' is provided ═══
        // First sync (since=null) = full dump. Subsequent syncs = only changed records.
        //
        // FIX (timezone watermark bug): customers/suppliers ALWAYS full dump bhejte hain.
        // `since` watermark server_time (IST) se banta hai, jabki MySQL updated_at UTC me
        // hota hai (container SYSTEM tz). String comparison "updated_at > since" isliye
        // web-created rows ko 5.5h skew se permanently skip kar deta tha — desktop par
        // customer list khali reh jati thi. Dono lookup tables chhoti hain, full dump cheap
        // hai aur ye class of bugs (stale watermark) eliminate ho jati hai.
        $items      = $this->pullItems($sinceTs);
        $customers  = $this->pullCustomers(null);   // always full dump — see note above
        $suppliers  = $this->pullSuppliers(null);   // always full dump — see note above
        $sales      = $this->pullSales($sinceTs);
        $saleReturns = $this->pullSaleReturns($sinceTs);
        $holds      = $this->pullHolds($sinceTs);
        $holdDetails = $this->pullSimpleTable('hold_details', $sinceTs);
        $purchases  = $this->pullPurchases($sinceTs);
        $purchaseReturns = $this->pullPurchaseReturns($sinceTs);
        $supplierPayments = $this->pullSupplierPayments($sinceTs);
        $expenses   = $this->pullExpenses($sinceTs);

        // Previously missing — all these are now pulled incrementally
        $promotions         = $this->pullSimpleTable('promotions', $sinceTs);
        $servicings         = $this->pullSimpleTable('servicings', $sinceTs);
        $warranties         = $this->pullSimpleTable('warranties', $sinceTs);
        $bookings           = $this->pullSimpleTable('bookings', $sinceTs);
        $customerReceives   = $this->pullSimpleTable('customer_receives', $sinceTs);
        $incomes            = $this->pullIncomes($sinceTs);
        $depositWithdraws   = $this->pullSimpleTable('deposit_withdraws', $sinceTs);
        $installmentSales   = $this->pullSimpleTable('installment_sales', $sinceTs);
        $installmentDetails = $this->pullSimpleTable('installment_sale_details', $sinceTs);
        $damages            = $this->pullSimpleTable('damages', $sinceTs);
        $damageDetails      = $this->pullSimpleTable('damage_details', $sinceTs);
        $transfers          = $this->pullSimpleTable('transfers', $sinceTs);
        $transferDetails    = $this->pullSimpleTable('transfer_details', $sinceTs);
        $quotations         = $this->pullSimpleTable('quotations', $sinceTs);
        $quotationDetails   = $this->pullSimpleTable('quotation_details', $sinceTs);
        $attendances        = $this->pullSimpleTable('attendances', $sinceTs);
        $salaries           = $this->pullSimpleTable('salaries', $sinceTs);
        $salaryItems        = $this->pullSimpleTable('salary_items', $sinceTs);
        $advancePayments    = $this->pullSimpleTable('employee_advance_payments', $sinceTs);
        $walletTxns         = $this->pullSimpleTable('wallet_transactions', $sinceTs);
        $installedModules   = $this->pullSimpleTable('installed_modules', $sinceTs);
        $saleDetails        = $this->pullSimpleTable('sale_details', $sinceTs);
        $purchaseDetails    = $this->pullSimpleTable('purchase_details', $sinceTs);
        $salePayments       = $this->pullSimpleTable('sale_payments', $sinceTs);
        $purchasePayments   = $this->pullSimpleTable('purchase_payments', $sinceTs);
        $registers          = $this->pullSimpleTable('registers', $sinceTs);
        $setOpeningStocks   = $this->pullSimpleTable('set_opening_stocks', $sinceTs);
        $stockDetail        = $this->pullSimpleTable('view_stock_detail', null); // always full (computed view)

        return response()->json([
            'server_time'               => now()->utc()->toDateTimeString(),
            'items'                     => $items,
            'customers'                 => $customers,
            'suppliers'                 => $suppliers,
            'sales'                     => $sales,
            'sale_returns'              => $saleReturns,
            'holds'                     => $holds,
            'purchases'                 => $purchases,
            'purchase_returns'          => $purchaseReturns,
            'supplier_payments'         => $supplierPayments,
            'expenses'                  => $expenses,
            // Newly added
            'promotions'                => $promotions,
            'servicings'                => $servicings,
            'warranties'                => $warranties,
            'bookings'                  => $bookings,
            'customer_receives'         => $customerReceives,
            'incomes'                   => $incomes,
            'deposit_withdraws'         => $depositWithdraws,
            'installment_sales'         => $installmentSales,
            'installment_sale_details'  => $installmentDetails,
            'damages'                   => $damages,
            'damage_details'            => $damageDetails,
            'transfers'                 => $transfers,
            'transfer_details'          => $transferDetails,
            'quotations'                => $quotations,
            'quotation_details'         => $quotationDetails,
            'attendances'               => $attendances,
            'salaries'                  => $salaries,
            'salary_items'              => $salaryItems,
            'employee_advance_payments' => $advancePayments,
            'wallet_transactions'       => $walletTxns,
            'installed_modules'         => $installedModules,
            'sale_details'              => $saleDetails,
            'purchase_details'          => $purchaseDetails,
            'sale_payments'             => $salePayments,
            'purchase_payments'         => $purchasePayments,
            'registers'                 => $registers,
            'set_opening_stocks'        => $setOpeningStocks,
            'view_stock_detail'         => $stockDetail,
            'master'                    => $this->pullMaster(),
        ]);
    }

    /**
     * Generic pull for simple tables — filters by company_id,
     * optionally by updated_at > $since. Returns plain array of all columns.
     *
     * NOTE (delete parity): jo tables `del_status` rakhti hain unki DELETED rows
     * bhi bhejte hain (tombstone) — desktop mirror unhe Deleted likh leta hai
     * aur list pages (jo khud Deleted filter karte hain) hide kar deti hain.
     * Pehle `del_status='Live'` filter tha → cloud delete desktop tak kabhi
     * nahi pahunchta tha (stale Live rows hamesha dikhti rehti thin).
     */
    private function pullSimpleTable(string $table, ?string $since): array
    {
        try {
            $schema = DB::getSchemaBuilder();
            $hasCompany  = $schema->hasColumn($table, 'company_id');
            $hasUpdatedAt = $schema->hasColumn($table, 'updated_at');

            $q = DB::table($table);
            if ($hasCompany)  $q->where('company_id', $this->companyId);
            if ($since && $hasUpdatedAt) $q->where('updated_at', '>', $since);

            return $q->get()->map(fn ($row) => (array) $row)->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Incomes are pulled WITHOUT the del_status='Live' filter so that
     * deleted rows (tombstones) reach the desktop app for two-way sync.
     */
    private function pullIncomes(?string $since): array
    {
        try {
            $q = DB::table('incomes')
                ->where('company_id', $this->companyId)
                ->select('id', 'reference_no', 'date', 'category_id', 'payment_method_id',
                         'amount', 'note', 'attachment', 'employee_id', 'user_id',
                         'outlet_id', 'del_status', 'created_at', 'updated_at');
            if ($since) {
                $q->where('updated_at', '>', $since);
            }

            return $q->get()->map(function ($i) {
                return [
                    'id' => (int) $i->id,
                    'reference_no' => $i->reference_no ?? '',
                    'date' => $i->date ?? '',
                    'category_id' => $i->category_id !== null ? (int) $i->category_id : null,
                    'payment_method_id' => $i->payment_method_id !== null ? (int) $i->payment_method_id : null,
                    'amount' => (float) $i->amount,
                    'note' => $i->note ?? '',
                    'attachment' => $i->attachment ?? '',
                    'employee_id' => $i->employee_id !== null ? (int) $i->employee_id : null,
                    'user_id' => $i->user_id !== null ? (int) $i->user_id : null,
                    'outlet_id' => $i->outlet_id !== null ? (int) $i->outlet_id : null,
                    'del_status' => $i->del_status ?? 'Live',
                    'created_at' => $i->created_at ?? '',
                    'updated_at' => $i->updated_at ?? now()->toDateTimeString(),
                ];
            })->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Full dump of the small master/config tables so the desktop app can keep
     * its SQLite mirror in sync. Rows are small (a few dozen each), so a full
     * dump per sync is cheap.
     */
    private function pullMaster(): array
    {
        $tables = [
            'companies',
            'outlets', 'states', 'denominations', 'multiple_currencies', 'printers',
            'counters', 'delivery_partners', 'income_categories', 'expense_categories',
            'item_categories', 'brands', 'units', 'racks', 'variations',
            'payment_methods', 'roles', 'users', 'business_club_settings',
            'customer_wallets', 'price_lists', 'suppliers', 'taxs',
            'permissions', 'role_has_permissions', 'model_has_roles',
            // Previously missing tables:
            'promotions', 'price_list_items', 'fixed_asset_items',
            'fixed_asset_stock_ins', 'fixed_asset_stock_outs',
            'fixed_asset_stock_in_details', 'fixed_asset_stock_out_details',
        ];
        $result = [];
        foreach ($tables as $t) {
            try {
                // NOTE: del_status ka filter yahan NAHI lagate — Deleted rows bhi
                // bhejte hain taaki desktop mirror me tombstone propagate ho aur
                // server par delete ki gayi categories/brands software se bhi hat
                // jayein. Desktop unhe del_status='Deleted' se mark karta hai.
                $result[$t] = DB::table($t)
                    // companies: single row per company — always the token's company id.
                    ->when($t === 'companies', function ($q) {
                        $q->where('id', $this->companyId);
                    })
                    ->when($t !== 'companies' && DB::getSchemaBuilder()->hasColumn($t, 'company_id'), function ($q) {
                        $q->where('company_id', $this->companyId);
                    })
                    ->get()->toArray();
            } catch (\Throwable $e) {
                $result[$t] = [];
            }
        }
        return $result;
    }

    /**
     * POST /api/sync/push
     * Payload: { items: [], customers: [], sales: [] }
     * Returns server ids mapped to local identifiers.
     */
    public function push(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'nullable|array',
            'customers' => 'nullable|array',
            'suppliers' => 'nullable|array',
            'sales' => 'nullable|array',
            'configs' => 'nullable|array',
            'purchases' => 'nullable|array',
            'purchase_returns' => 'nullable|array',
            'supplier_payments' => 'nullable|array',
            'expenses' => 'nullable|array',
            'sale_returns' => 'nullable|array',
            'wallet_transactions' => 'nullable|array',
            'loyalty_changes' => 'nullable|array',
            'salaries' => 'nullable|array',
            'attendances' => 'nullable|array',
            'promotions' => 'nullable|array',
            'bookings' => 'nullable|array',
            'servicings' => 'nullable|array',
            'warranties' => 'nullable|array',
            'deposit_withdraws' => 'nullable|array',
            'installment_sales' => 'nullable|array',
            'quotations' => 'nullable|array',
            'incomes' => 'nullable|array',
            'customer_receives' => 'nullable|array',
            'outlets' => 'nullable|array',
            'damages' => 'nullable|array',
            'transfers' => 'nullable|array',
        ]);

        $payload = $request->all();

        // ═══ FIX 1: IDEMPOTENCY — network timeout ke baad desktop same payload
        // dobara bhejta hai; agar server ne pehle hi process kar liya aur response
        // lost ho gaya to is key se wahi response wapas diya jata hai (duplicate
        // insert nahi hoga). Key = SHA256(payload), device-scoped, 24h expiry. ═══
        $idempotencyKey = (string) $request->header('X-Idempotency-Key', '');
        if ($idempotencyKey !== '') {
            $cached = DB::table('idempotency_keys')
                ->where('key', $idempotencyKey)
                ->where('device_id', $this->deviceId)
                ->where('expires_at', '>', now())
                ->first();
            if ($cached && $cached->response_snapshot !== null) {
                return response()->json(json_decode($cached->response_snapshot, true));
            }
        }

        // ═══ ENTERPRISE: Wrap all push operations in a transaction ═══
        $results = DB::transaction(function () use ($payload) {
            $itemResults = [];
            foreach ($payload['items'] ?? [] as $item) {
                $itemResults[] = $this->pushItem($item);
            }

            $customerResults = [];
            foreach ($payload['customers'] ?? [] as $customer) {
                $customerResults[] = $this->pushCustomer($customer);
            }

            $supplierResults = [];
            foreach ($payload['suppliers'] ?? [] as $supplier) {
                $supplierResults[] = $this->pushSupplier($supplier);
            }

            $saleResults = [];
            foreach ($payload['sales'] ?? [] as $sale) {
                $saleResults[] = $this->pushSale($sale);
            }

            $configResults = $this->pushConfigs($payload['configs'] ?? []);

            $purchaseResults = [];
            foreach ($payload['purchases'] ?? [] as $purchase) {
                $purchaseResults[] = $this->pushPurchase($purchase);
            }

            $purchaseReturnResults = [];
            foreach ($payload['purchase_returns'] ?? [] as $return) {
                $purchaseReturnResults[] = $this->pushPurchaseReturn($return);
            }

            $supplierPaymentResults = [];
            foreach ($payload['supplier_payments'] ?? [] as $payment) {
                $supplierPaymentResults[] = $this->pushSupplierPayment($payment);
            }

            $expenseResults = [];
            foreach ($payload['expenses'] ?? [] as $expense) {
                $expenseResults[] = $this->pushExpense($expense);
            }

            $saleReturnResults = [];
            foreach ($payload['sale_returns'] ?? [] as $saleReturn) {
                $saleReturnResults[] = $this->pushSaleReturn($saleReturn);
            }

            // 11 new entities
            $salaryResults = [];
            foreach ($payload['salaries'] ?? [] as $salary) {
                $salaryResults[] = $this->pushSalary($salary);
            }

            $attendanceResults = [];
            foreach ($payload['attendances'] ?? [] as $attendance) {
                $attendanceResults[] = $this->pushAttendance($attendance);
            }

            $promotionResults = [];
            foreach ($payload['promotions'] ?? [] as $promotion) {
                $promotionResults[] = $this->pushPromotion($promotion);
            }

            $bookingResults = [];
            foreach ($payload['bookings'] ?? [] as $booking) {
                $bookingResults[] = $this->pushBooking($booking);
            }

            $servicingResults = [];
            foreach ($payload['servicings'] ?? [] as $servicing) {
                $servicingResults[] = $this->pushServicing($servicing);
            }

            $warrantyResults = [];
            foreach ($payload['warranties'] ?? [] as $warranty) {
                $warrantyResults[] = $this->pushWarranty($warranty);
            }

            $depositWithdrawResults = [];
            foreach ($payload['deposit_withdraws'] ?? [] as $dw) {
                $depositWithdrawResults[] = $this->pushDepositWithdraw($dw);
            }

            $installmentSaleResults = [];
            foreach ($payload['installment_sales'] ?? [] as $isale) {
                $installmentSaleResults[] = $this->pushInstallmentSale($isale);
            }

            $quotationResults = [];
            foreach ($payload['quotations'] ?? [] as $quotation) {
                $quotationResults[] = $this->pushQuotation($quotation);
            }

            $incomeResults = [];
            foreach ($payload['incomes'] ?? [] as $income) {
                $incomeResults[] = $this->pushIncome($income);
            }

            $customerReceiveResults = [];
            foreach ($payload['customer_receives'] ?? [] as $cr) {
                $customerReceiveResults[] = $this->pushCustomerReceive($cr);
            }

            $outletResults = [];
            foreach ($payload['outlets'] ?? [] as $outlet) {
                $outletResults[] = $this->pushOutlet($outlet);
            }

            $damageResults = [];
            foreach ($payload['damages'] ?? [] as $damage) {
                $damageResults[] = $this->pushDamage($damage);
            }

            $transferResults = [];
            foreach ($payload['transfers'] ?? [] as $transfer) {
                $transferResults[] = $this->pushTransfer($transfer);
            }

            return compact('itemResults', 'customerResults', 'supplierResults',
                'saleResults', 'configResults', 'purchaseResults',
                'purchaseReturnResults', 'supplierPaymentResults',
                'expenseResults', 'saleReturnResults',
                'salaryResults', 'attendanceResults', 'promotionResults',
                'bookingResults', 'servicingResults', 'warrantyResults',
                'depositWithdrawResults', 'installmentSaleResults',
                'quotationResults', 'incomeResults', 'customerReceiveResults',
                'outletResults', 'damageResults', 'transferResults');
        });

        // Wallet transactions — outside main transaction
        $walletTransactionResults = [];
        foreach ($payload['wallet_transactions'] ?? [] as $walletTxn) {
            $walletTransactionResults[] = $this->pushWalletTransaction($walletTxn);
        }
        $results['walletTransactionResults'] = $walletTransactionResults;

        $loyaltyChangeResults = [];
        foreach ($payload['loyalty_changes'] ?? [] as $loyaltyChange) {
            $loyaltyChangeResults[] = $this->pushLoyaltyChange($loyaltyChange);
        }
        $results['loyaltyChangeResults'] = $loyaltyChangeResults;

        // Stock reconciliation
        foreach ($payload['items'] ?? [] as $item) {
            $this->reconcileManualStock($item['code'] ?? null);
        }
        $this->refreshStockView();

        $this->businessClubFallbackCredit(
            $results['saleResults'],
            $payload['wallet_transactions'] ?? [],
            $payload['sales'] ?? []
        );

        $responseData = [
            'items' => $results['itemResults'],
            'customers' => $results['customerResults'],
            'suppliers' => $results['supplierResults'],
            'sales' => $results['saleResults'],
            'configs' => $results['configResults'],
            'purchases' => $results['purchaseResults'],
            'purchase_returns' => $results['purchaseReturnResults'],
            'supplier_payments' => $results['supplierPaymentResults'],
            'expenses' => $results['expenseResults'],
            'sale_returns' => $results['saleReturnResults'],
            'wallet_transactions' => $results['walletTransactionResults'],
            'loyalty_changes' => $results['loyaltyChangeResults'],
            'salaries' => $results['salaryResults'],
            'attendances' => $results['attendanceResults'],
            'promotions' => $results['promotionResults'],
            'bookings' => $results['bookingResults'],
            'servicings' => $results['servicingResults'],
            'warranties' => $results['warrantyResults'],
            'deposit_withdraws' => $results['depositWithdrawResults'],
            'installment_sales' => $results['installmentSaleResults'],
            'quotations' => $results['quotationResults'],
            'incomes' => $results['incomeResults'],
            'customer_receives' => $results['customerReceiveResults'],
            'outlets' => $results['outletResults'],
            'damages' => $results['damageResults'],
            'transfers' => $results['transferResults'],
            'server_time' => now()->toDateTimeString(),
        ];

        // Response cache karo (FIX 1) — 24 ghante tak same key = same response
        if ($idempotencyKey !== '') {
            DB::table('idempotency_keys')->updateOrInsert(
                ['key' => $idempotencyKey, 'device_id' => $this->deviceId],
                [
                    'response_snapshot' => json_encode($responseData),
                    'expires_at' => now()->addHours(24),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }

        return response()->json($responseData);
    }

    /**
     * Push a wallet transaction from the desktop POS.
     * Inserts/updates the transaction and adjusts the customer_wallets balance.
     * For Business Club credit transactions, deduplicates by sale_id + customer_id + type
     * to prevent double-crediting when both desktop and server create the same credit.
     */
    private function pushWalletTransaction(array $txn): array
    {
        $now = now()->toDateTimeString();
        $localId = (int) ($txn['local_id'] ?? 0);

        try {
            // Idempotency: check if already pushed via mapping
            if ($localId > 0) {
                $mapped = $this->findMapping('wallet_transactions', $localId);
                if ($mapped && DB::table('wallet_transactions')->where('id', $mapped->server_id)->exists()) {
                    return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id];
                }
            }

            $customerId = (int) ($txn['customer_id'] ?? 0);
            $walletId = (int) ($txn['wallet_id'] ?? 0);
            $type = (string) ($txn['type'] ?? 'redeem');
            $amount = (float) ($txn['amount'] ?? 0);
            $balanceBefore = (float) ($txn['balance_before'] ?? 0);
            $balanceAfter = (float) ($txn['balance_after'] ?? 0);
            $description = (string) ($txn['description'] ?? '');
            $transactionDate = $txn['transaction_date'] ?? date('Y-m-d');
            $saleId = ! empty($txn['sale_id']) ? (int) $txn['sale_id'] : null;

            if ($customerId <= 0 || $amount <= 0) {
                return ['local_id' => $localId, 'server_id' => null, 'error' => 'Invalid customer or amount'];
            }

            // ═══ BUSINESS CLUB DEDUP: If a credit transaction for the same
            // sale_id + customer_id + type already exists on the server (created
            // by creditWalletFromSale fallback or a previous push), skip insert
            // to prevent double-crediting. ═══
            if ($saleId && $type === 'credit') {
                $existing = DB::table('wallet_transactions')
                    ->where('sale_id', $saleId)
                    ->where('customer_id', $customerId)
                    ->where('type', 'credit')
                    ->where('company_id', $this->companyId)
                    ->where('del_status', 'Live')
                    ->first();
                if ($existing) {
                    // Already credited — map and return existing server id
                    if ($localId > 0) {
                        $this->mapLocal('wallet_transactions', $localId, '', (int) $existing->id);
                    }
                    return ['local_id' => $localId, 'server_id' => (int) $existing->id, 'skipped_duplicate' => true];
                }
            }

            // ALWAYS resolve wallet_id from server DB (desktop sends local id which doesn't exist here)
            $wallet = DB::table('customer_wallets')
                ->where('customer_id', $customerId)
                ->where('company_id', $this->companyId)
                ->first();
            if ($wallet) {
                $walletId = (int) $wallet->id;
            } else {
                // Create wallet for this customer if it doesn't exist
                $walletId = (int) DB::table('customer_wallets')->insertGetId([
                    'customer_id' => $customerId,
                    'balance' => 0,
                    'total_earned' => 0,
                    'total_redeemed' => 0,
                    'company_id' => $this->companyId,
                    'del_status' => 'Live',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Insert the wallet transaction record
            $serverId = DB::table('wallet_transactions')->insertGetId([
                'wallet_id' => $walletId > 0 ? $walletId : null,
                'customer_id' => $customerId,
                'sale_id' => $saleId,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'transaction_date' => $transactionDate,
                'company_id' => $this->companyId,
                'del_status' => 'Live',
                'created_at' => $txn['created_at'] ?? $now,
                'updated_at' => $now,
            ]);

            // Update customer_wallets balance accordingly
            if ($walletId > 0) {
                if ($type === 'redeem' || $type === 'debit') {
                    DB::table('customer_wallets')
                        ->where('id', $walletId)
                        ->where('company_id', $this->companyId)
                        ->update([
                            'balance' => DB::raw("balance - {$amount}"),
                            'total_redeemed' => DB::raw("total_redeemed + {$amount}"),
                            'updated_at' => $now,
                        ]);
                } elseif ($type === 'credit' || $type === 'topup') {
                    DB::table('customer_wallets')
                        ->where('id', $walletId)
                        ->where('company_id', $this->companyId)
                        ->update([
                            'balance' => DB::raw("balance + {$amount}"),
                            'total_earned' => DB::raw("total_earned + {$amount}"),
                            'updated_at' => $now,
                        ]);
                }
            }

            if ($localId > 0) {
                $this->mapLocal('wallet_transactions', $localId, '', (int) $serverId);
            }

            return ['local_id' => $localId, 'server_id' => (int) $serverId];
        } catch (\Throwable $e) {
            \Log::error('pushWalletTransaction FAILED', ['local_id' => $localId, 'error' => $e->getMessage()]);
            return ['local_id' => $localId, 'server_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Push a customer loyalty point change from the desktop POS.
     * Directly updates the customer's loyalty_point on the server.
     */
    private function pushLoyaltyChange(array $change): array
    {
        $customerId = (int) ($change['customer_id'] ?? 0);
        $loyaltyPoint = (int) ($change['loyalty_point'] ?? 0);

        if ($customerId <= 0) {
            return ['customer_id' => $customerId, 'error' => 'Invalid customer'];
        }

        try {
            $customer = DB::table('customers')
                ->where('id', $customerId)
                ->where('company_id', $this->companyId)
                ->first();

            if (! $customer) {
                return ['customer_id' => $customerId, 'error' => 'Customer not found'];
            }

            DB::table('customers')
                ->where('id', $customerId)
                ->where('company_id', $this->companyId)
                ->update([
                    'loyalty_point' => $loyaltyPoint,
                    'updated_at' => now()->toDateTimeString(),
                ]);

            return ['customer_id' => $customerId, 'server_id' => $customerId, 'synced' => true];
        } catch (\Throwable $e) {
            return ['customer_id' => $customerId, 'error' => $e->getMessage()];
        }
    }

    // ═══════════ 11 NEW ENTITY PUSH METHODS ═══════════

    private function pushSalary(array $salary): array
    {
        $localId = $salary['local_id'] ?? null;
        $now = now()->toDateTimeString();
        try {
            if (!empty($localId)) {
                $mapped = $this->findMapping('salaries', $localId);
                if ($mapped && DB::table('salaries')->where('id', $mapped->server_id)->exists())
                    return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id];
            }
            $serverId = DB::table('salaries')->insertGetId([
                'reference_no' => $salary['reference_no'] ?? null,
                'year' => $salary['year'] ?? null,
                'month' => $salary['month'] ?? null,
                'generated_date' => $salary['generated_date'] ?? null,
                'total_amount' => $salary['total_amount'] ?? 0,
                'user_id' => $salary['user_id'] ?? null,
                'company_id' => $this->companyId,
                'outlet_id' => $this->outletId,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!empty($localId)) $this->mapLocal('salaries', $localId, '', (int) $serverId);
            return ['local_id' => $localId, 'server_id' => (int) $serverId];
        } catch (\Throwable $e) {
            return ['local_id' => $localId, 'error' => $e->getMessage()];
        }
    }

    private function pushAttendance(array $att): array
    {
        $localId = $att['local_id'] ?? null;
        $now = now()->toDateTimeString();
        try {
            if (!empty($localId)) {
                $mapped = $this->findMapping('attendances', $localId);
                if ($mapped && DB::table('attendances')->where('id', $mapped->server_id)->exists())
                    return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id];
            }
            $serverId = DB::table('attendances')->insertGetId([
                'reference_no' => $att['reference_no'] ?? null,
                'date' => $att['date'] ?? null,
                'employee_id' => $att['employee_id'] ?? null,
                'in_time' => $att['in_time'] ?? null,
                'out_time' => $att['out_time'] ?? null,
                'note' => $att['note'] ?? null,
                'user_id' => $att['user_id'] ?? null,
                'company_id' => $this->companyId,
                'outlet_id' => $this->outletId,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!empty($localId)) $this->mapLocal('attendances', $localId, '', (int) $serverId);
            return ['local_id' => $localId, 'server_id' => (int) $serverId];
        } catch (\Throwable $e) {
            return ['local_id' => $localId, 'error' => $e->getMessage()];
        }
    }

    private function pushPromotion(array $p): array
    {
        $localId = $p['local_id'] ?? null;
        $now = now()->toDateTimeString();
        try {
            if (!empty($localId)) {
                $mapped = $this->findMapping('promotions', $localId);
                if ($mapped && DB::table('promotions')->where('id', $mapped->server_id)->exists()) {
                    $existingRow = DB::table('promotions')->where('id', $mapped->server_id)->first();
                    // ═══ TWO-WAY DELETE (E2) ═══
                    if (($p['del_status'] ?? 'Live') === 'Deleted') {
                        DB::table('promotions')->where('id', $mapped->server_id)->update([
                            'del_status' => 'Deleted',
                            'updated_at' => $now,
                        ]);
                        return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id, 'deleted' => true];
                    }
                    // ═══ LAST-WRITE-WINS (E1) ═══
                    if (! $this->lwwApply($p, $existingRow, 'promotions', (string) ($p['name'] ?? $p['title'] ?? $localId), (int) $mapped->server_id)) {
                        return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id, 'conflict' => 'skipped_older'];
                    }
                    DB::table('promotions')->where('id', $mapped->server_id)->update([
                        'title' => $p['title'] ?? null,
                        'type' => $p['type'] ?? null,
                        'scheme_basis' => $p['scheme_basis'] ?? null,
                        'item_id' => $p['item_id'] ?? null,
                        'qty' => $p['qty'] ?? null,
                        'get_item_id' => $p['get_item_id'] ?? null,
                        'get_qty' => $p['get_qty'] ?? null,
                        'discount' => $p['discount'] ?? null,
                        'coupon_code' => $p['coupon_code'] ?? null,
                        'tier_percentages' => $p['tier_percentages'] ?? null,
                        'flavour_alternatives' => $p['flavour_alternatives'] ?? null,
                        'applicable_items' => $p['applicable_items'] ?? null,
                        'applicable_categories' => $p['applicable_categories'] ?? null,
                        'applicable_customers' => $p['applicable_customers'] ?? null,
                        'applicable_customer_types' => $p['applicable_customer_types'] ?? null,
                        'min_purchase_amount' => $p['min_purchase_amount'] ?? 0,
                        'max_discount_amount' => $p['max_discount_amount'] ?? 0,
                        'bill_level_discount' => $p['bill_level_discount'] ?? 0,
                        'bill_level_discount_type' => $p['bill_level_discount_type'] ?? null,
                        'start_date' => $p['start_date'] ?? null,
                        'end_date' => $p['end_date'] ?? null,
                        'start_time' => $p['start_time'] ?? null,
                        'end_time' => $p['end_time'] ?? null,
                        'status' => $p['status'] ?? null,
                        'del_status' => $p['del_status'] ?? 'Live',
                        'updated_at' => $now,
                    ]);
                    return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id];
                }
            }
            $serverId = DB::table('promotions')->insertGetId([
                'name' => $p['name'] ?? $p['title'] ?? null,
                'title' => $p['title'] ?? null,
                'type' => $p['type'] ?? null,
                'scheme_basis' => $p['scheme_basis'] ?? 'item',
                'discount_type' => $p['discount_type'] ?? null,
                'discount_value' => $p['discount_value'] ?? 0,
                'start_date' => $p['start_date'] ?? null,
                'end_date' => $p['end_date'] ?? null,
                'start_time' => $p['start_time'] ?? null,
                'end_time' => $p['end_time'] ?? null,
                'status' => $p['status'] ?? 'Active',
                'item_id' => $p['item_id'] ?? null,
                'qty' => $p['qty'] ?? null,
                'get_item_id' => $p['get_item_id'] ?? null,
                'get_qty' => $p['get_qty'] ?? null,
                'applicable_items' => $p['applicable_items'] ?? null,
                'applicable_categories' => $p['applicable_categories'] ?? null,
                'applicable_customers' => $p['applicable_customers'] ?? null,
                'applicable_customer_types' => $p['applicable_customer_types'] ?? null,
                'min_purchase_amount' => $p['min_purchase_amount'] ?? 0,
                'max_discount_amount' => $p['max_discount_amount'] ?? 0,
                'bill_level_discount' => $p['bill_level_discount'] ?? 0,
                'bill_level_discount_type' => $p['bill_level_discount_type'] ?? null,
                'coupon_code' => $p['coupon_code'] ?? null,
                'tier_percentages' => $p['tier_percentages'] ?? null,
                'flavour_alternatives' => $p['flavour_alternatives'] ?? null,
                'user_id' => $p['user_id'] ?? null,
                'company_id' => $this->companyId,
                'outlet_id' => $this->outletId,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!empty($localId)) $this->mapLocal('promotions', $localId, '', (int) $serverId);
            return ['local_id' => $localId, 'server_id' => (int) $serverId];
        } catch (\Throwable $e) {
            return ['local_id' => $localId, 'error' => $e->getMessage()];
        }
    }

    private function pushBooking(array $b): array
    {
        $localId = $b['local_id'] ?? null;
        $now = now()->toDateTimeString();
        try {
            if (!empty($localId)) {
                $mapped = $this->findMapping('bookings', $localId);
                if ($mapped && DB::table('bookings')->where('id', $mapped->server_id)->exists())
                    return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id];
            }
            $serverId = DB::table('bookings')->insertGetId([
                'customer_id' => $b['customer_id'] ?? null,
                'service_seller_id' => $b['service_seller_id'] ?? null,
                'start_date' => $b['start_date'] ?? null,
                'end_date' => $b['end_date'] ?? null,
                'status' => $b['status'] ?? null,
                'note' => $b['note'] ?? null,
                'user_id' => $b['user_id'] ?? null,
                'outlet_id' => $b['outlet_id'] ?? $this->outletId,
                'company_id' => $this->companyId,
                'item_id' => $b['item_id'] ?? null,
                'service_note' => $b['service_note'] ?? null,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!empty($localId)) $this->mapLocal('bookings', $localId, '', (int) $serverId);
            return ['local_id' => $localId, 'server_id' => (int) $serverId];
        } catch (\Throwable $e) {
            return ['local_id' => $localId, 'error' => $e->getMessage()];
        }
    }

    private function pushServicing(array $s): array
    {
        $localId = $s['local_id'] ?? null;
        $now = now()->toDateTimeString();
        try {
            if (!empty($localId)) {
                $mapped = $this->findMapping('servicings', $localId);
                if ($mapped && DB::table('servicings')->where('id', $mapped->server_id)->exists())
                    return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id];
            }
            $serverId = DB::table('servicings')->insertGetId([
                'reference_no' => $s['reference_no'] ?? null,
                'customer_id' => $s['customer_id'] ?? null,
                'employee_id' => $s['employee_id'] ?? null,
                'date' => $s['date'] ?? null,
                'receiving_date' => $s['receiving_date'] ?? null,
                'delivery_date' => $s['delivery_date'] ?? null,
                'servicing_charge' => $s['servicing_charge'] ?? 0,
                'paid_amount' => $s['paid_amount'] ?? 0,
                'due_amount' => $s['due_amount'] ?? 0,
                'payment_method_id' => $s['payment_method_id'] ?? null,
                'description' => $s['description'] ?? null,
                'note' => $s['note'] ?? null,
                'user_id' => $s['user_id'] ?? null,
                'outlet_id' => $s['outlet_id'] ?? $this->outletId,
                'company_id' => $this->companyId,
                'current_status' => $s['current_status'] ?? null,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!empty($localId)) $this->mapLocal('servicings', $localId, '', (int) $serverId);
            return ['local_id' => $localId, 'server_id' => (int) $serverId];
        } catch (\Throwable $e) {
            return ['local_id' => $localId, 'error' => $e->getMessage()];
        }
    }

    private function pushWarranty(array $w): array
    {
        $localId = $w['local_id'] ?? null;
        $now = now()->toDateTimeString();
        try {
            if (!empty($localId)) {
                $mapped = $this->findMapping('warranties', $localId);
                if ($mapped && DB::table('warranties')->where('id', $mapped->server_id)->exists())
                    return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id];
            }
            $serverId = DB::table('warranties')->insertGetId([
                'reference_no' => $w['reference_no'] ?? null,
                'customer_id' => $w['customer_id'] ?? null,
                'technician_id' => $w['technician_id'] ?? null,
                'receiving_date' => $w['receiving_date'] ?? null,
                'delivery_date' => $w['delivery_date'] ?? null,
                'current_status' => $w['current_status'] ?? null,
                'description' => $w['description'] ?? null,
                'note' => $w['note'] ?? null,
                'user_id' => $w['user_id'] ?? null,
                'outlet_id' => $w['outlet_id'] ?? $this->outletId,
                'company_id' => $this->companyId,
                'item_name' => $w['item_name'] ?? null,
                'item_model' => $w['item_model'] ?? null,
                'serial_no' => $w['serial_no'] ?? null,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!empty($localId)) $this->mapLocal('warranties', $localId, '', (int) $serverId);
            return ['local_id' => $localId, 'server_id' => (int) $serverId];
        } catch (\Throwable $e) {
            return ['local_id' => $localId, 'error' => $e->getMessage()];
        }
    }

    private function pushDepositWithdraw(array $dw): array
    {
        $localId = $dw['local_id'] ?? null;
        $now = now()->toDateTimeString();
        try {
            if (!empty($localId)) {
                $mapped = $this->findMapping('deposit_withdraws', $localId);
                if ($mapped && DB::table('deposit_withdraws')->where('id', $mapped->server_id)->exists())
                    return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id];
            }
            $serverId = DB::table('deposit_withdraws')->insertGetId([
                'reference_no' => $dw['reference_no'] ?? null,
                'date' => $dw['date'] ?? null,
                'type' => $dw['type'] ?? null,
                'payment_method_id' => $dw['payment_method_id'] ?? null,
                'amount' => $dw['amount'] ?? 0,
                'note' => $dw['note'] ?? null,
                'user_id' => $dw['user_id'] ?? null,
                'outlet_id' => $dw['outlet_id'] ?? $this->outletId,
                'company_id' => $this->companyId,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!empty($localId)) $this->mapLocal('deposit_withdraws', $localId, '', (int) $serverId);
            return ['local_id' => $localId, 'server_id' => (int) $serverId];
        } catch (\Throwable $e) {
            return ['local_id' => $localId, 'error' => $e->getMessage()];
        }
    }

    private function pushInstallmentSale(array $is): array
    {
        $localId = $is['local_id'] ?? null;
        $now = now()->toDateTimeString();
        try {
            if (!empty($localId)) {
                $mapped = $this->findMapping('installment_sales', $localId);
                if ($mapped && DB::table('installment_sales')->where('id', $mapped->server_id)->exists())
                    return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id];
            }
            $serverId = DB::table('installment_sales')->insertGetId([
                'reference_no' => $is['reference_no'] ?? null,
                'customer_id' => $is['customer_id'] ?? null,
                'item_id' => $is['item_id'] ?? null,
                'date' => $is['date'] ?? null,
                'price' => $is['price'] ?? 0,
                'discount_amount' => $is['discount_amount'] ?? 0,
                'percentage_of_interest' => $is['percentage_of_interest'] ?? 0,
                'interest_amount' => $is['interest_amount'] ?? 0,
                'shipping_other' => $is['shipping_other'] ?? 0,
                'total' => $is['total'] ?? 0,
                'down_payment' => $is['down_payment'] ?? 0,
                'payment_method_id' => $is['payment_method_id'] ?? null,
                'remaining' => $is['remaining'] ?? 0,
                'paid_amount' => $is['paid_amount'] ?? 0,
                'due_amount' => $is['due_amount'] ?? 0,
                'status' => $is['status'] ?? null,
                'installment_count' => $is['installment_count'] ?? null,
                'note' => $is['note'] ?? null,
                'user_id' => $is['user_id'] ?? null,
                'outlet_id' => $is['outlet_id'] ?? $this->outletId,
                'company_id' => $this->companyId,
                'discount' => $is['discount'] ?? null,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!empty($localId)) $this->mapLocal('installment_sales', $localId, '', (int) $serverId);
            return ['local_id' => $localId, 'server_id' => (int) $serverId];
        } catch (\Throwable $e) {
            return ['local_id' => $localId, 'error' => $e->getMessage()];
        }
    }

    private function pushQuotation(array $q): array
    {
        $localId = $q['local_id'] ?? null;
        $now = now()->toDateTimeString();
        try {
            if (!empty($localId)) {
                $mapped = $this->findMapping('quotations', $localId);
                if ($mapped && DB::table('quotations')->where('id', $mapped->server_id)->exists())
                    return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id];
            }
            $serverId = DB::table('quotations')->insertGetId([
                'reference_no' => $q['reference_no'] ?? null,
                'customer_id' => $q['customer_id'] ?? null,
                'date' => $q['date'] ?? null,
                'grand_total' => $q['grand_total'] ?? 0,
                'note' => $q['note'] ?? null,
                'discount' => $q['discount'] ?? null,
                'user_id' => $q['user_id'] ?? null,
                'outlet_id' => $q['outlet_id'] ?? $this->outletId,
                'company_id' => $this->companyId,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            // Push quotation_details
            foreach ($q['items'] ?? [] as $item) {
                DB::table('quotation_details')->insert([
                    'quotation_id' => $serverId,
                    'item_id' => $item['item_id'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'total' => $item['total'] ?? 0,
                    'description' => $item['description'] ?? null,
                    'del_status' => 'Live',
                ]);
            }
            if (!empty($localId)) $this->mapLocal('quotations', $localId, '', (int) $serverId);
            return ['local_id' => $localId, 'server_id' => (int) $serverId];
        } catch (\Throwable $e) {
            return ['local_id' => $localId, 'error' => $e->getMessage()];
        }
    }

    private function pushIncome(array $inc): array
    {
        $localId = $inc['local_id'] ?? null;
        $now = now()->toDateTimeString();
        try {
            if (!empty($localId)) {
                $mapped = $this->findMapping('incomes', $localId);
                if ($mapped && DB::table('incomes')->where('id', $mapped->server_id)->exists())
                    return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id];
            }
            $serverId = DB::table('incomes')->insertGetId([
                'reference_no' => $inc['reference_no'] ?? null,
                'date' => $inc['date'] ?? null,
                'category_id' => $inc['category_id'] ?? null,
                'payment_method_id' => $inc['payment_method_id'] ?? null,
                'amount' => $inc['amount'] ?? 0,
                'note' => $inc['note'] ?? null,
                'employee_id' => $inc['employee_id'] ?? null,
                'user_id' => $inc['user_id'] ?? null,
                'outlet_id' => $inc['outlet_id'] ?? $this->outletId,
                'company_id' => $this->companyId,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!empty($localId)) $this->mapLocal('incomes', $localId, '', (int) $serverId);
            return ['local_id' => $localId, 'server_id' => (int) $serverId];
        } catch (\Throwable $e) {
            return ['local_id' => $localId, 'error' => $e->getMessage()];
        }
    }

    private function pushCustomerReceive(array $cr): array
    {
        $localId = $cr['local_id'] ?? null;
        $now = now()->toDateTimeString();
        try {
            if (!empty($localId)) {
                $mapped = $this->findMapping('customer_receives', $localId);
                if ($mapped && DB::table('customer_receives')->where('id', $mapped->server_id)->exists())
                    return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id];
            }
            $serverId = DB::table('customer_receives')->insertGetId([
                'customer_id' => $cr['customer_id'] ?? null,
                'payment_method_id' => $cr['payment_method_id'] ?? null,
                'amount' => $cr['amount'] ?? 0,
                'date' => $cr['date'] ?? null,
                'note' => $cr['note'] ?? null,
                'user_id' => $cr['user_id'] ?? null,
                'outlet_id' => $cr['outlet_id'] ?? $this->outletId,
                'company_id' => $this->companyId,
                'reference_no' => $cr['reference_no'] ?? null,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!empty($localId)) $this->mapLocal('customer_receives', $localId, '', (int) $serverId);
            return ['local_id' => $localId, 'server_id' => (int) $serverId];
        } catch (\Throwable $e) {
            return ['local_id' => $localId, 'error' => $e->getMessage()];
        }
    }

    /**
     * Push a sale return created on the desktop POS (F6).
     * Mirrors Modules\Sale SaleReturnService validation:
     *  - sale must exist and belong to the company
     *  - return qty per item cannot exceed (sold - already returned)
     */
    private function pushSaleReturn(array $sr): array
    {
        $now = now()->toDateTimeString();
        $localId = $sr['local_id'] ?? null;
        $saleId = (int) ($sr['sale_id'] ?? 0);

        try {
            // Idempotency: same device + local return already pushed? (multi-counter safe)
            if (! empty($localId)) {
                $mapped = $this->findMapping('sale_returns', $localId);
                if ($mapped && DB::table('sale_returns')->where('id', $mapped->server_id)->exists()) {
                    return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id];
                }
            }

            $sale = null;
            if ($saleId > 0) {
                $sale = DB::table('sales')
                    ->where('id', $saleId)
                    ->where('company_id', $this->companyId)
                    ->where('del_status', 'Live')
                    ->first();

                if (! $sale) {
                    return ['local_id' => $localId, 'server_id' => null, 'error' => 'Sale not found'];
                }
            }

            $items = $sr['items'] ?? [];
            if (count($items) === 0) {
                return ['local_id' => $localId, 'server_id' => null, 'error' => 'No items'];
            }

            // Validate quantities against (sold - already returned) only for sale-linked returns.
            // Without-bill returns (sale_id 0/null) are stored as-is.
            $soldMap = [];
            $returnedMap = [];
            if ($sale) {
                $soldMap = DB::table('sale_details')
                    ->where('sales_id', $saleId)
                    ->where('del_status', 'Live')
                    ->groupBy('item_id')
                    ->selectRaw('item_id, SUM(qty) AS qty')
                    ->get()->pluck('qty', 'item_id');

                $returnedMap = DB::table('sale_return_details')
                    ->where('sale_id', $saleId)
                    ->where('del_status', 'Live')
                    ->groupBy('item_id')
                    ->selectRaw('item_id, SUM(return_quantity_amount) AS qty')
                    ->get()->pluck('qty', 'item_id');
            }

            $totalReturnAmount = 0;
            $detailRows = [];
            foreach ($items as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);

                // ── DESKTOP ID MAPPING ──
                // Desktop local item ids negative hote hain (ServerId alag column).
                // Direct hit na mile to sync_local_mappings se resolve karo, phir
                // item_code se. Warna sale-linked returns "Item not in sale" se
                // reject ho jate the (abhi connection nahi dikhta tha).
                if ($itemId !== 0 && ! DB::table('items')->where('id', $itemId)->where('company_id', $this->companyId)->exists()) {
                    // findMapping outlet/device filter se miss hota hai (purani item
                    // mappings outlet 2 par the, request outlet 1) — isliye outlet/device
                    // agnostic direct lookup karo.
                    $mapped = DB::table('sync_local_mappings')
                        ->where('entity_type', 'items')
                        ->where('company_id', $this->companyId)
                        ->where('local_id', $itemId)
                        ->orderByDesc('id')
                        ->first();
                    if ($mapped && DB::table('items')->where('id', (int) $mapped->server_id)->where('company_id', $this->companyId)->exists()) {
                        $itemId = (int) $mapped->server_id;
                    }
                }
                if ($itemId !== 0 && ! DB::table('items')->where('id', $itemId)->where('company_id', $this->companyId)->exists()
                    && ! empty($line['item_code'])) {
                    $byCode = DB::table('items')->where('code', $line['item_code'])->where('company_id', $this->companyId)->where('del_status', 'Live')->first();
                    if ($byCode) $itemId = (int) $byCode->id;
                }

                $returnQty = (float) ($line['return_quantity_amount'] ?? 0);
                $saleQty = (float) ($line['sale_quantity_amount'] ?? ($soldMap[$itemId] ?? 0));
                $unitPriceSale = (float) ($line['unit_price_in_sale'] ?? 0);
                $unitPriceReturn = (float) ($line['unit_price_in_return'] ?? $unitPriceSale);

                if ($itemId === 0 || $returnQty <= 0) {
                    continue;
                }

                if ($sale) {
                    $sold = (float) ($soldMap[$itemId] ?? 0);
                    if ($sold <= 0) {
                        return ['local_id' => $localId, 'server_id' => null, 'error' => "Item $itemId not in sale $saleId"];
                    }

                    $returned = (float) ($returnedMap[$itemId] ?? 0);
                    if ($returnQty > $sold - $returned + 0.0001) {
                        return ['local_id' => $localId, 'server_id' => null, 'error' => "Item $itemId return qty exceeds available (sold $sold, returned $returned)"];
                    }
                }

                $totalReturnAmount += $returnQty * $unitPriceReturn;
                $detailRows[] = [
                    'item_id' => $itemId,
                    'sale_quantity_amount' => $saleQty,
                    'return_quantity_amount' => $returnQty,
                    'unit_price_in_sale' => $unitPriceSale,
                    'unit_price_in_return' => $unitPriceReturn,
                    'user_id' => $this->userId,
                    'outlet_id' => $this->outletId,
                    'company_id' => $this->companyId,
                    'del_status' => 'Live',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (count($detailRows) === 0) {
                return ['local_id' => $localId, 'server_id' => null, 'error' => 'No valid return lines'];
            }

            // Only reference entities that exist on the cloud (FK safety for without-bill returns)
            $customerId = (int) ($sr['customer_id'] ?? 0);
            if ($customerId > 0 && ! DB::table('customers')->where('id', $customerId)->where('company_id', $this->companyId)->exists()) {
                $customerId = 0;
            }
            $paymentMethodId = (int) ($sr['payment_method_id'] ?? 1);
            if ($paymentMethodId > 0 && ! DB::table('payment_methods')->where('id', $paymentMethodId)->where('company_id', $this->companyId)->exists()) {
                $paymentMethodId = 1;
            }
            foreach ($detailRows as &$dr) {
                $dr['item_id'] = (int) $dr['item_id'];
                if (! DB::table('items')->where('id', $dr['item_id'])->where('company_id', $this->companyId)->exists()) {
                    $dr['item_id'] = null;
                }
            }
            unset($dr);

            $reference = (string) ($sr['reference_no'] ?? '');
            if ($reference === '' || DB::table('sale_returns')->where('reference_no', $reference)->where('company_id', $this->companyId)->exists()) {
                $count = (int) DB::table('sale_returns')->where('company_id', $this->companyId)->count('id');
                $reference = 'SR-' . date('Y') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
            }

            $returnId = DB::table('sale_returns')->insertGetId([
                'reference_no' => $reference,
                'sale_id' => $sale ? $saleId : null,
                'customer_id' => $customerId > 0 ? $customerId : null,
                'date' => $sr['date'] ?? date('Y-m-d'),
                'total_return_amount' => round($totalReturnAmount, 3),
                'paid' => (float) ($sr['paid'] ?? $totalReturnAmount),
                'due' => (float) ($sr['due'] ?? 0),
                'payment_method_id' => $paymentMethodId > 0 ? $paymentMethodId : null,
                'note' => $sr['note'] ?? null,
                'user_id' => $this->userId,
                'outlet_id' => $this->outletId,
                'company_id' => $this->companyId,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($detailRows as &$row) {
                $row['sale_return_id'] = $returnId;
                $row['sale_id'] = $saleId > 0 ? $saleId : null;
                DB::table('sale_return_details')->insert($row);

                // Restore stock for returned items
                $itemId = $row['item_id'];
                $returnQty = (float) $row['return_quantity_amount'];
                if ($itemId && $returnQty > 0) {
                    DB::table('items')->where('id', $itemId)->increment('stock_quantity', $returnQty);
                }
            }

            if (! empty($localId)) {
                $this->mapLocal('sale_returns', $localId, '', (int) $returnId);
            }

            return ['local_id' => $localId, 'server_id' => $returnId];
        } catch (\Throwable $e) {
            return ['local_id' => $localId, 'server_id' => null, 'error' => $e->getMessage()];
        }
    }

    // ═══════════════════ BUSINESS CLUB FALLBACK CREDIT ═══════════════════

    /**
     * For each newly created sale, check if the desktop already pushed a
     * wallet_transaction (credit) for that sale. If not, call
     * BusinessClubService::creditWalletFromSale() as a server-side fallback
     * so profit sharing works even with older desktop versions.
     *
     * Conditions (mirroring the WPF desktop logic):
     * - customer_id > 1
     * - grand_total >= minimum_bill_amount (from business_club_settings)
     * - customer total purchases >= min_purchase_amount
     * - profit = (sale_price - purchase_price) * qty for each item
     */
    private function businessClubFallbackCredit(array $saleResults, array $pushedWalletTxns, array $pushedSales): void
    {
        try {
            // Collect sale_ids that had a wallet_transaction credit pushed alongside
            $coveredSaleIds = [];
            foreach ($pushedWalletTxns as $txn) {
                if (($txn['type'] ?? '') === 'credit' && !empty($txn['sale_id'])) {
                    $coveredSaleIds[] = (int) $txn['sale_id'];
                }
            }
            // Also check local_ids: desktop may reference sale by local_id in wallet_txn.sale_id
            // before it knows the server_id. Build a local_id -> server_id map.
            $localToServer = [];
            foreach ($saleResults as $sr) {
                if (!empty($sr['server_id']) && !empty($sr['local_id'])) {
                    $localToServer[(string) $sr['local_id']] = (int) $sr['server_id'];
                }
            }
            foreach ($pushedWalletTxns as $txn) {
                if (($txn['type'] ?? '') === 'credit' && !empty($txn['sale_id'])) {
                    // If sale_id in wallet_txn matches a local_id that was just mapped
                    $saleRef = (string) $txn['sale_id'];
                    if (isset($localToServer[$saleRef])) {
                        $coveredSaleIds[] = $localToServer[$saleRef];
                    }
                }
            }
            $coveredSaleIds = array_unique($coveredSaleIds);

            // Get business club settings
            $service = app(BusinessClubService::class);
            $settings = $service->getSettings($this->companyId);
            if (!$settings) {
                return; // Business Club not configured — nothing to do
            }

            $minimumBillAmount = (float) ($settings->minimum_bill_amount ?? 0);
            $minPurchaseAmount = (float) ($settings->min_purchase_amount ?? 0);

            // Process each newly created sale
            foreach ($saleResults as $idx => $result) {
                if (empty($result['server_id']) || empty($result['created'])) {
                    continue; // Not a newly created sale (duplicate/error)
                }

                $serverId = (int) $result['server_id'];

                // If desktop already pushed the wallet_transaction for this sale, skip
                if (in_array($serverId, $coveredSaleIds, true)) {
                    continue;
                }

                // Also check if a credit wallet_transaction already exists on server
                // (from a previous push or retry)
                $existingCredit = DB::table('wallet_transactions')
                    ->where('sale_id', $serverId)
                    ->where('type', 'credit')
                    ->where('company_id', $this->companyId)
                    ->where('del_status', 'Live')
                    ->exists();
                if ($existingCredit) {
                    continue;
                }

                // Load the sale from DB
                $sale = DB::table('sales')
                    ->where('id', $serverId)
                    ->where('company_id', $this->companyId)
                    ->where('del_status', 'Live')
                    ->first();
                if (!$sale || !$sale->customer_id || (int) $sale->customer_id <= 1) {
                    continue;
                }

                $grandTotal = (float) ($sale->grand_total ?? 0);

                // Check minimum bill amount
                if ($minimumBillAmount > 0 && $grandTotal < $minimumBillAmount) {
                    continue;
                }

                $customerId = (int) $sale->customer_id;

                // Check customer total purchases >= min_purchase_amount
                if ($minPurchaseAmount > 0) {
                    $totalPurchases = (float) DB::table('sales')
                        ->where('customer_id', $customerId)
                        ->where('company_id', $this->companyId)
                        ->where('del_status', 'Live')
                        ->sum('grand_total');
                    if ($totalPurchases < $minPurchaseAmount) {
                        continue;
                    }
                }

                // Calculate total profit from sale items:
                // profit = (sale_price - purchase_price) * qty
                $saleDetails = DB::table('sale_details')
                    ->where('sales_id', $serverId)
                    ->where('del_status', 'Live')
                    ->get(['item_id', 'qty', 'menu_unit_price', 'purchase_price']);

                $totalProfit = 0;
                foreach ($saleDetails as $detail) {
                    $salePrice = (float) ($detail->menu_unit_price ?? 0);
                    $purchasePrice = (float) ($detail->purchase_price ?? 0);
                    $qty = (float) ($detail->qty ?? 0);
                    $profit = ($salePrice - $purchasePrice) * $qty;
                    if ($profit > 0) {
                        $totalProfit += $profit;
                    }
                }

                if ($totalProfit <= 0) {
                    continue;
                }

                // Credit wallet using the service
                $service->creditWalletFromSale($customerId, $totalProfit, $serverId, $this->companyId);
            }
        } catch (\Throwable $e) {
            // Fallback credit should never break the sync response
            \Illuminate\Support\Facades\Log::warning('businessClubFallbackCredit error: ' . $e->getMessage());
        }
    }

    // ═══════════════════════ PULL HELPERS ═══════════════════════

    /**
     * Fold a manually-set items.stock_quantity into the stock ledger.
     *
     * The desktop writes manual stock changes (bulk update / item edit / import)
     * into items.stock_quantity only. The web and /api/sync/pull report stock
     * from the ledger (purchase_details - sale_details + set_opening_stocks), so
     * a delta row in set_opening_stocks (marked SYNC_ADJ) keeps the two in sync.
     */
    private function reconcileManualStock(?string $code): void
    {
        if (empty($code)) {
            return;
        }
        try {
            $item = DB::table('items')
                ->where('code', $code)
                ->where('company_id', $this->companyId)
                ->where('del_status', 'Live')
                ->first();
            if (! $item) {
                return;
            }

            $in  = (float) DB::table('purchase_details')->where('item_id', $item->id)->where('del_status', 'Live')->sum('quantity_amount');
            $out = (float) DB::table('sale_details')->where('item_id', $item->id)->where('del_status', 'Live')->sum('qty');
            // User-created opening stock rows feed the ledger too (rebuild UNION).
            $userOpening = (float) DB::table('set_opening_stocks')
                ->where('item_id', $item->id)
                ->where('item_description', 'NOT LIKE', 'SYNC_ADJ%')
                ->sum('stock_quantity');
            $desired = (float) $item->stock_quantity;

            // The SYNC_ADJ row is ABSOLUTE: ledger(purchase - sale + user opening)
            // plus SYNC_ADJ must equal the manually-set items.stock_quantity.
            $adj = round($desired - ($in - $out + $userOpening), 3);

            if (abs($adj) < 0.001) {
                DB::table('set_opening_stocks')
                    ->where('item_id', $item->id)
                    ->where('item_description', 'LIKE', 'SYNC_ADJ%')
                    ->delete();
                return;
            }

            $existing = DB::table('set_opening_stocks')
                ->where('item_id', $item->id)
                ->where('item_description', 'LIKE', 'SYNC_ADJ%')
                ->first();

            $now = now()->toDateTimeString();
            if ($existing) {
                DB::table('set_opening_stocks')->where('id', $existing->id)->update([
                    'stock_quantity' => $adj,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('set_opening_stocks')->insert([
                    'item_id' => $item->id,
                    'item_type' => 'opening',
                    'item_description' => 'SYNC_ADJ',
                    'stock_quantity' => $adj,
                    'outlet_id' => $this->outletId,
                    'user_id' => $this->userId,
                    'company_id' => $this->companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        } catch (\Throwable $e) {
            \Log::error('reconcileManualStock failed: ' . $e->getMessage());
        }
    }

    /**
     * Rebuild view_stock_detail (the ledger the web stock pages read) from
     * purchase_details / sale_details / set_opening_stocks. SYNC_ADJ delta rows
     * are included even when negative, so manual stock reductions propagate too.
     */
    private function refreshStockView(): void
    {
        try {
            DB::statement('TRUNCATE TABLE view_stock_detail');
            DB::statement("INSERT INTO view_stock_detail (item_id, type, stock_quantity, outlet_id, company_id, del_status)
                -- Purchases IN
                SELECT item_id, 1, quantity_amount, outlet_id, company_id, del_status
                FROM purchase_details WHERE del_status='Live' AND quantity_amount > 0
                UNION ALL
                -- Opening Stock IN
                SELECT item_id, 1, stock_quantity, outlet_id, company_id, 'Live'
                FROM set_opening_stocks WHERE stock_quantity > 0 OR item_description LIKE 'SYNC_ADJ%'
                UNION ALL
                -- Sale Returns IN
                SELECT item_id, 1, return_quantity_amount, outlet_id, company_id, del_status
                FROM sale_return_details WHERE del_status='Live' AND return_quantity_amount > 0
                UNION ALL
                -- Sales OUT
                SELECT item_id, 2, qty, outlet_id, company_id, del_status
                FROM sale_details WHERE del_status='Live' AND qty > 0
                UNION ALL
                -- Purchase Returns OUT
                SELECT item_id, 2, return_quantity_amount, outlet_id, company_id, del_status
                FROM purchase_return_details WHERE del_status='Live' AND return_quantity_amount > 0
                UNION ALL
                -- Damages OUT
                SELECT item_id, 2, damage_quantity, outlet_id, company_id, del_status
                FROM damage_details WHERE del_status='Live' AND damage_quantity > 0
            ");
        } catch (\Throwable $e) {
            \Log::error('refreshStockView failed: ' . $e->getMessage());
        }
    }

    private function pullItems(?string $since): array
    {
        // NOTE: del_status ka filter yahan NAHI lagate — Deleted rows (tombstones)
        // bhi bhejte hain taaki server par delete ki gai items software se bhi
        // hat jayein. Desktop unhe del_status='Deleted' se mark karta hai.
        $query = DB::table('items')
            ->where('company_id', $this->companyId);
        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        $rows = $query->get([
            'id', 'name', 'code', 'alternative_name', 'type', 'category_id',
            'brand_id', 'supplier_id', 'hsn_code', 'unit_type', 'purchase_unit_id',
            'sale_unit_id', 'conversion_rate', 'mrp_price', 'sale_price',
            'whole_sale_price', 'purchase_price', 'profit_margin', 'alert_quantity',
            'loyalty_point', 'warranty', 'warranty_date', 'guarantee', 'guarantee_date',
            'tax_string', 'tax_type',
            'enable_disable_status', 'stock_quantity', 'parent_id', 'del_status', 'updated_at',
            'last_three_purchase_avg', 'last_purchase_price', 'sync_version',
        ]);

        $itemIds = $rows->pluck('id')->all();

        // Stock = purchase in - sale out (matches view_stock_detail)
        $stockMap = [];
        if (count($itemIds) > 0) {
            $purchaseIn = DB::table('purchase_details')
                ->whereIn('item_id', $itemIds)
                ->where('del_status', 'Live')
                ->groupBy('item_id')
                ->selectRaw('item_id, SUM(quantity_amount) AS qty')
                ->get()->pluck('qty', 'item_id');

            $saleOut = DB::table('sale_details')
                ->whereIn('item_id', $itemIds)
                ->where('del_status', 'Live')
                ->groupBy('item_id')
                ->selectRaw('item_id, SUM(qty) AS qty')
                ->get()->pluck('qty', 'item_id');

            // Manual stock adjustments pushed from the desktop live in
            // set_opening_stocks (SYNC_ADJ rows) — they feed the ledger exactly
            // like the web's view_stock_detail rebuild.
            $openingIn = DB::table('set_opening_stocks')
                ->whereIn('item_id', $itemIds)
                ->groupBy('item_id')
                ->selectRaw('item_id, SUM(stock_quantity) AS qty')
                ->get()->pluck('qty', 'item_id');

            foreach ($itemIds as $id) {
                $stockMap[$id] = round(
                    (float) ($purchaseIn[$id] ?? 0) + (float) ($openingIn[$id] ?? 0) - (float) ($saleOut[$id] ?? 0), 3
                );
            }
        }

        return $rows->map(function ($item) use ($stockMap) {
            return [
                'id' => (int) $item->id,
                'code' => $item->code ?? '',
                'name' => $item->name ?? '',
                'alternative_name' => $item->alternative_name ?? '',
                'type' => $item->type ?? '',
                'category_id' => $item->category_id ? (int) $item->category_id : null,
                'brand_id' => $item->brand_id ? (int) $item->brand_id : null,
                'supplier_id' => $item->supplier_id ? (int) $item->supplier_id : null,
                'hsn_code' => $item->hsn_code ?? '',
                'unit_type' => $item->unit_type ?? '',
                'purchase_unit_id' => $item->purchase_unit_id ? (int) $item->purchase_unit_id : null,
                'sale_unit_id' => $item->sale_unit_id ? (int) $item->sale_unit_id : null,
                'conversion_rate' => (float) ($item->conversion_rate ?? 1),
                'mrp_price' => (float) $item->mrp_price,
                'sale_price' => (float) $item->sale_price,
                'whole_sale_price' => (float) $item->whole_sale_price,
                'purchase_price' => (float) $item->purchase_price,
                'profit_margin' => (float) $item->profit_margin,
                'alert_quantity' => (float) $item->alert_quantity,
                'loyalty_point' => (int) ($item->loyalty_point ?? 0),
                'warranty' => $item->warranty ?? null,
                'warranty_date' => $item->warranty_date ?? null,
                'guarantee' => $item->guarantee ?? null,
                'guarantee_date' => $item->guarantee_date ?? null,
                'tax_string' => $item->tax_string ?? '',
                'tax_type' => $item->tax_type ?? 'Exclusive',
                'enable_disable_status' => (int) ($item->enable_disable_status ?? 1),
                'stock_quantity' => array_key_exists((int) $item->id, $stockMap)
                    ? max($stockMap[(int) $item->id], (float) ($item->stock_quantity ?? 0))
                    : (float) ($item->stock_quantity ?? 0),
                'parent_id' => $item->parent_id ? (int) $item->parent_id : null,
                'del_status' => $item->del_status ?? 'Live',
                'last_three_purchase_avg' => (float) ($item->last_three_purchase_avg ?? 0),
                'last_purchase_price' => (float) ($item->last_purchase_price ?? 0),
                'sync_version' => (int) ($item->sync_version ?? 1),
                'updated_at' => $item->updated_at ?? now()->toDateTimeString(),
            ];
        })->all();
    }

    private function pullCustomers(?string $since): array
    {
        // Core columns that always exist in the base schema
        $core = [
            'id', 'name', 'email', 'phone', 'address', 'city', 'postal_code',
            'gst_number', 'opening_balance', 'opening_balance_type', 'credit_limit',
            'loyalty_point', 'del_status', 'updated_at', 'sync_version',
        ];
        // Optional columns – may or may not exist depending on DB version
        $optional = [
            'customer_type', 'business_type', 'same_or_diff_state',
            'is_installment_customer', 'work_address', 'guarantor_name',
            'guarantor_mobile',
        ];
        $existing = array_intersect($optional, DB::getSchemaBuilder()->getColumnListing('customers'));
        $columns  = array_merge($core, $existing);

        $query = DB::table('customers')
            ->where('company_id', $this->companyId)
            ->select($columns);
        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(function ($c) use ($existing) {
            return [
                'id' => (int) $c->id,
                'name' => $c->name ?? '',
                'email' => $c->email ?? '',
                'phone' => $c->phone ?? '',
                'address' => $c->address ?? '',
                'city' => $c->city ?? '',
                'postal_code' => $c->postal_code ?? '',
                'gst_number' => $c->gst_number ?? '',
                'opening_balance' => (float) $c->opening_balance,
                'opening_balance_type' => $c->opening_balance_type ?? 'Dr',
                'credit_limit' => (float) $c->credit_limit,
                'loyalty_point' => (int) ($c->loyalty_point ?? 0),
                'del_status' => $c->del_status ?? 'Live',
                'sync_version' => (int) ($c->sync_version ?? 1),
                'updated_at' => $c->updated_at ?? now()->toDateTimeString(),
                // Optional fields – null if column doesn't exist
                'customer_type' => in_array('customer_type', $existing) ? ($c->customer_type ?? 'B2C') : 'B2C',
                'business_type' => in_array('business_type', $existing) ? ($c->business_type ?? 'B2C') : 'B2C',
                'same_or_diff_state' => in_array('same_or_diff_state', $existing) ? ($c->same_or_diff_state ?? '') : '',
                'is_installment_customer' => in_array('is_installment_customer', $existing) ? ($c->is_installment_customer ?? 'No') : 'No',
                'work_address' => in_array('work_address', $existing) ? ($c->work_address ?? '') : '',
                'guarantor_name' => in_array('guarantor_name', $existing) ? ($c->guarantor_name ?? '') : '',
                'guarantor_mobile' => in_array('guarantor_mobile', $existing) ? ($c->guarantor_mobile ?? '') : '',
            ];
        })->all();
    }

    private function pullSuppliers(?string $since): array
    {
        // NOTE: del_status ka filter yahan NAHI lagate — Deleted rows (tombstones)
        // bhi bhejte hain taaki server par delete kiye gaye suppliers software se bhi
        // hat jayein. Desktop UpsertParties unhe del_status='Deleted' se mark karta hai.
        $query = DB::table('suppliers')
            ->where('company_id', $this->companyId);
        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get([
            'id', 'name', 'company_name', 'email', 'phone', 'address', 'city',
            'state', 'postal_code', 'country', 'gst_number', 'opening_balance',
            'opening_balance_type', 'credit_limit', 'description', 'del_status', 'updated_at', 'sync_version',
        ])->map(function ($s) {
            return [
                'id' => (int) $s->id,
                'name' => $s->name ?? '',
                'company_name' => $s->company_name ?? '',
                'email' => $s->email ?? '',
                'phone' => $s->phone ?? '',
                'address' => $s->address ?? '',
                'city' => $s->city ?? '',
                'postal_code' => $s->postal_code ?? '',
                'gst_number' => $s->gst_number ?? '',
                'opening_balance' => (float) $s->opening_balance,
                'opening_balance_type' => $s->opening_balance_type ?? 'Dr',
                'credit_limit' => (float) $s->credit_limit,
                'description' => $s->description ?? '',
                'del_status' => $s->del_status ?? 'Live',
                'sync_version' => (int) ($s->sync_version ?? 1),
                'updated_at' => $s->updated_at ?? now()->toDateTimeString(),
            ];
        })->all();
    }

    private function pullSales(?string $since): array
    {
        // NOTE (delete parity): Live filter nahi — Deleted rows (tombstones) bhi
        // bhejte hain taaki desktop `sales` mirror Deleted ho jaye (cloud delete
        // → desktop list se hat jaye). Desktop UpsertSimpleRows generic mirror
        // del_status copy karta hai.
        $query = DB::table('sales')
            ->where('company_id', $this->companyId);
        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        $sales = $query->get([
            'id', 'invoice_no', 'sale_no', 'sale_date', 'date_time', 'customer_id',
            'sub_total', 'given_amount', 'paid_amount', 'change_amount',
            'disc', 'vat', 'total_payable', 'grand_total', 'mrp_total', 'savings',
            'note', 'user_id',
            'outlet_id', 'del_status', 'created_at', 'updated_at',
        ]);

        if ($sales->isEmpty()) {
            return [];
        }

        $ids = $sales->pluck('id')->all();

        $details = DB::table('sale_details')
            ->whereIn('sales_id', $ids)->where('del_status', 'Live')
            ->get(['sales_id', 'item_id', 'qty', 'menu_unit_price', 'menu_vat_percentage',
                'item_tax_amount', 'discount_amount', 'discount_type', 'is_promo_item'])
            ->groupBy('sales_id');

        $payments = DB::table('sale_payments')
            ->whereIn('sale_id', $ids)->where('del_status', 'Live')
            ->get(['sale_id', 'payment_id', 'amount', 'date', 'note'])
            ->groupBy('sale_id');

        return $sales->map(function ($sale) use ($details, $payments) {
            return [
                'id' => (int) $sale->id,
                'invoice_no' => $sale->invoice_no ?? $sale->sale_no ?? '',
                'sale_date' => $sale->sale_date ?? '',
                'date_time' => $sale->date_time ?? $sale->created_at ?? '',
                'customer_id' => $sale->customer_id ? (int) $sale->customer_id : null,
                'sub_total' => (float) $sale->sub_total,
                'given_amount' => (float) $sale->given_amount,
                'paid_amount' => (float) $sale->paid_amount,
                'change_amount' => (float) $sale->change_amount,
                'disc' => (float) $sale->disc,
                'vat' => (float) $sale->vat,
                'total_payable' => (float) $sale->total_payable,
                'grand_total' => (float) $sale->grand_total,
                'mrp_total' => (float) ($sale->mrp_total ?? 0),
                'savings' => (float) ($sale->savings ?? 0),
                'note' => $sale->note ?? '',
                'user_id' => $sale->user_id ? (int) $sale->user_id : null,
                'outlet_id' => $sale->outlet_id ? (int) $sale->outlet_id : null,
                'del_status' => $sale->del_status ?? 'Live',
                'items' => ($details[$sale->id] ?? collect())->map(fn ($d) => [
                    'item_id' => (int) $d->item_id,
                    'qty' => (float) $d->qty,
                    'menu_unit_price' => (float) $d->menu_unit_price,
                    'menu_vat_percentage' => (float) $d->menu_vat_percentage,
                    'item_tax_amount' => (float) $d->item_tax_amount,
                    'discount_amount' => (float) $d->discount_amount,
                    'discount_type' => $d->discount_type ?? 'fixed',
                    'is_promo_item' => $d->is_promo_item ?? 'No',
                ])->values()->all(),
                'payments' => ($payments[$sale->id] ?? collect())->map(fn ($p) => [
                    'payment_id' => (int) $p->payment_id,
                    'amount' => (float) $p->amount,
                    'date' => $p->date ?? '',
                    'note' => $p->note ?? '',
                ])->values()->all(),
                'updated_at' => $sale->updated_at ?? now()->toDateTimeString(),
            ];
        })->values()->all();
    }

    private function pullSaleReturns(?string $since): array
    {
        // NOTE (delete parity): Live filter nahi — Deleted rows (tombstones) bhi.
        $query = DB::table('sale_returns')
            ->where('company_id', $this->companyId);
        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        $returns = $query->get([
            'id', 'reference_no', 'sale_id', 'customer_id', 'date',
            'total_return_amount', 'paid', 'due', 'payment_method_id', 'note',
            'user_id', 'outlet_id', 'del_status', 'created_at', 'updated_at',
        ]);

        if ($returns->isEmpty()) {
            return [];
        }

        $ids = $returns->pluck('id')->all();

        $details = DB::table('sale_return_details')
            ->whereIn('sale_return_id', $ids)->where('del_status', 'Live')
            ->get(['sale_return_id', 'sale_id', 'item_id', 'sale_quantity_amount',
                'return_quantity_amount', 'unit_price_in_sale', 'unit_price_in_return'])
            ->groupBy('sale_return_id');

        return $returns->map(function ($r) use ($details) {
            return [
                'id' => (int) $r->id,
                'reference_no' => $r->reference_no ?? '',
                'sale_id' => (int) $r->sale_id,
                'customer_id' => $r->customer_id ? (int) $r->customer_id : null,
                'date' => $r->date ?? '',
                'total_return_amount' => (float) $r->total_return_amount,
                'paid' => (float) $r->paid,
                'due' => (float) $r->due,
                'payment_method_id' => (int) $r->payment_method_id,
                'note' => $r->note ?? '',
                'del_status' => $r->del_status ?? 'Live',
                'items' => ($details[$r->id] ?? collect())->map(fn ($d) => [
                    'item_id' => (int) $d->item_id,
                    'sale_quantity_amount' => (float) $d->sale_quantity_amount,
                    'return_quantity_amount' => (float) $d->return_quantity_amount,
                    'unit_price_in_sale' => (float) $d->unit_price_in_sale,
                    'unit_price_in_return' => (float) $d->unit_price_in_return,
                ])->values()->all(),
                'updated_at' => $r->updated_at ?? now()->toDateTimeString(),
            ];
        })->values()->all();
    }

    private function pullHolds(?string $since): array
    {
        // NOTE (delete parity): Live filter nahi — Deleted rows (tombstones) bhi.
        $query = DB::table('holds')
            ->where('company_id', $this->companyId);
        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        $holds = $query->get(['id', 'invoice_no', 'sale_date', 'customer_id',
            'sub_total', 'disc', 'vat', 'total_payable', 'note', 'del_status', 'updated_at']);

        if ($holds->isEmpty()) {
            return [];
        }

        $ids = $holds->pluck('id')->all();
        $details = DB::table('hold_details')
            ->whereIn('holds_id', $ids)->where('del_status', 'Live')
            ->get(['holds_id', 'item_id', 'qty', 'menu_unit_price', 'menu_vat_percentage',
                'item_tax_amount', 'discount_amount'])
            ->groupBy('holds_id');

        return $holds->map(function ($hold) use ($details) {
            return [
                'id' => (int) $hold->id,
                'invoice_no' => $hold->invoice_no ?? '',
                'sale_date' => $hold->sale_date ?? '',
                'customer_id' => $hold->customer_id ? (int) $hold->customer_id : null,
                'sub_total' => (float) $hold->sub_total,
                'disc' => (float) $hold->disc,
                'vat' => (float) $hold->vat,
                'total_payable' => (float) $hold->total_payable,
                'note' => $hold->note ?? '',
                'del_status' => $hold->del_status ?? 'Live',
                'items' => ($details[$hold->id] ?? collect())->map(fn ($d) => [
                    'item_id' => (int) $d->item_id,
                    'qty' => (float) $d->qty,
                    'menu_unit_price' => (float) $d->menu_unit_price,
                    'menu_vat_percentage' => (float) $d->menu_vat_percentage,
                    'item_tax_amount' => (float) $d->item_tax_amount,
                    'discount_amount' => (float) $d->discount_amount,
                ])->values()->all(),
                'updated_at' => $hold->updated_at ?? now()->toDateTimeString(),
            ];
        })->values()->all();
    }

    private function pullPurchases(?string $since): array
    {
        // NOTE: del_status ka filter yahan NAHI lagate — Deleted rows (tombstones)
        // bhi bhejte hain taaki server par delete ki gai purchases software se bhi
        // hat jayein. Desktop UpsertPurchases unhe del_status='Deleted' se mark karta hai.
        $query = DB::table('purchases')
            ->where('company_id', $this->companyId);
        if ($since) {
            $query->where('updated_at', '>', $since);
        }
        $rows = $query->get(['id', 'reference_no', 'invoice_no', 'supplier_id', 'date',
            'grand_total', 'paid', 'due_amount', 'note', 'discount', 'status', 'del_status', 'updated_at']);

        if ($rows->isEmpty()) {
            return [];
        }

        $liveIds = $rows->where('del_status', 'Live')->pluck('id')->all();
        $details = DB::table('purchase_details')
            ->whereIn('purchase_id', $liveIds)->where('del_status', 'Live')
            ->get(['purchase_id', 'item_id', 'unit_price', 'quantity_amount', 'total', 'item_type'])
            ->groupBy('purchase_id');
        $payments = DB::table('purchase_payments')
            ->whereIn('purchase_id', $liveIds)->where('del_status', 'Live')
            ->get(['purchase_id', 'payment_id', 'amount', 'date', 'reference_no'])
            ->groupBy('purchase_id');

        $supplierNames = DB::table('suppliers')
            ->whereIn('id', $rows->pluck('supplier_id')->filter()->all())
            ->get(['id', 'name'])->pluck('name', 'id');

        return $rows->map(function ($p) use ($details, $payments, $supplierNames) {
            return [
                'id' => (int) $p->id,
                'reference_no' => $p->reference_no ?? '',
                'invoice_no' => $p->invoice_no ?? '',
                'supplier_id' => $p->supplier_id ? (int) $p->supplier_id : null,
                'supplier_name' => $supplierNames[$p->supplier_id] ?? '',
                'date' => $p->date ?? '',
                'grand_total' => (float) $p->grand_total,
                'paid' => (float) $p->paid,
                'due_amount' => (float) $p->due_amount,
                'note' => $p->note ?? '',
                'discount' => $p->discount ?? '',
                'status' => $p->status ?? 'Pending',
                'del_status' => $p->del_status ?? 'Live',
                'items' => ($details[$p->id] ?? collect())->map(fn ($d) => [
                    'item_id' => (int) $d->item_id,
                    'unit_price' => (float) $d->unit_price,
                    'quantity' => (float) $d->quantity_amount,
                    'total' => (float) $d->total,
                    'item_type' => $d->item_type ?? '',
                ])->values()->all(),
                'payments' => ($payments[$p->id] ?? collect())->map(fn ($pm) => [
                    'payment_id' => (int) $pm->payment_id,
                    'amount' => (float) $pm->amount,
                    'date' => $pm->date ?? '',
                    'reference_no' => $pm->reference_no ?? '',
                ])->values()->all(),
                'updated_at' => $p->updated_at ?? now()->toDateTimeString(),
            ];
        })->values()->all();
    }

    private function pullPurchaseReturns(?string $since): array
    {
        // NOTE: del_status ka filter yahan NAHI lagate — Deleted rows (tombstones)
        // bhi bhejte hain taaki server par delete ki gai purchase returns software se
        // bhi hat jayein. Desktop UpsertPurchaseReturns unhe del_status='Deleted' se mark karta hai.
        $query = DB::table('purchase_returns')
            ->where('company_id', $this->companyId);
        if ($since) {
            $query->where('updated_at', '>', $since);
        }
        $rows = $query->get(['id', 'reference_no', 'pur_ref_no', 'supplier_id', 'date',
            'purchase_date', 'return_status', 'total_return_amount', 'payment_method_id', 'note', 'del_status', 'updated_at']);

        if ($rows->isEmpty()) {
            return [];
        }

        $liveIds = $rows->where('del_status', 'Live')->pluck('id')->all();
        $details = DB::table('purchase_return_details')
            ->whereIn('pur_return_id', $liveIds)->where('del_status', 'Live')
            ->get(['pur_return_id', 'item_id', 'return_quantity_amount', 'unit_price', 'total', 'return_note'])
            ->groupBy('pur_return_id');

        $supplierNames = DB::table('suppliers')
            ->whereIn('id', $rows->pluck('supplier_id')->filter()->all())
            ->get(['id', 'name'])->pluck('name', 'id');

        return $rows->map(function ($r) use ($details, $supplierNames) {
            return [
                'id' => (int) $r->id,
                'reference_no' => $r->reference_no ?? '',
                'pur_ref_no' => $r->pur_ref_no ?? '',
                'supplier_id' => $r->supplier_id ? (int) $r->supplier_id : null,
                'supplier_name' => $supplierNames[$r->supplier_id] ?? '',
                'date' => $r->date ?? '',
                'purchase_date' => $r->purchase_date ?? '',
                'return_status' => $r->return_status ?? '',
                'total_return_amount' => (float) $r->total_return_amount,
                'payment_method_id' => $r->payment_method_id ? (int) $r->payment_method_id : null,
                'note' => $r->note ?? '',
                'del_status' => $r->del_status ?? 'Live',
                'items' => ($details[$r->id] ?? collect())->map(fn ($d) => [
                    'item_id' => (int) $d->item_id,
                    'quantity' => (float) $d->return_quantity_amount,
                    'unit_price' => (float) $d->unit_price,
                    'total' => (float) $d->total,
                    'return_note' => $d->return_note ?? '',
                ])->values()->all(),
                'updated_at' => $r->updated_at ?? now()->toDateTimeString(),
            ];
        })->values()->all();
    }

    private function pullSupplierPayments(?string $since): array
    {
        // NOTE: del_status ka filter yahan NAHI lagate — Deleted rows (tombstones)
        // bhi bhejte hain taaki server par delete kiye gaye payments software se bhi
        // hat jayein. Desktop UpsertSimpleRows unhe del_status='Deleted' se mark karta hai.
        $query = DB::table('supplier_payments')
            ->where('company_id', $this->companyId);
        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get(['id', 'reference_no', 'supplier_id', 'payment_method_id', 'amount',
            'date', 'note', 'del_status', 'updated_at'])->map(function ($p) {
            return [
                'id' => (int) $p->id,
                'reference_no' => $p->reference_no ?? '',
                'supplier_id' => $p->supplier_id ? (int) $p->supplier_id : null,
                'payment_method_id' => $p->payment_method_id ? (int) $p->payment_method_id : null,
                'amount' => (float) $p->amount,
                'date' => $p->date ?? '',
                'note' => $p->note ?? '',
                'del_status' => $p->del_status ?? 'Live',
                'updated_at' => $p->updated_at ?? now()->toDateTimeString(),
            ];
        })->all();
    }

    private function pullExpenses(?string $since): array
    {
        // NOTE: del_status ka filter yahan NAHI lagate — Deleted rows (tombstones)
        // bhi bhejte hain taaki server par delete ki gayi expenses software se bhi
        // hat jayein. Desktop UpsertSimpleRows unhe del_status='Deleted' se mark karta hai.
        $query = DB::table('expenses')
            ->where('company_id', $this->companyId);
        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        $rows = $query->get(['id', 'reference_no', 'date', 'category_id', 'payment_method_id',
            'amount', 'note', 'employee_id', 'del_status', 'updated_at']);

        $categoryNames = DB::table('expense_categories')
            ->whereIn('id', $rows->pluck('category_id')->filter()->all())
            ->get(['id', 'name'])->pluck('name', 'id');

        return $rows->map(function ($e) use ($categoryNames) {
            return [
                'id' => (int) $e->id,
                'reference_no' => $e->reference_no ?? '',
                'date' => $e->date ?? '',
                'category_id' => $e->category_id ? (int) $e->category_id : null,
                'category_name' => $categoryNames[$e->category_id] ?? '',
                'payment_method_id' => $e->payment_method_id ? (int) $e->payment_method_id : null,
                'amount' => (float) $e->amount,
                'note' => $e->note ?? '',
                'employee_id' => $e->employee_id ? (int) $e->employee_id : null,
                'del_status' => $e->del_status ?? 'Live',
                'updated_at' => $e->updated_at ?? now()->toDateTimeString(),
            ];
        })->all();
    }

    // ═══════════════════════ PUSH HELPERS ═══════════════════════
    private function pushItem(array $item): array
    {
        $now = now()->toDateTimeString();

        $existing = DB::table('items')
            ->where('code', $item['code'])
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live')
            ->first();

        $data = [
            'name' => $item['name'] ?? '',
            'alternative_name' => $item['alternative_name'] ?? null,
            'generic_name' => $item['generic_name'] ?? null,
            'type' => $item['type'] ?? 'Product',
            'parent_id' => $this->resolveParentId($item) ?? null,
            'category_id' => !empty($item['category_id']) && is_numeric($item['category_id']) && (int) $item['category_id'] > 0 ? (int) $item['category_id'] : null,
            'brand_id' => !empty($item['brand_id']) && is_numeric($item['brand_id']) && (int) $item['brand_id'] > 0 ? (int) $item['brand_id'] : null,
            'supplier_id' => !empty($item['supplier_id']) && is_numeric($item['supplier_id']) && (int) $item['supplier_id'] > 0 ? (int) $item['supplier_id'] : null,
            'rack_id' => !empty($item['rack_id']) && is_numeric($item['rack_id']) && (int) $item['rack_id'] > 0 ? (int) $item['rack_id'] : null,
            'hsn_code' => $item['hsn_code'] ?? null,
            'unit_type' => $item['unit_type'] ?? null,
            'purchase_unit_id' => !empty($item['purchase_unit_id']) && is_numeric($item['purchase_unit_id']) && (int) $item['purchase_unit_id'] > 0 ? (int) $item['purchase_unit_id'] : null,
            'sale_unit_id' => !empty($item['sale_unit_id']) && is_numeric($item['sale_unit_id']) && (int) $item['sale_unit_id'] > 0 ? (int) $item['sale_unit_id'] : null,
            'conversion_rate' => $item['conversion_rate'] ?? 1,
            'mrp_price' => $item['mrp_price'] ?? 0,
            'sale_price' => $item['sale_price'] ?? 0,
            'whole_sale_price' => $item['whole_sale_price'] ?? 0,
            'purchase_price' => $item['purchase_price'] ?? 0,
            'profit_margin' => $item['profit_margin'] ?? 0,
            'alert_quantity' => $item['alert_quantity'] ?? 0,
            'stock_quantity' => $item['stock_quantity'] ?? 0,
            'loyalty_point' => $item['loyalty_point'] ?? 0,
            'warranty' => $item['warranty'] ?? null,
            'warranty_date' => $item['warranty_date'] ?? null,
            'guarantee' => $item['guarantee'] ?? null,
            'guarantee_date' => $item['guarantee_date'] ?? null,
            'tax_string' => $item['tax_string'] ?? null,
            'tax_type' => $item['tax_type'] ?? 'Exclusive',
            'description' => $item['description'] ?? null,
            'enable_disable_status' => $item['enable_disable_status'] ?? 1,
            'updated_at' => $now,
        ];

        if ($existing) {
            // ═══ TWO-WAY DELETE (E2): incoming tombstone → mark existing deleted ═══
            if (($item['del_status'] ?? 'Live') === 'Deleted') {
                DB::table('items')->where('id', $existing->id)->update([
                    'del_status' => 'Deleted',
                    'updated_at' => $now,
                ]);
                return ['local_code' => $item['local_code'], 'server_id' => (int) $existing->id, 'created' => false, 'deleted' => true];
            }
            // ═══ LAST-WRITE-WINS (E1): purana edit skip + log ═══
            if (! $this->lwwApply($item, $existing, 'items', (string) ($item['code'] ?? $item['local_code'] ?? ''), (int) $existing->id)) {
                return ['local_code' => $item['local_code'], 'server_id' => (int) $existing->id, 'created' => false, 'conflict' => 'skipped_older'];
            }
            DB::table('items')->where('id', $existing->id)->update($data);
            $this->applyVersion('items', (int) $existing->id, $item); // FIX 2
            return ['local_code' => $item['local_code'], 'server_id' => (int) $existing->id, 'created' => false];
        }

        $data['code'] = $item['code'] ?? (string) random_int(100000, 999999);
        $data['user_id'] = $this->userId;
        $data['company_id'] = $this->companyId;
        $data['del_status'] = 'Live';
        $data['created_at'] = $now;

        $id = DB::table('items')->insertGetId($data);

        return ['local_code' => $item['local_code'], 'server_id' => (int) $id, 'created' => true];
    }

    /**
     * Resolve the parent_id for a variation child item.
     * Priority: explicit parent_id > parent_code lookup.
     */
    private function resolveParentId(array $item): ?int
    {
        if (!empty($item['parent_id']) && is_numeric($item['parent_id']) && (int) $item['parent_id'] > 0) {
            return (int) $item['parent_id'];
        }
        $parentCode = $item['parent_code'] ?? null;
        if (!empty($parentCode)) {
            $parent = DB::table('items')
                ->where('code', $parentCode)
                ->where('company_id', $this->companyId)
                ->where('del_status', 'Live')
                ->value('id');
            if ($parent) {
                return (int) $parent;
            }
        }
        return null;
    }

    /**
     * Phone ko 10-digit canonical form me laao — +91/0 prefix, spaces,
     * dashes, brackets hata ke. Dedupe matching ke liye.
     */
    private function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone) ?? '';
        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    private function pushCustomer(array $customer): array
    {
        $now = now()->toDateTimeString();

        $existing = null;
        // 1. Try phone match (strongest dedup) — normalized (+91/0/spaces/dashes
        //    variants bhi same customer hi count hon, duplicate na bane)
        if (! empty($customer['phone'])) {
            $norm = $this->normalizePhone($customer['phone']);
            $existing = DB::table('customers')
                ->where('company_id', $this->companyId)
                ->where('del_status', 'Live')
                ->where(function ($q) use ($customer, $norm) {
                    $q->where('phone', $customer['phone'])
                      ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(phone,''),' ',''),'-',''),'+',''),'(','') LIKE ?", ['%' . $norm]);
                })
                ->first();
        }
        // 2. Try composite key: name + email (if both provided)
        if (! $existing && ! empty($customer['name']) && ! empty($customer['email'])) {
            $existing = DB::table('customers')
                ->where('name', $customer['name'])
                ->where('email', $customer['email'])
                ->where('company_id', $this->companyId)
                ->where('del_status', 'Live')
                ->first();
        }
        // 3. Try name + phone composite (if phone provided but no match above)
        if (! $existing && ! empty($customer['name']) && ! empty($customer['phone'])) {
            $existing = DB::table('customers')
                ->where('name', $customer['name'])
                ->where('phone', $customer['phone'])
                ->where('company_id', $this->companyId)
                ->where('del_status', 'Live')
                ->first();
        }

        $data = [
            'name' => $customer['name'] ?? '',
            'email' => $customer['email'] ?? null,
            'phone' => $customer['phone'] ?? null,
            'address' => $customer['address'] ?? null,
            'city' => $customer['city'] ?? null,
            'postal_code' => $customer['postal_code'] ?? null,
            'gst_number' => $customer['gst_number'] ?? null,
            'opening_balance' => $customer['opening_balance'] ?? 0,
            'opening_balance_type' => $customer['opening_balance_type'] ?? 'Dr',
            'credit_limit' => $customer['credit_limit'] ?? 0,
            'customer_type' => $customer['customer_type'] ?? 'B2C',
            'business_type' => $customer['business_type'] ?? 'B2C',
            'same_or_diff_state' => $customer['same_or_diff_state'] ?? null,
            'loyalty_point' => $customer['loyalty_point'] ?? 0,
            'is_installment_customer' => $customer['is_installment_customer'] ?? 'No',
            'discount' => $customer['discount'] ?? null,
            'date_of_birth' => $customer['date_of_birth'] ?? null,
            'date_of_anniversary' => $customer['date_of_anniversary'] ?? null,
            'work_address' => $customer['work_address'] ?? null,
            'guarantor_name' => $customer['guarantor_name'] ?? null,
            'guarantor_mobile' => $customer['guarantor_mobile'] ?? null,
            'updated_at' => $now,
        ];

        if ($existing) {
            // ═══ TWO-WAY DELETE (E2) ═══
            if (($customer['del_status'] ?? 'Live') === 'Deleted') {
                DB::table('customers')->where('id', $existing->id)->update([
                    'del_status' => 'Deleted',
                    'updated_at' => $now,
                ]);
                return ['local_code' => $customer['local_code'], 'server_id' => (int) $existing->id, 'created' => false, 'deleted' => true];
            }
            // ═══ LAST-WRITE-WINS (E1) — consistent LWW har mutable entity pe ═══
            if (! $this->lwwApply($customer, $existing, 'customers', (string) ($customer['phone'] ?? $customer['local_code'] ?? ''), (int) $existing->id)) {
                return ['local_code' => $customer['local_code'], 'server_id' => (int) $existing->id, 'created' => false, 'conflict' => 'skipped_older'];
            }
            DB::table('customers')->where('id', $existing->id)->update($data);
            $this->applyVersion('customers', (int) $existing->id, $customer); // FIX 2
            return ['local_code' => $customer['local_code'], 'server_id' => (int) $existing->id, 'created' => false];
        }

        $data['user_id'] = $this->userId;
        $data['company_id'] = $this->companyId;
        $data['del_status'] = 'Live';
        $data['created_at'] = $now;

        $id = DB::table('customers')->insertGetId($data);

        return ['local_code' => $customer['local_code'], 'server_id' => (int) $id, 'created' => true];
    }

    private function pushSupplier(array $supplier): array
    {
        $now = now()->toDateTimeString();

        $existing = null;
        // 1. Try phone match (strongest dedup) — normalized variants bhi match hon
        if (! empty($supplier['phone'])) {
            $norm = $this->normalizePhone($supplier['phone']);
            $existing = DB::table('suppliers')
                ->where('company_id', $this->companyId)
                ->where('del_status', 'Live')
                ->where(function ($q) use ($supplier, $norm) {
                    $q->where('phone', $supplier['phone'])
                      ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(phone,''),' ',''),'-',''),'+',''),'(','') LIKE ?", ['%' . $norm]);
                })
                ->first();
        }
        // 2. Try composite key: name + company_name (if both provided)
        if (! $existing && ! empty($supplier['name']) && ! empty($supplier['company_name'])) {
            $existing = DB::table('suppliers')
                ->where('name', $supplier['name'])
                ->where('company_name', $supplier['company_name'])
                ->where('company_id', $this->companyId)
                ->where('del_status', 'Live')
                ->first();
        }
        // 3. Try name + email composite (if email provided)
        if (! $existing && ! empty($supplier['name']) && ! empty($supplier['email'])) {
            $existing = DB::table('suppliers')
                ->where('name', $supplier['name'])
                ->where('email', $supplier['email'])
                ->where('company_id', $this->companyId)
                ->where('del_status', 'Live')
                ->first();
        }

        $data = [
            'name' => $supplier['name'] ?? '',
            'company_name' => $supplier['company_name'] ?? null,
            'email' => $supplier['email'] ?? null,
            'phone' => $supplier['phone'] ?? null,
            'address' => $supplier['address'] ?? null,
            'city' => $supplier['city'] ?? null,
            'state' => $supplier['state'] ?? null,
            'postal_code' => $supplier['postal_code'] ?? null,
            'country' => $supplier['country'] ?? null,
            'gst_number' => $supplier['gst_number'] ?? null,
            'opening_balance' => $supplier['opening_balance'] ?? 0,
            'opening_balance_type' => $supplier['opening_balance_type'] ?? 'Dr',
            'credit_limit' => $supplier['credit_limit'] ?? 0,
            'description' => $supplier['description'] ?? null,
            'contact_person' => $supplier['contact_person'] ?? null,
            'updated_at' => $now,
        ];

        if ($existing) {
            // ═══ TWO-WAY DELETE (E2) ═══
            if (($supplier['del_status'] ?? 'Live') === 'Deleted') {
                DB::table('suppliers')->where('id', $existing->id)->update([
                    'del_status' => 'Deleted',
                    'updated_at' => $now,
                ]);
                return ['local_code' => $supplier['local_code'], 'server_id' => (int) $existing->id, 'created' => false, 'deleted' => true];
            }
            // ═══ LAST-WRITE-WINS (E1) — consistent LWW har mutable entity pe ═══
            if (! $this->lwwApply($supplier, $existing, 'suppliers', (string) ($supplier['phone'] ?? $supplier['name'] ?? ''), (int) $existing->id)) {
                return ['local_code' => $supplier['local_code'], 'server_id' => (int) $existing->id, 'created' => false, 'conflict' => 'skipped_older'];
            }
            DB::table('suppliers')->where('id', $existing->id)->update($data);
            $this->applyVersion('suppliers', (int) $existing->id, $supplier); // FIX 2
            return ['local_code' => $supplier['local_code'], 'server_id' => (int) $existing->id, 'created' => false];
        }

        $data['user_id'] = $this->userId;
        $data['company_id'] = $this->companyId;
        $data['del_status'] = 'Live';
        $data['created_at'] = $now;

        $id = DB::table('suppliers')->insertGetId($data);

        return ['local_code' => $supplier['local_code'], 'server_id' => (int) $id, 'created' => true];
    }

    private function pushSale(array $sale): array
    {
        $now = now()->toDateTimeString();

        $items = $sale['items'] ?? [];
        if (count($items) === 0) {
            return ['local_id' => $sale['local_id'], 'server_id' => null, 'error' => 'No items'];
        }

        // Multi-counter idempotency: ye local sale (device + VchCode) pehle hi push
        // ho chuki hai? Retry/ack-loss pe duplicate sale na bane.
        $mappedSale = $this->findMapping('sales', 0, (string) ($sale['local_id'] ?? ''));
        if ($mappedSale && DB::table('sales')->where('id', $mappedSale->server_id)->where('del_status', 'Live')->exists()) {
            return ['local_id' => $sale['local_id'], 'server_id' => (int) $mappedSale->server_id, 'created' => false];
        }

        // Map payment method names -> ids (Cash/UPI/Card/Bank Transfer)
        $paymentMethodMap = [];
        foreach (DB::table('payment_methods')->where('del_status', 'Live')->get(['id', 'name']) as $pm) {
            $key = strtolower(trim($pm->name));
            $paymentMethodMap[$key] = (int) $pm->id;
            if ($key === 'credit card') {
                $paymentMethodMap['card'] = (int) $pm->id;
            }
            if ($key === 'debit card') {
                $paymentMethodMap['card'] = (int) $pm->id;
            }
        }
        $paymentMethodMap['cash'] = $paymentMethodMap['cash'] ?? 1;

        $saleDate = $sale['sale_date'] ?? date('Y-m-d');
        $saleDate = date('Y-m-d', strtotime($saleDate));

        $customerId = null;
        if (! empty($sale['customer_id'])) {
            $customerId = (int) $sale['customer_id'];
        }

        $invoiceNo = $sale['invoice_no'] ?? $sale['local_id'];

        // Desktop ka offline counter server se peeche ho sakta hai (pull ke baad web pe
        // naye sales ban gaye) — invoice_no collide ho to server counter se naya number de do.
        $invoiceExists = DB::table('sales')
            ->where('company_id', $this->companyId)
            ->where('invoice_no', $invoiceNo)
            ->exists();
        if ($invoiceExists) {
            $prefix = 'SALE-' . date('Y') . '-';
            $maxSeq = DB::table('sales')
                ->where('company_id', $this->companyId)
                ->where('invoice_no', 'like', $prefix . '%')
                ->pluck('invoice_no')
                ->map(fn ($n) => (int) preg_replace('/^SALE-\d+-[^-]+-/', '', (string) $n) ?: 0)
                ->max();
            $invoiceNo = $prefix . 'C' . $this->outletId . '-' . str_pad(($maxSeq ?? 0) + 1, 6, '0', STR_PAD_LEFT);
        }

        $saleId = DB::table('sales')->insertGetId([
            'invoice_no' => $invoiceNo,
            'sale_no' => $invoiceNo,
            'sale_date' => $saleDate,
            'date_time' => $sale['date_time'] ?? ($saleDate . ' ' . date('H:i:s')),
            'order_date_time' => $sale['date_time'] ?? null,
            'customer_id' => $customerId,
            'employee_id' => $sale['employee_id'] ?? null,
            'sub_total' => $sale['sub_total'] ?? 0,
            'given_amount' => $sale['given_amount'] ?? $sale['total_payable'] ?? 0,
            'paid_amount' => $sale['paid_amount'] ?? $sale['total_payable'] ?? 0,
            'change_amount' => $sale['change_amount'] ?? 0,
            'due_amount' => $sale['due_amount'] ?? 0,
            'disc' => $sale['disc'] ?? 0,
            'disc_actual' => $sale['disc'] ?? 0,
            'vat' => $sale['vat'] ?? 0,
            'rounding' => $sale['rounding'] ?? 0,
            'total_payable' => $sale['total_payable'] ?? 0,
            'grand_total' => $sale['grand_total'] ?? $sale['total_payable'] ?? 0,
            'mrp_total' => $sale['mrp_total'] ?? 0,
            'savings' => $sale['savings'] ?? 0,
            'sale_vat_objects' => $sale['sale_vat_objects'] ?? null,
            'note' => $sale['note'] ?? 'Synced from desktop POS',
            'user_id' => $this->userId,
            'outlet_id' => $this->outletId,
            'company_id' => $this->companyId,
            'del_status' => 'Live',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Items + stock deduction
        foreach ($items as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            $qty = (float) ($line['qty'] ?? 0);

            // Fallback: match item by code (for items created on desktop)
            if ($itemId === 0 && ! empty($line['item_code'])) {
                $matched = DB::table('items')
                    ->where('code', $line['item_code'])
                    ->where('company_id', $this->companyId)
                    ->where('del_status', 'Live')
                    ->first();
                if ($matched) {
                    $itemId = (int) $matched->id;
                }
            }

            if ($itemId === 0) {
                continue;
            }

            DB::table('sale_details')->insert([
                'sales_id' => $saleId,
                'item_id' => $itemId,
                'qty' => $qty,
                'menu_price_without_discount' => $line['menu_unit_price'] ?? 0,
                'menu_price_with_discount' => ($line['menu_unit_price'] ?? 0) - ($line['discount_amount'] ?? 0),
                'menu_unit_price' => $line['menu_unit_price'] ?? 0,
                'purchase_price' => $line['purchase_price'] ?? 0,
                'menu_vat_percentage' => $line['menu_vat_percentage'] ?? 0,
                'item_tax_amount' => $line['item_tax_amount'] ?? 0,
                'menu_taxes' => isset($line['menu_taxes']) && $line['menu_taxes'] !== null
                    ? (is_array($line['menu_taxes']) ? json_encode($line['menu_taxes']) : $line['menu_taxes'])
                    : null,
                'menu_discount_value' => $line['discount_amount'] ?? 0,
                'discount_amount' => $line['discount_amount'] ?? 0,
                'discount_type' => $line['discount_type'] ?? 'fixed',
                'is_promo_item' => $line['is_promo_item'] ?? 'No',
                'user_id' => $this->userId,
                'outlet_id' => $this->outletId,
                'company_id' => $this->companyId,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Keep items.stock_quantity in sync as well
            if ($itemId > 0 && $qty > 0) {
                DB::table('items')
                    ->where('id', $itemId)
                    ->where('company_id', $this->companyId)
                    ->update(['stock_quantity' => DB::raw("stock_quantity - {$qty}")]);
            }
        }

        // Payments
        foreach ($sale['payments'] ?? [] as $payment) {
            $paymentId = null;
            if (! empty($payment['payment_id'])) {
                $paymentId = (int) $payment['payment_id'];
            } elseif (! empty($payment['payment_name'])) {
                $paymentId = $paymentMethodMap[strtolower(trim($payment['payment_name']))] ?? null;
            }
            if (! $paymentId) {
                $paymentId = $paymentMethodMap['cash'] ?? 1;
            }

            DB::table('sale_payments')->insert([
                'sale_id' => $saleId,
                'payment_id' => $paymentId,
                'date' => $saleDate,
                'amount' => $payment['amount'] ?? 0,
                'note' => $payment['note'] ?? null,
                'user_id' => $this->userId,
                'outlet_id' => $this->outletId,
                'company_id' => $this->companyId,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->mapLocal('sales', 0, (string) ($sale['local_id'] ?? ''), (int) $saleId);

        return ['local_id' => $sale['local_id'], 'server_id' => (int) $saleId, 'created' => true];
    }

    /**
     * Upsert small config entities (categories/brands/units/racks/variations).
     * Payload: configs: { categories: [{local_id,name,description}], ... }
     */
    private function pushConfigs(array $configs): array
    {
        $results = [];
        $handlers = [
            'categories' => 'item_categories',
            'brands' => 'brands',
            'units' => 'units',
            'racks' => 'racks',
            'variations' => 'variations',
            'expense_categories' => 'expense_categories',
        ];

        foreach ($handlers as $key => $table) {
            $results[$key] = [];
            foreach ($configs[$key] ?? [] as $item) {
                $results[$key][] = $this->pushConfigItem($table, $key, $item);
            }
        }

        return $results;
    }

    /**
     * ═══ LAST-WRITE-WINS (E1) ═══
     * Apply the incoming edit only if it is newer than the existing row.
     * Older edits are skipped and logged to sync_conflicts for the audit trail.
     * Returns true when the caller should apply the update.
     */
    private function lwwApply(array $incoming, ?object $existing, string $entityType, string $entityKey, ?int $serverId): bool
    {
        if (! $existing) {
            return true; // nayi row — apply
        }

        // ═══ FIX 2: LOGICAL CLOCK (sync_version) — desktop aur server clocks
        // 1-2 second bhi mismatch hon to updated_at galat winner chun sakta hai.
        // Version integer compare: jo zyada version hai wo jeeta — clock se koi
        // fark nahi. Dono taraf version missing ho tab hi updated_at fallback. ═══
        $incomingVersion = isset($incoming['sync_version']) ? (int) $incoming['sync_version'] : 0;
        $existingVersion = ! empty($existing->sync_version) ? (int) $existing->sync_version : 0;

        if ($incomingVersion > 0 && $existingVersion > 0) {
            if ($incomingVersion < $existingVersion) {
                $this->logConflict($entityType, $entityKey, $serverId, $incoming, $existing, 'server_won');
                return false; // purana version — server ki value jeeti
            }
            if ($incomingVersion > $existingVersion) {
                return true; // naya version — jeeta
            }
            // Version barabar (dono counters ne same base version se edit kiya) —
            // tie-break updated_at se, phir purana fallback.
        }

        // Fallback: version columns migrate hone se pehle wale devices ke liye.
        // Desktop SQLite `datetime('now')` = UTC string; server `now()` =
        // Asia/Kolkata string. Sirf strtotime() karne se same wall-clock par
        // UTC (chhota) < IST (bada) ho jata hai aur desktop ka naya edit galat
        // tarah "purana" declare ho kar skip ho jata hai — isliye dono ko apne
        // apne timezone se parse karo.
        $incomingTs = isset($incoming['updated_at']) && strtotime($incoming['updated_at']) !== false
            ? \Carbon\Carbon::parse($incoming['updated_at'], 'UTC')->getTimestamp() : null;
        $existingTs = ! empty($existing->updated_at) && strtotime($existing->updated_at) !== false
            ? \Carbon\Carbon::parse($existing->updated_at, config('app.timezone', 'Asia/Kolkata'))->getTimestamp() : null;

        if ($incomingTs !== null && $existingTs !== null && $incomingTs <= $existingTs) {
            $this->logConflict($entityType, $entityKey, $serverId, $incoming, $existing, 'server_won');
            return false; // purana edit — server ki value jeeti
        }
        return true;
    }

    /**
     * FIX 2: Update path par sync_version ko logical clock ki tarah aage badhao.
     * Incoming version jeet chuka hai (lwwApply) to server row par bhi wahi
     * version set karo — taaki dusre counters ka purana edit haar jaye.
     */
    private function applyVersion(string $table, int $serverId, array $incoming): void
    {
        $version = (int) ($incoming['sync_version'] ?? 0);
        if ($version <= 0) {
            return;
        }
        try {
            if (Schema::hasColumn($table, 'sync_version')) {
                DB::table($table)->where('id', $serverId)->update(['sync_version' => $version]);
            }
        } catch (\Throwable $e) {
            \Log::warning("applyVersion failed for {$table}: ".$e->getMessage());
        }
    }

    /**
     * Write a conflict entry to sync_conflicts (audit trail).
     */
    private function logConflict(string $entityType, string $entityKey, ?int $serverId, array $incoming, $existing, string $resolution): void
    {
        try {
            DB::table('sync_conflicts')->insert([
                'company_id'          => $this->companyId,
                'outlet_id'           => $this->outletId,
                'device_id'           => $this->deviceId,
                'entity_type'         => $entityType,
                'entity_key'          => mb_substr($entityKey, 0, 255),
                'server_id'           => $serverId,
                'incoming_json'       => json_encode($incoming),
                'existing_json'       => json_encode($existing ? (array) $existing : null),
                'incoming_updated_at' => $incoming['updated_at'] ?? null,
                'existing_updated_at' => $existing->updated_at ?? null,
                'resolution'          => $resolution,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('logConflict failed: '.$e->getMessage());
        }
    }

    private function pushConfigItem(string $table, string $key, array $item): array
    {
        $now = now()->toDateTimeString();
        $localId = $item['local_id'] ?? null;

        if ($key === 'variations') {
            $nameCol = 'variation_name';
            $name = trim((string) ($item['variation_name'] ?? ''));
        } else {
            $nameCol = $key === 'units' ? 'unit_name' : 'name';
            $name = trim((string) ($item['name'] ?? ''));
        }
        if ($name === '') {
            return ['local_id' => $localId, 'server_id' => null, 'error' => 'Name required'];
        }

        $existing = DB::table($table)
            ->whereRaw("LOWER({$nameCol}) = LOWER(?)", [$name])
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live')
            ->first();

        if ($key === 'variations') {
            $rawValue = $item['variation_value'] ?? [];
            $value = is_string($rawValue) ? json_decode($rawValue, true) : $rawValue;
            $value = is_array($value) ? array_values(array_filter($value, fn ($v) => trim((string) $v) !== '')) : [];
            $data = [
                'variation_name' => $name,
                'variation_value' => json_encode($value),
                'updated_at' => $now,
            ];
        } else {
            $data = [
                $nameCol => $name,
                'description' => $item['description'] ?? null,
                'updated_at' => $now,
            ];
        }

        if ($existing) {
            // ═══ TWO-WAY DELETE (E2): incoming tombstone → mark existing deleted ═══
            if (($item['del_status'] ?? 'Live') === 'Deleted') {
                DB::table($table)->where('id', $existing->id)->update([
                    'del_status' => 'Deleted',
                    'updated_at' => $now,
                ]);
                return ['local_id' => $localId, 'server_id' => (int) $existing->id, 'created' => false, 'deleted' => true];
            }
            // ═══ LAST-WRITE-WINS (E1): purana edit skip + log ═══
            if (! $this->lwwApply($item, $existing, $table, $name, (int) $existing->id)) {
                return ['local_id' => $localId, 'server_id' => (int) $existing->id, 'created' => false, 'conflict' => 'skipped_older'];
            }
            DB::table($table)->where('id', $existing->id)->update($data);
            $this->applyVersion($table, (int) $existing->id, $item); // FIX 2
            return ['local_id' => $localId, 'server_id' => (int) $existing->id, 'created' => false];
        }

        $data['user_id'] = $this->userId;
        $data['company_id'] = $this->companyId;
        $data['del_status'] = 'Live';
        $data['created_at'] = $now;

        $id = DB::table($table)->insertGetId($data);

        return ['local_id' => $localId, 'server_id' => (int) $id, 'created' => true];
    }

    /**
     * Upsert an outlet from the desktop. Matches by server_id first (so desktop
     * edits update the same server row), then falls back to outlet_code within
     * the company. Returns local_code + server_id for the desktop to mark synced.
     */
    private function pushOutlet(array $outlet): array
    {
        $now = now()->toDateTimeString();
        $code = $outlet['outlet_code'] ?? $outlet['local_code'] ?? null;
        $serverId = isset($outlet['server_id']) ? (int) $outlet['server_id'] : 0;

        $existing = null;
        if ($serverId > 0) {
            $existing = DB::table('outlets')
                ->where('id', $serverId)
                ->where('company_id', $this->companyId)
                ->first();
        }
        if (! $existing && ! empty($code)) {
            $existing = DB::table('outlets')
                ->where('outlet_code', $code)
                ->where('company_id', $this->companyId)
                ->where('del_status', 'Live')
                ->first();
        }

        $stateId = $outlet['state_id'] ?? null;
        if (is_numeric($stateId) && (int) $stateId > 0) {
            $stateId = (int) $stateId;
        } else {
            $stateId = null;
        }

        $data = [
            'outlet_code' => $code,
            'outlet_name' => $outlet['outlet_name'] ?? $outlet['name'] ?? null,
            'name' => $outlet['name'] ?? $outlet['outlet_name'] ?? null,
            'phone' => $outlet['phone'] ?? null,
            'email' => $outlet['email'] ?? null,
            'address' => $outlet['address'] ?? null,
            'state_id' => $stateId,
            'active_status' => $outlet['active_status'] ?? 'Active',
            'is_active' => (int) ($outlet['is_active'] ?? 1),
            'del_status' => 'Live',
            'updated_at' => $now,
        ];

        if ($existing) {
            // ═══ TWO-WAY DELETE (E2) ═══
            if (($outlet['del_status'] ?? 'Live') === 'Deleted') {
                DB::table('outlets')->where('id', $existing->id)->update([
                    'del_status' => 'Deleted',
                    'updated_at' => $now,
                ]);
                return ['local_code' => $code, 'server_id' => (int) $existing->id, 'created' => false, 'deleted' => true];
            }
            // ═══ LAST-WRITE-WINS (E1) ═══
            if (! $this->lwwApply($outlet, $existing, 'outlets', (string) ($code ?? ''), (int) $existing->id)) {
                return ['local_code' => $code, 'server_id' => (int) $existing->id, 'created' => false, 'conflict' => 'skipped_older'];
            }
            DB::table('outlets')->where('id', $existing->id)->update($data);
            return ['local_code' => $code, 'server_id' => (int) $existing->id, 'created' => false];
        }

        $data['user_id'] = $this->userId;
        $data['company_id'] = $this->companyId;
        $data['created_at'] = $now;

        $id = DB::table('outlets')->insertGetId($data);

        return ['local_code' => $code, 'server_id' => (int) $id, 'created' => true];
    }

    private function pushDamage(array $damage): array
    {
        $now = now()->toDateTimeString();
        $localId = $damage['local_id'] ?? null;
        try {
            if (!empty($localId)) {
                $mapped = $this->findMapping('damages', $localId);
                if ($mapped && DB::table('damages')->where('id', $mapped->server_id)->where('del_status', 'Live')->exists())
                    return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id, 'created' => false];
            }
            $damageId = DB::table('damages')->insertGetId([
                'reference_no' => $damage['reference_no'] ?? null,
                'date'         => $damage['date'] ?? date('Y-m-d'),
                'total_loss'   => (float) ($damage['total_loss'] ?? 0),
                'note'         => $damage['note'] ?? null,
                'employee_id'  => !empty($damage['employee_id']) ? (int) $damage['employee_id'] : null,
                'user_id'      => $this->userId,
                'outlet_id'    => $this->outletId,
                'company_id'   => $this->companyId,
                'del_status'   => 'Live',
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
            foreach ($damage['items'] ?? [] as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                if ($itemId === 0) continue;
                DB::table('damage_details')->insert([
                    'damage_id'           => $damageId,
                    'item_id'             => $itemId,
                    'date'                => $line['date'] ?? date('Y-m-d'),
                    'damage_quantity'     => (float) ($line['damage_quantity'] ?? 0),
                    'last_purchase_price' => (float) ($line['last_purchase_price'] ?? 0),
                    'loss_amount'         => (float) ($line['loss_amount'] ?? 0),
                    'total_amount'        => (float) ($line['total_amount'] ?? 0),
                    'user_id'             => $this->userId,
                    'outlet_id'           => $this->outletId,
                    'company_id'          => $this->companyId,
                    'del_status'          => 'Live',
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ]);
            }
            if (!empty($localId)) $this->mapLocal('damages', $localId, '', (int) $damageId);
            return ['local_id' => $localId, 'server_id' => (int) $damageId, 'created' => true];
        } catch (\Throwable $e) {
            return ['local_id' => $localId, 'error' => $e->getMessage()];
        }
    }

    private function pushTransfer(array $transfer): array
    {
        $now = now()->toDateTimeString();
        $localId = $transfer['local_id'] ?? null;
        try {
            if (!empty($localId)) {
                $mapped = $this->findMapping('transfers', $localId);
                if ($mapped && DB::table('transfers')->where('id', $mapped->server_id)->where('del_status', 'Live')->exists())
                    return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id, 'created' => false];
            }
            $fromOutletId = !empty($transfer['from_outlet_id']) ? (int) $transfer['from_outlet_id'] : $this->outletId;
            $toOutletId   = !empty($transfer['to_outlet_id'])   ? (int) $transfer['to_outlet_id']   : null;
            $transferId = DB::table('transfers')->insertGetId([
                'reference_no'   => $transfer['reference_no'] ?? null,
                'date'           => $transfer['date'] ?? date('Y-m-d'),
                'from_outlet_id' => $fromOutletId,
                'to_outlet_id'   => $toOutletId,
                'note'           => $transfer['note'] ?? null,
                'user_id'        => $this->userId,
                'outlet_id'      => $this->outletId,
                'company_id'     => $this->companyId,
                'del_status'     => 'Live',
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            foreach ($transfer['items'] ?? [] as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                if ($itemId === 0) continue;
                DB::table('transfer_details')->insert([
                    'transfer_id' => $transferId,
                    'item_id'     => $itemId,
                    'quantity'    => (float) ($line['quantity'] ?? 0),
                    'unit_price'  => (float) ($line['unit_price'] ?? 0),
                    'total'       => (float) ($line['total'] ?? 0),
                    'user_id'     => $this->userId,
                    'outlet_id'   => $this->outletId,
                    'company_id'  => $this->companyId,
                    'del_status'  => 'Live',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
            if (!empty($localId)) $this->mapLocal('transfers', $localId, '', (int) $transferId);
            return ['local_id' => $localId, 'server_id' => (int) $transferId, 'created' => true];
        } catch (\Throwable $e) {
            return ['local_id' => $localId, 'error' => $e->getMessage()];
        }
    }

    private function pushPurchase(array $purchase): array
    {
        $now = now()->toDateTimeString();
        $localId = $purchase['local_id'] ?? null;
        $items = $purchase['items'] ?? [];
        if (count($items) === 0) {
            return ['local_id' => $localId, 'server_id' => null, 'error' => 'No items'];
        }

        // Multi-counter idempotency — retry/ack-loss pe duplicate purchase na bane
        $mapped = $this->findMapping('purchases', $localId);
        if ($mapped && DB::table('purchases')->where('id', $mapped->server_id)->where('del_status', 'Live')->exists()) {
            return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id, 'created' => false];
        }

        $supplierId = ! empty($purchase['supplier_id']) && is_numeric($purchase['supplier_id']) ? (int) $purchase['supplier_id'] : null;
        if ($supplierId && ! DB::table('suppliers')->where('id', $supplierId)->where('company_id', $this->companyId)->exists()) {
            $supplierId = null;
        }

        $purchaseId = DB::table('purchases')->insertGetId([
            'reference_no' => $purchase['reference_no'] ?? null,
            'invoice_no' => $purchase['invoice_no'] ?? null,
            'supplier_id' => $supplierId,
            'date' => date('Y-m-d', strtotime($purchase['date'] ?? date('Y-m-d'))),
            'grand_total' => (float) ($purchase['grand_total'] ?? 0),
            'paid' => (float) ($purchase['paid'] ?? 0),
            'due_amount' => (float) ($purchase['due_amount'] ?? 0),
            'note' => $purchase['note'] ?? 'Synced from desktop POS',
            'discount' => $purchase['discount'] ?? null,
            'status' => $purchase['status'] ?? 'Pending',
            'user_id' => $this->userId,
            'outlet_id' => $this->outletId,
            'company_id' => $this->companyId,
            'del_status' => 'Live',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($items as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            if ($itemId === 0) continue;
            DB::table('purchase_details')->insert([
                'purchase_id' => $purchaseId,
                'item_id' => $itemId,
                'item_type' => $line['item_type'] ?? null,
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'quantity_amount' => (float) ($line['quantity'] ?? 0),
                'total' => (float) ($line['total'] ?? 0),
                'user_id' => $this->userId,
                'outlet_id' => $this->outletId,
                'company_id' => $this->companyId,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($purchase['payments'] ?? [] as $pm) {
            DB::table('purchase_payments')->insert([
                'purchase_id' => $purchaseId,
                'payment_id' => ! empty($pm['payment_id']) ? (int) $pm['payment_id'] : 1,
                'date' => $pm['date'] ?? date('Y-m-d'),
                'amount' => (float) ($pm['amount'] ?? 0),
                'reference_no' => $pm['reference_no'] ?? null,
                'outlet_id' => $this->outletId,
                'user_id' => $this->userId,
                'company_id' => $this->companyId,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->mapLocal('purchases', $localId, '', (int) $purchaseId);

        return ['local_id' => $localId, 'server_id' => (int) $purchaseId, 'created' => true];
    }

    private function pushPurchaseReturn(array $return): array
    {
        $now = now()->toDateTimeString();
        $localId = $return['local_id'] ?? null;
        $items = $return['items'] ?? [];
        if (count($items) === 0) {
            return ['local_id' => $localId, 'server_id' => null, 'error' => 'No items'];
        }

        // Multi-counter idempotency — retry pe duplicate purchase return na bane
        $mapped = $this->findMapping('purchase_returns', $localId);
        if ($mapped && DB::table('purchase_returns')->where('id', $mapped->server_id)->where('del_status', 'Live')->exists()) {
            return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id, 'created' => false];
        }

        $supplierId = ! empty($return['supplier_id']) && is_numeric($return['supplier_id']) ? (int) $return['supplier_id'] : null;
        if ($supplierId && ! DB::table('suppliers')->where('id', $supplierId)->where('company_id', $this->companyId)->exists()) {
            $supplierId = null;
        }

        $paymentMethodId = ! empty($return['payment_method_id']) ? (int) $return['payment_method_id'] : 1;
        if (! DB::table('payment_methods')->where('id', $paymentMethodId)->where('company_id', $this->companyId)->exists()) {
            $paymentMethodId = 1;
        }

        $returnId = DB::table('purchase_returns')->insertGetId([
            'reference_no' => $return['reference_no'] ?? null,
            'pur_ref_no' => $return['pur_ref_no'] ?? null,
            'supplier_id' => $supplierId,
            'date' => date('Y-m-d', strtotime($return['date'] ?? date('Y-m-d'))),
            'purchase_date' => ! empty($return['purchase_date']) ? date('Y-m-d', strtotime($return['purchase_date'])) : null,
            'return_status' => $return['return_status'] ?? '',
            'total_return_amount' => (float) ($return['total_return_amount'] ?? 0),
            'payment_method_id' => $paymentMethodId,
            'note' => $return['note'] ?? 'Synced from desktop POS',
            'user_id' => $this->userId,
            'outlet_id' => $this->outletId,
            'company_id' => $this->companyId,
            'del_status' => 'Live',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($items as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            if ($itemId === 0) continue;
            DB::table('purchase_return_details')->insert([
                'pur_return_id' => $returnId,
                'item_id' => $itemId,
                'return_quantity_amount' => (float) ($line['quantity'] ?? 0),
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'total' => (float) ($line['total'] ?? 0),
                'return_note' => $line['return_note'] ?? null,
                'user_id' => $this->userId,
                'outlet_id' => $this->outletId,
                'company_id' => $this->companyId,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->mapLocal('purchase_returns', $localId, '', (int) $returnId);

        return ['local_id' => $localId, 'server_id' => (int) $returnId, 'created' => true];
    }

    private function pushSupplierPayment(array $payment): array
    {
        $now = now()->toDateTimeString();
        $localId = $payment['local_id'] ?? null;
        $mapped = $this->findMapping('supplier_payments', $localId);
        if ($mapped && DB::table('supplier_payments')->where('id', $mapped->server_id)->exists()) {
            return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id, 'created' => false];
        }
        $supplierId = ! empty($payment['supplier_id']) && is_numeric($payment['supplier_id']) ? (int) $payment['supplier_id'] : null;
        if (! $supplierId) {
            return ['local_id' => $localId, 'server_id' => null, 'error' => 'Supplier required'];
        }

        $id = DB::table('supplier_payments')->insertGetId([
            'reference_no' => $payment['reference_no'] ?? null,
            'supplier_id' => $supplierId,
            'payment_method_id' => ! empty($payment['payment_method_id']) ? (int) $payment['payment_method_id'] : 1,
            'amount' => (float) ($payment['amount'] ?? 0),
            'date' => date('Y-m-d', strtotime($payment['date'] ?? date('Y-m-d'))),
            'note' => $payment['note'] ?? 'Synced from desktop POS',
            'user_id' => $this->userId,
            'outlet_id' => $this->outletId,
            'company_id' => $this->companyId,
            'del_status' => 'Live',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->mapLocal('supplier_payments', $localId, '', (int) $id);

        return ['local_id' => $localId, 'server_id' => (int) $id, 'created' => true];
    }

    private function pushExpense(array $expense): array
    {
        $now = now()->toDateTimeString();
        $localId = $expense['local_id'] ?? null;
        $mapped = $this->findMapping('expenses', $localId);
        if ($mapped && DB::table('expenses')->where('id', $mapped->server_id)->exists()) {
            // ── UPDATE: mapped row exists — edit ki hui expense yahan update hoti hai ──
            $categoryId = ! empty($expense['category_id']) && is_numeric($expense['category_id']) ? (int) $expense['category_id'] : null;
            DB::table('expenses')->where('id', $mapped->server_id)->update([
                'reference_no' => $expense['reference_no'] ?? null,
                'date' => date('Y-m-d', strtotime($expense['date'] ?? date('Y-m-d'))),
                'category_id' => $categoryId,
                'payment_method_id' => ! empty($expense['payment_method_id']) ? (int) $expense['payment_method_id'] : 1,
                'amount' => (float) ($expense['amount'] ?? 0),
                'note' => $expense['note'] ?? 'Synced from desktop POS',
                'employee_id' => ! empty($expense['employee_id']) ? (int) $expense['employee_id'] : null,
                'updated_at' => $now,
            ]);
            return ['local_id' => $localId, 'server_id' => (int) $mapped->server_id, 'created' => false];
        }
        $categoryId = ! empty($expense['category_id']) && is_numeric($expense['category_id']) ? (int) $expense['category_id'] : null;
        if (! $categoryId) {
            return ['local_id' => $localId, 'server_id' => null, 'error' => 'Category required'];
        }

        $id = DB::table('expenses')->insertGetId([
            'reference_no' => $expense['reference_no'] ?? null,
            'date' => date('Y-m-d', strtotime($expense['date'] ?? date('Y-m-d'))),
            'category_id' => $categoryId,
            'payment_method_id' => ! empty($expense['payment_method_id']) ? (int) $expense['payment_method_id'] : 1,
            'amount' => (float) ($expense['amount'] ?? 0),
            'note' => $expense['note'] ?? 'Synced from desktop POS',
            'employee_id' => ! empty($expense['employee_id']) ? (int) $expense['employee_id'] : null,
            'user_id' => $this->userId,
            'outlet_id' => $this->outletId,
            'company_id' => $this->companyId,
            'del_status' => 'Live',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->mapLocal('expenses', $localId, '', (int) $id);

        return ['local_id' => $localId, 'server_id' => (int) $id, 'created' => true];
    }



    // ═══════════════════ GENERIC ENTITY PUSH (/api/sync/push-entity) ═══════════════════

    // ═══════════ MULTI-COUNTER MAPPING HELPERS ═══════════
    // (device_id + local key se mapping — iske bina alag counters ek dusre ki
    // mapping clobber karke server par duplicate rows bana dete the)

    private function findMapping(string $entityType, $localId = 0, string $localRef = ''): ?object
    {
        $schema = DB::getSchemaBuilder();
        $hasDevice   = $schema->hasColumn('sync_local_mappings', 'device_id');
        $hasLocalRef = $schema->hasColumn('sync_local_mappings', 'local_ref');

        $q = DB::table('sync_local_mappings')
            ->where('entity_type', $entityType)
            ->where('company_id', $this->companyId)
            ->where('outlet_id', $this->outletId);
        if ($hasDevice) {
            $q->where('device_id', $this->deviceId);
        }
        if ($localRef !== '' && $hasLocalRef) {
            $q->where('local_ref', $localRef);
        } else {
            $q->where('local_id', (int) $localId);
        }

        return $q->first();
    }

    private function mapLocal(string $entityType, $localId, string $localRef, int $serverId): void
    {
        try {
            $schema = DB::getSchemaBuilder();
            $hasDevice   = $schema->hasColumn('sync_local_mappings', 'device_id');
            $hasLocalRef = $schema->hasColumn('sync_local_mappings', 'local_ref');

            $match = [
                'entity_type' => $entityType,
                'company_id'  => $this->companyId,
                'outlet_id'   => $this->outletId,
            ];
            if ($hasDevice) {
                $match['device_id'] = $this->deviceId;
            }
            if ($hasLocalRef) {
                $match['local_ref'] = $localRef;
            }
            $match['local_id'] = ($hasLocalRef && $localRef !== '') ? 0 : (int) $localId;

            DB::table('sync_local_mappings')->updateOrInsert(
                $match,
                ['server_id' => $serverId, 'updated_at' => now()->toDateTimeString()]
            );
        } catch (\Throwable) {
            // mapping write fail ho to sirf log — retry pe dobara ho jayega
            \Illuminate\Support\Facades\Log::warning('mapLocal failed', ['entity' => $entityType, 'ref' => $localRef]);
        }
    }

    /**
     * Config entities jinki 'name' natural key hai (brands, categories, units...).
     * users ko kabhi include nahi karte — 2 employees ka same naam legal hai.
     */
    private function naturalKeyColumn(string $entityType, $cols): ?string
    {
        if ($entityType === 'units') {
            return $cols->contains('unit_name') ? 'unit_name' : null;
        }
        if ($entityType === 'variations') {
            return $cols->contains('variation_name') ? 'variation_name' : null;
        }

        $nameTables = [
            'brands', 'item_categories', 'income_categories', 'expense_categories',
            'racks', 'counters', 'printers', 'delivery_partners', 'denominations',
            'multiple_currencies', 'payment_methods', 'taxs', 'outlets', 'states',
            'time_zones', 'promotions', 'warranties', 'servicings', 'roles',
            'business_club_settings',
        ];

        return in_array($entityType, $nameTables, true) && $cols->contains('name') ? 'name' : null;
    }

    /**
     * Tables the desktop app may push through the generic pending_sync queue.
     * (Typed entities — items/customers/sales/purchases/... — use the typed push.)
     */
    private const ENTITY_TABLES = [
        'attendances', 'bookings', 'brands', 'business_club_members',
        'business_club_settings', 'business_club_transactions',
        'combo_items', 'combo_sales', 'companies', 'counters', 'customer_receives',
        'customer_wallets', 'customers', 'damage_details', 'damages', 'delivery_partners',
        'denominations', 'deposit_withdraws', 'employee_advance_payments',
        'expense_categories', 'expenses', 'fixed_asset_items',
        'fixed_asset_stock_in_details', 'fixed_asset_stock_ins',
        'fixed_asset_stock_out_details', 'fixed_asset_stock_outs',
        'hold_combo_items', 'hold_details', 'holds', 'income_categories',
        'incomes', 'installment_sale_details', 'installment_sale_payments',
        'installment_sales', 'installed_modules', 'item_categories', 'items', 'multiple_currencies',
        'model_has_roles', 'outlets', 'payment_methods', 'price_list_items', 'price_lists',
        'printers', 'promotions', 'purchase_details', 'purchase_payments',
        'purchase_return_details', 'purchase_returns', 'purchases',
        'quotation_details', 'quotations', 'racks', 'registers', 'role_has_permissions', 'roles', 'salaries',
        'salary_items', 'salary_payments', 'sale_details', 'sale_payments',
        'sale_return_details', 'sale_returns', 'sales', 'servicings',
        'set_opening_stocks', 'states', 'supplier_payments', 'suppliers',
        'taxs', 'time_zones', 'transfer_details', 'transfers', 'units',
        'users', 'variations', 'wallet_transactions', 'warranties',
        'zatca_invoices', 'zatca_requests',
    ];

    /** Columns used for client-side sync bookkeeping — never written to server tables. */
    private const RESERVED_COLUMNS = [
        'id', 'local_id', 'server_id', 'SyncStatus', 'ServerId', 'SyncError',
        'syncstatus', 'serverid', 'syncerror', 'user_id', 'company_id',
        'outlet_id', 'del_status', 'created_at', 'updated_at',
    ];

    /**
     * POST /api/sync/push/batch — FIX 3: SPLIT PUSH
     * Ek request = ek entity type. Har record apne try/catch mein process hota hai,
     * isliye ek record ki failure baaki records ko rok nahi sakti. Main /push
     * (24 entities ek saath) agar fail ho to desktop is endpoint se entity-by-entity
     * push karta hai — promotions jaisi complex entity ki validation failure ab
     * brands/categories/items ke sync ko block nahi karegi.
     *
     * Body: { entity_type: "brands", records: [ {..row..}, {..row..} ] }
     * Response: { entity_type, [entity_type]: [ results... ], server_time }
     *   (result shape wahi hai jo main /push ke per-entity arrays ka hota hai —
     *    desktop ka existing result-processing bina badle kaam karta hai)
     */
    public function pushBatch(Request $request): JsonResponse
    {
        $entityType = (string) $request->input('entity_type', '');
        $records    = $request->input('records', []);
        $records    = is_array($records) ? $records : [];
        $deviceId   = $this->deviceId;

        $handlerMap = [
            'items' => 'pushItem', 'customers' => 'pushCustomer', 'suppliers' => 'pushSupplier',
            'sales' => 'pushSale', 'purchases' => 'pushPurchase', 'purchase_returns' => 'pushPurchaseReturn',
            'supplier_payments' => 'pushSupplierPayment', 'expenses' => 'pushExpense',
            'sale_returns' => 'pushSaleReturn', 'salaries' => 'pushSalary',
            'attendances' => 'pushAttendance', 'promotions' => 'pushPromotion',
            'bookings' => 'pushBooking', 'servicings' => 'pushServicing',
            'warranties' => 'pushWarranty', 'deposit_withdraws' => 'pushDepositWithdraw',
            'installment_sales' => 'pushInstallmentSale', 'quotations' => 'pushQuotation',
            'incomes' => 'pushIncome', 'customer_receives' => 'pushCustomerReceive',
            'outlets' => 'pushOutlet', 'wallet_transactions' => 'pushWalletTransaction',
            'loyalty_changes' => 'pushLoyaltyChange', 'configs' => null,
        ];

        // Config master tables (brands/categories/units/racks/variations/expense_categories)
        // — pushConfigItem se process hote hain, natural-key dedupe ke saath.
        $configTables = [
            'brands', 'item_categories', 'units', 'racks', 'variations',
            'expense_categories', 'payment_methods', 'counters', 'denominations',
            'delivery_partners', 'multiple_currencies', 'taxs', 'time_zones', 'states',
        ];

        if (in_array($entityType, $configTables, true)) {
            return $this->pushBatchConfigs($request, $entityType, $records);
        }

        if (! array_key_exists($entityType, $handlerMap)) {
            return response()->json(['success' => false, 'error' => 'Entity not allowed: '.$entityType], 403);
        }

        // ═══ FIX 1: idempotency (same as push()) ═══
        $idempotencyKey = (string) $request->header('X-Idempotency-Key', '');
        if ($idempotencyKey !== '') {
            $cached = DB::table('idempotency_keys')
                ->where('key', $idempotencyKey)
                ->where('device_id', $deviceId)
                ->where('expires_at', '>', now())
                ->first();
            if ($cached && $cached->response_snapshot !== null) {
                return response()->json(json_decode($cached->response_snapshot, true));
            }
        }

        // configs ek dict hai (groups), records[0] wo dict hota hai
        if ($entityType === 'configs') {
            $configDict = $records[0] ?? [];
            $results = is_array($configDict) ? $this->pushConfigs($configDict) : [];
            $responseData = ['entity_type' => $entityType, 'configs' => $results, 'server_time' => now()->toDateTimeString()];
            if ($idempotencyKey !== '') {
                DB::table('idempotency_keys')->updateOrInsert(
                    ['key' => $idempotencyKey, 'device_id' => $deviceId],
                    ['response_snapshot' => json_encode($responseData), 'expires_at' => now()->addHours(24),
                     'created_at' => DB::raw('COALESCE(created_at, NOW())')]
                );
            }
            return response()->json($responseData);
        }

        $handler = $handlerMap[$entityType];
        $results = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }
            // Har record alag transaction mein — ek fail = sirf wo record fail
            try {
                $result = DB::transaction(function () use ($handler, $record) {
                    return $this->{$handler}($record);
                });
                $results[] = $result;
            } catch (\Throwable $e) {
                $localKey = $record['local_id'] ?? $record['local_code'] ?? null;
                $results[] = [
                    'local_id' => $localKey,
                    'local_code' => $record['local_code'] ?? null,
                    'server_id' => null,
                    'error' => $e->getMessage(),
                ];
                // FIX 4: silent drop mat karo — dead letter log karo
                $this->sendToDeadLetter($deviceId, $entityType, $localKey, $record, $e->getMessage());
            }
        }

        // Items ke baad stock ledger reconcile + view rebuild (main push jaisa hi)
        if ($entityType === 'items') {
            foreach ($records as $record) {
                if (is_array($record)) {
                    $this->reconcileManualStock($record['code'] ?? null);
                }
            }
            $this->refreshStockView();
        }
        // Business Club fallback credit — sirf jab sales + wallet_transactions dono is request mein hain
        if ($entityType === 'sales') {
            $this->businessClubFallbackCredit($results, [], $records);
        }

        $responseData = [
            'entity_type' => $entityType,
            $entityType => $results,
            'server_time' => now()->toDateTimeString(),
        ];

        if ($idempotencyKey !== '') {
            DB::table('idempotency_keys')->updateOrInsert(
                ['key' => $idempotencyKey, 'device_id' => $deviceId],
                ['response_snapshot' => json_encode($responseData), 'expires_at' => now()->addHours(24),
                 'created_at' => DB::raw('COALESCE(created_at, NOW())')]
            );
        }

        return response()->json($responseData);
    }

    /**
     * pushBatch ka config-table variant: brands/item_categories/units/racks/
     * variations... — har record pushConfigItem se (natural-key dedupe ke saath),
     * per-record transaction + dead letter on failure. Same idempotency as pushBatch.
     */
    private function pushBatchConfigs(Request $request, string $entityType, array $records): JsonResponse
    {
        $deviceId = $this->deviceId;
        $idempotencyKey = (string) $request->header('X-Idempotency-Key', '');

        if ($idempotencyKey !== '') {
            $cached = DB::table('idempotency_keys')
                ->where('key', $idempotencyKey)
                ->where('device_id', $deviceId)
                ->where('expires_at', '>', now())
                ->first();
            if ($cached && $cached->response_snapshot !== null) {
                return response()->json(json_decode($cached->response_snapshot, true));
            }
        }

        $results = [];
        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }
            try {
                $result = DB::transaction(function () use ($entityType, $record) {
                    return $this->pushConfigItem($entityType, $entityType, $record);
                });
                $results[] = $result;
            } catch (\Throwable $e) {
                $results[] = [
                    'local_id' => $record['local_id'] ?? null,
                    'server_id' => null,
                    'error' => $e->getMessage(),
                ];
                $this->sendToDeadLetter($deviceId, $entityType, $record['local_id'] ?? null, $record, $e->getMessage());
            }
        }

        $responseData = [
            'entity_type' => $entityType,
            $entityType => $results,
            'server_time' => now()->toDateTimeString(),
        ];

        if ($idempotencyKey !== '') {
            DB::table('idempotency_keys')->updateOrInsert(
                ['key' => $idempotencyKey, 'device_id' => $deviceId],
                ['response_snapshot' => json_encode($responseData), 'expires_at' => now()->addHours(24),
                 'created_at' => DB::raw('COALESCE(created_at, NOW())')]
            );
        }

        return response()->json($responseData);
    }

    /**
     * FIX 4: validation/repeated failure ko sync_dead_letters mein log karo taaki
     * koi record silently drop na ho. Same (device, entity, local_id) = same row
     * update hota hai (retry_count/last_failed_at bump), 100 duplicate rows nahi.
     */
    private function sendToDeadLetter(string $deviceId, string $entityType, $localId, array $record, string $error): void
    {
        try {
            DB::table('sync_dead_letters')->updateOrInsert(
                [
                    'device_id'   => $deviceId,
                    'entity_type' => $entityType,
                    'local_id'    => $localId !== null ? (string) $localId : '',
                ],
                [
                    'outlet_id'       => $this->outletId,
                    'payload'         => json_encode($record),
                    'error_message'   => mb_substr($error, 0, 1000),
                    'retry_count'     => DB::raw('retry_count + 1'),
                    'first_failed_at' => DB::raw('COALESCE(first_failed_at, NOW())'),
                    'last_failed_at'  => now(),
                    'resolved'        => false,
                ]
            );
        } catch (\Throwable $e) {
            \Log::warning('sendToDeadLetter failed: '.$e->getMessage());
        }
    }

    /**
     * GET /api/sync/dead-letters — FIX 4: unresolved failed records list karo
     * (desktop UI sync status se count dikhata hai).
     */
    public function deadLetters(): JsonResponse
    {
        $rows = DB::table('sync_dead_letters')
            ->where('resolved', false)
            ->orderByDesc('last_failed_at')
            ->limit(200)
            ->get(['id', 'device_id', 'entity_type', 'local_id', 'error_message', 'retry_count', 'last_failed_at']);

        return response()->json(['success' => true, 'count' => $rows->count(), 'dead_letters' => $rows]);
    }

    /**
     * POST /api/sync/push-entity
     * Generic offline-queue endpoint consumed by the desktop app's pending_sync
     * queue. Payload:
     *   { entity_type: "outlets", entity_id: 7,
     *     operation: "insert|update|delete", payload: "{...row JSON...}" }
     *
     * - Only writes columns that exist on the target table (local sync columns
     *   and reserved columns are ignored).
     * - Always stamps user_id/company_id/outlet_id/del_status when present.
     * - Keeps a local_id -> server_id mapping (sync_local_mappings) so retries
     *   never create duplicates and deletes resolve the correct server row.
     */
    public function pushEntity(Request $request): JsonResponse
    {
        $entityType = (string) $request->input('entity_type', '');
        $entityId   = (int) $request->input('entity_id', 0);
        $operation  = (string) $request->input('operation', 'insert');
        $payload    = $request->input('payload');

        // Role/permission check: restrict sensitive tables to admin role only
        $adminOnlyTables = ['users', 'roles', 'permissions', 'companies', 'outlets', 'model_has_roles', 'role_has_permissions'];
        if (in_array($entityType, $adminOnlyTables) && !$request->user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Insufficient permissions'], 403);
        }

        if (! in_array($entityType, self::ENTITY_TABLES, true)) {
            return response()->json([
                'local_id' => $entityId, 'server_id' => null,
                'error' => 'Unsupported entity_type: ' . $entityType,
            ]);
        }

        $data = is_string($payload) ? json_decode($payload, true) : $payload;
        if (! is_array($data)) {
            return response()->json(['local_id' => $entityId, 'server_id' => null, 'error' => 'Invalid payload']);
        }

        try {
            $cols = collect(DB::getSchemaBuilder()->getColumnListing($entityType));

            $mapping = $this->findMapping($entityType, $entityId);

            $serverId = $mapping ? (int) $mapping->server_id : null;

            // ── Companies: single row per company — always the token's company id.
            //    Skip natural-key dedupe below, otherwise an update with a known
            //    company name would be intercepted and never applied. ──
            if ($entityType === 'companies') {
                $serverId = $this->companyId;
            }

            // ── Items: always resolve by code (same rule as the typed push). A
            //    stale local mapping may point at a duplicate row created by an
            //    old build, and without a mapping the update would INSERT a
            //    brand-new item instead of updating the real one. ──
            if ($entityType === 'items' && $operation !== 'delete') {
                $code = $data['code'] ?? null;
                if (! empty($code)) {
                    $byCode = DB::table('items')
                        ->where('code', $code)
                        ->where('company_id', $this->companyId)
                        ->where('del_status', 'Live')
                        ->first();
                    if ($byCode) {
                        $serverId = (int) $byCode->id;
                        $this->mapLocal('items', $entityId, '', $serverId);
                    }
                }
            }

            // ── DELETE: soft-delete the server row if we know it ──
            if ($operation === 'delete') {
                // Mapping nahi mila to entity_id hi server id hai (pulled rows
                // desktop par server id se hi rakhi jati hain).
                $serverId = $serverId ?: $entityId;
                if ($serverId && $cols->contains('del_status')) {
                    DB::table($entityType)->where('id', $serverId)->update([
                        'del_status' => 'Deleted',
                        'updated_at' => now(),
                    ]);

                    // ── Items parent delete → variation children bhi delete ──
                    // (parent_id link + code-prefix fallback — orphaned children
                    //  ke liye jinke parent_id NULL reh gaya tha). Nahi to web
                    //  par "Amul Ice Cream - 100 Gram" jaisi orphaned Live
                    //  rows dikhti rehti hain. ──
                    if ($entityType === 'items') {
                        $parentCode = DB::table('items')->where('id', $serverId)->value('code');
                        if ($parentCode) {
                            DB::table('items')
                                ->where('del_status', 'Live')
                                ->where('type', '0')
                                ->where(function ($q) use ($serverId, $parentCode) {
                                    $q->where('parent_id', $serverId)
                                      ->orWhere('code', 'like', $parentCode . '-%');
                                })
                                ->update(['del_status' => 'Deleted', 'updated_at' => now()]);
                        }
                    }
                }
                if ($mapping) {
                    DB::table('sync_local_mappings')->where('id', $mapping->id)->delete();
                }
                return response()->json(['local_id' => $entityId, 'server_id' => $serverId, 'deleted' => true]);
            }

            // ── Natural-key dedupe: config entities (brands/categories/units...) ka
            //    naam company me unique hona chahiye. Alag counters same naam wali
            //    cheez 2 baar na banayein (Parle/Parle double-add bug). ──
            if ($operation !== 'delete' && ! $serverId) {
                $keyCol = $this->naturalKeyColumn($entityType, $cols);
                if ($keyCol && ! empty($data[$keyCol])) {
                    $q = DB::table($entityType)->where($keyCol, $data[$keyCol]);
                    if ($cols->contains('company_id')) $q->where('company_id', $this->companyId);
                    if ($cols->contains('del_status')) $q->where('del_status', 'Live');
                    $existingByName = $q->first();
                    if ($existingByName) {
                        $this->mapLocal($entityType, $entityId, '', (int) $existingByName->id);
                        return response()->json(['local_id' => $entityId, 'server_id' => (int) $existingByName->id, 'created' => false]);
                    }
                }

                // ── Users (employees): email unique per company — same rule as web ──
                if ($entityType === 'users' && ! empty($data['email'])) {
                    $existingEmail = DB::table('users')
                        ->where('email', $data['email'])
                        ->where('company_id', $this->companyId)
                        ->where('del_status', 'Live')
                        ->first();
                    if ($existingEmail) {
                        $this->mapLocal('users', $entityId, '', (int) $existingEmail->id);
                        return response()->json(['local_id' => $entityId, 'server_id' => (int) $existingEmail->id, 'created' => false]);
                    }
                }
            }

            // ── INSERT / UPDATE: whitelist columns to what the table actually has ──
            $insert = [];
            foreach ($data as $k => $v) {
                if (! is_string($k) || ! $cols->contains($k)) continue;
                if (in_array($k, self::RESERVED_COLUMNS, true)) continue;
                if ($v === null) {
                    $insert[$k] = null;
                } elseif ($v === '') {
                    continue; // let the DB default apply
                } elseif (is_scalar($v)) {
                    // Convert boolean-like strings to integers for integer columns
                    if (in_array($k, ['expiry_date_maintain', 'enable_disable_status', 'loyalty_point', 'conversion_rate']) && is_string($v)) {
                        $v = in_array(strtolower($v), ['yes', 'true', '1']) ? 1 : (in_array(strtolower($v), ['no', 'false', '0']) ? 0 : (int) $v);
                    }
                    $insert[$k] = $v;
                } else {
                    $insert[$k] = json_encode($v);
                }
            }

            // ── FK columns: 0 kabhi valid reference nahi hai. Desktop "no
            //    selection" ko 0 bhejta hai (e.g. items.rack_id=0) → MySQL FK
            //    violation (1452) → dead letter. 0 ko null karo. ──
            foreach ($this->fkColumnsFor($entityType) as $fkCol) {
                if (array_key_exists($fkCol, $insert) && (int) $insert[$fkCol] === 0) {
                    $insert[$fkCol] = null;
                }
            }

            if ($cols->contains('user_id'))    $insert['user_id']    = $this->userId;
            if ($cols->contains('company_id')) $insert['company_id'] = $this->companyId;
            if ($cols->contains('outlet_id'))  $insert['outlet_id']  = $this->outletId;
            if ($cols->contains('del_status')) $insert['del_status'] = 'Live';
            if ($cols->contains('updated_at')) $insert['updated_at'] = now()->toDateTimeString();

            // ── Users (employees): desktop picks the outlet; keep its choice.
            //    users.password is NOT NULL — guard against empty pushes. ──
            if ($entityType === 'users') {
                if (! empty($data['outlet_id'])) $insert['outlet_id'] = (string) $data['outlet_id'];
                if (empty($insert['password'])) $insert['password'] = bcrypt(\Illuminate\Support\Str::random(16));
            }

            if (empty($insert)) {
                return response()->json(['local_id' => $entityId, 'server_id' => null, 'error' => 'No writable columns']);
            }

            if ($serverId && DB::table($entityType)->where('id', $serverId)->exists()) {
                unset($insert['created_at']); // never overwrite original creation time on update
                DB::table($entityType)->where('id', $serverId)->update($insert);
                if ($entityType === 'items') {
                    $this->reconcileManualStock($data['code'] ?? null);
                    $this->refreshStockView();
                }
                return response()->json(['local_id' => $entityId, 'server_id' => $serverId, 'created' => false]);
            }

            if ($cols->contains('created_at') && ! array_key_exists('created_at', $insert)) {
                $insert['created_at'] = now()->toDateTimeString();
            }

            // Insert the row and record its local<->server mapping atomically, so a
            // crash mid-way can never leave an unmapped server row (which would
            // duplicate the record on the next retry).
            $newId = DB::transaction(function () use ($entityType, $insert, $entityId) {
                $id = DB::table($entityType)->insertGetId($insert);
                $this->mapLocal($entityType, $entityId, '', (int) $id);
                return $id;
            });

            // After inserting a customer_receives record, decrement customer's due_amount
            if ($entityType === 'customer_receives' && isset($data['customer_id']) && isset($data['amount'])) {
                DB::table('customers')->where('id', (int) $data['customer_id'])->where('company_id', $this->companyId)->decrement('due_amount', (float) $data['amount']);
            }

            if ($entityType === 'items') {
                $this->reconcileManualStock($data['code'] ?? null);
                $this->refreshStockView();
            }

            return response()->json(['local_id' => $entityId, 'server_id' => (int) $newId, 'created' => true]);
        } catch (\Throwable $e) {
            // FIX 4: validation failure ko dead letter mein log karo — silently
            // drop nahi hoga; desktop retry ke saath dead letter bhi dekhega.
            $this->sendToDeadLetter($this->deviceId, $entityType, $entityId, $data, $e->getMessage());
            return response()->json(['local_id' => $entityId, 'server_id' => null, 'error' => $e->getMessage()]);
        }
    }

    /**
     * FK columns of a table (information_schema, per-request cached). Generic
     * push path ko batata hai ki kaunse columns reference columns hain — wahan
     * 0 invalid hai aur null karna padta hai (FK violation avoidance).
     */
    private array $fkColumnsCache = [];

    private function fkColumnsFor(string $table): array
    {
        if (array_key_exists($table, $this->fkColumnsCache)) {
            return $this->fkColumnsCache[$table];
        }
        $rows = DB::select(
            'SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table]
        );
        return $this->fkColumnsCache[$table] = array_map(fn ($r) => $r->COLUMN_NAME, $rows);
    }
}
