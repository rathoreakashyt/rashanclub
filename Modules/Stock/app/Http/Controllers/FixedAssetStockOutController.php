<?php

namespace Modules\Stock\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Stock\Http\Request\FixedAssetStockOutRequest;
use Modules\Stock\Services\FixedAssetStockOutService;
use Modules\Stock\Models\FixedAssetItem;

class FixedAssetStockOutController extends Controller
{
    protected $stockOutService;

    public function __construct(FixedAssetStockOutService $stockOutService)
    {
        $this->stockOutService = $stockOutService;
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
                $this->stockOutService->getDataTableData($start, $length, $search, $draw)
            );
        }

        return view('stock::fixed-assets.stock-out.index');
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
            'reference_no' => $this->stockOutService->generateReferenceNumber(),
        ];

        return view('stock::fixed-assets.stock-out.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FixedAssetStockOutRequest $request)
    {
        try {
            $validatedData = $request->validated();
            
            // Log validated data for debugging (remove in production)
            \Log::info('Stock Out Store - Validated Data', ['data' => $validatedData]);
            
            $stockOut = $this->stockOutService->createStockOut($validatedData);
            
            \Log::info('Stock Out Created Successfully', ['id' => $stockOut->id]);
            
            return redirect()->route('fixed-asset-stock-out.index')
                ->with('success', __('Stock Out Created Successfully'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('fixed-asset-stock-out.create')
                ->withInput()
                ->withErrors($e->errors())
                ->with('error', __('Validation failed. Please check the form and try again.'));
        } catch (\Exception $e) {
            \Log::error('Stock Out Store Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('fixed-asset-stock-out.create')
                ->withInput()
                ->with('error', $e->getMessage() ?: __('An error occurred while creating the stock out.'));
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $stockOut = $this->stockOutService->getStockOutByEncryptedId($id);

            if (!$stockOut) {
                return redirect()->route('fixed-asset-stock-out.index')
                    ->with('error', __('Stock Out not found'));
            }

            // Load relationships
            $stockOut->load(['user', 'company', 'outlet', 'stockOutDetails.fixedAssetItem']);

            return view('stock::fixed-assets.stock-out.details', compact('stockOut'));
        } catch (\Exception $e) {
            return redirect()->route('fixed-asset-stock-out.index')
                ->with('error', __('Stock Out not found'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $stockOut = $this->stockOutService->getStockOutByEncryptedId($id);

            if (!$stockOut) {
                return redirect()->route('fixed-asset-stock-out.index')
                    ->with('error', __('Stock Out not found'));
            }

            $companyId = session('company.company_id');

            $data = [
                'stockOut' => $stockOut,
                'items' => FixedAssetItem::where([
                    ['del_status', 'Live'],
                    ['company_id', $companyId],
                ])
                    ->select('id', 'name', 'description')
                    ->orderBy('name')
                    ->get(),
            ];

            return view('stock::fixed-assets.stock-out.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('fixed-asset-stock-out.index')
                ->with('error', __('Stock Out not found'));
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(FixedAssetStockOutRequest $request, string $id)
    {
        try {
            $stockOut = $this->stockOutService->getStockOutByEncryptedId($id);

            if (!$stockOut) {
                return redirect()->route('fixed-asset-stock-out.index')
                    ->with('error', __('Stock Out not found'));
            }

            $validatedData = $request->validated();
            
            // Log validated data for debugging (remove in production)
            \Log::info('Stock Out Update - Validated Data', ['id' => $id, 'data' => $validatedData]);
            
            $this->stockOutService->updateStockOut($stockOut, $validatedData);
            
            \Log::info('Stock Out Updated Successfully', ['id' => $stockOut->id]);

            return redirect()->route('fixed-asset-stock-out.index')
                ->with('success', __('Stock Out Updated Successfully'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('fixed-asset-stock-out.edit', $id)
                ->withInput()
                ->withErrors($e->errors())
                ->with('error', __('Validation failed. Please check the form and try again.'));
        } catch (\Exception $e) {
            \Log::error('Stock Out Update Error', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('fixed-asset-stock-out.index')
                ->with('error', $e->getMessage() ?: __('An error occurred while updating the stock out.'));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $stockOut = $this->stockOutService->getStockOutByEncryptedId($id);

            if (!$stockOut) {
                return redirect()->route('fixed-asset-stock-out.index')
                    ->with('error', __('Stock Out not found'));
            }

            $this->stockOutService->deleteStockOut($stockOut);

            return redirect()->route('fixed-asset-stock-out.index')
                ->with('success', __('Stock Out Deleted Successfully'));
        } catch (\Exception $e) {
            return redirect()->route('fixed-asset-stock-out.index')
                ->with('error', $e->getMessage());
        }
    }
}

