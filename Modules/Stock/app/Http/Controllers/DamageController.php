<?php

namespace Modules\Stock\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Stock\Http\Request\DamageRequest;
use Modules\Stock\Services\DamageService;
use Modules\Stock\Models\Item;
use App\Models\User;

class DamageController extends Controller
{
    protected $damageService;

    public function __construct(DamageService $damageService)
    {
        $this->damageService = $damageService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            $length = request()->length ?? 10;
            $start = request()->start ?? 0;
            $search = request()->search['value'] ?? '';
            $draw = request()->draw;

            return response()->json(
                $this->damageService->getDataTableData($start, $length, $search, $draw)
            );
        }

        return view('stock::damage.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $companyId = session('company.company_id');

        $data = [
            'employees' => User::where([
                ['del_status', 'Live'],
                ['company_id', $companyId],
            ])
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get(),
            'users' => User::where([
                ['del_status', 'Live'],
                ['company_id', $companyId],
            ])
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get(),
            'items' => Item::with('purchaseUnit:id,unit_name', 'saleUnit:id,unit_name')->where([
                ['del_status', 'Live'],
                ['company_id', $companyId],
                ['type', '!=', '0'], // Exclude child items
            ])
                ->select('id', 'name', 'code', 'sale_price', 'mrp_price', 'purchase_price', 'purchase_unit_id', 'sale_unit_id', 'type', 'expiry_date_maintain')
                ->get(),
            'reference_no' => $this->damageService->generateReferenceNumber(),
        ];

        return view('stock::damage.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(DamageRequest $request)
    {
        try {
            $this->damageService->createDamage($request->validated());

            return redirect()->route('damage.index')
                ->with('success', __('Damage Created Successfully'));
        } catch (\Exception $e) {
            return redirect()->route('damage.create')
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $damage = $this->damageService->getDamageByEncryptedId($id);

            if (!$damage) {
                return redirect()->route('damage.index')
                    ->with('error', __('Damage not found'));
            }

            // Load relationships
            $damage->load(['employee', 'responsiblePerson', 'company', 'outlet', 'damageDetails.item.parent', 'damageDetails.item.brand']);

            return view('stock::damage.details', compact('damage'));
        } catch (\Exception $e) {
            return redirect()->route('damage.index')
                ->with('error', __('Damage not found'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $damage = $this->damageService->getDamageByEncryptedId($id);

            if (!$damage) {
                return redirect()->route('damage.index')
                    ->with('error', __('Damage not found'));
            }

            $companyId = session('company.company_id');

            // Load damage details with relationships for edit
            $damage->load(['damageDetails.item.parent', 'damageDetails.item.brand', 'damageDetails.item.purchaseUnit', 'damageDetails.item.saleUnit', 'employee', 'responsiblePerson']);

            $data = [
                'damage' => $damage,
                'employees' => User::where([
                    ['del_status', 'Live'],
                    ['company_id', $companyId],
                ])
                    ->select('id', 'name', 'email')
                    ->orderBy('name')
                    ->get(),
                'users' => User::where([
                    ['del_status', 'Live'],
                    ['company_id', $companyId],
                ])
                    ->select('id', 'name', 'email')
                    ->orderBy('name')
                    ->get(),
                'items' => Item::with('purchaseUnit:id,unit_name', 'saleUnit:id,unit_name')->where([
                    ['del_status', 'Live'],
                    ['company_id', $companyId],
                    ['type', '!=', '0'],
                ])
                    ->select('id', 'name', 'code', 'sale_price', 'mrp_price', 'purchase_price', 'purchase_unit_id', 'sale_unit_id', 'type')
                    ->get(),
            ];

            return view('stock::damage.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('damage.index')
                ->with('error', __('Damage not found'));
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(DamageRequest $request, string $id)
    {
        try {
            $damage = $this->damageService->getDamageByEncryptedId($id);

            if (!$damage) {
                return redirect()->route('damage.index')
                    ->with('error', __('Damage not found'));
            }

            $this->damageService->updateDamage($damage, $request->validated());

            return redirect()->route('damage.index')
                ->with('success', __('Damage Updated Successfully'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('damage.edit', $id)
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('damage.edit', $id)
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $damage = $this->damageService->getDamageByEncryptedId($id);

            if (!$damage) {
                return redirect()->route('damage.index')
                    ->with('error', __('Damage not found'));
            }

            $this->damageService->deleteDamage($damage);

            return redirect()->route('damage.index')
                ->with('success', __('Damage Deleted Successfully'));
        } catch (\Exception $e) {
            return redirect()->route('damage.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Get item details
     */
    public function getItemDetails(Request $request): JsonResponse
    {
        $itemId = $request->item_id;
        $item = Item::find($itemId);
        
        if (!$item) {
            return response()->json(['error' => 'Item not found'], 404);
        }
        
        return response()->json([
            'id' => $item->id,
            'name' => $item->name,
            'code' => $item->code,
            'purchase_price' => $item->purchase_price,
            'sale_price' => $item->sale_price,
            'mrp_price' => $item->mrp_price,
            'purchase_unit_id' => $item->purchase_unit_id,
            'sale_unit_id' => $item->sale_unit_id,
            'type' => $item->type,
            'requires_imei_serial' => in_array($item->type, ['IMEI_Product', 'Serial_Product', 'Medicine_Product']),
            'can_be_multiple' => in_array($item->type, ['IMEI_Product', 'Serial_Product', 'Medicine_Product'])
        ]);
    }
    
    /**
     * Get child items for a variation product
     */
    public function getVariationChildItems(Request $request): JsonResponse
    {
        $parentId = $request->parent_id;
        $companyId = session('company.company_id');
        
        // Get parent item
        $parentItem = Item::find($parentId);
        $parentName = $parentItem ? $parentItem->name : '';
        
        $childItems = Item::where([
            ['parent_id', $parentId],
            ['type', '0'], // Type 0 for child items
            ['del_status', 'Live'],
            ['company_id', $companyId]
        ])
        ->with('purchaseUnit:id,unit_name', 'saleUnit:id,unit_name')
        ->select('id', 'name', 'code', 'sale_price', 'mrp_price', 'purchase_price', 'purchase_unit_id', 'sale_unit_id', 'type')
        ->get()
        ->map(function($childItem) use ($parentName) {
            $childItem->parent_name = $parentName;
            return $childItem;
        });
        
        return response()->json($childItems);
    }

    /**
     * Get current stock for an item (in sale unit) for damage validation.
     */
    public function getItemCurrentStock(Request $request): JsonResponse
    {
        $itemId = $request->item_id;
        $outletId = $request->outlet_id ?? session('outlet.outlet_id');

        if (!$itemId) {
            return response()->json(['error' => 'Item ID required'], 400);
        }

        $result = DB::table('items as p')
            ->select([
                DB::raw("(
                    SELECT IFNULL(SUM(st3.stock_quantity), 0)
                    FROM view_stock_detail st3
                    WHERE p.id = st3.item_id
                        AND st3.type = 1
                        AND st3.outlet_id = " . (int) $outletId . "
                ) as stock_qty"),
                DB::raw("(
                    SELECT IFNULL(SUM(st4.stock_quantity), 0)
                    FROM view_stock_detail st4
                    WHERE p.id = st4.item_id
                        AND st4.type = 2
                        AND st4.outlet_id = " . (int) $outletId . "
                ) as out_qty"),
            ])
            ->where('p.id', $itemId)
            ->where('p.company_id', session('company.company_id'))
            ->where('p.del_status', 'Live')
            ->first();

        if (!$result) {
            return response()->json(['current_stock' => 0]);
        }

        $currentStock = (float) $result->stock_qty - (float) $result->out_qty;
        return response()->json(['current_stock' => max(0, $currentStock)]);
    }

    /**
     * Check if IMEI/Serial exists in the current outlet's stock (for damage entry).
     * Returns available: false when IMEI/Serial is in outlet stock (allowed for damage),
     * available: true when not in outlet (not allowed).
     */
    public function checkImeiSerialInOutlet(Request $request): JsonResponse
    {
        $itemId = $request->item_id;
        $itemDetails = $request->item_details;
        $outletId = $request->outlet_id ?? session('outlet.outlet_id');

        if (!$itemId || !$itemDetails || !isset($itemDetails[0]['value'])) {
            return response()->json(['available' => true, 'message' => __('Invalid request.')]);
        }

        $imeiToCheck = trim($itemDetails[0]['value']);
        if ($imeiToCheck === '') {
            return response()->json(['available' => true]);
        }

        $outletId = (int) $outletId;
        if ($outletId <= 0) {
            return response()->json(['available' => true, 'message' => __('Outlet is required.')]);
        }

        // Get all IMEI/Serial in stock (type=1) for this item in this outlet, excluding those already out (type=2)
        $result = DB::table('items as p')
            ->select([
                DB::raw("(
                    SELECT GROUP_CONCAT(st.expiry_imei_serial SEPARATOR '||')
                    FROM view_stock_detail st
                    WHERE p.id = st.item_id
                        AND st.type = 1
                        AND st.outlet_id = " . $outletId . "
                        AND st.expiry_imei_serial != ''
                        AND st.expiry_imei_serial NOT IN (
                            SELECT st2.expiry_imei_serial
                            FROM view_stock_detail st2
                            WHERE st2.item_id = p.id
                                AND st2.type = 2
                                AND st2.outlet_id = " . $outletId . "
                                AND st2.expiry_imei_serial != ''
                        )
                ) as allimei"),
            ])
            ->where('p.id', $itemId)
            ->where('p.company_id', session('company.company_id'))
            ->where('p.del_status', 'Live')
            ->first();

        if (!$result || empty($result->allimei)) {
            return response()->json([
                'available' => true,
                'message' => __('This IMEI/Serial does not exist in this outlet\'s stock.'),
            ]);
        }

        $imeiArray = array_map('trim', explode('||', $result->allimei));
        $inOutlet = in_array($imeiToCheck, $imeiArray, true);

        if ($inOutlet) {
            return response()->json(['available' => false, 'message' => __('IMEI/Serial exists in this outlet\'s stock.')]);
        }

        return response()->json([
            'available' => true,
            'message' => __('This IMEI/Serial does not exist in this outlet\'s stock.'),
        ]);
    }
}

