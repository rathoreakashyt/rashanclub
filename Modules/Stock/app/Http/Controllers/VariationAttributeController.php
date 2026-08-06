<?php

namespace Modules\Stock\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Stock\Http\Request\VariationRequest;
use Modules\Stock\Services\VariationService;

class VariationAttributeController extends Controller
{
    /**
     * @var VariationService
     */
    protected $variationService;

    /**
     * VariationAttributeController constructor.
     *
     * @param VariationService $variationService
     */
    public function __construct(VariationService $variationService)
    {
        $this->variationService = $variationService;
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

            $data = $this->variationService->getDataTableData($params);
            return response()->json($data);
        }

        return view('stock::variation.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('stock::variation.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(VariationRequest $request)
    {
        try {
            $this->variationService->createVariation($request->validated());
            return redirect()->route('variation-attribute.index')
                ->with('success', 'Variation Attribute Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('variation-attribute.create')
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
            $variation_attribute = $this->variationService->getVariationByEncryptedId($id);
            
            if (!$variation_attribute) {
                return redirect()->route('variation-attribute.index')
                    ->with('error', 'Variation Attribute not found');
            }

            return view('stock::variation.create', compact('variation_attribute'));
        } catch (\Exception $e) {
            return redirect()->route('variation-attribute.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(VariationRequest $request, string $id)
    {
        try {
            $this->variationService->updateVariation($id, $request->validated());
            return redirect()->route('variation-attribute.index')
                ->with('success', 'Variation Attribute Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('variation-attribute.edit', $id)
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
            $this->variationService->deleteVariation($id);
            return redirect()->route('variation-attribute.index')
                ->with('success', 'Variation Attribute deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('variation-attribute.index')
                ->with('error', $e->getMessage());
        }
    }
}
