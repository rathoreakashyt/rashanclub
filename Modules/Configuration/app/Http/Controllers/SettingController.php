<?php

namespace Modules\Configuration\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Sale\Models\Customer;
use App\Http\Controllers\Controller;
use Modules\Configuration\Models\Company;
use Modules\Configuration\Models\Tax;
use Modules\Configuration\Models\TimeZone;
use Modules\Accounting\Models\PaymentMethod;
use Illuminate\Validation\ValidationException;
use Modules\Configuration\Services\SMSService;
use Modules\Configuration\Services\EmailService;
use Modules\Configuration\Services\SettingService;
use Modules\Configuration\Services\WhatsappService;
use Modules\Configuration\Http\Request\PosSettingRequest;
use Modules\Configuration\Http\Request\SmsSettingRequest;
use Modules\Configuration\Http\Request\StoreTaxRequest;
use Modules\Configuration\Http\Request\TaxSettingRequest;
use Modules\Configuration\Http\Request\EmailSettingRequest;
use Modules\Configuration\Http\Request\InvoiceSettingRequest;
use Modules\Configuration\Http\Request\PaymentSettingRequest;
use Modules\Configuration\Http\Request\BusinessSettingRequest;
use Modules\Configuration\Http\Request\WhatsappSettingRequest;
use Modules\Configuration\Http\Request\WhitelabelSettingRequest;
use Modules\Configuration\Http\Request\ZatcaSettingRequest;
use App\Services\PwaSettingService;
use App\Http\Requests\PwaSettingRequest;

class SettingController extends Controller
{
    protected $settingService;
    protected $emailService;
    protected $smsService;
    protected $whatsappService;
    protected $pwaSettingService;

    public function __construct(
        SettingService $settingService,
        EmailService $emailService,
        SMSService $smsService,
        WhatsappService $whatsappService,
        PwaSettingService $pwaSettingService
    ) {
        $this->settingService = $settingService;
        $this->emailService = $emailService;
        $this->smsService = $smsService;
        $this->whatsappService = $whatsappService;
        $this->pwaSettingService = $pwaSettingService;
    }

    /**
     * Display the settings page
    */
    public function setting()
    {
        $data = [];
        $data['timezones'] = TimeZone::where('del_status', 'Live')->get();
        $data['company'] = $this->settingService->getCompanyForSettings();
        $data['pwaSettings'] = $this->pwaSettingService->getOrCreateSettings();
        $data['customers'] = Customer::where([
            ['del_status', 'Live'],
            ['company_id', session('company.company_id')]
        ])->get();
        $data['payment_methods'] = PaymentMethod::where([
            ['del_status', 'Live'],
            ['company_id', session('company.company_id')]
        ])->get();
        $taxs = Tax::forCompany()->live()->with('childTaxes')->orderBy('id')->get();
        $data['taxs'] = $taxs;
        $data['tax_groups'] = $this->settingService->buildTaxGroups($taxs);
        $data['available_sub_taxes'] = Tax::forCompany()->live()->gstSubTaxTypes()->orderBy('tax_name')->get();
        return view('configuration::backend.settings.setting', $data);
    }

    /**
     * Update business settings
     */
    public function businessSetting(BusinessSettingRequest $request)
    {
        try {
            $result = $this->settingService->updateBusinessSettings($request->validated());
            return response()->json($result, 201);
        } catch (\Exception $e) {
            \Log::error('businessSetting failed: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => collect($e->getTrace())->take(3)->toArray(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save business settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update POS settings
     */
    public function posSetting(PosSettingRequest $request)
    {
        try {
            $result = $this->settingService->updatePosSettings($request->all());
            return response()->json($result, 201);
        } catch (\Exception $e) {
            \Log::error('posSetting failed: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => collect($e->getTrace())->take(3)->toArray(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save POS settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Update POS settings
     */
    public function posLayout(Request $request)
    {
        try {
            $company = Company::where('id', session('company.company_id'))->update([
                'grocery_experience' => $request->grocery_experience,
            ]);
            // Update session only grocery_experience
            $sessionCompany = session('company');
            $sessionCompany['grocery_experience'] = $request->grocery_experience;
            session(['company' => $sessionCompany]);
            return response()->json([
                'status' => 'success',
                'message' => 'POS setting saved successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save POS settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update tax settings (collect_tax, tax_title, etc.)
     */
    public function taxSetting(TaxSettingRequest $request)
    {
        try {
            $result = $this->settingService->updateTaxSettings($request->validated());
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save tax settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new tax
     */
    public function storeTax(StoreTaxRequest $request)
    {
        try {
            $result = $this->settingService->storeTax($request->validated());
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add tax',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing tax
     */
    public function updateTax(StoreTaxRequest $request, int $id)
    {
        try {
            $result = $this->settingService->updateTax($id, $request->validated());
            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update tax',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a tax
     */
    public function deleteTax(int $id)
    {
        try {
            $result = $this->settingService->deleteTax($id);
            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete tax',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update email settings
     */
    public function emailSetting(EmailSettingRequest $request)
    {
        try {
            $result = $this->settingService->updateEmailSettings($request->validated());
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save email settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update SMS settings
     */
    public function smsSetting(SmsSettingRequest $request)
    {
        try {
            $result = $this->settingService->updateSmsSettings($request->validated());
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save SMS settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update WhatsApp settings
     */
    public function whatsappSetting(WhatsappSettingRequest $request)
    {
        try {
            $result = $this->settingService->updateWhatsAppSettings($request->validated());
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save WhatsApp settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment gateway settings
     */
    public function paymentSetting(PaymentSettingRequest $request)
    {
        try {
            $result = $this->settingService->updatePaymentSettings($request->validated());
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save payment settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update invoice settings
     */
    public function invoiceSetting(InvoiceSettingRequest $request)
    {
        try {
            $result = $this->settingService->updateInvoiceSettings($request->validated(), $request);
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save invoice settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update whitelabel settings
     */
    public function whitelabelSetting(WhitelabelSettingRequest $request)
    {
        try {
            $result = $this->settingService->updateWhitelabelSettings($request->validated(), $request);
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save white label settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update PWA settings
     */
    public function pwaSetting(PwaSettingRequest $request)
    {
        try {
            $result = $this->pwaSettingService->updateSettings(
                $request->validated(),
                $request->file('logo_file')
            );
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save PWA settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update ZATCA settings
     */
    public function zatcaSetting(ZatcaSettingRequest $request)
    {
        try {
            $result = $this->settingService->updateZatcaSettings($request->validated());
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save ZATCA settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate ZATCA directory
     */
    public function generateZatcaDirectory(Request $request)
    {
        try {
            // Use direct filesystem path to ensure storage/app/zatca
            $directoryPath = storage_path('app/zatca');

            if (!file_exists($directoryPath)) {
                // Create directory recursively if it doesn't exist
                if (!mkdir($directoryPath, 0755, true)) {
                    throw new \Exception('Failed to create directory');
                }
                $message = 'ZATCA directory created successfully';
            } else {
                $message = 'ZATCA directory already exists';
            }

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'path' => $directoryPath
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create directory: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate CSR (Certificate Signing Request) for ZATCA
     */
    public function generateCsr(Request $request)
    {
        try {
            $validated = $request->validate([
                'vat_registration_number' => 'required|string|max:255',
                'legal_business_name_arabic' => 'required|string|max:255',
                'legal_business_name_english' => 'required|string|max:255',
                'zatca_address' => 'required|string',
            ]);


            // Use direct filesystem path to ensure storage/app/zatca
            $directoryPath = storage_path('app/zatca');
            if (!file_exists($directoryPath)) {
                // Create directory recursively if it doesn't exist
                if (!mkdir($directoryPath, 0755, true)) {
                    throw new \Exception('Failed to create directory');
                }
            } 
    

            // Generate private key with Windows OpenSSL fix
            $config = [
                "digest_alg" => "sha256",
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
            ];

            // Fix for Windows OpenSSL error:80000003
            // Create a minimal OpenSSL config file if needed
            $opensslConfigPath = $this->createOpenSSLConfigIfNeeded();
            if ($opensslConfigPath) {
                $config["config"] = $opensslConfigPath;
            }

            $privateKeyResource = openssl_pkey_new($config);
            if (!$privateKeyResource) {
                $error = '';
                while (($err = openssl_error_string()) !== false) {
                    $error .= $err . "\n";
                }
                throw new \Exception('Failed to generate private key: ' . ($error ?: 'Unknown OpenSSL error'));
            }

            // Export private key with same config
            openssl_pkey_export($privateKeyResource, $privateKey, null, $config);
            if (!$privateKey) {
                $error = '';
                while (($err = openssl_error_string()) !== false) {
                    $error .= $err . "\n";
                }
                throw new \Exception('Failed to export private key: ' . ($error ?: 'Unknown OpenSSL error'));
            }

            // Create CSR
            $dn = [
                "countryName" => "SA",
                "stateOrProvinceName" => "Saudi Arabia",
                "localityName" => $validated['zatca_address'],
                "organizationName" => $validated['legal_business_name_english'],
                "organizationalUnitName" => "ZATCA",
                "commonName" => $validated['vat_registration_number'],
            ];

            // Create CSR with same config
            $csrResource = openssl_csr_new($dn, $privateKeyResource, $config);
            if (!$csrResource) {
                $error = '';
                while (($err = openssl_error_string()) !== false) {
                    $error .= $err . "\n";
                }
                throw new \Exception('Failed to generate CSR: ' . ($error ?: 'Unknown OpenSSL error'));
            }

            // Export CSR
            openssl_csr_export($csrResource, $csr);
            if (!$csr) {
                $error = '';
                while (($err = openssl_error_string()) !== false) {
                    $error .= $err . "\n";
                }
                throw new \Exception('Failed to export CSR: ' . ($error ?: 'Unknown OpenSSL error'));
            }

            // Save files directly to storage/app/zatca
            $privateKeyPath = storage_path('app/zatca/private_key.pem');
            $csrPath = storage_path('app/zatca/csr.pem');
            
            file_put_contents($privateKeyPath, $privateKey);
            file_put_contents($csrPath, $csr);

            // Clean up resources
            openssl_free_key($privateKeyResource);
            if ($csrResource) {
                unset($csrResource);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'CSR and Private Key generated successfully',
                'files' => [
                    'csr' => route('zatca.download', ['file' => 'csr']),
                    'private_key' => route('zatca.download', ['file' => 'private_key']),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate CSR: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download ZATCA files (CSR or Private Key)
     */
    public function downloadZatcaFile(Request $request, $file)
    {
        try {
            $allowedFiles = ['csr', 'private_key'];
            if (!in_array($file, $allowedFiles)) {
                abort(404, 'File not found');
            }

            // Use direct path to storage/app/zatca
            $fileName = $file === 'csr' ? 'csr.pem' : 'private_key.pem';
            $filePath = storage_path('app/zatca/' . $fileName);
            
            if (!file_exists($filePath)) {
                abort(404, 'File not found');
            }

            $downloadName = $file === 'csr' ? 'zatca_csr.pem' : 'zatca_private_key.pem';
            
            return response()->download($filePath, $downloadName);
        } catch (\Exception $e) {
            abort(500, 'Failed to download file: ' . $e->getMessage());
        }
    }

    /**
     * Create OpenSSL config file if needed (fixes Windows error:80000003)
     */
    private function createOpenSSLConfigIfNeeded()
    {
        // Check if running on Windows
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        if ($isWindows) {
            // Create a minimal OpenSSL config file in storage/app/zatca
            $configPath = storage_path('app/zatca/openssl.cnf');
            $configDir = dirname($configPath);
            
            // Ensure directory exists
            if (!file_exists($configDir)) {
                mkdir($configDir, 0755, true);
            }
            
            // Create minimal OpenSSL config if it doesn't exist
            if (!file_exists($configPath)) {
                $minimalConfig = <<<'CONFIG'
                    # Minimal OpenSSL configuration for Windows
                    HOME = .
                    RANDFILE = $ENV::HOME/.rnd

                    [ req ]
                    default_bits = 2048
                    default_keyfile = privkey.pem
                    distinguished_name = req_distinguished_name
                    attributes = req_attributes
                    x509_extensions = v3_ca
                    string_mask = utf8only

                    [ req_distinguished_name ]

                    [ req_attributes ]

                    [ v3_ca ]
                    CONFIG;
                
                file_put_contents($configPath, $minimalConfig);
            }
            
            return $configPath;
        }
        
        // For Linux/Unix, try to find existing config
        $defaultPaths = [
            '/etc/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
            '/usr/local/ssl/openssl.cnf',
        ];
        
        foreach ($defaultPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Return null to use system default
        return null;
    }

    /**
     * Test email sending
     */
    public function testEmail(Request $request)
    {
        try {
            $validated = $request->validate([
                'to' => 'required|email',
                'subject' => 'required|string|max:255',
                'message' => 'required|string',
            ]);

            // Get credentials from request if provided (from form), otherwise use saved settings
            $credentials = [];
            if ($request->has('smtp_type')) {
                // Map form fields to service credentials
                $credentials = [
                    'mail_type' => $request->input('smtp_type'), // Map smtp_type to mail_type
                    'host_address' => $request->input('host_name', ''),
                    'mail_port' => $request->input('port_address', ''),
                    'encryption' => $request->input('encryption', 'tls'),
                    'mail_username' => $request->input('user_name', ''),
                    'mail_password' => $request->input('password', ''),
                    'mail_from' => $request->input('from_email', ''),
                    'mail_from_name' => $request->input('from_name', ''),
                    'mail_api_key' => $request->input('api_key', ''),
                ];
            }

            $result = $this->emailService->sendEmail(
                $validated['to'],
                $validated['subject'],
                $validated['message'],
                $credentials
            );

            if ($result['status'] === 'Success') {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message']
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message']
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send test email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test SMS sending
     */
    public function testSMS(Request $request)
    {
        try {
            $validated = $request->validate([
                'to' => 'required|string',
                'message' => 'required|string',
            ]);

            // Get credentials from request if provided
            $credentials = [];
            if ($request->has('sms_service_provider')) {
                $smsDetails = json_decode($request->input('sms_details', '{}'), true);
                $providerId = $request->input('sms_service_provider');
                $providerMap = ['1' => 'twilio', '2' => 'mobishastra', '3' => 'mimsms', '4' => 'textlocal'];
                $smsType = $providerMap[$providerId] ?? 'mobishastra';

                $providerDetails = [];
                if ($smsType === 'twilio' && isset($smsDetails['twilio'])) {
                    $providerDetails = $smsDetails['twilio'];
                    $credentials = [
                        'sms_type' => $smsType,
                        'sms_api_key' => $providerDetails['sid'] ?? '',
                        'sms_api_secret' => $providerDetails['token'] ?? '',
                        'sms_sender_id' => $providerDetails['number'] ?? '',
                    ];
                } elseif ($smsType === 'mobishastra' && isset($smsDetails['mobishastra'])) {
                    $providerDetails = $smsDetails['mobishastra'];
                    $credentials = [
                        'sms_type' => $smsType,
                        'sms_api_key' => $providerDetails['profile_id'] ?? '',
                        'sms_sender_id' => $providerDetails['sender_id'] ?? '',
                        'sms_password' => $providerDetails['password'] ?? '',
                    ];
                } elseif ($smsType === 'mimsms' && isset($smsDetails['mim_sms'])) {
                    $providerDetails = $smsDetails['mim_sms'];
                    $credentials = [
                        'sms_type' => $smsType,
                        'sms_api_key' => $providerDetails['api_key'] ?? '',
                        'sms_sender_id' => $providerDetails['sender_id'] ?? '',
                        'sms_username' => $providerDetails['username'] ?? '',
                    ];
                } elseif ($smsType === 'textlocal' && isset($smsDetails['text_local'])) {
                    $providerDetails = $smsDetails['text_local'];
                    $credentials = [
                        'sms_type' => $smsType,
                        'sms_api_key' => $providerDetails['api_key'] ?? '',
                        'sms_sender_id' => $providerDetails['sender_id'] ?? '',
                    ];
                }
            }

            $result = $this->smsService->sendSMS(
                $validated['to'],
                $validated['message'],
                $credentials
            );

            if ($result['status'] === 'Success') {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message']
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message']
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send test SMS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test WhatsApp sending
     */
    public function testWhatsapp(Request $request)
    {
        try {
            $validated = $request->validate([
                'to' => 'required|string',
                'message' => 'required|string',
            ]);

            // Get credentials from request if provided
            $credentials = [];
            if ($request->has('whatsapp_type')) {
                $credentials = [
                    'whatsapp_type' => $request->input('whatsapp_type'),
                    'whatsapp_app_key' => $request->input('whatsapp_app_key') ?? '',
                    'whatsapp_auth_key' => $request->input('whatsapp_authkey') ?? '',
                    'whatsapp_account_sid' => $request->input('whatsapp_account_sid') ?? '',
                    'whatsapp_auth_token' => $request->input('whatsapp_auth_token') ?? '',
                    'whatsapp_from_number' => $request->input('whatsapp_from_number') ?? '',
                ];
            }

            $result = $this->whatsappService->sendTestWhatsapp(
                $validated['to'],
                $validated['message'],
                $credentials
            );

            if ($result['status'] === 'Success') {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message']
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message']
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send test WhatsApp: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Email Marketing
     */
    public function emailMarketing()
    {
        return view('configuration::marketing.email-marketing');
    }

    /**
     * SMS Marketing
     */
    public function smsMarketing()
    {
        return view('configuration::marketing.sms-marketing');
    }

    /**
     * WhatsApp Marketing
     */
    public function whatsappMarketing()
    {
        return view('configuration::marketing.whatsapp-marketing');
    }

}

