<?php

namespace Modules\Sale\Services\Zatca;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * ZATCA API Client
 * Handles communication with ZATCA Sandbox and Production APIs
 */
class ZatcaApiClient
{
    protected $baseUrl;
    protected $environment; // 'sandbox' or 'production'
    protected $complianceCsid;
    protected $productionCsid;
    protected $secret;
    protected $timeout;

    public function __construct()
    {
        $this->environment = config('zatca.environment', 'sandbox');
        $this->baseUrl = $this->environment === 'production' 
            ? config('zatca.production_url', 'https://gw.gazt.gov.sa/e-invoicing/developer-portal/invoicing')
            : config('zatca.sandbox_url', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/invoicing');
        
        $this->complianceCsid = config('zatca.compliance_csid');
        $this->productionCsid = config('zatca.production_csid');
        $this->secret = config('zatca.secret');
        $this->timeout = config('zatca.timeout', 30);
    }

    /**
     * Request clearance for Standard Tax Invoice (B2B)
     * 
     * @param string $signedXml Signed UBL XML
     * @return array Response data
     * @throws \Exception
     */
    public function requestClearance(string $signedXml): array
    {
        $url = $this->baseUrl . '/invoices/clearance/single';
        
        $headers = $this->getHeaders();
        
        $startTime = microtime(true);
        
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($headers)
                ->withBody($signedXml, 'application/xml')
                ->post($url);
            
            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            
            $responseData = [
                'status_code' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers(),
                'response_time_ms' => (int)$responseTime,
            ];
            
            if ($response->successful()) {
                $responseData['success'] = true;
                $responseData['data'] = $this->parseResponse($response->body());
            } else {
                $responseData['success'] = false;
                $responseData['error'] = $this->parseError($response->body());
            }
            
            return $responseData;
            
        } catch (\Exception $e) {
            Log::error('ZATCA Clearance Request Failed', [
                'error' => $e->getMessage(),
                'url' => $url,
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \Exception('ZATCA API request failed: ' . $e->getMessage());
        }
    }

    /**
     * Report Simplified Tax Invoice (B2C)
     * 
     * @param string $signedXml Signed UBL XML
     * @return array Response data
     * @throws \Exception
     */
    public function reportInvoice(string $signedXml): array
    {
        $url = $this->baseUrl . '/invoices/reporting/single';
        
        $headers = $this->getHeaders();
        
        $startTime = microtime(true);
        
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($headers)
                ->withBody($signedXml, 'application/xml')
                ->post($url);
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            $responseData = [
                'status_code' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers(),
                'response_time_ms' => (int)$responseTime,
            ];
            
            if ($response->successful()) {
                $responseData['success'] = true;
                $responseData['data'] = $this->parseResponse($response->body());
            } else {
                $responseData['success'] = false;
                $responseData['error'] = $this->parseError($response->body());
            }
            
            return $responseData;
            
        } catch (\Exception $e) {
            Log::error('ZATCA Reporting Request Failed', [
                'error' => $e->getMessage(),
                'url' => $url,
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \Exception('ZATCA API request failed: ' . $e->getMessage());
        }
    }

    /**
     * Get authentication headers
     * 
     * @return array
     */
    protected function getHeaders(): array
    {
        $csid = $this->environment === 'production' ? $this->productionCsid : $this->complianceCsid;
        
        return [
            'Accept' => 'application/xml',
            'Content-Type' => 'application/xml',
            'Accept-Language' => 'en',
            'OTP' => $this->secret,
            'Clearance-Status' => '0', // 0 = Clearance, 1 = Reporting
        ];
    }

    /**
     * Parse successful response
     * 
     * @param string $body
     * @return array
     */
    protected function parseResponse(string $body): array
    {
        try {
            $xml = simplexml_load_string($body);
            $json = json_encode($xml);
            $data = json_decode($json, true);
            
            return [
                'validation_uuid' => $data['ValidationResults']['ValidationUUID'] ?? null,
                'clearing_status' => $data['ValidationResults']['ClearingStatus'] ?? null,
                'qr_sellertype_name' => $data['ValidationResults']['QrSellertypeName'] ?? null,
                'qr_sellertype_code' => $data['ValidationResults']['QrSellertypeCode'] ?? null,
                'invoice_hash' => $data['ValidationResults']['InvoiceHash'] ?? null,
                'invoice_qrcode' => $data['ValidationResults']['InvoiceQRCode'] ?? null,
                'cryptographic_stamp' => $data['ValidationResults']['CryptographicStamp'] ?? null,
                'packaging_authorized_serial_number' => $data['ValidationResults']['PackagingAuthorizedSerialNumber'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to parse ZATCA response', [
                'body' => $body,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Parse error response
     * 
     * @param string $body
     * @return array
     */
    protected function parseError(string $body): array
    {
        try {
            $xml = simplexml_load_string($body);
            $json = json_encode($xml);
            $data = json_decode($json, true);
            
            return [
                'error_code' => $data['Error']['Code'] ?? null,
                'error_message' => $data['Error']['Message'] ?? $body,
                'error_category' => $data['Error']['Category'] ?? null,
                'error_severity' => $data['Error']['Severity'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'error_message' => $body,
            ];
        }
    }

    /**
     * Generate QR Code data for invoice
     * Format: Base64 encoded string containing invoice data
     * 
     * @param array $invoiceData
     * @return string
     */
    public function generateQRCode(array $invoiceData): string
    {
        // QR Code format for ZATCA Phase 2:
        // Base64 encoded JSON containing:
        // - Seller Name
        // - VAT Number
        // - Invoice Date
        // - Invoice Total (with VAT)
        // - VAT Amount
        // - Cryptographic Stamp
        
        $qrData = [
            'seller_name' => $invoiceData['seller_name'] ?? '',
            'vat_number' => $invoiceData['vat_number'] ?? '',
            'invoice_date' => $invoiceData['invoice_date'] ?? '',
            'invoice_total' => $invoiceData['invoice_total'] ?? '0.00',
            'vat_amount' => $invoiceData['vat_amount'] ?? '0.00',
            'cryptographic_stamp' => $invoiceData['cryptographic_stamp'] ?? '',
        ];
        
        $json = json_encode($qrData);
        return base64_encode($json);
    }

    /**
     * Get base URL
     * 
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
