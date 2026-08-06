<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\ValidationException;

class EmailSettingRequest extends BaseRequest
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
            'smtp_type' => ['required', 'string', 'in:Gmail,Sendinblue'],
            'smtp_enable_status' => ['required', 'string', 'in:1,2'],
            'host_name' => ['required', 'string', 'max:55'],
            'port_address' => ['required', 'string', 'max:10'],
            'encryption' => ['required', 'string', 'max:50'],
            'user_name' => ['required', 'string', 'max:55'],
            'password' => ['required', 'string', 'max:255'],
            'from_name' => ['required', 'string', 'max:55'],
            'from_email' => ['required', 'string', 'email', 'max:55'],
            'api_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->smtp_type === 'Sendinblue' && empty($this->api_key)) {
                $apiKey = __('API_Key') ?: 'API Key';
                $validator->errors()->add('api_key', $apiKey . ' ' . __('is required when using Sendinblue'));
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $smtpType = __('SMTP Type');
        $smtpEnableStatus = __('SMTP_Enable_Status') ?: 'SMTP Enable Status';
        $hostName = __('Host Name');
        $portAddress = __('Port Address');
        $encryption = __('Encryption');
        $userName = __('User Name');
        $password = __('Password');
        $fromName = __('From Name');
        $fromEmail = __('From Email');
        $apiKey = __('API Key');
        
        return [
            'smtp_type.required' => $smtpType . ' ' . __('is required.'),
            'smtp_type.in' => __('Invalid') . ' ' . strtolower($smtpType) . ' ' . __('selected'),
            'smtp_enable_status.required' => $smtpEnableStatus . ' ' . __('is required.'),
            'smtp_enable_status.in' => __('Invalid') . ' ' . strtolower($smtpEnableStatus) . ' ' . __('selected'),
            'host_name.required' => $hostName . ' ' . __('is required.'),
            'host_name.max' => $hostName . ' ' . __('must not exceed') . ' 55 ' . __('characters.'),
            'port_address.required' => $portAddress . ' ' . __('is required.'),
            'port_address.max' => $portAddress . ' ' . __('must not exceed') . ' 10 ' . __('characters.'),
            'encryption.required' => $encryption . ' ' . __('is required.'),
            'encryption.max' => $encryption . ' ' . __('must not exceed') . ' 50 ' . __('characters.'),
            'user_name.required' => $userName . ' ' . __('is required.'),
            'user_name.max' => $userName . ' ' . __('must not exceed') . ' 55 ' . __('characters.'),
            'password.required' => $password . ' ' . __('is required.'),
            'password.max' => $password . ' ' . __('must not exceed') . ' 255 ' . __('characters.'),
            'from_name.required' => $fromName . ' ' . __('is required.'),
            'from_name.max' => $fromName . ' ' . __('must not exceed') . ' 55 ' . __('characters.'),
            'from_email.required' => $fromEmail . ' ' . __('is required.'),
            'from_email.email' => $fromEmail . ' ' . __('must be a valid email address.'),
            'from_email.max' => $fromEmail . ' ' . __('must not exceed') . ' 55 ' . __('characters.'),
            'api_key.max' => $apiKey . ' ' . __('must not exceed') . ' 255 ' . __('characters.'),
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
            'smtp_type' => __('SMTP Type'),
            'smtp_enable_status' => __('SMTP Enable Status'),
            'host_name' => __('Host Name'),
            'port_address' => __('Port Address'),
            'encryption' => __('Encryption'),
            'user_name' => __('User Name'),
            'password' => __('Password'),
            'from_name' => __('From Name'),
            'from_email' => __('From Email'),
            'api_key' => __('API Key'),
        ];
    }
}

