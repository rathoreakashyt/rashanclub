<?php

namespace Modules\Configuration\Models;

use Illuminate\Database\Eloquent\Model;

class MultipleCurrency extends Model
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

    /**
     * Get the medicine count
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }
}

