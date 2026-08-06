<?php

namespace Modules\BusinessClub\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessClubSetting extends Model
{
    protected $table = 'business_club_settings';

    protected $guarded = ['id'];

    protected $casts = [
        'profit_percentage' => 'decimal:2',
        'redemption_date' => 'integer',
        'min_purchase_amount' => 'decimal:2',
        'minimum_bill_amount' => 'decimal:2',
    ];
}
