<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class CounterRequest extends BaseRequest
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
        $encryptedId = $this->route('counter');
        $counterId = null;
        
        // If we have an encrypted ID, decrypt it to get the actual counter ID
        if ($encryptedId) {
            try {
                $counterId = decrypt($encryptedId);
                $counterId = (int) $counterId;
            } catch (\Exception $e) {
                $counterId = null;
            }
        }
        
        $companyId = session('company.company_id');

        return [
            'name' => [
                'required',
                'string',
                'max:55',
                Rule::unique('counters')->ignore($counterId)->where('company_id', $companyId)->where('del_status', 'Live')
            ],
            'outlet_id' => [
                'required',
                'integer',
                Rule::exists('outlets', 'id')->where('company_id', $companyId)->where('del_status', 'Live')
            ],
            'printer_id' => [
                'nullable',
                'integer',
                Rule::exists('printers', 'id')->where('company_id', $companyId)->where('del_status', 'Live')
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
        $counterName = __('Counter Name');
        $outlet = __('Outlet');
        $printer = __('Printer');
        $description = __('Description');
        
        return [
            'name.required' => __('The') . ' ' . $counterName . ' ' . __('is required.'),
            'name.string' => __('The') . ' ' . $counterName . ' ' . __('must be a valid string.'),
            'name.max' => __('The') . ' ' . $counterName . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'name.unique' => __('A Counter with this name already exists for your company.'),
            'outlet_id.required' => __('The') . ' ' . $outlet . ' ' . __('is required.'),
            'outlet_id.integer' => __('The') . ' ' . $outlet . ' ' . __('must be a valid selection.'),
            'outlet_id.exists' => __('The selected') . ' ' . $outlet . ' ' . __('does not exist or is not available.'),
            'printer_id.integer' => __('The') . ' ' . $printer . ' ' . __('must be a valid selection.'),
            'printer_id.exists' => __('The selected') . ' ' . $printer . ' ' . __('does not exist or is not available.'),
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
            'name' => __('Counter_Name') ?: 'Counter Name',
            'outlet_id' => __('Outlet'),
            'printer_id' => __('Printer') ?: 'Printer',
            'description' => __('Description'),
        ];
    }
}

