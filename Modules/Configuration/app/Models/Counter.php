<?php

namespace Modules\Configuration\Models;

use Illuminate\Database\Eloquent\Model;

class Counter extends Model
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
     * Get the printer associated with the counter.
     */
    public function printer()
    {
        return $this->belongsTo(Printer::class, 'printer_id');
    }

    /**
     * Get the outlet associated with the counter.
     */
    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }
}

