<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessClubSetting extends Model
{
    protected $table = 'business_club_settings';

    protected $fillable = [
        'company_name',
        'business_partner_name',
        'address',
        'phone',
        'email',
        'logo',
        'profit_percentage',
        'redemption_date',
        'min_purchase_amount',
        'membership_amount',
        'profit_share_percentage',
        'redemption_day',
        'is_active',
        'minimum_bill_amount',
        'company_id',
        'del_status',
    ];

    protected $casts = [
        'profit_percentage' => 'decimal:2',
        'profit_share_percentage' => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
        'membership_amount' => 'decimal:2',
        'minimum_bill_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
