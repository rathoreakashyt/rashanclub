<?php

namespace Modules\Administrator\Services;

use App\Models\User;
use Modules\Administrator\Repositories\UserRepository;
use Modules\Configuration\Models\Outlet;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserService
{
    protected $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all users with pagination
     */
    public function getAllUsers($perPage = 10)
    {
        return $this->repository->paginate($perPage);
    }

    /**
     * Get active users
     */
    public function getActiveUsers()
    {
        return $this->repository->getActiveUsers();
    }

    /**
     * Get all users for DataTable
     */
    public function getDataTableData(int $length = 10, int $start = 0, string $search = '')
    {
        $result = $this->repository->getDataTableData($length, $start, $search);
        
        $transformedUsers = $result['data']->map(function ($user) {
            $outletIds = $user->outlet_id ? explode(',', $user->outlet_id) : [];
            $outlets = Outlet::whereIn('id', $outletIds)
                ->pluck('outlet_name')
                ->implode(', ');

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->roles->first()?->name ?? 'No Role',
                'outlets' => $outlets ?: 'No Outlet',
                'status' => $user->will_login === 'Yes' ? 1 : 0,
                'is_super_admin' => $user->id == 1 && Auth::id() == 1 ? true : false,
                'encrypted_id' => $user->encrypted_id
            ];
        });

        return [
            'draw' => request()->draw ?? 1,
            'recordsTotal' => $result['recordsTotal'],
            'recordsFiltered' => $result['recordsFiltered'],
            'data' => $transformedUsers
        ];
    }

    /**
     * Get data for create/edit form
     */
    public function getFormData(?string $encryptedId = null): array
    {
        $data = [];
        $data['roles'] = Role::orderBy('id')->pluck('name', 'id');
        $data['outlets'] = Outlet::orderBy('id')->pluck('outlet_name', 'id');
        
        if ($encryptedId) {
            $data['user'] = $this->repository->findByEncryptedId($encryptedId);
            if (!$data['user']) {
                abort(404, 'User not found');
            }
        }

        return $data;
    }

    /**
     * Get repository instance (for controller access)
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * Create a new user
     */
    public function createUser(array $data): User
    {
        DB::beginTransaction();
        try {
            // Prepare user data
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'role' => (int) $data['role'],
                'outlet_id' => implode(',', $data['outlets']),
                'salary' => $data['salary'] ?? null,
                'commission' => $data['commission'] ?? null,
                'discount_permission_code' => $data['discount_permission_code'] ?? null,
                'discount_amt' => $data['discount_amt'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'will_login' => $data['will_login'] ?? 'Yes',
                'company_id' => session('company.company_id'),
                'del_status' => 'Live',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ];

            // Handle password if will_login is Yes
            if ($userData['will_login'] === 'Yes' && isset($data['password'])) {
                $userData['password'] = Hash::make($data['password']);
            } else {
                // Set a default password if will_login is No
                $userData['password'] = Hash::make('password');
            }

            // Handle image upload
            if (isset($data['photo']) && $data['photo']) {
                $userData['photo'] = $this->storeImage($data['photo']);
            }

            // Create user
            $user = $this->repository->create($userData);

            // Assign role
            if (isset($data['role'])) {
                $user->assignRole((int)$data['role']);
            }

            DB::commit();
            return $user;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing user
     */
    public function updateUser(string $encryptedId, array $data): User
    {
        DB::beginTransaction();
        try {
            $user = $this->repository->findByEncryptedId($encryptedId);
            
            if (!$user) {
                throw new \Exception('User not found');
            }

            // Prepare user data
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'role' => (int) $data['role'],
                'outlet_id' => implode(',', $data['outlets']),
                'salary' => $data['salary'] ?? null,
                'commission' => $data['commission'] ?? null,
                'discount_permission_code' => $data['discount_permission_code'] ?? null,
                'discount_amt' => $data['discount_amt'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'will_login' => $data['will_login'] ?? 'Yes',
                'updated_by' => Auth::id(),
            ];

            // Handle password if will_login is Yes and password is provided
            if ($userData['will_login'] === 'Yes' && isset($data['password']) && !empty($data['password'])) {
                $userData['password'] = Hash::make($data['password']);
            }

            // Handle image upload
            if (isset($data['photo']) && $data['photo']) {
                // Delete old image if exists
                if ($user->photo) {
                    $this->deleteImage($user->photo);
                }
                $userData['photo'] = $this->storeImage($data['photo']);
            }

            // Update user
            $this->repository->update($user, $userData);

            // Update role if changed
            if (isset($data['role'])) {
                $currentRole = $user->roles->first();
                if (!$currentRole || $currentRole->id != $data['role']) {
                    $user->syncRoles([(int)$data['role']]);
                }
            }

            DB::commit();
            return $user->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a user (soft delete)
     */
    public function deleteUser(string $encryptedId): bool
    {
        $user = $this->repository->findByEncryptedId($encryptedId);
        
        if (!$user) {
            throw new \Exception('User not found');
        }

        // Delete image if exists
        if ($user->photo) {
            $this->deleteImage($user->photo);
        }

        return $this->repository->delete($user);
    }

    /**
     * Toggle user status
     */
    public function toggleUserStatus(User $user)
    {
        return $user->update([
            'will_login' => $user->will_login === 'Yes' ? 'No' : 'Yes',
            'updated_by' => Auth::id()
        ]);
    }

    /**
     * Store image with cropping support
     */
    private function storeImage($image): string
    {
        // If it's a base64 string (from cropper), decode it
        if (is_string($image) && strpos($image, 'data:image') === 0) {
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $image));
            $fileName = 'user_' . time() . '_' . uniqid() . '.jpg';
            $filePath = 'users/' . $fileName;
            
            // Create directory if it doesn't exist
            $directory = public_path('uploads/users');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            // Save the image
            file_put_contents(public_path('uploads/' . $filePath), $imageData);
            
            return $filePath;
        }

        // If it's an uploaded file
        if ($image instanceof \Illuminate\Http\UploadedFile) {
            $fileName = 'user_' . time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $filePath = 'users/' . $fileName;
            
            // Create directory if it doesn't exist
            $directory = public_path('uploads/users');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            // Move the file
            $image->move(public_path('uploads/users'), $fileName);
            
            return $filePath;
        }

        throw new \Exception('Invalid image format');
    }

    /**
     * Delete image file
     */
    private function deleteImage(string $imagePath): void
    {
        if ($imagePath && file_exists(public_path('uploads/' . $imagePath))) {
            unlink(public_path('uploads/' . $imagePath));
        }
    }

    /**
     * Update user profile (name, email, phone, photo)
     */
    public function updateProfile(int $userId, array $data): User
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($userId);
            
            // Prepare user data
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'updated_by' => Auth::id(),
            ];

            // Handle image upload
            if (isset($data['photo']) && $data['photo']) {
                // Delete old image if exists
                if ($user->photo) {
                    $this->deleteImage($user->photo);
                }
                $userData['photo'] = $this->storeImage($data['photo']);
            }

            // Update user
            $user->update($userData);

            DB::commit();
            return $user->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update user password
     */
    public function updateProfilePassword(int $userId, string $newPassword): User
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($userId);
            
            $user->update([
                'password' => Hash::make($newPassword),
                'updated_by' => Auth::id(),
            ]);

            DB::commit();
            return $user->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update user security settings
     */
    public function updateProfileSecurity(int $userId, array $data): User
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($userId);
            
            // Prepare security data
            $securityData = [
                'updated_by' => Auth::id(),
            ];
            
            if (isset($data['two_factor_enabled'])) {
                $securityData['two_factor_enabled'] = $data['two_factor_enabled'] ? 1 : 0;
            }
            
            if (isset($data['session_timeout'])) {
                $securityData['session_timeout'] = (int) $data['session_timeout'];
            }
            
            if (isset($data['login_notifications'])) {
                $securityData['login_notifications'] = $data['login_notifications'] ? 1 : 0;
            }
            
            if (isset($data['security_question'])) {
                $securityData['question'] = $data['security_question'];
            }
            
            if (isset($data['security_answer'])) {
                $securityData['answer'] = $data['security_answer'];
            }

            // Update user
            $user->update($securityData);

            DB::commit();
            return $user->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get user statistics
     */
    public function getUserStatistics()
    {
        return [
            'total' => $this->repository->getTotalCount(),
            'active' => $this->repository->getActiveUsers()->count(),
            'with_login' => $this->repository->getByLoginPermission('Yes')->count(),
            'without_login' => $this->repository->getByLoginPermission('No')->count(),
        ];
    }
}

