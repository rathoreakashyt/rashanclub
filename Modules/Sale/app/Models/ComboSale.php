<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComboSale extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'combo_sales';

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'combo_item_qty' => 'decimal:3',
        'combo_item_price' => 'decimal:3',
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
     * Get the sale that owns the combo sale.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    /**
     * Get the combo sale item (sale detail) that this combo sale belongs to.
     */
    public function comboSaleItem(): BelongsTo
    {
        return $this->belongsTo(SaleDetail::class, 'combo_sale_item_id');
    }

    /**
     * Get the combo item.
     */
    public function comboItem(): BelongsTo
    {
        return $this->belongsTo(\Modules\Stock\Models\Item::class, 'combo_item_id');
    }

    /**
     * Get the combo item seller (employee) for the combo sale.
     */
    public function comboItemSeller(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'combo_item_seller_id');
    }

    /**
     * Get the user who created the combo sale.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the outlet for the combo sale.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Outlet::class);
    }

    /**
     * Get the company for the combo sale.
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
     * Scope a query to only include records for a specific outlet.
     */
    public function scopeForOutlet($query, int $outletId)
    {
        return $query->where('outlet_id', $outletId);
    }

    /**
     * Scope a query to only include records for a specific sale.
     */
    public function scopeForSale($query, int $saleId)
    {
        return $query->where('sale_id', $saleId);
    }

    /**
     * Scope a query to only include items that should be shown in invoice.
     */
    public function scopeShowInInvoice($query)
    {
        return $query->where('show_in_invoice', 'Yes');
    }

    /**
     * Calculate the line total for this combo sale.
     */
    public function getLineTotal(): float
    {
        return $this->combo_item_qty * $this->combo_item_price;
    }
}
