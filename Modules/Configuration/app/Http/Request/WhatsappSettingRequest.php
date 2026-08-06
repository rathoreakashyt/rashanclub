<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;

class WhatsappSettingRequest extends BaseRequest
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
            'whatsapp_provider' => ['required', 'string', 'max:55'],
            'whatsapp_invoice_enable_status' => ['required', 'string', 'in:Enable,Disable'],
            'whatsapp_app_key' => ['required', 'string', 'max:255'],
            'whatsapp_authkey' => ['required', 'string', 'max:255']
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $whatsappProvider = __('WhatsApp Provider');
        $whatsappEnableStatus = __('WhatsApp Enable Status');
        $whatsappAppKey = __('WhatsApp App Key');
        $whatsappAuthKey = __('WhatsApp Auth Key');
        
        return [
            'whatsapp_provider.required' => $whatsappProvider . ' ' . __('is required.'),
            'whatsapp_invoice_enable_status.required' => $whatsappEnableStatus . ' ' . __('is required.'),
            'whatsapp_invoice_enable_status.in' => __('Invalid') . ' ' . strtolower($whatsappEnableStatus) . ' ' . __('selected'),
            'whatsapp_app_key.required' => $whatsappAppKey . ' ' . __('is required.'),
            'whatsapp_authkey.required' => $whatsappAuthKey . ' ' . __('is required.')
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
            'whatsapp_provider' => __('WhatsApp Provider'),
            'whatsapp_invoice_enable_status' => __('WhatsApp Enable Status'),
            'whatsapp_app_key' => __('WhatsApp App Key'),
            'whatsapp_authkey' => __('WhatsApp Auth Key'),
        ];
    }
}

