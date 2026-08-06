<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class MultipleCurrencyRequest extends BaseRequest
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
        // Get the encrypted ID from the route parameter
        $encryptedId = $this->route('multiple_currency');
        $currencyId = null;
        
        // If we have an encrypted ID, decrypt it to get the actual currency ID
        if ($encryptedId) {
            try {
                $currencyId = decrypt($encryptedId);
                $currencyId = (int) $currencyId;
            } catch (\Exception $e) {
                $currencyId = null;
            }
        }
        
        $companyId = session('company.company_id');

        return [
            'currency' => [
                'required',
                'string',
                'max:55',
                Rule::unique('multiple_currencies')->ignore($currencyId)->where('company_id', $companyId)->where('del_status', 'Live')
            ],
            'conversion_rate' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $currency = __('Currency');
        $conversionRate = __('Conversion Rate');
        
        return [
            'currency.required' => __('The') . ' ' . $currency . ' ' . __('is required.'),
            'currency.string' => __('The') . ' ' . $currency . ' ' . __('must be a valid string.'),
            'currency.max' => __('The') . ' ' . $currency . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'currency.unique' => __('A Currency with this name already exists for your company.'),
            'conversion_rate.required' => __('The') . ' ' . $conversionRate . ' ' . __('is required.'),
            'conversion_rate.numeric' => __('The') . ' ' . $conversionRate . ' ' . __('must be a valid number.'),
            'conversion_rate.min' => __('The') . ' ' . $conversionRate . ' ' . __('must be at least') . ' 0.',
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
            'currency' => __('Currency'),
            'conversion_rate' => __('Conversion Rate'),
        ];
    }
}

