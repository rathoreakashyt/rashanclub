<?php

namespace Modules\Sale\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class ServicingRequest extends BaseRequest
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
        $servicingId = null;
        if ($this->route('servicing')) {
            $servicingId = decrypt($this->route('servicing'));
        } elseif ($this->route('id')) {
            $servicingId = $this->route('id');
        }

        $companyId = session('company.company_id');

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
            ],
            'date' => ['required', 'date'],
            'product_name' => ['required', 'string', 'max:255'],
            'product_model' => ['nullable', 'string', 'max:255'],
            'problem_description' => ['nullable', 'string'],
            'receiving_date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:receiving_date'],
            'servicing_charge' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'due_amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'status' => ['required', 'string', 'max:50'],
            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
            ],
            'payment_method_id' => [
                'nullable',
                'integer',
                Rule::exists('payment_methods', 'id')
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
            ],
            'outlet_id' => [
                'nullable',
                'integer',
                Rule::exists('outlets', 'id')
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $customer = __('Customer');
        $date = __('Date');
        $productName = __('Product_Name') ?: 'Product Name';
        $productModel = __('Product_Model') ?: 'Product Model';
        $problemDescription = __('Problem_Description') ?: 'Problem Description';
        $receivingDate = __('Receiving_Date') ?: 'Receiving Date';
        $deliveryDate = __('Delivery_Date') ?: 'Delivery Date';
        $servicingCharge = __('Servicing_Charge') ?: 'Servicing Charge';
        $paidAmount = __('Paid_Amount') ?: 'Paid Amount';
        $dueAmount = __('Due_Amount') ?: 'Due Amount';
        $status = __('Status');
        $employee = __('Employee');
        $paymentMethod = __('Payment_Method');
        $outlet = __('Outlet');
        
        return [
            'customer_id.required' => __('The') . ' ' . $customer . ' ' . __('is required.'),
            'customer_id.exists' => __('The selected') . ' ' . $customer . ' ' . __('is invalid.'),
            'date.required' => $date . ' ' . __('is required.'),
            'date.date' => $date . ' ' . __('must be a valid date.'),
            'product_name.required' => __('The') . ' ' . $productName . ' ' . __('is required.'),
            'product_name.max' => __('The') . ' ' . $productName . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'product_model.max' => __('The') . ' ' . $productModel . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'receiving_date.required' => __('The') . ' ' . $receivingDate . ' ' . __('is required.'),
            'receiving_date.date' => $receivingDate . ' ' . __('must be a valid date.'),
            'delivery_date.date' => $deliveryDate . ' ' . __('must be a valid date.'),
            'delivery_date.after_or_equal' => $deliveryDate . ' ' . __('must be equal to or after the') . ' ' . strtolower($receivingDate) . '.',
            'servicing_charge.required' => __('The') . ' ' . $servicingCharge . ' ' . __('is required.'),
            'servicing_charge.numeric' => $servicingCharge . ' ' . __('must be a number.'),
            'servicing_charge.min' => $servicingCharge . ' ' . __('cannot be negative.'),
            'paid_amount.numeric' => $paidAmount . ' ' . __('must be a number.'),
            'paid_amount.min' => $paidAmount . ' ' . __('cannot be negative.'),
            'due_amount.numeric' => $dueAmount . ' ' . __('must be a number.'),
            'due_amount.min' => $dueAmount . ' ' . __('cannot be negative.'),
            'status.required' => $status . ' ' . __('is required.'),
            'status.max' => __('The') . ' ' . $status . ' ' . __('may not be greater than') . ' 50 ' . __('characters.'),
            'employee_id.exists' => __('The selected') . ' ' . $employee . ' ' . __('is invalid.'),
            'payment_method_id.exists' => __('The selected') . ' ' . $paymentMethod . ' ' . __('is invalid.'),
            'outlet_id.exists' => __('The selected') . ' ' . $outlet . ' ' . __('is invalid.'),
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
            'customer_id' => __('Customer'),
            'date' => __('Date'),
            'product_name' => __('Product_Name') ?: 'Product Name',
            'product_model' => __('Product_Model') ?: 'Product Model',
            'problem_description' => __('Problem_Description') ?: 'Problem Description',
            'receiving_date' => __('Receiving_Date') ?: 'Receiving Date',
            'delivery_date' => __('Delivery_Date') ?: 'Delivery Date',
            'servicing_charge' => __('Servicing_Charge') ?: 'Servicing Charge',
            'paid_amount' => __('Paid_Amount') ?: 'Paid Amount',
            'due_amount' => __('Due_Amount') ?: 'Due Amount',
            'status' => __('Status'),
            'employee_id' => __('Employee'),
            'payment_method_id' => __('Payment_Method'),
            'outlet_id' => __('Outlet'),
        ];
    }
}
