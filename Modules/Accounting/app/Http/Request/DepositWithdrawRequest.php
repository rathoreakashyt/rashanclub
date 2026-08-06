<?php

namespace Modules\Accounting\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class DepositWithdrawRequest extends BaseRequest
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
        $encryptedId = $this->route('deposit_withdraw');
        $depositWithdrawId = null;
        
        // If we have an encrypted ID, decrypt it to get the actual ID
        if ($encryptedId) {
            try {
                $depositWithdrawId = decrypt($encryptedId);
                $depositWithdrawId = (int) $depositWithdrawId;
            } catch (\Exception $e) {
                $depositWithdrawId = null;
            }
        }
        
        $companyId = session('company.company_id');
        
        return [
            'reference_no' => [
                'required',
                'string',
                'max:55',
                Rule::unique('deposit_withdraws')->ignore($depositWithdrawId)->where('company_id', $companyId)->where('del_status', 'Live')
            ],
            'date' => 'required|string|max:25',
            'type' => 'required|in:Deposit,Withdraw',
            'note' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
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
        $type = __('Type');
        $note = __('Note');
        $amount = __('Amount');
        $paymentMethod = __('Payment_Method');
        
        return [
            'reference_no.required' => __('The') . ' ' . $refNum . ' ' . __('is required.'),
            'reference_no.unique' => __('The') . ' ' . $refNum . ' ' . __('has already been taken.'),
            'reference_no.max' => __('The') . ' ' . $refNum . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'date.required' => __('The') . ' ' . $date . ' ' . __('is required.'),
            'date.max' => __('The') . ' ' . $date . ' ' . __('may not be greater than') . ' 25 ' . __('characters.'),
            'type.required' => __('The') . ' ' . $type . ' ' . __('is required.'),
            'type.in' => __('The') . ' ' . $type . ' ' . __('must be either') . ' ' . __('Deposit') . ' ' . __('or') . ' ' . __('Withdraw') . '.',
            'note.max' => __('The') . ' ' . $note . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'amount.required' => __('The') . ' ' . $amount . ' ' . __('is required.'),
            'amount.numeric' => __('The') . ' ' . $amount . ' ' . __('must be a number.'),
            'amount.min' => __('The') . ' ' . $amount . ' ' . __('must be at least') . ' 0.01.',
            'payment_method_id.required' => __('The') . ' ' . $paymentMethod . ' ' . __('is required.'),
            'payment_method_id.exists' => __('The selected') . ' ' . $paymentMethod . ' ' . __('is invalid.'),
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
            'reference_no' => __('Reference_Number') ?: 'Reference Number',
            'date' => __('Date'),
            'type' => __('Type'),
            'note' => __('Note'),
            'amount' => __('Amount'),
            'payment_method_id' => __('Payment_Method'),
        ];
    }
}

