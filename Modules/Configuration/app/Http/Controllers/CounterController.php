<?php

namespace Modules\Configuration\Http\Controllers;

use Modules\Configuration\Http\Request\CounterRequest;
use Modules\Configuration\Services\CounterService;
use Illuminate\Routing\Controller;

class CounterController extends Controller
{
    /**
     * @var CounterService
     */
    protected $counterService;

    /**
     * CounterController constructor.
     *
     * @param CounterService $counterService
     */
    public function __construct(CounterService $counterService)
    {
        $this->counterService = $counterService;
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

            $data = $this->counterService->getDataTableData($params);
            return response()->json($data);
        }

        return view('configuration::counter.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $formData = $this->counterService->getFormData();
        return view('configuration::counter.create', $formData);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CounterRequest $request)
    {
        try {
            $this->counterService->createCounter($request->validated());
            return redirect()->route('counter.index')
                ->with('success', 'Counter Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('counter.create')
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
            $counter = $this->counterService->getCounterByEncryptedId($id);
            
            if (!$counter) {
                return redirect()->route('counter.index')
                    ->with('error', 'Counter not found');
            }

            $formData = $this->counterService->getFormData();
            return view('configuration::counter.create', array_merge($formData, ['counter' => $counter]));
        } catch (\Exception $e) {
            return redirect()->route('counter.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CounterRequest $request, string $id)
    {
        try {
            $this->counterService->updateCounter($id, $request->validated());
            return redirect()->route('counter.index')
                ->with('success', 'Counter Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('counter.edit', $id)
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
            $this->counterService->deleteCounter($id);
            return redirect()->route('counter.index')
                ->with('success', 'Counter Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('counter.index')
                ->with('error', $e->getMessage());
        }
    }
}

