<?php

namespace Modules\Sale\Repositories;

use Modules\Sale\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerRepository
{
    protected $model;

    public function __construct(Customer $model)
    {
        $this->model = $model;
    }

    /**
     * Get all customers for a company
     */
    public function all(int $companyId): Collection
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('is_installment_customer', 'No')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get customers with pagination and search for DataTable
     */
    public function getDataTableData(int $companyId, int $start = 0, int $length = 10, string $search = '', ?string $typeFilter = null): array
    {
        $baseQuery = $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('is_installment_customer', 'No');

        $query = clone $baseQuery;

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply type filter (will be filtered after calculating balance in service)
        // We'll get all customers and filter in service based on calculated balance

        $recordsTotal = $baseQuery->count();
        $filteredCount = $query->count();

        $customers = $query
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'data' => $customers,
            'typeFilter' => $typeFilter,
        ];
    }

    /**
     * Get installment customers with pagination and search for DataTable
     */
    public function getInstallmentDataTableData(int $companyId, int $start = 0, int $length = 10, string $search = ''): array
    {
        $baseQuery = $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('is_installment_customer', 'Yes');

        $query = clone $baseQuery;

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $baseQuery->count();
        $filteredCount = $query->count();

        $customers = $query
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'data' => $customers,
        ];
    }

    /**
     * Find customer by ID
     */
    public function find(int $id): ?Customer
    {
        return $this->model
            ->where('del_status', 'Live')
            ->find($id);
    }

    /**
     * Find customer by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Customer
    {
        $decryptedId = decrypt($encryptedId);
        return $this->find($decryptedId);
    }

    /**
     * Find customer by ID
     */
    public function findById(string $id): ?Customer
    {
        return $this->model
            ->where('del_status', 'Live')
            ->find($id);
    }

    /**
     * Create a new customer
     */
    public function create(array $data): Customer
    {
        return $this->model->create($data);
    }

    /**
     * Update a customer
     */
    public function update(Customer $customer, array $data): bool
    {
        return $customer->update($data);
    }

    /**
     * Soft delete a customer (mark as deleted)
     */
    public function delete(Customer $customer): bool
    {
        return $customer->update(['del_status' => 'Deleted']);
    }

    /**
     * Get active customers for dropdown
     */
    public function getActiveCustomers(int $companyId): Collection
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('name', '!=', 'Walk-in Customer')
            ->where('company_id', $companyId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    /**
     * Search customers by name
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
     * Get total customers count
     */
    public function getTotalCount(int $companyId): int
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->count();
    }

    /**
     * Get customers with outstanding balance
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
     * Get base query for customers
     */
    public function getBaseQuery(int $companyId)
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('is_installment_customer', 'No');
    }
}

