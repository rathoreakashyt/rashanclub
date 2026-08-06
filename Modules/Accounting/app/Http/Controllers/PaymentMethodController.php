<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Accounting\Models\PaymentMethod;

class PaymentMethodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            $length = request()->length ?? 10;
            $start = request()->start ?? 0;
            $search = request()->search['value'] ?? '';
            $query = PaymentMethod::where('del_status', 'Live')
                    ->where('company_id', session('company.company_id'))
                    ->orderByRaw('COALESCE(sort_id, 999999) ASC')
                    ->orderBy('id', 'desc');
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('account_type', 'like', "%{$search}%");
                });
            }
            $recordsTotal = PaymentMethod::where('del_status', 'Live')
                    ->where('company_id', session('company.company_id'))
                    ->count();
            $filteredCount = $query->count();
            $payments = $query->orderByRaw('COALESCE(sort_id, 999999) ASC')
                          ->orderBy('id', 'desc')
                          ->skip($start)
                          ->take($length)
                          ->get();
            
            // Calculate the starting number for the current page
            $startingNumber = $filteredCount - $start;
            $transformedUsers = $payments->map(function ($payment, $index) use ($startingNumber) {
                return [
                    'id' => $startingNumber - $index, // Sequential number
                    'actual_id' => $payment->id,     // Keep actual ID
                    'name' => $payment->name,
                    'account_type' => $payment->account_type,
                    'status' => $payment->status,
                    'current_balance' => formatAmount($payment->current_balance),
                    'is_deletable' => $payment->is_deletable,
                    'encrypted_id' => $payment->encrypted_id
                ];
            });
            
            return response()->json([
                'draw' => request()->draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $filteredCount,
                'data' => $transformedUsers
            ]);
        }
        return view('accounting::payment-method.index');
    }
    

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('accounting::payment-method.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validationRules = [
            'name' => ['required', 'string', 'max:55'],
            'description' => ['nullable', 'string', 'max:255'],
            'current_balance' => ['nullable', 'max:999999999999999'],
            'account_type' => ['required', 'string', 'max:55'],
            'status' => ['required', 'string', 'max:55']
        ];
        $request->validate($validationRules);
        try {
            $storeData = [
                'name' => $request->name,
                'description' => $request->description,
                'current_balance' => $request->current_balance,
                'account_type' => $request->account_type,
                'status' => $request->status,
                'user_id' => Auth::user()->id,
                'company_id' => session('company.company_id'),
            ];
            
            // Handle configuration data if present - restructure by gateway
            if ($request->has('configuration') && is_array($request->configuration) && !empty(array_filter($request->configuration))) {
                $storeData['configuration'] = $this->restructureConfiguration($request->configuration, $request->account_type);
            } else {
                $storeData['configuration'] = null;
            }
            
            PaymentMethod::create($storeData);
            return redirect()->route('payment-method.index')
                ->with('success', 'Payment Method Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('payment-method.create')
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
        $decryptedId = decrypt($id);
        try {
            $data = [];
            $decryptedId = decrypt($id);
            $data['payment_method'] = PaymentMethod::findOrFail($decryptedId);
            return view('accounting::payment-method.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('payment-method.index')
                ->with('error', 'Payment Method not found');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $decryptedId = decrypt($id);
        try {
            $validationRules = [
                'name' => ['required', 'string', 'max:55'],
                'description' => ['nullable', 'string', 'max:255'],
                'current_balance' => ['nullable', 'max:999999999999999'],
                'account_type' => ['required', 'string', 'max:55'],
                'status' => ['required', 'string', 'min:0']
            ];
            $request->validate($validationRules);
            $paymentMethod = PaymentMethod::findOrFail($decryptedId);
            $updateData = [
                'name' => $request->name,
                'description' => $request->description,
                'current_balance' => $request->current_balance,
                'account_type' => $request->account_type,
                'status' => $request->status,
                'user_id' => Auth::user()->id,
                'company_id' => session('company.company_id'),
                'updated_at' => now()
            ];
            
            // Handle configuration data if present - restructure by gateway
            if ($request->has('configuration') && is_array($request->configuration) && !empty(array_filter($request->configuration))) {
                $updateData['configuration'] = $this->restructureConfiguration($request->configuration, $request->account_type);
            } else {
                // Clear configuration if account_type doesn't require it
                $updateData['configuration'] = null;
            }
            
            $paymentMethod->update($updateData);
            return redirect()->route('payment-method.index')
                ->with('success', 'Payment Method Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('payment-method.edit', encrypt($decryptedId))
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $decryptedId = decrypt($id);
        try {
            $payment_method = PaymentMethod::findOrFail($decryptedId);
            $payment_method->update(['del_status' => 'Deleted']);
            return redirect()->route('payment-method.index')
                ->with('success', 'Payment Method Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('payment-method.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Show sort payment method page
     */
    public function sortPaymentMethod()
    {
        try {
            $companyId = session('company.company_id');
            $paymentMethods = PaymentMethod::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->orderByRaw('COALESCE(sort_id, 999999) ASC')
                ->orderBy('id', 'ASC')
                ->get();
            
            return view('accounting::payment-method.sort-payment-method', compact('paymentMethods'));
        } catch (\Exception $e) {
            return redirect()->route('payment-method.index')
                ->with('error', 'Error loading sort page: ' . $e->getMessage());
        }
    }

    /**
     * Update payment method sort order
     */
    public function updateSortOrder()
    {
        try {
            $sortedIds = request()->input('sorted_ids', []);
            $companyId = session('company.company_id');
            
            if (empty($sortedIds)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No payment methods to sort'
                ], 400);
            }

            foreach ($sortedIds as $index => $paymentMethodId) {
                PaymentMethod::where('id', $paymentMethodId)
                    ->where('company_id', $companyId)
                    ->update(['sort_id' => $index + 1]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Payment method order updated successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restructure configuration from flat to nested by gateway
     * 
     * @param array $config Flat configuration array
     * @param string $accountType Account type (e.g., 'Stripe', 'Paypal')
     * @return array Nested configuration array
     */
    private function restructureConfiguration(array $config, string $accountType): array
    {
        // Map account type to gateway name (lowercase)
        $gatewayMap = [
            'Paypal' => 'paypal',
            'Stripe' => 'stripe',
            'Razorpay' => 'razorpay',
            'Paystack' => 'paystack',
            'Paytm' => 'paytm',
            'Flutterwave' => 'flutterwave',
            'SslCommerz' => 'sslcommerz',
            'Mollie' => 'mollie',
            'Senangpay' => 'senangpay',
            'Bkash' => 'bkash',
            'Mercadopago' => 'mercadopago',
            'Cashfree' => 'cashfree',
            'Payfast' => 'payfast',
            'Skrill' => 'skrill',
            'PhonePe' => 'phonepe',
            'Telr' => 'telr',
            'Iyzico' => 'iyzico',
            'Pesapal' => 'pesapal',
            'Midtrans' => 'midtrans',
            'MyFatoorah' => 'myfatoorah',
            'EasyPaisa' => 'easypaisa',
        ];

        $gatewayName = $gatewayMap[$accountType] ?? strtolower($accountType);
        
        // If configuration is already nested, return as is
        if (isset($config['gateway']) || (isset($config[$gatewayName]) && is_array($config[$gatewayName]))) {
            return $config;
        }

        // Extract gateway-specific keys and restructure
        $gatewayConfig = [];
        $gatewayPrefix = strtolower($gatewayName) . '_';
        
        foreach ($config as $key => $value) {
            if (strpos(strtolower($key), $gatewayPrefix) === 0) {
                // Remove gateway prefix from key
                $cleanKey = substr($key, strlen($gatewayPrefix));
                $gatewayConfig[$cleanKey] = $value;
            }
        }

        // Build nested structure
        $nestedConfig = [
            'gateway' => $gatewayName,
            $gatewayName => $gatewayConfig
        ];

        return $nestedConfig;
    }
}

