<?php

namespace Modules\Sale\Services\Zatca;

use Modules\Sale\Models\Sale;
use Modules\Configuration\Models\Company;
use Illuminate\Support\Facades\Log;

/**
 * ZATCA Phase 1 Service
 * Generates simple QR codes for invoices without API submission
 */
class ZatcaPhase1Service
{
    /**
     * Generate Phase 1 QR code for invoice
     * 
     * Phase 1 QR code format (TLV - Tag Length Value):
     * Tag 1: Seller Name
     * Tag 2: VAT Registration Number
     * Tag 3: Invoice Date (YYYY-MM-DDTHH:mm:ssZ)
     * Tag 4: Invoice Total (with VAT)
     * Tag 5: VAT Amount
     * 
     * @param Sale $sale
     * @param array $zatcaConfig
     * @return string Base64 encoded QR code data
     */
    public function generateQRCode(Sale $sale, array $zatcaConfig): string
    {
        try {
            $company = Company::find($sale->company_id);
            
            // Get seller information from company
            $sellerName = $zatcaConfig['legal_business_name_arabic'] ?? 
                         $zatcaConfig['legal_business_name_english'] ?? 
                         $company->business_name ?? 
                         '';
            
            $vatNumber = $zatcaConfig['vat_registration_number'] ?? 
                        $company->tax_registration_no ?? 
                        '';
            
            // Format invoice date (ISO 8601 format)
            $invoiceDate = $sale->date_time 
                ? $sale->date_time->format('Y-m-d\TH:i:s\Z') 
                : $sale->sale_date->format('Y-m-d\TH:i:s\Z');
            
            // Invoice total with VAT
            $invoiceTotal = number_format((float)$sale->grand_total, 2, '.', '');
            
            // VAT amount
            $vatAmount = number_format((float)$sale->vat, 2, '.', '');
            
            // Build TLV structure
            $tlvData = $this->buildTLV([
                1 => $sellerName,
                2 => $vatNumber,
                3 => $invoiceDate,
                4 => $invoiceTotal,
                5 => $vatAmount,
            ]);
            
            // Return base64 encoded TLV data
            return base64_encode($tlvData);
            
        } catch (\Exception $e) {
            Log::error('ZATCA Phase 1 QR Code Generation Failed', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return empty string on error
            return '';
        }
    }
    
    /**
     * Build TLV (Tag Length Value) structure
     * 
     * @param array $data Array of tag => value pairs
     * @return string Binary TLV data
     */
    protected function buildTLV(array $data): string
    {
        $tlv = '';
        
        foreach ($data as $tag => $value) {
            // Convert tag to 1-byte binary
            $tagByte = chr($tag);
            
            // Get value length
            $valueLength = strlen($value);
            
            // Convert length to 2-byte binary (big-endian)
            $lengthBytes = pack('n', $valueLength);
            
            // Append: Tag (1 byte) + Length (2 bytes) + Value
            $tlv .= $tagByte . $lengthBytes . $value;
        }
        
        return $tlv;
    }
    
    /**
     * Generate QR code data in JSON format (alternative simpler format)
     * This is a simpler approach that doesn't require TLV encoding
     * 
     * @param Sale $sale
     * @param array $zatcaConfig
     * @return string Base64 encoded JSON
     */
    public function generateQRCodeJSON(Sale $sale, array $zatcaConfig): string
    {
        try {
            $company = Company::find($sale->company_id);
            
            // Get seller information
            $sellerName = $zatcaConfig['legal_business_name_arabic'] ?? 
                         $zatcaConfig['legal_business_name_english'] ?? 
                         $company->business_name ?? 
                         '';
            
            $vatNumber = $zatcaConfig['vat_registration_number'] ?? 
                        $company->tax_registration_no ?? 
                        '';
            
            // Format invoice date
            $invoiceDate = $sale->date_time 
                ? $sale->date_time->format('Y-m-d\TH:i:s\Z') 
                : $sale->sale_date->format('Y-m-d\TH:i:s\Z');
            
            // Build QR code data structure
            $qrData = [
                'seller_name' => $sellerName,
                'vat_number' => $vatNumber,
                'invoice_date' => $invoiceDate,
                'invoice_total' => number_format((float)$sale->grand_total, 2, '.', ''),
                'vat_amount' => number_format((float)$sale->vat, 2, '.', ''),
            ];
            
            // Encode as JSON and then base64
            $json = json_encode($qrData, JSON_UNESCAPED_UNICODE);
            return base64_encode($json);
            
        } catch (\Exception $e) {
            Log::error('ZATCA Phase 1 QR Code (JSON) Generation Failed', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return '';
        }
    }
}
