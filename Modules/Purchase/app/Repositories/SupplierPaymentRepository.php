<?php

namespace Modules\Purchase\Repositories;

use Modules\Purchase\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Collection;

class SupplierPaymentRepository
{
    protected $model;

    public function __construct(SupplierPayment $model)
    {
        $this->model = $model;
    }

    /**
     * Get all supplier payments for a company
     */
    public function all(int $companyId): Collection
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get supplier payments with pagination and search for DataTable
     */
    public function getDataTableData(int $companyId, int $start = 0, int $length = 10, string $search = ''): array
    {

        // relation with supplier and payment method
        $baseQuery = $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->with('supplier:id,name,phone', 'account:id,name');


        $query = clone $baseQuery;

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('date', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('account', function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $recordsTotal = $baseQuery->count();
        $filteredCount = $query->count();

        $supplierPayments = $query
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'data' => $supplierPayments,
        ];
    }

    /**
     * Find supplier payment by ID
     */
    public function find(int $id): ?SupplierPayment
    {
        return $this->model
            ->where('del_status', 'Live')
            ->find($id);
    }

    /**
     * Find supplier payment by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?SupplierPayment
    {
        $decryptedId = decrypt($encryptedId);
        return $this->find($decryptedId);
    }

    /**
     * Create a new supplier payment
     */
    public function create(array $data): SupplierPayment
    {
        return $this->model->create($data);
    }

    /**
     * Update a supplier payment
     */
    public function update(SupplierPayment $supplierPayment, array $data): bool
    {
        return $supplierPayment->update($data);
    }

    /**
     * Soft delete a supplier payment (mark as deleted)
     */
    public function delete(SupplierPayment $supplierPayment): bool
    {
        return $supplierPayment->update(['del_status' => 'Deleted']);
    }

    /**
     * Get last record for reference number generation
     */
    public function getLastRecord(): ?SupplierPayment
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
     * Get payments by supplier
     */
    public function getBySupplier(int $supplierId, int $companyId): Collection
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * Get total payments amount for a supplier
     */
    public function getTotalPaymentsBySupplier(int $supplierId, int $companyId): float
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->sum('amount');
    }
}

