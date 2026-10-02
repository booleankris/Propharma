@extends('layouts.app')

@section('title', 'Executive Monitoring & Analytics HO')

@section('style')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* Premium Glassmorphism & Depth System */
        .executive-surface {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.85) 100%);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04), 0 2px 6px -1px rgba(15, 23, 42, 0.02);
        }

        .kpi-deck-card {
            position: relative;
            background: #ffffff;
            border-radius: 1.5rem;
            border: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow: 0 8px 24px -8px rgba(15, 23, 42, 0.06), 0 3px 6px -2px rgba(15, 23, 42, 0.02);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
        }

        .kpi-deck-card:hover {
            transform: translateY(-5px) scale(1.008);
            box-shadow: 0 18px 36px -10px rgba(15, 23, 42, 0.12), 0 6px 14px -4px rgba(15, 23, 42, 0.04);
        }

        /* Ambient Background Meshes */
        .mesh-emerald {
            background: radial-gradient(circle at 85% 15%, rgba(16, 185, 129, 0.14) 0%, transparent 60%),
                linear-gradient(180deg, #ffffff 0%, #fafffc 100%);
        }

        .mesh-blue {
            background: radial-gradient(circle at 85% 15%, rgba(59, 130, 246, 0.14) 0%, transparent 60%),
                linear-gradient(180deg, #ffffff 0%, #f8faff 100%);
        }

        .mesh-rose {
            background: radial-gradient(circle at 85% 15%, rgba(244, 63, 94, 0.14) 0%, transparent 60%),
                linear-gradient(180deg, #ffffff 0%, #fffbfb 100%);
        }

        .mesh-amber {
            background: radial-gradient(circle at 85% 15%, rgba(245, 158, 11, 0.14) 0%, transparent 60%),
                linear-gradient(180deg, #ffffff 0%, #fffdfa 100%);
        }

        /* 3D Floating Glowing Icon Pods */
        .icon-pod-emerald {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 8px 18px -3px rgba(16, 185, 129, 0.4), inset 0 2px 4px rgba(255, 255, 255, 0.35);
        }

        .icon-pod-blue {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            box-shadow: 0 8px 18px -3px rgba(59, 130, 246, 0.4), inset 0 2px 4px rgba(255, 255, 255, 0.35);
        }

        .icon-pod-rose {
            background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%);
            box-shadow: 0 8px 18px -3px rgba(244, 63, 94, 0.4), inset 0 2px 4px rgba(255, 255, 255, 0.35);
        }

        .icon-pod-amber {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            box-shadow: 0 8px 18px -3px rgba(245, 158, 11, 0.4), inset 0 2px 4px rgba(255, 255, 255, 0.35);
        }

        /* Shimmering Top Accent Beam */
        .beam-top {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3.5px;
            opacity: 0.9;
        }

        /* Period Filter Pills with Glow */
        .period-pill {
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .period-pill.active {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: #ffffff;
            box-shadow: 0 4px 14px -2px rgba(79, 70, 229, 0.4);
            transform: scale(1.02);
        }

        /* Branch Outlet Card Styling */
        .branch-card {
            transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            position: relative;
        }

        .branch-card:hover {
            transform: translateX(4px);
            background: #ffffff;
            box-shadow: 0 10px 24px -6px rgba(15, 23, 42, 0.08);
        }

        .branch-card.active {
            background: linear-gradient(135deg, #f8faff 0%, #eef2ff 100%);
            border: 2px solid #6366f1 !important;
            box-shadow: 0 12px 28px -6px rgba(99, 102, 241, 0.25);
            transform: translateX(4px);
        }

        /* Entry Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-up {
            animation: fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes pulseAura {

            0%,
            100% {
                opacity: 0.6;
                transform: scale(1);
            }

            50% {
                opacity: 1;
                transform: scale(1.08);
            }
        }

        .animate-aura {
            animation: pulseAura 4s ease-in-out infinite;
        }

        /* Smooth Custom Scrollbar for Branch Cards */
        #branch_cards_container::-webkit-scrollbar {
            width: 4px;
        }

        #branch_cards_container::-webkit-scrollbar-track {
            background: transparent;
        }

        #branch_cards_container::-webkit-scrollbar-thumb {
            background: rgba(203, 213, 225, 0.8);
            border-radius: 9999px;
        }

        #branch_cards_container::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 163, 184, 1);
        }
    </style>
@endsection

@section('content')
    <div class="min-h-screen bg-slate-50/70 p-4 sm:p-6 lg:p-7 space-y-6 animate-fade-up">

        <!-- Top Header Bar with Instant Filters -->
        <div class="executive-surface rounded-3xl p-5 sm:p-6 relative overflow-hidden">
            <div
                class="absolute -right-16 -top-16 w-56 h-56 bg-indigo-200/30 rounded-full blur-3xl pointer-events-none animate-aura">
            </div>
            <div
                class="absolute -left-16 -bottom-16 w-56 h-56 bg-blue-200/30 rounded-full blur-3xl pointer-events-none animate-aura">
            </div>

            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">

                <!-- Title & Badge -->
                <div>

                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-tight">
                        Executive Analytics & Monitoring
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 font-medium mt-0.5">
                        Monitoring visual omset penjualan, belanja pengadaan supplier, dan pergerakan retur
                    </p>
                </div>

                <!-- Instant Time Filters -->
                <div class="flex items-center gap-3">
                    <div
                        class="inline-flex p-1.5 bg-slate-200/60 backdrop-blur-md rounded-2xl border border-slate-300/60 shadow-inner">
                        <button type="button" onclick="setPeriod('today')" id="btn_period_today"
                            class="period-pill px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 transition-all">
                            Hari Ini
                        </button>
                        <button type="button" onclick="setPeriod('this_month')" id="btn_period_this_month"
                            class="period-pill active px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 transition-all">
                            Bulan Ini
                        </button>
                        <button type="button" onclick="setPeriod('this_year')" id="btn_period_this_year"
                            class="period-pill px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 transition-all">
                            Tahun Ini
                        </button>
                        <button type="button" id="btn_period_custom"
                            class="period-pill relative inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 transition-all cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-calendar pointer-events-none">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12z" />
                                <path d="M16 3v4" />
                                <path d="M8 3v4" />
                                <path d="M4 11h16" />
                            </svg>
                            <span id="custom_period_label" class="pointer-events-none">Kustom</span>
                            <input type="text" id="custom_date_range_picker"
                                class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10" readonly>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Period Active Indicator -->
            <div class="relative z-10 mt-4 pt-3.5 border-t border-slate-200/70 flex items-center justify-between text-xs">
                <div class="flex items-center gap-2 text-slate-600">
                    <span class="text-slate-400 font-medium">Periode Aktif:</span>
                    <span id="active_period_text"
                        class="font-extrabold text-indigo-700 bg-white/90 px-3 py-0.5 rounded-xl border border-indigo-100 shadow-2xs">
                        {{ $analytics['period_label'] }}
                    </span>
                    <span class="text-slate-400 font-medium ml-2">Outlet:</span>
                    <span id="active_outlet_badge"
                        class="font-extrabold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-xl border border-emerald-200">
                        Konsolidasi Seluruh Cabang
                    </span>
                </div>
            </div>
        </div>

        <!-- MAIN TWO-COLUMN DASHBOARD GRID -->
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
            <div class="xl:col-span-4 2xl:col-span-3 space-y-4 sticky top-6 self-start">

                <div
                    class="executive-surface rounded-3xl p-5 shadow-xs flex flex-col justify-between max-h-[calc(100vh-3rem)]">

                    <!-- Panel Header -->
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-1.5">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                                <h3 class="text-base font-black text-slate-900 tracking-tight">Unit & Cabang</h3>
                            </div>
                            <span
                                class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-slate-200/80 text-slate-700">
                                {{ count($analytics['branch_list']) + 1 }} Outlet
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 font-medium">Pilih cabang untuk memantau analitik unit secara
                            spesifik</p>

                        <!-- Search Input -->
                        <div class="relative mt-3">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                class="absolute left-3 top-2.5 w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            <input type="text" id="search_branch_input" onkeyup="filterBranchList()"
                                placeholder="Cari cabang apotek..."
                                class="w-full bg-white border border-slate-200 rounded-xl text-xs py-2 pl-8 pr-3 focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 font-semibold text-slate-700 placeholder-slate-400">
                        </div>
                    </div>

                    <!-- Vertical Cards Container -->
                    <div id="branch_cards_container" class="space-y-2.5 max-h-[calc(100vh-280px)] overflow-y-auto pr-1">

                        <!-- MASTER CARD: SEMUA CABANG (KONSOLIDASI) -->
                        <div onclick="selectBranch('all', this, 'Konsolidasi Seluruh Cabang')" id="branch_card_all"
                            class="branch-card active p-3.5 rounded-2xl border border-slate-200/80 bg-white flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-black text-sm shadow-xs shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            class="icon icon-tabler icon-tabler-building" width="20" height="20"
                                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M3 21l18 0" />
                                            <path d="M9 8l1 0" />
                                            <path d="M9 12l1 0" />
                                            <path d="M9 16l1 0" />
                                            <path d="M14 8l1 0" />
                                            <path d="M14 12l1 0" />
                                            <path d="M14 16l1 0" />
                                            <path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-black text-slate-900 leading-tight">Semua Cabang (HO)</h4>
                                        <span class="text-[10px] text-indigo-600 font-bold block">Konsolidasi Total
                                            Group</span>
                                    </div>
                                </div>
                                <span class="active-check text-indigo-600 text-sm font-black">✓</span>
                            </div>
                            <div
                                class="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                <span class="text-slate-400 font-medium">Total Omset Group</span>
                                <strong id="branch_card_total_omset"
                                    class="text-slate-900 font-extrabold">{{ $analytics['total_sales_rp'] }}</strong>
                            </div>
                        </div>

                        <!-- PER-BRANCH CARDS -->
                        @foreach ($analytics['branch_list'] as $b)
                            <div onclick="selectBranch('{{ $b['id'] }}', this, '📍 {{ addslashes($b['name']) }}')"
                                data-branch-name="{{ strtolower($b['name']) }}" id="branch_card_{{ $b['id'] }}"
                                class="branch-card p-3.5 rounded-2xl border border-slate-200/80 bg-white flex flex-col justify-between">

                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2.5">
                                        <div
                                            class="w-9 h-9 rounded-xl {{ $b['is_warehouse'] ? 'bg-violet-100 text-violet-700' : 'bg-emerald-100 text-emerald-700' }} flex items-center justify-center font-black text-sm shrink-0">
                                            {!! $b['is_warehouse']
                                                ? '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-box" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" /><path d="M12 12l8 -4.5" /><path d="M12 12l0 9" /><path d="M12 12l-8 -4.5" /><path d="M16 5.25l-8 4.5" /></svg>'
                                                : '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-building-hospital" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l18 0" /><path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16" /><path d="M9 21v-4a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v4" /><path d="M10 9l4 0" /><path d="M12 7l0 4" /></svg>' !!}
                                        </div>
                                        <div class="overflow-hidden">
                                            <h4 class="text-xs font-black text-slate-900 truncate leading-tight">
                                                {{ $b['name'] }}</h4>
                                            <span
                                                class="text-[10px] text-slate-400 font-medium block">{{ $b['city'] }}</span>
                                        </div>
                                    </div>
                                    <span class="active-check hidden text-indigo-600 text-sm font-black">✓</span>
                                </div>

                                <!-- Branch Metrics -->
                                <div class="mt-2.5 pt-2 border-t border-slate-100">
                                    <div class="flex items-center justify-between text-[11px] mb-1">
                                        <span class="text-slate-500 font-medium">Omset Unit:</span>
                                        <strong
                                            class="text-slate-900 font-extrabold branch-sales-label">{{ $b['sales_rp'] }}</strong>
                                    </div>
                                    <!-- Mini Progress Bar (% of total omset) -->
                                    <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                        <div class="bg-indigo-500 h-full rounded-full branch-share-bar"
                                            style="width: {{ $b['share'] }}%"></div>
                                    </div>
                                    <div
                                        class="flex items-center justify-between text-[9px] text-slate-400 mt-1 font-medium">
                                        <span><strong
                                                class="text-slate-700 font-bold branch-txns-label">{{ number_format($b['txns'], 0, ',', '.') }}</strong>
                                            transaksi</span>
                                        <span>Kontribusi: <strong
                                                class="text-indigo-600 font-bold branch-share-label">{{ $b['share'] }}%</strong></span>
                                    </div>
                                </div>

                            </div>
                        @endforeach

                    </div>

                </div>

            </div>
            <!-- LEFT / MAIN ANALYTICS SECTION (9 COLS) -->
            <div class="xl:col-span-8 2xl:col-span-9 space-y-6">
                <div id="stock_info_section"
                    class="hidden bg-white border border-slate-200/80 rounded-3xl p-5 sm:p-6 text-slate-900 shadow-sm relative overflow-hidden mb-6">
                    <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                        <div>
                            <span
                                class="text-[9px] font-black uppercase tracking-widest text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
                                Informasi Stok
                            </span>
                            <h3 class="text-base sm:text-lg font-black text-slate-900 mt-1">Estimasi Nilai & Kuantitas Stok Obat</h3>
                        </div>
                        <a id="export_stock_btn" href="{{ route('ho.analytics.export-stock') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl transition-all shadow-sm" onclick="showExportLoading(this)">
                            <span id="export_stock_icon" class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-file-spreadsheet" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path d="M14 3v4a1 1 0 0 0 1 1h4"></path>
                                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"></path>
                                    <path d="M8 11h8v7h-8z"></path>
                                    <path d="M8 15h8"></path>
                                    <path d="M11 11v7"></path>
                                </svg>
                            </span>
                            <span id="export_stock_text">Export Excel</span>
                        </a>
                    </div>
                    <div class="relative z-10 space-y-5">
                        <!-- Etalase -->
                        <div>
                            <h4 class="text-[10px] font-black text-slate-500 uppercase tracking-widest flex items-center gap-2 mb-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                Stok Etalase (Cabang)
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-center">
                                    <span class="text-[9px] text-slate-500 font-bold uppercase tracking-wide mb-1">Harga Stok</span>
                                    <span id="stock_etalase_value" class="text-sm font-black text-slate-900">-</span>
                                </div>
                                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-center">
                                    <span class="text-[9px] text-slate-500 font-bold uppercase tracking-wide mb-1">Total Stok Barang</span>
                                    <span id="stock_etalase_qty" class="text-sm font-black text-slate-900">-</span>
                                </div>
                                <div class="p-3.5 rounded-2xl bg-orange-50/50 border border-orange-100/50 flex flex-col justify-center">
                                    <span class="text-[9px] text-orange-600/80 font-bold uppercase tracking-wide mb-1">Stok Dekat ED (≤ 6 Bulan)</span>
                                    <span id="stock_etalase_near_ed" class="text-sm font-black text-orange-700">-</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Gudang (PMI ONLY) -->
                        <div id="stock_gudang_card" class="hidden">
                            <h4 class="text-[10px] font-black text-slate-500 uppercase tracking-widest flex items-center gap-2 mb-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                                Stok Gudang (PMI)
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-center">
                                    <span class="text-[9px] text-slate-500 font-bold uppercase tracking-wide mb-1">Harga Stok</span>
                                    <span id="stock_gudang_value" class="text-sm font-black text-slate-900">-</span>
                                </div>
                                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-center">
                                    <span class="text-[9px] text-slate-500 font-bold uppercase tracking-wide mb-1">Total Stok Barang</span>
                                    <span id="stock_gudang_qty" class="text-sm font-black text-slate-900">-</span>
                                </div>
                                <div class="p-3.5 rounded-2xl bg-orange-50/50 border border-orange-100/50 flex flex-col justify-center">
                                    <span class="text-[9px] text-orange-600/80 font-bold uppercase tracking-wide mb-1">Stok Dekat ED (≤ 6 Bulan)</span>
                                    <span id="stock_gudang_near_ed" class="text-sm font-black text-orange-700">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- 4 HERO KPI METRIC CARDS -->
                <div class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-4 gap-4 sm:gap-5">
                    <!-- CARD 1: TOTAL PENJUALAN (SALES) -->
                    <div class="kpi-deck-card mesh-emerald p-5 flex flex-col justify-between">
                        <div class="beam-top bg-gradient-to-r from-emerald-400 via-teal-400 to-emerald-500"></div>
                        <div>
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <span
                                        class="text-[10px] font-extrabold text-emerald-800 uppercase tracking-wider bg-emerald-100/70 px-2.5 py-0.5 rounded-lg">
                                        Penjualan (Omset)
                                    </span>
                                    <p class="text-[10px] text-slate-400 font-medium mt-1">Total Pendapatan Kasir</p>
                                </div>
                                <div
                                    class="w-11 h-11 rounded-2xl icon-pod-emerald flex items-center justify-center text-white shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-cash">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path
                                            d="M7 9m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z" />
                                        <path d="M14 14m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                        <path d="M17 9v-2a2 2 0 0 0 -2 -2h-10a2 2 0 0 0 -2 2v6a2 2 0 0 0 2 2h2" />
                                    </svg>
                                </div>
                            </div>

                            <div class="my-1.5">
                                <h3 id="metric_sales_rp"
                                    class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight leading-none">
                                    {{ $analytics['total_sales_rp'] }}
                                </h3>
                            </div>
                        </div>

                        <div
                            class="pt-3 border-t border-emerald-100/80 flex items-center justify-between text-xs text-slate-600">
                            <span class="inline-flex items-center gap-1 font-bold">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                <strong id="metric_sales_count"
                                    class="text-slate-900">{{ number_format($analytics['total_sales_count'], 0, ',', '.') }}</strong>
                                Struk
                            </span>
                            <span class="text-[10px] text-slate-400 font-medium">
                                AOV: <strong id="metric_sales_aov"
                                    class="text-emerald-700 font-bold">{{ $analytics['average_order_value'] }}</strong>
                            </span>
                        </div>
                    </div>

                    <!-- CARD 2: TOTAL PEMBELIAN (PURCHASES) -->
                    <div class="kpi-deck-card mesh-blue p-5 flex flex-col justify-between">
                        <div class="beam-top bg-gradient-to-r from-blue-400 via-indigo-400 to-blue-600"></div>
                        <div>
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <span
                                        class="text-[10px] font-extrabold text-blue-800 uppercase tracking-wider bg-blue-100/70 px-2.5 py-0.5 rounded-lg">
                                        Pembelian Supplier
                                    </span>
                                    <p class="text-[10px] text-slate-400 font-medium mt-1">Total Pengadaan Obat</p>
                                </div>
                                <div
                                    class="w-11 h-11 rounded-2xl icon-pod-blue flex items-center justify-center text-white shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-truck-delivery">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                        <path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                        <path d="M5 17h-2v-4m-1 -8h11v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5" />
                                        <path d="M3 9l4 0" />
                                    </svg>
                                </div>
                            </div>

                            <div class="my-1.5">
                                <h3 id="metric_purchases_rp"
                                    class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight leading-none">
                                    {{ $analytics['total_purchases_rp'] }}
                                </h3>
                            </div>
                        </div>

                        <div
                            class="pt-3 border-t border-blue-100/80 flex items-center justify-between text-xs text-slate-600">
                            <span class="inline-flex items-center gap-1 font-bold">
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                <strong id="metric_purchases_count"
                                    class="text-slate-900">{{ number_format($analytics['total_purchases_count'], 0, ',', '.') }}</strong>
                                SP
                            </span>
                            <span
                                class="text-[10px] font-extrabold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-md border border-blue-100">
                                Penerimaan Selesai
                            </span>
                        </div>
                    </div>

                    <!-- CARD 3: RETUR PENJUALAN (CUSTOMER RETURN) -->
                    <div class="kpi-deck-card mesh-rose p-5 flex flex-col justify-between">
                        <div class="beam-top bg-gradient-to-r from-rose-400 via-pink-400 to-rose-600"></div>
                        <div>
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <span
                                        class="text-[10px] font-extrabold text-rose-800 uppercase tracking-wider bg-rose-100/70 px-2.5 py-0.5 rounded-lg">
                                        Retur Penjualan
                                    </span>
                                    <p class="text-[10px] text-slate-400 font-medium mt-1">Pengembalian Pasien</p>
                                </div>
                                <div
                                    class="w-11 h-11 rounded-2xl icon-pod-rose flex items-center justify-center text-white shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-back-up">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M9 14l-4 -4l4 -4" />
                                        <path d="M5 10h11a4 4 0 1 1 0 8h-1" />
                                    </svg>
                                </div>
                            </div>

                            <div class="my-1.5">
                                <h3 id="metric_sales_retur_rp"
                                    class="text-xl sm:text-2xl font-black text-rose-600 tracking-tight leading-none">
                                    {{ $analytics['total_sales_retur_rp'] }}
                                </h3>
                            </div>
                        </div>

                        <div
                            class="pt-3 border-t border-rose-100/80 flex items-center justify-between text-xs text-slate-600">
                            <span class="inline-flex items-center gap-1 font-bold">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                <strong id="metric_sales_retur_count"
                                    class="text-slate-900">{{ number_format($analytics['total_sales_retur_count'], 0, ',', '.') }}</strong>
                                Retur
                            </span>
                            <span
                                class="text-[10px] font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-md border border-rose-100">
                                Rasio: <strong id="metric_sales_retur_rate">{{ $analytics['sales_retur_rate'] }}</strong>
                            </span>
                        </div>
                    </div>

                    <!-- CARD 4: RETUR PEMBELIAN (SUPPLIER RETURN) -->
                    <div class="kpi-deck-card mesh-amber p-5 flex flex-col justify-between">
                        <div class="beam-top bg-gradient-to-r from-amber-400 via-orange-400 to-amber-600"></div>
                        <div>
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <span
                                        class="text-[10px] font-extrabold text-amber-800 uppercase tracking-wider bg-amber-100/70 px-2.5 py-0.5 rounded-lg">
                                        Retur Pembelian
                                    </span>
                                    <p class="text-[10px] text-slate-400 font-medium mt-1">Pengembalian ke PBF</p>
                                </div>
                                <div
                                    class="w-11 h-11 rounded-2xl icon-pod-amber flex items-center justify-center text-white shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-truck-return">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                        <path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                        <path d="M5 17h-2v-11a1 1 0 0 1 1 -1h9v12m-4 0h6m4 0h2v-6h-4" />
                                        <path d="M9 10.5l3 -3l3 3" />
                                        <path d="M12 7.5v6" />
                                    </svg>
                                </div>
                            </div>

                            <div class="my-1.5">
                                <h3 id="metric_purchase_retur_rp"
                                    class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight leading-none">
                                    {{ $analytics['total_purchase_retur_rp'] }}
                                </h3>
                            </div>
                        </div>

                        <div
                            class="pt-3 border-t border-amber-100/80 flex items-center justify-between text-xs text-slate-600">
                            <span class="inline-flex items-center gap-1 font-bold">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                <strong id="metric_purchase_retur_count"
                                    class="text-slate-900">{{ number_format($analytics['total_purchase_retur_count'], 0, ',', '.') }}</strong>
                                Log
                            </span>
                            <span
                                class="text-[10px] font-extrabold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-100">
                                Klaim PBF
                            </span>
                        </div>
                    </div>

                </div>

                <!-- GRAPHS SECTION (MONTHLY SPLINE & TRANSACTION TYPE DONUT) -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                    <!-- LEFT CHART (7 COLS): TREN BULANAN -->
                    <div
                        class="lg:col-span-7 executive-surface rounded-3xl p-5 sm:p-6 shadow-xs flex flex-col justify-between relative overflow-hidden">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                            <div>
                                <h3 class="text-base font-black text-slate-900 tracking-tight">
                                    Tren Bulanan (Tahun <span id="chart_year_label"
                                        class="text-indigo-600 font-extrabold">{{ $analytics['target_year'] }}</span>)
                                </h3>
                                <p class="text-[11px] text-slate-400 font-medium">Perbandingan Penjualan vs Pembelian vs
                                    Retur (12 Bulan)</p>
                            </div>
                            <div
                                class="flex items-center gap-2.5 text-[11px] font-bold bg-slate-100/90 px-3 py-1 rounded-xl border border-slate-200/70">
                                <span class="inline-flex items-center gap-1 text-emerald-700">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Jual
                                </span>
                                <span class="inline-flex items-center gap-1 text-blue-700">
                                    <span class="w-2 h-2 rounded-full bg-blue-600"></span> Beli
                                </span>
                                <span class="inline-flex items-center gap-1 text-rose-600">
                                    <span class="w-2 h-2 rounded-full bg-rose-500"></span> Retur
                                </span>
                            </div>
                        </div>

                        <!-- ApexChart Container -->
                        <div class="w-full">
                            <div id="monthlyTrendChart" class="w-full min-h-[300px]"></div>
                        </div>
                    </div>

                    <!-- RIGHT CHART (5 COLS): DISTRIBUSI JENIS TRANSAKSI -->
                    <div
                        class="lg:col-span-5 executive-surface rounded-3xl p-5 sm:p-6 shadow-xs flex flex-col justify-between relative overflow-hidden">
                        <div>
                            <div class="mb-3">
                                <h3 class="text-base font-black text-slate-900 tracking-tight">Distribusi Transaksi</h3>
                                <p class="text-[11px] text-slate-400 font-medium">Proporsi omset: HV, UPDS, Resep Kredit,
                                    Resep Tunai</p>
                            </div>

                            <!-- Donut Chart -->
                            <div class="flex justify-center items-center my-1">
                                <div id="transactionTypeChart" class="w-full max-w-[240px] min-h-[190px]"></div>
                            </div>

                            <!-- Detailed Breakdown List -->
                            <div id="transaction_breakdown_list" class="space-y-2 mt-3 pt-3 border-t border-slate-200/80">
                                @foreach ($analytics['type_breakdown'] as $item)
                                    <div
                                        class="flex items-center justify-between text-xs bg-slate-50/80 hover:bg-slate-100/80 p-2 rounded-xl transition-all border border-slate-100">
                                        <div class="flex items-center gap-2">
                                            <span class="w-3 h-3 rounded-md shadow-2xs shrink-0"
                                                style="background-color: {{ $item['color'] }}"></span>
                                            <div>
                                                <span
                                                    class="font-extrabold text-slate-800 block leading-tight">{{ $item['label'] }}</span>
                                                <span
                                                    class="text-[10px] text-slate-400 font-medium">{{ number_format($item['count'], 0, ',', '.') }}
                                                    transaksi</span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span
                                                class="font-black text-slate-900 block leading-tight">{{ $item['amount_rp'] }}</span>
                                            <span
                                                class="text-[10px] font-extrabold text-indigo-600 bg-indigo-50 px-1 py-0.2 rounded">{{ $item['percentage'] }}%</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                </div>

                <!-- STOCK INFO SECTION (PER CABANG) -->
                <div
                    class="bg-white border border-slate-200/80 rounded-3xl p-5 sm:p-6 text-slate-900 shadow-sm relative overflow-hidden">
                    <div
                        class="absolute -right-10 -bottom-10 w-48 h-48 bg-indigo-500/20 rounded-full blur-2xl pointer-events-none">
                    </div>
                    <div
                        class="absolute -left-10 -top-10 w-48 h-48 bg-emerald-500/15 rounded-full blur-2xl pointer-events-none">
                    </div>

                    <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                        <div>
                            <span
                                class="text-[9px] font-black uppercase tracking-widest text-indigo-700 bg-indigo-50 px-2.5 py-0.5 rounded-full border border-indigo-200">
                                Executive Summary
                            </span>
                            <h3 class="text-base sm:text-lg font-black text-slate-900 mt-1">Ringkasan Omset Bersih &
                                Vitalitas
                                Bisnis</h3>
                        </div>
                        <div
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs">
                            <span class="text-slate-500 font-medium">Net Revenue:</span>
                            <span id="metric_net_revenue_rp" class="font-black text-emerald-600 text-base tracking-tight">
                                {{ $analytics['net_revenue_rp'] }}
                            </span>
                        </div>
                    </div>

                    <!-- 3 Highlight Cockpit Cards -->
                    <div class="relative z-10 grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-xl bg-emerald-100 border-emerald-200 text-emerald-600 flex items-center justify-center font-black text-lg shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-coin"
                                    width="20" height="20" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                    <path
                                        d="M14.8 9a2 2 0 0 0 -1.8 -1h-2a2 2 0 1 0 0 4h2a2 2 0 1 1 0 4h-2a2 2 0 0 1 -1.8 -1" />
                                    <path d="M12 7v10" />
                                </svg>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Omset Bersih
                                    Riil</span>
                                <h4 id="summary_net_revenue"
                                    class="text-sm sm:text-base font-black text-slate-900 leading-tight mt-0.5">
                                    {{ $analytics['net_revenue_rp'] }}
                                </h4>
                                <span class="text-[9px] text-slate-400 font-medium">Penjualan Bruto – Retur Jual</span>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-xl bg-indigo-100 border-indigo-200 text-indigo-600 flex items-center justify-center font-black text-lg shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-tag"
                                    width="20" height="20" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M7.5 7.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                                    <path
                                        d="M3 6v5.172a2 2 0 0 0 .586 1.414l7.71 7.71a2.41 2.41 0 0 0 3.408 0l5.592 -5.592a2.41 2.41 0 0 0 0 -3.408l-7.71 -7.71a2 2 0 0 0 -1.414 -.586h-5.172a3 3 0 0 0 -3 3z" />
                                </svg>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Total Potongan
                                    Diskon</span>
                                <h4 id="summary_discount"
                                    class="text-sm sm:text-base font-black text-slate-900 leading-tight mt-0.5">
                                    {{ $analytics['total_discount'] }}
                                </h4>
                                <span class="text-[9px] text-slate-400 font-medium">Diskon kasir diberikan</span>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-xl bg-purple-100 border-purple-200 text-purple-600 flex items-center justify-center font-black text-lg shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-chart-bar"
                                    width="20" height="20" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M3 13a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                    <path d="M15 9a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                    <path d="M9 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                    <path d="M4 20h14" />
                                </svg>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Rata-Rata Struk
                                    (AOV)</span>
                                <h4 id="summary_aov"
                                    class="text-sm sm:text-base font-black text-slate-900 leading-tight mt-0.5">
                                    {{ $analytics['average_order_value'] }}
                                </h4>
                                <span class="text-[9px] text-slate-400 font-medium">Nilai rata-rata belanja</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABLE: REAL-TIME STOCK BREAKDOWN -->
                <div class="bg-white border border-slate-200/80 rounded-3xl p-5 sm:p-6 text-slate-900 shadow-sm relative overflow-hidden mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
                        <div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-indigo-700 bg-indigo-50 px-2.5 py-0.5 rounded-full border border-indigo-200">
                                Rincian Obat
                            </span>
                            <h3 class="text-base sm:text-lg font-black text-slate-900 mt-1">Stok Per Cabang (Real-time)</h3>
                        </div>
                        <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto">
                            <select id="filter_stock_table" class="w-full sm:w-40 pl-3 pr-8 py-2 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all appearance-none bg-white">
                                <option value="all">Semua Status</option>
                                <option value="available">Stok Tersedia</option>
                                <option value="empty">Stok Kosong</option>
                            </select>
                            <div class="relative w-full sm:w-64">
                                <input type="text" id="search_stock_table" placeholder="Cari obat..." class="w-full pl-10 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-search absolute left-3 top-2.5 text-slate-400" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"></path>
                                    <path d="M21 21l-6 -6"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto rounded-xl border border-slate-100">
                        <table class="w-full text-left border-collapse min-w-max">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-black uppercase tracking-widest text-slate-500">
                                    <th class="p-4 rounded-tl-xl">Nama Obat</th>
                                    <th class="p-4 text-center">Stok Gudang</th>
                                    <th class="p-4 text-center">Stok PMI</th>
                                    <th class="p-4 text-center">Stok ASM</th>
                                    <th class="p-4 text-center">Stok MIM</th>
                                    <th class="p-4 rounded-tr-xl text-center">Stok ASA</th>
                                </tr>
                            </thead>
                            <tbody id="stock_table_body" class="text-xs sm:text-sm text-slate-700 divide-y divide-slate-50">
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-400">
                                        <svg class="animate-spin h-6 w-6 text-indigo-500 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        Memuat data...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-[11px] text-slate-500 font-medium" id="stock_table_info">
                            Menampilkan - data
                        </div>
                        <div class="flex items-center gap-1" id="stock_table_pagination">
                            <!-- Pagination buttons will be rendered here -->
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        let currentPeriod = 'this_month';
        let currentPharmacyId = 'all';
        let currentStartDate = '{{ $analytics['start_date'] }}';
        let currentEndDate = '{{ $analytics['end_date'] }}';
        let monthlyChart = null;
        let typeChart = null;

        // Initial Data from Backend
        const initialData = @json($analytics);

        document.addEventListener('DOMContentLoaded', function() {
            initCharts(initialData);
            initFlatpickr();
        });

        function formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        }

        function initFlatpickr() {
            const pickerInput = document.getElementById('custom_date_range_picker');
            if (!pickerInput) return;

            flatpickr(pickerInput, {
                mode: "range",
                dateFormat: "Y-m-d",
                position: "auto right",
                defaultDate: [currentStartDate, currentEndDate],
                onClose: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        currentPeriod = 'custom';
                        currentStartDate = instance.formatDate(selectedDates[0], "Y-m-d");
                        currentEndDate = instance.formatDate(selectedDates[1], "Y-m-d");

                        document.querySelectorAll('.period-pill').forEach(b => b.classList.remove('active'));
                        document.getElementById('btn_period_custom').classList.add('active');
                        document.getElementById('custom_period_label').innerText = instance.formatDate(
                            selectedDates[0], "d/m") + ' - ' + instance.formatDate(selectedDates[1], "d/m");

                        fetchAnalyticsData();
                    }
                }
            });
        }

        function setPeriod(period) {
            currentPeriod = period;
            document.querySelectorAll('.period-pill').forEach(b => b.classList.remove('active'));
            const activeBtn = document.getElementById('btn_period_' + period);
            if (activeBtn) activeBtn.classList.add('active');
            document.getElementById('custom_period_label').innerText = 'Kustom';

            fetchAnalyticsData();
        }

        function selectBranch(pharmacyId, el, label) {
            currentPharmacyId = pharmacyId;

            // Update active class on cards
            document.querySelectorAll('.branch-card').forEach(c => {
                c.classList.remove('active');
                const chk = c.querySelector('.active-check');
                if (chk) chk.classList.add('hidden');
            });

            if (el) {
                el.classList.add('active');
                const chk = el.querySelector('.active-check');
                if (chk) chk.classList.remove('hidden');
            }

            // Update Active Outlet Badge
            const badge = document.getElementById('active_outlet_badge');
            if (badge) {
                badge.innerText = label;
                if (pharmacyId === 'all') {
                    badge.className =
                        'font-extrabold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-xl border border-emerald-200';
                } else {
                    badge.className =
                        'font-extrabold text-indigo-700 bg-indigo-50 px-2.5 py-0.5 rounded-xl border border-indigo-200';
                }
            }

            fetchAnalyticsData();
        }

        function filterBranchList() {
            const query = (document.getElementById('search_branch_input')?.value || '').toLowerCase().trim();
            const cards = document.querySelectorAll('#branch_cards_container .branch-card');

            cards.forEach(c => {
                if (c.id === 'branch_card_all') return; // Always show master card
                const branchName = c.getAttribute('data-branch-name') || '';
                if (branchName.includes(query) || query === '') {
                    c.style.display = 'flex';
                } else {
                    c.style.display = 'none';
                }
            });
        }

        async function fetchAnalyticsData() {
            const url = new URL("{{ route('ho.analytics.data') }}", window.location.origin);
            url.searchParams.append('period', currentPeriod);
            url.searchParams.append('pharmacy_id', currentPharmacyId);
            if (currentPeriod === 'custom') {
                url.searchParams.append('start_date', currentStartDate);
                url.searchParams.append('end_date', currentEndDate);
            }

            // Update Export Button URL
            const exportBtn = document.getElementById('export_stock_btn');
            if (exportBtn) {
                const exportUrl = new URL("{{ route('ho.analytics.export-stock') }}", window.location.origin);
                exportUrl.searchParams.append('period', currentPeriod);
                if (currentPeriod === 'custom') {
                    exportUrl.searchParams.append('start_date', currentStartDate);
                    exportUrl.searchParams.append('end_date', currentEndDate);
                }
                exportBtn.href = exportUrl.toString();
            }

            try {
                const res = await fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                if (!res.ok) throw new Error('Gagal memuat data analytics');
                const data = await res.json();

                updateDashboardUI(data);
            } catch (err) {
                console.error("Fetch analytics error:", err);
                if (window.iziToast) {
                    iziToast.error({
                        title: 'Gagal',
                        message: 'Gagal memperbarui data analytics.',
                        position: 'topRight'
                    });
                }
            }
        }

        function updateDashboardUI(data) {
            // Update Period Indicators
            document.getElementById('active_period_text').innerText = data.period_label;
            document.getElementById('chart_year_label').innerText = data.target_year;

            // Update 4 Hero Metric Cards
            document.getElementById('metric_sales_rp').innerText = data.total_sales_rp;
            document.getElementById('metric_sales_count').innerText = Number(data.total_sales_count).toLocaleString(
                'id-ID');
            document.getElementById('metric_sales_aov').innerText = data.average_order_value;

            document.getElementById('metric_purchases_rp').innerText = data.total_purchases_rp;
            document.getElementById('metric_purchases_count').innerText = Number(data.total_purchases_count).toLocaleString(
                'id-ID');

            document.getElementById('metric_sales_retur_rp').innerText = data.total_sales_retur_rp;
            document.getElementById('metric_sales_retur_count').innerText = Number(data.total_sales_retur_count)
                .toLocaleString('id-ID');
            document.getElementById('metric_sales_retur_rate').innerText = data.sales_retur_rate;

            document.getElementById('metric_purchase_retur_rp').innerText = data.total_purchase_retur_rp;
            document.getElementById('metric_purchase_retur_count').innerText = Number(data.total_purchase_retur_count)
                .toLocaleString('id-ID');

            // Update Summary Section
            document.getElementById('metric_net_revenue_rp').innerText = data.net_revenue_rp;
            document.getElementById('summary_net_revenue').innerText = data.net_revenue_rp;
            document.getElementById('summary_discount').innerText = data.total_discount;
            document.getElementById('summary_aov').innerText = data.average_order_value;

            // Update Stock Info Section
            const stockSection = document.getElementById('stock_info_section');
            if (data.pharmacy_id === 'all') {
                if (stockSection) stockSection.classList.add('hidden');
            } else {
                if (stockSection) stockSection.classList.remove('hidden');

                if (data.stock_info) {
                    document.getElementById('stock_etalase_qty').innerText = Number(data.stock_info.etalase_qty)
                        .toLocaleString('id-ID') + ' item';
                    document.getElementById('stock_etalase_value').innerText = data.stock_info.etalase_value_rp;
                    const elEtalaseNearEd = document.getElementById('stock_etalase_near_ed');
                    if (elEtalaseNearEd) elEtalaseNearEd.innerText = Number(data.stock_info.etalase_near_ed_qty || 0).toLocaleString('id-ID') + ' item';

                    const gudangCard = document.getElementById('stock_gudang_card');
                    if (data.pharmacy_id == 1 || data.pharmacy_id === '1') {
                        if (gudangCard) gudangCard.classList.remove('hidden');
                        const stockGudangQty = document.getElementById('stock_gudang_qty');
                        const stockGudangValue = document.getElementById('stock_gudang_value');
                        const stockGudangNearEd = document.getElementById('stock_gudang_near_ed');
                        if (stockGudangQty) stockGudangQty.innerText = Number(data.stock_info.gudang_qty).toLocaleString(
                            'id-ID') + ' item';
                        if (stockGudangValue) stockGudangValue.innerText = data.stock_info.gudang_value_rp;
                        if (stockGudangNearEd) stockGudangNearEd.innerText = Number(data.stock_info.gudang_near_ed_qty || 0).toLocaleString('id-ID') + ' item';
                    } else {
                        if (gudangCard) gudangCard.classList.add('hidden');
                    }
                }
            }

            // Update Master Branch Card Total
            const masterCardTotal = document.getElementById('branch_card_total_omset');
            if (masterCardTotal) masterCardTotal.innerText = data.total_sales_rp;

            // Update Breakdown List
            updateBreakdownList(data.type_breakdown);

            // Update Branch List Cards (sales & share labels)
            if (data.branch_list) {
                data.branch_list.forEach(b => {
                    const card = document.getElementById('branch_card_' + b.id);
                    if (card) {
                        const salesLabel = card.querySelector('.branch-sales-label');
                        const txnsLabel = card.querySelector('.branch-txns-label');
                        const shareLabel = card.querySelector('.branch-share-label');
                        const shareBar = card.querySelector('.branch-share-bar');

                        if (salesLabel) salesLabel.innerText = b.sales_rp;
                        if (txnsLabel) txnsLabel.innerText = Number(b.txns).toLocaleString('id-ID');
                        if (shareLabel) shareLabel.innerText = b.share + '%';
                        if (shareBar) shareBar.style.width = b.share + '%';
                    }
                });
            }

            // Update ApexCharts
            if (monthlyChart) {
                monthlyChart.updateSeries([{
                        name: 'Penjualan',
                        data: data.monthly_chart.sales
                    },
                    {
                        name: 'Pembelian',
                        data: data.monthly_chart.purchases
                    },
                    {
                        name: 'Retur Jual',
                        data: data.monthly_chart.returns
                    }
                ]);
            }

            if (typeChart) {
                typeChart.updateOptions({
                    labels: data.pie_chart.labels,
                    colors: data.pie_chart.colors
                });
                typeChart.updateSeries(data.pie_chart.series);
            }
        }

        function updateBreakdownList(breakdown) {
            const container = document.getElementById('transaction_breakdown_list');
            if (!container) return;

            let html = '';
            Object.values(breakdown).forEach(item => {
                html += `
                <div class="flex items-center justify-between text-xs bg-slate-50/80 hover:bg-slate-100/80 p-2 rounded-xl transition-all border border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-md shadow-2xs shrink-0" style="background-color: ${item.color}"></span>
                        <div>
                            <span class="font-extrabold text-slate-800 block leading-tight">${item.label}</span>
                            <span class="text-[10px] text-slate-400 font-medium">${Number(item.count).toLocaleString('id-ID')} transaksi</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="font-black text-slate-900 block leading-tight">${item.amount_rp}</span>
                        <span class="text-[10px] font-extrabold text-indigo-600 bg-indigo-50 px-1 py-0.2 rounded">${item.percentage}%</span>
                    </div>
                </div>
            `;
            });
            container.innerHTML = html;
        }

        function initCharts(data) {
            // 1. Monthly Trend Area Spline Chart
            const monthlyOptions = {
                series: [{
                        name: 'Penjualan',
                        data: data.monthly_chart.sales
                    },
                    {
                        name: 'Pembelian',
                        data: data.monthly_chart.purchases
                    },
                    {
                        name: 'Retur Jual',
                        data: data.monthly_chart.returns
                    }
                ],
                chart: {
                    type: 'area',
                    height: 300,
                    fontFamily: 'inherit',
                    toolbar: {
                        show: false
                    },
                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 500
                    }
                },
                colors: ['#10b981', '#3b82f6', '#f43f5e'],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.45,
                        opacityTo: 0.05,
                        stops: [0, 95, 100]
                    }
                },
                stroke: {
                    curve: 'smooth',
                    width: [3, 2.5, 2]
                },
                dataLabels: {
                    enabled: false
                },
                xaxis: {
                    categories: data.monthly_chart.categories,
                    labels: {
                        style: {
                            colors: '#64748b',
                            fontSize: '11px',
                            fontWeight: 600
                        }
                    },
                    axisBorder: {
                        show: false
                    },
                    axisTicks: {
                        show: false
                    }
                },
                yaxis: {
                    labels: {
                        formatter: function(val) {
                            if (val >= 1000000000) return (val / 1000000000).toFixed(1) + ' M';
                            if (val >= 1000000) return (val / 1000000).toFixed(0) + ' Jt';
                            if (val >= 1000) return (val / 1000).toFixed(0) + ' Rb';
                            return val;
                        },
                        style: {
                            colors: '#64748b',
                            fontSize: '11px',
                            fontWeight: 600
                        }
                    }
                },
                grid: {
                    borderColor: '#f1f5f9',
                    strokeDashArray: 4,
                    padding: {
                        top: 0,
                        right: 0,
                        bottom: 0,
                        left: 10
                    }
                },
                tooltip: {
                    theme: 'light',
                    y: {
                        formatter: function(val) {
                            return formatRupiah(val);
                        }
                    }
                },
                legend: {
                    show: false
                }
            };

            monthlyChart = new ApexCharts(document.querySelector("#monthlyTrendChart"), monthlyOptions);
            monthlyChart.render();

            // 2. Transaction Type Donut Chart
            const typeOptions = {
                series: data.pie_chart.series,
                labels: data.pie_chart.labels,
                colors: data.pie_chart.colors,
                chart: {
                    type: 'donut',
                    height: 190,
                    fontFamily: 'inherit',
                    animations: {
                        enabled: true
                    }
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '72%',
                            labels: {
                                show: true,
                                name: {
                                    show: false
                                },
                                value: {
                                    show: true,
                                    fontSize: '14px',
                                    fontWeight: 800,
                                    color: '#0f172a',
                                    formatter: function(val) {
                                        if (val >= 1000000000) return (val / 1000000000).toFixed(2) + ' M';
                                        if (val >= 1000000) return (val / 1000000).toFixed(1) + ' Jt';
                                        return formatRupiah(val);
                                    }
                                },
                                total: {
                                    show: true,
                                    label: 'Total',
                                    fontSize: '10px',
                                    fontWeight: 600,
                                    color: '#64748b',
                                    formatter: function(w) {
                                        const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                        if (total >= 1000000000) return (total / 1000000000).toFixed(1) + ' M';
                                        if (total >= 1000000) return (total / 1000000).toFixed(0) + ' Jt';
                                        return formatRupiah(total);
                                    }
                                }
                            }
                        }
                    }
                },
                dataLabels: {
                    enabled: false
                },
                legend: {
                    show: false
                },
                stroke: {
                    width: 2,
                    colors: ['#ffffff']
                },
                tooltip: {
                    theme: 'light',
                    y: {
                        formatter: function(val) {
                            return formatRupiah(val);
                        }
                    }
                }
            };

            typeChart = new ApexCharts(document.querySelector("#transactionTypeChart"), typeOptions);
            typeChart.render();
        }

        function showExportLoading(btn) {
            const iconSpan = document.getElementById('export_stock_icon');
            const textSpan = document.getElementById('export_stock_text');
            
            if (!iconSpan || !textSpan) return;

            const originalIcon = iconSpan.innerHTML;
            const originalText = textSpan.innerHTML;
            
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.style.pointerEvents = 'none';
            
            iconSpan.innerHTML = `<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`;
            textSpan.innerHTML = 'Mengekspor...';
            
            if (window.iziToast) {
                iziToast.info({
                    title: 'Info',
                    message: 'Sedang menyiapkan file Excel. Mohon tunggu...',
                    position: 'topRight',
                    timeout: 7000
                });
            }
            
            setTimeout(() => {
                btn.classList.remove('opacity-75', 'cursor-not-allowed');
                btn.style.pointerEvents = 'auto';
                iconSpan.innerHTML = originalIcon;
                textSpan.innerHTML = originalText;
            }, 8000);
        }

        // ==========================
        // REAL-TIME STOCK TABLE LOGIC
        // ==========================
        let currentStockPage = 1;
        let stockSearchQuery = '';
        let stockFilterValue = 'all';
        let stockSearchTimer = null;

        async function fetchStockTable(page = 1) {
            currentStockPage = page;
            const tbody = document.getElementById('stock_table_body');
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="p-8 text-center text-slate-400">
                        <svg class="animate-spin h-6 w-6 text-indigo-500 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Memuat data...
                    </td>
                </tr>
            `;

            try {
                const url = new URL("{{ route('ho.analytics.stock-table') }}", window.location.origin);
                url.searchParams.append('page', page);
                url.searchParams.append('filter', stockFilterValue);
                
                if (stockSearchQuery) {
                    url.searchParams.append('search', stockSearchQuery);
                }

                const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Gagal memuat tabel stok');
                const data = await res.json();
                
                renderStockTable(data);
            } catch (err) {
                console.error(err);
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="p-8 text-center text-red-500">
                            Gagal memuat data stok. Silakan coba lagi.
                        </td>
                    </tr>
                `;
            }
        }

        function renderStockTable(data) {
            const tbody = document.getElementById('stock_table_body');
            const info = document.getElementById('stock_table_info');
            const pagination = document.getElementById('stock_table_pagination');

            if (!data.data || data.data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-400">
                            Tidak ada data obat ditemukan.
                        </td>
                    </tr>
                `;
                info.innerText = 'Menampilkan 0 data';
                pagination.innerHTML = '';
                return;
            }

            // Render Rows
            tbody.innerHTML = data.data.map(item => `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="p-4 font-medium">${item.name}</td>
                    <td class="p-4 text-center">
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold ${item.gudang_qty > 0 ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-500'}">
                            ${parseInt(item.gudang_qty || 0).toLocaleString('id-ID')}
                        </span>
                    </td>
                    <td class="p-4 text-center">
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold ${item.pmi_qty > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}">
                            ${parseInt(item.pmi_qty || 0).toLocaleString('id-ID')}
                        </span>
                    </td>
                    <td class="p-4 text-center">
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold ${item.asm_qty > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}">
                            ${parseInt(item.asm_qty || 0).toLocaleString('id-ID')}
                        </span>
                    </td>
                    <td class="p-4 text-center">
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold ${item.mim_qty > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}">
                            ${parseInt(item.mim_qty || 0).toLocaleString('id-ID')}
                        </span>
                    </td>
                    <td class="p-4 text-center">
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold ${item.asa_qty > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}">
                            ${parseInt(item.asa_qty || 0).toLocaleString('id-ID')}
                        </span>
                    </td>
                </tr>
            `).join('');

            // Render Info
            info.innerText = `Menampilkan ${data.from} - ${data.to} dari ${data.total} obat`;

            // Render Pagination Buttons
            let buttonsHtml = '';
            
            // Prev Button
            if (data.current_page > 1) {
                buttonsHtml += `<button onclick="fetchStockTable(${data.current_page - 1})" class="px-3 py-1 text-xs font-bold bg-white border border-slate-200 text-slate-600 rounded-lg hover:bg-slate-50">&laquo; Prev</button>`;
            } else {
                buttonsHtml += `<button disabled class="px-3 py-1 text-xs font-bold bg-slate-50 border border-slate-100 text-slate-400 rounded-lg cursor-not-allowed">&laquo; Prev</button>`;
            }
            
            // Page Indicator
            buttonsHtml += `<span class="px-3 py-1 text-xs font-bold text-slate-700">Hal ${data.current_page} / ${data.last_page}</span>`;
            
            // Next Button
            if (data.current_page < data.last_page) {
                buttonsHtml += `<button onclick="fetchStockTable(${data.current_page + 1})" class="px-3 py-1 text-xs font-bold bg-white border border-slate-200 text-slate-600 rounded-lg hover:bg-slate-50">Next &raquo;</button>`;
            } else {
                buttonsHtml += `<button disabled class="px-3 py-1 text-xs font-bold bg-slate-50 border border-slate-100 text-slate-400 rounded-lg cursor-not-allowed">Next &raquo;</button>`;
            }

            pagination.innerHTML = buttonsHtml;
        }

        // Initialize table events
        document.addEventListener('DOMContentLoaded', () => {
            fetchStockTable(1);

            const searchInput = document.getElementById('search_stock_table');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    stockSearchQuery = e.target.value.trim();
                    clearTimeout(stockSearchTimer);
                    stockSearchTimer = setTimeout(() => {
                        fetchStockTable(1);
                    }, 500); // Debounce 500ms
                });
            }

            const statusFilter = document.getElementById('filter_stock_table');
            if (statusFilter) {
                statusFilter.addEventListener('change', (e) => {
                    stockFilterValue = e.target.value;
                    fetchStockTable(1);
                });
            }
        });

    </script>
@endsection
