<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FeatureActivation extends Model
{
    protected $fillable = ['feature_key', 'feature_name', 'group', 'is_active', 'company_id'];

    protected $casts = ['is_active' => 'boolean'];

    /**
     * Check if a feature is active (cached for performance)
     */
    public static function isActive(string $featureKey, int $companyId = 1): bool
    {
        $features = Cache::remember("feature_activations_{$companyId}", 300, function () use ($companyId) {
            return self::where('company_id', $companyId)->pluck('is_active', 'feature_key')->toArray();
        });

        return $features[$featureKey] ?? true;
    }

    /**
     * Get all features status (for API sync)
     */
    public static function getAllStatus(int $companyId = 1): array
    {
        return self::where('company_id', $companyId)
            ->get(['feature_key', 'feature_name', 'group', 'is_active', 'updated_at'])
            ->toArray();
    }

    /**
     * Clear cache when updated
     */
    public static function clearCache(int $companyId = 1): void
    {
        Cache::forget("feature_activations_{$companyId}");
    }
}
