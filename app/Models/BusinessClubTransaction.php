<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Sale\Models\Customer;

class BusinessClubTransaction extends Model
{
    protected $table = 'business_club_transactions';

    protected $fillable = [
        'member_id',
        'customer_id',
        'sale_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'transaction_date',
        'company_id',
        'del_status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function member()
    {
        return $this->belongsTo(BusinessClubMember::class, 'member_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
