<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'sale_payments';

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'date' => 'date',
        'multi_currency_rate' => 'decimal:4',
        'amount' => 'decimal:3',
        'usage_point' => 'decimal:3',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = ['encrypted_id'];

    /**
     * Get the encrypted ID attribute.
     */
    public function getEncryptedIdAttribute(): string
    {
        return encrypt($this->id);
    }

    /**
     * Get the sale that owns the payment.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    /**
     * Get the payment method for this payment.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\PaymentMethod::class, 'payment_id');
    }

    /**
     * Get the user who processed the payment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the outlet for the payment.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Outlet::class);
    }

    /**
     * Get the company for the payment.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Company::class);
    }

    /**
     * Scope a query to only include live records.
     */
    public function scopeLive($query)
    {
        return $query->where('del_status', 'Live');
    }

    /**
     * Scope a query to only include records for a specific company.
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope a query to filter by payment method.
     */
    public function scopeByPaymentMethod($query, int $paymentMethodId)
    {
        return $query->where('payment_id', $paymentMethodId);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $dateFrom, $dateTo)
    {
        return $query->whereBetween('date', [$dateFrom, $dateTo]);
    }

    /**
     * Get the amount in base currency if multi-currency is used.
     */
    public function getBaseCurrencyAmount(): float
    {
        if ($this->multi_currency === 'Yes' && $this->multi_currency_rate > 0) {
            return $this->amount / $this->multi_currency_rate;
        }
        return $this->amount;
    }
}

