<?php

namespace Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Purchase\Http\Requests\SupplierRequest;
use Modules\Purchase\Services\SupplierService;

class SupplierController extends Controller
{
    protected $supplierService;

    public function __construct(SupplierService $supplierService)
    {
        $this->supplierService = $supplierService;
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
                $this->supplierService->getDataTableData($start, $length, $search, $draw, $typeFilter)
            );
        }

        return view('purchase::supplier.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('purchase::supplier.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SupplierRequest $request)
    {
        try {
            $this->supplierService->createSupplier($request->validated());

            return redirect()->route('supplier.index')
                ->with('success', 'Supplier Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('supplier.create')
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
            $supplier = $this->supplierService->getSupplierByEncryptedId($id);

            if (!$supplier) {
                return redirect()->route('supplier.index')
                    ->with('error', 'Supplier not found');
            }

            return view('purchase::supplier.create', compact('supplier'));
        } catch (\Exception $e) {
            return redirect()->route('supplier.index')
                ->with('error', 'Supplier not found');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SupplierRequest $request, string $id)
    {
        try {
            $supplier = $this->supplierService->getSupplierByEncryptedId($id);

            if (!$supplier) {
                return redirect()->route('supplier.index')
                    ->with('error', 'Supplier not found');
            }

            $this->supplierService->updateSupplier($supplier, $request->validated());

            return redirect()->route('supplier.index')
                ->with('success', 'Supplier Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('supplier.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $supplier = $this->supplierService->getSupplierByEncryptedId($id);

            if (!$supplier) {
                return redirect()->route('supplier.index')
                    ->with('error', 'Supplier not found');
            }

            $this->supplierService->deleteSupplier($supplier);

            return redirect()->route('supplier.index')
                ->with('success', 'Supplier Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('supplier.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Get supplier current balance by regular ID (for AJAX calls from forms)
     */
    public function getBalanceById(int $id)
    {
        try {
            $supplier = \Modules\Purchase\Models\Supplier::where('id', $id)
                ->where('company_id', session('company.company_id'))
                ->where('del_status', 'Live')
                ->first();

            if (!$supplier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier not found'
                ], 404);
            }

            $outletId = session('outlet.id');
            $balance = $this->supplierService->getSupplierDue($supplier->id, $outletId);
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
