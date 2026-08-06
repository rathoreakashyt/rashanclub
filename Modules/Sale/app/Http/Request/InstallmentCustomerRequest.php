<?php

namespace Modules\Sale\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class InstallmentCustomerRequest extends BaseRequest
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
        $customerId = $this->route('installment_customer') ? decrypt($this->route('installment_customer')) : null;
        $companyId = session('company.company_id');

        $rules = [
            'name' => [
                'required',
                'string',
                'max:55'
            ],
            'phone' => [
                'required', 
                'string', 
                'max:15',
                Rule::unique('customers')
                    ->ignore($customerId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
            ],
            'email' => ['nullable', 'email', 'max:55',
                Rule::unique('customers')
                    ->ignore($customerId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
            ],
            'address' => ['required', 'string', 'max:255'],
            'permanent_address' => ['required', 'string', 'max:255'],
            'work_address' => ['required', 'string', 'max:255'],
            'customer_nid' => $customerId ? ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'] : ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'photo' => $customerId ? ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'] : ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'g_name' => ['required', 'string', 'max:55'],
            'g_mobile' => ['required', 'string', 'max:15'],
            'g_nid' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'g_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'g_pre_address' => ['required', 'string', 'max:255'],
            'g_work_address' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'string', 'max:25'],
            'date_of_anniversary' => ['nullable', 'string', 'max:25'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'opening_balance_type' => ['nullable', 'in:Debit,Credit'],
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
        $name = __('Name');
        $phone = __('Phone');
        $email = __('Email');
        $address = __('Address');
        $permanentAddress = __('Permanent_Address') ?: 'Permanent Address';
        $workAddress = __('Work_Address') ?: 'Work Address';
        $customerNid = __('Customer_NID') ?: 'Customer NID';
        $customerPhoto = __('Customer_Photo') ?: 'Customer Photo';
        $guarantorName = __('Guarantor_Name') ?: 'Guarantor Name';
        $guarantorMobile = __('Guarantor_Mobile') ?: 'Guarantor Mobile';
        $guarantorNid = __('Guarantor_NID') ?: 'Guarantor NID';
        $guarantorPhoto = __('Guarantor_Photo') ?: 'Guarantor Photo';
        $guarantorPresentAddress = __('Guarantor_Present_Address') ?: 'Guarantor Present Address';
        $guarantorWorkAddress = __('Guarantor_Work_Address') ?: 'Guarantor Work Address';
        $dateOfBirth = __('Date_of_Birth') ?: 'Date of Birth';
        $dateOfAnniversary = __('Date_of_Anniversary') ?: 'Date of Anniversary';
        $openingBalance = __('Opening_Balance') ?: 'Opening Balance';
        $openingBalanceType = __('Opening_Balance_Type') ?: 'Opening Balance Type';
        
        return [
            'name.required' => __('The') . ' ' . $name . ' ' . __('is required.'),
            'name.max' => __('The') . ' ' . $name . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'phone.required' => __('The') . ' ' . $phone . ' ' . __('Number is required.'),
            'phone.unique' => __('A Phone Number with this name already exists.'),
            'phone.max' => __('The') . ' ' . $phone . ' ' . __('Number may not be greater than') . ' 15 ' . __('characters.'),
            'email.email' => __('The') . ' ' . $email . ' ' . __('must be a valid email address.'),
            'email.unique' => __('A Email with this name already exists.'),
            'email.max' => __('The') . ' ' . $email . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'address.required' => __('The') . ' ' . $address . ' ' . __('is required.'),
            'address.max' => __('The') . ' ' . $address . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'permanent_address.required' => __('The') . ' ' . $permanentAddress . ' ' . __('is required.'),
            'permanent_address.max' => __('The') . ' ' . $permanentAddress . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'work_address.required' => __('The') . ' ' . $workAddress . ' ' . __('is required.'),
            'work_address.max' => __('The') . ' ' . $workAddress . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'customer_nid.required' => __('The') . ' ' . $customerNid . ' ' . __('is required.'),
            'customer_nid.image' => $customerNid . ' ' . __('must be an image.'),
            'customer_nid.mimes' => $customerNid . ' ' . __('must be a file of type:') . ' jpeg, png, jpg, gif.',
            'customer_nid.max' => $customerNid . ' ' . __('may not be greater than') . ' 2MB.',
            'photo.required' => __('The') . ' ' . $customerPhoto . ' ' . __('is required.'),
            'photo.image' => $customerPhoto . ' ' . __('must be an image.'),
            'photo.mimes' => $customerPhoto . ' ' . __('must be a file of type:') . ' jpeg, png, jpg, gif.',
            'photo.max' => $customerPhoto . ' ' . __('may not be greater than') . ' 2MB.',
            'g_name.required' => __('The') . ' ' . $guarantorName . ' ' . __('is required.'),
            'g_name.max' => __('The') . ' ' . $guarantorName . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'g_mobile.required' => __('The') . ' ' . $guarantorMobile . ' ' . __('is required.'),
            'g_mobile.max' => __('The') . ' ' . $guarantorMobile . ' ' . __('may not be greater than') . ' 15 ' . __('characters.'),
            'g_pre_address.required' => __('The') . ' ' . $guarantorPresentAddress . ' ' . __('is required.'),
            'g_pre_address.max' => __('The') . ' ' . $guarantorPresentAddress . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'g_work_address.required' => __('The') . ' ' . $guarantorWorkAddress . ' ' . __('is required.'),
            'g_work_address.max' => __('The') . ' ' . $guarantorWorkAddress . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'g_nid.image' => $guarantorNid . ' ' . __('must be an image.'),
            'g_nid.mimes' => $guarantorNid . ' ' . __('must be a file of type:') . ' jpeg, png, jpg, gif.',
            'g_nid.max' => $guarantorNid . ' ' . __('may not be greater than') . ' 2MB.',
            'g_photo.image' => $guarantorPhoto . ' ' . __('must be an image.'),
            'g_photo.mimes' => $guarantorPhoto . ' ' . __('must be a file of type:') . ' jpeg, png, jpg, gif.',
            'g_photo.max' => $guarantorPhoto . ' ' . __('may not be greater than') . ' 2MB.',
            'date_of_birth.max' => __('The') . ' ' . $dateOfBirth . ' ' . __('may not be greater than') . ' 25 ' . __('characters.'),
            'date_of_anniversary.max' => __('The') . ' ' . $dateOfAnniversary . ' ' . __('may not be greater than') . ' 25 ' . __('characters.'),
            'opening_balance.numeric' => $openingBalance . ' ' . __('must be a valid number.'),
            'opening_balance.min' => $openingBalance . ' ' . __('cannot be negative.'),
            'opening_balance_type.in' => __('The') . ' ' . $openingBalanceType . ' ' . __('must be either') . ' ' . __('Debit') . ' ' . __('or') . ' ' . __('Credit') . '.',
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
            'name' => __('Name'),
            'phone' => __('Phone'),
            'email' => __('Email'),
            'address' => __('Address'),
            'permanent_address' => __('Permanent_Address') ?: 'Permanent Address',
            'work_address' => __('Work_Address') ?: 'Work Address',
            'customer_nid' => __('Customer_NID') ?: 'Customer NID',
            'photo' => __('Customer_Photo') ?: 'Customer Photo',
            'g_name' => __('Guarantor_Name') ?: 'Guarantor Name',
            'g_mobile' => __('Guarantor_Mobile') ?: 'Guarantor Mobile',
            'g_nid' => __('Guarantor_NID') ?: 'Guarantor NID',
            'g_photo' => __('Guarantor_Photo') ?: 'Guarantor Photo',
            'g_pre_address' => __('Guarantor_Present_Address') ?: 'Guarantor Present Address',
            'g_work_address' => __('Guarantor_Work_Address') ?: 'Guarantor Work Address',
            'date_of_birth' => __('Date_of_Birth') ?: 'Date of Birth',
            'date_of_anniversary' => __('Date_of_Anniversary') ?: 'Date of Anniversary',
            'opening_balance' => __('Opening_Balance') ?: 'Opening Balance',
            'opening_balance_type' => __('Opening_Balance_Type') ?: 'Opening Balance Type',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $customerId = $this->route('installment_customer') ? decrypt($this->route('installment_customer')) : null;
            
            if ($customerId) {
                $customer = \Modules\Sale\Models\Customer::find($customerId);
                if ($customer) {
                    if (!$this->hasFile('customer_nid') && empty($customer->customer_nid)) {
                        $customerNid = __('Customer_NID') ?: 'Customer NID';
                        $validator->errors()->add('customer_nid', __('The') . ' ' . $customerNid . ' ' . __('is required.'));
                    }
                    if (!$this->hasFile('photo') && empty($customer->photo)) {
                        $customerPhoto = __('Customer_Photo') ?: 'Customer Photo';
                        $validator->errors()->add('photo', __('The') . ' ' . $customerPhoto . ' ' . __('is required.'));
                    }
                }
            }
        });
    }
}

