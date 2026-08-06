<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class ZatcaSettingRequest extends BaseRequest
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
            'zatca_phase' => ['required', 'string', Rule::in(['0', '1', '2'])],
            'zatca_status' => ['required_if:zatca_phase,2', 'nullable', 'string', Rule::in(['Enable', 'Disable'])],
            'vat_registration_number' => ['required_unless:zatca_phase,0', 'nullable', 'string', 'max:255'],
            'legal_business_name_arabic' => ['required_unless:zatca_phase,0', 'nullable', 'string', 'max:255'],
            'legal_business_name_english' => ['required_unless:zatca_phase,0', 'nullable', 'string', 'max:255'],
            'zatca_address' => ['required_unless:zatca_phase,0', 'nullable', 'string'],
            'compliance_csid' => ['nullable', 'string', 'max:255'],
            'production_csid' => ['nullable', 'string', 'max:255'],
            'zatca_secret_key' => ['nullable', 'string', 'max:255'],
            // Invoice format and numbering fields (from existing invoice setting)
            'invoice_format_or_size' => ['nullable', 'string', Rule::in(['56mm', '80mm', 'A4 Print', 'Half A4 Print', 'Letter Head'])],
            'schema_type' => ['nullable', 'string'],
            'inv_numbering_type' => ['nullable', 'string', Rule::in(['Sequential', 'Random'])],
            'inv_number_of_digit' => ['nullable', 'integer', 'min:4', 'max:10'],
            'inv_prefix' => ['nullable', 'string', 'max:55'],
            'inv_start_from' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'zatca_phase.required' => __('ZATCA Phase') . ' ' . __('is required.'),
            'zatca_phase.in' => __('Invalid') . ' ' . __('ZATCA Phase') . ' ' . __('selected'),
            'zatca_status.required_if' => __('ZATCA Phase 2 Status') . ' ' . __('is required when Phase 2 is selected.'),
            'zatca_status.in' => __('Invalid') . ' ' . __('ZATCA Phase 2 Status') . ' ' . __('selected'),
            'vat_registration_number.required_if' => __('VAT Registration Number') . ' ' . __('is required when ZATCA Phase is selected.'),
            'legal_business_name_arabic.required_if' => __('Legal Business Name (Arabic)') . ' ' . __('is required when ZATCA Phase is selected.'),
            'legal_business_name_english.required_if' => __('Legal Business Name (English)') . ' ' . __('is required when ZATCA Phase is selected.'),
            'zatca_address.required_if' => __('Address') . ' ' . __('is required when ZATCA Phase is selected.'),
        ];
    }
}
