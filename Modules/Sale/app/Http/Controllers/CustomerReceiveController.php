<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Sale\Http\Request\CustomerReceiveRequest;
use Modules\Sale\Services\CustomerReceiveService;

class CustomerReceiveController extends Controller
{
    protected $customerReceiveService;

    public function __construct(CustomerReceiveService $customerReceiveService)
    {
        $this->customerReceiveService = $customerReceiveService;
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
                $this->customerReceiveService->getDataTableData($start, $length, $search, $draw)
            );
        }

        return view('sale::customer-receive.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data = $this->customerReceiveService->getFormData();
        return view('sale::customer-receive.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CustomerReceiveRequest $request)
    {
        try {
            $this->customerReceiveService->createCustomerReceive($request->validated());

            return redirect()->route('customer-receive.index')
                ->with('success', 'Customer Receive Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('customer-receive.create')
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
            $customerReceive = $this->customerReceiveService->getCustomerReceiveByEncryptedId($id);

            if (!$customerReceive) {
                return redirect()->route('customer-receive.index')
                    ->with('error', 'Customer Receive not found');
            }

            $data = $this->customerReceiveService->getFormData($customerReceive);
            return view('sale::customer-receive.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('customer-receive.index')
                ->with('error', 'Customer Receive not found');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CustomerReceiveRequest $request, string $id)
    {
        try {
            $customerReceive = $this->customerReceiveService->getCustomerReceiveByEncryptedId($id);

            if (!$customerReceive) {
                return redirect()->route('customer-receive.index')
                    ->with('error', 'Customer Receive not found');
            }

            $this->customerReceiveService->updateCustomerReceive($customerReceive, $request->validated());

            return redirect()->route('customer-receive.index')
                ->with('success', 'Customer Receive Updated Successfully');
        } catch (\Exception $e) {
            $decryptedId = decrypt($id);
            return redirect()->route('customer-receive.edit', encrypt($decryptedId))
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $customerReceive = $this->customerReceiveService->getCustomerReceiveByEncryptedId($id);

            if (!$customerReceive) {
                return redirect()->route('customer-receive.index')
                    ->with('error', 'Customer Receive not found');
            }

            $this->customerReceiveService->deleteCustomerReceive($customerReceive);

            return redirect()->route('customer-receive.index')
                ->with('success', 'Customer Receive Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('customer-receive.index')
                ->with('error', $e->getMessage());
        }
    }
}
