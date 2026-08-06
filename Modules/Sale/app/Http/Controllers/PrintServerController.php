<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Sale\Services\PrintServerService;
use Modules\Sale\Services\ReceiptPrintService;

class PrintServerController extends Controller
{
    public function __construct(
        protected PrintServerService $printServerService,
        protected ReceiptPrintService $receiptPrintService
    ) {}

    /**
     * Called by POS/frontend: returns printer_server_url and content_data for the client to POST to the print server.
     */
    public function callPrintServer(Request $request)
    {
        $request->validate(['sale_id' => 'required|string']);

        $saleId = $request->input('sale_id');
        $contentData = $this->printServerService->buildInvoiceContentData($saleId);

        if (!$contentData) {
            return response()->json([
                'printer_server_url' => null,
                'content_data' => null,
                'print_type' => null,
            ]);
        }

        $printerId = getPrinterIdByRegisterID();
        $printer = $printerId ? getPrinterInfo($printerId) : null;
        $printServerUrlInvoice = $printer?->print_server_url_invoice ?? null;
        $printerServerUrl = $printServerUrlInvoice ? getIPv4WithFormat($printServerUrlInvoice) : '';

        return response()->json([
            'printer_server_url' => $printerServerUrl,
            'content_data' => $contentData,
            'print_type' => 'Invoice',
        ]);
    }

    /**
     * Print server endpoint: receives content_data and print_type from the client (or from Laravel app) and sends to printer.
     * Can be called from same origin (POS) or from external print server URL. Exclude from CSRF if needed for cross-origin.
     */
    public function receivePrint(Request $request)
    {
        $contentData = $request->input('content_data');
        $printType = $request->input('print_type', '');

        if (empty($contentData)) {
            return response()->json(['success' => false, 'message' => 'Missing content_data'], 400);
        }

        $data = is_string($contentData) ? json_decode($contentData) : $contentData;
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Invalid content_data'], 400);
        }

        try {
            if ($printType === 'Invoice') {
                $this->receiptPrintService->printReceipt($data);
            }
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('PrintServer receivePrint failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Print failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
