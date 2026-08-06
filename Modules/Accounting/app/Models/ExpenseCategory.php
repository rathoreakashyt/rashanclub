<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
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

    public function expense()
    {
        return $this->hasMany(Expense::class);
    }
   

    /**
     * Get the medicine count
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }
}

