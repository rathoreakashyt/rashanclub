<?php

namespace Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Purchase\Http\Requests\SupplierPaymentRequest;
use Modules\Purchase\Services\SupplierPaymentService;

class SupplierPaymentController extends Controller
{
    protected $supplierPaymentService;

    public function __construct(SupplierPaymentService $supplierPaymentService)
    {
        $this->supplierPaymentService = $supplierPaymentService;
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

            return response()->json(
                $this->supplierPaymentService->getDataTableData($start, $length, $search, $draw)
            );
        }

        return view('purchase::supplier-payment.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data = $this->supplierPaymentService->getFormData();
        return view('purchase::supplier-payment.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SupplierPaymentRequest $request)
    {
        try {
            $this->supplierPaymentService->createSupplierPayment($request->validated());

            return redirect()->route('supplier-payment.index')
                ->with('success', 'Supplier Payment Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('supplier-payment.create')
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
            $supplierPayment = $this->supplierPaymentService->getSupplierPaymentByEncryptedId($id);

            if (!$supplierPayment) {
                return redirect()->route('supplier-payment.index')
                    ->with('error', 'Supplier Payment not found');
            }

            $data = $this->supplierPaymentService->getFormData($supplierPayment);
            return view('purchase::supplier-payment.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('supplier-payment.index')
                ->with('error', 'Supplier Payment not found');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SupplierPaymentRequest $request, string $id)
    {
        try {
            $supplierPayment = $this->supplierPaymentService->getSupplierPaymentByEncryptedId($id);

            if (!$supplierPayment) {
                return redirect()->route('supplier-payment.index')
                    ->with('error', 'Supplier Payment not found');
            }

            $this->supplierPaymentService->updateSupplierPayment($supplierPayment, $request->validated());

            return redirect()->route('supplier-payment.index')
                ->with('success', 'Supplier Payment Updated Successfully');
        } catch (\Exception $e) {
            $decryptedId = decrypt($id);
            return redirect()->route('supplier-payment.edit', encrypt($decryptedId))
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $supplierPayment = $this->supplierPaymentService->getSupplierPaymentByEncryptedId($id);

            if (!$supplierPayment) {
                return redirect()->route('supplier-payment.index')
                    ->with('error', 'Supplier Payment not found');
            }

            $this->supplierPaymentService->deleteSupplierPayment($supplierPayment);

            return redirect()->route('supplier-payment.index')
                ->with('success', 'Supplier Payment Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('supplier-payment.index')
                ->with('error', $e->getMessage());
        }
    }
}
