<?php

namespace Modules\Configuration\Models;

use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    protected $table = 'taxs';

    protected $guarded = ['id'];

    protected $fillable = [
        'tax_name',
        'tax_rate',
        'parent_tax_id',
        'show_in_item_profile',
        'company_id',
        'del_status',
    ];

    protected $casts = [
        'tax_rate' => 'decimal:2',
    ];

    /**
     * Get the parent tax (e.g. GST for IGST, CGST, SGST)
     */
    public function parentTax()
    {
        return $this->belongsTo(Tax::class, 'parent_tax_id');
    }

    /**
     * Get child/sub taxes (e.g. IGST, CGST, SGST for GST)
     */
    public function childTaxes()
    {
        return $this->hasMany(Tax::class, 'parent_tax_id');
    }

    /**
     * Scope for current company
     */
    public function scopeForCompany($query, $companyId = null)
    {
        $companyId = $companyId ?? session('company.company_id');
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope for live records only
     */
    public function scopeLive($query)
    {
        return $query->where('del_status', 'Live');
    }

    /**
     * Scope for parent taxes only (no parent_tax_id)
     */
    public function scopeParents($query)
    {
        return $query->whereNull('parent_tax_id');
    }

    /**
     * Scope for GST sub tax types (IGST, CGST, SGST) - available for assignment
     */
    public function scopeGstSubTaxTypes($query)
    {
        return $query->whereIn('tax_name', ['IGST', 'CGST', 'SGST']);
    }
}
