@extends('layouts.app')

@section('title', 'Rincian Penerimaan')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        .text-end {
            text-align: right !important;
        }

        #rincianItemsTable thead th {
            background-color: #f8fafc !important;
            font-weight: 600 !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e5e7eb !important;
            padding: 12px 14px !important;
            color: #475569 !important;
        }

        #rincianItemsTable tbody td {
            padding: 12px 14px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }

        #rincianItemsTable tbody tr:hover {
            background-color: #f8fafc !important;
        }

        .select2-container .select2-selection--single {
            height: 42px !important;
            border-radius: 12px !important;
            border: 1px solid #d1d5db !important;
            display: flex !important;
            align-items: center !important;
            padding-left: 8px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
            right: 8px !important;
        }

        .select2-dropdown {
            border-radius: 12px !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
            border: 1px solid #e2e8f0 !important;
            overflow: hidden !important;
            font-size: 12px !important;
        }
    </style>
@endsection

@section('content')
    <section class="section py-4 px-[18px] bg-gray-50 min-h-screen pb-28 font-nunito">
        <div class="mx-auto space-y-6">

            <!-- HEADER HALAMAN -->
            <div
                class="flex flex-wrap items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold uppercase font-poppins text-gray-800">Rincian Penerimaan</h1>

                        @if ($order)
                            @if ($order->status == 3)
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                    Diterima (Selesai)
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-800 border border-blue-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mr-1.5"></span>
                                    Draft Penerimaan
                                </span>
                            @endif
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 mt-1.5">
                        Menampilkan faktur, PBF, dan rincian item barang yang telah di <strong>Simpan Draft</strong> atau
                        <strong>Selesaikan Pesanan</strong>.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($order)
                        <a href="{{ route('orders.comparison', $order->id) }}"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-50 border border-indigo-200 px-3.5 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M16 3l0 18" />
                                <path d="M18 18l-3 3l-3 -3" />
                                <path d="M8 21l0 -18" />
                                <path d="M10 6l-3 -3l-3 3" />
                            </svg>
                            Bandingkan
                        </a>

                        <a href="{{ url('/receiving/print/' . $order->id) }}" target="_blank"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-purple-50 border border-purple-200 px-3.5 py-2 text-xs font-semibold text-purple-700 hover:bg-purple-100 transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Cetak Penerimaan
                        </a>

                        @if ($order->status == 3)
                            <a href="{{ url('/receiving/' . $order->id . '/printorders') }}" target="_blank"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-slate-50 border border-slate-200 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.25-2.142V8.25" />
                                </svg>
                                Cetak Invoice
                            </a>
                        @endif
                    @endif

                    <a href="{{ route('receiving.index') }}"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 shadow-xs transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Daftar Pesanan
                    </a>
                </div>
            </div>

            @if (!$order)
                <div class="bg-white p-12 rounded-2xl shadow-sm border border-gray-100 text-center space-y-3">
                    <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mx-auto">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-gray-800">Belum Ada Pesanan yang Tersedia</h3>
                    <p class="text-xs text-gray-500 max-w-md mx-auto">
                        Belum ada pesanan yang tersimpan sebagai draft penerimaan atau diselesaikan. Silakan buat atau
                        proses penerimaan barang terlebih dahulu.
                    </p>
                    <div class="pt-2">
                        <a href="{{ route('receiving.index') }}"
                            class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700">
                            Ke Daftar Pesanan
                        </a>
                    </div>
                </div>
            @else
                <!-- BLOK 1: INFORMASI FAKTUR & SUPPLIER (READ-ONLY) -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                            <span
                                class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-100 text-blue-600 text-xs font-bold">1</span>
                            Informasi Faktur & Supplier
                        </h2>
                        <span class="text-xs text-gray-400">* Mode Rincian (Read-Only) - Pilih kreditur dan faktur untuk
                            menyaring tampilan item</span>
                    </div>

                    <!-- Grid Form Informasi Utama -->
                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">

                        <!-- Cari Kreditur / PBF -->
                        <div class="lg:col-span-2">
                            <label for="creditor" class="block text-xs font-semibold text-gray-700 mb-1">
                                Cari Kreditur
                            </label>
                            <select id="creditor" name="creditor_id"
                                class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                                <option value="">---- Semua Kreditur / PBF ----</option>
                                @foreach ($creditorOption as $creditor)
                                    <option value="{{ $creditor->code }}">{{ $creditor->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Tanggal Terima (Readonly) -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Tanggal Terima</label>
                            <input type="text" id="returdate" readonly
                                value="{{ Carbon\Carbon::parse($order->created_at)->format('d/m/Y') }}"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-xs text-gray-700 cursor-not-allowed">
                        </div>

                        <!-- Nomor Terima (Readonly) -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Nomor Terima</label>
                            <input type="text" id="returnumber" readonly value="-"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-xs text-blue-700 font-mono font-semibold cursor-not-allowed">
                        </div>

                        <!-- Nomor BPBA (Dropdown untuk ganti pesanan draft/selesai) -->
                        <div>
                            <label for="bpbaSelect" class="block text-xs font-semibold text-gray-700 mb-1">
                                Cari Nomor BPBA
                            </label>
                            <select id="bpbaSelect"
                                class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                                @foreach ($availableOrders as $avail)
                                    <option value="{{ $avail->id }}" {{ $avail->id == $order->id ? 'selected' : '' }}>
                                        {{ $avail->code }} ({{ $avail->status == 3 ? 'Diterima' : 'Draft' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Nomor Faktur (Readonly) -->
                        <div>
                            <label for="invoice_number" class="block text-xs font-semibold text-gray-500 mb-1">Nomor
                                Faktur</label>
                            <input id="invoice_number" readonly value="-"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-xs text-gray-800 font-medium cursor-not-allowed">
                        </div>

                        <!-- Tanggal Faktur (Readonly) -->
                        <div>
                            <label for="invoice_date" class="block text-xs font-semibold text-gray-500 mb-1">Tanggal
                                Faktur</label>
                            <input id="invoice_date" readonly value="-"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-xs text-gray-700 cursor-not-allowed">
                        </div>

                        <!-- Jenis Bayar (Readonly) -->
                        <div>
                            <label for="invoice_payment" class="block text-xs font-semibold text-gray-500 mb-1">Jenis
                                Bayar</label>
                            <input id="invoice_payment" readonly value="-"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-xs text-gray-800 font-medium cursor-not-allowed">
                        </div>

                        <!-- Waktu Kredit / Tempo (Readonly) -->
                        <div>
                            <label for="invoice_times" class="block text-xs font-semibold text-gray-500 mb-1">Tempo
                                (Hari)</label>
                            <input id="invoice_times" readonly value="0"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-xs text-gray-700 cursor-not-allowed">
                        </div>

                        <!-- Jatuh Tempo (Readonly) -->
                        <div>
                            <label for="invoice_due" class="block text-xs font-semibold text-gray-500 mb-1">Tgl Jatuh
                                Tempo</label>
                            <input id="invoice_due" readonly value="-"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-xs text-gray-700 cursor-not-allowed">
                        </div>

                        <!-- Jenis PPN (Readonly) -->
                        <div>
                            <label for="invoice_ppn" class="block text-xs font-semibold text-gray-500 mb-1">Jenis
                                PPN</label>
                            <input id="invoice_ppn" readonly value="-"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-xs text-gray-800 font-semibold cursor-not-allowed">
                        </div>

                        <!-- Pilih Faktur -->
                        <div>
                            <label for="print_faktur" class="block text-xs font-semibold text-gray-700 mb-1">Pilih
                                Faktur</label>
                            <select id="print_faktur" name="print_faktur"
                                class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                                <option value="">-- Semua Faktur --</option>
                                @foreach ($allFakturs as $detail)
                                    <option value="{{ $detail->id }}" data-creditor="{{ $detail->creditor_code }}"
                                        data-code="{{ $detail->receiving_details_code }}"
                                        data-invoice="{{ $detail->invoice_number }}">
                                        Faktur: {{ $detail->invoice_number ?: $detail->receiving_details_code }}
                                        ({{ $detail->receiving_details_code ?: $detail->sp_code ?? 'Draft' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Cetak Penerimaan Faktur Ini -->
                        <div class="flex items-end">
                            <button type="button" onclick="printReceivingSelectedFaktur()" id="btnPrintReceiving"
                                class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-[12px] font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Cetak Penerimaan Faktur Ini
                            </button>
                        </div>

                    </div>
                </div>

                <!-- BLOK 2: ITEM YANG DITERIMA (READ-ONLY) -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-4">

                    <!-- Baris Judul & Pencarian Obat -->
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-4">
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-gray-800">
                                Item Yang Diterima
                            </h2>
                            <span id="itemsCountBadge"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                                {{ count($itemsData) }} Item
                            </span>
                        </div>

                        <!-- Input Pencarian Nama / Kode Obat -->
                        <div class="relative w-full sm:w-72">
                            <div
                                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input type="text" id="searchMedicineInput" placeholder="Cari nama atau kode obat..."
                                oninput="filterItemsTable()" autocomplete="off"
                                class="w-full pl-9 pr-3.5 py-2 text-xs rounded-xl border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all placeholder:text-gray-400">
                        </div>
                    </div>

                    <!-- Tabel Item -->
                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table id="rincianItemsTable" class="w-full text-left text-xs text-gray-700">
                            <thead>
                                <tr>
                                    <th>Nama Obat</th>
                                    <th class="text-center">QTY Beli</th>
                                    <th class="text-center">QTY Diterima</th>
                                    <th class="text-center">Batch & ED</th>
                                    <th class="text-right">HNA</th>
                                    <th class="text-right">Harga PPN</th>
                                    <th class="text-center">Diskon</th>
                                    <th class="text-center">Extra Diskon</th>
                                    <th class="text-center">Diskon PBF</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody id="rincianTableBody" class="divide-y divide-gray-100 bg-white text-[12px]">
                                <!-- Rendered via JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <!-- SUMMARY KEUANGAN RINCIAN -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-4 p-4 bg-slate-50 border border-slate-200 rounded-2xl">
                        <div class="text-xs text-slate-500">
                            * Data total dihitung otomatis berdasarkan item yang tampil sesuai filter kreditur & faktur.
                        </div>
                        <div class="flex flex-wrap items-center gap-6">
                            <div class="text-right">
                                <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total HNA
                                    (DPP)</p>
                                <p id="summaryTotalHna" class="text-sm font-bold text-slate-800">Rp 0</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total PPN</p>
                                <p id="summaryTotalPpn" class="text-sm font-bold text-slate-800">Rp 0</p>
                            </div>
                            <div class="text-right pl-3 border-l border-slate-300">
                                <p class="text-[11px] font-semibold text-blue-600 uppercase tracking-wider">Total Akhir</p>
                                <p id="summaryTotalAkhir" class="text-base font-extrabold text-blue-700">Rp 0</p>
                            </div>
                        </div>
                    </div>

                </div>
            @endif

        </div>
    </section>
@endsection

@section('scripts')
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script>
        const allItemsData = @json($itemsData ?? []);
        const allFaktursData = @json($faktursData ?? []);
        const currentOrderId = {{ $order->id ?? 'null' }};

        document.addEventListener('DOMContentLoaded', function() {
            // Inisialisasi Select2
            $('#bpbaSelect').select2({
                placeholder: 'Pilih Nomor BPBA...',
                width: '100%'
            }).on('select2:select change', function() {
                const targetOrderId = $(this).val();
                if (targetOrderId && String(targetOrderId) !== String(currentOrderId)) {
                    window.location.href = `/orders/${targetOrderId}/rincian`;
                }
            });

            $('#creditor').select2({
                placeholder: 'Semua Kreditur / PBF...',
                allowClear: true,
                width: '100%'
            }).on('select2:select change', function() {
                onCreditorChanged();
            });

            $('#print_faktur').select2({
                placeholder: 'Semua Faktur...',
                allowClear: true,
                width: '100%'
            }).on('select2:select change', function() {
                onFakturChanged();
            });

            // Auto-select initial state
            if ($('#creditor option').length === 2) {
                // Hanya ada 1 kreditur selain option "Semua", auto select
                $('#creditor').val($('#creditor option').eq(1).val()).trigger('change');
            } else {
                updateFakturDropdown();
                updateInvoiceFields();
                renderTable();
            }
        });

        function onCreditorChanged() {
            updateFakturDropdown();
            renderTable();
        }

        function onFakturChanged() {
            updateInvoiceFields();
            renderTable();
        }

        function updateFakturDropdown() {
            const selectedCreditor = $('#creditor').val();
            const $fakturSelect = $('#print_faktur');
            const currentSelected = $fakturSelect.val();

            $fakturSelect.empty();
            $fakturSelect.append(new Option('-- Semua Faktur --', ''));

            let filteredFakturs = allFaktursData;
            if (selectedCreditor) {
                filteredFakturs = allFaktursData.filter(f => f.creditor_code === selectedCreditor);
            }

            filteredFakturs.forEach(f => {
                const label =
                    `Faktur: ${f.invoice_number !== '-' ? f.invoice_number : f.receiving_details_code} (${f.receiving_details_code !== '-' ? f.receiving_details_code : f.sp_code})`;
                $fakturSelect.append(new Option(label, f.id));
            });

            if (filteredFakturs.length >= 1) {
                if (!currentSelected || !filteredFakturs.some(f => String(f.id) === String(currentSelected))) {
                    $fakturSelect.val(filteredFakturs[0].id);
                } else {
                    $fakturSelect.val(currentSelected);
                }
            } else {
                $fakturSelect.val('');
            }

            $fakturSelect.trigger('change.select2');
            updateInvoiceFields();
        }

        function updateInvoiceFields() {
            const selectedFakturId = $('#print_faktur').val();
            const selectedCreditorCode = $('#creditor').val();

            let targetFaktur = null;
            if (selectedFakturId) {
                targetFaktur = allFaktursData.find(f => String(f.id) === String(selectedFakturId));
            } else if (selectedCreditorCode) {
                targetFaktur = allFaktursData.find(f => f.creditor_code === selectedCreditorCode);
            } else if (allFaktursData.length > 0) {
                targetFaktur = allFaktursData[0];
            }

            if (targetFaktur) {
                $('#returnumber').val(targetFaktur.receiving_details_code || '-');
                $('#returdate').val(targetFaktur.receiving_date || '-');
                $('#invoice_number').val(targetFaktur.invoice_number || '-');
                $('#invoice_date').val(formatDate(targetFaktur.invoice_date));
                $('#invoice_payment').val(targetFaktur.invoice_payment || '-');
                $('#invoice_times').val(targetFaktur.invoice_times || '0');
                $('#invoice_due').val(formatDate(targetFaktur.invoice_due));
                $('#invoice_ppn').val(targetFaktur.invoice_ppn || 'TANPA');
            } else {
                $('#returnumber').val('-');
                $('#invoice_number').val('-');
                $('#invoice_date').val('-');
                $('#invoice_payment').val('-');
                $('#invoice_times').val('0');
                $('#invoice_due').val('-');
                $('#invoice_ppn').val('-');
            }
        }

        function formatDate(val) {
            if (!val || val === '-') return '-';
            if (val.includes('/')) return val;
            const parts = val.split('-');
            if (parts.length === 3) {
                return `${parts[2]}/${parts[1]}/${parts[0]}`;
            }
            return val;
        }

        function formatRupiah(number) {
            return 'Rp ' + Number(number || 0).toLocaleString('id-ID');
        }

        function filterItemsTable() {
            renderTable();
        }

        function renderTable() {
            const selectedCreditor = $('#creditor').val();
            const selectedFakturId = $('#print_faktur').val();
            const searchKeyword = ($('#searchMedicineInput').val() || '').toLowerCase().trim();

            const tbody = document.getElementById('rincianTableBody');
            tbody.innerHTML = '';

            let filtered = allItemsData;

            if (selectedCreditor) {
                filtered = filtered.filter(item => item.creditor_code === selectedCreditor);
            }

            if (selectedFakturId) {
                filtered = filtered.filter(item => String(item.receiving_details_id) === String(selectedFakturId));
            }

            if (searchKeyword) {
                filtered = filtered.filter(item =>
                    (item.medicine_name && item.medicine_name.toLowerCase().includes(searchKeyword)) ||
                    (item.medicine_code && item.medicine_code.toLowerCase().includes(searchKeyword))
                );
            }

            document.getElementById('itemsCountBadge').innerText = `${filtered.length} Item`;

            let sumHna = 0;
            let sumPpn = 0;
            let sumTotal = 0;

            if (filtered.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="11" class="text-center py-8 text-gray-400 font-medium italic">
                            Tidak ada item obat yang cocok dengan filter / kriteria saat ini.
                        </td>
                    </tr>
                `;
            } else {
                filtered.forEach(item => {
                    const hnaVal = Number(item.hna || 0);
                    const ppnVal = Number(item.harga_ppn || 0);
                    const totalVal = Number(item.total || 0);

                    sumHna += (hnaVal * Number(item.quantity_received || item.quantity_ordered || 0));
                    sumTotal += totalVal;

                    const batchEdHtml = item.batch !== '-' || item.expired_date !== '-' ?
                        `<div class="flex flex-col items-center gap-0.5">
                             <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-700 border border-slate-200">${item.batch}</span>
                             <span class="text-[10px] text-slate-400">ED: ${item.expired_date}</span>
                           </div>` :
                        `<span class="text-slate-400">-</span>`;

                    const rowHtml = `
                        <tr>
                            <td class="font-medium text-gray-900">
                                <div class="font-semibold text-slate-800">${item.medicine_name}</div>
                                <div class="text-[10px] font-mono text-slate-400">${item.medicine_code || ''}</div>
                            </td>
                            <td class="text-center font-semibold text-slate-700">${item.quantity_ordered}</td>
                            <td class="text-center font-bold text-emerald-700 bg-emerald-50/40">${item.quantity_received}</td>
                            <td class="text-center">${batchEdHtml}</td>
                            <td class="text-right font-medium text-slate-700">${formatRupiah(item.hna)}</td>
                            <td class="text-right font-semibold text-slate-900">${formatRupiah(item.harga_ppn)}</td>
                            <td class="text-center text-slate-600">${item.discount || 0}%</td>
                            <td class="text-center text-slate-600">${item.extra_discount || 0}%</td>
                            <td class="text-center text-slate-600">${item.pbf_discount || '0%'}</td>
                            <td class="text-right font-bold text-slate-900">${formatRupiah(item.total)}</td>
                            <td class="text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border ${item.status_class}">
                                    ${item.status}
                                </span>
                            </td>
                        </tr>
                    `;
                    tbody.insertAdjacentHTML('beforeend', rowHtml);
                });
            }

            sumPpn = Math.max(0, sumTotal - sumHna);

            document.getElementById('summaryTotalHna').innerText = formatRupiah(sumHna);
            document.getElementById('summaryTotalPpn').innerText = formatRupiah(sumPpn);
            document.getElementById('summaryTotalAkhir').innerText = formatRupiah(sumTotal);
        }

        function printReceivingSelectedFaktur() {
            if (!currentOrderId) {
                iziToast.warning({
                    title: 'Peringatan',
                    message: 'Data pesanan belum tersedia!',
                    position: 'topRight'
                });
                return;
            }

            const selectedFaktur = $('#print_faktur').val();
            if (selectedFaktur) {
                window.open(`/receiving/print/${currentOrderId}?faktur=${selectedFaktur}`, "_blank");
            } else {
                window.open(`/receiving/print/${currentOrderId}`, "_blank");
            }
        }
    </script>
@endsection
