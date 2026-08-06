<?php

namespace Modules\Stock\Http\Request;
use App\Http\Requests\BaseRequest;

class UnitRequest extends BaseRequest
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
        $encryptedId = $this->route('unit');
        $unitId = null;
        
        // If we have an encrypted ID, decrypt it to get the actual unit ID
        if ($encryptedId) {
            try {
                if (is_string($encryptedId)) {
                    $unitId = decrypt($encryptedId);
                    $unitId = (int) $unitId;
                } else {
                    $unitId = $encryptedId->id ?? null;
                }
            } catch (\Exception $e) {
                $unitId = null;
            }
        }
        
        $companyId = session('company.company_id');

        return [
            'unit_name' => [
                'required',
                'string',
                'max:55',
                \Illuminate\Validation\Rule::unique('units')->ignore($unitId)->where('company_id', $companyId)->where('del_status', 'Live')
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
        $unitName = __('Unit_Name') ?: __('Unit') . ' ' . __('Name');
        $description = __('Description');
        
        return [
            'unit_name.required' => __('The') . ' ' . $unitName . ' ' . __('is required.'),
            'unit_name.unique' => __('A Unit with this name already exists.'),
            'unit_name.max' => __('The') . ' ' . $unitName . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
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
            'unit_name' => __('Unit_Name') ?: __('Unit') . ' ' . __('Name'),
            'description' => __('Description'),
            'status' => __('Status'),
        ];
    }
}
