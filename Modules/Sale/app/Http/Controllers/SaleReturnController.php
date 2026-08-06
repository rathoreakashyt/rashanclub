<?php

namespace Modules\Sale\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Modules\Sale\Models\Customer;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Sale\Services\SaleReturnService;
use Modules\Sale\Http\Requests\SaleReturnRequest;

class SaleReturnController extends Controller
{
    protected $saleReturnService;

    public function __construct(SaleReturnService $saleReturnService)
    {
        $this->saleReturnService = $saleReturnService;
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

            $data = $this->saleReturnService->getDataTableData($params);
            return response()->json($data);
        }

        return view('sale::sale-return.index', compact('status'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $companyId = session('company.company_id');
        
        $customers = Customer::where([
            ['name', '!=', 'Walk-in Customer'],
            ['del_status', 'Live'],
            ['company_id', $companyId]
        ])->get();
        
        $payment_methods = PaymentMethod::where([
            ['status', 'Enable'],
            ['account_type', '!=', 'Loyalty Point'],
            ['del_status', 'Live'],
            ['company_id', $companyId]
        ])->orderBy('sort_id')->get();
        
        $reference_no = $this->saleReturnService->generateReferenceNumber($companyId);
        
        return view('sale::sale-return.create', compact('customers', 'payment_methods', 'reference_no'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SaleReturnRequest $request)
    {
        try {
            $saleReturn = $this->saleReturnService->createSaleReturn($request);

            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Sale return created successfully',
                    'data' => $saleReturn
                ]);
            }
            
            return redirect()->route('sale-return.index')
                ->with('success', 'Sale return created successfully');
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
                    'message' => 'Failed to create sale return: ' . $e->getMessage()
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
        $saleReturn = $this->saleReturnService->getSaleReturnByEncryptedId($id);
        
        if (!$saleReturn) {
            return redirect()->route('sale-return.index')
                ->with('error', 'Sale return not found');
        }
        
        // Load relationships (only live sale return details)
        $saleReturn->load([
            'customer', 
            'sale',
            'paymentMethod',
            'company',
            'outlet',
            'saleReturnDetails' => function($query) {
                $query->where('del_status', 'Live');
            },
            'saleReturnDetails.item.parent'
        ]);
        
        return view('sale::sale-return.show', compact('saleReturn'));
    }

    /**
     * Print sale return invoice (A4 format)
     */
    public function printInvoice(string $id)
    {
        try {
            $saleReturn = $this->saleReturnService->getSaleReturnByEncryptedId($id);

            if (!$saleReturn) {
                return redirect()->route('sale-return.index')
                    ->with('error', 'Sale return not found');
            }

            // Load relationships (only live sale return details)
            $saleReturn->load([
                'customer', 
                'sale',
                'paymentMethod',
                'company',
                'outlet',
                'saleReturnDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'saleReturnDetails.item.parent'
            ]);

            return view('sale::sale-return.a4-invoice', compact('saleReturn'));
        } catch (\Exception $e) {
            return redirect()->route('sale-return.index')
                ->with('error', 'Sale return not found');
        }
    }

    /**
     * Generate PDF for sale return (A4 format)
     */
    public function generatePdf(string $id)
    {
        try {
            $saleReturn = $this->saleReturnService->getSaleReturnByEncryptedId($id);

            if (!$saleReturn) {
                return redirect()->route('sale-return.index')
                    ->with('error', 'Sale return not found');
            }

            // Load relationships (only live sale return details)
            $saleReturn->load([
                'customer', 
                'sale',
                'paymentMethod',
                'company',
                'outlet',
                'saleReturnDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'saleReturnDetails.item.parent'
            ]);

            // Render the view to HTML
            $html = view('sale::sale-return.a4-invoice-pdf', compact('saleReturn'))->render();

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
            $filename = 'Sale Return Reference No - ' . $saleReturn->reference_no . '.pdf';

            // Get PDF content as string
            $pdfContent = $mpdf->Output('', 'S');

            // Return PDF as download response
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Content-Length', strlen($pdfContent));
        } catch (\Exception $e) {
            return redirect()->route('sale-return.index')
                ->with('error', 'Error generating PDF: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $saleReturn = $this->saleReturnService->getSaleReturnByEncryptedId($id);
        
        if (!$saleReturn) {
            return redirect()->route('sale-return.index')
                ->with('error', 'Sale return not found');
        }
        
        // Load relationships (only live sale return details)
        $saleReturn->load([
            'saleReturnDetails' => function($query) {
                $query->where('del_status', 'Live');
            },
            'saleReturnDetails.item.parent',
            'paymentMethod'
        ]);
        
        $companyId = session('company.company_id');
        
        $customers = Customer::where([
            ['del_status', 'Live'],
            ['company_id', $companyId]
        ])->get();
        
        $payment_methods = PaymentMethod::where([
            ['del_status', 'Live'],
            ['company_id', $companyId]
        ])->get();
        
        return view('sale::sale-return.edit', compact('saleReturn', 'customers', 'payment_methods'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SaleReturnRequest $request, string $id)
    {
        try {
            $saleReturn = $this->saleReturnService->updateSaleReturn($request, $id);
            
            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Sale return updated successfully',
                    'data' => $saleReturn
                ]);
            }
            
            return redirect()->route('sale-return.index')
                ->with('success', 'Sale return updated successfully');
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
                    'message' => 'Failed to update sale return: ' . $e->getMessage()
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
            $this->saleReturnService->deleteSaleReturn($id);
            
            return redirect()->route('sale-return.index')
                ->with('success', 'Sale return deleted successfully');
        } catch (\Exception $e) {
            return redirect()->route('sale-return.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Get customer sale invoices (AJAX endpoint)
     */
    public function getCustomerSaleInvoices(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'customer_id' => 'required|exists:customers,id',
            ]);

            $customerId = $request->customer_id;
            $invoices = $this->saleReturnService->getCustomerSaleInvoices($customerId);

            return response()->json([
                'success' => true,
                'data' => $invoices,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get sale invoice items (AJAX endpoint)
     */
    public function getSaleInvoiceItems(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'sale_id' => 'required|exists:sales,id',
            ]);

            $saleId = $request->sale_id;
            $items = $this->saleReturnService->getSaleInvoiceItems($saleId);

            return response()->json([
                'success' => true,
                'data' => $items,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get POS form data (AJAX endpoint for POS context)
     */
    public function getPosFormData(Request $request): JsonResponse
    {
        try {
            $companyId = session('company.company_id');
            
            $customers = Customer::where([
                ['name', '!=', 'Walk-in Customer'],
                ['del_status', 'Live'],
                ['company_id', $companyId]
            ])->get(['id', 'name']);
            
            $payment_methods = PaymentMethod::where([
                ['status', 'Enable'],
                ['account_type', '!=', 'Loyalty Point'],
                ['del_status', 'Live'],
                ['company_id', $companyId]
            ])->orderBy('sort_id')->get(['id', 'name']);
            
            $reference_no = $this->saleReturnService->generateReferenceNumber($companyId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'customers' => $customers,
                    'payment_methods' => $payment_methods,
                    'reference_no' => $reference_no,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
