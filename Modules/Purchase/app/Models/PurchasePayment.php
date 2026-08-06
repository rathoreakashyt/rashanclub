<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Configuration\Models\Company;

class PurchasePayment extends Model
{
    protected $table = 'purchase_payments';
    
    protected $fillable = [
        'date',
        'purchase_id',
        'payment_id',
        'amount',
        'outlet_id',
        'user_id',
        'company_id',
        'del_status'
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the purchase that owns the purchase payment.
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * Get the payment method that owns the purchase payment.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\PaymentMethod::class, 'payment_id');
    }

    /**
     * Get the user that created the purchase payment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the outlet that owns the purchase payment.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Backend\Outlet::class);
    }

    /**
     * Get the company that owns the purchase payment.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope a query to only include live purchase payments.
     */
    public function scopeLive($query)
    {
        return $query->where('del_status', 'Live');
    }

    /**
     * Scope a query to only include purchase payments for a specific company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
