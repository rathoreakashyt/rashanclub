<?php

namespace Modules\BusinessClub\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Sale\Models\Customer;

class BusinessClubMember extends Model
{
    protected $table = 'business_club_members';

    protected $guarded = ['id'];

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
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function transactions()
    {
        return $this->hasMany(BusinessClubTransaction::class, 'member_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('del_status', 'Live');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
