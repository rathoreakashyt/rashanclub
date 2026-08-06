<?php

namespace Modules\Configuration\Http\Controllers;

use Modules\Configuration\Http\Request\MultipleCurrencyRequest;
use Modules\Configuration\Services\MultipleCurrencyService;
use Illuminate\Routing\Controller;

class MultipleCurrencyController extends Controller
{
    /**
     * @var MultipleCurrencyService
     */
    protected $multipleCurrencyService;

    /**
     * MultipleCurrencyController constructor.
     *
     * @param MultipleCurrencyService $multipleCurrencyService
     */
    public function __construct(MultipleCurrencyService $multipleCurrencyService)
    {
        $this->multipleCurrencyService = $multipleCurrencyService;
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

            $data = $this->multipleCurrencyService->getDataTableData($params);
            return response()->json($data);
        }

        return view('configuration::multiple-currency.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('configuration::multiple-currency.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(MultipleCurrencyRequest $request)
    {
        try {
            $this->multipleCurrencyService->createMultipleCurrency($request->validated());
            return redirect()->route('multiple-currency.index')
                ->with('success', 'Multiple Currency Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('multiple-currency.create')
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
            $multiple_currency = $this->multipleCurrencyService->getMultipleCurrencyByEncryptedId($id);
            
            if (!$multiple_currency) {
                return redirect()->route('multiple-currency.index')
                    ->with('error', 'Multiple Currency not found');
            }

            return view('configuration::multiple-currency.create', compact('multiple_currency'));
        } catch (\Exception $e) {
            return redirect()->route('multiple-currency.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(MultipleCurrencyRequest $request, string $id)
    {
        try {
            $this->multipleCurrencyService->updateMultipleCurrency($id, $request->validated());
            return redirect()->route('multiple-currency.index')
                ->with('success', 'Multiple Currency Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('multiple-currency.edit', $id)
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
            $this->multipleCurrencyService->deleteMultipleCurrency($id);
            return redirect()->route('multiple-currency.index')
                ->with('success', 'Multiple Currency Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('multiple-currency.index')
                ->with('error', $e->getMessage());
        }
    }
}

