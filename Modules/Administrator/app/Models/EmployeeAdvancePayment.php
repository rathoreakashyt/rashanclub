<?php

namespace Modules\Administrator\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\PaymentMethod;

class EmployeeAdvancePayment extends Model
{
    protected $table = 'employee_advance_payments';

    protected $fillable = [
        'reference_no',
        'date',
        'amount',
        'note',
        'payment_method_id',
        'employee_id',
        'outlet_id',
        'user_id',
        'company_id',
        'del_status',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    protected $appends = [
        'encrypted_id',
    ];

    /**
     * Get the employee that received the advance payment
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Get the user who created the record
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the payment method
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * Get the encrypted ID attribute
     */
    public function getEncryptedIdAttribute(): string
    {
        return encrypt($this->id);
    }

    /**
     * Resolve route binding using encrypted ID
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', decrypt($value))->first();
    }
}
