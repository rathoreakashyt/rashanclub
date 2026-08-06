<?php

namespace Modules\Stock\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Stock\Http\Request\RackRequest;
use Modules\Stock\Services\RackService;

class RackController extends Controller
{
    /**
     * @var RackService
     */
    protected $rackService;

    /**
     * RackController constructor.
     *
     * @param RackService $rackService
     */
    public function __construct(RackService $rackService)
    {
        $this->rackService = $rackService;
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

            $data = $this->rackService->getDataTableData($params);
            return response()->json($data);
        }

        return view('stock::rack.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('stock::rack.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RackRequest $request)
    {
        try {
            $this->rackService->createRack($request->validated());
            return redirect()->route('rack.index')
                ->with('success', 'Rack Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('rack.create')
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
            $rack = $this->rackService->getRackByEncryptedId($id);
            if (!$rack) {
                return redirect()->route('rack.index')
                    ->with('error', 'Rack not found');
            }
            return view('stock::rack.create', compact('rack'));
        } catch (\Exception $e) {
            return redirect()->route('rack.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RackRequest $request, string $id)
    {
        try {
            $this->rackService->updateRack($id, $request->validated());
            return redirect()->route('rack.index')
                ->with('success', 'Rack updated successfully.');
        } catch (\Exception $e) {
            return redirect()->route('rack.edit', $id)
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
            $this->rackService->deleteRack($id);
            return redirect()->route('rack.index')
                ->with('success', 'Rack deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('rack.index')
                ->with('error', $e->getMessage());
        }
    }
}
