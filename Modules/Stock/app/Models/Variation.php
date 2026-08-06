<?php
namespace Modules\Stock\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Variation extends Model
{
    use HasFactory;

    protected $fillable = [
        'variation_name',
        'variation_value',
        'user_id',
        'company_id',
        'del_status',
    ];

    protected $casts = [
        'variation_value' => 'array',
        'user_id' => 'integer',
        'company_id' => 'integer',
        'del_status' => 'string',
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
     * Scope for active variations
     */
    public function scopeActive($query)
    {
        return $query->where('del_status', 'Live');
    }

    /**
     * Scope for inactive variations
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

