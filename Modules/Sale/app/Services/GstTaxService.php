<?php

namespace Modules\Sale\Services;

use Modules\Configuration\Models\Tax;
use Modules\Configuration\Models\State;
use Modules\Configuration\Models\Outlet;
use Modules\Sale\Models\Customer;

/**
 * Indian GST Tax Calculation Service
 * Handles Intra-State (CGST+SGST) and Inter-State (IGST) logic
 * Supports Exclusive and Inclusive tax types
 */
class GstTaxService
{
    /**
     * Calculate tax for a single item based on applicable taxes, tax type, and state
     *
     * @param float $priceAfterDiscount Price per unit after item discount
     * @param float $quantity Quantity
     * @param string|null $applicableTaxIds Comma-separated tax IDs (e.g. "4,5")
     * @param string $taxType 'Exclusive' or 'Inclusive'
     * @param string|null $outletStateCode Outlet/seller state code (e.g. "27")
     * @param int|null $customerStateId Customer state ID (from states table)
     * @param array $taxsMap Map of tax_id => Tax model (id, tax_name, tax_rate, parent_tax_id)
     * @return array ['taxable_value' => float, 'total_tax' => float, 'breakdown' => [...], 'cgst' => float, 'sgst' => float, 'igst' => float]
     */
    public static function calculateItemTax(
        float $priceAfterDiscount,
        float $quantity,
        ?string $applicableTaxIds,
        string $taxType,
        ?string $outletStateCode,
        ?int $customerStateId,
        array $taxsMap
    ): array {
        $result = [
            'taxable_value' => 0,
            'total_tax' => 0,
            'breakdown' => [],
            'cgst' => 0,
            'sgst' => 0,
            'igst' => 0,
        ];

        if (empty($applicableTaxIds) || empty($taxsMap)) {
            $result['taxable_value'] = round($priceAfterDiscount * $quantity, 2);
            return $result;
        }

        $taxIds = array_filter(array_map('trim', explode(',', $applicableTaxIds)));
        $customerStateCode = $customerStateId ? (State::find($customerStateId)?->state_code ?? null) : null;

        // Intra-State: Walk-in (null customer state), or outlet has no state, or same state
        // Inter-State: Customer state differs from outlet state
        $isIntraState = ($customerStateCode === null) || ($outletStateCode === null) || ($customerStateCode === $outletStateCode);

        $lineTotal = $priceAfterDiscount * $quantity;

        $totalGstRate = 0;

        foreach ($taxIds as $taxId) {
            $tax = $taxsMap[$taxId] ?? null;
            if (!$tax) continue;

            $taxData = is_object($tax) ? (array) $tax : $tax;
            $taxName = $taxData['tax_name'] ?? '';
            $taxRate = (float) ($taxData['tax_rate'] ?? 0);
            $parentId = $taxData['parent_tax_id'] ?? null;

            // GST parent (e.g. "GST", "GST 5%", "GST 12%", "GST 18%"): resolve from children
            // Intra-State: CGST + SGST | Inter-State: IGST
            $isGstParent = (str_starts_with(strtoupper($taxName), 'GST')) && $parentId === null;
            if ($isGstParent) {
                foreach ($taxsMap as $childId => $childTax) {
                    $cData = is_object($childTax) ? (array) $childTax : $childTax;
                    $cParent = $cData['parent_tax_id'] ?? null;
                    if ($cParent != null && (string) $cParent === (string) $taxId) {
                        $cName = strtoupper($cData['tax_name'] ?? '');
                        $cRate = (float) ($cData['tax_rate'] ?? 0);
                        if ($isIntraState && in_array($cName, ['CGST', 'SGST'])) {
                            $totalGstRate += $cRate;
                        } elseif (!$isIntraState && $cName === 'IGST') {
                            $totalGstRate += $cRate;
                        }
                    }
                }
                continue;
            }

            // GST children (IGST, CGST, SGST) - add only when matching state type
            // Intra-State: CGST + SGST | Inter-State: IGST only
            if (strtoupper($taxName) === 'CGST' || strtoupper($taxName) === 'SGST') {
                if ($isIntraState) {
                    $totalGstRate += $taxRate;
                }
            } elseif (strtoupper($taxName) === 'IGST') {
                if (!$isIntraState) {
                    $totalGstRate += $taxRate;
                }
            } else {
                // Non-GST tax (e.g. VAT) - apply directly
                $taxAmount = $taxType === 'Exclusive'
                    ? ($lineTotal * $taxRate) / 100
                    : ($lineTotal * $taxRate) / (100 + $taxRate);
                $result['total_tax'] += $taxAmount;
                $result['breakdown'][] = [
                    'tax_field_id' => (string) $taxId,
                    'tax_field_type' => $taxName,
                    'tax_field_name' => $taxName,
                    'tax_field_amount' => round($taxAmount, 2),
                    'tax_field_percentage' => round($taxRate, 2),
                ];
            }
        }

        if ($totalGstRate <= 0) {
            $result['taxable_value'] = round($lineTotal, 2);
            return $result;
        }

        // Inclusive: Taxable = Price / (1 + GST/100), GST = Price - Taxable
        // Exclusive: Taxable = Price, GST = Price * rate / 100
        if ($taxType === 'Exclusive') {
            $taxableValue = $lineTotal;
            $gstAmount = ($lineTotal * $totalGstRate) / 100;
        } else {
            $taxableValue = $lineTotal / (1 + $totalGstRate / 100);
            $gstAmount = $lineTotal - $taxableValue;
        }

        $result['taxable_value'] = round($taxableValue, 2);

        // Find GST child tax IDs for CGST, SGST, IGST
        $cgstTaxId = null;
        $sgstTaxId = null;
        $igstTaxId = null;
        foreach ($taxsMap as $tid => $t) {
            $tData = is_object($t) ? (array) $t : $t;
            $tName = strtoupper($tData['tax_name'] ?? '');
            if ($tName === 'CGST') $cgstTaxId = $tid;
            if ($tName === 'SGST') $sgstTaxId = $tid;
            if ($tName === 'IGST') $igstTaxId = $tid;
        }

        if ($isIntraState) {
            $halfAmount = $gstAmount / 2;
            $result['cgst'] = round($halfAmount, 2);
            $result['sgst'] = round($halfAmount, 2);
            $result['igst'] = 0;
            $pct = $lineTotal > 0 ? ($totalGstRate / 2) : 0;
            $result['breakdown'][] = [
                'tax_field_id' => (string) ($cgstTaxId ?? ''),
                'tax_field_type' => 'CGST',
                'tax_field_name' => 'CGST',
                'tax_field_amount' => round($halfAmount, 2),
                'tax_field_percentage' => round($pct, 2),
            ];
            $result['breakdown'][] = [
                'tax_field_id' => (string) ($sgstTaxId ?? ''),
                'tax_field_type' => 'SGST',
                'tax_field_name' => 'SGST',
                'tax_field_amount' => round($halfAmount, 2),
                'tax_field_percentage' => round($pct, 2),
            ];
        } else {
            $result['igst'] = round($gstAmount, 2);
            $result['cgst'] = 0;
            $result['sgst'] = 0;
            $result['breakdown'][] = [
                'tax_field_id' => (string) ($igstTaxId ?? ''),
                'tax_field_type' => 'IGST',
                'tax_field_name' => 'IGST',
                'tax_field_amount' => round($gstAmount, 2),
                'tax_field_percentage' => round($totalGstRate, 2),
            ];
        }

        $result['total_tax'] += $gstAmount;
        $result['total_tax'] = round($result['total_tax'], 2);

        return $result;
    }

    /**
     * Build sale_vat_objects from cart items (backend)
     *
     * @param array $cartItems
     * @param int|null $customerId
     * @param int $outletId
     * @param int $companyId
     * @return array [['tax_field_type' => 'CGST', 'tax_field_amount' => ...], ...]
     */
    public static function buildSaleVatObjects(array $cartItems, ?int $customerId, int $outletId, int $companyId): array
    {
        $outlet = Outlet::find($outletId);
        $outletStateCode = $outlet?->state_code ?? null;

        $customer = $customerId ? Customer::find($customerId) : null;
        $customerStateId = $customer?->state_id ?? null;

        $taxs = Tax::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->get()
            ->keyBy('id');

        $taxsMap = $taxs->map(fn ($t) => [
            'id' => $t->id,
            'tax_name' => $t->tax_name,
            'tax_rate' => (float) $t->tax_rate,
            'parent_tax_id' => $t->parent_tax_id,
        ])->toArray();

        $taxAmountMap = [];

        foreach ($cartItems as $item) {
            $itemSubtotal = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
            $itemDiscount = $item['discount'] ?? 0;
            $itemDiscountType = $item['discount_type'] ?? 'fixed';

            $discountAmount = $itemDiscountType === 'percentage'
                ? ($itemSubtotal * $itemDiscount) / 100
                : $itemDiscount;

            $priceAfterDiscount = $itemSubtotal - $discountAmount;
            if ($itemSubtotal > 0) {
                $pricePerUnit = $priceAfterDiscount / ($item['quantity'] ?? 1);
            } else {
                $pricePerUnit = 0;
            }

            $applicableTaxIds = $item['applicable_tax_id'] ?? null;
            $taxType = $item['tax_type'] ?? 'Inclusive';

            // Fallback: use tax_information for backward compatibility
            if (empty($applicableTaxIds) && !empty($item['tax_information'])) {
                $taxInfo = $item['tax_information'];
                if (is_string($taxInfo)) {
                    $taxInfo = json_decode($taxInfo, true) ?: [];
                }
                if (!empty($taxInfo)) {
                    $legacyResult = self::calculateFromLegacyTaxInfo($priceAfterDiscount, $item['quantity'] ?? 1, $taxInfo);
                    foreach ($legacyResult['breakdown'] as $b) {
                        $name = $b['tax_field_type'] ?? $b['tax_field_name'] ?? '';
                        $amt = $b['tax_field_amount'] ?? 0;
                        if (!isset($taxAmountMap[$name])) $taxAmountMap[$name] = 0;
                        $taxAmountMap[$name] += $amt;
                    }
                    continue;
                }
            }

            $calc = self::calculateItemTax(
                $pricePerUnit,
                $item['quantity'] ?? 1,
                $applicableTaxIds,
                $taxType,
                $outletStateCode,
                $customerStateId,
                $taxsMap
            );

            foreach ($calc['breakdown'] as $b) {
                $name = $b['tax_field_type'] ?? '';
                $amt = $b['tax_field_amount'] ?? 0;
                if (!isset($taxAmountMap[$name])) $taxAmountMap[$name] = 0;
                $taxAmountMap[$name] += $amt;
            }
        }

        // Build name -> tax lookup for tax_field_id and tax_field_percentage
        $taxByName = $taxs->keyBy(fn ($t) => strtoupper($t->tax_name ?? ''));

        $saleVatObjects = [];
        foreach ($taxAmountMap as $taxName => $amount) {
            if ($amount > 0) {
                $taxByKey = $taxByName->get(strtoupper($taxName));
                $taxFieldId = $taxByKey ? (string) $taxByKey->id : '';
                $taxFieldPct = $taxByKey ? round((float) $taxByKey->tax_rate, 2) : 0;

                $saleVatObjects[] = [
                    'tax_field_id' => $taxFieldId,
                    'tax_field_type' => $taxName,
                    'tax_field_amount' => number_format(round($amount, 2), 2, '.', ''),
                    'tax_field_percentage' => (string) $taxFieldPct,
                ];
            }
        }

        return $saleVatObjects;
    }

    /**
     * Legacy: calculate from old tax_information format
     */
    protected static function calculateFromLegacyTaxInfo(float $priceAfterDiscount, float $quantity, array $taxInfo): array
    {
        $lineTotal = $priceAfterDiscount * $quantity;
        $breakdown = [];
        $totalTax = 0;

        foreach ($taxInfo as $t) {
            $name = $t['tax_field_name'] ?? $t['tax_field_type'] ?? '';
            $pct = (float) ($t['tax_field_percentage'] ?? 0);
            if ($name && $pct > 0) {
                $amt = ($lineTotal * $pct) / 100;
                $breakdown[] = ['tax_field_type' => $name, 'tax_field_amount' => round($amt, 2)];
                $totalTax += $amt;
            }
        }

        return [
            'total_tax' => round($totalTax, 2),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Get menu_taxes format for a single cart item (for SaleDetail storage)
     *
     * @return array [['tax_field_name'=>'CGST','tax_field_percentage'=>'9'], ...]
     */
    public static function getItemMenuTaxes(array $item, ?int $customerId, int $outletId, int $companyId): array
    {
        $outlet = Outlet::find($outletId);
        $outletStateCode = $outlet?->state_code ?? null;
        $customer = $customerId ? Customer::find($customerId) : null;
        $customerStateId = $customer?->state_id ?? null;

        $taxs = Tax::where('company_id', $companyId)->where('del_status', 'Live')->get();
        $taxsMap = $taxs->map(fn ($t) => [
            'id' => $t->id,
            'tax_name' => $t->tax_name,
            'tax_rate' => (float) $t->tax_rate,
            'parent_tax_id' => $t->parent_tax_id,
        ])->keyBy('id')->toArray();

        $itemSubtotal = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
        $itemDiscount = $item['discount'] ?? 0;
        $itemDiscountType = $item['discount_type'] ?? 'fixed';
        $discountAmount = $itemDiscountType === 'percentage' ? ($itemSubtotal * $itemDiscount) / 100 : $itemDiscount;
        $priceAfterDiscount = max(0, $itemSubtotal - $discountAmount);
        $qty = $item['quantity'] ?? 1;
        $pricePerUnit = $qty > 0 ? $priceAfterDiscount / $qty : 0;

        $applicableTaxIds = $item['applicable_tax_id'] ?? null;
        $taxType = $item['tax_type'] ?? 'Inclusive';

        if (empty($applicableTaxIds)) {
            $taxInfo = $item['tax_information'] ?? [];
            if (is_string($taxInfo)) {
                $taxInfo = json_decode($taxInfo, true) ?: [];
            }
            $lineTotal = $priceAfterDiscount * $qty;
            $menuTaxes = [];
            foreach ($taxInfo as $t) {
                $name = $t['tax_field_name'] ?? $t['tax_field_type'] ?? '';
                $pct = (float) ($t['tax_field_percentage'] ?? 0);
                $taxId = (string) ($t['tax_field_id'] ?? '');
                if ($name && $pct > 0) {
                    $amt = round(($lineTotal * $pct) / 100, 2);
                    $menuTaxes[] = [
                        'tax_field_id' => $taxId,
                        'tax_field_name' => $name,
                        'tax_field_amount' => number_format($amt, 2, '.', ''),
                        'tax_field_percentage' => (string) $pct,
                    ];
                } elseif ($name) {
                    $menuTaxes[] = [
                        'tax_field_id' => $taxId,
                        'tax_field_name' => $name,
                        'tax_field_amount' => '0.00',
                        'tax_field_percentage' => (string) $pct,
                    ];
                }
            }
            return $menuTaxes;
        }

        $calc = self::calculateItemTax(
            $pricePerUnit,
            $qty,
            $applicableTaxIds,
            $taxType,
            $outletStateCode,
            $customerStateId,
            $taxsMap
        );

        $menuTaxes = [];
        foreach ($calc['breakdown'] as $b) {
            $name = $b['tax_field_name'] ?? $b['tax_field_type'] ?? '';
            $pct = (float) ($b['tax_field_percentage'] ?? 0);
            $taxId = (string) ($b['tax_field_id'] ?? '');
            $amt = (float) ($b['tax_field_amount'] ?? 0);
            if ($name) {
                $menuTaxes[] = [
                    'tax_field_id' => $taxId,
                    'tax_field_name' => $name,
                    'tax_field_amount' => number_format(round($amt, 2), 2, '.', ''),
                    'tax_field_percentage' => (string) $pct,
                ];
            }
        }
        return $menuTaxes;
    }

    /**
     * Get total item tax amount (sum of all tax_field_amount in menu_taxes)
     *
     * @param array $menuTaxes Format from getItemMenuTaxes
     * @return float
     */
    public static function getItemTaxAmount(array $menuTaxes): float
    {
        $total = 0;
        foreach ($menuTaxes as $t) {
            $total += (float) ($t['tax_field_amount'] ?? 0);
        }
        return round($total, 2);
    }

    /**
     * Get total tax percentage for an item (sum of all tax rates) - for menu_vat_percentage
     */
    public static function getItemTaxPercentage(array $menuTaxes): float
    {
        $total = 0;
        foreach ($menuTaxes as $t) {
            $total += (float) ($t['tax_field_percentage'] ?? 0);
        }
        return round($total, 2);
    }

    /**
     * Get total tax amount from cart items (for backend validation)
     */
    public static function calculateTotalTaxFromCart(array $cartItems, ?int $customerId, int $outletId, int $companyId): float
    {
        $objs = self::buildSaleVatObjects($cartItems, $customerId, $outletId, $companyId);
        $total = 0;
        foreach ($objs as $o) {
            $total += (float) ($o['tax_field_amount'] ?? 0);
        }
        return round($total, 2);
    }
}
