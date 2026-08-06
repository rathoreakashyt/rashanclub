<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\ValidationException;

class SmsSettingRequest extends BaseRequest
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
     * When sms_enable_status is 2 (Disable), no provider or provider-specific validation is applied.
     */
    public function rules(): array
    {
        $rules = [
            'sms_enable_status' => ['required', 'string', 'in:1,2'],
        ];

        // When SMS is disabled (status 2), sms_service_provider can be None — skip all other validation
        if ($this->sms_enable_status == '2') {
            $rules['sms_service_provider'] = ['nullable', 'string'];
            return $rules;
        }

        // When SMS is enabled, require a valid provider and its credentials
        $rules['sms_service_provider'] = ['required', 'string', 'in:1,2,3,4'];

        if ($this->sms_service_provider == '1') {
            $rules['twilio_sid'] = ['required', 'string', 'max:255'];
            $rules['twilio_token'] = ['required', 'string', 'max:255'];
            $rules['twilio_number'] = ['required', 'string', 'max:20'];
        } elseif ($this->sms_service_provider == '2') {
            $rules['mobishastra_profile_id'] = ['required', 'string', 'max:255'];
            $rules['mobishastra_password'] = ['required', 'string', 'max:255'];
            $rules['mobishastra_sender_id'] = ['required', 'string', 'max:20'];
            $rules['mobishastra_country_code'] = ['required', 'string', 'max:10'];
        } elseif ($this->sms_service_provider == '3') {
            $rules['mim_sms_api_key'] = ['required', 'string', 'max:255'];
            $rules['mim_sms_sender_id'] = ['required', 'string', 'max:20'];
            $rules['mim_sms_username'] = ['required', 'string', 'max:255'];
        } elseif ($this->sms_service_provider == '4') {
            $rules['text_local_profile_id'] = ['required', 'string', 'max:255'];
            $rules['text_local_api_key'] = ['required', 'string', 'max:255'];
            $rules['text_local_sender_id'] = ['required', 'string', 'max:20'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $smsServiceProvider = __('SMS Service Provider');
        $smsEnableStatus = __('SMS Enable Status');
        $twilioSid = __('Twilio SID');
        $twilioToken = __('Twilio Token');
        $twilioNumber = __('Twilio Number');
        $mobishastraProfileId = __('Mobishastra Profile ID');
        $mobishastraPassword = __('Mobishastra Password');
        $mobishastraSenderId = __('Mobishastra Sender ID');
        $mobishastraCountryCode = __('Mobishastra Country Code');
        $mimSmsApiKey = __('Mim SMS API Key');
        $mimSmsSenderId = __('Mim SMS Sender ID');
        $mimSmsUsername = __('Mim SMS Username');
        $textLocalProfileId = __('Text Local Profile ID');
        $textLocalApiKey = __('Text Local API Key');
        $textLocalSenderId = __('Text Local Sender ID');
        
        return [
            'sms_service_provider.required' => $smsServiceProvider . ' ' . __('is required.'),
            'sms_service_provider.in' => __('Invalid') . ' ' . strtolower($smsServiceProvider) . ' ' . __('selected'),
            'sms_enable_status.required' => $smsEnableStatus . ' ' . __('is required.'),
            'sms_enable_status.in' => __('Invalid') . ' ' . strtolower($smsEnableStatus) . ' ' . __('selected'),
            'twilio_sid.required' => $twilioSid . ' ' . __('is required.'),
            'twilio_token.required' => $twilioToken . ' ' . __('is required.'),
            'twilio_number.required' => $twilioNumber . ' ' . __('is required.'),
            'mobishastra_profile_id.required' => $mobishastraProfileId . ' ' . __('is required.'),
            'mobishastra_password.required' => $mobishastraPassword . ' ' . __('is required.'),
            'mobishastra_sender_id.required' => $mobishastraSenderId . ' ' . __('is required.'),
            'mobishastra_country_code.required' => $mobishastraCountryCode . ' ' . __('is required.'),
            'mim_sms_api_key.required' => $mimSmsApiKey . ' ' . __('is required.'),
            'mim_sms_sender_id.required' => $mimSmsSenderId . ' ' . __('is required.'),
            'mim_sms_username.required' => $mimSmsUsername . ' ' . __('is required.'),
            'text_local_profile_id.required' => $textLocalProfileId . ' ' . __('is required.'),
            'text_local_api_key.required' => $textLocalApiKey . ' ' . __('is required.'),
            'text_local_sender_id.required' => $textLocalSenderId . ' ' . __('is required.'),
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
            'sms_service_provider' => __('SMS Service Provider'),
            'sms_enable_status' => __('SMS Enable Status'),
            'twilio_sid' => __('Twilio SID'),
            'twilio_token' => __('Twilio Token'),
            'twilio_number' => __('Twilio Number'),
            'mobishastra_profile_id' => __('Mobishastra Profile ID'),
            'mobishastra_password' => __('Mobishastra Password'),
            'mobishastra_sender_id' => __('Mobishastra Sender ID'),
            'mobishastra_country_code' => __('Mobishastra Country Code'),
            'mim_sms_api_key' => __('Mim SMS API Key'),
            'mim_sms_sender_id' => __('Mim SMS Sender ID'),
            'mim_sms_username' => __('Mim SMS Username'),
            'text_local_profile_id' => __('Text Local Profile ID'),
            'text_local_api_key' => __('Text Local API Key'),
            'text_local_sender_id' => __('Text Local Sender ID'),
        ];
    }
}

