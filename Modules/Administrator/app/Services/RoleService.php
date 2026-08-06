<?php

namespace Modules\Administrator\Services;

use Modules\Administrator\Repositories\RoleRepository;
use Modules\Administrator\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoleService
{
    protected $roleRepository;

    public function __construct(RoleRepository $roleRepository)
    {
        $this->roleRepository = $roleRepository;
    }

    /**
     * Get all roles with pagination
     */
    public function getAllRoles($perPage = 10)
    {
        return $this->roleRepository->paginate($perPage);
    }

    /**
     * Get all permissions grouped by group_name
     */
    public function getAllPermissionsGrouped()
    {
        return $this->roleRepository->getAllPermissionsGrouped();
    }

    /**
     * Create a new role with permissions
     */
    public function createRole(array $data)
    {
        try {
            DB::beginTransaction();
            
            // Create the role
            $role = $this->roleRepository->create(['name' => $data['name']]);
            
            // Attach permissions if provided
            if (!empty($data['permissions'])) {
                $permissions = $this->roleRepository->getPermissionsByIds($data['permissions']);
                if ($permissions->count() > 0) {
                    $this->roleRepository->syncPermissions($role, $data['permissions']);
                } else {
                    DB::rollBack();
                    throw new \Exception('Selected permissions are invalid');
                }
            }
            
            DB::commit();
            return $role;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating role: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update an existing role with permissions
     */
    public function updateRole(Role $role, array $data)
    {
        try {
            DB::beginTransaction();
            
            // Update the role name
            $this->roleRepository->update($role, ['name' => $data['name']]);
            
            // Sync permissions if provided
            if (isset($data['permissions'])) {
                $permissions = $this->roleRepository->getPermissionsByIds($data['permissions']);
                if ($permissions->count() > 0) {
                    $this->roleRepository->syncPermissions($role, $data['permissions']);
                } else {
                    DB::rollBack();
                    throw new \Exception('Selected permissions are invalid');
                }
            }
            
            DB::commit();
            return $role;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating role: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a role
     */
    public function deleteRole(Role $role)
    {
        try {
            DB::beginTransaction();
            
            // Check if role is assigned to any users
            if ($role->users()->count() > 0) {
                throw new \Exception('Cannot delete role as it is assigned to users');
            }
            
            $this->roleRepository->delete($role);
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting role: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the role repository instance
     */
    public function getRepository(): RoleRepository
    {
        return $this->roleRepository;
    }

    /**
     * Get role by ID with permissions
     */
    public function getRoleById(int $id)
    {
        return $this->roleRepository->findWithRelations($id);
    }

    /**
     * Search roles by name
     */
    public function searchRoles(string $name)
    {
        return $this->roleRepository->searchByName($name);
    }

    /**
     * Get role statistics
     */
    public function getRoleStatistics()
    {
        return [
            'total_roles' => $this->roleRepository->getCount(),
            'total_permissions' => $this->roleRepository->getPermissionCount(),
        ];
    }

    /**
     * Check if role name exists
     */
    public function roleNameExists(string $name, ?int $excludeId = null)
    {
        return $this->roleRepository->nameExists($name, $excludeId);
    }
}
