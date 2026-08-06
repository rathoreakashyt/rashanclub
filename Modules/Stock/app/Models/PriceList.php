<?php

namespace Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;

class PriceList extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'customer_type' => 'string',
    ];

    public function items()
    {
        return $this->hasMany(PriceListItem::class, 'price_list_id');
    }

    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }

    protected $appends = ['encrypted_id'];
}
