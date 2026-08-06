<?php

namespace Modules\BusinessClub\Services;

use Modules\BusinessClub\Models\BusinessClubSetting;
use Modules\BusinessClub\Models\CustomerWallet;
use Modules\BusinessClub\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class BusinessClubService
{
    public function getSettings(int $companyId): ?BusinessClubSetting
    {
        return BusinessClubSetting::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->first();
    }

    public function saveSettings(array $data, int $companyId): BusinessClubSetting
    {
        $settings = BusinessClubSetting::updateOrCreate(
            ['company_id' => $companyId],
            [
                'profit_percentage' => $data['profit_percentage'] ?? 50,
                'redemption_date' => $data['redemption_date'] ?? 1,
                'min_purchase_amount' => $data['min_purchase_amount'] ?? 10000,
                'minimum_bill_amount' => $data['minimum_bill_amount'] ?? 0,
                'del_status' => 'Live',
            ]
        );

        return $settings;
    }

    public function getOrCreateWallet(int $customerId, int $companyId): CustomerWallet
    {
        return CustomerWallet::firstOrCreate(
            ['customer_id' => $customerId, 'company_id' => $companyId],
            [
                'total_earned' => 0,
                'balance' => 0,
                'total_redeemed' => 0,
                'del_status' => 'Live',
            ]
        );
    }

    public function creditWalletFromSale(int $customerId, float $totalProfit, int $saleId, int $companyId): ?WalletTransaction
    {
        $settings = $this->getSettings($companyId);
        if (!$settings) {
            return null;
        }

        $percentage = $settings->profit_percentage / 100;
        $creditAmount = round($totalProfit * $percentage, 2);

        if ($creditAmount <= 0) {
            return null;
        }

        $wallet = $this->getOrCreateWallet($customerId, $companyId);
        $balanceBefore = $wallet->balance;

        $wallet->increment('total_earned', $creditAmount);
        $wallet->increment('balance', $creditAmount);

        return WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'customer_id' => $customerId,
            'sale_id' => $saleId,
            'type' => 'credit',
            'amount' => $creditAmount,
            'balance_before' => $balanceBefore,
            'balance_after' => $wallet->fresh()->balance,
            'description' => 'Profit share from sale #' . $saleId,
            'transaction_date' => now()->toDateString(),
            'company_id' => $companyId,
            'del_status' => 'Live',
        ]);
    }

    public function getWalletBalance(int $customerId, int $companyId): float
    {
        $wallet = CustomerWallet::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->first();

        return $wallet ? (float) $wallet->balance : 0;
    }

    public function getWalletReport(int $companyId, ?string $search = '', int $start = 0, int $length = 10)
    {
        $settings = $this->getSettings($companyId);
        $minimumBillAmount = $settings ? (float) $settings->minimum_bill_amount : 0;

        $base = DB::table('customers')
            ->where('customers.company_id', $companyId)
            ->where('customers.del_status', 'Live')
            ->where('customers.id', '!=', 1);

        if ($minimumBillAmount > 0) {
            $base->whereExists(function ($q) use ($companyId, $minimumBillAmount) {
                $q->select(DB::raw(1))
                    ->from('sales')
                    ->whereColumn('sales.customer_id', '=', 'customers.id')
                    ->where('sales.company_id', $companyId)
                    ->where('sales.del_status', 'Live')
                    ->where('sales.grand_total', '>=', $minimumBillAmount);
            });
        }

        $recordsTotal = $base->count();

        if ($search) {
            $base->where(function ($q) use ($search) {
                $q->where('customers.name', 'like', "%{$search}%")
                  ->orWhere('customers.phone', 'like', "%{$search}%");
            });
        }

        $filteredCount = $base->count();

        $data = $base
            ->leftJoin('customer_wallets', function ($join) use ($companyId) {
                $join->on('customers.id', '=', 'customer_wallets.customer_id')
                    ->where('customer_wallets.company_id', '=', $companyId)
                    ->where('customer_wallets.del_status', '=', 'Live');
            })
            ->select(
                'customers.id as customer_id',
                DB::raw('COALESCE(customer_wallets.id, 0) as id'),
                'customers.name as customer_name',
                'customers.phone as customer_phone',
                DB::raw('COALESCE(customer_wallets.total_earned, 0) as total_earned'),
                DB::raw('COALESCE(customer_wallets.balance, 0) as balance'),
                DB::raw('COALESCE(customer_wallets.total_redeemed, 0) as total_redeemed')
            )
            ->orderBy('customers.id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return compact('recordsTotal', 'filteredCount', 'data');
    }

    public function getTransactions(int $walletId, int $companyId, int $start = 0, int $length = 10)
    {
        $query = WalletTransaction::where('wallet_id', $walletId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live');

        $recordsTotal = $query->count();
        $filteredCount = $recordsTotal;
        $data = $query->orderBy('id', 'desc')->skip($start)->take($length)->get();

        return compact('recordsTotal', 'filteredCount', 'data');
    }

    public function redeemWallet(int $customerId, float $amount, int $companyId): ?WalletTransaction
    {
        $wallet = CustomerWallet::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->firstOrFail();

        if ($wallet->balance < $amount) {
            throw new \Exception('Insufficient wallet balance');
        }

        $balanceBefore = $wallet->balance;
        $wallet->decrement('balance', $amount);
        $wallet->increment('total_redeemed', $amount);

        return WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'customer_id' => $customerId,
            'sale_id' => null,
            'type' => 'redeem',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $wallet->fresh()->balance,
            'description' => 'Redeemed as check payment',
            'transaction_date' => now()->toDateString(),
            'company_id' => $companyId,
            'del_status' => 'Live',
        ]);
    }
}
