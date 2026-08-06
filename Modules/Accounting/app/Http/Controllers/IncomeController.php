<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Accounting\Http\Request\IncomeRequest;
use Modules\Accounting\Services\IncomeService;

class IncomeController extends Controller
{
    public function __construct(private IncomeService $incomeService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            $length = (int) (request()->length ?? 10);
            $start = (int) (request()->start ?? 0);
            $search = request()->search['value'] ?? '';

            $tableData = $this->incomeService->getDataTable($length, $start, $search);

            return response()->json([
                'draw' => request()->draw,
                'recordsTotal' => $tableData['recordsTotal'],
                'recordsFiltered' => $tableData['recordsFiltered'],
                'data' => $tableData['data'],
            ]);
        }
        return view('accounting::income.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            $data = $this->incomeService->getCreateData();
            return view('accounting::income.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('income.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(IncomeRequest $request)
    {
        try {
            $this->incomeService->create($request->validated());
            return redirect()->route('income.index')
                ->with('success', 'Income Created Successfully');
        } catch (\Exception $e) {
            return redirect()->back()
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
            $data = $this->incomeService->getEditData($id);
            return view('accounting::income.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('income.index')
                ->with('error', 'Income not found');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(IncomeRequest $request, string $id)
    {
        try {
            $this->incomeService->update($id, $request->validated());
            return redirect()->route('income.index')
                ->with('success', 'Income Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->back()
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
            $this->incomeService->delete($id);
            return redirect()->route('income.index')
                ->with('success', 'Income Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('income.index')
                ->with('error', $e->getMessage());
        }
    }
}

