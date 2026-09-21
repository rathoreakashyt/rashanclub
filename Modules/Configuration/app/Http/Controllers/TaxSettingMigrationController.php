<?php

namespace Modules\Configuration\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Modules\Configuration\Models\Tax;
use Modules\Configuration\Models\Company;
use Modules\Stock\Models\Item;
use Modules\Sale\Models\Sale;
use Modules\Sale\Models\SaleDetail;
use Modules\Sale\Services\GstTaxService;

/**
 * Migrates legacy companies.tax_setting to taxs table and updates
 * items, sales, sale_details references.
 */
class TaxSettingMigrationController extends Controller
{
    /**
     * Run the full tax migration (Steps 1, 2, 3).
     */
    public function migrate(Request $request): JsonResponse
    {
        $companyId = session('company.company_id');
        $company = Company::find($companyId);

        if (!$company) {
            return response()->json([
                'status' => 'error',
                'message' => __('Company not found'),
            ], 404);
        }

        $taxSetting = $company->tax_setting;
        if (empty($taxSetting)) {
            return response()->json([
                'status' => 'error',
                'message' => __('No tax_setting found. Migration only applies when companies.tax_setting is not blank.'),
            ], 422);
        }

        $taxSettingArr = is_string($taxSetting) ? json_decode($taxSetting, true) : $taxSetting;
        if (!is_array($taxSettingArr) || empty($taxSettingArr)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Invalid tax_setting format'),
            ], 422);
        }

        try {
            $nameToId = $this->step1MigrateTaxsToTable($company, $taxSettingArr);
            $this->step2UpdateItemsApplicableTax($company, $nameToId);
            $this->step3UpdateSalesAndSaleDetails($company, $nameToId);

            // Clear tax_setting after successful migration
            $company->update(['tax_setting' => null]);
            $company->refresh();

            // Update session company so UI reflects change before page reload
            $sessionCompany = session('company', []);
            $sessionCompany['tax_setting'] = null;
            session(['company' => $sessionCompany]);

            return response()->json([
                'status' => 'success',
                'message' => __('Tax migration completed successfully. Please refresh the page.'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Migration failed: ') . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 1: Migrate tax_setting to taxs table.
     * Create GST if IGST, CGST, SGST exist; set parent_tax_id for GST children.
     *
     * @return array Map of tax_name (uppercase) => tax id
     */
    protected function step1MigrateTaxsToTable(Company $company, array $taxSettingArr): array
    {
        $companyId = $company->id;
        $nameToId = [];

        $taxByName = [];
        foreach ($taxSettingArr as $t) {
            $name = trim($t['tax'] ?? $t['tax_name'] ?? '');
            $rate = (float) ($t['tax_rate'] ?? 0);
            if ($name) {
                $taxByName[strtoupper($name)] = $rate;
            }
        }
        if (empty($taxByName)) {
            return $nameToId;
        }

        $hasGst = isset($taxByName['IGST']) && isset($taxByName['CGST']) && isset($taxByName['SGST']);
        $gstId = null;

        // Create GST when IGST, CGST, SGST exist (regardless of tax_is_gst)
        if ($hasGst) {
            $igstRate = $taxByName['IGST'];
            $existingGst = Tax::forCompany($companyId)->live()->where('tax_name', 'GST')->first();
            if ($existingGst) {
                $gstId = $existingGst->id;
                $existingGst->update(['tax_rate' => $igstRate]);
            } else {
                $gst = Tax::create([
                    'tax_name' => 'GST',
                    'tax_rate' => $igstRate,
                    'parent_tax_id' => null,
                    'show_in_item_profile' => 1,
                    'company_id' => $companyId,
                    'del_status' => 'Live',
                ]);
                $gstId = $gst->id;
            }
        }

        foreach ($taxByName as $nameUpper => $rate) {
            $parentId = null;
            if (in_array($nameUpper, ['CGST', 'SGST', 'IGST']) && $gstId) {
                $parentId = $gstId;
            }

            $existing = Tax::forCompany($companyId)->live()->where('tax_name', $nameUpper)->first();
            if ($existing) {
                $existing->update([
                    'tax_rate' => $rate,
                    'parent_tax_id' => $parentId,
                ]);
                $nameToId[$nameUpper] = $existing->id;
            } else {
                $tax = Tax::create([
                    'tax_name' => $nameUpper,
                    'tax_rate' => $rate,
                    'parent_tax_id' => $parentId,
                    'show_in_item_profile' => 1,
                    'company_id' => $companyId,
                    'del_status' => 'Live',
                ]);
                $nameToId[$nameUpper] = $tax->id;
            }
        }

        if ($gstId) {
            $nameToId['GST'] = $gstId;
        }

        return $nameToId;
    }

    /**
     * Step 2: Update items.applicable_tax_id and items.tax_type.
     */
    protected function step2UpdateItemsApplicableTax(Company $company, array $nameToId): void
    {
        $companyId = $company->id;
        $taxIsGst = ($company->tax_is_gst ?? 'No') === 'Yes';
        $taxType = ($company->tax_type ?? '1') == '1' ? 'Exclusive' : 'Inclusive';

        $items = Item::where('company_id', $companyId)->where('del_status', 'Live')->get();

        foreach ($items as $item) {
            $updateData = ['tax_type' => $taxType];

            $taxInfo = $item->tax_information;
            if (is_string($taxInfo)) {
                $taxInfo = json_decode($taxInfo, true) ?: [];
            }

            if (is_array($taxInfo) && !empty($taxInfo)) {
                $applicableIds = [];
                $hasGstTaxes = false;
                $otherTaxIds = [];

                foreach ($taxInfo as $t) {
                    $taxName = strtoupper(trim($t['tax_field_name'] ?? $t['tax_field_type'] ?? ''));
                    if (!$taxName) {
                        continue;
                    }
                    $taxId = $nameToId[$taxName] ?? null;
                    if (!$taxId) {
                        continue;
                    }
                    if (in_array($taxName, ['IGST', 'CGST', 'SGST'])) {
                        $hasGstTaxes = true;
                    } else {
                        $otherTaxIds[] = $taxId;
                    }
                }

                if ($taxIsGst && $hasGstTaxes && isset($nameToId['GST'])) {
                    $applicableIds[] = $nameToId['GST'];
                }
                $applicableIds = array_merge($applicableIds, array_unique($otherTaxIds));
                $applicableIds = array_values(array_unique($applicableIds));

                $updateData['applicable_tax_id'] = !empty($applicableIds) ? implode(',', $applicableIds) : null;
            }

            $item->update($updateData);
        }
    }

    /**
     * Step 3: Update sales.sale_vat_objects and sale_details.menu_taxes.
     */
    protected function step3UpdateSalesAndSaleDetails(Company $company, array $nameToId): void
    {
        $companyId = $company->id;

        $taxs = Tax::where('company_id', $companyId)->where('del_status', 'Live')->get();
        $taxsMap = [];
        foreach ($taxs as $t) {
            $taxsMap[(string) $t->id] = [
                'id' => $t->id,
                'tax_name' => $t->tax_name,
                'tax_rate' => (float) $t->tax_rate,
                'parent_tax_id' => $t->parent_tax_id,
            ];
        }

        $sales = Sale::where('company_id', $companyId)->where('del_status', 'Live')->get();

        foreach ($sales as $sale) {
            $saleVatObjects = $sale->sale_vat_objects;
            if ($saleVatObjects) {
                $objs = is_string($saleVatObjects) ? json_decode($saleVatObjects, true) : $saleVatObjects;
                if (is_array($objs)) {
                    $updated = $this->updateSaleVatObjects($objs, $nameToId);
                    $sale->update(['sale_vat_objects' => json_encode($updated)]);
                }
            }
        }

        $saleDetails = SaleDetail::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->whereNotNull('menu_taxes')
            ->where('menu_taxes', '!=', '')
            ->with(['sale.customer', 'sale.outlet', 'item'])
            ->get();

        $taxIsGst = ($company->tax_is_gst ?? 'No') === 'Yes';

        foreach ($saleDetails as $detail) {
            $menuTaxes = is_string($detail->menu_taxes) ? json_decode($detail->menu_taxes, true) : $detail->menu_taxes;
            if (!is_array($menuTaxes)) {
                continue;
            }

            $sale = $detail->sale;
            if (!$sale) {
                continue;
            }

            $outlet = $sale->outlet;
            $outletStateCode = $outlet?->state_code ?? null;
            $customer = $sale->customer;
            $customerStateId = $customer?->state_id ?? null;

            $item = $detail->item;
            $applicableTaxIds = $item ? $item->applicable_tax_id : null;

            if (empty($applicableTaxIds)) {
                $applicableTaxIds = $this->deriveApplicableTaxIdsFromMenuTaxes($menuTaxes, $nameToId, $taxIsGst);
            }

            $taxType = $item ? ($item->tax_type ?? 'Inclusive') : 'Inclusive';

            $pricePerUnit = (float) $detail->menu_price_with_discount;
            $qty = (float) $detail->qty;

            $calc = GstTaxService::calculateItemTax(
                $pricePerUnit,
                $qty,
                $applicableTaxIds,
                $taxType,
                $outletStateCode,
                $customerStateId,
                $taxsMap
            );

            $newMenuTaxes = [];
            foreach ($calc['breakdown'] as $b) {
                $name = $b['tax_field_name'] ?? $b['tax_field_type'] ?? '';
                $taxId = $b['tax_field_id'] ?? '';
                $pct = (float) ($b['tax_field_percentage'] ?? 0);
                $amt = (float) ($b['tax_field_amount'] ?? 0);
                if ($name) {
                    $newMenuTaxes[] = [
                        'tax_field_id' => (string) $taxId,
                        'tax_field_name' => $name,
                        'tax_field_amount' => number_format(round($amt, 2), 2, '.', ''),
                        'tax_field_percentage' => (string) round($pct, 2),
                    ];
                }
            }

            $itemTaxAmount = GstTaxService::getItemTaxAmount($newMenuTaxes);
            $menuVatPercentage = GstTaxService::getItemTaxPercentage($newMenuTaxes);

            $detail->update([
                'menu_taxes' => !empty($newMenuTaxes) ? json_encode($newMenuTaxes) : null,
                'item_tax_amount' => $itemTaxAmount,
                'menu_vat_percentage' => $menuVatPercentage,
            ]);
        }
    }

    /**
     * Update sale_vat_objects: map tax_field_id by tax name, set tax_field_percentage from taxs.
     */
    protected function updateSaleVatObjects(array $objs, array $nameToId): array
    {
        $result = [];
        foreach ($objs as $o) {
            $origName = trim($o['tax_field_name'] ?? $o['tax_field_type'] ?? '');
            $name = strtoupper($origName);
            $newId = $nameToId[$name] ?? $o['tax_field_id'] ?? '';
            $tax = $newId ? Tax::find($newId) : null;
            $pct = $tax ? round((float) $tax->tax_rate, 2) : (float) ($o['tax_field_percentage'] ?? 0);

            $result[] = [
                'tax_field_id' => (string) $newId,
                'tax_field_name' => $origName ?: $name,
                'tax_field_amount' => $o['tax_field_amount'] ?? '0.00',
                'tax_field_percentage' => (string) $pct,
            ];
        }
        return $result;
    }

    /**
     * Derive applicable_tax_id from legacy menu_taxes when item is null or has no applicable_tax_id.
     */
    protected function deriveApplicableTaxIdsFromMenuTaxes(array $menuTaxes, array $nameToId, bool $taxIsGst): ?string
    {
        $applicableIds = [];
        $hasGstTaxes = false;
        $otherTaxIds = [];

        foreach ($menuTaxes as $t) {
            $taxName = strtoupper(trim($t['tax_field_name'] ?? $t['tax_field_type'] ?? ''));
            if (!$taxName) {
                continue;
            }
            $taxId = $nameToId[$taxName] ?? null;
            if (!$taxId) {
                continue;
            }
            if (in_array($taxName, ['IGST', 'CGST', 'SGST'])) {
                $hasGstTaxes = true;
            } else {
                $otherTaxIds[] = $taxId;
            }
        }

        if ($taxIsGst && $hasGstTaxes && isset($nameToId['GST'])) {
            $applicableIds[] = $nameToId['GST'];
        }
        $applicableIds = array_merge($applicableIds, array_unique($otherTaxIds));
        $applicableIds = array_values(array_unique($applicableIds));

        return !empty($applicableIds) ? implode(',', $applicableIds) : null;
    }
}
