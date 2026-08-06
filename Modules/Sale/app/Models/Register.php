<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Register extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'registers';

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'opening_balance' => 'float',
        'closing_balance' => 'float',
        'sale_paid_amount' => 'float',
        'refund_amount' => 'float',
        'customer_due_receive' => 'float',
        'total_purchase' => 'float',
        'total_downpayment' => 'float',
        'total_installmentcollection' => 'float',
        'total_servicing' => 'float',
        'total_purchase_return' => 'float',
        'total_due_payment' => 'float',
        'total_expense' => 'float',
        'register_status' => 'integer',
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
     * Get the user that owns the register.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the outlet for the register.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Outlet::class);
    }

    /**
     * Get the company for the register.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Company::class);
    }

    /**
     * Get the counter for the register.
     */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Counter::class);
    }

    /**
     * Scope a query to only include live records.
     */
    public function scopeLive($query)
    {
        return $query->where('del_status', 'Live');
    }

    /**
     * Scope a query to only include open registers.
     */
    public function scopeOpen($query)
    {
        return $query->where('register_status', 1);
    }

    /**
     * Scope a query to only include closed registers.
     */
    public function scopeClosed($query)
    {
        return $query->where('register_status', 2);
    }

    /**
     * Scope a query to only include records for a specific company.
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope a query to only include records for a specific outlet.
     */
    public function scopeForOutlet($query, int $outletId)
    {
        return $query->where('outlet_id', $outletId);
    }

    /**
     * Scope a query to only include records for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if register is open.
     */
    public function isOpen(): bool
    {
        return $this->register_status == 1;
    }

    /**
     * Check if register is closed.
     */
    public function isClosed(): bool
    {
        return $this->register_status == 2;
    }

    /**
     * Check if user has an open register
     * 
     * @param int $userId
     * @param int $outletId
     * @param int $companyId
     * @return bool
     */
    public static function isUserRegisterOpen(int $userId, int $outletId, int $companyId): bool
    {
        $latestRegister = static::where('user_id', $userId)
            ->where('outlet_id', $outletId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('id', 'desc')
            ->first();

        // Register is open only if latest record exists and has status = 1
        return $latestRegister && $latestRegister->register_status == 1;
    }

    /**
     * Get the latest register for a user
     * 
     * @param int $userId
     * @param int $outletId
     * @param int $companyId
     * @return Register|null
     */
    public static function getLatestRegister(int $userId, int $outletId, int $companyId): ?Register
    {
        return static::where('user_id', $userId)
            ->where('outlet_id', $outletId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('id', 'desc')
            ->first();
    }
}
