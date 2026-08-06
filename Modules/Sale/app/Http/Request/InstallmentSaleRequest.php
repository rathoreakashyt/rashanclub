<?php

namespace Modules\Sale\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

class InstallmentSaleRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $rules = [
            'date' => 'required|date',
            'reference_no' => 'nullable|string|max:50',
            'customer_id' => 'required|exists:customers,id',
            'item_id' => 'required|exists:items,id',
            'item_type' => 'nullable|string|max:50',
            'expiry_imei_serial' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0.001',
            'discount' => 'nullable|string|max:25',
            'number_of_installment' => 'required|integer|min:1|max:120',
            'percentage_of_interest' => 'nullable|numeric|min:0|max:100',
            'shipping_other' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0.001',
            'down_payment' => 'nullable|numeric|min:0',
            'remaining' => 'required|numeric|min:0',
            'installment_type' => 'required|integer|min:1', // Duration in days
            'payment_method_id' => 'required|exists:payment_methods,id',
            'account_type' => 'nullable|string|max:50',
            
            // Account-specific fields
            'check_no' => 'nullable|string|max:100',
            'check_issue_date' => 'nullable|date',
            'check_expiry_date' => 'nullable|date|after_or_equal:check_issue_date',
            'mobile_no' => 'nullable|string|max:50',
            'transaction_no' => 'nullable|string|max:100',
            'card_holder_name' => 'nullable|string|max:100',
            'card_holding_number' => 'nullable|string|max:50',
            'paypal_email' => 'nullable|email|max:100',
            'stripe_email' => 'nullable|email|max:100',
            'p_note' => 'nullable|string|max:500',
            
            // Installment details (arrays)
            'amount_of_payment' => 'required|array|min:1',
            'amount_of_payment.*' => 'required|numeric|min:0.001',
            'payment_date' => 'required|array|min:1',
            'payment_date.*' => 'required|date|after_or_equal:date',
            'paid_status' => 'nullable|array',
            'paid_status.*' => 'nullable|in:Unpaid,Paid,Partial',
            
            'note' => 'nullable|string|max:1000',
        ];

        // Add IMEI/Serial validation for specific product types
        if ($this->isImeiOrSerialProduct()) {
            $rules['expiry_imei_serial'] = 'required|string|max:255';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $date = __('Date');
        $customer = __('Customer');
        $product = __('Product');
        $price = __('Price');
        $numberOfInstallments = __('Number_of_Installments') ?: 'Number of Installments';
        $total = __('Total');
        $remaining = __('Remaining');
        $installmentDuration = __('Installment_Duration') ?: 'Installment Duration';
        $paymentAccount = __('Payment_Account') ?: 'Payment Account';
        $installmentAmount = __('Installment_Amount') ?: 'Installment Amount';
        $paymentDate = __('Payment_Date') ?: 'Payment Date';
        $imeiSerial = __('IMEI_Serial') ?: 'IMEI/Serial';
        $checkExpiryDate = __('Check_Expiry_Date') ?: 'Check Expiry Date';
        $checkIssueDate = __('Check_Issue_Date') ?: 'Check Issue Date';
        $paypalEmail = __('PayPal_Email') ?: 'PayPal Email';
        $stripeEmail = __('Stripe_Email') ?: 'Stripe Email';
        $discount = __('Discount');
        $downPayment = __('Down_Payment') ?: 'Down Payment';
        $note = __('Note');
        
        return [
            'date.required' => $date . ' ' . __('is required.'),
            'date.date' => $date . ' ' . __('must be a valid date.'),
            'reference_no.max' => __('The') . ' ' . __('Reference_Number') . ' ' . __('may not be greater than') . ' 50 ' . __('characters.'),
            'customer_id.required' => __('The') . ' ' . $customer . ' ' . __('is required.'),
            'customer_id.exists' => __('The selected') . ' ' . $customer . ' ' . __('is invalid.'),
            'item_id.required' => __('The') . ' ' . $product . ' ' . __('is required.'),
            'item_id.exists' => __('The selected') . ' ' . $product . ' ' . __('is invalid.'),
            'item_type.max' => __('The') . ' ' . __('Item_Type') . ' ' . __('may not be greater than') . ' 50 ' . __('characters.'),
            'expiry_imei_serial.required' => $imeiSerial . ' ' . __('number is required for this product type.'),
            'expiry_imei_serial.max' => __('The') . ' ' . $imeiSerial . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'price.required' => __('The') . ' ' . $price . ' ' . __('is required.'),
            'price.numeric' => $price . ' ' . __('must be a number.'),
            'price.min' => __('The') . ' ' . $price . ' ' . __('must be at least') . ' 0.001.',
            'discount.max' => __('The') . ' ' . $discount . ' ' . __('may not be greater than') . ' 25 ' . __('characters.'),
            'number_of_installment.required' => __('The') . ' ' . $numberOfInstallments . ' ' . __('is required.'),
            'number_of_installment.integer' => $numberOfInstallments . ' ' . __('must be a whole number.'),
            'number_of_installment.min' => $numberOfInstallments . ' ' . __('must be at least') . ' 1.',
            'number_of_installment.max' => $numberOfInstallments . ' ' . __('may not be greater than') . ' 120.',
            'percentage_of_interest.numeric' => __('Interest_Percentage') . ' ' . __('must be a number.'),
            'percentage_of_interest.min' => __('Interest_Percentage') . ' ' . __('must be at least') . ' 0.',
            'percentage_of_interest.max' => __('Interest_Percentage') . ' ' . __('may not be greater than') . ' 100.',
            'shipping_other.numeric' => __('Shipping_Other') . ' ' . __('must be a number.'),
            'shipping_other.min' => __('Shipping_Other') . ' ' . __('must be at least') . ' 0.',
            'total.required' => __('The') . ' ' . $total . ' ' . __('is required.'),
            'total.numeric' => $total . ' ' . __('must be a number.'),
            'total.min' => __('The') . ' ' . $total . ' ' . __('must be at least') . ' 0.001.',
            'down_payment.numeric' => $downPayment . ' ' . __('must be a number.'),
            'down_payment.min' => $downPayment . ' ' . __('must be at least') . ' 0.',
            'remaining.required' => __('The') . ' ' . $remaining . ' ' . __('is required.'),
            'remaining.numeric' => $remaining . ' ' . __('must be a number.'),
            'remaining.min' => $remaining . ' ' . __('must be at least') . ' 0.',
            'installment_type.required' => __('The') . ' ' . $installmentDuration . ' ' . __('is required.'),
            'installment_type.integer' => $installmentDuration . ' ' . __('must be a whole number.'),
            'installment_type.min' => $installmentDuration . ' ' . __('must be at least') . ' 1.',
            'payment_method_id.required' => __('The') . ' ' . $paymentAccount . ' ' . __('is required.'),
            'payment_method_id.exists' => __('The selected') . ' ' . $paymentAccount . ' ' . __('is invalid.'),
            'account_type.max' => __('The') . ' ' . __('Account_Type') . ' ' . __('may not be greater than') . ' 50 ' . __('characters.'),
            'check_no.max' => __('The') . ' ' . __('Check_No') . ' ' . __('may not be greater than') . ' 100 ' . __('characters.'),
            'check_issue_date.date' => $checkIssueDate . ' ' . __('must be a valid date.'),
            'check_expiry_date.date' => $checkExpiryDate . ' ' . __('must be a valid date.'),
            'check_expiry_date.after_or_equal' => $checkExpiryDate . ' ' . __('must be on or after the') . ' ' . strtolower($checkIssueDate) . '.',
            'mobile_no.max' => __('The') . ' ' . __('Mobile_No') . ' ' . __('may not be greater than') . ' 50 ' . __('characters.'),
            'transaction_no.max' => __('The') . ' ' . __('Transaction_No') . ' ' . __('may not be greater than') . ' 100 ' . __('characters.'),
            'card_holder_name.max' => __('The') . ' ' . __('Card_Holder_Name') . ' ' . __('may not be greater than') . ' 100 ' . __('characters.'),
            'card_holding_number.max' => __('The') . ' ' . __('Card_Holding_Number') . ' ' . __('may not be greater than') . ' 50 ' . __('characters.'),
            'paypal_email.email' => __('Please enter a valid') . ' ' . strtolower($paypalEmail) . '.',
            'paypal_email.max' => __('The') . ' ' . $paypalEmail . ' ' . __('may not be greater than') . ' 100 ' . __('characters.'),
            'stripe_email.email' => __('Please enter a valid') . ' ' . strtolower($stripeEmail) . '.',
            'stripe_email.max' => __('The') . ' ' . $stripeEmail . ' ' . __('may not be greater than') . ' 100 ' . __('characters.'),
            'p_note.max' => __('The') . ' ' . __('Note') . ' ' . __('may not be greater than') . ' 500 ' . __('characters.'),
            'amount_of_payment.required' => __('At least one') . ' ' . strtolower($installmentAmount) . ' ' . __('is required.'),
            'amount_of_payment.*.required' => __('Each') . ' ' . strtolower($installmentAmount) . ' ' . __('is required.'),
            'amount_of_payment.*.numeric' => __('Each') . ' ' . strtolower($installmentAmount) . ' ' . __('must be a number.'),
            'amount_of_payment.*.min' => __('Each') . ' ' . strtolower($installmentAmount) . ' ' . __('must be at least') . ' 0.001.',
            'payment_date.required' => __('At least one') . ' ' . strtolower($paymentDate) . ' ' . __('is required.'),
            'payment_date.*.required' => __('Each') . ' ' . strtolower($paymentDate) . ' ' . __('is required.'),
            'payment_date.*.date' => __('Each') . ' ' . strtolower($paymentDate) . ' ' . __('must be a valid date.'),
            'payment_date.*.after_or_equal' => __('Payment dates must be on or after the sale date.'),
            'paid_status.*.in' => __('The') . ' ' . __('Paid_Status') . ' ' . __('must be either') . ' ' . __('Unpaid') . ', ' . __('Paid') . ' ' . __('or') . ' ' . __('Partial') . '.',
            'note.max' => __('The') . ' ' . $note . ' ' . __('may not be greater than') . ' 1000 ' . __('characters.'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'date' => __('Date'),
            'reference_no' => __('Reference_Number') ?: 'Reference Number',
            'customer_id' => __('Customer'),
            'item_id' => __('Product'),
            'item_type' => __('Item_Type') ?: 'Item Type',
            'expiry_imei_serial' => __('IMEI_Serial') ?: 'IMEI/Serial',
            'price' => __('Price'),
            'discount' => __('Discount'),
            'number_of_installment' => __('Number_of_Installments') ?: 'Number of Installments',
            'percentage_of_interest' => __('Interest_Percentage') ?: 'Interest Percentage',
            'shipping_other' => __('Shipping_Other') ?: 'Shipping/Other',
            'total' => __('Total'),
            'down_payment' => __('Down_Payment') ?: 'Down Payment',
            'remaining' => __('Remaining'),
            'installment_type' => __('Installment_Duration') ?: 'Installment Duration',
            'payment_method_id' => __('Payment_Account') ?: 'Payment Account',
            'account_type' => __('Account_Type') ?: 'Account Type',
            'check_no' => __('Check_No') ?: 'Check No',
            'check_issue_date' => __('Check_Issue_Date') ?: 'Check Issue Date',
            'check_expiry_date' => __('Check_Expiry_Date') ?: 'Check Expiry Date',
            'mobile_no' => __('Mobile_No') ?: 'Mobile No',
            'transaction_no' => __('Transaction_No') ?: 'Transaction No',
            'card_holder_name' => __('Card_Holder_Name') ?: 'Card Holder Name',
            'card_holding_number' => __('Card_Holding_Number') ?: 'Card Holding Number',
            'paypal_email' => __('PayPal_Email') ?: 'PayPal Email',
            'stripe_email' => __('Stripe_Email') ?: 'Stripe Email',
            'p_note' => __('Note'),
            'amount_of_payment.*' => __('Installment_Amount') ?: 'Installment Amount',
            'payment_date.*' => __('Payment_Date') ?: 'Payment Date',
            'paid_status.*' => __('Paid_Status') ?: 'Paid Status',
            'note' => __('Note'),
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean up numeric fields
        $this->merge([
            'price' => $this->cleanNumericValue($this->price),
            'total' => $this->cleanNumericValue($this->total),
            'down_payment' => $this->cleanNumericValue($this->down_payment),
            'remaining' => $this->cleanNumericValue($this->remaining),
            'shipping_other' => $this->cleanNumericValue($this->shipping_other),
            'percentage_of_interest' => $this->cleanNumericValue($this->percentage_of_interest),
        ]);

        // Clean up installment amounts
        if ($this->has('amount_of_payment') && is_array($this->amount_of_payment)) {
            $cleanedAmounts = array_map(function ($amount) {
                return $this->cleanNumericValue($amount);
            }, $this->amount_of_payment);
            $this->merge(['amount_of_payment' => $cleanedAmounts]);
        }
    }

    /**
     * Clean numeric value
     */
    protected function cleanNumericValue($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) preg_replace('/[^0-9.]/', '', $value);
    }

    /**
     * Check if product type requires IMEI/Serial
     */
    protected function isImeiOrSerialProduct(): bool
    {
        $itemType = $this->input('item_type');
        return in_array($itemType, ['IMEI_Product', 'Serial_Product']);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate total installment amount equals remaining
            $this->validateTotalInstallmentAmount($validator);
            
            // Validate down payment doesn't exceed total
            $this->validateDownPayment($validator);
        });
    }

    /**
     * Validate total installment amount equals remaining
     */
    protected function validateTotalInstallmentAmount($validator): void
    {
        if ($this->has('amount_of_payment') && is_array($this->amount_of_payment)) {
            $totalInstallmentAmount = array_sum(array_map('floatval', $this->amount_of_payment));
            $remaining = (float) $this->remaining;
            
            // Allow small floating point differences
            if (abs($totalInstallmentAmount - $remaining) > 0.01) {
                $installmentAmount = __('Installment_Amount') ?: 'Installment Amount';
                $remaining = __('Remaining');
                $validator->errors()->add(
                    'amount_of_payment',
                    __('Total') . ' ' . strtolower($installmentAmount) . ' ' . __('must equal the') . ' ' . strtolower($remaining) . ' ' . __('amount.') . '.'
                );
            }
        }
    }

    /**
     * Validate down payment doesn't exceed total
     */
    protected function validateDownPayment($validator): void
    {
        $downPayment = (float) $this->down_payment;
        $total = (float) $this->total;
        
        if ($downPayment > $total) {
            $downPayment = __('Down_Payment') ?: 'Down Payment';
            $total = __('Total');
            $validator->errors()->add(
                'down_payment',
                $downPayment . ' ' . __('cannot exceed the') . ' ' . strtolower($total) . ' ' . __('amount.') . '.'
            );
        }
    }
}

