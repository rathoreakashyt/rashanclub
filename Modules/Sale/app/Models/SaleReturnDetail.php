<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnDetail extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'sale_return_details';

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'sale_quantity_amount' => 'decimal:3',
        'return_quantity_amount' => 'decimal:3',
        'unit_price_in_sale' => 'decimal:3',
        'unit_price_in_return' => 'decimal:3',
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
     * Get the sale return that owns the detail.
     */
    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class, 'sale_return_id');
    }

    /**
     * Get the sale that this return detail is for.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the item for the sale return detail.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(\Modules\Stock\Models\Item::class, 'item_id');
    }

    /**
     * Get the user who created the sale return detail.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the outlet for the sale return detail.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Outlet::class);
    }

    /**
     * Get the company for the sale return detail.
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
     * Calculate the line total for this return detail.
     */
    public function getLineTotal(): float
    {
        return $this->return_quantity_amount * $this->unit_price_in_return;
    }

    /**
     * Calculate the difference between sale price and return price.
     */
    public function getPriceDifference(): float
    {
        return ($this->unit_price_in_sale - $this->unit_price_in_return) * $this->return_quantity_amount;
    }
}

