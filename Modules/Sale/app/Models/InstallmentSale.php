<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentSale extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'installment_sales';

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'date' => 'date',
        'price' => 'decimal:3',
        'discount_amount' => 'decimal:3',
        'percentage_of_interest' => 'decimal:3',
        'interest_amount' => 'decimal:3',
        'shipping_other' => 'decimal:3',
        'total' => 'decimal:3',
        'down_payment' => 'decimal:3',
        'remaining' => 'decimal:3',
        'paid_amount' => 'decimal:3',
        'due_amount' => 'decimal:3',
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
     * Get the customer that owns the installment sale.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the item for the installment sale.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(\Modules\Stock\Models\Item::class);
    }

    /**
     * Get the user who created the installment sale.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the outlet for the installment sale.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Outlet::class);
    }

    /**
     * Get the company for the installment sale.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Company::class);
    }

    /**
     * Get the installment details for the sale.
     */
    public function installmentDetails(): HasMany
    {
        return $this->hasMany(InstallmentSaleDetail::class);
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
     * Scope a query to only include active installment sales.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Get total paid amount from installment details.
     */
    public function getTotalPaidFromInstallments(): float
    {
        return $this->installmentDetails()
            ->where('del_status', 'Live')
            ->sum('paid_amount');
    }

    /**
     * Check if all installments are paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->installmentDetails()
            ->where('del_status', 'Live')
            ->where('paid_status', '!=', 'Paid')
            ->count() === 0;
    }
}

