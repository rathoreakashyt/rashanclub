<?php

namespace Modules\BusinessClub\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerWallet extends Model
{
    protected $table = 'customer_wallets';

    protected $guarded = ['id'];

    protected $casts = [
        'total_earned' => 'decimal:2',
        'balance' => 'decimal:2',
        'total_redeemed' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(\Modules\Sale\Models\Customer::class, 'customer_id');
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id');
    }
}
