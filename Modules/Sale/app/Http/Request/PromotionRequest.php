<?php

namespace Modules\Sale\Http\Request;

use App\Http\Requests\BaseRequest;

class PromotionRequest extends BaseRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'type' => ['required'],
            'title' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'string', 'max:55'],
            'end_date' => ['required', 'string', 'max:55'],
            'status' => ['required'],
            'start_time' => ['nullable', 'string', 'max:10'],
            'end_time' => ['nullable', 'string', 'max:10'],
            'min_purchase_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'scheme_basis' => ['nullable', 'string'],
            'applicable_customer_types' => ['nullable', 'array'],
            'bill_level_discount' => ['nullable', 'numeric', 'min:0'],
            'tier_percentages' => ['nullable', 'string'],
            'flavour_alternatives' => ['nullable', 'string'],
        ];

        // Type can be integer (1,2,3) or string (Discount, Coupon Discount, Free Item)
        $type = $this->type;
        $typeInt = is_numeric($type) ? (int) $type : 0;
        if (!$typeInt) {
            // Map string type to integer
            $typeInt = match(strtolower(trim($type ?? ''))) {
                'discount' => 1,
                'coupon discount', 'coupon discount (on entire sale)', 'coupon' => 2,
                'free item', 'free_quantity', 'buy x get y' => 3,
                default => 1,
            };
        }

        if ($typeInt === 1) {
            $rules['item_id'] = ['nullable', 'integer'];
            $rules['discount'] = ['required', 'string', 'max:55'];
        } elseif ($typeInt === 2) {
            $rules['discount'] = ['required', 'string', 'max:55'];
            $rules['coupon_code'] = ['nullable', 'string', 'max:50'];
        } elseif ($typeInt === 3) {
            $rules['item_id'] = ['required', 'integer'];
            $rules['qty'] = ['required', 'integer'];
            $rules['get_item_id'] = ['required', 'integer'];
            $rules['get_qty'] = ['required', 'integer'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $type = __('Type');
        $title = __('Title');
        $startDate = __('Start_Date') ?: 'Start Date';
        $endDate = __('End_Date') ?: 'End Date';
        $status = __('Status');
        $item = __('Item');
        $discount = __('Discount');
        $couponCode = __('Coupon_Code') ?: 'Coupon Code';
        $quantity = __('Quantity');
        $getItem = __('Get_Item') ?: 'Get Item';
        $getQuantity = __('Get_Quantity') ?: 'Get Quantity';
        
        return [
            'type.required' => __('The') . ' ' . $type . ' ' . __('is required.'),
            'type.integer' => $type . ' ' . __('must be a whole number.'),
            'title.required' => __('The') . ' ' . $title . ' ' . __('is required.'),
            'title.max' => __('The') . ' ' . $title . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'start_date.required' => __('The') . ' ' . $startDate . ' ' . __('is required.'),
            'start_date.max' => __('The') . ' ' . $startDate . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'end_date.required' => __('The') . ' ' . $endDate . ' ' . __('is required.'),
            'end_date.max' => __('The') . ' ' . $endDate . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'status.required' => $status . ' ' . __('is required.'),
            'status.in' => __('The') . ' ' . $status . ' ' . __('must be either') . ' 1 ' . __('or') . ' 2.',
            'item_id.required' => __('The') . ' ' . $item . ' ' . __('is required.'),
            'item_id.integer' => $item . ' ' . __('must be a whole number.'),
            'discount.required' => __('The') . ' ' . $discount . ' ' . __('is required.'),
            'discount.max' => __('The') . ' ' . $discount . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'discount.regex' => __('The') . ' ' . $discount . ' ' . __('must be a number (e.g. 10) or percentage (e.g. 10%) only.'),
            'coupon_code.required' => __('The') . ' ' . $couponCode . ' ' . __('is required.'),
            'coupon_code.max' => __('The') . ' ' . $couponCode . ' ' . __('may not be greater than') . ' 50 ' . __('characters.'),
            'qty.required' => __('The') . ' ' . $quantity . ' ' . __('is required.'),
            'qty.integer' => $quantity . ' ' . __('must be a whole number.'),
            'get_item_id.required' => __('The') . ' ' . $getItem . ' ' . __('is required.'),
            'get_item_id.integer' => $getItem . ' ' . __('must be a whole number.'),
            'get_qty.required' => __('The') . ' ' . $getQuantity . ' ' . __('is required.'),
            'get_qty.integer' => $getQuantity . ' ' . __('must be a whole number.'),
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
            'type' => __('Type'),
            'title' => __('Title'),
            'start_date' => __('Start_Date') ?: 'Start Date',
            'end_date' => __('End_Date') ?: 'End Date',
            'status' => __('Status'),
            'item_id' => __('Item'),
            'discount' => __('Discount'),
            'coupon_code' => __('Coupon_Code') ?: 'Coupon Code',
            'qty' => __('Quantity'),
            'get_item_id' => __('Get_Item') ?: 'Get Item',
            'get_qty' => __('Get_Quantity') ?: 'Get Quantity',
        ];
    }
}

