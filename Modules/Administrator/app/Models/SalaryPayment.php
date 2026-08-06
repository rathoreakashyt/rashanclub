<?php

namespace Modules\Administrator\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPayment extends Model
{
    protected $fillable = [
        'salary_id',
        'payment_method_id',
        'amount',
        'user_id',
        'company_id',
        'del_status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected $appends = [
        'encrypted_id',
    ];

    /**
     * Get the salary that owns the salary payment
     */
    public function salary(): BelongsTo
    {
        return $this->belongsTo(Salary::class);
    }

    /**
     * Get the payment method that owns the salary payment
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\PaymentMethod::class, 'payment_method_id');
    }

    /**
     * Get the user that created the salary payment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the encrypted ID attribute
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }
}

