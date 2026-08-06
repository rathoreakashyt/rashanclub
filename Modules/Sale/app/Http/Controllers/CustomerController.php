<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Sale\Http\Request\CustomerRequest;
use Modules\Sale\Services\CustomerService;

class CustomerController extends Controller
{
    protected $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            $length = request()->length ?? 10;
            $start = request()->start ?? 0;
            $search = request()->search['value'] ?? '';
            $draw = request()->draw;
            $typeFilter = request()->type_filter;

            return response()->json(
                $this->customerService->getDataTableData($start, $length, $search, $draw, $typeFilter)
            );
        }

        return view('sale::customer.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $states = \Modules\Configuration\Models\State::orderBy('state_code')->get();
        return view('sale::customer.create', compact('states'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CustomerRequest $request)
    {
        try {
            $this->customerService->createCustomer($request->validated());

            return redirect()->route('customer.index')
                ->with('success', 'Customer Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('customer.create')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $customer = $this->customerService->getCustomerByEncryptedId($id);

            if (!$customer) {
                return redirect()->route('customer.index')
                    ->with('error', 'Customer not found');
            }

            // Prevent editing "Walk-in Customer"
            if ($customer->name === 'Walk-in Customer') {
                return redirect()->route('customer.index')
                    ->with('error', '"Walk-in Customer" cannot be edited.');
            }

            $states = \Modules\Configuration\Models\State::orderBy('state_code')->get();
            return view('sale::customer.create', compact('customer', 'states'));
        } catch (\Exception $e) {
            return redirect()->route('customer.index')
                ->with('error', 'Customer not found');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CustomerRequest $request, string $id)
    {
        try {
            $customer = $this->customerService->getCustomerByEncryptedId($id);

            if (!$customer) {
                return redirect()->route('customer.index')
                    ->with('error', 'Customer not found');
            }

            $this->customerService->updateCustomer($customer, $request->validated());

            return redirect()->route('customer.index')
                ->with('success', 'Customer Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('customer.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $customer = $this->customerService->getCustomerByEncryptedId($id);

            if (!$customer) {
                return redirect()->route('customer.index')
                    ->with('error', 'Customer not found');
            }

            // Prevent deletion of "Walk-in Customer"
            if ($customer->name === 'Walk-in Customer') {
                return redirect()->route('customer.index')
                    ->with('error', '"Walk-in Customer" cannot be deleted.');
            }

            $this->customerService->deleteCustomer($customer);

            return redirect()->route('customer.index')
                ->with('success', 'Customer Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('customer.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Store customer via AJAX for POS
     */
    public function posStore(CustomerRequest $request)
    {
        try {
            $customer = $this->customerService->createCustomer($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Customer Created Successfully',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Update customer via AJAX for POS
     */
    public function posUpdate(CustomerRequest $request, string $id)
    {
        try {
            // POS route uses regular ID, not encrypted ID
            $customer = $this->customerService->getCustomerById($id);

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found'
                ], 404);
            }

            $this->customerService->updateCustomer($customer, $request->validated());
            $customer->refresh();

            return response()->json([
                'status' => 'success',
                'message' => 'Customer Updated Successfully',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get customer data for POS edit
     */
    public function posGetCustomer(string $id)
    {
        try {
            $customer = $this->customerService->getCustomerById($id);

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'customer' => [
                    'id' => $customer->id,
                    'encrypted_id' => $customer->encrypted_id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email ?? '',
                    'opening_balance' => $customer->opening_balance ?? 0,
                    'opening_balance_type' => $customer->opening_balance_type ?? 'Debit',
                    'credit_limit' => $customer->credit_limit ?? 0,
                    'discount' => $customer->discount ?? 0,
                    'customer_type' => $customer->customer_type ?? '',
                    'date_of_birth' => $customer->date_of_birth ?? '',
                    'date_of_anniversary' => $customer->date_of_anniversary ?? '',
                    'gst_number' => $customer->gst_number ?? '',
                    'same_or_diff_state' => $customer->same_or_diff_state ?? '',
                    'state_id' => $customer->state_id ?? '',
                    'business_type' => $customer->business_type ?? 'B2C',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found'
            ], 404);
        }
    }

    /**
     * Get latest customers for POS dropdown.
     * Supports search param: when provided, searches by name/phone/email and returns matching customers.
     */
    public function posGetCustomers(\Illuminate\Http\Request $request)
    {
        try {
            $query = \Modules\Sale\Models\Customer::where('del_status', 'Live')
                ->where('company_id', session('company.company_id'));

            $search = trim($request->input('search', ''));
            $includeId = $request->input('include_id');

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $customers = $query
                ->orderByRaw("CASE WHEN name = 'Walk-in Customer' THEN 0 ELSE 1 END")
                ->orderBy('id', 'desc')
                ->limit(50)
                ->get();

            // Ensure a specific customer is included (e.g. after adding new customer or when selected)
            if ($includeId) {
                $includeCustomer = \Modules\Sale\Models\Customer::where('id', (int) $includeId)
                    ->where('company_id', session('company.company_id'))
                    ->where('del_status', 'Live')
                    ->first();
                if ($includeCustomer && ! $customers->contains('id', $includeCustomer->id)) {
                    $customers->prepend($includeCustomer);
                }
            }

            // Transform to include encrypted_id
            $customersData = $customers->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'encrypted_id' => $customer->encrypted_id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ];
            });

            return response()->json([
                'status' => 'success',
                'customers' => $customersData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer credit info for POS (credit_limit, current_due, available_credit).
     * Used to validate if customer can have more due amount.
     */
    public function posGetCustomerCreditInfo(string $id)
    {
        try {
            $customer = $this->customerService->getCustomerById($id);

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found',
                ], 404);
            }

            $creditLimit = (float) ($customer->credit_limit ?? 0);
            $currentDue = 0;

            if ($creditLimit > 0 && $customer->name !== 'Walk-in Customer') {
                $outletId = session('outlet.outlet_id') ?? session('outlet.id') ?? null;
                $currentDue = $this->customerService->getCustomerDue($customer->id, $outletId);
                $currentDue = max(0, $currentDue); // Only positive due counts against credit
            }

            $availableCredit = max(0, $creditLimit - $currentDue);

            return response()->json([
                'status' => 'success',
                'credit_limit' => $creditLimit,
                'current_due' => $currentDue,
                'available_credit' => $availableCredit,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer current balance (using encrypted ID)
     */
    public function getBalance(string $id)
    {
        try {
            $customer = $this->customerService->getCustomerByEncryptedId($id);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            $outletId = session('outlet.id');
            $balance = $this->customerService->getCustomerDue($customer->id, $outletId);
            $balanceType = $balance >= 0 ? 'Debit' : 'Credit';
            $balanceAmount = abs($balance);

            return response()->json([
                'success' => true,
                'data' => [
                    'balance' => $balance,
                    'balance_amount' => $balanceAmount,
                    'balance_type' => $balanceType,
                    'formatted_balance' => formatAmount($balanceAmount) . ' (' . $balanceType . ')'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer current balance by regular ID (for AJAX calls from forms)
     */
    public function getBalanceById(int $id)
    {
        try {
            $customer = $this->customerService->getCustomerById($id);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            $outletId = session('outlet.id');
            $balance = $this->customerService->getCustomerDue($customer->id, $outletId);
            $balanceType = $balance >= 0 ? 'Debit' : 'Credit';
            $balanceAmount = abs($balance);

            return response()->json([
                'success' => true,
                'data' => [
                    'balance' => $balance,
                    'balance_amount' => $balanceAmount,
                    'balance_type' => $balanceType,
                    'formatted_balance' => formatAmount($balanceAmount) . ' (' . $balanceType . ')'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
