<?php

namespace Modules\Administrator\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository
{
    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    /**
     * Get all users
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with(['roles'])
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get users with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with(['roles'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get users with pagination for DataTable
     */
    public function getDataTableData(int $length = 10, int $start = 0, string $search = ''): array
    {
        $query = $this->model->with(['roles'])
            ->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();
        
        $filteredCount = $query->count();
        
        $users = $query->skip($start)
                      ->take($length)
                      ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $users
        ];
    }

    /**
     * Find user by ID
     */
    public function find(int $id): ?User
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->find($id);
    }

    /**
     * Find user by ID with relationships
     */
    public function findWithRelations(int $id): ?User
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with(['roles'])
            ->find($id);
    }

    /**
     * Find user by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?User
    {
        try {
            $id = decrypt($encryptedId);
            return $this->findWithRelations($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new user
     */
    public function create(array $data): User
    {
        return $this->model->create($data);
    }

    /**
     * Update a user
     */
    public function update(User $user, array $data): bool
    {
        return $user->update($data);
    }

    /**
     * Soft delete a user
     */
    public function delete(User $user): bool
    {
        $user->del_status = 'Deleted';
        return $user->save();
    }

    /**
     * Get active users
     */
    public function getActiveUsers(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('active_status', 'Active')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get inactive users
     */
    public function getInactiveUsers(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('active_status', 'Inactive')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get users by role
     */
    public function getUsersByRole(int $roleId): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->whereHas('roles', function($q) use ($roleId) {
                $q->where('roles.id', $roleId);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Get users by login permission
     */
    public function getByLoginPermission(string $permission): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('will_login', $permission)
            ->orderBy('name')
            ->get();
    }

    /**
     * Search users by name
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
     * Get users by status
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('active_status', $status)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get users created by a specific user
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
     * Get user count by status
     */
    public function getCountByStatus(string $status): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('active_status', $status)
            ->count();
    }

    /**
     * Get total user count
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();
    }
}

