<?php

namespace Modules\Stock\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_name',
        'description',
        'company_id',
        'user_id',
        'del_status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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
     * Get the user who created this unit
     */
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Get the user who last updated this unit
     */
    public function updater()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Scope for active units
     */
    public function scopeActive($query)
    {
        return $query->where('del_status', 'Live');
    }

    /**
     * Scope for inactive units
     */
    public function scopeInactive($query)
    {
        return $query->where('del_status', 'Deleted');
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(Item::class, 'purchase_unit_id');
    }

    public function saleItems()
    {
        return $this->hasMany(Item::class, 'sale_unit_id');
    }

    /**
     * Resolve route binding using encrypted ID
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', decrypt($value))->first();
    }
}
