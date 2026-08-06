<?php

namespace Modules\Purchase\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Stock\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Modules\Purchase\Models\Supplier;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Purchase\Services\PurchaseService;
use Modules\Purchase\Http\Requests\PurchaseRequest;

class PurchaseController extends Controller
{
    protected $purchaseService;

    public function __construct(PurchaseService $purchaseService)
    {
        $this->purchaseService = $purchaseService;
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

            $data = $this->purchaseService->getDataTableData($params);
            return response()->json($data);
        }

        return view('purchase::purchase.index', compact('status'));
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
            ['enable_disable_status', 1],
            ['type', '!=', 'Service_Product'],
            ['type', '!=', 'Combo_Product'],
            ['type', '!=', '0'],
            ['company_id', $companyId]
        ])
            ->select('id', 'name', 'code', 'sale_price', 'mrp_price', 'purchase_price', 'purchase_unit_id', 'sale_unit_id', 'type', 'expiry_date_maintain')
            ->orderBy('name', 'asc')
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
        
        $data['reference_no'] = $this->purchaseService->generateReferenceNumber($companyId);
        
        return view('purchase::purchase.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PurchaseRequest $request)
    {
        try {
            $purchase = $this->purchaseService->createPurchase($request);

            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Purchase created successfully',
                    'data' => $purchase
                ]);
            }
            
            return redirect()->route('purchase.index')
                ->with('success', 'Purchase created successfully');
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
                    'message' => 'Failed to create purchase: ' . $e->getMessage()
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
        $purchase = $this->purchaseService->getPurchaseByEncryptedId($id);

        
        if (!$purchase) {
            return redirect()->route('purchase.index')
                ->with('error', 'Purchase not found');
        }
        
        // Load relationships (only live purchase details)
        $purchase->load([
            'supplier', 
            'company',
            'outlet',
            'purchaseDetails' => function($query) {
                $query->where('del_status', 'Live');
            },
            'purchaseDetails.item.parent', 
            'purchasePayments' => function($query) {
                $query->where('del_status', 'Live');
            },
            'purchasePayments.paymentMethod' => function($query) {
                $query->where('del_status', 'Live');
            }
        ]);
        
        return view('purchase::purchase.show', compact('purchase'));
    }

    /**
     * Print purchase invoice (A4 format)
     */
    public function printInvoice(string $id)
    {
        try {
            $purchase = $this->purchaseService->getPurchaseByEncryptedId($id);

            if (!$purchase) {
                return redirect()->route('purchase.index')
                    ->with('error', 'Purchase not found');
            }

            // Load relationships (only live purchase details and payments)
            $purchase->load([
                'supplier', 
                'company',
                'outlet',
                'purchaseDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'purchaseDetails.item.parent', 
                'purchasePayments' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'purchasePayments.paymentMethod'
            ]);

            return view('purchase::purchase.a4-invoice', compact('purchase'));
        } catch (\Exception $e) {
            return redirect()->route('purchase.index')
                ->with('error', 'Purchase not found');
        }
    }

    /**
     * Generate PDF for purchase (A4 format)
     */
    public function generatePdf(string $id)
    {
        try {
            $purchase = $this->purchaseService->getPurchaseByEncryptedId($id);

            if (!$purchase) {
                return redirect()->route('purchase.index')
                    ->with('error', 'Purchase not found');
            }

            // Load relationships (only live purchase details and payments)
            $purchase->load([
                'supplier', 
                'company',
                'outlet',
                'purchaseDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'purchaseDetails.item.parent', 
                'purchasePayments' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'purchasePayments.paymentMethod'
            ]);

            // Render the view to HTML
            $html = view('purchase::purchase.a4-invoice-pdf', compact('purchase'))->render();

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
            $filename = 'Purchase Reference No - ' . $purchase->reference_no . '.pdf';

            // Get PDF content as string
            $pdfContent = $mpdf->Output('', 'S');

            // Return PDF as download response
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Content-Length', strlen($pdfContent));
        } catch (\Exception $e) {
            return redirect()->route('purchase.index')
                ->with('error', 'Error generating PDF: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $purchase = $this->purchaseService->getPurchaseByEncryptedId($id);
        
        if (!$purchase) {
            return redirect()->route('purchase.index')
                ->with('error', 'Purchase not found');
        }
        
        // Load relationships (only live purchase details and payments)
        $purchase->load([
            'purchaseDetails' => function($query) {
                $query->where('del_status', 'Live');
            },
            'purchaseDetails.item.parent',
            'purchasePayments' => function($query) {
                $query->where('del_status', 'Live');
            },
            'purchasePayments.paymentMethod'
        ]);
        
        $companyId = session('company.company_id');
        
        $items = Item::with('purchaseUnit:id,unit_name', 'saleUnit:id,unit_name')->where([
            ['del_status', 'Live'],
            ['enable_disable_status', 1],
            ['type', '!=', 'Service_Product'],
            ['type', '!=', 'Combo_Product'],
            ['type', '!=', '0'],
            ['company_id', $companyId]
        ])
            ->select('id', 'name', 'code', 'sale_price', 'mrp_price', 'purchase_price', 'purchase_unit_id', 'sale_unit_id', 'type', 'expiry_date_maintain')
            ->orderBy('name', 'asc')
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
        
        return view('purchase::purchase.edit', compact('purchase', 'items', 'suppliers', 'payment_methods'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PurchaseRequest $request, string $id)
    {
        try {
            $purchase = $this->purchaseService->updatePurchase($request, $id);
            
            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Purchase updated successfully',
                    'data' => $purchase
                ]);
            }
            
            return redirect()->route('purchase.index')
                ->with('success', 'Purchase updated successfully');
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
                    'message' => 'Failed to update purchase: ' . $e->getMessage()
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
            $this->purchaseService->deletePurchase($id);
            
            return redirect()->route('purchase.index')
                ->with('success', 'Purchase deleted successfully');
        } catch (\Exception $e) {
            return redirect()->route('purchase.index')
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
        ->select('id', 'name', 'code', 'sale_price', 'mrp_price', 'purchase_price', 'purchase_unit_id', 'sale_unit_id', 'type')
        ->get();
        
        return response()->json($childItems);
    }

    /**
     * Add payment to a purchase
     */
    public function addPayment(Request $request, $id)
    {
        try {
            $this->validate($request, [
                'payment_id' => 'required|exists:payment_methods,id',
                'amount' => 'required|numeric|min:0.01',
                'note' => 'nullable|string|max:500'
            ]);
            
            $paymentData = [
                'payment_id' => $request->payment_id,
                'amount' => $request->amount,
                'note' => $request->note
            ];
            
            $this->purchaseService->addPayment($id, $paymentData);
            
            return redirect()->route('purchase.show', $id)
                ->with('success', 'Payment added successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Add multiple payments to a purchase
     */
    public function addMultiplePayments(Request $request, $id)
    {
        try {
            $this->validate($request, [
                'payments' => 'required|array|min:1',
                'payments.*.payment_id' => 'required|exists:payment_methods,id',
                'payments.*.amount' => 'required|numeric|min:0.01',
                'payments.*.note' => 'nullable|string|max:500'
            ]);
            
            $this->purchaseService->addMultiplePayments($id, $request->payments);
            
            return redirect()->route('purchase.show', $id)
                ->with('success', 'Payments added successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Delete a purchase payment
     */
    public function deletePayment($paymentId)
    {
        try {
            $this->purchaseService->deletePurchasePayment($paymentId);
            
            return redirect()->back()
                ->with('success', 'Payment deleted successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Check stock availability
     */
    public function checkStockAvailability(Request $request): JsonResponse
    {

        $itemDetails = $request->item_details;
        $itemType = $request->item_type;
        $itemId = $request->item_id;

        $result = DB::table('items as p')
            ->select([
                'p.name as item_name',
                'p.code as item_code',
                'p.type as item_type',
                DB::raw("(
                    SELECT GROUP_CONCAT(st.expiry_imei_serial SEPARATOR '||')
                    FROM view_stock_detail st
                    WHERE p.id = st.item_id
                    AND st.type = 1
                    AND st.expiry_imei_serial != ''
                    AND st.expiry_imei_serial NOT IN (
                        SELECT st2.expiry_imei_serial
                        FROM view_stock_detail st2
                        WHERE st2.item_id = p.id
                            AND st2.type = 2
                            AND st2.expiry_imei_serial != ''
                    )
                ) as allimei"),
                DB::raw("(
                    SELECT IFNULL(SUM(st3.stock_quantity), 0)
                    FROM view_stock_detail st3
                    WHERE p.id = st3.item_id
                    AND st3.type = 1
                ) as stock_qty"),
                DB::raw("(
                    SELECT IFNULL(SUM(st4.stock_quantity), 0)
                    FROM view_stock_detail st4
                    WHERE p.id = st4.item_id
                    AND st4.type = 2
                ) as out_qty"),
            ])
            ->where('p.id', $itemId)
            ->where('p.del_status', 'Live')
            ->first();
        $imeiToCheck = $itemDetails[0]['value'];
        $imeiArray = explode('||', $result->allimei);
        if (in_array($imeiToCheck, $imeiArray, true)) {
            $cehck = true;
        } else {
            $cehck = false;
        }

        if($cehck){
            return response()->json(['available' => false, 'message' => 'The IMEI number already exists in stock']);
        } else {
            return response()->json(['available' => true]);
        }
    }
    /**
     * Check existing IMEI/Serial number
     */
    public function checkExistingIMEISerial(Request $request): JsonResponse
    {
        $itemDetails = $request->item_details;
        $itemType = $request->item_type;
        $itemId = $request->item_id;
        $exists = DB::table('view_stock_detail')
            ->where('expiry_imei_serial', $itemDetails[0]['value'])
            ->exists();

        if($exists){
            return response()->json(['available' => false, 'message' => 'The IMEI number already taken by another purchase / Sold out']);
        } else {
            return response()->json(['available' => true]);
        }
    }
}
