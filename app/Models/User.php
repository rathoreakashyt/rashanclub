<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Modules\Configuration\Models\Outlet;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'salary',
        'commission',
        'outlet_id',
        'will_login',
        'del_status',
        'photo',
        'discount_permission_code',
        'discount_amt',
        'start_date',
        'end_date',
        'company_id',
        'two_factor_enabled',
        'session_timeout',
        'login_notifications',
        'question',
        'answer',
    ];

    protected $append = [
        'encrypted_id',
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the role associated with the user
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the outlets associated with the user
     * Note: outlet_id is stored as comma-separated IDs
     */
    public function getOutletsAttribute()
    {
        if (!$this->outlet_id) {
            return collect([]);
        }
        $outletIds = explode(',', $this->outlet_id);
        return Outlet::whereIn('id', $outletIds)->get();
    }

    /**
     * Get the medicine count
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }

    public function incomes()
    {
        return $this->hasMany(Income::class);
    }

    public function expense()
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Resolve route binding using encrypted ID
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', decrypt($value))->first();
    }

}

