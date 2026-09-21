<?php

namespace Modules\Report\Services;

use Modules\Sale\Models\Sale;
use Modules\Sale\Models\SaleDetail;
use Modules\Configuration\Models\Outlet;
use Modules\Configuration\Models\State;

/**
 * GST Report Service - Calculates GST report data with correct tax breakdown
 * Handles Intra-State (CGST+SGST) vs Inter-State (IGST) classification
 */
class GstReportService
{
    /**
     * Parse sale_vat_objects and extract CGST, SGST, IGST amounts
     */
    public static function parseVatObjects(?string $saleVatObjects): array
    {
        $result = ['cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total_tax' => 0];
        if (empty($saleVatObjects)) {
            return $result;
        }
        $objs = json_decode($saleVatObjects, true);
        if (!is_array($objs)) {
            return $result;
        }
        foreach ($objs as $o) {
            $type = strtoupper($o['tax_field_type'] ?? '');
            $amt = (float) ($o['tax_field_amount'] ?? 0);
            $result['total_tax'] += $amt;
            if ($type === 'CGST') $result['cgst'] += $amt;
            elseif ($type === 'SGST') $result['sgst'] += $amt;
            elseif ($type === 'IGST') $result['igst'] += $amt;
        }
        return $result;
    }

    /**
     * Parse tax for a sale - uses sale_vat_objects when present, otherwise
     * falls back to the legacy "vat" column (split CGST/SGST half-half for
     * intra-state, all IGST for inter-state)
     */
    public static function parseSaleTax(Sale $sale): array
    {
        $tax = self::parseVatObjects($sale->sale_vat_objects ?? '');
        if ($tax['total_tax'] > 0) {
            return $tax;
        }
        $vatAmount = (float) ($sale->vat ?? 0);
        if ($vatAmount <= 0) {
            return $tax;
        }
        $outletCode = $sale->outlet?->state?->state_code ?? $sale->outlet?->state_code ?? null;
        $customerCode = $sale->customer?->state?->state_code ?? null;
        if (self::isIntraState($outletCode, $customerCode)) {
            $tax['cgst'] = round($vatAmount / 2, 2);
            $tax['sgst'] = round($vatAmount / 2, 2);
        } else {
            $tax['igst'] = $vatAmount;
        }
        $tax['total_tax'] = $vatAmount;
        return $tax;
    }

    /**
     * Determine if sale is Intra-State (same state) or Inter-State
     */
    public static function isIntraState(?string $outletStateCode, ?string $customerStateCode): bool
    {
        if ($customerStateCode === null || $outletStateCode === null) {
            return true; // Walk-in or no state = treat as intra
        }
        return $outletStateCode === $customerStateCode;
    }

    /**
     * 1. GST Tax Summary Report (Intra vs Inter State)
     */
    public static function getGstTaxSummaryReport(int $companyId, ?string $dateFrom, ?string $dateTo, ?int $outletId): array
    {
        $query = Sale::with(['outlet.state', 'customer.state'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->whereNotNull('sale_vat_objects')
                    ->where('sale_vat_objects', '!=', '[]')
                    ->where('sale_vat_objects', '!=', '')
                    ->orWhere('vat', '>', 0);
            });

        if ($dateFrom) $query->whereDate('sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sale_date', '<=', $dateTo);
        if ($outletId) $query->where('outlet_id', $outletId);

        $sales = $query->get();

        $intra = ['taxable_value' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total_tax' => 0, 'invoice_count' => 0];
        $inter = ['taxable_value' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total_tax' => 0, 'invoice_count' => 0];

        foreach ($sales as $sale) {
            $outletCode = $sale->outlet?->state?->state_code ?? $sale->outlet?->state_code ?? null;
            $customerCode = $sale->customer?->state?->state_code ?? null;
            $isIntra = self::isIntraState($outletCode, $customerCode);

            $tax = self::parseSaleTax($sale);
            $taxableValue = (float) ($sale->sub_total ?? 0);

            if ($isIntra) {
                $intra['taxable_value'] += $taxableValue;
                $intra['cgst'] += $tax['cgst'];
                $intra['sgst'] += $tax['sgst'];
                $intra['igst'] += $tax['igst'];
                $intra['total_tax'] += $tax['total_tax'];
                $intra['invoice_count']++;
            } else {
                $inter['taxable_value'] += $taxableValue;
                $inter['cgst'] += $tax['cgst'];
                $inter['sgst'] += $tax['sgst'];
                $inter['igst'] += $tax['igst'];
                $inter['total_tax'] += $tax['total_tax'];
                $inter['invoice_count']++;
            }
        }

        return [
            'rows' => [
                [
                    'tax_type' => __('Intra-State (Same State)'),
                    'taxable_value' => $intra['taxable_value'],
                    'cgst' => $intra['cgst'],
                    'sgst' => $intra['sgst'],
                    'igst' => 0,
                    'total_tax' => $intra['total_tax'],
                    'invoice_count' => $intra['invoice_count'],
                ],
                [
                    'tax_type' => __('Inter-State (Different State)'),
                    'taxable_value' => $inter['taxable_value'],
                    'cgst' => 0,
                    'sgst' => 0,
                    'igst' => $inter['igst'],
                    'total_tax' => $inter['total_tax'],
                    'invoice_count' => $inter['invoice_count'],
                ],
            ],
            'totals' => [
                'taxable_value' => $intra['taxable_value'] + $inter['taxable_value'],
                'cgst' => $intra['cgst'] + $inter['cgst'],
                'sgst' => $intra['sgst'] + $inter['sgst'],
                'igst' => $intra['igst'] + $inter['igst'],
                'total_tax' => $intra['total_tax'] + $inter['total_tax'],
                'invoice_count' => $intra['invoice_count'] + $inter['invoice_count'],
            ],
        ];
    }

    /**
     * 2. GST Tax Summary Report (Monthly / Date Wise)
     */
    public static function getGstMonthlySummaryReport(int $companyId, ?string $dateFrom, ?string $dateTo, ?int $outletId): array
    {
        $query = Sale::with(['outlet'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->whereNotNull('sale_vat_objects')
                    ->where('sale_vat_objects', '!=', '[]')
                    ->where('sale_vat_objects', '!=', '')
                    ->orWhere('vat', '>', 0);
            });

        if ($dateFrom) $query->whereDate('sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sale_date', '<=', $dateTo);
        if ($outletId) $query->where('outlet_id', $outletId);

        $sales = $query->get();

        $totalInvoiceCount = $sales->count();
        $totalTaxableValue = 0;
        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;

        foreach ($sales as $sale) {
            $totalTaxableValue += (float) ($sale->sub_total ?? 0);
            $tax = self::parseSaleTax($sale);
            $totalCgst += $tax['cgst'];
            $totalSgst += $tax['sgst'];
            $totalIgst += $tax['igst'];
        }

        $totalGstCollected = $totalCgst + $totalSgst + $totalIgst;
        $grandTotalSales = $totalTaxableValue + $totalGstCollected;

        return [
            'particulars' => [
                ['label' => __('Total Invoice Count'), 'amount' => $totalInvoiceCount],
                ['label' => __('Total Taxable Value'), 'amount' => $totalTaxableValue],
                ['label' => __('Total CGST'), 'amount' => $totalCgst],
                ['label' => __('Total SGST'), 'amount' => $totalSgst],
                ['label' => __('Total IGST'), 'amount' => $totalIgst],
                ['label' => __('Total GST Collected'), 'amount' => $totalGstCollected],
                ['label' => __('Grand Total Sales'), 'amount' => $grandTotalSales],
            ],
        ];
    }

    /**
     * 3. GST Rate Wise Summary Report
     * Groups by effective GST rate from menu_taxes in sale_details
     */
    public static function getGstRateWiseSummaryReport(int $companyId, ?string $dateFrom, ?string $dateTo, ?int $outletId): array
    {
        $saleQuery = Sale::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->whereNotNull('sale_vat_objects')
                    ->where('sale_vat_objects', '!=', '[]')
                    ->where('sale_vat_objects', '!=', '')
                    ->orWhere('vat', '>', 0);
            });

        if ($dateFrom) $saleQuery->whereDate('sale_date', '>=', $dateFrom);
        if ($dateTo) $saleQuery->whereDate('sale_date', '<=', $dateTo);
        if ($outletId) $saleQuery->where('outlet_id', $outletId);

        $saleIds = $saleQuery->pluck('id')->toArray();

        if (empty($saleIds)) {
            return ['rows' => [], 'totals' => ['taxable_value' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total_tax' => 0]];
        }

        $details = SaleDetail::with(['sale.outlet', 'sale.customer', 'item'])
            ->whereIn('sales_id', $saleIds)
            ->where('del_status', 'Live')
            ->get();

        $byRate = [];
        foreach ($details as $d) {
            $lineTotal = (float) $d->menu_price_with_discount * (float) $d->qty;
            $itemTaxAmount = (float) ($d->item_tax_amount ?? 0);
            $taxableValue = $lineTotal;
            if ($itemTaxAmount > 0) {
                $taxableValue = $lineTotal - $itemTaxAmount;
            }

            $menuTaxes = $d->menu_taxes;
            if (is_string($menuTaxes)) {
                $menuTaxes = json_decode($menuTaxes, true) ?: [];
            }
            $menuTaxes = is_array($menuTaxes) ? $menuTaxes : [];

            $cgst = 0;
            $sgst = 0;
            $igst = 0;
            $totalPct = 0;
            foreach ($menuTaxes as $t) {
                $name = strtoupper($t['tax_field_name'] ?? $t['tax_field_type'] ?? '');
                $amt = (float) ($t['tax_field_amount'] ?? 0);
                $pct = (float) ($t['tax_field_percentage'] ?? 0);
                if ($name === 'CGST') { $cgst += $amt; $totalPct += $pct; }
                elseif ($name === 'SGST') { $sgst += $amt; $totalPct += $pct; }
                elseif ($name === 'IGST') { $igst += $amt; $totalPct += $pct; }
            }
            $rateKey = $totalPct > 0 ? (string) round($totalPct, 0) : '0';
            if (!isset($byRate[$rateKey])) {
                $byRate[$rateKey] = ['taxable_value' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total_tax' => 0];
            }
            $byRate[$rateKey]['taxable_value'] += $taxableValue;
            $byRate[$rateKey]['cgst'] += $cgst;
            $byRate[$rateKey]['sgst'] += $sgst;
            $byRate[$rateKey]['igst'] += $igst;
            $byRate[$rateKey]['total_tax'] += $cgst + $sgst + $igst;
        }

        ksort($byRate, SORT_NUMERIC);
        $rows = [];
        foreach ($byRate as $rate => $data) {
            $rows[] = [
                'gst_percent' => $rate . '%',
                'taxable_value' => $data['taxable_value'],
                'cgst' => $data['cgst'],
                'sgst' => $data['sgst'],
                'igst' => $data['igst'],
                'total_tax' => $data['total_tax'],
            ];
        }

        $totals = ['taxable_value' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total_tax' => 0];
        foreach ($byRate as $d) {
            $totals['taxable_value'] += $d['taxable_value'];
            $totals['cgst'] += $d['cgst'];
            $totals['sgst'] += $d['sgst'];
            $totals['igst'] += $d['igst'];
            $totals['total_tax'] += $d['total_tax'];
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * 4. B2B Report (For GSTR-1 Filing) - Customer business_type = B2B
     */
    public static function getB2BReport(int $companyId, ?string $dateFrom, ?string $dateTo, ?int $outletId): array
    {
        $query = Sale::with(['outlet.state', 'customer.state'])
            ->whereHas('customer', fn ($q) => $q->where('business_type', 'B2B'))
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->whereNotNull('sale_vat_objects')
                    ->where('sale_vat_objects', '!=', '[]')
                    ->where('sale_vat_objects', '!=', '')
                    ->orWhere('vat', '>', 0);
            });

        if ($dateFrom) $query->whereDate('sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sale_date', '<=', $dateTo);
        if ($outletId) $query->where('outlet_id', $outletId);

        $sales = $query->orderBy('sale_date')->orderBy('id')->get();

        $rows = [];
        $totals = ['taxable_value' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total_invoice_value' => 0];

        foreach ($sales as $sale) {
            $tax = self::parseSaleTax($sale);
            $taxableValue = (float) ($sale->sub_total ?? 0);
            $totalInvoiceValue = (float) ($sale->total_payable ?? 0);

            $customer = $sale->customer;
            $stateCode = $customer && $customer->state ? $customer->state->state_code . ' (' . $customer->state->state_name . ')' : '-';
            $gstin = $customer && $customer->gst_number ? $customer->gst_number : 'Unregistered';

            $rows[] = [
                'invoice_no' => $sale->sale_no ?? '-',
                'invoice_date' => $sale->sale_date ? formatDate($sale->sale_date) : '-',
                'customer_name' => $customer ? $customer->name : '-',
                'gstin' => $gstin,
                'state_code' => $stateCode,
                'taxable_value' => $taxableValue,
                'cgst' => $tax['cgst'],
                'sgst' => $tax['sgst'],
                'igst' => $tax['igst'],
                'total_invoice_value' => $totalInvoiceValue,
            ];

            $totals['taxable_value'] += $taxableValue;
            $totals['cgst'] += $tax['cgst'];
            $totals['sgst'] += $tax['sgst'];
            $totals['igst'] += $tax['igst'];
            $totals['total_invoice_value'] += $totalInvoiceValue;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * 5. B2C Small (Consolidated State Wise) - Invoice value <= 2.5L, B2C
     */
    public static function getB2CSmallReport(int $companyId, ?string $dateFrom, ?string $dateTo, ?int $outletId): array
    {
        $b2cLimit = 250000;
        $query = Sale::with(['outlet.state', 'customer.state'])
            ->where(function ($q) {
                $q->whereDoesntHave('customer')
                    ->orWhereHas('customer', fn ($c) => $c->where('business_type', 'B2C'));
            })
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('total_payable', '<=', $b2cLimit)
            ->where(function ($q) {
                $q->whereNotNull('sale_vat_objects')
                    ->where('sale_vat_objects', '!=', '[]')
                    ->where('sale_vat_objects', '!=', '')
                    ->orWhere('vat', '>', 0);
            });

        if ($dateFrom) $query->whereDate('sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sale_date', '<=', $dateTo);
        if ($outletId) $query->where('outlet_id', $outletId);

        $sales = $query->orderBy('sale_date')->orderBy('id')->get();

        $rows = [];
        $totals = ['taxable_value' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total' => 0];

        foreach ($sales as $sale) {
            $tax = self::parseSaleTax($sale);
            $taxableValue = (float) ($sale->sub_total ?? 0);
            $totalVal = $taxableValue + $tax['cgst'] + $tax['sgst'] + $tax['igst'];

            $outletCode = $sale->outlet?->state?->state_code ?? $sale->outlet?->state_code ?? null;
            $customerCode = $sale->customer?->state?->state_code ?? null;
            $isIntra = self::isIntraState($outletCode, $customerCode);
            $stateLabel = $isIntra ? __('Local (State)') : ($sale->customer && $sale->customer->state ? $sale->customer->state->state_name : __('Inter-State'));

            $gstPct = $taxableValue > 0 ? round((($tax['cgst'] + $tax['sgst'] + $tax['igst']) / $taxableValue) * 100, 0) : 0;

            $rows[] = [
                'invoice_no' => $sale->sale_no ?? '-',
                'date' => $sale->sale_date ? formatDate($sale->sale_date) : '-',
                'state' => $stateLabel,
                'gst_percent' => $gstPct . '%',
                'taxable_value' => $taxableValue,
                'cgst' => $tax['cgst'],
                'sgst' => $tax['sgst'],
                'igst' => $tax['igst'],
                'total' => $totalVal,
            ];

            $totals['taxable_value'] += $taxableValue;
            $totals['cgst'] += $tax['cgst'];
            $totals['sgst'] += $tax['sgst'];
            $totals['igst'] += $tax['igst'];
            $totals['total'] += $totalVal;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * 6. B2C Large (Interstate > 2.5L)
     */
    public static function getB2CLargeReport(int $companyId, ?string $dateFrom, ?string $dateTo, ?int $outletId): array
    {
        $b2cLimit = 250000;
        $query = Sale::with(['outlet.state', 'customer.state'])
            ->where(function ($q) {
                $q->whereDoesntHave('customer')
                    ->orWhereHas('customer', fn ($c) => $c->where('business_type', 'B2C'));
            })
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('total_payable', '>', $b2cLimit)
            ->where(function ($q) {
                $q->whereNotNull('sale_vat_objects')
                    ->where('sale_vat_objects', '!=', '[]')
                    ->where('sale_vat_objects', '!=', '')
                    ->orWhere('vat', '>', 0);
            });

        if ($dateFrom) $query->whereDate('sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sale_date', '<=', $dateTo);
        if ($outletId) $query->where('outlet_id', $outletId);

        $sales = $query->orderBy('sale_date')->orderBy('id')->get();

        $rows = [];
        $totals = ['taxable_value' => 0, 'total_invoice_value' => 0];

        foreach ($sales as $sale) {
            $outletCode = $sale->outlet?->state?->state_code ?? $sale->outlet?->state_code ?? null;
            $customerCode = $sale->customer?->state?->state_code ?? null;
            if (self::isIntraState($outletCode, $customerCode)) {
                continue; // Only Inter-State for B2C Large
            }

            $taxableValue = (float) ($sale->sub_total ?? 0);
            $totalInvoiceValue = (float) ($sale->total_payable ?? 0);

            $rows[] = [
                'invoice_no' => $sale->sale_no ?? '-',
                'taxable_value' => $taxableValue,
                'total_invoice_value' => $totalInvoiceValue,
            ];

            $totals['taxable_value'] += $taxableValue;
            $totals['total_invoice_value'] += $totalInvoiceValue;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * 7. HSN Summary Report (Mandatory in GSTR-1)
     */
    public static function getHsnSummaryReport(int $companyId, ?string $dateFrom, ?string $dateTo, ?int $outletId): array
    {
        $saleQuery = Sale::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->whereNotNull('sale_vat_objects')
                    ->where('sale_vat_objects', '!=', '[]')
                    ->where('sale_vat_objects', '!=', '')
                    ->orWhere('vat', '>', 0);
            });

        if ($dateFrom) $saleQuery->whereDate('sale_date', '>=', $dateFrom);
        if ($dateTo) $saleQuery->whereDate('sale_date', '<=', $dateTo);
        if ($outletId) $saleQuery->where('outlet_id', $outletId);

        $saleIds = $saleQuery->pluck('id')->toArray();
        if (empty($saleIds)) {
            return ['rows' => [], 'totals' => ['total_qty' => 0, 'taxable_value' => 0]];
        }

        $details = SaleDetail::with('item')
            ->whereIn('sales_id', $saleIds)
            ->where('del_status', 'Live')
            ->get();

        $byHsn = [];
        foreach ($details as $d) {
            $hsnCode = $d->item && $d->item->hsn_code ? $d->item->hsn_code : '0000';
            $hsnName = $d->item && $d->item->hsn_code ? $d->item->hsn_code : '0000 (Others)';
            if ($d->item && $d->item->name) {
                $hsnName = $hsnCode . ' (' . $d->item->name . ')';
            }

            if (!isset($byHsn[$hsnCode])) {
                $byHsn[$hsnCode] = ['name' => $hsnName, 'total_qty' => 0, 'taxable_value' => 0];
            }
            $qty = (float) $d->qty;
            $lineTotal = (float) $d->menu_price_with_discount * $qty;
            $byHsn[$hsnCode]['total_qty'] += $qty;
            $byHsn[$hsnCode]['taxable_value'] += $lineTotal;
        }

        $rows = [];
        foreach ($byHsn as $code => $data) {
            $rows[] = [
                'hsn_code' => $data['name'],
                'total_qty' => $data['total_qty'],
                'taxable_value' => $data['taxable_value'],
            ];
        }

        $totals = ['total_qty' => 0, 'taxable_value' => 0];
        foreach ($byHsn as $d) {
            $totals['total_qty'] += $d['total_qty'];
            $totals['taxable_value'] += $d['taxable_value'];
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * 8. GSTR-3B Summary Format
     */
    public static function getGstr3bSummaryReport(int $companyId, ?string $dateFrom, ?string $dateTo, ?int $outletId): array
    {
        $query = Sale::with(['outlet'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);

        if ($dateFrom) $query->whereDate('sale_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('sale_date', '<=', $dateTo);
        if ($outletId) $query->where('outlet_id', $outletId);

        $sales = $query->get();

        $outwardTaxable = ['taxable_value' => 0, 'total_tax' => 0];
        $zeroRated = ['taxable_value' => 0, 'total_tax' => 0];
        $exempt = ['taxable_value' => 0, 'total_tax' => 0];

        foreach ($sales as $sale) {
            $taxableValue = (float) ($sale->sub_total ?? 0);
            $tax = self::parseSaleTax($sale);
            $totalTax = $tax['total_tax'];

            if ($totalTax > 0) {
                $outwardTaxable['taxable_value'] += $taxableValue;
                $outwardTaxable['total_tax'] += $totalTax;
            } elseif ($taxableValue > 0) {
                $exempt['taxable_value'] += $taxableValue;
            }
        }

        $rows = [
            ['nature' => __('Outward Taxable Supplies'), 'taxable_value' => $outwardTaxable['taxable_value'], 'total_tax' => $outwardTaxable['total_tax']],
            ['nature' => __('Zero Rated Supplies (Exports)'), 'taxable_value' => $zeroRated['taxable_value'], 'total_tax' => 0],
            ['nature' => __('Exempt / Nil Rated Supplies'), 'taxable_value' => $exempt['taxable_value'], 'total_tax' => 0],
        ];

        $totals = [
            'taxable_value' => $outwardTaxable['taxable_value'] + $zeroRated['taxable_value'] + $exempt['taxable_value'],
            'total_tax' => $outwardTaxable['total_tax'],
        ];

        return ['rows' => $rows, 'totals' => $totals];
    }
}
