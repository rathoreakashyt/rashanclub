<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HoldDetail extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'hold_details';

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'qty' => 'decimal:3',
        'menu_price_without_discount' => 'decimal:3',
        'menu_price_with_discount' => 'decimal:3',
        'menu_unit_price' => 'decimal:3',
        'menu_vat_percentage' => 'decimal:3',
        'item_tax_amount' => 'decimal:3',
        'menu_discount_value' => 'decimal:3',
        'discount_amount' => 'decimal:3',
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
     * Get the hold that owns the detail.
     */
    public function hold(): BelongsTo
    {
        return $this->belongsTo(Hold::class, 'holds_id');
    }

    /**
     * Get the item for the hold detail.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(\Modules\Stock\Models\Item::class, 'item_id');
    }

    /**
     * Get the promo parent item if this is a promo item.
     */
    public function promoParent(): BelongsTo
    {
        return $this->belongsTo(HoldDetail::class, 'promo_parent_id');
    }

    /**
     * Get the item seller (employee) for the hold detail.
     */
    public function itemSeller(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'item_seller_id');
    }

    /**
     * Get the user who created the hold detail.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the outlet for the hold detail.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Outlet::class);
    }

    /**
     * Get the company for the hold detail.
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
     * Scope a query to only include promo items.
     */
    public function scopePromoItems($query)
    {
        return $query->where('is_promo_item', 'Yes');
    }

    /**
     * Scope a query to exclude promo items.
     */
    public function scopeRegularItems($query)
    {
        return $query->where('is_promo_item', 'No');
    }

    /**
     * Calculate the line total for this detail.
     */
    public function getLineTotal(): float
    {
        return $this->qty * $this->menu_price_with_discount;
    }
}
