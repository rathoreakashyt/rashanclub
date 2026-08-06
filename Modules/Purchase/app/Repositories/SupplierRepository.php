<?php

namespace Modules\Purchase\Repositories;

use Modules\Purchase\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierRepository
{
    protected $model;

    public function __construct(Supplier $model)
    {
        $this->model = $model;
    }

    /**
     * Get all suppliers for a company
     */
    public function all(int $companyId): Collection
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get suppliers with pagination and search
     */
    public function getDataTableData(int $companyId, int $start = 0, int $length = 10, string $search = ''): array
    {
        $baseQuery = $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);

        $query = clone $baseQuery;

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('opening_balance', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $baseQuery->count();
        $filteredCount = $query->count();

        $suppliers = $query
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'data' => $suppliers,
        ];
    }

    /**
     * Find supplier by ID
     */
    public function find(int $id): ?Supplier
    {
        return $this->model
            ->where('del_status', 'Live')
            ->find($id);
    }

    /**
     * Find supplier by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Supplier
    {
        $decryptedId = decrypt($encryptedId);
        return $this->find($decryptedId);
    }

    /**
     * Create a new supplier
     */
    public function create(array $data): Supplier
    {
        return $this->model->create($data);
    }

    /**
     * Update a supplier
     */
    public function update(Supplier $supplier, array $data): bool
    {
        return $supplier->update($data);
    }

    /**
     * Soft delete a supplier (mark as deleted)
     */
    public function delete(Supplier $supplier): bool
    {
        return $supplier->update(['del_status' => 'Deleted']);
    }

    /**
     * Get active suppliers for dropdown
     */
    public function getActiveSuppliers(int $companyId): Collection
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    /**
     * Search suppliers by name
     */
    public function searchByName(int $companyId, string $name): Collection
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('name', 'like', "%{$name}%")
            ->orderBy('name')
            ->get();
    }

    /**
     * Get total suppliers count
     */
    public function getTotalCount(int $companyId): int
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->count();
    }

    /**
     * Get suppliers with outstanding balance
     */
    public function getWithOutstandingBalance(int $companyId): Collection
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('opening_balance', '>', 0)
            ->orderBy('opening_balance', 'desc')
            ->get();
    }

    /**
     * Get base query for suppliers
     */
    public function getBaseQuery(int $companyId)
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);
    }
}

