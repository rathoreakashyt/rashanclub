<?php

namespace Modules\Stock\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Stock\Services\PriceListService;
use Modules\Stock\Models\PriceList;

class PriceListController extends Controller
{
    protected $priceListService;

    public function __construct(PriceListService $priceListService)
    {
        $this->priceListService = $priceListService;
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $draw = (int) $request->input('draw', 1);
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $search = $request->input('search.value', '') ?? '';

            return response()->json(
                $this->priceListService->getDataTableData($start, $length, $search, $draw)
            );
        }

        return view('stock::price-lists.index');
    }

    public function create()
    {
        $data = $this->priceListService->getCreateData();
        return view('stock::price-lists.create', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'customer_type' => 'nullable|string|max:20',
            'items' => 'nullable|array',
            'items.*.item_id' => 'required|integer',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        try {
            $this->priceListService->createPriceList($request->all());
            return redirect()->route('price-list.index')
                ->with('success', __('Price List created successfully'));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function edit(string $encryptedId)
    {
        $priceList = $this->priceListService->getEditData(
            PriceList::find(decrypt($encryptedId))
        );
        return view('stock::price-lists.create', compact('priceList'));
    }

    public function update(Request $request, string $encryptedId)
    {
        $priceList = PriceList::find(decrypt($encryptedId));

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'customer_type' => 'nullable|string|max:20',
            'items' => 'nullable|array',
            'items.*.item_id' => 'required|integer',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        try {
            $this->priceListService->updatePriceList($priceList, $request->all());
            return redirect()->route('price-list.index')
                ->with('success', __('Price List updated successfully'));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function destroy(string $encryptedId)
    {
        $priceList = PriceList::find(decrypt($encryptedId));
        try {
            $this->priceListService->deletePriceList($priceList);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function listForSelect(): JsonResponse
    {
        $priceLists = $this->priceListService->getAll(session('company.company_id'));
        return response()->json([
            'status' => 'success',
            'data' => $priceLists->map(fn($pl) => ['id' => $pl->id, 'name' => $pl->name, 'customer_type' => $pl->customer_type]),
        ]);
    }
}
