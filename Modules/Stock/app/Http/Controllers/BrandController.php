<?php

namespace Modules\Stock\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Stock\Http\Request\BrandRequest;
use Modules\Stock\Services\BrandService;

class BrandController extends Controller
{
    /**
     * @var BrandService
     */
    protected $brandService;

    /**
     * BrandController constructor.
     *
     * @param BrandService $brandService
     */
    public function __construct(BrandService $brandService)
    {
        $this->brandService = $brandService;
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

            $data = $this->brandService->getDataTableData($params);
            return response()->json($data);
        }

        return view('stock::brand.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('stock::brand.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BrandRequest $request)
    {
        try {
            $this->brandService->createBrand($request->validated());
            return redirect()->route('brand.index')
                ->with('success', 'Brand created successfully.');
        } catch (\Exception $e) {
            return redirect()->route('brand.create')
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the specified resource.
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
            $brand = $this->brandService->getBrandByEncryptedId($id);
            
            if (!$brand) {
                return redirect()->route('brand.index')
                    ->with('error', 'Brand not found');
            }

            return view('stock::brand.create', compact('brand'));
        } catch (\Exception $e) {
            return redirect()->route('brand.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(BrandRequest $request, string $id)
    {
        try {
            $this->brandService->updateBrand($id, $request->validated());
            return redirect()->route('brand.index')
                ->with('success', 'Brand updated successfully.');
        } catch (\Exception $e) {
            return redirect()->route('brand.edit', $id)
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
            $this->brandService->deleteBrand($id);
            return redirect()->route('brand.index')
                ->with('success', 'Brand deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('brand.index')
                ->with('error', $e->getMessage());
        }
    }

}
