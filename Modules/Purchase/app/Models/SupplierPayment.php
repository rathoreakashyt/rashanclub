<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Models\PaymentMethod;

class SupplierPayment extends Model
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
     * Get the supplier that owns the supplier payment.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the payment method that owns the supplier payment.
     */
    public function account()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * Get the encrypted ID attribute
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }
}

