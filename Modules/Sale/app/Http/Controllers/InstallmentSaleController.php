<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Sale\Http\Request\InstallmentSaleRequest;
use Modules\Sale\Services\InstallmentSaleService;
use Modules\Sale\Services\InstallmentCustomerService;
use Modules\Sale\Models\InstallmentSale;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Stock\Models\Item;

class InstallmentSaleController extends Controller
{
    protected $installmentSaleService;
    protected $installmentCustomerService;

    public function __construct(
        InstallmentSaleService $installmentSaleService,
        InstallmentCustomerService $installmentCustomerService
    ) {
        $this->installmentSaleService = $installmentSaleService;
        $this->installmentCustomerService = $installmentCustomerService;
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
                $this->installmentSaleService->getDataTableData($start, $length, $search, $draw)
            );
        }

        return view('sale::installment-sale.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $companyId = session('company.company_id');

        $data = [
            'customers' => $this->installmentCustomerService->getActiveInstallmentCustomers(),
            'products' => Item::where([
                ['items.del_status', 'Live'],
                ['items.company_id', $companyId],
            ])
            ->whereIn('items.type', [
                'IMEI_Product',
                'Serial_Product',
                'Installment_Product'
            ])
                ->select('items.id', 'items.name', 'items.code', 'items.sale_price', 'items.mrp_price', 'items.type')
                ->leftJoin('brands', 'items.brand_id', '=', 'brands.id')
                ->selectRaw('brands.name as brand_name')
                ->get(),
            'payment_methods' => PaymentMethod::where([
                ['del_status', 'Live'],
                ['company_id', $companyId],
            ])->get(),
            'reference_no' => $this->installmentSaleService->generateReferenceNumber(),
            'precision' => session('company.precision', 2),
        ];

        return view('sale::installment-sale.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(InstallmentSaleRequest $request)
    {
        try {
            $this->installmentSaleService->createInstallmentSale($request->validated());

            if ($request->has('add_more') && $request->add_more == '1') {
                return redirect()->route('installment-sale.create')
                    ->with('success', __('Installment Sale Created Successfully'));
            }

            return redirect()->route('installment-sale.index')
                ->with('success', __('Installment Sale Created Successfully'));
        } catch (\Exception $e) {
            return redirect()->route('installment-sale.create')
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
            $installmentSale = $this->installmentSaleService->getInstallmentSaleByEncryptedId($id);

            if (!$installmentSale) {
                return redirect()->route('installment-sale.index')
                    ->with('error', __('Installment Sale not found'));
            }

            $data = [
                'installmentSale' => $installmentSale,
                'installmentDetails' => $this->installmentSaleService->getInstallmentDetails($installmentSale->id),
                'payments' => $this->installmentSaleService->getPayments($installmentSale->id),
                'payment_methods' => PaymentMethod::where([
                    ['del_status', 'Live'],
                    ['company_id', session('company.company_id')],
                ])->get(),
            ];

            return view('sale::installment-sale.show', $data);
        } catch (\Exception $e) {
            return redirect()->route('installment-sale.index')
                ->with('error', __('Installment Sale not found'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $installmentSale = $this->installmentSaleService->getInstallmentSaleByEncryptedId($id);

            if (!$installmentSale) {
                return redirect()->route('installment-sale.index')
                    ->with('error', __('Installment Sale not found'));
            }
            $companyId = session('company.company_id');
            $data = [
                'installmentSale' => $installmentSale,
                'customers' => $this->installmentCustomerService->getActiveInstallmentCustomers(),
                'products' => Item::where([
                    ['items.del_status', 'Live'],
                    ['items.company_id', $companyId],
                    ['items.type', '!=', 0],
                ])
                ->leftJoin('brands', 'items.brand_id', '=', 'brands.id')
                ->select(
                    'items.id',
                    'items.name',
                    'items.code',
                    'items.sale_price',
                    'items.mrp_price',
                    'items.type'
                )
                ->addSelect('brands.name as brand_name')
                ->get(),
                'payment_methods' => PaymentMethod::where([
                    ['del_status', 'Live'],
                    ['company_id', $companyId],
                ])->get(),
            ];

            return view('sale::installment-sale.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('installment-sale.index')
                ->with('error', __('Installment Sale not found'));
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $installmentSale = $this->installmentSaleService->getInstallmentSaleByEncryptedId($id);

            if (!$installmentSale) {
                return redirect()->route('installment-sale.index')
                    ->with('error', __('Installment Sale not found'));
            }

            $this->installmentSaleService->updateInstallmentSale($installmentSale, $request->all());

            return redirect()->route('installment-sale.index')
                ->with('success', __('Installment Sale Updated Successfully'));
        } catch (\Exception $e) {
            return redirect()->route('installment-sale.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $installmentSale = $this->installmentSaleService->getInstallmentSaleByEncryptedId($id);

            if (!$installmentSale) {
                return redirect()->route('installment-sale.index')
                    ->with('error', __('Installment Sale not found'));
            }

            $this->installmentSaleService->deleteInstallmentSale($installmentSale);

            return redirect()->route('installment-sale.index')
                ->with('success', __('Installment Sale Deleted Successfully'));
        } catch (\Exception $e) {
            return redirect()->route('installment-sale.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Get item details for AJAX request.
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
            'sale_price' => $item->sale_price,
            'mrp_price' => $item->mrp_price,
            'type' => $item->type,
            'requires_imei_serial' => in_array($item->type, ['IMEI_Product', 'Serial_Product']),
        ]);
    }

    /**
     * Get IMEI/Serial numbers for a product.
     */
    public function getImeiSerial(Request $request): JsonResponse
    {
        $itemId = $request->item_id;
        $companyId = session('company.company_id');
        
        // Get available IMEI/Serial numbers from stock (purchase details)
        $item = Item::find($itemId);
        
        if (!$item) {
            return response()->json(['error' => 'Item not found'], 404);
        }

        // Query purchase details for available IMEI/Serial numbers
        $availableImeiSerial = \Modules\Purchase\Models\PurchaseDetail::where('item_id', $itemId)
            ->where('del_status', 'Live')
            ->whereNotNull('expiry_imei_serial')
            ->where('expiry_imei_serial', '!=', '')
            ->pluck('expiry_imei_serial')
            ->filter()
            ->flatMap(function ($value) {
                return explode('||', $value);
            })
            ->map('trim')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Get sold IMEI/Serial numbers
        $soldImeiSerial = \Modules\Sale\Models\InstallmentSale::where('item_id', $itemId)
            ->where('del_status', 'Live')
            ->whereNotNull('expiry_imei_serial')
            ->pluck('expiry_imei_serial')
            ->filter()
            ->toArray();

        // Filter out sold ones
        $availableImeiSerial = array_diff($availableImeiSerial, $soldImeiSerial);

        return response()->json([
            'data' => [
                'allimei' => implode('||', $availableImeiSerial),
            ],
        ]);
    }

    /**
     * Record installment payment.
     */
    public function recordPayment(Request $request, string $detailId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'payment_method_id' => 'required|exists:payment_methods,id',
                'amount' => 'required|numeric|min:0.01',
                'paid_date' => 'nullable|date',
                'account_type' => 'nullable|string',
                'check_no' => 'nullable|string|max:100',
                'check_issue_date' => 'nullable|date',
                'check_expiry_date' => 'nullable|date',
                'mobile_no' => 'nullable|string|max:50',
                'transaction_no' => 'nullable|string|max:100',
                'card_holder_name' => 'nullable|string|max:100',
                'card_holding_number' => 'nullable|string|max:50',
                'paypal_email' => 'nullable|email|max:100',
                'stripe_email' => 'nullable|email|max:100',
                'note' => 'nullable|string|max:500',
            ]);

            $decryptedDetailId = decrypt($detailId);
            $payment = $this->installmentSaleService->recordInstallmentPayment($decryptedDetailId, $validated);

            return response()->json([
                'success' => true,
                'message' => __('Payment recorded successfully'),
                'payment' => $payment,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get overdue installments.
     */
    public function overdueInstallments(): JsonResponse
    {
        $overdueInstallments = $this->installmentSaleService->getOverdueInstallments();

        return response()->json([
            'success' => true,
            'data' => $overdueInstallments,
        ]);
    }

    /**
     * Get statistics for dashboard.
     */
    public function statistics(): JsonResponse
    {
        $statistics = $this->installmentSaleService->getStatistics();

        return response()->json([
            'success' => true,
            'data' => $statistics,
        ]);
    }

    /**
     * Display installment collection page.
     */
    public function installmentCollection()
    {
        $companyId = session('company.company_id');

        $data = [
            'customers' => $this->installmentCustomerService->getActiveInstallmentCustomers(),
            'payment_methods' => PaymentMethod::where([
                ['del_status', 'Live'],
                ['company_id', $companyId],
            ])->get(),
        ];

        return view('sale::installment-collection.index', $data);
    }

    /**
     * Display due installment page.
     */
    public function dueInstallment()
    {
        $companyId = session('company.company_id');

        $data = [
            'customers' => $this->installmentCustomerService->getActiveInstallmentCustomers(),
            'payment_methods' => PaymentMethod::where([
                ['del_status', 'Live'],
                ['company_id', $companyId],
            ])->get(),
        ];

        return view('sale::installment-collection.index', $data);
    }

    /**
     * Get installment sales for a customer (AJAX).
     */
    public function getCustomerInstallments(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'customer_id' => 'required|exists:customers,id',
            ]);

            $customerId = $request->customer_id;
            $companyId = session('company.company_id');

            $installments = InstallmentSale::where([
                ['customer_id', $customerId],
                ['company_id', $companyId],
                ['del_status', 'Live'],
            ])
            ->with(['item'])
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'encrypted_id' => $sale->encrypted_id,
                    'reference_no' => $sale->reference_no,
                    'date' => formatDate($sale->date),
                    'item_name' => $sale->item->name ?? 'N/A',
                    'total' => formatAmount($sale->total),
                    'paid_amount' => formatAmount($sale->paid_amount),
                    'due_amount' => formatAmount($sale->due_amount),
                    'status' => $sale->status,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $installments,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get installment items (details) for a sale (AJAX).
     */
    public function getInstallmentItems(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'installment_sale_id' => 'required|string',
            ]);

            // Decrypt the encrypted ID
            try {
                $installmentSaleId = decrypt($request->installment_sale_id);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => __('The selected installment sale id is invalid.'),
                ], 400);
            }

            // Validate that the decrypted ID exists
            $installmentSale = InstallmentSale::where('id', $installmentSaleId)
                ->where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->first();

            if (!$installmentSale) {
                return response()->json([
                    'success' => false,
                    'message' => __('The selected installment sale id is invalid.'),
                ], 400);
            }

            $installmentDetails = $this->installmentSaleService->getInstallmentDetails($installmentSaleId);

            $items = $installmentDetails->map(function ($detail) {
                return [
                    'id' => $detail->id,
                    'encrypted_id' => $detail->encrypted_id,
                    'amount' => formatAmount($detail->amount_of_payment),
                    'paid_amount' => formatAmount($detail->paid_amount),
                    'remaining_amount' => formatAmount($detail->amount_of_payment - $detail->paid_amount),
                    'payment_date' => formatDate($detail->payment_date),
                    'paid_date' => $detail->paid_date ? formatDate($detail->paid_date) : '-',
                    'paid_status' => $detail->paid_status,
                ];
            });

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
     * Show payment page for an installment detail.
     */
    public function showPaymentPage(string $detailId)
    {
        try {
            $decryptedDetailId = decrypt($detailId);
            $detail = $this->installmentSaleService->getInstallmentDetailById($decryptedDetailId);

            if (!$detail) {
                return redirect()->route('installment-collection.index')
                    ->with('error', __('Installment detail not found'));
            }

            $installmentSale = $detail->installmentSale;
            $customer = $installmentSale->customer;
            
            $companyId = session('company.company_id');

            // Get all due installments for this customer
            $dueInstallments = $this->installmentSaleService->getCustomerDueInstallments($customer->id);

            $data = [
                'detail' => $detail,
                'installmentSale' => $installmentSale,
                'customer' => $customer,
                'allInstallmentDetails' => $this->installmentSaleService->getInstallmentDetails($installmentSale->id),
                'dueInstallments' => $dueInstallments,
                'payment_methods' => PaymentMethod::where([
                    ['del_status', 'Live'],
                    ['company_id', $companyId],
                ])->get(),
            ];

            return view('sale::installment-collection.payment', $data);
        } catch (\Exception $e) {
            return redirect()->route('installment-collection.index')
                ->with('error', __('Installment detail not found'));
        }
    }

    /**
     * Get due installments for listing (AJAX).
     * Shows all unpaid installments from installment_sale_details table.
     */
    public function getDueInstallments(Request $request): JsonResponse
    {
        try {
            $companyId = session('company.company_id');
            
            // Get all unpaid installments (not just overdue)
            $dueInstallments = \Modules\Sale\Models\InstallmentSaleDetail::with(['installmentSale.customer', 'installmentSale.item'])
                ->whereHas('installmentSale', function ($q) use ($companyId) {
                    $q->where('company_id', $companyId)
                      ->where('del_status', 'Live');
                })
                ->where('paid_status', '!=', 'Paid')
                ->where('del_status', 'Live')
                ->orderBy('payment_date')
                ->get();
            
            $items = $dueInstallments->map(function ($detail) {
                $sale = $detail->installmentSale;
                $customer = $sale->customer;
                $item = $sale->item;
                
                return [
                    'id' => $detail->id,
                    'encrypted_id' => $detail->encrypted_id,
                    'reference_no' => $sale->reference_no,
                    'customer_name' => $customer->name ?? 'N/A',
                    'customer_phone' => $customer->phone ?? 'N/A',
                    'item_name' => $item->name ?? 'N/A',
                    'payment_date' => formatDate($detail->payment_date),
                    'amount' => formatAmount($detail->amount_of_payment),
                    'paid_amount' => formatAmount($detail->paid_amount),
                    'remaining_amount' => formatAmount($detail->amount_of_payment - $detail->paid_amount),
                    'paid_status' => $detail->paid_status,
                    'is_overdue' => $detail->payment_date < now()->toDateString() && $detail->paid_status !== 'Paid',
                ];
            });

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
     * Send SMS/Email notifications to selected due installments.
     */
    public function sendDueNotifications(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'required|in:sms,email',
                'detail_ids' => 'nullable|array',
                'detail_ids.*' => 'string',
            ]);

            $type = $request->type;
            $detailIds = $request->detail_ids ?? [];
            
            // If no IDs provided, get all due installments
            if (empty($detailIds)) {
                $dueInstallments = $this->installmentSaleService->getOverdueInstallments();
            } else {
                // Decrypt IDs and get selected installments
                $decryptedIds = array_map(function ($id) {
                    try {
                        return decrypt($id);
                    } catch (\Exception $e) {
                        return null;
                    }
                }, $detailIds);
                
                $decryptedIds = array_filter($decryptedIds);
                
                $dueInstallments = \Modules\Sale\Models\InstallmentSaleDetail::with(['installmentSale.customer', 'installmentSale.item'])
                    ->whereIn('id', $decryptedIds)
                    ->where('paid_status', '!=', 'Paid')
                    ->where('del_status', 'Live')
                    ->get();
            }
            
            $customers = $dueInstallments->groupBy(function ($detail) {
                return $detail->installmentSale->customer_id;
            });

            $sent = 0;
            $failed = 0;
            $errors = [];

            foreach ($customers as $customerId => $installments) {
                $customer = $installments->first()->installmentSale->customer;
                $totalDue = $installments->sum(function ($detail) {
                    return ($detail->amount_of_payment ?? $detail->amount ?? 0) - ($detail->paid_amount ?? 0);
                });
                
                if ($type === 'sms' && $customer->phone) {
                    $message = "Dear {$customer->name}, you have pending installment payments, Due Amount: " . formatAmount($totalDue) . ". Please make payment at your earliest convenience.";
                    $result = $this->sendSMSNotification($customer->phone, $message);
                    
                    if ($result['status'] === 'Success') {
                        $sent++;
                    } else {
                        $failed++;
                        $errors[] = "Failed to send SMS to {$customer->name}: {$result['message']}";
                    }
                } elseif ($type === 'email' && $customer->email) {
                    $subject = "Pending Installment Payment Reminder";
                    $message = "Dear {$customer->name},<br><br>You have pending installment payments totaling " . formatAmount($totalDue) . ".<br><br>Please make payment at your earliest convenience.";
                    $result = $this->sendEmailNotification($customer->email, $subject, $message);
                    
                    if ($result['status'] === 'Success') {
                        $sent++;
                    } else {
                        $failed++;
                        $errors[] = "Failed to send email to {$customer->name}: {$result['message']}";
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Sent {$type} to {$sent} customers. {$failed} failed.",
                'sent' => $sent,
                'failed' => $failed,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Send SMS notification.
     */
    protected function sendSMSNotification(string $phone, string $message): array
    {
        try {
            $smsService = app(\Modules\Configuration\Services\SMSService::class);
            return $smsService->sendSMS($phone, $message);
        } catch (\Exception $e) {
            return [
                'status' => 'Error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send Email notification.
     */
    protected function sendEmailNotification(string $email, string $subject, string $message): array
    {
        try {
            $emailService = app(\Modules\Configuration\Services\EmailService::class);
            return $emailService->sendEmail($email, $subject, $message);
        } catch (\Exception $e) {
            return [
                'status' => 'Error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Print installment sale invoice (A4 format)
     */
    public function printInvoice(string $id)
    {
        try {
            $installmentSale = $this->installmentSaleService->getInstallmentSaleByEncryptedId($id);

            if (!$installmentSale) {
                return redirect()->route('installment-sale.index')
                    ->with('error', __('Installment Sale not found'));
            }

            // Load relationships
            $installmentSale->load(['customer', 'item', 'outlet', 'company']);
            $installmentDetails = $this->installmentSaleService->getInstallmentDetails($installmentSale->id);

            return view('sale::installment-sale.a4-invoice', compact('installmentSale', 'installmentDetails'));
        } catch (\Exception $e) {
            return redirect()->route('installment-sale.index')
                ->with('error', __('Installment Sale not found'));
        }
    }

    /**
     * Generate PDF for installment sale (A4 format)
     */
    public function generatePdf(string $id)
    {
        try {
            $installmentSale = $this->installmentSaleService->getInstallmentSaleByEncryptedId($id);

            if (!$installmentSale) {
                return redirect()->route('installment-sale.index')
                    ->with('error', __('Installment Sale not found'));
            }

            // Load relationships
            $installmentSale->load(['customer', 'item', 'outlet', 'company']);
            $installmentDetails = $this->installmentSaleService->getInstallmentDetails($installmentSale->id);

            // Render the view to HTML
            $html = view('sale::installment-sale.a4-invoice-pdf', compact('installmentSale', 'installmentDetails'))->render();

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
            $filename = 'Installment Sale Invoice - ' . $installmentSale->reference_no . '.pdf';

            // Get PDF content as string
            $pdfContent = $mpdf->Output('', 'S');

            // Return PDF as download response
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Content-Length', strlen($pdfContent));
        } catch (\Exception $e) {
            return redirect()->route('installment-sale.index')
                ->with('error', __('Error generating PDF: ') . $e->getMessage());
        }
    }
}

