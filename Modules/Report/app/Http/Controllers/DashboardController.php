<?php

namespace Modules\Report\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Sale\Models\Sale;
use Modules\Stock\Models\Item;
use Modules\Stock\Models\Damage;
use Modules\Sale\Models\Customer;
use Illuminate\Support\Facades\DB;
use Modules\Sale\Models\SaleDetail;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Accounting\Models\Income;
use Modules\Purchase\Models\Purchase;
use Modules\Accounting\Models\Expense;
use Modules\Configuration\Models\Outlet;
use Modules\Sale\Models\CustomerReceive;
use Modules\Purchase\Models\SupplierPayment;

class DashboardController extends Controller
{

    /**
     * dashboard
     */
    public function dashboard()
    {
        return view('backend.dashboard.dashboard');
    }
    
    /**
     * user home
     */
    public function userHome()
    {
        return view('backend.home.home');
    }

    /**
     * Display the dashboard.
     */
    public function index(Request $request)
    {
        $companyId = session('company.company_id');
        
        // Get filter parameters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $outletId = $request->get('outlet_id');
        
        // Get outlets for dropdown
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('outlet_name')
            ->get();
        
        // Get current date and calculate date ranges
        $today = Carbon::today();
        
        // If filters are provided, use them; otherwise use default ranges
        if ($dateFrom && $dateTo) {
            $filterStart = Carbon::parse($dateFrom);
            $filterEnd = Carbon::parse($dateTo);
            $currentMonthStart = $filterStart;
            $currentMonthEnd = $filterEnd;
            $lastMonthStart = $filterStart->copy()->subDays($filterStart->diffInDays($filterEnd) + 1);
            $lastMonthEnd = $filterStart->copy()->subDay();
        } else {
            $currentMonthStart = $today->copy()->startOfMonth();
            $currentMonthEnd = $today->copy()->endOfMonth();
            $lastMonthStart = $today->copy()->subMonth()->startOfMonth();
            $lastMonthEnd = $today->copy()->subMonth()->endOfMonth();
        }
        
        $currentYearStart = $today->copy()->startOfYear();
        
        // Helper function to apply filters to sales
        $applySaleFilters = function($query) use ($companyId, $dateFrom, $dateTo, $outletId) {
            $query->where('del_status', 'Live')
                ->where('company_id', $companyId);
            
            if ($dateFrom) {
                $query->whereDate('sale_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('sale_date', '<=', $dateTo);
            }
            if ($outletId) {
                $query->where('outlet_id', $outletId);
            }
            
            return $query;
        };
        
        // Statistics - Filtered data
        $totalSalesCount = $applySaleFilters(Sale::query())->count();
        
        $totalSalesAmount = $applySaleFilters(Sale::query())->sum('total_payable');
        
        $totalCustomers = Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->count();
        
        $totalProducts = Item::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('type', '!=', '0')
            ->count();
        
        // Profit - Calculate from sales (revenue) minus expenses (use filtered period)
        $periodSales = Sale::where('del_status', 'Live')
            ->where('company_id', $companyId);
        if ($dateFrom) {
            $periodSales->whereDate('sale_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $periodSales->whereDate('sale_date', '<=', $dateTo);
        }
        if ($outletId) {
            $periodSales->where('outlet_id', $outletId);
        }
        $periodSalesAmount = $periodSales->sum('total_payable');
        
        $periodExpenses = Expense::where('del_status', 'Live')
            ->where('company_id', $companyId);
        if ($dateFrom) {
            $periodExpenses->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $periodExpenses->whereDate('date', '<=', $dateTo);
        }
        $periodExpensesAmount = $periodExpenses->sum('amount');
        
        $lastMonthProfit = $periodSalesAmount - $periodExpensesAmount;
        $lastMonthSales = $periodSalesAmount;
        
        // Revenue Report - Last 12 months or based on date range
        $revenueData = [];
        if ($dateFrom && $dateTo) {
            // If date range is provided, show monthly breakdown within that range
            $startDate = Carbon::parse($dateFrom);
            $endDate = Carbon::parse($dateTo);
            $current = $startDate->copy()->startOfMonth();
            
            while ($current <= $endDate) {
                $monthStart = $current->copy()->startOfMonth();
                $monthEnd = $current->copy()->endOfMonth();
                if ($monthEnd > $endDate) {
                    $monthEnd = $endDate;
                }
                if ($monthStart < $startDate) {
                    $monthStart = $startDate;
                }
                
                $monthRevenue = Sale::where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->whereBetween('sale_date', [$monthStart, $monthEnd]);
                if ($outletId) {
                    $monthRevenue->where('outlet_id', $outletId);
                }
                $monthRevenue = $monthRevenue->sum('total_payable');
                
                $revenueData[] = [
                    'month' => $current->format('M'),
                    'revenue' => floatval($monthRevenue)
                ];
                
                $current->addMonth();
            }
        } else {
            // Default: Last 12 months
            for ($i = 11; $i >= 0; $i--) {
                $monthStart = $today->copy()->subMonths($i)->startOfMonth();
                $monthEnd = $today->copy()->subMonths($i)->endOfMonth();
                $monthName = $monthStart->format('M');
                
                $monthRevenue = Sale::where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->whereBetween('sale_date', [$monthStart, $monthEnd]);
                if ($outletId) {
                    $monthRevenue->where('outlet_id', $outletId);
                }
                $monthRevenue = $monthRevenue->sum('total_payable');
                
                $revenueData[] = [
                    'month' => $monthName,
                    'revenue' => floatval($monthRevenue)
                ];
            }
        }
        
        // Earning Reports - Filtered period
        $periodIncome = Income::where('del_status', 'Live')
            ->where('company_id', $companyId);
        if ($dateFrom) {
            $periodIncome->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $periodIncome->whereDate('date', '<=', $dateTo);
        }
        $periodIncomeAmount = $periodIncome->sum('amount');
        
        $periodSalesForIncome = Sale::where('del_status', 'Live')
            ->where('company_id', $companyId);
        if ($dateFrom) {
            $periodSalesForIncome->whereDate('sale_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $periodSalesForIncome->whereDate('sale_date', '<=', $dateTo);
        }
        if ($outletId) {
            $periodSalesForIncome->where('outlet_id', $outletId);
        }
        $periodSalesForIncomeAmount = $periodSalesForIncome->sum('total_payable');
        
        $totalIncome = $periodSalesForIncomeAmount + $periodIncomeAmount;
        $totalExpenses = $periodExpensesAmount;
        $netProfit = $totalIncome - $totalExpenses;
        
        // Previous period for comparison (same duration before the filter period)
        if ($dateFrom && $dateTo) {
            $startDate = Carbon::parse($dateFrom);
            $endDate = Carbon::parse($dateTo);
            $duration = $startDate->diffInDays($endDate);
            $previousStart = $startDate->copy()->subDays($duration + 1);
            $previousEnd = $startDate->copy()->subDay();
        } else {
            $previousStart = $lastMonthStart->copy()->subMonth()->startOfMonth();
            $previousEnd = $lastMonthStart->copy()->subMonth()->endOfMonth();
        }
        
        $previousPeriodSales = Sale::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->whereBetween('sale_date', [$previousStart, $previousEnd]);
        if ($outletId) {
            $previousPeriodSales->where('outlet_id', $outletId);
        }
        $previousPeriodSalesAmount = $previousPeriodSales->sum('total_payable');
        
        $previousPeriodIncome = Income::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->whereBetween('date', [$previousStart, $previousEnd])
            ->sum('amount');
        
        $previousPeriodExpenses = Expense::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->whereBetween('date', [$previousStart, $previousEnd])
            ->sum('amount');
        
        $previousPeriodTotalIncome = $previousPeriodSalesAmount + $previousPeriodIncome;
        $previousPeriodTotalExpenses = $previousPeriodExpenses;
        $previousPeriodNetProfit = $previousPeriodTotalIncome - $previousPeriodTotalExpenses;
        
        // Calculate percentage changes
        $netProfitChange = $previousPeriodNetProfit != 0 
            ? (($netProfit - $previousPeriodNetProfit) / abs($previousPeriodNetProfit)) * 100 
            : 0;
        
        $totalIncomeChange = $previousPeriodTotalIncome > 0 
            ? (($totalIncome - $previousPeriodTotalIncome) / $previousPeriodTotalIncome) * 100 
            : 0;
        
        $totalExpensesChange = $previousPeriodTotalExpenses > 0 
            ? (($totalExpenses - $previousPeriodTotalExpenses) / $previousPeriodTotalExpenses) * 100 
            : 0;
        
        // Operational Comparison Data
        $operationalData = [];
        
        // Purchase
        $purchaseQuery = Purchase::where('del_status', 'Live')
            ->where('company_id', $companyId);
        if ($dateFrom) {
            $purchaseQuery->whereDate('purchase_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $purchaseQuery->whereDate('purchase_date', '<=', $dateTo);
        }
        if ($outletId) {
            $purchaseQuery->where('outlet_id', $outletId);
        }
        $operationalData['purchase'] = floatval($purchaseQuery->sum('grand_total'));
        
        // Sale
        $saleQuery = Sale::where('del_status', 'Live')
            ->where('company_id', $companyId);
        if ($dateFrom) {
            $saleQuery->whereDate('sale_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $saleQuery->whereDate('sale_date', '<=', $dateTo);
        }
        if ($outletId) {
            $saleQuery->where('outlet_id', $outletId);
        }
        $operationalData['sale'] = floatval($saleQuery->sum('total_payable'));
        
        // Damage
        $damageQuery = Damage::where('del_status', 'Live')
            ->where('company_id', $companyId);
        if ($dateFrom) {
            $damageQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $damageQuery->whereDate('date', '<=', $dateTo);
        }
        if ($outletId) {
            $damageQuery->where('outlet_id', $outletId);
        }
        $operationalData['damage'] = floatval($damageQuery->sum('total_loss'));
        
        // Expense
        $expenseQuery = Expense::where('del_status', 'Live')
            ->where('company_id', $companyId);
        if ($dateFrom) {
            $expenseQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $expenseQuery->whereDate('date', '<=', $dateTo);
        }
        $operationalData['expense'] = floatval($expenseQuery->sum('amount'));
        
        // Customer Receive
        $customerReceiveQuery = CustomerReceive::where('del_status', 'Live')
            ->where('company_id', $companyId);
        if ($dateFrom) {
            $customerReceiveQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $customerReceiveQuery->whereDate('date', '<=', $dateTo);
        }
        if ($outletId) {
            $customerReceiveQuery->where('outlet_id', $outletId);
        }
        $operationalData['customer_receive'] = floatval($customerReceiveQuery->sum('amount'));
        
        // Supplier Payment
        $supplierPaymentQuery = SupplierPayment::where('del_status', 'Live')
            ->where('company_id', $companyId);
        if ($dateFrom) {
            $supplierPaymentQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $supplierPaymentQuery->whereDate('date', '<=', $dateTo);
        }
        if ($outletId) {
            $supplierPaymentQuery->where('outlet_id', $outletId);
        }
        $operationalData['supplier_payment'] = floatval($supplierPaymentQuery->sum('amount'));
        
        // Popular Products - Top 6 by total quantity sold
        $popularProducts = SaleDetail::with(['item'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('item_id', DB::raw('SUM(qty) as total_qty'), DB::raw('SUM(qty * menu_price_with_discount) as total_revenue'))
            ->groupBy('item_id')
            ->orderBy('total_qty', 'desc')
            ->limit(6)
            ->get()
            ->map(function($detail) {
                return [
                    'item' => $detail->item,
                    'total_qty' => floatval($detail->total_qty),
                    'total_revenue' => floatval($detail->total_revenue),
                    'item_code' => $detail->item ? ($detail->item->code ?? 'N/A') : 'N/A'
                ];
            });
        
        // Recent Transactions - Last 7 transactions (Sales, Expenses, Incomes)
        $recentTransactions = collect();
        
        // Recent Sales
        $recentSales = Sale::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(function($sale) {
                return [
                    'type' => 'Sale',
                    'title' => 'Sale',
                    'description' => 'Sale Invoice: ' . ($sale->sale_no ?? '-'),
                    'amount' => floatval($sale->total_payable ?? 0),
                    'is_positive' => true,
                    'icon' => 'tabler-currency-dollar',
                    'badge_class' => 'bg-label-success',
                    'text_class' => 'text-success',
                    'date' => $sale->created_at
                ];
            });
        
        // Recent Expenses
        $recentExpenses = Expense::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(function($expense) {
                return [
                    'type' => 'Expense',
                    'title' => 'Expense',
                    'description' => ($expense->note ?? 'Expense') . ' - ' . ($expense->reference_no ?? '-'),
                    'amount' => floatval($expense->amount ?? 0),
                    'is_positive' => false,
                    'icon' => 'tabler-credit-card',
                    'badge_class' => 'bg-label-danger',
                    'text_class' => 'text-danger',
                    'date' => $expense->created_at
                ];
            });
        
        // Recent Incomes
        $recentIncomes = Income::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(function($income) {
                return [
                    'type' => 'Income',
                    'title' => 'Income',
                    'description' => ($income->note ?? 'Income') . ' - ' . ($income->reference_no ?? '-'),
                    'amount' => floatval($income->amount ?? 0),
                    'is_positive' => true,
                    'icon' => 'tabler-browser-check',
                    'badge_class' => 'bg-label-success',
                    'text_class' => 'text-success',
                    'date' => $income->created_at
                ];
            });
        
        // Combine and sort by date (use concat to avoid merge() expecting Eloquent models with getKey())
        $recentTransactions = collect()
            ->concat($recentSales)
            ->concat($recentExpenses)
            ->concat($recentIncomes)
            ->sortByDesc('date')
            ->take(7)
            ->values();
        
        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => [
                        'totalSalesCount' => $totalSalesCount,
                        'totalSalesAmount' => floatval($totalSalesAmount),
                        'totalCustomers' => $totalCustomers,
                        'totalProducts' => $totalProducts,
                    ],
                    'profit' => [
                        'amount' => floatval($lastMonthProfit),
                        'percentage' => $lastMonthSales > 0 ? (($lastMonthProfit / $lastMonthSales) * 100) : 0,
                        'sales' => floatval($lastMonthSales),
                    ],
                    'revenue' => $revenueData,
                    'earning' => [
                        'netProfit' => floatval($netProfit),
                        'netProfitChange' => $netProfitChange,
                        'totalIncome' => floatval($totalIncome),
                        'totalIncomeChange' => $totalIncomeChange,
                        'totalExpenses' => floatval($totalExpenses),
                        'totalExpensesChange' => $totalExpensesChange,
                    ],
                    'operational' => $operationalData,
                ]
            ]);
        }

        // Pass data to view
        return view('report::dashboard.dashboard', compact(
            'totalSalesCount',
            'totalSalesAmount',
            'totalCustomers',
            'totalProducts',
            'lastMonthProfit',
            'lastMonthSales',
            'revenueData',
            'netProfit',
            'netProfitChange',
            'totalIncome',
            'totalIncomeChange',
            'totalExpenses',
            'totalExpensesChange',
            'popularProducts',
            'recentTransactions',
            'outlets',
            'operationalData'
        ));
    }


    /**
     * Get authenticated user's sales for datatable
     */
    public function getUserSales(Request $request)
    {
        if (!$request->ajax()) {
            return response()->json(['error' => 'Invalid request'], 400);
        }

        $userId = Auth::id();
        $companyId = session('company.company_id');
        $outletId = session('outlet.outlet_id');

        $length = $request->input('length', 10);
        $start = $request->input('start', 0);
        $search = $request->input('search.value', '');

        $query = Sale::with(['customer', 'employee'])
            ->withCount('saleDetails as total_items')
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->orderBy('id', 'desc');

        // Filter by outlet if set
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        // Search functionality
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('sale_no', 'like', "%{$search}%")
                  ->orWhere('sale_date', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Get total count
        $recordsTotal = Sale::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('user_id', $userId);
        
        if ($outletId) {
            $recordsTotal->where('outlet_id', $outletId);
        }
        $recordsTotal = $recordsTotal->count();

        $filteredCount = $query->count();

        // Get paginated results
        $sales = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $data = $sales->map(function ($sale, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'sale_no' => $sale->sale_no,
                // add customer phone if exists
                'customer_name' => $sale->customer ? $sale->customer->name . ($sale->customer->phone ? ' (' . $sale->customer->phone . ')' : '') : 'Walk-in Customer',
                'total_items' => $sale->total_items ?? 0,
                'total_payable' => number_format($sale->total_payable ?? 0, 2),
                'paid_amount' => number_format($sale->paid_amount ?? 0, 2),
                'sale_date' => $sale->sale_date ? date('Y/m/d', strtotime($sale->sale_date)) : '-',
                'encrypted_id' => encrypt($sale->id),
                'actual_id' => $sale->id
            ];
        });

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $data
        ]);
    }

    /**
     * Get this week statistics
     */
    public function getThisWeekStatistics()
    {
        $userId = Auth::id();
        $companyId = session('company.company_id');
        $outletId = session('outlet.outlet_id');

        // Get this week date range
        $thisWeekStart = now()->startOfWeek();
        $thisWeekEnd = now()->endOfWeek();
        $lastWeekStart = now()->subWeek()->startOfWeek();
        $lastWeekEnd = now()->subWeek()->endOfWeek();

        // This Week Sales (count) - using sale_date
        $thisWeekSales = Sale::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereBetween('sale_date', [$thisWeekStart->toDateString(), $thisWeekEnd->toDateString()]);
        if ($outletId) {
            $thisWeekSales->where('outlet_id', $outletId);
        }
        $thisWeekSales = $thisWeekSales->count();
        
        $lastWeekSales = Sale::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereBetween('sale_date', [$lastWeekStart->toDateString(), $lastWeekEnd->toDateString()]);
        if ($outletId) {
            $lastWeekSales->where('outlet_id', $outletId);
        }
        $lastWeekSales = $lastWeekSales->count();
        
        $salesChange = $lastWeekSales > 0 
            ? round((($thisWeekSales - $lastWeekSales) / $lastWeekSales) * 100, 1)
            : ($thisWeekSales > 0 ? 100 : 0);

        // This Week Purchases (count) - using date
        $thisWeekPurchases = Purchase::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->whereBetween('date', [$thisWeekStart->toDateString(), $thisWeekEnd->toDateString()]);
        if ($outletId) {
            $thisWeekPurchases->where('outlet_id', $outletId);
        }
        $thisWeekPurchases = $thisWeekPurchases->count();
        
        $lastWeekPurchases = Purchase::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->whereBetween('date', [$lastWeekStart->toDateString(), $lastWeekEnd->toDateString()]);
        if ($outletId) {
            $lastWeekPurchases->where('outlet_id', $outletId);
        }
        $lastWeekPurchases = $lastWeekPurchases->count();
        
        $purchasesChange = $lastWeekPurchases > 0 
            ? round((($thisWeekPurchases - $lastWeekPurchases) / $lastWeekPurchases) * 100, 1)
            : ($thisWeekPurchases > 0 ? 100 : 0);

        // This Week Customer Receive (count) - using date
        $thisWeekCustomerReceive = CustomerReceive::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereBetween('date', [$thisWeekStart->toDateString(), $thisWeekEnd->toDateString()]);
        if ($outletId) {
            $thisWeekCustomerReceive->where('outlet_id', $outletId);
        }
        $thisWeekCustomerReceive = $thisWeekCustomerReceive->count();
        
        $lastWeekCustomerReceive = CustomerReceive::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereBetween('date', [$lastWeekStart->toDateString(), $lastWeekEnd->toDateString()]);
        if ($outletId) {
            $lastWeekCustomerReceive->where('outlet_id', $outletId);
        }
        $lastWeekCustomerReceive = $lastWeekCustomerReceive->count();
        
        $customerReceiveChange = $lastWeekCustomerReceive > 0 
            ? round((($thisWeekCustomerReceive - $lastWeekCustomerReceive) / $lastWeekCustomerReceive) * 100, 1)
            : ($thisWeekCustomerReceive > 0 ? 100 : 0);

        // This Week Supplier Payment (count) - using date
        $thisWeekSupplierPayment = SupplierPayment::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->whereBetween('date', [$thisWeekStart->toDateString(), $thisWeekEnd->toDateString()]);
        if ($outletId) {
            $thisWeekSupplierPayment->where('outlet_id', $outletId);
        }
        $thisWeekSupplierPayment = $thisWeekSupplierPayment->count();
        
        $lastWeekSupplierPayment = SupplierPayment::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->whereBetween('date', [$lastWeekStart->toDateString(), $lastWeekEnd->toDateString()]);
        if ($outletId) {
            $lastWeekSupplierPayment->where('outlet_id', $outletId);
        }
        $lastWeekSupplierPayment = $lastWeekSupplierPayment->count();
        
        $supplierPaymentChange = $lastWeekSupplierPayment > 0 
            ? round((($thisWeekSupplierPayment - $lastWeekSupplierPayment) / $lastWeekSupplierPayment) * 100, 1)
            : ($thisWeekSupplierPayment > 0 ? 100 : 0);

        return response()->json([
            'status' => 'success',
            'data' => [
                'sales' => [
                    'count' => $thisWeekSales,
                    'change' => $salesChange,
                    'is_positive' => $salesChange >= 0
                ],
                'purchases' => [
                    'count' => $thisWeekPurchases,
                    'change' => $purchasesChange,
                    'is_positive' => $purchasesChange >= 0
                ],
                'customer_receive' => [
                    'count' => $thisWeekCustomerReceive,
                    'change' => $customerReceiveChange,
                    'is_positive' => $customerReceiveChange >= 0
                ],
                'supplier_payment' => [
                    'count' => $thisWeekSupplierPayment,
                    'change' => $supplierPaymentChange,
                    'is_positive' => $supplierPaymentChange >= 0
                ]
            ]
        ]);
    }
}
