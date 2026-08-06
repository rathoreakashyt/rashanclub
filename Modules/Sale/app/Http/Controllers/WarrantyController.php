<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Modules\Sale\Http\Request\WarrantyRequest;
use Modules\Sale\Models\Customer;
use Modules\Sale\Services\WarrantyService;

class WarrantyController extends Controller
{
    protected $warrantyService;

    public function __construct(WarrantyService $warrantyService)
    {
        $this->warrantyService = $warrantyService;
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
            $data = $this->warrantyService->getDataTableData($params);
            return response()->json($data);
        }

        return view('sale::warranty.index');
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

        $technicians = User::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        return view('sale::warranty.create', compact('customers', 'technicians'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(WarrantyRequest $request)
    {
        try {
            $this->warrantyService->createWarranty($request->validated());

            return redirect()->route('warranty.index')
                ->with('success', 'Warranty Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('warranty.create')
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
            $warranty = $this->warrantyService->getWarrantyByEncryptedId($id);

            if (!$warranty) {
                return redirect()->route('warranty.index')
                    ->with('error', 'Warranty not found');
            }

            $warranty->load(['customer', 'technician', 'user', 'outlet', 'company']);

            return view('sale::warranty.show', compact('warranty'));
        } catch (\Exception $e) {
            return redirect()->route('warranty.index')
                ->with('error', 'Warranty not found');
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $warranty = $this->warrantyService->getWarrantyByEncryptedId($id);

            if (!$warranty) {
                return redirect()->route('warranty.index')
                    ->with('error', 'Warranty not found');
            }

            $companyId = session('company.company_id');
            
            $customers = Customer::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'phone']);

            $technicians = User::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->orderBy('name', 'asc')
                ->get(['id', 'name']);

            return view('sale::warranty.create', compact('warranty', 'customers', 'technicians'));
        } catch (\Exception $e) {
            return redirect()->route('warranty.index')
                ->with('error', 'Warranty not found');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(WarrantyRequest $request, string $id)
    {
        try {
            $warranty = $this->warrantyService->getWarrantyByEncryptedId($id);

            if (!$warranty) {
                return redirect()->route('warranty.index')
                    ->with('error', 'Warranty not found');
            }

            $this->warrantyService->updateWarranty($id, $request->validated());

            return redirect()->route('warranty.index')
                ->with('success', 'Warranty Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('warranty.edit', $id)
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
            $warranty = $this->warrantyService->getWarrantyByEncryptedId($id);

            if (!$warranty) {
                return redirect()->route('warranty.index')
                    ->with('error', 'Warranty not found');
            }

            $this->warrantyService->deleteWarranty($id);

            return redirect()->route('warranty.index')
                ->with('success', 'Warranty Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('warranty.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Search product by IMEI/Serial number
     */
    public function searchProductByImeiSerial()
    {
        $imeiSerial = request()->get('imei_serial');
        
        if (!$imeiSerial) {
            return response()->json(['error' => 'IMEI/Serial number is required'], 400);
        }

        try {
            $result = $this->warrantyService->searchProductByImeiSerial($imeiSerial);
            
            if (!$result) {
                return response()->json(['error' => 'Product not found with this IMEI/Serial number'], 404);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update warranty status
     */
    public function updateStatus()
    {
        $id = request()->get('id');
        $status = request()->get('status');

        if (!$id || !$status) {
            return response()->json(['error' => 'ID and Status are required'], 400);
        }

        try {
            $this->warrantyService->updateStatus($id, $status);
            return response()->json(['success' => true, 'message' => 'Status updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show warranty checking page
     */
    public function checking()
    {
        return view('sale::warranty.warranty-checking');
    }

    /**
     * Search for warranty check
     */
    public function searchForWarranty()
    {
        $searchType = request()->get('search_type');
        $searchValue = request()->get('search_value');

        if (!$searchType || !$searchValue) {
            return response()->json(['error' => 'Search type and value are required'], 400);
        }

        if (!in_array($searchType, ['imei', 'invoice'])) {
            return response()->json(['error' => 'Invalid search type'], 400);
        }

        try {
            $result = $this->warrantyService->searchForWarrantyCheck($searchType, $searchValue);
            
            if (!$result) {
                return response()->json(['error' => 'No sale found with the provided information'], 404);
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display warranty invoice
     */
    public function warrantyInvoice(string $id)
    {
        try {
            $saleId = decrypt($id);
            $sale = \Modules\Sale\Models\Sale::with([
                'customer',
                'employee',
                'user',
                'company',
                'outlet',
                'saleDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'saleDetails.item',
                'salePayments' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salePayments.paymentMethod'
            ])->find($saleId);

            if (!$sale) {
                return redirect()->route('warranty.checking')
                    ->with('error', 'Sale not found');
            }

            return view('sale::warranty.warranty-invoice', compact('sale'));
        } catch (\Exception $e) {
            return redirect()->route('warranty.checking')
                ->with('error', 'Invalid sale ID');
        }
    }
}
