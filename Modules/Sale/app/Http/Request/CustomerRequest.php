<?php

namespace Modules\Sale\Http\Request;

use App\Http\Requests\BaseRequest;
use App\Rules\GstinRuleForState;
use Illuminate\Validation\Rule;

class CustomerRequest extends BaseRequest
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
        // Handle both encrypted ID (from regular customer routes) and regular ID (from POS routes)
        $customerId = null;
        if ($this->route('customer')) {
            // Regular customer route with encrypted ID
            $customerId = decrypt($this->route('customer'));
        } elseif ($this->route('id')) {
            // POS route with regular ID
            $customerId = $this->route('id');
        }
        
        $companyId = session('company.company_id');

        // Validation rules for name
        $nameRules = [
            'required',
            'string',
            'max:55'
        ];

        // If creating a new "Walk-in Customer", check if one already exists for this company
        if (!$customerId && $this->input('name') === 'Walk-in Customer') {
            $nameRules[] = function ($attribute, $value, $fail) use ($companyId) {
                $existingWalkIn = \Modules\Sale\Models\Customer::where('name', 'Walk-in Customer')
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->first();
                
                if ($existingWalkIn) {
                    $fail('A "Walk-in Customer" already exists for this company. Only one "Walk-in Customer" is allowed per company.');
                }
            };
        }

        $rules = [
            'name' => $nameRules,
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
            'address' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'string', 'max:255'],
            'discount' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'string', 'max:25'],
            'customer_type' => ['nullable', 'string', 'max:5'],
            'date_of_anniversary' => ['nullable', 'string', 'max:25'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'opening_balance_type' => ['nullable', 'in:Debit,Credit'],
            'business_type' => ['required', 'in:B2B,B2C'],
        ];

        // Add GST validation rules if GST is enabled
        if (function_exists('isEnableGST') && isEnableGST() && session('company.collect_tax') == 'Yes') {
            $stateId = $this->input('state_id') ? (int) $this->input('state_id') : null;
            // GSTIN required only for B2B; optional for B2C but validated if provided (based on selected state)
            $gstRules = $this->input('business_type') === 'B2B'
                ? ['required', 'string', 'size:15', new GstinRuleForState($stateId)]
                : ['nullable', 'string', 'size:15', new GstinRuleForState($stateId)];
            $rules['gst_number'] = $gstRules;
            $rules['same_or_diff_state'] = ['required', 'string', 'max:5'];
            $rules['state_id'] = ['required', 'exists:states,id'];
        }

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
        $creditLimit = __('Credit_Limit') ?: 'Credit Limit';
        $discount = __('Discount');
        $dateOfBirth = __('Date_of_Birth') ?: 'Date of Birth';
        $customerType = __('Customer_Type') ?: 'Customer Type';
        $dateOfAnniversary = __('Date_of_Anniversary') ?: 'Date of Anniversary';
        $openingBalance = __('Opening_Balance') ?: 'Opening Balance';
        $openingBalanceType = __('Opening_Balance_Type') ?: 'Opening Balance Type';
        $gstin = __('GSTIN') ?: 'GSTIN';
        $sameOrDiffState = __('Same_or_Different_State') ?: 'Same or Different State';
        $state = __('State') ?: 'State';
        $businessType = __('Business_Type') ?: 'Business Type';
        
        return [
            'name.required' => __('The') . ' ' . $name . ' ' . __('is required.'),
            'name.max' => __('The') . ' ' . $name . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'phone.required' => __('The') . ' ' . $phone . ' ' . __('Number is required.'),
            'phone.unique' => __('A Phone Number with this name already exists.'),
            'phone.max' => __('The') . ' ' . $phone . ' ' . __('Number may not be greater than') . ' 15 ' . __('characters.'),
            'email.email' => __('The') . ' ' . $email . ' ' . __('must be a valid email address.'),
            'email.unique' => __('A Email with this name already exists.'),
            'email.max' => __('The') . ' ' . $email . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'address.max' => __('The') . ' ' . $address . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'credit_limit.max' => __('The') . ' ' . $creditLimit . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'discount.max' => __('The') . ' ' . $discount . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'date_of_birth.max' => __('The') . ' ' . $dateOfBirth . ' ' . __('may not be greater than') . ' 25 ' . __('characters.'),
            'customer_type.max' => __('The') . ' ' . $customerType . ' ' . __('may not be greater than') . ' 5 ' . __('characters.'),
            'date_of_anniversary.max' => __('The') . ' ' . $dateOfAnniversary . ' ' . __('may not be greater than') . ' 25 ' . __('characters.'),
            'opening_balance.numeric' => $openingBalance . ' ' . __('must be a valid number.'),
            'opening_balance.min' => $openingBalance . ' ' . __('cannot be negative.'),
            'opening_balance_type.in' => __('The') . ' ' . $openingBalanceType . ' ' . __('must be either') . ' ' . __('Debit') . ' ' . __('or') . ' ' . __('Credit') . '.',
            'business_type.required' => $businessType . ' ' . __('is required.'),
            'business_type.in' => __('The') . ' ' . $businessType . ' ' . __('must be either') . ' B2B ' . __('or') . ' B2C.',
            'gst_number.required' => $gstin . ' ' . __('is required.'),
            'gst_number.size' => __('The') . ' ' . $gstin . ' ' . __('must be exactly 15 characters.'),
            'same_or_diff_state.required' => $sameOrDiffState . ' ' . __('is required.'),
            'same_or_diff_state.max' => __('The') . ' ' . $sameOrDiffState . ' ' . __('may not be greater than') . ' 5 ' . __('characters.'),
            'state_id.required' => $state . ' ' . __('is required.'),
            'state_id.exists' => __('The :attribute must be a valid state.'),
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
            'credit_limit' => __('Credit_Limit') ?: 'Credit Limit',
            'discount' => __('Discount'),
            'date_of_birth' => __('Date_of_Birth') ?: 'Date of Birth',
            'customer_type' => __('Customer_Type') ?: 'Customer Type',
            'date_of_anniversary' => __('Date_of_Anniversary') ?: 'Date of Anniversary',
            'opening_balance' => __('Opening_Balance') ?: 'Opening Balance',
            'opening_balance_type' => __('Opening_Balance_Type') ?: 'Opening Balance Type',
            'business_type' => __('Business_Type') ?: 'Business Type',
            'gst_number' => __('GSTIN') ?: 'GSTIN',
            'same_or_diff_state' => __('Same_or_Different_State') ?: 'Same or Different State',
            'state_id' => __('State') ?: 'State',
        ];
    }
}

