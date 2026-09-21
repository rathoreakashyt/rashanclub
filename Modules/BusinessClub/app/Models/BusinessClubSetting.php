<?php

namespace Modules\BusinessClub\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessClubSetting extends Model
{
    protected $table = 'business_club_settings';

    protected $guarded = ['id'];

    protected $casts = [
        'membership_amount' => 'decimal:2',
        'profit_share_percentage' => 'decimal:2',
        'profit_percentage' => 'decimal:2',
        'redemption_day' => 'integer',
        'redemption_date' => 'integer',
        'min_purchase_amount' => 'decimal:2',
        'minimum_bill_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function members()
    {
        return $this->hasMany(BusinessClubMember::class, 'company_id', 'company_id');
    }
}
