<?php

namespace Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;

class PriceListItem extends Model
{
    protected $guarded = ['id'];

    public function priceList()
    {
        return $this->belongsTo(PriceList::class, 'price_list_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
