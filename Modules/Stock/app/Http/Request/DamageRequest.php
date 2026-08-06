<?php

namespace Modules\Stock\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

class DamageRequest extends FormRequest
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
            'employee_id' => 'required|exists:users,id',
            'total_loss' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*' => 'required|exists:items,id',
            'item_types' => 'nullable|array',
            'item_types.*' => 'nullable|string',
            'expiry_imei_serial' => 'nullable|array',
            'expiry_imei_serial.*' => 'nullable|string|max:255',
            'last_purchase_price' => 'required|array|min:1',
            'last_purchase_price.*' => 'required|numeric|min:0',
            'damage_quantity' => 'required|array|min:1',
            'damage_quantity.*' => 'required|numeric|min:0',
            'loss_amount' => 'nullable|array',
            'loss_amount.*' => 'nullable|numeric|min:0',
            'total_amount' => 'required|array|min:1',
            'total_amount.*' => 'required|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $date = __('Date');
        $employee = __('Employee');
        $totalLoss = __('Total_Loss') ?: 'Total Loss';
        $note = __('Note');
        $items = __('Items');
        $item = __('Item');
        $damageQuantity = __('Damage_Quantity') ?: 'Damage Quantity';
        $lastPurchasePrice = __('Last_Purchase_Price') ?: 'Last Purchase Price';
        $lossAmount = __('Loss_Amount') ?: 'Loss Amount';
        $totalAmount = __('Total_Amount') ?: 'Total Amount';
        $expiryImeiSerial = __('IMEI_Serial_Medicine') ?: 'IMEI/Serial/Medicine';
        $refNum = __('Reference_Number') ?: 'Reference Number';
        
        return [
            'date.required' => $date . ' ' . __('is required.'),
            'date.date' => $date . ' ' . __('must be a valid date.'),
            'reference_no.max' => __('The') . ' ' . $refNum . ' ' . __('may not be greater than') . ' 50 ' . __('characters.'),
            'employee_id.required' => __('The') . ' ' . $employee . ' ' . __('is required.'),
            'employee_id.exists' => __('The selected') . ' ' . $employee . ' ' . __('is invalid.'),
            'total_loss.required' => __('The') . ' ' . $totalLoss . ' ' . __('is required.'),
            'total_loss.numeric' => $totalLoss . ' ' . __('must be a number.'),
            'total_loss.min' => $totalLoss . ' ' . __('must be at least') . ' 0.',
            'note.max' => __('The') . ' ' . $note . ' ' . __('may not be greater than') . ' 1000 ' . __('characters.'),
            'items.required' => __('At least one') . ' ' . strtolower($item) . ' ' . __('is required.'),
            'items.array' => $items . ' ' . __('must be an array.'),
            'items.min' => __('At least one') . ' ' . strtolower($item) . ' ' . __('is required.'),
            'items.*.required' => __('Each') . ' ' . strtolower($item) . ' ' . __('is required.'),
            'items.*.exists' => __('One or more selected') . ' ' . strtolower($items) . ' ' . __('are invalid.'),
            'expiry_imei_serial.*.max' => __('The') . ' ' . $expiryImeiSerial . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'damage_quantity.required' => $damageQuantity . ' ' . __('is required for all') . ' ' . strtolower($items) . '.',
            'damage_quantity.*.required' => __('Each') . ' ' . strtolower($item) . ' ' . __('must have a') . ' ' . strtolower($damageQuantity) . '.',
            'damage_quantity.*.numeric' => $damageQuantity . ' ' . __('must be a number.'),
            'damage_quantity.*.min' => $damageQuantity . ' ' . __('must be at least') . ' 0.',
            'last_purchase_price.required' => $lastPurchasePrice . ' ' . __('is required for all') . ' ' . strtolower($items) . '.',
            'last_purchase_price.*.required' => __('Each') . ' ' . strtolower($item) . ' ' . __('must have a') . ' ' . strtolower($lastPurchasePrice) . '.',
            'last_purchase_price.*.numeric' => $lastPurchasePrice . ' ' . __('must be a number.'),
            'last_purchase_price.*.min' => $lastPurchasePrice . ' ' . __('must be at least') . ' 0.',
            'loss_amount.*.numeric' => $lossAmount . ' ' . __('must be a number.'),
            'loss_amount.*.min' => $lossAmount . ' ' . __('must be at least') . ' 0.',
            'total_amount.required' => $totalAmount . ' ' . __('is required for all') . ' ' . strtolower($items) . '.',
            'total_amount.*.required' => __('Each') . ' ' . strtolower($item) . ' ' . __('must have a') . ' ' . strtolower($totalAmount) . '.',
            'total_amount.*.numeric' => $totalAmount . ' ' . __('must be a number.'),
            'total_amount.*.min' => $totalAmount . ' ' . __('must be at least') . ' 0.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'date' => __('Date'),
            'reference_no' => __('Reference_Number') ?: 'Reference Number',
            'employee_id' => __('Employee'),
            'total_loss' => __('Total_Loss') ?: 'Total Loss',
            'note' => __('Note'),
            'items' => __('Items'),
            'damage_quantity' => __('Damage_Quantity') ?: 'Damage Quantity',
            'last_purchase_price' => __('Last_Purchase_Price') ?: 'Last Purchase Price',
            'loss_amount' => __('Loss_Amount') ?: 'Loss Amount',
            'total_amount' => __('Total_Amount') ?: 'Total Amount',
            'expiry_imei_serial' => __('IMEI_Serial_Medicine') ?: 'IMEI/Serial/Medicine',
        ];
    }
}

