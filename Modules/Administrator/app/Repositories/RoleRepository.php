<?php

namespace Modules\Administrator\Repositories;

use Modules\Administrator\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RoleRepository
{
    protected $model;
    protected $permissionModel;

    public function __construct(Role $model, Permission $permissionModel)
    {
        $this->model = $model;
        $this->permissionModel = $permissionModel;
    }

    /**
     * Get all roles
     */
    public function all(): Collection
    {
        return $this->model->with('permissions')->get();
    }

    /**
     * Get roles with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->with('permissions')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find role by ID
     */
    public function find(int $id): ?Role
    {
        return $this->model->find($id);
    }

    /**
     * Find role by ID with relationships
     */
    public function findWithRelations(int $id): ?Role
    {
        return $this->model->with('permissions')->find($id);
    }

    /**
     * Create a new role
     */
    public function create(array $data): Role
    {
        return $this->model->create($data);
    }

    /**
     * Update a role
     */
    public function update(Role $role, array $data): bool
    {
        return $role->update($data);
    }

    /**
     * Delete a role
     */
    public function delete(Role $role): bool
    {
        return $role->delete();
    }

    /**
     * Find role by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Role
    {
        try {
            $id = decrypt($encryptedId);
            // Ensure the decrypted value is an integer
            $id = (int) $id;
            \Log::info('Attempting to find role with encrypted ID: ' . $encryptedId . ', decrypted ID: ' . $id);
            return $this->findWithRelations($id);
        } catch (\Exception $e) {
            \Log::error('Error decrypting encrypted ID: ' . $encryptedId . ', error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all permissions grouped by group_name
     */
    public function getAllPermissionsGrouped(): Collection
    {
        return $this->permissionModel->all()->groupBy('group_name');
    }

    /**
     * Get all permissions
     */
    public function getAllPermissions(): Collection
    {
        return $this->permissionModel->all();
    }

    /**
     * Get permissions by IDs
     */
    public function getPermissionsByIds(array $ids): Collection
    {
        return $this->permissionModel->whereIn('id', $ids)->get();
    }

    /**
     * Sync permissions to a role
     */
    public function syncPermissions(Role $role, array $permissionIds): Role
    {
        // Get permission names from IDs
        $permissions = $this->permissionModel->whereIn('id', $permissionIds)->pluck('name')->toArray();
        return $role->syncPermissions($permissions);
    }

    /**
     * Search roles by name
     */
    public function searchByName(string $name): Collection
    {
        return $this->model->where('name', 'like', "%{$name}%")
            ->with('permissions')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get role count
     */
    public function getCount(): int
    {
        return $this->model->count();
    }

    /**
     * Get permission count
     */
    public function getPermissionCount(): int
    {
        return $this->permissionModel->count();
    }

    /**
     * Check if role name exists
     */
    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $query = $this->model->where('name', $name);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->exists();
    }
}
