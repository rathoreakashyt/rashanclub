<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Configuration\Models\Company;

class PurchaseReturn extends Model
{
    protected $table = 'purchase_returns';
    
    protected $fillable = [
        'reference_no',
        'pur_ref_no',
        'supplier_id',
        'date',
        'purchase_date',
        'return_status',
        'total_return_amount',
        'payment_method_id',
        'payment_method_type',
        'account_type',
        'note',
        'user_id',
        'outlet_id',
        'company_id',
        'del_status'
    ];

    protected $casts = [
        'date' => 'date',
        'purchase_date' => 'date',
        'total_return_amount' => 'decimal:3',
    ];

    protected $appends = [
        'encrypted_id',
    ];

    /**
     * Get the purchase return details for the purchase return.
     */
    public function purchaseReturnDetails(): HasMany
    {
        return $this->hasMany(PurchaseReturnDetail::class, 'pur_return_id');
    }

    /**
     * Get the supplier that owns the purchase return.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the user that created the purchase return.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Get the company that owns the purchase return.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the outlet that owns the purchase return.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Outlet::class);
    }

    /**
     * Get the payment method for the purchase return.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\PaymentMethod::class, 'payment_method_id');
    }

    /**
     * Scope a query to only include live purchase returns.
     */
    public function scopeLive($query)
    {
        return $query->where('del_status', 'Live');
    }

    /**
     * Scope a query to only include purchase returns for a specific company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Get the encrypted ID attribute
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }
}

