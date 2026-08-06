<?php

namespace Modules\Sale\Http\Controllers\POS;

use App\Models\User;
use Illuminate\Http\Request;
use Modules\Sale\Models\Sale;
use Modules\Stock\Models\Item;
use Illuminate\Http\JsonResponse;
use Modules\Sale\Models\Customer;
use Illuminate\Support\Facades\DB;
use Modules\Sale\Models\SaleDetail;
use Modules\Stock\Models\ComboItem;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Sale\Models\SalePayment;
use Modules\Sale\Models\ComboSale;
use Modules\Sale\Models\Hold;
use Modules\Sale\Models\HoldDetail;
use Modules\Sale\Models\HoldComboItem;
use Modules\Sale\Models\Register;
use Modules\Configuration\Models\Counter;
use Modules\Stock\Models\ItemCategory;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Stock\Repositories\StockRepository;
use Modules\Sale\Models\Promotion;
use App\Facades\PaymentGateway;
use Modules\Configuration\Models\Company;
use Modules\Configuration\Models\State;
use Modules\Configuration\Models\Tax;
use Modules\Sale\Services\GstTaxService;
use Modules\Sale\Services\Zatca\ZatcaPhase1Service;
use Modules\Sale\Services\CustomerService;

class POSController extends Controller
{

    protected $stockRepository;

    protected $customerService;

    public function __construct(StockRepository $stockRepository, CustomerService $customerService)
    {
        $this->stockRepository = $stockRepository;
        $this->customerService = $customerService;
    }

    /**
     * Display the POS (Point of Sale) interface
     */
    public function index()
    {
        $categories = ItemCategory::where('del_status', 'Live')->where('company_id', session('company.company_id'))->orderBy('sort_id')->get();
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderByRaw("CASE WHEN name = 'Walk-in Customer' THEN 0 ELSE 1 END")
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();
        // Get Employee 
        $employees = User::where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('name', 'asc')
            ->get();
        // Get Payment Methods
        $payment_methods = PaymentMethod::where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('status', 'Enable')
            ->orderBy('sort_id')
            ->orderBy('name')
            ->get();
        $totalProducts = Item::where('company_id', session('company.company_id'))
            ->where('del_status', 'Live')
            ->where('enable_disable_status', 1)
            ->where('type', '!=', '0')
            ->where(function($q) { $q->where('parent_id', 0)->orWhereNull('parent_id'); })
            ->count();

        $printer_settings = null;
        $register = Register::getLatestRegister(Auth::id(), session('outlet.outlet_id'), session('company.company_id'));
        if ($register && $register->register_status == 1 && $register->counter_id) {
            $counter = Counter::with('printer')->find($register->counter_id);
            if ($counter && $counter->printer) {
                $p = $counter->printer;
                $printer_settings = [
                    'invoice_print' => $p->invoice_print ?? 'browser_print',
                    'print_format' => $p->print_format_invoice ?? $p->print_format ?? 'A4 Print',
                    'print_server_url_invoice' => $p->print_server_url_invoice ?? null,
                ];
            }
        }

        $editSaleId = request()->query('edit_sale');
        $states = State::orderBy('state_code')->get();
        $outlet = \Modules\Configuration\Models\Outlet::find(session('outlet.outlet_id'));
        $taxs = Tax::where('company_id', session('company.company_id'))
            ->where('del_status', 'Live')
            ->get(['id', 'tax_name', 'tax_rate', 'parent_tax_id'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'tax_name' => $t->tax_name,
                'tax_rate' => (float) $t->tax_rate,
                'parent_tax_id' => $t->parent_tax_id,
            ])
            ->values()
            ->toArray();
        return view('sale::pos.pages.pos-index', compact('categories', 'customers', 'employees', 'payment_methods', 'totalProducts', 'printer_settings', 'editSaleId', 'states', 'outlet', 'taxs'));
    }

    /**
     * Display the POS Excel Mode page
     */
    public function excelMode()
    {
        $categories = ItemCategory::where('del_status', 'Live')->where('company_id', session('company.company_id'))->orderBy('sort_id')->get();
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderByRaw("CASE WHEN name = 'Walk-in Customer' THEN 0 ELSE 1 END")
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();
        $employees = User::where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('name', 'asc')
            ->get();
        $payment_methods = PaymentMethod::where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('status', 'Enable')
            ->orderBy('sort_id')
            ->orderBy('name')
            ->get();
        $totalProducts = Item::where('company_id', session('company.company_id'))
            ->where('del_status', 'Live')
            ->where('enable_disable_status', 1)
            ->where('type', '!=', '0')
            ->where(function($q) { $q->where('parent_id', 0)->orWhereNull('parent_id'); })
            ->count();

        $printer_settings = null;
        $register = Register::getLatestRegister(Auth::id(), session('outlet.outlet_id'), session('company.company_id'));
        if ($register && $register->register_status == 1 && $register->counter_id) {
            $counter = Counter::with('printer')->find($register->counter_id);
            if ($counter && $counter->printer) {
                $p = $counter->printer;
                $printer_settings = [
                    'invoice_print' => $p->invoice_print ?? 'browser_print',
                    'print_format' => $p->print_format_invoice ?? $p->print_format ?? 'A4 Print',
                    'print_server_url_invoice' => $p->print_server_url_invoice ?? null,
                ];
            }
        }

        $editSaleId = request()->query('edit_sale');
        $states = State::orderBy('state_code')->get();
        $outlet = \Modules\Configuration\Models\Outlet::find(session('outlet.outlet_id'));
        $taxs = Tax::where('company_id', session('company.company_id'))
            ->where('del_status', 'Live')
            ->get(['id', 'tax_name', 'tax_rate', 'parent_tax_id'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'tax_name' => $t->tax_name,
                'tax_rate' => (float) $t->tax_rate,
                'parent_tax_id' => $t->parent_tax_id,
            ])
            ->values()
            ->toArray();
        return view('sale::pos.pages.pos-excel', compact('categories', 'customers', 'employees', 'payment_methods', 'totalProducts', 'printer_settings', 'editSaleId', 'states', 'outlet', 'taxs'));
    }

    /**
     * Display the POS Busy Mode page
     */
    public function busyMode()
    {
        $categories = ItemCategory::where('del_status', 'Live')->where('company_id', session('company.company_id'))->orderBy('sort_id')->get();
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderByRaw("CASE WHEN name = 'Walk-in Customer' THEN 0 ELSE 1 END")
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();
        $employees = User::where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('name', 'asc')
            ->get();
        $payment_methods = PaymentMethod::where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('status', 'Enable')
            ->orderBy('sort_id')
            ->orderBy('name')
            ->get();
        $totalProducts = Item::where('company_id', session('company.company_id'))
            ->where('del_status', 'Live')
            ->where('enable_disable_status', 1)
            ->where('type', '!=', '0')
            ->where(function($q) { $q->where('parent_id', 0)->orWhereNull('parent_id'); })
            ->count();

        $printer_settings = null;
        $counterName = null;
        $register = Register::getLatestRegister(Auth::id(), session('outlet.outlet_id'), session('company.company_id'));
        if ($register && $register->register_status == 1 && $register->counter_id) {
            $counter = Counter::with('printer')->find($register->counter_id);
            if ($counter) {
                $counterName = $counter->name;
                if ($counter->printer) {
                    $p = $counter->printer;
                    $printer_settings = [
                        'invoice_print' => $p->invoice_print ?? 'browser_print',
                        'print_format' => $p->print_format_invoice ?? $p->print_format ?? 'A4 Print',
                        'print_server_url_invoice' => $p->print_server_url_invoice ?? null,
                    ];
                }
            }
        }

        $editSaleId = request()->query('edit_sale');
        $states = State::orderBy('state_code')->get();
        $outlet = \Modules\Configuration\Models\Outlet::find(session('outlet.outlet_id'));
        $taxs = Tax::where('company_id', session('company.company_id'))
            ->where('del_status', 'Live')
            ->get(['id', 'tax_name', 'tax_rate', 'parent_tax_id'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'tax_name' => $t->tax_name,
                'tax_rate' => (float) $t->tax_rate,
                'parent_tax_id' => $t->parent_tax_id,
            ])
            ->values()
            ->toArray();
        return view('sale::pos.pages.pos-busy', compact('categories', 'customers', 'employees', 'payment_methods', 'totalProducts', 'printer_settings', 'editSaleId', 'states', 'outlet', 'taxs', 'counterName'));
    }

    /**
     * Display the Customer Display page
     */
    public function customerDisplay()
    {
        return view('sale::pos.pages.customer-display');
    }

    /**
     * Fetch products in batches for IndexedDB storage.
     * Scalable: uses batch queries for stock, IMEI/Serial, and medicine so 50k+ products load efficiently.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getProductsBatch(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(1000, max(100, (int) $request->input('per_page', 500)));
        $companyId = session('company.company_id');
        $outletId = session('outlet.outlet_id');
        $currentDate = now()->format('Y-m-d');

        // Total count (single query)
        $totalProducts = Item::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('enable_disable_status', 1)
            ->where('type', '!=', '0')
            ->where(function($q) { $q->where('parent_id', 0)->orWhereNull('parent_id'); })
            ->count();
        $totalPages = (int) ceil($totalProducts / $perPage);

        // Fetch items for this page only
        $items = Item::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('enable_disable_status', 1)
            ->where(function($q) { $q->where('parent_id', 0)->orWhereNull('parent_id'); })
            ->where('type', '!=', '0')
            ->with(['category:id,name', 'brand:id,name', 'saleUnit:id,unit_name'])
            ->select([
                'id', 'name', 'code', 'alternative_name', 'generic_name',
                'type', 'category_id', 'brand_id', 'sale_unit_id',
                'sale_price', 'mrp_price', 'whole_sale_price', 'purchase_price',
                'photo', 'description', 'alert_quantity', 'unit_type',
                'conversion_rate', 'tax_information', 'tax_string',
                'tax_type', 'applicable_tax_id', 'hsn_code',
                'variation_details', 'loyalty_point', 'expiry_date_maintain'
            ])
            ->orderBy('id', 'asc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        if ($items->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'data' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalProducts,
                    'total_pages' => $totalPages,
                    'has_more' => $page < $totalPages,
                ],
            ]);
        }

        $mainIds = $items->pluck('id')->toArray();
        $allItemIds = $mainIds;
        $imeiSerialIds = [];
        $medicineIds = [];
        $comboIds = [];
        $variationParentIds = [];

        foreach ($items as $item) {
            if (in_array($item->type, ['IMEI_Product', 'Serial_Product'])) {
                $imeiSerialIds[] = $item->id;
            }
            if ($item->type === 'Medicine_Product' && ($item->expiry_date_maintain ?? '') === 'Yes') {
                $medicineIds[] = $item->id;
            }
            if ($item->type === 'Combo_Product') {
                $comboIds[] = $item->id;
            }
            if ($item->type === 'Variation_Product') {
                $variationParentIds[] = $item->id;
            }
        }

        // Load all variations for this page's variation products in one query
        $variationsByParent = [];
        if (!empty($variationParentIds)) {
            $variationItems = Item::whereIn('parent_id', $variationParentIds)
                ->where('del_status', 'Live')
                ->select([
                    'id', 'parent_id', 'name', 'code', 'sale_price', 'mrp_price', 'whole_sale_price',
                    'purchase_price', 'photo', 'variation_details', 'type',
                    'tax_type', 'applicable_tax_id', 'hsn_code', 'tax_information', 'tax_string'
                ])
                ->orderBy('id')
                ->get();
            foreach ($variationItems as $v) {
                $variationsByParent[$v->parent_id][] = $v;
                $allItemIds[] = $v->id;
                if (in_array($v->type, ['IMEI_Product', 'Serial_Product'])) {
                    $imeiSerialIds[] = $v->id;
                }
            }
        }

        // Batch: stock for all items (main + variations)
        $stockIn = [];
        $stockOut = [];
        $stockByItem = [];
        try {
            $stockBatch = $this->stockRepository->getStockQuantitiesBatch($allItemIds, $outletId);
            $stockIn = $stockBatch['in'] ?? [];
            $stockOut = $stockBatch['out'] ?? [];
            foreach ($allItemIds as $id) {
                $stockByItem[$id] = ($stockIn[$id] ?? 0) - ($stockOut[$id] ?? 0);
            }
        } catch (\Exception $e) {
            \Log::warning('Stock batch query failed: ' . $e->getMessage());
        }

        // Batch: IMEI/Serial numbers for IMEI_Product and Serial_Product
        $imeiBatch = [];
        if (!empty($imeiSerialIds)) {
            try {
                $imeiBatch = $this->stockRepository->getIMEINumbersBatch($imeiSerialIds, $outletId) ?? [];
            } catch (\Exception $e) {
                \Log::warning('IMEI batch query failed: ' . $e->getMessage());
            }
        }

        // Batch: medicine expiry/quantity for Medicine_Product with expiry
        $medicineBatch = [];
        if (!empty($medicineIds)) {
            try {
                $medicineBatch = $this->stockRepository->getMedicineStockBatch($medicineIds, $outletId) ?? [];
            } catch (\Exception $e) {
                \Log::warning('Medicine batch query failed: ' . $e->getMessage());
            }
        }

        // Batch: combo items for Combo_Product
        $comboByProduct = [];
        if (!empty($comboIds)) {
            $comboRows = ComboItem::query()
                ->join('items', 'items.id', '=', 'combo_items.item_id')
                ->whereIn('combo_items.combo_item_id', $comboIds)
                ->select([
                    'combo_items.combo_item_id',
                    'combo_items.item_id',
                    'items.name as item_name',
                    'items.code as item_code',
                    'combo_items.quantity',
                    'combo_items.amount',
                    'combo_items.total',
                    'combo_items.show_in_invoice',
                ])
                ->get();
            foreach ($comboRows as $row) {
                $comboByProduct[$row->combo_item_id][] = (array) $row;
            }
        }

        // Batch: promotions for all item IDs (main + variations)
        $promotionByItem = $this->getActivePromotionsForItemsBatch($allItemIds, $companyId, $outletId, $currentDate);

        // Load taxs for GST calculation (used by frontend)
        $taxs = Tax::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->get(['id', 'tax_name', 'tax_rate', 'parent_tax_id'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'tax_name' => $t->tax_name,
                'tax_rate' => (float) $t->tax_rate,
                'parent_tax_id' => $t->parent_tax_id,
            ])
            ->values()
            ->toArray();

        // Build response for each product using pre-fetched data
        $products = [];
        foreach ($items as $item) {
            $isIMEISerial = in_array($item->type, ['IMEI_Product', 'Serial_Product']);
            $isMedicineExpiry = $item->type === 'Medicine_Product' && ($item->expiry_date_maintain ?? '') === 'Yes';

            if ($isIMEISerial) {
                $imeiData = $imeiBatch[$item->id] ?? ['imei_numbers' => []];
                $stock = $stockByItem[$item->id] ?? 0;
                $imeiNumbers = $imeiData['imei_numbers'] ?? [];
                $medicineNumbers = [];
            } else {
                $stock = $stockByItem[$item->id] ?? 0;
                $imeiNumbers = [];
                $medicineNumbers = [];
            }
            if ($isMedicineExpiry) {
                $medicineNumbers = $medicineBatch[$item->id] ?? [];
                $stock = 0;
            }

            $comboItems = $comboByProduct[$item->id] ?? [];
            $promotion = $promotionByItem[$item->id] ?? [];

            $productData = [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'alternative_name' => $item->alternative_name,
                'generic_name' => $item->generic_name,
                'type' => $item->type,
                'category_id' => $item->category_id,
                'category_name' => $item->category ? $item->category->name : null,
                'brand_id' => $item->brand_id,
                'brand_name' => $item->brand ? $item->brand->name : null,
                'sale_unit_id' => $item->sale_unit_id,
                'sale_unit_name' => $item->saleUnit ? $item->saleUnit->unit_name : null,
                'sale_price' => $item->sale_price,
                'mrp_price' => $item->mrp_price,
                'whole_sale_price' => $item->whole_sale_price,
                'purchase_price' => $item->purchase_price,
                'photo' => $item->photo,
                'description' => $item->description,
                'alert_quantity' => $item->alert_quantity,
                'unit_type' => $item->unit_type,
                'conversion_rate' => $item->conversion_rate,
                'tax_information' => $item->tax_information,
                'tax_string' => $item->tax_string,
                'tax_type' => $item->tax_type ?? 'Inclusive',
                'applicable_tax_id' => $item->applicable_tax_id,
                'hsn_code' => $item->hsn_code,
                'variation_details' => $item->variation_details,
                'loyalty_point' => $item->loyalty_point,
                'expiry_date_maintain' => $item->expiry_date_maintain,
                'image_url' => $item->photo ? asset('uploads/items/' . $item->photo) : asset('uploads/dummy_images/default-picture-pos.png'),
                'stock' => $stock,
                'imei_number' => $imeiNumbers,
                'medicine' => $medicineNumbers,
                'combo_items' => $comboItems,
                'promotion' => $promotion,
            ];

            if ($item->type === 'Variation_Product') {
                $variationList = $variationsByParent[$item->id] ?? [];
                $productData['variations'] = array_map(function ($variation) use ($stockByItem, $imeiBatch, $promotionByItem, $item) {
                    $variationDetails = $variation->variation_details;
                    if (is_string($variationDetails)) {
                        $variationDetails = json_decode($variationDetails, true);
                    }
                    $variationName = ($variationDetails && isset($variationDetails['variation_name']))
                        ? $variationDetails['variation_name']
                        : $variation->name;
                    $isVarImei = in_array($variation->type, ['IMEI_Product', 'Serial_Product']);
                    $varStock = $stockByItem[$variation->id] ?? 0;
                    $varImei = $isVarImei ? ($imeiBatch[$variation->id]['imei_numbers'] ?? []) : [];
                    $varPromo = $promotionByItem[$variation->id] ?? null;
                    return [
                        'id' => $variation->id,
                        'name' => $variationName,
                        'code' => $variation->code,
                        'sale_price' => $variation->sale_price,
                        'mrp_price' => $variation->mrp_price,
                        'whole_sale_price' => $variation->whole_sale_price,
                        'purchase_price' => $variation->purchase_price,
                        'photo' => $variation->photo,
                        'type' => $variation->type,
                        'tax_type' => $variation->tax_type ?? $item->tax_type ?? 'Inclusive',
                        'applicable_tax_id' => $variation->applicable_tax_id ?? $variation->applicable_tax_id,
                        'hsn_code' => $variation->hsn_code ?? $item->hsn_code,
                        'tax_information' => $variation->tax_information ?? $item->tax_information,
                        'tax_string' => $variation->tax_string ?? $item->tax_string,
                        'stock' => $varStock,
                        'imei_number' => $varImei,
                        'promotion' => $varPromo,
                    ];
                }, $variationList);
            }

            $products[] = $productData;
        }

        return response()->json([
            'status' => 'success',
            'data' => $products,
            'taxs' => $taxs,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalProducts,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
        ]);
    }

    /**
     * Get active promotions for multiple item IDs in one query (for batch product load).
     *
     * @param array $itemIds
     * @param int $companyId
     * @param int|null $outletId
     * @param string $currentDate
     * @return array [item_id => promotion array or null]
     */
    protected function getActivePromotionsForItemsBatch(array $itemIds, int $companyId, ?int $outletId, string $currentDate): array
    {
        if (empty($itemIds)) {
            return [];
        }
        $currentTime = now()->format('H:i:s');
        $promotions = Promotion::where('del_status', 'Live')
            ->where('status', '1')
            ->where('company_id', $companyId)
            ->where('start_date', '<=', $currentDate)
            ->where('end_date', '>=', $currentDate)
            ->orderBy('id', 'desc')
            ->get();
        $byItem = [];
        $billLevelPromotions = [];
        foreach ($promotions as $p) {
            if ($p->start_time && $currentTime < $p->start_time) continue;
            if ($p->end_time && $currentTime > $p->end_time) continue;

            $schemeBasis = $p->scheme_basis ?? 'item';
            if ($schemeBasis === 'bill') {
                $billLevelPromotions[] = $this->formatPromotionForPos($p);
                continue;
            }

            $applicableItems = $p->applicable_items ? (is_array($p->applicable_items) ? $p->applicable_items : json_decode($p->applicable_items, true)) : [];
            if (empty($applicableItems) && $p->type == '1') {
                $applicableItems = $p->item_id ? [$p->item_id] : [];
            }
            if (empty($applicableItems)) {
                continue;
            }
            foreach ($applicableItems as $aid) {
                if (in_array($aid, $itemIds) && (!isset($byItem[$aid]) || $byItem[$aid] === null)) {
                    $byItem[$aid] = $this->formatPromotionForPos($p);
                }
            }
        }
        if (!empty($billLevelPromotions)) {
            $byItem['_bill_level'] = $billLevelPromotions;
        }
        return $byItem;
    }

    /**
     * Format a single Promotion model for POS response (same shape as getActivePromotionForItem).
     * Supports type 1 (discount), type 2 (coupon), type 3 (buy X get Y).
     */
    protected function formatPromotionForPos(Promotion $promotion): array
    {
        $data = [
            'id' => $promotion->id,
            'title' => $promotion->name,
            'type' => $promotion->type,
            'start_date' => $promotion->start_date,
            'end_date' => $promotion->end_date,
            'start_time' => $promotion->start_time,
            'end_time' => $promotion->end_time,
            'scheme_basis' => $promotion->scheme_basis ?? 'item',
            'min_purchase_amount' => (float) ($promotion->min_purchase_amount ?? 0),
            'max_discount_amount' => (float) ($promotion->max_discount_amount ?? 0),
        ];
        // Type 1: Discount on specific item
        if ($promotion->type == '1') {
            $discount = $promotion->discount ?? null;
            if ($discount !== null && str_contains((string) $discount, '%')) {
                $data['discount_type'] = 'percentage';
                $data['discount_value'] = str_replace('%', '', $discount);
                $data['discount'] = $discount;
            } else {
                $data['discount_type'] = 'flat';
                $data['discount_value'] = $discount;
                $data['discount'] = $discount;
            }
            $data['bill_level_discount'] = $promotion->bill_level_discount ?? 0;
            $data['bill_level_discount_type'] = $promotion->bill_level_discount_type;
        }
        // Type 2: Coupon code discount
        elseif ($promotion->type == '2') {
            $discount = $promotion->discount ?? null;
            if ($discount !== null && str_contains((string) $discount, '%')) {
                $data['discount_type'] = 'percentage';
                $data['discount'] = floatval(str_replace('%', '', $discount));
            } else {
                $data['discount_type'] = 'fixed';
                $data['discount'] = floatval($discount);
            }
            $data['coupon_code'] = $promotion->coupon_code;
            $data['bill_level_discount'] = $promotion->bill_level_discount ?? 0;
            $data['bill_level_discount_type'] = $promotion->bill_level_discount_type;
        }
        // Type 3: Buy X Get Y (free item) - get_item details not included; frontend uses IndexedDB
        elseif ($promotion->type == '3') {
            $data['buy_item_id'] = $promotion->item_id;
            $data['buy_qty'] = (int) ($promotion->qty ?? 1);
            $data['get_item_id'] = $promotion->get_item_id ? (int) $promotion->get_item_id : null;
            $data['get_qty'] = (int) ($promotion->get_qty ?? 1);
        }
        return $data;
    }

    

    /**
     * Get product variations for Variation_Product
     * 
     * @param Request $request
     * @param int $productId
     * @return JsonResponse
     */
    public function getProductVariations(Request $request, $productId): JsonResponse
    {

        try {
            $product = Item::where('id', $productId)
                ->where('company_id', session('company.company_id'))
                ->where('del_status', 'Live')
                ->where('type', 'Variation_Product')
                ->first();

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found or not a Variation_Product'
                ], 404);
            }

            $variationItems = Item::where('parent_id', $product->id)
                ->where('del_status', 'Live')
                ->select([
                    'id', 'name', 'code', 'sale_price', 'mrp_price', 'whole_sale_price', 
                    'purchase_price', 'photo', 'variation_details', 'tax_information', 'tax_string',
                    'tax_type', 'applicable_tax_id', 'hsn_code', 'type',
                    'unit_type', 'conversion_rate', 'sale_unit_id'
                ])
                ->get();

            // Batch load promotions for all variations (same as getProductsBatch)
            $variationIds = $variationItems->pluck('id')->toArray();
            $companyId = (int) session('company.company_id');
            $outletId = session('outlet.outlet_id') ? (int) session('outlet.outlet_id') : null;
            $currentDate = date('Y-m-d');
            $promotionByItem = $this->getActivePromotionsForItemsBatch($variationIds, $companyId, $outletId, $currentDate);

            $variations = $variationItems->map(function ($variation) use ($promotionByItem, $product) {
                    $variationDetails = $variation->variation_details;
                    if (is_string($variationDetails)) {
                        $variationDetails = json_decode($variationDetails, true);
                    }
                    
                    $variationName = $variation->name;
                    if ($variationDetails && isset($variationDetails['variation_name'])) {
                        $variationName = $variationDetails['variation_name'];
                    }
                    
                    // Check if variation type is IMEI_Product or Serial_Product
                    $isIMEISerialProduct = in_array($variation->type, ['IMEI_Product', 'Serial_Product']);
                    
                    // Get stock and IMEI/Serial numbers based on variation type
                    if ($isIMEISerialProduct) {
                        $imeiData = $this->stockRepository->getIMEINumber($variation->id);
                        $stock = $imeiData['stock'] ?? 0;
                        $imeiNumbers = $imeiData['imei_numbers'] ?? [];
                    } else {
                        $stock = $this->stockRepository->getStock($variation->id);
                        $imeiNumbers = [];
                    }

                    $variationPromotion = $promotionByItem[$variation->id] ?? null;
                    
                    return [
                        'id' => $variation->id,
                        'name' => $variationName,
                        'code' => $variation->code,
                        'sale_price' => $variation->sale_price,
                        'mrp_price' => $variation->mrp_price,
                        'whole_sale_price' => $variation->whole_sale_price,
                        'purchase_price' => $variation->purchase_price,
                        'photo' => $variation->photo,
                        'type' => $variation->type,
                        'tax_information' => $variation->tax_information,
                        'tax_string' => $variation->tax_string,
                        'tax_type' => $variation->tax_type ?? $product->tax_type ?? 'Inclusive',
                        'applicable_tax_id' => $variation->applicable_tax_id ?? $product->applicable_tax_id,
                        'hsn_code' => $variation->hsn_code ?? $product->hsn_code,
                        'stock' => $stock,
                        'imei_number' => $imeiNumbers, // Array format for easy IndexedDB handling
                        'unit_type' => $variation->unit_type,
                        'conversion_rate' => $variation->conversion_rate,
                        'sale_unit_name' => $variation->saleUnit ? $variation->saleUnit->unit_name : null,
                        'promotion' => $variationPromotion,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $variations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch variations: ' . $e->getMessage()
            ], 500);
        }
    }


    




    /**
     * Initialize payment gateway payment
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function initializeGatewayPayment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'gateway' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'nullable|string|size:3',
                'description' => 'nullable|string',
                'payment_method_id' => 'required|integer|exists:payment_methods,id',
                'sale_no' => 'nullable|string',
            ]);

            // Check if gateway is available
            if (!PaymentGateway::isGatewayAvailable($validated['gateway'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Payment gateway '{$validated['gateway']}' is not available"
                ], 400);
            }

            // Prepare metadata
            $metadata = [
                'payment_method_id' => $validated['payment_method_id'],
                'sale_no' => $validated['sale_no'] ?? null,
                'customer_id' => $request->input('customer_id'),
                'outlet_id' => session('outlet.outlet_id'),
                'company_id' => session('company.company_id'),
                'return_url' => $request->input('return_url'),
                'cancel_url' => $request->input('cancel_url'),
            ];

            // Process payment initialization
            $response = PaymentGateway::processPayment(
                gatewayName: $validated['gateway'],
                amount: $validated['amount'],
                currency: $validated['currency'] ?? 'USD',
                description: $validated['description'] ?? "POS Payment",
                metadata: $metadata,
                paymentMethodId: $validated['payment_method_id']
            );

            // Get response data
            $responseData = $response->getData();
            
            // Build response based on gateway type
            $data = [
                'transaction_id' => $response->getTransactionId(),
                'gateway_response' => $responseData,
            ];
            
            // Add gateway-specific fields
            if ($validated['gateway'] === 'razorpay') {
                // Razorpay specific fields
                $data['key_id'] = $responseData['key_id'] ?? null;
                $data['razorpay_order_id'] = $responseData['razorpay_order_id'] ?? $response->getTransactionId();
            } elseif ($validated['gateway'] === 'paytm') {
                // Paytm specific fields
                $data['redirect_url'] = $response->getRedirectUrl();
                $data['order_id'] = $responseData['order_id'] ?? $response->getTransactionId();
                $data['txn_token'] = $responseData['txn_token'] ?? null;
            } elseif ($validated['gateway'] === 'paystack') {
                // Paystack specific fields
                $data['redirect_url'] = $response->getRedirectUrl();
                $data['reference'] = $responseData['reference'] ?? $response->getTransactionId();
                $data['authorization_url'] = $responseData['authorization_url'] ?? null;
            } elseif ($validated['gateway'] === 'flutterwave') {
                // Flutterwave specific fields
                $data['redirect_url'] = $response->getRedirectUrl();
                $data['tx_ref'] = $responseData['tx_ref'] ?? $response->getTransactionId();
                $data['link'] = $responseData['link'] ?? null;
            } elseif ($validated['gateway'] === 'myfatoorah') {
                // MyFatoorah specific fields
                $data['redirect_url'] = $response->getRedirectUrl();
                $data['invoice_id'] = $responseData['invoice_id'] ?? $response->getTransactionId();
                $data['invoice_url'] = $responseData['invoice_url'] ?? null;
            } elseif ($validated['gateway'] === 'mpesa') {
                // Mpesa specific fields
                $data['checkout_request_id'] = $responseData['checkout_request_id'] ?? $response->getTransactionId();
                $data['merchant_request_id'] = $responseData['merchant_request_id'] ?? null;
                $data['customer_message'] = $responseData['customer_message'] ?? 'Please check your phone to complete the payment';
                $data['response_code'] = $responseData['response_code'] ?? null;
            } else {
                // Stripe/PayPal specific fields
                $data['client_secret'] = $responseData['client_secret'] ?? null;
                $data['redirect_url'] = $response->getRedirectUrl();
            }

            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);

        } catch (\Mdiqbal\LaravelPayments\Exceptions\PaymentException $e) {
            // Payment gateway specific errors
            \Log::error('Payment gateway initialization error', [
                'error' => $e->getMessage(),
                'gateway' => $validated['gateway'] ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = $e->getMessage();
            $userFriendlyMessage = $this->getUserFriendlyPaymentError($errorMessage);
            
            return response()->json([
                'status' => 'error',
                'message' => $userFriendlyMessage,
                'error_type' => 'payment_gateway',
                'error_code' => $this->extractErrorCode($errorMessage)
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Payment initialization error', [
                'error' => $e->getMessage(),
                'gateway' => $validated['gateway'] ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = $e->getMessage();
            $userFriendlyMessage = $this->getUserFriendlyError($errorMessage);
            
            return response()->json([
                'status' => 'error',
                'message' => $userFriendlyMessage,
                'error_type' => 'system'
            ], 500);
        }
    }

    /**
     * Save POS sale (with multiple payment methods)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function saveSale(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'customer_id' => 'nullable|integer',
                'employee_id' => 'nullable|integer',
                'cart_items' => 'required|array|min:1',
                'cart_items.*.product_id' => 'required|integer',
                'cart_items.*.product_type' => 'required|string',
                'cart_items.*.quantity' => 'required|numeric|min:0.001',
                'cart_items.*.unit_price' => 'required|numeric|min:0',
                'cart_items.*.discount' => 'nullable|numeric|min:0',
                'cart_items.*.discount_type' => 'nullable|string|in:fixed,percentage',
                'cart_items.*.selected_imei_serial' => 'nullable|array',
                'cart_items.*.selected_imei_serial.*' => 'nullable|string',
                'cart_items.*.selected_medicine_expiry' => 'nullable|array',
                'cart_items.*.is_promotion_free_item' => 'nullable|boolean',
                'cart_items.*.promotion_id' => 'nullable|integer',
                'cart_items.*.promotion' => 'nullable|array',
                'cart_items.*.has_promotion_discount' => 'nullable|boolean',
                'cart_items.*.selected_medicine_expiry.*.expiry_date' => 'nullable|string',
                'cart_items.*.selected_medicine_expiry.*.quantity' => 'nullable|numeric|min:0',
                'cart_items.*.selected_medicine_expiry.*.stock_quantity' => 'nullable|numeric|min:0',
                'cart_items.*.tax_information' => 'nullable|array',
                'cart_items.*.applicable_tax_id' => 'nullable',
                'cart_items.*.tax_type' => 'nullable|string|in:Exclusive,Inclusive',
                'cart_items.*.combo_items' => 'nullable|array',
                'cart_items.*.item_seller_id' => 'nullable|integer|exists:users,id',
                'subtotal' => 'required|numeric|min:0',
                'tax' => 'required|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                'discount_type' => 'nullable|string|in:fixed,percentage',
                'shipping' => 'nullable|numeric|min:0',
                'total_payable' => 'required|numeric|min:0',
                'payments' => 'nullable|array',
                'payments.*.payment_id' => 'required|integer|exists:payment_methods,id',
                'payments.*.amount' => 'required|numeric|min:0.01',
                'payments.*.gateway_transaction_id' => 'nullable|string',
                'payments.*.gateway_name' => 'nullable|string',
                'total_paid' => 'nullable|numeric|min:0',
                'change_amount' => 'nullable|numeric|min:0',
                'due_amount' => 'nullable|numeric|min:0',
                'sale_as_due' => 'nullable|boolean',
            ]);

            $companyId = session('company.company_id');
            $outletId = session('outlet.outlet_id');
            $userId = auth()->id();

            return DB::transaction(function () use ($validated, $companyId, $outletId, $userId) {
                // Generate sale number
                $saleNo = $this->generateSaleNo($companyId);

                // Calculate totals
                $subtotal = $validated['subtotal'];
                $taxAmt = $validated['tax'];

                $shipping = $validated['shipping'] ?? 0;
                $cartDiscount = $validated['discount'] ?? 0;
                $cartDiscountType = $validated['discount_type'] ?? 'fixed';

                // Calculate item-level discounts
                $totalItemDiscount = 0;
                foreach ($validated['cart_items'] as $item) {
                    $itemSubtotal = $item['quantity'] * $item['unit_price'];
                    $itemDiscount = $item['discount'] ?? 0;
                    $itemDiscountType = $item['discount_type'] ?? 'fixed';
                    
                    if ($itemDiscountType === 'percentage') {
                        $totalItemDiscount += ($itemSubtotal * $itemDiscount) / 100;
                    } else {
                        $totalItemDiscount += $itemDiscount;
                    }
                }

                // Calculate cart-level discount
                $cartDiscountAmount = 0;
                if ($cartDiscount > 0) {
                    if ($cartDiscountType === 'percentage') {
                        $cartDiscountAmount = (($subtotal - $totalItemDiscount) * $cartDiscount) / 100;
                    } else {
                        $cartDiscountAmount = $cartDiscount;
                    }
                }

                $subTotalWithDiscount = $subtotal - $totalItemDiscount;
                $grandTotal = $subTotalWithDiscount - $cartDiscountAmount + $taxAmt + $shipping;

                // Calculate payment totals
                $payments = $validated['payments'] ?? [];
                $totalPaid = $validated['total_paid'] ?? 0;
                $changeAmount = $validated['change_amount'] ?? 0;
                $dueAmount = $validated['due_amount'] ?? 0;
                $saleAsDue = $validated['sale_as_due'] ?? false;

                // If due sale requested, record full amount as due with no payment
                if ($saleAsDue) {
                    $totalPaid = 0;
                    $changeAmount = 0;
                    $dueAmount = $grandTotal;
                } elseif (empty($payments)) {
                    // If no payments provided, use total_payable as paid (backward compatibility)
                    $totalPaid = $grandTotal;
                    $changeAmount = 0;
                    $dueAmount = 0;
                } else {
                    // Calculate from payments if not provided
                    if ($totalPaid == 0) {
                        $totalPaid = array_sum(array_column($payments, 'amount'));
                    }
                    // Calculate change and due
                    if ($totalPaid > $grandTotal) {
                        $changeAmount = $totalPaid - $grandTotal;
                        $dueAmount = 0;
                    } else {
                        $changeAmount = 0;
                        $dueAmount = $grandTotal - $totalPaid;
                    }
                }

                // Credit limit validation: block if customer has credit limit and due would exceed it
                if ($dueAmount > 0 && ($validated['customer_id'] ?? null)) {
                    $customer = Customer::find($validated['customer_id']);
                    if ($customer && $customer->name !== 'Walk-in Customer') {
                        $creditLimit = (float) ($customer->credit_limit ?? 0);
                        if ($creditLimit > 0) {
                            $currentDue = $this->customerService->getCustomerDue($customer->id, $outletId);
                            $currentDue = max(0, $currentDue);
                            $availableCredit = max(0, $creditLimit - $currentDue);
                            if ($dueAmount > $availableCredit) {
                                return response()->json([
                                    'status' => 'error',
                                    'message' => 'Customer credit limit exceeded. Credit limit: ' . number_format($creditLimit, 2) . ', Current due: ' . number_format($currentDue, 2) . ', Available credit: ' . number_format($availableCredit, 2) . '. This sale would add ' . number_format($dueAmount, 2) . ' as due.',
                                ], 422);
                            }
                        }
                    }
                }

                // Calculate sale_vat_objects using new GST tax logic (Intra/Inter-state, Exclusive/Inclusive)
                $saleVatObjects = GstTaxService::buildSaleVatObjects(
                    $validated['cart_items'],
                    $validated['customer_id'] ?? null,
                    $outletId,
                    $companyId
                );

                // Encode to JSON string
                $saleVatObjectsJson = null;
                if (!empty($saleVatObjects)) {
                    $saleVatObjectsJson = json_encode($saleVatObjects);
                    if ($saleVatObjectsJson === false) {
                        $saleVatObjectsJson = null;
                    }
                }

                // Create sale record (without sale_vat_objects to avoid Eloquent processing)
                $sale = Sale::create([
                    'customer_id' => $validated['customer_id'] ?? null,
                    'employee_id' => $validated['employee_id'] ?? null,
                    'sale_no' => $saleNo,
                    'total_items' => count($validated['cart_items']),
                    'sub_total' => $subtotal,
                    'given_amount' => $totalPaid,
                    'paid_amount' => $totalPaid,
                    'change_amount' => $changeAmount,
                    'previous_due' => 0,
                    'due_amount' => $dueAmount,
                    'disc' => $cartDiscountAmount,
                    'disc_actual' => $cartDiscountAmount,
                    'vat' => $taxAmt,
                    'rounding' => 0,
                    'total_payable' => $grandTotal,
                    'total_item_discount_amount' => $totalItemDiscount,
                    'sub_total_with_discount' => $subTotalWithDiscount,
                    'sub_total_discount_amount' => $cartDiscountAmount,
                    'total_discount_amount' => $totalItemDiscount + $cartDiscountAmount,
                    'delivery_charge' => $shipping,
                    'sub_total_discount_value' => $cartDiscount,
                    'sub_total_discount_type' => $cartDiscountType,
                    'sale_date' => now()->toDateString(),
                    'date_time' => now(),
                    'order_time' => now(),
                    'sale_vat_objects' => $saleVatObjectsJson,
                    'grand_total' => $grandTotal,
                    'online_yes_no' => 'No',
                    'user_id' => $userId,
                    'outlet_id' => $outletId,
                    'company_id' => $companyId,
                    'del_status' => 'Live',
                ]);


                // Create sale details
                foreach ($validated['cart_items'] as $item) {
                    $itemSubtotal = $item['quantity'] * $item['unit_price'];
                    $itemDiscount = $item['discount'] ?? 0;
                    $itemDiscountType = $item['discount_type'] ?? 'fixed';
                    
                    $discountAmount = 0;
                    if ($itemDiscountType === 'percentage') {
                        $discountAmount = ($itemSubtotal * $itemDiscount) / 100;
                    } else {
                        $discountAmount = $itemDiscount;
                    }

                    // Get product to find purchase price and unit information
                    $product = Item::find($item['product_id']);
                    $purchasePrice = $product ? ($product->purchase_price ?? 0) : 0;
                    
                    // Fix purchase_price based on unit_type and conversion_rate
                    // If unit_type is not 1 (single), divide purchase_price by conversion_rate
                    if ($product && $product->unit_type != 1) {
                        $conversionRate = $product->conversion_rate ?? 1;
                        if ($conversionRate > 0) {
                            $purchasePrice = $purchasePrice / $conversionRate;
                        }
                    }

                    // Get tax information using new GST logic (Intra/Inter-state, Exclusive/Inclusive)
                    $menuTaxes = GstTaxService::getItemMenuTaxes($item, $validated['customer_id'] ?? null, $outletId, $companyId);
                    $taxPercentage = GstTaxService::getItemTaxPercentage($menuTaxes);
                    $itemTaxAmount = GstTaxService::getItemTaxAmount($menuTaxes);

                    // Get selected IMEI/Serial numbers
                    $selectedImeiSerial = $item['selected_imei_serial'] ?? [];
                    $isIMEISerialProduct = in_array($item['product_type'] ?? '', ['IMEI_Product', 'Serial_Product']);
                    
                    // Get selected Medicine expiry dates
                    $selectedMedicineExpiry = $item['selected_medicine_expiry'] ?? [];
                    $isMedicineProduct = ($item['product_type'] ?? '') === 'Medicine_Product' && !empty($selectedMedicineExpiry);
                    
                    // Get item_seller_id for Service_Product
                    $itemSellerId = null;
                    if (($item['product_type'] ?? '') === 'Service_Product') {
                        $itemSellerId = $item['item_seller_id'] ?? null;
                    }

                    // Check if Combo_Product
                    $isComboProduct = ($item['product_type'] ?? '') === 'Combo_Product';
                    $comboItems = $item['combo_items'] ?? [];
                    
                    // Promotion data handling
                    $isPromotionFreeItem = isset($item['is_promotion_free_item']) && $item['is_promotion_free_item'] == true;
                    $promotionId = $item['promotion_id'] ?? null;
                    $promotion = $item['promotion'] ?? null;
                    $hasPromotionDiscount = isset($item['has_promotion_discount']) && $item['has_promotion_discount'] == true;
                    
                    // Determine is_promo_item and promo_parent_id
                    $isPromoItem = 'No';
                    $promoParentId = null;
                    if ($isPromotionFreeItem) {
                        $isPromoItem = 'Yes';
                        $promoParentId = $promotionId;
                    } elseif ($hasPromotionDiscount && $promotion && isset($promotion['type']) && $promotion['type'] == '1' && isset($promotion['id'])) {
                        $isPromoItem = 'No';
                        $promoParentId = $promotion['id'];
                    } elseif ($promotionId && !$isPromotionFreeItem) {
                        $isPromoItem = 'No';
                        $promoParentId = $promotionId;
                    }
                    
                    // If IMEI/Serial product and has selected IMEI/serial, create one entry per IMEI/serial
                    if ($isIMEISerialProduct && !empty($selectedImeiSerial) && is_array($selectedImeiSerial)) {
                        foreach ($selectedImeiSerial as $imeiSerial) {
                            SaleDetail::create([
                                'item_id' => $item['product_id'],
                                'qty' => 1, // Each IMEI/serial is quantity 1
                                'menu_price_without_discount' => $item['unit_price'],
                                'menu_price_with_discount' => $item['unit_price'] - ($discountAmount / $item['quantity']),
                                'menu_unit_price' => $item['unit_price'],
                                'purchase_price' => $purchasePrice,
                                'menu_vat_percentage' => $taxPercentage,
                                'item_tax_amount' => $itemTaxAmount,
                                'menu_taxes' => !empty($menuTaxes) ? json_encode($menuTaxes) : null,
                                'menu_discount_value' => $itemDiscount,
                                'discount_type' => $itemDiscountType,
                                'discount_amount' => $discountAmount / count($selectedImeiSerial), // Divide discount among entries
                                'item_type' => $item['product_type'] ?? null,
                                'expiry_imei_serial' => $imeiSerial, // Store IMEI/serial in this field
                                'item_seller_id' => $itemSellerId, // Store employee ID for Service_Product
                                'is_promo_item' => $isPromoItem,
                                'promo_parent_id' => $promoParentId,
                                'sales_id' => $sale->id,
                                'user_id' => $userId,
                                'outlet_id' => $outletId,
                                'company_id' => $companyId,
                                'del_status' => 'Live',
                            ]);
                        }
                    } elseif ($isMedicineProduct && !empty($selectedMedicineExpiry) && is_array($selectedMedicineExpiry)) {
                        // If Medicine_Product with expiry dates, create one entry per expiry date
                        $totalMedicineQty = array_sum(array_column($selectedMedicineExpiry, 'quantity'));
                        foreach ($selectedMedicineExpiry as $medExpiry) {
                            $expiryDate = $medExpiry['expiry_date'] ?? '';
                            $medQuantity = floatval($medExpiry['quantity'] ?? 1);
                            
                            SaleDetail::create([
                                'item_id' => $item['product_id'],
                                'qty' => $medQuantity,
                                'menu_price_without_discount' => $item['unit_price'],
                                'menu_price_with_discount' => $item['unit_price'] - ($discountAmount / $totalMedicineQty),
                                'menu_unit_price' => $item['unit_price'],
                                'purchase_price' => $purchasePrice,
                                'menu_vat_percentage' => $taxPercentage,
                                'item_tax_amount' => $itemTaxAmount,
                                'menu_taxes' => !empty($menuTaxes) ? json_encode($menuTaxes) : null,
                                'menu_discount_value' => $itemDiscount,
                                'discount_type' => $itemDiscountType,
                                'discount_amount' => ($discountAmount / $totalMedicineQty) * $medQuantity, // Divide discount proportionally
                                'item_type' => $item['product_type'] ?? null,
                                'expiry_imei_serial' => $expiryDate, // Store as "quantity - expiry_date"
                                'item_seller_id' => $itemSellerId,
                                'is_promo_item' => $isPromoItem,
                                'promo_parent_id' => $promoParentId,
                                'sales_id' => $sale->id,
                                'user_id' => $userId,
                                'outlet_id' => $outletId,
                                'company_id' => $companyId,
                                'del_status' => 'Live',
                            ]);
                        }
                    } else {
                        // Regular product or Combo_Product - create single entry
                        $saleDetail = SaleDetail::create([
                            'item_id' => $item['product_id'],
                            'qty' => $item['quantity'],
                            'menu_price_without_discount' => $item['unit_price'],
                            'menu_price_with_discount' => $item['unit_price'] - ($discountAmount / $item['quantity']),
                            'menu_unit_price' => $item['unit_price'],
                            'purchase_price' => $purchasePrice,
                            'menu_vat_percentage' => $taxPercentage,
                            'item_tax_amount' => $itemTaxAmount,
                            'menu_taxes' => !empty($menuTaxes) ? json_encode($menuTaxes) : null,
                            'menu_discount_value' => $itemDiscount,
                            'discount_type' => $itemDiscountType,
                            'discount_amount' => $discountAmount,
                            'item_type' => $item['product_type'] ?? null,
                            'expiry_imei_serial' => null,
                            'item_seller_id' => $itemSellerId, // Store employee ID for Service_Product
                            'is_promo_item' => $isPromoItem,
                            'promo_parent_id' => $promoParentId,
                            'sales_id' => $sale->id,
                            'user_id' => $userId,
                            'outlet_id' => $outletId,
                            'company_id' => $companyId,
                            'del_status' => 'Live',
                        ]);
                        
                        // If Combo_Product, save combo items to combo_sales table
                        if ($isComboProduct && !empty($comboItems) && is_array($comboItems)) {
                            foreach ($comboItems as $comboItem) {
                                // Get combo item product to determine type
                                $comboItemProduct = Item::find($comboItem['item_id'] ?? null);
                                $comboItemType = $comboItemProduct ? ($comboItemProduct->type ?? null) : null;
                                
                                ComboSale::create([
                                    'sale_id' => $sale->id,
                                    'combo_sale_item_id' => $saleDetail->id,
                                    'show_in_invoice' => ($comboItem['show_in_invoice'] === true || $comboItem['show_in_invoice'] === 'Yes' || $comboItem['show_in_invoice'] === 1) ? 'Yes' : 'No',
                                    'combo_item_id' => $comboItem['item_id'] ?? null,
                                    'combo_item_type' => $comboItemType,
                                    'combo_item_qty' => $comboItem['quantity'] ?? 0,
                                    'combo_item_price' => $comboItem['unit_price'] ?? $comboItem['amount'] ?? 0,
                                    'combo_item_seller_id' => $comboItem['employee_id'] ?? null,
                                    'outlet_id' => $outletId,
                                    'user_id' => $userId,
                                    'company_id' => $companyId,
                                    'del_status' => 'Live',
                                ]);
                            }
                        }
                    }
                }
                
                // Create sale payments (multiple payment methods)
                if (!empty($payments)) {
                    foreach ($payments as $payment) {
                        // Get payment method name
                        $paymentMethod = PaymentMethod::find($payment['payment_id']);
                        $paymentName = $paymentMethod ? $paymentMethod->name : 'Unknown';
                        // Prepare payment data
                        $paymentData = [
                            'date' => now()->toDateString(),
                            'payment_id' => $payment['payment_id'],
                            'amount' => $payment['amount'],
                            'sale_id' => $sale->id,
                            'payment_name' => $paymentName,
                            'outlet_id' => $outletId,
                            'user_id' => $userId,
                            'company_id' => $companyId,
                            'del_status' => 'Live',
                        ];
                        // Store gateway transaction ID if provided
                        if (isset($payment['gateway_transaction_id'])) {
                            $paymentData['gateway_transaction_id'] = $payment['gateway_transaction_id'];
                        }
                        if (isset($payment['gateway_name'])) {
                            $paymentData['gateway_name'] = $payment['gateway_name'];
                        }
                        SalePayment::create($paymentData);
                    }
                } else {
                    // Fallback: Create single cash payment (backward compatibility)
                    SalePayment::create([
                        'date' => now()->toDateString(),
                        'payment_id' => 1, // Cash payment
                        'amount' => $grandTotal,
                        'sale_id' => $sale->id,
                        'payment_name' => 'Cash',
                        'outlet_id' => $outletId,
                        'user_id' => $userId,
                        'company_id' => $companyId,
                        'del_status' => 'Live',
                    ]);
                }
                
                // Get ZATCA configuration once for both Phase 1 and Phase 2
                $zatcaPhase1QrCode = null;
                $zatcaInvoice = null;
                $company = Company::find($companyId);
                $zatcaConfig = [];
                
                if ($company && $company->zatca_configuration) {
                    $decoded = json_decode($company->zatca_configuration, true);
                    $zatcaConfig = is_array($decoded) ? $decoded : [];
                }
                
                // Get selected ZATCA phase (0 = None, 1 = Phase 1, 2 = Phase 2)
                $zatcaPhase = $zatcaConfig['zatca_phase'] ?? '0';
                
                // For backward compatibility, check old zatca_1 and zatca_2 if zatca_phase is not set
                if ($zatcaPhase == '0' || !isset($zatcaConfig['zatca_phase'])) {
                    if (isset($zatcaConfig['zatca_1']) && $zatcaConfig['zatca_1'] == '1') {
                        $zatcaPhase = '1';
                    } elseif (isset($zatcaConfig['zatca_2']) && $zatcaConfig['zatca_2'] == '1') {
                        $zatcaPhase = '2';
                    }
                }
                
                // Process ZATCA Phase 1 (if enabled) - Simple QR code generation
                // Phase 1 only requires QR code generation, no API submission
                if ($zatcaPhase == '1') {
                    try {
                        $phase1Service = app(ZatcaPhase1Service::class);
                        $zatcaPhase1QrCode = $phase1Service->generateQRCodeJSON($sale, $zatcaConfig);
                        
                        // Update sale with Phase 1 QR code
                        if ($zatcaPhase1QrCode) {
                            $sale->update(['zatca_phase1_qr_code' => $zatcaPhase1QrCode]);
                        }
                    } catch (\Exception $e) {
                        \Log::error('ZATCA Phase 1 QR code generation failed', [
                            'sale_id' => $sale->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        // Don't fail the sale if Phase 1 fails
                    }
                }
                
                // Process ZATCA Phase 2 compliance (if enabled) - NON-BLOCKING
                // ZATCA Phase 2 processing should not block sale saving or cause offline detection
                if ($zatcaPhase == '2' && !empty($zatcaConfig) && isset($zatcaConfig['zatca_status']) && $zatcaConfig['zatca_status'] == 'Enable') {
                    try {
                        // Try to process ZATCA synchronously first (fast path)
                        // If it fails or takes too long, we'll queue it
                        $isOffline = false; // Don't check connection - let ZATCA API handle it
                        $zatcaService = app(\Modules\Sale\Services\Zatca\ZatcaService::class);

                        // Process invoice - wrap in try-catch to ensure it doesn't block
                        $zatcaInvoice = $zatcaService->processInvoice($sale, $isOffline);

                    } catch (\Exception $e) {
                        \Log::error('ZATCA processing failed during sale save - will queue for retry', [
                            'sale_id' => $sale->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ]);
                        
                        // Queue for background processing instead
                        try {
                            \Modules\Sale\Jobs\ProcessZatcaInvoiceJob::dispatch($sale->id)
                                ->onQueue(config('zatca.queue_name', 'zatca'));
                        } catch (\Exception $queueError) {
                            \Log::error('Failed to queue ZATCA job', [
                                'sale_id' => $sale->id,
                                'error' => $queueError->getMessage()
                            ]);
                        }
                        
                        // Don't fail the sale if ZATCA processing fails
                        // Sale is already saved successfully at this point
                        $zatcaInvoice = null; // Ensure it's null on error
                    }
                }

                // Send sale invoice via Email and/or WhatsApp if requested (follow Quotation flow)
                $sendEmail = !empty($validated['send_email']);
                $sendWhatsapp = !empty($validated['send_whatsapp']);
                if (($sendEmail || $sendWhatsapp) && $sale->customer_id) {
                    try {
                        $sale->load([
                            'customer',
                            'employee',
                            'company',
                            'outlet',
                            'saleDetails' => function ($q) {
                                $q->where('del_status', 'Live');
                            },
                            'saleDetails.item.parent',
                            'salePayments' => function ($q) {
                                $q->where('del_status', 'Live');
                            },
                            'salePayments.paymentMethod',
                        ]);
                        $this->ensureZatcaQRCodeForSale($sale);
                        $pdf = $this->generateSaleInvoicePdf($sale);
                        $customer = $sale->customer;
                        $customerName = $customer ? $customer->name : '';

                        if ($sendEmail && $customer && !empty(trim($customer->email ?? ''))) {
                            $companyName = $sale->company->business_name ?? config('app.name');
                            $subject = __('Your Sale Invoice') . ' - ' . $sale->sale_no . ' | ' . $companyName;
                            $message = __('Dear') . ' ' . ($customerName ?: __('Customer')) . "\n\n"
                                . __('Please find your sale invoice attached to this email.') . "\n\n" . __('Thank you for your business.');
                            $emailService = app(\Modules\Configuration\Services\EmailService::class);
                            $emailService->sendEmail(
                                $customer->email,
                                $subject,
                                $message,
                                [],
                                'Sale',
                                ['content' => $pdf['content'], 'filename' => $pdf['filename']],
                                ['referenceNo' => $sale->sale_no, 'customerName' => $customerName]
                            );
                        }

                        if ($sendWhatsapp && $customer && !empty(trim($customer->phone ?? ''))) {
                            $whatsappService = app(\Modules\Configuration\Services\WhatsappService::class);
                            $waMessage = __('Your sale invoice') . ' ' . $sale->sale_no . '. ' . __('Please find the invoice attached to your email.');
                            $whatsappService->sendTestWhatsappWithAttachment(
                                $customer->phone,
                                $waMessage,
                                $pdf['content'] ?? null,
                                $pdf['filename'] ?? null
                            );
                        }
                    } catch (\Exception $e) {
                        \Log::error('POS send invoice email/whatsapp failed', [
                            'sale_id' => $sale->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }

                try {
                    DB::statement("TRUNCATE TABLE view_stock_detail");
                    DB::statement("INSERT INTO view_stock_detail SELECT item_id, 1 AS type, quantity_amount AS stock_quantity, outlet_id, company_id, del_status FROM purchase_details WHERE del_status = 'Live' AND quantity_amount > 0 UNION ALL SELECT item_id, 2 AS type, qty AS stock_quantity, outlet_id, company_id, del_status FROM sale_details WHERE del_status = 'Live' AND qty > 0 UNION ALL SELECT item_id, 1 AS type, stock_quantity, outlet_id, company_id, 'Live' AS del_status FROM set_opening_stocks WHERE stock_quantity > 0");
                } catch (\Exception $e) {
                    \Log::error('Failed to refresh stock view after sale: ' . $e->getMessage());
                }

                try {
                    if (!empty($request->customer_id) && $request->customer_id != 1) {
                        $settings = \Modules\BusinessClub\Models\BusinessClubSetting::where('company_id', $companyId)->where('del_status', 'Live')->first();
                        if ($settings && $settings->profit_percentage > 0 && $sale->grand_total >= $settings->minimum_bill_amount) {
                            $sale->load('saleDetails');
                            $totalProfit = 0;
                            foreach ($sale->saleDetails as $detail) {
                                $profit = ($detail->menu_price_with_discount - $detail->purchase_price) * $detail->qty;
                                if ($profit > 0) $totalProfit += $profit;
                            }
                            if ($totalProfit > 0) {
                                $percentage = $settings->profit_percentage / 100;
                                $creditAmount = round($totalProfit * $percentage, 2);
                                if ($creditAmount > 0) {
                                    $svc = app(\Modules\BusinessClub\Services\BusinessClubService::class);
                                    $svc->creditWalletFromSale($request->customer_id, $totalProfit, $sale->id, $companyId);
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('BusinessClub wallet credit failed: ' . $e->getMessage());
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Sale saved successfully',
                    'sale_id' => $sale->id,
                    'sale_no' => $sale->sale_no,
                    'encrypted_id' => $sale->encrypted_id,
                    'zatca_phase1' => $zatcaPhase1QrCode ? [
                        'qr_code' => $zatcaPhase1QrCode,
                    ] : null,
                    'zatca' => $zatcaInvoice ? [
                        'status' => $zatcaInvoice->zatca_status,
                        'uuid' => $zatcaInvoice->uuid,
                        'qr_code' => $zatcaInvoice->qr_code,
                    ] : null,
                ]);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Mdiqbal\LaravelPayments\Exceptions\PaymentException $e) {
            // Payment gateway specific errors
            \Log::error('Payment gateway error during sale save', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            // Extract meaningful error message
            $errorMessage = $e->getMessage();
            $userFriendlyMessage = $this->getUserFriendlyPaymentError($errorMessage);
            
            return response()->json([
                'status' => 'error',
                'message' => $userFriendlyMessage,
                'error_type' => 'payment_gateway',
                'error_code' => $this->extractErrorCode($errorMessage)
            ], 400);
        } catch (\Illuminate\Database\QueryException $e) {
            // Database errors
            \Log::error('Database error during sale save', [
                'error' => $e->getMessage(),
                'sql' => $e->getSql() ?? null,
                'bindings' => $e->getBindings() ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Check for specific database errors
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            if (strpos($errorMessage, 'Duplicate entry') !== false) {
                $userMessage = 'A record with this information already exists. Please check and try again.';
            } elseif (strpos($errorMessage, 'foreign key constraint') !== false) {
                $userMessage = 'Cannot save sale: Related record not found. Please refresh and try again.';
            } elseif (strpos($errorMessage, 'Connection') !== false) {
                $userMessage = 'Database connection error. Please check your connection and try again.';
            } else {
                $userMessage = 'Database error occurred while saving the sale. Please try again.';
            }
            
            return response()->json([
                'status' => 'error',
                'message' => $userMessage,
                'error_type' => 'database'
            ], 500);
        } catch (\Exception $e) {
            // General errors - log full details but show user-friendly message
            \Log::error('Unexpected error during sale save', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'customer_id' => $request->input('customer_id'),
                    'total_payable' => $request->input('total_payable'),
                    'payment_count' => count($request->input('payments', [])),
                ]
            ]);
            
            // Check if it's a credential/configuration error
            $errorMessage = $e->getMessage();
            $userFriendlyMessage = $this->getUserFriendlyError($errorMessage);
            
            return response()->json([
                'status' => 'error',
                'message' => $userFriendlyMessage,
                'error_type' => 'system'
            ], 500);
        }
    }

    /**
     * Generate unique sale number
     * 
     * @param int $companyId
     * @return string
     */
    private function generateSaleNo(int $companyId): string
    {
        $prefix = 'SALE-' . date('Y') . '-';

        // Desktop POS rows ("SALE-2026-C1D3-000001") ko ignore karo — sirf pure
        // numeric suffix wale (web format) rows ka max lo. Pehle substring parsing
        // desktop rows par 0 deta tha aur web bar-bar same number bana leta tha
        // (duplicate sale_no).
        $pos = strlen($prefix) + 1; // 1-based SUBSTRING position
        $lastNumber = (int) DB::table('sales')
            ->where('company_id', $companyId)
            ->where('sale_no', 'like', $prefix . '%')
            ->where('sale_no', 'regexp', '^' . preg_quote($prefix, '/') . '[0-9]+$')
            ->selectRaw('MAX(CAST(SUBSTRING(sale_no, ' . $pos . ') AS UNSIGNED)) as mx')
            ->value('mx');

        return $prefix . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate unique hold reference number
     * 
     * @param int $companyId
     * @return string
     */
    private function generateHoldNo(int $companyId): string
    {
        $prefix = 'HOLD-' . date('Y') . '-';
        $lastHold = Hold::where('hold_no', 'like', $prefix . '%')
            ->where('company_id', $companyId)
            ->orderBy('hold_no', 'desc')
            ->first();

        if ($lastHold) {
            $lastNumber = (int) substr($lastHold->hold_no, strlen($prefix));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get next hold reference number
     * 
     * @return JsonResponse
     */
    public function getNextHoldNo(): JsonResponse
    {
        try {
            $companyId = session('company.company_id');
            $holdNo = $this->generateHoldNo($companyId);
            
            return response()->json([
                'status' => 'success',
                'hold_no' => $holdNo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate hold number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save POS hold sale
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function saveHold(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'hold_no' => 'required|string|unique:holds,hold_no',
                'customer_id' => 'nullable|integer',
                'employee_id' => 'nullable|integer',
                'cart_items' => 'required|array|min:1',
                'cart_items.*.product_id' => 'required|integer',
                'cart_items.*.product_type' => 'required|string',
                'cart_items.*.quantity' => 'required|numeric|min:0.001',
                'cart_items.*.unit_price' => 'required|numeric|min:0',
                'cart_items.*.discount' => 'nullable|numeric|min:0',
                'cart_items.*.discount_type' => 'nullable|string|in:fixed,percentage',
                'cart_items.*.selected_imei_serial' => 'nullable|array',
                'cart_items.*.selected_imei_serial.*' => 'nullable|string',
                'cart_items.*.selected_medicine_expiry' => 'nullable|array',
                'cart_items.*.selected_medicine_expiry.*.expiry_date' => 'nullable|string',
                'cart_items.*.selected_medicine_expiry.*.quantity' => 'nullable|numeric|min:0',
                'cart_items.*.selected_medicine_expiry.*.stock_quantity' => 'nullable|numeric|min:0',
                'cart_items.*.tax_information' => 'nullable|array',
                'cart_items.*.applicable_tax_id' => 'nullable',
                'cart_items.*.tax_type' => 'nullable|string|in:Exclusive,Inclusive',
                'cart_items.*.combo_items' => 'nullable|array',
                'cart_items.*.item_seller_id' => 'nullable|integer|exists:users,id',
                'subtotal' => 'required|numeric|min:0',
                'tax' => 'required|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                'discount_type' => 'nullable|string|in:fixed,percentage',
                'shipping' => 'nullable|numeric|min:0',
                'total_payable' => 'required|numeric|min:0',
            ]);

            $companyId = session('company.company_id');
            $outletId = session('outlet.outlet_id');
            $userId = auth()->id();

            return DB::transaction(function () use ($validated, $companyId, $outletId, $userId) {
                // Calculate totals (same as sale)
                $subtotal = $validated['subtotal'];
                $taxAmt = $validated['tax'];
                $shipping = $validated['shipping'] ?? 0;
                $cartDiscount = $validated['discount'] ?? 0;
                $cartDiscountType = $validated['discount_type'] ?? 'fixed';

                // Calculate item-level discounts
                $totalItemDiscount = 0;
                foreach ($validated['cart_items'] as $item) {
                    $itemSubtotal = $item['quantity'] * $item['unit_price'];
                    $itemDiscount = $item['discount'] ?? 0;
                    $itemDiscountType = $item['discount_type'] ?? 'fixed';
                    
                    if ($itemDiscountType === 'percentage') {
                        $totalItemDiscount += ($itemSubtotal * $itemDiscount) / 100;
                    } else {
                        $totalItemDiscount += $itemDiscount;
                    }
                }

                // Calculate cart-level discount
                $cartDiscountAmount = 0;
                if ($cartDiscount > 0) {
                    if ($cartDiscountType === 'percentage') {
                        $cartDiscountAmount = (($subtotal - $totalItemDiscount) * $cartDiscount) / 100;
                    } else {
                        $cartDiscountAmount = $cartDiscount;
                    }
                }

                $subTotalWithDiscount = $subtotal - $totalItemDiscount;
                $grandTotal = $subTotalWithDiscount - $cartDiscountAmount + $taxAmt + $shipping;

                // Calculate sale_vat_objects using new GST tax logic
                $saleVatObjects = GstTaxService::buildSaleVatObjects(
                    $validated['cart_items'],
                    $validated['customer_id'] ?? null,
                    $outletId,
                    $companyId
                );
                $saleVatObjectsJson = !empty($saleVatObjects) ? json_encode($saleVatObjects) : null;
                if ($saleVatObjectsJson === false) {
                    $saleVatObjectsJson = null;
                }

                // Create hold record
                $hold = Hold::create([
                    'customer_id' => $validated['customer_id'] ?? null,
                    'employee_id' => $validated['employee_id'] ?? null,
                    'hold_no' => $validated['hold_no'],
                    'total_items' => count($validated['cart_items']),
                    'sub_total' => $subtotal,
                    'paid_amount' => 0,
                    'due_amount' => $grandTotal,
                    'disc' => $cartDiscountAmount,
                    'disc_actual' => $cartDiscountAmount,
                    'vat' => $taxAmt,
                    'total_payable' => $grandTotal,
                    'total_item_discount_amount' => $totalItemDiscount,
                    'sub_total_with_discount' => $subTotalWithDiscount,
                    'sub_total_discount_amount' => $cartDiscountAmount,
                    'total_discount_amount' => $totalItemDiscount + $cartDiscountAmount,
                    'delivery_charge' => $shipping,
                    'sub_total_discount_value' => $cartDiscount,
                    'sub_total_discount_type' => $cartDiscountType,
                    'sale_date' => now()->toDateString(),
                    'date_time' => now(),
                    'sale_time' => now()->toTimeString(),
                    'sale_vat_objects' => $saleVatObjectsJson,
                    'user_id' => $userId,
                    'outlet_id' => $outletId,
                    'company_id' => $companyId,
                    'del_status' => 'Live',
                ]);

                // Create hold details
                foreach ($validated['cart_items'] as $item) {
                    $itemSubtotal = $item['quantity'] * $item['unit_price'];
                    $itemDiscount = $item['discount'] ?? 0;
                    $itemDiscountType = $item['discount_type'] ?? 'fixed';
                    
                    $discountAmount = 0;
                    if ($itemDiscountType === 'percentage') {
                        $discountAmount = ($itemSubtotal * $itemDiscount) / 100;
                    } else {
                        $discountAmount = $itemDiscount;
                    }

                    $product = Item::find($item['product_id']);
                    $purchasePrice = $product ? ($product->purchase_price ?? 0) : 0;
                    
                    if ($product && $product->unit_type != 1) {
                        $conversionRate = $product->conversion_rate ?? 1;
                        if ($conversionRate > 0) {
                            $purchasePrice = $purchasePrice / $conversionRate;
                        }
                    }

                    $menuTaxes = GstTaxService::getItemMenuTaxes($item, $validated['customer_id'] ?? null, $outletId, $companyId);
                    $taxPercentage = GstTaxService::getItemTaxPercentage($menuTaxes);
                    $itemTaxAmount = GstTaxService::getItemTaxAmount($menuTaxes);

                    $selectedImeiSerial = $item['selected_imei_serial'] ?? [];
                    $isIMEISerialProduct = in_array($item['product_type'] ?? '', ['IMEI_Product', 'Serial_Product']);
                    
                    $selectedMedicineExpiry = $item['selected_medicine_expiry'] ?? [];
                    $isMedicineProduct = ($item['product_type'] ?? '') === 'Medicine_Product' && !empty($selectedMedicineExpiry);
                    
                    $itemSellerId = null;
                    if (($item['product_type'] ?? '') === 'Service_Product') {
                        $itemSellerId = $item['item_seller_id'] ?? null;
                    }

                    $isComboProduct = ($item['product_type'] ?? '') === 'Combo_Product';
                    $comboItems = $item['combo_items'] ?? [];
                    
                    // Promotion data handling
                    $isPromotionFreeItem = isset($item['is_promotion_free_item']) && $item['is_promotion_free_item'] == true;
                    $promotionId = $item['promotion_id'] ?? null;
                    $promotion = $item['promotion'] ?? null;
                    $hasPromotionDiscount = isset($item['has_promotion_discount']) && $item['has_promotion_discount'] == true;
                    
                    // Determine is_promo_item and promo_parent_id
                    $isPromoItem = 'No';
                    $promoParentId = null;
                    
                    if ($isPromotionFreeItem && $promotionId) {
                        // Free item (Y item) - mark as promo item and link to promotion
                        $isPromoItem = 'Yes';
                        $promoParentId = $promotionId;
                    } elseif ($hasPromotionDiscount && $promotion && isset($promotion['type']) && $promotion['type'] == '1' && isset($promotion['id'])) {
                        // Discount promotion (Type 1) - link to promotion but not marked as free item
                        $isPromoItem = 'No';
                        $promoParentId = $promotion['id'];
                    } elseif ($promotionId && !$isPromotionFreeItem) {
                        // Other promotion types (like Buy X Get Y main item) - just link promotion ID
                        $isPromoItem = 'No';
                        $promoParentId = $promotionId;
                    }
                    
                    if ($isIMEISerialProduct && !empty($selectedImeiSerial) && is_array($selectedImeiSerial)) {
                        foreach ($selectedImeiSerial as $imeiSerial) {
                            HoldDetail::create([
                                'item_id' => $item['product_id'],
                                'qty' => 1,
                                'menu_price_without_discount' => $item['unit_price'],
                                'menu_price_with_discount' => $item['unit_price'] - ($discountAmount / $item['quantity']),
                                'menu_unit_price' => $item['unit_price'],
                                'menu_vat_percentage' => $taxPercentage,
                                'item_tax_amount' => $itemTaxAmount,
                                'menu_taxes' => !empty($menuTaxes) ? json_encode($menuTaxes) : null,
                                'menu_discount_value' => $itemDiscount,
                                'discount_type' => $itemDiscountType,
                                'discount_amount' => $discountAmount / count($selectedImeiSerial),
                                'item_type' => $item['product_type'] ?? null,
                                'expiry_imei_serial' => $imeiSerial,
                                'item_seller_id' => $itemSellerId,
                                'is_promo_item' => $isPromoItem,
                                'promo_parent_id' => $promoParentId,
                                'holds_id' => $hold->id,
                                'user_id' => $userId,
                                'outlet_id' => $outletId,
                                'company_id' => $companyId,
                                'del_status' => 'Live',
                            ]);
                        }
                    } elseif ($isMedicineProduct && !empty($selectedMedicineExpiry) && is_array($selectedMedicineExpiry)) {
                        // If Medicine_Product with expiry dates, create one entry per expiry date
                        $totalMedicineQty = array_sum(array_column($selectedMedicineExpiry, 'quantity'));
                        foreach ($selectedMedicineExpiry as $medExpiry) {
                            $expiryDate = $medExpiry['expiry_date'] ?? '';
                            $medQuantity = floatval($medExpiry['quantity'] ?? 1);
                            
                            // Format: "quantity - expiry_date" for consistency with other modules
                            $expiryImeiSerialValue = $medQuantity . ' - ' . $expiryDate;
                            
                            HoldDetail::create([
                                'item_id' => $item['product_id'],
                                'qty' => $medQuantity,
                                'menu_price_without_discount' => $item['unit_price'],
                                'menu_price_with_discount' => $item['unit_price'] - ($discountAmount / $totalMedicineQty),
                                'menu_unit_price' => $item['unit_price'],
                                'menu_vat_percentage' => $taxPercentage,
                                'item_tax_amount' => $itemTaxAmount,
                                'menu_taxes' => !empty($menuTaxes) ? json_encode($menuTaxes) : null,
                                'menu_discount_value' => $itemDiscount,
                                'discount_type' => $itemDiscountType,
                                'discount_amount' => ($discountAmount / $totalMedicineQty) * $medQuantity,
                                'item_type' => $item['product_type'] ?? null,
                                'expiry_imei_serial' => $expiryImeiSerialValue,
                                'item_seller_id' => $itemSellerId,
                                'is_promo_item' => $isPromoItem,
                                'promo_parent_id' => $promoParentId,
                                'holds_id' => $hold->id,
                                'user_id' => $userId,
                                'outlet_id' => $outletId,
                                'company_id' => $companyId,
                                'del_status' => 'Live',
                            ]);
                        }
                    } else {
                        $holdDetail = HoldDetail::create([
                            'item_id' => $item['product_id'],
                            'qty' => $item['quantity'],
                            'menu_price_without_discount' => $item['unit_price'],
                            'menu_price_with_discount' => $item['unit_price'] - ($discountAmount / $item['quantity']),
                            'menu_unit_price' => $item['unit_price'],
                            'menu_vat_percentage' => $taxPercentage,
                            'item_tax_amount' => $itemTaxAmount,
                            'menu_taxes' => !empty($menuTaxes) ? json_encode($menuTaxes) : null,
                            'menu_discount_value' => $itemDiscount,
                            'discount_type' => $itemDiscountType,
                            'discount_amount' => $discountAmount,
                            'item_type' => $item['product_type'] ?? null,
                            'expiry_imei_serial' => null,
                            'item_seller_id' => $itemSellerId,
                            'is_promo_item' => $isPromoItem,
                            'promo_parent_id' => $promoParentId,
                            'holds_id' => $hold->id,
                            'user_id' => $userId,
                            'outlet_id' => $outletId,
                            'company_id' => $companyId,
                            'del_status' => 'Live',
                        ]);
                        
                        // If Combo_Product, save combo items
                        if ($isComboProduct && !empty($comboItems) && is_array($comboItems)) {
                            foreach ($comboItems as $comboItem) {
                                $comboItemProduct = Item::find($comboItem['item_id'] ?? null);
                                $comboItemType = $comboItemProduct ? ($comboItemProduct->type ?? null) : null;
                                
                                HoldComboItem::create([
                                    'sale_id' => $hold->id,
                                    'combo_sale_item_id' => $holdDetail->id,
                                    'show_in_invoice' => ($comboItem['show_in_invoice'] === true || $comboItem['show_in_invoice'] === 'Yes' || $comboItem['show_in_invoice'] === 1) ? 'Yes' : 'No',
                                    'combo_item_id' => $comboItem['item_id'] ?? null,
                                    'combo_item_type' => $comboItemType,
                                    'combo_item_qty' => $comboItem['quantity'] ?? 0,
                                    'combo_item_price' => $comboItem['unit_price'] ?? $comboItem['amount'] ?? 0,
                                    'combo_item_seller_id' => $comboItem['employee_id'] ?? null,
                                    'outlet_id' => $outletId,
                                    'user_id' => $userId,
                                    'company_id' => $companyId,
                                    'del_status' => 'Live',
                                ]);
                            }
                        }
                    }
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Hold saved successfully',
                    'hold_id' => $hold->id,
                    'hold_no' => $hold->hold_no,
                ]);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save hold: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of holds (DataTable format)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getHolds(Request $request): JsonResponse
    {
        try {
            $companyId = session('company.company_id');
            $outletId = session('outlet.outlet_id');
            
            // Check if this is a DataTable request
            if ($request->ajax()) {
                $draw = $request->input('draw', 1);
                $start = $request->input('start', 0);
                $length = $request->input('length', 10);
                $search = $request->input('search.value', '');
                
                $query = Hold::where('company_id', $companyId)
                    ->where('outlet_id', $outletId)
                    ->where('del_status', 'Live')
                    ->with(['customer:id,name', 'employee:id,name']);
                
                // Apply search filter
                if (!empty($search)) {
                    $query->where(function($q) use ($search) {
                        $q->where('hold_no', 'like', '%' . $search . '%')
                          ->orWhereHas('customer', function($q) use ($search) {
                              $q->where('name', 'like', '%' . $search . '%');
                          })
                          ->orWhereHas('employee', function($q) use ($search) {
                              $q->where('name', 'like', '%' . $search . '%');
                          });
                    });
                }
                
                // Get total count
                $totalRecords = Hold::where('company_id', $companyId)
                    ->where('outlet_id', $outletId)
                    ->where('del_status', 'Live')
                    ->count();
                
                // Get filtered count
                $filteredRecords = $query->count();
                
                // Get paginated data
                $holds = $query->orderBy('created_at', 'desc')
                    ->skip($start)
                    ->take($length)
                    ->get()
                    ->map(function ($hold) {
                        return [
                            'id' => $hold->id,
                            'hold_no' => $hold->hold_no,
                            'customer_name' => $hold->customer ? $hold->customer->name : 'Walk-in Customer',
                            'employee_name' => $hold->employee ? $hold->employee->name : 'N/A',
                            'total_items' => $hold->total_items,
                            'total_payable' => $hold->total_payable,
                            'date_time' => $hold->date_time,
                            'encrypted_id' => $hold->encrypted_id,
                        ];
                    });
                
                return response()->json([
                    'draw' => intval($draw),
                    'recordsTotal' => $totalRecords,
                    'recordsFiltered' => $filteredRecords,
                    'data' => $holds
                ]);
            }
            
            // For non-AJAX requests, return simple format
            $holds = Hold::where('company_id', $companyId)
                ->where('outlet_id', $outletId)
                ->where('del_status', 'Live')
                ->with(['customer:id,name', 'employee:id,name'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($hold) {
                    return [
                        'id' => $hold->id,
                        'hold_no' => $hold->hold_no,
                        'customer_name' => $hold->customer ? $hold->customer->name : 'N/A',
                        'employee_name' => $hold->employee ? $hold->employee->name : 'N/A',
                        'total_items' => $hold->total_items,
                        'total_payable' => $hold->total_payable,
                        'sale_date' => $hold->sale_date,
                        'date_time' => $hold->date_time,
                        'created_at' => $hold->created_at,
                    ];
                });
            
            return response()->json([
                'status' => 'success',
                'data' => $holds
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch holds: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single hold with details
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function getHold($id): JsonResponse
    {
        try {
            $companyId = session('company.company_id');
            $outletId = session('outlet.outlet_id');
            
            $hold = Hold::where('id', $id)
                ->where('company_id', $companyId)
                ->where('outlet_id', $outletId)
                ->where('del_status', 'Live')
                ->with([
                    'customer:id,name,phone,email',
                    'employee:id,name',
                    'holdDetails.item:id,name,code,type',
                    'holdComboItems.comboItem:id,name,code',
                    'holdComboItems.comboSaleItem:id'
                ])
                ->first();
            
            if (!$hold) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hold not found'
                ], 404);
            }
            
            $holdDetails = $hold->holdDetails->map(function ($detail) use ($hold) {
                $comboItems = $hold->holdComboItems->where('combo_sale_item_id', $detail->id)->map(function ($combo) {
                    return [
                        'item_id' => $combo->combo_item_id,
                        'item_name' => $combo->comboItem ? $combo->comboItem->name : 'N/A',
                        'quantity' => $combo->combo_item_qty,
                        'unit_price' => $combo->combo_item_price,
                        'show_in_invoice' => $combo->show_in_invoice,
                        'employee_id' => $combo->combo_item_seller_id,
                    ];
                })->values();
                
                // Format tax information to match cart format
                $taxInformation = [];
                if ($detail->menu_taxes) {
                    $menuTaxes = json_decode($detail->menu_taxes, true);
                    if (is_array($menuTaxes)) {
                        foreach ($menuTaxes as $tax) {
                            $taxInformation[] = [
                                'tax_field_name' => $tax['tax_field_name'] ?? '',
                                'tax_field_percentage' => floatval($tax['tax_field_percentage'] ?? 0)
                            ];
                        }
                    }
                }
                
                $item = $detail->item;
                return [
                    'product_id' => $detail->item_id,
                    'product_name' => $item ? $item->name : 'N/A',
                    'product_code' => $item ? $item->code : 'N/A',
                    'product_type' => $detail->item_type,
                    'quantity' => $detail->qty,
                    'unit_price' => $detail->menu_unit_price,
                    'discount' => $detail->menu_discount_value,
                    'discount_type' => $detail->discount_type,
                    'tax_information' => $taxInformation,
                    'applicable_tax_id' => $item ? $item->applicable_tax_id : null,
                    'tax_type' => $item ? ($item->tax_type ?? 'Inclusive') : 'Inclusive',
                    'selected_imei_serial' => $detail->expiry_imei_serial ? [$detail->expiry_imei_serial] : [],
                    'selected_medicine_expiry' => [],
                    'item_seller_id' => $detail->item_seller_id,
                    'combo_items' => $comboItems->toArray(),
                ];
            });
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'hold' => [
                        'id' => $hold->id,
                        'hold_no' => $hold->hold_no,
                        'customer_id' => $hold->customer_id,
                        'employee_id' => $hold->employee_id,
                        'customer_name' => $hold->customer ? $hold->customer->name : 'N/A',
                        'employee_name' => $hold->employee ? $hold->employee->name : 'N/A',
                        'subtotal' => $hold->sub_total,
                        'tax' => $hold->vat,
                        'discount' => $hold->sub_total_discount_value,
                        'discount_type' => $hold->sub_total_discount_type,
                        'shipping' => $hold->delivery_charge,
                        'total_payable' => $hold->total_payable,
                        'sale_date' => $hold->sale_date,
                        'date_time' => $hold->date_time,
                    ],
                    'items' => $holdDetails
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch hold: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert hold to sale and delete hold
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function convertHoldToSale($id): JsonResponse
    {
        try {
            $companyId = session('company.company_id');
            $outletId = session('outlet.outlet_id');
            $userId = auth()->id();
            
            return DB::transaction(function () use ($id, $companyId, $outletId, $userId) {
                $hold = Hold::where('id', $id)
                    ->where('company_id', $companyId)
                    ->where('outlet_id', $outletId)
                    ->where('del_status', 'Live')
                    ->with(['holdDetails', 'holdComboItems'])
                    ->first();
                
                if (!$hold) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Hold not found'
                    ], 404);
                }
                
                // Get hold data formatted for cart
                $holdResponse = $this->getHold($id);
                $holdData = json_decode($holdResponse->getContent(), true);
                
                if ($holdData['status'] !== 'success') {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to fetch hold data'
                    ], 500);
                }
                
                // Return cart items data
                return response()->json([
                    'status' => 'success',
                    'message' => 'Hold data retrieved successfully',
                    'cart_items' => $holdData['data']['items'],
                    'customer_id' => $holdData['data']['hold']['customer_id'],
                    'employee_id' => $holdData['data']['hold']['employee_id'],
                    'subtotal' => $holdData['data']['hold']['subtotal'],
                    'tax' => $holdData['data']['hold']['tax'],
                    'discount' => $holdData['data']['hold']['discount'],
                    'discount_type' => $holdData['data']['hold']['discount_type'],
                    'shipping' => $holdData['data']['hold']['shipping'],
                    'total_payable' => $holdData['data']['hold']['total_payable'],
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to convert hold: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete hold after conversion
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function deleteHold($id): JsonResponse
    {
        try {
            $companyId = session('company.company_id');
            $outletId = session('outlet.outlet_id');
            
            return DB::transaction(function () use ($id, $companyId, $outletId) {
                $hold = Hold::where('id', $id)
                    ->where('company_id', $companyId)
                    ->where('outlet_id', $outletId)
                    ->where('del_status', 'Live')
                    ->first();
                
                if (!$hold) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Hold not found'
                    ], 404);
                }
                
                // Soft delete hold (cascade will handle details and combo items)
                $hold->update(['del_status' => 'Deleted']);
                
                // Also soft delete related records
                HoldDetail::where('holds_id', $hold->id)->update(['del_status' => 'Deleted']);
                HoldComboItem::where('sale_id', $hold->id)->update(['del_status' => 'Deleted']);
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Hold deleted successfully'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete hold: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get last sale invoice for printing
     * 
     * @return JsonResponse
     */
    public function getLastSale(): JsonResponse
    {
        try {
            $companyId = session('company.company_id');
            $outletId = session('outlet.outlet_id');
            
            $lastSale = Sale::where('company_id', $companyId)
                ->where('outlet_id', $outletId)
                ->where('del_status', 'Live')
                ->orderBy('created_at', 'desc')
                ->first();
            
            if (!$lastSale) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No sale found'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $lastSale->id,
                    'encrypted_id' => $lastSale->encrypted_id,
                    'sale_no' => $lastSale->sale_no
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get last sale: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active promotion for an item
     * 
     * @param int $itemId
     * @param int $companyId
     * @param int|null $outletId
     * @param string $currentDate
     * @return array|null
     */
    protected function getActivePromotionForItem(int $itemId, int $companyId, ?int $outletId, string $currentDate): ?array
    {
        $currentTime = now()->format('H:i:s');

        $query = Promotion::where('del_status', 'Live')
            ->where('status', '1')
            ->where('company_id', $companyId)
            ->where('item_id', $itemId)
            ->where('outlet_id', $outletId)
            ->where(function($q) use ($currentDate) {
                $q->where('start_date', '<=', $currentDate)
                  ->where('end_date', '>=', $currentDate);
            });

        $promotion = $query->orderBy('id', 'desc')->first();

        if (!$promotion) {
            return null;
        }

        if ($promotion->start_time && $currentTime < $promotion->start_time) return null;
        if ($promotion->end_time && $currentTime > $promotion->end_time) return null;

        $promotionData = [
            'id' => $promotion->id,
            'title' => $promotion->title,
            'type' => $promotion->type,
            'start_date' => $promotion->start_date,
            'end_date' => $promotion->end_date,
            'start_time' => $promotion->start_time,
            'end_time' => $promotion->end_time,
            'scheme_basis' => $promotion->scheme_basis ?? 'item',
            'min_purchase_amount' => (float) ($promotion->min_purchase_amount ?? 0),
            'max_discount_amount' => (float) ($promotion->max_discount_amount ?? 0),
        ];

        if ($promotion->type == '1') {
            if (strpos($promotion->discount, '%') !== false) {
                $promotionData['discount_type'] = 'percentage';
                $promotionData['discount'] = floatval(str_replace('%', '', $promotion->discount));
            } else {
                $promotionData['discount_type'] = 'fixed';
                $promotionData['discount'] = floatval($promotion->discount);
            }
            $promotionData['item_id'] = $promotion->item_id;
        }
        elseif ($promotion->type == '2') {
            if (strpos($promotion->discount, '%') !== false) {
                $promotionData['discount_type'] = 'percentage';
                $promotionData['discount'] = floatval(str_replace('%', '', $promotion->discount));
            } else {
                $promotionData['discount_type'] = 'fixed';
                $promotionData['discount'] = floatval($promotion->discount);
            }
            $promotionData['coupon_code'] = $promotion->coupon_code;
        }
        elseif ($promotion->type == '3') {
            $promotionData['buy_item_id'] = $promotion->item_id;
            $promotionData['buy_qty'] = $promotion->qty;
            $promotionData['get_item_id'] = $promotion->get_item_id;
            $promotionData['get_qty'] = $promotion->get_qty;
        }

        return $promotionData;
    }

    /**
     * Handle PayPal payment return (after user approves payment)
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function handlePayPalReturn(Request $request)
    {
        try {
            // PayPal returns with 'token' parameter (order ID) and optionally 'PayerID'
            $token = $request->query('token');
            $payerId = $request->query('PayerID');
            $paymentMethodId = $request->query('payment_method_id');
            $amount = $request->query('amount');

            if (!$token) {
                return view('sale::pos.payment.paypal-return', [
                    'success' => false,
                    'message' => 'Payment token not found'
                ]);
            }

            // Get payment gateway instance with proper configuration
            $paymentGateway = \Mdiqbal\LaravelPayments\Facades\Payment::gateway('paypal');
            
            // Capture the PayPal order
            if (method_exists($paymentGateway, 'capturePayment')) {
                try {
                    $response = $paymentGateway->capturePayment($token);
                    
                    if ($response->isSuccess()) {
                        return view('sale::pos.payment.paypal-return', [
                            'success' => true,
                            'message' => 'Payment completed successfully',
                            'transaction_id' => $response->getTransactionId() ?? $token,
                            'amount' => $amount,
                            'payment_method_id' => $paymentMethodId
                        ]);
                    } else {
                        return view('sale::pos.payment.paypal-return', [
                            'success' => false,
                            'message' => 'Payment capture failed: ' . ($response->getMessage() ?? 'Unknown error'),
                            'transaction_id' => $response->getTransactionId() ?? $token
                        ]);
                    }
                } catch (\Exception $captureError) {
                    \Log::error('PayPal capture error', [
                        'error' => $captureError->getMessage(),
                        'token' => $token,
                        'trace' => $captureError->getTraceAsString()
                    ]);
                    
                    // Try to retrieve payment status as fallback
                    try {
                        $response = PaymentGateway::retrievePayment('paypal', $token);
                        return view('sale::pos.payment.paypal-return', [
                            'success' => $response->isSuccess(),
                            'message' => $response->isSuccess() 
                                ? 'Payment completed successfully' 
                                : 'Payment verification failed: ' . ($response->getMessage() ?? 'Unknown error'),
                            'transaction_id' => $response->getTransactionId() ?? $token,
                            'amount' => $amount,
                            'payment_method_id' => $paymentMethodId
                        ]);
                    } catch (\Exception $retrieveError) {
                        return view('sale::pos.payment.paypal-return', [
                            'success' => false,
                            'message' => 'Payment processing failed: ' . $captureError->getMessage(),
                            'transaction_id' => $token
                        ]);
                    }
                }
            } else {
                // If capturePayment method doesn't exist, just verify the payment
                $response = PaymentGateway::retrievePayment('paypal', $token);
                
                return view('sale::pos.payment.paypal-return', [
                    'success' => $response->isSuccess(),
                    'message' => $response->isSuccess() ? 'Payment completed successfully' : 'Payment verification failed',
                    'transaction_id' => $response->getTransactionId() ?? $token,
                    'amount' => $amount,
                    'payment_method_id' => $paymentMethodId
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('PayPal return handler error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->all()
            ]);

            return view('sale::pos.payment.paypal-return', [
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'transaction_id' => $request->query('token')
            ]);
        }
    }

    /**
     * Handle PayPal payment cancellation
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function handlePayPalCancel(Request $request)
    {
        $paymentMethodId = $request->query('payment_method_id');
        
        return view('sale::pos.payment.paypal-cancel', [
            'message' => 'Payment was cancelled',
            'payment_method_id' => $paymentMethodId
        ]);
    }

    /**
     * Handle Paytm payment callback (POST request from Paytm)
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function handlePaytmCallback(Request $request)
    {
        try {
            $payload = $request->all();
            
            \Log::info('Paytm callback received', [
                'payload' => $payload,
                'request_method' => $request->method(),
            ]);

            // Verify payment using PaymentGateway
            $response = PaymentGateway::verifyPayment('paytm', $payload);

            if ($response->isSuccess()) {
                // Payment successful
                $transactionId = $response->getTransactionId();
                $orderId = $payload['ORDERID'] ?? $transactionId;
                
                \Log::info('Paytm payment verified successfully', [
                    'transaction_id' => $transactionId,
                    'order_id' => $orderId,
                ]);

                // Redirect to return page with success status
                return redirect()->route('pos.payment.paytm.return', [
                    'status' => 'success',
                    'transaction_id' => $transactionId,
                    'order_id' => $orderId,
                ]);
            } else {
                // Payment failed
                $errorMessage = $response->getData()['response_message'] ?? 'Payment verification failed';
                
                \Log::warning('Paytm payment verification failed', [
                    'error' => $errorMessage,
                    'payload' => $payload,
                ]);

                return redirect()->route('pos.payment.paytm.return', [
                    'status' => 'failed',
                    'message' => $errorMessage,
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Paytm callback handler error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return redirect()->route('pos.payment.paytm.return', [
                'status' => 'error',
                'message' => 'An error occurred while processing payment: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle Paytm payment return (GET request after callback)
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function handlePaytmReturn(Request $request)
    {
        $status = $request->query('status', 'pending');
        $transactionId = $request->query('transaction_id');
        $orderId = $request->query('order_id');
        $message = $request->query('message', '');

        // If status is success, try to verify payment one more time
        if ($status === 'success' && $transactionId) {
            try {
                // Get payment status from Paytm
                $paymentResponse = PaymentGateway::retrievePayment('paytm', $transactionId);
                
                if ($paymentResponse->isSuccess()) {
                    $status = 'success';
                    $message = 'Payment verified successfully';
                } else {
                    $status = 'failed';
                    $message = 'Payment verification failed';
                }
            } catch (\Exception $e) {
                \Log::error('Error verifying Paytm payment on return', [
                    'error' => $e->getMessage(),
                    'transaction_id' => $transactionId,
                ]);
                // Keep the status as is, but log the error
            }
        }

        return view('sale::pos.payment.paytm-return', [
            'success' => $status === 'success',
            'status' => $status,
            'message' => $message,
            'transaction_id' => $transactionId,
            'order_id' => $orderId,
        ]);
    }

    /**
     * Handle Paystack payment callback
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handlePaystackCallback(Request $request)
    {
        try {
            $reference = $request->query('reference');
            
            if (!$reference) {
                return redirect()->route('pos.payment.paystack.return', [
                    'status' => 'error',
                    'message' => 'Payment reference not found'
                ]);
            }

            \Log::info('Paystack callback received', [
                'reference' => $reference,
                'query_params' => $request->query(),
            ]);

            // Verify payment using PaymentGateway
            $response = PaymentGateway::verifyPayment('paystack', ['reference' => $reference]);

            if ($response->isSuccess()) {
                $transactionId = $response->getTransactionId();
                
                \Log::info('Paystack payment verified successfully', [
                    'transaction_id' => $transactionId,
                    'reference' => $reference,
                ]);

                return redirect()->route('pos.payment.paystack.return', [
                    'status' => 'success',
                    'transaction_id' => $transactionId,
                    'reference' => $reference,
                ]);
            } else {
                $errorMessage = $response->getData()['message'] ?? 'Payment verification failed';
                
                \Log::warning('Paystack payment verification failed', [
                    'error' => $errorMessage,
                    'reference' => $reference,
                ]);

                return redirect()->route('pos.payment.paystack.return', [
                    'status' => 'failed',
                    'message' => $errorMessage,
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Paystack callback handler error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'query' => $request->query(),
            ]);

            return redirect()->route('pos.payment.paystack.return', [
                'status' => 'error',
                'message' => 'An error occurred while processing payment: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle Paystack payment return
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function handlePaystackReturn(Request $request)
    {
        $status = $request->query('status', 'pending');
        $transactionId = $request->query('transaction_id');
        $reference = $request->query('reference');
        $message = $request->query('message', '');

        // If status is success, try to verify payment one more time
        if ($status === 'success' && $reference) {
            try {
                $paymentResponse = PaymentGateway::retrievePayment('paystack', $reference);
                
                if ($paymentResponse->isSuccess()) {
                    $status = 'success';
                    $message = 'Payment verified successfully';
                } else {
                    $status = 'failed';
                    $message = 'Payment verification failed';
                }
            } catch (\Exception $e) {
                \Log::error('Error verifying Paystack payment on return', [
                    'error' => $e->getMessage(),
                    'reference' => $reference,
                ]);
            }
        }

        return view('sale::pos.payment.paystack-return', [
            'success' => $status === 'success',
            'status' => $status,
            'message' => $message,
            'transaction_id' => $transactionId,
            'reference' => $reference,
        ]);
    }

    /**
     * Handle Flutterwave payment callback
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleFlutterwaveCallback(Request $request)
    {
        try {
            $txRef = $request->query('tx_ref');
            $transactionId = $request->query('transaction_id');
            
            if (!$txRef && !$transactionId) {
                return redirect()->route('pos.payment.flutterwave.return', [
                    'status' => 'error',
                    'message' => 'Transaction reference not found'
                ]);
            }

            \Log::info('Flutterwave callback received', [
                'tx_ref' => $txRef,
                'transaction_id' => $transactionId,
                'query_params' => $request->query(),
            ]);

            // Verify payment using PaymentGateway
            $response = PaymentGateway::verifyPayment('flutterwave', [
                'tx_ref' => $txRef,
                'transaction_id' => $transactionId
            ]);

            if ($response->isSuccess()) {
                $verifiedTransactionId = $response->getTransactionId();
                
                \Log::info('Flutterwave payment verified successfully', [
                    'transaction_id' => $verifiedTransactionId,
                    'tx_ref' => $txRef,
                ]);

                return redirect()->route('pos.payment.flutterwave.return', [
                    'status' => 'success',
                    'transaction_id' => $verifiedTransactionId,
                    'tx_ref' => $txRef,
                ]);
            } else {
                $errorMessage = $response->getData()['message'] ?? 'Payment verification failed';
                
                \Log::warning('Flutterwave payment verification failed', [
                    'error' => $errorMessage,
                    'tx_ref' => $txRef,
                ]);

                return redirect()->route('pos.payment.flutterwave.return', [
                    'status' => 'failed',
                    'message' => $errorMessage,
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Flutterwave callback handler error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'query' => $request->query(),
            ]);

            return redirect()->route('pos.payment.flutterwave.return', [
                'status' => 'error',
                'message' => 'An error occurred while processing payment: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle Flutterwave payment return
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function handleFlutterwaveReturn(Request $request)
    {
        $status = $request->query('status', 'pending');
        $transactionId = $request->query('transaction_id');
        $txRef = $request->query('tx_ref');
        $message = $request->query('message', '');

        // If status is success, try to verify payment one more time
        if ($status === 'success' && ($txRef || $transactionId)) {
            try {
                $paymentResponse = PaymentGateway::retrievePayment('flutterwave', $txRef ?? $transactionId);
                
                if ($paymentResponse->isSuccess()) {
                    $status = 'success';
                    $message = 'Payment verified successfully';
                } else {
                    $status = 'failed';
                    $message = 'Payment verification failed';
                }
            } catch (\Exception $e) {
                \Log::error('Error verifying Flutterwave payment on return', [
                    'error' => $e->getMessage(),
                    'tx_ref' => $txRef,
                ]);
            }
        }

        return view('sale::pos.payment.flutterwave-return', [
            'success' => $status === 'success',
            'status' => $status,
            'message' => $message,
            'transaction_id' => $transactionId,
            'tx_ref' => $txRef,
        ]);
    }

    /**
     * Handle MyFatoorah payment callback
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleMyFatoorahCallback(Request $request)
    {
        try {
            $paymentId = $request->query('paymentId');
            $invoiceId = $request->query('invoiceId');
            
            if (!$paymentId && !$invoiceId) {
                return redirect()->route('pos.payment.myfatoorah.return', [
                    'status' => 'error',
                    'message' => 'Payment ID or Invoice ID not found'
                ]);
            }

            \Log::info('MyFatoorah callback received', [
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
                'query_params' => $request->query(),
            ]);

            // Verify payment using PaymentGateway
            $response = PaymentGateway::verifyPayment('myfatoorah', [
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId
            ]);

            if ($response->isSuccess()) {
                $transactionId = $response->getTransactionId();
                
                \Log::info('MyFatoorah payment verified successfully', [
                    'transaction_id' => $transactionId,
                    'payment_id' => $paymentId,
                ]);

                return redirect()->route('pos.payment.myfatoorah.return', [
                    'status' => 'success',
                    'transaction_id' => $transactionId,
                    'payment_id' => $paymentId,
                ]);
            } else {
                $errorMessage = $response->getData()['message'] ?? 'Payment verification failed';
                
                \Log::warning('MyFatoorah payment verification failed', [
                    'error' => $errorMessage,
                    'payment_id' => $paymentId,
                ]);

                return redirect()->route('pos.payment.myfatoorah.return', [
                    'status' => 'failed',
                    'message' => $errorMessage,
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('MyFatoorah callback handler error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'query' => $request->query(),
            ]);

            return redirect()->route('pos.payment.myfatoorah.return', [
                'status' => 'error',
                'message' => 'An error occurred while processing payment: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle MyFatoorah payment return
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function handleMyFatoorahReturn(Request $request)
    {
        $status = $request->query('status', 'pending');
        $transactionId = $request->query('transaction_id');
        $paymentId = $request->query('payment_id');
        $message = $request->query('message', '');

        // If status is success, try to verify payment one more time
        if ($status === 'success' && ($paymentId || $transactionId)) {
            try {
                $paymentResponse = PaymentGateway::retrievePayment('myfatoorah', $paymentId ?? $transactionId);
                
                if ($paymentResponse->isSuccess()) {
                    $status = 'success';
                    $message = 'Payment verified successfully';
                } else {
                    $status = 'failed';
                    $message = 'Payment verification failed';
                }
            } catch (\Exception $e) {
                \Log::error('Error verifying MyFatoorah payment on return', [
                    'error' => $e->getMessage(),
                    'payment_id' => $paymentId,
                ]);
            }
        }

        return view('sale::pos.payment.myfatoorah-return', [
            'success' => $status === 'success',
            'status' => $status,
            'message' => $message,
            'transaction_id' => $transactionId,
            'payment_id' => $paymentId,
        ]);
    }

    /**
     * Handle Mpesa payment callback (STK Push callback)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleMpesaCallback(Request $request)
    {
        try {
            $payload = $request->all();
            
            \Log::info('Mpesa callback received', [
                'payload' => $payload,
                'request_method' => $request->method(),
            ]);

            // Verify payment using PaymentGateway
            $response = PaymentGateway::verifyPayment('mpesa', $payload);

            if ($response->isSuccess()) {
                $transactionId = $response->getTransactionId();
                $checkoutRequestId = $payload['CheckoutRequestID'] ?? $payload['checkout_request_id'] ?? null;
                
                \Log::info('Mpesa payment verified successfully', [
                    'transaction_id' => $transactionId,
                    'checkout_request_id' => $checkoutRequestId,
                    'mpesa_receipt_number' => $payload['MpesaReceiptNumber'] ?? null,
                ]);

                // Return success response to Mpesa
                return response()->json([
                    'ResultCode' => 0,
                    'ResultDesc' => 'Accept the service request successfully.'
                ]);
            } else {
                $errorMessage = $response->getData()['result_description'] ?? 'Payment verification failed';
                
                \Log::warning('Mpesa payment verification failed', [
                    'error' => $errorMessage,
                    'payload' => $payload,
                ]);

                // Return failure response to Mpesa
                return response()->json([
                    'ResultCode' => 1,
                    'ResultDesc' => $errorMessage
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Mpesa callback handler error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            // Return error response to Mpesa
            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'An error occurred while processing payment'
            ]);
        }
    }

    /**
     * Handle Mpesa payment return
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function handleMpesaReturn(Request $request)
    {
        $status = $request->query('status', 'pending');
        $transactionId = $request->query('transaction_id');
        $checkoutRequestId = $request->query('checkout_request_id');
        $message = $request->query('message', '');

        // If status is success, try to verify payment one more time
        if ($status === 'success' && ($checkoutRequestId || $transactionId)) {
            try {
                $paymentResponse = PaymentGateway::retrievePayment('mpesa', $checkoutRequestId ?? $transactionId);
                
                if ($paymentResponse->isSuccess()) {
                    $status = 'success';
                    $message = 'Payment verified successfully';
                } else {
                    $status = 'failed';
                    $message = 'Payment verification failed';
                }
            } catch (\Exception $e) {
                \Log::error('Error verifying Mpesa payment on return', [
                    'error' => $e->getMessage(),
                    'checkout_request_id' => $checkoutRequestId,
                ]);
            }
        }

        return view('sale::pos.payment.mpesa-return', [
            'success' => $status === 'success',
            'status' => $status,
            'message' => $message,
            'transaction_id' => $transactionId,
            'checkout_request_id' => $checkoutRequestId,
        ]);
    }

    /**
     * Check internet connection
     * 
     * @return bool
     */
    private function checkInternetConnection(): bool
    {
        // More reliable check - don't block on slow connections
        // Just check if we can resolve DNS and connect (with short timeout)
        try {
            // Use a lightweight check - just verify we can resolve DNS
            // Don't actually connect to avoid blocking
            $connected = @gethostbyname('www.google.com');
            return $connected !== 'www.google.com'; // If resolved, we got an IP, not the hostname
        } catch (\Exception $e) {
            // If check fails, assume online (don't block ZATCA processing)
            // The actual API call will fail if truly offline
            return true;
        }
    }

    /**
     * Get ZATCA invoice status
     * 
     * @param Request $request
     * @param int $saleId
     * @return JsonResponse
     */
    public function getZatcaStatus(Request $request, int $saleId): JsonResponse
    {
        try {
            $sale = Sale::where('id', $saleId)
                ->where('company_id', session('company.company_id'))
                ->first();

            if (!$sale) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sale not found'
                ], 404);
            }

            $zatcaInvoice = \Modules\Sale\Models\ZatcaInvoice::where('sale_id', $sale->id)->first();

            if (!$zatcaInvoice) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'ZATCA invoice not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'zatca_status' => $zatcaInvoice->zatca_status,
                    'uuid' => $zatcaInvoice->uuid,
                    'qr_code' => $zatcaInvoice->qr_code,
                    'invoice_hash' => $zatcaInvoice->invoice_hash,
                    'cleared_at' => $zatcaInvoice->cleared_at?->toIso8601String(),
                    'reported_at' => $zatcaInvoice->reported_at?->toIso8601String(),
                    'error' => $zatcaInvoice->zatca_error,
                    'is_offline' => $zatcaInvoice->is_offline,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get ZATCA status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retry ZATCA submission
     * 
     * @param Request $request
     * @param int $saleId
     * @return JsonResponse
     */
    public function retryZatcaSubmission(Request $request, int $saleId): JsonResponse
    {
        try {
            $sale = Sale::where('id', $saleId)
                ->where('company_id', session('company.company_id'))
                ->first();

            if (!$sale) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sale not found'
                ], 404);
            }

            $zatcaInvoice = \Modules\Sale\Models\ZatcaInvoice::where('sale_id', $sale->id)->first();

            if (!$zatcaInvoice) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'ZATCA invoice not found'
                ], 404);
            }

            $zatcaService = app(\Modules\Sale\Services\Zatca\ZatcaService::class);
            $success = $zatcaService->retrySubmission($zatcaInvoice);

            return response()->json([
                'status' => $success ? 'success' : 'error',
                'message' => $success ? 'ZATCA submission retried successfully' : 'ZATCA submission failed',
                'data' => [
                    'zatca_status' => $zatcaInvoice->fresh()->zatca_status,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retry ZATCA submission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user-friendly payment error message
     * 
     * @param string $errorMessage
     * @return string
     */
    private function getUserFriendlyPaymentError(string $errorMessage): string
    {
        // Hide sensitive information
        $errorMessage = preg_replace('/merchant[_\s]?(key|secret|id|password)[\s:=]+[^\s,}]+/i', 'merchant_credentials', $errorMessage);
        $errorMessage = preg_replace('/api[_\s]?key[\s:=]+[^\s,}]+/i', 'api_key', $errorMessage);
        $errorMessage = preg_replace('/secret[\s:=]+[^\s,}]+/i', 'secret', $errorMessage);
        
        // Payment gateway specific errors
        if (stripos($errorMessage, 'merchant credentials') !== false || 
            stripos($errorMessage, 'not configured') !== false) {
            return 'Payment gateway credentials are not configured correctly. Please check your payment method settings.';
        }
        
        if (stripos($errorMessage, 'callback') !== false || 
            stripos($errorMessage, 'return_url') !== false) {
            return 'Payment gateway callback URL configuration error. Please contact support.';
        }
        
        if (stripos($errorMessage, 'checksum') !== false || 
            stripos($errorMessage, 'signature') !== false) {
            return 'Payment gateway authentication error. Please try again or contact support.';
        }
        
        if (stripos($errorMessage, '501') !== false || 
            stripos($errorMessage, 'System Error') !== false) {
            return 'Payment gateway system error. Please check your payment gateway configuration or try again later.';
        }
        
        if (stripos($errorMessage, 'timeout') !== false || 
            stripos($errorMessage, 'connection') !== false) {
            return 'Payment gateway connection timeout. Please check your internet connection and try again.';
        }
        
        if (stripos($errorMessage, 'invalid') !== false || 
            stripos($errorMessage, 'not found') !== false) {
            return 'Payment gateway configuration error. Please verify your payment method settings.';
        }
        
        // Generic payment error
        return 'Payment processing error occurred. Please verify your payment method configuration or try a different payment method.';
    }
    
    /**
     * Get user-friendly general error message
     * 
     * @param string $errorMessage
     * @return string
     */
    private function getUserFriendlyError(string $errorMessage): string
    {
        // Hide sensitive information
        $errorMessage = preg_replace('/password[\s:=]+[^\s,}]+/i', 'password', $errorMessage);
        $errorMessage = preg_replace('/secret[\s:=]+[^\s,}]+/i', 'secret', $errorMessage);
        $errorMessage = preg_replace('/token[\s:=]+[^\s,}]+/i', 'token', $errorMessage);
        
        // Credential/configuration errors
        if (stripos($errorMessage, 'credential') !== false || 
            stripos($errorMessage, 'not configured') !== false ||
            stripos($errorMessage, 'missing') !== false) {
            return 'System configuration error. Please check your settings or contact support.';
        }
        
        // Database errors
        if (stripos($errorMessage, 'SQL') !== false || 
            stripos($errorMessage, 'database') !== false) {
            return 'Database error occurred. Please try again or contact support if the problem persists.';
        }
        
        // Network/connection errors
        if (stripos($errorMessage, 'connection') !== false || 
            stripos($errorMessage, 'network') !== false ||
            stripos($errorMessage, 'timeout') !== false) {
            return 'Network connection error. Please check your internet connection and try again.';
        }
        
        // File/permission errors
        if (stripos($errorMessage, 'permission') !== false || 
            stripos($errorMessage, 'file') !== false ||
            stripos($errorMessage, 'directory') !== false) {
            return 'File system error. Please contact support.';
        }
        
        // Generic error
        return 'An unexpected error occurred. Please try again or contact support if the problem persists.';
    }
    
    /**
     * Extract error code from error message
     * 
     * @param string $errorMessage
     * @return string|null
     */
    private function extractErrorCode(string $errorMessage): ?string
    {
        // Extract error codes like [501], [400], etc.
        if (preg_match('/\[(\d+)\]/', $errorMessage, $matches)) {
            return $matches[1];
        }
        
        // Extract HTTP status codes
        if (preg_match('/HTTP\s+(\d+)/i', $errorMessage, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Ensure Zatca QR code is generated for the sale if Zatca is enabled (for invoice PDF/email).
     */
    private function ensureZatcaQRCodeForSale($sale): void
    {
        try {
            if ($sale->zatca_phase1_qr_code) {
                return;
            }
            $company = Company::find($sale->company_id);
            if (!$company || !$company->zatca_configuration) {
                return;
            }
            $zatcaConfig = is_array($company->zatca_configuration)
                ? $company->zatca_configuration
                : (json_decode($company->zatca_configuration, true) ?: []);
            $zatcaPhase = $zatcaConfig['zatca_phase'] ?? '0';
            if ($zatcaPhase === '0' || !isset($zatcaConfig['zatca_phase'])) {
                if (isset($zatcaConfig['zatca_1']) && $zatcaConfig['zatca_1'] == '1') {
                    $zatcaPhase = '1';
                } elseif (isset($zatcaConfig['zatca_2']) && $zatcaConfig['zatca_2'] == '1') {
                    $zatcaPhase = '2';
                }
            }
            if ($zatcaPhase == '1') {
                $phase1Service = app(ZatcaPhase1Service::class);
                $zatcaPhase1QrCode = $phase1Service->generateQRCodeJSON($sale, $zatcaConfig);
                if ($zatcaPhase1QrCode) {
                    $sale->update(['zatca_phase1_qr_code' => $zatcaPhase1QrCode]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to generate Zatca QR code for sale invoice', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate sale invoice PDF content (for email/WhatsApp attachment). Follows SaleController::generatePdf.
     *
     * @return array{content: string, filename: string}
     */
    private function generateSaleInvoicePdf(Sale $sale): array
    {
        $html = view('sale::sale.a4-invoice-pdf', compact('sale'))->render();
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);
        $mpdf->WriteHTML($html);
        $filename = 'Sale No - ' . $sale->sale_no . '.pdf';
        $content = $mpdf->Output('', 'S');
        return ['content' => $content, 'filename' => $filename];
    }

    /**
     * Get sale data formatted for POS edit (cart items + sale header).
     * Converts sale details to cart format for each item type: General, Variation, IMEI/Serial, Medicine, Combo.
     *
     * @param string $encryptedId Encrypted sale ID
     * @return JsonResponse
     */
    public function getSaleForEdit(string $encryptedId): JsonResponse
    {
        try {
            $id = decrypt($encryptedId);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Invalid sale ID'], 400);
        }

        $companyId = session('company.company_id');
        $outletId = session('outlet.outlet_id');

        $sale = Sale::with([
            'customer',
            'employee',
            'saleDetails' => function ($q) {
                $q->where('del_status', 'Live');
            },
            'saleDetails.item.parent',
            'salePayments' => function ($q) {
                $q->where('del_status', 'Live');
            },
            'salePayments.paymentMethod',
        ])
            ->where('id', (int) $id)
            ->where('company_id', $companyId)
            ->where('outlet_id', $outletId)
            ->where('del_status', 'Live')
            ->first();

        if (!$sale) {
            return response()->json(['status' => 'error', 'message' => 'Sale not found'], 404);
        }

        $detailIds = $sale->saleDetails->pluck('id')->toArray();
        $comboSales = [];
        if (!empty($detailIds)) {
            $comboSales = ComboSale::where('sale_id', $sale->id)
                ->whereIn('combo_sale_item_id', $detailIds)
                ->where('del_status', 'Live')
                ->with('comboItem')
                ->get()
                ->groupBy('combo_sale_item_id');
        }

        $cartItems = $this->convertSaleDetailsToCartItems($sale->saleDetails, $comboSales);

        return response()->json([
            'status' => 'success',
            'data' => [
                'sale' => [
                    'id' => $sale->id,
                    'encrypted_id' => $sale->encrypted_id,
                    'sale_no' => $sale->sale_no,
                    'customer_id' => $sale->customer_id,
                    'employee_id' => $sale->employee_id,
                    'customer_name' => $sale->customer ? $sale->customer->name : 'Walk-in Customer',
                    'employee_name' => $sale->employee ? $sale->employee->name : 'N/A',
                    'subtotal' => (float) $sale->sub_total,
                    'tax' => (float) $sale->vat,
                    'discount' => (float) ($sale->sub_total_discount_value ?? 0),
                    'discount_type' => $sale->sub_total_discount_type ?? 'fixed',
                    'shipping' => (float) ($sale->delivery_charge ?? 0),
                    'total_payable' => (float) $sale->grand_total,
                    'sale_date' => $sale->sale_date?->format('Y-m-d'),
                    'date_time' => $sale->date_time?->toIso8601String(),
                ],
                'items' => $cartItems,
            ],
        ]);
    }

    /**
     * Convert sale details to cart items format (grouping IMEI/Serial and Medicine expiry as needed).
     *
     * @param \Illuminate\Support\Collection $saleDetails
     * @param \Illuminate\Support\Collection $comboSalesByDetailId
     * @return array
     */
    private function convertSaleDetailsToCartItems($saleDetails, $comboSalesByDetailId): array
    {
        $imeiSerialTypes = ['IMEI_Product', 'Serial_Product'];

        // Group IMEI/Serial details by item_id (one cart line per product with multiple serials)
        $imeiGroups = [];
        $medicineGroups = [];
        $regularDetails = [];

        foreach ($saleDetails as $detail) {
            $itemType = $detail->item_type ?? ($detail->item ? $detail->item->type : null);

            if ($itemType && in_array($itemType, $imeiSerialTypes) && $detail->expiry_imei_serial) {
                $key = $detail->item_id;
                if (!isset($imeiGroups[$key])) {
                    $imeiGroups[$key] = [
                        'detail' => $detail,
                        'serials' => [],
                    ];
                }
                $imeiGroups[$key]['serials'][] = $detail->expiry_imei_serial;
                continue;
            }

            if ($itemType === 'Medicine_Product' && $detail->expiry_imei_serial) {
                $key = $detail->item_id;
                if (!isset($medicineGroups[$key])) {
                    $first = $detail;
                    $medicineGroups[$key] = [
                        'detail' => $first,
                        'expiry_rows' => [],
                    ];
                }
                $medicineGroups[$key]['expiry_rows'][] = [
                    'expiry_date' => $detail->expiry_imei_serial,
                    'quantity' => (float) $detail->qty,
                ];
                continue;
            }

            $regularDetails[] = $detail;
        }

        $cartItems = [];
        $taxDecode = function ($menuTaxes) {
            $arr = [];
            if ($menuTaxes && is_string($menuTaxes)) {
                $dec = json_decode($menuTaxes, true);
                if (is_array($dec)) {
                    foreach ($dec as $t) {
                        $arr[] = [
                            'tax_field_name' => $t['tax_field_name'] ?? '',
                            'tax_field_percentage' => (float) ($t['tax_field_percentage'] ?? 0),
                        ];
                    }
                }
            }
            return $arr;
        };

        foreach ($imeiGroups as $itemId => $group) {
            $d = $group['detail'];
            $item = $d->item;
            $cartItems[] = [
                'product_id' => $d->item_id,
                'product_name' => $item ? $item->name : 'N/A',
                'product_code' => $item ? $item->code : 'N/A',
                'product_type' => $d->item_type ?? ($item ? $item->type : null),
                'quantity' => count($group['serials']),
                'unit_price' => (float) $d->menu_unit_price,
                'discount' => (float) $d->menu_discount_value,
                'discount_type' => $d->discount_type ?? 'fixed',
                'tax_information' => $taxDecode($d->menu_taxes),
                'applicable_tax_id' => $item ? $item->applicable_tax_id : null,
                'tax_type' => $item ? ($item->tax_type ?? 'Inclusive') : 'Inclusive',
                'selected_imei_serial' => $group['serials'],
                'item_seller_id' => $d->item_seller_id,
                'combo_items' => [],
            ];
        }

        foreach ($medicineGroups as $itemId => $group) {
            $d = $group['detail'];
            $item = $d->item;
            $cartItems[] = [
                'product_id' => $d->item_id,
                'product_name' => $item ? $item->name : 'N/A',
                'product_code' => $item ? $item->code : 'N/A',
                'product_type' => $d->item_type ?? ($item ? $item->type : null),
                'quantity' => array_sum(array_column($group['expiry_rows'], 'quantity')),
                'unit_price' => (float) $d->menu_unit_price,
                'discount' => (float) $d->menu_discount_value,
                'discount_type' => $d->discount_type ?? 'fixed',
                'tax_information' => $taxDecode($d->menu_taxes),
                'applicable_tax_id' => $item ? $item->applicable_tax_id : null,
                'tax_type' => $item ? ($item->tax_type ?? 'Inclusive') : 'Inclusive',
                'selected_medicine_expiry' => $group['expiry_rows'],
                'item_seller_id' => $d->item_seller_id,
                'combo_items' => [],
            ];
        }

        // Group regular details by item_id (cart has one row per product_id; sum qty, take first price/discount)
        $regularByItem = [];
        foreach ($regularDetails as $detail) {
            $key = $detail->item_id;
            if (!isset($regularByItem[$key])) {
                $regularByItem[$key] = [
                    'detail' => $detail,
                    'qty' => (float) $detail->qty,
                    'comboItems' => [],
                ];
                $comboRows = $comboSalesByDetailId->get($detail->id, collect());
                foreach ($comboRows as $cs) {
                    $regularByItem[$key]['comboItems'][] = [
                        'item_id' => $cs->combo_item_id,
                        'item_name' => $cs->comboItem ? $cs->comboItem->name : 'N/A',
                        'quantity' => (float) $cs->combo_item_qty,
                        'unit_price' => (float) $cs->combo_item_price,
                        'show_in_invoice' => $cs->show_in_invoice === 'Yes',
                        'employee_id' => $cs->combo_item_seller_id,
                    ];
                }
            } else {
                $regularByItem[$key]['qty'] += (float) $detail->qty;
                $comboRows = $comboSalesByDetailId->get($detail->id, collect());
                foreach ($comboRows as $cs) {
                    $regularByItem[$key]['comboItems'][] = [
                        'item_id' => $cs->combo_item_id,
                        'item_name' => $cs->comboItem ? $cs->comboItem->name : 'N/A',
                        'quantity' => (float) $cs->combo_item_qty,
                        'unit_price' => (float) $cs->combo_item_price,
                        'show_in_invoice' => $cs->show_in_invoice === 'Yes',
                        'employee_id' => $cs->combo_item_seller_id,
                    ];
                }
            }
        }
        foreach ($regularByItem as $itemId => $group) {
            $d = $group['detail'];
            $item = $d->item;
            $cartItems[] = [
                'product_id' => $d->item_id,
                'product_name' => $item ? $item->name : 'N/A',
                'product_code' => $item ? $item->code : 'N/A',
                'product_type' => $d->item_type ?? ($item ? $item->type : null),
                'quantity' => $group['qty'],
                'unit_price' => (float) $d->menu_unit_price,
                'discount' => (float) $d->menu_discount_value,
                'discount_type' => $d->discount_type ?? 'fixed',
                'tax_information' => $taxDecode($d->menu_taxes),
                'applicable_tax_id' => $item ? $item->applicable_tax_id : null,
                'tax_type' => $item ? ($item->tax_type ?? 'Inclusive') : 'Inclusive',
                'selected_imei_serial' => [],
                'selected_medicine_expiry' => [],
                'item_seller_id' => $d->item_seller_id,
                'combo_items' => $group['comboItems'],
            ];
        }

        return $cartItems;
    }

    /**
     * Update an existing sale (same payload as saveSale). Replaces details and payments.
     *
     * @param Request $request
     * @param string $sale Encrypted sale ID from route
     * @return JsonResponse
     */
    public function updateSale(Request $request, string $sale): JsonResponse
    {
        try {
            $id = decrypt($sale);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Invalid sale ID'], 400);
        }

        $companyId = session('company.company_id');
        $outletId = session('outlet.outlet_id');
        $existingSale = Sale::where('id', (int) $id)
            ->where('company_id', $companyId)
            ->where('outlet_id', $outletId)
            ->where('del_status', 'Live')
            ->first();

        if (!$existingSale) {
            return response()->json(['status' => 'error', 'message' => 'Sale not found'], 404);
        }

        try {
            $validated = $request->validate([
                'customer_id' => 'nullable|integer',
                'employee_id' => 'nullable|integer',
                'cart_items' => 'required|array|min:1',
                'cart_items.*.product_id' => 'required|integer',
                'cart_items.*.product_type' => 'required|string',
                'cart_items.*.quantity' => 'required|numeric|min:0.001',
                'cart_items.*.unit_price' => 'required|numeric|min:0',
                'cart_items.*.discount' => 'nullable|numeric|min:0',
                'cart_items.*.discount_type' => 'nullable|string|in:fixed,percentage',
                'cart_items.*.selected_imei_serial' => 'nullable|array',
                'cart_items.*.selected_imei_serial.*' => 'nullable|string',
                'cart_items.*.selected_medicine_expiry' => 'nullable|array',
                'cart_items.*.is_promotion_free_item' => 'nullable|boolean',
                'cart_items.*.promotion_id' => 'nullable|integer',
                'cart_items.*.promotion' => 'nullable|array',
                'cart_items.*.has_promotion_discount' => 'nullable|boolean',
                'cart_items.*.selected_medicine_expiry.*.expiry_date' => 'nullable|string',
                'cart_items.*.selected_medicine_expiry.*.quantity' => 'nullable|numeric|min:0',
                'cart_items.*.selected_medicine_expiry.*.stock_quantity' => 'nullable|numeric|min:0',
                'cart_items.*.tax_information' => 'nullable|array',
                'cart_items.*.applicable_tax_id' => 'nullable',
                'cart_items.*.tax_type' => 'nullable|string|in:Exclusive,Inclusive',
                'cart_items.*.combo_items' => 'nullable|array',
                'cart_items.*.item_seller_id' => 'nullable|integer|exists:users,id',
                'subtotal' => 'required|numeric|min:0',
                'tax' => 'required|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                'discount_type' => 'nullable|string|in:fixed,percentage',
                'shipping' => 'nullable|numeric|min:0',
                'total_payable' => 'required|numeric|min:0',
                'payments' => 'nullable|array',
                'payments.*.payment_id' => 'required|integer|exists:payment_methods,id',
                'payments.*.amount' => 'required|numeric|min:0.01',
                'payments.*.gateway_transaction_id' => 'nullable|string',
                'payments.*.gateway_name' => 'nullable|string',
                'total_paid' => 'nullable|numeric|min:0',
                'change_amount' => 'nullable|numeric|min:0',
                'due_amount' => 'nullable|numeric|min:0',
                'sale_as_due' => 'nullable|boolean',
                'send_email' => 'nullable|boolean',
                'send_sms' => 'nullable|boolean',
                'send_whatsapp' => 'nullable|boolean',
            ]);

            $companyId = session('company.company_id');

            $userId = auth()->id();

            return DB::transaction(function () use ($existingSale, $validated, $companyId, $outletId, $userId) {
                $sale = $existingSale;
                $saleNo = $sale->sale_no;

                $subtotal = $validated['subtotal'];
                $taxAmt = $validated['tax'];
                $shipping = $validated['shipping'] ?? 0;
                $cartDiscount = $validated['discount'] ?? 0;
                $cartDiscountType = $validated['discount_type'] ?? 'fixed';

                $totalItemDiscount = 0;
                foreach ($validated['cart_items'] as $item) {
                    $itemSubtotal = $item['quantity'] * $item['unit_price'];
                    $itemDiscount = $item['discount'] ?? 0;
                    $itemDiscountType = $item['discount_type'] ?? 'fixed';
                    if ($itemDiscountType === 'percentage') {
                        $totalItemDiscount += ($itemSubtotal * $itemDiscount) / 100;
                    } else {
                        $totalItemDiscount += $itemDiscount;
                    }
                }

                $cartDiscountAmount = 0;
                if ($cartDiscount > 0) {
                    if ($cartDiscountType === 'percentage') {
                        $cartDiscountAmount = (($subtotal - $totalItemDiscount) * $cartDiscount) / 100;
                    } else {
                        $cartDiscountAmount = $cartDiscount;
                    }
                }

                $subTotalWithDiscount = $subtotal - $totalItemDiscount;
                $grandTotal = $subTotalWithDiscount - $cartDiscountAmount + $taxAmt + $shipping;

                $payments = $validated['payments'] ?? [];
                $totalPaid = $validated['total_paid'] ?? 0;
                $changeAmount = $validated['change_amount'] ?? 0;
                $dueAmount = $validated['due_amount'] ?? 0;
                $saleAsDue = $validated['sale_as_due'] ?? false;

                if ($saleAsDue) {
                    $totalPaid = 0;
                    $changeAmount = 0;
                    $dueAmount = $grandTotal;
                } elseif (empty($payments)) {
                    $totalPaid = $grandTotal;
                    $changeAmount = 0;
                    $dueAmount = 0;
                } else {
                    if ($totalPaid == 0) {
                        $totalPaid = array_sum(array_column($payments, 'amount'));
                    }
                    if ($totalPaid > $grandTotal) {
                        $changeAmount = $totalPaid - $grandTotal;
                        $dueAmount = 0;
                    } else {
                        $changeAmount = 0;
                        $dueAmount = $grandTotal - $totalPaid;
                    }
                }

                // Credit limit validation for update (current due includes this sale; we're replacing it)
                if ($dueAmount > 0 && ($validated['customer_id'] ?? null)) {
                    $customer = Customer::find($validated['customer_id']);
                    if ($customer && $customer->name !== 'Walk-in Customer') {
                        $creditLimit = (float) ($customer->credit_limit ?? 0);
                        if ($creditLimit > 0) {
                            $currentDue = $this->customerService->getCustomerDue($customer->id, $outletId);
                            $currentDue = max(0, $currentDue);
                            $existingDue = (float) ($sale->due_amount ?? 0);
                            $newTotalDue = $currentDue - $existingDue + $dueAmount;
                            if ($newTotalDue > $creditLimit) {
                                return response()->json([
                                    'status' => 'error',
                                    'message' => 'Customer credit limit exceeded. Credit limit: ' . number_format($creditLimit, 2) . ', New total due would be: ' . number_format($newTotalDue, 2) . '.',
                                ], 422);
                            }
                        }
                    }
                }

                $saleVatObjects = GstTaxService::buildSaleVatObjects(
                    $validated['cart_items'],
                    $validated['customer_id'] ?? null,
                    $outletId,
                    $companyId
                );
                $saleVatObjectsJson = !empty($saleVatObjects) ? json_encode($saleVatObjects) : null;
                if ($saleVatObjectsJson === false) {
                    $saleVatObjectsJson = null;
                }

                $sale->update([
                    'customer_id' => $validated['customer_id'] ?? null,
                    'employee_id' => $validated['employee_id'] ?? null,
                    'total_items' => count($validated['cart_items']),
                    'sub_total' => $subtotal,
                    'given_amount' => $totalPaid,
                    'paid_amount' => $totalPaid,
                    'change_amount' => $changeAmount,
                    'previous_due' => 0,
                    'due_amount' => $dueAmount,
                    'disc' => $cartDiscountAmount,
                    'disc_actual' => $cartDiscountAmount,
                    'vat' => $taxAmt,
                    'rounding' => 0,
                    'total_payable' => $grandTotal,
                    'total_item_discount_amount' => $totalItemDiscount,
                    'sub_total_with_discount' => $subTotalWithDiscount,
                    'sub_total_discount_amount' => $cartDiscountAmount,
                    'total_discount_amount' => $totalItemDiscount + $cartDiscountAmount,
                    'delivery_charge' => $shipping,
                    'sub_total_discount_value' => $cartDiscount,
                    'sub_total_discount_type' => $cartDiscountType,
                    'sale_vat_objects' => $saleVatObjectsJson,
                    'grand_total' => $grandTotal,
                ]);

                $sale->saleDetails()->update(['del_status' => 'Deleted']);
                ComboSale::where('sale_id', $sale->id)->update(['del_status' => 'Deleted']);
                $sale->salePayments()->update(['del_status' => 'Deleted']);

                foreach ($validated['cart_items'] as $item) {
                    $itemSubtotal = $item['quantity'] * $item['unit_price'];
                    $itemDiscount = $item['discount'] ?? 0;
                    $itemDiscountType = $item['discount_type'] ?? 'fixed';
                    $discountAmount = $itemDiscountType === 'percentage' ? ($itemSubtotal * $itemDiscount) / 100 : $itemDiscount;

                    $product = Item::find($item['product_id']);
                    $purchasePrice = $product ? ($product->purchase_price ?? 0) : 0;
                    if ($product && ($product->unit_type ?? 1) != 1) {
                        $cr = $product->conversion_rate ?? 1;
                        if ($cr > 0) {
                            $purchasePrice = $purchasePrice / $cr;
                        }
                    }

                    $menuTaxes = GstTaxService::getItemMenuTaxes($item, $validated['customer_id'] ?? null, $outletId, $companyId);
                    $taxPercentage = GstTaxService::getItemTaxPercentage($menuTaxes);
                    $itemTaxAmount = GstTaxService::getItemTaxAmount($menuTaxes);

                    $selectedImeiSerial = $item['selected_imei_serial'] ?? [];
                    $isIMEISerialProduct = in_array($item['product_type'] ?? '', ['IMEI_Product', 'Serial_Product']);
                    $selectedMedicineExpiry = $item['selected_medicine_expiry'] ?? [];
                    $isMedicineProduct = ($item['product_type'] ?? '') === 'Medicine_Product' && !empty($selectedMedicineExpiry);
                    $itemSellerId = ($item['product_type'] ?? '') === 'Service_Product' ? ($item['item_seller_id'] ?? null) : null;
                    $isComboProduct = ($item['product_type'] ?? '') === 'Combo_Product';
                    $comboItems = $item['combo_items'] ?? [];

                    $isPromoItem = 'No';
                    $promoParentId = null;
                    if (!empty($item['is_promotion_free_item'])) {
                        $isPromoItem = 'Yes';
                        $promoParentId = $item['promotion_id'] ?? null;
                    } elseif (!empty($item['has_promotion_discount']) && !empty($item['promotion']['type']) && ($item['promotion']['type'] ?? '') == '1') {
                        $promoParentId = $item['promotion']['id'] ?? null;
                    } elseif (!empty($item['promotion_id'])) {
                        $promoParentId = $item['promotion_id'];
                    }

                    if ($isIMEISerialProduct && !empty($selectedImeiSerial) && is_array($selectedImeiSerial)) {
                        foreach ($selectedImeiSerial as $imeiSerial) {
                            SaleDetail::create([
                                'item_id' => $item['product_id'],
                                'qty' => 1,
                                'menu_price_without_discount' => $item['unit_price'],
                                'menu_price_with_discount' => $item['unit_price'] - ($discountAmount / $item['quantity']),
                                'menu_unit_price' => $item['unit_price'],
                                'purchase_price' => $purchasePrice,
                                'menu_vat_percentage' => $taxPercentage,
                                'item_tax_amount' => $itemTaxAmount,
                                'menu_taxes' => !empty($menuTaxes) ? json_encode($menuTaxes) : null,
                                'menu_discount_value' => $itemDiscount,
                                'discount_type' => $itemDiscountType,
                                'discount_amount' => $discountAmount / count($selectedImeiSerial),
                                'item_type' => $item['product_type'] ?? null,
                                'expiry_imei_serial' => $imeiSerial,
                                'item_seller_id' => $itemSellerId,
                                'is_promo_item' => $isPromoItem,
                                'promo_parent_id' => $promoParentId,
                                'sales_id' => $sale->id,
                                'user_id' => $userId,
                                'outlet_id' => $outletId,
                                'company_id' => $companyId,
                                'del_status' => 'Live',
                            ]);
                        }
                    } elseif ($isMedicineProduct && !empty($selectedMedicineExpiry) && is_array($selectedMedicineExpiry)) {
                        $totalMedicineQty = array_sum(array_column($selectedMedicineExpiry, 'quantity'));
                        foreach ($selectedMedicineExpiry as $medExpiry) {
                            $expiryDate = $medExpiry['expiry_date'] ?? '';
                            $medQuantity = floatval($medExpiry['quantity'] ?? 1);
                            SaleDetail::create([
                                'item_id' => $item['product_id'],
                                'qty' => $medQuantity,
                                'menu_price_without_discount' => $item['unit_price'],
                                'menu_price_with_discount' => $item['unit_price'] - ($discountAmount / $totalMedicineQty),
                                'menu_unit_price' => $item['unit_price'],
                                'purchase_price' => $purchasePrice,
                                'menu_vat_percentage' => $taxPercentage,
                                'item_tax_amount' => $itemTaxAmount,
                                'menu_taxes' => !empty($menuTaxes) ? json_encode($menuTaxes) : null,
                                'menu_discount_value' => $itemDiscount,
                                'discount_type' => $itemDiscountType,
                                'discount_amount' => ($discountAmount / $totalMedicineQty) * $medQuantity,
                                'item_type' => $item['product_type'] ?? null,
                                'expiry_imei_serial' => $expiryDate,
                                'item_seller_id' => $itemSellerId,
                                'is_promo_item' => $isPromoItem,
                                'promo_parent_id' => $promoParentId,
                                'sales_id' => $sale->id,
                                'user_id' => $userId,
                                'outlet_id' => $outletId,
                                'company_id' => $companyId,
                                'del_status' => 'Live',
                            ]);
                        }
                    } else {
                        $saleDetail = SaleDetail::create([
                            'item_id' => $item['product_id'],
                            'qty' => $item['quantity'],
                            'menu_price_without_discount' => $item['unit_price'],
                            'menu_price_with_discount' => $item['unit_price'] - ($discountAmount / $item['quantity']),
                            'menu_unit_price' => $item['unit_price'],
                            'purchase_price' => $purchasePrice,
                            'menu_vat_percentage' => $taxPercentage,
                            'item_tax_amount' => $itemTaxAmount,
                            'menu_taxes' => !empty($menuTaxes) ? json_encode($menuTaxes) : null,
                            'menu_discount_value' => $itemDiscount,
                            'discount_type' => $itemDiscountType,
                            'discount_amount' => $discountAmount,
                            'item_type' => $item['product_type'] ?? null,
                            'expiry_imei_serial' => null,
                            'item_seller_id' => $itemSellerId,
                            'is_promo_item' => $isPromoItem,
                            'promo_parent_id' => $promoParentId,
                            'sales_id' => $sale->id,
                            'user_id' => $userId,
                            'outlet_id' => $outletId,
                            'company_id' => $companyId,
                            'del_status' => 'Live',
                        ]);
                        if ($isComboProduct && !empty($comboItems) && is_array($comboItems)) {
                            foreach ($comboItems as $comboItem) {
                                $comboItemProduct = Item::find($comboItem['item_id'] ?? null);
                                $comboItemType = $comboItemProduct ? ($comboItemProduct->type ?? null) : null;
                                ComboSale::create([
                                    'sale_id' => $sale->id,
                                    'combo_sale_item_id' => $saleDetail->id,
                                    'show_in_invoice' => ($comboItem['show_in_invoice'] === true || $comboItem['show_in_invoice'] === 'Yes' || $comboItem['show_in_invoice'] === 1) ? 'Yes' : 'No',
                                    'combo_item_id' => $comboItem['item_id'] ?? null,
                                    'combo_item_type' => $comboItemType,
                                    'combo_item_qty' => $comboItem['quantity'] ?? 0,
                                    'combo_item_price' => $comboItem['unit_price'] ?? $comboItem['amount'] ?? 0,
                                    'combo_item_seller_id' => $comboItem['employee_id'] ?? null,
                                    'outlet_id' => $outletId,
                                    'user_id' => $userId,
                                    'company_id' => $companyId,
                                    'del_status' => 'Live',
                                ]);
                            }
                        }
                    }
                }

                if (!empty($payments)) {
                    foreach ($payments as $payment) {
                        $paymentMethod = PaymentMethod::find($payment['payment_id']);
                        $paymentName = $paymentMethod ? $paymentMethod->name : 'Unknown';
                        $paymentData = [
                            'date' => now()->toDateString(),
                            'payment_id' => $payment['payment_id'],
                            'amount' => $payment['amount'],
                            'sale_id' => $sale->id,
                            'payment_name' => $paymentName,
                            'outlet_id' => $outletId,
                            'user_id' => $userId,
                            'company_id' => $companyId,
                            'del_status' => 'Live',
                        ];
                        if (isset($payment['gateway_transaction_id'])) {
                            $paymentData['gateway_transaction_id'] = $payment['gateway_transaction_id'];
                        }
                        if (isset($payment['gateway_name'])) {
                            $paymentData['gateway_name'] = $payment['gateway_name'];
                        }
                        SalePayment::create($paymentData);
                    }
                } else {
                    SalePayment::create([
                        'date' => now()->toDateString(),
                        'payment_id' => 1,
                        'amount' => $grandTotal,
                        'sale_id' => $sale->id,
                        'payment_name' => 'Cash',
                        'outlet_id' => $outletId,
                        'user_id' => $userId,
                        'company_id' => $companyId,
                        'del_status' => 'Live',
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Sale updated successfully',
                    'sale_id' => $sale->id,
                    'sale_no' => $sale->sale_no,
                    'encrypted_id' => $sale->encrypted_id,
                ]);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Sale update error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'sale_id' => $existingSale->id,
            ]);
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check PayPal payment status (AJAX endpoint)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function checkPayPalPaymentStatus(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'transaction_id' => 'required|string',
                'payment_method_id' => 'required|integer|exists:payment_methods,id',
            ]);

            // Retrieve payment status
            $response = PaymentGateway::retrievePayment('paypal', $validated['transaction_id']);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'transaction_id' => $response->getTransactionId(),
                    'status' => $response->getStatus(),
                    'is_success' => $response->isSuccess(),
                    'gateway_data' => $response->getData(),
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('PayPal payment status check failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $request->input('transaction_id')
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check payment status: ' . $e->getMessage()
            ], 500);
        }
    }
}
