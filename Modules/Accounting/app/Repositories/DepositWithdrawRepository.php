<?php

namespace Modules\Accounting\Repositories;

use Modules\Accounting\Models\DepositWithdraw;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DepositWithdrawRepository
{
    protected $model;

    public function __construct(DepositWithdraw $model)
    {
        $this->model = $model;
    }

    /**
     * Get all deposit/withdraws
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')->get();
    }

    /**
     * Get deposit/withdraws with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->with(['paymentMethod'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find deposit/withdraw by ID
     */
    public function find(int $id): ?DepositWithdraw
    {
        return $this->model->where('del_status', 'Live')->find($id);
    }

    /**
     * Find deposit/withdraw by ID with relationships
     */
    public function findWithRelations(int $id): ?DepositWithdraw
    {
        return $this->model->where('del_status', 'Live')
            ->with(['paymentMethod'])
            ->find($id);
    }

    /**
     * Create a new deposit/withdraw
     */
    public function create(array $data): DepositWithdraw
    {
        return $this->model->create($data);
    }

    /**
     * Update a deposit/withdraw
     */
    public function update(DepositWithdraw $depositWithdraw, array $data): bool
    {
        return $depositWithdraw->update($data);
    }

    /**
     * Delete a deposit/withdraw (soft delete)
     */
    public function delete(DepositWithdraw $depositWithdraw): bool
    {
        return $depositWithdraw->update(['del_status' => 'Deleted']);
    }

    /**
     * Get deposit/withdraws by type
     */
    public function getByType(string $type): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('type', $type)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get deposit/withdraws by payment method
     */
    public function getByPaymentMethod(int $paymentMethodId): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('payment_method_id', $paymentMethodId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get deposit/withdraws created by a specific user
     */
    public function getByCreator(int $userId): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get deposit/withdraws for a specific company
     */
    public function getByCompany(int $companyId): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Search deposit/withdraws by reference number
     */
    public function searchByReference(string $reference): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('reference_no', 'like', "%{$reference}%")
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get the last deposit/withdraw for the current company to generate the next reference number
     */
    public function getLastDepositWithdrawForCompany(): ?DepositWithdraw
    {
        return $this->model->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Find a deposit/withdraw by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?DepositWithdraw
    {
        try {
            $id = decrypt($encryptedId);
            // Ensure the decrypted value is an integer
            $id = (int) $id;
            return $this->find($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Find a deposit/withdraw by encrypted ID with relationships
     */
    public function findByEncryptedIdWithRelations(string $encryptedId): ?DepositWithdraw
    {
        try {
            $id = decrypt($encryptedId);
            // Ensure the decrypted value is an integer
            $id = (int) $id;
            return $this->findWithRelations($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get total count
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')->count();
    }

    /**
     * Get count by type
     */
    public function getCountByType(string $type): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('type', $type)
            ->count();
    }
}

