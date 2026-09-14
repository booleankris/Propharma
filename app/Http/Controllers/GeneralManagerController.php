<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class GeneralManagerController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole('General Manager'), 403);
        $search = trim((string) $request->query('search', ''));
        $users = User::with(['roles', 'pharmacy'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();
        $roles = Role::where('guard_name', 'web')->where('name', '!=', 'administrator')->orderBy('name')->get();

        return view('general-manager.home', compact('users', 'roles', 'search'));
    }

    public function updateRoles(Request $request, User $user)
    {
        abort_unless($request->user()->hasRole('General Manager'), 403);
        abort_if($user->hasRole('administrator') || $user->is_fixed || $user->id === $request->user()->id, 403,
            'Role akun ini hanya dapat diubah oleh administrator.');

        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web'), Rule::notIn(['administrator'])],
        ]);
        $user->syncRoles($data['roles']);

        return redirect()->back()->with('success', "Role {$user->name} berhasil diperbarui.");
    }
}
