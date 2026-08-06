<?php

namespace Modules\Purchase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
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
        // Get purchase ID from route parameter (encrypted) and decrypt it
        $purchaseId = null;
        $purchaseParam = $this->route('purchase');
        
        if ($purchaseParam) {
            try {
                $purchaseId = decrypt($purchaseParam);
            } catch (\Exception $e) {
                // If decryption fails, try to get it directly (might be already decrypted)
                $purchaseId = $purchaseParam;
            }
        }
        
        $rules = [
            'reference_no' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('purchases', 'reference_no')->ignore($purchaseId)
            ],
            'supplier_id' => 'required|exists:suppliers,id',
            'date' => 'required|date',
            'supplier_invoice_no' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*' => 'required|exists:items,id',
            'quantity' => 'required|array|min:1',
            'quantity.*' => 'required|numeric|min:0.01',
            'unit_price' => 'required|array|min:1',
            'unit_price.*' => 'required|numeric|min:0',
            'imei_serial' => 'nullable|array',
            'imei_serial.*' => 'nullable|string',
            'item_note' => 'nullable|array',
            'item_note.*' => 'nullable|string|max:500',
            // Payment validation rules (optional)
            'payments' => 'nullable|array',
            'payments.*.payment_id' => 'required_with:payments|exists:payment_methods,id',
            'payments.*.amount' => 'required_with:payments|numeric|min:0.01',
            'payments.*.note' => 'nullable|string|max:500',
        ];

        // For IMEI_Product, Serial_Product, and Medicine_Product, IMEI/Serial is required
        if ($this->items) {
            foreach ($this->items as $index => $itemId) {
                $item = \Modules\Stock\Models\Item::find($itemId);
                if ($item && in_array($item->type, ['IMEI_Product', 'Serial_Product', 'Medicine_Product'])) {
                    $rules["imei_serial.$index"] = 'required|string';
                }
            }
        }

        return $rules;
    }

    /**
     * Configure the validator instance (paid amount cannot exceed grand total).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->has('items') || !$this->has('unit_price') || !$this->has('quantity')) {
                return;
            }
            $subtotal = 0;
            $items = $this->input('items', []);
            $quantities = $this->input('quantity', []);
            $unitPrices = $this->input('unit_price', []);
            foreach (array_keys($items) as $index) {
                $qty = isset($quantities[$index]) ? (float) $quantities[$index] : 0;
                $price = isset($unitPrices[$index]) ? (float) $unitPrices[$index] : 0;
                $subtotal += $qty * $price;
            }
            $discountValue = $this->input('discount', '');
            $discountAmount = 0;
            if ($discountValue !== '' && $discountValue !== null) {
                $str = trim((string) $discountValue);
                if (strpos($str, '%') !== false) {
                    $discountAmount = $subtotal * ((float) str_replace('%', '', $str) / 100);
                } else {
                    $discountAmount = (float) $str;
                }
            }
            $grandTotal = $subtotal - $discountAmount;
            $totalPaid = 0;
            $payments = $this->input('payments', []);
            foreach ($payments as $p) {
                if (!empty($p['payment_id']) && isset($p['amount'])) {
                    $totalPaid += (float) $p['amount'];
                }
            }
            if ($totalPaid > $grandTotal) {
                $validator->errors()->add(
                    'payments',
                    __('Paid amount cannot be greater than grand total.')
                );
            }
        });
    }

    /**
     * Get the custom error messages for validation rules.
     */
    public function messages(): array
    {
        $items = __('Items') ?: 'Items';
        $quantity = __('Quantity') ?: 'Quantity';
        $unitPrice = __('Unit_Price') ?: 'Unit Price';
        $imeiSerial = __('IMEI_Serial') ?: 'IMEI/Serial';
        $supplier = __('Supplier') ?: 'Supplier';
        $date = __('Date');
        $supplierInvoiceNo = __('Supplier_Invoice_No') ?: 'Supplier Invoice No';
        $note = __('Note');
        $itemNote = __('Item_Note') ?: 'Item Note';
        
        $refNum = __('Reference_Number') ?: 'Reference Number';
        
        return [
            'reference_no.unique' => __('The') . ' ' . $refNum . ' ' . __('has already been taken.'),
            'reference_no.max' => __('The') . ' ' . $refNum . ' ' . __('may not be greater than') . ' 50 ' . __('characters.'),
            'supplier_id.required' => __('The') . ' ' . $supplier . ' ' . __('is required.'),
            'supplier_id.exists' => __('The selected') . ' ' . $supplier . ' ' . __('is invalid.'),
            'date.required' => $date . ' ' . __('is required.'),
            'date.date' => $date . ' ' . __('must be a valid date.'),
            'supplier_invoice_no.max' => __('The') . ' ' . $supplierInvoiceNo . ' ' . __('may not be greater than') . ' 100 ' . __('characters.'),
            'note.max' => __('The') . ' ' . $note . ' ' . __('may not be greater than') . ' 1000 ' . __('characters.'),
            'items.required' => __('At least one') . ' ' . strtolower($items) . ' ' . __('is required.'),
            'items.min' => __('At least one') . ' ' . strtolower($items) . ' ' . __('is required.'),
            'items.*.exists' => __('Selected') . ' ' . strtolower(__('Item')) . ' ' . __('is invalid.'),
            'quantity.*.required' => $quantity . ' ' . __('is required.'),
            'quantity.*.min' => $quantity . ' ' . __('must be at least') . ' 0.01.',
            'unit_price.*.required' => $unitPrice . ' ' . __('is required.'),
            'unit_price.*.min' => $unitPrice . ' ' . __('must be at least') . ' 0.',
            'imei_serial.*.required' => $imeiSerial . ' ' . __('number is required for this product type.'),
            'item_note.*.max' => $itemNote . ' ' . __('may not be greater than') . ' 500 ' . __('characters.'),
            'payments.*.payment_id.required_with' => __('Payment_Method') . ' ' . __('is required.'),
            'payments.*.payment_id.exists' => __('The selected') . ' ' . __('Payment_Method') . ' ' . __('is invalid.'),
            'payments.*.amount.required_with' => __('Amount') . ' ' . __('is required.'),
            'payments.*.amount.min' => __('Amount') . ' ' . __('must be at least') . ' 0.01.',
            'payments.*.note.max' => __('Note') . ' ' . __('may not be greater than') . ' 500 ' . __('characters.'),
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
            'supplier_invoice_no' => __('Supplier_Invoice_No') ?: 'Supplier Invoice No',
            'note' => __('Note'),
            'items' => __('Items') ?: 'Items',
            'quantity' => __('Quantity') ?: 'Quantity',
            'unit_price' => __('Unit_Price') ?: 'Unit Price',
            'imei_serial' => __('IMEI_Serial') ?: 'IMEI/Serial',
            'item_note' => __('Item_Note') ?: 'Item Note',
            'payments.*.payment_id' => __('Payment_Method'),
            'payments.*.amount' => __('Amount'),
            'payments.*.note' => __('Note'),
        ];
    }
}
