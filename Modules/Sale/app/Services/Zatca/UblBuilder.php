<?php

namespace Modules\Sale\Services\Zatca;

use Modules\Sale\Models\Sale;
use Modules\Sale\Models\SaleDetail;
use Modules\Configuration\Models\Company;
use Modules\Configuration\Models\Outlet;
use Modules\Sale\Models\Customer;
use Carbon\Carbon;

/**
 * UBL 2.1 XML Builder for ZATCA Phase 2
 * Generates compliant UBL 2.1 XML invoices
 */
class UblBuilder
{
    protected $sale;
    protected $company;
    protected $outlet;
    protected $customer;
    protected $invoiceType;

    public function __construct(Sale $sale, string $invoiceType = 'simplified')
    {
        $this->sale = $sale;
        $this->invoiceType = $invoiceType; // 'standard' or 'simplified'
        $this->company = $sale->company ?? Company::find($sale->company_id);
        $this->outlet = $sale->outlet ?? ($sale->outlet_id ? Outlet::find($sale->outlet_id) : null);
        $this->customer = $sale->customer ?? ($sale->customer_id ? Customer::find($sale->customer_id) : null);
    }

    /**
     * Build UBL 2.1 XML document
     * 
     * @param string $uuid Invoice UUID
     * @return string UBL XML
     */
    public function build(string $uuid): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"></Invoice>');
        
        // Add namespaces
        $xml->addAttribute('xmlns:ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $xml->addAttribute('xmlns:qdt', 'urn:oasis:names:specification:ubl:schema:xsd:QualifiedDatatypes-2');
        
        // cbc:CustomizationID - Invoice Type
        $xml->addChild('cbc:CustomizationID', $this->invoiceType === 'standard' ? 'urn:cen.eu:en16931:2017#compliant#urn:customization:sa:gcc:invoice:v1.0' : 'urn:cen.eu:en16931:2017#compliant#urn:customization:sa:gcc:simplified-invoice:v1.0');
        
        // cbc:ProfileID
        $xml->addChild('cbc:ProfileID', $this->invoiceType === 'standard' ? 'reporting:1.0' : 'reporting:1.0');
        
        // cbc:ID - Invoice Number
        $xml->addChild('cbc:ID', $this->sale->sale_no);
        
        // cbc:UUID - Invoice UUID
        $xml->addChild('cbc:UUID', $uuid);
        
        // cbc:IssueDate - Invoice Date
        $xml->addChild('cbc:IssueDate', $this->sale->sale_date->format('Y-m-d'));
        
        // cbc:IssueTime - Invoice Time
        $xml->addChild('cbc:IssueTime', $this->sale->date_time ? $this->sale->date_time->format('H:i:s') : now()->format('H:i:s'));
        
        // cbc:InvoiceTypeCode - Invoice Type Code
        $invoiceTypeCode = $xml->addChild('cbc:InvoiceTypeCode');
        $invoiceTypeCode->addAttribute('listID', 'UN/ECE 1001');
        $invoiceTypeCode->addAttribute('listAgencyID', '6');
        $invoiceTypeCode[0] = $this->invoiceType === 'standard' ? '388' : '383'; // 388 = Tax Invoice, 383 = Simplified Tax Invoice
        
        // cbc:DocumentCurrencyCode - Currency (SAR)
        $xml->addChild('cbc:DocumentCurrencyCode', 'SAR');
        
        // Add Seller (Supplier) Party
        $this->addSellerParty($xml);
        
        // Add Buyer (Customer) Party
        $this->addBuyerParty($xml);
        
        // Add Tax Total
        $this->addTaxTotal($xml);
        
        // Add Legal Monetary Total
        $this->addLegalMonetaryTotal($xml);
        
        // Add Invoice Lines
        $this->addInvoiceLines($xml);
        
        // Convert to formatted XML string
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml->asXML());
        
        return $dom->saveXML();
    }

    /**
     * Add Seller (Supplier) Party Information
     */
    protected function addSellerParty(\SimpleXMLElement $xml): void
    {
        $accountingSupplierParty = $xml->addChild('cac:AccountingSupplierParty');
        $party = $accountingSupplierParty->addChild('cac:Party');
        
        // Party Identification
        $partyIdentification = $party->addChild('cac:PartyIdentification');
        $partyIdentification->addChild('cbc:ID', $this->company->vat_number ?? $this->company->tax_number ?? '');
        
        // Party Name
        $partyName = $party->addChild('cac:PartyName');
        $partyName->addChild('cbc:Name', htmlspecialchars($this->company->name ?? ''));
        
        // Postal Address
        $postalAddress = $party->addChild('cac:PostalAddress');
        $postalAddress->addChild('cbc:StreetName', htmlspecialchars($this->company->address ?? ''));
        $postalAddress->addChild('cbc:CityName', htmlspecialchars($this->company->city ?? ''));
        $postalAddress->addChild('cbc:PostalZone', $this->company->postal_code ?? '');
        $country = $postalAddress->addChild('cac:Country');
        $country->addChild('cbc:IdentificationCode', 'SA');
        
        // Party Tax Scheme (VAT)
        $partyTaxScheme = $party->addChild('cac:PartyTaxScheme');
        $taxScheme = $partyTaxScheme->addChild('cac:TaxScheme');
        $taxScheme->addChild('cbc:ID', 'VAT');
        $partyTaxScheme->addChild('cbc:CompanyID', $this->company->vat_number ?? $this->company->tax_number ?? '');
        
        // Party Legal Entity
        $partyLegalEntity = $party->addChild('cac:PartyLegalEntity');
        $partyLegalEntity->addChild('cbc:RegistrationName', htmlspecialchars($this->company->name ?? ''));
        
        // Contact
        $contact = $party->addChild('cac:Contact');
        $contact->addChild('cbc:Telephone', $this->company->phone ?? '');
        $contact->addChild('cbc:ElectronicMail', $this->company->email ?? '');
    }

    /**
     * Add Buyer (Customer) Party Information
     */
    protected function addBuyerParty(\SimpleXMLElement $xml): void
    {
        $accountingCustomerParty = $xml->addChild('cac:AccountingCustomerParty');
        $party = $accountingCustomerParty->addChild('cac:Party');
        
        if ($this->customer) {
            // Party Identification
            $partyIdentification = $party->addChild('cac:PartyIdentification');
            $partyIdentification->addChild('cbc:ID', $this->customer->vat_number ?? $this->customer->tax_number ?? $this->customer->id);
            
            // Party Name
            $partyName = $party->addChild('cac:PartyName');
            $partyName->addChild('cbc:Name', htmlspecialchars($this->customer->name ?? 'Walk-in Customer'));
            
            // Postal Address (if available)
            if ($this->customer->address) {
                $postalAddress = $party->addChild('cac:PostalAddress');
                $postalAddress->addChild('cbc:StreetName', htmlspecialchars($this->customer->address ?? ''));
                $postalAddress->addChild('cbc:CityName', htmlspecialchars($this->customer->city ?? ''));
                $postalAddress->addChild('cbc:PostalZone', $this->customer->postal_code ?? '');
                $country = $postalAddress->addChild('cac:Country');
                $country->addChild('cbc:IdentificationCode', 'SA');
            }
            
            // Party Tax Scheme (if B2B)
            if ($this->invoiceType === 'standard' && ($this->customer->vat_number || $this->customer->tax_number)) {
                $partyTaxScheme = $party->addChild('cac:PartyTaxScheme');
                $taxScheme = $partyTaxScheme->addChild('cac:TaxScheme');
                $taxScheme->addChild('cbc:ID', 'VAT');
                $partyTaxScheme->addChild('cbc:CompanyID', $this->customer->vat_number ?? $this->customer->tax_number);
            }
        } else {
            // Walk-in customer
            $partyIdentification = $party->addChild('cac:PartyIdentification');
            $partyIdentification->addChild('cbc:ID', 'WALK-IN');
            $partyName = $party->addChild('cac:PartyName');
            $partyName->addChild('cbc:Name', 'Walk-in Customer');
        }
    }

    /**
     * Add Tax Total
     */
    protected function addTaxTotal(\SimpleXMLElement $xml): void
    {
        $taxTotal = $xml->addChild('cac:TaxTotal');
        
        // Tax Amount
        $taxAmount = $taxTotal->addChild('cbc:TaxAmount');
        $taxAmount->addAttribute('currencyID', 'SAR');
        $taxAmount[0] = number_format($this->sale->vat ?? 0, 2, '.', '');
        
        // Tax Subtotal
        $taxSubtotal = $taxTotal->addChild('cac:TaxSubtotal');
        
        // Taxable Amount
        $taxableAmount = $taxSubtotal->addChild('cbc:TaxableAmount');
        $taxableAmount->addAttribute('currencyID', 'SAR');
        $taxableAmount[0] = number_format($this->sale->sub_total_with_discount ?? $this->sale->sub_total ?? 0, 2, '.', '');
        
        // Tax Amount
        $taxSubtotalAmount = $taxSubtotal->addChild('cbc:TaxAmount');
        $taxSubtotalAmount->addAttribute('currencyID', 'SAR');
        $taxSubtotalAmount[0] = number_format($this->sale->vat ?? 0, 2, '.', '');
        
        // Tax Category
        $taxCategory = $taxSubtotal->addChild('cac:TaxCategory');
        $taxCategory->addChild('cbc:ID', 'S');
        $taxCategory->addChild('cbc:Percent', '15'); // Standard VAT rate in Saudi Arabia
        $taxScheme = $taxCategory->addChild('cac:TaxScheme');
        $taxScheme->addChild('cbc:ID', 'VAT');
    }

    /**
     * Add Legal Monetary Total
     */
    protected function addLegalMonetaryTotal(\SimpleXMLElement $xml): void
    {
        $legalMonetaryTotal = $xml->addChild('cac:LegalMonetaryTotal');
        
        // Line Extension Amount
        $lineExtensionAmount = $legalMonetaryTotal->addChild('cbc:LineExtensionAmount');
        $lineExtensionAmount->addAttribute('currencyID', 'SAR');
        $lineExtensionAmount[0] = number_format($this->sale->sub_total_with_discount ?? $this->sale->sub_total ?? 0, 2, '.', '');
        
        // Tax Exclusive Amount
        $taxExclusiveAmount = $legalMonetaryTotal->addChild('cbc:TaxExclusiveAmount');
        $taxExclusiveAmount->addAttribute('currencyID', 'SAR');
        $taxExclusiveAmount[0] = number_format($this->sale->sub_total_with_discount ?? $this->sale->sub_total ?? 0, 2, '.', '');
        
        // Tax Inclusive Amount
        $taxInclusiveAmount = $legalMonetaryTotal->addChild('cbc:TaxInclusiveAmount');
        $taxInclusiveAmount->addAttribute('currencyID', 'SAR');
        $taxInclusiveAmount[0] = number_format($this->sale->grand_total ?? $this->sale->total_payable ?? 0, 2, '.', '');
        
        // Payable Amount
        $payableAmount = $legalMonetaryTotal->addChild('cbc:PayableAmount');
        $payableAmount->addAttribute('currencyID', 'SAR');
        $payableAmount[0] = number_format($this->sale->grand_total ?? $this->sale->total_payable ?? 0, 2, '.', '');
    }

    /**
     * Add Invoice Lines
     */
    protected function addInvoiceLines(\SimpleXMLElement $xml): void
    {
        $saleDetails = SaleDetail::where('sales_id', $this->sale->id)
            ->where('del_status', 'Live')
            ->get();
        
        foreach ($saleDetails as $index => $detail) {
            $invoiceLine = $xml->addChild('cac:InvoiceLine');
            
            // ID
            $invoiceLine->addChild('cbc:ID', (string)($index + 1));
            
            // Invoiced Quantity
            $invoicedQuantity = $invoiceLine->addChild('cbc:InvoicedQuantity');
            $invoicedQuantity->addAttribute('unitCode', 'C62'); // C62 = unit
            $invoicedQuantity[0] = number_format($detail->qty ?? 1, 3, '.', '');
            
            // Line Extension Amount
            $lineExtensionAmount = $invoiceLine->addChild('cbc:LineExtensionAmount');
            $lineExtensionAmount->addAttribute('currencyID', 'SAR');
            $lineExtensionAmount[0] = number_format(($detail->menu_price_with_discount ?? $detail->menu_unit_price ?? 0) * ($detail->qty ?? 1), 2, '.', '');
            
            // Item
            $item = $invoiceLine->addChild('cac:Item');
            $item->addChild('cbc:Name', htmlspecialchars($detail->item->name ?? 'Item'));
            $item->addChild('cbc:Description', htmlspecialchars($detail->item->description ?? ''));
            
            // Classified Tax Category
            $classifiedTaxCategory = $item->addChild('cac:ClassifiedTaxCategory');
            $classifiedTaxCategory->addChild('cbc:ID', 'S');
            $classifiedTaxCategory->addChild('cbc:Percent', '15');
            $taxScheme = $classifiedTaxCategory->addChild('cac:TaxScheme');
            $taxScheme->addChild('cbc:ID', 'VAT');
            
            // Price
            $price = $invoiceLine->addChild('cac:Price');
            $priceAmount = $price->addChild('cbc:PriceAmount');
            $priceAmount->addAttribute('currencyID', 'SAR');
            $priceAmount[0] = number_format($detail->menu_unit_price ?? 0, 2, '.', '');
        }
    }
}
