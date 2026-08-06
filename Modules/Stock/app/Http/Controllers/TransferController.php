<?php

namespace Modules\Stock\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Stock\Http\Request\TransferRequest;
use Modules\Stock\Services\TransferService;
use Modules\Stock\Models\Item;
use Modules\Configuration\Models\Outlet;

class TransferController extends Controller
{
    protected $transferService;

    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
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
                $this->transferService->getDataTableData($start, $length, $search, $draw)
            );
        }

        return view('stock::transfer.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $companyId = session('company.company_id');

        $data = [
            'outlets' => Outlet::where([
                ['del_status', 'Live'],
                ['company_id', $companyId],
            ])
                ->select('id', 'outlet_name', 'outlet_code')
                ->orderBy('outlet_name')
                ->get(),
            'items' => Item::with('purchaseUnit:id,unit_name', 'saleUnit:id,unit_name', 'brand:id,name')->where([
                ['del_status', 'Live'],
                ['company_id', $companyId],
                ['type', '!=', '0'], // Exclude child items
            ])
                ->select('id', 'name', 'code', 'sale_price', 'mrp_price', 'purchase_price', 'purchase_unit_id', 'sale_unit_id', 'type', 'brand_id', 'expiry_date_maintain')
                ->get(),
            'reference_no' => $this->transferService->generateReferenceNumber(),
        ];

        return view('stock::transfer.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TransferRequest $request)
    {
        try {
            $this->transferService->createTransfer($request->validated());

            return redirect()->route('transfer.index')
                ->with('success', __('Transfer Created Successfully'));
        } catch (\Exception $e) {
            return redirect()->route('transfer.create')
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
            $transfer = $this->transferService->getTransferByEncryptedId($id);

            if (!$transfer) {
                return redirect()->route('transfer.index')
                    ->with('error', __('Transfer not found'));
            }

            // Load relationships
            $transfer->load(['fromOutlet', 'toOutlet', 'company', 'outlet', 'transferDetails.item']);

            return view('stock::transfer.details', compact('transfer'));
        } catch (\Exception $e) {
            return redirect()->route('transfer.index')
                ->with('error', __('Transfer not found'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $transfer = $this->transferService->getTransferByEncryptedId($id);

            if (!$transfer) {
                return redirect()->route('transfer.index')
                    ->with('error', __('Transfer not found'));
            }

            $companyId = session('company.company_id');

            // Load transfer details with relationships for edit
            $transfer->load(['transferDetails.item.parent', 'transferDetails.item.brand', 'transferDetails.item.purchaseUnit', 'transferDetails.item.saleUnit']);

            $data = [
                'transfer' => $transfer,
                'outlets' => Outlet::where([
                    ['del_status', 'Live'],
                    ['company_id', $companyId],
                ])
                    ->select('id', 'outlet_name', 'outlet_code')
                    ->orderBy('outlet_name')
                    ->get(),
                'items' => Item::with('purchaseUnit:id,unit_name', 'saleUnit:id,unit_name', 'brand:id,name')->where([
                    ['del_status', 'Live'],
                    ['company_id', $companyId],
                    ['type', '!=', '0'],
                ])
                    ->select('id', 'name', 'code', 'sale_price', 'mrp_price', 'purchase_price', 'purchase_unit_id', 'sale_unit_id', 'type', 'brand_id')
                    ->get(),
            ];

            return view('stock::transfer.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('transfer.index')
                ->with('error', __('Transfer not found'));
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TransferRequest $request, string $id)
    {
        try {
            $transfer = $this->transferService->getTransferByEncryptedId($id);

            if (!$transfer) {
                return redirect()->route('transfer.index')
                    ->with('error', __('Transfer not found'));
            }

            $this->transferService->updateTransfer($transfer, $request->validated());

            return redirect()->route('transfer.index')
                ->with('success', __('Transfer Updated Successfully'));
        } catch (\Exception $e) {
            return redirect()->route('transfer.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $transfer = $this->transferService->getTransferByEncryptedId($id);

            if (!$transfer) {
                return redirect()->route('transfer.index')
                    ->with('error', __('Transfer not found'));
            }

            $this->transferService->deleteTransfer($transfer);

            return redirect()->route('transfer.index')
                ->with('success', __('Transfer Deleted Successfully'));
        } catch (\Exception $e) {
            return redirect()->route('transfer.index')
                ->with('error', $e->getMessage());
        }
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
        ->with('purchaseUnit:id,unit_name', 'saleUnit:id,unit_name', 'brand:id,name')
        ->select('id', 'name', 'code', 'sale_price', 'mrp_price', 'purchase_price', 'purchase_unit_id', 'sale_unit_id', 'type', 'brand_id')
        ->get()
        ->map(function($childItem) use ($parentName) {
            $childItem->parent_name = $parentName;
            return $childItem;
        });
        
        return response()->json($childItems);
    }

    /**
     * Get current stock for an item at the given (From) outlet (for transfer validation).
     */
    public function getItemStockAtOutlet(Request $request): JsonResponse
    {
        $itemId = $request->item_id;
        $fromOutletId = $request->from_outlet_id ?? $request->outlet_id;

        if (!$itemId) {
            return response()->json(['current_stock' => 0, 'error' => __('Item ID required')], 400);
        }

        $outletId = (int) $fromOutletId;
        if ($outletId <= 0) {
            return response()->json(['current_stock' => 0]);
        }

        $result = DB::table('items as p')
            ->select([
                DB::raw("(
                    SELECT IFNULL(SUM(st3.stock_quantity), 0)
                    FROM view_stock_detail st3
                    WHERE p.id = st3.item_id
                        AND st3.type = 1
                        AND st3.outlet_id = " . $outletId . "
                ) as stock_qty"),
                DB::raw("(
                    SELECT IFNULL(SUM(st4.stock_quantity), 0)
                    FROM view_stock_detail st4
                    WHERE p.id = st4.item_id
                        AND st4.type = 2
                        AND st4.outlet_id = " . $outletId . "
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
     * Check if IMEI/Serial exists in the From Outlet's stock (for transfer).
     * Returns available: false when IMEI/Serial is in outlet stock (allowed),
     * available: true when not in outlet (not allowed).
     */
    public function checkImeiSerialInFromOutlet(Request $request): JsonResponse
    {
        $itemId = $request->item_id;
        $itemDetails = $request->item_details;
        $fromOutletId = $request->from_outlet_id ?? $request->outlet_id ?? session('outlet.outlet_id');

        if (!$itemId || !$itemDetails || !isset($itemDetails[0]['value'])) {
            return response()->json(['available' => true, 'message' => __('Invalid request.')]);
        }

        $imeiToCheck = trim($itemDetails[0]['value']);
        if ($imeiToCheck === '') {
            return response()->json(['available' => true]);
        }

        $outletId = (int) $fromOutletId;
        if ($outletId <= 0) {
            return response()->json(['available' => true, 'message' => __('From Outlet is required.')]);
        }

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
                'message' => __('This IMEI/Serial does not exist in From Outlet\'s stock.'),
            ]);
        }

        $imeiArray = array_map('trim', explode('||', $result->allimei));
        $inOutlet = in_array($imeiToCheck, $imeiArray, true);

        if ($inOutlet) {
            return response()->json(['available' => false, 'message' => __('IMEI/Serial exists in From Outlet\'s stock.')]);
        }

        return response()->json([
            'available' => true,
            'message' => __('This IMEI/Serial does not exist in From Outlet\'s stock.'),
        ]);
    }
}

