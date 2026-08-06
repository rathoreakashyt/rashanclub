<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Sale\Models\Register;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Configuration\Models\Counter;

class RegisterController extends Controller
{
    /**
     * Show the form for opening a register
     */
    public function create()
    {
        try {
            $companyId = session('company.company_id');
            $outletId = session('outlet.outlet_id');

            if (!$outletId) {
                return redirect()->route('outlet.index')->with('error', 'Please select an outlet first.');
            }

            // Get enabled payment methods excluding Loyalty Point
            $paymentMethods = PaymentMethod::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('status', 'Enable')
                ->where('account_type', '!=', 'Loyalty Point')
                ->orderBy('sort_id')
                ->get(['id', 'name', 'account_type', 'current_balance']);

            // Get counters for the outlet
            $counters = Counter::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('outlet_id', $outletId)
                ->get(['id', 'name']);

            return view('sale::register.create', [
                'payment_methods' => $paymentMethods,
                'counters' => $counters
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Check register status for the current user
     */
    public function checkStatus()
    {
        try {
            $userId = Auth::id();
            $outletId = session('outlet.outlet_id');
            $companyId = session('company.company_id');

            if (!$outletId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select an outlet first.'
                ], 400);
            }

            // Get the latest register record for this user
            $register = Register::getLatestRegister($userId, $outletId, $companyId);

            // Register is open only if:
            // 1. A record exists, AND
            // 2. The latest record has register_status = 1 (open)
            $registerOpen = $register && $register->register_status == 1;

            if ($registerOpen) {
                $printerSettings = null;
                if ($register->counter_id) {
                    $counter = Counter::with('printer')->find($register->counter_id);
                    if ($counter && $counter->printer) {
                        $p = $counter->printer;
                        $printerSettings = [
                            'invoice_print' => $p->invoice_print ?? 'browser_print',
                            'print_format' => $p->print_format_invoice ?? $p->print_format ?? 'A4 Print',
                            'print_server_url_invoice' => $p->print_server_url_invoice ?? null,
                        ];
                    }
                }
                return response()->json([
                    'success' => true,
                    'register_open' => true,
                    'register' => $register,
                    'printer_settings' => $printerSettings,
                ]);
            }

            // Register is closed if no record exists OR latest record has status = 2
            $message = !$register 
                ? 'Register is not opened yet. Please open a register.' 
                : 'Register is closed. Please open a register.';

            return response()->json([
                'success' => true,
                'register_open' => false,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment methods and counters for opening register
     */
    public function getFormData()
    {
        try {
            $companyId = session('company.company_id');
            $outletId = session('outlet.outlet_id');

            if (!$outletId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select an outlet first.'
                ], 400);
            }

            // Get enabled payment methods excluding Loyalty Point
            $paymentMethods = PaymentMethod::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('status', 'Enable')
                ->where('account_type', '!=', 'Loyalty Point')
                ->orderBy('sort_id')
                ->get(['id', 'name', 'account_type', 'current_balance']);

            // Get counters for the outlet
            $counters = Counter::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('outlet_id', $outletId)
                ->get(['id', 'name']);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_methods' => $paymentMethods,
                    'counters' => $counters
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
     * Open a register
     */
    public function open(Request $request)
    {
        try {
            $request->validate([
                'counter_id' => 'required|integer|exists:counters,id',
                'opening_balance' => 'nullable|array',
                'opening_details' => 'nullable|string',
            ]);

            $userId = Auth::id();
            $outletId = session('outlet.outlet_id');
            $companyId = session('company.company_id');

            if (!$outletId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select an outlet first.'
                ], 400);
            }

            // Use database transaction to prevent double entry
            return DB::transaction(function () use ($request, $userId, $outletId, $companyId) {
                // Check if there's already an open register for this user (double check within transaction)
                $latestRegister = Register::where('user_id', $userId)
                    ->where('outlet_id', $outletId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->where('register_status', 1)
                    ->lockForUpdate() // Lock the row to prevent concurrent inserts
                    ->first();

                // If latest register exists and is open (status = 1), prevent opening a new one
                if ($latestRegister) {
                    // show the exact counter name where he open the register
                    $counter = Counter::find($latestRegister->counter_id);
                    if ($counter) {
                        $counterName = $counter->name;
                    }
                    else{
                        $counterName = 'Unknown';
                    }

                    return response()->json([
                        'success' => false,
                        'message' => 'You already have an open register on counter: ' . $counterName . '.'
                    ], 400);
                }

                // Get all enabled payment methods excluding Loyalty Point
                $paymentMethods = PaymentMethod::where('del_status', 'Live')
                    ->where('company_id', $companyId)
                    ->where('status', 'Enable')
                    ->where('account_type', '!=', 'Loyalty Point')
                    ->orderBy('id')
                    ->get(['id', 'name']);

                // Prepare opening details - include ALL payment methods, even with 0 balance
                $openingDetails = [];
                $openingBalance = $request->opening_balance ?? [];
                $totalOpeningBalance = 0;

                foreach ($paymentMethods as $paymentMethod) {
                    $balance = isset($openingBalance[$paymentMethod->id]) 
                        ? floatval($openingBalance[$paymentMethod->id]) 
                        : 0;
                    
                    // Include all payment methods in opening_details, even if balance is 0
                    $openingDetails[] = $paymentMethod->id . '||' . $paymentMethod->name . '||' . $balance;
                    $totalOpeningBalance += $balance;
                }

                // Create register
                $register = Register::create([
                    'opening_balance' => $totalOpeningBalance,
                    'opening_details' => json_encode($openingDetails),
                    'opening_balance_date_time' => now()->format('Y-m-d H:i:s'),
                    'register_status' => 1, // Open
                    'counter_id' => $request->counter_id,
                    'user_id' => $userId,
                    'outlet_id' => $outletId,
                    'company_id' => $companyId,
                    'date' => now()->format('Y-m-d'),
                    'del_status' => 'Live',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Register opened successfully.',
                    'register' => $register
                ]);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Close a register
     */
    public function close(Request $request)
    {
        try {
            $userId = Auth::id();
            $outletId = session('outlet.outlet_id');
            $companyId = session('company.company_id');

            // Get the latest register record
            $register = Register::getLatestRegister($userId, $outletId, $companyId);

            // Check if register exists and is open
            if (!$register || $register->register_status != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'No open register found.'
                ], 404);
            }

            // Get opening date time
            $openingDateTime = $register->opening_balance_date_time 
                ? \Carbon\Carbon::parse($register->opening_balance_date_time)
                : \Carbon\Carbon::parse($register->created_at);
            
            $closingDateTime = now();
            $openingDate = $openingDateTime->format('Y-m-d');
            $closingDate = $closingDateTime->format('Y-m-d');

            // Calculate sale_paid_amount (sum of paid_amount from sales)
            // Filter by date_time or sale_date or created_at between opening and closing
            $salePaidAmount = \Modules\Sale\Models\Sale::where('user_id', $userId)
                ->where('outlet_id', $outletId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where(function($query) use ($openingDateTime, $closingDateTime, $openingDate, $closingDate) {
                    $query->where(function($q) use ($openingDateTime, $closingDateTime) {
                        // Filter by date_time if available
                        $q->whereNotNull('date_time')
                          ->whereBetween('date_time', [$openingDateTime, $closingDateTime]);
                    })->orWhere(function($q) use ($openingDate, $closingDate) {
                        // Filter by sale_date if date_time is null
                        $q->whereNull('date_time')
                          ->whereBetween('sale_date', [$openingDate, $closingDate]);
                    })->orWhere(function($q) use ($openingDateTime, $closingDateTime) {
                        // Fallback to created_at
                        $q->whereNull('date_time')
                          ->whereNull('sale_date')
                          ->whereBetween('created_at', [$openingDateTime, $closingDateTime]);
                    });
                })
                ->sum('paid_amount') ?? 0;

            // Calculate refund_amount (sum from sale_returns)
            $refundAmount = \Modules\Sale\Models\SaleReturn::where('user_id', $userId)
                ->where('outlet_id', $outletId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where(function($query) use ($openingDate, $closingDate, $openingDateTime, $closingDateTime) {
                    $query->whereBetween('date', [$openingDate, $closingDate])
                          ->orWhere(function($q) use ($openingDateTime, $closingDateTime) {
                              $q->whereNull('date')
                                ->whereBetween('created_at', [$openingDateTime, $closingDateTime]);
                          });
                })
                ->sum('total_return_amount') ?? 0;

            // Calculate customer_due_receive (sum from customer_receives)
            $customerDueReceive = \Modules\Sale\Models\CustomerReceive::where('user_id', $userId)
                ->where('outlet_id', $outletId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where(function($query) use ($openingDate, $closingDate, $openingDateTime, $closingDateTime) {
                    $query->whereBetween('date', [$openingDate, $closingDate])
                          ->orWhere(function($q) use ($openingDateTime, $closingDateTime) {
                              $q->whereNull('date')
                                ->whereBetween('created_at', [$openingDateTime, $closingDateTime]);
                          });
                })
                ->sum('amount') ?? 0;

            // Calculate payment_methods_sale (JSON with payment method totals)
            $paymentMethodsSale = [];
            $salePayments = \Modules\Sale\Models\SalePayment::whereHas('sale', function($query) use ($userId, $outletId, $companyId, $openingDateTime, $closingDateTime, $openingDate, $closingDate) {
                    $query->where('user_id', $userId)
                          ->where('outlet_id', $outletId)
                          ->where('company_id', $companyId)
                          ->where('del_status', 'Live')
                          ->where(function($q) use ($openingDateTime, $closingDateTime, $openingDate, $closingDate) {
                              $q->where(function($subQ) use ($openingDateTime, $closingDateTime) {
                                  $subQ->whereNotNull('date_time')
                                       ->whereBetween('date_time', [$openingDateTime, $closingDateTime]);
                              })->orWhere(function($subQ) use ($openingDate, $closingDate) {
                                  $subQ->whereNull('date_time')
                                       ->whereBetween('sale_date', [$openingDate, $closingDate]);
                              })->orWhere(function($subQ) use ($openingDateTime, $closingDateTime) {
                                  $subQ->whereNull('date_time')
                                       ->whereNull('sale_date')
                                       ->whereBetween('created_at', [$openingDateTime, $closingDateTime]);
                              });
                          });
                })
                ->where('del_status', 'Live')
                ->selectRaw('payment_id, SUM(amount) as total')
                ->groupBy('payment_id')
                ->get();

            foreach ($salePayments as $payment) {
                $paymentMethod = \Modules\Accounting\Models\PaymentMethod::find($payment->payment_id);
                if ($paymentMethod) {
                    $paymentMethodsSale[$paymentMethod->name] = floatval($payment->total ?? 0);
                }
            }

            // Calculate total_purchase
            $totalPurchase = \Modules\Purchase\Models\Purchase::where('user_id', $userId)
                ->where('outlet_id', $outletId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where(function($query) use ($openingDate, $closingDate, $openingDateTime, $closingDateTime) {
                    $query->whereBetween('date', [$openingDate, $closingDate])
                          ->orWhere(function($q) use ($openingDateTime, $closingDateTime) {
                              $q->whereNull('date')
                                ->whereBetween('created_at', [$openingDateTime, $closingDateTime]);
                          });
                })
                ->sum('grand_total') ?? 0;

            // Calculate total_purchase_return
            $totalPurchaseReturn = \Modules\Purchase\Models\PurchaseReturn::where('user_id', $userId)
                ->where('outlet_id', $outletId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where(function($query) use ($openingDate, $closingDate, $openingDateTime, $closingDateTime) {
                    $query->whereBetween('date', [$openingDate, $closingDate])
                          ->orWhere(function($q) use ($openingDateTime, $closingDateTime) {
                              $q->whereNull('date')
                                ->whereBetween('created_at', [$openingDateTime, $closingDateTime]);
                          });
                })
                ->sum('total_return_amount') ?? 0;

            // Calculate total_expense
            $totalExpense = \Modules\Accounting\Models\Expense::where('user_id', $userId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where(function($query) use ($openingDate, $closingDate, $openingDateTime, $closingDateTime) {
                    $query->whereBetween('date', [$openingDate, $closingDate])
                          ->orWhere(function($q) use ($openingDateTime, $closingDateTime) {
                              $q->whereNull('date')
                                ->whereBetween('created_at', [$openingDateTime, $closingDateTime]);
                          });
                })
                ->sum('amount') ?? 0;

            // Calculate total_downpayment (from installment sales)
            $totalDownpayment = \Modules\Sale\Models\InstallmentSale::where('user_id', $userId)
                ->where('outlet_id', $outletId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where(function($query) use ($openingDate, $closingDate, $openingDateTime, $closingDateTime) {
                    $query->whereBetween('date', [$openingDate, $closingDate])
                          ->orWhere(function($q) use ($openingDateTime, $closingDateTime) {
                              $q->whereNull('date')
                                ->whereBetween('created_at', [$openingDateTime, $closingDateTime]);
                          });
                })
                ->sum('down_payment') ?? 0;

            // // Calculate total_installmentcollection (from installment sale payments)
            // $totalInstallmentcollection = \Modules\Sale\Models\InstallmentSalePayment::whereHas('installmentSale', function($query) use ($userId, $outletId, $companyId) {
            //         $query->where('user_id', $userId)
            //               ->where('outlet_id', $outletId)
            //               ->where('company_id', $companyId)
            //               ->where('del_status', 'Live');
            //     })
            //     ->where('del_status', 'Live')
            //     ->where(function($query) use ($openingDate, $closingDate, $openingDateTime, $closingDateTime) {
            //         $query->whereBetween('date', [$openingDate, $closingDate])
            //               ->orWhere(function($q) use ($openingDateTime, $closingDateTime) {
            //                   $q->whereNull('date')
            //                     ->whereBetween('created_at', [$openingDateTime, $closingDateTime]);
            //               });
            //     })
            //     ->sum('amount') ?? 0;

            // Calculate total_due_payment (from supplier payments)
            $totalDuePayment = \Modules\Purchase\Models\SupplierPayment::where('user_id', $userId)
                ->where('outlet_id', $outletId)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where(function($query) use ($openingDate, $closingDate, $openingDateTime, $closingDateTime) {
                    $query->whereBetween('date', [$openingDate, $closingDate])
                          ->orWhere(function($q) use ($openingDateTime, $closingDateTime) {
                              $q->whereNull('date')
                                ->whereBetween('created_at', [$openingDateTime, $closingDateTime]);
                          });
                })
                ->sum('amount') ?? 0;

            // Calculate closing_balance (opening_balance + sale_paid_amount + customer_due_receive - refund_amount - total_expense - total_purchase + total_purchase_return)
            $closingBalance = ($register->opening_balance ?? 0) 
                + $salePaidAmount 
                + $customerDueReceive 
                - $refundAmount 
                - $totalExpense 
                - $totalPurchase 
                + $totalPurchaseReturn;

            // Update register to closed with all calculated totals
            $register->update([
                'register_status' => 2, // Closed
                'closing_balance_date_time' => $closingDateTime->format('Y-m-d H:i:s'),
                'closing_balance' => $closingBalance,
                'sale_paid_amount' => $salePaidAmount,
                'refund_amount' => $refundAmount,
                'customer_due_receive' => $customerDueReceive,
                'payment_methods_sale' => json_encode($paymentMethodsSale ?? []),
                'total_purchase' => $totalPurchase,
                'total_purchase_return' => $totalPurchaseReturn,
                'total_expense' => $totalExpense,
                'total_downpayment' => $totalDownpayment,
                // 'total_installmentcollection' => $totalInstallmentcollection,
                'total_servicing' => 0, // Set to 0 if not used
                'total_due_payment' => $totalDuePayment,
                'others_currency' => json_encode([]), // Can be populated if needed
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Register closed successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get register summary with payment method wise transactions
     */
    public function getRegisterSummary()
    {
        try {
            $userId = Auth::id();
            $outletId = session('outlet.outlet_id');
            $companyId = session('company.company_id');

            if (!$outletId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select an outlet first.'
                ], 400);
            }

            // Get the latest open register
            $register = Register::getLatestRegister($userId, $outletId, $companyId);

            if (!$register || $register->register_status != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'No open register found.'
                ], 404);
            }

            // Get opening date time
            $openingDateTime = $register->opening_balance_date_time 
                ? \Carbon\Carbon::parse($register->opening_balance_date_time)
                : \Carbon\Carbon::parse($register->created_at);
            
            $currentDateTime = now();
            $openingDate = $openingDateTime->format('Y-m-d');
            $currentDate = $currentDateTime->format('Y-m-d');

            // Get all payment methods
            $paymentMethods = PaymentMethod::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('status', 'Enable')
                ->where('account_type', '!=', 'Loyalty Point')
                ->orderBy('sort_id')
                ->get(['id', 'name', 'account_type']);

            // Parse opening details
            $openingDetails = [];
            if ($register->opening_details) {
                $openingDetailsArray = json_decode($register->opening_details, true);
                if (is_array($openingDetailsArray)) {
                    foreach ($openingDetailsArray as $detail) {
                        if (is_string($detail)) {
                            $parts = explode('||', $detail);
                            if (count($parts) >= 3) {
                                $openingDetails[$parts[0]] = [
                                    'name' => $parts[1],
                                    'amount' => floatval($parts[2])
                                ];
                            }
                        }
                    }
                }
            }

            // Initialize summary data structure
            $summary = [];
            $paymentMethodTotals = [];

            foreach ($paymentMethods as $paymentMethod) {
                $paymentMethodId = $paymentMethod->id;
                $paymentMethodName = $paymentMethod->name;
                
                $summary[$paymentMethodId] = [
                    'payment_method_id' => $paymentMethodId,
                    'payment_method_name' => $paymentMethodName,
                    'transactions' => []
                ];
                $paymentMethodTotals[$paymentMethodId] = 0;

                // 1. Opening Balance
                $openingBalance = isset($openingDetails[$paymentMethodId]) 
                    ? floatval($openingDetails[$paymentMethodId]['amount']) 
                    : 0;
                if ($openingBalance > 0) {
                    $summary[$paymentMethodId]['transactions'][] = [
                        'transaction_type' => 'Opening Balance',
                        'amount' => $openingBalance
                    ];
                    $paymentMethodTotals[$paymentMethodId] += $openingBalance;
                }

                // 2. Purchase (from purchase_payments)
                $purchaseAmount = \Modules\Purchase\Models\PurchasePayment::whereHas('purchase', function($query) use ($userId, $outletId, $companyId, $openingDateTime, $currentDateTime, $openingDate, $currentDate) {
                        $query->where('user_id', $userId)
                              ->where('outlet_id', $outletId)
                              ->where('company_id', $companyId)
                              ->where('del_status', 'Live')
                              ->where(function($q) use ($openingDateTime, $currentDateTime, $openingDate, $currentDate) {
                                  $q->whereBetween('date', [$openingDate, $currentDate])
                                    ->orWhere(function($subQ) use ($openingDateTime, $currentDateTime) {
                                        $subQ->whereNull('date')
                                            ->whereBetween('created_at', [$openingDateTime, $currentDateTime]);
                                    });
                              });
                    })
                    ->where('payment_id', $paymentMethodId)
                    ->where('del_status', 'Live')
                    ->sum('amount') ?? 0;

                if ($purchaseAmount > 0) {
                    $summary[$paymentMethodId]['transactions'][] = [
                        'transaction_type' => 'Purchase',
                        'amount' => -$purchaseAmount // Negative because it's an outflow
                    ];
                    $paymentMethodTotals[$paymentMethodId] -= $purchaseAmount;
                }

                // 3. Purchase Return (from purchase_returns)
                $purchaseReturnAmount = \Modules\Purchase\Models\PurchaseReturn::where('user_id', $userId)
                    ->where('outlet_id', $outletId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->where('payment_method_id', $paymentMethodId)
                    ->where(function($query) use ($openingDate, $currentDate, $openingDateTime, $currentDateTime) {
                        $query->whereBetween('date', [$openingDate, $currentDate])
                              ->orWhere(function($q) use ($openingDateTime, $currentDateTime) {
                                  $q->whereNull('date')
                                    ->whereBetween('created_at', [$openingDateTime, $currentDateTime]);
                              });
                    })
                    ->sum('total_return_amount') ?? 0;

                if ($purchaseReturnAmount > 0) {
                    $summary[$paymentMethodId]['transactions'][] = [
                        'transaction_type' => 'Purchase Return',
                        'amount' => $purchaseReturnAmount
                    ];
                    $paymentMethodTotals[$paymentMethodId] += $purchaseReturnAmount;
                }

                // 4. Supplier Payment (from supplier_payments)
                $supplierPaymentAmount = \Modules\Purchase\Models\SupplierPayment::where('user_id', $userId)
                    ->where('outlet_id', $outletId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->where('payment_method_id', $paymentMethodId)
                    ->where(function($query) use ($openingDate, $currentDate, $openingDateTime, $currentDateTime) {
                        $query->whereBetween('date', [$openingDate, $currentDate])
                              ->orWhere(function($q) use ($openingDateTime, $currentDateTime) {
                                  $q->whereNull('date')
                                    ->whereBetween('created_at', [$openingDateTime, $currentDateTime]);
                              });
                    })
                    ->sum('amount') ?? 0;

                if ($supplierPaymentAmount > 0) {
                    $summary[$paymentMethodId]['transactions'][] = [
                        'transaction_type' => 'Supplier Payment',
                        'amount' => -$supplierPaymentAmount
                    ];
                    $paymentMethodTotals[$paymentMethodId] -= $supplierPaymentAmount;
                }

                // 5. Sale (from sale_payments)
                $saleAmount = \Modules\Sale\Models\SalePayment::whereHas('sale', function($query) use ($userId, $outletId, $companyId, $openingDateTime, $currentDateTime, $openingDate, $currentDate) {
                        $query->where('user_id', $userId)
                              ->where('outlet_id', $outletId)
                              ->where('company_id', $companyId)
                              ->where('del_status', 'Live')
                              ->where(function($q) use ($openingDateTime, $currentDateTime, $openingDate, $currentDate) {
                                  $q->where(function($subQ) use ($openingDateTime, $currentDateTime) {
                                      $subQ->whereNotNull('date_time')
                                          ->whereBetween('date_time', [$openingDateTime, $currentDateTime]);
                                  })->orWhere(function($subQ) use ($openingDate, $currentDate) {
                                      $subQ->whereNull('date_time')
                                          ->whereBetween('sale_date', [$openingDate, $currentDate]);
                                  })->orWhere(function($subQ) use ($openingDateTime, $currentDateTime) {
                                      $subQ->whereNull('date_time')
                                          ->whereNull('sale_date')
                                          ->whereBetween('created_at', [$openingDateTime, $currentDateTime]);
                                  });
                              });
                    })
                    ->where('payment_id', $paymentMethodId)
                    ->where('del_status', 'Live')
                    ->sum('amount') ?? 0;

                if ($saleAmount > 0) {
                    $summary[$paymentMethodId]['transactions'][] = [
                        'transaction_type' => 'Sale',
                        'amount' => $saleAmount
                    ];
                    $paymentMethodTotals[$paymentMethodId] += $saleAmount;
                }

                // 6. Customer Due Receive (from customer_receives)
                $customerDueReceiveAmount = \Modules\Sale\Models\CustomerReceive::where('user_id', $userId)
                    ->where('outlet_id', $outletId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->where('payment_method_id', $paymentMethodId)
                    ->where(function($query) use ($openingDate, $currentDate, $openingDateTime, $currentDateTime) {
                        $query->whereBetween('date', [$openingDate, $currentDate])
                              ->orWhere(function($q) use ($openingDateTime, $currentDateTime) {
                                  $q->whereNull('date')
                                    ->whereBetween('created_at', [$openingDateTime, $currentDateTime]);
                              });
                    })
                    ->sum('amount') ?? 0;

                if ($customerDueReceiveAmount > 0) {
                    $summary[$paymentMethodId]['transactions'][] = [
                        'transaction_type' => 'Customer Due Receive',
                        'amount' => $customerDueReceiveAmount
                    ];
                    $paymentMethodTotals[$paymentMethodId] += $customerDueReceiveAmount;
                }

                // 7. Sale Return (from sale_returns)
                $saleReturnAmount = \Modules\Sale\Models\SaleReturn::where('user_id', $userId)
                    ->where('outlet_id', $outletId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->where('payment_method_id', $paymentMethodId)
                    ->where(function($query) use ($openingDate, $currentDate, $openingDateTime, $currentDateTime) {
                        $query->whereBetween('date', [$openingDate, $currentDate])
                              ->orWhere(function($q) use ($openingDateTime, $currentDateTime) {
                                  $q->whereNull('date')
                                    ->whereBetween('created_at', [$openingDateTime, $currentDateTime]);
                              });
                    })
                    ->sum('total_return_amount') ?? 0;

                if ($saleReturnAmount > 0) {
                    $summary[$paymentMethodId]['transactions'][] = [
                        'transaction_type' => 'Sale Return',
                        'amount' => -$saleReturnAmount
                    ];
                    $paymentMethodTotals[$paymentMethodId] -= $saleReturnAmount;
                }

                // 8. Down Payment (from installment_sales)
                $downPaymentAmount = \Modules\Sale\Models\InstallmentSale::where('user_id', $userId)
                    ->where('outlet_id', $outletId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->where('payment_method_id', $paymentMethodId)
                    ->where(function($query) use ($openingDate, $currentDate, $openingDateTime, $currentDateTime) {
                        $query->whereBetween('date', [$openingDate, $currentDate])
                              ->orWhere(function($q) use ($openingDateTime, $currentDateTime) {
                                  $q->whereNull('date')
                                    ->whereBetween('created_at', [$openingDateTime, $currentDateTime]);
                              });
                    })
                    ->sum('down_payment') ?? 0;

                if ($downPaymentAmount > 0) {
                    $summary[$paymentMethodId]['transactions'][] = [
                        'transaction_type' => 'Down Payment',
                        'amount' => $downPaymentAmount
                    ];
                    $paymentMethodTotals[$paymentMethodId] += $downPaymentAmount;
                }

                // 9. Installment Collection (from installment_sale_details where paid_status is Paid or Partial)
                $installmentCollectionAmount = \Modules\Sale\Models\InstallmentSaleDetail::whereHas('installmentSale', function($query) use ($userId, $outletId, $companyId) {
                        $query->where('user_id', $userId)
                              ->where('outlet_id', $outletId)
                              ->where('company_id', $companyId)
                              ->where('del_status', 'Live');
                    })
                    ->where('payment_method_id', $paymentMethodId)
                    ->where('del_status', 'Live')
                    ->whereIn('paid_status', ['Paid', 'Partial'])
                    ->where(function($query) use ($openingDate, $currentDate, $openingDateTime, $currentDateTime) {
                        $query->whereBetween('paid_date', [$openingDate, $currentDate])
                              ->orWhere(function($q) use ($openingDateTime, $currentDateTime) {
                                  $q->whereNull('paid_date')
                                    ->whereBetween('created_at', [$openingDateTime, $currentDateTime]);
                              });
                    })
                    ->sum('paid_amount') ?? 0;

                if ($installmentCollectionAmount > 0) {
                    $summary[$paymentMethodId]['transactions'][] = [
                        'transaction_type' => 'Installment Collection',
                        'amount' => $installmentCollectionAmount
                    ];
                    $paymentMethodTotals[$paymentMethodId] += $installmentCollectionAmount;
                }

                // 10. Expense (from expenses)
                $expenseAmount = \Modules\Accounting\Models\Expense::where('user_id', $userId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->where('payment_method_id', $paymentMethodId)
                    ->where(function($query) use ($openingDate, $currentDate, $openingDateTime, $currentDateTime) {
                        $query->whereBetween('date', [$openingDate, $currentDate])
                              ->orWhere(function($q) use ($openingDateTime, $currentDateTime) {
                                  $q->whereNull('date')
                                    ->whereBetween('created_at', [$openingDateTime, $currentDateTime]);
                              });
                    })
                    ->sum('amount') ?? 0;

                if ($expenseAmount > 0) {
                    $summary[$paymentMethodId]['transactions'][] = [
                        'transaction_type' => 'Expense',
                        'amount' => -$expenseAmount
                    ];
                    $paymentMethodTotals[$paymentMethodId] -= $expenseAmount;
                }

                // 11. Income (from incomes)
                $incomeAmount = \Modules\Accounting\Models\Income::where('user_id', $userId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->where('payment_method_id', $paymentMethodId)
                    ->where(function($query) use ($openingDate, $currentDate, $openingDateTime, $currentDateTime) {
                        $query->whereBetween('date', [$openingDate, $currentDate])
                              ->orWhere(function($q) use ($openingDateTime, $currentDateTime) {
                                  $q->whereNull('date')
                                    ->whereBetween('created_at', [$openingDateTime, $currentDateTime]);
                              });
                    })
                    ->sum('amount') ?? 0;

                if ($incomeAmount > 0) {
                    $summary[$paymentMethodId]['transactions'][] = [
                        'transaction_type' => 'Income',
                        'amount' => $incomeAmount
                    ];
                    $paymentMethodTotals[$paymentMethodId] += $incomeAmount;
                }

                // 12. Servicing (if exists - placeholder for now)
                // $servicingAmount = 0; // Add if servicing module exists
            }

            // Get user name
            $user = Auth::user();
            $userName = $user->name ?? 'Unknown';

            // Format summary data for response
            $formattedSummary = [];
            foreach ($summary as $paymentMethodId => $data) {
                if (count($data['transactions']) > 0 || $paymentMethodTotals[$paymentMethodId] != 0) {
                    $formattedSummary[] = [
                        'payment_method_id' => $paymentMethodId,
                        'payment_method_name' => $data['payment_method_name'],
                        'transactions' => $data['transactions'],
                        'total' => $paymentMethodTotals[$paymentMethodId]
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user_name' => $userName,
                    'start_time' => $openingDateTime->format('Y-m-d H:i:s'),
                    'end_time' => $currentDateTime->format('Y-m-d H:i:s'),
                    'summary' => $formattedSummary,
                    'payment_method_totals' => $paymentMethodTotals
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
