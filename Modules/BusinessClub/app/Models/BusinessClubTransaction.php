<?php

namespace Modules\BusinessClub\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Sale\Models\Customer;
use Modules\Sale\Models\Sale;

class BusinessClubTransaction extends Model
{
    protected $table = 'business_club_transactions';

    protected $guarded = ['id'];

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
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId)->where('del_status', 'Live');
    }
}
