<?php

namespace Modules\Accounting\Http\Controllers;

use Modules\Accounting\Http\Request\DepositWithdrawRequest;
use Modules\Accounting\Services\DepositWithdrawService;
use Modules\Accounting\Models\DepositWithdraw;
use Modules\Accounting\Models\PaymentMethod;
use Illuminate\Routing\Controller;

class DepositWithdrawController extends Controller
{
    /**
     * @var DepositWithdrawService
     */
    protected $depositWithdrawService;

    /**
     * DepositWithdrawController constructor.
     *
     * @param DepositWithdrawService $depositWithdrawService
     */
    public function __construct(DepositWithdrawService $depositWithdrawService)
    {
        $this->depositWithdrawService = $depositWithdrawService;
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
            $query = DepositWithdraw::where('del_status', 'Live')
                    ->where('company_id', session('company.company_id'))
                    ->with(['paymentMethod'])
                    ->orderBy('id', 'desc');
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('reference_no', 'like', "%{$search}%")
                      ->orWhere('date', 'like', "%{$search}%")
                      ->orWhere('type', 'like', "%{$search}%");
                });
            }
            $recordsTotal = DepositWithdraw::where('del_status', 'Live')
                    ->where('company_id', session('company.company_id'))
                    ->count();
            $filteredCount = $query->count();
            $depositWithdraws = $query->orderBy('id', 'desc')
                          ->skip($start)
                          ->take($length)
                          ->get();
            
            // Calculate the starting number for the current page
            $startingNumber = $filteredCount - $start;
            $transformedData = $depositWithdraws->map(function ($item, $index) use ($startingNumber) {
                return [
                    'id' => $startingNumber - $index, // Sequential number
                    'actual_id' => $item->id,     // Keep actual ID
                    'reference_no' => $item->reference_no,
                    'date' => formatDate($item->date),
                    'type' => $item->type,
                    'amount' => formatAmount($item->amount),
                    'payment_method' => $item->paymentMethod ? $item->paymentMethod->name : 'N/A',
                    'note' => truncateText($item->note, 50),
                    'encrypted_id' => $item->encrypted_id
                ];
            });
            
            return response()->json([
                'draw' => request()->draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $filteredCount,
                'data' => $transformedData
            ]);
        }
        return view('accounting::deposit-withdraw.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            $data = $this->depositWithdrawService->getCreateData();
            $data['payment_methods'] = PaymentMethod::where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
            return view('accounting::deposit-withdraw.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('deposit-withdraw.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(DepositWithdrawRequest $request)
    {
        try {
            $this->depositWithdrawService->createDepositWithdraw($request->all());
            return redirect()->route('deposit-withdraw.index')
                ->with('success', 'Deposit/Withdraw Created Successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Something went wrong: ' . $e->getMessage())
                ->withInput();
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
            $data = $this->depositWithdrawService->getEditData($id);
            $data['payment_methods'] = PaymentMethod::where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
            return view('accounting::deposit-withdraw.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('deposit-withdraw.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(DepositWithdrawRequest $request, string $id)
    {
        try {
            $this->depositWithdrawService->updateDepositWithdraw($id, $request->all());
            return redirect()->route('deposit-withdraw.index')
                ->with('success', 'Deposit/Withdraw Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Something went wrong: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->depositWithdrawService->deleteDepositWithdraw($id);
            return redirect()->route('deposit-withdraw.index')
                ->with('success', 'Deposit/Withdraw Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('deposit-withdraw.index')
                ->with('error', $e->getMessage());
        }
    }
}

