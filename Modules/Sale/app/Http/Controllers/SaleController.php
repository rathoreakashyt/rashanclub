<?php

namespace Modules\Sale\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Sale\Services\SaleService;

class SaleController extends Controller
{
    protected $saleService;

    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
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
            $data = $this->saleService->getDataTableData($params);
            return response()->json($data);
        }
        return view('sale::sale.index', compact('status'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $sale = $this->saleService->getSaleByEncryptedId($id);
        
        if (!$sale) {
            return redirect()->back()
                ->with('error', 'Sale not found');
        }
        
        // Load relationships (only live sale details)
        $sale->load([
            'customer', 
            'employee',
            'user',
            'company',
            'outlet',
            'saleDetails' => function($query) {
                $query->where('del_status', 'Live');
            },
            'saleDetails.item.parent', 
            'salePayments' => function($query) {
                $query->where('del_status', 'Live');
            },
            'salePayments.paymentMethod'
        ]);
        
        return view('sale::sale.show', compact('sale'));
    }

    /**
     * Print sale invoice (supports multiple formats: 56mm, 80mm, A4 Print, Half A4 Print, Letter Head)
     */
    public function printInvoice(string $id)
    {
        try {
            $sale = $this->saleService->getSaleByEncryptedId($id);

            if (!$sale) {
                return redirect()->back()
                    ->with('error', 'Sale not found');
            }

            // Load relationships (only live sale details and payments)
            $sale->load([
                'customer',
                'customer.state',
                'employee',
                'user',
                'company',
                'outlet',
                'outlet.state',
                'saleDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'saleDetails.item.parent',
                'salePayments' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salePayments.paymentMethod'
            ]);

            // Ensure Zatca QR code is generated if Zatca is enabled
            $this->ensureZatcaQRCode($sale);

            // Get invoice configuration from session
            $invoiceConfiguration = session('company.invoice_configuration');
            $invoiceFormat = 'A4 Print'; // Default format
            
            if ($invoiceConfiguration) {
                $invConfig = json_decode($invoiceConfiguration, true);
                if (isset($invConfig['invoice_format_or_size'])) {
                    $invoiceFormat = $invConfig['invoice_format_or_size'];
                }
            }

            

            // Return appropriate view based on invoice format (with no-cache headers to prevent wrong format on refresh)
            $viewName = match ($invoiceFormat) {
                '56mm' => 'sale::sale.56mm-invoice',
                '80mm' => 'sale::sale.80mm-invoice',
                'Half A4 Print' => 'sale::sale.a5-invoice',
                'Letter Head' => 'sale::sale.letterhead-invoice',
                default => 'sale::sale.a4-invoice',
            };

            return response()
                ->view($viewName, compact('sale'))
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Sale not found');
        }
    }

    /**
     * Print sale challan (delivery note without tax details)
     */
    public function printChallan(string $id)
    {
        try {
            $sale = $this->saleService->getSaleByEncryptedId($id);

            if (!$sale) {
                return redirect()->back()
                    ->with('error', 'Sale not found');
            }

            $sale->load([
                'customer',
                'customer.state',
                'employee',
                'outlet',
                'outlet.state',
                'saleDetails' => fn($q) => $q->where('del_status', 'Live'),
                'saleDetails.item.parent',
            ]);

            return response()
                ->view('sale::sale.challan', compact('sale'))
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Sale not found');
        }
    }

    /**
     * Generate PDF for sale (A4 format)
     */
    public function generatePdf(string $id)
    {
        try {
            $sale = $this->saleService->getSaleByEncryptedId($id);

            if (!$sale) {
                return redirect()->back()
                    ->with('error', 'Sale not found');
            }

            // Load relationships (only live sale details and payments)
            $sale->load([
                'customer',
                'customer.state',
                'employee',
                'user',
                'company',
                'outlet',
                'outlet.state',
                'saleDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'saleDetails.item.parent',
                'salePayments' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salePayments.paymentMethod'
            ]);

            // Ensure Zatca QR code is generated if Zatca is enabled
            $this->ensureZatcaQRCode($sale);

            // Render the view to HTML
            $html = view('sale::sale.a4-invoice-pdf', compact('sale'))->render();

            // Configure mPDF (80mm thermal width — same as desktop design)
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => [80, 300],
                'margin_left' => 2,
                'margin_right' => 2,
                'margin_top' => 3,
                'margin_bottom' => 3,
                'margin_header' => 0,
                'margin_footer' => 0,
                'auto_page_break' => true,
            ]);

            // Write HTML to PDF
            $mpdf->WriteHTML($html);

            // Generate filename
            $filename = 'Sale No - ' . $sale->sale_no . '.pdf';

            // Get PDF content as string
            $pdfContent = $mpdf->Output('', 'S');

            // Return PDF as download response
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Content-Length', strlen($pdfContent));
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error generating PDF: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     * Redirects to POS with edit_sale parameter so the sale is loaded into the cart for editing.
     */
    public function edit(string $id)
    {
        $sale = $this->saleService->getSaleByEncryptedId($id);

        if (!$sale) {
            return redirect()->back()
                ->with('error', 'Sale not found');
        }

        return redirect()->route('pos.index', ['edit_sale' => $id]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->saleService->deleteSale($id);
            
            return redirect()->back()
                ->with('success', 'Sale deleted successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Ensure Zatca QR code is generated for the sale if Zatca is enabled
     */
    private function ensureZatcaQRCode($sale)
    {
        try {
            // Check if QR code already exists
            if ($sale->zatca_phase1_qr_code) {
                return;
            }

            // Get company Zatca configuration
            $company = \Modules\Configuration\Models\Company::find($sale->company_id);
            if (!$company || !$company->zatca_configuration) {
                return;
            }

            $zatcaConfig = $company->zatca_configuration;
            $zatcaPhase = $zatcaConfig['zatca_phase'] ?? '0';

            // For backward compatibility
            if ($zatcaPhase == '0' || !isset($zatcaConfig['zatca_phase'])) {
                if (isset($zatcaConfig['zatca_1']) && $zatcaConfig['zatca_1'] == '1') {
                    $zatcaPhase = '1';
                } elseif (isset($zatcaConfig['zatca_2']) && $zatcaConfig['zatca_2'] == '1') {
                    $zatcaPhase = '2';
                }
            }

            // Generate Phase 1 QR code if enabled
            if ($zatcaPhase == '1') {
                $phase1Service = app(\Modules\Sale\Services\Zatca\ZatcaPhase1Service::class);
                $zatcaPhase1QrCode = $phase1Service->generateQRCodeJSON($sale, $zatcaConfig);
                
                if ($zatcaPhase1QrCode) {
                    $sale->update(['zatca_phase1_qr_code' => $zatcaPhase1QrCode]);
                }
            }
        } catch (\Exception $e) {
            // Silently fail - don't break invoice generation if QR code generation fails
            \Log::error('Failed to generate Zatca QR code for invoice', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
