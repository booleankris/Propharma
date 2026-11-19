@extends('layouts.app')

@section('title', 'Revisi Stok Terfokus')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body space-y-4">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-6">
                {{-- Header --}}
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 border-b border-slate-100 pb-4 mb-5">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <h1 class="text-lg font-bold text-slate-800 leading-tight">Revisi Stok Terfokus</h1>
                            <p class="text-xs text-slate-500">
                                Selaraskan stok batch tanpa mengubah <strong>Saldo Awal (SO-SALDO-AWAL)</strong> dan otomatis tercatat sebagai <strong>REVISI STOK</strong> di Kartu Stok.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap lg:flex-nowrap items-center gap-2 shrink-0">
                        @if ($canAccessWarehouse)
                            <div class="inline-flex rounded-xl bg-slate-100 p-1 border border-slate-200 whitespace-nowrap shrink-0">
                                <button type="button" id="btn_mode_gudang"
                                    class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all whitespace-nowrap {{ $defaultMode === 'gudang' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                                    Gudang PMI
                                </button>
                                <button type="button" id="btn_mode_pelayanan"
                                    class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all whitespace-nowrap {{ $defaultMode === 'pelayanan' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                                    Pelayanan / Etalase
                                </button>
                            </div>
                        @else
                            <span class="px-3 py-1.5 text-xs font-bold bg-cyan-50 text-cyan-700 border border-cyan-200 rounded-xl whitespace-nowrap shrink-0">
                                Mode Etalase: {{ $activePharmacy->name ?? '-' }}
                            </span>
                        @endif

                        <button type="button" id="btn_scan_anomalies"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-600 hover:text-white text-xs font-bold transition-colors whitespace-nowrap shrink-0">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z" />
                            </svg>
                            Cek Batch Minus / Selisih
                        </button>

                        <a href="{{ route('supplies.index') }}"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 hover:bg-slate-200 text-xs font-bold transition-colors whitespace-nowrap shrink-0">
                            Kartu Stok
                        </a>
                    </div>
                </div>

                {{-- Search Obat --}}
                <div class="relative mb-5">
                    <label for="rev_search_input" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">
                        Cari Obat untuk Direvisi
                    </label>
                    <div class="relative">
                        <input type="text" id="rev_search_input" autocomplete="off"
                            placeholder="Ketik nama obat atau kode obat (mis. FIESTA INTIM, ATEVIR)..."
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 focus:border-indigo-500">
                        <div id="rev_search_dropdown"
                            class="hidden absolute left-0 right-0 z-50 mt-1.5 bg-white border border-slate-200 rounded-xl shadow-xl max-h-72 overflow-y-auto divide-y divide-slate-100">
                        </div>
                    </div>
                </div>

                {{-- Panel Anomali / Selisih --}}
                <div id="anomalies_panel" class="hidden mb-5 rounded-xl border border-rose-200 bg-rose-50/40 p-4">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-rose-800">Daftar Obat Batch Minus / Selisih Saldo</h2>
                            <p class="text-[11px] text-rose-600">Klik "Pilih" untuk meninjau batch dan menyelaraskan stoknya ke Kartu Stok.</p>
                        </div>
                        <button type="button" id="btn_close_anomalies" class="text-xs font-semibold text-rose-600 hover:text-rose-800">Tutup</button>
                    </div>
                    <div id="anomalies_content" class="overflow-x-auto bg-white rounded-xl border border-rose-100"></div>
                </div>

                {{-- Detail Obat Terpilih --}}
                <div id="revision_workspace" class="hidden space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Obat Terpilih &bull; <span id="rev_location_label">-</span></div>
                            <div class="text-base font-bold text-slate-800" id="rev_med_name">-</div>
                            <div class="text-xs text-slate-500 font-mono" id="rev_med_code">-</div>
                        </div>
                        <button type="button" id="btn_sync_to_ledger"
                            class="hidden px-3.5 py-2 rounded-xl bg-amber-100 text-amber-900 border border-amber-300 hover:bg-amber-200 text-xs font-bold transition-colors">
                            Isi Otomatis Mengikuti Saldo Kartu Stok
                        </button>
                    </div>

                    {{-- 4 KPI Cards --}}
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                        <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-3.5">
                            <span class="block text-[11px] font-bold uppercase tracking-wider text-indigo-500">Saldo Awal (Terkunci)</span>
                            <span id="kpi_opening" class="mt-1 block text-xl font-bold text-indigo-900">0</span>
                        </div>
                        <div class="rounded-xl border border-purple-100 bg-purple-50/60 p-3.5">
                            <span class="block text-[11px] font-bold uppercase tracking-wider text-purple-600">Saldo Kartu Stok</span>
                            <span id="kpi_ledger" class="mt-1 block text-xl font-bold text-purple-900">0</span>
                        </div>
                        <div class="rounded-xl border border-amber-100 bg-amber-50/60 p-3.5">
                            <span class="block text-[11px] font-bold uppercase tracking-wider text-amber-700" id="kpi_db_label">Stok Batch DB</span>
                            <span id="kpi_db" class="mt-1 block text-xl font-bold text-amber-900">0</span>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-3.5">
                            <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Selisih (DB &minus; Saldo)</span>
                            <span id="kpi_diff" class="mt-1 block text-xl font-bold text-slate-800">0</span>
                        </div>
                    </div>

                    {{-- Tabel Batch --}}
                    <div class="rounded-xl border border-slate-200 overflow-hidden">
                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-200">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Daftar Batch di Lokasi Aktif</span>
                            <button type="button" id="btn_add_new_batch"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-600 hover:text-white text-xs font-bold transition-colors whitespace-nowrap">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                Tambah Batch
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-xs text-left">
                                <thead class="bg-slate-50 text-slate-600 uppercase text-[11px] border-b border-slate-200">
                                    <tr>
                                        <th class="px-4 py-3">Batch</th>
                                        <th class="px-4 py-3">Expired Date</th>
                                        <th class="px-4 py-3">Etalase</th>
                                        <th class="px-4 py-3 text-right">Stok DB Saat Ini</th>
                                        <th class="px-4 py-3 text-right w-44">Stok Revisi Baru</th>
                                        <th class="px-4 py-3 text-right">Perubahan Batch</th>
                                    </tr>
                                </thead>
                                <tbody id="rev_batches_tbody" class="divide-y divide-slate-100 bg-white"></tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Preview Efek ke Kartu Stok + Form Alasan --}}
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
                        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-4 space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label for="rev_reason" class="block text-xs font-bold text-slate-700 mb-1">Alasan Revisi <span class="text-rose-500">*</span></label>
                                    <select id="rev_reason" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                        <option value="Koreksi batch minus / selisih stok DB">Koreksi batch minus / selisih stok DB</option>
                                        <option value="Penyesuaian stok fisik rak">Penyesuaian stok fisik rak</option>
                                        <option value="Koreksi pasca retur pembelian">Koreksi pasca retur pembelian</option>
                                        <option value="Koreksi salah input batch / mutasi">Koreksi salah input batch / mutasi</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="rev_notes" class="block text-xs font-bold text-slate-700 mb-1">Catatan Detail (Opsional)</label>
                                    <input type="text" id="rev_notes" placeholder="Contoh: Perbaikan batch RET-2610/0007..."
                                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100">
                                <p class="text-[11px] text-slate-500">
                                    Menyimpan revisi ini akan menulis baris <strong>REVISI STOK (REV-...)</strong> di Kartu Stok tanpa mengubah <strong>Saldo Awal</strong>.
                                </p>
                                <button type="button" id="btn_submit_revision"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-sm transition-colors">
                                    Simpan Revisi Stok
                                </button>
                            </div>
                        </div>

                        {{-- Preview Kartu Stok --}}
                        <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 space-y-2 text-xs">
                            <div class="font-bold text-indigo-900 uppercase tracking-wider text-[11px]">Simulasi Dampak ke Kartu Stok</div>
                            <div class="flex justify-between">
                                <span class="text-slate-600">Saldo Awal (Tetap):</span>
                                <strong id="sim_opening" class="text-slate-900">0</strong>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-600">Total DB Baru:</span>
                                <strong id="sim_new_db" class="text-slate-900">0</strong>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-600">Qty Tercatat di Kartu Stok:</span>
                                <strong id="sim_ledger_delta" class="text-indigo-700">0</strong>
                            </div>
                            <div class="flex justify-between border-t border-indigo-200 pt-2">
                                <span class="font-bold text-indigo-900">Saldo Akhir Kartu Stok:</span>
                                <strong id="sim_new_ledger" class="text-indigo-900 text-sm">0</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Riwayat Revisi Stok --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-800">Riwayat Revisi Stok</h2>
                        <p class="text-xs text-slate-500">Jejak perubahan revisi stok cabang aktif (tercatat juga di Kartu Stok).</p>
                    </div>
                    <button type="button" id="btn_refresh_history"
                        class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold">
                        Refresh
                    </button>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-600 uppercase text-[11px] border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Kode</th>
                                <th class="px-4 py-3">Obat</th>
                                <th class="px-4 py-3 text-right">Stok DB</th>
                                <th class="px-4 py-3 text-right">Saldo Kartu Stok</th>
                                <th class="px-4 py-3">Alasan &amp; Catatan</th>
                                <th class="px-4 py-3">Petugas</th>
                            </tr>
                        </thead>
                        <tbody id="rev_history_tbody" class="divide-y divide-slate-100 bg-white">
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-slate-400">Memuat riwayat...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script>
        (function() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const routes = {
                search: @json(route('supplies.revision.searchMedicine')),
                anomalies: @json(route('supplies.revision.anomalies')),
                history: @json(route('supplies.revision.history')),
                inspectBase: @json(url('/stock-revision/medicine')),
                store: @json(route('supplies.revision.store')),
            };

            let targetMode = @json($defaultMode);
            let currentData = null;
            let searchTimer = null;
            const preselectedMedId = @json($preselectedMedId);

            function escapeHtml(str) {
                return String(str ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function fmtNum(val) {
                return new Intl.NumberFormat('id-ID').format(Number(val) || 0);
            }

            function setMode(mode) {
                targetMode = mode;
                const btnG = document.getElementById('btn_mode_gudang');
                const btnP = document.getElementById('btn_mode_pelayanan');
                if (btnG && btnP) {
                    btnG.className = 'px-3 py-1.5 text-xs font-bold rounded-lg transition-all ' + (mode === 'gudang' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600 hover:text-slate-900');
                    btnP.className = 'px-3 py-1.5 text-xs font-bold rounded-lg transition-all ' + (mode === 'pelayanan' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600 hover:text-slate-900');
                }
                if (currentData?.medicine?.id) {
                    inspectMedicine(currentData.medicine.id);
                }
                loadHistory();
                if (!document.getElementById('anomalies_panel').classList.contains('hidden')) {
                    loadAnomalies();
                }
            }

            document.getElementById('btn_mode_gudang')?.addEventListener('click', () => setMode('gudang'));
            document.getElementById('btn_mode_pelayanan')?.addEventListener('click', () => setMode('pelayanan'));

            const searchInput = document.getElementById('rev_search_input');
            const searchDropdown = document.getElementById('rev_search_dropdown');

            searchInput.addEventListener('input', function() {
                const q = this.value.trim();
                clearTimeout(searchTimer);
                if (q.length < 1) {
                    searchDropdown.classList.add('hidden');
                    searchDropdown.innerHTML = '';
                    return;
                }
                searchTimer = setTimeout(() => {
                    axios.get(routes.search, { params: { q, target_mode: targetMode } })
                        .then(res => {
                            const rows = res.data || [];
                            if (rows.length === 0) {
                                searchDropdown.innerHTML = '<div class="px-4 py-3 text-xs text-slate-400 italic">Obat tidak ditemukan.</div>';
                                searchDropdown.classList.remove('hidden');
                                return;
                            }
                            searchDropdown.innerHTML = rows.map(item => `
                                <button type="button" data-med-id="${item.id}"
                                    class="rev-pick-med w-full text-left px-4 py-2.5 hover:bg-indigo-50 flex items-center justify-between gap-3 transition-colors">
                                    <div>
                                        <div class="text-xs font-bold text-slate-800">${escapeHtml(item.name)}</div>
                                        <div class="text-[11px] font-mono text-slate-400">${escapeHtml(item.code || '-')}</div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-xs font-bold ${item.has_negative || item.db_stock < 0 ? 'text-rose-600' : 'text-slate-700'}">
                                            ${fmtNum(item.db_stock)} ${escapeHtml(item.unit)}
                                        </span>
                                        ${item.has_negative ? '<span class="block text-[10px] font-bold text-rose-600">Ada Batch Minus</span>' : ''}
                                    </div>
                                </button>
                            `).join('');
                            searchDropdown.classList.remove('hidden');
                        });
                }, 250);
            });

            document.addEventListener('click', function(e) {
                const btn = e.target.closest('.rev-pick-med');
                if (btn) {
                    const medId = Number(btn.dataset.medId);
                    searchDropdown.classList.add('hidden');
                    inspectMedicine(medId);
                    return;
                }
                if (!e.target.closest('#rev_search_input') && !e.target.closest('#rev_search_dropdown')) {
                    searchDropdown.classList.add('hidden');
                }
            });

            function inspectMedicine(medId) {
                axios.get(`${routes.inspectBase}/${medId}`, { params: { target_mode: targetMode } })
                    .then(res => {
                        currentData = res.data;
                        renderWorkspace();
                    })
                    .catch(err => {
                        iziToast.error({ title: 'Gagal', message: err.response?.data?.message || 'Gagal memuat detail obat.', position: 'topRight' });
                    });
            }

            function renderWorkspace() {
                if (!currentData) return;
                const { medicine, target_pharmacy, summary, batches } = currentData;

                document.getElementById('revision_workspace').classList.remove('hidden');
                document.getElementById('rev_med_name').textContent = `${medicine.name} (${medicine.unit})`;
                document.getElementById('rev_med_code').textContent = `Kode: ${medicine.code || '-'}`;
                document.getElementById('rev_location_label').textContent =
                    (summary.target_mode === 'gudang' ? 'GUDANG: ' : 'ETALASE: ') + (target_pharmacy.name || '-');

                document.getElementById('kpi_opening').textContent = fmtNum(summary.opening_stock);
                document.getElementById('kpi_ledger').textContent = fmtNum(summary.ledger_balance);
                document.getElementById('kpi_db_label').textContent = summary.target_mode === 'gudang' ? 'Stok Gudang (DB)' : 'Stok Etalase (DB)';
                document.getElementById('kpi_db').textContent = fmtNum(summary.db_stock);

                const diffEl = document.getElementById('kpi_diff');
                diffEl.textContent = (summary.discrepancy > 0 ? '+' : '') + fmtNum(summary.discrepancy);
                diffEl.className = 'mt-1 block text-xl font-bold ' + (summary.discrepancy !== 0 ? 'text-rose-600' : 'text-emerald-600');

                const syncBtn = document.getElementById('btn_sync_to_ledger');
                if (summary.discrepancy !== 0 && batches.length > 0 && summary.ledger_balance >= 0) {
                    syncBtn.classList.remove('hidden');
                } else {
                    syncBtn.classList.add('hidden');
                }

                const tbody = document.getElementById('rev_batches_tbody');
                if (!batches || batches.length === 0) {
                    tbody.innerHTML = '<tr id="rev_empty_batches_row"><td colspan="6" class="px-4 py-5 text-center text-slate-400">Belum ada batch terdaftar di lokasi ini. Klik <strong>+ Tambah Batch</strong> untuk menambahkan batch baru.</td></tr>';
                } else {
                    tbody.innerHTML = batches.map(b => {
                        const defaultNew = b.current_qty < 0 ? 0 : b.current_qty;
                        return `
                            <tr data-batch-id="${b.batch_id}" data-current-qty="${b.current_qty}">
                                <td class="px-4 py-3 font-bold text-slate-800">
                                    ${escapeHtml(b.name)}
                                    ${b.is_negative ? '<span class="ml-1.5 px-1.5 py-0.5 text-[10px] rounded bg-rose-100 text-rose-700 font-bold">MINUS</span>' : ''}
                                </td>
                                <td class="px-4 py-3 text-slate-600">${escapeHtml(b.expired_date)}</td>
                                <td class="px-4 py-3 text-slate-500">${escapeHtml(b.etalase_name || '-')}</td>
                                <td class="px-4 py-3 text-right font-bold ${b.current_qty < 0 ? 'text-rose-600' : 'text-slate-700'}">${fmtNum(b.current_qty)}</td>
                                <td class="px-4 py-3 text-right">
                                    <input type="number" min="0" step="1" value="${defaultNew}"
                                        class="rev-batch-input w-28 text-right font-bold rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-200 focus:border-indigo-500">
                                </td>
                                <td class="px-4 py-3 text-right font-bold rev-batch-diff text-slate-600">0</td>
                            </tr>
                        `;
                    }).join('');
                }

                recalculateSimulation();
            }

            function appendNewBatchRow() {
                if (!currentData) return;
                const tbody = document.getElementById('rev_batches_tbody');
                document.getElementById('rev_empty_batches_row')?.remove();

                const defaultEd = new Date(new Date().setFullYear(new Date().getFullYear() + 2)).toISOString().slice(0, 10);
                const isPelayanan = currentData.summary?.target_mode === 'pelayanan';
                const etalases = currentData.etalases || [];
                const etalaseControl = isPelayanan && etalases.length > 0
                    ? `<select class="rev-new-etalase w-full min-w-[120px] rounded-lg border border-slate-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-200">
                            ${etalases.map(e => `<option value="${e.id}">${escapeHtml(e.name)}</option>`).join('')}
                       </select>`
                    : '<span class="text-slate-400">-</span>';

                const tr = document.createElement('tr');
                tr.dataset.isNewBatch = '1';
                tr.dataset.currentQty = '0';
                tr.className = 'bg-emerald-50/30';
                tr.innerHTML = `
                    <td class="px-4 py-2.5">
                        <div class="flex items-center gap-1.5">
                            <span class="px-1.5 py-0.5 text-[10px] rounded bg-emerald-100 text-emerald-700 font-bold shrink-0">BARU</span>
                            <input type="text" placeholder="Nomor batch..." class="rev-new-name w-36 rounded-lg border border-emerald-300 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                        </div>
                    </td>
                    <td class="px-4 py-2.5">
                        <input type="date" value="${defaultEd}" class="rev-new-ed rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    </td>
                    <td class="px-4 py-2.5">${etalaseControl}</td>
                    <td class="px-4 py-2.5 text-right font-bold text-slate-500">0</td>
                    <td class="px-4 py-2.5 text-right">
                        <input type="number" min="0" step="1" value="0"
                            class="rev-batch-input w-28 text-right font-bold rounded-lg border border-emerald-300 bg-white px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-200">
                    </td>
                    <td class="px-4 py-2.5 text-right">
                        <div class="inline-flex items-center gap-2">
                            <span class="font-bold rev-batch-diff text-slate-400">0</span>
                            <button type="button" class="rev-remove-new-batch px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white text-[11px] font-bold" title="Hapus baris batch baru">&times;</button>
                        </div>
                    </td>
                `;

                tbody.appendChild(tr);
                tr.querySelector('.rev-new-name')?.focus();
                recalculateSimulation();
            }

            document.getElementById('btn_add_new_batch').addEventListener('click', appendNewBatchRow);

            document.getElementById('rev_batches_tbody').addEventListener('click', function(e) {
                const removeBtn = e.target.closest('.rev-remove-new-batch');
                if (removeBtn) {
                    removeBtn.closest('tr')?.remove();
                    if (document.querySelectorAll('#rev_batches_tbody tr').length === 0) {
                        document.getElementById('rev_batches_tbody').innerHTML =
                            '<tr id="rev_empty_batches_row"><td colspan="6" class="px-4 py-5 text-center text-slate-400">Belum ada batch terdaftar di lokasi ini. Klik <strong>+ Tambah Batch</strong> untuk menambahkan batch baru.</td></tr>';
                    }
                    recalculateSimulation();
                }
            });

            function recalculateSimulation() {
                if (!currentData) return;
                const rows = document.querySelectorAll('#rev_batches_tbody tr[data-batch-id], #rev_batches_tbody tr[data-is-new-batch="1"]');
                let newDbTotal = 0;

                rows.forEach(tr => {
                    const curr = Number(tr.dataset.currentQty) || 0;
                    const input = tr.querySelector('.rev-batch-input');
                    const val = Math.max(0, parseInt(input?.value || '0', 10) || 0);
                    newDbTotal += val;

                    const diff = val - curr;
                    const diffCell = tr.querySelector('.rev-batch-diff');
                    if (diffCell) {
                        diffCell.textContent = (diff > 0 ? '+' : '') + fmtNum(diff);
                        diffCell.className = 'px-4 py-3 text-right font-bold rev-batch-diff ' + (diff > 0 ? 'text-emerald-600' : (diff < 0 ? 'text-rose-600' : 'text-slate-400'));
                        if (tr.dataset.isNewBatch === '1') {
                            diffCell.className = 'font-bold rev-batch-diff ' + (diff > 0 ? 'text-emerald-600' : 'text-slate-400');
                        }
                    }
                });

                const ledgerBefore = Number(currentData.summary.ledger_balance) || 0;
                const ledgerDelta = newDbTotal - ledgerBefore;

                document.getElementById('sim_opening').textContent = fmtNum(currentData.summary.opening_stock);
                document.getElementById('sim_new_db').textContent = fmtNum(newDbTotal);
                document.getElementById('sim_ledger_delta').textContent = (ledgerDelta > 0 ? '+' : '') + fmtNum(ledgerDelta);
                document.getElementById('sim_new_ledger').textContent = fmtNum(newDbTotal);
            }

            document.getElementById('rev_batches_tbody').addEventListener('input', function(e) {
                if (e.target.classList.contains('rev-batch-input')) {
                    recalculateSimulation();
                }
            });

            document.getElementById('btn_sync_to_ledger').addEventListener('click', function() {
                if (!currentData) return;
                const targetTotal = Math.max(0, Number(currentData.summary.ledger_balance) || 0);
                const rows = Array.from(document.querySelectorAll('#rev_batches_tbody tr[data-batch-id], #rev_batches_tbody tr[data-is-new-batch="1"]'));
                if (rows.length === 0) return;

                rows.forEach((tr, idx) => {
                    const inp = tr.querySelector('.rev-batch-input');
                    if (inp) {
                        inp.value = idx === 0 ? targetTotal : 0;
                    }
                });
                recalculateSimulation();
            });

            document.getElementById('btn_submit_revision').addEventListener('click', function() {
                if (!currentData) return;
                const existingRows = Array.from(document.querySelectorAll('#rev_batches_tbody tr[data-batch-id]'));
                const newRows = Array.from(document.querySelectorAll('#rev_batches_tbody tr[data-is-new-batch="1"]'));
                if (existingRows.length === 0 && newRows.length === 0) {
                    iziToast.warning({ title: 'Perhatian', message: 'Tidak ada batch untuk direvisi atau ditambahkan.', position: 'topRight' });
                    return;
                }

                const batchesPayload = existingRows.map(tr => ({
                    batch_id: Number(tr.dataset.batchId),
                    new_qty: Math.max(0, parseInt(tr.querySelector('.rev-batch-input')?.value || '0', 10) || 0),
                }));

                const newBatchesPayload = [];
                for (const tr of newRows) {
                    const name = (tr.querySelector('.rev-new-name')?.value || '').trim();
                    if (!name) {
                        iziToast.warning({ title: 'Perhatian', message: 'Nama batch baru wajib diisi.', position: 'topRight' });
                        tr.querySelector('.rev-new-name')?.focus();
                        return;
                    }
                    const etalaseEl = tr.querySelector('.rev-new-etalase');
                    newBatchesPayload.push({
                        name,
                        expired_date: tr.querySelector('.rev-new-ed')?.value || null,
                        etalases_id: etalaseEl ? Number(etalaseEl.value) : null,
                        new_qty: Math.max(0, parseInt(tr.querySelector('.rev-batch-input')?.value || '0', 10) || 0),
                    });
                }

                const btn = this;
                btn.disabled = true;

                axios.post(routes.store, {
                    medicine_id: currentData.medicine.id,
                    target_mode: currentData.summary.target_mode,
                    reason: document.getElementById('rev_reason').value,
                    notes: document.getElementById('rev_notes').value,
                    batches: batchesPayload,
                    new_batches: newBatchesPayload,
                }, {
                    headers: { 'X-CSRF-TOKEN': csrfToken }
                }).then(res => {
                    iziToast.success({ title: 'Berhasil', message: res.data.message, position: 'topRight' });
                    document.getElementById('rev_notes').value = '';
                    inspectMedicine(currentData.medicine.id);
                    loadHistory();
                    if (!document.getElementById('anomalies_panel').classList.contains('hidden')) {
                        loadAnomalies();
                    }
                }).catch(err => {
                    iziToast.error({ title: 'Gagal', message: err.response?.data?.message || 'Gagal menyimpan revisi stok.', position: 'topRight' });
                }).finally(() => {
                    btn.disabled = false;
                });
            });

            function loadAnomalies() {
                const panel = document.getElementById('anomalies_panel');
                const content = document.getElementById('anomalies_content');
                panel.classList.remove('hidden');
                content.innerHTML = '<div class="p-4 text-xs text-slate-500">Memeriksa obat batch minus / selisih...</div>';

                axios.get(routes.anomalies, { params: { target_mode: targetMode } })
                    .then(res => {
                        const rows = res.data || [];
                        if (rows.length === 0) {
                            content.innerHTML = '<div class="p-4 text-xs text-emerald-700 font-semibold">Tidak ditemukan batch minus atau selisih saldo di lokasi aktif.</div>';
                            return;
                        }
                        content.innerHTML = `
                            <table class="min-w-full text-xs text-left">
                                <thead class="bg-rose-50 text-rose-800 uppercase text-[10px]">
                                    <tr>
                                        <th class="px-3 py-2">Obat</th>
                                        <th class="px-3 py-2 text-right">Awal</th>
                                        <th class="px-3 py-2 text-right">Saldo Kartu Stok</th>
                                        <th class="px-3 py-2 text-right">Stok DB</th>
                                        <th class="px-3 py-2 text-right">Selisih</th>
                                        <th class="px-3 py-2 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-rose-100">
                                    ${rows.map(r => `
                                        <tr>
                                            <td class="px-3 py-2 font-bold text-slate-800">
                                                ${escapeHtml(r.name)}
                                                ${r.has_negative_batch ? '<span class="ml-1 px-1.5 py-0.5 text-[10px] rounded bg-rose-100 text-rose-700">Batch Minus</span>' : ''}
                                            </td>
                                            <td class="px-3 py-2 text-right">${fmtNum(r.opening_stock)}</td>
                                            <td class="px-3 py-2 text-right font-bold text-purple-700">${fmtNum(r.ledger_balance)}</td>
                                            <td class="px-3 py-2 text-right font-bold ${r.db_stock < 0 ? 'text-rose-600' : ''}">${fmtNum(r.db_stock)}</td>
                                            <td class="px-3 py-2 text-right font-bold text-rose-600">${r.discrepancy > 0 ? '+' : ''}${fmtNum(r.discrepancy)}</td>
                                            <td class="px-3 py-2 text-center">
                                                <button type="button" data-med-id="${r.medicine_id}"
                                                    class="rev-pick-anomaly px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-[11px]">
                                                    Pilih
                                                </button>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        `;
                    })
                    .catch(() => {
                        content.innerHTML = '<div class="p-4 text-xs text-rose-600">Gagal memuat daftar anomali.</div>';
                    });
            }

            document.getElementById('btn_scan_anomalies').addEventListener('click', loadAnomalies);
            document.getElementById('btn_close_anomalies').addEventListener('click', () => {
                document.getElementById('anomalies_panel').classList.add('hidden');
            });
            document.getElementById('anomalies_content').addEventListener('click', function(e) {
                const btn = e.target.closest('.rev-pick-anomaly');
                if (btn) {
                    inspectMedicine(Number(btn.dataset.medId));
                }
            });

            function loadHistory() {
                const tbody = document.getElementById('rev_history_tbody');
                axios.get(routes.history, { params: { target_mode: targetMode } })
                    .then(res => {
                        const rows = res.data || [];
                        if (rows.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Belum ada riwayat revisi stok di lokasi ini.</td></tr>';
                            return;
                        }
                        tbody.innerHTML = rows.map(r => `
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-600">${escapeHtml(r.date)}</td>
                                <td class="px-4 py-3 whitespace-nowrap font-mono font-bold text-indigo-700">${escapeHtml(r.revision_code)}</td>
                                <td class="px-4 py-3 font-bold text-slate-800">${escapeHtml(r.medicine_name)}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <span class="text-slate-500">${fmtNum(r.db_stock_before)}</span> &rarr; <strong>${fmtNum(r.db_stock_after)}</strong>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <span class="text-slate-500">${fmtNum(r.ledger_balance_before)}</span> &rarr; <strong class="text-purple-700">${fmtNum(r.ledger_balance_after)}</strong>
                                    <span class="block text-[10px] font-bold ${r.ledger_delta >= 0 ? 'text-emerald-600' : 'text-rose-600'}">(${r.ledger_delta > 0 ? '+' : ''}${fmtNum(r.ledger_delta)})</span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    <div class="font-semibold">${escapeHtml(r.reason)}</div>
                                    ${r.notes ? `<div class="text-[11px] text-slate-400">${escapeHtml(r.notes)}</div>` : ''}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-600">${escapeHtml(r.user_name)}</td>
                            </tr>
                        `).join('');
                    })
                    .catch(() => {
                        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-6 text-center text-rose-500">Gagal memuat riwayat revisi.</td></tr>';
                    });
            }

            document.getElementById('btn_refresh_history').addEventListener('click', loadHistory);

            loadHistory();
            if (preselectedMedId) {
                inspectMedicine(preselectedMedId);
            }
        })();
    </script>
@endsection
