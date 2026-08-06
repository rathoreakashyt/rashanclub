<?php

namespace Modules\Stock\Http\Request;

use App\Http\Requests\BaseRequest;

class VariationRequest extends BaseRequest
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
        $encryptedId = $this->route('variation_attribute');
        $variationId = null;
        
        // If we have an encrypted ID, decrypt it to get the actual variation ID
        if ($encryptedId) {
            try {
                if (is_string($encryptedId)) {
                    $variationId = decrypt($encryptedId);
                    $variationId = (int) $variationId;
                } else {
                    $variationId = $encryptedId->id ?? null;
                }
            } catch (\Exception $e) {
                $variationId = null;
            }
        }
        
        $companyId = session('company.company_id');

        return [
            'variation_name' => [
                'required',
                'string',
                'max:55',
                \Illuminate\Validation\Rule::unique('variations')->ignore($variationId)->where('company_id', $companyId)->where('del_status', 'Live')
            ],
            'variation_value' => ['required', 'array', 'distinct'],
            'variation_value.*' => ['required', 'string', 'max:55'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $variation_value = $this->input('variation_value', []);
            if (!empty($variation_value)) {
                // Check for duplicate values
                if (count($variation_value) !== count(array_unique($variation_value))) {
                    $variationValue = __('Variation_Value') ?: 'Variation Value';
                    $validator->errors()->add('variation_value', $variationValue . ' ' . __('must be unique.'));
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $variationName = __('Variation_Name') ?: 'Variation Name';
        $variationValue = __('Variation_Value') ?: 'Variation Value';
        
        return [
            'variation_name.required' => __('The') . ' ' . $variationName . ' ' . __('is required.'),
            'variation_name.unique' => __('A Variation Attribute with this name already exists.'),
            'variation_name.max' => __('The') . ' ' . $variationName . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'variation_value.required' => __('At least one') . ' ' . strtolower($variationValue) . ' ' . __('is required.'),
            'variation_value.array' => $variationValue . ' ' . __('must be an array.'),
            'variation_value.distinct' => $variationValue . ' ' . __('must be unique.'),
            'variation_value.*.required' => __('Each') . ' ' . strtolower($variationValue) . ' ' . __('is required.'),
            'variation_value.*.max' => __('Each') . ' ' . strtolower($variationValue) . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
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
            'variation_name' => __('Variation_Name') ?: 'Variation Name',
            'variation_value' => __('Variation_Value') ?: 'Variation Value',
        ];
    }
}

