<?php

namespace App\Http\Controllers\backend;

use Illuminate\Http\Request;
use App\Models\Admin\Customer;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data = [];
        $data['permissions'] = Permission::all()->groupBy('group_name');

        // $data['permissions'] = $permissions->groupBy(function($permission) {
        //     return strtolower(explode('_', $permission->name)[0]);
        // })->map(function($group) {
        //     return $group->groupBy(function($permission) {
        //         $parts = explode('_', $permission->name);
        //         return $parts[1] ?? '';
        //     });
        // });
    
        return view('backend.role.add_role', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role_name' => 'required|unique:roles,name|max:25',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'required|exists:permissions,id'
        ]);
 
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        try {
            DB::beginTransaction();
            $role = \Spatie\Permission\Models\Role::create(['name' => $request->role_name]);
            if (!empty($request->permissions)) {
                $permissions = Permission::whereIn('id', $request->permissions)->get();
                if ($permissions->count() > 0) {
                    $role->syncPermissions($permissions);
                } else {
                    DB::rollBack();
                    return redirect()->back()
                        ->with('error', 'Selected permissions are invalid')
                        ->withInput();
                }
            }
            DB::commit();
            return redirect()->route('role.index')
                ->with('success', 'Role created successfully');
        } catch (\Exception $e) {
            // Roll back the transaction if any error occurs
            DB::rollBack();
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
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $role = $this->findRole($id);
        if (!$role) {
            return redirect()->route('role.index')
                ->with('error', 'Role not found');
        }
        $data = [];
        $data['role'] = $role;
        $data['permissions'] = Permission::all()->groupBy('group_name');
        return view('backend.role.add_role', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $role = $this->findRole($id);
        if (!$role) {
            return redirect()->route('role.index')
                ->with('error', 'Role not found');
        }

        $validator = Validator::make($request->all(), [
            'role_name' => 'required|max:25|unique:roles,name,' . $role->id,
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'required|exists:permissions,id'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        try {
            DB::beginTransaction();
            $role->update(['name' => $request->role_name]);
            if (!empty($request->permissions)) {
                $permissions = Permission::whereIn('id', $request->permissions)->get();
                if ($permissions->count() > 0) {
                    $role->syncPermissions($permissions);
                } else {
                    DB::rollBack();
                    return redirect()->back()
                        ->with('error', 'Selected permissions are invalid')
                        ->withInput();
                }
            }
            DB::commit();
            return redirect()->route('role.index')
                ->with('success', 'Role updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Something went wrong: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Find role by encrypted ID or numeric ID.
     */
    protected function findRole(string $id): ?SpatieRole
    {
        try {
            $decrypted = decrypt($id);
            $role = SpatieRole::find((int) $decrypted);
            if ($role) {
                return $role;
            }
        } catch (\Exception $e) {
            // Not encrypted, try numeric
        }
        if (ctype_digit((string) $id)) {
            return SpatieRole::find((int) $id);
        }
        return null;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
