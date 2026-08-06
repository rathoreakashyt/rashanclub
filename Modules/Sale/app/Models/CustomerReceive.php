<?php

namespace Modules\Sale\Models;

use Modules\Accounting\Models\PaymentMethod;
use Modules\Configuration\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomerReceive extends Model
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

    public function payment()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the medicine count
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }
}