<?php

namespace Modules\Purchase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseReturnRequest extends FormRequest
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
        // Get purchase return ID from route parameter (encrypted) and decrypt it
        $purchaseReturnId = null;
        $purchaseReturnParam = $this->route('purchase_return') ?? $this->route('purchase-return');
        
        if ($purchaseReturnParam) {
            try {
                $purchaseReturnId = decrypt($purchaseReturnParam);
            } catch (\Exception $e) {
                // If decryption fails, try to get it directly (might be already decrypted)
                $purchaseReturnId = $purchaseReturnParam;
            }
        }
        
        $rules = [
            'reference_no' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('purchase_returns', 'reference_no')->ignore($purchaseReturnId)
            ],
            'supplier_id' => 'required|exists:suppliers,id',
            'date' => 'required|date',
            'purchase_date' => 'nullable|date',
            'status' => [
                'required',
                Rule::in([
                    'draft',
                    'taken_by_sup_pro_not_returned',
                    'taken_by_sup_money_returned',
                    'taken_by_sup_pro_returned'
                ])
            ],
            'items' => 'required|array|min:1',
            'items.*' => 'required|exists:items,id',
            'quantity' => 'required|array|min:1',
            'quantity.*' => 'required|numeric|min:0.01',
            'unit_price' => 'required|array|min:1',
            'unit_price.*' => 'required|numeric|min:0',
            'imei_serial' => 'nullable|array',
            'imei_serial.*' => 'nullable|string',
            'returned_imei_serial' => 'nullable|array',
            'returned_imei_serial.*' => 'nullable|string',
            'item_note' => 'nullable|array',
            'item_note.*' => 'nullable|string|max:500',
        ];

        // Amount and payment method are required only when status is "taken_by_sup_money_returned"
        if ($this->status === 'taken_by_sup_money_returned') {
            $rules['payment_method_id'] = 'required|exists:payment_methods,id';
            $rules['total_return_amount'] = 'required|numeric|min:0.01';
        } else {
            $rules['payment_method_id'] = 'nullable|exists:payment_methods,id';
            $rules['total_return_amount'] = 'nullable|numeric|min:0';
        }

        // For IMEI_Product, Serial_Product, and Medicine_Product, IMEI/Serial is required
        if ($this->items) {
            foreach ($this->items as $index => $itemId) {
                $item = \Modules\Stock\Models\Item::find($itemId);
                if ($item && in_array($item->type, ['IMEI_Product', 'Serial_Product', 'Medicine_Product'])) {
                    $rules["imei_serial.$index"] = 'required|string';
                    
                    // Returned IMEI/Serial is required when status is "taken_by_sup_pro_returned"
                    if ($this->status === 'taken_by_sup_pro_returned') {
                        $rules["returned_imei_serial.$index"] = 'required|string';
                    }
                }
            }
        }

        return $rules;
    }

    /**
     * Get the custom error messages for validation rules.
     */
    public function messages(): array
    {
        $supplier = __('Supplier') ?: 'Supplier';
        $items = __('Items') ?: 'Items';
        $quantity = __('Quantity') ?: 'Quantity';
        $unitPrice = __('Unit_Price') ?: 'Unit Price';
        $imeiSerial = __('IMEI_Serial') ?: 'IMEI/Serial';
        $returnedImeiSerial = __('Returned_IMEI_Serial') ?: 'Returned IMEI/Serial';
        $grandTotal = __('Grand_Total') ?: 'Grand Total';
        $paymentMethod = __('Payment_Method');
        $status = __('Status');
        $date = __('Date');
        $purchaseDate = __('Purchase_Date') ?: 'Purchase Date';
        $itemNote = __('Item_Note') ?: 'Item Note';
        
        $refNum = __('Reference_Number') ?: 'Reference Number';
        
        return [
            'reference_no.unique' => __('The') . ' ' . $refNum . ' ' . __('has already been taken.'),
            'reference_no.max' => __('The') . ' ' . $refNum . ' ' . __('may not be greater than') . ' 50 ' . __('characters.'),
            'supplier_id.required' => __('The') . ' ' . $supplier . ' ' . __('is required.'),
            'supplier_id.exists' => __('The selected') . ' ' . $supplier . ' ' . __('is invalid.'),
            'date.required' => $date . ' ' . __('is required.'),
            'date.date' => $date . ' ' . __('must be a valid date.'),
            'purchase_date.date' => $purchaseDate . ' ' . __('must be a valid date.'),
            'status.required' => $status . ' ' . __('is required.'),
            'status.in' => __('Invalid') . ' ' . strtolower($status) . ' ' . __('selected'),
            'items.required' => __('At least one') . ' ' . strtolower($items) . ' ' . __('is required.'),
            'items.min' => __('At least one') . ' ' . strtolower($items) . ' ' . __('is required.'),
            'items.*.exists' => __('Selected') . ' ' . strtolower(__('Item')) . ' ' . __('is invalid.'),
            'quantity.*.required' => $quantity . ' ' . __('is required.'),
            'quantity.*.min' => $quantity . ' ' . __('must be at least') . ' 0.01.',
            'unit_price.*.required' => $unitPrice . ' ' . __('is required.'),
            'unit_price.*.min' => $unitPrice . ' ' . __('must be at least') . ' 0.',
            'imei_serial.*.required' => $imeiSerial . ' ' . __('number is required for this product type.'),
            'returned_imei_serial.*.required' => $returnedImeiSerial . ' ' . __('number is required for this product type.'),
            'total_return_amount.required' => $grandTotal . ' ' . __('is required when status is') . ' "' . __('Taken By Supplier Money Returned') . '".',
            'total_return_amount.min' => $grandTotal . ' ' . __('must be at least') . ' 0.01.',
            'payment_method_id.required' => $paymentMethod . ' ' . __('is required when status is') . ' "' . __('Taken By Supplier Money Returned') . '".',
            'payment_method_id.exists' => __('The selected') . ' ' . $paymentMethod . ' ' . __('is invalid.'),
            'item_note.*.max' => $itemNote . ' ' . __('may not be greater than') . ' 500 ' . __('characters.'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'reference_no' => __('Reference_Number') ?: 'Reference Number',
            'supplier_id' => __('Supplier') ?: 'Supplier',
            'date' => __('Date'),
            'purchase_date' => __('Purchase_Date') ?: 'Purchase Date',
            'status' => __('Status'),
            'payment_method_id' => __('Payment_Method'),
            'total_return_amount' => __('Grand_Total') ?: 'Grand Total',
            'invoice_no' => __('Invoice_No') ?: 'Invoice No',
            'note' => __('Note'),
            'items' => __('Items') ?: 'Items',
            'quantity' => __('Quantity') ?: 'Quantity',
            'unit_price' => __('Unit_Price') ?: 'Unit Price',
            'imei_serial' => __('IMEI_Serial') ?: 'IMEI/Serial',
            'returned_imei_serial' => __('Returned_IMEI_Serial') ?: 'Returned IMEI/Serial',
            'item_note' => __('Item_Note') ?: 'Item Note',
        ];
    }
}

