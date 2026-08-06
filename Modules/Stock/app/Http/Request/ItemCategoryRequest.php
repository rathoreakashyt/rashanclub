<?php

namespace Modules\Stock\Http\Request;
use App\Http\Requests\BaseRequest;

class ItemCategoryRequest extends BaseRequest
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
        $encryptedId = $this->route('item_category');
        $itemCategoryId = null;

        // If we have an encrypted ID, decrypt it to get the actual item category ID
        if ($encryptedId) {
            try {
                if (is_string($encryptedId)) {
                    $itemCategoryId = decrypt($encryptedId);
                    $itemCategoryId = (int) $itemCategoryId;
                } else {
                    $itemCategoryId = $encryptedId->id ?? null;
                }
            } catch (\Exception $e) {
                $itemCategoryId = null;
            }
        }

        $companyId = session('company.company_id');

        return [
            'name' => [
                'required',
                'string',
                'max:55',
                \Illuminate\Validation\Rule::unique('item_categories')->ignore($itemCategoryId)->where('company_id', $companyId)->where('del_status', 'Live')
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
        $categoryName = __('Category_Name') ?: __('Category') . ' ' . __('Name');
        $description = __('Description');
        
        return [
            'name.required' => __('The') . ' ' . $categoryName . ' ' . __('is required.'),
            'name.unique' => __('A Category with this name already exists.'),
            'name.max' => __('The') . ' ' . $categoryName . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
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
            'name' => __('Category_Name') ?: __('Category') . ' ' . __('Name'),
            'description' => __('Description'),
        ];
    }

}
