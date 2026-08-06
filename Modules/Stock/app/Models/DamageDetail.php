<?php

namespace Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageDetail extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'damage_details';

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'date' => 'date',
        'damage_quantity' => 'decimal:3',
        'last_purchase_price' => 'decimal:3',
        'loss_amount' => 'decimal:3',
        'total_amount' => 'decimal:3',
    ];

    /**
     * Get the damage that owns the detail.
     */
    public function damage(): BelongsTo
    {
        return $this->belongsTo(Damage::class);
    }

    /**
     * Get the item for the damage detail.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
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

