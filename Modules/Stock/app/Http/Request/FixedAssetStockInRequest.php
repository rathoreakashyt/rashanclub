<?php

namespace Modules\Stock\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

class FixedAssetStockInRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'reference_no' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:1000',
            'grand_total' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*' => 'required|exists:fixed_asset_items,id',
            'quantity' => 'required|array|min:1',
            'quantity.*' => 'required|integer|min:1',
            'unit_price' => 'required|array|min:1',
            'unit_price.*' => 'required|numeric|min:0',
            'total' => 'required|array|min:1',
            'total.*' => 'required|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $date = __('Date');
        $grandTotal = __('Grand_Total') ?: 'Grand Total';
        $items = __('Items');
        $item = __('Item');
        $quantity = __('Quantity');
        $unitPrice = __('Unit_Price') ?: 'Unit Price';
        $total = __('Total');
        $note = __('Note');
        $refNum = __('Reference_Number') ?: 'Reference Number';
        
        return [
            'date.required' => $date . ' ' . __('is required.'),
            'date.date' => $date . ' ' . __('must be a valid date.'),
            'reference_no.max' => __('The') . ' ' . $refNum . ' ' . __('may not be greater than') . ' 50 ' . __('characters.'),
            'grand_total.required' => __('The') . ' ' . $grandTotal . ' ' . __('is required.'),
            'grand_total.numeric' => $grandTotal . ' ' . __('must be a number.'),
            'grand_total.min' => $grandTotal . ' ' . __('must be at least') . ' 0.',
            'note.max' => __('The') . ' ' . $note . ' ' . __('may not be greater than') . ' 1000 ' . __('characters.'),
            'items.required' => __('At least one') . ' ' . strtolower($item) . ' ' . __('is required.'),
            'items.array' => $items . ' ' . __('must be an array.'),
            'items.min' => __('At least one') . ' ' . strtolower($item) . ' ' . __('is required.'),
            'items.*.required' => __('Each') . ' ' . strtolower($item) . ' ' . __('is required.'),
            'items.*.exists' => __('One or more selected') . ' ' . strtolower($items) . ' ' . __('are invalid.'),
            'quantity.required' => $quantity . ' ' . __('is required for all') . ' ' . strtolower($items) . '.',
            'quantity.*.required' => __('Each') . ' ' . strtolower($item) . ' ' . __('must have a') . ' ' . strtolower($quantity) . '.',
            'quantity.*.integer' => $quantity . ' ' . __('must be a whole number.'),
            'quantity.*.min' => $quantity . ' ' . __('must be at least') . ' 1.',
            'unit_price.required' => $unitPrice . ' ' . __('is required for all') . ' ' . strtolower($items) . '.',
            'unit_price.*.required' => __('Each') . ' ' . strtolower($item) . ' ' . __('must have a') . ' ' . strtolower($unitPrice) . '.',
            'unit_price.*.numeric' => $unitPrice . ' ' . __('must be a number.'),
            'unit_price.*.min' => $unitPrice . ' ' . __('must be at least') . ' 0.',
            'total.required' => $total . ' ' . __('is required for all') . ' ' . strtolower($items) . '.',
            'total.*.required' => __('Each') . ' ' . strtolower($item) . ' ' . __('must have a') . ' ' . strtolower($total) . '.',
            'total.*.numeric' => $total . ' ' . __('must be a number.'),
            'total.*.min' => $total . ' ' . __('must be at least') . ' 0.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'date' => __('Date'),
            'grand_total' => __('Grand Total'),
            'note' => __('Note'),
            'items' => __('Items'),
            'quantity' => __('Quantity'),
            'unit_price' => __('Unit Price'),
            'total' => __('Total'),
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean up numeric fields
        $this->merge([
            'grand_total' => $this->cleanNumericValue($this->grand_total),
        ]);

        // Clean up item totals
        if ($this->has('total') && is_array($this->total)) {
            $cleanedTotals = array_map(function ($total) {
                return $this->cleanNumericValue($total);
            }, $this->total);
            $this->merge(['total' => $cleanedTotals]);
        }

        // Clean up unit prices
        if ($this->has('unit_price') && is_array($this->unit_price)) {
            $cleanedPrices = array_map(function ($price) {
                return $this->cleanNumericValue($price);
            }, $this->unit_price);
            $this->merge(['unit_price' => $cleanedPrices]);
        }
    }

    /**
     * Clean numeric value
     */
    protected function cleanNumericValue($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) preg_replace('/[^0-9.]/', '', $value);
    }
}

