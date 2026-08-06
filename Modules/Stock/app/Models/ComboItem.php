<?php

namespace Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ComboItem extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'combo_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'combo_item_id',
        'item_id',
        'quantity',
        'amount',
        'total',
        'show_in_invoice',
        'user_id',
        'company_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'decimal:3',
        'amount' => 'decimal:3',
        'total' => 'decimal:3',
        'show_in_invoice' => 'boolean',
        'user_id' => 'integer',
        'company_id' => 'integer',
    ];

    /**
     * Get the combo item (parent item).
     */
    public function comboItem()
    {
        return $this->belongsTo(Item::class, 'combo_item_id');
    }

    /**
     * Get the item included in the combo.
     */
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * Scope a query to only include items for current company.
     */
    public function scopeForCompany($query)
    {
        return $query->where('company_id', session('company.company_id'));
    }
}

