<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

// use App\PermissionsLabel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

use App\Models\PermissionsLabel as ModelsPermissionsLabel;

use DB;

class PermissionsController extends Controller
{
    public function __construct() 
    {
        $this->middleware('auth');
        $this->middleware('role:administrator');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $permission = ModelsPermissionsLabel::orderBy('position', 'asc')
                                    ->with(['permission'])
                                    ->get();

        return view('admin.permissions.index', compact('permission'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'label' => 'required|min:3',
            'route' => 'required|min:3'
        ]);

        $input = $request->all();
        $input['route'] = Str::slug($input['route']);
        $input['position'] = ModelsPermissionsLabel::orderBy('position', 'desc')->first()->position+1;

        $label = ModelsPermissionsLabel::create($input);
        $labelId = $label->id;
        $permissionName = $input['route'];

        /**
         * Module Can List Data
         */
        if($request->has('list')){
            Permission::create([
                'label_id' => $labelId,
                'name' => $permissionName .'-list'
            ]);
        }

        /**
         * Module Can Create Data
         */
        if($request->has('create')){
            Permission::create([
                'label_id' => $labelId,
                'name' => $permissionName .'-create'
            ]);
        }

        /**
         * Module Can Edit Data
         */
        if($request->has('edit')){
            Permission::create([
                'label_id' => $labelId,
                'name' => $permissionName .'-edit'
            ]);
        }

        /**
         * Module Can Delete Data
         */
        if($request->has('delete')){
            Permission::create([
                'label_id' => $labelId,
                'name' => $permissionName .'-delete'
            ]);
        }

        return redirect()->route('permissions.index')
                        ->with('success','Modul baru berhasil dibuat');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'label' => 'required|min:3',
            'route' => 'required|min:3'
        ]);

        $input = $request->all();
        $permissionName = Str::slug($input['route']);

        $label = ModelsPermissionsLabel::findOrFail($id);

        /**
         * Temp old permission Name
         */
        $oldPermissionName = $label->route;

        $label->label = $input['label'];
        $label->route = $permissionName;
        $label->save();

        app()['cache']->forget('spatie.permission.cache');

        /**
         * Module Can List Data
         */
        if($request->has('list')){ 
            Permission::updateOrCreate([
                'label_id' => $label->id,'name' => $oldPermissionName .'-list'],
                ['name' => $permissionName .'-list']
            );
        }
        else {
            Permission::where('label_id', $id)
                        ->where('name', $oldPermissionName .'-list')
                        ->delete();
        }

        /**
         * Module Can Create Data
         */
        if($request->has('create')){ 
            Permission::updateOrCreate(
                ['label_id' => $label->id,'name' => $oldPermissionName .'-create'],
                ['name' => $permissionName .'-create']
            );
        }
        else {
            Permission::where('label_id', $id)
                        ->where('name', $oldPermissionName .'-create')
                        ->delete();
        }

        /**
         * Module can Edit Data
         */
        if($request->has('edit')){ 
            Permission::updateOrCreate(
                ['label_id' => $label->id, 'name' => $oldPermissionName .'-edit'],
                ['name' => $permissionName .'-edit']
            );
        }
        else {
            Permission::where('label_id', $id)
                        ->where('name', $oldPermissionName .'-edit')
                        ->delete();
        }

        /**
         * Module Can Delete Data
         */
        if($request->has('delete')){ 
            Permission::updateOrCreate(
                ['label_id' => $label->id,'name' => $oldPermissionName .'-delete'],
                ['name' => $permissionName .'-delete']
            );
        }
        else {
            Permission::where('label_id', $id)
                        ->where('name', $oldPermissionName .'-delete')
                        ->delete();
        }

        return redirect()->route('permissions.index')
                        ->with('success','Modul berhasil diupdate');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function sortModule(Request $request)
    {
        $modules = $request->get('module');
        foreach ($modules as $module) {
            ModelsPermissionsLabel::find($module['id'])->update([
                'position' => $module['position']
            ]);
        }
        
        return response()->json(['status' => true, 'message' => 'Modul telah disortir']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $route = ModelsPermissionsLabel::with(['permission'])->findOrFail($id);
        if(count($route->permission) == 0) {
            $route->delete();
            return redirect()->route('permissions.index')
                        ->with('success', 'Route barhasil dihapus');
        }
        else {
            $delete = $route->permission()->delete();
            if($delete) {
                $route->delete();
                return redirect()->route('permissions.index')
                        ->with('success', 'Route barhasil dihapus');
            }
            else {
                return redirect()->route('permissions.index')
                            ->with('error', 'Route tidak dapat dihapus');
            }
        }
    }
}
