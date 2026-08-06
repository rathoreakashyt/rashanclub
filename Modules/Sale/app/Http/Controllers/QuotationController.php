<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Sale\Http\Request\QuotationRequest;
use Modules\Sale\Services\QuotationService;
use Modules\Sale\Repositories\CustomerRepository;
use Modules\Stock\Models\Item;

class QuotationController extends Controller
{
    protected $quotationService;
    protected $customerRepository;

    public function __construct(
        QuotationService $quotationService,
        CustomerRepository $customerRepository
    ) {
        $this->quotationService = $quotationService;
        $this->customerRepository = $customerRepository;
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
                $this->quotationService->getDataTableData($start, $length, $search, $draw)
            );
        }

        return view('sale::quotation.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $companyId = session('company.company_id');
        $data = [
            'customers' => $this->customerRepository->all($companyId),
            'items' => Item::with('purchaseUnit:id,unit_name', 'saleUnit:id,unit_name')->where([
                ['del_status', 'Live'],
                ['company_id', $companyId],
                ['type', '!=', '0'], // Exclude child items
            ])
                ->select('id', 'name', 'code', 'sale_price', 'mrp_price', 'purchase_price', 'purchase_unit_id', 'sale_unit_id', 'type')
                ->get(),
            'reference_no' => $this->quotationService->generateReferenceNumber(),
        ];

        return view('sale::quotation.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(QuotationRequest $request)
    {
        try {
            $validated = $request->validated();
            $submitAction = $validated['submit_action'] ?? null;

            $quotation = $this->quotationService->createQuotation($validated);

            return $this->handleSubmitAction($quotation, $submitAction, 'created');
        } catch (\Exception $e) {
            return redirect()->route('quotation.create')
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
            $quotation = $this->quotationService->getQuotationByEncryptedId($id);

            if (!$quotation) {
                return redirect()->route('quotation.index')
                    ->with('error', __('Quotation not found'));
            }

            // Load relationships
            $quotation->load(['customer', 'company', 'outlet', 'quotationDetails.item']);

            return view('sale::quotation.details', compact('quotation'));
        } catch (\Exception $e) {
            return redirect()->route('quotation.index')
                ->with('error', __('Quotation not found'));
        }
    }

    /**
     * Print quotation invoice (A4 format)
     */
    public function printInvoice(string $id)
    {
        try {
            $quotation = $this->quotationService->getQuotationByEncryptedId($id);

            if (!$quotation) {
                return redirect()->route('quotation.index')
                    ->with('error', __('Quotation not found'));
            }

            // Load relationships
            $quotation->load(['customer', 'company', 'outlet', 'quotationDetails.item']);

            return view('sale::quotation.a4-invoice', compact('quotation'));
        } catch (\Exception $e) {
            return redirect()->route('quotation.index')
                ->with('error', __('Quotation not found'));
        }
    }

    /**
     * Generate PDF for quotation (A4 format)
     */
    public function generatePdf(string $id)
    {
        try {
            $quotation = $this->quotationService->getQuotationByEncryptedId($id);

            if (!$quotation) {
                return redirect()->route('quotation.index')
                    ->with('error', __('Quotation not found'));
            }

            // Load relationships
            $quotation->load(['customer', 'company', 'outlet', 'quotationDetails.item']);

            // Render the view to HTML
            $html = view('sale::quotation.a4-invoice-pdf', compact('quotation'))->render();

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
            $filename = 'Quotation Reference No - ' . $quotation->reference_no . '.pdf';

            // Get PDF content as string
            $pdfContent = $mpdf->Output('', 'S');

            // Return PDF as download response
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Content-Length', strlen($pdfContent));
        } catch (\Exception $e) {
            return redirect()->route('quotation.index')
                ->with('error', __('Error generating PDF: ') . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $quotation = $this->quotationService->getQuotationByEncryptedId($id);

            if (!$quotation) {
                return redirect()->route('quotation.index')
                    ->with('error', __('Quotation not found'));
            }

            $companyId = session('company.company_id');

            $data = [
                'quotation' => $quotation,
                'customers' => $this->customerRepository->all($companyId),
                'items' => Item::where([
                    ['del_status', 'Live'],
                    ['company_id', $companyId],
                    ['type', '!=', '0'],
                ])
                    ->select('id', 'name', 'code', 'sale_price', 'mrp_price', 'purchase_price', 'purchase_unit_id', 'sale_unit_id', 'type')
                    ->get(),
            ];

            return view('sale::quotation.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('quotation.index')
                ->with('error', __('Quotation not found'));
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(QuotationRequest $request, string $id)
    {
        try {
            $quotation = $this->quotationService->getQuotationByEncryptedId($id);

            if (!$quotation) {
                return redirect()->route('quotation.index')
                    ->with('error', __('Quotation not found'));
            }

            $validated = $request->validated();
            $submitAction = $validated['submit_action'] ?? null;

            $this->quotationService->updateQuotation($quotation, $validated);

            // Reload quotation with relationships for email/print
            $quotation->load(['customer', 'company', 'outlet', 'quotationDetails.item']);

            return $this->handleSubmitAction($quotation, $submitAction, 'updated');
        } catch (\Exception $e) {
            return redirect()->route('quotation.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $quotation = $this->quotationService->getQuotationByEncryptedId($id);

            if (!$quotation) {
                return redirect()->route('quotation.index')
                    ->with('error', __('Quotation not found'));
            }

            $this->quotationService->deleteQuotation($quotation);

            return redirect()->route('quotation.index')
                ->with('success', __('Quotation Deleted Successfully'));
        } catch (\Exception $e) {
            return redirect()->route('quotation.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Handle post-save action: redirect for email or print.
     */
    protected function handleSubmitAction($quotation, ?string $submitAction, string $flashKey): \Illuminate\Http\RedirectResponse
    {
        $successMessage = $flashKey === 'created'
            ? __('Quotation Created Successfully')
            : __('Quotation Updated Successfully');

        if ($submitAction === 'email') {
            $customer = $quotation->customer;
            if (!$customer || empty($customer->email)) {
                return redirect()->route('quotation.index')
                    ->with('success', $successMessage)
                    ->with('warning', __('Customer has no email address. Quotation saved but email was not sent.'));
            }

            $companyName = $quotation->company->business_name ?? config('app.name');
            $subject = __('Your Quotation') . ' - ' . $quotation->reference_no . ' | ' . $companyName;
            $result = $this->sendQuotationEmailWithPdf($quotation, $customer->email, $subject, $customer->name ?? '');

            if ($result['status'] === 'Success') {
                return redirect()->route('quotation.index')
                    ->with('success', $successMessage . ' ' . __('Email sent to customer.'));
            }

            return redirect()->route('quotation.index')
                ->with('success', $successMessage)
                ->with('warning', __('Email could not be sent: ') . ($result['message'] ?? 'Unknown error'));
        }

        if ($submitAction === 'print') {
            return redirect()->route('quotation.print-invoice', $quotation->encrypted_id)
                ->with('success', $successMessage);
        }

        return redirect()->route('quotation.index')
            ->with('success', $successMessage);
    }

    /**
     * Generate quotation PDF and return content and filename (for email attachment).
     */
    protected function generateQuotationPdfAttachment($quotation): array
    {
        $quotation->load(['customer', 'company', 'outlet', 'quotationDetails.item']);
        $html = view('sale::quotation.a4-invoice-pdf', compact('quotation'))->render();

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
        $mpdf->WriteHTML($html);
        $filename = 'Quotation-' . $quotation->reference_no . '.pdf';
        $content = $mpdf->Output('', 'S');

        return ['content' => $content, 'filename' => $filename];
    }

    /**
     * Send quotation email with PDF attachment via EmailService.
     */
    protected function sendQuotationEmailWithPdf($quotation, string $to, string $subject, string $customerName = ''): array
    {
        try {
            $quotation->loadMissing(['customer', 'company', 'quotationDetails.item']);
            $pdf = $this->generateQuotationPdfAttachment($quotation);

            $message = __('Dear') . ' ' . ($customerName ?: __('Customer')) . ",\n\n"
                . __('Please find your quotation attached to this email.')
                . "\n\n" . __('Thank you for your business.');

            $extraViewData = [
                'referenceNo' => $quotation->reference_no,
                'customerName' => $customerName,
            ];

            $emailService = app(\Modules\Configuration\Services\EmailService::class);
            return $emailService->sendEmail($to, $subject, $message, [], 'Quotation', [
                'content' => $pdf['content'],
                'filename' => $pdf['filename'],
            ], $extraViewData);
        } catch (\Exception $e) {
            return [
                'status' => 'Error',
                'message' => __('Email could not be sent. Please check your mail configuration in settings.'),
            ];
        }
    }

    /**
     * Get variation child items for AJAX request.
     */
    public function getVariationChildItems(Request $request): JsonResponse
    {
        $parentId = $request->parent_id;
        $companyId = session('company.company_id');
        
        // get purhase unit name and sale unit name
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
}

