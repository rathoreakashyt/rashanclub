<?php

namespace Modules\Sale\Services\Zatca;

use Modules\Sale\Models\Sale;
use Modules\Sale\Models\ZatcaInvoice;
use Modules\Sale\Models\ZatcaRequest;
use Modules\Sale\Models\InvoiceHashChain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Main ZATCA Service
 * Orchestrates invoice generation, signing, and submission to ZATCA
 */
class ZatcaService
{
    protected $hashService;
    protected $signatureService;
    protected $ublBuilder;
    protected $apiClient;

    public function __construct(
        HashService $hashService,
        SignatureService $signatureService,
        UblBuilder $ublBuilder,
        ZatcaApiClient $apiClient
    ) {
        $this->hashService = $hashService;
        $this->signatureService = $signatureService;
        $this->ublBuilder = $ublBuilder;
        $this->apiClient = $apiClient;
    }

    /**
     * Process invoice for ZATCA compliance
     * 
     * @param Sale $sale
     * @param bool $isOffline Whether this is an offline invoice
     * @return ZatcaInvoice
     * @throws \Exception
     */
    public function processInvoice(Sale $sale, bool $isOffline = false): ZatcaInvoice
    {
        // Start transaction for invoice creation
        DB::beginTransaction();
        
        try {
            // Determine invoice type (B2B = standard, B2C = simplified)
            $invoiceType = $this->determineInvoiceType($sale);
            
            // Generate UUID
            $uuid = $this->generateUuid();
            
            // Build UBL XML
            $ublBuilder = new UblBuilder($sale, $invoiceType);
            $ublXml = $ublBuilder->build($uuid);
            
            // Generate invoice hash
            $invoiceHash = $this->hashService->generateHash($ublXml);
            
            // Get previous invoice hash for chain
            $previousHash = $this->hashService->getPreviousInvoiceHash(
                $sale->company_id,
                $sale->outlet_id
            );
            
            // Generate combined hash
            $combinedHash = $this->hashService->generateInvoiceHash($previousHash, $uuid, $invoiceHash);
            
            // Sign XML
            $zatcaConfig = Company::find($sale->company_id)->zatca_configuration;
            
            $privateKeyPath = $zatcaConfig['zatca_secret_key'];
            $certificatePath = $zatcaConfig['compliance_csid'];
            
            if (!$privateKeyPath || !$certificatePath) {
                throw new \Exception('ZATCA private key or certificate not configured. Check your Zatca Phase 2 Configuration.');
            }
            
            $signedXml = $this->signatureService->signXml($ublXml, $privateKeyPath, $certificatePath);
            
            // Create ZATCA invoice record
            $zatcaInvoice = ZatcaInvoice::create([
                'sale_id' => $sale->id,
                'company_id' => $sale->company_id,
                'outlet_id' => $sale->outlet_id,
                'invoice_type' => $invoiceType,
                'uuid' => $uuid,
                'invoice_hash' => $combinedHash,
                'previous_invoice_hash' => $previousHash,
                'ubl_xml' => $ublXml,
                'signed_xml' => $signedXml,
                'zatca_status' => $isOffline ? 'pending' : 'pending',
                'is_offline' => $isOffline,
                'queued_at' => $isOffline ? now() : null,
            ]);
            
            // Save hash chain
            $chainIndex = InvoiceHashChain::where('company_id', $sale->company_id)
                ->where('outlet_id', $sale->outlet_id)
                ->max('chain_index') ?? -1;
            
            InvoiceHashChain::create([
                'company_id' => $sale->company_id,
                'outlet_id' => $sale->outlet_id,
                'previous_hash' => $previousHash,
                'current_hash' => $combinedHash,
                'zatca_invoice_id' => $zatcaInvoice->id,
                'chain_index' => $chainIndex + 1,
            ]);
            
            // Update sale record
            $sale->update([
                'zatca_compliant' => true,
                'zatca_invoice_id' => $zatcaInvoice->id,
            ]);
            
            DB::commit();
            
            // If not offline, submit to ZATCA immediately (outside transaction to avoid blocking)
            // This ensures the sale is saved even if ZATCA API is slow or fails
            if (!$isOffline) {
                try {
                    $this->submitToZatca($zatcaInvoice);
                } catch (\Exception $e) {
                    // Log but don't fail - invoice record is already created
                    Log::warning('ZATCA submission failed after invoice creation', [
                        'zatca_invoice_id' => $zatcaInvoice->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            return $zatcaInvoice;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ZATCA Invoice Processing Failed', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Submit invoice to ZATCA for clearance/reporting
     * 
     * @param ZatcaInvoice $zatcaInvoice
     * @return bool
     */
    public function submitToZatca(ZatcaInvoice $zatcaInvoice): bool
    {
        try {
            $requestType = $zatcaInvoice->invoice_type === 'standard' ? 'clearance' : 'reporting';
            
            // Create request record
            $zatcaRequest = ZatcaRequest::create([
                'zatca_invoice_id' => $zatcaInvoice->id,
                'company_id' => $zatcaInvoice->company_id,
                'request_type' => $requestType,
                'request_payload' => $zatcaInvoice->signed_xml,
                'request_url' => $this->apiClient->getBaseUrl() . ($requestType === 'clearance' ? '/invoices/clearance/single' : '/invoices/reporting/single'),
                'request_method' => 'POST',
                'status' => 'pending',
                'requested_at' => now(),
            ]);
            
            // Submit to ZATCA API
            if ($requestType === 'clearance') {
                $response = $this->apiClient->requestClearance($zatcaInvoice->signed_xml);
            } else {
                $response = $this->apiClient->reportInvoice($zatcaInvoice->signed_xml);
            }
            
            // Update request record
            $zatcaRequest->update([
                'response_status_code' => $response['status_code'],
                'response_body' => $response['body'],
                'response_headers' => $response['headers'],
                'response_time_ms' => $response['response_time_ms'],
                'responded_at' => now(),
                'status' => $response['success'] ? 'success' : 'failed',
                'error_message' => $response['error']['error_message'] ?? null,
                'error_code' => $response['error']['error_code'] ?? null,
            ]);
            
            if ($response['success']) {
                // Update ZATCA invoice with response data
                $zatcaInvoice->update([
                    'zatca_status' => $requestType === 'clearance' ? 'cleared' : 'reported',
                    'cryptographic_stamp' => $response['data']['cryptographic_stamp'] ?? null,
                    'packaging_authorized_serial_number' => $response['data']['packaging_authorized_serial_number'] ?? null,
                    'qr_code' => $response['data']['invoice_qrcode'] ?? null,
                    'cleared_at' => $requestType === 'clearance' ? now() : null,
                    'reported_at' => $requestType === 'reporting' ? now() : null,
                    'is_offline' => false,
                    'queued_at' => null,
                ]);
                
                return true;
            } else {
                // Handle error
                $zatcaInvoice->update([
                    'zatca_status' => 'failed',
                    'zatca_error' => $response['error']['error_message'] ?? 'Unknown error',
                    'failed_at' => now(),
                    'retry_count' => $zatcaInvoice->retry_count + 1,
                    'last_retry_at' => now(),
                ]);
                
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('ZATCA Submission Failed', [
                'zatca_invoice_id' => $zatcaInvoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $zatcaInvoice->update([
                'zatca_status' => 'failed',
                'zatca_error' => $e->getMessage(),
                'failed_at' => now(),
                'retry_count' => $zatcaInvoice->retry_count + 1,
                'last_retry_at' => now(),
            ]);
            
            return false;
        }
    }

    /**
     * Determine invoice type based on customer
     * 
     * @param Sale $sale
     * @return string 'standard' or 'simplified'
     */
    protected function determineInvoiceType(Sale $sale): string
    {
        // Standard (B2B) if customer has VAT number
        if ($sale->customer && ($sale->customer->vat_number || $sale->customer->tax_number)) {
            return 'standard';
        }
        
        // Simplified (B2C) for walk-in customers or customers without VAT
        return 'simplified';
    }

    /**
     * Generate UUID v4
     * 
     * @return string
     */
    protected function generateUuid(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Retry failed invoice submission
     * 
     * @param ZatcaInvoice $zatcaInvoice
     * @param int $maxRetries
     * @return bool
     */
    public function retrySubmission(ZatcaInvoice $zatcaInvoice, int $maxRetries = 3): bool
    {
        if ($zatcaInvoice->retry_count >= $maxRetries) {
            Log::warning('ZATCA Invoice exceeded max retries', [
                'zatca_invoice_id' => $zatcaInvoice->id,
                'retry_count' => $zatcaInvoice->retry_count
            ]);
            return false;
        }
        
        return $this->submitToZatca($zatcaInvoice);
    }

    /**
     * Process queued offline invoices
     * 
     * @param int $limit
     * @return int Number of processed invoices
     */
    public function processQueuedInvoices(int $limit = 50): int
    {
        $queuedInvoices = ZatcaInvoice::where('is_offline', true)
            ->where('zatca_status', 'pending')
            ->whereNotNull('queued_at')
            ->limit($limit)
            ->get();
        
        $processed = 0;
        
        foreach ($queuedInvoices as $invoice) {
            try {
                if ($this->submitToZatca($invoice)) {
                    $processed++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to process queued invoice', [
                    'zatca_invoice_id' => $invoice->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $processed;
    }
}
