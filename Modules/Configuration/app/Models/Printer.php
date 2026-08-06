<?php

namespace Modules\Configuration\Models;

use Illuminate\Database\Eloquent\Model;

class Printer extends Model
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

    /**
     * Get the counters associated with the printer.
     */
    public function counters()
    {
        return $this->hasMany(Counter::class, 'printer_id');
    }
}

