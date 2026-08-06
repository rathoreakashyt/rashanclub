<?php

namespace Modules\Stock\Http\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferRequest extends FormRequest
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
        $fromOutletId = $this->input('from_outlet_id');

        return [
            'date' => 'required|date',
            'reference_no' => 'nullable|string|max:50',
            'from_outlet_id' => 'required|exists:outlets,id',
            'to_outlet_id' => [
                'required',
                'exists:outlets,id',
                Rule::notIn([$fromOutletId]),
            ],
            'status' => 'required|in:Draft,Sent,Received',
            'note_for_sender' => 'nullable|string|max:1000',
            'note_for_receiver' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*' => 'required|exists:items,id',
            'item_types' => 'nullable|array',
            'item_types.*' => 'nullable|string',
            'quantity_amount' => 'required|array|min:1',
            'quantity_amount.*' => 'required|integer|min:1',
            'expiry_imei_serial' => 'nullable|array',
            'expiry_imei_serial.*' => 'nullable|string|max:55',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $date = __('Date');
        $fromOutlet = __('From_Outlet') ?: 'From Outlet';
        $toOutlet = __('To_Outlet') ?: 'To Outlet';
        $status = __('Status');
        $items = __('Items');
        $item = __('Item');
        $quantity = __('Quantity');
        $expiryImeiSerial = __('IMEI_Serial_Medicine') ?: 'IMEI/Serial/Medicine';
        $refNum = __('Reference_Number') ?: 'Reference Number';
        $noteForSender = __('Note_for_Sender') ?: 'Note for Sender';
        $noteForReceiver = __('Note_for_Receiver') ?: 'Note for Receiver';
        
        return [
            'date.required' => $date . ' ' . __('is required.'),
            'date.date' => $date . ' ' . __('must be a valid date.'),
            'reference_no.max' => __('The') . ' ' . $refNum . ' ' . __('may not be greater than') . ' 50 ' . __('characters.'),
            'from_outlet_id.required' => __('The') . ' ' . $fromOutlet . ' ' . __('is required.'),
            'from_outlet_id.exists' => __('The selected') . ' ' . strtolower($fromOutlet) . ' ' . __('is invalid.'),
            'to_outlet_id.required' => __('The') . ' ' . $toOutlet . ' ' . __('is required.'),
            'to_outlet_id.exists' => __('The selected') . ' ' . strtolower($toOutlet) . ' ' . __('is invalid.'),
            'to_outlet_id.not_in' => $toOutlet . ' ' . __('must be different from the') . ' ' . strtolower($fromOutlet) . '.',
            'status.required' => __('The') . ' ' . $status . ' ' . __('is required.'),
            'status.in' => __('The') . ' ' . $status . ' ' . __('must be') . ' ' . __('Draft') . ', ' . __('Sent') . ' ' . __('or') . ' ' . __('Received') . '.',
            'note_for_sender.max' => __('The') . ' ' . $noteForSender . ' ' . __('may not be greater than') . ' 1000 ' . __('characters.'),
            'note_for_receiver.max' => __('The') . ' ' . $noteForReceiver . ' ' . __('may not be greater than') . ' 1000 ' . __('characters.'),
            'items.required' => __('At least one') . ' ' . strtolower($item) . ' ' . __('is required.'),
            'items.array' => $items . ' ' . __('must be an array.'),
            'items.min' => __('At least one') . ' ' . strtolower($item) . ' ' . __('is required.'),
            'items.*.required' => __('Each') . ' ' . strtolower($item) . ' ' . __('is required.'),
            'items.*.exists' => __('One or more selected') . ' ' . strtolower($items) . ' ' . __('are invalid.'),
            'quantity_amount.required' => $quantity . ' ' . __('is required for all') . ' ' . strtolower($items) . '.',
            'quantity_amount.*.required' => __('Each') . ' ' . strtolower($item) . ' ' . __('must have a') . ' ' . strtolower($quantity) . '.',
            'quantity_amount.*.integer' => $quantity . ' ' . __('must be a whole number.'),
            'quantity_amount.*.min' => $quantity . ' ' . __('must be at least') . ' 1.',
            'expiry_imei_serial.*.max' => $expiryImeiSerial . ' ' . __('value must not exceed') . ' 55 ' . __('characters.'),
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
            'from_outlet_id' => __('From_Outlet') ?: 'From Outlet',
            'to_outlet_id' => __('To_Outlet') ?: 'To Outlet',
            'status' => __('Status'),
            'note_for_sender' => __('Note_for_Sender') ?: 'Note for Sender',
            'note_for_receiver' => __('Note_for_Receiver') ?: 'Note for Receiver',
            'items' => __('Items'),
            'item_types' => __('Item_Types') ?: 'Item Types',
            'quantity_amount' => __('Quantity'),
            'expiry_imei_serial' => __('IMEI_Serial_Medicine') ?: 'IMEI/Serial/Medicine',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure arrays are properly formatted
        if ($this->has('items') && !is_array($this->items)) {
            $this->merge(['items' => []]);
        }

        if ($this->has('quantity_amount') && !is_array($this->quantity_amount)) {
            $this->merge(['quantity_amount' => []]);
        }

        if ($this->has('expiry_imei_serial') && !is_array($this->expiry_imei_serial)) {
            $this->merge(['expiry_imei_serial' => []]);
        }

        if ($this->has('item_types') && !is_array($this->item_types)) {
            $this->merge(['item_types' => []]);
        }
    }
}
