@extends('layouts.app')

@section('title', 'Performa Kasir & Staff Analytics')

@section('style')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        /* Executive Glassmorphism & Depth System */
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
            transform: translateY(-4px) scale(1.006);
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

        .mesh-indigo {
            background: radial-gradient(circle at 85% 15%, rgba(99, 102, 241, 0.14) 0%, transparent 60%),
                linear-gradient(180deg, #ffffff 0%, #f8f9ff 100%);
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

        .icon-pod-indigo {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            box-shadow: 0 8px 18px -3px rgba(99, 102, 241, 0.4), inset 0 2px 4px rgba(255, 255, 255, 0.35);
        }

        .icon-pod-amber {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            box-shadow: 0 8px 18px -3px rgba(245, 158, 11, 0.4), inset 0 2px 4px rgba(255, 255, 255, 0.35);
        }

        /* Top Accent Beam */
        .beam-top {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3.5px;
            opacity: 0.9;
        }

        /* Period Filter Pills */
        .period-pill {
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .period-pill.active {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: #ffffff !important;
            box-shadow: 0 4px 14px -2px rgba(79, 70, 229, 0.4);
            transform: scale(1.02);
        }

        /* Podium Cards Styling */
        .podium-card {
            position: relative;
            background: #ffffff;
            border-radius: 1.5rem;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
        }

        .podium-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 35px -10px rgba(15, 23, 42, 0.12);
        }

        .podium-rank-1 {
            border: 2px solid rgba(245, 158, 11, 0.5);
            background: linear-gradient(180deg, #fffdf8 0%, #ffffff 100%);
            box-shadow: 0 12px 30px -8px rgba(245, 158, 11, 0.2);
        }

        .podium-rank-2 {
            border: 2px solid rgba(148, 163, 184, 0.4);
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
            box-shadow: 0 10px 24px -8px rgba(100, 116, 139, 0.12);
        }

        .podium-rank-3 {
            border: 2px solid rgba(217, 119, 6, 0.35);
            background: linear-gradient(180deg, #fffaf5 0%, #ffffff 100%);
            box-shadow: 0 10px 24px -8px rgba(217, 119, 6, 0.12);
        }

        /* Avatar System */
        .avatar-pod {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 800;
            flex-shrink: 0;
            box-shadow: 0 4px 10px -2px rgba(15, 23, 42, 0.08);
        }

        /* DataTables Modern SaaS Styling */
        #staffTable_wrapper .dataTables_filter {
            margin-bottom: 1rem;
        }

        #staffTable_wrapper .dataTables_filter input {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.45rem 0.85rem;
            font-size: 12px;
            font-weight: 600;
            color: #1e293b;
            outline: none;
            background-color: #f8fafc;
            transition: all 0.2s;
        }

        #staffTable_wrapper .dataTables_filter input:focus {
            border-color: #6366f1;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }

        #staffTable_wrapper .dataTables_length select {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.35rem 1.75rem 0.35rem 0.75rem;
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            background-color: #f8fafc;
            outline: none;
        }

        #staffTable thead th {
            font-size: 11px !important;
            font-weight: 800 !important;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b !important;
            background: #f8fafc !important;
            padding: 12px 14px !important;
            border-bottom: 1px solid #e2e8f0 !important;
        }

        #staffTable tbody td {
            padding: 12px 14px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            font-size: 12px !important;
            vertical-align: middle;
        }

        #staffTable tbody tr:hover {
            background-color: #f8faff !important;
        }

        /* Animations */
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
    </style>
@endsection

@section('content')
    <div class="min-h-screen bg-slate-50/70 p-4 sm:p-6 lg:p-7 space-y-6 animate-fade-up">

        <!-- TOP HEADER BAR WITH INSTANT CONTROLS -->
        <div class="executive-surface rounded-3xl p-5 sm:p-6 relative overflow-hidden">
            <div
                class="absolute -right-16 -top-16 w-56 h-56 bg-indigo-200/30 rounded-full blur-3xl pointer-events-none animate-aura">
            </div>
            <div
                class="absolute -left-16 -bottom-16 w-56 h-56 bg-emerald-200/25 rounded-full blur-3xl pointer-events-none animate-aura">
            </div>

            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">

                <!-- Title & Status Badge -->
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span
                            class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-[10px] font-black bg-gradient-to-r from-indigo-500/10 to-purple-500/10 text-indigo-700 border border-indigo-200/80 uppercase tracking-wider shadow-2xs">
                            <span class="w-2 h-2 rounded-full bg-indigo-600 animate-ping"></span>
                            Cashier Intelligence & Leaderboard
                        </span>
                        <span class="text-xs text-slate-400 font-semibold">| Staff Performance Analytics</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-tight">
                        Performa Kasir & Tim
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 font-medium mt-0.5">
                        Monitoring produktivitas omset penjualan kasir, total struk transaksi, dan shift kerja
                    </p>
                </div>

                <!-- Instant Filter Controls -->
                <div class="flex flex-wrap items-center gap-2.5">

                    <!-- Branch Selector -->
                    <div class="relative">
                        <select id="pharmacyFilter" onchange="onPharmacyChange()"
                            class="text-xs font-bold px-3.5 py-2 rounded-2xl border border-slate-200 bg-white/90 shadow-2xs text-slate-700 outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all cursor-pointer">
                            <option value="all">🏢 Semua Cabang (Total Group)</option>
                            @foreach ($pharmacies as $p)
                                <option value="{{ $p->id }}">📍 {{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Period Pills -->
                    <div
                        class="inline-flex p-1.5 bg-slate-200/60 backdrop-blur-md rounded-2xl border border-slate-300/60 shadow-inner">
                        <button type="button" onclick="setPeriod('today')" id="btn_period_today"
                            class="period-pill active px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 transition-all">
                            Hari Ini
                        </button>
                        <button type="button" onclick="setPeriod('this_week')" id="btn_period_this_week"
                            class="period-pill px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 transition-all">
                            Minggu Ini
                        </button>
                        <button type="button" onclick="setPeriod('this_month')" id="btn_period_this_month"
                            class="period-pill px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 transition-all">
                            Bulan Ini
                        </button>
                        <button type="button" onclick="setPeriod('this_year')" id="btn_period_this_year"
                            class="period-pill px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 transition-all">
                            Tahun Ini
                        </button>
                        <button type="button" onclick="setPeriod('all')" id="btn_period_all"
                            class="period-pill px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 transition-all">
                            Semua
                        </button>
                        <button type="button" id="btn_period_custom"
                            class="period-pill relative inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 transition-all cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-calendar">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12z" />
                                <path d="M16 3v4" />
                                <path d="M8 3v4" />
                                <path d="M4 11h16" />
                            </svg>
                            <span id="custom_period_label">Kustom</span>
                            <input type="text" id="custom_date_range_picker"
                                class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10" readonly>
                        </button>
                    </div>

                </div>

            </div>

            <!-- Active Indicator -->
            <div class="relative z-10 mt-4 pt-3.5 border-t border-slate-200/70 flex items-center justify-between text-xs">
                <div class="flex items-center gap-2 text-slate-600">
                    <span class="text-slate-400 font-medium">Periode:</span>
                    <span id="active_period_text"
                        class="font-extrabold text-indigo-700 bg-white/90 px-3 py-0.5 rounded-xl border border-indigo-100 shadow-2xs">
                        {{ $initialSummary['period_label'] }}
                    </span>
                    <span class="text-slate-400 font-medium ml-2">Kasir Aktif:</span>
                    <span id="active_staff_badge"
                        class="font-extrabold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-xl border border-emerald-200">
                        {{ $initialSummary['active_staff_count'] }} dari {{ $initialSummary['total_staff_count'] }} Staf
                    </span>
                </div>
                <div class="flex items-center gap-2 text-slate-500 text-[11px] font-semibold">
                    <span class="relative flex h-2 w-2">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span>Live Auto Sync</span>
                </div>
            </div>
        </div>

        <!-- 4 HERO KPI METRIC CARDS -->

        <!-- TOP 3 CASHIER LEADERBOARD PODIUM -->
        <div class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🏆</span>
                    <h3 class="text-base font-black text-slate-900 tracking-tight">Top 3 Kasir Berprestasi</h3>
                    <span class="text-xs text-slate-400 font-medium">(Leaderboard Periode Terpilih)</span>
                </div>
            </div>

            <div id="podium_container" class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-5">
                @for ($i = 0; $i < 3; $i++)
                    @php
                        $p = $initialSummary['podium'][$i] ?? null;
                        $rankNumber = $i + 1;
                        $rankBadge =
                            $rankNumber == 1 ? '🥇 #1 GOLD' : ($rankNumber == 2 ? '🥈 #2 SILVER' : '🥉 #3 BRONZE');
                        $rankClass =
                            $rankNumber == 1 ? 'podium-rank-1' : ($rankNumber == 2 ? 'podium-rank-2' : 'podium-rank-3');
                        $avatarBg =
                            $rankNumber == 1
                                ? 'bg-amber-100 text-amber-800'
                                : ($rankNumber == 2
                                    ? 'bg-slate-200 text-slate-800'
                                    : 'bg-orange-100 text-orange-800');
                    @endphp

                    <div id="podium_card_{{ $rankNumber }}"
                        class="podium-card {{ $rankClass }} p-5 flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-3 mb-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="avatar-pod {{ $avatarBg }} font-black text-sm">
                                        <span class="podium-initials">{{ $p['initials'] ?? '—' }}</span>
                                    </div>
                                    <div>
                                        <h4
                                            class="text-sm font-black text-slate-900 leading-tight truncate max-w-[150px] podium-name">
                                            {{ $p['name'] ?? 'Belum ada data' }}
                                        </h4>
                                        <span class="text-[10px] text-slate-400 font-medium block podium-branch">
                                            {{ $p['pharmacy_name'] ?? '—' }}
                                        </span>
                                    </div>
                                </div>
                                <span
                                    class="text-[10px] font-black px-2.5 py-0.5 rounded-full {{ $rankNumber == 1 ? 'bg-amber-500 text-white shadow-xs' : ($rankNumber == 2 ? 'bg-slate-400 text-white' : 'bg-amber-700 text-white') }}">
                                    {{ $rankBadge }}
                                </span>
                            </div>

                            <!-- Omset Value -->
                            <div class="bg-white/80 rounded-2xl p-3 border border-slate-100 my-2">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block">Omset
                                    Kasir</span>
                                <h3
                                    class="text-base sm:text-lg font-black text-slate-900 tracking-tight leading-tight mt-0.5 podium-sales">
                                    {{ $p['sales_rp'] ?? 'Rp 0' }}
                                </h3>

                                <!-- Mini progress bar share -->
                                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mt-2">
                                    <div class="bg-indigo-600 h-full rounded-full podium-bar"
                                        style="width: {{ $p['share'] ?? 0 }}%"></div>
                                </div>
                                <div class="flex items-center justify-between text-[9px] text-slate-400 mt-1 font-semibold">
                                    <span>Kontribusi Tim</span>
                                    <span class="text-indigo-600 font-bold podium-share">{{ $p['share'] ?? 0 }}%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Secondary Metrics -->
                        <div class="pt-3 border-t border-slate-100 grid grid-cols-2 gap-2 text-center text-xs">
                            <div class="bg-slate-50 p-2 rounded-xl">
                                <span class="text-[10px] text-slate-400 block font-medium">Transaksi</span>
                                <strong
                                    class="text-slate-800 font-black podium-txns">{{ number_format($p['txns'] ?? 0, 0, ',', '.') }}</strong>
                            </div>
                            <div class="bg-slate-50 p-2 rounded-xl">
                                <span class="text-[10px] text-slate-400 block font-medium">AOV</span>
                                <strong
                                    class="text-emerald-700 font-black podium-aov">{{ $p['aov_rp'] ?? 'Rp 0' }}</strong>
                            </div>
                        </div>

                    </div>
                @endfor
            </div>
        </div>

        <!-- VISUAL PERFORMANCE SECTION (CHART & TEAM INSIGHTS) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- LEFT CHART: TOP 8 CASHIERS BAR CHART (7 COLS) -->
            <div
                class="lg:col-span-12 executive-surface rounded-3xl p-5 sm:p-6 shadow-xs flex flex-col justify-between relative overflow-hidden">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-base font-black text-slate-900 tracking-tight">
                            Komparasi Omset Kasir Teratas
                        </h3>
                        <p class="text-[11px] text-slate-400 font-medium">Perbandingan kontribusi omset kasir terproduktif
                        </p>
                    </div>
                    <span
                        class="text-[11px] font-bold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-xl border border-indigo-100">
                        Top 8 Performer
                    </span>
                </div>

                <div class="w-full">
                    <div id="cashierBarChart" class="w-full min-h-[290px]"></div>
                </div>
            </div>
        </div>

        <!-- FULL STAFF LEADERBOARD DATATABLE -->
        <div class="executive-surface rounded-3xl p-5 sm:p-6 shadow-xs">
            <div
                class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-200/80">
                <div class="flex items-center gap-2.5">
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
                        </svg>
                        Tabel Lengkap Performa Kasir
                    </span>
                    <span class="text-xs text-slate-400 font-medium">Urutan berdasarkan omset tertinggi</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table id="staffTable" class="w-full text-left">
                    <thead>
                        <tr>
                            <th class="w-12 text-center">Rank</th>
                            <th>Kasir & Unit Cabang</th>
                            <th class="text-right">Penjualan (Omset)</th>
                            <th class="text-right">Transaksi (Struk)</th>
                            <th class="text-right">Rata-rata Struk (AOV)</th>
                            <th class="text-right">Shift Selesai</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        let activePeriod = 'today';
        let customStart = '';
        let customEnd = '';
        let staffDataTable = null;
        let cashierBarChart = null;

        const initialSummary = @json($initialSummary);

        const formatRupiah = (val) => {
            return 'Rp ' + Math.round(val || 0).toLocaleString('id-ID');
        };

        const formatNumber = (val) => {
            return Math.round(val || 0).toLocaleString('id-ID');
        };

        function setPeriod(period) {
            activePeriod = period;
            document.querySelectorAll('.period-pill').forEach(btn => btn.classList.remove('active'));

            const targetBtn = document.getElementById(`btn_period_${period}`);
            if (targetBtn) targetBtn.classList.add('active');

            if (period !== 'custom') {
                document.getElementById('custom_period_label').textContent = 'Kustom';
                customStart = '';
                customEnd = '';
                reloadDashboard();
            }
        }

        function onPharmacyChange() {
            reloadDashboard();
        }

        function reloadDashboard() {
            const pharmacyId = document.getElementById('pharmacyFilter').value;

            // 1. Fetch Summary & Podium & Chart via AJAX
            $.ajax({
                url: '{{ route('admin.staff-stats.summary') }}',
                method: 'GET',
                data: {
                    period: activePeriod,
                    start_date: customStart,
                    end_date: customEnd,
                    pharmacy_id: pharmacyId
                },
                success: function(data) {
                    updateSummaryUI(data);
                    updateChart(data.chart_data);
                }
            });

            // 2. Reload DataTables
            if (staffDataTable) {
                staffDataTable.ajax.reload();
            }
        }

        function updateSummaryUI(data) {
            $('#active_period_text').text(data.period_label);
            $('#active_staff_badge').text(`${data.active_staff_count} dari ${data.total_staff_count} Staf`);

            // Hero Cards
            $('#metric_total_sales').text(data.total_sales_rp);
            $('#metric_active_staff').text(data.active_staff_count);
            $('#metric_total_txns').text(formatNumber(data.total_transactions));
            $('#metric_total_shifts').text(data.total_shifts);
            $('#metric_aov').text(data.average_order_value);

            // Top Performer
            if (data.top_performer) {
                $('#metric_top_name').text(data.top_performer.name);
                $('#metric_top_sales').text(data.top_performer.sales_rp);
                $('#metric_top_branch').text(data.top_performer.pharmacy_name);
                $('#metric_top_share').text(`${data.top_performer.share}% Share`);
            } else {
                $('#metric_top_name').text('Belum ada data');
                $('#metric_top_sales').text('Rp 0');
                $('#metric_top_branch').text('—');
                $('#metric_top_share').text('0% Share');
            }

            // Insights
            $('#insight_active_ratio').text(`${data.active_staff_count} / ${data.total_staff_count} Kasir`);
            const avgPerStaff = data.active_staff_count > 0 ? (data.total_sales / data.active_staff_count) : 0;
            $('#insight_avg_staff_sales').text(formatRupiah(avgPerStaff));
            $('#insight_total_shifts').text(`${data.total_shifts} Shift`);

            // Podium
            for (let i = 1; i <= 3; i++) {
                const p = data.podium[i - 1];
                const card = $(`#podium_card_${i}`);
                if (p) {
                    card.find('.podium-initials').text(p.initials);
                    card.find('.podium-name').text(p.name);
                    card.find('.podium-branch').text(p.pharmacy_name);
                    card.find('.podium-sales').text(p.sales_rp);
                    card.find('.podium-bar').css('width', `${p.share}%`);
                    card.find('.podium-share').text(`${p.share}%`);
                    card.find('.podium-txns').text(formatNumber(p.txns));
                    card.find('.podium-aov').text(p.aov_rp);
                    card.removeClass('opacity-50');
                } else {
                    card.find('.podium-initials').text('—');
                    card.find('.podium-name').text('Belum ada data');
                    card.find('.podium-branch').text('—');
                    card.find('.podium-sales').text('Rp 0');
                    card.find('.podium-bar').css('width', '0%');
                    card.find('.podium-share').text('0%');
                    card.find('.podium-txns').text('0');
                    card.find('.podium-aov').text('Rp 0');
                    card.addClass('opacity-50');
                }
            }
        }

        function initChart(chartData) {
            const options = {
                series: [{
                    name: 'Omset Kasir',
                    data: chartData.series || []
                }],
                chart: {
                    type: 'bar',
                    height: 290,
                    fontFamily: 'inherit',
                    toolbar: {
                        show: false
                    }
                },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 6,
                        barHeight: '60%',
                        distributed: true,
                        dataLabels: {
                            position: 'bottom'
                        }
                    }
                },
                colors: ['#4f46e5', '#6366f1', '#3b82f6', '#0ea5e9', '#10b981', '#14b8a6', '#f59e0b', '#8b5cf6'],
                dataLabels: {
                    enabled: true,
                    textAnchor: 'start',
                    style: {
                        colors: ['#0f172a'],
                        fontSize: '11px',
                        fontWeight: 700
                    },
                    formatter: function(val, opt) {
                        return formatRupiah(val);
                    },
                    offsetX: 5
                },
                xaxis: {
                    categories: chartData.categories || [],
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
                        style: {
                            colors: '#334155',
                            fontSize: '11px',
                            fontWeight: 700
                        }
                    }
                },
                grid: {
                    borderColor: '#f1f5f9',
                    strokeDashArray: 4,
                    padding: {
                        left: 10,
                        right: 20
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

            cashierBarChart = new ApexCharts(document.querySelector("#cashierBarChart"), options);
            cashierBarChart.render();
        }

        function updateChart(chartData) {
            if (cashierBarChart) {
                cashierBarChart.updateOptions({
                    xaxis: {
                        categories: chartData.categories || []
                    }
                });
                cashierBarChart.updateSeries([{
                    data: chartData.series || []
                }]);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {

            // Initialize Flatpickr for custom date range
            flatpickr("#custom_date_range_picker", {
                mode: "range",
                dateFormat: "Y-m-d",
                position: "auto right",
                onClose: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        const fmt = d => d.toISOString().split('T')[0];
                        customStart = fmt(selectedDates[0]);
                        customEnd = fmt(selectedDates[1]);
                        activePeriod = 'custom';

                        document.querySelectorAll('.period-pill').forEach(btn => btn.classList.remove(
                            'active'));
                        document.getElementById('btn_period_custom').classList.add('active');
                        document.getElementById('custom_period_label').textContent =
                            `${customStart} - ${customEnd}`;

                        reloadDashboard();
                    }
                }
            });

            // Initialize ApexChart
            initChart(initialSummary.chart_data);

            // Initialize DataTables
            staffDataTable = $('#staffTable').DataTable({
                responsive: true,
                autoWidth: false,
                processing: true,
                serverSide: true,
                searching: true,
                ajax: {
                    url: '{{ route('admin.staff-stats') }}',
                    data: function(d) {
                        d.period = activePeriod;
                        d.start_date = customStart;
                        d.end_date = customEnd;
                        d.pharmacy_id = document.getElementById('pharmacyFilter').value;
                    }
                },
                language: {
                    processing: 'Memuat data performa...',
                    emptyTable: 'Tidak ada data kasir',
                    zeroRecords: 'Kasir tidak ditemukan',
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ kasir',
                    infoEmpty: '0 kasir',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    search: 'Cari kasir:',
                    paginate: {
                        next: '›',
                        previous: '‹'
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center font-bold',
                        render: function(val) {
                            if (val == 1)
                                return '<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-100 text-amber-800 text-[11px] font-black border border-amber-300">🥇 1</span>';
                            if (val == 2)
                                return '<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-200 text-slate-700 text-[11px] font-black border border-slate-300">🥈 2</span>';
                            if (val == 3)
                                return '<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-orange-100 text-orange-800 text-[11px] font-black border border-orange-300">🥉 3</span>';
                            return `<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-100 text-slate-600 text-[10px] font-bold">${val}</span>`;
                        }
                    },
                    {
                        data: 'name',
                        orderable: true,
                        searchable: true,
                        render: function(val, type, row) {
                            const colors = ['bg-indigo-100 text-indigo-700',
                                'bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700',
                                'bg-purple-100 text-purple-700'
                            ];
                            const colorIndex = (row.id || 0) % colors.length;
                            const avatarColor = colors[colorIndex];

                            return `
                                <div class="flex items-center gap-3">
                                    <div class="avatar-pod ${avatarColor}">
                                        ${row.initials}
                                    </div>
                                    <div>
                                        <div class="font-extrabold text-slate-900 leading-tight text-xs">${val}</div>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="text-[10px] text-slate-400 font-semibold">@${row.username}</span>
                                            <span class="text-[9px] font-extrabold px-2 py-0.2 rounded-md bg-slate-100 text-slate-600 border border-slate-200/60">
                                                ${row.pharmacy_name ? row.pharmacy_name : 'Semua Cabang'}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                    },
                    {
                        data: 'filtered_sales',
                        orderable: true,
                        searchable: false,
                        className: 'text-right',
                        render: function(val, type, row) {
                            const salesNum = parseFloat(val) || 0;
                            const share = row.sales_share || 0;
                            return `
                                <div>
                                    <div class="font-black text-slate-900 text-xs">${formatRupiah(salesNum)}</div>
                                    <div class="flex items-center justify-end gap-1.5 mt-1">
                                        <div class="w-16 bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                            <div class="bg-indigo-500 h-full rounded-full" style="width: ${share}%"></div>
                                        </div>
                                        <span class="text-[9px] font-bold text-indigo-600">${share}%</span>
                                    </div>
                                </div>
                            `;
                        }
                    },
                    {
                        data: 'filtered_transactions',
                        orderable: true,
                        searchable: false,
                        className: 'text-right',
                        render: function(val) {
                            return `<div class="font-black text-slate-800 text-xs">${formatNumber(val)} <span class="text-[10px] font-normal text-slate-400">struk</span></div>`;
                        }
                    },
                    {
                        data: 'aov',
                        orderable: true,
                        searchable: false,
                        className: 'text-right',
                        render: function(val, type, row) {
                            return `<div class="font-bold text-emerald-700 text-xs">${row.aov_rp}</div>`;
                        }
                    },
                    {
                        data: 'shifts_completed',
                        orderable: true,
                        searchable: false,
                        className: 'text-right',
                        render: function(val) {
                            const numShifts = parseInt(val) || 0;
                            return `<span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[11px] font-bold ${numShifts > 0 ? 'bg-blue-50 text-blue-700 border border-blue-100' : 'text-slate-400'}">${numShifts} shift</span>`;
                        }
                    }
                ],
                order: [
                    [2, 'desc']
                ],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100]
            });

        });
    </script>
@endsection
