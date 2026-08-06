<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;

class PosSettingRequest extends BaseRequest
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
            'allow_less_sale' => ['required', 'string', 'in:Yes,No'],
            'default_customer' => ['required', 'exists:customers,id'],
            'default_payment' => ['required', 'exists:payment_methods,id'],
            'pos_total_payable_type' => ['required', 'string', 'in:0,1,0.05,0.01,0.5'],
            'default_cursor_position' => ['required', 'string', 'in:Search Box,Barcode Box'],
            'product_display' => ['required', 'string', 'in:Image View,Box View'],
            'onscreen_keyboard_status' => ['required', 'string', 'in:Enable,Disable'],
            'grocery_experience' => ['required', 'string', 'in:Regular,Medicine,Grocery'],
            'direct_cart' => ['required', 'string', 'in:Yes,No']
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $allowLessSale = __('Allow Less Sale');
        $defaultCustomer = __('Default Customer');
        $defaultPayment = __('Default Payment');
        $posTotalPayableType = __('POS Total Payable Type');
        $defaultCursorPosition = __('Default Cursor Position');
        $productDisplay = __('Product Display');
        $onscreenKeyboardStatus = __('Onscreen Keyboard Status');
        $groceryExperience = __('Grocery Experience');
        $directCart = __('Direct Cart');
        
        return [
            'allow_less_sale.required' => $allowLessSale . ' ' . __('is required.'),
            'allow_less_sale.in' => __('Invalid') . ' ' . strtolower($allowLessSale) . ' ' . __('option selected'),
            'default_customer.required' => $defaultCustomer . ' ' . __('is required.'),
            'default_customer.exists' => __('The selected') . ' ' . strtolower(__('Customer')) . ' ' . __('is invalid.'),
            'default_payment.required' => $defaultPayment . ' ' . __('is required.'),
            'default_payment.exists' => __('The selected') . ' ' . strtolower(__('Payment_Method')) . ' ' . __('is invalid.'),
            'pos_total_payable_type.required' => $posTotalPayableType . ' ' . __('is required.'),
            'pos_total_payable_type.in' => __('Invalid') . ' ' . strtolower($posTotalPayableType) . ' ' . __('selected'),
            'default_cursor_position.required' => $defaultCursorPosition . ' ' . __('is required.'),
            'default_cursor_position.in' => __('Invalid') . ' ' . strtolower($defaultCursorPosition) . ' ' . __('selected'),
            'product_display.required' => $productDisplay . ' ' . __('is required.'),
            'product_display.in' => __('Invalid') . ' ' . strtolower($productDisplay) . ' ' . __('selected'),
            'onscreen_keyboard_status.required' => $onscreenKeyboardStatus . ' ' . __('is required.'),
            'onscreen_keyboard_status.in' => __('Invalid') . ' ' . strtolower(__('keyboard status')) . ' ' . __('selected'),
            'grocery_experience.required' => $groceryExperience . ' ' . __('is required.'),
            'grocery_experience.in' => __('Invalid') . ' ' . strtolower($groceryExperience) . ' ' . __('selected'),
            'direct_cart.required' => $directCart . ' ' . __('is required.'),
            'direct_cart.in' => __('Invalid') . ' ' . strtolower($directCart) . ' ' . __('option selected')
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
            'allow_less_sale' => __('Allow Less Sale'),
            'default_customer' => __('Default Customer'),
            'default_payment' => __('Default Payment'),
            'pos_total_payable_type' => __('POS Total Payable Type'),
            'default_cursor_position' => __('Default Cursor Position'),
            'product_display' => __('Product Display'),
            'onscreen_keyboard_status' => __('Onscreen Keyboard Status'),
            'grocery_experience' => __('Grocery Experience'),
            'direct_cart' => __('Direct Cart'),
        ];
    }
}

