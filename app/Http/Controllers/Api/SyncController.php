<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
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
            'units' => DB::table('units')
                ->where('del_status', 'Live')
                ->get(['id', 'unit_name', 'description']),
            'brands' => DB::table('brands')
                ->where('del_status', 'Live')
                ->get(['id', 'name', 'description']),
            'categories' => DB::table('item_categories')
                ->where('del_status', 'Live')
                ->get(['id', 'name', 'description']),
            'suppliers' => DB::table('suppliers')
                ->where('del_status', 'Live')
                ->where('company_id', $this->companyId)
                ->get(['id', 'name']),
            'racks' => DB::table('racks')
                ->where('del_status', 'Live')
                ->get(['id', 'name', 'description']),
            'variations' => DB::table('variations')
                ->where('del_status', 'Live')
                ->get(['id', 'variation_name', 'variation_value']),
            'expense_categories' => DB::table('expense_categories')
                ->where('del_status', 'Live')
                ->get(['id', 'name', 'description']),
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

        // Items / customers / suppliers are small tables: always send the FULL
        // list (ignoring "since") so the desktop app's mirror stays complete even
        // when nothing was updated after the last sync.
        $items      = $this->pullItems(null);
        $customers  = $this->pullCustomers(null);
        $suppliers  = $this->pullSuppliers(null);
        $sales      = $this->pullSales($sinceTs);
        $saleReturns = $this->pullSaleReturns($sinceTs);
        $holds      = $this->pullHolds($sinceTs);
        $holdDetails = $this->pullSimpleTable('hold_details', $sinceTs);
        $purchases  = $this->pullPurchases($sinceTs);
        $purchaseReturns = $this->pullPurchaseReturns($sinceTs);
        $supplierPayments = $this->pullSupplierPayments($sinceTs);
        $expenses   = $this->pullExpenses($sinceTs);

        // Previously missing — all these are now pulled in full
        $promotions         = $this->pullSimpleTable('promotions', $sinceTs);
        $servicings         = $this->pullSimpleTable('servicings', $sinceTs);
        $warranties         = $this->pullSimpleTable('warranties', $sinceTs);
        $bookings           = $this->pullSimpleTable('bookings', $sinceTs);
        $customerReceives   = $this->pullSimpleTable('customer_receives', $sinceTs);
        $incomes            = $this->pullSimpleTable('incomes', $sinceTs);
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
        $saleDetails        = $this->pullSimpleTable('sale_details', $sinceTs);
        $purchaseDetails    = $this->pullSimpleTable('purchase_details', $sinceTs);
        $salePayments       = $this->pullSimpleTable('sale_payments', $sinceTs);

        return response()->json([
            'server_time'               => now()->toDateTimeString(),
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
            'sale_details'              => $saleDetails,
            'purchase_details'          => $purchaseDetails,
            'sale_payments'             => $salePayments,
            'master'                    => $this->pullMaster(),
        ]);
    }

    /**
     * Generic pull for simple tables — filters by company_id and del_status,
     * optionally by updated_at > $since. Returns plain array of all columns.
     */
    private function pullSimpleTable(string $table, ?string $since): array
    {
        try {
            $schema = DB::getSchemaBuilder();
            $hasCompany  = $schema->hasColumn($table, 'company_id');
            $hasDelStatus = $schema->hasColumn($table, 'del_status');
            $hasUpdatedAt = $schema->hasColumn($table, 'updated_at');

            $q = DB::table($table);
            if ($hasCompany)  $q->where('company_id', $this->companyId);
            if ($hasDelStatus) $q->where('del_status', 'Live');
            if ($since && $hasUpdatedAt) $q->where('updated_at', '>', $since);

            return $q->get()->map(fn ($row) => (array) $row)->all();
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
            'outlets', 'states', 'denominations', 'multiple_currencies', 'printers',
            'counters', 'delivery_partners', 'income_categories', 'expense_categories',
            'item_categories', 'brands', 'units', 'racks', 'variations',
            'payment_methods', 'roles', 'users', 'business_club_settings',
            'customer_wallets', 'price_lists', 'suppliers', 'taxs',
            // Previously missing tables:
            'promotions', 'price_list_items', 'fixed_asset_items',
            'fixed_asset_stock_ins', 'fixed_asset_stock_outs',
            'fixed_asset_stock_in_details', 'fixed_asset_stock_out_details',
        ];
        $result = [];
        foreach ($tables as $t) {
            try {
                $result[$t] = DB::table($t)
                    ->when(DB::getSchemaBuilder()->hasColumn($t, 'company_id'), function ($q) {
                        $q->where('company_id', $this->companyId);
                    })
                    ->when(DB::getSchemaBuilder()->hasColumn($t, 'del_status'), function ($q) {
                        $q->where('del_status', 'Live');
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
            'sales' => 'nullable|array',
            'configs' => 'nullable|array',
            'purchases' => 'nullable|array',
            'purchase_returns' => 'nullable|array',
            'supplier_payments' => 'nullable|array',
            'expenses' => 'nullable|array',
            'sale_returns' => 'nullable|array',
        ]);

        $payload = $request->all();

        $itemResults = [];
        foreach ($payload['items'] ?? [] as $item) {
            $itemResults[] = $this->pushItem($item);
        }

        $customerResults = [];
        foreach ($payload['customers'] ?? [] as $customer) {
            $customerResults[] = $this->pushCustomer($customer);
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

        return response()->json([
            'items' => $itemResults,
            'customers' => $customerResults,
            'sales' => $saleResults,
            'configs' => $configResults,
            'purchases' => $purchaseResults,
            'purchase_returns' => $purchaseReturnResults,
            'supplier_payments' => $supplierPaymentResults,
            'expenses' => $expenseResults,
            'sale_returns' => $saleReturnResults,
            'server_time' => now()->toDateTimeString(),
        ]);
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
            // Idempotency: this local return was already pushed before
            if (! empty($localId)) {
                $existing = DB::table('sale_returns')
                    ->where('company_id', $this->companyId)
                    ->where('outlet_id', $this->outletId)
                    ->where('local_id', $localId)
                    ->first();
                if ($existing) {
                    return ['local_id' => $localId, 'server_id' => (int) $existing->id];
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
                'local_id' => ! empty($localId) ? (int) $localId : null,
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
            }

            return ['local_id' => $localId, 'server_id' => $returnId];
        } catch (\Throwable $e) {
            return ['local_id' => $localId, 'server_id' => null, 'error' => $e->getMessage()];
        }
    }

    // ═══════════════════════ PULL HELPERS ═══════════════════════

    private function pullItems(?string $since): array
    {
        $query = DB::table('items')
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live');
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
            'enable_disable_status', 'stock_quantity', 'parent_id', 'updated_at',
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

            foreach ($itemIds as $id) {
                $stockMap[$id] = round(
                    (float) ($purchaseIn[$id] ?? 0) - (float) ($saleOut[$id] ?? 0), 3
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
                'stock_quantity' => $stockMap[(int) $item->id] ?? (float) $item->stock_quantity,
                'parent_id' => $item->parent_id ? (int) $item->parent_id : null,
                'updated_at' => $item->updated_at ?? now()->toDateTimeString(),
            ];
        })->all();
    }

    private function pullCustomers(?string $since): array
    {
        $query = DB::table('customers')
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live');
        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get([
            'id', 'name', 'email', 'phone', 'address', 'city', 'postal_code',
            'gst_number', 'opening_balance', 'credit_limit', 'customer_type',
            'business_type', 'same_or_diff_state', 'updated_at',
        ])->map(function ($c) {
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
                'credit_limit' => (float) $c->credit_limit,
                'customer_type' => $c->customer_type ?? 'B2C',
                'business_type' => $c->business_type ?? 'B2C',
                'same_or_diff_state' => $c->same_or_diff_state ?? '',
                'updated_at' => $c->updated_at ?? now()->toDateTimeString(),
            ];
        })->all();
    }

    private function pullSuppliers(?string $since): array
    {
        $query = DB::table('suppliers')
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live');

        return $query->get([
            'id', 'name', 'company_name', 'email', 'phone', 'address', 'city',
            'postal_code', 'gst_number', 'opening_balance', 'credit_limit',
            'updated_at',
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
                'credit_limit' => (float) $s->credit_limit,
                'updated_at' => $s->updated_at ?? now()->toDateTimeString(),
            ];
        })->all();
    }

    private function pullSales(?string $since): array
    {
        $query = DB::table('sales')
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live');
        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        $sales = $query->get([
            'id', 'invoice_no', 'sale_no', 'sale_date', 'date_time', 'customer_id',
            'sub_total', 'given_amount', 'paid_amount', 'change_amount',
            'disc', 'vat', 'total_payable', 'grand_total', 'note', 'user_id',
            'outlet_id', 'created_at', 'updated_at',
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
                'note' => $sale->note ?? '',
                'user_id' => $sale->user_id ? (int) $sale->user_id : null,
                'outlet_id' => $sale->outlet_id ? (int) $sale->outlet_id : null,
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
        $query = DB::table('sale_returns')
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live');
        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        $returns = $query->get([
            'id', 'reference_no', 'sale_id', 'customer_id', 'date',
            'total_return_amount', 'paid', 'due', 'payment_method_id', 'note',
            'user_id', 'outlet_id', 'created_at', 'updated_at',
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
        $query = DB::table('holds')
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live');
        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        $holds = $query->get(['id', 'invoice_no', 'sale_date', 'customer_id',
            'sub_total', 'disc', 'vat', 'total_payable', 'note', 'updated_at']);

        if ($holds->isEmpty()) {
            return [];
        }

        $ids = $holds->pluck('id')->all();
        $details = DB::table('hold_details')
            ->whereIn('hold_id', $ids)->where('del_status', 'Live')
            ->get(['hold_id', 'item_id', 'qty', 'menu_unit_price', 'menu_vat_percentage',
                'item_tax_amount', 'discount_amount'])
            ->groupBy('hold_id');

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
        $query = DB::table('purchases')
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live');
        $rows = $query->get(['id', 'reference_no', 'invoice_no', 'supplier_id', 'date',
            'grand_total', 'paid', 'due_amount', 'note', 'discount', 'status', 'updated_at']);

        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->pluck('id')->all();
        $details = DB::table('purchase_details')
            ->whereIn('purchase_id', $ids)->where('del_status', 'Live')
            ->get(['purchase_id', 'item_id', 'unit_price', 'quantity_amount', 'total', 'item_type'])
            ->groupBy('purchase_id');
        $payments = DB::table('purchase_payments')
            ->whereIn('purchase_id', $ids)->where('del_status', 'Live')
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
        $query = DB::table('purchase_returns')
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live');

        $rows = $query->get(['id', 'reference_no', 'pur_ref_no', 'supplier_id', 'date',
            'purchase_date', 'return_status', 'total_return_amount', 'payment_method_id', 'note', 'updated_at']);

        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->pluck('id')->all();
        $details = DB::table('purchase_return_details')
            ->whereIn('pur_return_id', $ids)->where('del_status', 'Live')
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
        $query = DB::table('supplier_payments')
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live');

        return $query->get(['id', 'reference_no', 'supplier_id', 'payment_method_id', 'amount',
            'date', 'note', 'updated_at'])->map(function ($p) {
            return [
                'id' => (int) $p->id,
                'reference_no' => $p->reference_no ?? '',
                'supplier_id' => $p->supplier_id ? (int) $p->supplier_id : null,
                'payment_method_id' => $p->payment_method_id ? (int) $p->payment_method_id : null,
                'amount' => (float) $p->amount,
                'date' => $p->date ?? '',
                'note' => $p->note ?? '',
                'updated_at' => $p->updated_at ?? now()->toDateTimeString(),
            ];
        })->all();
    }

    private function pullExpenses(?string $since): array
    {
        $query = DB::table('expenses')
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live');

        $rows = $query->get(['id', 'reference_no', 'date', 'category_id', 'payment_method_id',
            'amount', 'note', 'employee_id', 'updated_at']);

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
            'type' => $item['type'] ?? 'Product',
            'category_id' => isset($item['category_id']) && is_numeric($item['category_id']) ? (int) $item['category_id'] : null,
            'brand_id' => isset($item['brand_id']) && is_numeric($item['brand_id']) ? (int) $item['brand_id'] : null,
            'supplier_id' => isset($item['supplier_id']) && is_numeric($item['supplier_id']) ? (int) $item['supplier_id'] : null,
            'hsn_code' => $item['hsn_code'] ?? null,
            'unit_type' => $item['unit_type'] ?? null,
            'purchase_unit_id' => isset($item['purchase_unit_id']) && is_numeric($item['purchase_unit_id']) ? (int) $item['purchase_unit_id'] : null,
            'sale_unit_id' => isset($item['sale_unit_id']) && is_numeric($item['sale_unit_id']) ? (int) $item['sale_unit_id'] : null,
            'conversion_rate' => $item['conversion_rate'] ?? 1,
            'mrp_price' => $item['mrp_price'] ?? 0,
            'sale_price' => $item['sale_price'] ?? 0,
            'whole_sale_price' => $item['whole_sale_price'] ?? 0,
            'purchase_price' => $item['purchase_price'] ?? 0,
            'profit_margin' => $item['profit_margin'] ?? 0,
            'alert_quantity' => $item['alert_quantity'] ?? 0,
            'loyalty_point' => $item['loyalty_point'] ?? 0,
            'warranty' => $item['warranty'] ?? null,
            'warranty_date' => $item['warranty_date'] ?? null,
            'guarantee' => $item['guarantee'] ?? null,
            'guarantee_date' => $item['guarantee_date'] ?? null,
            'tax_string' => $item['tax_string'] ?? null,
            'tax_type' => $item['tax_type'] ?? 'Exclusive',
            'enable_disable_status' => $item['enable_disable_status'] ?? 1,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('items')->where('id', $existing->id)->update($data);
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

    private function pushCustomer(array $customer): array
    {
        $now = now()->toDateTimeString();

        $existing = null;
        if (! empty($customer['phone'])) {
            $existing = DB::table('customers')
                ->where('phone', $customer['phone'])
                ->where('company_id', $this->companyId)
                ->where('del_status', 'Live')
                ->first();
        }
        if (! $existing) {
            $existing = DB::table('customers')
                ->where('name', $customer['name'])
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
            'credit_limit' => $customer['credit_limit'] ?? 0,
            'customer_type' => $customer['customer_type'] ?? 'B2C',
            'business_type' => $customer['business_type'] ?? 'B2C',
            'same_or_diff_state' => $customer['same_or_diff_state'] ?? null,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('customers')->where('id', $existing->id)->update($data);
            return ['local_code' => $customer['local_code'], 'server_id' => (int) $existing->id, 'created' => false];
        }

        $data['user_id'] = $this->userId;
        $data['company_id'] = $this->companyId;
        $data['del_status'] = 'Live';
        $data['created_at'] = $now;

        $id = DB::table('customers')->insertGetId($data);

        return ['local_code' => $customer['local_code'], 'server_id' => (int) $id, 'created' => true];
    }

    private function pushSale(array $sale): array
    {
        $now = now()->toDateTimeString();

        $items = $sale['items'] ?? [];
        if (count($items) === 0) {
            return ['local_id' => $sale['local_id'], 'server_id' => null, 'error' => 'No items'];
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
                ->map(fn ($n) => (int) preg_replace('/^SALE-\d+-C\d+-/', '', (string) $n) ?: 0)
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
            ->where($nameCol, $name)
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
            DB::table($table)->where('id', $existing->id)->update($data);
            return ['local_id' => $localId, 'server_id' => (int) $existing->id, 'created' => false];
        }

        $data['user_id'] = $this->userId;
        $data['company_id'] = $this->companyId;
        $data['del_status'] = 'Live';
        $data['created_at'] = $now;

        $id = DB::table($table)->insertGetId($data);

        return ['local_id' => $localId, 'server_id' => (int) $id, 'created' => true];
    }

    private function pushPurchase(array $purchase): array
    {
        $now = now()->toDateTimeString();
        $localId = $purchase['local_id'] ?? null;
        $items = $purchase['items'] ?? [];
        if (count($items) === 0) {
            return ['local_id' => $localId, 'server_id' => null, 'error' => 'No items'];
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

        return ['local_id' => $localId, 'server_id' => (int) $returnId, 'created' => true];
    }

    private function pushSupplierPayment(array $payment): array
    {
        $now = now()->toDateTimeString();
        $localId = $payment['local_id'] ?? null;
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

        return ['local_id' => $localId, 'server_id' => (int) $id, 'created' => true];
    }

    private function pushExpense(array $expense): array
    {
        $now = now()->toDateTimeString();
        $localId = $expense['local_id'] ?? null;
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

        return ['local_id' => $localId, 'server_id' => (int) $id, 'created' => true];
    }



    // ═══════════════════ GENERIC ENTITY PUSH (/api/sync/push-entity) ═══════════════════

    /**
     * Tables the desktop app may push through the generic pending_sync queue.
     * (Typed entities — items/customers/sales/purchases/... — use the typed push.)
     */
    private const ENTITY_TABLES = [
        'attendances', 'bookings', 'brands', 'business_club_settings',
        'combo_items', 'combo_sales', 'counters', 'customer_receives',
        'customer_wallets', 'damage_details', 'damages', 'delivery_partners',
        'denominations', 'deposit_withdraws', 'employee_advance_payments',
        'expense_categories', 'expenses', 'fixed_asset_items',
        'fixed_asset_stock_in_details', 'fixed_asset_stock_ins',
        'fixed_asset_stock_out_details', 'fixed_asset_stock_outs',
        'hold_combo_items', 'hold_details', 'holds', 'income_categories',
        'incomes', 'installment_sale_details', 'installment_sale_payments',
        'installment_sales', 'item_categories', 'items', 'multiple_currencies',
        'outlets', 'payment_methods', 'price_list_items', 'price_lists',
        'printers', 'promotions', 'purchase_details', 'purchase_payments',
        'purchase_return_details', 'purchase_returns', 'purchases',
        'quotation_details', 'quotations', 'racks', 'registers', 'salaries',
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

            $mapping = DB::table('sync_local_mappings')
                ->where('entity_type', $entityType)
                ->where('local_id', $entityId)
                ->where('company_id', $this->companyId)
                ->where('outlet_id', $this->outletId)
                ->first();

            $serverId = $mapping ? (int) $mapping->server_id : null;

            // ── DELETE: soft-delete the server row if we know it ──
            if ($operation === 'delete') {
                if ($serverId && $cols->contains('del_status')) {
                    DB::table($entityType)->where('id', $serverId)->update([
                        'del_status' => 'Deleted',
                        'updated_at' => now(),
                    ]);
                }
                if ($mapping) {
                    DB::table('sync_local_mappings')->where('id', $mapping->id)->delete();
                }
                return response()->json(['local_id' => $entityId, 'server_id' => $serverId, 'deleted' => true]);
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
                    $insert[$k] = $v;
                } else {
                    $insert[$k] = json_encode($v);
                }
            }

            if ($cols->contains('user_id'))    $insert['user_id']    = $this->userId;
            if ($cols->contains('company_id')) $insert['company_id'] = $this->companyId;
            if ($cols->contains('outlet_id'))  $insert['outlet_id']  = $this->outletId;
            if ($cols->contains('del_status')) $insert['del_status'] = 'Live';
            if ($cols->contains('updated_at')) $insert['updated_at'] = now()->toDateTimeString();

            if (empty($insert)) {
                return response()->json(['local_id' => $entityId, 'server_id' => null, 'error' => 'No writable columns']);
            }

            if ($serverId && DB::table($entityType)->where('id', $serverId)->exists()) {
                unset($insert['created_at']); // never overwrite original creation time on update
                DB::table($entityType)->where('id', $serverId)->update($insert);
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
                DB::table('sync_local_mappings')->updateOrInsert(
                    [
                        'entity_type' => $entityType,
                        'local_id'    => $entityId,
                        'company_id'  => $this->companyId,
                        'outlet_id'   => $this->outletId,
                    ],
                    ['server_id' => $id, 'updated_at' => now()->toDateTimeString()]
                );
                return $id;
            });

            return response()->json(['local_id' => $entityId, 'server_id' => (int) $newId, 'created' => true]);
        } catch (\Throwable $e) {
            return response()->json(['local_id' => $entityId, 'server_id' => null, 'error' => $e->getMessage()]);
        }
    }
}
