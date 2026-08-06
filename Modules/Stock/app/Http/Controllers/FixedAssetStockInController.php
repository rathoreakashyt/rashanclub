<?php

namespace Modules\Stock\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Stock\Http\Request\FixedAssetStockInRequest;
use Modules\Stock\Services\FixedAssetStockInService;
use Modules\Stock\Models\FixedAssetItem;

class FixedAssetStockInController extends Controller
{
    protected $stockInService;

    public function __construct(FixedAssetStockInService $stockInService)
    {
        $this->stockInService = $stockInService;
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
                $this->stockInService->getDataTableData($start, $length, $search, $draw)
            );
        }

        return view('stock::fixed-assets.stock-in.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $companyId = session('company.company_id');
        $data = [
            'items' => FixedAssetItem::where([
                ['del_status', 'Live'],
                ['company_id', $companyId],
            ])
                ->select('id', 'name', 'description')
                ->orderBy('name')
                ->get(),
            'reference_no' => $this->stockInService->generateReferenceNumber(),
        ];

        return view('stock::fixed-assets.stock-in.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FixedAssetStockInRequest $request)
    {
        try {
            $validatedData = $request->validated();
            
            // Log validated data for debugging (remove in production)
            \Log::info('Stock In Store - Validated Data', ['data' => $validatedData]);
            
            $stockIn = $this->stockInService->createStockIn($validatedData);
            
            \Log::info('Stock In Created Successfully', ['id' => $stockIn->id]);
            
            return redirect()->route('fixed-asset-stock-in.index')
                ->with('success', __('Stock In Created Successfully'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('fixed-asset-stock-in.create')
                ->withInput()
                ->withErrors($e->errors())
                ->with('error', __('Validation failed. Please check the form and try again.'));
        } catch (\Exception $e) {
            \Log::error('Stock In Store Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('fixed-asset-stock-in.create')
                ->withInput()
                ->with('error', $e->getMessage() ?: __('An error occurred while creating the stock in.'));
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $stockIn = $this->stockInService->getStockInByEncryptedId($id);

            if (!$stockIn) {
                return redirect()->route('fixed-asset-stock-in.index')
                    ->with('error', __('Stock In not found'));
            }

            // Load relationships
            $stockIn->load(['user', 'company', 'outlet', 'stockInDetails.fixedAssetItem']);

            return view('stock::fixed-assets.stock-in.details', compact('stockIn'));
        } catch (\Exception $e) {
            return redirect()->route('fixed-asset-stock-in.index')
                ->with('error', __('Stock In not found'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $stockIn = $this->stockInService->getStockInByEncryptedId($id);

            if (!$stockIn) {
                return redirect()->route('fixed-asset-stock-in.index')
                    ->with('error', __('Stock In not found'));
            }

            $companyId = session('company.company_id');

            $data = [
                'stockIn' => $stockIn,
                'items' => FixedAssetItem::where([
                    ['del_status', 'Live'],
                    ['company_id', $companyId],
                ])
                    ->select('id', 'name', 'description')
                    ->orderBy('name')
                    ->get(),
            ];

            return view('stock::fixed-assets.stock-in.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('fixed-asset-stock-in.index')
                ->with('error', __('Stock In not found'));
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(FixedAssetStockInRequest $request, string $id)
    {
        try {
            $stockIn = $this->stockInService->getStockInByEncryptedId($id);

            if (!$stockIn) {
                return redirect()->route('fixed-asset-stock-in.index')
                    ->with('error', __('Stock In not found'));
            }

            $validatedData = $request->validated();
            
            // Log validated data for debugging (remove in production)
            \Log::info('Stock In Update - Validated Data', ['id' => $id, 'data' => $validatedData]);
            
            $this->stockInService->updateStockIn($stockIn, $validatedData);
            
            \Log::info('Stock In Updated Successfully', ['id' => $stockIn->id]);

            return redirect()->route('fixed-asset-stock-in.index')
                ->with('success', __('Stock In Updated Successfully'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('fixed-asset-stock-in.edit', $id)
                ->withInput()
                ->withErrors($e->errors())
                ->with('error', __('Validation failed. Please check the form and try again.'));
        } catch (\Exception $e) {
            \Log::error('Stock In Update Error', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('fixed-asset-stock-in.index')
                ->with('error', $e->getMessage() ?: __('An error occurred while updating the stock in.'));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $stockIn = $this->stockInService->getStockInByEncryptedId($id);

            if (!$stockIn) {
                return redirect()->route('fixed-asset-stock-in.index')
                    ->with('error', __('Stock In not found'));
            }

            $this->stockInService->deleteStockIn($stockIn);

            return redirect()->route('fixed-asset-stock-in.index')
                ->with('success', __('Stock In Deleted Successfully'));
        } catch (\Exception $e) {
            return redirect()->route('fixed-asset-stock-in.index')
                ->with('error', $e->getMessage());
        }
    }
}

