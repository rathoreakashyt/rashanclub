<?php

namespace Modules\Stock\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class ItemRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Log validation errors for debugging.
     */
    protected function failedValidation(Validator $validator)
    {
        \Illuminate\Support\Facades\Log::error('ItemRequest validation failed', [
            'errors' => $validator->errors()->toArray(),
            'input_keys' => array_keys($this->all()),
            'method' => $this->method(),
        ]);
        parent::failedValidation($validator);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        // Get the encrypted ID from the route parameter
        $encryptedId = $this->route('item');
        $itemId = null;
        
        // If we have an encrypted ID, decrypt it to get the actual item ID
        if ($encryptedId) {
            try {
                if (is_string($encryptedId)) {
                    $itemId = decrypt($encryptedId);
                    $itemId = (int) $itemId;
                } else {
                    $itemId = $encryptedId->id ?? null;
                }
            } catch (\Exception $e) {
                $itemId = null;
            }
        }
        
        $companyId = session('company.company_id');

        // Base validation rules
        $validationRules = [
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:55',
                \Illuminate\Validation\Rule::unique('items', 'code')->ignore($itemId)->where('company_id', $companyId)->where('del_status', 'Live')
            ],
            'alternative_name' => 'nullable|string|max:55',
            'category_id' => 'required|exists:item_categories,id',
            'type' => 'required|in:General_Product,Variation_Product,IMEI_Product,Serial_Product,Medicine_Product,Installment_Product,Service_Product,Combo_Product',
        ];

        // Sale price validation (not required for Variation_Product and Combo_Product)
        if ($this->type !== 'Variation_Product' && $this->type !== 'Combo_Product') {
            $validationRules['sale_price'] = 'required|numeric|min:0';
        }
        $validationRules['mrp_price'] = 'nullable|numeric|min:0';
        
        // Service_Product specific validations - only allow specific fields
        if ($this->type === 'Service_Product') {
            // Make unit_type and unit fields optional for Service_Product
            $validationRules['unit_type'] = 'nullable|in:1,2';
            $validationRules['sale_unit_id'] = 'nullable|exists:units,id';
            $validationRules['purchase_unit_id'] = 'nullable|exists:units,id';
            $validationRules['conversion_rate'] = 'nullable|numeric|min:0';
        }

        // Combo_Product specific validations
        if ($this->type === 'Combo_Product') {
            // Make unit_type and unit fields optional for Combo_Product
            $validationRules['unit_type'] = 'nullable|in:1,2';
            $validationRules['sale_unit_id'] = 'nullable|exists:units,id';
            $validationRules['purchase_unit_id'] = 'nullable|exists:units,id';
            $validationRules['conversion_rate'] = 'nullable|numeric|min:0';
            // Combo sale price validation
            $validationRules['combo_sale_price'] = 'required|numeric|min:0';
            // Combo items validation
            $validationRules['combo_items'] = 'required|array|min:1';
            $validationRules['combo_items.*.item_id'] = 'required|exists:items,id';
            $validationRules['combo_items.*.quantity'] = 'required|numeric|min:0.001';
            $validationRules['combo_items.*.amount'] = 'required|numeric|min:0';
            $validationRules['combo_items.*.total'] = 'required|numeric|min:0';
            $validationRules['combo_items.*.show_in_invoice'] = 'nullable|boolean';
        }

        // Tax Validation
        // if(session('company.collect_tax') == 'Yes' && session('company.tax_is_gst') == 'Yes'){
        //     $validationRules['taxes'] = 'required|array';
        //     $validationRules['tax_rate'] = 'required|array';
        // }else if(session('company.collect_tax') == 'Yes' && session('company.tax_is_gst') == 'No'){
        //     $validationRules['taxes'] = 'required|array';
        //     $validationRules['tax_rate'] = 'required|array';
        // }

        // Applicable tax (item profile taxes) - optional multi-select, stored comma-separated in applicable_tax_id
        $validationRules['applicable_tax_id'] = 'nullable|array';
        $validationRules['applicable_tax_id.*'] = 'exists:taxs,id';

        // Tax Type - Exclusive or Inclusive, default Exclusive
        $validationRules['tax_type'] = 'nullable|in:Exclusive,Inclusive';

        // HSN Code - optional, shown only when GST is enabled
        $validationRules['hsn_code'] = 'nullable|string|max:100';

        // For variation products, validate variations array
        if ($this->type === 'Variation_Product') {
            $validationRules['variations'] = 'required|array|min:1';
            $validationRules['variations.*.variation_name'] = 'required|string|max:55';
            $validationRules['variations.*.item_code'] = [
                'required',
                'string',
                'max:55',
                function ($attribute, $value, $fail) use ($companyId, $itemId) {
                    // Get the variation ID from the request if it exists (for edit mode)
                    $variationIndex = (int) explode('.', $attribute)[1];
                    $variationId = null;
                    if (isset($this->variations[$variationIndex]['id'])) {
                        try {
                            $variationId = decrypt($this->variations[$variationIndex]['id']);
                        } catch (\Exception $e) {
                            // Invalid encrypted ID, treat as new variation
                        }
                    }
                    
                    $existingItem = \Modules\Stock\Models\Item::where('code', $value)
                        ->where('company_id', $companyId)
                        ->where('del_status', 'Live');
                    
                    // Exclude current variation item if editing
                    if ($variationId) {
                        $existingItem->where('id', '!=', $variationId);
                    }
                    
                    $existingItem = $existingItem->first();
                    if ($existingItem) {
                        $itemCode = __('Item_Code') ?: 'Item Code';
                        $fail($itemCode . ' "' . $value . '" ' . __('has already been taken.'));
                    }
                }
            ];
            $validationRules['variations.*.sale_price'] = 'required|numeric|min:0';
            $validationRules['variations.*.whole_sale_price'] = 'nullable|numeric|min:0';
            $validationRules['variations.*.mrp_price'] = 'nullable|numeric|min:0';
            $validationRules['variations.*.alert_quantity'] = 'nullable|numeric|min:0';
            $validationRules['variations.*.opening_stock'] = 'nullable|array';
            $validationRules['variations.*.opening_stock.*'] = 'nullable|numeric|min:0';
            
            // Validate purchase_price for variations - required if opening stock exists
            foreach ($this->variations ?? [] as $index => $variation) {
                $hasOpeningStock = false;
                if (isset($variation['opening_stock']) && is_array($variation['opening_stock'])) {
                    foreach ($variation['opening_stock'] as $outletId => $quantity) {
                        if ((float)$quantity > 0) {
                            $hasOpeningStock = true;
                            break;
                        }
                    }
                }
                
                if ($hasOpeningStock) {
                    $validationRules["variations.{$index}.purchase_price"] = 'required|numeric|min:0';
                } else {
                    $validationRules["variations.{$index}.purchase_price"] = 'nullable|numeric|min:0';
                }
            }
        } else {
            // For non-service products
            if ($this->type !== 'Service_Product' && $this->type !== 'Combo_Product') {
                // Check if opening stock exists to make purchase_price required
                $hasOpeningStock = false;
                
                // Check for opening stock from modal (outlet_id array)
                if ($this->has('outlet_id') && is_array($this->outlet_id) && count($this->outlet_id) > 0) {
                    foreach ($this->outlet_id as $key => $outletId) {
                        $quantity = isset($this->quantity[$key]) ? (float)$this->quantity[$key] : 0;
                        $itemDescription = isset($this->item_description[$key]) ? $this->item_description[$key] : null;
                        
                        if ($quantity > 0 || !empty($itemDescription)) {
                            $hasOpeningStock = true;
                            break;
                        }
                    }
                }
                
                // Check for simple opening stock
                if (!$hasOpeningStock && $this->has('opening_stock') && !empty($this->opening_stock)) {
                    $hasOpeningStock = true;
                }
                
                if ($hasOpeningStock) {
                    $validationRules['purchase_price'] = 'required|numeric|min:0';
                } else {
                    $validationRules['purchase_price'] = 'nullable|numeric|min:0';
                }
                
                $validationRules['whole_sale_price'] = 'nullable|numeric|min:0';
            }
        }

        // Unit type validation - applies to all product types except Service_Product and Combo_Product
        if ($this->type !== 'Service_Product' && $this->type !== 'Combo_Product') {
            $validationRules['unit_type'] = 'required|in:1,2';
            if ($this->unit_type == 1) {
                $validationRules['sale_unit_id'] = 'required|exists:units,id';
            }
            if ($this->unit_type == 2) {
                $validationRules['purchase_unit_id'] = 'required|exists:units,id';
                $validationRules['sale_unit_id'] = 'required|exists:units,id';
                $validationRules['conversion_rate'] = 'required|numeric|min:0';
            }
        }

        // Additional validations
        $validationRules['expiry_date_maintain'] = 'nullable|in:Yes,No';
        $validationRules['generic_name'] = 'nullable|string|max:255';
        $validationRules['rack_id'] = 'nullable|exists:racks,id';
        $validationRules['brand_id'] = 'nullable|exists:brands,id';
        $validationRules['supplier_id'] = 'nullable|exists:suppliers,id';
        $validationRules['alert_quantity'] = 'nullable|numeric|min:0';
        $validationRules['description'] = 'nullable|string';
        $validationRules['warranty'] = 'nullable|numeric|min:0';
        $validationRules['warranty_date'] = 'nullable|in:day,month,year';
        $validationRules['guarantee'] = 'nullable|numeric|min:0';
        $validationRules['guarantee_date'] = 'nullable|in:day,month,year';
        $validationRules['profit_margin'] = 'nullable|numeric|min:0';
        $validationRules['loyalty_point'] = 'nullable|numeric|min:0';
        $validationRules['photo'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120';
        $validationRules['tax_information'] = 'nullable|string';
        $validationRules['tax_string'] = 'nullable|string';
        $validationRules['variation_details'] = 'nullable|string';
        $validationRules['enable_disable_status'] = 'nullable|in:Enable,Disable';

        return $validationRules;
    }

    /**
     * Configure the validator instance.
     */
    // public function withValidator($validator)
    // {
    //     $validator->after(function ($validator) {
    //         // Tax validation for GST
    //         if(session('company.collect_tax') == 'Yes' && session('company.tax_is_gst') == 'Yes'){
    //             $taxes = array_map('strtoupper', $this->taxes ?? []);
    //             $requiredTaxes = ['CGST', 'SGST', 'IGST'];
    //             $missingTaxes = array_diff($requiredTaxes, $taxes);
    //             if (!empty($missingTaxes)) {
    //                 $validator->errors()->add('taxes', __('When GST is enabled, CGST, SGST and IGST are required. Missing:') . ' ' . implode(', ', $missingTaxes));
    //             }
    //         }
    //     });
    // }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $itemName = __('Item Name') ?: 'Item Name';
        $alternativeName = __('Alternative Name') ?: 'Alternative Name';
        $itemCode = __('Item Code') ?: 'Item Code';
        $category = __('Category');
        $itemType = __('Item Type') ?: 'Item Type';
        $salePrice = __('Sale Price') ?: 'Sale Price';
        $purchasePrice = __('Purchase Price') ?: 'Purchase Price';
        $wholeSalePrice = __('Whole Sale Price') ?: 'Whole Sale Price';
        $mrpPrice = __('MRP Price') ?: 'MRP Price';
        $variationName = __('Variation Name') ?: 'Variation Name';
        $unitType = __('Unit Type') ?: 'Unit Type';
        $saleUnit = __('Sale_Unit') ?: 'Sale Unit';
        $purchaseUnit = __('Purchase_Unit') ?: 'Purchase Unit';
        $conversionRate = __('Conversion Rate') ?: 'Conversion Rate';
        $photo = __('Photo') ?: 'Photo';
        $variation = __('Variation') ?: 'Variation';
        $comboSalePrice = __('Combo Sale Price') ?: 'Combo Sale Price';
        $comboItems = __('Combo Items') ?: 'Combo Items';
        
        return [
            'name.required' => __('The') . ' ' . $itemName . ' ' . __('is required.'),
            'name.max' => __('The') . ' ' . $itemName . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'alternative_name.max' => __('The') . ' ' . $alternativeName . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'code.required' => __('The') . ' ' . $itemCode . ' ' . __('is required.'),
            'code.unique' => __('The') . ' ' . $itemCode . ' ' . __('has already been taken.'),
            'code.max' => __('The') . ' ' . $itemCode . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'category_id.required' => __('The') . ' ' . $category . ' ' . __('is required.'),
            'category_id.exists' => __('The selected') . ' ' . $category . ' ' . __('is invalid.'),
            'type.required' => __('The') . ' ' . $itemType . ' ' . __('is required.'),
            'type.in' => __('The selected') . ' ' . $itemType . ' ' . __('is invalid.'),
            'sale_price.required' => __('The') . ' ' . $salePrice . ' ' . __('is required.'),
            'sale_price.numeric' => $salePrice . ' ' . __('must be a number.'),
            'sale_price.min' => $salePrice . ' ' . __('must be at least') . ' 0.',
            'purchase_price.required' => __('The') . ' ' . $purchasePrice . ' ' . __('is required.'),
            'purchase_price.numeric' => $purchasePrice . ' ' . __('must be a number.'),
            'purchase_price.min' => $purchasePrice . ' ' . __('must be at least') . ' 0.',
            'whole_sale_price.numeric' => $wholeSalePrice . ' ' . __('must be a number.'),
            'whole_sale_price.min' => $wholeSalePrice . ' ' . __('must be at least') . ' 0.',
            'mrp_price.numeric' => $mrpPrice . ' ' . __('must be a number.'),
            'mrp_price.min' => $mrpPrice . ' ' . __('must be at least') . ' 0.',
            'variations.required' => __('At least one') . ' ' . strtolower($variation) . ' ' . __('is required for Variation Product.'),
            'variations.min' => __('At least one') . ' ' . strtolower($variation) . ' ' . __('is required for Variation Product.'),
            'variations.*.variation_name.required' => __('The') . ' ' . $variationName . ' ' . __('is required for each') . ' ' . strtolower($variation) . '.',
            'variations.*.variation_name.max' => __('The') . ' ' . $variationName . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'variations.*.item_code.required' => __('The') . ' ' . $itemCode . ' ' . __('is required for each') . ' ' . strtolower($variation) . '.',
            'variations.*.item_code.unique' => __('The') . ' ' . $itemCode . ' ' . __('has already been taken for this') . ' ' . strtolower($variation) . '.',
            'variations.*.sale_price.required' => __('The') . ' ' . $salePrice . ' ' . __('is required for each') . ' ' . strtolower($variation) . '.',
            'variations.*.sale_price.numeric' => $salePrice . ' ' . __('must be a number.'),
            'variations.*.sale_price.min' => $salePrice . ' ' . __('must be at least') . ' 0.',
            'variations.*.whole_sale_price.numeric' => $wholeSalePrice . ' ' . __('must be a number.'),
            'variations.*.whole_sale_price.min' => $wholeSalePrice . ' ' . __('must be at least') . ' 0.',
            'variations.*.mrp_price.numeric' => $mrpPrice . ' ' . __('must be a number.'),
            'variations.*.mrp_price.min' => $mrpPrice . ' ' . __('must be at least') . ' 0.',
            'variations.*.alert_quantity.numeric' => __('Alert_Quantity') . ' ' . __('must be a number.'),
            'variations.*.alert_quantity.min' => __('Alert_Quantity') . ' ' . __('must be at least') . ' 0.',
            'variations.*.purchase_price.required' => __('The') . ' ' . $purchasePrice . ' ' . __('is required.'),
            'variations.*.purchase_price.numeric' => $purchasePrice . ' ' . __('must be a number.'),
            'variations.*.purchase_price.min' => $purchasePrice . ' ' . __('must be at least') . ' 0.',
            'unit_type.required' => __('The') . ' ' . $unitType . ' ' . __('is required.'),
            'unit_type.in' => __('The selected') . ' ' . $unitType . ' ' . __('is invalid.'),
            'sale_unit_id.required' => __('The') . ' ' . $saleUnit . ' ' . __('is required.'),
            'sale_unit_id.exists' => __('The selected') . ' ' . $saleUnit . ' ' . __('is invalid.'),
            'purchase_unit_id.required' => __('The') . ' ' . $purchaseUnit . ' ' . __('is required when using Double Unit.'),
            'purchase_unit_id.exists' => __('The selected') . ' ' . $purchaseUnit . ' ' . __('is invalid.'),
            'conversion_rate.required' => __('The') . ' ' . $conversionRate . ' ' . __('is required when using Double Unit.'),
            'conversion_rate.numeric' => $conversionRate . ' ' . __('must be a number.'),
            'conversion_rate.min' => $conversionRate . ' ' . __('must be at least') . ' 0.',
            'combo_sale_price.required' => __('The') . ' ' . $comboSalePrice . ' ' . __('is required.'),
            'combo_sale_price.numeric' => $comboSalePrice . ' ' . __('must be a number.'),
            'combo_sale_price.min' => $comboSalePrice . ' ' . __('must be at least') . ' 0.',
            'combo_items.required' => __('At least one') . ' ' . strtolower($comboItems) . ' ' . __('is required.'),
            'combo_items.min' => __('At least one') . ' ' . strtolower($comboItems) . ' ' . __('is required.'),
            'combo_items.*.item_id.required' => __('Item') . ' ' . __('is required.'),
            'combo_items.*.item_id.exists' => __('The selected') . ' ' . __('Item') . ' ' . __('is invalid.'),
            'combo_items.*.quantity.required' => __('Quantity') . ' ' . __('is required.'),
            'combo_items.*.quantity.numeric' => __('Quantity') . ' ' . __('must be a number.'),
            'combo_items.*.quantity.min' => __('Quantity') . ' ' . __('must be at least') . ' 0.001.',
            'combo_items.*.amount.required' => __('Amount') . ' ' . __('is required.'),
            'combo_items.*.amount.numeric' => __('Amount') . ' ' . __('must be a number.'),
            'combo_items.*.amount.min' => __('Amount') . ' ' . __('must be at least') . ' 0.',
            'combo_items.*.total.required' => __('Total') . ' ' . __('is required.'),
            'combo_items.*.total.numeric' => __('Total') . ' ' . __('must be a number.'),
            'combo_items.*.total.min' => __('Total') . ' ' . __('must be at least') . ' 0.',
            'taxes.required' => __('Taxes') . ' ' . __('is required.'),
            'tax_rate.required' => __('Tax_Rate') . ' ' . __('is required.'),
            'photo.image' => $photo . ' ' . __('must be an image.'),
            'photo.mimes' => $photo . ' ' . __('must be a file of type:') . ' jpeg, png, jpg, gif, webp.',
            'photo.max' => $photo . ' ' . __('may not be greater than') . ' 2048 ' . __('kilobytes.'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Item Name') ?: 'Item Name',
            'alternative_name' => __('Alternative Name') ?: 'Alternative Name',
            'code' => __('Item Code') ?: 'Item Code',
            'category_id' => __('Category'),
            'type' => __('Item Type') ?: 'Item Type',
            'sale_price' => __('Sale Price') ?: 'Sale Price',
            'purchase_price' => __('Purchase_Price') ?: 'Purchase Price',
            'whole_sale_price' => __('Whole Sale Price') ?: 'Whole Sale Price',
            'mrp_price' => __('MRP Price') ?: 'MRP Price',
            'variations.*.variation_name' => __('Variation Name') ?: 'Variation Name',
            'variations.*.item_code' => __('Item Code') ?: 'Item Code',
            'variations.*.sale_price' => __('Sale Price') ?: 'Sale Price',
            'variations.*.purchase_price' => __('Purchase Price') ?: 'Purchase Price',
            'variations.*.whole_sale_price' => __('Whole Sale Price') ?: 'Whole Sale Price',
            'variations.*.mrp_price' => __('MRP Price') ?: 'MRP Price',
            'variations.*.alert_quantity' => __('Alert Quantity') ?: 'Alert Quantity',
            'unit_type' => __('Unit Type') ?: 'Unit Type',
            'sale_unit_id' => __('Sale_Unit') ?: 'Sale Unit',
            'purchase_unit_id' => __('Purchase_Unit') ?: 'Purchase Unit',
            'conversion_rate' => __('Conversion_Rate') ?: 'Conversion Rate',
            'combo_sale_price' => __('Combo_Sale_Price') ?: 'Combo Sale Price',
            'combo_items' => __('Combo_Items') ?: 'Combo Items',
            'combo_items.*.item_id' => __('Item'),
            'combo_items.*.quantity' => __('Quantity'),
            'combo_items.*.amount' => __('Amount'),
            'combo_items.*.total' => __('Total'),
            'taxes' => __('Taxes'),
            'tax_rate' => __('Tax_Rate') ?: 'Tax Rate',
            'photo' => __('Photo') ?: 'Photo',
        ];
    }
}

