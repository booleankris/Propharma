@extends('layouts.app')

@section('title', 'Revisi Faktur')

@section('content')
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
                                            <button type="button" data-details-id="{{ $rd->id }}"
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
                    <input type="hidden" id="add_medicine_id">

                    <!-- Mode Toggle: BPBA vs Master -->
                    <div class="grid grid-cols-2 gap-2 p-1 bg-gray-100 rounded-xl">
                        <button type="button" id="add_mode_bpba_btn" onclick="setAddMode('bpba')"
                            class="px-3 py-2 rounded-lg text-xs font-semibold transition-all bg-blue-600 text-white shadow-sm">
                            Dari BPBA (Faktur Ini)
                        </button>
                        <button type="button" id="add_mode_master_btn" onclick="setAddMode('master')"
                            class="px-3 py-2 rounded-lg text-xs font-semibold transition-all bg-white text-gray-700">
                            Dari Master Obat
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
                    <div id="section_master_select" class="space-y-2 hidden">
                        <label class="text-xs font-semibold text-gray-700">Pilih Obat dari Master <span
                                class="text-red-500">*</span></label>
                        <div class="relative">
                            <input id="master_search_input" type="text" placeholder="Ketik nama / kode obat..."
                                autocomplete="off" oninput="searchMasterMedicine(this.value)"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <div id="master_search_dropdown"
                                class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg hidden max-h-56 overflow-y-auto">
                            </div>
                        </div>
                        <div id="master_selected_medicine"
                            class="hidden p-2 bg-emerald-50 border border-emerald-200 rounded-lg">
                            <div class="text-[11px] text-emerald-700 font-semibold">Obat Terpilih:</div>
                            <div class="text-xs text-gray-700" id="master_selected_medicine_name">-</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Kemasan Utuh</label>
                            <label
                                class="flex items-center gap-2 mt-1 bg-white px-3 py-2 rounded-lg border border-gray-300 cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="checkbox" id="add_pack" onchange="onAddPackChange()"
                                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                                <span class="text-xs font-medium text-gray-700 select-none">Utuh (Box)</span>
                            </label>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Isi Obat</label>
                            <input id="add_content" readonly placeholder="-"
                                class="w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-xs text-gray-600">
                        </div>
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
                            <label class="text-xs font-semibold text-gray-700">HNA<span class="text-red-500">*</span>
                                <span id="add_box_price_info"
                                    class="text-[10px] text-blue-600 font-normal"></span></label>
                            <input id="add_raw_price" type="text" placeholder="Rp 0"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Diskon (%/Rp)</label>
                            <input id="add_discount" type="number" min="0" step="any" placeholder="≤100 = %, >100 = Rp"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <p class="text-[10px] text-gray-400 mt-0.5">≤ 100 dianggap persen, &gt; 100 dianggap nominal.</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Extra Diskon (%/Rp)</label>
                            <input id="add_extra_discount" type="number" min="0" step="any" placeholder="≤100 = %, >100 = Rp"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <p class="text-[10px] text-gray-400 mt-0.5">≤ 100 dianggap persen, &gt; 100 dianggap nominal.</p>
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
                            <label class="text-xs font-semibold text-gray-700">Diskon (%/Rp)</label>
                            <input id="edit_discount" type="number" min="0" step="any" placeholder="≤100 = %, >100 = Rp"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Extra Diskon (%/Rp)</label>
                            <input id="edit_extra_discount" type="number" min="0" step="any" placeholder="≤100 = %, >100 = Rp"
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
    @php

        $detailsData = $allReceivingDetails
            ->map(fn($d) => ['id' => $d->id, 'code' => $d->receiving_details_code, 'invoice' => $d->invoice_number])
            ->values();

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

        $revisionPageData = [
            'orderId' => $order->id,
            'searchMedicineUrl' => route('receiving.revision.searchMasterMedicine'),
            'details' => $detailsData,
            'orderItems' => collect($orderItemsData)->values(),
            'items' => $allItemsData->values(),
        ];
    @endphp
    <script id="revision-page-data" type="application/json">@json($revisionPageData)</script>
    <script data-cfasync="false"
        src="{{ asset('js/invoice-revision.js') }}?v={{ filemtime(public_path('js/invoice-revision.js')) }}"></script>
@endsection

@section('scripts')
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
@endsection
