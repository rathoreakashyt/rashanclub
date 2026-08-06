<?php

namespace Modules\Sale\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class WarrantyRequest extends BaseRequest
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
        $warrantyId = null;
        if ($this->route('warranty')) {
            $warrantyId = decrypt($this->route('warranty'));
        } elseif ($this->route('id')) {
            $warrantyId = $this->route('id');
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
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_mobile' => ['nullable', 'string', 'max:50'],
            'product_name' => ['required', 'string', 'max:255'],
            'product_serial_no' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'receiving_date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:receiving_date'],
            'current_status' => ['required', 'string', 'max:50'],
            'technician_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
            ],
            'present_location' => ['nullable', 'string', 'max:255'],
            'sender_service_center' => ['nullable', 'string', 'max:255'],
            'receiver_service_center' => ['nullable', 'string', 'max:255'],
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
        $customerName = __('Customer_Name') ?: 'Customer Name';
        $customerMobile = __('Customer_Mobile') ?: 'Customer Mobile';
        $productName = __('Product_Name') ?: 'Product Name';
        $productSerialNo = __('Product_Serial_Number') ?: 'Product Serial Number';
        $description = __('Description');
        $receivingDate = __('Receiving_Date') ?: 'Receiving Date';
        $deliveryDate = __('Delivery_Date') ?: 'Delivery Date';
        $currentStatus = __('Status');
        $technician = __('Technician') ?: 'Technician';
        $presentLocation = __('Present_Location') ?: 'Present Location';
        $senderServiceCenter = __('Sender_Service_Center') ?: 'Sender Service Center';
        $receiverServiceCenter = __('Receiver_Service_Center') ?: 'Receiver Service Center';
        $outlet = __('Outlet');
        
        return [
            'customer_id.required' => __('The') . ' ' . $customer . ' ' . __('is required.'),
            'customer_id.exists' => __('The selected') . ' ' . $customer . ' ' . __('is invalid.'),
            'customer_name.max' => __('The') . ' ' . $customerName . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'customer_mobile.max' => __('The') . ' ' . $customerMobile . ' ' . __('may not be greater than') . ' 50 ' . __('characters.'),
            'product_name.required' => __('The') . ' ' . $productName . ' ' . __('is required.'),
            'product_name.max' => __('The') . ' ' . $productName . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'product_serial_no.max' => __('The') . ' ' . $productSerialNo . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'receiving_date.required' => __('The') . ' ' . $receivingDate . ' ' . __('is required.'),
            'receiving_date.date' => $receivingDate . ' ' . __('must be a valid date.'),
            'delivery_date.date' => $deliveryDate . ' ' . __('must be a valid date.'),
            'delivery_date.after_or_equal' => $deliveryDate . ' ' . __('must be equal to or after the') . ' ' . strtolower($receivingDate) . '.',
            'current_status.required' => __('The') . ' ' . $currentStatus . ' ' . __('is required.'),
            'current_status.max' => __('The') . ' ' . $currentStatus . ' ' . __('may not be greater than') . ' 50 ' . __('characters.'),
            'technician_id.exists' => __('The selected') . ' ' . $technician . ' ' . __('is invalid.'),
            'present_location.max' => __('The') . ' ' . $presentLocation . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'sender_service_center.max' => __('The') . ' ' . $senderServiceCenter . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'receiver_service_center.max' => __('The') . ' ' . $receiverServiceCenter . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
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
            'customer_name' => __('Customer_Name') ?: 'Customer Name',
            'customer_mobile' => __('Customer_Mobile') ?: 'Customer Mobile',
            'product_name' => __('Product_Name') ?: 'Product Name',
            'product_serial_no' => __('Product_Serial_Number') ?: 'Product Serial Number',
            'description' => __('Description'),
            'receiving_date' => __('Receiving_Date') ?: 'Receiving Date',
            'delivery_date' => __('Delivery_Date') ?: 'Delivery Date',
            'current_status' => __('Status'),
            'technician_id' => __('Technician') ?: 'Technician',
            'present_location' => __('Present_Location') ?: 'Present Location',
            'sender_service_center' => __('Sender_Service_Center') ?: 'Sender Service Center',
            'receiver_service_center' => __('Receiver_Service_Center') ?: 'Receiver Service Center',
            'outlet_id' => __('Outlet'),
        ];
    }
}
