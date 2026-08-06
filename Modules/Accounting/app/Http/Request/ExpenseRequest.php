<?php

namespace Modules\Accounting\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends BaseRequest
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
        $encryptedId = $this->route('expense');
        $expenseId = null;

        if ($encryptedId) {
            try {
                $expenseId = (int) decrypt($encryptedId);
            } catch (\Exception $e) {
                $expenseId = null;
            }
        }

        $companyId = session('company.company_id');

        return [
            'reference_no' => [
                'required',
                'string',
                'max:55',
                Rule::unique('expenses')->ignore($expenseId)->where('company_id', $companyId)->where('del_status', 'Live'),
            ],
            'date' => 'required|string|max:25',
            'category_id' => 'required|integer|exists:expense_categories,id',
            'employee_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:255',
            'payment_method_id' => 'required|integer|exists:payment_methods,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $refNum = __('Reference_Number') ?: 'Reference Number';
        $date = __('Date');
        $category = __('Expense_Category') ?: __('Category');
        $employee = __('Employee');
        $amount = __('Amount');
        $note = __('Note');
        $paymentMethod = __('Payment_Method');
        
        return [
            'reference_no.required' => __('The') . ' ' . $refNum . ' ' . __('is required.'),
            'reference_no.unique' => __('The') . ' ' . $refNum . ' ' . __('has already been taken.'),
            'reference_no.max' => __('The') . ' ' . $refNum . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'date.required' => $date . ' ' . __('is required.'),
            'date.max' => __('The') . ' ' . $date . ' ' . __('may not be greater than') . ' 25 ' . __('characters.'),
            'category_id.required' => __('The') . ' ' . $category . ' ' . __('is required.'),
            'category_id.exists' => __('The selected') . ' ' . $category . ' ' . __('is invalid.'),
            'employee_id.required' => __('The') . ' ' . $employee . ' ' . __('is required.'),
            'employee_id.exists' => __('The selected') . ' ' . $employee . ' ' . __('is invalid.'),
            'amount.required' => __('The') . ' ' . $amount . ' ' . __('is required.'),
            'amount.numeric' => __('The') . ' ' . $amount . ' ' . __('must be a number.'),
            'amount.min' => __('The') . ' ' . $amount . ' ' . __('must be at least') . ' 0.01.',
            'note.max' => __('The') . ' ' . $note . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'payment_method_id.required' => __('The') . ' ' . $paymentMethod . ' ' . __('is required.'),
            'payment_method_id.exists' => __('The selected') . ' ' . $paymentMethod . ' ' . __('is invalid.'),
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
            'reference_no' => __('Reference_Number') ?: 'Reference Number',
            'date' => __('Date'),
            'category_id' => __('Expense_Category') ?: __('Category'),
            'amount' => __('Amount'),
            'note' => __('Note'),
            'employee_id' => __('Employee'),
            'payment_method_id' => __('Payment_Method'),
        ];
    }
}


