<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class IncomeCategory extends Model
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

    public function incomes()
    {
        return $this->hasMany(Income::class);
    }

    /**
     * Get the medicine count
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }
}

