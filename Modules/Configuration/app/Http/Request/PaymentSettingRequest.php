<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;

class PaymentSettingRequest extends BaseRequest
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
            'action_type_stripe' => ['required', 'string', 'in:Enable,Disable'],
            'action_type_paypal' => ['required', 'string', 'in:Enable,Disable'],
            'stripe_api_key' => ['required_if:action_type_stripe,Enable', 'string', 'max:255'],
            'stripe_publishable_key' => ['required_if:action_type_stripe,Enable', 'string', 'max:255'],
            'paypal_user_name' => ['required_if:action_type_paypal,Enable', 'string', 'max:255'],
            'paypal_password' => ['required_if:action_type_paypal,Enable', 'string', 'max:255'],
            'paypal_signature' => ['required_if:action_type_paypal,Enable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $stripeStatus = __('Stripe Enable Status');
        $paypalStatus = __('PayPal Enable Status');
        $stripeApiKey = __('Stripe API Key');
        $stripePublishableKey = __('Stripe Publishable Key');
        $paypalUsername = __('PayPal Username');
        $paypalPassword = __('PayPal Password');
        $paypalSignature = __('PayPal Signature');
        
        return [
            'action_type_stripe.required' => $stripeStatus . ' ' . __('is required.'),
            'action_type_stripe.in' => __('Invalid') . ' ' . strtolower($stripeStatus) . ' ' . __('selected'),
            'action_type_paypal.required' => $paypalStatus . ' ' . __('is required.'),
            'action_type_paypal.in' => __('Invalid') . ' ' . strtolower($paypalStatus) . ' ' . __('selected'),
            'stripe_api_key.required_if' => $stripeApiKey . ' ' . __('is required when Stripe is enabled'),
            'stripe_publishable_key.required_if' => $stripePublishableKey . ' ' . __('is required when Stripe is enabled'),
            'paypal_user_name.required_if' => $paypalUsername . ' ' . __('is required when PayPal is enabled'),
            'paypal_password.required_if' => $paypalPassword . ' ' . __('is required when PayPal is enabled'),
            'paypal_signature.required_if' => $paypalSignature . ' ' . __('is required when PayPal is enabled'),
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
            'action_type_stripe' => __('Stripe Enable Status'),
            'action_type_paypal' => __('PayPal Enable Status'),
            'stripe_api_key' => __('Stripe API Key'),
            'stripe_publishable_key' => __('Stripe Publishable Key'),
            'paypal_user_name' => __('PayPal Username'),
            'paypal_password' => __('PayPal Password'),
            'paypal_signature' => __('PayPal Signature'),
        ];
    }
}

