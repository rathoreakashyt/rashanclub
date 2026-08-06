<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentSalePayment extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'installment_sale_payments';

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'payment_date' => 'date',
        'check_issue_date' => 'date',
        'check_expiry_date' => 'date',
        'amount' => 'decimal:3',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = ['encrypted_id'];

    /**
     * Get the encrypted ID attribute.
     */
    public function getEncryptedIdAttribute(): string
    {
        return encrypt($this->id);
    }

    /**
     * Get the installment sale for this payment.
     */
    public function installmentSale(): BelongsTo
    {
        return $this->belongsTo(InstallmentSale::class);
    }

    /**
     * Get the installment detail for this payment (if applicable).
     */
    public function installmentSaleDetail(): BelongsTo
    {
        return $this->belongsTo(InstallmentSaleDetail::class);
    }

    /**
     * Get the payment method for this payment.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\PaymentMethod::class);
    }

    /**
     * Get the user who processed the payment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Scope a query to only include live records.
     */
    public function scopeLive($query)
    {
        return $query->where('del_status', 'Live');
    }

    /**
     * Scope a query to only include down payments.
     */
    public function scopeDownPayments($query)
    {
        return $query->where('payment_type', 'Down_Payment');
    }

    /**
     * Scope a query to only include installment payments.
     */
    public function scopeInstallmentPayments($query)
    {
        return $query->where('payment_type', 'Installment_Payment');
    }

    /**
     * Check if this is a down payment.
     */
    public function isDownPayment(): bool
    {
        return $this->payment_type === 'Down_Payment';
    }
}

