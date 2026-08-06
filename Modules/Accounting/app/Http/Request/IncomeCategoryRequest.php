<?php

namespace Modules\Accounting\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class IncomeCategoryRequest extends BaseRequest
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
        $encryptedId = $this->route('income');
        $categoryId = null;

        if ($encryptedId) {
            try {
                $categoryId = (int) decrypt($encryptedId);
            } catch (\Exception $e) {
                $categoryId = null;
            }
        }

        $companyId = session('company.company_id');

        return [
            'name' => [
                'required',
                'string',
                'max:55',
                Rule::unique('income_categories')->ignore($categoryId)->where('company_id', $companyId)->where('del_status', 'Live'),
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
        $name = __('Income Category Name');
        $description = __('Description');
        
        return [
            'name.required' => __('The') . ' ' . $name . ' ' . __('is required.'),
            'name.max' => __('The') . ' ' . $name . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'name.regex' => __('The') . ' ' . $name . ' ' . __('format is invalid.'),
            'name.unique' => __('The') . ' ' . $name . ' ' . __('has already been taken.'),
            'description.max' => __('The') . ' ' . $description . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
        ];
    }

    /**
     * Get custom attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Income Category Name'),
            'description' => __('Description'),
        ];
    }
}


