<?php

namespace Modules\Configuration\Http\Controllers;

use Modules\Configuration\Http\Request\OutletRequest;
use Modules\Configuration\Services\OutletService;
use Illuminate\Routing\Controller;

class OutletController extends Controller
{
    /**
     * @var OutletService
     */
    protected $outletService;

    /**
     * OutletController constructor.
     *
     * @param OutletService $outletService
     */
    public function __construct(OutletService $outletService)
    {
        $this->outletService = $outletService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $outlets = $this->outletService->getAllOutlets();
        return view('configuration::outlet.index', compact('outlets'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data = $this->outletService->getCreateData();
        return view('configuration::outlet.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(OutletRequest $request)
    {
        try {
            $this->outletService->createOutlet($request->all());
            return redirect()->route('outlet.index')
                ->with('success', 'Outlet created successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Something went wrong: ' . $e->getMessage())
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
            $data = $this->outletService->getEditData($id);
            return view('configuration::outlet.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('outlet.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(OutletRequest $request, string $id)
    {
        try {
            $this->outletService->updateOutlet($id, $request->all());
            return redirect()->route('outlet.index')
                ->with('success', 'Outlet updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Something went wrong: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->outletService->deleteOutlet($id);
            return redirect()->route('outlet.index')
                ->with('success', 'Outlet Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('outlet.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Enter Outlet.
     */
    public function enter(string $id)
    {
        try {
            $redirectRoute = $this->outletService->enterOutlet($id);
            return redirect()->route($redirectRoute);
        } catch (\Exception $e) {
            return redirect()->route('outlet.index')
                ->with('error', $e->getMessage());
        }
    }
}

