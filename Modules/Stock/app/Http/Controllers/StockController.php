<?php

namespace Modules\Stock\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Stock\Services\StockService;
use Modules\Stock\Models\Item;
use Modules\Stock\Models\ItemCategory;
use Modules\Stock\Models\Brand;
use Modules\Purchase\Models\Supplier;

class StockController extends Controller
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $companyId = session('company.company_id');
        
        // Get filter data
        $data = [
            'items' => Item::where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->select('id', 'name', 'code', 'parent_id', 'mrp_price', 'sale_price')
                ->get(),
            'item_categories' => ItemCategory::where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->select('id', 'name')
                ->get(),
            'brands' => Brand::where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->select('id', 'name')
                ->get(),
            'suppliers' => Supplier::where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->select('id', 'name')
                ->get(),
        ];
        
        return view('stock::stock.index', $data);
    }

    /**
     * Get AJAX data for DataTables
     */
    public function stock(Request $request)
    {
        $filters = [
            'item_id' => $request->input('item_id', ''),
            'item_code' => $request->input('item_code', ''),
            'brand_id' => $request->input('brand_id', ''),
            'category_id' => $request->input('category_id', ''),
            'supplier_id' => $request->input('supplier_id', ''),
            'generic_name' => $request->input('generic_name', ''),
            'price_type' => $request->input('price_type', 'last_three_purchase_avg'),
            'search' => $request->input('search.value', ''),
        ];

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length < 1) {
            $length = 10;
        }

        $result = $this->stockService->getDataTableData($filters, $start, $length);
        $stockEvaluation = $this->stockService->getStockEvaluation($filters);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $result['recordsTotal'],
            'recordsFiltered' => $result['recordsFiltered'],
            'data' => $result['data'],
            'stock_value' => $stockEvaluation,
            'alertSum' => $result['alertSum'],
        ]);
    }

    /**
     * Get stock segmentation of an item (for modal)
     */
    public function getStockSegmentationOfItem(Request $request)
    {
        $itemId = $request->input('item_id');
        $itemType = $request->input('item_type');
        
        $html = $this->stockService->getStockSegmentationOfItem($itemId, $itemType);
        
        return response()->json($html);
    }

    /**
     * Display low stock listing
     */
    public function lowStock()
    {
        $companyId = session('company.company_id');
        
        // Get filter data
        $data = [
            'items' => Item::where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->select('id', 'name', 'code', 'parent_id', 'mrp_price', 'sale_price')
                ->get(),
            'item_categories' => ItemCategory::where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->select('id', 'name')
                ->get(),
            'brands' => Brand::where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->select('id', 'name')
                ->get(),
            'suppliers' => Supplier::where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->select('id', 'name')
                ->get(),
        ];
        
        return view('stock::stock.low-stock', $data);
    }

    /**
     * Get AJAX data for Low Stock DataTables
     */
    public function getLowStockAjaxData(Request $request)
    {
        $filters = [
            'item_id' => $request->input('item_id', ''),
            'item_code' => $request->input('item_code', ''),
            'brand_id' => $request->input('brand_id', ''),
            'category_id' => $request->input('category_id', ''),
            'supplier_id' => $request->input('supplier_id', ''),
            'generic_name' => $request->input('generic_name', ''),
            'price_type' => $request->input('price_type', 'last_three_purchase_avg'),
        ];

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length < 1) {
            $length = 10;
        }

        $result = $this->stockService->getLowStockDataTableData($filters, $start, $length);
        $stockEvaluation = $this->stockService->getStockEvaluation($filters);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $result['recordsTotal'],
            'recordsFiltered' => $result['recordsFiltered'],
            'data' => $result['data'],
            'stock_value' => $stockEvaluation,
            'alertSum' => $result['alertSum'],
        ]);
    }
}
