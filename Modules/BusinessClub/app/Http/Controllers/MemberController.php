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

class MemberController extends Controller
{
    protected $businessClubService;

    public function __construct(BusinessClubService $businessClubService)
    {
        $this->businessClubService = $businessClubService;
    }

    /**
     * Display the members list page.
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
     * Register a new member.
     */
    public function register(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
        ]);

        $companyId = session('company.company_id');
        $customerId = (int) $request->input('customer_id');

        try {
            $member = $this->businessClubService->registerMember($customerId, $companyId);

            return response()->json([
                'status' => 'success',
                'message' => 'Member registered successfully. Member ID: ' . $member->member_id,
                'member' => [
                    'id' => $member->id,
                    'member_id' => $member->member_id,
                    'customer_id' => $member->customer_id,
                    'membership_amount' => $member->membership_amount,
                    'locked_balance' => $member->locked_balance,
                    'joined_at' => $member->joined_at->format('d-m-Y H:i'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Redeem earned profit by cheque.
     */
    public function redeem(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $companyId = session('company.company_id');
        $customerId = (int) $request->input('customer_id');
        $amount = (float) $request->input('amount');

        try {
            $transaction = $this->businessClubService->redeemProfit($customerId, $amount, $companyId);

            return response()->json([
                'status' => 'success',
                'message' => 'Profit redeemed successfully. Amount: ₹' . number_format($amount, 2),
                'transaction' => [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'balance_after' => $transaction->balance_after,
                    'date' => $transaction->transaction_date->format('d-m-Y'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get member transactions (AJAX).
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
     * Get member profile page.
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

        // Map variables for backward compatibility with existing profile view
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
     * Check if customer is a BC member (API for POS).
     */
    public function checkMembership(int $customerId)
    {
        $companyId = session('company.company_id');
        $isMember = $this->businessClubService->isMember($customerId, $companyId);
        $member = null;

        if ($isMember) {
            $member = $this->businessClubService->getMember($customerId, $companyId);
        }

        return response()->json([
            'is_member' => $isMember,
            'member' => $member ? [
                'id' => $member->id,
                'member_id' => $member->member_id,
                'locked_balance' => (float) $member->locked_balance,
                'earned_balance' => (float) $member->earned_balance,
                'total_earned' => (float) $member->total_earned,
                'total_redeemed' => (float) $member->total_redeemed,
                'joined_at' => $member->joined_at ? $member->joined_at->format('d-m-Y') : null,
            ] : null,
        ]);
    }

    /**
     * Get earned balance (for POS/API).
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

    /**
     * Generate printable ID card for a BC member.
     */
    public function idCard(int $customerId)
    {
        $companyId = session('company.company_id');

        $member = BusinessClubMember::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->first();

        if (!$member) {
            abort(404, 'Member not found');
        }

        $customer = Customer::where('id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->firstOrFail();

        $settings = $this->businessClubService->getSettings($companyId);
        $companyName = $settings->company_name ?? session('company.company_name') ?? 'Business Club';
        $clubPhone = $settings->phone ?? session('company.phone') ?? '';
        $clubAddress = $settings->address ?? session('company.address') ?? '';

        $html = $this->buildIdCardHtml($member, $customer, $companyName, $clubPhone, $clubAddress);

        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Build HTML for printable ID card.
     */
    private function buildIdCardHtml($member, $customer, $companyName, $clubPhone, $clubAddress): string
    {
        $memberId = e($member->member_id);
        $customerName = e($customer->name);
        $customerPhone = e($customer->phone ?? '-');
        $customerAddress = e($customer->address ?? '-');
        $joinedAt = $member->joined_at ? $member->joined_at->format('d-M-Y') : '-';
        $membershipAmount = number_format($member->membership_amount, 0);
        $companyName = e($companyName);
        $clubPhone = e($clubPhone);
        $clubAddress = e($clubAddress);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Business Club ID Card - {$memberId}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; padding: 20px; }
    .card-container { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; }
    .id-card {
        width: 340px; height: 210px; border-radius: 12px; padding: 16px;
        position: relative; overflow: hidden; color: #fff;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    }
    .card-front {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5986 50%, #1e3a5f 100%);
    }
    .card-back {
        background: linear-gradient(135deg, #2d5986 0%, #1e3a5f 50%, #2d5986 100%);
    }
    .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
    .club-name { font-size: 14px; font-weight: 700; letter-spacing: 0.5px; }
    .club-subtitle { font-size: 9px; opacity: 0.7; margin-top: 2px; }
    .member-badge {
        background: #ffd700; color: #1e3a5f; padding: 3px 8px; border-radius: 4px;
        font-size: 10px; font-weight: 700;
    }
    .member-info { margin-top: 8px; }
    .member-name { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
    .member-detail { font-size: 10px; opacity: 0.8; margin-bottom: 2px; }
    .card-footer {
        position: absolute; bottom: 12px; left: 16px; right: 16px;
        display: flex; justify-content: space-between; align-items: flex-end;
    }
    .member-id-display { font-size: 13px; font-weight: 700; letter-spacing: 1px; color: #ffd700; }
    .join-date { font-size: 9px; opacity: 0.7; }
    .deposit-badge {
        background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 4px;
        font-size: 9px; text-align: center;
    }
    .deposit-amount { font-size: 12px; font-weight: 700; color: #ffd700; }
    .back-info { font-size: 10px; line-height: 1.6; margin-top: 10px; }
    .back-label { font-weight: 700; opacity: 0.9; }
    .terms { position: absolute; bottom: 12px; left: 16px; right: 16px; font-size: 8px; opacity: 0.6; line-height: 1.4; }
    .print-btn {
        display: block; margin: 20px auto; padding: 10px 30px; background: #1e3a5f;
        color: #fff; border: none; border-radius: 6px; font-size: 14px; cursor: pointer;
    }
    .print-btn:hover { background: #2d5986; }
    @media print {
        body { background: #fff; padding: 0; }
        .print-btn { display: none; }
        .card-container { gap: 10px; }
    }
</style>
</head>
<body>
<div class="card-container">
    <!-- FRONT -->
    <div class="id-card card-front">
        <div class="card-header">
            <div>
                <div class="club-name">{$companyName}</div>
                <div class="club-subtitle">BUSINESS CLUB MEMBER</div>
            </div>
            <div class="member-badge">MEMBER</div>
        </div>
        <div class="member-info">
            <div class="member-name">{$customerName}</div>
            <div class="member-detail">📞 {$customerPhone}</div>
        </div>
        <div class="card-footer">
            <div>
                <div class="member-id-display">{$memberId}</div>
                <div class="join-date">Member since: {$joinedAt}</div>
            </div>
            <div class="deposit-badge">
                <div>Deposit</div>
                <div class="deposit-amount">₹{$membershipAmount}</div>
            </div>
        </div>
    </div>

    <!-- BACK -->
    <div class="id-card card-back">
        <div class="card-header">
            <div class="club-name">{$companyName}</div>
        </div>
        <div class="back-info">
            <p><span class="back-label">Address:</span> {$clubAddress}</p>
            <p><span class="back-label">Contact:</span> {$clubPhone}</p>
            <p style="margin-top:8px;"><span class="back-label">Member:</span> {$customerName}</p>
            <p><span class="back-label">ID:</span> {$memberId}</p>
        </div>
        <div class="terms">
            This card is non-transferable. The locked deposit amount cannot be withdrawn.<br>
            Earned profit is redeemable monthly by cheque at the store.
        </div>
    </div>
</div>
<button class="print-btn" onclick="window.print()">🖨️ Print ID Card</button>
</body>
</html>
HTML;
    }
}
