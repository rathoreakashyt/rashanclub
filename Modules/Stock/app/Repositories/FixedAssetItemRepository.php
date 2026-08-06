<?php

namespace Modules\Stock\Repositories;

use Modules\Stock\Models\FixedAssetItem;
use Illuminate\Database\Eloquent\Collection;

class FixedAssetItemRepository
{
    protected $model;

    public function __construct(FixedAssetItem $model)
    {
        $this->model = $model;
    }

    /**
     * Get all fixed asset items for a company
     */
    public function all(int $companyId): Collection
    {
        return $this->model
            ->live()
            ->forCompany($companyId)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get fixed asset items with pagination and search for DataTable
     */
    public function getDataTableData(int $companyId, int $start = 0, int $length = 10, string $search = ''): array
    {
        $baseQuery = $this->model
            ->live()
            ->forCompany($companyId);

        $query = clone $baseQuery;

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $baseQuery->count();
        $filteredCount = $query->count();

        $items = $query
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'data' => $items,
        ];
    }

    /**
     * Find fixed asset item by ID
     */
    public function find(int $id): ?FixedAssetItem
    {
        return $this->model
            ->live()
            ->find($id);
    }

    /**
     * Find fixed asset item by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?FixedAssetItem
    {
        try {
            $decryptedId = decrypt($encryptedId);
            return $this->find($decryptedId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new fixed asset item
     */
    public function create(array $data): FixedAssetItem
    {
        return $this->model->create($data);
    }

    /**
     * Update a fixed asset item
     */
    public function update(FixedAssetItem $item, array $data): bool
    {
        return $item->update($data);
    }

    /**
     * Soft delete a fixed asset item
     */
    public function delete(FixedAssetItem $item): bool
    {
        return $item->update(['del_status' => 'Deleted']);
    }
}

