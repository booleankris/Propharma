<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pharmacies;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Hash as FacadesHash;
use Illuminate\Support\Facades\Hash;

use App\Models\User;

use Form;
use Image;
use DataTables;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:administrator|Manager');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $users = User::select(['id', 'pharmacy_id', 'username', 'name',  'is_fixed']);
            if ($request->has('order') == false) {
                $users = $users->orderBy('is_fixed', 'DESC')
                    ->orderBy('name', 'ASC');
            }

            return DataTables::of($users)
                ->addIndexColumn()
                ->filter(function ($query) use ($request) {
                    if ($request->has('name')) {
                        $query->where('name', 'like', "%{$request->get('name')}%");
                    }

                    if ($request->has('username')) {
                        $query->where('username', 'like', "%{$request->get('username')}%");
                    }
                })
                ->addColumn('pharmacy', function ($user) {
                    return $user->pharmacy
                        ? '<span class="badge badge-outline-info px-2 py-1">'
                        . ucfirst($user->pharmacy->name) .
                        '</span>'
                        : '-';
                })
                ->addColumn('roles', function ($user) {
                    $roles = '';

                    foreach ($user->getRoleNames() as $role) {
                        $roles .= '<span class="badge badge-primary mr-1">'
                            . ucfirst($role) .
                            '</span>';
                    }

                    return $roles ?: '-';
                })
                ->addColumn('action', function ($user) {
                    $button = '<div class="btn-toolbar" role="toolbar">
                                            <div class="btn-group m-1 mr-2" role="group">
                                            <a class="btn btn-outline-primary" href="' . route('users.show', $user->id) . '">Detail</a>';
                    if (Auth::user()->hasRole('administrator')) {
                        if (Auth::user()->id == $user->id || $user->is_fixed == 0) {
                            $button .= '<a class="btn btn-primary" href="' . route('users.edit', $user->id) . '">Edit</a>';
                        }
                    }
                    $button .= '</div>';
                    if ($user->is_fixed == 0):
                        $button .= '<div class="btn-group m-1">';
                        $button .= Form::button('Delete', ['id' => 'button-delete-' . $user->id, 'class' => 'btn btn-danger', 'data-route' => route('users.destroy', $user->id), 'onclick' => 'delete_data(' . $user->id . ')']);
                        $button .= '</div>';
                    endif;

                    $button .= '</div>';
                    return $button;
                })
                ->rawColumns(['roles', 'action', 'pharmacy'])->toJson();
        }

        return view('admin.users.index');
    }

    public function create()
    {
        $roles = Role::pluck('name', 'name')->all();
        $pharmacies = Pharmacies::pluck('name', 'id')->all();

        return view('admin.users.create', compact('roles', 'pharmacies'));
    }

    public function store(Request $request)
    {

        $validate = $this->validate($request, [
            'name'        => 'required|string|min:3|max:255',
            'username'    => 'required|string|min:3|max:255',
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'password'    => 'required|min:6|same:confirm-password',
            'roles'       => 'required'
        ]);
        $input = $request->all();
        $input['secret_pin'] =  $input['password'];
        $input['password'] = FacadesHash::make($input['password']);

        $user = User::create($input);
        $user->assignRole($request->input('roles'));

        return redirect()->route('users.index')
            ->with('success', 'Administrator berhasil ditambahkan');
    }

    public function show($id)
    {
        $user = User::findOrFail($id);

        return view('admin.users.show', compact('user'));
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);

        if ($user->fixed == 1 && Auth::user()->id != $id) {
            abort(404);
        }
        $roles = Role::pluck('name', 'name')->all();
        $userRole = $user->roles->pluck('name', 'name')->all();

        return view('admin.users.edit', compact('user', 'roles', 'userRole'));
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name'      => 'required',
            'username'     => 'required',
            'password'  => 'same:confirm-password',
            'roles'     => 'required'
        ]);

        $input = $request->all();
        if (! empty($input['password'])) {
            $input['password'] = Hash::make($input['password']);
        } else {
            $input = Arr::except($input, ['password']);
        }

        $user = User::find($id);
        $user->update($input);

        if ($user->fixed == 0) {
            FacadesDB::table('model_has_roles')->where('model_id', $id)->delete();
            $user->assignRole($request->input('roles'));
        }

        return redirect()->route('users.index')
            ->with('success', 'Administrator berhasil diupdate');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->is_fixed != 1) {
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Administrator berhasil dihapus'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'User ini tidak bisa dihapus'
        ], 403);
    }
}
