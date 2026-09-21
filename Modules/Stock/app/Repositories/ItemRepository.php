<?php

namespace Modules\Stock\Repositories;

use Modules\Stock\Models\Item;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ItemRepository
{
    protected $model;

    public function __construct(Item $model)
    {
        $this->model = $model;
    }

    /**
     * Get all items
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get items with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find item by ID
     */
    public function find(int $id): ?Item
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->find($id);
    }

    /**
     * Find item by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Item
    {
        try {
            $id = decrypt($encryptedId);
            $id = (int) $id;
            return $this->find($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Find item by code
     */
    public function findByCode(string $code): ?Item
    {
        return $this->model->where('code', $code)
            ->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->first();
    }

    /**
     * Create a new item
     */
    public function create(array $data): Item
    {
        // Safety net: ensure expiry_date_maintain is always integer
        if (array_key_exists('expiry_date_maintain', $data) && is_string($data['expiry_date_maintain'])) {
            $data['expiry_date_maintain'] = in_array(strtolower($data['expiry_date_maintain']), ['yes', 'true', '1']) ? 1 : 0;
        }
        return $this->model->create($data);
    }

    /**
     * Update an item
     */
    public function update(Item $item, array $data): bool
    {
        // Safety net: ensure expiry_date_maintain is always integer
        if (array_key_exists('expiry_date_maintain', $data) && is_string($data['expiry_date_maintain'])) {
            $data['expiry_date_maintain'] = in_array(strtolower($data['expiry_date_maintain']), ['yes', 'true', '1']) ? 1 : 0;
        }
        return $item->update($data);
    }

    /**
     * Delete an item (soft delete)
     */
    public function delete(Item $item): bool
    {
        return $item->update(['del_status' => 'Deleted']);
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        $length = $params['length'] ?? 10;
        $start = $params['start'] ?? 0;
        $search = $params['search'] ?? '';

        // For Variation_Product parents, show children with prices.
        // For other types, show as-is. Exclude orphaned children (type='0' without valid parent).
        $query = $this->model->where('items.del_status', 'Live')
            ->where('items.company_id', session('company.company_id'))
            ->where(function ($q) {
                // Show non-variation items (not type '0')
                $q->where('items.type', '!=', '0');
            })
            ->leftJoin('item_categories', 'items.category_id', '=', 'item_categories.id')
            ->select('items.*', 'item_categories.name as category_name');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('items.name', 'like', "%{$search}%")
                  ->orWhere('items.code', 'like', "%{$search}%")
                  ->orWhere('items.type', 'like', "%{$search}%")
                  ->orWhere('item_categories.name', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('type', '!=', '0')
            ->count();

        $filteredCount = $query->count();

        $items = $query->orderBy('items.id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        // For variation parent items, get min/max prices and total stock from children
        $variationChildrenMap = [];
        $parentIds = $items->where('type', 'Variation_Product')->pluck('id')->toArray();
        if (!empty($parentIds)) {
            $children = $this->model->where('parent_id', $parentIds)
                ->where('del_status', 'Live')
                ->get();
            foreach ($children as $child) {
                $pid = $child->parent_id;
                if (!isset($variationChildrenMap[$pid])) {
                    $variationChildrenMap[$pid] = [
                        'min_sale' => null, 'max_sale' => null,
                        'min_purchase' => null, 'min_mrp' => null,
                        'min_wholesale' => null,
                        'total_stock' => 0, 'has_prices' => false
                    ];
                }
                $sale = (float) ($child->sale_price ?? 0);
                $purchase = (float) ($child->purchase_price ?? 0);
                $mrp = (float) ($child->mrp_price ?? 0);
                $wholesale = (float) ($child->whole_sale_price ?? 0);
                if ($sale > 0 || $purchase > 0) {
                    $variationChildrenMap[$pid]['has_prices'] = true;
                    if ($sale > 0 && ($variationChildrenMap[$pid]['min_sale'] === null || $sale < $variationChildrenMap[$pid]['min_sale'])) {
                        $variationChildrenMap[$pid]['min_sale'] = $sale;
                    }
                    if ($sale > 0 && ($variationChildrenMap[$pid]['max_sale'] === null || $sale > $variationChildrenMap[$pid]['max_sale'])) {
                        $variationChildrenMap[$pid]['max_sale'] = $sale;
                    }
                    if ($purchase > 0 && ($variationChildrenMap[$pid]['min_purchase'] === null || $purchase < $variationChildrenMap[$pid]['min_purchase'])) {
                        $variationChildrenMap[$pid]['min_purchase'] = $purchase;
                    }
                    if ($mrp > 0 && ($variationChildrenMap[$pid]['min_mrp'] === null || $mrp < $variationChildrenMap[$pid]['min_mrp'])) {
                        $variationChildrenMap[$pid]['min_mrp'] = $mrp;
                    }
                    if ($wholesale > 0 && ($variationChildrenMap[$pid]['min_wholesale'] === null || $wholesale < $variationChildrenMap[$pid]['min_wholesale'])) {
                        $variationChildrenMap[$pid]['min_wholesale'] = $wholesale;
                    }
                }
                $variationChildrenMap[$pid]['total_stock'] += (float) ($child->stock_quantity ?? 0);
            }
        }

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $items->map(function ($item, $index) use ($startingNumber, $variationChildrenMap) {
            $isVariation = $item->type === 'Variation_Product';
            $childData = $variationChildrenMap[$item->id] ?? null;

            // For variation products, show child prices if parent has 0
            $purchasePrice = (float) ($item->purchase_price ?? 0);
            $salePrice = (float) ($item->sale_price ?? 0);
            $mrpPrice = (float) ($item->mrp_price ?? 0);
            $wholeSalePrice = (float) ($item->whole_sale_price ?? 0);

            if ($isVariation && $childData && $childData['has_prices']) {
                if ($salePrice == 0 && $childData['min_sale'] !== null) {
                    $salePrice = $childData['min_sale'];
                }
                if ($purchasePrice == 0 && $childData['min_purchase'] !== null) {
                    $purchasePrice = $childData['min_purchase'];
                }
                if ($mrpPrice == 0 && $childData['min_mrp'] !== null) {
                    $mrpPrice = $childData['min_mrp'];
                }
                if ($wholeSalePrice == 0 && $childData['min_wholesale'] !== null) {
                    $wholeSalePrice = $childData['min_wholesale'];
                }
            }

            return [
                'id' => $startingNumber - $index,
                'actual_id' => $item->id,
                'name' => $item->name . ' (' . $item->code . ')',
                'code' => $item->code,
                'type' => str_replace('_', ' ', $item->type),
                'category_id' => $item->category_name ?? '-',
                'purchase_price' => formatAmount($purchasePrice),
                'sale_price' => formatAmount($salePrice),
                'sale_price_raw' => $salePrice,
                'whole_sale_price' => formatAmount($wholeSalePrice),
                'mrp_price' => formatAmount($mrpPrice),
                'enable_disable_status' => $item->enable_disable_status ?? 'Disable',
                'photo' => $item->photo,
                'encrypted_id' => $item->encrypted_id
            ];
        });

        return [
            'draw' => $params['draw'] ?? 1,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $transformedData
        ];
    }

    /**
     * Get data for Bulk Update DataTables
     */
    public function getBulkUpdateDataTableData(array $params): array
    {
        $length = $params['length'] ?? 10;
        $start = $params['start'] ?? 0;
        $search = $params['search'] ?? '';

        $query = $this->model->where('items.del_status', 'Live')
            ->where('items.type',  '!=', '0')
            ->whereNull('items.parent_id') // Only parent items, exclude variations
            ->where('items.company_id', session('company.company_id'))
            ->join('item_categories', 'items.category_id', '=', 'item_categories.id')
            ->select('items.*', 'item_categories.name as category_name');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('items.name', 'like', "%{$search}%")
                  ->orWhere('items.code', 'like', "%{$search}%")
                  ->orWhere('items.type', 'like', "%{$search}%")
                  ->orWhere('item_categories.name', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $this->model->where('del_status', 'Live')
            ->whereNull('parent_id')
            ->where('company_id', session('company.company_id'))
            ->count();

        $filteredCount = $query->count();

        $items = $query->orderBy('items.id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $items->map(function ($item, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $item->id,
                'name' => $item->name . ' (' . $item->code . ')',
                'type' => str_replace('_', ' ', $item->type),
                'category_id' => $item->category_name,
                'purchase_price' => numberFormatWithCurrency($item->purchase_price),
                'purchase_price_raw' => $item->purchase_price,
                'sale_price' => numberFormatWithCurrency($item->sale_price),
                'sale_price_raw' => $item->sale_price,
                'whole_sale_price' => $item->whole_sale_price ?? 0,
                'mrp_price' => $item->mrp_price ?? 0,
                'enable_disable_status' => $item->enable_disable_status ?? 'Disable',
                'photo' => $item->photo,
                'encrypted_id' => $item->encrypted_id
            ];
        });

        return [
            'draw' => $params['draw'] ?? 1,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $transformedData
        ];
    }

    /**
     * Get items by type
     */
    public function getByType(string $type): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('type', $type)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get parent items (for variation products)
     */
    public function getParentItems(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->whereNull('parent_id')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get child items by parent ID
     */
    public function getChildItems(int $parentId): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('parent_id', $parentId)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get total item count
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();
    }

    /**
     * Check if code exists
     */
    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $query = $this->model->where('code', $code)
            ->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'));
        
        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }
        
        return $query->exists();
    }
}

