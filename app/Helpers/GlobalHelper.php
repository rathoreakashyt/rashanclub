<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

if (!function_exists('requiredField')) {
    function requiredField()
    {
        return '<span class="text-danger">*</span>';
    }
}

if (!function_exists('insertSuccess')) {
    function insertSuccess($message)
    {
        return '<div class="d-flex align-items-center alert alert-success alert-dismissible" role="alert">
                    <i class="ti tabler-check me-2"></i>
                    '. $message .'
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}

if (!function_exists('insertFailed')) {
    function insertFailed($message)
    {
        return '<div class="d-flex align-items-center  alert alert-danger alert-dismissible" role="alert">
                    <i class="ti tabler-circle-x me-2"></i>
                    '. $message .'
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}

if (!function_exists('addIconWithText')) {
    function addIconWithText($parameter = '')
    {
        return '<i class="ti tabler-plus me-1"></i>' . $parameter;
    }
}
if (!function_exists('submitIconWithText')) {
    function submitIconWithText($parameter = '')
    {
        return '<i class="ti tabler-checkbox me-1"></i>' . ($parameter ? __('Update') : __('Submit'));
    }
}
if (!function_exists('editIconWithText')) {
    function editIconWithText()
    {
        return '<i class="ti tabler-edit me-1"></i>' . __('Edit');
    }
}

if (!function_exists('closeIconWithText')) {
    function closeIconWithText()
    {
        return '<i class="ti tabler-x me-1"></i>' . __('Close');
    }
}

if (!function_exists('backIconWithText')) {
    function backIconWithText()
    {
        return '<i class="ti tabler-arrow-back-up me-1"></i>' . __('Back');
    }
}
if (!function_exists('printIconWithText')) {
    function printIconWithText()
    {
        return '<i class="ti tabler-printer me-1"></i>' . __('Print');
    }
}

if (!function_exists('createDirectory')) {
    function createDirectory($path, $permissions = 0777, $recursive = true)
    {
        if (!file_exists($path)) {
            try {
                if (!mkdir($path, $permissions, $recursive)) {
                    throw new \Exception("Failed to create directory: " . $path);
                }
                return true;
            } catch (\Exception $e) {
                Log::error("Directory creation failed: " . $e->getMessage());
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('getWhiteLabel')) {
    function getWhiteLabel($param = '')
    {
        $company_id = session()->has('company.company_id') ? session('company.company_id') : 1;
        $company = \Modules\Configuration\Models\Company::select('white_label')->where('id', $company_id)->first();
        if ($company && $company->white_label) {
            $whiteLabel = json_decode($company->white_label, true);
            if ($param) {
                return $whiteLabel[$param] ?? null;
            }
            return $whiteLabel;
        }
        return null;
    }
}

/**
 * When LP=BD, returns the base64 data URI for the LP favicon/logo (same as old bip.js behaviour).
 * Otherwise returns $defaultUrl. Data URI avoids extra request and works like the legacy CodeIgniter script.
 */
if (!function_exists('lpFaviconLogoUrl')) {
    function lpFaviconLogoUrl($defaultUrl)
    {
        if (defined('LP') && LP === 'BD' && defined('LP_BD_FAVICON_LOGO_DATA_URI')) {
            return LP_BD_FAVICON_LOGO_DATA_URI;
        }
        return $defaultUrl;
    }
}

/**
 * Favicon type for <link rel="icon">: image/png when LP=BD (base64 PNG), else image/x-icon.
 */
if (!function_exists('lpFaviconLogoType')) {
    function lpFaviconLogoType()
    {
        return (defined('LP') && LP === 'BD') ? 'image/png' : 'image/x-icon';
    }
}

if (!function_exists('getPwaSettings')) {
    function getPwaSettings()
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('pwa_settings')) {
                return (object) [
                    'theme_color' => '#0f172a',
                    'app_name' => config('app.name', 'Rashan Ki Dukan'),
                ];
            }
            $companyId = session('company.company_id') ?? 1;
            $settings = \App\Models\PwaSetting::where('company_id', $companyId)->first();
            if ($settings) {
                return $settings;
            }
        } catch (\Throwable $e) {
            // Ignore if DB not ready
        }
        return (object) [
            'theme_color' => '#0f172a',
            'app_name' => config('app.name', 'Rashan Ki Dukan'),
        ];
    }
}

if (!function_exists('numberFormatWithCurrency')) {
    function numberFormatWithCurrency($amount) {
        $amount = is_numeric($amount) ? $amount : 0;
        $precision = session('company.precision', 2);
        $decimals_separator = session('company.decimals_separator', '.');
        $thousands_separator = session('company.thousands_separator', ',');
        $currency_position = session('company.currency_position', 'Before Amount');
        $currency = session('company.currency', '$');
        $formattedAmount = number_format((float)$amount, (int)$precision, $decimals_separator, $thousands_separator);
        $isBangladesh = false;
        $currencySpan = $isBangladesh ? '<span class="currency_show">' . $currency . '</span>' : $currency;

        return $currency_position === "Before Amount" 
            ? $currencySpan . $formattedAmount
            : $formattedAmount . $currencySpan;
    }
}

if (!function_exists('numberFormat')) {
    function numberFormat($amount) {
        $amount = is_numeric($amount) ? $amount : 0;
        $precision = session('company.precision', 2);
        $decimals_separator = session('company.decimals_separator', '.');
        $thousands_separator = session('company.thousands_separator', ',');
        return number_format((float)$amount, (int)$precision, $decimals_separator, $thousands_separator);
    }
}

if (!function_exists('numberFormatPrecision')) {
    function numberFormatPrecision($amount) {
        $amount = is_numeric($amount) ? $amount : 0;
        $precision = session('company.precision', 2);
        return number_format((float)$amount, (int)$precision);
    }
}

if (!function_exists('formatAmount')) {
    function formatAmount($amount) {
        $company = Session::get('company');

        if (!$company) {
            return $amount;
        }

        $precision          = $company['precision'] ?? 2;
        $currency           = $company['currency'] ?? '';
        $currencyPosition   = $company['currency_position'] ?? 'Before Amount';
        $thousandSepKey     = $company['thousands_separator'] ?? ',';
        $thousandSeparator  = $thousandSepKey == 'space' ? ' ' : $thousandSepKey;
        $decimalSepKey      = $company['decimals_separator'] ?? '.';
        $decimalSeparator   = $decimalSepKey == 'space' ? ' ' : $decimalSepKey;

        // Format number
        $formatted = number_format(
            (float) $amount,
            $precision,
            $decimalSeparator,
            $thousandSeparator
        );

        // Apply currency position
        if ($currencyPosition === "Before Amount") {
            return $currency . $formatted;
        }

        return $formatted . $currency;
    }
}
if (!function_exists('formatDate')) {
    function formatDate($date)
    {
        $company = Session::get('company');
        if (!$company) {
            return $date;
        }
        $dateFormat = $company['date_format'] ?? 'Y/m/d';
        try {
            $carbonDate = \Carbon\Carbon::parse($date);
        } catch (\Exception $e) {
            return $date;
        }
        return $carbonDate->format($dateFormat);
    }
}
if (!function_exists('formatDateTime')) {
    function formatDateTime($date)
    {
        if (empty($date)) {
            return $date;
        }
        $company = Session::get('company');
        if (!$company) {
            return $date;
        }
        $dateTimeFormat = $company['date_time_format']
            ?? (($company['date_format'] ?? 'Y-m-d') . ' H:i:s');
        try {
            return \Carbon\Carbon::parse($date)->format($dateTimeFormat);
        } catch (\Exception $e) {
            return $date;
        }
    }
}

if (!function_exists('itemCodeGenerator')) {
    function itemCodeGenerator() {
        $item = \Modules\Stock\Models\Item::select('code')
                ->where('company_id', session('company.company_id'))
                ->where('type', '!=', '0')
                ->where('del_status', 'Live')
                ->orderBy('id', 'desc')
                ->first();
        if (!$item) {
            $start_from = session('company.product_code_start_from') ?? '000001';
            return $start_from;
        }
        $last_code = $item->code;
        $next_number = intval($last_code) + 1;
        $code_length = strlen($last_code);
        $next_code = str_pad($next_number, $code_length, '0', STR_PAD_LEFT);
        return $next_code;
    }
}

if (!function_exists('isEnableGST')) {
    function isEnableGST() {
        if (session('company.tax_is_gst') == 'Yes') {
            return true;
        }
        return false;
    }
}

if (!function_exists('isValidGstin')) {
    /**
     * Validate Indian GSTIN (15 chars, format + Luhn mod 36 checksum).
     *
     * @param string|null $gstin
     * @return bool
     */
    function isValidGstin($gstin) {
        return $gstin !== null && $gstin !== '' && \App\Helpers\GstinValidator::isValid($gstin);
    }
}

if (!function_exists('sessionAll')) {
    function sessionAll() {
        return session()->all();
    }
}

if (!function_exists('truncateText')) {
    function truncateText($text, $charLimit = 50, $wordLimit = 20)
    {
        $text = trim($text);
        $words = explode(' ', $text);
        if (count($words) > $wordLimit) {
            $text = implode(' ', array_slice($words, 0, $wordLimit)) . '...';
        }
        if (strlen($text) > $charLimit) {
            $text = substr($text, 0, $charLimit) . '...';
        }
        return $text;
    }
}

if (!function_exists('getParentItemName')) {
    function getParentItemName($id)
    {
        $parentItem = DB::table('items')->where('id', $id)->first();
        if ($parentItem) {
            return $parentItem->name;
        }
        return;
    }
}