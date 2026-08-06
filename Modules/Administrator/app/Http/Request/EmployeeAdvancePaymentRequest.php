<?php

namespace Modules\Administrator\Http\Request;

use App\Http\Requests\BaseRequest;

class EmployeeAdvancePaymentRequest extends BaseRequest
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
        $advancePaymentId = $this->route('employee_advance_payment');
        $companyId = session('company.company_id');

        $rules = [
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:1000'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'outlet_id' => ['nullable', 'integer'],
        ];

        if (!$advancePaymentId) {
            $rules['reference_no'] = ['required', 'string', 'max:50', 'unique:employee_advance_payments,reference_no'];
        } else {
            try {
                $decryptedId = decrypt($advancePaymentId);
                $rules['reference_no'] = ['required', 'string', 'max:50', 'unique:employee_advance_payments,reference_no,' . $decryptedId];
            } catch (\Exception $e) {
                $rules['reference_no'] = ['required', 'string', 'max:50', 'unique:employee_advance_payments,reference_no'];
            }
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
        $date = __('Date');
        $amount = __('Amount');
        $note = __('Note');
        $paymentMethod = __('Payment_Method');
        $employee = __('Employee');
        $refNo = __('Reference_Number') ?: 'Reference Number';

        return [
            'date.required' => __('The') . ' ' . $date . ' ' . __('is required.'),
            'date.date' => __('The') . ' ' . $date . ' ' . __('must be a valid date.'),
            'amount.required' => __('The') . ' ' . $amount . ' ' . __('is required.'),
            'amount.numeric' => __('The') . ' ' . $amount . ' ' . __('must be a valid number.'),
            'amount.min' => __('The') . ' ' . $amount . ' ' . __('must be at least 0.01.'),
            'payment_method_id.required' => __('Please select a') . ' ' . strtolower($paymentMethod) . '.',
            'payment_method_id.exists' => __('The selected') . ' ' . strtolower($paymentMethod) . ' ' . __('is invalid.'),
            'employee_id.required' => __('Please select an') . ' ' . strtolower($employee) . '.',
            'employee_id.exists' => __('The selected') . ' ' . strtolower($employee) . ' ' . __('is invalid.'),
            'reference_no.required' => __('The') . ' ' . $refNo . ' ' . __('is required.'),
            'reference_no.unique' => __('The') . ' ' . $refNo . ' ' . __('has already been taken.'),
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
            'date' => __('Date'),
            'amount' => __('Amount'),
            'note' => __('Note'),
            'payment_method_id' => __('Payment_Method'),
            'employee_id' => __('Employee'),
            'outlet_id' => __('Outlet'),
            'reference_no' => __('Reference_Number') ?: 'Reference Number',
        ];
    }
}
