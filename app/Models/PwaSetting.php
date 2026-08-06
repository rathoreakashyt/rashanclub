<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Configuration\Models\Company;

class PwaSetting extends Model
{
    protected $fillable = [
        'company_id',
        'app_name',
        'short_name',
        'theme_color',
        'background_color',
        'logo',
        'start_url',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    /**
     * Get the company that owns the PWA setting.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope for current company (multi-tenant).
     */
    public function scopeForCompany($query, $companyId = null)
    {
        $companyId = $companyId ?? session('company.company_id', 1);
        return $query->where('company_id', $companyId);
    }
}
