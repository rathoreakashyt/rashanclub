<?php

namespace Modules\Stock\Repositories;

use Modules\Stock\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

class StockRepository
{
    /**
     * Get low stock data for DataTables (server-side pagination + batch queries for speed)
     */
    public function getLowStockDataTableData(array $filters = [], int $start = 0, int $length = 10): array
    {
        $itemId = $filters['item_id'] ?? '';
        $itemCode = $filters['item_code'] ?? '';
        $brandId = $filters['brand_id'] ?? '';
        $categoryId = $filters['category_id'] ?? '';
        $supplierId = $filters['supplier_id'] ?? '';
        $genericName = $filters['generic_name'] ?? '';
        $priceType = $filters['price_type'] ?? 'last_three_purchase_avg';
        $priceCol = $priceType === 'last_purchase_price' ? 'last_purchase_price' : 'last_three_purchase_avg';
        
        $outletId = session('outlet.outlet_id') ?? session('company.default_outlet_id');
        $companyId = session('company.company_id');
        
        $baseQuery = Item::where('company_id', $companyId)
            ->where('enable_disable_status', 1)
            ->where('del_status', 'Live')
            ->where('type', '!=', 'Service_Product')
            ->where('type', '!=', 'Combo_Product')
            ->where('type', '!=', '0');
        
        if ($itemId) {
            $parentId = $this->getItemParentId($itemId);
            if ($parentId) {
                $baseQuery->where('id', $parentId);
            } else {
                $baseQuery->where('id', $itemId);
            }
        } else {
            $baseQuery->whereNull('parent_id');
        }
        if ($itemCode) {
            $baseQuery->where('code', $itemCode);
        }
        if ($brandId) {
            $baseQuery->where('brand_id', $brandId);
        }
        if ($categoryId) {
            $baseQuery->where('category_id', $categoryId);
        }
        if ($supplierId) {
            $baseQuery->where('supplier_id', $supplierId);
        }
        if ($genericName) {
            $baseQuery->where('generic_name', $genericName);
        }
        
        $allFiltered = $baseQuery->orderBy('id')->get(['id', 'type', 'alert_quantity', 'last_three_purchase_avg', 'last_purchase_price', 'purchase_price']);
        $allIds = $allFiltered->pluck('id')->toArray();
        $stockBatch = $this->getStockQuantitiesBatch($allIds, $outletId);
        
        $filteredItems = $allFiltered->filter(function ($item) use ($stockBatch) {
            $stockQty = $stockBatch['in'][$item->id] ?? 0;
            $outQty = $stockBatch['out'][$item->id] ?? 0;
            $currentStock = $stockQty - $outQty;
            if ($item->type == 'Variation_Product' || $item->type == 'Medicine_Product') {
                return true;
            }
            return $currentStock < $item->alert_quantity;
        });
        
        $lowStockIds = $filteredItems->pluck('id')->values()->toArray();
        $recordsTotal = count($lowStockIds);
        $recordsFiltered = $recordsTotal;
        $pageIds = array_slice($lowStockIds, $start, $length);
        
        if (empty($pageIds)) {
            return [
                'data' => [],
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'alertSum' => 0,
            ];
        }
        
        $orderSql = 'FIELD(id, ' . implode(',', array_map('intval', $pageIds)) . ')';
        $items = Item::with(['category', 'brand', 'supplier', 'purchaseUnit', 'saleUnit'])
            ->whereIn('id', $pageIds)
            ->orderByRaw($orderSql)
            ->get();
        
        $variationParentIds = $items->where('type', 'Variation_Product')->pluck('id')->toArray();
        $variationsByParent = $this->getItemVariationsBatch($variationParentIds, $outletId, $priceCol);
        $medicineExpiryItemIds = $items->filter(fn ($i) => $i->type === 'Medicine_Product' && ($i->expiry_date_maintain ?? '') === 'Yes')->pluck('id')->toArray();
        $expiryByItem = $this->getMedicineExpiryDataBatch($medicineExpiryItemIds, $outletId);
        
        $data = [];
        $alertQtySum = 0;
        $itemStockAlertCls = 'stock-alert-color';
        
        foreach ($items as $key => $item) {
            $subArray = [];
            $generalStock = 0;
            $purchasePriceSum = 0;
            $purchaseUnitSum = 0;
            $saleUnitSum = 0;
            
            $stockQty = $stockBatch['in'][$item->id] ?? 0;
            $outQty = $stockBatch['out'][$item->id] ?? 0;
            
            if ($item->type != 'Variation_Product') {
                if (($stockQty - $outQty) < $item->alert_quantity) {
                    $alertQtySum++;
                }
            }
            
            if (in_array($item->type, ['General_Product', 'Installment_Product']) || 
                ($item->type == 'Medicine_Product' && $item->expiry_date_maintain == 'No')) {
                $generalStock = $stockQty - $outQty;
                $conversionRate = (int)$item->conversion_rate > 0 ? (int)$item->conversion_rate : 1;
                $itemPrice = $this->getEffectivePrice($item, $priceCol);
                $genConvertedPrice = $itemPrice / $conversionRate;
                $purchasePriceSum = $genConvertedPrice * $generalStock;
                if ($item->unit_type == '1') {
                    $saleUnitSum = (int)$generalStock;
                } elseif ($item->unit_type == '2') {
                    $purchaseUnitSum = (int)((int)$generalStock / $conversionRate);
                    $saleUnitSum = ((int)$generalStock) % $conversionRate;
                }
            }
            
            $subArray[] = '<div class="' . $itemStockAlertCls . '">' . ($start + $key + 1) . '</div>';
            $itemName = strlen($item->name) > 50 ? substr($item->name, 0, 50) . '...' : $item->name;
            $subArray[] = '<div class="' . $itemStockAlertCls . '">' . e($itemName) . ' (' . e($item->code) . ')</div>';
            $subArray[] = '<div class="' . $itemStockAlertCls . '">' . e($item->category->name ?? '') . '</div>';
            $variation = '<div class="' . $itemStockAlertCls . '">';
            
            if ($item->type == 'Variation_Product') {
                $conversionRate = (int)$item->conversion_rate > 0 ? (int)$item->conversion_rate : 1;
                $variations = $variationsByParent[$item->id] ?? [];
                foreach ($variations as $variationData) {
                    $variationStock = $variationData['stock_in'] - $variationData['stock_out'];
                    $generalStock += $variationStock;
                    $variationAlert = (int)$variationData['alert_quantity'];
                    $varPrice = $this->getEffectivePrice($variationData, $priceCol);
                    $variationConvertedPrice = $varPrice / $conversionRate;
                    $purchasePriceSum += $variationConvertedPrice * $variationStock;
                    if ($variationStock < $variationAlert) {
                        $alertQtySum++;
                    }
                    if ($item->unit_type == '1') {
                        $saleUnitSum += $variationStock;
                    } elseif ($item->unit_type == '2') {
                        $purchaseUnitSum += (int)((int)$variationStock / $conversionRate);
                        $saleUnitSum += ((int)$variationStock) % $conversionRate;
                    }
                }
                $variation .= '<button type="button" class="btn btn-primary modal_trigger" data-bs-toggle="modal" data-bs-target="#exampleModal" data-id="' . $item->id . '" data-type="' . $item->type . '" data-name="' . e($item->name . '(' . $item->code . ')') . '">Show All Variation</button>';
            } elseif (in_array($item->type, ['IMEI_Product', 'Serial_Product'])) {
                $expStock = $stockQty - $outQty;
                $conversionRate = (int)$item->conversion_rate > 0 ? (int)$item->conversion_rate : 1;
                $itemPrice = $this->getEffectivePrice($item, $priceCol);
                $purchasePriceSum = ($itemPrice / $conversionRate) * $expStock;
                $purchaseUnitSum = (int)$expStock;
                $saleUnitSum = (int)$expStock;
                $variation .= '<button type="button" class="btn btn-primary modal_trigger" data-bs-toggle="modal" data-bs-target="#exampleModal" data-id="' . $item->id . '" data-type="' . $item->type . '" data-name="' . e($item->name . '(' . $item->code . ')') . '">Show All ' . ($item->type == 'IMEI_Product' ? 'IMEI' : 'Serial') . '</button>';
            } elseif ($item->type == 'Medicine_Product' && $item->expiry_date_maintain == 'Yes') {
                $conversionRate = (int)$item->conversion_rate > 0 ? (int)$item->conversion_rate : 1;
                $itemPrice = $this->getEffectivePrice($item, $priceCol);
                $purchasePriceSum = ($itemPrice / $conversionRate) * ($stockQty - $outQty);
                $expiryData = $expiryByItem[$item->id] ?? [];
                foreach ($expiryData as $expiry) {
                    $expSaleQtySum = ((int)$expiry['quantity'] / $conversionRate) * $conversionRate;
                    $generalStock += $expSaleQtySum;
                    if ($item->unit_type == '1') {
                        $saleUnitSum += (int)$expiry['quantity'];
                    } elseif ($item->unit_type == '2') {
                        $purchaseUnitSum += ((int)((int)$expiry['quantity'] / $conversionRate));
                        $saleUnitSum += ((int)$expiry['quantity'] % $conversionRate);
                    }
                }
                $variation .= '<button type="button" class="btn btn-primary modal_trigger" data-bs-toggle="modal" data-bs-target="#exampleModal" data-id="' . $item->id . '" data-type="' . $item->type . '" data-name="' . e($item->name . '(' . $item->code . ')') . '">Show All Medicine</button>';
            }
            
            $variation .= '</div>';
            $subArray[] = $variation;
            $unitType = '<div class="' . $itemStockAlertCls . '">';
            if ($item->unit_type == '1') {
                $unitType .= number_format($saleUnitSum, 2) . ' ' . ($item->saleUnit->unit_name ?? '');
            } elseif ($item->unit_type == '2') {
                $unitType .= number_format($purchaseUnitSum, 2) . ' ' . ($item->purchaseUnit->unit_name ?? '') . ' ' . number_format($saleUnitSum, 2) . ' ' . ($item->saleUnit->unit_name ?? '') . ' ';
                $unitType .= '(' . number_format($generalStock, 2) . ' ' . ($item->saleUnit->unit_name ?? '') . ')';
            }
            $unitType .= '</div>';
            $subArray[] = $unitType;
            $conversionRate = (int)$item->conversion_rate > 0 ? (int)$item->conversion_rate : 1;
            $itemPrice = $this->getEffectivePrice($item, $priceCol);
            $lpp = $itemPrice / $conversionRate;
            $subArray[] = '<div class="' . $itemStockAlertCls . '">' . number_format($lpp, 2) . '</div>';
            $totalHtml = '<div class="' . $itemStockAlertCls . '">' . number_format($purchasePriceSum, 2) . '</div>';
            $subArray[] = $totalHtml;
            $data[] = $subArray;
        }
        
        return [
            'data' => $data,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'alertSum' => $alertQtySum,
        ];
    }

    /**
     * Get data for DataTables (with server-side pagination)
     */
    public function getDataTableData(array $filters = [], int $start = 0, int $length = 10): array
    {
        $itemId = $filters['item_id'] ?? '';
        $itemCode = $filters['item_code'] ?? '';
        $brandId = $filters['brand_id'] ?? '';
        $categoryId = $filters['category_id'] ?? '';
        $supplierId = $filters['supplier_id'] ?? '';
        $genericName = $filters['generic_name'] ?? '';
        $search = trim($filters['search'] ?? '');
        $priceType = $filters['price_type'] ?? 'last_three_purchase_avg';
        $priceCol = $priceType === 'last_purchase_price' ? 'last_purchase_price' : 'last_three_purchase_avg';
        
        $outletId = session('outlet.outlet_id') ?? session('company.default_outlet_id');
        $companyId = session('company.company_id');
        
        $query = Item::with(['category', 'brand', 'supplier', 'purchaseUnit', 'saleUnit'])
            ->where('company_id', $companyId)
            ->where('enable_disable_status', 1)
            ->where('del_status', 'Live')
            ->where('type', '!=', 'Service_Product')
            ->where('type', '!=', 'Combo_Product')
            ->where('type', '!=', '0');
        
        // Apply filters
        if ($itemId) {
            $parentId = $this->getItemParentId($itemId);
            if ($parentId) {
                $query->where('id', $parentId);
            } else {
                $query->where('id', $itemId);
            }
        } else {
            $query->whereNull('parent_id');
        }
        
        if ($itemCode) {
            $query->where('code', $itemCode);
        }
        
        if ($brandId) {
            $query->where('brand_id', $brandId);
        }
        
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        
        if ($genericName) {
            $query->where('generic_name', $genericName);
        }
        
        $recordsTotal = $query->count();
        
        // Apply DataTables search (database search box)
        if ($search !== '') {
            $searchTerm = '%' . $search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('code', 'like', $searchTerm)
                    ->orWhere('generic_name', 'like', $searchTerm)
                    ->orWhereHas('category', function ($cq) use ($searchTerm) {
                        $cq->where('name', 'like', $searchTerm);
                    })
                    ->orWhereHas('brand', function ($bq) use ($searchTerm) {
                        $bq->where('name', 'like', $searchTerm);
                    })
                    ->orWhereHas('supplier', function ($sq) use ($searchTerm) {
                        $sq->where('name', 'like', $searchTerm);
                    });
            });
        }
        
        $recordsFiltered = $query->count();
        $items = $query->orderBy('id')->skip($start)->take($length)->get();
        
        $itemIds = $items->pluck('id')->toArray();
        $stockBatch = $this->getStockQuantitiesBatch($itemIds, $outletId);
        $variationParentIds = $items->where('type', 'Variation_Product')->pluck('id')->toArray();
        $variationsByParent = $this->getItemVariationsBatch($variationParentIds, $outletId, $priceCol);
        $medicineExpiryItemIds = $items->filter(fn ($i) => $i->type === 'Medicine_Product' && ($i->expiry_date_maintain ?? '') === 'Yes')->pluck('id')->toArray();
        $expiryByItem = $this->getMedicineExpiryDataBatch($medicineExpiryItemIds, $outletId);
        
        $data = [];
        $alertQtySum = 0;
        
        foreach ($items as $key => $item) {
            $subArray = [];
            $generalStock = 0;
            $purchasePriceSum = 0;
            $purchaseUnitSum = 0;
            $saleUnitSum = 0;
            $itemStockAlertCls = '';
            
            $stockQty = $stockBatch['in'][$item->id] ?? 0;
            $outQty = $stockBatch['out'][$item->id] ?? 0;
            
            // Check alert quantity
            if ($item->type != 'Variation_Product') {
                if (($stockQty - $outQty) < $item->alert_quantity) {
                    $itemStockAlertCls = 'stock-alert-color';
                    $alertQtySum++;
                }
            }
            
            // Calculate stock for General/Installment/Medicine products
            if (in_array($item->type, ['General_Product', 'Installment_Product']) || 
                ($item->type == 'Medicine_Product' && $item->expiry_date_maintain == 'No')) {
                $generalStock = $stockQty - $outQty;
                $conversionRate = (int)$item->conversion_rate > 0 ? (int)$item->conversion_rate : 1;
                $itemPrice = $this->getEffectivePrice($item, $priceCol);
                $genConvertedPrice = $itemPrice / $conversionRate;
                $purchasePriceSum = $genConvertedPrice * $generalStock;
                
                if ($item->unit_type == '1') {
                    $saleUnitSum = (int)$generalStock;
                } elseif ($item->unit_type == '2') {
                    $purchaseUnitSum = (int)((int)$generalStock / $conversionRate);
                    $saleUnitSum = ((int)$generalStock) % $conversionRate;
                }
            }
            
            // SN column (row number across pages: start + key + 1)
            $subArray[] = '<div class="' . $itemStockAlertCls . '">' . ($start + $key + 1) . '</div>';
            
            // Item name and code
            $itemName = $item->name;
            if (strlen($itemName) > 50) {
                $itemName = substr($itemName, 0, 50) . '...';
            }
            $subArray[] = '<div class="' . $itemStockAlertCls . '">' . e($itemName) . ' (' . e($item->code) . ')</div>';
            
            // Category
            $subArray[] = '<div class="' . $itemStockAlertCls . '">' . e($item->category->name ?? '') . '</div>';
            
            // Stock Segmentation
            $variation = '<div class="' . $itemStockAlertCls . '">';
            
            if ($item->type == 'Variation_Product') {
                $conversionRate = (int)$item->conversion_rate > 0 ? (int)$item->conversion_rate : 1;
                $variations = $variationsByParent[$item->id] ?? [];
                foreach ($variations as $variationData) {
                    $variationStock = $variationData['stock_in'] - $variationData['stock_out'];
                    $generalStock += $variationStock;
                    $variationAlert = (int)$variationData['alert_quantity'];
                    $varPrice = $this->getEffectivePrice($variationData, $priceCol);
                    $variationConvertedPrice = $varPrice / $conversionRate;
                    $purchasePriceSum += $variationConvertedPrice * $variationStock;
                    
                    if ($variationStock < $variationAlert) {
                        $alertQtySum++;
                    }
                    
                    if ($item->unit_type == '1') {
                        $saleUnitSum += $variationStock;
                    } elseif ($item->unit_type == '2') {
                        $purchaseUnitSum += (int)((int)$variationStock / $conversionRate);
                        $saleUnitSum += ((int)$variationStock) % $conversionRate;
                    }
                }
                $variation .= '<button type="button" class="btn btn-primary modal_trigger" data-bs-toggle="modal" data-bs-target="#exampleModal" data-id="' . $item->id . '" data-type="' . $item->type . '" data-name="' . e($item->name . '(' . $item->code . ')') . '">Show All Variation</button>';
            } elseif (in_array($item->type, ['IMEI_Product', 'Serial_Product'])) {
                $expStock = $stockQty - $outQty;
                $conversionRate = (int)$item->conversion_rate > 0 ? (int)$item->conversion_rate : 1;
                $itemPrice = $this->getEffectivePrice($item, $priceCol);
                $expConvertedPrice = $itemPrice / $conversionRate;
                $purchasePriceSum = $expConvertedPrice * $expStock;
                $purchaseUnitSum = (int)$expStock;
                $saleUnitSum = (int)$expStock;
                $variation .= '<button type="button" class="btn btn-primary modal_trigger" data-bs-toggle="modal" data-bs-target="#exampleModal" data-id="' . $item->id . '" data-type="' . $item->type . '" data-name="' . e($item->name . '(' . $item->code . ')') . '">Show All ' . ($item->type == 'IMEI_Product' ? 'IMEI' : 'Serial') . '</button>';
            } elseif ($item->type == 'Medicine_Product' && $item->expiry_date_maintain == 'Yes') {
                $conversionRate = (int)$item->conversion_rate > 0 ? (int)$item->conversion_rate : 1;
                $itemPrice = $this->getEffectivePrice($item, $priceCol);
                $purchasePriceSum = ($itemPrice / $conversionRate) * ($stockQty - $outQty);
                
                $expiryData = $expiryByItem[$item->id] ?? [];
                foreach ($expiryData as $expiry) {
                    $expSaleQtySum = ((int)$expiry['quantity'] / $conversionRate) * $conversionRate;
                    $generalStock += $expSaleQtySum;
                    
                    if ($item->unit_type == '1') {
                        $saleUnitSum += (int)$expiry['quantity'];
                    } elseif ($item->unit_type == '2') {
                        $purchaseUnitSum += ((int)((int)$expiry['quantity'] / $conversionRate));
                        $saleUnitSum += ((int)$expiry['quantity'] % $conversionRate);
                    }
                }
                $variation .= '<button type="button" class="btn btn-primary modal_trigger" data-bs-toggle="modal" data-bs-target="#exampleModal" data-id="' . $item->id . '" data-type="' . $item->type . '" data-name="' . e($item->name . '(' . $item->code . ')') . '">Show All Medicine</button>';
            }
            
            $variation .= '</div>';
            $subArray[] = $variation;
            
            // Total Stock Quantity
            $unitType = '<div class="' . $itemStockAlertCls . '">';
            if ($item->unit_type == '1') {
                $unitType .= number_format($saleUnitSum, 2) . ' ' . ($item->saleUnit->unit_name ?? '');
            } elseif ($item->unit_type == '2') {
                $unitType .= number_format($purchaseUnitSum, 2) . ' ' . ($item->purchaseUnit->unit_name ?? '') . ' ' . number_format($saleUnitSum, 2) . ' ' . ($item->saleUnit->unit_name ?? '') . ' ';
                $unitType .= '(' . number_format($generalStock, 2) . ' ' . ($item->saleUnit->unit_name ?? '') . ')';
            }
            $unitType .= '</div>';
            $subArray[] = $unitType;
            
            // LPP/PP (Last Purchase Price / Purchase Price) - based on Price Type filter
            $conversionRate = (int)$item->conversion_rate > 0 ? (int)$item->conversion_rate : 1;
            $itemPrice = $this->getEffectivePrice($item, $priceCol);
            $lpp = $itemPrice / $conversionRate;
            $subArray[] = '<div class="' . $itemStockAlertCls . '">' . formatAmount($lpp, 2) . '</div>';
            
            // Total
            $totalHtml = '<div class="' . $itemStockAlertCls . '">';
            $totalHtml .= formatAmount($purchasePriceSum, 2);
            $totalHtml .= '</div>';
            $subArray[] = $totalHtml;
            
            $data[] = $subArray;
        }
        
        $stockEvaluation = $this->getStockEvaluationOptimized($filters);
        
        return [
            'data' => $data,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'stock_value' => $stockEvaluation,
            'alertSum' => $alertQtySum,
        ];
    }

    /**
     * Optimized stock evaluation: 3–4 queries total instead of 2 per item.
     * Uses batch stock query and optional short cache for pagination.
     */
    private function getStockEvaluationOptimized(array $filters = []): array
    {
        $outletId = $filters['outlet_id'] ?? session('outlet.outlet_id') ?? session('company.default_outlet_id');
        $companyId = session('company.company_id');
        $cacheKey = 'stock_eval_' . $companyId . '_' . $outletId . '_' . md5(json_encode($filters));

        return Cache::remember($cacheKey, 30, function () use ($filters, $outletId, $companyId) {
            $priceType = $filters['price_type'] ?? 'last_three_purchase_avg';
            $priceCol = $priceType === 'last_purchase_price' ? 'last_purchase_price' : 'last_three_purchase_avg';

            $query = Item::where('company_id', $companyId)
                ->where('enable_disable_status', 1)
                ->where('del_status', 'Live')
                ->where('type', '!=', 'Service_Product')
                ->where('type', '!=', 'Combo_Product')
                ->where('type', '!=', '0')
                ->select('id', 'type', 'conversion_rate', 'unit_type', 'last_three_purchase_avg', 'last_purchase_price', 'purchase_price');

            if (!empty($filters['item_id'])) {
                $parentId = $this->getItemParentId($filters['item_id']);
                if ($parentId) {
                    $query->where('id', $parentId);
                } else {
                    $query->where('id', $filters['item_id']);
                }
            } else {
                $query->whereNull('parent_id');
            }
            if (!empty($filters['item_code'])) {
                $query->where('code', $filters['item_code']);
            }
            if (!empty($filters['brand_id'])) {
                $query->where('brand_id', $filters['brand_id']);
            }
            if (!empty($filters['category_id'])) {
                $query->where('category_id', $filters['category_id']);
            }
            if (!empty($filters['supplier_id'])) {
                $query->where('supplier_id', $filters['supplier_id']);
            }
            if (!empty($filters['generic_name'])) {
                $query->where('generic_name', $filters['generic_name']);
            }

            $items = $query->get();
            $mainIds = $items->pluck('id')->toArray();

            $stockBatch = $this->getStockQuantitiesBatch($mainIds, $outletId);
            $currentStock = [];
            foreach ($mainIds as $id) {
                $currentStock[$id] = ($stockBatch['in'][$id] ?? 0) - ($stockBatch['out'][$id] ?? 0);
            }

            $totalStockValue = 0;
            $totalStockCount = 0;

            foreach ($items as $item) {
                $stock = $currentStock[$item->id] ?? 0;
                $conversionRate = (int)($item->conversion_rate ?? 1) > 0 ? (int)$item->conversion_rate : 1;
                $itemPrice = $this->getEffectivePrice($item, $priceCol);
                if ($item->unit_type == '2') {
                    $totalStockValue += ($itemPrice / $conversionRate) * $stock;
                } else {
                    $totalStockValue += $itemPrice * $stock;
                }
                $totalStockCount += $stock;
            }

            return [
                'stock_value' => round($totalStockValue, 2),
                'stock_count' => round($totalStockCount, 2),
            ];
        });
    }

    /**
     * Get effective purchase price for an item with fallbacks.
     * Order: selected price column -> last_purchase_price -> purchase_price.
     * Works with Item objects and variation arrays.
     */
    private function getEffectivePrice($item, string $priceCol): float
    {
        $get = function (string $key) use ($item) {
            if (is_array($item)) {
                return $item[$key] ?? 0;
            }
            return $item->{$key} ?? 0;
        };

        $price = (float) $get($priceCol);
        if ($price <= 0) {
            $price = (float) $get('last_purchase_price');
        }
        if ($price <= 0) {
            $price = (float) $get('purchase_price');
        }
        return $price;
    }

    /**
     * Get stock evaluation (total stock value and count)
     * Properly handles unit_type (single vs double unit) and conversion_rate
     */
    public function getStockEvaluation(array $filters = []): array
    {
        return $this->getStockEvaluationOptimized($filters);
    }

    /**
     * Get stock segmentation of an item
     */
    public function getStockSegmentationOfItem(int $itemId, string $itemType): string
    {
        $outletId = session('outlet.outlet_id');
        $html = '';
        
        // Get the main item to check unit_type and unit relationships
        $item = Item::with(['purchaseUnit', 'saleUnit'])->find($itemId);
        
        if (in_array($itemType, ['IMEI_Product', 'Serial_Product'])) {
            $imeiData = $this->getIMEINumber($itemId);
            $imeiSerials = $imeiData['imei_numbers'] ?? [];
            $sn = 1;
            foreach ($imeiSerials as $imeiSerial) {
                $html .= '<tr>';
                $html .= '<td>' . $sn . '</td>';
                $html .= '<td>' . e($imeiSerial) . '</td>';
                $html .= '</tr>';
                $sn++;
            }
        } elseif ($itemType == 'Medicine_Product') {
            $expiryData = $this->getMedicineExpiryData($itemId, $outletId);
            $sn = 1;
            foreach ($expiryData as $expiry) {
                $quantity = $expiry['quantity'];
                $stockDisplay = $this->formatStockDisplay($quantity, $item);
                
                $html .= '<tr>';
                $html .= '<td>' . $sn . '</td>';
                $html .= '<td>' . e($expiry['expiry_date']) . '</td>';
                $html .= '<td>' . $stockDisplay . '</td>';
                $html .= '</tr>';
                $sn++;
            }
        } elseif ($itemType == 'Variation_Product') {
            $variations = $this->getItemVariations($itemId);
            $sn = 1;
            foreach ($variations as $variation) {
                $stock = $variation['stock_in'] - $variation['stock_out'];
                $conversionRate = (int)($variation['conversion_rate'] ?? 1) > 0 ? (int)$variation['conversion_rate'] : 1;
                $lpp = $this->getEffectivePrice($variation, 'last_three_purchase_avg') / $conversionRate;
                $total = $lpp * $stock;
                
                // Get variation item for unit info
                $variationItem = Item::with(['purchaseUnit', 'saleUnit'])->find($variation['id']);
                $stockDisplay = $this->formatStockDisplay($stock, $variationItem);
                
                $html .= '<tr>';
                $html .= '<td>' . $sn . '</td>';
                $html .= '<td>' . e($variation['name']) . '</td>';
                $html .= '<td>' . $stockDisplay . '</td>';
                $html .= '<td>' . number_format($lpp, 2) . '</td>';
                $html .= '<td>' . number_format($total, 2) . '</td>';
                $html .= '</tr>';
                $sn++;
            }
        }
        
        return $html;
    }
    
    /**
     * Format stock display based on unit_type
     */
    private function formatStockDisplay($stock, $item)
    {
        if (!$item) {
            return number_format($stock, 2);
        }
        
        $unitType = $item->unit_type ?? '1';
        $conversionRate = (int)($item->conversion_rate ?? 1) > 0 ? (int)$item->conversion_rate : 1;
        $purchaseUnitName = $item->purchaseUnit->unit_name ?? '';
        $saleUnitName = $item->saleUnit->unit_name ?? '';
        
        if ($unitType == '2') {
            // Double unit: Purchase Unit + Sale Unit
            // Format: "11 Packet 5 PCS"
            $purchaseUnit = (int)((int)$stock / $conversionRate);
            $saleUnit = ((int)$stock) % $conversionRate;
            
            $display = '';
            if ($purchaseUnit > 0) {
                $display .= number_format($purchaseUnit, 2) . ' ' . $purchaseUnitName;
            }
            if ($saleUnit > 0) {
                if ($purchaseUnit > 0) {
                    $display .= ' ';
                }
                $display .= number_format($saleUnit, 2) . ' ' . $saleUnitName;
            }
            // If both are 0, show 0 in sale unit
            if ($purchaseUnit == 0 && $saleUnit == 0) {
                $display = number_format(0, 2) . ' ' . $saleUnitName;
            }
            
            return $display;
        } else {
            // Single unit: Sale Unit only
            return number_format($stock, 2) . ' ' . $saleUnitName;
        }
    }

    /**
     * Helper methods
     */
    private function getItemParentId($itemId)
    {
        $item = Item::find($itemId);
        return $item && $item->parent_id ? $item->parent_id : null;
    }

    private function getStockInQuantity($itemId, $outletId)
    {
        return DB::table('view_stock_detail')
            ->where('item_id', $itemId)
            ->where('type', 1)
            ->where('outlet_id', $outletId)
            ->sum('stock_quantity') ?? 0;
    }

    private function getStockOutQuantity($itemId, $outletId)
    {
        return DB::table('view_stock_detail')
            ->where('item_id', $itemId)
            ->where('type', 2)
            ->where('outlet_id', $outletId)
            ->sum('stock_quantity') ?? 0;
    }

    /**
     * Get stock in/out quantities for multiple items in one query (for performance).
     * Returns ['in' => [item_id => quantity], 'out' => [item_id => quantity]]
     */
    public function getStockQuantitiesBatch(array $itemIds, $outletId): array
    {
        if (empty($itemIds)) {
            return ['in' => [], 'out' => []];
        }
        $rows = DB::table('view_stock_detail')
            ->whereIn('item_id', $itemIds)
            ->where('outlet_id', $outletId)
            ->selectRaw('item_id, type, SUM(stock_quantity) as total')
            ->groupBy('item_id', 'type')
            ->get();
        $in = [];
        $out = [];
        foreach ($rows as $r) {
            if ((int) $r->type === 1) {
                $in[$r->item_id] = (float) $r->total;
            } else {
                $out[$r->item_id] = (float) $r->total;
            }
        }
        return ['in' => $in, 'out' => $out];
    }

    private function getItemVariations($itemId)
    {
        $variations = Item::where('parent_id', $itemId)
            ->where('type', '0')
            ->where('del_status', 'Live')
            ->get();
        
        $result = [];
        $outletId = session('outlet.outlet_id') ?? session('company.default_outlet_id');
        
        foreach ($variations as $variation) {
            $stockIn = $this->getStockInQuantity($variation->id, $outletId);
            $stockOut = $this->getStockOutQuantity($variation->id, $outletId);
            
            $result[] = [
                'id' => $variation->id,
                'name' => $variation->name,
                'code' => $variation->code,
                'stock_in' => $stockIn,
                'stock_out' => $stockOut,
                'alert_quantity' => $variation->alert_quantity,
                'last_three_purchase_avg' => $variation->last_three_purchase_avg,
                'last_purchase_price' => $variation->last_purchase_price ?? $variation->last_three_purchase_avg,
                'purchase_price' => $variation->purchase_price,
                'conversion_rate' => $variation->conversion_rate,
                'unit_type' => $variation->unit_type,
            ];
        }
        
        return $result;
    }

    /**
     * Get variations and their stock for multiple parent items in batch (for performance).
     * Returns [parent_id => [variation rows same as getItemVariations]]
     */
    private function getItemVariationsBatch(array $parentIds, $outletId, string $priceCol): array
    {
        if (empty($parentIds)) {
            return [];
        }
        $variations = Item::whereIn('parent_id', $parentIds)
            ->where('type', '0')
            ->where('del_status', 'Live')
            ->get();
        if ($variations->isEmpty()) {
            return array_fill_keys($parentIds, []);
        }
        $variationIds = $variations->pluck('id')->toArray();
        $stockBatch = $this->getStockQuantitiesBatch($variationIds, $outletId);
        $result = array_fill_keys($parentIds, []);
        foreach ($variations as $v) {
            $stockIn = $stockBatch['in'][$v->id] ?? 0;
            $stockOut = $stockBatch['out'][$v->id] ?? 0;
            $result[$v->parent_id][] = [
                'id' => $v->id,
                'name' => $v->name,
                'code' => $v->code,
                'stock_in' => $stockIn,
                'stock_out' => $stockOut,
                'alert_quantity' => $v->alert_quantity,
                'last_three_purchase_avg' => $v->last_three_purchase_avg,
                'last_purchase_price' => $v->last_purchase_price ?? $v->last_three_purchase_avg,
                'purchase_price' => $v->purchase_price,
                'conversion_rate' => $v->conversion_rate,
                'unit_type' => $v->unit_type,
            ];
        }
        return $result;
    }

    private function getMedicineExpiryData($itemId, $outletId)
    {
        return DB::table('view_stock_detail')
            ->where('item_id', $itemId)
            ->where('type', 1)
            ->where('outlet_id', $outletId)
            ->whereNotNull('expiry_imei_serial')
            ->where('expiry_imei_serial', '!=', '')
            ->select('expiry_imei_serial as expiry_date', DB::raw('SUM(stock_quantity) as quantity'))
            ->groupBy('expiry_imei_serial')
            ->get()
            ->map(function ($item) {
                return [
                    'expiry_date' => $item->expiry_date,
                    'quantity' => $item->quantity
                ];
            })
            ->toArray();
    }

    /**
     * Get medicine expiry data for multiple items in one query (for performance).
     * Returns [item_id => [['expiry_date' => ..., 'quantity' => ...], ...]]
     */
    private function getMedicineExpiryDataBatch(array $itemIds, $outletId): array
    {
        if (empty($itemIds)) {
            return [];
        }
        $rows = DB::table('view_stock_detail')
            ->whereIn('item_id', $itemIds)
            ->where('type', 1)
            ->where('outlet_id', $outletId)
            ->whereNotNull('expiry_imei_serial')
            ->where('expiry_imei_serial', '!=', '')
            ->select('item_id', 'expiry_imei_serial as expiry_date', DB::raw('SUM(stock_quantity) as quantity'))
            ->groupBy('item_id', 'expiry_imei_serial')
            ->get();
        $result = array_fill_keys($itemIds, []);
        foreach ($rows as $r) {
            $result[$r->item_id][] = [
                'expiry_date' => $r->expiry_date,
                'quantity' => $r->quantity
            ];
        }
        return $result;
    }




    // ================================ IMEI/Serial number related methods ================================

    /**
     * Get IMEI/Serial numbers for multiple items in one query (for POS batch loading).
     * Returns [item_id => ['imei_numbers' => [...]]]. Use getStockQuantitiesBatch for stock.
     *
     * @param array $itemIds
     * @param int|string $outletId
     * @return array
     */
    public function getIMEINumbersBatch(array $itemIds, $outletId): array
    {
        if (empty($itemIds)) {
            return [];
        }
        $out = DB::table('view_stock_detail as st')
            ->select('st.item_id', 'st.expiry_imei_serial')
            ->where('st.type', 1)
            ->where('st.outlet_id', $outletId)
            ->whereIn('st.item_id', $itemIds)
            ->where('st.expiry_imei_serial', '!=', '')
            ->whereNotNull('st.expiry_imei_serial')
            ->whereNotExists(function ($query) use ($outletId) {
                $query->select(DB::raw(1))
                    ->from('view_stock_detail as st2')
                    ->whereColumn('st2.item_id', 'st.item_id')
                    ->whereColumn('st2.expiry_imei_serial', 'st.expiry_imei_serial')
                    ->where('st2.type', 2)
                    ->where('st2.outlet_id', $outletId)
                    ->where('st2.expiry_imei_serial', '!=', '');
            })
            ->get();
        $result = array_fill_keys($itemIds, ['imei_numbers' => []]);
        foreach ($out as $r) {
            $result[$r->item_id]['imei_numbers'][] = $r->expiry_imei_serial;
        }
        return $result;
    }

    /**
     * Get medicine stock (expiry + quantity) for multiple items in one query (for POS batch loading).
     * Returns [item_id => [['expiry_imei_serial' => x, 'stock_quantity' => y], ...]]
     *
     * @param array $itemIds
     * @param int|string $outletId
     * @return array
     */
    public function getMedicineStockBatch(array $itemIds, $outletId): array
    {
        if (empty($itemIds)) {
            return [];
        }
        $rows = DB::table('view_stock_detail2 as s')
            ->select('s.item_id', 's.expiry_imei_serial', DB::raw('COALESCE(SUM(s.stock_quantity), 0) as stock_quantity'))
            ->where('s.outlet_id', $outletId)
            ->whereIn('s.item_id', $itemIds)
            ->groupBy('s.item_id', 's.expiry_imei_serial')
            ->orderBy('s.expiry_imei_serial')
            ->get();
        $result = array_fill_keys($itemIds, []);
        foreach ($rows as $r) {
            $result[$r->item_id][] = [
                'expiry_imei_serial' => $r->expiry_imei_serial,
                'stock_quantity' => $r->stock_quantity,
            ];
        }
        return $result;
    }

    /**
     * Get IMEI/Serial numbers and stock for a product
     * Returns array with imei_numbers (array) and stock (calculated)
     * 
     * @param int $item_id
     * @return array|false
     */
    public function getIMEINumber($item_id)
    {
        $outlet_id = session('outlet.outlet_id');
    
        // Get IMEI/Serial numbers as individual rows
        $imeiNumbers = DB::table('view_stock_detail as st')
            ->select('st.expiry_imei_serial')
            ->where('st.item_id', $item_id)
            ->where('st.type', 1)
            ->where('st.expiry_imei_serial', '!=', '')
            ->where('st.outlet_id', $outlet_id)
            ->whereNotIn('st.expiry_imei_serial', function($query) use ($item_id, $outlet_id) {
                $query->select('st2.expiry_imei_serial')
                    ->from('view_stock_detail as st2')
                    ->where('st2.item_id', $item_id)
                    ->where('st2.type', 2)
                    ->where('st2.expiry_imei_serial', '!=', '')
                    ->where('st2.outlet_id', $outlet_id);
            })
            ->pluck('expiry_imei_serial')
            ->toArray();
    
        // Get stock quantities
        $result = DB::table('items as p')
            ->select([
                DB::raw("(
                    SELECT IFNULL(SUM(st3.stock_quantity), 0)
                    FROM view_stock_detail st3
                    WHERE p.id = st3.item_id
                    AND st3.type = 1
                    AND st3.outlet_id = '$outlet_id'
                ) as stock_qty"),
    
                DB::raw("(
                    SELECT IFNULL(SUM(st4.stock_quantity), 0)
                    FROM view_stock_detail st4
                    WHERE p.id = st4.item_id
                    AND st4.type = 2
                    AND st4.outlet_id = '$outlet_id'
                ) as out_qty"),
            ])
            ->where('p.id', $item_id)
            ->where('p.company_id', session('company.company_id'))
            ->where('p.del_status', 'Live')
            ->first();
    
        if ($result) {
            $stock = (float)$result->stock_qty - (float)$result->out_qty;
            return [
                'imei_numbers' => $imeiNumbers, // Array format for easy IndexedDB handling
                'stock' => $stock
            ];
        }
    
        return [
            'imei_numbers' => [],
            'stock' => 0
        ];
    }


    /**
     * Get stock for a product
     * 
     * @param int $item_id
     * @return float
     */
    public function getStock($item_id)
    {
        $outlet_id = session('outlet.outlet_id');
        $result = DB::table('items as p')
            ->select([
                'p.name as item_name',

                // all IMEI / serial
                DB::raw("(
                    SELECT GROUP_CONCAT(st.expiry_imei_serial SEPARATOR '||')
                    FROM view_stock_detail st
                    WHERE p.id = st.item_id
                        AND st.type = 1
                        AND st.expiry_imei_serial != ''
                        AND st.outlet_id = '{$outlet_id}'
                        AND st.expiry_imei_serial NOT IN (
                            SELECT st2.expiry_imei_serial
                            FROM view_stock_detail st2
                            WHERE st2.item_id = p.id
                                AND st2.type = 2
                                AND st2.expiry_imei_serial != ''
                                AND st2.outlet_id = '{$outlet_id}'
                        )
                ) as allimei"),

                // stock qty
                DB::raw("(
                    SELECT IFNULL(SUM(st3.stock_quantity), 0)
                    FROM view_stock_detail st3
                    WHERE p.id = st3.item_id
                        AND st3.type = 1
                        AND st3.outlet_id = '{$outlet_id}'
                ) as stock_qty"),

                // out qty
                DB::raw("(
                    SELECT IFNULL(SUM(st4.stock_quantity), 0)
                    FROM view_stock_detail st4
                    WHERE p.id = st4.item_id
                        AND st4.type = 2
                        AND st4.outlet_id = '{$outlet_id}'
                ) as out_qty"),
            ])
            ->where('p.id', $item_id)
            ->where('p.company_id', session('company.company_id'))
            ->where('p.del_status', 'Live')
            ->first();

            if ($result) {
                return (float)$result->stock_qty - (float)$result->out_qty;
            }
            
            return 0;
    }


    public function getMedicineStock($item_id)
    {
        $outlet_id = session('outlet.outlet_id');
        $result = DB::table('view_stock_detail2 as s')
        ->select(
            's.item_id',
            's.expiry_imei_serial',
            DB::raw('COALESCE(SUM(s.stock_quantity), 0) as stock_quantity')
        )
        ->where('s.outlet_id', $outlet_id)
        ->where('s.item_id', $item_id)
        ->groupBy('s.item_id', 's.expiry_imei_serial')
        ->orderBy('s.expiry_imei_serial')
        ->get();

        return $result;
    }


}

