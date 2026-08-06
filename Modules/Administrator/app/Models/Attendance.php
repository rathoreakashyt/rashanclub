<?php

namespace Modules\Administrator\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'reference_no',
        'date',
        'employee_id',
        'in_time',
        'out_time',
        'note',
        'user_id',
        'company_id',
        'del_status',
    ];

    protected $casts = [
        'date' => 'date',
        'in_time' => 'datetime:H:i',
        'out_time' => 'datetime:H:i',
    ];

    protected $appends = [
        'encrypted_id',
    ];

    /**
     * Get the employee that owns the attendance
     */
    public function employee()
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

    /**
     * Resolve route binding using encrypted ID
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', decrypt($value))->first();
    }
}
