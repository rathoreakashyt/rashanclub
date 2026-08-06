<?php

namespace Modules\BusinessClub\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\BusinessClub\Models\CustomerWallet;
use Modules\BusinessClub\Models\WalletTransaction;
use Modules\BusinessClub\Models\BusinessClubSetting;
use Illuminate\Support\Facades\DB;

class BusinessClubDashboardController extends Controller
{
    public function index()
    {
        $companyId = session('company.company_id');

        // Basic stats
        $totalWallets = CustomerWallet::where('company_id', $companyId)->where('del_status', 'Live')->count();
        $totalBalance = CustomerWallet::where('company_id', $companyId)->where('del_status', 'Live')->sum('balance');
        $totalEarned = CustomerWallet::where('company_id', $companyId)->where('del_status', 'Live')->sum('total_earned');
        $totalRedeemed = CustomerWallet::where('company_id', $companyId)->where('del_status', 'Live')->sum('total_redeemed');
        $settings = BusinessClubSetting::where('company_id', $companyId)->where('del_status', 'Live')->first();

        // Previous month stats for percentage change
        $prevMonth = now()->subMonth();
        $prevTotalWallets = CustomerWallet::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('created_at', '<', $prevMonth->startOfMonth())
            ->count();
        $prevTotalBalance = CustomerWallet::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('created_at', '<', $prevMonth->startOfMonth())
            ->sum('balance');

        $walletChange = $prevTotalWallets > 0 ? round((($totalWallets - $prevTotalWallets) / $prevTotalWallets) * 100, 2) : 0;
        $balanceChange = $prevTotalBalance > 0 ? round((($totalBalance - $prevTotalBalance) / $prevTotalBalance) * 100, 2) : 0;

        // Weekly earning & redemption data (last 4 weeks)
        $weeklyData = [];
        for ($i = 3; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = now()->subWeeks($i)->endOfWeek();

            $earned = WalletTransaction::where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where('type', 'credit')
                ->whereBetween('transaction_date', [$weekStart, $weekEnd])
                ->sum('amount');

            $redeemed = WalletTransaction::where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where('type', 'redeem')
                ->whereBetween('transaction_date', [$weekStart, $weekEnd])
                ->sum('amount');

            $weeklyData[] = [
                'label' => 'Week ' . ($i + 1),
                'earned' => (float) $earned,
                'redeemed' => (float) $redeemed,
            ];
        }

        // Top earning business partners (customers)
        $topPartners = CustomerWallet::with('customer')
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('total_earned', '>', 0)
            ->orderBy('total_earned', 'desc')
            ->take(5)
            ->get()
            ->map(function ($wallet) {
                return [
                    'name' => optional($wallet->customer)->name ?? 'N/A',
                    'earned' => (float) $wallet->total_earned,
                    'balance' => (float) $wallet->balance,
                    'photo' => optional($wallet->customer)->photo ?? null,
                ];
            });

        // Recent transactions with sale data
        $recentTransactions = WalletTransaction::with(['customer', 'sale'])
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('id', 'desc')
            ->take(10)
            ->get()
            ->map(function ($tx) {
                return [
                    'date' => $tx->transaction_date ? $tx->transaction_date->format('d-m-Y H:i:s') : '-',
                    'customer_name' => optional($tx->customer)->name ?? 'N/A',
                    'type' => $tx->type,
                    'sale_amount' => $tx->sale ? (float) $tx->sale->grand_total : 0,
                    'amount' => (float) $tx->amount,
                    'balance_after' => (float) $tx->balance_after,
                ];
            });

        // Membership growth
        $thisMonthWallets = CustomerWallet::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $lastMonthWallets = CustomerWallet::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        $membershipGrowth = $lastMonthWallets > 0 ? round((($thisMonthWallets - $lastMonthWallets) / $lastMonthWallets) * 100) : 0;

        // Latest member joins
        $latestJoins = CustomerWallet::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return view('businessclub::dashboard.index', compact(
            'totalWallets', 'totalBalance', 'totalEarned', 'totalRedeemed',
            'settings', 'walletChange', 'balanceChange',
            'weeklyData', 'topPartners', 'recentTransactions',
            'membershipGrowth', 'latestJoins'
        ));
    }
}
