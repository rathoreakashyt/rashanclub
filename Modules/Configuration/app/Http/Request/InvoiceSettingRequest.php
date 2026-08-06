<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class InvoiceSettingRequest extends BaseRequest
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
        return [
            'invoice_format_or_size' => ['required', 'string', Rule::in(['56mm', '80mm', 'A4 Print', 'Half A4 Print', 'Letter Head'])],
            'schema_type' => ['required', 'string'],
            'inv_numbering_type' => ['required', 'string', Rule::in(['Sequential', 'Random'])],
            'inv_number_of_digit' => ['required', 'integer', 'min:4', 'max:10'],
            'inv_prefix' => ['nullable', 'string', 'max:55'],
            'inv_start_from' => ['required_if:inv_numbering_type,Sequential', 'nullable', 'string'],
            'inv_logo_is_show' => ['nullable', 'string', Rule::in(['Yes', 'No'])],
            'invoice_logo' => ['nullable', 'string'], // Base64 from cropper
            'invoice_logo_file' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,gif,png', 'max:1024'],
            'invoice_logo_p' => ['nullable', 'string'],
            'show_letter_head' => ['nullable', 'string', Rule::in(['Yes', 'No'])],
            'letter_head_gap' => ['nullable', 'string'],
            'letter_footer_gap' => ['nullable', 'string'],
            'invoice_heading' => ['required', 'string', 'max:255'],
            'invoice_heading_arabic' => ['nullable', 'string', 'max:255'],
            'invoice_heading_due' => ['nullable', 'string', 'max:255'],
            'invoice_heading_paid' => ['nullable', 'string', 'max:255'],
            'invoice_no_label' => ['required', 'string', 'max:255'],
            'invoice_no_label_arabic' => ['nullable', 'string', 'max:255'],
            'invoice_date_label' => ['required', 'string', 'max:255'],
            'invoice_date_label_arabic' => ['nullable', 'string', 'max:255'],
            'invoice_show_due_date' => ['required', 'string', Rule::in(['Yes', 'No'])],
            'invoice_due_date_label' => ['required', 'string', 'max:255'],
            'invoice_due_date_label_arabic' => ['nullable', 'string', 'max:255'],
            'sales_person_label' => ['required', 'string', 'max:255'],
            'commission_agent_label' => ['required', 'string', 'max:255'],
            'show_business_name' => ['required', 'string', Rule::in(['Yes', 'No'])],
            'business_name_arabic' => ['nullable', 'string', 'max:255'],
            'show_business_tax_number' => ['required', 'string', Rule::in(['Yes', 'No'])],
            'business_tax_number_label' => ['required', 'string', 'max:255'],
            'customer_label' => ['required', 'string', 'max:255'],
            'customer_tax_number_label' => ['required', 'string', 'max:255'],
            'show_customer_phone_number' => ['required', 'string', Rule::in(['Yes', 'No'])],
            'show_customer_email' => ['required', 'string', Rule::in(['Yes', 'No'])],
            'show_customer_address' => ['required', 'string', Rule::in(['Yes', 'No'])],
            'serial_no_label' => ['required', 'string', 'max:255'],
            'serial_no_label_arabic' => ['nullable', 'string', 'max:255'],
            'item_label' => ['required', 'string', 'max:255'],
            'item_label_arabic' => ['nullable', 'string', 'max:255'],
            'price_label' => ['required', 'string', 'max:255'],
            'price_label_arabic' => ['nullable', 'string', 'max:255'],
            'quantity_label' => ['required', 'string', 'max:255'],
            'quantity_label_arabic' => ['nullable', 'string', 'max:255'],
            'item_discount_label' => ['required', 'string', 'max:255'],
            'item_discount_label_arabic' => ['nullable', 'string', 'max:255'],
            'subtotal_label' => ['required', 'string', 'max:255'],
            'subtotal_label_arabic' => ['nullable', 'string', 'max:255'],
            'total_label' => ['required', 'string', 'max:255'],
            'total_label_arabic' => ['nullable', 'string', 'max:255'],
            'total_item_label' => ['required', 'string', 'max:255'],
            'total_item_label_arabic' => ['nullable', 'string', 'max:255'],
            'tax_label' => ['required', 'string', 'max:255'],
            'tax_label_arabic' => ['nullable', 'string', 'max:255'],
            'charge_label' => ['required', 'string', 'max:255'],
            'charge_label_arabic' => ['nullable', 'string', 'max:255'],
            'discount_label' => ['required', 'string', 'max:255'],
            'discount_label_arabic' => ['nullable', 'string', 'max:255'],
            'delivery_partner_label' => ['required', 'string', 'max:255'],
            'delivery_partner_label_arabic' => ['nullable', 'string', 'max:255'],
            'rounding_label' => ['required', 'string', 'max:255'],
            'rounding_label_arabic' => ['nullable', 'string', 'max:255'],
            'total_payable_label' => ['required', 'string', 'max:255'],
            'total_payable_label_arabic' => ['nullable', 'string', 'max:255'],
            'previous_balance_label' => ['required', 'string', 'max:255'],
            'previous_balance_label_arabic' => ['nullable', 'string', 'max:255'],
            'paid_amount_label' => ['required', 'string', 'max:255'],
            'paid_amount_label_arabic' => ['nullable', 'string', 'max:255'],
            'due_amount_label' => ['required', 'string', 'max:255'],
            'due_amount_label_arabic' => ['nullable', 'string', 'max:255'],
            'due_receive_label' => ['required', 'string', 'max:255'],
            'due_receive_label_arabic' => ['nullable', 'string', 'max:255'],
            'advance_receive_label' => ['required', 'string', 'max:255'],
            'advance_receive_label_arabic' => ['nullable', 'string', 'max:255'],
            'given_amount_label' => ['required', 'string', 'max:255'],
            'given_amount_label_arabic' => ['nullable', 'string', 'max:255'],
            'change_amount_label' => ['required', 'string', 'max:255'],
            'change_amount_label_arabic' => ['nullable', 'string', 'max:255'],
            'servicing_charge_label' => ['required', 'string', 'max:255'],
            'servicing_charge_label_arabic' => ['nullable', 'string', 'max:255'],
            'show_payment_method' => ['required', 'string', Rule::in(['Yes', 'No'])],
            'payment_method_label' => ['required', 'string', 'max:255'],
            'payment_method_label_arabic' => ['nullable', 'string', 'max:255'],
            'show_hsn_code' => ['nullable', 'string', Rule::in(['Yes', 'No'])],
            'show_brand' => ['nullable', 'string', Rule::in(['Yes', 'No'])],
            'show_product_code' => ['nullable', 'string', Rule::in(['Yes', 'No'])],
            'show_product_imei_serial_number' => ['nullable', 'string', Rule::in(['Yes', 'No'])],
            'show_product_image' => ['nullable', 'string', Rule::in(['Yes', 'No'])],
            'show_warranty_period' => ['nullable', 'string', Rule::in(['Yes', 'No'])],
            'show_warranty_expiry_date' => ['nullable', 'string', Rule::in(['Yes', 'No'])],
            'show_guarantee_period' => ['nullable', 'string', Rule::in(['Yes', 'No'])],
            'show_guarantee_expiry_date' => ['nullable', 'string', Rule::in(['Yes', 'No'])],
            'show_total_in_words' => ['nullable', 'string', Rule::in(['Yes', 'No'])],
            'word_format' => ['nullable', 'string', Rule::in(['International', 'Indian'])],
            'term_conditions' => ['nullable', 'string'],
            'invoice_footer' => ['nullable', 'string'],
            'qr_code_option' => ['nullable', 'string', Rule::in(['ZATCA QR Code', 'Regular QR Code'])],
            'qr_code_business' => ['nullable', 'string'],
            'qr_code_address' => ['nullable', 'string'],
            'qr_code_taxnumber' => ['nullable', 'string'],
            'qr_code_invoice_no' => ['nullable', 'string'],
            'qr_code_invoice_date_time' => ['nullable', 'string'],
            'qr_code_subtotal' => ['nullable', 'string'],
            'qr_code_charge' => ['nullable', 'string'],
            'qr_code_tax' => ['nullable', 'string'],
            'qr_code_total_payable' => ['nullable', 'string'],
            'qr_code_customer_name' => ['nullable', 'string'],
            'qr_code_invoice_url' => ['nullable', 'string'],
            'qr_code_outlet' => ['nullable', 'string'],
        ];
    }

    /**
    * Get custom messages for validator errors.
    */
    public function messages(): array
    {
        $invoiceFormat = __('Invoice Format Or Size');
        $schemaType = __('Schema Type');
        $invNumberingType = __('Invoice Numbering Type');
        $invNumberOfDigit = __('Invoice Number Of Digits');
        $invStartFrom = __('Invoice Start From');
        $invoiceHeading = __('Invoice Heading');
        $invoiceNoLabel = __('Invoice No Label');
        $invoiceDateLabel = __('Invoice Date Label');
        $invoiceShowDueDate = __('Invoice Show Due Date');
        $invoiceDueDateLabel = __('Invoice Due Date Label');
        $salesPersonLabel = __('Sales Person Label');
        $commissionAgentLabel = __('Commission Agent Label');
        $showBusinessName = __('Show Business Name');
        $showBusinessTaxNumber = __('Show Business Tax Number');
        $businessTaxNumberLabel = __('Business Tax Number Label');
        $customerLabel = __('Customer Label');
        $customerTaxNumberLabel = __('Customer Tax Number Label');
        $showCustomerPhoneNumber = __('Show Customer Phone Number');
        $showCustomerEmail = __('Show Customer Email');
        $showCustomerAddress = __('Show Customer Address');
        $serialNoLabel = __('Serial No Label');
        $itemLabel = __('Item Label');
        $priceLabel = __('Price Label');
        $quantityLabel = __('Quantity Label');
        $itemDiscountLabel = __('Item Discount Label');
        $subtotalLabel = __('Subtotal Label');
        $totalLabel = __('Total Label');
        $totalItemLabel = __('Total Item Label');
        $taxLabel = __('Tax Label');
        $chargeLabel = __('Charge Label');
        $discountLabel = __('Discount Label');
        $deliveryPartnerLabel = __('Delivery Partner Label');
        $roundingLabel = __('Rounding Label');
        $totalPayableLabel = __('Total Payable Label');
        $previousBalanceLabel = __('Previous Balance Label');
        $paidAmountLabel = __('Paid Amount Label');
        $dueAmountLabel = __('Due Amount Label');
        $dueReceiveLabel = __('Due Receive Label');
        $advanceReceiveLabel = __('Advance Receive Label');
        $givenAmountLabel = __('Given Amount Label');
        $changeAmountLabel = __('Change Amount Label');
        $servicingChargeLabel = __('Servicing Charge Label');
        $showPaymentMethod = __('Show Payment Method');
        $paymentMethodLabel = __('Payment Method Label');
        
        return [
            'invoice_format_or_size.required' => $invoiceFormat . ' ' . __('is required.'),
            'schema_type.required' => $schemaType . ' ' . __('is required.'),
            'inv_numbering_type.required' => $invNumberingType . ' ' . __('is required.'),
            'inv_number_of_digit.required' => $invNumberOfDigit . ' ' . __('is required.'),
            'inv_start_from.required_if' => $invStartFrom . ' ' . __('is required when numbering type is Sequential'),
            'invoice_heading.required' => $invoiceHeading . ' ' . __('is required.'),
            'invoice_no_label.required' => $invoiceNoLabel . ' ' . __('is required.'),
            'invoice_date_label.required' => $invoiceDateLabel . ' ' . __('is required.'),
            'invoice_show_due_date.required' => $invoiceShowDueDate . ' ' . __('is required.'),
            'invoice_due_date_label.required' => $invoiceDueDateLabel . ' ' . __('is required.'),
            'sales_person_label.required' => $salesPersonLabel . ' ' . __('is required.'),
            'commission_agent_label.required' => $commissionAgentLabel . ' ' . __('is required.'),
            'show_business_name.required' => $showBusinessName . ' ' . __('is required.'),
            'show_business_tax_number.required' => $showBusinessTaxNumber . ' ' . __('is required.'),
            'business_tax_number_label.required' => $businessTaxNumberLabel . ' ' . __('is required.'),
            'customer_label.required' => $customerLabel . ' ' . __('is required.'),
            'customer_tax_number_label.required' => $customerTaxNumberLabel . ' ' . __('is required.'),
            'show_customer_phone_number.required' => $showCustomerPhoneNumber . ' ' . __('is required.'),
            'show_customer_email.required' => $showCustomerEmail . ' ' . __('is required.'),
            'show_customer_address.required' => $showCustomerAddress . ' ' . __('is required.'),
            'serial_no_label.required' => $serialNoLabel . ' ' . __('is required.'),
            'item_label.required' => $itemLabel . ' ' . __('is required.'),
            'price_label.required' => $priceLabel . ' ' . __('is required.'),
            'quantity_label.required' => $quantityLabel . ' ' . __('is required.'),
            'item_discount_label.required' => $itemDiscountLabel . ' ' . __('is required.'),
            'subtotal_label.required' => $subtotalLabel . ' ' . __('is required.'),
            'total_label.required' => $totalLabel . ' ' . __('is required.'),
            'total_item_label.required' => $totalItemLabel . ' ' . __('is required.'),
            'tax_label.required' => $taxLabel . ' ' . __('is required.'),
            'charge_label.required' => $chargeLabel . ' ' . __('is required.'),
            'discount_label.required' => $discountLabel . ' ' . __('is required.'),
            'delivery_partner_label.required' => $deliveryPartnerLabel . ' ' . __('is required.'),
            'rounding_label.required' => $roundingLabel . ' ' . __('is required.'),
            'total_payable_label.required' => $totalPayableLabel . ' ' . __('is required.'),
            'previous_balance_label.required' => $previousBalanceLabel . ' ' . __('is required.'),
            'paid_amount_label.required' => $paidAmountLabel . ' ' . __('is required.'),
            'due_amount_label.required' => $dueAmountLabel . ' ' . __('is required.'),
            'due_receive_label.required' => $dueReceiveLabel . ' ' . __('is required.'),
            'advance_receive_label.required' => $advanceReceiveLabel . ' ' . __('is required.'),
            'given_amount_label.required' => $givenAmountLabel . ' ' . __('is required.'),
            'change_amount_label.required' => $changeAmountLabel . ' ' . __('is required.'),
            'servicing_charge_label.required' => $servicingChargeLabel . ' ' . __('is required.'),
            'show_payment_method.required' => $showPaymentMethod . ' ' . __('is required.'),
            'payment_method_label.required' => $paymentMethodLabel . ' ' . __('is required.'),
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
            'invoice_format_or_size' => __('Invoice Format Or Size'),
            'schema_type' => __('Schema Type'),
            'inv_numbering_type' => __('Invoice Numbering Type'),
            'inv_number_of_digit' => __('Invoice Number Of Digits'),
            'inv_start_from' => __('Invoice Start From'),
            'invoice_heading' => __('Invoice Heading'),
            'invoice_no_label' => __('Invoice No Label'),
            'invoice_date_label' => __('Invoice Date Label'),
            'invoice_show_due_date' => __('Invoice Show Due Date'),
            'invoice_due_date_label' => __('Invoice Due Date Label'),
            'sales_person_label' => __('Sales Person Label'),
            'commission_agent_label' => __('Commission Agent Label'),
            'show_business_name' => __('Show Business Name'),
            'show_business_tax_number' => __('Show Business Tax Number'),
            'business_tax_number_label' => __('Business Tax Number Label'),
            'customer_label' => __('Customer Label'),
            'customer_tax_number_label' => __('Customer Tax Number Label'),
            'show_customer_phone_number' => __('Show Customer Phone Number'),
            'show_customer_email' => __('Show Customer Email'),
            'show_customer_address' => __('Show Customer Address'),
            'serial_no_label' => __('Serial No Label'),
            'item_label' => __('Item Label'),
            'price_label' => __('Price Label'),
            'quantity_label' => __('Quantity Label'),
            'item_discount_label' => __('Item Discount Label'),
            'subtotal_label' => __('Subtotal Label'),
            'total_label' => __('Total Label'),
            'total_item_label' => __('Total Item Label'),
            'tax_label' => __('Tax Label'),
            'charge_label' => __('Charge Label'),
            'discount_label' => __('Discount Label'),
            'delivery_partner_label' => __('Delivery Partner Label'),
            'rounding_label' => __('Rounding Label'),
            'total_payable_label' => __('Total Payable Label'),
            'previous_balance_label' => __('Previous Balance Label'),
            'paid_amount_label' => __('Paid Amount Label'),
            'due_amount_label' => __('Due Amount Label'),
            'due_receive_label' => __('Due Receive Label'),
            'advance_receive_label' => __('Advance Receive Label'),
            'given_amount_label' => __('Given Amount Label'),
            'change_amount_label' => __('Change Amount Label'),
            'servicing_charge_label' => __('Servicing Charge Label'),
            'show_payment_method' => __('Show Payment Method'),
            'payment_method_label' => __('Payment Method Label'),
        ];
    }
}
