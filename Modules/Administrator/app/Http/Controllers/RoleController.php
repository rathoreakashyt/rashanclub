<?php

namespace Modules\Administrator\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Administrator\Http\Request\RoleRequest;
use Modules\Administrator\Services\RoleService;
use Modules\Administrator\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    protected $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            $length = (int) request('length', 10);
            $start = (int) request('start', 0);
            $search = request('search.value', '');
            $draw = (int) request('draw', 1);

            // Safe ordering
            $columns = ['id', 'name', 'created_at'];
            $order = request('order')[0] ?? ['column' => 0, 'dir' => 'desc'];

            $orderColumn = $columns[$order['column']] ?? 'id';
            $orderDir = $order['dir'] ?? 'desc';

            // Get all roles
            $roles = $this->roleService->getAllRoles($length);

            // Filter by search if provided
            if ($search !== '') {
                $roles = $this->roleService->searchRoles($search);
            }

            // Total count
            $recordsTotal = $this->roleService->getRoleStatistics()['total_roles'];

            // Filtered count
            $recordsFiltered = $search !== '' ? $roles->count() : $recordsTotal;

            // Paginate results
            if ($search !== '') {
                $roles = $roles->skip($start)->take($length);
            }

            // Get paginated results
            if ($search !== '') {
                $paginatedRoles = $roles->get();
            } else {
                $paginatedRoles = $roles->getCollection();
            }

            // Reverse number
            $startingNumber = max($recordsFiltered - $start, 0);

            $data = $paginatedRoles->map(function ($item, $index) use ($startingNumber) {
                return [
                    'id' => $startingNumber - $index,
                    'actual_id' => $item->id,
                    'name' => $item->name,
                    'permissions_count' => $item->permissions->count(),
                    'users_count' => $item->users->count(),
                    'encrypted_id' => encrypt($item->id),
                ];
            });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ]);
        }

        return view('administrator::role.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data['permissions'] = $this->roleService->getAllPermissionsGrouped();
        return view('administrator::role.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RoleRequest $request)
    {
        try {
            $data = $request->validated();
            $this->roleService->createRole($data);
            
            return redirect()->route('role.index')
                ->with('success', 'Role created successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Something went wrong: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $role = $this->roleService->getRepository()->findByEncryptedId($id);
            if (!$role) {
                return redirect()->route('role.index')
                    ->with('error', 'Role not found');
            }
        } catch (\Exception $e) {
            return redirect()->route('role.index')
                ->with('error', 'Invalid role ID');
        }
        
        return view('administrator::role.show', compact('role'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $role = $this->roleService->getRepository()->findByEncryptedId($id);
            if (!$role) {
                return redirect()->route('role.index')
                    ->with('error', 'Role not found');
            }
        } catch (\Exception $e) {
            return redirect()->route('role.index')
                ->with('error', 'Invalid role ID');
        }
        
        $data['role'] = $role;
        $data['permissions'] = $this->roleService->getAllPermissionsGrouped();
        
        return view('administrator::role.create', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RoleRequest $request, string $id)
    {
        $role = $this->roleService->getRepository()->findByEncryptedId($id);
        if (!$role && ctype_digit((string) $id)) {
            $role = $this->roleService->getRepository()->find((int) $id);
        }
        if (!$role) {
            return redirect()->route('role.index')
                ->with('error', 'Role not found');
        }

        try {
            $data = $request->validated();
            $this->roleService->updateRole($role, $data);

            return redirect()->route('role.index')
                ->with('success', 'Role updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Something went wrong: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $role = $this->roleService->getRepository()->findByEncryptedId($id);
            if (!$role) {
                return redirect()->route('role.index')
                    ->with('error', 'Role not found');
            }
        } catch (\Exception $e) {
            return redirect()->route('role.index')
                ->with('error', 'Invalid role ID');
        }
        
        try {
            $this->roleService->deleteRole($role);
            
            return redirect()->route('role.index')
                ->with('success', 'Role deleted successfully');
        } catch (\Exception $e) {
            return redirect()->route('role.index')
                ->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }
}
