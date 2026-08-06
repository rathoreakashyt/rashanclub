<?php

namespace Modules\Configuration\Services;

use Modules\Configuration\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    /**
     * Send WhatsApp message with optional PDF attachment (e.g. sale invoice).
     * When attachment is provided, sends the message; document is sent if the provider supports it.
     *
     * @param string $to Phone number
     * @param string $message Text message
     * @param string|null $pdfContent Raw PDF content (binary string)
     * @param string|null $filename Filename for the document (e.g. Sale No - 001.pdf)
     * @param array $credentials Optional overrides
     * @return array
     */
    public function sendTestWhatsappWithAttachment($to, $message, $pdfContent = null, $filename = null, $credentials = [])
    {
        // For now, send text only; document sending can be added per-provider when API supports it
        $result = $this->sendTestWhatsapp($to, $message, $credentials);
        if ($pdfContent && $filename && $result['status'] === 'Success') {
            Log::info('WhatsApp invoice message sent (document attachment not sent by provider)', [
                'to' => $to,
                'filename' => $filename,
            ]);
        }
        return $result;
    }

    /**
     * Send test WhatsApp message using configured WhatsApp service
     *
     * @param string $to
     * @param string $message
     * @param array $credentials
     * @return array
     */
    public function sendTestWhatsapp($to, $message, $credentials = [])
    {
        try {
            $company = Company::find(session('company.company_id', 1));

            if (!$company) {
                return [
                    'status' => 'Error',
                    'message' => 'Company not found'
                ];
            }

            // Get WhatsApp settings from credentials or database
            if (!empty($credentials)) {
                $whatsappType = $credentials['whatsapp_type'] ?? 'RC Soft';
                $whatsappAppKey = $credentials['whatsapp_app_key'] ?? '';
                $whatsappAuthKey = $credentials['whatsapp_auth_key'] ?? '';
                $whatsappAccountSid = $credentials['whatsapp_account_sid'] ?? '';
                $whatsappAuthToken = $credentials['whatsapp_auth_token'] ?? '';
                $whatsappFromNumber = $credentials['whatsapp_from_number'] ?? '';
            } else {
                // Get from company settings
                $whatsappType = $company->whatsapp_type ?? 'RC Soft';
                $whatsappAppKey = $company->whatsapp_app_key ?? '';
                $whatsappAuthKey = $company->whatsapp_authkey ?? '';
                $whatsappAccountSid = '';
                $whatsappAuthToken = '';
                $whatsappFromNumber = '';
            }

            switch ($whatsappType) {
                case 'RC Soft':
                    return $this->sendViaRCSoft($to, $message, $whatsappAppKey, $whatsappAuthKey);
                case 'Twilio':
                    return $this->sendViaTwilio($to, $message, $whatsappAccountSid, $whatsappAuthToken, $whatsappFromNumber);
                default:
                    return [
                        'status' => 'Error',
                        'message' => 'Unsupported WhatsApp service: ' . $whatsappType
                    ];
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp sending failed', ['exception' => $e]);
            return [
                'status' => 'Error',
                'message' => 'WhatsApp sending failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send WhatsApp message via RC Soft
     */
    private function sendViaRCSoft($to, $message, $appKey, $authKey)
    {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://whats-api.rcsoft.in/api/create-message',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array(
                    'appkey' => $appKey,
                    'authkey' => $authKey,
                    'to' => $to,
                    'message' => $message,
                    'sandbox' => 'false'
                ),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/x-www-form-urlencoded',
                ),
            ));

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($error) {
                Log::error('RC Soft WhatsApp cURL error', ['error' => $error]);
                return [
                    'status' => 'Error',
                    'message' => 'RC Soft WhatsApp cURL error: ' . $error
                ];
            }

            $result = json_decode($response, true);

            if ($httpCode === 200 && isset($result['status']) && $result['status'] === 'success') {
                Log::info('RC Soft WhatsApp sent successfully', ['to' => $to]);
                return [
                    'status' => 'Success',
                    'message' => 'WhatsApp message sent successfully via RC Soft'
                ];
            } else {
                Log::error('RC Soft WhatsApp API error', [
                    'http_code' => $httpCode,
                    'response' => $result
                ]);
                return [
                    'status' => 'Error',
                    'message' => 'RC Soft WhatsApp sending failed: ' . ($result['message'] ?? 'Unknown error')
                ];
            }
        } catch (\Exception $e) {
            Log::error('RC Soft WhatsApp exception', ['exception' => $e]);
            return [
                'status' => 'Error',
                'message' => 'RC Soft WhatsApp sending failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send WhatsApp message via Twilio
     */
    private function sendViaTwilio($to, $message, $accountSid, $authToken, $fromNumber)
    {
        try {
            // Ensure phone numbers are in proper format
            $to = $this->formatPhoneNumber($to);
            $fromNumber = $this->formatPhoneNumber($fromNumber);

            // Use WhatsApp Business API endpoint
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
            
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post($url, [
                    'To' => "whatsapp:{$to}",
                    'From' => "whatsapp:{$fromNumber}",
                    'Body' => $message
                ]);

            $result = $response->json();

            if ($response->successful() && isset($result['status']) && in_array($result['status'], ['queued', 'sent', 'delivered'])) {
                Log::info('Twilio WhatsApp sent successfully', ['to' => $to]);
                return [
                    'status' => 'Success',
                    'message' => 'WhatsApp message sent successfully via Twilio'
                ];
            } else {
                Log::error('Twilio WhatsApp API error', [
                    'status_code' => $response->status(),
                    'response' => $result
                ]);
                return [
                    'status' => 'Error',
                    'message' => 'Twilio WhatsApp sending failed: ' . ($result['message'] ?? 'Unknown error')
                ];
            }
        } catch (\Exception $e) {
            Log::error('Twilio WhatsApp exception', ['exception' => $e]);
            return [
                'status' => 'Error',
                'message' => 'Twilio WhatsApp sending failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Format phone number to international format
     */
    private function formatPhoneNumber($phoneNumber)
    {
        // Remove all non-digit characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // If number doesn't start with country code, assume it's a local number
        if (strlen($phoneNumber) === 10) {
            $phoneNumber = '91' . $phoneNumber; // Default to India (+91)
        }
        
        // Add + prefix
        return '+' . $phoneNumber;
    }
}
