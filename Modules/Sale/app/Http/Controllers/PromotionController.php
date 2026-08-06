<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Sale\Http\Request\PromotionRequest;
use Modules\Sale\Services\PromotionService;
use Modules\Stock\Models\Item;

class PromotionController extends Controller
{
    protected $promotionService;

    public function __construct(PromotionService $promotionService)
    {
        $this->promotionService = $promotionService;
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
                $this->promotionService->getDataTableData($start, $length, $search, $draw)
            );
        }

        return view('sale::promotion.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $items = Item::from('items as child')
            ->leftJoin('items as parent', 'child.parent_id', '=', 'parent.id')
            ->where('child.del_status', 'Live')
            ->where('child.company_id', session('company.company_id'))
            ->where('child.enable_disable_status', 1)
            ->where('child.type', '!=', 'Variation_Product')
            ->orderBy('child.code', 'asc') 
            ->select([
                'child.*',
                DB::raw("
                    CASE 
                        WHEN parent.id IS NOT NULL
                        THEN CONCAT(parent.name, ' ( ', child.name, ' - ', child.code, ' )')
                        ELSE child.name
                    END as display_name
                ")
            ])
            ->get();

        return view('sale::promotion.create', compact('items'));

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PromotionRequest $request)
    {
        try {
            $this->promotionService->createPromotion($request->validated());

            return redirect()->route('promotion.index')
                ->with('success', 'Promotion Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('promotion.create')
                ->withInput()
                ->with('error', $e->getMessage());
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
            $promotion = $this->promotionService->getPromotionByEncryptedId($id);

            if (!$promotion) {
                return redirect()->route('promotion.index')
                    ->with('error', 'Promotion not found');
            }

            $items = Item::from('items as child')
            ->leftJoin('items as parent', 'child.parent_id', '=', 'parent.id')
            ->where('child.del_status', 'Live')
            ->where('child.company_id', session('company.company_id'))
            ->where('child.enable_disable_status', 1)
            ->where('child.type', '!=', 'Variation_Product')
            ->orderBy('child.code', 'asc') 
            ->select([
                'child.*',
                DB::raw("
                    CASE 
                        WHEN parent.id IS NOT NULL
                        THEN CONCAT(parent.name, ' ( ', child.name, ' - ', child.code, ' )')
                        ELSE child.name
                    END as display_name
                ")
            ])
            ->get();

            return view('sale::promotion.create', compact('promotion', 'items'));
        } catch (\Exception $e) {
            return redirect()->route('promotion.index')
                ->with('error', 'Promotion not found');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PromotionRequest $request, string $id)
    {
        try {
            $promotion = $this->promotionService->getPromotionByEncryptedId($id);

            if (!$promotion) {
                return redirect()->route('promotion.index')
                    ->with('error', 'Promotion not found');
            }

            $this->promotionService->updatePromotion($promotion, $request->validated());

            return redirect()->route('promotion.index')
                ->with('success', 'Promotion Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('promotion.edit', $id)
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $promotion = $this->promotionService->getPromotionByEncryptedId($id);

            if (!$promotion) {
                return redirect()->route('promotion.index')
                    ->with('error', 'Promotion not found');
            }

            $this->promotionService->deletePromotion($promotion);

            return redirect()->route('promotion.index')
                ->with('success', 'Promotion Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('promotion.index')
                ->with('error', $e->getMessage());
        }

    }
}