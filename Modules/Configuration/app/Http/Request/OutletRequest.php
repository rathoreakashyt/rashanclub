<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;
use App\Rules\GstinRuleForState;
use Illuminate\Validation\Rule;

class OutletRequest extends BaseRequest
{
    /**
     * Whether GST is enabled (State, GSTIN required when true).
     */
    protected function isGstEnabled(): bool
    {
        return session('company.tax_is_gst') === 'Yes';
    }

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
        $encryptedId = $this->route('id');
        $outletId = null;

        if ($encryptedId) {
            try {
                $outletId = decrypt($encryptedId);
                $outletId = (int) $outletId;
            } catch (\Exception $e) {
                $outletId = null;
            }
        }

        $companyId = session('company.company_id');
        $gstRequired = $this->isGstEnabled();
        $stateId = $this->input('state_id') ? (int) $this->input('state_id') : null;

        $rules = [
            'outlet_name' => [
                'required',
                'string',
                'max:55',
                Rule::unique('outlets')->ignore($outletId)->where('company_id', $companyId)->where('del_status', 'Live')
            ],
            'outlet_code' => [
                'required',
                'string',
                'max:55',
                Rule::unique('outlets')->ignore($outletId)->where('company_id', $companyId)->where('del_status', 'Live')
            ],
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|string|max:55|email',
            'active_status' => 'required|in:Active,Inactive',
            'state_id' => [$gstRequired ? 'required' : 'nullable', 'integer', 'exists:states,id'],
            'gstin' => [$gstRequired ? 'required' : 'nullable', 'string', 'size:15', new GstinRuleForState($stateId)],
        ];

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $outletName = __('Outlet Name');
        $outletCode = __('Outlet Code');
        $address = __('Address');
        $phone = __('Phone');
        $email = __('Email');
        $status = __('Status');
        $state = __('State');
        $gstin = __('GSTIN');

        return [
            'outlet_name.required' => __('The') . ' ' . $outletName . ' ' . __('is required.'),
            'outlet_name.unique' => __('An Outlet with this name already exists.'),
            'outlet_name.max' => __('The') . ' ' . $outletName . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'outlet_code.required' => __('The') . ' ' . $outletCode . ' ' . __('is required.'),
            'outlet_code.unique' => __('An Outlet with this Code already exists.'),
            'outlet_code.max' => __('The') . ' ' . $outletCode . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'address.required' => __('The') . ' ' . $address . ' ' . __('is required.'),
            'address.max' => __('The') . ' ' . $address . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'phone.required' => __('The') . ' ' . $phone . ' ' . __('Number is required.'),
            'phone.max' => __('The') . ' ' . $phone . ' ' . __('Number may not be greater than') . ' 30 ' . __('characters.'),
            'email.email' => __('The') . ' ' . $email . ' ' . __('must be a valid email address.'),
            'email.max' => __('The') . ' ' . $email . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'active_status.required' => __('The') . ' ' . $status . ' ' . __('is required.'),
            'active_status.in' => __('The') . ' ' . $status . ' ' . __('must be either') . ' ' . __('Active') . ' ' . __('or') . ' ' . __('Inactive') . '.',
            'state_id.required' => __('The') . ' ' . $state . ' ' . __('is required.'),
            'state_id.exists' => __('The selected') . ' ' . $state . ' ' . __('is invalid.'),
            'gstin.required' => __('The') . ' ' . $gstin . ' ' . __('is required.'),
            'gstin.size' => __('The') . ' ' . $gstin . ' ' . __('must be exactly 15 characters.'),
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
            'outlet_name' => __('Outlet Name'),
            'outlet_code' => __('Outlet Code'),
            'address' => __('Address'),
            'phone' => __('Phone'),
            'email' => __('Email'),
            'active_status' => __('Status'),
            'state_id' => __('State'),
            'gstin' => __('GSTIN'),
        ];
    }
}
