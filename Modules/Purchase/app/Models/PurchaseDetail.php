<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseDetail extends Model
{
    protected $table = 'purchase_details';
    
    protected $fillable = [
        'item_id',
        'item_type',
        'expiry_imei_serial',
        'unit_price',
        'quantity_amount',
        'total',
        'purchase_id',
        'user_id',
        'outlet_id',
        'company_id',
        'del_status'
    ];

    protected $casts = [
        'unit_price' => 'decimal:3',
        'quantity_amount' => 'decimal:3',
        'total' => 'decimal:3',
    ];

    /**
     * Get the purchase that owns the purchase detail.
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * Get the item that owns the purchase detail.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(\Modules\Stock\Models\Item::class);
    }

    /**
     * Scope a query to only include live purchase details.
     */
    public function scopeLive($query)
    {
        return $query->where('del_status', 'Live');
    }

    /**
     * Get the IMEI/Serial numbers as an array
     */
    public function getImeiSerialNumbersAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    /**
     * Set the IMEI/Serial numbers as JSON
     */
    public function setImeiSerialNumbersAttribute($value)
    {
        $this->attributes['imei_serial_numbers'] = is_array($value) ? json_encode($value) : $value;
    }
}
