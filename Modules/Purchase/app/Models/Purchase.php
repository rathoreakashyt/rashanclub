<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Configuration\Models\Company;

class Purchase extends Model
{
    protected $table = 'purchases';
    
    protected $fillable = [
        'reference_no',
        'invoice_no',
        'supplier_id',
        'date',
        'other',
        'grand_total',
        'paid',
        'due_amount',
        'note',
        'discount',
        'attachment',
        'user_id',
        'outlet_id',
        'company_id',
        'del_status'
    ];

    protected $casts = [
        'date' => 'date',
        'other' => 'decimal:3',
        'grand_total' => 'decimal:3',
        'paid' => 'decimal:3',
        'due_amount' => 'decimal:3',
        // 'discount' is stored as string to preserve % symbol (e.g., "19%" or "100")
    ];

    protected $appends = [
        'encrypted_id',
    ];

    /**
     * Get the purchase details for the purchase.
     */
    public function purchaseDetails(): HasMany
    {
        return $this->hasMany(PurchaseDetail::class);
    }

    /**
     * Get the supplier that owns the purchase.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the user that created the purchase.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Get the user that made the purchase.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Get the company that owns the purchase.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the outlet that owns the purchase.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Outlet::class);
    }

    /**
     * Get the purchase payments for the purchase.
     */
    public function purchasePayments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    /**
     * Scope a query to only include live purchases.
     */
    public function scopeLive($query)
    {
        return $query->where('del_status', 'Live');
    }

    /**
     * Scope a query to only include purchases for a specific company.
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

    /**
     * Get the status attribute (computed based on due_amount)
     */
    public function getStatusAttribute()
    {
        if ($this->due_amount > 0) {
            return 'Pending';
        }
        return 'Received';
    }
}

