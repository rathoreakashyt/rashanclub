<?php

namespace Modules\BusinessClub\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\BusinessClub\Services\BusinessClubService;
use Modules\BusinessClub\Models\BusinessClubMember;
use Modules\BusinessClub\Models\BusinessClubTransaction;
use Modules\Sale\Models\Customer;
use Modules\Sale\Models\Sale;

/**
 * WalletController kept for backward compatibility.
 * Delegates to BusinessClubService using new tables (business_club_members, business_club_transactions).
 */
class WalletController extends Controller
{
    protected $businessClubService;

    public function __construct(BusinessClubService $businessClubService)
    {
        $this->businessClubService = $businessClubService;
    }

    /**
     * Members list page (replaces old wallet index).
     */
    public function index(Request $request)
    {
        $companyId = session('company.company_id');

        if ($request->ajax()) {
            $draw = (int) $request->input('draw', 1);
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $search = $request->input('search.value', '') ?? '';

            $result = $this->businessClubService->getMembersList($companyId, $search, $start, $length);

            $data = $result['data']->map(function ($row, $index) use ($start) {
                return [
                    'id' => $row->id,
                    'sn' => $start + $index + 1,
                    'member_id' => $row->member_id,
                    'customer_id' => $row->customer_id,
                    'customer_name' => $row->customer_name ?? 'N/A',
                    'customer_phone' => $row->customer_phone ?? '-',
                    'membership_amount' => number_format($row->membership_amount, 2),
                    'locked_balance' => number_format($row->locked_balance, 2),
                    'earned_balance' => number_format($row->earned_balance, 2),
                    'total_earned' => number_format($row->total_earned, 2),
                    'total_redeemed' => number_format($row->total_redeemed, 2),
                    'status' => $row->status,
                    'joined_at' => $row->joined_at ? \Carbon\Carbon::parse($row->joined_at)->format('d-m-Y') : '-',
                ];
            });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $result['recordsTotal'],
                'recordsFiltered' => $result['filteredCount'],
                'data' => $data,
            ]);
        }

        return view('businessclub::wallets.index');
    }

    /**
     * Member transactions.
     */
    public function transactions(Request $request, int $memberId)
    {
        $companyId = session('company.company_id');
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 100);

        $result = $this->businessClubService->getMemberTransactions($memberId, $companyId, $start, $length);

        return response()->json([
            'data' => $result['data']->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'type' => ucfirst(str_replace('_', ' ', $tx->type)),
                    'amount' => number_format($tx->amount, 2),
                    'balance_before' => number_format($tx->balance_before, 2),
                    'balance_after' => number_format($tx->balance_after, 2),
                    'description' => $tx->description,
                    'date' => $tx->transaction_date->format('d-m-Y'),
                    'sale_id' => $tx->sale_id,
                ];
            }),
            'recordsTotal' => $result['recordsTotal'],
            'recordsFiltered' => $result['filteredCount'],
        ]);
    }

    /**
     * Member profile page.
     */
    public function profile(int $customerId)
    {
        $companyId = session('company.company_id');

        $customer = Customer::where('id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->firstOrFail();

        $member = BusinessClubMember::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->first();

        $settings = $this->businessClubService->getSettings($companyId);

        $transactions = BusinessClubTransaction::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('id', 'desc')
            ->take(50)
            ->get();

        $recentProfitShares = BusinessClubTransaction::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('type', 'profit_credit')
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();

        $monthlySpend = DB::table('sales')
            ->where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->whereMonth('sale_date', now()->month)
            ->whereYear('sale_date', now()->year)
            ->sum('grand_total');

        $totalSales = DB::table('sales')
            ->where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->count();

        $totalSaleAmount = DB::table('sales')
            ->where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->sum('grand_total');

        $recentSales = Sale::with(['saleDetails.item'])
            ->where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        // Backward compatibility: map $member to $wallet for existing views
        $wallet = $member;
        $minPurchase = $settings ? (float) $settings->membership_amount : 0;
        $profitPercentage = $settings ? (float) $settings->profit_share_percentage : 0;

        return view('businessclub::wallets.profile', compact(
            'customer', 'member', 'wallet', 'transactions', 'recentProfitShares',
            'totalSales', 'totalSaleAmount', 'monthlySpend',
            'minPurchase', 'profitPercentage', 'recentSales', 'settings'
        ));
    }

    /**
     * Get wallet/earned balance for a customer (API endpoint for POS).
     */
    public function getBalance(int $customerId)
    {
        $companyId = session('company.company_id');
        $member = $this->businessClubService->getMember($customerId, $companyId);

        if (!$member) {
            return response()->json([
                'is_member' => false,
                'balance' => 0,
                'earned_balance' => 0,
                'locked_balance' => 0,
            ]);
        }

        return response()->json([
            'is_member' => true,
            'balance' => (float) $member->earned_balance,
            'earned_balance' => (float) $member->earned_balance,
            'locked_balance' => (float) $member->locked_balance,
            'member_id' => $member->member_id,
        ]);
    }
}
