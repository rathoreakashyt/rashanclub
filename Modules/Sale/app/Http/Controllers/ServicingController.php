<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Modules\Sale\Http\Request\ServicingRequest;
use Modules\Sale\Models\Customer;
use Modules\Sale\Services\ServicingService;
use Modules\Accounting\Models\PaymentMethod;

class ServicingController extends Controller
{
    protected $servicingService;

    public function __construct(ServicingService $servicingService)
    {
        $this->servicingService = $servicingService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            $params = [
                'draw' => request()->draw ?? 1,
                'length' => request()->length ?? 10,
                'start' => request()->start ?? 0,
                'search' => request()->search['value'] ?? '',
            ];
            $data = $this->servicingService->getDataTableData($params);
            return response()->json($data);
        }

        return view('sale::servicing.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $companyId = session('company.company_id');
        
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'phone']);

        $employees = User::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        $paymentMethods = PaymentMethod::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('status', 'Enable')
            ->orderBy('sort_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('sale::servicing.create', compact('customers', 'employees', 'paymentMethods'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ServicingRequest $request)
    {
        try {
            $this->servicingService->createServicing($request->validated());

            return redirect()->route('servicing.index')
                ->with('success', 'Servicing Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('servicing.create')
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $servicing = $this->servicingService->getServicingByEncryptedId($id);

            if (!$servicing) {
                return redirect()->route('servicing.index')
                    ->with('error', 'Servicing not found');
            }

            $servicing->load(['customer', 'employee', 'user', 'outlet', 'company', 'paymentMethod']);

            return view('sale::servicing.show', compact('servicing'));
        } catch (\Exception $e) {
            return redirect()->route('servicing.index')
                ->with('error', 'Servicing not found');
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $servicing = $this->servicingService->getServicingByEncryptedId($id);

            if (!$servicing) {
                return redirect()->route('servicing.index')
                    ->with('error', 'Servicing not found');
            }

            $companyId = session('company.company_id');
            
            $customers = Customer::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'phone']);

            $employees = User::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->orderBy('name', 'asc')
                ->get(['id', 'name']);

            $paymentMethods = PaymentMethod::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('status', 'Enable')
                ->orderBy('sort_id')
                ->orderBy('name')
                ->get(['id', 'name']);


            return view('sale::servicing.create', compact('servicing', 'customers', 'employees', 'paymentMethods'));
        } catch (\Exception $e) {
            return redirect()->route('servicing.index')
                ->with('error', 'Servicing not found');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ServicingRequest $request, string $id)
    {
        try {
            $servicing = $this->servicingService->getServicingByEncryptedId($id);

            if (!$servicing) {
                return redirect()->route('servicing.index')
                    ->with('error', 'Servicing not found');
            }

            $this->servicingService->updateServicing($id, $request->validated());

            return redirect()->route('servicing.index')
                ->with('success', 'Servicing Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('servicing.edit', $id)
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $servicing = $this->servicingService->getServicingByEncryptedId($id);

            if (!$servicing) {
                return redirect()->route('servicing.index')
                    ->with('error', 'Servicing not found');
            }

            $this->servicingService->deleteServicing($id);

            return redirect()->route('servicing.index')
                ->with('success', 'Servicing Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('servicing.index')
                ->with('error', $e->getMessage());
        }
    }
}
