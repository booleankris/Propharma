@extends('layouts.app')
@section('style')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        .kasir-home,
        .kasir-home * {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .stat-card-glow {
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card-glow:hover {
            transform: translateY(-2px);
        }

        .kbd-badge {
            background: rgba(255, 255, 255, 0.22);
            border: 1px solid rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(4px);
            border-radius: 6px;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
        }

        .menu-tile {
            transition: all 0.2s ease;
        }

        .menu-tile:hover {
            transform: translateY(-2px);
        }
    </style>
@endsection

@section('content')
    <div class="kasir-home px-4 md:px-8 py-2 w-full space-y-5">

        {{-- ======================================================== --}}
        {{-- 1. HEADER SECTION (GREETING & REALTIME STATUS)          --}}
        {{-- ======================================================== --}}
        <div
            class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center gap-3.5">
                <div
                    class="inline-flex justify-center items-center w-12 h-12 rounded-2xl {{ isWarehousePharmacy() ? 'bg-violet-50 text-violet-600 border-violet-100' : (isOnlineRole() ? (auth()->user()->hasRole('Digital') ? 'bg-indigo-50 text-indigo-600 border-indigo-100' : (auth()->user()->hasRole('Online Shopee') ? 'bg-orange-50 text-orange-600 border-orange-100' : (auth()->user()->hasRole('Online Grab') ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-teal-50 text-teal-600 border-teal-100'))) : 'bg-blue-50 text-blue-600 border-blue-100') }} border shadow-sm shrink-0">
                    @if (isWarehousePharmacy())
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M3 21v-13l9 -4l9 4v13" />
                            <path d="M13 13h4v8h-10v-6h6" />
                            <path d="M13 21v-9a1 1 0 0 0 -1 -1h-2a1 1 0 0 0 -1 1v3" />
                        </svg>
                    @elseif (isOnlineRole())
                        @if (auth()->user()->hasRole('Digital'))
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M6 5a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2v-14z" />
                                <path d="M11 4h2" />
                                <path d="M12 17v.01" />
                            </svg>
                        @elseif (auth()->user()->hasRole('Online Shopee'))
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path
                                    d="M6.331 8h11.339a2 2 0 0 1 1.977 2.304l-1.255 8.152a3 3 0 0 1 -2.966 2.544h-6.852a3 3 0 0 1 -2.965 -2.544l-1.255 -8.152a2 2 0 0 1 1.977 -2.304z" />
                                <path d="M9 11v-5a3 3 0 0 1 6 0v5" />
                            </svg>
                        @elseif (auth()->user()->hasRole('Online Grab'))
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M5 17h-2v-11a1 1 0 0 1 1 -1h9v12m-4 0h6m4 0h2v-6h-1.8m-4.2 0h6l-3 -5h-4" />
                            </svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M3 21l1.65 -3.8a9 9 0 1 1 3.4 2.9l-5.05 .9" />
                                <path
                                    d="M9 10a.5 .5 0 0 0 1 0v-1a.5 .5 0 0 0 -1 0v1a5 5 0 0 0 5 5h1a.5 .5 0 0 0 0 -1h-1a.5 .5 0 0 0 0 1" />
                            </svg>
                        @endif
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M3 12a9 9 0 1 0 3-6.708" />
                            <path d="M3 4v5h5" />
                            <path d="M12 7v5l3 3" />
                        </svg>
                    @endif
                </div>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-slate-800 tracking-tight leading-tight">
                        {{ isWarehousePharmacy() ? 'Dashboard Gudang Logistik' : (isOnlineRole() ? 'Dashboard Kasir ' . getOnlineChannelName() : 'Dashboard Kasir') }}
                    </h1>
                    <p class="text-xs text-slate-500 font-medium mt-0.5">
                        {{ isWarehousePharmacy() ? 'Panel monitoring pengadaan, penerimaan & mutasi barang cabang' : (isOnlineRole() ? 'Panel transaksi & pengelolaan penjualan pesanan online' : 'Selamat datang, pantau performa kasir & akses transaksi dengan cepat') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2.5 self-start md:self-auto">
                <div
                    class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-slate-100/80 border border-slate-200 text-slate-700 text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span id="live-clock">{{ date('d M Y') }}</span>
                </div>
                <div
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-blue-50 border border-blue-200/70 text-blue-700 text-xs font-bold">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <circle cx="12" cy="7" r="4" />
                        <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                    </svg>
                    {{ auth()->user()->name ?? 'Kasir' }}
                </div>
            </div>
        </div>

        {{-- Digital Role Banner --}}
        @if (auth()->user()->hasRole('Digital'))
            <div
                class="p-4 rounded-2xl bg-gradient-to-r from-indigo-500/10 via-purple-500/10 to-blue-500/10 border border-indigo-200/60 flex items-center justify-between gap-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-600 to-purple-600 text-white flex items-center justify-center shadow-md shadow-indigo-500/20 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 9h.01" />
                            <path d="M11 12h1v4h1" />
                            <path d="M12 3c7.2 0 9 1.8 9 9s-1.8 9 -9 9s-9 -1.8 -9 -9s1.8 -9 9 -9z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-indigo-950 uppercase tracking-wider">Entri Penjualan Aplikasi
                            Digital</h4>
                        <p class="text-[12px] text-slate-600 mt-0.5">Pencatatan manual pesanan aplikasi digital sementara
                            sebelum integrasi API. Seluruh transaksi otomatis terangkum pada <strong>LIPH Online (Sheet
                                Aplikasi Digital)</strong>.</p>
                    </div>
                </div>
                <span
                    class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200 shrink-0">
                    <span class="w-2 h-2 rounded-full bg-indigo-600 animate-pulse"></span>
                    Manual Input Pre-API
                </span>
            </div>
        @endif

        {{-- HO Banner --}}
        @role('HO')
            <div
                class="p-5 rounded-2xl bg-gradient-to-r from-indigo-950 via-slate-900 to-blue-950 text-white shadow-md border border-indigo-900/60 flex flex-col sm:flex-row items-center justify-between gap-4 relative overflow-hidden">
                <div
                    class="absolute -right-10 -bottom-10 w-44 h-44 bg-indigo-500/20 rounded-full blur-2xl pointer-events-none">
                </div>
                <div class="flex items-center gap-4 relative z-10">
                    <div
                        class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center border border-white/20 text-indigo-200 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M9 12v-4" />
                            <path d="M15 12v-2" />
                            <path d="M12 12v-1" />
                            <path d="M3 4h18" />
                            <path d="M4 4v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-10" />
                            <path d="M12 16v4" />
                            <path d="M9 20h6" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold tracking-tight text-white">Executive Analytics &amp; Monitoring HO
                            </h3>
                            <span
                                class="text-[9px] font-extrabold px-2 py-0.5 rounded-full bg-indigo-500/40 text-indigo-200 border border-indigo-400/30 uppercase">Eksklusif
                                HO</span>
                        </div>
                        <p class="text-xs text-indigo-200/90 mt-0.5">Monitoring omzet penjualan, belanja pembelian, retur, tren
                            bulanan &amp; grafik jenis transaksi</p>
                    </div>
                </div>
                <a href="{{ route('ho.analytics') }}"
                    class="relative z-10 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white hover:bg-indigo-50 text-indigo-950 text-xs font-bold shadow-sm transition-all hover:scale-105 whitespace-nowrap">
                    Buka Dashboard HO
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M5 12l14 0" />
                        <path d="M13 18l6 -6" />
                        <path d="M13 6l6 6" />
                    </svg>
                </a>
            </div>
        @endrole

        {{-- ======================================================== --}}
        {{-- 2. METRIC CARDS (DIPINDAHKAN KE ATAS)                   --}}
        {{-- ======================================================== --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 {{ isOnlineRole() ? 'lg:grid-cols-3' : 'lg:grid-cols-4' }} gap-4">

            {{-- Card 1: Penjualan Hari Ini --}}
            <div
                class="stat-card-glow relative overflow-hidden rounded-2xl border border-blue-200/70 bg-gradient-to-br from-blue-600 via-blue-600 to-indigo-700 p-4 sm:p-5 text-white shadow-md shadow-blue-500/10">
                <div class="flex items-start justify-between">
                    <div>
                        <span
                            class="text-[11px] font-bold uppercase tracking-wider text-blue-100 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-300"></span>
                            {{ isOnlineRole() ? 'Penjualan Hari Ini' : 'Penjualan Hari Ini' }}
                        </span>
                        <h3 class="text-xl sm:text-2xl font-extrabold text-white mt-1 tracking-tight truncate">
                            {{ $today_sales_rp }}</h3>
                    </div>
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 backdrop-blur border border-white/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path
                                d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 0 4h12a1 1 0 0 1 1 1v3m0 4v3a1 1 0 0 1 -1 1h-12a2 2 0 0 1 -2 -2v-12" />
                            <path d="M20 12v4h-4a2 2 0 0 1 0 -4h4" />
                        </svg>
                    </div>
                </div>
                <div
                    class="mt-3 flex items-center justify-between pt-2 border-t border-white/15 text-[11px] text-blue-100">
                    <span>Transaksi hari ini:</span>
                    <span class="font-bold bg-white/20 px-2 py-0.5 rounded-full text-white">{{ $today_qty_sales ?? 0 }}
                        Struk</span>
                </div>
            </div>

            {{-- Card 2: Penjualan Bulan Ini --}}
            <div
                class="stat-card-glow relative overflow-hidden rounded-2xl border border-indigo-200/70 bg-white p-4 sm:p-5 shadow-sm hover:border-indigo-300">
                <div class="flex items-start justify-between">
                    <div>
                        <span
                            class="text-[11px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                            Bulan Ini ({{ date('M Y') }})
                        </span>
                        <h3 class="text-xl sm:text-2xl font-extrabold text-slate-800 mt-1 tracking-tight truncate">
                            {{ $month_sales_rp }}</h3>
                    </div>
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
                            <path d="M12 7v5l3 3" />
                        </svg>
                    </div>
                </div>
                <div
                    class="mt-3 flex items-center justify-between pt-2 border-t border-slate-100 text-[11px] text-slate-500">
                    <span>Total Akumulasi:</span>
                    <span class="font-bold text-indigo-600">{{ $total_sales_rp }}</span>
                </div>
            </div>

            @if (isOnlineRole())
                {{-- Card 3 (Online): Total Transaksi --}}
                <div
                    class="stat-card-glow relative overflow-hidden rounded-2xl border border-emerald-200/70 bg-white p-4 sm:p-5 shadow-sm hover:border-emerald-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <span
                                class="text-[11px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Total Transaksi
                            </span>
                            <h3 class="text-xl sm:text-2xl font-extrabold text-slate-800 mt-1 tracking-tight truncate">
                                {{ number_format($qty_sales ?? 0, 0, ',', '.') }} <span
                                    class="text-sm font-semibold text-slate-400">Struk</span></h3>
                        </div>
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M9 5h10l2 7h-18l2 -7z" />
                                <path d="M9 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                            </svg>
                        </div>
                    </div>
                    <div
                        class="mt-3 flex items-center justify-between pt-2 border-t border-slate-100 text-[11px] text-slate-500">
                        <span>Channel:</span>
                        <span class="font-bold text-emerald-600">{{ getOnlineChannelName() }}</span>
                    </div>
                </div>
            @else
                {{-- Card 3 (Offline / Gudang): Total Pembelian --}}
                <div
                    class="stat-card-glow relative overflow-hidden rounded-2xl border border-emerald-200/70 bg-white p-4 sm:p-5 shadow-sm hover:border-emerald-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <span
                                class="text-[11px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Total Pembelian
                            </span>
                            <h3 class="text-xl sm:text-2xl font-extrabold text-slate-800 mt-1 tracking-tight truncate">
                                {{ $total_orders_rp }}</h3>
                        </div>
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M5 17h-2v-11a1 1 0 0 1 1 -1h9v12m-4 0h6m4 0h2v-6h-1.8m-4.2 0h6l-3 -5h-4" />
                            </svg>
                        </div>
                    </div>
                    <div
                        class="mt-3 flex items-center justify-between pt-2 border-t border-slate-100 text-[11px] text-slate-500">
                        <span>Pengadaan Logistik:</span>
                        <span class="font-bold text-emerald-600">Pesanan Selesai</span>
                    </div>
                </div>

                {{-- Card 4 (Offline / Gudang): Total Retur / Ditolak --}}
                <div
                    class="stat-card-glow relative overflow-hidden rounded-2xl border border-rose-200/70 bg-white p-4 sm:p-5 shadow-sm hover:border-rose-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <span
                                class="text-[11px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                Total Retur
                            </span>
                            <h3 class="text-xl sm:text-2xl font-extrabold text-slate-800 mt-1 tracking-tight truncate">
                                {{ $total_reject_rp }}</h3>
                        </div>
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 border border-rose-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M9 14l-4 -4l4 -4" />
                                <path d="M5 10h11a4 4 0 1 1 0 8h-1" />
                            </svg>
                        </div>
                    </div>
                    <div
                        class="mt-3 flex items-center justify-between pt-2 border-t border-slate-100 text-[11px] text-slate-500">
                        <span>Barang Rusak/Kembali:</span>
                        <span class="font-bold text-rose-600">Retur Obat</span>
                    </div>
                </div>
            @endif

        </div>

        {{-- ======================================================== --}}
        {{-- 3. AKSI UTAMA & PINTASAN SHORTCUT                        --}}
        {{-- ======================================================== --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 md:p-6 shadow-sm space-y-6">

            <div>
                <div class="flex items-center justify-between mb-3 px-1">
                    <div class="flex items-center gap-2">
                        <span
                            class="w-2.5 h-2.5 rounded-full {{ isWarehousePharmacy() ? 'bg-violet-500' : 'bg-blue-500' }} animate-pulse"></span>
                        <h2 class="text-xs font-bold uppercase tracking-widest text-slate-700">
                            {{ isWarehousePharmacy() ? 'Aksi Utama Gudang' : 'Pintasan Transaksi Utama' }}
                        </h2>
                    </div>
                    <span class="text-[11px] text-slate-400 font-medium hidden sm:inline-flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <rect x="2" y="6" width="20" height="12" rx="2" />
                            <path d="M6 10h.01" />
                            <path d="M10 10h.01" />
                            <path d="M14 10h.01" />
                            <path d="M18 10h.01" />
                            <path d="M6 14h12" />
                        </svg>
                        Mendukung pintasan keyboard
                    </span>
                </div>

                <div class="grid grid-cols-1 {{ isOnlineRole() ? 'max-w-xl' : 'md:grid-cols-2' }} gap-4">
                    @if (isWarehousePharmacy())
                        {{-- Mutasi Stok untuk Gudang --}}
                        <a onclick="openModal('transfersModal')" id="btn-action-primary"
                            class="group relative flex items-center justify-between rounded-2xl p-5 transition-all duration-300 bg-gradient-to-r from-violet-600 via-violet-700 to-indigo-700 text-white shadow-md shadow-violet-500/20 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-violet-500/30 border border-violet-500/30 cursor-pointer">
                            <div class="flex items-center gap-4 relative z-10">
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/15 backdrop-blur-md border border-white/20 shadow-inner transition-transform duration-300 group-hover:scale-105">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M20 10h-16l5.5 -6" />
                                        <path d="M4 14h16l-5.5 6" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-base font-bold tracking-tight">Mutasi Stok Cabang</span>
                                        <span
                                            class="kbd-badge text-[10px] font-extrabold px-2 py-0.5 uppercase tracking-wider text-white">F1</span>
                                    </div>
                                    <span class="text-xs text-violet-100/90 font-medium block mt-0.5">Distribusi &amp;
                                        transfer barang ke apotek cabang</span>
                                </div>
                            </div>
                            <div
                                class="relative z-10 flex items-center justify-center w-8 h-8 rounded-full bg-white/10 text-white/80 group-hover:bg-white/20 group-hover:text-white transition-all shrink-0 ml-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-0.5"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M9 6l6 6l-6 6" />
                                </svg>
                            </div>
                        </a>

                        {{-- Pembelian untuk Gudang --}}
                        <a onclick="openModal('receivingModal')" id="btn-action-secondary"
                            class="group relative flex items-center justify-between rounded-2xl p-5 transition-all duration-300 bg-gradient-to-r from-emerald-600 via-emerald-700 to-teal-700 text-white shadow-md shadow-emerald-500/20 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-emerald-500/30 border border-emerald-500/30 cursor-pointer">
                            <div class="flex items-center gap-4 relative z-10">
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/15 backdrop-blur-md border border-white/20 shadow-inner transition-transform duration-300 group-hover:scale-105">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                        <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                        <path d="M17 17h-11v-14h-2" />
                                        <path d="M6 5l14 1l-1 7h-13" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-base font-bold tracking-tight">Penerimaan &amp; BPBA</span>
                                        <span
                                            class="kbd-badge text-[10px] font-extrabold px-2 py-0.5 uppercase tracking-wider text-white">F2</span>
                                    </div>
                                    <span class="text-xs text-emerald-100/90 font-medium block mt-0.5">Input faktur
                                        supplier, SP &amp; verifikasi penerimaan</span>
                                </div>
                            </div>
                            <div
                                class="relative z-10 flex items-center justify-center w-8 h-8 rounded-full bg-white/10 text-white/80 group-hover:bg-white/20 group-hover:text-white transition-all shrink-0 ml-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-0.5"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M9 6l6 6l-6 6" />
                                </svg>
                            </div>
                        </a>
                    @elseif (isOnlineRole())
                        {{-- Penjualan Online --}}
                        <a href="{{ url('transaction/upds') }}" id="btn-action-primary"
                            class="group relative flex items-center justify-between rounded-2xl p-5 transition-all duration-300 {{ auth()->user()->hasRole('Digital') ? 'bg-gradient-to-r from-indigo-600 via-purple-600 to-violet-700 shadow-indigo-500/20 border-indigo-500/30' : (auth()->user()->hasRole('Online Shopee') ? 'bg-gradient-to-r from-orange-600 to-amber-600 shadow-orange-500/20 border-orange-500/30' : (auth()->user()->hasRole('Online Grab') ? 'bg-gradient-to-r from-emerald-600 to-teal-600 shadow-emerald-500/20 border-emerald-500/30' : 'bg-gradient-to-r from-blue-600 to-indigo-600 shadow-blue-500/20 border-blue-500/30')) }} text-white shadow-md hover:-translate-y-0.5 hover:shadow-xl border">
                            <div class="flex items-center gap-4 relative z-10">
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/15 backdrop-blur-md border border-white/20 shadow-inner transition-transform duration-300 group-hover:scale-105">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                        <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                        <path d="M17 17h-11v-14h-2" />
                                        <path d="M6 5l14 1l-1 7h-13" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-base font-bold tracking-tight">{{ auth()->user()->hasRole('Digital') ? 'Entri Penjualan Digital' : 'Penjualan Kasir' }}</span>
                                        <span
                                            class="kbd-badge text-[10px] font-extrabold px-2 py-0.5 uppercase tracking-wider text-white">F1</span>
                                    </div>
                                    <span class="text-xs text-white/90 font-medium block mt-0.5">Catat transaksi penjualan
                                        pesanan {{ getOnlineChannelName() }}</span>
                                </div>
                            </div>
                            <div
                                class="relative z-10 flex items-center justify-center w-8 h-8 rounded-full bg-white/10 text-white/80 group-hover:bg-white/20 group-hover:text-white transition-all shrink-0 ml-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-0.5"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M9 6l6 6l-6 6" />
                                </svg>
                            </div>
                        </a>
                    @else
                        {{-- Penjualan Kasir Regular --}}
                        <a href="{{ url('transaction/upds') }}" id="btn-action-primary"
                            class="group relative flex items-center justify-between rounded-2xl p-5 transition-all duration-300 bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-700 text-white shadow-md shadow-blue-500/20 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-blue-500/30 border border-blue-500/30">
                            <div class="flex items-center gap-4 relative z-10">
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/15 backdrop-blur-md border border-white/20 shadow-inner transition-transform duration-300 group-hover:scale-105">
                                    {{-- Ikon Mesin Kasir / POS --}}
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <rect x="5" y="3" width="14" height="6" rx="1.5" />
                                        <path
                                            d="M4 14a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" />
                                        <path d="M8 15h.01" />
                                        <path d="M12 15h.01" />
                                        <path d="M16 15h.01" />
                                        <path d="M8 18h.01" />
                                        <path d="M12 18h.01" />
                                        <path d="M16 18h.01" />
                                        <path d="M12 3v-1" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-base font-bold tracking-tight">Penjualan</span>
                                        <span
                                            class="kbd-badge text-[10px] font-extrabold px-2 py-0.5 uppercase tracking-wider text-white">F1</span>
                                    </div>
                                    <span class="text-xs text-blue-100/90 font-medium block mt-0.5">Buka kasir &amp; catat
                                        penjualan obat / resep baru</span>
                                </div>
                            </div>
                            <div
                                class="relative z-10 flex items-center justify-center w-8 h-8 rounded-full bg-white/10 text-white/80 group-hover:bg-white/20 group-hover:text-white transition-all shrink-0 ml-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-0.5"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M9 6l6 6l-6 6" />
                                </svg>
                            </div>
                        </a>

                        {{-- Pembelian / Penerimaan Barang --}}
                        <a href="{{ route('receiving.index') }}" id="btn-action-secondary"
                            class="group relative flex items-center justify-between rounded-2xl p-5 transition-all duration-300 bg-gradient-to-r from-emerald-600 via-emerald-700 to-teal-700 text-white shadow-md shadow-emerald-500/20 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-emerald-500/30 border border-emerald-500/30">
                            <div class="flex items-center gap-4 relative z-10">
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/15 backdrop-blur-md border border-white/20 shadow-inner transition-transform duration-300 group-hover:scale-105">
                                    {{-- Ikon Penerimaan / Pembelian Barang --}}
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                        <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                        <path d="M17 17h-11v-14h-2" />
                                        <path d="M6 5l14 1l-1 7h-13" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-base font-bold tracking-tight">Penerimaan &amp; Pembelian</span>
                                        <span
                                            class="kbd-badge text-[10px] font-extrabold px-2 py-0.5 uppercase tracking-wider text-white">F2</span>
                                    </div>
                                    <span class="text-xs text-emerald-100/90 font-medium block mt-0.5">Kelola pesanan (SP),
                                        BPBA &amp; faktur masuk</span>
                                </div>
                            </div>
                            <div
                                class="relative z-10 flex items-center justify-center w-8 h-8 rounded-full bg-white/10 text-white/80 group-hover:bg-white/20 group-hover:text-white transition-all shrink-0 ml-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-0.5"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M9 6l6 6l-6 6" />
                                </svg>
                            </div>
                        </a>
                    @endif
                </div>
            </div>

            <hr class="border-t border-slate-100">

            {{-- ======================================================== --}}
            {{-- 4. PINTASAN DATA & LAPORAN                              --}}
            {{-- ======================================================== --}}
            <div>
                <h2 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-3.5 px-1 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-slate-400" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M3 4m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z" />
                        <path d="M7 8h10" />
                        <path d="M7 12h10" />
                        <path d="M7 16h10" />
                    </svg>
                    Data &amp; Laporan Operasional
                </h2>

                <div
                    class="grid grid-cols-2 sm:grid-cols-3 {{ isWarehousePharmacy() ? 'lg:grid-cols-4' : (isOnlineRole() ? 'max-w-xl' : 'lg:grid-cols-5') }} gap-3">
                    @if (isWarehousePharmacy())
                        {{-- Laporan Pembelian --}}
                        <a onclick="openModal('orderReportModal')"
                            class="menu-tile group flex flex-col items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-center hover:bg-white hover:border-orange-200 hover:shadow-md hover:shadow-orange-500/5 cursor-pointer">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-orange-100/80 text-orange-600 transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                    <path d="M9 17l0 -5" />
                                    <path d="M12 17l0 -1" />
                                    <path d="M15 17l0 -3" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-orange-600">Laporan
                                Pembelian</span>
                        </a>

                        {{-- Data Pembelian --}}
                        <a onclick="openModal('receivingModal')"
                            class="menu-tile group flex flex-col items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-center hover:bg-white hover:border-violet-200 hover:shadow-md hover:shadow-violet-500/5 cursor-pointer">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-100/80 text-violet-600 transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                    <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                    <path d="M17 17h-11v-14h-2" />
                                    <path d="M6 5l14 1l-1 7h-13" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-violet-600">Data
                                Pembelian</span>
                        </a>

                        {{-- Persediaan --}}
                        <a onclick="openModal('logModal')"
                            class="menu-tile group flex flex-col items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-center hover:bg-white hover:border-amber-200 hover:shadow-md hover:shadow-amber-500/5 cursor-pointer">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-100/80 text-amber-600 transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path
                                        d="M21 16v-8a2 2 0 0 0 -1 -1.73l-7 -4a2 2 0 0 0 -2 0l-7 4a2 2 0 0 0 -1 1.73v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7 -4a2 2 0 0 0 1 -1.73z" />
                                    <path d="M3.27 6.96l8.73 5.05l8.73 -5.05" />
                                    <path d="M12 22.08v-10" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-amber-600">Persediaan</span>
                        </a>

                        {{-- Mutasi Stok --}}
                        <a onclick="openModal('transfersModal')"
                            class="menu-tile group flex flex-col items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-center hover:bg-white hover:border-cyan-200 hover:shadow-md hover:shadow-cyan-500/5 cursor-pointer">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-100/80 text-cyan-600 transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M17 1l4 4l-4 4" />
                                    <path d="M3 11v-2a4 4 0 0 1 4 -4h14" />
                                    <path d="M7 23l-4 -4l4 -4" />
                                    <path d="M21 13v2a4 4 0 0 1 -4 4h-14" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-cyan-600">Mutasi Stok</span>
                        </a>
                    @elseif (isOnlineRole())
                        {{-- Data Penjualan --}}
                        <a onclick="openModal('salesModal')"
                            class="menu-tile group flex flex-col items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-center hover:bg-white hover:border-amber-200 hover:shadow-md hover:shadow-amber-500/5 cursor-pointer">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-100/80 text-amber-600 transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M3 13a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                    <path d="M15 9a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                    <path d="M9 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                    <path d="M4 20h14" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-amber-600">Data Penjualan</span>
                        </a>

                        {{-- Laporan Penjualan --}}
                        <a onclick="openModal('reportModal')"
                            class="menu-tile group flex flex-col items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-center hover:bg-white hover:border-rose-200 hover:shadow-md hover:shadow-rose-500/5 cursor-pointer">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-rose-100/80 text-rose-600 transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                    <path d="M9 17v-5l2 2l2 -2v5" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-rose-600">Laporan
                                Penjualan</span>
                        </a>
                    @else
                        {{-- Data Penjualan --}}
                        <a onclick="openModal('salesModal')"
                            class="menu-tile group flex flex-col items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-center hover:bg-white hover:border-amber-200 hover:shadow-md hover:shadow-amber-500/5 cursor-pointer">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-100/80 text-amber-600 transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M3 13a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                    <path d="M15 9a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                    <path d="M9 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                    <path d="M4 20h14" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-amber-600">Data Penjualan</span>
                        </a>

                        {{-- Data Pembelian --}}
                        <a onclick="openModal('receivingModal')"
                            class="menu-tile group flex flex-col items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-center hover:bg-white hover:border-violet-200 hover:shadow-md hover:shadow-violet-500/5 cursor-pointer">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-100/80 text-violet-600 transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                    <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                    <path d="M17 17h-11v-14h-2" />
                                    <path d="M6 5l14 1l-1 7h-13" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-violet-600">Data
                                Pembelian</span>
                        </a>

                        {{-- Laporan Penjualan --}}
                        <a onclick="openModal('reportModal')"
                            class="menu-tile group flex flex-col items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-center hover:bg-white hover:border-rose-200 hover:shadow-md hover:shadow-rose-500/5 cursor-pointer">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-rose-100/80 text-rose-600 transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                    <path d="M9 17v-5l2 2l2 -2v5" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-rose-600">Lap. Penjualan</span>
                        </a>

                        {{-- Laporan Pembelian --}}
                        <a onclick="openModal('orderReportModal')"
                            class="menu-tile group flex flex-col items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-center hover:bg-white hover:border-orange-200 hover:shadow-md hover:shadow-orange-500/5 cursor-pointer">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-orange-100/80 text-orange-600 transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                    <path d="M9 17l0 -5" />
                                    <path d="M12 17l0 -1" />
                                    <path d="M15 17l0 -3" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-orange-600">Lap.
                                Pembelian</span>
                        </a>

                        {{-- Pareto --}}
                        <a href="{{ route('pareto.index') }}"
                            class="menu-tile group flex flex-col items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-center hover:bg-white hover:border-pink-200 hover:shadow-md hover:shadow-pink-500/5 cursor-pointer">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-pink-100/80 text-pink-600 transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M4 19l16 0" />
                                    <path d="M4 15l4 -6l4 2l4 -5l4 4" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-pink-600">Analisis Pareto</span>
                        </a>
                    @endif
                </div>
            </div>

            {{-- ======================================================== --}}
            {{-- 5. MANAJEMEN & INVENTARIS                                --}}
            {{-- ======================================================== --}}
            @if (!isWarehousePharmacy() && !isOnlineRole())
                <hr class="border-t border-slate-100">

                <div>
                    <h2
                        class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-3.5 px-1 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-slate-400" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path
                                d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        Manajemen &amp; Logistik
                    </h2>

                    <div
                        class="grid grid-cols-2 sm:grid-cols-3 {{ auth()->user()->hasRole('HO') ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }} gap-3">
                        {{-- Master Data (Khusus HO) --}}
                        @role('HO')
                            <a onclick="openModal('masterModal')"
                                class="menu-tile group flex flex-col items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-center hover:bg-white hover:border-teal-200 hover:shadow-md hover:shadow-teal-500/5 cursor-pointer">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-teal-100/80 text-teal-600 transition-transform duration-200 group-hover:scale-110">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <ellipse cx="12" cy="5" rx="9" ry="3" />
                                        <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" />
                                        <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3v-14" />
                                    </svg>
                                </div>
                                <span class="text-xs font-bold text-slate-700 group-hover:text-teal-600">Master Data</span>
                            </a>
                        @endrole

                        {{-- Persediaan --}}
                        <a onclick="openModal('logModal')"
                            class="menu-tile group flex flex-col items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-center hover:bg-white hover:border-amber-200 hover:shadow-md hover:shadow-amber-500/5 cursor-pointer">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-100/80 text-amber-600 transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path
                                        d="M21 16v-8a2 2 0 0 0 -1 -1.73l-7 -4a2 2 0 0 0 -2 0l-7 4a2 2 0 0 0 -1 1.73v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7 -4a2 2 0 0 0 1 -1.73z" />
                                    <path d="M3.27 6.96l8.73 5.05l8.73 -5.05" />
                                    <path d="M12 22.08v-10" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-amber-600">Persediaan</span>
                        </a>

                        {{-- Mutasi --}}
                        <a onclick="openModal('transfersModal')"
                            class="menu-tile group flex flex-col items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-center hover:bg-white hover:border-cyan-200 hover:shadow-md hover:shadow-cyan-500/5 cursor-pointer">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-100/80 text-cyan-600 transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M17 1l4 4l-4 4" />
                                    <path d="M3 11v-2a4 4 0 0 1 4 -4h14" />
                                    <path d="M7 23l-4 -4l4 -4" />
                                    <path d="M21 13v2a4 4 0 0 1 -4 4h-14" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-cyan-600">Mutasi Stok</span>
                        </a>

                        {{-- Klaim Tagihan --}}
                        <a href="{{ url('invoices/') }}"
                            class="menu-tile group flex flex-col items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-center hover:bg-white hover:border-blue-200 hover:shadow-md hover:shadow-blue-500/5 cursor-pointer">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-100/80 text-blue-600 transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path
                                        d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2" />
                                    <path d="M9 7l6 0" />
                                    <path d="M9 11l6 0" />
                                    <path d="M9 15l4 0" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-blue-600">Klaim Tagihan</span>
                        </a>

                        {{-- Hutang Dagang --}}
                        <a href="{{ url('orders-payment/') }}"
                            class="menu-tile group flex flex-col items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-center hover:bg-white hover:border-orange-200 hover:shadow-md hover:shadow-orange-500/5 cursor-pointer">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-orange-100/80 text-orange-600 transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path
                                        d="M6 4h11a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-11a1 1 0 0 1 -1 -1v-14a1 1 0 0 1 1 -1m3 0v18" />
                                    <path d="M13 8l2 0" />
                                    <path d="M13 12l2 0" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-orange-600">Hutang Dagang</span>
                        </a>
                    </div>
                </div>
            @endif

        </div>

        {{-- ======================================================== --}}
        {{-- 6. BARANG MENDEKATI KADALUARSA (NEAR EXPIRY TABLE)      --}}
        {{-- ======================================================== --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-slate-50/50">
                <div class="flex items-center gap-2.5">
                    <span class="flex items-center justify-center w-8 h-8 rounded-xl bg-amber-100/80 text-amber-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 8v4l2 2" />
                            <path d="M3.05 11a9 9 0 1 1 .5 4m-.5-5v-5h5" />
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Barang Mendekati Kadaluarsa (Near-Expiry)</h3>
                        <p class="text-[11px] text-slate-400">Monitoring masa berlaku obat untuk pencegahan kerugian stok
                        </p>
                    </div>
                </div>
                <a href="{{ route('kasir.nearExpiry') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-100 transition-colors">
                    Lihat Semua
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M5 12l14 0" />
                        <path d="M13 6l6 6l-6 6" />
                    </svg>
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr
                            class="text-[11px] uppercase tracking-wider text-slate-500 bg-slate-50/40 border-b border-slate-100">
                            <th class="px-5 py-3.5 font-bold">Nama Obat</th>
                            <th class="px-5 py-3.5 font-bold">Nomor Batch</th>
                            <th class="px-5 py-3.5 font-bold">Expired Date</th>
                            <th class="px-5 py-3.5 font-bold">Sisa Hari</th>
                            <th class="px-5 py-3.5 font-bold text-right">Stok</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($nearExpiry as $item)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-5 py-3.5">
                                    <div class="font-bold text-slate-800">{{ $item->medicines->name ?? '-' }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $item->medicines->code ?? '-' }}
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-slate-600 font-mono text-xs">{{ $item->name }}</td>
                                <td class="px-5 py-3.5 text-slate-600 font-medium">{{ $item->expiry_formatted }}</td>
                                <td class="px-5 py-3.5">
                                    @if ($item->expiry_status === 'expired')
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            Expired ({{ abs($item->days_left) }} hari lalu)
                                        </span>
                                    @elseif ($item->expiry_status === 'near')
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            {{ $item->days_left }} hari lagi
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            {{ $item->days_left }} hari lagi
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right font-bold text-slate-800">
                                    {{ number_format($item->stock, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-slate-300"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <circle cx="12" cy="12" r="9" />
                                            <path d="M9 12l2 2l4 -4" />
                                        </svg>
                                        <span>Tidak ada barang yang mendekati masa kadaluarsa saat ini.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- Keyboard Shortcuts Listener (F1 / F2) --}}
    <script>
        document.addEventListener('keydown', function(e) {
            // Ignore shortcut when input or textarea is active
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
                return;
            }

            if (e.key === 'F1') {
                e.preventDefault();
                const primaryBtn = document.getElementById('btn-action-primary');
                if (primaryBtn) primaryBtn.click();
            } else if (e.key === 'F2') {
                e.preventDefault();
                const secondaryBtn = document.getElementById('btn-action-secondary');
                if (secondaryBtn) secondaryBtn.click();
            }
        });
    </script>
@endsection
