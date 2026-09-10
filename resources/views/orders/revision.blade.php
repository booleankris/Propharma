@extends('layouts.app')

@section('title', 'Revisi Faktur')

@section('content')
    <script>
        // Early definition so clicks during page streaming/loading never throw ReferenceError
        ['openAddModal', 'closeAddModal', 'openUnifiedMergeModal', 'closeUnifiedMergeModal', 'openMoveItemModal', 'openMergeModal', 'editRow', 'deleteEmptyDetails'].forEach(fn => {
            if (typeof window[fn] === 'undefined') {
                window[fn] = function(...args) {
                    const check = () => {
                        if (typeof window['_' + fn] === 'function') {
                            window['_' + fn](...args);
                        } else {
                            setTimeout(check, 50);
                        }
                    };
                    check();
                };
            }
        });
    </script>
    <section class="section py-4 px-[18px] bg-gray-50 min-h-screen">
        <div class="mx-auto space-y-6">

            <!-- Header Card -->
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl font-bold uppercase font-poppins text-gray-800">Revisi Faktur</h1>
                        @if ($order->is_consolidation)
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                BPBA Konsolidasi
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        Pesanan: <span class="font-semibold text-gray-700">{{ $order->code }}</span>
                        @if ($order->order_items->first() && $order->order_items->first()->creditors)
                            &bull; PBF: <span
                                class="font-semibold text-gray-700">{{ $order->order_items->first()->creditors->name }}</span>
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($allReceivingDetails->count() > 1 || (isset($orphanedItems) && $orphanedItems->isNotEmpty()))
                        <button type="button" onclick="openUnifiedMergeModal()"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold flex items-center gap-1.5 shadow-xs transition-all cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                            <span>Gabung / Pindah Item</span>
                        </button>
                    @endif
                    <a href="{{ $order->status == 3 ? route('receiving.index') : route('receiving.receive', $order->id) }}"
                        class="px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-xs font-semibold text-gray-700 flex items-center gap-1.5 transition-all">
                        <span>&larr;</span> Kembali ke Penerimaan
                    </a>
                </div>
            </div>

            <!-- Main Table Section -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-gray-800">Daftar Penerimaan & Rincian Faktur</h2>
                        <p class="text-xs text-gray-500">Kelola item obat, tambahkan obat ke nomor terima tertentu, atau
                            gabungkan nomor terima yang terpisah.</p>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200">
                    <table id="revisiTable" class="w-full text-left text-xs text-gray-700">
                        <thead
                            class="bg-gray-50 text-[11px] uppercase font-semibold text-gray-600 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3">Nama Obat</th>
                                <th class="px-3 py-3 text-center">Batch</th>
                                <th class="px-3 py-3 text-center">Exp Date</th>
                                <th class="px-3 py-3 text-center">QTY Diterima</th>
                                <th class="px-4 py-3 text-right">Raw Price</th>
                                <th class="px-3 py-3 text-center">Diskon</th>
                                <th class="px-3 py-3 text-center">Extra Diskon</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-3 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($allReceivingDetails as $rd)
                                @php
                                    $itemsCount = $rd->receiving_items->count();
                                @endphp
                                <!-- Group Header for this Nomor Terima -->
                                <tr class="bg-blue-50/70 border-t border-b border-blue-100">
                                    <td colspan="6" class="px-4 py-2.5">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-xs font-bold text-blue-900 font-mono tracking-wide">
                                                Nomor Terima: {{ $rd->receiving_details_code }}
                                            </span>
                                            @if ($rd->invoice_number)
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-blue-100 text-blue-800">
                                                    No Faktur: {{ $rd->invoice_number }}
                                                </span>
                                            @endif
                                            @if ($rd->invoice_date)
                                                <span class="text-[11px] text-gray-500">
                                                    (Tgl: {{ $rd->invoice_date }})
                                                </span>
                                            @endif
                                            <span class="text-[11px] text-gray-500">
                                                &bull; {{ $itemsCount }} Item
                                            </span>
                                        </div>
                                    </td>
                                    <td colspan="3" class="px-4 py-2.5 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <!-- Tambah Obat button -->
                                            <button type="button"
                                                data-details-id="{{ $rd->id }}"
                                                data-details-code="{{ $rd->receiving_details_code }}"
                                                onclick="openAddModal(this.dataset.detailsId, this.dataset.detailsCode)"
                                                class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-semibold rounded-lg shadow-xs transition-all flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 4v16m8-8H4" />
                                                </svg>
                                                Tambah Obat
                                            </button>

                                            <!-- Gabungkan ke... (if multiple receiving details exist) -->
                                            @if ($allReceivingDetails->count() > 1)
                                                <button type="button"
                                                    onclick="openUnifiedMergeModal(null, {{ $rd->id }})"
                                                    class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-semibold rounded-lg shadow-xs transition-all flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                                    </svg>
                                                    Gabungkan
                                                </button>
                                            @endif

                                            <!-- Hapus Nomor Terima Kosong (if 0 items) -->
                                            @if ($itemsCount === 0)
                                                <button type="button"
                                                    onclick="deleteEmptyDetails({{ $rd->id }}, '{{ $rd->receiving_details_code }}')"
                                                    class="px-2.5 py-1 bg-red-600 hover:bg-red-700 text-white text-[11px] font-semibold rounded-lg shadow-xs transition-all flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    Hapus NT Kosong
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>

                                @if ($itemsCount === 0)
                                    <tr class="bg-gray-50/60">
                                        <td colspan="9" class="px-4 py-5 text-center text-xs text-gray-500 italic">
                                            Belum ada obat di Nomor Terima ini. Klik tombol <button type="button"
                                                data-details-id="{{ $rd->id }}"
                                                data-details-code="{{ $rd->receiving_details_code }}"
                                                onclick="openAddModal(this.dataset.detailsId, this.dataset.detailsCode)"
                                                class="font-semibold text-emerald-600 hover:underline">+ Tambah
                                                Obat</button> untuk memasukkan item.
                                        </td>
                                    </tr>
                                @else
                                    @foreach ($rd->receiving_items as $ri)
                                        @php
                                            $ri->medicine_name = $ri->order_items->medicines->name ?? '-';
                                        @endphp
                                        <tr data-item='@json($ri)'>
                                            <td class="px-4 py-3 font-medium text-gray-800">{{ $ri->medicine_name }}</td>
                                            <td class="px-3 py-3 text-center font-mono">{{ $ri->batch ?? '-' }}</td>
                                            <td class="px-3 py-3 text-center">{{ $ri->expired_date ?? '-' }}</td>
                                            <td class="px-3 py-3 text-center font-semibold">{{ $ri->qty_received }}</td>
                                            <td class="px-4 py-3 text-right">Rp
                                                {{ number_format($ri->raw_price, 0, ',', '.') }}</td>
                                            <td class="px-3 py-3 text-center">Rp
                                                {{ number_format($ri->discount, 0, ',', '.') }}</td>
                                            <td class="px-3 py-3 text-center">Rp
                                                {{ number_format($ri->extra_discount, 0, ',', '.') }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-gray-900">Rp
                                                {{ number_format($ri->total, 0, ',', '.') }}</td>
                                            <td class="px-3 py-3 text-center whitespace-nowrap">
                                                <button onclick="editRow(this)"
                                                    class="text-blue-600 hover:text-blue-800 font-semibold text-xs">Edit</button>

                                                @if ($allReceivingDetails->count() > 1)
                                                    <button
                                                        onclick="openMoveItemModal({{ $ri->id }}, '{{ addslashes($ri->medicine_name) }}', {{ $rd->id }})"
                                                        class="text-indigo-600 hover:text-indigo-800 font-semibold text-xs ml-2">Pindah</button>
                                                @endif

                                                <button onclick="deleteRow({{ $ri->id }})"
                                                    class="text-red-600 hover:text-red-800 font-semibold text-xs ml-2">Hapus</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-xs text-gray-500 italic">
                                        Tidak ada data penerimaan untuk pesanan ini.
                                    </td>
                                </tr>
                            @endforelse

                            <!-- Orphaned items if any -->
                            @if (isset($orphanedItems) && $orphanedItems->isNotEmpty())
                                <tr class="bg-amber-50 border-t border-b border-amber-200">
                                    <td colspan="9" class="px-4 py-2.5 text-xs font-bold text-amber-800">
                                        Item Tanpa Nomor Terima (Belum Terkait)
                                    </td>
                                </tr>
                                @foreach ($orphanedItems as $ri)
                                    @php
                                        $ri->medicine_name = $ri->order_items->medicines->name ?? '-';
                                    @endphp
                                    <tr data-item='@json($ri)'>
                                        <td class="px-4 py-3 font-medium text-gray-800">{{ $ri->medicine_name }}</td>
                                        <td class="px-3 py-3 text-center font-mono">{{ $ri->batch ?? '-' }}</td>
                                        <td class="px-3 py-3 text-center">{{ $ri->expired_date ?? '-' }}</td>
                                        <td class="px-3 py-3 text-center font-semibold">{{ $ri->qty_received }}</td>
                                        <td class="px-4 py-3 text-right">Rp
                                            {{ number_format($ri->raw_price, 0, ',', '.') }}</td>
                                        <td class="px-3 py-3 text-center">Rp
                                            {{ number_format($ri->discount, 0, ',', '.') }}</td>
                                        <td class="px-3 py-3 text-center">Rp
                                            {{ number_format($ri->extra_discount, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900">Rp
                                            {{ number_format($ri->total, 0, ',', '.') }}</td>
                                        <td class="px-3 py-3 text-center whitespace-nowrap">
                                            <button onclick="editRow(this)"
                                                class="text-blue-600 hover:text-blue-800 font-semibold text-xs">Edit</button>
                                            @if ($allReceivingDetails->isNotEmpty())
                                                <button
                                                    onclick="openMoveItemModal({{ $ri->id }}, '{{ addslashes($ri->medicine_name) }}', null)"
                                                    class="text-indigo-600 hover:text-indigo-800 font-semibold text-xs ml-2">Tautkan
                                                    ke NT</button>
                                            @endif
                                            <button onclick="deleteRow({{ $ri->id }})"
                                                class="text-red-600 hover:text-red-800 font-semibold text-xs ml-2">Hapus</button>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-2">
                    <a href="{{ $order->status == 3 ? route('receiving.index') : route('receiving.receive', $order->id) }}"
                        class="px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-xs font-semibold text-gray-700 flex items-center gap-1.5 cursor-pointer transition-all">
                        <span>&larr;</span> Selesai & Kembali ke Penerimaan
                    </a>
                </div>
            </div>

            <!-- ==================== MODAL TAMBAH OBAT ==================== -->
            <div id="addModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl p-6 w-full max-w-2xl space-y-4 max-h-[90vh] overflow-y-auto shadow-2xl">
                    <div class="flex items-center justify-between border-b pb-3">
                        <div>
                            <h2 class="text-lg font-bold text-gray-800">Tambah Obat ke Penerimaan</h2>
                            <p class="text-xs text-gray-500">Target Nomor Terima: <span id="add_target_code"
                                    class="font-bold text-blue-600 font-mono">-</span></p>
                        </div>
                        <button type="button" onclick="closeAddModal()"
                            class="text-gray-400 hover:text-gray-600 text-lg font-bold">&times;</button>
                    </div>

                    <input type="hidden" id="add_receiving_details_id">

                    <!-- Mode Toggle -->
                    <div class="flex rounded-xl bg-gray-100 p-1 text-xs font-semibold">
                        <button type="button" id="tab_mode_bpba" onclick="switchAddMode('bpba')"
                            class="flex-1 py-1.5 rounded-lg bg-white shadow-xs text-blue-700 transition-all">
                            Dari Pesanan / BPBA Ini
                        </button>
                    </div>

                    <!-- Medicine Selection: BPBA Mode -->
                    <div id="section_bpba_select" class="space-y-1">
                        <label class="text-xs font-semibold text-gray-700">Pilih Obat dari BPBA <span
                                class="text-red-500">*</span></label>
                        <select id="add_order_items_id" onchange="onSelectOrderItem()"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="">-- Pilih Obat dari Faktur Ini --</option>

                        </select>
                    </div>

                    <!-- Medicine Selection: Master Mode -->
                    <div id="section_master_select" class="space-y-1 hidden">
                        <label class="text-xs font-semibold text-gray-700">Pilih Obat dari Master <span
                                class="text-red-500">*</span></label>
                        <select id="add_medicine_id" onchange="onSelectMasterMedicine()"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="">-- Pilih Obat dari Master Obat --</option>
                            @foreach ($allMedicines as $m)
                                <option value="{{ $m->id }}" data-price="{{ $m->raw_price ?? 0 }}">
                                    {{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-700">No. Batch <span
                                    class="text-red-500">*</span></label>
                            <input id="add_batch" placeholder="Contoh: B12345"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Exp Date <span
                                    class="text-red-500">*</span></label>
                            <input id="add_expired_date" type="date"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">QTY Diterima <span
                                    class="text-red-500">*</span></label>
                            <input id="add_qty_received" type="number" min="0.01" step="any" placeholder="0"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">HNA / Raw Price <span
                                    class="text-red-500">*</span></label>
                            <input id="add_raw_price" type="text" placeholder="Rp 0"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Diskon (Rp)</label>
                            <input id="add_discount" type="text" placeholder="Rp 0"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Extra Diskon (Rp)</label>
                            <input id="add_extra_discount" type="text" placeholder="Rp 0"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Status Barang</label>
                            <input id="add_status" value="1"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Total</label>
                            <input id="add_total" type="text" readonly
                                class="w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-xs font-bold text-gray-800">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <button type="button" onclick="closeAddModal()"
                            class="px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-xs font-semibold text-gray-700">Batal</button>
                        <button type="button" onclick="submitAddMedicine()"
                            class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                            Simpan ke Nomor Terima
                        </button>
                    </div>
                </div>
            </div>

            <!-- ==================== MODAL EDIT ITEM ==================== -->
            <div id="editModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl p-6 w-full max-w-2xl space-y-4 max-h-[90vh] overflow-y-auto shadow-2xl">
                    <div class="flex items-center justify-between border-b pb-3">
                        <h2 class="text-lg font-bold text-gray-800">Edit Item Penerimaan</h2>
                        <button type="button" onclick="closeModal()"
                            class="text-gray-400 hover:text-gray-600 text-lg font-bold">&times;</button>
                    </div>

                    <input type="hidden" id="edit_id">

                    <!-- Pindah Nomor Terima Dropdown in Edit -->
                    <div>
                        <label class="text-xs font-semibold text-gray-700">Nomor Terima</label>
                        <select id="edit_receiving_details_id"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            @foreach ($allReceivingDetails as $rd)
                                <option value="{{ $rd->id }}">
                                    {{ $rd->receiving_details_code }}
                                    {{ $rd->invoice_number ? "({$rd->invoice_number})" : '' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-400 mt-0.5">Ubah pilihan di atas jika ingin memindahkan item ini ke
                            nomor terima lain.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Batch</label>
                            <input id="edit_batch"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Exp Date</label>
                            <input id="edit_expired_date" type="date"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">QTY Diterima</label>
                            <input id="edit_qty_received" type="number" min="0.01" step="any"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">HNA</label>
                            <input id="edit_raw_price" type="text"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Diskon (Rp)</label>
                            <input id="edit_discount" type="text"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Extra Diskon (Rp)</label>
                            <input id="edit_extra_discount" type="text"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Status Barang</label>
                            <input id="edit_status"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Total</label>
                            <input id="edit_total" type="text" readonly
                                class="w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-xs font-bold text-gray-800">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <button type="button" onclick="closeModal()"
                            class="px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-xs font-semibold text-gray-700">Batal</button>
                        <button type="button" onclick="submitEdit()"
                            class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-xs">Simpan
                            Revisi</button>
                    </div>
                </div>
            </div>

            <!-- ==================== MODAL GABUNG / PINDAH ITEM (UNIFIED) ==================== -->
            <div id="unifiedMergeModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl p-6 w-full max-w-2xl space-y-4 shadow-2xl max-h-[90vh] flex flex-col">
                    <div class="flex items-center justify-between border-b pb-3 shrink-0">
                        <div class="flex items-center gap-2">
                            <div class="p-2 bg-indigo-50 text-indigo-600 rounded-xl">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-gray-800">Gabung / Pindahkan Item</h2>
                                <p class="text-xs text-gray-500">Pilih nomor terima tujuan dan tentukan obat-obat yang
                                    ingin dipindahkan</p>
                            </div>
                        </div>
                        <button type="button" onclick="closeUnifiedMergeModal()"
                            class="text-gray-400 hover:text-gray-600 text-lg font-bold">&times;</button>
                    </div>

                    <div class="space-y-4 overflow-y-auto pr-1 flex-1">
                        <!-- Step 1: Target Destination -->
                        <div class="p-4 bg-gray-50 rounded-xl border border-gray-200/80 space-y-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                                1. Pilih Nomor Terima Tujuan <span class="text-red-500">*</span>
                            </label>
                            <select id="unified_target_id" onchange="onTargetDetailsChange()"
                                class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs font-semibold text-gray-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none cursor-pointer">
                                <!-- Populated dynamically -->
                            </select>
                            <p class="text-[11px] text-gray-500">
                                Item-item yang Anda centang di bawah ini akan dipindahkan dan digabungkan ke nomor terima
                                ini.
                            </p>
                        </div>

                        <!-- Step 2: Items to move -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    2. Pilih Item yang Ingin Dipindahkan <span class="text-red-500">*</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick="toggleSelectAllItems(true)"
                                        class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">
                                        Pilih Semua
                                    </button>
                                    <span class="text-gray-300">&bull;</span>
                                    <button type="button" onclick="toggleSelectAllItems(false)"
                                        class="text-[11px] font-semibold text-gray-500 hover:text-gray-700 hover:underline">
                                        Hapus Pilihan
                                    </button>
                                </div>
                            </div>

                            <div class="border border-gray-200 rounded-xl overflow-hidden shadow-2xs">
                                <div class="max-h-64 overflow-y-auto divide-y divide-gray-100" id="unified_items_list">
                                    <!-- Populated dynamically based on items not belonging to selected target -->
                                </div>
                            </div>
                        </div>

                        <div
                            class="p-3 bg-blue-50 rounded-xl border border-blue-200 text-[11px] text-blue-900 leading-relaxed flex items-start gap-2">
                            <svg class="w-4 h-4 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <strong>Pembersihan Otomatis:</strong> Jika semua item dalam nomor terima asal habis
                                dipindahkan, nomor terima asal tersebut akan otomatis dihapus/dibersihkan dari sistem.
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t shrink-0">
                        <span id="unified_selected_count" class="text-xs font-medium text-gray-500">0 item terpilih</span>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="closeUnifiedMergeModal()"
                                class="px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-xs font-semibold text-gray-700 transition-all">
                                Batal
                            </button>
                            <button type="button" onclick="submitUnifiedMerge()"
                                class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs flex items-center gap-1.5 transition-all cursor-pointer">
                                <span>Pindahkan Item</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script>
        const ORDER_ID = {{ $order->id }};
        @php
            $detailsData = $allReceivingDetails->map(fn($d) => ['id' => $d->id, 'code' => $d->receiving_details_code, 'invoice' => $d->invoice_number])->values();
        @endphp
        const ALL_DETAILS = {{ Illuminate\Support\Js::from($detailsData) }};
        const BPBA_ITEMS = {{ Illuminate\Support\Js::from(collect($orderItemsData)->values()) }};
        @php
            $allItemsData = collect();
            foreach ($allReceivingDetails as $rd) {
                foreach ($rd->receiving_items as $ri) {
                    $allItemsData->push([
                        'id' => $ri->id,
                        'details_id' => $rd->id,
                        'order_items_id' => $ri->order_items_id,
                        'details_code' => $rd->receiving_details_code,
                        'details_invoice' => $rd->invoice_number ?? '',
                        'medicine_name' => $ri->order_items->medicines->name ?? ($ri->medicines->name ?? '-'),
                        'batch' => $ri->batch ?? '-',
                        'expired_date' => $ri->expired_date ?? '-',
                        'qty' => (float) $ri->qty_received,
                        'total' => (float) $ri->total,
                    ]);
                }
            }
            if (isset($orphanedItems) && $orphanedItems->isNotEmpty()) {
                foreach ($orphanedItems as $ri) {
                    $allItemsData->push([
                        'id' => $ri->id,
                        'details_id' => null,
                        'details_code' => 'Tanpa NT',
                        'details_invoice' => '',
                        'medicine_name' => $ri->order_items->medicines->name ?? ($ri->medicines->name ?? '-'),
                        'batch' => $ri->batch ?? '-',
                        'expired_date' => $ri->expired_date ?? '-',
                        'qty' => (float) $ri->qty_received,
                        'total' => (float) $ri->total,
                    ]);
                }
            }
        @endphp
        const ALL_ITEMS = {{ Illuminate\Support\Js::from($allItemsData->values()) }};
        let currentAddMode = 'bpba';

        function parseRupiah(value) {
            if (!value) return 0;
            return parseInt(value.toString().replace(/[^\d]/g, ''), 10) || 0;
        }

        function formatRupiah(value) {
            const number = Number(value) || 0;
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(number);
        }

        function formatInputRupiah(e) {
            let val = parseRupiah(e.target.value);
            e.target.value = val ? formatRupiah(val) : '';
        }

        // ================== RECALC TOTALS ==================
        function recalcEditTotal() {
            const qtyEl = document.getElementById('edit_qty_received');
            const priceEl = document.getElementById('edit_raw_price');
            const discEl = document.getElementById('edit_discount');
            const extraDiscEl = document.getElementById('edit_extra_discount');
            const totalEl = document.getElementById('edit_total');

            const qty = qtyEl ? (parseFloat(qtyEl.value) || 0) : 0;
            const price = priceEl ? parseRupiah(priceEl.value) : 0;
            const disc = discEl ? parseRupiah(discEl.value) : 0;
            const extraDisc = extraDiscEl ? parseRupiah(extraDiscEl.value) : 0;
            const gross = qty * price;
            const net = Math.max(0, gross - disc - extraDisc);
            if (totalEl) totalEl.value = formatRupiah(net);
        }

        function recalcAddTotal() {
            const qtyEl = document.getElementById('add_qty_received');
            const priceEl = document.getElementById('add_raw_price');
            const discEl = document.getElementById('add_discount');
            const extraDiscEl = document.getElementById('add_extra_discount');
            const totalEl = document.getElementById('add_total');

            const qty = qtyEl ? (parseFloat(qtyEl.value) || 0) : 0;
            const price = priceEl ? parseRupiah(priceEl.value) : 0;
            const disc = discEl ? parseRupiah(discEl.value) : 0;
            const extraDisc = extraDiscEl ? parseRupiah(extraDiscEl.value) : 0;
            const gross = qty * price;
            const net = Math.max(0, gross - disc - extraDisc);
            if (totalEl) totalEl.value = formatRupiah(net);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const bindRecalc = (id, event, fn) => {
                const el = document.getElementById(id);
                if (el) el.addEventListener(event, fn);
            };

            bindRecalc('edit_qty_received', 'input', recalcEditTotal);
            bindRecalc('edit_raw_price', 'input', e => { formatInputRupiah(e); recalcEditTotal(); });
            bindRecalc('edit_discount', 'input', e => { formatInputRupiah(e); recalcEditTotal(); });
            bindRecalc('edit_extra_discount', 'input', e => { formatInputRupiah(e); recalcEditTotal(); });

            bindRecalc('add_qty_received', 'input', recalcAddTotal);
            bindRecalc('add_raw_price', 'input', e => { formatInputRupiah(e); recalcAddTotal(); });
            bindRecalc('add_discount', 'input', e => { formatInputRupiah(e); recalcAddTotal(); });
            bindRecalc('add_extra_discount', 'input', e => { formatInputRupiah(e); recalcAddTotal(); });
        });

        // ================== MODAL TAMBAH OBAT ==================
        function populateInvoiceMedicineOptions(detailsId) {
            const select = document.getElementById('add_order_items_id');
            const invoiceItemIds = new Set(ALL_ITEMS
                .filter(item => String(item.details_id) === String(detailsId))
                .map(item => String(item.order_items_id)));
            const items = BPBA_ITEMS.filter(item => invoiceItemIds.has(String(item.id)));

            select.options.length = 0;
            select.add(new Option(items.length
                ? '-- Pilih Obat dari Faktur Ini --'
                : '-- Tidak ada obat pada faktur ini --', ''));
            select.disabled = items.length === 0;

            items.forEach(item => {
                const option = new Option(
                    `${item.medicine_name} (Pesan: ${item.ordered_qty}, Diterima: ${item.received_qty}, Sisa: ${item.remaining_qty})`,
                    item.id
                );
                option.dataset.price = item.price;
                option.dataset.disc = item.discount;
                option.dataset.rem = item.remaining_qty;
                option.dataset.pack = item.pack ? '1' : '0';
                select.add(option);
            });
        }

        function openAddModal(detailsId, detailsCode) {
            document.getElementById('add_receiving_details_id').value = detailsId;
            document.getElementById('add_target_code').innerText = detailsCode;

            populateInvoiceMedicineOptions(detailsId);

            // Reset inputs
            switchAddMode('bpba');
            document.getElementById('add_order_items_id').value = '';
            document.getElementById('add_medicine_id').value = '';
            document.getElementById('add_batch').value = '';
            document.getElementById('add_expired_date').value = '';
            document.getElementById('add_qty_received').value = '';
            document.getElementById('add_raw_price').value = '';
            document.getElementById('add_discount').value = '';
            document.getElementById('add_extra_discount').value = '';
            document.getElementById('add_total').value = '';
            document.getElementById('add_status').value = '1';

            document.getElementById('addModal').classList.remove('hidden');
            document.getElementById('addModal').classList.add('flex');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
            document.getElementById('addModal').classList.remove('flex');
        }

        function switchAddMode(mode) {
            currentAddMode = mode;
            const btnBpba = document.getElementById('tab_mode_bpba');
            const secBpba = document.getElementById('section_bpba_select');
            const secMaster = document.getElementById('section_master_select');

            if (mode === 'bpba') {
                btnBpba.className =
                    'flex-1 py-1.5 rounded-lg bg-white shadow-xs text-blue-700 font-semibold transition-all';
                secBpba.classList.remove('hidden');
                secMaster.classList.add('hidden');
            } else {

                btnBpba.className = 'flex-1 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition-all';
                secMaster.classList.remove('hidden');
                secBpba.classList.add('hidden');
            }
        }

        function onSelectOrderItem() {
            const select = document.getElementById('add_order_items_id');
            const opt = select.options[select.selectedIndex];
            if (!opt || !opt.value) return;

            const price = parseFloat(opt.dataset.price) || 0;
            const rem = parseFloat(opt.dataset.rem) || 0;
            const disc = parseFloat(opt.dataset.disc) || 0;

            document.getElementById('add_raw_price').value = formatRupiah(price);
            if (rem > 0) {
                document.getElementById('add_qty_received').value = rem;
            }
            if (disc > 0) {
                document.getElementById('add_discount').value = formatRupiah(disc);
            }
            recalcAddTotal();
        }

        function onSelectMasterMedicine() {
            const select = document.getElementById('add_medicine_id');
            const opt = select.options[select.selectedIndex];
            if (!opt || !opt.value) return;

            const price = parseFloat(opt.dataset.price) || 0;
            document.getElementById('add_raw_price').value = formatRupiah(price);
            recalcAddTotal();
        }

        async function submitAddMedicine() {
            const detailsId = document.getElementById('add_receiving_details_id').value;
            const batch = document.getElementById('add_batch').value.trim();
            const expDate = document.getElementById('add_expired_date').value;
            const qty = parseFloat(document.getElementById('add_qty_received').value) || 0;
            const rawPrice = parseRupiah(document.getElementById('add_raw_price').value);

            if (!batch) {
                alert('Harap isi No. Batch.');
                return;
            }
            if (!expDate) {
                alert('Harap pilih Exp Date.');
                return;
            }
            if (qty <= 0) {
                alert('Kuantitas diterima harus lebih dari 0.');
                return;
            }
            if (rawPrice <= 0) {
                alert('HNA / Raw Price harus diisi.');
                return;
            }

            const payload = {
                receiving_details_id: detailsId,
                batch: batch,
                expired_date: expDate,
                qty_received: qty,
                raw_price: rawPrice,
                discount: parseRupiah(document.getElementById('add_discount').value),
                extra_discount: parseRupiah(document.getElementById('add_extra_discount').value),
                status: document.getElementById('add_status').value || 1,
                total: parseRupiah(document.getElementById('add_total').value),
            };

            if (currentAddMode === 'bpba') {
                const oiId = document.getElementById('add_order_items_id').value;
                if (!oiId) {
                    alert('Pilih obat dari faktur ini terlebih dahulu.');
                    return;
                }
                payload.order_items_id = oiId;
            } else {
                const medId = document.getElementById('add_medicine_id').value;
                if (!medId) {
                    alert('Pilih obat dari master terlebih dahulu.');
                    return;
                }
                payload.medicine_id = medId;
            }

            try {
                const res = await axios.post(`/orders/${ORDER_ID}/revision/add-item`, payload, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (res.data.success) {
                    if (typeof Swal !== 'undefined') {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: res.data.message,
                            confirmButtonColor: '#2563eb'
                        });
                    } else {
                        alert(res.data.message);
                    }
                    location.reload();
                }
            } catch (err) {
                const msg = err.response?.data?.message || 'Gagal menambahkan obat ke nomor terima.';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: msg,
                        confirmButtonColor: '#2563eb'
                    });
                } else {
                    alert(msg);
                }
            }
        }

        // ================== EDIT MODAL ==================
        function editRow(btn) {
            const row = btn.closest('tr');
            const data = JSON.parse(row.dataset.item);

            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_batch').value = data.batch;
            document.getElementById('edit_expired_date').value = data.expired_date;
            document.getElementById('edit_qty_received').value = data.qty_received;
            document.getElementById('edit_raw_price').value = formatRupiah(data.raw_price);
            document.getElementById('edit_discount').value = formatRupiah(data.discount);
            document.getElementById('edit_extra_discount').value = formatRupiah(data.extra_discount);
            document.getElementById('edit_status').value = data.status;
            document.getElementById('edit_total').value = formatRupiah(data.total);

            if (data.receiving_details_id) {
                document.getElementById('edit_receiving_details_id').value = data.receiving_details_id;
            }

            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').classList.add('flex');
        }

        function closeModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editModal').classList.remove('flex');
        }

        function submitEdit() {
            const id = document.getElementById('edit_id').value;

            const payload = {
                receiving_details_id: document.getElementById('edit_receiving_details_id').value,
                batch: document.getElementById('edit_batch').value,
                expired_date: document.getElementById('edit_expired_date').value,
                qty_received: document.getElementById('edit_qty_received').value,
                raw_price: parseRupiah(document.getElementById('edit_raw_price').value),
                discount: parseRupiah(document.getElementById('edit_discount').value),
                extra_discount: parseRupiah(document.getElementById('edit_extra_discount').value),
                status: document.getElementById('edit_status').value,
                total: parseRupiah(document.getElementById('edit_total').value),
            };

            axios.put(`/receiving-items/${id}`, payload, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            }).then(res => {
                if (res.data.success) {
                    iziToast.success({
                        title: 'Berhasil',
                        message: res.data.message,
                        position: 'topRight'
                    });
                    setTimeout(() => location.reload(), 700);
                }
            }).catch(err => {
                iziToast.error({
                    title: 'Gagal',
                    message: err.response?.data?.message ?? 'Gagal merevisi item',
                    position: 'topRight'
                });
            });
        }

        function deleteRow(id) {
            if (!confirm('Hapus item ini? Stok akan dikembalikan ke gudang/pesanan.')) return;

            axios.delete(`/receiving-items/${id}`, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            }).then(res => {
                if (res.data.success) {
                    iziToast.success({
                        title: 'Berhasil',
                        message: res.data.message,
                        position: 'topRight'
                    });
                    setTimeout(() => location.reload(), 700);
                }
            }).catch(err => {
                iziToast.error({
                    title: 'Gagal',
                    message: err.response?.data?.message ?? 'Gagal menghapus item',
                    position: 'topRight'
                });
            });
        }

        // ================== MODAL GABUNG / PINDAH ITEM (UNIFIED) ==================
        let preselectedItemId = null;
        let preselectedSourceDetailsId = null;

        function openUnifiedMergeModal(itemId = null, sourceDetailsId = null) {
            preselectedItemId = itemId;
            preselectedSourceDetailsId = sourceDetailsId;

            const select = document.getElementById('unified_target_id');
            select.innerHTML = '';

            // Populate all receiving details
            ALL_DETAILS.forEach((d, idx) => {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = `${d.code} ${d.invoice ? `(No. Faktur: ${d.invoice})` : ''}`;
                select.appendChild(opt);
            });

            // If a sourceDetailsId is passed, default the target to the other detail (not the source)
            if (sourceDetailsId) {
                const otherDetail = ALL_DETAILS.find(d => d.id !== sourceDetailsId);
                if (otherDetail) {
                    select.value = otherDetail.id;
                }
            } else if (itemId) {
                const item = ALL_ITEMS.find(i => i.id === itemId);
                if (item && item.details_id) {
                    const otherDetail = ALL_DETAILS.find(d => d.id !== item.details_id);
                    if (otherDetail) {
                        select.value = otherDetail.id;
                    }
                }
            }

            renderUnifiedItems();

            document.getElementById('unifiedMergeModal').classList.remove('hidden');
            document.getElementById('unifiedMergeModal').classList.add('flex');
        }

        function closeUnifiedMergeModal() {
            document.getElementById('unifiedMergeModal').classList.add('hidden');
            document.getElementById('unifiedMergeModal').classList.remove('flex');
            preselectedItemId = null;
            preselectedSourceDetailsId = null;
        }

        function onTargetDetailsChange() {
            renderUnifiedItems();
        }

        function renderUnifiedItems() {
            const targetId = parseInt(document.getElementById('unified_target_id').value, 10);
            const container = document.getElementById('unified_items_list');
            container.innerHTML = '';

            // Items eligible to move: all items NOT in target details
            const eligibleItems = ALL_ITEMS.filter(i => i.details_id !== targetId);

            if (eligibleItems.length === 0) {
                container.innerHTML = `
                    <div class="p-6 text-center text-xs text-gray-400 italic">
                        Tidak ada item obat lain yang dapat dipindahkan ke Nomor Terima ini.
                    </div>
                `;
                updateUnifiedSelectedCount();
                return;
            }

            eligibleItems.forEach(item => {
                // Determine if this item should be pre-checked
                let isChecked = false;
                if (preselectedItemId && item.id === preselectedItemId) {
                    isChecked = true;
                } else if (preselectedSourceDetailsId && item.details_id === preselectedSourceDetailsId) {
                    isChecked = true;
                }

                const row = document.createElement('label');
                row.className =
                    'flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50/80 cursor-pointer transition-colors';
                row.innerHTML = `
                    <input type="checkbox" name="unified_item_checkbox" value="${item.id}"
                        ${isChecked ? 'checked' : ''}
                        onchange="updateUnifiedSelectedCount()"
                        class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-bold text-gray-800 truncate">${item.medicine_name}</span>
                            <span class="text-xs font-semibold text-gray-700 whitespace-nowrap">${formatRupiah(item.total)}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 mt-0.5 text-[11px] text-gray-500">
                            <span class="inline-flex items-center px-1.5 py-0.2 rounded bg-gray-100 text-gray-700 font-mono">
                                Asal: ${item.details_code}${item.details_invoice ? ` (${item.details_invoice})` : ''}
                            </span>
                            <span>&bull; Batch: <span class="font-mono text-gray-600">${item.batch}</span></span>
                            <span>&bull; Exp: ${item.expired_date}</span>
                            <span>&bull; Qty: <strong class="text-gray-700">${item.qty}</strong></span>
                        </div>
                    </div>
                `;
                container.appendChild(row);
            });

            updateUnifiedSelectedCount();
        }

        function toggleSelectAllItems(selectAll) {
            const checkboxes = document.querySelectorAll('input[name="unified_item_checkbox"]');
            checkboxes.forEach(cb => {
                cb.checked = selectAll;
            });
            updateUnifiedSelectedCount();
        }

        function updateUnifiedSelectedCount() {
            const checked = document.querySelectorAll('input[name="unified_item_checkbox"]:checked');
            const label = document.getElementById('unified_selected_count');
            if (label) {
                label.textContent = `${checked.length} item terpilih`;
            }
        }

        async function submitUnifiedMerge() {
            const targetId = document.getElementById('unified_target_id').value;
            if (!targetId) {
                alert('Pilih Nomor Terima tujuan.');
                return;
            }

            const checkedBoxes = Array.from(document.querySelectorAll('input[name="unified_item_checkbox"]:checked'));
            const selectedIds = checkedBoxes.map(cb => parseInt(cb.value, 10));

            if (selectedIds.length === 0) {
                alert('Pilih minimal satu item obat yang ingin dipindahkan.');
                return;
            }

            const targetDetail = ALL_DETAILS.find(d => d.id == targetId);
            const targetName = targetDetail ? `${targetDetail.code}` : 'nomor terima tujuan';

            const confirmMsg =
                `Pindahkan ${selectedIds.length} item obat ke ${targetName}? Nomor terima asal yang kosong akan otomatis dibersihkan.`;
            if (!confirm(confirmMsg)) {
                return;
            }

            try {
                const res = await axios.post(`/orders/${ORDER_ID}/revision/move-item`, {
                    receiving_item_ids: selectedIds,
                    target_details_id: targetId,
                }, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (res.data.success) {
                    if (typeof Swal !== 'undefined') {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Berhasil Dipindahkan',
                            text: res.data.message,
                            confirmButtonColor: '#2563eb'
                        });
                    } else {
                        alert(res.data.message);
                    }
                    location.reload();
                }
            } catch (err) {
                const msg = err.response?.data?.message || 'Gagal memindahkan item obat.';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: msg,
                        confirmButtonColor: '#2563eb'
                    });
                } else {
                    alert(msg);
                }
            }
        }

        // Bridge legacy openMoveItemModal to unified modal
        function openMoveItemModal(itemId, itemName, currentDetailsId) {
            openUnifiedMergeModal(itemId, null);
        }

        // Bridge legacy openMergeModal to unified modal
        function openMergeModal(sourceId, sourceCode) {
            openUnifiedMergeModal(null, sourceId);
        }

        // ================== HAPUS NOMOR TERIMA KOSONG ==================
        async function deleteEmptyDetails(detailsId, code) {
            if (!confirm(`Apakah Anda yakin ingin menghapus nomor terima ${code} yang kosong ini?`)) return;

            try {
                const res = await axios.delete(`/orders/${ORDER_ID}/revision/details/${detailsId}`, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (res.data.success) {
                    if (typeof Swal !== 'undefined') {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: res.data.message,
                            confirmButtonColor: '#2563eb'
                        });
                    } else {
                        alert(res.data.message);
                    }
                    location.reload();
                }
            } catch (err) {
                const msg = err.response?.data?.message || 'Gagal menghapus nomor terima.';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: msg,
                        confirmButtonColor: '#2563eb'
                    });
                } else {
                    alert(msg);
                }
            }
        }

        // Bind real implementations to window
        window._openAddModal = openAddModal;
        window.openAddModal = openAddModal;
        window._closeAddModal = closeAddModal;
        window.closeAddModal = closeAddModal;
        window._openUnifiedMergeModal = openUnifiedMergeModal;
        window.openUnifiedMergeModal = openUnifiedMergeModal;
        window._closeUnifiedMergeModal = closeUnifiedMergeModal;
        window.closeUnifiedMergeModal = closeUnifiedMergeModal;
        window._openMoveItemModal = openMoveItemModal;
        window.openMoveItemModal = openMoveItemModal;
        window._openMergeModal = openMergeModal;
        window.openMergeModal = openMergeModal;
        window._editRow = editRow;
        window.editRow = editRow;
        window._deleteEmptyDetails = deleteEmptyDetails;
        window.deleteEmptyDetails = deleteEmptyDetails;
    </script>
@endsection
