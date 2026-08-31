@extends('layouts.app')
@section('style')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <style>
        .kasir-home,
        .kasir-home * {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }
    </style>
@endsection
@section('content')
    {{-- Section Product Preview --}}
    <div class="kasir-home px-2 py-2 md:px-[46px] md:py-[15px] mx-4 p-4 max-w-full grid grid-cols-12 gap-6">
        <!-- LEFT COLUMN -->
        <section class="col-span-12 lg:col-span-12">
            <!-- Header Card -->


            {{-- <div class="flex flex-col justify-center items-center h-[100vh] pt-4">
                
                <p class="font-normal text-navy-700 mt-20 mx-auto w-max">Profile Card component from <a
                        href="https://horizon-ui.com?ref=tailwindcomponents.com" target="_blank"
                        class="text-brand-500 font-bold">Horizon UI Tailwind React</a></p>
            </div> --}}
            <div class="flex items-center justify-start gap-3">
                <span
                    class="inline-flex justify-center items-center w-12 h-12 rounded-2xl bg-white {{ isWarehousePharmacy() ? 'text-violet-600' : (isOnlineRole() ? (auth()->user()->hasRole('Online Shopee') ? 'text-orange-500' : (auth()->user()->hasRole('Online Grab') ? 'text-emerald-500' : 'text-teal-600')) : 'text-[#008bff]') }} shadow-sm border border-slate-100">
                    @if (isWarehousePharmacy())
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"
                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M3 21v-13l9 -4l9 4v13" />
                            <path d="M13 13h4v8h-10v-6h6" />
                            <path d="M13 21v-9a1 1 0 0 0 -1 -1h-2a1 1 0 0 0 -1 1v3" />
                        </svg>
                    @elseif (isOnlineRole())
                        @if (auth()->user()->hasRole('Online Shopee'))
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M6.331 8h11.339a2 2 0 0 1 1.977 2.304l-1.255 8.152a3 3 0 0 1 -2.966 2.544h-6.852a3 3 0 0 1 -2.965 -2.544l-1.255 -8.152a2 2 0 0 1 1.977 -2.304z" />
                                <path d="M9 11v-5a3 3 0 0 1 6 0v5" />
                            </svg>
                        @elseif (auth()->user()->hasRole('Online Grab'))
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M5 17h-2v-11a1 1 0 0 1 1 -1h9v12m-4 0h6m4 0h2v-6h-1.8m-4.2 0h6l-3 -5h-4" />
                            </svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M3 21l1.65 -3.8a9 9 0 1 1 3.4 2.9l-5.05 .9" />
                                <path d="M9 10a.5 .5 0 0 0 1 0v-1a.5 .5 0 0 0 -1 0v1a5 5 0 0 0 5 5h1a.5 .5 0 0 0 0 -1h-1a.5 .5 0 0 0 0 1" />
                            </svg>
                        @endif
                    @else
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"
                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M3 12a9 9 0 1 0 3-6.708" />
                            <path d="M3 4v5h5" />
                            <path d="M12 7v5l3 3" />
                        </svg>
                    @endif
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 capitalize leading-tight">
                        {{ isWarehousePharmacy() ? 'Dashboard Gudang' : (isOnlineRole() ? 'Dashboard Kasir ' . getOnlineChannelName() : 'Dashboard Kasir') }}</h1>
                    <p class="text-xs text-slate-400">
                        {{ isWarehousePharmacy() ? 'Selamat datang di Panel Gudang Logistik & Distribusi' : (isOnlineRole() ? 'Selamat datang di Panel Kasir ' . getOnlineChannelName() . '. Silakan pilih menu untuk melanjutkan' : 'Selamat datang, dan silahkan pilih menu untuk melanjutkan') }}
                    </p>
                </div>
            </div>

            @role('HO')
            <div class="my-4 p-5 rounded-3xl bg-gradient-to-r from-indigo-900 via-indigo-800 to-blue-900 text-white shadow-lg border border-indigo-700/50 flex flex-col sm:flex-row items-center justify-between gap-4 relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-indigo-500/20 rounded-full blur-2xl pointer-events-none"></div>
                <div class="flex items-center gap-4 relative z-10">
                    <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center border border-white/20 text-indigo-200">
                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-presentation-analytics">
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
                            <h3 class="text-lg font-black tracking-tight text-white">Executive Analytics & Monitoring HO</h3>
                            <span class="text-[9px] font-extrabold px-2 py-0.5 rounded-full bg-indigo-500/40 text-indigo-200 border border-indigo-400/30 uppercase">Eksklusif HO</span>
                        </div>
                        <p class="text-xs text-indigo-200 mt-0.5">Monitoring pergerakan omset penjualan, belanja pembelian, retur, tren bulanan & grafik jenis transaksi</p>
                    </div>
                </div>
                <a href="{{ route('ho.analytics') }}"
                    class="relative z-10 inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-white hover:bg-indigo-50 text-indigo-950 text-xs font-black shadow-sm transition-all hover:scale-105 whitespace-nowrap">
                    Buka Dashboard HO
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-right">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M5 12l14 0" />
                        <path d="M13 18l6 -6" />
                        <path d="M13 6l6 6" />
                    </svg>
                </a>
            </div>
            @endrole

            <div class="p-5 md:p-6 my-4 bg-white rounded-2xl shadow-sm border border-slate-200 w-full mx-auto">
                <div class="space-y-6 w-full">

                    {{-- 1. HIGHLIGHT TRANSAKSI UTAMA --}}
                    <div>
                        <div class="flex items-center gap-2 mb-3 px-1">
                            <span
                                class="w-2 h-2 rounded-full {{ isWarehousePharmacy() ? 'bg-violet-500' : 'bg-blue-500' }} animate-pulse"></span>
                            <h2 class="text-[11px] font-bold uppercase tracking-widest text-slate-500">
                                {{ isWarehousePharmacy() ? 'Aksi Utama Gudang' : 'Aksi Transaksi' }}
                            </h2>
                        </div>

                        <div class="grid grid-cols-1 {{ isOnlineRole() ? 'max-w-xl' : 'md:grid-cols-2' }} gap-4 w-full">
                            @if (isWarehousePharmacy())
                                {{-- Mutasi Stok untuk Gudang --}}
                                <a onclick="openModal('transfersModal')"
                                    class="group relative flex items-center justify-between overflow-hidden rounded-xl px-5 py-4 transition-all duration-300 bg-gradient-to-r from-violet-600 to-indigo-600 text-white shadow-md shadow-violet-500/20 hover:-translate-y-1 hover:shadow-lg hover:shadow-violet-500/40 cursor-pointer">

                                    <div class="flex items-center gap-4 relative z-10">
                                        <div
                                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3">
                                            <svg class="text-white w-6 h-6" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                <path d="M20 10h-16l5.5 -6" />
                                                <path d="M4 14h16l-5.5 6" />
                                            </svg>
                                        </div>
                                        <div class="flex flex-col text-left">
                                            <span class="text-sm font-bold tracking-wide">Mutasi Stok</span>
                                            <span class="text-[11px] text-violet-100 font-medium">Distribusi & Kirim Barang
                                                ke Cabang</span>
                                        </div>
                                    </div>

                                    <div
                                        class="relative z-10 opacity-0 -translate-x-2 transition-all duration-300 group-hover:opacity-100 group-hover:translate-x-0">
                                        <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M5 12h14"></path>
                                            <path d="m12 5 7 7-7 7"></path>
                                        </svg>
                                    </div>

                                    <div
                                        class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10 blur-xl group-hover:bg-white/20 transition-all duration-300">
                                    </div>
                                </a>

                                {{-- Pembelian untuk Gudang --}}
                                <a onclick="openModal('receivingModal')"
                                    class="group relative flex items-center justify-between overflow-hidden rounded-xl px-5 py-4 transition-all duration-300 bg-gradient-to-r from-emerald-600 to-emerald-500 text-white shadow-md shadow-emerald-500/20 hover:-translate-y-1 hover:shadow-lg hover:shadow-emerald-500/40 cursor-pointer">

                                    <div class="flex items-center gap-4 relative z-10">
                                        <div
                                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3">
                                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                                <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                                <path d="M17 17h-11v-14h-2" />
                                                <path d="M6 5l14 1l-1 7h-13" />
                                            </svg>
                                        </div>
                                        <div class="flex flex-col text-left">
                                            <span class="text-sm font-bold tracking-wide">Pembelian</span>
                                            <span class="text-[11px] text-emerald-100 font-medium">Pemesanan (SP) &
                                                Penerimaan Barang</span>
                                        </div>
                                    </div>

                                    <div
                                        class="relative z-10 opacity-0 -translate-x-2 transition-all duration-300 group-hover:opacity-100 group-hover:translate-x-0">
                                        <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M5 12h14"></path>
                                            <path d="m12 5 7 7-7 7"></path>
                                        </svg>
                                    </div>

                                    <div
                                        class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10 blur-xl group-hover:bg-white/20 transition-all duration-300">
                                    </div>
                                </a>
                            @elseif (isOnlineRole())
                                {{-- Penjualan Online --}}
                                <a href="{{ url('transaction/upds') }}"
                                    class="group relative flex items-center justify-between overflow-hidden rounded-xl px-5 py-4 transition-all duration-300 {{ auth()->user()->hasRole('Online Shopee') ? 'bg-gradient-to-r from-orange-600 to-amber-500 shadow-orange-500/20 hover:shadow-orange-500/40' : (auth()->user()->hasRole('Online Grab') ? 'bg-gradient-to-r from-emerald-600 to-teal-500 shadow-emerald-500/20 hover:shadow-emerald-500/40' : 'bg-gradient-to-r from-blue-600 to-indigo-500 shadow-blue-500/20 hover:shadow-blue-500/40') }} text-white shadow-md hover:-translate-y-1 hover:shadow-lg">

                                    <div class="flex items-center gap-4 relative z-10">
                                        <div
                                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="text-white w-6 h-6"
                                                viewBox="0 0 24 24" fill="none" stroke="currentcolor" stroke-width="1.75"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path
                                                    d="M21 15h-2.5c-.398 0 -.779 .158 -1.061 .439c-.281 .281 -.439 .663 -.439 1.061c0 .398 .158 .779 .439 1.061c.281 .281 .663 .439 1.061 .439h1c.398 0 .779 .158 1.061 .439c.281 .281 .439 .663 .439 1.061c0 .398 -.158 .779 -.439 1.061c-.281 .281 -.663 .439 -1.061 .439h-2.5" />
                                                <path d="M19 21v1m0 -8v1" />
                                                <path
                                                    d="M13 21h-7c-.53 0 -1.039 -.211 -1.414 -.586c-.375 -.375 -.586 -.884 -.586 -1.414v-10c0 -.53 .211 -1.039 .586 -1.414c.375 -.375 .884 -.586 1.414 -.586h2m12 3.12v-1.12c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586h-2" />
                                                <path
                                                    d="M16 10v-6c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586h-4c-.53 0 -1.039 .211 -1.414 .586c-.375 .375 -.586 .884 -.586 1.414v6m8 0h-8m8 0h1m-9 0h-1" />
                                                <path d="M8 14v.01" />
                                                <path d="M8 17v.01" />
                                                <path d="M12 13.99v.01" />
                                                <path d="M12 17v.01" />
                                            </svg>
                                        </div>
                                        <div class="flex flex-col text-left">
                                            <span class="text-sm font-bold tracking-wide">Penjualan Kasir</span>
                                            <span class="text-[11px] text-white/90 font-medium">Catat transaksi penjualan pesanan {{ getOnlineChannelName() }}</span>
                                        </div>
                                    </div>

                                    <div
                                        class="relative z-10 opacity-0 -translate-x-2 transition-all duration-300 group-hover:opacity-100 group-hover:translate-x-0">
                                        <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M5 12h14"></path>
                                            <path d="m12 5 7 7-7 7"></path>
                                        </svg>
                                    </div>

                                    <div
                                        class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10 blur-xl group-hover:bg-white/20 transition-all duration-300">
                                    </div>
                                </a>
                            @else
                                {{-- Penjualan --}}
                                <a href="{{ url('transaction/upds') }}"
                                    class="group relative flex items-center justify-between overflow-hidden rounded-xl px-5 py-4 transition-all duration-300 bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-md shadow-blue-500/20 hover:-translate-y-1 hover:shadow-lg hover:shadow-blue-500/40">

                                    <div class="flex items-center gap-4 relative z-10">
                                        <div
                                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3">


                                            <svg xmlns="http://www.w3.org/2000/svg" class="text-white w-6 h-6"
                                                viewBox="0 0 24 24" fill="none" stroke="currentcolor" stroke-width="1.75"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path
                                                    d="M21 15h-2.5c-.398 0 -.779 .158 -1.061 .439c-.281 .281 -.439 .663 -.439 1.061c0 .398 .158 .779 .439 1.061c.281 .281 .663 .439 1.061 .439h1c.398 0 .779 .158 1.061 .439c.281 .281 .439 .663 .439 1.061c0 .398 -.158 .779 -.439 1.061c-.281 .281 -.663 .439 -1.061 .439h-2.5" />
                                                <path d="M19 21v1m0 -8v1" />
                                                <path
                                                    d="M13 21h-7c-.53 0 -1.039 -.211 -1.414 -.586c-.375 -.375 -.586 -.884 -.586 -1.414v-10c0 -.53 .211 -1.039 .586 -1.414c.375 -.375 .884 -.586 1.414 -.586h2m12 3.12v-1.12c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586h-2" />
                                                <path
                                                    d="M16 10v-6c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586h-4c-.53 0 -1.039 .211 -1.414 .586c-.375 .375 -.586 .884 -.586 1.414v6m8 0h-8m8 0h1m-9 0h-1" />
                                                <path d="M8 14v.01" />
                                                <path d="M8 17v.01" />
                                                <path d="M12 13.99v.01" />
                                                <path d="M12 17v.01" />
                                            </svg>

                                        </div>
                                        <div class="flex flex-col text-left">
                                            <span class="text-sm font-bold tracking-wide">Penjualan</span>
                                            <span class="text-[11px] text-blue-100 font-medium">Catat transaksi
                                                kasir/sales</span>
                                        </div>
                                    </div>

                                    <div
                                        class="relative z-10 opacity-0 -translate-x-2 transition-all duration-300 group-hover:opacity-100 group-hover:translate-x-0">
                                        <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M5 12h14"></path>
                                            <path d="m12 5 7 7-7 7"></path>
                                        </svg>
                                    </div>

                                    <!-- Decorative background element -->
                                    <div
                                        class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10 blur-xl group-hover:bg-white/20 transition-all duration-300">
                                    </div>
                                </a>

                                {{-- Pembelian --}}
                                <a href="{{ route('receiving.index') }}"
                                    class="group relative flex items-center justify-between overflow-hidden rounded-xl px-5 py-4 transition-all duration-300 bg-gradient-to-r from-emerald-600 to-emerald-500 text-white shadow-md shadow-emerald-500/20 hover:-translate-y-1 hover:shadow-lg hover:shadow-emerald-500/40">

                                    <div class="flex items-center gap-4 relative z-10">
                                        <div
                                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3">
                                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                                <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                                <path d="M17 17h-11v-14h-2" />
                                                <path d="M6 5l14 1l-1 7h-13" />
                                            </svg>
                                        </div>
                                        <div class="flex flex-col text-left">
                                            <span class="text-sm font-bold tracking-wide">Pembelian</span>
                                            <span class="text-[11px] text-emerald-100 font-medium">Buat BPBA & Terima
                                                Barang</span>
                                        </div>
                                    </div>

                                    <div
                                        class="relative z-10 opacity-0 -translate-x-2 transition-all duration-300 group-hover:opacity-100 group-hover:translate-x-0">
                                        <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M5 12h14"></path>
                                            <path d="m12 5 7 7-7 7"></path>
                                        </svg>
                                    </div>

                                    <!-- Decorative background element -->
                                    <div
                                        class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10 blur-xl group-hover:bg-white/20 transition-all duration-300">
                                    </div>
                                </a>
                            @endif
                        </div>
                    </div>

                    <hr class="border-t border-dashed border-slate-200">

                    {{-- 2. DATA & LAPORAN --}}
                    <div>
                        <h2 class="text-[11px] font-bold uppercase tracking-widest text-slate-400 mb-3 px-1">
                            Data &amp; Laporan
                        </h2>
                        <div
                            class="grid grid-cols-2 sm:grid-cols-3 {{ isWarehousePharmacy() ? 'lg:grid-cols-4' : (isOnlineRole() ? 'max-w-xl' : 'lg:grid-cols-5') }} gap-3">
                            @if (isWarehousePharmacy())
                                {{-- Laporan Pembelian --}}
                                <a onclick="openModal('orderReportModal')"
                                    class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:border-orange-200 hover:shadow-md hover:shadow-orange-500/5 cursor-pointer">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-100 text-orange-600 transition-transform duration-200 group-hover:scale-110">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                            <path
                                                d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                            <path d="M9 17l0 -5" />
                                            <path d="M12 17l0 -1" />
                                            <path d="M15 17l0 -3" />
                                        </svg>
                                    </div>
                                    <span
                                        class="text-[11px] font-semibold text-slate-600 group-hover:text-orange-700">Laporan
                                        Pembelian</span>
                                </a>

                                {{-- Data Pembelian --}}
                                <a onclick="openModal('receivingModal')"
                                    class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:border-violet-200 hover:shadow-md hover:shadow-violet-500/5 cursor-pointer">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-100 text-violet-600 transition-transform duration-200 group-hover:scale-110">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                            <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                            <path d="M17 17h-11v-14h-2" />
                                            <path d="M6 5l14 1l-1 7h-13" />
                                        </svg>
                                    </div>
                                    <span class="text-[11px] font-semibold text-slate-600 group-hover:text-violet-700">Data
                                        Pembelian</span>
                                </a>
                                {{-- Persediaan --}}
                                <a onclick="openModal('logModal')"
                                    class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:border-amber-200 hover:shadow-md hover:shadow-amber-500/5 cursor-pointer">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-600 transition-transform duration-200 group-hover:scale-110">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path
                                                d="M21 16v-8a2 2 0 0 0 -1 -1.73l-7 -4a2 2 0 0 0 -2 0l-7 4a2 2 0 0 0 -1 1.73v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7 -4a2 2 0 0 0 1 -1.73z" />
                                            <path d="M3.27 6.96l8.73 5.05l8.73 -5.05" />
                                            <path d="M12 22.08v-10" />
                                        </svg>
                                    </div>
                                    <span
                                        class="text-[11px] font-semibold text-slate-600 group-hover:text-amber-700">Persediaan</span>
                                </a>

                                {{-- Mutasi --}}
                                <a onclick="openModal('transfersModal')"
                                    class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:border-cyan-200 hover:shadow-md hover:shadow-cyan-500/5 cursor-pointer">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-100 text-cyan-600 transition-transform duration-200 group-hover:scale-110">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M17 1l4 4l-4 4" />
                                            <path d="M3 11v-2a4 4 0 0 1 4 -4h14" />
                                            <path d="M7 23l-4 -4l4 -4" />
                                            <path d="M21 13v2a4 4 0 0 1 -4 4h-14" />
                                        </svg>
                                    </div>
                                    <span class="text-[11px] font-semibold text-slate-600 group-hover:text-cyan-700">Mutasi
                                        Stok</span>
                                </a>
                            @elseif (isOnlineRole())
                                {{-- Data Penjualan --}}
                                <a onclick="openModal('salesModal')"
                                    class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:border-amber-200 hover:shadow-md hover:shadow-amber-500/5 cursor-pointer">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-600 transition-transform duration-200 group-hover:scale-110">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path
                                                d="M3 13a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                            <path
                                                d="M15 9a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                            <path
                                                d="M9 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                            <path d="M4 20h14" />
                                        </svg>
                                    </div>
                                    <span class="text-[11px] font-semibold text-slate-600 group-hover:text-amber-700">Data
                                        Penjualan</span>
                                </a>

                                {{-- Laporan Penjualan --}}
                                <a onclick="openModal('reportModal')"
                                    class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:border-rose-200 hover:shadow-md hover:shadow-rose-500/5 cursor-pointer">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-rose-100 text-rose-600 transition-transform duration-200 group-hover:scale-110">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                            <path
                                                d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                            <path d="M9 17v-5l2 2l2 -2v5" />
                                        </svg>
                                    </div>
                                    <span class="text-[11px] font-semibold text-slate-600 group-hover:text-rose-700">Laporan
                                        Penjualan</span>
                                </a>
                            @else
                                {{-- Data Penjualan --}}
                                <a onclick="openModal('salesModal')"
                                    class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:border-amber-200 hover:shadow-md hover:shadow-amber-500/5 cursor-pointer">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-600 transition-transform duration-200 group-hover:scale-110">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path
                                                d="M3 13a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                            <path
                                                d="M15 9a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                            <path
                                                d="M9 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                            <path d="M4 20h14" />
                                        </svg>
                                    </div>
                                    <span class="text-[11px] font-semibold text-slate-600 group-hover:text-amber-700">Data
                                        Penjualan</span>
                                </a>

                                {{-- Data Pembelian --}}
                                <a onclick="openModal('receivingModal')"
                                    class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:border-violet-200 hover:shadow-md hover:shadow-violet-500/5 cursor-pointer">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-100 text-violet-600 transition-transform duration-200 group-hover:scale-110">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                            <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                            <path d="M17 17h-11v-14h-2" />
                                            <path d="M6 5l14 1l-1 7h-13" />
                                        </svg>
                                    </div>
                                    <span class="text-[11px] font-semibold text-slate-600 group-hover:text-violet-700">Data
                                        Pembelian</span>
                                </a>

                                {{-- Laporan Penjualan --}}
                                <a onclick="openModal('reportModal')"
                                    class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:border-rose-200 hover:shadow-md hover:shadow-rose-500/5 cursor-pointer">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-rose-100 text-rose-600 transition-transform duration-200 group-hover:scale-110">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                            <path
                                                d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                            <path d="M9 17v-5l2 2l2 -2v5" />
                                        </svg>
                                    </div>
                                    <span class="text-[11px] font-semibold text-slate-600 group-hover:text-rose-700">Lap.
                                        Penjualan</span>
                                </a>

                                {{-- Laporan Pembelian --}}
                                <a onclick="openModal('orderReportModal')"
                                    class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:border-orange-200 hover:shadow-md hover:shadow-orange-500/5 cursor-pointer">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-100 text-orange-600 transition-transform duration-200 group-hover:scale-110">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                            <path
                                                d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                            <path d="M9 17l0 -5" />
                                            <path d="M12 17l0 -1" />
                                            <path d="M15 17l0 -3" />
                                        </svg>
                                    </div>
                                    <span class="text-[11px] font-semibold text-slate-600 group-hover:text-orange-700">Lap.
                                        Pembelian</span>
                                </a>

                                {{-- Pareto --}}
                                <a href="{{ route('pareto.index') }}"
                                    class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:border-pink-200 hover:shadow-md hover:shadow-pink-500/5 cursor-pointer">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-pink-100 text-pink-600 transition-transform duration-200 group-hover:scale-110">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M4 19l16 0" />
                                            <path d="M4 15l4 -6l4 2l4 -5l4 4" />
                                        </svg>
                                    </div>
                                    <span
                                        class="text-[11px] font-semibold text-slate-600 group-hover:text-pink-700">Pareto</span>
                                </a>
                            @endif
                        </div>
                    </div>

                    @if (!isWarehousePharmacy() && !isOnlineRole())
                        <hr class="border-t border-dashed border-slate-200">

                        {{-- 3. MANAJEMEN --}}
                        <div>
                            <h2 class="text-[11px] font-bold uppercase tracking-widest text-slate-400 mb-3 px-1">
                                Manajemen
                            </h2>
                            <div
                                class="grid grid-cols-2 sm:grid-cols-3 {{ isWarehousePharmacy() ? 'lg:grid-cols-2 max-w-xl' : (auth()->user()->hasRole('HO') ? 'lg:grid-cols-5' : 'lg:grid-cols-4') }} gap-3">
                                {{-- Master Data (Khusus HO) --}}
                                @role('HO')
                                <a onclick="openModal('masterModal')"
                                    class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:border-teal-200 hover:shadow-md hover:shadow-teal-500/5 cursor-pointer">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-100 text-teal-600 transition-transform duration-200 group-hover:scale-110">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <ellipse cx="12" cy="5" rx="9" ry="3" />
                                            <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" />
                                            <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3v-14" />
                                        </svg>
                                    </div>
                                    <span class="text-[11px] font-semibold text-slate-600 group-hover:text-teal-700">Master
                                        Data</span>
                                </a>
                                @endrole

                                {{-- Persediaan --}}
                                <a onclick="openModal('logModal')"
                                    class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:border-amber-200 hover:shadow-md hover:shadow-amber-500/5 cursor-pointer">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-600 transition-transform duration-200 group-hover:scale-110">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path
                                                d="M21 16v-8a2 2 0 0 0 -1 -1.73l-7 -4a2 2 0 0 0 -2 0l-7 4a2 2 0 0 0 -1 1.73v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7 -4a2 2 0 0 0 1 -1.73z" />
                                            <path d="M3.27 6.96l8.73 5.05l8.73 -5.05" />
                                            <path d="M12 22.08v-10" />
                                        </svg>
                                    </div>
                                    <span
                                        class="text-[11px] font-semibold text-slate-600 group-hover:text-amber-700">Persediaan</span>
                                </a>

                                {{-- Mutasi --}}
                                <a onclick="openModal('transfersModal')"
                                    class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:border-cyan-200 hover:shadow-md hover:shadow-cyan-500/5 cursor-pointer">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-100 text-cyan-600 transition-transform duration-200 group-hover:scale-110">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M17 1l4 4l-4 4" />
                                            <path d="M3 11v-2a4 4 0 0 1 4 -4h14" />
                                            <path d="M7 23l-4 -4l4 -4" />
                                            <path d="M21 13v2a4 4 0 0 1 -4 4h-14" />
                                        </svg>
                                    </div>
                                    <span
                                        class="text-[11px] font-semibold text-slate-600 group-hover:text-cyan-700">Mutasi</span>
                                </a>

                                {{-- Klaim Tagihan --}}
                                <a href="{{ url('invoices/') }}"
                                    class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:border-blue-200 hover:shadow-md hover:shadow-blue-500/5 cursor-pointer">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-600 transition-transform duration-200 group-hover:scale-110">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path
                                                d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2" />
                                            <path d="M9 7l6 0" />
                                            <path d="M9 11l6 0" />
                                            <path d="M9 15l4 0" />
                                        </svg>
                                    </div>
                                    <span class="text-[11px] font-semibold text-slate-600 group-hover:text-blue-700">Klaim
                                        Tagihan</span>
                                </a>

                                {{-- Hutang Dagang --}}
                                <a href="{{ url('orders-payment/') }}"
                                    class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:border-orange-200 hover:shadow-md hover:shadow-orange-500/5 cursor-pointer">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-100 text-orange-600 transition-transform duration-200 group-hover:scale-110">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path
                                                d="M6 4h11a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-11a1 1 0 0 1 -1 -1v-14a1 1 0 0 1 1 -1m3 0v18" />
                                            <path d="M13 8l2 0" />
                                            <path d="M13 12l2 0" />
                                        </svg>
                                    </div>
                                    <span
                                        class="text-[11px] font-semibold text-slate-600 group-hover:text-orange-700">Hutang
                                        Dagang</span>
                                </a>

                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 {{ isOnlineRole() ? 'max-w-2xl' : 'lg:grid-cols-3' }}">

                {{-- Total Penjualan --}}
                <div
                    class="group relative flex items-center gap-4 overflow-hidden rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-500 to-blue-600 p-5 shadow-lg shadow-blue-500/20 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-blue-500/30">
                    <div
                        class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/20 text-white backdrop-blur">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 20l16 0" />
                            <path d="M4 15l4 -6l4 2l4 -5l4 4" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wide text-blue-100">{{ isOnlineRole() ? 'Penjualan ' . getOnlineChannelName() : 'Total Penjualan' }}</p>
                        <h4 class="text-xl font-bold text-white truncate">{{ $total_sales_rp }}</h4>
                    </div>
                </div>

                @if (isOnlineRole())
                    {{-- Total Transaksi Selesai --}}
                    <div
                        class="group relative flex items-center gap-4 overflow-hidden rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-500 to-emerald-600 p-5 shadow-lg shadow-emerald-500/20 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-emerald-500/30">
                        <div
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/20 text-white backdrop-blur">
                            <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2" />
                                <path d="M9 7l6 0" />
                                <path d="M9 11l6 0" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium uppercase tracking-wide text-emerald-100">Total Transaksi</p>
                            <h4 class="text-xl font-bold text-white truncate">{{ number_format($qty_sales ?? 0, 0, ',', '.') }} Struk</h4>
                        </div>
                    </div>
                @else
                    {{-- Total Pembelian --}}
                    <div
                        class="group relative flex items-center gap-4 overflow-hidden rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-500 to-emerald-600 p-5 shadow-lg shadow-emerald-500/20 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-emerald-500/30">
                        <div
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/20 text-white backdrop-blur">
                            <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M17 17h-11v-14h-2" />
                                <path d="M6 5l14 1l-1 7h-13" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium uppercase tracking-wide text-emerald-100">Total Pembelian</p>
                            <h4 class="text-xl font-bold text-white truncate">{{ $total_orders_rp }}</h4>
                        </div>
                    </div>

                    {{-- Total Ditolak --}}
                    <div
                        class="group relative flex items-center gap-4 overflow-hidden rounded-2xl border border-rose-100 bg-gradient-to-br from-rose-500 to-rose-600 p-5 shadow-lg shadow-rose-500/20 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-rose-500/30">
                        <div
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/20 text-white backdrop-blur">
                            <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                <path d="M10 10l4 4m0 -4l-4 4" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium uppercase tracking-wide text-rose-100">Total Ditolak</p>
                            <h4 class="text-xl font-bold text-white truncate">{{ $total_reject_rp }}</h4>
                        </div>
                    </div>
                @endif

            </div>
            <div class="mt-3 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-50 text-amber-600">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M12 8v4l2 2" />
                                <path d="M3.05 11a9 9 0 1 1 .5 4m-.5-5v-5h5" />
                            </svg>
                        </span>
                        <h3 class="text-sm font-bold text-slate-800">Barang Mendekati Kadaluarsa</h3>
                    </div>
                    <a href="{{ route('kasir.nearExpiry') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 transition-colors">
                        Lihat detail
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M5 12l14 0" />
                            <path d="M13 6l6 6l-6 6" />
                        </svg>
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wider text-slate-500 border-b border-slate-100">
                                <th class="px-5 py-3 font-semibold">Obat</th>
                                <th class="px-5 py-3 font-semibold">Batch</th>
                                <th class="px-5 py-3 font-semibold">Expired Date</th>
                                <th class="px-5 py-3 font-semibold">Sisa Hari</th>
                                <th class="px-5 py-3 font-semibold text-right">Stok</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse ($nearExpiry as $item)
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <td class="px-5 py-3">
                                        <div class="font-semibold text-slate-700">{{ $item->medicines->name ?? '-' }}
                                        </div>
                                        <div class="text-[11px] text-slate-400">{{ $item->medicines->code ?? '-' }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600">{{ $item->name }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $item->expiry_formatted }}</td>
                                    <td class="px-5 py-3">
                                        @if ($item->expiry_status === 'expired')
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-600">Expired
                                                ({{ abs($item->days_left) }} hari lalu)
                                            </span>
                                        @elseif ($item->expiry_status === 'near')
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-600">{{ $item->days_left }}
                                                hari lagi</span>
                                        @else
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-600">{{ $item->days_left }}
                                                hari lagi</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right font-semibold text-slate-700">{{ $item->stock }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-400">Tidak ada
                                        barang yang mendekati kadaluarsa.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
@endsection
