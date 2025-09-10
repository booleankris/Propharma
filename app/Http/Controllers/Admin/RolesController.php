<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

use App\PermissionsLabel;
use App\Models\PermissionsLabel as ModelsPermissionsLabel;

use DB;

class RolesController extends Controller
{
    public function __construct() 
    {
        $this->middleware('auth');
        $this->middleware('role:administrator');
    }

    public function index(Request $request)
    {
        $roles = Role::orderBy('name', 'ASC')
                    ->paginate(5);

        return view('admin.roles.index',compact('roles'))
            ->with('i', ($request->input('page', 1) - 1) * 5);
    }

    public function create()
    {
        $permission = Permission::get();
        return view('admin.roles.create', compact('permission'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name'       => 'required|unique:roles,name',
            'permission' => 'required',
        ]);

        $role = Role::create(['name' => $request->input('name'), 'guard_name' => 'web']);
        $role->save();
        $role->permissions()->sync($request->input('permission'));

        return redirect()->route('roles.index')
                        ->with('success','Level berhasil dibuat');
    }

    public function show($id)
    {
        $role = Role::find($id);
        $rolePermissions = Permission::join("role_has_permissions","role_has_permissions.permission_id","=","permissions.id")
            ->where("role_has_permissions.role_id",$id)
            ->get();

        return view('admin.roles.show', compact('role', 'rolePermissions'));
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        if($role->fixed == 0) {
            $role->delete();
            return redirect()->route('roles.index')
                        ->with('success', 'Level berhasil dihapus');
        }
        else {
            return redirect()->route('roles.index')
                        ->with('error', 'Level tidak dapat dihapus');
        }
        
    }

    public function edit($id)
    {
        $role = Role::find($id);
        $permission = Permission::get();
        $rolePermissions = DB::table("role_has_permissions")->where("role_has_permissions.role_id",$id)
            ->pluck('role_has_permissions.permission_id','role_has_permissions.permission_id')
            ->all();
    
        return view('admin.roles.edit',compact('role','permission','rolePermissions'));
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required',
            'permission' => 'required',
        ]);
    
        $role = Role::find($id);
        $role->name = $request->input('name');
        $role->save();
    
        $role->permissions()->sync($request->input('permission'));
    
        return redirect()->route('roles.index')
                        ->with('success','Role updated successfully');
    }
}
