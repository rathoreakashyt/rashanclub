<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected $append = [
        'encrypted_id',
    ];

    public function customer_receives()
    {
        return $this->hasMany(CustomerReceive::class, 'customer_id', 'id');
    }

    /**
     * Get the state associated with the customer.
     */
    public function state()
    {
        return $this->belongsTo(\Modules\Configuration\Models\State::class);
    }

    /**
     * Get the medicine count
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }
}