<?php

namespace Modules\Administrator\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class SalaryRequest extends BaseRequest
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
        // Get the salary ID from the route parameter
        $salaryId = $this->route('salary');
        $companyId = session('company.company_id');
        
        $rules = [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'generated_date' => ['required', 'date'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.employee_id' => ['required', 'integer', 'exists:users,id'],
            'items.*.salary_amount' => ['required', 'numeric', 'min:0'],
            'items.*.overtime_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.overtime_hour' => ['nullable', 'numeric', 'min:0'],
            'items.*.additional_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.deduction_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.absent_day' => ['nullable', 'integer', 'min:0'],
            'items.*.absent_day_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tips' => ['nullable', 'numeric', 'min:0'],
            'items.*.advance_taken' => ['nullable', 'numeric', 'min:0'],
            'items.*.net_salary' => ['required', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];

        // For create requests, reference_no is required and must be unique
        if (!$salaryId) {
            $rules['reference_no'] = ['required', 'string', 'max:50', 'unique:salaries,reference_no'];
        } else {
            // For update requests, reference_no must be unique except for the current record
            try {
                $decryptedId = decrypt($salaryId);
                $rules['reference_no'] = ['required', 'string', 'max:50', 'unique:salaries,reference_no,'.$decryptedId];
            } catch (\Exception $e) {
                $rules['reference_no'] = ['required', 'string', 'max:50', 'unique:salaries,reference_no'];
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
        $year = __('Year') ?: 'Year';
        $month = __('Month') ?: 'Month';
        $generatedDate = __('Generated_Date') ?: 'Generation Date';
        $totalAmount = __('Total_Amount') ?: 'Total Amount';
        $employee = __('Employee');
        $salaryAmount = __('Salary_Amount') ?: 'Salary Amount';
        $netSalary = __('Net_Salary') ?: 'Net Salary';
        $paymentMethod = __('Payment_Method');
        $amount = __('Amount');
        $refNum = __('Reference_Number') ?: 'Reference Number';
        
        return [
            'year.required' => __('The') . ' ' . $year . ' ' . __('is required.'),
            'year.integer' => __('The') . ' ' . $year . ' ' . __('must be a valid number.'),
            'year.min' => __('The') . ' ' . $year . ' ' . __('must be at least') . ' 2000.',
            'year.max' => __('The') . ' ' . $year . ' ' . __('must not exceed') . ' 2100.',
            'month.required' => __('The') . ' ' . $month . ' ' . __('is required.'),
            'month.integer' => __('The') . ' ' . $month . ' ' . __('must be a valid number.'),
            'month.min' => __('The') . ' ' . $month . ' ' . __('must be between') . ' 1 ' . __('and') . ' 12.',
            'month.max' => __('The') . ' ' . $month . ' ' . __('must be between') . ' 1 ' . __('and') . ' 12.',
            'month.unique' => __('Salary for this month and year combination has already been generated.'),
            'generated_date.required' => __('The') . ' ' . $generatedDate . ' ' . __('is required.'),
            'generated_date.date' => __('The') . ' ' . $generatedDate . ' ' . __('must be a valid date.'),
            'total_amount.required' => __('The') . ' ' . $totalAmount . ' ' . __('is required.'),
            'total_amount.numeric' => __('The') . ' ' . $totalAmount . ' ' . __('must be a valid number.'),
            'items.required' => __('At least one employee salary item is required.'),
            'items.min' => __('At least one employee salary item is required.'),
            'items.*.employee_id.required' => __('Please select an') . ' ' . strtolower($employee) . '.',
            'items.*.employee_id.exists' => __('The selected') . ' ' . strtolower($employee) . ' ' . __('is invalid.'),
            'items.*.salary_amount.required' => __('The') . ' ' . $salaryAmount . ' ' . __('is required.'),
            'items.*.net_salary.required' => __('The') . ' ' . $netSalary . ' ' . __('is required.'),
            'payments.required' => __('At least one') . ' ' . strtolower($paymentMethod) . ' ' . __('is required.'),
            'payments.min' => __('At least one') . ' ' . strtolower($paymentMethod) . ' ' . __('is required.'),
            'payments.*.payment_method_id.required' => __('Please select a') . ' ' . strtolower($paymentMethod) . '.',
            'payments.*.payment_method_id.exists' => __('The selected') . ' ' . strtolower($paymentMethod) . ' ' . __('is invalid.'),
            'payments.*.amount.required' => __('The') . ' ' . __('payment') . ' ' . $amount . ' ' . __('is required.'),
            'payments.*.amount.min' => __('Payment method amount cannot be zero.'),
            'reference_no.required' => __('The') . ' ' . $refNum . ' ' . __('is required.'),
            'reference_no.unique' => __('The') . ' ' . $refNum . ' ' . __('has already been taken.'),
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
            'year' => __('Year') ?: 'Year',
            'month' => __('Month') ?: 'Month',
            'generated_date' => __('Generated_Date') ?: 'Generation Date',
            'total_amount' => __('Total_Amount') ?: 'Total Amount',
            'items' => __('Items') ?: 'Items',
            'items.*.employee_id' => __('Employee'),
            'items.*.salary_amount' => __('Salary_Amount') ?: 'Salary Amount',
            'items.*.net_salary' => __('Net_Salary') ?: 'Net Salary',
            'payments' => __('Payments') ?: 'Payments',
            'payments.*.payment_method_id' => __('Payment_Method'),
            'payments.*.amount' => __('Amount'),
            'reference_no' => __('Reference_Number') ?: 'Reference Number',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $salaryId = $this->route('salary');
            $companyId = session('company.company_id');
            $year = $this->input('year');
            $month = $this->input('month');
            
            // Validate that same month and year combination doesn't exist
            $existingSalary = \Modules\Administrator\Models\Salary::where('year', $year)
                ->where('month', $month)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live');
            
            if ($salaryId) {
                try {
                    $decryptedId = decrypt($salaryId);
                    $existingSalary->where('id', '!=', $decryptedId);
                } catch (\Exception $e) {
                    // If decryption fails, just check without ignoring
                }
            }
            
            if ($existingSalary->exists()) {
                $validator->errors()->add(
                    'month',
                    'Salary for this month and year combination has already been generated.'
                );
            }
            
            // Validate payment total: must equal total amount (not more, not less)
            $totalAmount = (float) $this->input('total_amount', 0);
            $payments = $this->input('payments', []);
            
            $totalPaymentAmount = 0;
            foreach ($payments as $payment) {
                $totalPaymentAmount += floatval($payment['amount'] ?? 0);
            }
            $totalPaymentAmount = round($totalPaymentAmount, 2);
            $totalAmount = round($totalAmount, 2);
            
            if ($totalPaymentAmount > $totalAmount) {
                $validator->errors()->add(
                    'payments',
                    __('The sum of payment amounts') . ' (' . number_format($totalPaymentAmount, 2) . ') ' . __('cannot exceed the total salary amount') . ' (' . number_format($totalAmount, 2) . ').'
                );
            } elseif ($totalPaymentAmount < $totalAmount) {
                $validator->errors()->add(
                    'payments',
                    __('Total payment amount must equal total salary amount') . ' (' . __('Total') . ': ' . number_format($totalAmount, 2) . ', ' . __('Payment total') . ': ' . number_format($totalPaymentAmount, 2) . ').'
                );
            }
        });
    }
}

