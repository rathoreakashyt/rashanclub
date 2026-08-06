<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturn extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'sale_returns';

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'date' => 'date',
        'total_return_amount' => 'decimal:3',
        'paid' => 'decimal:3',
        'due' => 'decimal:3',
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
     * Get the customer that owns the sale return.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the sale that this return is for.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the payment method for the sale return.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\PaymentMethod::class, 'payment_method_id');
    }

    /**
     * Get the user who created the sale return.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the outlet for the sale return.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Outlet::class);
    }

    /**
     * Get the company for the sale return.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Company::class);
    }

    /**
     * Get the sale return details for the sale return.
     */
    public function saleReturnDetails(): HasMany
    {
        return $this->hasMany(SaleReturnDetail::class, 'sale_return_id');
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
     * Scope a query to only include records for a specific outlet.
     */
    public function scopeForOutlet($query, int $outletId)
    {
        return $query->where('outlet_id', $outletId);
    }

    /**
     * Scope a query to only include sale returns with due amount.
     */
    public function scopeWithDue($query)
    {
        return $query->where('due', '>', 0);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $dateFrom, $dateTo)
    {
        return $query->whereBetween('date', [$dateFrom, $dateTo]);
    }

    /**
     * Check if sale return is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->due <= 0;
    }
}

