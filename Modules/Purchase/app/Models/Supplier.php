<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
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
     * Get the supplier payments for the supplier.
     */
    public function supplierPayments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * Get the payment method that owns the supplier.
     */
    public function paymentAccounts(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\PaymentMethod::class, 'payment_method_id');
    }

    /**
     * Get the encrypted ID attribute
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }
}

