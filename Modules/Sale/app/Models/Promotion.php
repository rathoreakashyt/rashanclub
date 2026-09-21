<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected $casts = [
        'start_time' => 'string',
        'end_time' => 'string',
        'applicable_items' => 'array',
        'applicable_categories' => 'array',
        'applicable_customers' => 'array',
        'applicable_customer_types' => 'array',
        'tier_percentages' => 'array',
        'flavour_alternatives' => 'array',
    ];

    protected $append = [
        'encrypted_id',
    ];

    /**
     * Get the encrypted ID attribute
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }
}

