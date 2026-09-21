<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Sale\Models\Customer;

class BusinessClubMember extends Model
{
    protected $table = 'business_club_members';

    protected $fillable = [
        'member_id',
        'customer_id',
        'company_id',
        'membership_amount',
        'locked_balance',
        'earned_balance',
        'total_earned',
        'total_redeemed',
        'status',
        'joined_at',
        'del_status',
    ];

    protected $casts = [
        'membership_amount' => 'decimal:2',
        'locked_balance' => 'decimal:2',
        'earned_balance' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'total_redeemed' => 'decimal:2',
        'joined_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
