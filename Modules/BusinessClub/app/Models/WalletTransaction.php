<?php

namespace Modules\BusinessClub\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $table = 'wallet_transactions';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function wallet()
    {
        return $this->belongsTo(CustomerWallet::class, 'wallet_id');
    }

    public function customer()
    {
        return $this->belongsTo(\Modules\Sale\Models\Customer::class, 'customer_id');
    }

    public function sale()
    {
        return $this->belongsTo(\Modules\Sale\Models\Sale::class, 'sale_id');
    }
}
