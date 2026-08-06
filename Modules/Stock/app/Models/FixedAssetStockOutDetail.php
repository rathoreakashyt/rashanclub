<?php

namespace Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetStockOutDetail extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'fixed_asset_stock_out_details';

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'unit_price' => 'decimal:3',
        'total' => 'decimal:3',
    ];

    /**
     * Get the stock out that owns the detail.
     */
    public function stockOut(): BelongsTo
    {
        return $this->belongsTo(FixedAssetStockOut::class, 'asset_stock_out_id');
    }

    /**
     * Get the fixed asset item for the detail.
     */
    public function fixedAssetItem(): BelongsTo
    {
        return $this->belongsTo(FixedAssetItem::class, 'item_id');
    }

    /**
     * Get the user who created the detail.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the outlet for the detail.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Outlet::class);
    }

    /**
     * Get the company for the detail.
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
}

