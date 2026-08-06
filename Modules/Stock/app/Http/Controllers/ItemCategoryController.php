<?php

namespace Modules\Stock\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Stock\Http\Request\ItemCategoryRequest;
use Modules\Stock\Services\ItemCategoryService;

class ItemCategoryController extends Controller
{
    /**
     * @var ItemCategoryService
     */
    protected $itemCategoryService;

    /**
     * ItemCategoryController constructor.
     *
     * @param ItemCategoryService $itemCategoryService
     */
    public function __construct(ItemCategoryService $itemCategoryService)
    {
        $this->itemCategoryService = $itemCategoryService;
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

            $data = $this->itemCategoryService->getDataTableData($params);
            return response()->json($data);
        }

        return view('stock::item-category.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('stock::item-category.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ItemCategoryRequest $request)
    {
        try {
            $this->itemCategoryService->createItemCategory($request->validated());
            return redirect()->route('item-category.index')
                ->with('success', 'Item Category created successfully.');
        } catch (\Exception $e) {
            return redirect()->route('item-category.create')
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
            $itemCategory = $this->itemCategoryService->getItemCategoryByEncryptedId($id);
            
            if (!$itemCategory) {
                return redirect()->route('item-category.index')
                    ->with('error', 'Item Category not found');
            }

            return view('stock::item-category.create', compact('itemCategory'));
        } catch (\Exception $e) {
            return redirect()->route('item-category.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ItemCategoryRequest $request, string $id)
    {
        try {
            $this->itemCategoryService->updateItemCategory($id, $request->validated());
            return redirect()->route('item-category.index')
                ->with('success', 'Item Category updated successfully.');
        } catch (\Exception $e) {
            return redirect()->route('item-category.edit', $id)
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
            $this->itemCategoryService->deleteItemCategory($id);
            return redirect()->route('item-category.index')
                ->with('success', 'Item Category deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('item-category.index')
                ->with('error', $e->getMessage());
        }
    }

    public function sortCategory()
    {
        try {
            $categories = $this->itemCategoryService->getCategoriesForSorting();
            return view('stock::item-category.sort-category', compact('categories'));
        } catch (\Exception $e) {
            return redirect()->route('item-category.index')
                ->with('error', 'Error loading sort page: ' . $e->getMessage());
        }
    }

    /**
     * Update category sort order
     */
    public function updateSortOrder()
    {
        try {
            $sortedIds = request()->input('sorted_ids', []);
            $this->itemCategoryService->updateSortOrder($sortedIds);
            return response()->json([
                'status' => 'success',
                'message' => 'Category order updated successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
