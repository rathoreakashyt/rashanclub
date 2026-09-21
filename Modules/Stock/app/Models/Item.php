<?php

namespace Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Item extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'alternative_name',
        'generic_name',
        'type',
        'expiry_date_maintain',
        'category_id',
        'rack_id',
        'brand_id',
        'supplier_id',
        'alert_quantity',
        'unit_type',
        'purchase_unit_id',
        'sale_unit_id',
        'conversion_rate',
        'purchase_price',
        'last_three_purchase_avg',
        'last_purchase_price',
        'sale_price',
        'profit_margin',
        'whole_sale_price',
        'mrp_price',
        'description',
        'warranty',
        'warranty_date',
        'guarantee',
        'guarantee_date',
        'photo',
        'tax_information',
        'tax_string',
        'tax_type',
        'applicable_tax_id',
        'hsn_code',
        'variation_details',
        'enable_disable_status',
        'parent_id',
        'loyalty_point',
        'user_id',
        'company_id',
        'del_status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'purchase_price' => 'decimal:3',
        'last_three_purchase_avg' => 'decimal:3',
        'last_purchase_price' => 'decimal:3',
        'sale_price' => 'decimal:3',
        'profit_margin' => 'decimal:3',
        'whole_sale_price' => 'decimal:3',
        'mrp_price' => 'decimal:3',
        'variation_details' => 'array',
        'tax_information' => 'array',
        'parent_id' => 'integer',
        'user_id' => 'integer',
        'company_id' => 'integer',
        'expiry_date_maintain' => 'integer',
        'enable_disable_status' => 'integer',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'encrypted_id',
    ];

    /**
     * Get the encrypted ID attribute
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }

    /**
     * Get the purchase unit that owns the item.
     */
    public function purchaseUnit()
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    /**
     * Get the sale unit that owns the item.
     */
    public function saleUnit()
    {
        return $this->belongsTo(Unit::class, 'sale_unit_id');
    }

    /**
     * Get the brand that owns the item.
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * Get the category that owns the item.
     */
    public function category()
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    /**
     * Get the supplier that owns the item.
     */
    public function supplier()
    {
        return $this->belongsTo(\Modules\Purchase\Models\Supplier::class, 'supplier_id');
    }

    /**
     * Get the rack that owns the item.
     */
    public function rack()
    {
        return $this->belongsTo(Rack::class, 'rack_id');
    }

    /**
     * Get the parent item (for variation products).
     */
    public function parent()
    {
        return $this->belongsTo(Item::class, 'parent_id');
    }

    /**
     * Get the child items (for variation products).
     */
    public function children()
    {
        return $this->hasMany(Item::class, 'parent_id');
    }

    /**
     * Scope a query to only include live items.
     */
    public function scopeLive($query)
    {
        return $query->where('del_status', 'Live');
    }

    /**
     * Scope a query to only include items for current company.
     */
    public function scopeForCompany($query)
    {
        return $query->where('company_id', session('company.company_id'));
    }
}

