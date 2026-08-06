<?php

namespace App\Http\Controllers\Backend;

use App\Models\User;
use Illuminate\Http\Request;
use Modules\Configuration\Models\Outlet;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            $length = request()->length ?? 10;
            $start = request()->start ?? 0;
            $search = request()->search['value'] ?? '';
            $query = User::with(['roles', 'outlets']);
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }
            $recordsTotal = User::count();
            $filteredCount = $query->count();
            $users = $query->orderBy('id', 'desc')
                          ->skip($start)
                          ->take($length)
                          ->get();
            $transformedUsers = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->roles->first()?->name ?? 'No Role',
                    'outlets' => Outlet::whereIn('id', explode(',', $user->outlet_id))
                        ->pluck('outlet_name')
                        ->implode(', '),
                    'status' => $user->will_login === 'Yes' ? 1 : 0,
                    'encrypted_id' => $user->encrypted_id
                ];
            });
            return response()->json([
                'draw' => request()->draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $filteredCount,
                'data' => $transformedUsers
            ]);
        }
        return view('backend.user.list_user');


        // $users = User::with(['roles', 'outlets'])
        //     ->orderBy('id', 'desc')
        //     ->where('del_status', 'Live')
        //     ->get()
        //     ->map(function ($user) {
        //         return [
        //             'id' => $user->id,
        //             'name' => $user->name,
        //             'email' => $user->email,
        //             'phone' => $user->phone,
        //             'role' => $user->roles->first()?->name ?? 'No Role',
        //             'outlets' => Outlet::whereIn('id', explode(',', $user->outlet_id))
        //                 ->pluck('outlet_name')
        //                 ->implode(', '),
        //             'status' => $user->will_login === 'Yes' ? 'Yes' : 'No',
        //         ];
        //     });
        // return view('backend.user.list_user', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data = [];
        $data['roles'] = Role::orderBy('id')->pluck('name', 'id');
        $data['outlets'] = Outlet::orderBy('id')->pluck('outlet_name', 'id');
        return view('backend.user.add_edit_user', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validationRules = [
            'name' => ['required', 'string', 'max:55', 'regex:/^[\p{L}\s-]+$/u'],
            'email' => ['required', 'string', 'email:rfc,dns', 'unique:users,email', 'max:55'],
            'outlets' => ['required', 'array'],
            'outlets.*' => ['required', 'integer', 'exists:outlets,id'],
            'phone' => ['required', 'string', 'regex:/^([0-9\s\-\+\(\)]*)$/', 'min:10', 'max:20'],
            'role' => ['required', 'integer', 'exists:roles,id'],
        ];
        if ($request->will_login === 'Yes') {
            $validationRules['password'] = ['required', 'string', 'min:6', 'max:32', 'confirmed'];
            $validationRules['password_confirmation'] = ['required', 'string', 'min:6'];
        }
        $request->validate($validationRules);
        try {
            $userData = [
                'name' => $request->name,
                'email' => $request->email,
                'role' => (int) $request->role,
                'outlet_id' => implode(',', $request->outlets),
                'phone' => $request->phone,
                'commission' => $request->commission,
                'salary' => $request->salary,
                'will_login' => $request->will_login,
            ];
            if ($request->will_login === 'Yes') {
                $userData['password'] = Hash::make($request->password);
            }
            $user = User::create($userData);
            if ($request->role) {
                $user->assignRole((int)$request->role);
            }
            return redirect()->route('user.index')
                ->with('success', 'User Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('user.create')
                ->with('error', $e->getMessage());
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
        $decryptedId = decrypt($id);
        $data = [];
        $data['roles'] = Role::orderBy('id')->pluck('name', 'id');
        $data['outlets'] = Outlet::orderBy('id')->pluck('outlet_name', 'id');
        $data['user'] = User::findOrFail($decryptedId);
        return view('backend.user.add_edit_user', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validationRules = [
            'name' => ['required', 'string', 'max:55', 'regex:/^[\p{L}\s-]+$/u'],
            'email' => ['required', 'string', 'email:rfc,dns', 'unique:users,email,' . $id, 'max:55'],
            'outlets' => ['required', 'array'],
            'outlets.*' => ['required', 'integer', 'exists:outlets,id'],
            'phone' => ['required', 'string', 'regex:/^([0-9\s\-\+\(\)]*)$/', 'min:10', 'max:20'],
            'role' => ['required', 'integer', 'exists:roles,id'],
        ];
        if ($request->will_login === 'Yes' && $request->filled('password')) {
            $validationRules['password'] = ['required', 'string', 'min:6', 'max:32', 'confirmed'];
            $validationRules['password_confirmation'] = ['required', 'string', 'min:6'];
        }
        $request->validate($validationRules);
        try {
            $user = User::findOrFail($id);
            $userData = [
                'name' => $request->name,
                'email' => $request->email,
                'role' => (int) $request->role,
                'outlet_id' => implode(',', $request->outlets),
                'phone' => $request->phone,
                'commission' => $request->commission,
                'salary' => $request->salary,
                'will_login' => $request->will_login,
            ];
            if ($request->will_login === 'Yes' && $request->filled('password')) {
                $userData['password'] = Hash::make($request->password);
            }
            $user->update($userData);
            // Update role if changed
            if ($request->role && $user->roles->first()->id != $request->role) {
                $user->syncRoles([(int)$request->role]);
            }
            return redirect()->route('user.edit', $id)
                ->with('success', 'User Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('user.edit', $id)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $decryptedId = decrypt($id);
            $user = User::findOrFail($decryptedId);
            $user->delete();
            return redirect()->route('user.index')
                ->with('success', 'User Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('user.index')
                ->with('error', $e->getMessage());
        }
    }
}
