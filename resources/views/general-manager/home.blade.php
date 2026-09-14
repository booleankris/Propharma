@extends('layouts.app')

@section('title', 'Manajemen Role Pengguna')

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
                        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">Manajemen Role Users</h1>
                        <p class="text-blue-100 text-sm mt-1 max-w-2xl">
                            Atur dan delegasikan hak akses role pengguna apotek secara langsung. Pilih role yang sesuai pada masing-masing akun.
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
                            placeholder="Cari berdasarkan nama atau username..."
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
                        <h2 class="text-sm sm:text-base font-bold text-slate-800">Daftar Pengguna & Role</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Total {{ $users->total() }} akun pengguna terdaftar</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 text-[11px] font-bold uppercase tracking-wider">
                                <th class="py-3 px-5">Pengguna</th>
                                <th class="py-3 px-5">Apotek</th>
                                <th class="py-3 px-5">Role Saat Ini</th>
                                <th class="py-3 px-5">Ubah Role</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @forelse($users as $user)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-4 px-5 align-top">
                                        <div class="font-bold text-slate-800 text-[13px]">{{ $user->name }}</div>
                                        <div class="text-[11px] font-mono text-slate-400 mt-0.5">{{ $user->username }}</div>
                                    </td>
                                    <td class="py-4 px-5 align-top">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-medium">
                                            {{ $user->pharmacy->name ?? '-' }}
                                        </span>
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
                                    <td class="py-4 px-5 align-top">
                                        @if ($user->hasRole('administrator') || $user->is_fixed || $user->id === auth()->id())
                                            <span class="inline-flex items-center gap-1 text-slate-400 text-xs italic">
                                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                </svg>
                                                Dikelola administrator
                                            </span>
                                        @else
                                            <form method="POST" action="{{ route('general-manager.users.roles', $user) }}" class="space-y-3">
                                                @csrf
                                                @method('PATCH')
                                                <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3 max-h-36 overflow-y-auto space-y-1.5 min-w-[220px]">
                                                    @foreach ($roles as $role)
                                                        <label class="flex items-center gap-2 text-xs text-slate-700 hover:text-slate-900 cursor-pointer select-none">
                                                            <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                                                @checked($user->hasRole($role->name))
                                                                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                                            <span>{{ $role->name }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                                <button type="submit"
                                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 active:scale-95 text-white text-xs font-bold transition shadow-sm cursor-pointer">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    Simpan Role
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-12 text-slate-400">
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
@endsection
