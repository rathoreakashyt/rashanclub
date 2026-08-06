<?php

namespace Modules\Configuration\Http\Controllers;

use Modules\Configuration\Http\Request\DenominationRequest;
use Modules\Configuration\Services\DenominationService;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class DenominationController extends Controller
{
    /**
     * @var DenominationService
     */
    protected $denominationService;

    /**
     * DenominationController constructor.
     *
     * @param DenominationService $denominationService
     */
    public function __construct(DenominationService $denominationService)
    {
        $this->denominationService = $denominationService;
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

            $data = $this->denominationService->getDataTableData($params);
            return response()->json($data);
        }

        return view('configuration::denomination.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('configuration::denomination.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(DenominationRequest $request)
    {
        try {
            $this->denominationService->createDenomination($request->validated());
            return redirect()->route('denomination.index')
                ->with('success', 'Denomination Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('denomination.create')
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
            $denomination = $this->denominationService->getDenominationByEncryptedId($id);
            
            if (!$denomination) {
                return redirect()->route('denomination.index')
                    ->with('error', 'Denomination not found');
            }

            return view('configuration::denomination.create', compact('denomination'));
        } catch (\Exception $e) {
            return redirect()->route('denomination.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(DenominationRequest $request, string $id)
    {
        try {
            $this->denominationService->updateDenomination($id, $request->validated());
            return redirect()->route('denomination.index')
                ->with('success', 'Denomination Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('denomination.edit', $id)
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
            $this->denominationService->deleteDenomination($id);
            return redirect()->route('denomination.index')
                ->with('success', 'Denomination Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('denomination.index')
                ->with('error', $e->getMessage());
        }
    }
}

