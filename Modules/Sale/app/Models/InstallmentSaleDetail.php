<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentSaleDetail extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'installment_sale_details';

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'payment_date' => 'date',
        'paid_date' => 'date',
        'amount' => 'decimal:3',
        'paid_amount' => 'decimal:3',
        'remaining_amount' => 'decimal:3',
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
     * Get the installment sale that owns the detail.
     */
    public function installmentSale(): BelongsTo
    {
        return $this->belongsTo(InstallmentSale::class);
    }

    /**
     * Get the payment method for this installment.
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
     * Scope a query to only include unpaid installments.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('paid_status', 'Unpaid');
    }

    /**
     * Scope a query to only include overdue installments.
     */
    public function scopeOverdue($query)
    {
        return $query->where('paid_status', '!=', 'Paid')
            ->where('payment_date', '<', now()->toDateString());
    }

    /**
     * Check if installment is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->paid_status !== 'Paid' && $this->payment_date < now()->toDateString();
    }
}

