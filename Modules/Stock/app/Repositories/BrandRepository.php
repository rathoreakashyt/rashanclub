<?php

namespace Modules\Stock\Repositories;

use Modules\Stock\Models\Brand;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BrandRepository
{
    protected $model;

    public function __construct(Brand $model)
    {
        $this->model = $model;
    }

    /**
     * Get all brands
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get brands with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find brand by ID
     */
    public function find(int $id): ?Brand
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->find($id);
    }

    /**
     * Find brand by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Brand
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
     * Create a new brand
     */
    public function create(array $data): Brand
    {
        return $this->model->create($data);
    }

    /**
     * Update a brand
     */
    public function update(Brand $brand, array $data): bool
    {
        return $brand->update($data);
    }

    /**
     * Delete a brand (soft delete)
     */
    public function delete(Brand $brand): bool
    {
        return $brand->update(['del_status' => 'Deleted']);
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

        $brands = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $brands->map(function ($brand, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index, // Sequential number
                'actual_id' => $brand->id, // Keep actual ID
                'name' => $brand->name,
                'description' => truncateText($brand->description),
                'encrypted_id' => $brand->encrypted_id
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
     * Get total brand count
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();
    }

    /**
     * Search brands
     */
    public function search(string $term): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where(function($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            })
            ->orderBy('id', 'desc')
            ->get();
    }
}
