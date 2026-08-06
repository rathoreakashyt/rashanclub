<?php

namespace Modules\Configuration\Repositories;

use Modules\Configuration\Models\Outlet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OutletRepository
{
    protected $model;

    public function __construct(Outlet $model)
    {
        $this->model = $model;
    }

    /**
     * Get all outlets
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')->get();
    }

    /**
     * Get outlets with pagination
     */
    // no need to paginate, just return all outlets
    public function paginate(int $perPage = 100): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->with(['creator'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find outlet by ID
     */
    public function find(int $id): ?Outlet
    {
        return $this->model->where('del_status', 'Live')->find($id);
    }

    /**
     * Find outlet by ID with relationships
     */
    public function findWithRelations(int $id): ?Outlet
    {
        return $this->model->where('del_status', 'Live')
            ->with(['creator'])
            ->find($id);
    }

    /**
     * Create a new outlet
     */
    public function create(array $data): Outlet
    {
        return $this->model->create($data);
    }

    /**
     * Update an outlet
     */
    public function update(Outlet $outlet, array $data): bool
    {
        return $outlet->update($data);
    }

    /**
     * Delete an outlet (soft delete)
     */
    public function delete(Outlet $outlet): bool
    {
        return $outlet->update(['del_status' => 'Deleted']);
    }

    /**
     * Get active outlets
     */
    public function getActiveOutlets(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('active_status', 'Active')
            ->orderBy('outlet_name')
            ->get();
    }

    /**
     * Get inactive outlets
     */
    public function getInactiveOutlets(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('active_status', 'Inactive')
            ->orderBy('outlet_name')
            ->get();
    }

    /**
     * Search outlets by name
     */
    public function searchByName(string $name): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('outlet_name', 'like', "%{$name}%")
            ->orderBy('outlet_name')
            ->get();
    }

    /**
     * Get outlets by status
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('active_status', $status)
            ->orderBy('outlet_name')
            ->get();
    }

    /**
     * Get outlets created by a specific user
     */
    public function getByCreator(int $userId): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get outlet count by status
     */
    public function getCountByStatus(string $status): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('active_status', $status)
            ->count();
    }

    /**
     * Get total outlet count
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')->count();
    }

    /**
     * Get the last outlet for the current company to generate the next outlet code
     */
    public function getLastOutletForCompany(): ?Outlet
    {
        return $this->model->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Find an outlet by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Outlet
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
     * Find an outlet by encrypted ID with relationships
     */
    public function findByEncryptedIdWithRelations(string $encryptedId): ?Outlet
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
}
