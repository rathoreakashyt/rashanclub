<?php

namespace Modules\Stock\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

class FixedAssetItemRequest extends FormRequest
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
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $assetName = __('Asset_Name') ?: 'Asset Name';
        $assetDescription = __('Asset_Description') ?: 'Asset Description';
        
        return [
            'name.required' => __('The') . ' ' . $assetName . ' ' . __('is required.'),
            'name.string' => $assetName . ' ' . __('must be a valid string.'),
            'name.max' => __('The') . ' ' . $assetName . ' ' . __('may not be greater than') . ' 100 ' . __('characters.'),
            'description.string' => $assetDescription . ' ' . __('must be a valid string.'),
            'description.max' => __('The') . ' ' . $assetDescription . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array 
    {
        return [
            'name' => __('Asset_Name') ?: 'Asset Name',
            'description' => __('Asset_Description') ?: 'Asset Description',
        ];
    }
}

