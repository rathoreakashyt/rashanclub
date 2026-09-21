<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class BusinessSettingRequest extends BaseRequest
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
            'business_name' => ['required', 'string', 'max:125'],
            'address' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255', 'url'],
            'email' => ['required', 'string', 'email', 'max:55'],
            'phone' => ['required', 'string', 'max:25'],
            'date_format' => ['required', 'string', 'in:d/m/Y,m/d/Y,Y/m/d'],
            'zone_name' => ['required', 'string'],
            'currency' => ['required', 'string', 'max:25'],
            'currency_position' => ['required', 'string', 'in:Before Amount,After Amount'],
            'precision' => ['required', 'integer', 'in:1,2,3'],
            'thousands_separator' => ['required', 'string', Rule::in(['.', ',', 'space'])],
            'decimals_separator' => ['required', 'string', Rule::in(['.', ',', 'space'])],
            'installment_days' => ['required', 'integer', 'in:3,7,15,30'],
            'e_commerce_checker' => ['required', 'string', 'in:Yes,No'],
            'is_loyalty_enable' => ['required', 'string', 'in:Yes,No,Enable,Disable'],
            'minimum_point_to_redeem' => ['required_if:is_loyalty_enable,Enable', 'string', 'max:25'],
            'loyalty_rate' => ['required_if:is_loyalty_enable,Enable', 'string', 'max:25'],
            'product_code_start_from' => ['required', 'string', 'min:1']
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $businessName = __('Business Name');
        $address = __('Address');
        $website = __('Website');
        $email = __('Email');
        $phone = __('Phone');
        $dateFormat = __('Date Format');
        $timeZone = __('Time Zone');
        $currency = __('Currency');
        $currencyPosition = __('Currency Position');
        $precision = __('Precision');
        $thousandSeparator = __('Thousand Separator');
        $decimalSeparator = __('Decimal Separator');
        $installmentDays = __('Installment Days');
        $ecommerceStatus = __('E-commerce Status');
        $loyaltyStatus = __('Loyalty Status');
        $minimumPoint = __('Minimum Point To Redeem');
        $loyaltyRate = __('Loyalty Rate');
        $productCodeStart = __('Product Code Start From');
        
        return [
            'business_name.required' => $businessName . ' ' . __('is required.'),
            'business_name.max' => $businessName . ' ' . __('must not exceed') . ' 125 ' . __('characters.'),
            'address.required' => $address . ' ' . __('is required.'),
            'address.max' => $address . ' ' . __('must not exceed') . ' 255 ' . __('characters.'),
            'website.url' => __('Please enter a valid') . ' ' . __('website URL'),
            'email.required' => $email . ' ' . __('is required.'),
            'email.email' => __('Please enter a valid') . ' ' . __('email address'),
            'email.max' => $email . ' ' . __('must not exceed') . ' 55 ' . __('characters.'),
            'phone.required' => $phone . ' ' . __('Number is required.'),
            'phone.max' => $phone . ' ' . __('Number may not be greater than') . ' 25 ' . __('characters.'),
            'date_format.required' => $dateFormat . ' ' . __('is required.'),
            'date_format.in' => __('Invalid') . ' ' . strtolower($dateFormat) . ' ' . __('selected'),
            'zone_name.required' => $timeZone . ' ' . __('is required.'),
            'zone_name.exists' => __('The selected') . ' ' . strtolower($timeZone) . ' ' . __('is invalid.'),
            'currency.required' => $currency . ' ' . __('is required.'),
            'currency.max' => $currency . ' ' . __('must not exceed') . ' 25 ' . __('characters.'),
            'currency_position.required' => $currencyPosition . ' ' . __('is required.'),
            'currency_position.in' => __('Invalid') . ' ' . strtolower($currencyPosition) . ' ' . __('selected'),
            'precision.required' => $precision . ' ' . __('is required.'),
            'precision.integer' => $precision . ' ' . __('must be a number'),
            'precision.in' => __('Invalid') . ' ' . strtolower($precision) . ' ' . __('selected'),
            'thousands_separator.required' => $thousandSeparator . ' ' . __('is required.'),
            'thousands_separator.in' => __('Invalid') . ' ' . strtolower($thousandSeparator) . ' ' . __('selected'),
            'decimals_separator.required' => $decimalSeparator . ' ' . __('is required.'),
            'decimals_separator.in' => __('Invalid') . ' ' . strtolower($decimalSeparator) . ' ' . __('selected'),
            'installment_days.required' => $installmentDays . ' ' . __('is required.'),
            'installment_days.integer' => $installmentDays . ' ' . __('must be a number'),
            'installment_days.in' => __('Invalid') . ' ' . strtolower($installmentDays) . ' ' . __('selected'),
            'e_commerce_checker.required' => $ecommerceStatus . ' ' . __('is required.'),
            'e_commerce_checker.in' => __('Invalid') . ' ' . strtolower($ecommerceStatus) . ' ' . __('selected'),
            'is_loyalty_enable.required' => $loyaltyStatus . ' ' . __('is required.'),
            'is_loyalty_enable.in' => __('Invalid') . ' ' . strtolower($loyaltyStatus) . ' ' . __('selected'),
            'minimum_point_to_redeem.required_if' => $minimumPoint . ' ' . __('is required when loyalty is enabled'),
            'minimum_point_to_redeem.string' => $minimumPoint . ' ' . __('must be a number'),
            'minimum_point_to_redeem.max' => $minimumPoint . ' ' . __('must not exceed') . ' 25 ' . __('characters.'),
            'loyalty_rate.required_if' => $loyaltyRate . ' ' . __('is required when loyalty is enabled'),
            'loyalty_rate.string' => $loyaltyRate . ' ' . __('must be a number'),
            'loyalty_rate.max' => $loyaltyRate . ' ' . __('must not exceed') . ' 25 ' . __('characters.'),
            'product_code_start_from.required' => $productCodeStart . ' ' . __('is required.'),
            'product_code_start_from.integer' => $productCodeStart . ' ' . __('must be a number'),
            'product_code_start_from.min' => $productCodeStart . ' ' . __('must be at least') . ' 1'
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
            'business_name' => __('Business Name'),
            'address' => __('Address'),
            'website' => __('Website'),
            'email' => __('Email'),
            'phone' => __('Phone'),
            'date_format' => __('Date Format'),
            'zone_name' => __('Time Zone'),
            'currency' => __('Currency'),
            'currency_position' => __('Currency Position'),
            'precision' => __('Precision'),
            'thousands_separator' => __('Thousand Separator'),
            'decimals_separator' => __('Decimal Separator'),
            'installment_days' => __('Installment Days'),
            'e_commerce_checker' => __('E-commerce Status'),
            'is_loyalty_enable' => __('Loyalty Status'),
            'minimum_point_to_redeem' => __('Minimum Point To Redeem'),
            'loyalty_rate' => __('Loyalty Rate'),
            'product_code_start_from' => __('Product Code Start From'),
        ];
    }
}

