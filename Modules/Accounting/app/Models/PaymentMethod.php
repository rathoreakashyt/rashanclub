<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
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
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'configuration' => 'array',
    ];

    // Relation with CustomerReceive 
    public function customer_receives()
    {
        return $this->hasMany(CustomerReceive::class, 'payment_method_id');
    }

    public function incomes()
    {
        return $this->hasMany(Income::class, 'payment_method_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'payment_method_id');
    }

    public function supplierPayments()
    {
        return $this->hasMany(SupplierPayment::class, 'payment_method_id');
    }

    

    /**
     * Get the encrypted ID attribute
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }
}

