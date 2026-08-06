<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;

class TaxSettingRequest extends BaseRequest
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
        $rules = [
            'collect_tax' => ['required', 'string', 'in:Yes,No'],
        ];

        // Optional fields when collect_tax is Yes
        if ($this->collect_tax === 'Yes') {
            $rules['tax_title'] = ['nullable', 'string', 'max:55'];
            $rules['tax_registration_no'] = ['nullable', 'string', 'max:55'];
            $rules['tax_is_gst'] = ['nullable', 'string', 'in:Yes,No'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'collect_tax.required' => __('Collect Tax') . ' ' . __('is required.'),
            'collect_tax.in' => __('Invalid') . ' ' . strtolower(__('Collect Tax')) . ' ' . __('option selected'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'collect_tax' => __('Collect Tax'),
            'tax_title' => __('Tax Title'),
            'tax_registration_no' => __('Tax Registration No'),
            'tax_is_gst' => __('Tax Is GST'),
        ];
    }
}
