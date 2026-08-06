<?php

namespace Modules\Purchase\Http\Requests;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends BaseRequest
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
        $supplierId = $this->route('supplier') ? decrypt($this->route('supplier')) : null;
        $companyId = session('company.company_id');

        return [
            'name' => [
                'required',
                'string',
                'max:55'
            ],
            'contact_person' => ['required', 'string', 'max:55'],
            'phone' => ['required', 'string', 'max:55'],
            'email' => ['nullable', 'email', 'max:55'],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'opening_balance_type' => ['nullable', 'in:Debit,Credit'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $supplierName = __('Supplier_Name') ?: __('Supplier') . ' ' . __('Name');
        $contactPerson = __('Contact_Person') ?: 'Contact Person';
        $phone = __('Phone');
        $email = __('Email');
        $address = __('Address');
        $description = __('Description');
        $openingBalance = __('Opening_Balance') ?: 'Opening Balance';
        $openingBalanceType = __('Opening_Balance_Type') ?: 'Opening Balance Type';
        
        return [
            'name.required' => __('The') . ' ' . $supplierName . ' ' . __('is required.'),
            'name.unique' => __('A supplier with this name already exists.'),
            'name.max' => __('The') . ' ' . __('supplier name') . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'contact_person.required' => __('The') . ' ' . $contactPerson . ' ' . __('is required.'),
            'contact_person.max' => __('The') . ' ' . $contactPerson . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'phone.required' => __('The') . ' ' . $phone . ' ' . __('Number is required.'),
            'phone.max' => __('The') . ' ' . $phone . ' ' . __('Number may not be greater than') . ' 55 ' . __('characters.'),
            'email.email' => __('The') . ' ' . $email . ' ' . __('must be a valid email address.'),
            'email.max' => __('The') . ' ' . $email . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'address.max' => __('The') . ' ' . $address . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'description.max' => __('The') . ' ' . $description . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'opening_balance.numeric' => $openingBalance . ' ' . __('must be a valid number.'),
            'opening_balance.min' => $openingBalance . ' ' . __('cannot be negative.'),
            'opening_balance.max' => $openingBalance . ' ' . __('may not be greater than') . ' 999999999.999.',
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
            'name' => __('Supplier_Name') ?: __('Supplier') . ' ' . __('Name'),
            'contact_person' => __('Contact_Person') ?: 'Contact Person',
            'phone' => __('Phone'),
            'email' => __('Email'),
            'address' => __('Address'),
            'description' => __('Description'),
            'opening_balance' => __('Opening_Balance') ?: 'Opening Balance',
            'opening_balance_type' => __('Opening_Balance_Type') ?: 'Opening Balance Type',
        ];
    }
}

