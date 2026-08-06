<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnDetail extends Model
{
    protected $table = 'purchase_return_details';
    
    protected $fillable = [
        'pur_return_id',
        'item_id',
        'item_type',
        'expiry_imei_serial',
        'expiry_imei_serial_in',
        'return_note',
        'return_quantity_amount',
        'unit_price',
        'total',
        'return_status',
        'user_id',
        'outlet_id',
        'company_id',
        'del_status'
    ];

    protected $casts = [
        'unit_price' => 'decimal:3',
        'return_quantity_amount' => 'decimal:3',
        'total' => 'decimal:3',
    ];

    /**
     * Get the purchase return that owns the purchase return detail.
     */
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class, 'pur_return_id');
    }

    /**
     * Get the item that owns the purchase return detail.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(\Modules\Stock\Models\Item::class);
    }

    /**
     * Scope a query to only include live purchase return details.
     */
    public function scopeLive($query)
    {
        return $query->where('del_status', 'Live');
    }
}

