<?php

namespace Modules\BusinessClub\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\BusinessClub\Services\BusinessClubService;
use Modules\BusinessClub\Models\CustomerWallet;
use Modules\BusinessClub\Models\WalletTransaction;
use Modules\Sale\Models\Customer;
use Modules\Sale\Models\Sale;

class WalletController extends Controller
{
    protected $businessClubService;

    public function __construct(BusinessClubService $businessClubService)
    {
        $this->businessClubService = $businessClubService;
    }

    public function index(Request $request)
    {
        $companyId = session('company.company_id');

        if ($request->ajax()) {
            $draw = (int) $request->input('draw', 1);
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $search = $request->input('search.value', '') ?? '';

            $result = $this->businessClubService->getWalletReport($companyId, $search, $start, $length);

            $data = $result['data']->map(function ($row) use ($start) {
                static $index = 0;
                $index++;
                return [
                    'id' => $row->id,
                    'customer_id' => $row->customer_id,
                    'sn' => $start + $index,
                    'customer_name' => $row->customer_name ?? 'N/A',
                    'customer_phone' => $row->customer_phone ?? '-',
                    'total_earned' => number_format($row->total_earned, 2),
                    'balance' => number_format($row->balance, 2),
                    'total_redeemed' => number_format($row->total_redeemed, 2),
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

    public function transactions(Request $request, int $walletId)
    {
        $companyId = session('company.company_id');

        $result = $this->businessClubService->getTransactions($walletId, $companyId, 0, 100);

        return response()->json([
            'data' => $result['data']->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'type' => ucfirst($tx->type),
                    'amount' => number_format($tx->amount, 2),
                    'balance_before' => number_format($tx->balance_before, 2),
                    'balance_after' => number_format($tx->balance_after, 2),
                    'description' => $tx->description,
                    'date' => $tx->transaction_date->format('d-m-Y'),
                ];
            }),
        ]);
    }

    public function profile(int $customerId)
    {
        $companyId = session('company.company_id');

        $customer = Customer::where('id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->firstOrFail();

        $wallet = CustomerWallet::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->first();

        $settings = \Modules\BusinessClub\Models\BusinessClubSetting::where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->first();

        $transactions = WalletTransaction::with('sale')
            ->where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('id', 'desc')
            ->take(50)
            ->get();

        $recentProfitShares = WalletTransaction::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('type', 'credit')
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

        $minPurchase = $settings ? (float) $settings->min_purchase_amount : 0;
        $profitPercentage = $settings ? (float) $settings->profit_percentage : 0;

        $recentSales = Sale::with(['saleDetails.item'])
            ->where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        return view('businessclub::wallets.profile', compact(
            'customer', 'wallet', 'transactions', 'recentProfitShares',
            'totalSales', 'totalSaleAmount', 'monthlySpend',
            'minPurchase', 'profitPercentage', 'recentSales'
        ));
    }

    public function getBalance(int $customerId)
    {
        $companyId = session('company.company_id');
        $balance = $this->businessClubService->getWalletBalance($customerId, $companyId);
        return response()->json(['balance' => $balance]);
    }
}
