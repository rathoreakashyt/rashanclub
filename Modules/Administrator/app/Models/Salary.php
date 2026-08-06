<?php

namespace Modules\Administrator\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Salary extends Model
{
    protected $fillable = [
        'reference_no',
        'year',
        'month',
        'generated_date',
        'total_amount',
        'user_id',
        'company_id',
        'del_status',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'generated_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    protected $appends = [
        'encrypted_id',
    ];

    /**
     * Get the user that created the salary
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the salary items for this salary
     */
    public function salaryItems(): HasMany
    {
        return $this->hasMany(SalaryItem::class);
    }

    /**
     * Get the live salary items for this salary
     */
    public function liveSalaryItems(): HasMany
    {
        return $this->hasMany(SalaryItem::class)->where('del_status', 'Live');
    }

    /**
     * Get the salary payments for this salary
     */
    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }

    /**
     * Get the live salary payments for this salary
     */
    public function liveSalaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class)->where('del_status', 'Live');
    }

    /**
     * Get the encrypted ID attribute
     */
    public function getEncryptedIdAttribute()
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

