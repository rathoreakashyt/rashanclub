<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BusinessClubMember;
use App\Models\BusinessClubSetting;
use App\Models\BusinessClubTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sale\Models\Customer;

class BusinessClubController extends Controller
{
    private function companyId(): int
    {
        return (int) session('company.company_id', 1);
    }

    public function dashboard()
    {
        $companyId = $this->companyId();

        $totalActiveMembers = BusinessClubMember::where('company_id', $companyId)
            ->where('status', 'active')
            ->where('del_status', 'Live')
            ->count();

        $totalBalanceHeld = BusinessClubMember::where('company_id', $companyId)
            ->where('status', 'active')
            ->where('del_status', 'Live')
            ->sum('earned_balance');

        $totalEarned = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->sum('total_earned');

        $totalRedeemed = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->sum('total_redeemed');

        $recentTransactions = DB::table('business_club_transactions as t')
            ->leftJoin('customers as c', 'c.id', '=', 't.customer_id')
            ->leftJoin('sales as s', 's.id', '=', 't.sale_id')
            ->select('t.*', 'c.name as customer_name', 's.grand_total as sale_amount')
            ->where('t.company_id', $companyId)
            ->where('t.del_status', 'Live')
            ->orderBy('t.id', 'desc')
            ->limit(15)
            ->get();

        $topEarners = BusinessClubMember::where('company_id', $companyId)
            ->where('status', 'active')
            ->where('del_status', 'Live')
            ->with('customer:id,name,phone')
            ->orderBy('earned_balance', 'desc')
            ->limit(5)
            ->get();

        $settings = BusinessClubSetting::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->first();

        return view('backend.business-club.dashboard', compact(
            'totalActiveMembers',
            'totalBalanceHeld',
            'totalEarned',
            'totalRedeemed',
            'recentTransactions',
            'topEarners',
            'settings'
        ));
    }

    public function settings()
    {
        $companyId = $this->companyId();

        $settings = BusinessClubSetting::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->first();

        if (!$settings) {
            $settings = new BusinessClubSetting([
                'company_id' => $companyId,
                'del_status' => 'Live',
            ]);
        }

        return view('backend.business-club.settings', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        $companyId = $this->companyId();

        $data = $request->only([
            'company_name',
            'business_partner_name',
            'phone',
            'email',
            'address',
            'profit_percentage',
            'profit_share_percentage',
            'redemption_day',
            'min_purchase_amount',
            'membership_amount',
            'minimum_bill_amount',
        ]);

        $data['redemption_date'] = $request->input('redemption_day', $request->input('redemption_date', 1));
        $data['company_id'] = $companyId;
        $data['del_status'] = 'Live';
        $data['is_active'] = $request->boolean('is_active') ? 1 : 0;

        BusinessClubSetting::updateOrCreate(
            ['company_id' => $companyId],
            $data
        );

        return redirect()->route('businessclub.settings')
            ->with('success', 'Business Club settings saved successfully.');
    }

    public function wallets()
    {
        $companyId = $this->companyId();

        $members = BusinessClubMember::where('business_club_members.company_id', $companyId)
            ->where('business_club_members.del_status', 'Live')
            ->with('customer:id,name,phone')
            ->orderBy('business_club_members.id', 'desc')
            ->get();

        return view('backend.business-club.wallets', compact('members'));
    }

    public function register()
    {
        $companyId = $this->companyId();

        $customers = Customer::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('name')
            ->get();

        return view('backend.business-club.register', compact('customers'));
    }

    public function storeMember(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:10|regex:/^[0-9]+$/',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'membership_amount' => 'required|numeric|min:0',
        ]);

        $companyId = $this->companyId();
        $phone = trim($request->input('phone'));
        $name = trim($request->input('name'));
        $email = trim($request->input('email')) ?: null;
        $membershipAmount = (float) $request->input('membership_amount');

        // 1) Existing customer by phone? (company-scoped)
        $customer = Customer::where('phone', $phone)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->first();

        // 2) Nahi mila → naya customer banao (name/email phone se)
        if (!$customer) {
            $customer = Customer::create([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'company_id' => $companyId,
                'del_status' => 'Live',
            ]);
        } else {
            // Existing customer — naam/email refresh kar do
            $customer->update([
                'name' => $name,
                'email' => $email ?? $customer->email,
            ]);
        }

        $customerId = (int) $customer->id;

        $alreadyMember = BusinessClubMember::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('del_status', 'Live')
            ->first();

        if ($alreadyMember) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'This customer is already an active Business Club member.');
        }

        $memberId = $this->generateMemberId($companyId);

        DB::transaction(function () use ($companyId, $customerId, $membershipAmount, $memberId) {
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

            BusinessClubTransaction::create([
                'member_id' => $member->id,
                'customer_id' => $customerId,
                'sale_id' => null,
                'type' => 'membership_deposit',
                'amount' => $membershipAmount,
                'balance_before' => 0,
                'balance_after' => $membershipAmount,
                'description' => 'Membership registration',
                'transaction_date' => now()->toDateString(),
                'company_id' => $companyId,
                'del_status' => 'Live',
            ]);
        });

        return redirect()->route('businessclub.wallets')
            ->with('success', 'Member registered successfully. Member ID: ' . $memberId);
    }

    public function redeem(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'amount'      => 'required|numeric|gt:0',
        ]);

        $companyId  = $this->companyId();
        $customerId = (int) $request->input('customer_id');
        $amount     = (float) $request->input('amount');

        $member = BusinessClubMember::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('del_status', 'Live')
            ->first();

        if (!$member) {
            return redirect()->back()->with('error', 'Customer is not an active Business Club member.');
        }

        $earnedBalance = (float) $member->earned_balance;

        if ($amount > $earnedBalance) {
            return redirect()->back()
                ->with('error', 'Insufficient earned balance. Available: ₹' . number_format($earnedBalance, 2));
        }

        // ── Monthly redemption cap check ─────────────────────────────────────────
        $settings = \App\Models\BusinessClubSetting::where('company_id', $companyId)
            ->where('del_status', 'Live')->first();

        // Optional: agar redemption_day set hai aur aaj woh din nahi → warning only (block nahi)

        DB::transaction(function () use ($member, $customerId, $amount, $companyId, $earnedBalance) {
            $newBalance = $earnedBalance - $amount;

            $member->earned_balance  = $newBalance;
            $member->total_redeemed  = (float) $member->total_redeemed + $amount;
            $member->save();

            BusinessClubTransaction::create([
                'member_id'        => $member->id,
                'customer_id'      => $customerId,
                'sale_id'          => null,
                'type'             => 'redemption',
                'amount'           => $amount,
                'balance_before'   => $earnedBalance,
                'balance_after'    => $newBalance,
                'description'      => 'Profit redeemed - ₹' . number_format($amount, 2),
                'transaction_date' => now()->toDateString(),
                'company_id'       => $companyId,
                'del_status'       => 'Live',
            ]);

            // ── customer_wallets sync: balance ko proportionally reduce karo ──────
            // (balance = 0 galat tha — partial redeem ke baad baki balance rahna chahiye)
            DB::table('customer_wallets')
                ->where('customer_id', $customerId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->update([
                    'balance'        => DB::raw('GREATEST(0, IFNULL(balance, 0) - ' . (float) $amount . ')'),
                    'total_redeemed' => DB::raw('IFNULL(total_redeemed, 0) + ' . (float) $amount),
                    'updated_at'     => now(),
                ]);
        });

        return redirect()->back()->with('success', 'Profit redeemed successfully. Amount: ' . number_format($amount, 2));
    }

    public function idCard($id)
    {
        $companyId = $this->companyId();

        $member = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                    ->orWhere('customer_id', $id)
                    ->orWhere('member_id', $id);
            })
            ->first();

        if (!$member) {
            abort(404, 'Member not found');
        }

        $customer = Customer::where('id', $member->customer_id)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->first();

        $settings = BusinessClubSetting::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->first();

        return view('backend.business-club.id-card', compact('member', 'customer', 'settings'));
    }

    private function generateMemberId(int $companyId): string
    {
        $sequence = BusinessClubMember::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->count();

        do {
            $sequence++;
            $candidate = 'BC-' . str_pad($sequence, 5, '0', STR_PAD_LEFT);
        } while (BusinessClubMember::where('member_id', $candidate)->exists());

        return $candidate;
    }
}