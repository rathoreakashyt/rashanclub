<?php

namespace Modules\Sale\Repositories;

use Modules\Sale\Models\CustomerReceive;
use Illuminate\Database\Eloquent\Collection;

class CustomerReceiveRepository
{
    protected $model;

    public function __construct(CustomerReceive $model)
    {
        $this->model = $model;
    }

    /**
     * Get all customer receives for a company
     */
    public function all(int $companyId): Collection
    {
        return $this->model
            ->with('customer:id,name,phone', 'payment:id,name')
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get customer receives with pagination and search for DataTable
     */
    public function getDataTableData(int $companyId, int $start = 0, int $length = 10, string $search = ''): array
    {
        $baseQuery = $this->model
            ->with('customer:id,name,phone', 'payment:id,name')
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);

        $query = clone $baseQuery;

        // search by reference number, date, customer name, customer phone, payment account name
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('date', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('customer', function ($query) use ($search) {
                        $query->where('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('payment', function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $recordsTotal = $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->count();
        $filteredCount = $query->count();

        $customerReceives = $query
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'data' => $customerReceives,
        ];
    }

    /**
     * Find customer receive by ID
     */
    public function find(int $id): ?CustomerReceive
    {
        return $this->model
            ->where('del_status', 'Live')
            ->find($id);
    }

    /**
     * Find customer receive by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?CustomerReceive
    {
        $decryptedId = decrypt($encryptedId);
        return $this->find($decryptedId);
    }

    /**
     * Create a new customer receive
     */
    public function create(array $data): CustomerReceive
    {
        return $this->model->create($data);
    }

    /**
     * Update a customer receive
     */
    public function update(CustomerReceive $customerReceive, array $data): bool
    {
        return $customerReceive->update($data);
    }

    /**
     * Soft delete a customer receive (mark as deleted)
     */
    public function delete(CustomerReceive $customerReceive): bool
    {
        return $customerReceive->update(['del_status' => 'Deleted']);
    }

    /**
     * Get last record for reference number generation
     */
    public function getLastRecord(): ?CustomerReceive
    {
        return $this->model->orderBy('id', 'desc')->first();
    }

    /**
     * Generate next reference number
     */
    public function generateReferenceNumber(): string
    {
        $lastRecord = $this->getLastRecord();
        $nextId = $lastRecord ? $lastRecord->id + 1 : 1;
        return str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get receives by customer
     */
    public function getByCustomer(int $customerId, int $companyId): Collection
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * Get total receives amount for a customer
     */
    public function getTotalReceivesByCustomer(int $customerId, int $companyId): float
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->sum('amount');
    }
}

