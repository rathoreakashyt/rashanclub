<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Modules\Configuration\Models\Outlet;
use Modules\Stock\Models\Item;

/**
 * Package limitation when LP=BD in .env.
 * Reads bootstrap/blueimp/B.json for package (p), outlet limit (ol), product limit (pl).
 * Enforces: Silver (1 outlet, 500 products), Gold (1 outlet, 2000), Diamond (unlimited).
 */
class BippService
{
    private const P_L = [
        'Silver'  => [1, 500],
        'Gold'    => [1, 2000],
        'Diamond' => [null, null],
    ];
    private const B_JSON_PATH = 'bootstrap/blueimp/B.json';
    public static function isEnabled(): bool
    {
        $lp = env('LP', '');
        return strtoupper((string) $lp) === 'BD';
    }

    /**
     * 
     */
    public static function getLimits(): ?array
    {
        if (!self::isEnabled()) {
            return null;
        }

        $path = base_path(self::B_JSON_PATH);
        if (!is_readable($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $content = trim($raw);
        if ($content === '') {
            return null;
        }

        try {
            $decrypted = Crypt::decryptString($content);
            if ($decrypted !== '') {
                $content = $decrypted;
            }
        } catch (\Throwable $e) {
            // Not encrypted (e.g. legacy plain B.json)
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        $p = isset($data['p']) ? trim((string) $data['p']) : '';
        $ol = isset($data['ol']) ? trim((string) $data['ol']) : '';
        $pl = isset($data['pl']) ? trim((string) $data['pl']) : '';

        $outletLimit = null;
        $productLimit = null;

        if ($ol !== '' && $ol !== 'Unlimited' && is_numeric($ol)) {
            $outletLimit = (int) $ol;
        }
        if ($pl !== '' && $pl !== 'Unlimited' && is_numeric($pl)) {
            $productLimit = (int) $pl;
        }

        if ($outletLimit === null || $productLimit === null) {
            $key = ucfirst(strtolower(trim($p)));
            $packageLimits = self::P_L[$key] ?? null;
            if ($packageLimits !== null) {
                if ($outletLimit === null) {
                    $outletLimit = $packageLimits[0];
                }
                if ($productLimit === null) {
                    $productLimit = $packageLimits[1];
                }
            }
        }

        return [
            'outlet_limit'  => $outletLimit,
            'product_limit' => $productLimit,
        ];
    }

    /**
     * Current outlet count for the given company (or current session company if null).
     */
    public static function getCurrentOutletCount(?int $companyId = null): int
    {
        $companyId = $companyId ?? session('company.company_id');
        if ($companyId === null) {
            return 0;
        }
        return Outlet::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->count();
    }

    /**
     * Current product (item) count for the given company (or current session company if null).
     */
    public static function getCurrentProductCount(?int $companyId = null): int
    {
        $companyId = $companyId ?? session('company.company_id');
        if ($companyId === null) {
            return 0;
        }
        return Item::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->count();
    }

    /**
     * Whether adding $addCount more outlets would exceed the package limit.
     */
    public static function canAddOutlets(int $addCount = 1, ?int $companyId = null): bool
    {
        $limits = self::getLimits();
        if ($limits === null || $limits['outlet_limit'] === null) {
            return true;
        }
        $current = self::getCurrentOutletCount($companyId);
        return ($current + $addCount) <= $limits['outlet_limit'];
    }

    /**
     * Whether adding $addCount more products would exceed the package limit.
     */
    public static function canAddProducts(int $addCount = 1, ?int $companyId = null): bool
    {
        $limits = self::getLimits();
        if ($limits === null || $limits['product_limit'] === null) {
            return true;
        }
        $current = self::getCurrentProductCount($companyId);
        return ($current + $addCount) <= $limits['product_limit'];
    }

    /**
     * Message for outlet limit exceeded.
     */
    public static function outletLimitMessage(): string
    {
        $limits = self::getLimits();
        $max = $limits['outlet_limit'] ?? 0;
        return __('Your package allows a maximum of :max outlet(s). You cannot create more outlets.', ['max' => $max]);
    }

    /**
     * Message for product limit exceeded.
     */
    public static function productLimitMessage(): string
    {
        $limits = self::getLimits();
        $max = $limits['product_limit'] ?? 0;
        return __('Your package allows a maximum of :max product(s). You cannot add more products.', ['max' => $max]);
    }
}
