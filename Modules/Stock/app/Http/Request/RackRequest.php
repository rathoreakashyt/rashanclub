<?php

namespace Modules\Stock\Http\Request;

use App\Http\Requests\BaseRequest;

class RackRequest extends BaseRequest
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
        $encryptedId = $this->route('rack');
        $rackId = null;
        
        // If we have an encrypted ID, decrypt it to get the actual rack ID
        if ($encryptedId) {
            try {
                if (is_string($encryptedId)) {
                    $rackId = decrypt($encryptedId);
                    $rackId = (int) $rackId;
                } else {
                    $rackId = $encryptedId->id ?? null;
                }
            } catch (\Exception $e) {
                $rackId = null;
            }
        }
        
        $companyId = session('company.company_id');

        return [
            'name' => [
                'required',
                'string',
                'max:55',
                \Illuminate\Validation\Rule::unique('racks')->ignore($rackId)->where('company_id', $companyId)->where('del_status', 'Live')
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $rackName = __('Rack_Name') ?: __('Rack') . ' ' . __('Name');
        $description = __('Description');
        
        return [
            'name.required' => __('The') . ' ' . $rackName . ' ' . __('is required.'),
            'name.unique' => __('A Rack with this name already exists.'),
            'name.max' => __('The') . ' ' . $rackName . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
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
            'name' => __('Rack_Name') ?: __('Rack') . ' ' . __('Name'),
            'description' => __('Description'),
        ];
    }
}

