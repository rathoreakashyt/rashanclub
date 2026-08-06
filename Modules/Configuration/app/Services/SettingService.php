<?php

namespace Modules\Configuration\Services;

use Modules\Configuration\Models\Tax;
use Modules\Configuration\Models\Company;
use Modules\Configuration\Repositories\CompanyRepository;
use Illuminate\Support\Facades\Session;

class SettingService
{
    protected $companyRepository;

    /**
     * SettingService constructor.
     *
     * @param CompanyRepository $companyRepository
     */
    public function __construct(CompanyRepository $companyRepository)
    {
        $this->companyRepository = $companyRepository;
    }

    /**
     * Get company for settings page
     */
    public function getCompanyForSettings(): Company
    {
        return $this->companyRepository->getCurrentCompany();
    }

    /**
     * Update business settings
     */
    public function updateBusinessSettings(array $data): array
    {
        $company = $this->companyRepository->getCurrentCompany();
        
        $updateData = [
            'business_name' => $data['business_name'],
            'address' => $data['address'],
            'website' => $data['website'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'date_format' => $data['date_format'],
            'zone_name' => $data['zone_name'],
            'currency' => $data['currency'],
            'currency_position' => $data['currency_position'],
            'precision' => $data['precision'],
            'thousands_separator' => $data['thousands_separator'],
            'decimals_separator' => $data['decimals_separator'],
            'installment_days' => $data['installment_days'],
            'e_commerce_checker' => $data['e_commerce_checker'],
            'is_loyalty_enable' => $data['is_loyalty_enable'],
            'minimum_point_to_redeem' => $data['minimum_point_to_redeem'],
            'loyalty_rate' => $data['loyalty_rate'],
            'product_code_start_from' => $data['product_code_start_from']
        ];

        $this->companyRepository->update($company, $updateData);
        
        // Refresh company to get updated data
        $company->refresh();
        
        // Update session
        $this->updateCompanySession([]);
        
        return [
            'status' => 'success',
            'message' => 'Business setting saved successfully'
        ];
    }

    /**
     * Update POS settings
     */
    public function updatePosSettings(array $data): array
    {
        $company = $this->companyRepository->getCurrentCompany();
        
        // Prepare register content
        $registerContent = [
            'register_expense' => $data['register_expense'] ?? null,
            'register_purchase' => $data['register_purchase'] ?? null,
            'register_purchase_return' => $data['register_purchase_return'] ?? null,
            'register_supplier_payment' => $data['register_supplier_payment'] ?? null,
            'register_sale' => $data['register_sale'] ?? null,
            'register_sale_return' => $data['register_sale_return'] ?? null,
            'register_installment_down_payment' => $data['register_installment_down_payment'] ?? null,
            'register_installment_collection' => $data['register_installment_collection'] ?? null,
            'register_customer_due_receive' => $data['register_customer_due_receive'] ?? null,
            'register_servicing' => $data['register_servicing'] ?? null,
        ];
        
        $updateData = [
            'allow_less_sale' => $data['allow_less_sale'],
            'default_customer' => $data['default_customer'],
            'default_payment' => $data['default_payment'],
            'pos_total_payable_type' => $data['pos_total_payable_type'],
            'default_cursor_position' => $data['default_cursor_position'],
            'product_display' => $data['product_display'],
            'onscreen_keyboard_status' => $data['onscreen_keyboard_status'],
            'grocery_experience' => $data['grocery_experience'],
            'direct_cart' => $data['direct_cart'],
            'smtp_default_selected_in_pos' => $data['smtp_default_selected_in_pos'],
            'sms_default_selected_in_pos' => $data['sms_default_selected_in_pos'],
            'whatsapp_default_selected_in_pos' => $data['whatsapp_default_selected_in_pos'],
            'register_content' => json_encode($registerContent)
        ];

        $this->companyRepository->update($company, $updateData);
        
        // Refresh company to get updated data
        $company->refresh();
        
        // Update session
        $this->updateCompanySession([]);
        
        return [
            'status' => 'success',
            'message' => 'POS setting saved successfully'
        ];
    }

    /**
     * Update tax settings
     */
    public function updateTaxSettings(array $data): array
    {
        $company = $this->companyRepository->getCurrentCompany();
        $updateData = [
            'collect_tax' => $data['collect_tax'],
        ];
        if ($data['collect_tax'] === 'Yes') {
            $updateData['tax_title'] = $data['tax_title'] ?? $company->tax_title;
            $updateData['tax_registration_no'] = $data['tax_registration_no'] ?? $company->tax_registration_no;
            $updateData['tax_is_gst'] = $data['tax_is_gst'] ?? $company->tax_is_gst ?? 'No';
            // Build tax_setting and tax_string from taxs table for backward compatibility
            $taxs = Tax::forCompany()->live()->with(['childTaxes', 'parentTax'])->get();
            $taxSettings = [];
            foreach ($taxs as $tax) {
                if ($tax->parent_tax_id) {
                    $taxSettings[] = [
                        'id' => (string) $tax->id,
                        'tax' => $tax->parentTax->tax_name . ' - ' . $tax->tax_name,
                        'tax_rate' => (float) $tax->tax_rate
                    ];
                } elseif ($tax->childTaxes->isEmpty()) {
                    $taxSettings[] = [
                        'id' => (string) $tax->id,
                        'tax' => $tax->tax_name,
                        'tax_rate' => (float) $tax->tax_rate
                    ];
                } else {
                    foreach ($tax->childTaxes as $child) {
                        $taxSettings[] = [
                            'id' => (string) $child->id,
                            'tax' => $tax->tax_name . ' - ' . $child->tax_name,
                            'tax_rate' => (float) $child->tax_rate
                        ];
                    }
                }
            }
        } else {
            $updateData['tax_title'] = null;
            $updateData['tax_registration_no'] = null;
            $updateData['tax_is_gst'] = 'No';
        }
        $this->companyRepository->update($company, $updateData);
        $company->refresh();
        $this->updateCompanySession([]);
        return [
            'status' => 'success',
            'message' => 'Tax setting saved successfully'
        ];
    }

    /**
     * Store a new tax in taxs table.
     * For GST: requires sub_tax_ids (IGST, CGST, SGST) - assigns existing taxes as children.
     * GST rate = IGST rate (when IGST is assigned); otherwise sum of child rates.
     * For others: Tax Name, Tax Rate, Show in Item Profile.
     */
    public function storeTax(array $data): array
    {
        $companyId = session('company.company_id');
        $showInItemProfile = $data['show_in_item_profile'];

        if (strtoupper($data['tax_name'] ?? '') === 'GST' && !empty($data['sub_tax_ids'])) {
            $gstRate = $this->resolveGstRateFromSubTaxes($data['sub_tax_ids'], $data['tax_rate'] ?? null);
            $parentTax = Tax::create([
                'tax_name' => 'GST',
                'tax_rate' => $gstRate,
                'parent_tax_id' => null,
                'show_in_item_profile' => $showInItemProfile,
                'company_id' => $companyId,
                'del_status' => 'Live',
            ]);
            foreach ((array) $data['sub_tax_ids'] as $subTaxId) {
                Tax::forCompany()->live()->where('id', $subTaxId)->update([
                    'parent_tax_id' => $parentTax->id,
                    'show_in_item_profile' => $showInItemProfile,
                ]);
            }
            $this->syncCompanyTaxFromTaxsTable($companyId);
            $freshTaxs = Tax::forCompany()->live()->with('childTaxes')->orderBy('id')->get();
            return [
                'status' => 'success',
                'message' => __('Tax added successfully'),
                'tax_groups' => $this->buildTaxGroups($freshTaxs),
                'available_sub_taxes' => $this->getAvailableSubTaxesForResponse(),
            ];
        }

        $tax = Tax::create([
            'tax_name' => $data['tax_name'],
            'tax_rate' => $data['tax_rate'],
            'parent_tax_id' => null,
            'show_in_item_profile' => $showInItemProfile,
            'company_id' => $companyId,
            'del_status' => 'Live',
        ]);
        $this->syncCompanyTaxFromTaxsTable($companyId);
        $freshTaxs = Tax::forCompany()->live()->with('childTaxes')->orderBy('id')->get();
        return [
            'status' => 'success',
            'message' => __('Tax added successfully'),
            'tax_groups' => $this->buildTaxGroups($freshTaxs),
            'available_sub_taxes' => $this->getAvailableSubTaxesForResponse(),
        ];
    }

    /**
     * Update an existing tax
     */
    public function updateTax(int $id, array $data): array
    {
        $tax = Tax::forCompany()->with('childTaxes')->findOrFail($id);
        if (strtoupper($data['tax_name'] ?? '') === 'GST' && !empty($data['sub_tax_ids'])) {
            $gstRate = $this->resolveGstRateFromSubTaxes($data['sub_tax_ids'], $data['tax_rate'] ?? null, $tax->tax_rate);
            $tax->update([
                'tax_rate' => $gstRate,
                'show_in_item_profile' => $data['show_in_item_profile'],
            ]);
            $tax->childTaxes()->update(['parent_tax_id' => null]);
            foreach ((array) $data['sub_tax_ids'] as $subTaxId) {
                Tax::forCompany()->live()->where('id', $subTaxId)->update([
                    'parent_tax_id' => $tax->id,
                    'show_in_item_profile' => $data['show_in_item_profile'],
                ]);
            }
        } else {
            $tax->update([
                'tax_name' => $data['tax_name'],
                'tax_rate' => $data['tax_rate'] ?? $tax->tax_rate,
                'show_in_item_profile' => $data['show_in_item_profile'],
            ]);
        }
        $this->syncCompanyTaxFromTaxsTable($tax->company_id);
        $freshTaxs = Tax::forCompany()->live()->with('childTaxes')->orderBy('id')->get();
        return [
            'status' => 'success',
            'message' => __('Tax updated successfully'),
            'tax_groups' => $this->buildTaxGroups($freshTaxs),
            'available_sub_taxes' => $this->getAvailableSubTaxesForResponse(),
        ];
    }

    /**
     * Delete (soft delete) a tax. If parent, also unassign/delete children.
     */
    public function deleteTax(int $id): array
    {
        $tax = Tax::forCompany()->with('childTaxes')->findOrFail($id);
        $companyId = $tax->company_id;
        if ($tax->parent_tax_id === null && $tax->childTaxes->isNotEmpty()) {
            $tax->childTaxes()->update(['parent_tax_id' => null]);
        }
        $tax->update(['del_status' => 'Deleted']);
        $this->syncCompanyTaxFromTaxsTable($companyId);
        $freshTaxs = Tax::forCompany()->live()->with('childTaxes')->orderBy('id')->get();
        return [
            'status' => 'success',
            'message' => __('Tax deleted successfully'),
            'tax_groups' => $this->buildTaxGroups($freshTaxs),
            'available_sub_taxes' => $this->getAvailableSubTaxesForResponse(),
        ];
    }

    /**
     * Get available sub taxes (IGST, CGST, SGST) for Assign Sub Taxes dropdown
     */
    protected function getAvailableSubTaxesForResponse(): array
    {
        return Tax::forCompany()->live()->gstSubTaxTypes()->orderBy('tax_name')->get()
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->tax_name, 'rate' => (float) $t->tax_rate])
            ->values()
            ->toArray();
    }

    /**
     * Resolve GST rate from sub taxes. GST = IGST (use IGST rate when IGST is assigned).
     * Otherwise use sum of CGST+SGST or fallback to provided/default rate.
     */
    protected function resolveGstRateFromSubTaxes(array $subTaxIds, $providedRate = null, $fallbackRate = 0): float
    {
        if ($providedRate !== null && $providedRate !== '') {
            return (float) $providedRate;
        }
        $subTaxes = Tax::forCompany()->live()->whereIn('id', $subTaxIds)->get();
        $igst = $subTaxes->firstWhere('tax_name', 'IGST');
        if ($igst) {
            return (float) $igst->tax_rate;
        }
        $sumRate = $subTaxes->sum(fn ($t) => (float) $t->tax_rate);
        return $sumRate > 0 ? $sumRate : (float) $fallbackRate;
    }

    /**
     * Build grouped tax structure for display: parent taxes with child taxes as badges
     */
    public function buildTaxGroups($taxs)
    {
        if (!$taxs instanceof \Illuminate\Support\Collection) {
            $taxs = collect($taxs);
        }
        $parents = $taxs->whereNull('parent_tax_id');
        $children = $taxs->whereNotNull('parent_tax_id');
        return $parents->map(function ($parent) use ($children) {
            $childList = $children->where('parent_tax_id', $parent->id);
            $subTaxes = $childList->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->tax_name,
                'rate' => (float) $t->tax_rate,
            ])->values();
            $taxIds = $childList->isNotEmpty()
                ? $childList->pluck('id')->prepend($parent->id)->toArray()
                : [$parent->id];
            $taxRate = $childList->isEmpty()
                ? (float) $parent->tax_rate
                : ($childList->pluck('tax_rate')->unique()->count() === 1
                    ? $childList->sum(fn($t) => (float) $t->tax_rate)
                    : null);
            return [
                'tax_ids' => $taxIds,
                'tax_id' => $parent->id,
                'tax_name' => $parent->tax_name,
                'tax_rate' => $taxRate,
                'sub_taxes' => $subTaxes,
                'show_in_item_profile' => $parent->show_in_item_profile,
            ];
        })->values();
    }

    /**
     * Sync company tax_setting and tax_string from taxs table
     */
    protected function syncCompanyTaxFromTaxsTable(?int $companyId = null): void
    {
        $companyId = $companyId ?? session('company.company_id');
        $company = $this->companyRepository->getCurrentCompany();
        if ($company->id != $companyId) {
            $company = Company::find($companyId);
        }
        if (!$company || $company->collect_tax !== 'Yes') {
            return;
        }
        $taxs = Tax::where('company_id', $companyId)->live()->with(['childTaxes', 'parentTax'])->get();
        $taxSettings = [];
        foreach ($taxs as $tax) {
            if ($tax->parent_tax_id) {
                $taxSettings[] = [
                    'id' => (string) $tax->id,
                    'tax' => $tax->parentTax->tax_name . ' - ' . $tax->tax_name,
                    'tax_rate' => (float) $tax->tax_rate
                ];
            } elseif ($tax->childTaxes->isEmpty()) {
                $taxSettings[] = [
                    'id' => (string) $tax->id,
                    'tax' => $tax->tax_name,
                    'tax_rate' => (float) $tax->tax_rate
                ];
            } else {
                foreach ($tax->childTaxes as $child) {
                    $taxSettings[] = [
                        'id' => (string) $child->id,
                        'tax' => $tax->tax_name . ' - ' . $child->tax_name,
                        'tax_rate' => (float) $child->tax_rate
                    ];
                }
            }
        }
        $company->update([
            'tax_setting' => json_encode($taxSettings),
            'tax_string' => implode(':', array_map(fn($t) => $t['tax'], $taxSettings)) . ':'
        ]);
        $this->updateCompanySession([]);
    }

    /**
     * Update email settings
     */
    public function updateEmailSettings(array $data): array
    {
        $company = $this->companyRepository->getCurrentCompany();
        
        // Prepare SMTP details
        $smtpDetails = [
            'host_name' => $data['host_name'],
            'port_address' => $data['port_address'],
            'encryption' => $data['encryption'],
            'user_name' => $data['user_name'],
            'password' => $data['password'],
            'from_name' => $data['from_name'],
            'from_email' => $data['from_email'],
            'api_key' => $data['api_key'] ?? null,
        ];
        
        $updateData = [
            'smtp_type' => $data['smtp_type'],
            'smtp_enable_status' => $data['smtp_enable_status'],
            'smtp_details' => json_encode($smtpDetails)
        ];

        $this->companyRepository->update($company, $updateData);
        
        // Refresh company to get updated data
        $company->refresh();
        
        // Update session
        $this->updateCompanySession([]);
        
        return [
            'status' => 'success',
            'message' => 'Email setting saved successfully'
        ];
    }

    /**
     * Update SMS settings
     */
    public function updateSmsSettings(array $data): array
    {
        $company = $this->companyRepository->getCurrentCompany();
        
        // Prepare SMS details
        $smsDetails = [
            'twilio' => [
                'sid' => $data['twilio_sid'] ?? '',
                'token' => $data['twilio_token'] ?? '',
                'number' => $data['twilio_number'] ?? ''
            ],
            'mobishastra' => [
                'profile_id' => $data['mobishastra_profile_id'] ?? '',
                'password' => $data['mobishastra_password'] ?? '',
                'sender_id' => $data['mobishastra_sender_id'] ?? '',
                'country_code' => $data['mobishastra_country_code'] ?? ''
            ],
            'mim_sms' => [
                'api_key' => $data['mim_sms_api_key'] ?? '',
                'sender_id' => $data['mim_sms_sender_id'] ?? '',
                'username' => $data['mim_sms_username'] ?? ''
            ],
            'text_local' => [
                'profile_id' => $data['text_local_profile_id'] ?? '',
                'api_key' => $data['text_local_api_key'] ?? '',
                'sender_id' => $data['text_local_sender_id'] ?? ''
            ]
        ];
        
        $updateData = [
            'sms_service_provider' => $data['sms_service_provider'],
            'sms_enable_status' => $data['sms_enable_status'],
            'sms_details' => json_encode($smsDetails)
        ];

        $this->companyRepository->update($company, $updateData);
        
        // Refresh company to get updated data
        $company->refresh();
        
        // Update session
        $this->updateCompanySession([]);
        
        return [
            'status' => 'success',
            'message' => 'SMS setting saved successfully'
        ];
    }

    /**
     * Update WhatsApp settings
     */
    public function updateWhatsAppSettings(array $data): array
    {
        $company = $this->companyRepository->getCurrentCompany();
        
        $updateData = [
            'whatsapp_provider' => $data['whatsapp_provider'],
            'whatsapp_invoice_enable_status' => $data['whatsapp_invoice_enable_status'],
            'whatsapp_app_key' => $data['whatsapp_app_key'],
            'whatsapp_authkey' => $data['whatsapp_authkey']
        ];

        $this->companyRepository->update($company, $updateData);
        
        // Refresh company to get updated data
        $company->refresh();
        
        // Update session
        $this->updateCompanySession([]);
        
        return [
            'status' => 'success',
            'message' => 'WhatsApp setting saved successfully'
        ];
    }

    /**
     * Update payment gateway settings
     */
    public function updatePaymentSettings(array $data): array
    {
        $company = $this->companyRepository->getCurrentCompany();
        
        // Prepare payment details
        $paymentDetails = [
            'stripe' => [
                'action_type' => $data['action_type_stripe'] ?? '',
                'api_key' => $data['stripe_api_key'] ?? '',
                'publishable_key' => $data['stripe_publishable_key'] ?? ''
            ],
            'paypal' => [
                'action_type' => $data['action_type_paypal'] ?? '',
                'user_name' => $data['paypal_user_name'] ?? '',
                'password' => $data['paypal_password'] ?? '',
                'signature' => $data['paypal_signature'] ?? '',
            ],
        ];
        
        $updateData = [
            'payment_api_setting' => json_encode($paymentDetails)
        ];

        $this->companyRepository->update($company, $updateData);
        
        // Refresh company to get updated data
        $company->refresh();
        
        // Update session
        $this->updateCompanySession([]);
        
        return [
            'status' => 'success',
            'message' => 'Payment setting saved successfully'
        ];
    }

    /**
     * Update whitelabel settings
     */
    public function updateWhitelabelSettings(array $data, $request): array
    {
        $company = $this->companyRepository->getCurrentCompany();
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
                $data['site_logo'] = $logoFileName;
            } else {
                $data['site_logo'] = $request->site_logo_hidden;
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
            $data['site_logo'] = $siteLogoName;
        } else {
            $data['site_logo'] = $request->site_logo_hidden;
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
                $data['site_favicon'] = $faviconFileName;
            } else {
                $data['site_favicon'] = $request->site_favicon_hidden;
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
            $data['site_favicon'] = $siteFaviconName;
        } else {
            $data['site_favicon'] = $request->site_favicon_hidden;
        }

        $whiteLabelDetails = [
            'site_name' => $data['site_name'],
            'site_footer' => $data['site_footer'],
            'site_title' => $data['site_title'],
            'site_link' => $data['site_link'],
            'site_logo' => $data['site_logo'],
            'site_favicon' => $data['site_favicon'],
        ];
        
        $updateData = [
            'white_label' => json_encode($whiteLabelDetails)
        ];

        $this->companyRepository->update($company, $updateData);
        
        // Refresh company to get updated data
        $company->refresh();
        
        // Update white_label session
        $this->updateWhiteLabelSession($company);
        
        return [
            'status' => 'success',
            'message' => 'White label settings saved successfully'
        ];
    }

    /**
     * Store base64 image from cropper
     */
    public function storeBase64Image($base64Image, $uploadPath, $prefix = ''): ?string
    {
        // Check if it's a base64 string (from cropper)
        if (is_string($base64Image) && strpos($base64Image, 'data:image') === 0) {
            // Detect image format
            $extension = 'jpg';
            if (strpos($base64Image, 'data:image/png') === 0) {
                $extension = 'png';
            } elseif (strpos($base64Image, 'data:image/gif') === 0) {
                $extension = 'gif';
            }
            
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));
            $fileName = $prefix . time() . '_' . uniqid() . '.' . $extension;
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadPath)) {
                createDirectory($uploadPath);
            }
            
            // Save the image (preserves transparency for PNG/GIF)
            file_put_contents($uploadPath . '/' . $fileName, $imageData);
            
            return $fileName;
        }

        return null;
    }

    /**
     * Update invoice settings
     */
    public function updateInvoiceSettings(array $data, $request): array
    {
        $company = $this->companyRepository->getCurrentCompany();
        $uploadPath = public_path('uploads/site_settings');
        createDirectory($uploadPath);

        // Handle invoice logo upload (from cropper base64)
        $invoiceLogo = $company->invoice_logo;
        if (!empty($request->invoice_logo)) {
            // Base64 image from cropper
            $logoFileName = $this->storeBase64Image($request->invoice_logo, $uploadPath, 'invoice_logo_');
            if ($logoFileName) {
                // Delete old logo if exists
                if ($invoiceLogo && file_exists(public_path('uploads/site_settings/' . $invoiceLogo))) {
                    unlink(public_path('uploads/site_settings/' . $invoiceLogo));
                }
                $invoiceLogo = $logoFileName;
            } else {
                $invoiceLogo = $request->invoice_logo_p ?? $invoiceLogo;
            }
        } elseif ($request->hasFile('invoice_logo_file')) {
            // Regular file upload (fallback)
            $file = $request->file('invoice_logo_file');
            if ($file->isValid()) {
                // Delete old logo if exists
                if ($invoiceLogo && file_exists(public_path('uploads/site_settings/' . $invoiceLogo))) {
                    unlink(public_path('uploads/site_settings/' . $invoiceLogo));
                }
                
                $fileName = 'invoice_logo_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move($uploadPath, $fileName);
                $invoiceLogo = $fileName;
            }
        } elseif ($request->filled('invoice_logo_p')) {
            $invoiceLogo = $request->invoice_logo_p;
        }

        // Prepare invoice configuration data
        // If numbering type is Random, generate random number based on digits
        $invStartFrom = $data['inv_start_from'] ?? '1';
        if (($data['inv_numbering_type'] ?? 'Sequential') === 'Random') {
            $numberOfDigits = (int)($data['inv_number_of_digit'] ?? 4);
            $min = pow(10, $numberOfDigits - 1);
            $max = pow(10, $numberOfDigits) - 1;
            $invStartFrom = (string)rand($min, $max);
        }
        
        $invoiceConfiguration = [
            'invoice_format_or_size' => $data['invoice_format_or_size'] ?? '56mm',
            'schema_type' => $data['schema_type'] ?? 'XXXX',
            'inv_numbering_type' => $data['inv_numbering_type'] ?? 'Sequential',
            'inv_number_of_digit' => $data['inv_number_of_digit'] ?? 4,
            'inv_prefix' => $data['inv_prefix'] ?? '',
            'inv_start_from' => $invStartFrom,
            'show_letter_head' => $data['show_letter_head'] ?? 'No',
            'letter_head_gap' => $data['letter_head_gap'] ?? '200px',
            'letter_footer_gap' => $data['letter_footer_gap'] ?? '100px',
            'invoice_heading' => $data['invoice_heading'] ?? 'Invoice',
            'invoice_heading_arabic' => $data['invoice_heading_arabic'] ?? '',
            'invoice_heading_due' => $data['invoice_heading_due'] ?? 'Due',
            'invoice_heading_paid' => $data['invoice_heading_paid'] ?? 'Paid',
            'invoice_no_label' => $data['invoice_no_label'] ?? 'Invoice No',
            'invoice_no_label_arabic' => $data['invoice_no_label_arabic'] ?? '',
            'invoice_date_label' => $data['invoice_date_label'] ?? 'Date',
            'invoice_date_label_arabic' => $data['invoice_date_label_arabic'] ?? '',
            'invoice_show_due_date' => $data['invoice_show_due_date'] ?? 'Yes',
            'invoice_due_date_label' => $data['invoice_due_date_label'] ?? 'Due Date',
            'invoice_due_date_label_arabic' => $data['invoice_due_date_label_arabic'] ?? '',
            'sales_person_label' => $data['sales_person_label'] ?? 'Sales Person',
            'commission_agent_label' => $data['commission_agent_label'] ?? 'Commission Agent',
            'show_business_name' => $data['show_business_name'] ?? 'Yes',
            'business_name_arabic' => $data['business_name_arabic'] ?? '',
            'show_business_tax_number' => $data['show_business_tax_number'] ?? ($company->collect_tax == 'Yes' ? 'Yes' : 'No'),
            'business_tax_number_label' => $data['business_tax_number_label'] ?? 'Business Tax Number',
            'customer_label' => $data['customer_label'] ?? 'Customer',
            'customer_tax_number_label' => $data['customer_tax_number_label'] ?? 'Customer Tax Number',
            'show_customer_phone_number' => $data['show_customer_phone_number'] ?? 'Yes',
            'show_customer_email' => $data['show_customer_email'] ?? 'No',
            'show_customer_address' => $data['show_customer_address'] ?? 'Yes',
            'serial_no_label' => $data['serial_no_label'] ?? 'SN',
            'serial_no_label_arabic' => $data['serial_no_label_arabic'] ?? '',
            'item_label' => $data['item_label'] ?? 'Item',
            'item_label_arabic' => $data['item_label_arabic'] ?? '',
            'price_label' => $data['price_label'] ?? 'Price',
            'price_label_arabic' => $data['price_label_arabic'] ?? '',
            'quantity_label' => $data['quantity_label'] ?? 'Qty',
            'quantity_label_arabic' => $data['quantity_label_arabic'] ?? '',
            'item_discount_label' => $data['item_discount_label'] ?? 'Discount',
            'item_discount_label_arabic' => $data['item_discount_label_arabic'] ?? '',
            'subtotal_label' => $data['subtotal_label'] ?? 'Subtotal',
            'subtotal_label_arabic' => $data['subtotal_label_arabic'] ?? '',
            'total_label' => $data['total_label'] ?? 'Total',
            'total_label_arabic' => $data['total_label_arabic'] ?? '',
            'total_item_label' => $data['total_item_label'] ?? 'Total Item',
            'total_item_label_arabic' => $data['total_item_label_arabic'] ?? '',
            'tax_label' => $data['tax_label'] ?? 'Tax',
            'tax_label_arabic' => $data['tax_label_arabic'] ?? '',
            'charge_label' => $data['charge_label'] ?? 'Charge',
            'charge_label_arabic' => $data['charge_label_arabic'] ?? '',
            'discount_label' => $data['discount_label'] ?? 'Discount',
            'discount_label_arabic' => $data['discount_label_arabic'] ?? '',
            'delivery_partner_label' => $data['delivery_partner_label'] ?? 'Delivery Partner',
            'delivery_partner_label_arabic' => $data['delivery_partner_label_arabic'] ?? '',
            'rounding_label' => $data['rounding_label'] ?? 'Rounding',
            'rounding_label_arabic' => $data['rounding_label_arabic'] ?? '',
            'total_payable_label' => $data['total_payable_label'] ?? 'Total Payable',
            'total_payable_label_arabic' => $data['total_payable_label_arabic'] ?? '',
            'previous_balance_label' => $data['previous_balance_label'] ?? 'Previous Balance',
            'previous_balance_label_arabic' => $data['previous_balance_label_arabic'] ?? '',
            'paid_amount_label' => $data['paid_amount_label'] ?? 'Paid Amount',
            'paid_amount_label_arabic' => $data['paid_amount_label_arabic'] ?? '',
            'due_amount_label' => $data['due_amount_label'] ?? 'Due Amount',
            'due_amount_label_arabic' => $data['due_amount_label_arabic'] ?? '',
            'due_receive_label' => $data['due_receive_label'] ?? 'Due Receive',
            'due_receive_label_arabic' => $data['due_receive_label_arabic'] ?? '',
            'advance_receive_label' => $data['advance_receive_label'] ?? 'Advance Receive',
            'advance_receive_label_arabic' => $data['advance_receive_label_arabic'] ?? '',
            'given_amount_label' => $data['given_amount_label'] ?? 'Given Amount',
            'given_amount_label_arabic' => $data['given_amount_label_arabic'] ?? '',
            'change_amount_label' => $data['change_amount_label'] ?? 'Change Amount',
            'change_amount_label_arabic' => $data['change_amount_label_arabic'] ?? '',
            'servicing_charge_label' => $data['servicing_charge_label'] ?? 'Servicing Charge',
            'servicing_charge_label_arabic' => $data['servicing_charge_label_arabic'] ?? '',
            'show_payment_method' => $data['show_payment_method'] ?? 'Yes',
            'payment_method_label' => $data['payment_method_label'] ?? 'Payment Method',
            'payment_method_label_arabic' => $data['payment_method_label_arabic'] ?? '',
            'show_hsn_code' => $data['show_hsn_code'] ?? 'No',
            'show_brand' => $data['show_brand'] ?? 'No',
            'show_product_code' => $data['show_product_code'] ?? 'No',
            'show_product_imei_serial_number' => $data['show_product_imei_serial_number'] ?? 'No',
            'show_product_image' => $data['show_product_image'] ?? 'No',
            'show_warranty_period' => $data['show_warranty_period'] ?? 'Yes',
            'show_warranty_expiry_date' => $data['show_warranty_expiry_date'] ?? 'Yes',
            'show_guarantee_period' => $data['show_guarantee_period'] ?? 'Yes',
            'show_guarantee_expiry_date' => $data['show_guarantee_expiry_date'] ?? 'Yes',
            'show_total_in_words' => $data['show_total_in_words'] ?? 'Yes',
            'word_format' => $data['word_format'] ?? 'Indian',
            'qr_code_option' => $data['qr_code_option'] ?? 'ZATCA QR Code',
            'qr_code_business' => $data['qr_code_business'] ?? null,
            'qr_code_address' => $data['qr_code_address'] ?? null,
            'qr_code_taxnumber' => $data['qr_code_taxnumber'] ?? null,
            'qr_code_invoice_no' => $data['qr_code_invoice_no'] ?? null,
            'qr_code_invoice_date_time' => $data['qr_code_invoice_date_time'] ?? null,
            'qr_code_subtotal' => $data['qr_code_subtotal'] ?? null,
            'qr_code_charge' => $data['qr_code_charge'] ?? null,
            'qr_code_tax' => $data['qr_code_tax'] ?? null,
            'qr_code_total_payable' => $data['qr_code_total_payable'] ?? null,
            'qr_code_customer_name' => $data['qr_code_customer_name'] ?? null,
            'qr_code_invoice_url' => $data['qr_code_invoice_url'] ?? null,
            'qr_code_outlet' => $data['qr_code_outlet'] ?? null,
        ];

        // Extract integer values from letter_head_gap and letter_footer_gap (remove 'px' if present)
        $letterHeadGap = $data['letter_head_gap'] ?? ($company->letter_head_gap ?? 200);
        if (is_string($letterHeadGap) && preg_match('/(\d+)/', $letterHeadGap, $matches)) {
            $letterHeadGap = (int)$matches[1];
        } else {
            $letterHeadGap = (int)$letterHeadGap;
        }

        $letterFooterGap = $data['letter_footer_gap'] ?? ($company->letter_footer_gap ?? 100);
        if (is_string($letterFooterGap) && preg_match('/(\d+)/', $letterFooterGap, $matches)) {
            $letterFooterGap = (int)$matches[1];
        } else {
            $letterFooterGap = (int)$letterFooterGap;
        }

        $updateData = [
            'inv_logo_is_show' => $data['inv_logo_is_show'] ?? 'Yes',
            'invoice_logo' => $invoiceLogo,
            'letter_head_gap' => $letterHeadGap,
            'letter_footer_gap' => $letterFooterGap,
            'invoice_footer' => $data['invoice_footer'] ?? $company->invoice_footer,
            'term_conditions' => $data['term_conditions'] ?? $company->term_conditions,
            'invoice_configuration' => json_encode($invoiceConfiguration),
        ];

        $this->companyRepository->update($company, $updateData);
        
        // Refresh company to get updated data
        $company->refresh();
        
        // Update session
        $this->updateCompanySession([]);
        
        return [
            'status' => 'success',
            'message' => 'Invoice setting saved successfully'
        ];
    }

    /**
     * Update company session data
     */
    protected function updateCompanySession(array $updateData): void
    {
        $company = $this->companyRepository->getCurrentCompany();
        $sessionCompany = session('company', []);
        
        // Update session with all company data similar to AuthenticatedSessionController
        $sessionCompany['company_id'] = $company->id;
        $sessionCompany['business_name'] = $company->business_name;
        $sessionCompany['short_name'] = $company->short_name ?? null;
        $sessionCompany['company_email'] = $company->email;
        $sessionCompany['date_format'] = $company->date_format;
        $sessionCompany['zone_name'] = $company->zone_name;
        $sessionCompany['currency'] = $company->currency;
        $sessionCompany['currency_position'] = $company->currency_position;
        $sessionCompany['precision'] = $company->precision;
        $sessionCompany['default_customer'] = $company->default_customer;
        $sessionCompany['default_cursor_position'] = $company->default_cursor_position;
        $sessionCompany['product_display'] = $company->product_display;
        $sessionCompany['onscreen_keyboard_status'] = $company->onscreen_keyboard_status;
        $sessionCompany['default_payment'] = $company->default_payment;
        $sessionCompany['payment_settings'] = $company->payment_settings ?? null;
        $sessionCompany['company_address'] = $company->address;
        $sessionCompany['inv_logo_is_show'] = $company->inv_logo_is_show ?? null;
        $sessionCompany['invoice_logo'] = $company->invoice_logo ?? null;
        $sessionCompany['invoice_configuration'] = $company->invoice_configuration ?? null;
        $sessionCompany['collect_tax'] = $company->collect_tax;
        $sessionCompany['tax_title'] = $company->tax_title;
        $sessionCompany['tax_registration_no'] = $company->tax_registration_no;
        $sessionCompany['tax_is_gst'] = $company->tax_is_gst;
        $sessionCompany['tax_setting'] = $company->tax_setting;
        $sessionCompany['tax_string'] = $company->tax_string;
        $sessionCompany['sms_enable_status'] = $company->sms_enable_status;
        $sessionCompany['smtp_enable_status'] = $company->smtp_enable_status;
        $sessionCompany['e_commerce_checker'] = $company->e_commerce_checker;
        $sessionCompany['white_label_status'] = $company->white_label_status ?? null;
        $sessionCompany['thousands_separator'] = $company->thousands_separator;
        $sessionCompany['decimals_separator'] = $company->decimals_separator;
        $sessionCompany['purchase_price_show_hide'] = $company->purchase_price_show_hide ?? null;
        $sessionCompany['allow_less_sale'] = $company->allow_less_sale;
        $sessionCompany['is_rounding_enable'] = $company->is_rounding_enable ?? null;
        $sessionCompany['direct_cart'] = $company->direct_cart;
        $sessionCompany['register_content'] = $company->register_content;
        $sessionCompany['grocery_experience'] = $company->grocery_experience;
        $sessionCompany['generic_name_search_option'] = $company->generic_name_search_option ?? null;
        $sessionCompany['product_code_start_from'] = $company->product_code_start_from;
        $sessionCompany['smtp_default_selected_in_pos'] = $company->smtp_default_selected_in_pos;
        $sessionCompany['sms_default_selected_in_pos'] = $company->sms_default_selected_in_pos;
        $sessionCompany['whatsapp_default_selected_in_pos'] = $company->whatsapp_default_selected_in_pos;

        
        session(['company' => $sessionCompany]);
    }

    /**
     * Update white label session data
     */
    protected function updateWhiteLabelSession(Company $company): void
    {
        // White Label
        if (isset($company->white_label) && $company->white_label) {
            $whiteLabelData = json_decode($company->white_label, true);
            if ($whiteLabelData) {
                session()->put('white_label', [
                    'site_name' => $whiteLabelData['site_name'] ?? null,
                    'site_footer' => $whiteLabelData['site_footer'] ?? null,
                    'site_title' => $whiteLabelData['site_title'] ?? null,
                    'site_link' => $whiteLabelData['site_link'] ?? null,
                    'site_logo' => $whiteLabelData['site_logo'] ?? null,
                    'site_favicon' => $whiteLabelData['site_favicon'] ?? null
                ]);
            }
        } else {
            session()->put('white_label', [
                'site_name' => null,
                'site_footer' => null,
                'site_title' => null,
                'site_link' => null,
                'site_logo' => null,
                'site_favicon' => null
            ]);
        }
    }

    /**
     * Update ZATCA settings
     */
    public function updateZatcaSettings(array $data): array
    {
        $company = $this->companyRepository->getCurrentCompany();
        
        // Prepare ZATCA configuration
        $zatcaPhase = $data['zatca_phase'] ?? '0'; // 0 = None, 1 = Phase 1, 2 = Phase 2
        
        $zatcaConfiguration = [
            'zatca_phase' => $zatcaPhase,
            'zatca_status' => ($zatcaPhase == '2') ? ($data['zatca_status'] ?? 'Disable') : 'Disable',
            'vat_registration_number' => $data['vat_registration_number'] ?? '',
            'legal_business_name_arabic' => $data['legal_business_name_arabic'] ?? '',
            'legal_business_name_english' => $data['legal_business_name_english'] ?? '',
            'zatca_address' => $data['zatca_address'] ?? '',
            'compliance_csid' => $data['compliance_csid'] ?? '',
            'production_csid' => $data['production_csid'] ?? '',
            'zatca_secret_key' => $data['zatca_secret_key'] ?? '',
        ];
        
        // For backward compatibility, also set zatca_1 and zatca_2 based on zatca_phase
        $zatcaConfiguration['zatca_1'] = ($zatcaPhase == '1') ? '1' : '0';
        $zatcaConfiguration['zatca_2'] = ($zatcaPhase == '2') ? '1' : '0';

        // Also update invoice configuration if provided (for invoice format and numbering)
        $invoiceConfig = [];
        if (isset($company->invoice_configuration) && $company->invoice_configuration != '') {
            $invoiceConfig = json_decode($company->invoice_configuration, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $invoiceConfig = [];
            }
        }

        // Update invoice configuration fields if provided
        if (isset($data['invoice_format_or_size'])) {
            $invoiceConfig['invoice_format_or_size'] = $data['invoice_format_or_size'];
        }
        if (isset($data['schema_type'])) {
            $invoiceConfig['schema_type'] = $data['schema_type'];
        }
        if (isset($data['inv_numbering_type'])) {
            $invoiceConfig['inv_numbering_type'] = $data['inv_numbering_type'];
        }
        if (isset($data['inv_number_of_digit'])) {
            $invoiceConfig['inv_number_of_digit'] = $data['inv_number_of_digit'];
        }
        if (isset($data['inv_prefix'])) {
            $invoiceConfig['inv_prefix'] = $data['inv_prefix'];
        }
        if (isset($data['inv_start_from'])) {
            $invoiceConfig['inv_start_from'] = $data['inv_start_from'];
        }

        $updateData = [
            'zatca_configuration' => json_encode($zatcaConfiguration),
        ];

        // Only update invoice_configuration if it was modified
        if (!empty($invoiceConfig)) {
            $updateData['invoice_configuration'] = json_encode($invoiceConfig);
        }

        $this->companyRepository->update($company, $updateData);
        
        // Refresh company to get updated data
        $company->refresh();
        
        // Update session
        $this->updateCompanySession([]);
        
        return [
            'status' => 'success',
            'message' => 'ZATCA setting saved successfully'
        ];
    }
}

