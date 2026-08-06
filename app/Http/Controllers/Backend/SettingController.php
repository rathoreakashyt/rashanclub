<?php

namespace App\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Backend\TimeZone;
use Modules\Sale\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Configuration\Models\Company;
use Modules\Accounting\Models\PaymentMethod;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    public function setting()
    {
        $data = [];
        $data['timezones'] = TimeZone::where('del_status', 'Live')->get();
        $data['company'] = Company::findOrFail(session('company.company_id'));
        $data['customers'] = Customer::where([
            ['del_status', 'Live'],
            ['company_id', session('company.company_id')]
        ])->get();
        $data['payment_methods'] = PaymentMethod::where([
            ['del_status', 'Live'],
            ['company_id', session('company.company_id')]
        ])->get();
        return view('backend.settings.setting', $data);
    }

    public function businessSetting(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'business_name' => ['required', 'string', 'max:125'],
                'address' => ['nullable', 'string', 'max:255'],
                'website' => ['nullable', 'string', 'max:255', 'url'],
                'email' => ['required', 'string', 'email', 'max:55'],
                'phone' => ['required', 'string', 'max:25'],
                'date_format' => ['required', 'string', 'in:d/m/Y,m/d/Y,Y/m/d'],
                'zone_name' => ['required', 'string', 'exists:time_zones,zone_name'],
                'currency' => ['required', 'string', 'max:25'],
                'currency_position' => ['required', 'string', 'in:Before Amount,After Amount'],
                'precision' => ['required', 'integer', 'in:1,2,3'],
                'thousands_separator' => ['required', 'string', Rule::in(['.', ','])],
                'decimals_separator' => ['required', 'string', Rule::in(['.', ','])],
                'installment_days' => ['required', 'integer', 'in:3,7,15'],
                'e_commerce_checker' => ['required', 'string', 'in:Yes,No'],
                'is_loyalty_enable' => ['required', 'string', 'in:Enable,Disable'],
                'minimum_point_to_redeem' => ['required_if:is_loyalty_enable,Enable', 'string', 'max:25'],
                'loyalty_rate' => ['required_if:is_loyalty_enable,Enable', 'string', 'max:25'],
                'product_code_start_from' => ['required', 'string', 'min:1']
            ], [
                'business_name.required' => 'Business name is required',
                'business_name.max' => 'Business name must not exceed 125 characters',
                'address.max' => 'Address must not exceed 255 characters',
                'website.url' => 'Please enter a valid website URL',
                'email.required' => 'Email is required',
                'email.email' => 'Please enter a valid email address',
                'email.max' => 'Email must not exceed 55 characters',
                'phone.required' => 'Phone number is required',
                'phone.max' => 'Phone number must not exceed 25 characters',
                'date_format.required' => 'Date format is required',
                'date_format.in' => 'Invalid date format selected',
                'zone_name.required' => 'Time zone is required',
                'zone_name.exists' => 'Selected time zone is invalid',
                'currency.required' => 'Currency is required',
                'currency.max' => 'Currency must not exceed 25 characters',
                'currency_position.required' => 'Currency position is required',
                'currency_position.in' => 'Invalid currency position selected',
                'precision.required' => 'Precision is required',
                'precision.integer' => 'Precision must be a number',
                'precision.in' => 'Invalid precision selected',
                'thousands_separator.required' => 'Thousand separator is required',
                'thousands_separator.in' => 'Invalid thousand separator selected',
                'decimals_separator.required' => 'Decimal separator is required', 
                'decimals_separator.in' => 'Invalid decimal separator selected',
                'installment_days.required' => 'Installment days is required',
                'installment_days.integer' => 'Installment days must be a number',
                'installment_days.in' => 'Invalid installment days selected',
                'e_commerce_checker.required' => 'E-commerce status is required',
                'e_commerce_checker.in' => 'Invalid e-commerce status selected',
                'is_loyalty_enable.required' => 'Loyalty status is required',
                'is_loyalty_enable.in' => 'Invalid loyalty status selected',
                'minimum_point_to_redeem.required_if' => 'Minimum point to redeem is required when loyalty is enabled',
                'minimum_point_to_redeem.string' => 'Minimum point to redeem must be a number',
                'minimum_point_to_redeem.max' => 'Minimum point to redeem must not exceed 25 characters',
                'loyalty_rate.required_if' => 'Loyalty rate is required when loyalty is enabled',
                'loyalty_rate.string' => 'Loyalty rate must be a number',
                'loyalty_rate.max' => 'Loyalty rate must not exceed 25 characters',
                'product_code_start_from.required' => 'Product code start from is required',
                'product_code_start_from.integer' => 'Product code start from must be a number',
                'product_code_start_from.min' => 'Product code start from must be at least 1'
            ]);

            $company = Company::findOrFail(session('company.company_id'));
            $company->update([
                'business_name' => $validatedData['business_name'],
                'address' => $validatedData['address'],
                'website' => $validatedData['website'],
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'],
                'date_format' => $validatedData['date_format'],
                'zone_name' => $validatedData['zone_name'],
                'currency' => $validatedData['currency'],
                'currency_position' => $validatedData['currency_position'],
                'precision' => $validatedData['precision'],
                'thousands_separator' => $validatedData['thousands_separator'],
                'decimals_separator' => $validatedData['decimals_separator'],
                'installment_days' => $validatedData['installment_days'],
                'e_commerce_checker' => $validatedData['e_commerce_checker'],
                'is_loyalty_enable' => $validatedData['is_loyalty_enable'],
                'minimum_point_to_redeem' => $validatedData['minimum_point_to_redeem'],
                'loyalty_rate' => $validatedData['loyalty_rate'],
                'product_code_start_from' => $validatedData['product_code_start_from']
            ]);

            // Update session
            $sessionCompany = session('company');
            $updateData = [
                'business_name' => $validatedData['business_name'],
                'address' => $validatedData['address'],
                'website' => $validatedData['website'],
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'],
                'date_format' => $validatedData['date_format'],
                'zone_name' => $validatedData['zone_name'],
                'currency' => $validatedData['currency'],
                'currency_position' => $validatedData['currency_position'],
                'precision' => $validatedData['precision'],
                'thousands_separator' => $validatedData['thousands_separator'],
                'decimals_separator' => $validatedData['decimals_separator'],
                'installment_days' => $validatedData['installment_days'],
                'e_commerce_checker' => $validatedData['e_commerce_checker'],
                'is_loyalty_enable' => $validatedData['is_loyalty_enable'],
                'minimum_point_to_redeem' => $validatedData['minimum_point_to_redeem'],
                'loyalty_rate' => $validatedData['loyalty_rate'],
                'product_code_start_from' => $validatedData['product_code_start_from'],
            ];
            foreach ($updateData as $key => $value) {
                if (array_key_exists($key, $sessionCompany)) {
                    $sessionCompany[$key] = $value;
                }
            }
            session(['company' => $sessionCompany]);
            return response()->json([
                'status' => 'success',
                'message' => 'Business setting saved successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create Brand',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function posSetting(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'allow_less_sale' => ['required', 'string', 'in:Yes,No'],
                'default_customer' => ['required', 'exists:customers,id'],
                'default_payment' => ['required', 'exists:payment_methods,id'],
                'pos_total_payable_type' => ['required', 'string', 'in:0,1,0.05,0.01,0.5'],
                'default_cursor_position' => ['required', 'string', 'in:Search Box,Barcode Box'],
                'product_display' => ['required', 'string', 'in:Image View,Box View'],
                'onscreen_keyboard_status' => ['required', 'string', 'in:Enable,Disable'],
                'grocery_experience' => ['required', 'string', 'in:Regular,Medicine,Grocery'],
                'direct_cart' => ['required', 'string', 'in:Yes,No']
            ], [
                'allow_less_sale.required' => 'Allow less sale is required',
                'allow_less_sale.in' => 'Invalid allow less sale option selected',
                'default_customer.required' => 'Default customer is required',
                'default_customer.exists' => 'Selected customer is invalid',
                'default_payment.required' => 'Default payment is required',
                'default_payment.exists' => 'Selected payment method is invalid',
                'pos_total_payable_type.required' => 'POS total payable type is required',
                'pos_total_payable_type.in' => 'Invalid POS total payable type selected',
                'default_cursor_position.required' => 'Default cursor position is required',
                'default_cursor_position.in' => 'Invalid cursor position selected',
                'product_display.required' => 'Product display is required',
                'product_display.in' => 'Invalid product display selected',
                'onscreen_keyboard_status.required' => 'Onscreen keyboard status is required',
                'onscreen_keyboard_status.in' => 'Invalid keyboard status selected',
                'grocery_experience.required' => 'Grocery experience is required',
                'grocery_experience.in' => 'Invalid grocery experience selected',
                'direct_cart.required' => 'Direct cart is required',
                'direct_cart.in' => 'Invalid direct cart option selected'
            ]);

            $registerContent = [
                'register_expense' => $request->register_expense,
                'register_purchase' => $request->register_purchase, 
                'register_purchase_return' => $request->register_purchase_return,
                'register_supplier_payment' => $request->register_supplier_payment,
                'register_sale' => $request->register_sale,
                'register_sale_return' => $request->register_sale_return,
                'register_installment_down_payment' => $request->register_installment_down_payment,
                'register_installment_collection' => $request->register_installment_collection,
                'register_customer_due_receive' => $request->register_customer_due_receive,
                'register_servicing' => $request->register_servicing
            ];

            // Add register_content to validated data
            $validatedData['register_content'] = json_encode($registerContent);

            $company = Company::findOrFail(session('company.company_id'));
            $company->update([
                'allow_less_sale' => $validatedData['allow_less_sale'],
                'default_customer' => $validatedData['default_customer'],
                'default_payment' => $validatedData['default_payment'],
                'pos_total_payable_type' => $validatedData['pos_total_payable_type'],
                'default_cursor_position' => $validatedData['default_cursor_position'],
                'product_display' => $validatedData['product_display'],
                'onscreen_keyboard_status' => $validatedData['onscreen_keyboard_status'],
                'grocery_experience' => $validatedData['grocery_experience'],
                'direct_cart' => $validatedData['direct_cart'],
                'register_content' => $validatedData['register_content']
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'POS setting saved successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save POS settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function taxSetting(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'collect_tax' => ['required', 'string', 'in:Yes,No'],
                'tax_type' => ['required', 'string', 'in:1,2'],
                'tax_title' => ['required', 'string', 'max:55'],
                'tax_registration_no' => ['required', 'string', 'max:55'],
                'tax_is_gst' => ['required', 'string', 'in:Yes,No'],
                'taxes.*' => ['required', 'string'],
                'tax_rate.*' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            // GST CHECK
            if ($validatedData['tax_is_gst'] === 'Yes') {
                $taxes = array_map('strtoupper', $validatedData['taxes']);
                $requiredTaxes = ['CGST', 'SGST', 'IGST'];
                $missingTaxes = array_diff($requiredTaxes, $taxes);

                if (!empty($missingTaxes)) {
                    throw ValidationException::withMessages([
                        'taxes' => ['When GST is enabled, CGST, SGST and IGST are required. Missing: ' . implode(', ', $missingTaxes)]
                    ]);
                }
            }

            // Prepare tax settings
            $taxSettings = [];
            foreach ($validatedData['taxes'] as $key => $taxName) {
                $taxSettings[] = [
                    'id' => '1',
                    'tax' => $taxName,
                    'tax_rate' => $validatedData['tax_rate'][$key]
                ];
            }

            $taxString = implode(':', array_map(fn($t) => $t['tax'], $taxSettings)) . ':';

            $company = Company::findOrFail(session('company.company_id'));

            // Save DB
            $company->update([
                'collect_tax' => $validatedData['collect_tax'],
                'tax_type' => $validatedData['tax_type'], 
                'tax_title' => $validatedData['tax_title'],
                'tax_registration_no' => $validatedData['tax_registration_no'],
                'tax_is_gst' => $validatedData['tax_is_gst'],
            ]);

            // ------------------------------
            // UPDATE SESSION ONLY MATCHING KEYS
            // ------------------------------
            $sessionCompany = session('company');

            $updateData = [
                'collect_tax' => $validatedData['collect_tax'],
                'tax_type' => $validatedData['tax_type'],
                'tax_title' => $validatedData['tax_title'],
                'tax_registration_no' => $validatedData['tax_registration_no'],
                'tax_is_gst' => $validatedData['tax_is_gst'],
            ];

            foreach ($updateData as $key => $value) {
                if (array_key_exists($key, $sessionCompany)) {
                    $sessionCompany[$key] = $value;
                }
            }

            session(['company' => $sessionCompany]);


            return response()->json([
                'status' => 'success',
                'message' => 'Tax setting saved successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save tax settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function emailSetting(Request $request)
    {
        try {
            $validatedData = $request->validate([
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
            ], [
                'smtp_type.required' => 'SMTP type is required',
                'smtp_type.in' => 'Invalid SMTP type selected',
                'smtp_enable_status.required' => 'SMTP enable status is required',
                'smtp_enable_status.in' => 'Invalid SMTP enable status selected',
                'host_name.required' => 'Host name is required',
                'host_name.max' => 'Host name must not exceed 55 characters',
                'port_address.required' => 'Port address is required',
                'port_address.max' => 'Port address must not exceed 10 characters',
                'encryption.required' => 'Encryption is required',
                'encryption.max' => 'Encryption must not exceed 50 characters',
                'user_name.required' => 'User name is required',
                'user_name.max' => 'User name must not exceed 55 characters',
                'password.required' => 'Password is required',
                'password.max' => 'Password must not exceed 255 characters',
                'from_name.required' => 'From name is required',
                'from_name.max' => 'From name must not exceed 55 characters',
                'from_email.required' => 'From email is required',
                'from_email.email' => 'From email must be a valid email address',
                'from_email.max' => 'From email must not exceed 55 characters',
                'api_key.max' => 'API key must not exceed 55 characters',
            ]);

            // Additional validation for Sendinblue API key
            if ($validatedData['smtp_type'] === 'Sendinblue' && empty($validatedData['api_key'])) {
                throw ValidationException::withMessages([
                    'api_key' => ['API key is required']
                ]);
            }

            $company = Company::findOrFail(session('company.company_id'));

            $smtpDetails = [
                'host_name' => $validatedData['host_name'],
                'port_address' => $validatedData['port_address'],
                'encryption' => $validatedData['encryption'],
                'user_name' => $validatedData['user_name'],
                'password' => $validatedData['password'],
                'from_name' => $validatedData['from_name'],
                'from_email' => $validatedData['from_email'],
                'api_key' => $validatedData['api_key'] ?? null,
            ];

            $company->smtp_type = $validatedData['smtp_type'];
            $company->smtp_enable_status = $validatedData['smtp_enable_status'];
            $company->smtp_details = json_encode($smtpDetails);
            $company->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Email setting saved successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save email settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function smsSetting(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'sms_service_provider' => ['required', 'string', 'in:1,2,3,4'],
                'sms_enable_status' => ['required', 'string', 'in:1,2'],
            ], [
                'sms_service_provider.required' => 'SMS service provider is required',
                'sms_service_provider.in' => 'Invalid SMS service provider selected',
                'sms_enable_status.required' => 'SMS enable status is required',
                'sms_enable_status.in' => 'Invalid SMS enable status selected',
            ]);

            // Validate fields based on selected provider
            if ($request->sms_service_provider == '1') {
                $validatedData = array_merge($validatedData, $request->validate([
                    'twilio_sid' => ['required', 'string', 'max:255'],
                    'twilio_token' => ['required', 'string', 'max:255'],
                    'twilio_number' => ['required', 'string', 'max:20'],
                ], [
                    'twilio_sid.required' => 'Twilio SID is required',
                    'twilio_token.required' => 'Twilio token is required',
                    'twilio_number.required' => 'Twilio number is required',
                ]));
            } elseif ($request->sms_service_provider == '2') {
                $validatedData = array_merge($validatedData, $request->validate([
                    'mobishastra_profile_id' => ['required', 'string', 'max:255'],
                    'mobishastra_password' => ['required', 'string', 'max:255'],
                    'mobishastra_sender_id' => ['required', 'string', 'max:20'],
                    'mobishastra_country_code' => ['required', 'string', 'max:10'],
                ], [
                    'mobishastra_profile_id.required' => 'Mobishastra profile ID is required',
                    'mobishastra_password.required' => 'Mobishastra password is required',
                    'mobishastra_sender_id.required' => 'Mobishastra sender ID is required',
                    'mobishastra_country_code.required' => 'Mobishastra country code is required',
                ]));
            } elseif ($request->sms_service_provider == '3') {
                $validatedData = array_merge($validatedData, $request->validate([
                    'mim_sms_api_key' => ['required', 'string', 'max:255'],
                    'mim_sms_sender_id' => ['required', 'string', 'max:20'],
                ], [
                    'mim_sms_api_key.required' => 'Mim SMS API key is required',
                    'mim_sms_sender_id.required' => 'Mim SMS sender ID is required',
                ]));
            } elseif ($request->sms_service_provider == '4') {
                $validatedData = array_merge($validatedData, $request->validate([
                    'text_local_profile_id' => ['required', 'string', 'max:255'],
                    'text_local_api_key' => ['required', 'string', 'max:255'],
                    'text_local_sender_id' => ['required', 'string', 'max:20'],
                ], [
                    'text_local_profile_id.required' => 'Text Local profile ID is required',
                    'text_local_api_key.required' => 'Text Local API key is required',
                    'text_local_sender_id.required' => 'Text Local sender ID is required',
                ]));
            }

            $company = Company::findOrFail(session('company.company_id'));
            $smsDetails = [];
            
            $smsDetails = [
                'twilio' => [
                    'sid' => $validatedData['twilio_sid'] ?? '',
                    'token' => $validatedData['twilio_token'] ?? '',
                    'number' => $validatedData['twilio_number'] ?? ''
                ],
                'mobishastra' => [
                    'profile_id' => $validatedData['mobishastra_profile_id'] ?? '',
                    'password' => $validatedData['mobishastra_password'] ?? '',
                    'sender_id' => $validatedData['mobishastra_sender_id'] ?? '',
                    'country_code' => $validatedData['mobishastra_country_code'] ?? ''
                ],
                'mim_sms' => [
                    'api_key' => $validatedData['mim_sms_api_key'] ?? '',
                    'sender_id' => $validatedData['mim_sms_sender_id'] ?? ''
                ],
                'text_local' => [
                    'profile_id' => $validatedData['text_local_profile_id'] ?? '',
                    'api_key' => $validatedData['text_local_api_key'] ?? '',
                    'sender_id' => $validatedData['text_local_sender_id'] ?? ''
                ]
            ];

            $company->sms_service_provider = $validatedData['sms_service_provider'];
            $company->sms_enable_status = $validatedData['sms_enable_status'];
            $company->sms_details = json_encode($smsDetails);
            $company->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'SMS setting saved successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save SMS settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function whatsappSetting(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'whatsapp_invoice_enable_status' => ['required', 'string', 'in:Enable,Disable'],
                'whatsapp_app_key' => ['required', 'string', 'max:255'],
                'whatsapp_authkey' => ['required', 'string', 'max:255']
            ], [
                'whatsapp_invoice_enable_status.required' => 'WhatsApp enable status is required',
                'whatsapp_invoice_enable_status.in' => 'Invalid WhatsApp enable status selected',
                'whatsapp_app_key.required' => 'WhatsApp app key is required',
                'whatsapp_authkey.required' => 'WhatsApp auth key is required'
            ]);
            $company = Company::findOrFail(session('company.company_id'));
            $company->whatsapp_invoice_enable_status = $validatedData['whatsapp_invoice_enable_status'];
            $company->whatsapp_app_key = $validatedData['whatsapp_app_key'];
            $company->whatsapp_authkey = $validatedData['whatsapp_authkey'];
            $company->save();
            return response()->json([
                'status' => 'success',
                'message' => 'WhatsApp setting saved successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save WhatsApp settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function paymentSetting(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'action_type_stripe' => ['required', 'string', 'in:Enable,Disable'],
                'action_type_paypal' => ['required', 'string', 'in:Enable,Disable'],
                'stripe_api_key' => ['required_if:action_type_stripe,Enable', 'string', 'max:255'],
                'stripe_publishable_key' => ['required_if:action_type_stripe,Enable', 'string', 'max:255'],
                'paypal_user_name' => ['required_if:action_type_paypal,Enable', 'string', 'max:255'],
                'paypal_password' => ['required_if:action_type_paypal,Enable', 'string', 'max:255'],
                'paypal_signature' => ['required_if:action_type_paypal,Enable', 'string', 'max:255'],
            ], [
                'action_type_stripe.required' => 'Stripe enable status is required',
                'action_type_stripe.in' => 'Invalid Stripe enable status selected',
                'action_type_paypal.required' => 'PayPal enable status is required',
                'action_type_paypal.in' => 'Invalid PayPal enable status selected',
                'stripe_api_key.required_if' => 'Stripe API key is required when Stripe is enabled',
                'stripe_publishable_key.required_if' => 'Stripe publishable key is required when Stripe is enabled',
                'paypal_user_name.required_if' => 'PayPal username is required when PayPal is enabled',
                'paypal_password.required_if' => 'PayPal password is required when PayPal is enabled',
                'paypal_signature.required_if' => 'PayPal signature is required when PayPal is enabled',
            ]);
            $company = Company::findOrFail(session('company.company_id'));
            $paymentDetails = [];
            
            $paymentDetails = [
                'stripe' => [
                    'action_type' => $validatedData['action_type_stripe'] ?? '',
                    'api_key' => $validatedData['stripe_api_key'] ?? '',
                    'publishable_key' => $validatedData['stripe_publishable_key'] ?? ''
                ],
                'paypal' => [
                    'action_type' => $validatedData['action_type_paypal'] ?? '',
                    'user_name' => $validatedData['paypal_user_name'] ?? '',
                    'password' => $validatedData['paypal_password'] ?? '',
                    'signature' => $validatedData['paypal_signature'] ?? '',
                ],
            ];

            $company->payment_api_setting = json_encode($paymentDetails);
            $company->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Payment setting saved successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save payment settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function whitelabelSetting(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'site_name' => ['required', 'string', 'max:255'],
                'site_footer' => ['required', 'string', 'max:255'],
                'site_title' => ['required', 'string', 'max:255'],
                'site_link' => ['required', 'string', 'max:255'],
                'site_logo' => ['nullable', 'string'],
                'site_logo_file' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
                'site_favicon' => ['nullable', 'string'],
                'site_favicon_file' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,gif,svg,ico', 'max:2048'],
                'site_logo_hidden' => ['nullable', 'string'],
                'site_favicon_hidden' => ['nullable', 'string'],
            ], [
                'site_name.required' => 'Site name is required',
                'site_footer.required' => 'Site footer is required',
                'site_title.required' => 'Site title is required',
                'site_link.required' => 'Site link is required',
            ]);

            // Validate that either logo exists or new logo is provided
            if (empty($request->site_logo_hidden) && empty($request->site_logo) && !$request->hasFile('site_logo_file')) {
                throw ValidationException::withMessages([
                    'site_logo' => ['Site logo is required']
                ]);
            }

            // Validate that either favicon exists or new favicon is provided
            if (empty($request->site_favicon_hidden) && empty($request->site_favicon) && !$request->hasFile('site_favicon_file')) {
                throw ValidationException::withMessages([
                    'site_favicon' => ['Site favicon is required']
                ]);
            }

            $company = Company::findOrFail(session('company.company_id'));
            $uploadPath = public_path('uploads/whitelabel');
            createDirectory($uploadPath);

            // Handle Site Logo
            if (!empty($request->site_logo)) {
                // Base64 image from cropper
                $logoFileName = $this->storeBase64Image($request->site_logo, $uploadPath, 'site_logo_');
                if ($logoFileName) {
                    // Delete old logo if exists
                    if ($request->site_logo_hidden && file_exists($uploadPath . '/' . $request->site_logo_hidden)) {
                        unlink($uploadPath . '/' . $request->site_logo_hidden);
                    }
                    $validatedData['site_logo'] = $logoFileName;
                } else {
                    $validatedData['site_logo'] = $request->site_logo_hidden;
                }
            } elseif ($request->hasFile('site_logo_file')) {
                // Regular file upload
                $oldLogoPath = $uploadPath . '/' . $request->site_logo_hidden;
                if (file_exists($oldLogoPath) && !is_dir($oldLogoPath)) {
                    unlink($oldLogoPath);
                }
                $siteLogo = $request->file('site_logo_file');
                $siteLogoName = 'site_logo_' . time() . '.' . $siteLogo->getClientOriginalExtension();
                $siteLogo->move($uploadPath, $siteLogoName);
                $validatedData['site_logo'] = $siteLogoName;
            } else {
                $validatedData['site_logo'] = $request->site_logo_hidden;
            }

            // Handle Site Favicon
            if (!empty($request->site_favicon)) {
                // Base64 image from cropper
                $faviconFileName = $this->storeBase64Image($request->site_favicon, $uploadPath, 'site_favicon_');
                if ($faviconFileName) {
                    // Delete old favicon if exists
                    if ($request->site_favicon_hidden && file_exists($uploadPath . '/' . $request->site_favicon_hidden)) {
                        unlink($uploadPath . '/' . $request->site_favicon_hidden);
                    }
                    $validatedData['site_favicon'] = $faviconFileName;
                } else {
                    $validatedData['site_favicon'] = $request->site_favicon_hidden;
                }
            } elseif ($request->hasFile('site_favicon_file')) {
                // Regular file upload
                $oldFaviconPath = $uploadPath . '/' . $request->site_favicon_hidden;
                if (file_exists($oldFaviconPath) && !is_dir($oldFaviconPath)) {
                    unlink($oldFaviconPath);
                }
                $siteFavicon = $request->file('site_favicon_file');
                $siteFaviconName = 'site_favicon_' . time() . '.' . $siteFavicon->getClientOriginalExtension();
                $siteFavicon->move($uploadPath, $siteFaviconName);
                $validatedData['site_favicon'] = $siteFaviconName;
            } else {
                $validatedData['site_favicon'] = $request->site_favicon_hidden;
            }

            $whiteLabelDetails = [
                'site_name' => $validatedData['site_name'],
                'site_footer' => $validatedData['site_footer'],
                'site_title' => $validatedData['site_title'],
                'site_link' => $validatedData['site_link'],
                'site_logo' => $validatedData['site_logo'],
                'site_favicon' => $validatedData['site_favicon'],
            ];
            $company->white_label = json_encode($whiteLabelDetails);
            $company->save();
            return response()->json([
                'status' => 'success',
                'message' => 'White label settings saved successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save white label settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store base64 image from cropper
     */
    private function storeBase64Image($base64Image, $uploadPath, $prefix = ''): ?string
    {
        // Check if it's a base64 string (from cropper)
        if (is_string($base64Image) && strpos($base64Image, 'data:image') === 0) {
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));
            $fileName = $prefix . time() . '_' . uniqid() . '.jpg';
            
            // Save the image
            file_put_contents($uploadPath . '/' . $fileName, $imageData);
            
            return $fileName;
        }

        return null;
    }
}
