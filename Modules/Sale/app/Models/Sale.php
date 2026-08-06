<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'sales';

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'sale_date' => 'date',
        'date_time' => 'datetime',
        'order_time' => 'datetime',
        'due_date' => 'date',
        'due_date_time' => 'datetime',
        'order_date_time' => 'datetime',
        'close_time' => 'datetime',
        'sub_total' => 'decimal:3',
        'given_amount' => 'decimal:3',
        'paid_amount' => 'decimal:3',
        'change_amount' => 'decimal:3',
        'previous_due' => 'decimal:3',
        'due_amount' => 'decimal:3',
        'disc' => 'decimal:3',
        'disc_actual' => 'decimal:3',
        'vat' => 'decimal:3',
        'rounding' => 'decimal:3',
        'total_payable' => 'decimal:3',
        'total_item_discount_amount' => 'decimal:3',
        'sub_total_with_discount' => 'decimal:3',
        'sub_total_discount_amount' => 'decimal:3',
        'total_discount_amount' => 'decimal:3',
        'delivery_charge' => 'decimal:3',
        'sub_total_discount_value' => 'decimal:3',
        'grand_total' => 'decimal:3',
        // 'sale_vat_objects' => 'array', // Removed - storing as JSON string directly, not array
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
     * Get the customer that owns the sale.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the employee who made the sale.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'employee_id');
    }

    /**
     * Get the user who created the sale.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the outlet for the sale.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Outlet::class);
    }

    /**
     * Get the company for the sale.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Company::class);
    }

    /**
     * Get the delivery partner for the sale.
     */
    public function deliveryPartner(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\DeliveryPartner::class, 'delivery_partner_id');
    }

    /**
     * Get the sale details for the sale.
     */
    public function saleDetails(): HasMany
    {
        return $this->hasMany(SaleDetail::class, 'sales_id');
    }

    /**
     * Get the sale payments for the sale.
     */
    public function salePayments(): HasMany
    {
        return $this->hasMany(SalePayment::class, 'sale_id');
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
     * Scope a query to only include sales with due amount.
     */
    public function scopeWithDue($query)
    {
        return $query->where('due_amount', '>', 0);
    }

    /**
     * Scope a query to filter by sale date range.
     */
    public function scopeDateRange($query, $dateFrom, $dateTo)
    {
        return $query->whereBetween('sale_date', [$dateFrom, $dateTo]);
    }

    /**
     * Check if sale is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->due_amount <= 0;
    }

    /**
     * Get total paid amount from payments.
     */
    public function getTotalPaidFromPayments(): float
    {
        return $this->salePayments()
            ->where('del_status', 'Live')
            ->sum('amount');
    }
}

