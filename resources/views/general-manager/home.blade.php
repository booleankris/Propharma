@extends('layouts.app')

@section('title', 'Manajemen Pengguna & Role')

@section('content')
    <section class="section px-4 md:px-8 py-4">
        <div class="space-y-6">

            {{-- ─── Header Card ─── --}}
            <div
                class="bg-gradient-to-r from-blue-600 via-indigo-600 to-sky-600 rounded-3xl p-6 text-white shadow-lg relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 opacity-10 pointer-events-none">
                    <svg class="w-64 h-64 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div
                            class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 text-xs font-semibold backdrop-blur-sm mb-2">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            General Manager Access
                        </div>
                        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">Manajemen Pengguna & Role</h1>
                        <p class="text-blue-100 text-sm mt-1 max-w-2xl">
                            Kelola profil pengguna (Nama, NIK, Username, Apotek, Jabatan, Password/PIN) dan delegasikan hak akses role secara terpadu.
                        </p>
                    </div>
                </div>
            </div>

            {{-- ─── Alert Notifications ─── --}}
            @if (session('success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl flex items-center gap-3 text-sm shadow-sm">
                    <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl text-sm shadow-sm">
                    <div class="font-bold flex items-center gap-2 mb-1">
                        <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Terjadi kesalahan validasi:
                    </div>
                    <ul class="list-disc list-inside space-y-0.5 text-xs text-rose-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ─── Filter & Search Bar ─── --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5">
                <form method="GET" action="{{ route('general-manager.users.roles.index') }}"
                    class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="w-full sm:max-w-md relative">
                        <label for="search" class="sr-only">Cari pengguna</label>
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" id="search" name="search" value="{{ $search }}"
                            placeholder="Cari berdasarkan nama, username, atau NIK..."
                            class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none transition">
                    </div>
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button type="submit"
                            class="flex-1 sm:flex-none px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm shadow-blue-500/20 transition cursor-pointer flex items-center justify-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Cari
                        </button>
                        <a href="{{ route('general-manager.users.roles.index') }}"
                            class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition text-center">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            {{-- ─── Users Table Card ─── --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm sm:text-base font-bold text-slate-800">Daftar Pengguna</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Total {{ $users->total() }} akun pengguna terdaftar</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 text-[11px] font-bold uppercase tracking-wider">
                                <th class="py-3.5 px-5">Pengguna</th>
                                <th class="py-3.5 px-5">Apotek</th>
                                <th class="py-3.5 px-5">NIK & Jabatan</th>
                                <th class="py-3.5 px-5">Role Saat Ini</th>
                                <th class="py-3.5 px-5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @forelse($users as $user)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-4 px-5 align-top">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center font-bold text-xs shadow-xs flex-shrink-0">
                                                {{ strtoupper(substr($user->name, 0, 2)) }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-bold text-slate-800 text-[13px] truncate">{{ $user->name }}</div>
                                                @if($user->fullname && $user->fullname !== $user->name)
                                                    <div class="text-[11px] text-slate-500 truncate">{{ $user->fullname }}</div>
                                                @endif
                                                <div class="text-[11px] font-mono text-slate-400 mt-0.5"><span>&#64;</span>{{ $user->username }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-5 align-top">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-medium">
                                            <svg class="w-3.5 h-3.5 mr-1.5 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            {{ $user->pharmacy->name ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="py-4 px-5 align-top">
                                        <div class="space-y-1">
                                            @if($user->nik)
                                                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 font-mono text-[11px]">
                                                    <span class="text-slate-400 text-[9px] uppercase font-bold tracking-wider">NIK</span>
                                                    <span class="font-semibold">{{ $user->nik }}</span>
                                                </div>
                                            @else
                                                <div class="text-slate-400 text-[11px] italic">- NIK belum diisi -</div>
                                            @endif

                                            @if($user->position || $user->department)
                                                <div class="text-[11px] text-slate-600 font-medium">
                                                    {{ $user->position ?? '-' }}
                                                    @if($user->department)
                                                        <span class="text-slate-400 font-normal">({{ $user->department }})</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-4 px-5 align-top">
                                        <div class="flex flex-wrap gap-1 max-w-xs">
                                            @forelse($user->roles as $role)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-100">
                                                    {{ $role->name }}
                                                </span>
                                            @empty
                                                <span class="text-slate-400 italic text-[11px]">Belum ada role</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="py-4 px-5 align-top text-right">
                                        @if ($user->hasRole('administrator') || $user->is_fixed || $user->id === auth()->id())
                                            <span class="inline-flex items-center gap-1 text-slate-400 text-xs italic">
                                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                </svg>
                                                Dikelola administrator
                                            </span>
                                        @else
                                            <button type="button"
                                                onclick="openEditUserModal({{ json_encode([
                                                    'id' => $user->id,
                                                    'name' => $user->name,
                                                    'fullname' => $user->fullname,
                                                    'username' => $user->username,
                                                    'nik' => $user->nik,
                                                    'pharmacy_id' => $user->pharmacy_id,
                                                    'position' => $user->position,
                                                    'department' => $user->department,
                                                    'division' => $user->division,
                                                    'roles' => $user->roles->pluck('name')->toArray(),
                                                ]) }})"
                                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 active:scale-95 text-indigo-700 border border-indigo-200/80 text-xs font-bold transition shadow-2xs cursor-pointer">
                                                <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                Edit Informasi & Role
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-12 text-slate-400">
                                        <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <p class="font-medium">Tidak ada pengguna yang cocok dengan pencarian.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <div class="p-4 border-t border-slate-100">
                        {{ $users->links() }}
                    </div>
                @endif
            </div>

        </div>
    </section>

    {{-- ─── Modal Edit Informasi & Role Pengguna ─── --}}
    <div id="editUserModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
        <div class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-100 my-8 overflow-hidden transition-all transform scale-100">

            {{-- Modal Header --}}
            <div class="px-6 py-5 bg-gradient-to-r from-blue-600 via-indigo-600 to-sky-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center backdrop-blur-xs">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold" id="modalUserTitle">Edit Informasi Pengguna</h3>
                        <p class="text-xs text-blue-100 mt-0.5">Ubah profil akun, NIK, penempatan apotek, dan role</p>
                    </div>
                </div>
                <button type="button" onclick="closeEditUserModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition cursor-pointer text-white">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Modal Form --}}
            <form id="editUserForm" method="POST" action="" class="p-6 space-y-5">
                @csrf
                @method('PUT')

                {{-- Group 1: Profil Akun --}}
                <div class="space-y-3">
                    <div class="flex items-center gap-2 pb-1 border-b border-slate-100">
                        <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Informasi Dasar</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_name" class="block text-xs font-semibold text-slate-700 mb-1">
                                Nama Pengguna / Display <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" id="edit_name" name="name" required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none transition">
                        </div>
                        <div>
                            <label for="edit_fullname" class="block text-xs font-semibold text-slate-700 mb-1">
                                Nama Lengkap
                            </label>
                            <input type="text" id="edit_fullname" name="fullname"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none transition"
                                placeholder="Nama lengkap sesuai identitas">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_username" class="block text-xs font-semibold text-slate-700 mb-1">
                                Username <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" id="edit_username" name="username" required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none transition">
                        </div>
                        <div>
                            <label for="edit_nik" class="block text-xs font-semibold text-slate-700 mb-1">
                                Nomor Induk Kependudukan (NIK)
                            </label>
                            <input type="text" id="edit_nik" name="nik"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none transition"
                                placeholder="16 digit NIK">
                        </div>
                    </div>
                </div>

                {{-- Group 2: Penempatan & Pekerjaan --}}
                <div class="space-y-3 pt-2">
                    <div class="flex items-center gap-2 pb-1 border-b border-slate-100">
                        <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Penempatan & Jabatan</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_pharmacy_id" class="block text-xs font-semibold text-slate-700 mb-1">
                                Apotek Cabang <span class="text-rose-500">*</span>
                            </label>
                            <select id="edit_pharmacy_id" name="pharmacy_id" required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none transition cursor-pointer">
                                @foreach ($pharmacies as $pharmacy)
                                    <option value="{{ $pharmacy->id }}">{{ $pharmacy->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="edit_position" class="block text-xs font-semibold text-slate-700 mb-1">
                                Jabatan / Posisi
                            </label>
                            <input type="text" id="edit_position" name="position"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none transition"
                                placeholder="Contoh: Apoteker, Kasir, Staff Gudang">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_department" class="block text-xs font-semibold text-slate-700 mb-1">
                                Departemen
                            </label>
                            <input type="text" id="edit_department" name="department"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none transition"
                                placeholder="Contoh: Farmasi, Keuangan, Operasional">
                        </div>
                        <div>
                            <label for="edit_division" class="block text-xs font-semibold text-slate-700 mb-1">
                                Divisi
                            </label>
                            <input type="text" id="edit_division" name="division"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none transition"
                                placeholder="Contoh: Cabang, Gudang Pusat">
                        </div>
                    </div>
                </div>

                {{-- Group 3: Password / PIN Baru --}}
                <div class="space-y-3 pt-2">
                    <div class="flex items-center gap-2 pb-1 border-b border-slate-100">
                        <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Keamanan Akun</h4>
                    </div>

                    <div>
                        <label for="edit_password" class="block text-xs font-semibold text-slate-700 mb-1">
                            Password / PIN Baru
                        </label>
                        <input type="password" id="edit_password" name="password" minlength="4"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none transition"
                            placeholder="Biarkan kosong jika tidak ingin mengubah password / PIN">
                        <p class="text-[11px] text-slate-400 mt-1">Kosongkan jika tidak ingin mengganti password/PIN user.</p>
                    </div>
                </div>

                {{-- Group 4: Role & Hak Akses --}}
                <div class="space-y-3 pt-2">
                    <div class="flex items-center justify-between pb-1 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Role & Hak Akses</h4>
                        </div>
                        <span class="text-[11px] text-slate-400">Pilih satu atau beberapa role</span>
                    </div>

                    <div id="modal_roles_container" class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 p-3.5 bg-slate-50 border border-slate-200/80 rounded-2xl max-h-48 overflow-y-auto">
                        @foreach ($roles as $role)
                            <label class="flex items-center gap-2 text-xs text-slate-700 hover:text-slate-900 cursor-pointer select-none p-1 rounded hover:bg-white transition">
                                <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                    class="modal-role-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span class="truncate">{{ $role->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Modal Actions --}}
                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeEditUserModal()"
                        class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:scale-95 text-white text-xs font-bold shadow-md shadow-blue-500/20 transition cursor-pointer flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Script Modal Management --}}
    <script>
        function openEditUserModal(user) {
            const modal = document.getElementById('editUserModal');
            const form = document.getElementById('editUserForm');

            // Set Form action
            form.action = "{{ url('general-manager/users') }}/" + user.id;

            // Set Title & Inputs
            document.getElementById('modalUserTitle').innerText = 'Edit Informasi: ' + (user.name || user.username);
            document.getElementById('edit_name').value = user.name || '';
            document.getElementById('edit_fullname').value = user.fullname || '';
            document.getElementById('edit_username').value = user.username || '';
            document.getElementById('edit_nik').value = user.nik || '';
            document.getElementById('edit_pharmacy_id').value = user.pharmacy_id || '';
            document.getElementById('edit_position').value = user.position || '';
            document.getElementById('edit_department').value = user.department || '';
            document.getElementById('edit_division').value = user.division || '';
            document.getElementById('edit_password').value = '';

            // Set Roles Checkboxes
            const checkboxes = document.querySelectorAll('.modal-role-checkbox');
            const userRoles = Array.isArray(user.roles) ? user.roles : [];
            checkboxes.forEach(cb => {
                cb.checked = userRoles.includes(cb.value);
            });

            // Show Modal
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeEditUserModal() {
            const modal = document.getElementById('editUserModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }

        // Close on backdrop click
        document.getElementById('editUserModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditUserModal();
            }
        });

        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditUserModal();
            }
        });
    </script>
@endsection
