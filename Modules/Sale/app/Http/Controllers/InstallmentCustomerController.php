<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sale\Http\Request\InstallmentCustomerRequest;
use Modules\Sale\Services\InstallmentCustomerService;

class InstallmentCustomerController extends Controller
{
    protected $installmentCustomerService;

    public function __construct(InstallmentCustomerService $installmentCustomerService)
    {
        $this->installmentCustomerService = $installmentCustomerService;
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
                $this->installmentCustomerService->getDataTableData($start, $length, $search, $draw)
            );
        }

        return view('sale::installment-customer.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('sale::installment-customer.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(InstallmentCustomerRequest $request)
    {
        try {
            $this->installmentCustomerService->createInstallmentCustomer($request->validated(), $request);

            return redirect()->route('installment-customer.index')
                ->with('success', 'Installment Customer Created Successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('installment-customer.create')
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('installment-customer.create')
                ->with('error', $e->getMessage())
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
            $customer = $this->installmentCustomerService->getInstallmentCustomerByEncryptedId($id);

            if (!$customer) {
                return redirect()->route('installment-customer.index')
                    ->with('error', 'Installment Customer not found');
            }

            return view('sale::installment-customer.create', compact('customer'));
        } catch (\Exception $e) {
            return redirect()->route('installment-customer.index')
                ->with('error', 'Installment Customer not found');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(InstallmentCustomerRequest $request, string $id)
    {
        try {
            $customer = $this->installmentCustomerService->getInstallmentCustomerByEncryptedId($id);

            if (!$customer) {
                return redirect()->route('installment-customer.index')
                    ->with('error', 'Installment Customer not found');
            }

            $this->installmentCustomerService->updateInstallmentCustomer($customer, $request->validated(), $request);

            return redirect()->route('installment-customer.index')
                ->with('success', 'Installment Customer Updated Successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('installment-customer.edit', $id)
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('installment-customer.edit', $id)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $customer = $this->installmentCustomerService->getInstallmentCustomerByEncryptedId($id);

            if (!$customer) {
                return redirect()->route('installment-customer.index')
                    ->with('error', 'Installment Customer not found');
            }

            $this->installmentCustomerService->deleteInstallmentCustomer($customer);

            return redirect()->route('installment-customer.index')
                ->with('success', 'Installment Customer Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('installment-customer.index')
                ->with('error', $e->getMessage());
        }
    }
}
