<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Models\PaymentMethod;

class DepositWithdraw extends Model
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
     * Get the payment method that owns the deposit/withdraw.
     */
    public function paymentMethod()
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

