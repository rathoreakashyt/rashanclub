<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Accounting\Http\Request\ExpenseRequest;
use Modules\Accounting\Services\ExpenseService;

class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $expenseService)
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

            $tableData = $this->expenseService->getDataTable($length, $start, $search);

            return response()->json([
                'draw' => request()->draw,
                'recordsTotal' => $tableData['recordsTotal'],
                'recordsFiltered' => $tableData['recordsFiltered'],
                'data' => $tableData['data'],
            ]);
        }
        return view('accounting::expense.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            $data = $this->expenseService->getCreateData();
            return view('accounting::expense.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('expense.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ExpenseRequest $request)
    {
        try {
            $this->expenseService->create($request->validated());
            return redirect()->route('expense.index')
                ->with('success', 'Expense Created Successfully');
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
            $data = $this->expenseService->getEditData($id);
            return view('accounting::expense.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('expense.index')
                ->with('error', 'Expense not found');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ExpenseRequest $request, string $id)
    {
        try {
            $this->expenseService->update($id, $request->validated());
            return redirect()->route('expense.index')
                ->with('success', 'Expense Updated Successfully');
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
            $this->expenseService->delete($id);
            return redirect()->route('expense.index')
                ->with('success', 'Expense Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('expense.index')
                ->with('error', $e->getMessage());
        }
    }
}

