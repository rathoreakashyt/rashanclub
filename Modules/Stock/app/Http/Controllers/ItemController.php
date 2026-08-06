<?php

namespace Modules\Stock\Http\Controllers;

use Modules\Stock\Imports\ItemImport;
use Modules\Stock\Imports\OpeningStockGeneralImport;
use Modules\Stock\Imports\OpeningStockImeiSerialImport;
use Modules\Stock\Imports\OpeningStockMedicineImport;
use Modules\Stock\Exports\OpeningStockGeneralExport;
use Modules\Stock\Exports\OpeningStockImeiSerialExport;
use Modules\Stock\Exports\OpeningStockMedicineExport;
use Modules\Stock\Http\Request\ItemRequest;
use Modules\Stock\Services\ItemService;
use Modules\Stock\Models\Item;
use Modules\Configuration\Models\Outlet;
use Modules\Configuration\Models\Tax;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ItemController extends Controller
{
    /**
     * @var ItemService
     */
    protected $itemService;

    /**
     * ItemController constructor.
     *
     * @param ItemService $itemService
     */
    public function __construct(ItemService $itemService)
    {
        $this->itemService = $itemService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $status = request('status');

        if (request()->ajax()) {
            $params = [
                'draw' => request()->draw ?? 1,
                'length' => request()->length ?? 10,
                'start' => request()->start ?? 0,
                'search' => request()->search['value'] ?? '',
            ];

            // Check if this is a bulk update request
            $isBulkUpdate = request()->get('bulk_update') === 'true';
            
            if ($isBulkUpdate) {
                $data = $this->itemService->getBulkUpdateDataTableData($params);
            } else {
                $data = $this->itemService->getDataTableData($params);
            }
            
            return response()->json($data);
        }
        return view('stock::item.index', compact('status'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $companyId = session('company.company_id');
        
        $data['code'] = itemCodeGenerator();
        $data['outlets'] = Outlet::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('active_status', 'Active')
            ->select('id', 'outlet_name')
            ->get();
        
        // Load categories, brands, suppliers, units, and racks using Laravel
        $data['categories'] = \Modules\Stock\Models\ItemCategory::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('name')
            ->get(['id', 'name']);
        
        $data['brands'] = \Modules\Stock\Models\Brand::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('name')
            ->get(['id', 'name']);
        
        $data['suppliers'] = \Modules\Purchase\Models\Supplier::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('name')
            ->get(['id', 'name']);
        
        $data['units'] = \Modules\Stock\Models\Unit::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('unit_name')
            ->get(['id', 'unit_name']);
        
        $data['racks'] = \Modules\Stock\Models\Rack::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('name')
            ->get(['id', 'name']);
        
        // Taxes shown in item profile: show_in_item_profile = Yes, parent_tax_id = null
        $data['itemProfileTaxes'] = Tax::forCompany($companyId)->live()
            ->where('show_in_item_profile', 'Yes')
            ->whereNull('parent_tax_id')
            ->orderBy('tax_name')
            ->get(['id', 'tax_name', 'tax_rate']);
        
        return view('stock::item.create', $data);
    }

    /**
     * Get General_Product items for combo product
     */
    public function getGeneralProducts()
    {
        try {
            $items = \Modules\Stock\Models\Item::where('company_id', session('company.company_id'))
                ->where('del_status', 'Live')
                ->where('type', 'General_Product')
                ->select('id', 'name', 'code', 'sale_price', 'mrp_price')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $items
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch items'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ItemRequest $request)
    {
        DB::beginTransaction();
        try {
            $validatedData = $request->validated();

            // Handle Variation Product
            if ($request->type === 'Variation_Product') {
                $result = $this->itemService->createVariationProduct($validatedData, $request);
                
                DB::commit();

                if ($request->ajax()) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Variation Product created successfully with ' . count($result['variations']) . ' variations',
                        'data' => $result
                    ]);
                }

                return redirect()->route('item.index')
                    ->with('success', 'Variation Product created successfully.');

            } else {
                // Create the item (non-variation)
                $item = $this->itemService->createItem($validatedData, $request);

                DB::commit();

                if ($request->ajax()) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Item created successfully',
                        'data' => $item
                    ]);
                }

                return redirect()->route('item.index')
                    ->with('success', 'Item created successfully.');
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to create item: ' . $e->getMessage()
                ], 500);
            }
            return back()->with('error', 'Failed to create item: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $companyId = session('company.company_id');
        
        // Get item by encrypted ID
        $item = $this->itemService->getItemByEncryptedId($id);
        
        if (!$item) {
            return redirect()->route('item.index')
                ->with('error', 'Item not found');
        }
        
        // Load relationships
        $item->load(['category', 'brand', 'purchaseUnit', 'saleUnit', 'supplier', 'rack']);
        
        // Load all necessary data
        $data['item'] = $item;
        $data['code'] = $item->code;
        $data['outlets'] = Outlet::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('active_status', 'Active')
            ->select('id', 'outlet_name')
            ->get();
        
        // Load categories, brands, suppliers, units, and racks
        $data['categories'] = \Modules\Stock\Models\ItemCategory::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('name')
            ->get(['id', 'name']);
        
        $data['brands'] = \Modules\Stock\Models\Brand::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('name')
            ->get(['id', 'name']);
        
        $data['suppliers'] = \Modules\Purchase\Models\Supplier::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('name')
            ->get(['id', 'name']);
        
        $data['units'] = \Modules\Stock\Models\Unit::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('unit_name')
            ->get(['id', 'unit_name']);
        
        $data['racks'] = \Modules\Stock\Models\Rack::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('name')
            ->get(['id', 'name']);
        
        // Load opening stock data
        $openingStocks = \Modules\Stock\Models\SetOpeningStock::where('item_id', $item->id)
            ->get();
        
        $data['openingStockData'] = [];
        foreach ($openingStocks as $stock) {
            if (!isset($data['openingStockData'][$stock->outlet_id])) {
                $data['openingStockData'][$stock->outlet_id] = [
                    'items' => [],
                    'quantity' => 0
                ];
            }
            
            if ($item->type === 'General_Product' || $item->type === 'Installment_Product') {
                $data['openingStockData'][$stock->outlet_id]['quantity'] = $stock->stock_quantity;
            } else if ($item->type === 'Medicine_Product') {
                $itemDesc = $stock->item_description;
                if (is_string($itemDesc)) {
                    $decoded = json_decode($itemDesc, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $itemDesc = $decoded;
                    }
                }
                
                if (is_array($itemDesc)) {
                    foreach ($itemDesc as $desc) {
                        if (is_array($desc) && isset($desc['expiry_date'])) {
                            $data['openingStockData'][$stock->outlet_id]['items'][] = [
                                'quantity' => $desc['quantity'] ?? $stock->stock_quantity,
                                'expiry_date' => $desc['expiry_date']
                            ];
                        } else {
                            $data['openingStockData'][$stock->outlet_id]['items'][] = [
                                'quantity' => $stock->stock_quantity,
                                'expiry_date' => is_array($desc) ? ($desc['expiry_date'] ?? $desc) : $desc
                            ];
                        }
                    }
                } else {
                    $data['openingStockData'][$stock->outlet_id]['items'][] = [
                        'quantity' => $stock->stock_quantity,
                        'expiry_date' => $itemDesc
                    ];
                }
            } else {
                $itemDesc = $stock->item_description;
                if (is_string($itemDesc)) {
                    $decoded = json_decode($itemDesc, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $itemDesc = $decoded;
                    }
                }
                
                if (is_array($itemDesc)) {
                    foreach ($itemDesc as $descItem) {
                        if ($descItem !== null && $descItem !== '') {
                            $data['openingStockData'][$stock->outlet_id]['items'][] = $descItem;
                        }
                    }
                } else {
                    if ($itemDesc !== null && $itemDesc !== '') {
                        $data['openingStockData'][$stock->outlet_id]['items'][] = $itemDesc;
                    }
                }
            }
        }
        
        // Load variations if Variation_Product
        if ($item->type === 'Variation_Product') {
            $variations = Item::with(['purchaseUnit', 'saleUnit'])
                ->where('parent_id', $item->id)
                ->where('del_status', 'Live')
                ->get();
            
            $data['variations'] = [];
            foreach ($variations as $variation) {
                $variationOpeningStocks = \Modules\Stock\Models\SetOpeningStock::where('item_id', $variation->id)
                    ->get();
                
                $variationOpeningStock = [];
                $varConversionRate = (int)($variation->conversion_rate ?? 1) > 0 ? (int)$variation->conversion_rate : 1;
                foreach ($variationOpeningStocks as $stock) {
                    // For Double Unit, show opening stock as purchase quantity in edit
                    $variationOpeningStock[$stock->outlet_id] = ($variation->unit_type == '2')
                        ? (int) round($stock->stock_quantity / $varConversionRate)
                        : $stock->stock_quantity;
                }
                
                $variationDetails = $variation->variation_details;
                if (is_string($variationDetails)) {
                    $variationDetails = json_decode($variationDetails, true);
                }
                
                $variationName = $variation->name;
                if ($variationDetails && isset($variationDetails['variation_name'])) {
                    $variationName = $variationDetails['variation_name'];
                }
                
                // Current stock for variation (stock_in - stock_out) across all outlets
                $variationStockIn = (int) DB::table('view_stock_detail')
                    ->where('item_id', $variation->id)
                    ->where('type', 1)
                    ->sum('stock_quantity');
                $variationStockOut = (int) DB::table('view_stock_detail')
                    ->where('item_id', $variation->id)
                    ->where('type', 2)
                    ->sum('stock_quantity');
                $variationStock = $variationStockIn - $variationStockOut;
                
                // Unit info for stock display (like general item: quantity + converted quantity)
                $data['variations'][] = [
                    'id' => $variation->id,
                    'encrypted_id' => $variation->encrypted_id,
                    'variation_name' => $variationName,
                    'item_code' => $variation->code,
                    'purchase_price' => $variation->purchase_price,
                    'mrp_price' => $variation->mrp_price,
                    'sale_price' => $variation->sale_price,
                    'whole_sale_price' => $variation->whole_sale_price,
                    'alert_quantity' => $variation->alert_quantity,
                    'stock' => $variationStock,
                    'unit_type' => $variation->unit_type ?? '1',
                    'conversion_rate' => $varConversionRate,
                    'purchase_unit_name' => $variation->purchaseUnit->unit_name ?? '—',
                    'sale_unit_name' => $variation->saleUnit->unit_name ?? '—',
                    'photo' => $variation->photo,
                    'variation_details' => $variationDetails,
                    'opening_stock' => $variationOpeningStock
                ];
            }
        }
        
        // Load combo items if Combo_Product
        if ($item->type === 'Combo_Product') {
            $comboItems = \Modules\Stock\Models\ComboItem::where('combo_item_id', $item->id)
                ->with('item:id,name,code,sale_price')
                ->get();
            
            $data['comboItems'] = [];
            foreach ($comboItems as $comboItem) {
                $data['comboItems'][] = [
                    'id' => $comboItem->id,
                    'item_id' => $comboItem->item_id,
                    'item_name' => $comboItem->item->name . ' - ' . $comboItem->item->code,
                    'quantity' => $comboItem->quantity,
                    'amount' => $comboItem->amount,
                    'total' => $comboItem->total,
                    'show_in_invoice' => $comboItem->show_in_invoice
                ];
            }
        }
        
        return view('stock::item.show', $data);
    }

    /**
     * Get variations for an item (API endpoint)
     */
    public function getVariations(string $id)
    {
        $item = $this->itemService->getItemByEncryptedId($id);
        
        if (!$item || $item->type !== 'Variation_Product') {
            return response()->json(['variations' => []]);
        }
        
        $variations = Item::where('parent_id', $item->id)
            ->where('del_status', 'Live')
            ->get();
        
        $variationsData = [];
        foreach ($variations as $variation) {
            $variationDetails = $variation->variation_details;
            if (is_string($variationDetails)) {
                $variationDetails = json_decode($variationDetails, true);
            }
            
            $variationName = $variation->name;
            if ($variationDetails && isset($variationDetails['variation_name'])) {
                $variationName = $variationDetails['variation_name'];
            }
            
            $variationsData[] = [
                'variation_name' => $variationName,
                'item_code' => $variation->code,
                'mrp_price' => $variation->mrp_price ?? 0,
                'sale_price' => $variation->sale_price ?? 0
            ];
        }
        
        return response()->json(['variations' => $variationsData]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $companyId = session('company.company_id');
        
        // Get item by encrypted ID
        $item = $this->itemService->getItemByEncryptedId($id);
        
        if (!$item) {
            return redirect()->route('item.index')
                ->with('error', 'Item not found');
        }
        
        // Load all necessary data
        $data['item'] = $item;
        $data['code'] = $item->code;
        $data['outlets'] = Outlet::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('active_status', 'Active')
            ->select('id', 'outlet_name')
            ->get();
        
        // Load categories, brands, suppliers, units, and racks
        $data['categories'] = \Modules\Stock\Models\ItemCategory::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('name')
            ->get(['id', 'name']);
        
        $data['brands'] = \Modules\Stock\Models\Brand::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('name')
            ->get(['id', 'name']);
        
        $data['suppliers'] = \Modules\Purchase\Models\Supplier::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('name')
            ->get(['id', 'name']);
        
        $data['units'] = \Modules\Stock\Models\Unit::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('unit_name')
            ->get(['id', 'unit_name']);
        
        $data['racks'] = \Modules\Stock\Models\Rack::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('name')
            ->get(['id', 'name']);
        
        // Taxes shown in item profile: show_in_item_profile = Yes, parent_tax_id = null
        $data['itemProfileTaxes'] = Tax::forCompany($companyId)->live()
            ->where('show_in_item_profile', 'Yes')
            ->whereNull('parent_tax_id')
            ->orderBy('tax_name')
            ->get(['id', 'tax_name', 'tax_rate']);
        
        // Load opening stock data
        $openingStocks = \Modules\Stock\Models\SetOpeningStock::where('item_id', $item->id)
            ->get();
        
        $data['openingStockData'] = [];
        foreach ($openingStocks as $stock) {
            if (!isset($data['openingStockData'][$stock->outlet_id])) {
                $data['openingStockData'][$stock->outlet_id] = [
                    'items' => [],
                    'quantity' => 0
                ];
            }
            
            if ($item->type === 'General_Product' || $item->type === 'Installment_Product') {
                // For Double Unit, show opening stock as purchase quantity (not converted sale quantity)
                $conversionRate = (int)($item->conversion_rate ?? 1) > 0 ? (int)$item->conversion_rate : 1;
                $data['openingStockData'][$stock->outlet_id]['quantity'] = ($item->unit_type == '2')
                    ? (int) round($stock->stock_quantity / $conversionRate)
                    : $stock->stock_quantity;
            } else if ($item->type === 'Medicine_Product') {
                // For Medicine products: item_description is expiry_date, stock_quantity is quantity (stored in sale unit for double)
                $conversionRate = (int)($item->conversion_rate ?? 1) > 0 ? (int)$item->conversion_rate : 1;
                $itemDesc = $stock->item_description;
                if (is_string($itemDesc)) {
                    // Try to decode if it's JSON
                    $decoded = json_decode($itemDesc, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $itemDesc = $decoded;
                    }
                }
                
                if (is_array($itemDesc)) {
                    // If it's an array, merge items
                    foreach ($itemDesc as $desc) {
                        $rawQty = (int)($desc['quantity'] ?? $stock->stock_quantity);
                        // For Double Unit, show opening stock as purchase quantity in edit (e.g. 10 not 120)
                        $displayQty = ($item->unit_type == '2')
                            ? (int) round($rawQty / $conversionRate)
                            : $rawQty;
                        if (is_array($desc) && isset($desc['expiry_date'])) {
                            $data['openingStockData'][$stock->outlet_id]['items'][] = [
                                'quantity' => $displayQty,
                                'expiry_date' => $desc['expiry_date']
                            ];
                        } else {
                            $data['openingStockData'][$stock->outlet_id]['items'][] = [
                                'quantity' => $displayQty,
                                'expiry_date' => is_array($desc) ? ($desc['expiry_date'] ?? $desc) : $desc
                            ];
                        }
                    }
                } else {
                    // Single item: item_description is expiry_date
                    $rawQty = (int)$stock->stock_quantity;
                    $displayQty = ($item->unit_type == '2')
                        ? (int) round($rawQty / $conversionRate)
                        : $rawQty;
                    $data['openingStockData'][$stock->outlet_id]['items'][] = [
                        'quantity' => $displayQty,
                        'expiry_date' => $itemDesc
                    ];
                }
            } else {
                // For IMEI and Serial products
                $itemDesc = $stock->item_description;
                if (is_string($itemDesc)) {
                    // Try to decode if it's JSON
                    $decoded = json_decode($itemDesc, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $itemDesc = $decoded;
                    }
                }
                
                if (is_array($itemDesc)) {
                    // Handle array of items - merge them properly
                    foreach ($itemDesc as $descItem) {
                        if ($descItem !== null && $descItem !== '') {
                            $data['openingStockData'][$stock->outlet_id]['items'][] = $descItem;
                        }
                    }
                } else {
                    // Single item - add it directly
                    if ($itemDesc !== null && $itemDesc !== '') {
                        $data['openingStockData'][$stock->outlet_id]['items'][] = $itemDesc;
                    }
                }
            }
        }
        
        // Load variations if Variation_Product
        if ($item->type === 'Variation_Product') {
            $variations = Item::where('parent_id', $item->id)
                ->where('del_status', 'Live')
                ->get();
            
            $data['variations'] = [];
            foreach ($variations as $variation) {
                // Get opening stock for this variation
                $variationOpeningStocks = \Modules\Stock\Models\SetOpeningStock::where('item_id', $variation->id)
                    ->get();
                
                $variationOpeningStock = [];
                $varConversionRate = (int)($variation->conversion_rate ?? 1) > 0 ? (int)$variation->conversion_rate : 1;
                foreach ($variationOpeningStocks as $stock) {
                    // For Double Unit, show opening stock as purchase quantity in edit
                    $variationOpeningStock[$stock->outlet_id] = ($variation->unit_type == '2')
                        ? (int) round($stock->stock_quantity / $varConversionRate)
                        : $stock->stock_quantity;
                }
                
                $variationDetails = $variation->variation_details;
                if (is_string($variationDetails)) {
                    $variationDetails = json_decode($variationDetails, true);
                }
                
                // Extract variation name from variation_details if available, otherwise use name field
                // (for backward compatibility with old data that might have parent name + variation name)
                $variationName = $variation->name;
                if ($variationDetails && isset($variationDetails['variation_name'])) {
                    $variationName = $variationDetails['variation_name'];
                } else {
                    // If name contains " - ", it might be old format, extract just the variation part
                    // This handles backward compatibility
                    if (strpos($variation->name, ' - ') !== false) {
                        $parts = explode(' - ', $variation->name);
                        // Take the last part as variation name
                        $variationName = end($parts);
                    }
                }
                
                $data['variations'][] = [
                    'id' => $variation->id,
                    'encrypted_id' => $variation->encrypted_id,
                    'variation_name' => $variationName,
                    'item_code' => $variation->code,
                    'purchase_price' => $variation->purchase_price,
                    'mrp_price' => $variation->mrp_price,
                    'sale_price' => $variation->sale_price,
                    'whole_sale_price' => $variation->whole_sale_price,
                    'alert_quantity' => $variation->alert_quantity,
                    'photo' => $variation->photo,
                    'variation_details' => $variationDetails,
                    'opening_stock' => $variationOpeningStock
                ];
            }
        }
        
        // Load combo items if Combo_Product
        if ($item->type === 'Combo_Product') {
            $comboItems = \Modules\Stock\Models\ComboItem::where('combo_item_id', $item->id)
                ->with('item:id,name,code,sale_price')
                ->get();
            
            $data['comboItems'] = [];
            foreach ($comboItems as $comboItem) {
                $data['comboItems'][] = [
                    'id' => $comboItem->id,
                    'item_id' => $comboItem->item_id,
                    'item_name' => $comboItem->item->name . ' - ' . $comboItem->item->code,
                    'quantity' => $comboItem->quantity,
                    'amount' => $comboItem->amount,
                    'total' => $comboItem->total,
                    'show_in_invoice' => $comboItem->show_in_invoice
                ];
            }
        }
        
        return view('stock::item.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ItemRequest $request, string $id)
    {
        try {
            $validatedData = $request->validated();
            $this->itemService->updateItem($id, $validatedData, $request);
            
            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Item updated successfully.'
                ]);
            }
            
            return redirect()->route('item.index')
                ->with('success', 'Item updated successfully.');
        } catch (\Exception $e) {
            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ], 422);
            }
            
            return redirect()->route('item.edit', $id)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->itemService->deleteItem($id);
            return redirect()->route('item.index')
                ->with('success', 'Item deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('item.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Show bulk import form
     */
    public function bulkItemImport(Request $request)
    {
        return view('stock::item.bulk_import');
    }

    /**
     * Show Opening Stock Import page with 3 cards (General/Installment, IMEI/Serial, third card)
     */
    public function openingStockImport(Request $request)
    {
        $companyId = session('company.company_id');
        $outlets = Outlet::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('active_status', 'Active')
            ->select('id', 'outlet_name')
            ->orderBy('outlet_name')
            ->get();

        return view('stock::item.opening_stock_import', compact('outlets'));
    }

    /**
     * Export opening stock template for General_Product and Installment_Product (Card 1)
     */
    public function exportOpeningStockGeneral(): BinaryFileResponse
    {
        $companyId = session('company.company_id');
        return Excel::download(
            new OpeningStockGeneralExport($companyId),
            'opening-stock-general-' . date('Y-m-d-His') . '.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    /**
     * Export opening stock template for IMEI_Product and Serial_Product (Card 2)
     */
    public function exportOpeningStockImeiSerial(): BinaryFileResponse
    {
        $companyId = session('company.company_id');
        return Excel::download(
            new OpeningStockImeiSerialExport($companyId),
            'opening-stock-imei-serial-' . date('Y-m-d-His') . '.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    /**
     * Store opening stock import for General/Installment products (Card 1)
     */
    public function storeOpeningStockGeneral(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200',
        ]);

        $outletId = (int) session('outlet.outlet_id');
        if (!$outletId) {
            return back()->withErrors(['file' => __('Please select an outlet first.')]);
        }
        $this->ensureOutletBelongsToCompany($outletId);

        $file = $request->file('file');
        try {
            $companyId = session('company.company_id');
            $import = new OpeningStockGeneralImport(Auth::id(), $companyId, $outletId);
            Excel::import($import, $file);

            $summary = $import->summary();
            $message = sprintf(
                __('Opening stock import finished. Processed: %d, Created: %d, Skipped: %d.'),
                $summary['processed'],
                $summary['created'],
                $summary['skipped']
            );

            return back()
                ->with('success_general', $message)
                ->with('import_summary_general', $summary)
                ->with('import_failures_general', $summary['failures'] ?? []);
        } catch (ExcelValidationException $exception) {
            $failures = collect($exception->failures())->map(function ($failure) {
                return [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            })->values()->all();

            return back()
                ->withErrors(['file' => __('Import file contains invalid data. Review the error list below.')])
                ->with('import_failures_general', $failures ?? []);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['file' => __('Unable to process the import. Please try again or contact support.')]);
        }
    }

    /**
     * Store opening stock import for IMEI/Serial products (Card 2)
     */
    public function storeOpeningStockImeiSerial(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200',
        ]);

        $outletId = (int) session('outlet.outlet_id');
        if (!$outletId) {
            return back()->withErrors(['file_imei' => __('Please select an outlet first.')]);
        }
        $this->ensureOutletBelongsToCompany($outletId);

        $file = $request->file('file');
        try {
            $companyId = session('company.company_id');
            $import = new OpeningStockImeiSerialImport(Auth::id(), $companyId, $outletId);
            Excel::import($import, $file);

            $summary = $import->summary();
            $message = sprintf(
                __('Opening stock import finished. Processed: %d, Created: %d, Skipped: %d.'),
                $summary['processed'],
                $summary['created'],
                $summary['skipped']
            );

            return back()
                ->with('success_imei', $message)
                ->with('import_summary_imei', $summary)
                ->with('import_failures_imei', $summary['failures'] ?? []);
        } catch (ExcelValidationException $exception) {
            $failures = collect($exception->failures())->map(function ($failure) {
                return [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            })->values()->all();

            return back()
                ->withErrors(['file_imei' => __('Import file contains invalid data. Review the error list below.')])
                ->with('import_failures_imei', $failures ?? []);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['file_imei' => __('Unable to process the import. Please try again or contact support.')]);
        }
    }

    /**
     * Export opening stock template for Medicine_Product (Card 3)
     */
    public function exportOpeningStockMedicine(): BinaryFileResponse
    {
        $companyId = session('company.company_id');
        return Excel::download(
            new OpeningStockMedicineExport($companyId),
            'opening-stock-medicine-' . date('Y-m-d-His') . '.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    /**
     * Store opening stock import for Medicine products (Card 3)
     */
    public function storeOpeningStockMedicine(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200',
        ]);

        $outletId = (int) session('outlet.outlet_id');
        if (!$outletId) {
            return back()->withErrors(['file_medicine' => __('Please select an outlet first.')]);
        }
        $this->ensureOutletBelongsToCompany($outletId);

        $file = $request->file('file');
        try {
            $companyId = session('company.company_id');
            $import = new OpeningStockMedicineImport(Auth::id(), $companyId, $outletId);
            Excel::import($import, $file);

            $summary = $import->summary();
            $message = sprintf(
                __('Opening stock import finished. Processed: %d, Created: %d, Skipped: %d.'),
                $summary['processed'],
                $summary['created'],
                $summary['skipped']
            );

            return back()
                ->with('success_medicine', $message)
                ->with('import_summary_medicine', $summary)
                ->with('import_failures_medicine', $summary['failures'] ?? []);
        } catch (ExcelValidationException $exception) {
            $failures = collect($exception->failures())->map(function ($failure) {
                return [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            })->values()->all();

            return back()
                ->withErrors(['file_medicine' => __('Import file contains invalid data. Review the error list below.')])
                ->with('import_failures_medicine', $failures ?? []);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['file_medicine' => __('Unable to process the import. Please try again or contact support.')]);
        }
    }

    private function ensureOutletBelongsToCompany(int $outletId): void
    {
        $companyId = session('company.company_id');
        $exists = Outlet::where('id', $outletId)->where('company_id', $companyId)->exists();
        if (!$exists) {
            abort(403, __('Outlet does not belong to your company.'));
        }
    }

    /**
     * Download sample Excel file for bulk item import from public/sample/Item-Upload.xlsx
     */
    public function downloadItemImportSample()
    {
        $path = public_path('sample/Item-Upload.xlsx');
        if (!file_exists($path)) {
            abort(404, 'Sample file not found.');
        }
        return response()->download($path, 'Item-Upload.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Store bulk imported items
     */
    public function bulkItemImportStore(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200',
        ]);

        $file = $request->file('file');
        $removeDuplicateItems = $request->boolean('remove_duplicate_items');
        $removeAllItems = $request->boolean('remove_all_items');

        try {
            $companyId = session('company.company_id');
            $import = new ItemImport(Auth::id(), $companyId, $removeDuplicateItems, $removeAllItems);
            Excel::import($import, $file);

            $summary = $import->summary();
            $message = sprintf(
                'Bulk import finished. Processed: %d, Created: %d, Updated: %d, Skipped: %d.',
                $summary['processed'],
                $summary['created'],
                $summary['updated'],
                $summary['skipped']
            );

            return back()
                ->with('success', $message)
                ->with('import_summary', $summary)
                ->with('import_failures', $summary['failures'] ?? []);
        } catch (ExcelValidationException $exception) {
            $failures = collect($exception->failures())->map(function ($failure) {
                return [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            })->values()->all();

            return back()
                ->withErrors(['file' => 'Import file contains invalid data. Review the error list below.'])
                ->with('import_failures', $failures ?? []);
        } catch (\RuntimeException $exception) {
            return back()
                ->withErrors(['file' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            report($exception);

            return back()
                ->withErrors(['file' => 'Unable to process the import right now. Please try again or contact support.']);
        }
    }

    /**
     * Show bulk update form
     */
    public function bulkItemUpdate()
    {
        return view('stock::item.bulk-update');
    }

    /**
     * Process bulk update
     */
    public function bulkUpdateItems(Request $request)
    {
        try {
            $request->validate([
                'items' => 'required|json',
            ]);

            $items = json_decode($request->input('items'), true);
            $updatedCount = 0;
            $errors = [];

            foreach ($items as $encryptedId => $itemData) {
                try {
                    $item = $this->itemService->getItemByEncryptedId($encryptedId);
                    
                    if (!$item) {
                        $errors[] = "Item with ID {$encryptedId} not found.";
                        continue;
                    }

                    $updateData = [];

                    if (isset($itemData['sale_price'])) {
                        $updateData['sale_price'] = $itemData['sale_price'];
                    }

                    if (isset($itemData['mrp_price'])) {
                        $updateData['mrp_price'] = $itemData['mrp_price'];
                    }

                    if (isset($itemData['whole_sale_price'])) {
                        $updateData['whole_sale_price'] = $itemData['whole_sale_price'];
                    }

                    if (isset($itemData['enable_disable_status'])) {
                        $updateData['enable_disable_status'] = $itemData['enable_disable_status'];
                    }

                    // Handle image upload
                    if ($request->hasFile("images.{$encryptedId}")) {
                        $image = $request->file("images.{$encryptedId}");
                        $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                        $image->move(public_path('uploads/items'), $imageName);
                        
                        // Delete old image if exists
                        if ($item->photo && file_exists(public_path('uploads/items/' . $item->photo))) {
                            @unlink(public_path('uploads/items/' . $item->photo));
                        }
                        
                        $updateData['photo'] = $imageName;
                    }

                    if (!empty($updateData)) {
                        $item->update($updateData);
                        $updatedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error updating item {$encryptedId}: " . $e->getMessage();
                }
            }

            $message = "Successfully updated {$updatedCount} item(s).";
            if (!empty($errors)) {
                $message .= " " . count($errors) . " error(s) occurred.";
            }

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'updated_count' => $updatedCount,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Process bulk delete
     */
    public function bulkDeleteItems(Request $request)
    {
        try {
            $request->validate([
                'item_ids' => 'required|array',
                'item_ids.*' => 'required|string',
            ]);

            $itemIds = $request->input('item_ids');
            $deletedCount = 0;
            $errors = [];

            foreach ($itemIds as $encryptedId) {
                try {
                    $this->itemService->deleteItem($encryptedId);
                    $deletedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to delete item {$encryptedId}: " . $e->getMessage();
                }
            }

            $message = "Successfully deleted {$deletedCount} item(s).";
            if (!empty($errors)) {
                $message .= " " . count($errors) . " error(s) occurred.";
            }

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
