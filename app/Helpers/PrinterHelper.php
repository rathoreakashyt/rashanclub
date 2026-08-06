<?php

use Illuminate\Support\Facades\DB;

/**
 * Receipt printer helper functions (compatible with CodeIgniter print_server flow).
 */

if (!function_exists('drawLine')) {
    function drawLine($size)
    {
        $line = '';
        for ($i = 1; $i <= $size; $i++) {
            $line .= '-';
        }
        return $line . "\n";
    }
}

if (!function_exists('printLine')) {
    function printLine($str, $size, $sep = ':', $space = null)
    {
        $size = $space ?: $size;
        $length = strlen($str);
        $parts = explode(':', $str, 2);
        $first = $parts[0] ?? '';
        $second = $parts[1] ?? '';
        $line = $first . ($sep === ':' ? $sep : '');
        for ($i = 1; $i < ($size - $length); $i++) {
            $line .= ' ';
        }
        $line .= ($sep !== ':' ? $sep : '') . $second;
        return $line;
    }
}

if (!function_exists('printText')) {
    function printText($text, $size)
    {
        return wordwrap((string) $text, (int) $size, "\n");
    }
}

if (!function_exists('getAmt')) {
    function getAmt($amount)
    {
        return formatAmount($amount);
    }
}

if (!function_exists('getAmtP')) {
    function getAmtP($amount)
    {
        return numberFormatPrecision($amount);
    }
}

/**
 * Get printer ID for the current user's open register (by counter).
 */
if (!function_exists('getPrinterIdByRegisterID')) {
    function getPrinterIdByRegisterID()
    {
        $userId = auth()->id();
        $outletId = session('outlet.outlet_id');
        $companyId = session('company.company_id');

        if (!$userId || !$outletId || !$companyId) {
            return 0;
        }

        $register = DB::table('registers')
            ->where('user_id', $userId)
            ->where('outlet_id', $outletId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('id', 'desc')
            ->limit(1)
            ->first();

        if (!$register || !$register->counter_id) {
            return 0;
        }

        $counter = DB::table('counters')
            ->where('id', $register->counter_id)
            ->first();

        return $counter && $counter->printer_id ? (int) $counter->printer_id : 0;
    }
}

/**
 * Get printer info by ID from printers table.
 */
if (!function_exists('getPrinterInfo')) {
    function getPrinterInfo($id)
    {
        if (!$id) {
            return null;
        }
        return DB::table('printers')
            ->where('id', $id)
            ->where('del_status', 'Live')
            ->orderBy('id', 'desc')
            ->first();
    }
}

/**
 * Format IPv4/host address with protocol (http/https) and trailing slash.
 */
if (!function_exists('getIPv4WithFormat')) {
    function getIPv4WithFormat($ipv_address)
    {
        if (empty($ipv_address)) {
            return '';
        }
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
        return $protocol . rtrim($ipv_address, '/') . '/';
    }
}
