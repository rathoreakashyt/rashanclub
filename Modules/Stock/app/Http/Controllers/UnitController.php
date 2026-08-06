<?php

namespace Modules\Stock\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Stock\Http\Request\UnitRequest;
use Modules\Stock\Services\UnitService;

class UnitController extends Controller
{
    /**
     * @var UnitService
     */
    protected $unitService;

    /**
     * UnitController constructor.
     *
     * @param UnitService $unitService
     */
    public function __construct(UnitService $unitService)
    {
        $this->unitService = $unitService;
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

            $data = $this->unitService->getDataTableData($params);
            return response()->json($data);
        }

        return view('stock::unit.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('stock::unit.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UnitRequest $request)
    {
        try {
            $this->unitService->createUnit($request->validated());
            return redirect()->route('unit.index')
                ->with('success', 'Unit created successfully.');
        } catch (\Exception $e) {
            return redirect()->route('unit.create')
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
            $unit = $this->unitService->getUnitByEncryptedId($id);
            
            if (!$unit) {
                return redirect()->route('unit.index')
                    ->with('error', 'Unit not found');
            }

            return view('stock::unit.create', compact('unit'));
        } catch (\Exception $e) {
            return redirect()->route('unit.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UnitRequest $request, string $id)
    {
        try {
            $this->unitService->updateUnit($id, $request->validated());
            return redirect()->route('unit.index')
                ->with('success', 'Unit updated successfully.');
        } catch (\Exception $e) {
            return redirect()->route('unit.edit', $id)
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
            $this->unitService->deleteUnit($id);
            return redirect()->route('unit.index')
                ->with('success', 'Unit deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('unit.index')
                ->with('error', $e->getMessage());
        }
    }
}
