<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Accounting\Http\Request\IncomeCategoryRequest;
use Modules\Accounting\Services\IncomeCategoryService;

class IncomeCategoryController extends Controller
{
    public function __construct(private IncomeCategoryService $incomeCategoryService)
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

            $tableData = $this->incomeCategoryService->getDataTable($length, $start, $search);

            return response()->json([
                'draw' => request()->draw,
                'recordsTotal' => $tableData['recordsTotal'],
                'recordsFiltered' => $tableData['recordsFiltered'],
                'data' => $tableData['data'],
            ]);
        }
        return view('accounting::income-category.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('accounting::income-category.create');
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store(IncomeCategoryRequest $request)
    {
        try {
            $category = $this->incomeCategoryService->create($request->validated());

            if ($request->ajax()) {
                $categories = $this->incomeCategoryService->listForSelect();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Income Category Created Successfully',
                    'data' => [
                        'category' => $category,
                        'categories' => $categories,
                    ],
                ], 201);
            }

            return redirect()->route('income-category.index')
                ->with('success', 'Income Category Created Successfully');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 500);
            }
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
            $income_category = $this->incomeCategoryService->getByEncryptedId($id);
            return view('accounting::income-category.create', compact('income_category'));
        } catch (\Exception $e) {
            return redirect()->route('income-category.index')
                ->with('error', 'Income Category not found');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(IncomeCategoryRequest $request, string $id)
    {
        try {
            $this->incomeCategoryService->update($id, $request->validated());
            return redirect()->route('income-category.index')
                ->with('success', 'Income Category Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('income-category.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->incomeCategoryService->delete($id);
            return redirect()->route('income-category.index')
                ->with('success', 'Income Category Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('income-category.index')
                ->with('error', $e->getMessage());
        }
    }
}

