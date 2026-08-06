<?php

namespace Modules\Stock\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'sort_id',
        'company_id',
        'user_id',
        'del_status'
    ];

    protected $casts = [
        'sort_id' => 'integer',
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
     * Get the user who created this item category
     */
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Get the user who last updated this item category
     */
    public function updater()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Scope for active item categories
     */
    public function scopeActive($query)
    {
        return $query->where('del_status', 'Live');
    }

    /**
     * Scope for inactive item categories
     */
    public function scopeInactive($query)
    {
        return $query->where('del_status', 'Deleted');
    }

    /**
     * Resolve route binding using encrypted ID
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', decrypt($value))->first();
    }
}
