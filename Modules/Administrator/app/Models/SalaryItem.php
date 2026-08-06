<?php

namespace Modules\Administrator\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryItem extends Model
{
    protected $fillable = [
        'salary_id',
        'employee_id',
        'salary_amount',
        'overtime_rate',
        'overtime_hour',
        'additional_amount',
        'deduction_amount',
        'absent_day',
        'absent_day_amount',
        'tips',
        'advance_taken',
        'net_salary',
        'note',
        'user_id',
        'company_id',
        'del_status',
    ];

    protected $casts = [
        'salary_amount' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'overtime_hour' => 'decimal:2',
        'additional_amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'absent_day' => 'integer',
        'absent_day_amount' => 'decimal:2',
        'tips' => 'decimal:2',
        'advance_taken' => 'decimal:2',
        'net_salary' => 'decimal:2',
    ];

    protected $appends = [
        'encrypted_id',
    ];

    /**
     * Get the salary that owns the salary item
     */
    public function salary(): BelongsTo
    {
        return $this->belongsTo(Salary::class);
    }

    /**
     * Get the employee that owns the salary item
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Get the encrypted ID attribute
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }
}

