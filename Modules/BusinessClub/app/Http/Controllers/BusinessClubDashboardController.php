<?php

namespace Modules\BusinessClub\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\BusinessClub\Models\BusinessClubMember;
use Modules\BusinessClub\Models\BusinessClubTransaction;
use Modules\BusinessClub\Models\BusinessClubSetting;
use Modules\BusinessClub\Services\BusinessClubService;
use Illuminate\Support\Facades\DB;

class BusinessClubDashboardController extends Controller
{
    protected $businessClubService;

    public function __construct(BusinessClubService $businessClubService)
    {
        $this->businessClubService = $businessClubService;
    }

    public function index()
    {
        $companyId = session('company.company_id');
        $settings = $this->businessClubService->getSettings($companyId);

        // Basic stats from new tables
        $totalWallets = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('status', 'active')
            ->count();

        $totalBalance = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('status', 'active')
            ->sum('earned_balance');

        $totalEarned = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->sum('total_earned');

        $totalRedeemed = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->sum('total_redeemed');

        $totalLockedAmount = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('status', 'active')
            ->sum('locked_balance');

        // Previous month stats for percentage change
        $prevMonth = now()->subMonth();
        $prevTotalWallets = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('joined_at', '<', $prevMonth->startOfMonth())
            ->count();

        $prevTotalBalance = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('joined_at', '<', $prevMonth->startOfMonth())
            ->sum('earned_balance');

        $walletChange = $prevTotalWallets > 0
            ? round((($totalWallets - $prevTotalWallets) / $prevTotalWallets) * 100, 2)
            : 0;

        $balanceChange = $prevTotalBalance > 0
            ? round((($totalBalance - $prevTotalBalance) / $prevTotalBalance) * 100, 2)
            : 0;

        // Weekly earning & redemption data (last 4 weeks)
        $weeklyData = [];
        for ($i = 3; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = now()->subWeeks($i)->endOfWeek();

            $earned = BusinessClubTransaction::where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where('type', 'profit_credit')
                ->whereBetween('transaction_date', [$weekStart, $weekEnd])
                ->sum('amount');

            $redeemed = BusinessClubTransaction::where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where('type', 'redemption')
                ->whereBetween('transaction_date', [$weekStart, $weekEnd])
                ->sum('amount');

            $weeklyData[] = [
                'label' => 'Week ' . (4 - $i),
                'earned' => (float) $earned,
                'redeemed' => (float) $redeemed,
            ];
        }

        // Top earning business partners (members)
        $topPartners = BusinessClubMember::with('customer')
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('total_earned', '>', 0)
            ->orderBy('total_earned', 'desc')
            ->take(5)
            ->get()
            ->map(function ($member) {
                return [
                    'name' => optional($member->customer)->name ?? 'N/A',
                    'member_id' => $member->member_id,
                    'earned' => (float) $member->total_earned,
                    'balance' => (float) $member->earned_balance,
                    'photo' => optional($member->customer)->photo ?? null,
                ];
            });

        // Recent transactions with sale data
        $recentTransactions = BusinessClubTransaction::with(['member.customer', 'sale'])
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('id', 'desc')
            ->take(10)
            ->get()
            ->map(function ($tx) {
                return [
                    'date' => $tx->transaction_date ? $tx->transaction_date->format('d-m-Y') : '-',
                    'customer_name' => optional(optional($tx->member)->customer)->name ?? 'N/A',
                    'member_id' => optional($tx->member)->member_id ?? '-',
                    'type' => str_replace('_', ' ', $tx->type),
                    'sale_amount' => $tx->sale ? (float) $tx->sale->grand_total : 0,
                    'amount' => (float) $tx->amount,
                    'balance_after' => (float) $tx->balance_after,
                ];
            });

        // Membership growth
        $thisMonthWallets = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->whereMonth('joined_at', now()->month)
            ->whereYear('joined_at', now()->year)
            ->count();

        $lastMonthWallets = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->whereMonth('joined_at', now()->subMonth()->month)
            ->whereYear('joined_at', now()->subMonth()->year)
            ->count();

        $membershipGrowth = $lastMonthWallets > 0
            ? round((($thisMonthWallets - $lastMonthWallets) / $lastMonthWallets) * 100)
            : 0;

        $latestJoins = $thisMonthWallets;

        return view('businessclub::dashboard.index', compact(
            'totalWallets', 'totalBalance', 'totalEarned', 'totalRedeemed',
            'totalLockedAmount', 'settings', 'walletChange', 'balanceChange',
            'weeklyData', 'topPartners', 'recentTransactions',
            'membershipGrowth', 'latestJoins'
        ));
    }
}
