<?php

namespace Modules\BusinessClub\Services;

use Modules\BusinessClub\Models\BusinessClubSetting;
use Modules\BusinessClub\Models\BusinessClubMember;
use Modules\BusinessClub\Models\BusinessClubTransaction;
use Illuminate\Support\Facades\DB;

class BusinessClubService
{
    /**
     * Get business club settings for a company.
     */
    public function getSettings(int $companyId): ?BusinessClubSetting
    {
        return BusinessClubSetting::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->first();
    }

    /**
     * Save/update business club settings.
     */
    public function saveSettings(array $data, int $companyId): BusinessClubSetting
    {
        return BusinessClubSetting::updateOrCreate(
            ['company_id' => $companyId],
            [
                'membership_amount' => $data['membership_amount'] ?? 10000,
                'profit_share_percentage' => $data['profit_share_percentage'] ?? 50,
                'redemption_day' => $data['redemption_day'] ?? 1,
                'is_active' => $data['is_active'] ?? true,
                'company_name' => $data['company_name'] ?? null,
                'business_partner_name' => $data['business_partner_name'] ?? null,
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'logo' => $data['logo'] ?? null,
                'del_status' => 'Live',
            ]
        );
    }

    /**
     * Register a new member in the business club.
     * Creates member record, sets locked_balance = membership_amount, generates member_id.
     */
    public function registerMember(int $customerId, int $companyId): BusinessClubMember
    {
        $settings = $this->getSettings($companyId);
        if (!$settings) {
            throw new \Exception('Business Club settings not configured.');
        }

        if (!$settings->is_active) {
            throw new \Exception('Business Club is not active.');
        }

        // Check if already a member
        $existing = BusinessClubMember::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->first();

        if ($existing) {
            throw new \Exception('Customer is already a Business Club member.');
        }

        $membershipAmount = (float) $settings->membership_amount;
        $memberId = $this->generateMemberId($companyId);

        $member = DB::transaction(function () use ($customerId, $companyId, $membershipAmount, $memberId) {
            $member = BusinessClubMember::create([
                'member_id' => $memberId,
                'customer_id' => $customerId,
                'company_id' => $companyId,
                'membership_amount' => $membershipAmount,
                'locked_balance' => $membershipAmount,
                'earned_balance' => 0,
                'total_earned' => 0,
                'total_redeemed' => 0,
                'status' => 'active',
                'joined_at' => now(),
                'del_status' => 'Live',
            ]);

            // Record membership deposit transaction
            BusinessClubTransaction::create([
                'member_id' => $member->id,
                'customer_id' => $customerId,
                'sale_id' => null,
                'type' => 'membership_deposit',
                'amount' => $membershipAmount,
                'balance_before' => 0,
                'balance_after' => 0, // earned_balance remains 0; locked_balance is separate
                'description' => 'Membership deposit - Amount locked in wallet (Member ID: ' . $memberId . ')',
                'transaction_date' => now()->toDateString(),
                'company_id' => $companyId,
                'del_status' => 'Live',
            ]);

            return $member;
        });

        return $member;
    }

    /**
     * Generate next member ID in format BC-001, BC-002, etc.
     */
    public function generateMemberId(int $companyId): string
    {
        $lastMember = BusinessClubMember::where('company_id', $companyId)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastMember && preg_match('/BC-(\d+)/', $lastMember->member_id, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return 'BC-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Check if a customer is a Business Club member.
     */
    public function isMember(int $customerId, int $companyId): bool
    {
        return BusinessClubMember::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Get member record for a customer.
     */
    public function getMember(int $customerId, int $companyId): ?BusinessClubMember
    {
        return BusinessClubMember::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('status', 'active')
            ->first();
    }

    /**
     * Credit profit share to member's earned_balance from a sale.
     * Calculates profit per item: (sale_price - purchase_price) * qty
     * Then credits profit_share_percentage% of total profit to earned_balance.
     *
     * @param int $customerId
     * @param \Illuminate\Support\Collection|array $saleItems - collection of sale detail items with menu_price_with_discount, purchase_price, qty
     * @param int $saleId
     * @param int $companyId
     * @return BusinessClubTransaction|null
     */
    public function creditProfitFromSale(int $customerId, $saleItems, int $saleId, int $companyId, float $grandTotal = 0): ?BusinessClubTransaction
    {
        $member = $this->getMember($customerId, $companyId);
        if (!$member) {
            return null;
        }

        $settings = $this->getSettings($companyId);
        if (!$settings || !$settings->is_active) {
            return null;
        }

        $profitSharePercentage = (float) $settings->profit_share_percentage;
        if ($profitSharePercentage <= 0) {
            return null;
        }

        // RULE: Monthly shopping cap = deposit amount
        // Deposit 10k → profit only on first 10k worth of monthly bills
        // After 10k total bills in month, no more profit on further bills
        // If a bill partially exceeds cap, only the portion within cap earns profit
        $depositAmount = (float) $member->membership_amount;

        // Get total bill amount already processed this month (from sales)
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $billedThisMonth = (float) DB::table('sales')
            ->where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->whereBetween('sale_date', [$monthStart, $monthEnd])
            ->where('id', '!=', $saleId) // exclude current sale
            ->sum('grand_total');

        // If already billed >= deposit this month, no profit
        if ($billedThisMonth >= $depositAmount) {
            return null;
        }

        // How much of this bill qualifies for profit
        $remainingCap = $depositAmount - $billedThisMonth;
        $qualifyingAmount = min($grandTotal, $remainingCap);

        if ($qualifyingAmount <= 0) {
            return null;
        }

        // Calculate profit only on qualifying portion
        // If full bill qualifies, use full item profits
        // If partial, scale profit proportionally
        $totalProfit = 0;
        foreach ($saleItems as $item) {
            $salePrice = (float) ($item->menu_price_with_discount ?? $item->sale_price ?? 0);
            $purchasePrice = (float) ($item->purchase_price ?? 0);
            $qty = (float) ($item->qty ?? 0);
            $itemProfit = ($salePrice - $purchasePrice) * $qty;
            if ($itemProfit > 0) {
                $totalProfit += $itemProfit;
            }
        }

        if ($totalProfit <= 0) {
            return null;
        }

        // If only partial bill qualifies, scale profit proportionally
        if ($qualifyingAmount < $grandTotal && $grandTotal > 0) {
            $totalProfit = $totalProfit * ($qualifyingAmount / $grandTotal);
        }

        $creditAmount = round($totalProfit * ($profitSharePercentage / 100), 2);
        if ($creditAmount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($member, $customerId, $saleId, $companyId, $creditAmount, $totalProfit, $profitSharePercentage) {
            $balanceBefore = (float) $member->earned_balance;

            $member->earned_balance = $balanceBefore + $creditAmount;
            $member->total_earned = (float) $member->total_earned + $creditAmount;
            $member->save();

            return BusinessClubTransaction::create([
                'member_id' => $member->id,
                'customer_id' => $customerId,
                'sale_id' => $saleId,
                'type' => 'profit_credit',
                'amount' => $creditAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $member->earned_balance,
                'description' => 'Profit share from Sale #' . $saleId . ' (Profit: ' . number_format($totalProfit, 2) . ' × ' . $profitSharePercentage . '%)',
                'transaction_date' => now()->toDateString(),
                'company_id' => $companyId,
                'del_status' => 'Live',
            ]);
        });
    }

    /**
     * Redeem earned profit (by cheque).
     * Deducts from earned_balance, records redemption transaction.
     */
    public function redeemProfit(int $customerId, float $amount, int $companyId): BusinessClubTransaction
    {
        $member = $this->getMember($customerId, $companyId);
        if (!$member) {
            throw new \Exception('Customer is not a Business Club member.');
        }

        if ($amount <= 0) {
            throw new \Exception('Redemption amount must be greater than zero.');
        }

        if ($amount > (float) $member->earned_balance) {
            throw new \Exception('Insufficient earned balance. Available: ₹' . number_format($member->earned_balance, 2));
        }

        return DB::transaction(function () use ($member, $customerId, $amount, $companyId) {
            $balanceBefore = (float) $member->earned_balance;

            $member->earned_balance = $balanceBefore - $amount;
            $member->total_redeemed = (float) $member->total_redeemed + $amount;
            $member->save();

            return BusinessClubTransaction::create([
                'member_id' => $member->id,
                'customer_id' => $customerId,
                'sale_id' => null,
                'type' => 'redemption',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $member->earned_balance,
                'description' => 'Profit redeemed by cheque - ₹' . number_format($amount, 2),
                'transaction_date' => now()->toDateString(),
                'company_id' => $companyId,
                'del_status' => 'Live',
            ]);
        });
    }

    /**
     * Dashboard stats for Business Club.
     */
    public function getMemberDashboard(int $companyId): array
    {
        $totalMembers = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('status', 'active')
            ->count();

        $totalLockedAmount = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('status', 'active')
            ->sum('locked_balance');

        $totalEarned = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->sum('total_earned');

        $totalRedeemed = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->sum('total_redeemed');

        $totalPendingBalance = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('status', 'active')
            ->sum('earned_balance');

        $thisMonthEarnings = BusinessClubTransaction::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('type', 'profit_credit')
            ->whereMonth('transaction_date', now()->month)
            ->whereYear('transaction_date', now()->year)
            ->sum('amount');

        $thisMonthRedemptions = BusinessClubTransaction::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('type', 'redemption')
            ->whereMonth('transaction_date', now()->month)
            ->whereYear('transaction_date', now()->year)
            ->sum('amount');

        $recentTransactions = BusinessClubTransaction::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        return [
            'total_members' => $totalMembers,
            'total_locked_amount' => (float) $totalLockedAmount,
            'total_earned' => (float) $totalEarned,
            'total_redeemed' => (float) $totalRedeemed,
            'total_pending_balance' => (float) $totalPendingBalance,
            'this_month_earnings' => (float) $thisMonthEarnings,
            'this_month_redemptions' => (float) $thisMonthRedemptions,
            'recent_transactions' => $recentTransactions,
        ];
    }

    /**
     * Get members list for DataTable (server-side pagination).
     */
    public function getMembersList(int $companyId, ?string $search = '', int $start = 0, int $length = 10): array
    {
        $baseQuery = BusinessClubMember::where('business_club_members.company_id', $companyId)
            ->where('business_club_members.del_status', 'Live')
            ->join('customers', 'customers.id', '=', 'business_club_members.customer_id');

        $recordsTotal = (clone $baseQuery)->count();

        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('customers.name', 'like', '%' . $search . '%')
                  ->orWhere('customers.phone', 'like', '%' . $search . '%')
                  ->orWhere('business_club_members.member_id', 'like', '%' . $search . '%');
            });
        }

        $filteredCount = (clone $baseQuery)->count();

        $data = $baseQuery
            ->select(
                'business_club_members.id',
                'business_club_members.member_id',
                'business_club_members.customer_id',
                'customers.name as customer_name',
                'customers.phone as customer_phone',
                'business_club_members.membership_amount',
                'business_club_members.locked_balance',
                'business_club_members.earned_balance',
                'business_club_members.total_earned',
                'business_club_members.total_redeemed',
                'business_club_members.status',
                'business_club_members.joined_at'
            )
            ->orderBy('business_club_members.id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'data' => $data,
        ];
    }

    /**
     * Get transactions for a specific member (server-side pagination).
     */
    public function getMemberTransactions(int $memberId, int $companyId, int $start = 0, int $length = 10): array
    {
        $baseQuery = BusinessClubTransaction::where('member_id', $memberId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live');

        $recordsTotal = (clone $baseQuery)->count();
        $filteredCount = $recordsTotal;

        $data = (clone $baseQuery)
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'data' => $data,
        ];
    }

    /**
     * Alias used by SyncController::businessClubFallbackCredit().
     *
     * SyncController already calculates total profit from sale_details before
     * calling this — so we accept pre-computed totalProfit directly instead of
     * iterating over saleItems again.
     *
     * Also enforces the monthly shopping cap (deposit_amount) identical to
     * creditProfitFromSale() so offline-sync and web-POS behaviour match.
     *
     * @param int   $customerId
     * @param float $totalProfit  Pre-calculated profit (sum of (sale_price - purchase_price) * qty)
     * @param int   $saleId
     * @param int   $companyId
     */
    public function creditWalletFromSale(int $customerId, float $totalProfit, int $saleId, int $companyId): ?BusinessClubTransaction
    {
        $member = $this->getMember($customerId, $companyId);
        if (!$member) {
            return null;
        }

        $settings = $this->getSettings($companyId);
        if (!$settings || !$settings->is_active) {
            return null;
        }

        $profitSharePercentage = (float) $settings->profit_share_percentage;
        if ($profitSharePercentage <= 0 || $totalProfit <= 0) {
            return null;
        }

        // ── Monthly cap: deposit_amount se zyada billing pe profit nahi ──────────
        $depositAmount  = (float) $member->membership_amount;
        $monthStart     = now()->startOfMonth()->toDateString();
        $monthEnd       = now()->endOfMonth()->toDateString();

        $sale = DB::table('sales')
            ->where('id', $saleId)
            ->where('company_id', $companyId)
            ->first(['grand_total', 'sale_date']);

        $grandTotal = $sale ? (float) $sale->grand_total : 0;

        $billedThisMonth = (float) DB::table('sales')
            ->where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->whereBetween('sale_date', [$monthStart, $monthEnd])
            ->where('id', '!=', $saleId)
            ->sum('grand_total');

        if ($billedThisMonth >= $depositAmount) {
            return null; // Cap already reached
        }

        $remainingCap     = $depositAmount - $billedThisMonth;
        $qualifyingAmount = min($grandTotal, $remainingCap);

        if ($qualifyingAmount <= 0) {
            return null;
        }

        // Scale profit if only partial bill qualifies
        if ($grandTotal > 0 && $qualifyingAmount < $grandTotal) {
            $totalProfit = $totalProfit * ($qualifyingAmount / $grandTotal);
        }

        $creditAmount = round($totalProfit * ($profitSharePercentage / 100), 2);
        if ($creditAmount <= 0) {
            return null;
        }

        // ── Duplicate guard: ek sale ke liye sirf ek profit_credit ──────────────
        $alreadyCredited = BusinessClubTransaction::where('sale_id', $saleId)
            ->where('customer_id', $customerId)
            ->where('type', 'profit_credit')
            ->where('company_id', $companyId)
            ->exists();

        if ($alreadyCredited) {
            return null;
        }

        return DB::transaction(function () use ($member, $customerId, $saleId, $companyId, $creditAmount, $totalProfit, $profitSharePercentage) {
            $balanceBefore = (float) $member->earned_balance;

            $member->earned_balance = $balanceBefore + $creditAmount;
            $member->total_earned   = (float) $member->total_earned + $creditAmount;
            $member->save();

            return BusinessClubTransaction::create([
                'member_id'        => $member->id,
                'customer_id'      => $customerId,
                'sale_id'          => $saleId,
                'type'             => 'profit_credit',
                'amount'           => $creditAmount,
                'balance_before'   => $balanceBefore,
                'balance_after'    => $member->earned_balance,
                'description'      => 'Profit share from Sale #' . $saleId
                                    . ' (Profit: ' . number_format($totalProfit, 2)
                                    . ' × ' . $profitSharePercentage . '%)',
                'transaction_date' => now()->toDateString(),
                'company_id'       => $companyId,
                'del_status'       => 'Live',
            ]);
        });
    }
}
