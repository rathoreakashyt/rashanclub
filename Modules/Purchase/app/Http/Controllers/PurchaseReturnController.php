<?php

namespace Modules\Purchase\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Stock\Models\Item;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Purchase\Models\Supplier;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Purchase\Services\PurchaseReturnService;
use Modules\Purchase\Http\Requests\PurchaseReturnRequest;

class PurchaseReturnController extends Controller
{
    protected $purchaseReturnService;

    public function __construct(PurchaseReturnService $purchaseReturnService)
    {
        $this->purchaseReturnService = $purchaseReturnService;
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

            $data = $this->purchaseReturnService->getDataTableData($params);
            return response()->json($data);
        }

        return view('purchase::purchase-return.index', compact('status'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data = [];
        $companyId = session('company.company_id');
        
        $data['items'] = Item::with('purchaseUnit:id,unit_name', 'saleUnit:id,unit_name')->where([
            ['del_status', 'Live'],
            ['company_id', $companyId],
            ['type', '!=', '0']
        ])
            ->select('id', 'name', 'code', 'sale_price', 'mrp_price', 'purchase_price', 'purchase_unit_id', 'sale_unit_id', 'type', 'expiry_date_maintain', 'conversion_rate', 'unit_type')
            ->get();
            
        $data['suppliers'] = Supplier::where([
            ['del_status', 'Live'],
            ['company_id', $companyId]
        ])->get();

        $data['payment_methods'] = PaymentMethod::where([
            ['del_status', 'Live'],
            ['status', 'Enable'],
            ['account_type', '!=', 'Loyalty Point'],
            ['company_id', $companyId]
        ])->orderBy('sort_id')->get();
        
        $data['reference_no'] = $this->purchaseReturnService->generateReferenceNumber($companyId);
        
        return view('purchase::purchase-return.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PurchaseReturnRequest $request)
    {
        try {
            $purchaseReturn = $this->purchaseReturnService->createPurchaseReturn($request);

            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Purchase Return created successfully',
                    'data' => $purchaseReturn
                ]);
            }
            
            return redirect()->route('purchase-return.index')
                ->with('success', 'Purchase Return created successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to create purchase return: ' . $e->getMessage()
                ], 500);
            }
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $purchaseReturn = $this->purchaseReturnService->getPurchaseReturnByEncryptedId($id);
        
        if (!$purchaseReturn) {
            return redirect()->route('purchase-return.index')
                ->with('error', 'Purchase Return not found');
        }
        
        // Load relationships (only live purchase return details)
        $purchaseReturn->load([
            'supplier', 
            'company',
            'outlet',
            'purchaseReturnDetails' => function($query) {
                $query->where('del_status', 'Live');
            },
            'purchaseReturnDetails.item.parent'
        ]);
        
        return view('purchase::purchase-return.show', compact('purchaseReturn'));
    }

    /**
     * Print purchase return invoice (A4 format)
     */
    public function printInvoice(string $id)
    {
        try {
            $purchaseReturn = $this->purchaseReturnService->getPurchaseReturnByEncryptedId($id);

            if (!$purchaseReturn) {
                return redirect()->route('purchase-return.index')
                    ->with('error', 'Purchase Return not found');
            }

            // Load relationships (only live purchase return details)
            $purchaseReturn->load([
                'supplier', 
                'company',
                'outlet',
                'purchaseReturnDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'purchaseReturnDetails.item.parent'
            ]);

            return view('purchase::purchase-return.a4-invoice', compact('purchaseReturn'));
        } catch (\Exception $e) {
            return redirect()->route('purchase-return.index')
                ->with('error', 'Purchase Return not found');
        }
    }

    /**
     * Generate PDF for purchase return (A4 format)
     */
    public function generatePdf(string $id)
    {
        try {
            $purchaseReturn = $this->purchaseReturnService->getPurchaseReturnByEncryptedId($id);

            if (!$purchaseReturn) {
                return redirect()->route('purchase-return.index')
                    ->with('error', 'Purchase Return not found');
            }

            // Load relationships (only live purchase return details)
            $purchaseReturn->load([
                'supplier', 
                'company',
                'outlet',
                'purchaseReturnDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'purchaseReturnDetails.item.parent'
            ]);

            // Render the view to HTML
            $html = view('purchase::purchase-return.a4-invoice-pdf', compact('purchaseReturn'))->render();

            // Configure mPDF
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0,
                'margin_header' => 0,
                'margin_footer' => 0,
            ]);

            // Write HTML to PDF
            $mpdf->WriteHTML($html);

            // Generate filename
            $filename = 'Purchase Return Reference No - ' . $purchaseReturn->reference_no . '.pdf';

            // Get PDF content as string
            $pdfContent = $mpdf->Output('', 'S');

            // Return PDF as download response
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Content-Length', strlen($pdfContent));
        } catch (\Exception $e) {
            return redirect()->route('purchase-return.index')
                ->with('error', 'Error generating PDF: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $purchaseReturn = $this->purchaseReturnService->getPurchaseReturnByEncryptedId($id);
        
        if (!$purchaseReturn) {
            return redirect()->route('purchase-return.index')
                ->with('error', 'Purchase Return not found');
        }
        
        // Load relationships (only live purchase return details)
        $purchaseReturn->load([
            'purchaseReturnDetails' => function($query) {
                $query->where('del_status', 'Live');
            },
            'purchaseReturnDetails.item.parent'
        ]);
        
        $companyId = session('company.company_id');
        
        $items = Item::with('purchaseUnit:id,unit_name', 'saleUnit:id,unit_name')->where([
            ['del_status', 'Live'],
            ['company_id', $companyId],
            ['type', '!=', '0']
        ])
            ->select('id', 'name', 'code', 'sale_price', 'mrp_price', 'purchase_price', 'purchase_unit_id', 'sale_unit_id', 'type', 'expiry_date_maintain', 'conversion_rate', 'unit_type')
            ->get();
            
        $suppliers = Supplier::where([
            ['del_status', 'Live'],
            ['company_id', $companyId]
        ])->get();
        
        $payment_methods = PaymentMethod::where([
            ['del_status', 'Live'],
            ['status', 'Enable'],
            ['account_type', '!=', 'Loyalty Point'],
            ['company_id', $companyId]
        ])->orderBy('sort_id')->get();


        return view('purchase::purchase-return.edit', compact('purchaseReturn', 'items', 'suppliers', 'payment_methods'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PurchaseReturnRequest $request, string $id)
    {
        try {
            $purchaseReturn = $this->purchaseReturnService->updatePurchaseReturn($request, $id);
            
            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Purchase Return updated successfully',
                    'data' => $purchaseReturn
                ]);
            }
            
            return redirect()->route('purchase-return.index')
                ->with('success', 'Purchase Return updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to update purchase return: ' . $e->getMessage()
                ], 500);
            }
            return redirect()->back()
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
            $this->purchaseReturnService->deletePurchaseReturn($id);
            
            return redirect()->route('purchase-return.index')
                ->with('success', 'Purchase Return deleted successfully');
        } catch (\Exception $e) {
            return redirect()->route('purchase-return.index')
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
        
        $childItems = Item::with('purchaseUnit:id,unit_name', 'saleUnit:id,unit_name')->where([
            ['parent_id', $parentId],
            ['type', '0'], // Type 0 for child items
            ['del_status', 'Live'],
            ['company_id', $companyId]
        ])
        ->select('id', 'name', 'code', 'sale_price', 'mrp_price', 'purchase_price', 'purchase_unit_id', 'sale_unit_id', 'type', 'conversion_rate', 'unit_type')
        ->get();
        
        return response()->json($childItems);
    }

    /**
     * Get current stock for an item (in sale unit) for purchase return validation.
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
}

