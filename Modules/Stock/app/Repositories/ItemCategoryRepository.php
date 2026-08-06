<?php

namespace Modules\Stock\Repositories;

use Modules\Stock\Models\ItemCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ItemCategoryRepository
{
    protected $model;

    public function __construct(ItemCategory $model)
    {
        $this->model = $model;
    }

    /**
     * Get all item categories
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->get();
    }

    /**
     * Get item categories with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with(['creator', 'updater'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find item category by ID
     */
    public function find(int $id): ?ItemCategory
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->find($id);
    }

    /**
     * Find item category by ID with relationships
     */
    public function findWithRelations(int $id): ?ItemCategory
    {
        return $this->model->where('del_status', 'Live')
            ->with(['creator', 'updater'])
            ->find($id);
    }

    /**
     * Create a new item category
     */
    public function create(array $data): ItemCategory
    {
        return $this->model->create($data);
    }

    /**
     * Update a item category
     */
    public function update(ItemCategory $itemCategory, array $data): bool
    {
        return $itemCategory->update($data);
    }

    /**
     * Delete a item category (soft delete)
     */
    public function delete(ItemCategory $itemCategory): bool
    {
        return $itemCategory->update(['del_status' => 'Deleted']);
    }

    /**
     * Get active item categories
     */
    public function getActiveItemCategories(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * Get inactive item categories
     */
    public function getInactiveItemCategories(): Collection
    {
        return $this->model->where('del_status', 'Deleted')
            ->where('company_id', session('company.company_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * Search item categories by name
     */
    public function searchByName(string $name): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('name', 'like', "%{$name}%")
            ->orderBy('name')
            ->get();
    }

    /**
     * Get item categories by status
     */
    public function getByStatus(bool $status): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('status', $status)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get item categories created by a specific user
     */
    public function getByCreator(int $userId): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get item category count by status
     */
    public function getCountByStatus(bool $status): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('status', $status)
            ->count();
    }

    /**
     * Get total item category count
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();
    }

    /**
     * Find item category by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?ItemCategory
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
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        $length = $params['length'] ?? 10;
        $start = $params['start'] ?? 0;
        $search = $params['search'] ?? '';

        $query = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();

        $filteredCount = $query->count();

        $categories = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $categories->map(function ($category, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $category->id,
                'name' => $category->name,
                'description' => truncateText($category->description),
                'encrypted_id' => $category->encrypted_id
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
     * Get categories for sorting (ordered by sort_id, then by id)
     */
    public function getCategoriesForSorting(): Collection
    {
        $companyId = session('company.company_id');
        if (!$companyId) {
            return collect([]);
        }
        
        return $this->model->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderByRaw('COALESCE(sort_id, 999999) ASC')
            ->orderBy('id', 'ASC')
            ->get();
    }

    /**
     * Update sort order for categories
     */
    public function updateSortOrder(array $sortedIds): bool
    {
        foreach ($sortedIds as $index => $categoryId) {
            $this->model->where('id', $categoryId)
                ->where('company_id', session('company.company_id'))
                ->update(['sort_id' => $index + 1]);
        }
        return true;
    }
}
