<?php

namespace Modules\Stock\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends BaseRequest
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
        $encryptedId = $this->route('brand');
        $brandId = null;
        
        // If we have an encrypted ID, decrypt it to get the actual brand ID
        if ($encryptedId) {
            try {
                if (is_string($encryptedId)) {
                    $brandId = decrypt($encryptedId);
                    $brandId = (int) $brandId;
                } else {
                    $brandId = $encryptedId->id ?? null;
                }
            } catch (\Exception $e) {
                $brandId = null;
            }
        }
        
        $companyId = session('company.company_id');

        return [
            'name' => [
                'required',
                'string',
                'max:55',
                \Illuminate\Validation\Rule::unique('brands')->ignore($brandId)->where('company_id', $companyId)->where('del_status', 'Live')
            ],
            'description' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $brandName = __('Brand_Name') ?: __('Brand') . ' ' . __('Name');
        $description = __('Description');
        
        return [
            'name.required' => __('The') . ' ' . $brandName . ' ' . __('is required.'),
            'name.unique' => __('A Brand with this name already exists.'),
            'name.max' => __('The') . ' ' . $brandName . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'description.max' => __('The') . ' ' . $description . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
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
            'name' => __('Brand_Name') ?: __('Brand') . ' ' . __('Name'),
            'description' => __('Description'),
        ];
    }
}
