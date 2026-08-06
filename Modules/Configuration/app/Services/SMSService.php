<?php

namespace Modules\Configuration\Services;

use Modules\Configuration\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SMSService
{
    /**
     * Send test SMS using configured SMS service
     *
     * @param string $to
     * @param string $message
     * @param array $credentials
     * @return array
     */
    public function sendSMS($to, $message, $credentials = [])
    {
        try {
            $company = Company::find(session('company.company_id', 1));

            if (!$company) {
                return [
                    'status' => 'Error',
                    'message' => 'Company not found'
                ];
            }

            // Get SMS settings from credentials or database
            if (!empty($credentials)) {
                $smsType = $credentials['sms_type'] ?? 'mobishastra';
                $smsApiKey = $credentials['sms_api_key'] ?? '';
                $smsApiSecret = $credentials['sms_api_secret'] ?? '';
                $smsSenderId = $credentials['sms_sender_id'] ?? '';
                $smsUsername = $credentials['sms_username'] ?? '';
                $smsPassword = $credentials['sms_password'] ?? '';
            } else {
                $smsDetails = json_decode($company->sms_details ?? '{}', true);
                $providerId = $company->sms_service_provider ?? '2';
                
                // Map provider ID to type
                $providerMap = [
                    '1' => 'twilio',
                    '2' => 'mobishastra',
                    '3' => 'mimsms',
                    '4' => 'textlocal'
                ];
                $smsType = $providerMap[$providerId] ?? 'mobishastra';
                
                // Get provider-specific credentials
                if ($smsType === 'twilio') {
                    $smsApiKey = $smsDetails['twilio']['sid'] ?? '';
                    $smsApiSecret = $smsDetails['twilio']['token'] ?? '';
                    $smsSenderId = $smsDetails['twilio']['number'] ?? '';
                    $smsUsername = '';
                    $smsPassword = '';
                } elseif ($smsType === 'mobishastra') {
                    $smsApiKey = $smsDetails['mobishastra']['profile_id'] ?? '';
                    $smsApiSecret = '';
                    $smsSenderId = $smsDetails['mobishastra']['sender_id'] ?? '';
                    $smsUsername = '';
                    $smsPassword = $smsDetails['mobishastra']['password'] ?? '';
                } elseif ($smsType === 'mimsms') {
                    $smsApiKey = $smsDetails['mim_sms']['api_key'] ?? '';
                    $smsApiSecret = '';
                    $smsSenderId = $smsDetails['mim_sms']['sender_id'] ?? '';
                    $smsUsername = $smsDetails['mim_sms']['username'] ?? '';
                    $smsPassword = '';
                } elseif ($smsType === 'textlocal') {
                    $smsApiKey = $smsDetails['text_local']['api_key'] ?? '';
                    $smsApiSecret = '';
                    $smsSenderId = $smsDetails['text_local']['sender_id'] ?? '';
                    $smsUsername = '';
                    $smsPassword = '';
                } else {
                    $smsApiKey = '';
                    $smsApiSecret = '';
                    $smsSenderId = '';
                    $smsUsername = '';
                    $smsPassword = '';
                }
            }

            switch ($smsType) {
                case 'mobishastra':
                    return $this->sendViaMobishastra($to, $message, $smsApiKey, $smsSenderId, $smsPassword);
                case 'twilio':
                    return $this->sendViaTwilio($to, $message, $smsApiKey, $smsApiSecret, $smsSenderId);
                case 'textlocal':
                    return $this->sendViaTextLocal($to, $message, $smsApiKey, $smsSenderId);
                case 'mimsms':
                    return $this->sendViaMiMSMS($to, $message, $smsApiKey, $smsSenderId, $smsUsername);
                default:
                    return [
                        'status' => 'Error',
                        'message' => 'Unsupported SMS service: ' . $smsType
                    ];
            }
        } catch (\Exception $e) {
            Log::error('SMS sending failed', ['exception' => $e]);
            return [
                'status' => 'Error',
                'message' => 'SMS sending failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send SMS via Mobishastra
     */
    private function sendViaMobishastra($to, $message, $apiKey, $senderId, $password)
    {
        try {
            $response = Http::post('https://api.mobishastra.com/send_message.php', [
                'apikey' => $apiKey,
                'sender' => $senderId,
                'number' => $to,
                'message' => $message,
                'type' => 'text',
                'password' => $password
            ]);

            $result = $response->json();

            if ($response->successful() && isset($result['status']) && $result['status'] === 'success') {
                return [
                    'status' => 'Success',
                    'message' => 'SMS sent successfully via Mobishastra'
                ];
            } else {
                return [
                    'status' => 'Error',
                    'message' => 'Mobishastra SMS sending failed: ' . ($result['message'] ?? 'Unknown error')
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'Error',
                'message' => 'Mobishastra SMS sending failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send SMS via Twilio
     */
    private function sendViaTwilio($to, $message, $accountSid, $authToken, $fromNumber)
    {
        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'To' => $to,
                    'From' => $fromNumber,
                    'Body' => $message
                ]);

            $result = $response->json();

            if ($response->successful() && isset($result['status']) && in_array($result['status'], ['queued', 'sent', 'delivered'])) {
                return [
                    'status' => 'Success',
                    'message' => 'SMS sent successfully via Twilio'
                ];
            } else {
                return [
                    'status' => 'Error',
                    'message' => 'Twilio SMS sending failed: ' . ($result['message'] ?? 'Unknown error')
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'Error',
                'message' => 'Twilio SMS sending failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send SMS via TextLocal
     */
    private function sendViaTextLocal($to, $message, $apiKey, $senderId)
    {
        try {
            $response = Http::post('https://api.textlocal.in/send/', [
                'apikey' => $apiKey,
                'sender' => $senderId,
                'numbers' => $to,
                'message' => $message,
                'test' => false
            ]);

            $result = $response->json();

            if ($response->successful() && isset($result['status']) && $result['status'] === 'success') {
                return [
                    'status' => 'Success',
                    'message' => 'SMS sent successfully via TextLocal'
                ];
            } else {
                return [
                    'status' => 'Error',
                    'message' => 'TextLocal SMS sending failed: ' . ($result['message'] ?? 'Unknown error')
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'Error',
                'message' => 'TextLocal SMS sending failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send SMS via MiMSMS
     */
    private function sendViaMiMSMS($to, $message, $apiKey, $senderId, $username)
    {
        try {
            $phone = "88".$to;
            $message = urlencode($message);
            $url = "https://api.mimsms.com/api/SmsSending/Send?"
                . "UserName=" . urlencode($username)
                . "&Apikey=" . urlencode($apiKey)
                . "&MobileNumber=" . urlencode($phone)
                . "&SenderName=" . urlencode($senderId)
                . "&TransactionType=T"
                . "&Message=" . $message;
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
            ]);
            $response = curl_exec($curl);
            if (curl_errno($curl)) {
                echo "cURL Error: " . curl_error($curl);
            }
            curl_close($curl);
            return $response;
        } catch (\Exception $e) {
            return [
                'status' => 'Error',
                'message' => 'MiMSMS SMS sending failed: ' . $e->getMessage()
            ];
        }
    }
}
