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
        return $this->model->create($data);
    }

    /**
     * Update an item
     */
    public function update(Item $item, array $data): bool
    {
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

        $query = $this->model->where('items.del_status', 'Live')
            ->where('items.type',  '!=', '0')
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
                'code' => $item->code,
                'type' => str_replace('_', ' ', $item->type),
                'category_id' => $item->category_name,
                'purchase_price' => formatAmount($item->purchase_price),
                'sale_price' => formatAmount($item->sale_price),
                'sale_price_raw' => $item->sale_price,
                'whole_sale_price' => formatAmount($item->whole_sale_price ?? 0),
                'mrp_price' => formatAmount($item->mrp_price ?? 0),
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

