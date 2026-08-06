<?php

namespace Modules\Configuration\Models;

use Illuminate\Database\Eloquent\Model;

class Denomination extends Model
{
    protected $guarded = ['id'];

    protected $append = [
        'encrypted_id',
    ];

    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }

    // Map `amount` references to `value` column
    public function getAmountAttribute()
    {
        return $this->value;
    }

    public function setAmountAttribute($value)
    {
        $this->value = $value;
    }
}

