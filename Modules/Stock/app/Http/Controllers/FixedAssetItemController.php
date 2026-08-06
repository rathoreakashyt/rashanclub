<?php

namespace Modules\Stock\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Stock\Http\Request\FixedAssetItemRequest;
use Modules\Stock\Services\FixedAssetItemService;

class FixedAssetItemController extends Controller
{
    protected $itemService;

    public function __construct(FixedAssetItemService $itemService)
    {
        $this->itemService = $itemService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            $length = request()->length ?? 10;
            $start = request()->start ?? 0;
            $search = request()->search['value'] ?? '';
            $draw = request()->draw;

            return response()->json(
                $this->itemService->getDataTableData($start, $length, $search, $draw)
            );
        }

        return view('stock::fixed-assets.items.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('stock::fixed-assets.items.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FixedAssetItemRequest $request)
    {
        try {
            $this->itemService->createItem($request->validated());

            return redirect()->route('fixed-asset-item.index')
                ->with('success', __('Fixed Asset Item Created Successfully'));
        } catch (\Exception $e) {
            return redirect()->route('fixed-asset-item.create')
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $item = $this->itemService->getItemByEncryptedId($id);

            if (!$item) {
                return redirect()->route('fixed-asset-item.index')
                    ->with('error', __('Fixed Asset Item not found'));
            }

            return view('stock::fixed-assets.items.create', compact('item'));
        } catch (\Exception $e) {
            return redirect()->route('fixed-asset-item.index')
                ->with('error', __('Fixed Asset Item not found'));
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(FixedAssetItemRequest $request, string $id)
    {
        try {
            $item = $this->itemService->getItemByEncryptedId($id);

            if (!$item) {
                return redirect()->route('fixed-asset-item.index')
                    ->with('error', __('Fixed Asset Item not found'));
            }

            $this->itemService->updateItem($item, $request->validated());

            return redirect()->route('fixed-asset-item.index')
                ->with('success', __('Fixed Asset Item Updated Successfully'));
        } catch (\Exception $e) {
            return redirect()->route('fixed-asset-item.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $item = $this->itemService->getItemByEncryptedId($id);

            if (!$item) {
                return redirect()->route('fixed-asset-item.index')
                    ->with('error', __('Fixed Asset Item not found'));
            }

            $this->itemService->deleteItem($item);

            return redirect()->route('fixed-asset-item.index')
                ->with('success', __('Fixed Asset Item Deleted Successfully'));
        } catch (\Exception $e) {
            return redirect()->route('fixed-asset-item.index')
                ->with('error', $e->getMessage());
        }
    }
}

