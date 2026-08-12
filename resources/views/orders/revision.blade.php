@extends('layouts.app')

@section('title', 'Revisi Faktur')

@section('content')
    <section class="section py-4 px-[18px] bg-gray-50 min-h-screen">
        <div class="mx-auto space-y-6">

            <div class="flex items-center justify-between bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div>
                    <h1 class="text-2xl font-bold uppercase font-poppins text-gray-800">Revisi Faktur</h1>
                    <p class="text-xs text-gray-500 mt-1">Order: {{ $order->code }}</p>
                </div>

            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
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
                            @php
                                $allReceivingItems = collect();
                                foreach ($order->order_items as $orderItem) {
                                    foreach ($orderItem->receivingItems as $ri) {
                                        $ri->medicine_name = $orderItem->medicines->name ?? '-';
                                        $allReceivingItems->push($ri);
                                    }
                                }

                                $groupedItems = $allReceivingItems->groupBy(function ($ri) {
                                    return $ri->receiving_details->receiving_details_code ?? 'Tanpa Nomor Terima';
                                });
                            @endphp

                            @foreach ($groupedItems as $nomorTerima => $items)
                                <tr class="bg-blue-50/50">
                                    <td colspan="9" class="px-4 py-2 text-xs font-bold text-blue-800 border-b border-blue-100">
                                        Nomor Terima: {{ $nomorTerima }}
                                    </td>
                                </tr>
                                @foreach ($items as $ri)
                                    <tr data-item='@json($ri)'>
                                        <td class="px-4 py-3">{{ $ri->medicine_name }}</td>
                                        <td class="px-3 py-3 text-center">{{ $ri->batch }}</td>
                                        <td class="px-3 py-3 text-center">{{ $ri->expired_date }}</td>
                                        <td class="px-3 py-3 text-center">{{ $ri->qty_received }}</td>
                                        <td class="px-4 py-3 text-right">Rp {{ number_format($ri->raw_price, 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-3 text-center">Rp {{ number_format($ri->discount, 0, ',', '.') }}</td>
                                        <td class="px-3 py-3 text-center">Rp {{ number_format($ri->extra_discount, 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-right">Rp {{ number_format($ri->total, 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-3 text-center">
                                            <button onclick="editRow(this)" class="text-blue-600 font-semibold">Edit</button>
                                            <button onclick="deleteRow({{ $ri->id }})"
                                                class="text-red-600 font-semibold ml-2">Hapus</button>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end pt-2">
                    <button onclick="window.location.href='{{ route('receiving.index') }}'"
                        class="px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-xs font-semibold text-gray-700 flex items-center gap-1.5 cursor-pointer">
                        <span>&larr;</span> Selesai & Kembali
                    </button>
                </div>
            </div>

            <!-- Edit Modal -->
            <div id="editModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50">
                <div class="bg-white rounded-2xl p-6 w-full max-w-2xl space-y-4">
                    <h2 class="text-lg font-bold">Edit Item</h2>
                    <input type="hidden" id="edit_id">

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold">Batch</label>
                            <input id="edit_batch" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs">
                        </div>
                        <div>
                            <label class="text-xs font-semibold">Exp Date</label>
                            <input id="edit_expired_date" type="date"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs">
                        </div>
                        <div>
                            <label class="text-xs font-semibold">QTY Diterima</label>
                            <input id="edit_qty_received" type="number"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs">
                        </div>
                        <div>
                            <label class="text-xs font-semibold">Raw Price</label>
                            <input id="edit_raw_price" type="text"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs">
                        </div>
                        <div>
                            <label class="text-xs font-semibold">Diskon (Rp)</label>
                            <input id="edit_discount" type="text"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs">
                        </div>
                        <div>
                            <label class="text-xs font-semibold">Extra Diskon (Rp)</label>
                            <input id="edit_extra_discount" type="text"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs">
                        </div>
                        <div>
                            <label class="text-xs font-semibold">Status Barang</label>
                            <input id="edit_status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs">
                        </div>
                        <div>
                            <label class="text-xs font-semibold">Total</label>
                            <input id="edit_total" type="text" readonly
                                class="w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-xs">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button onclick="closeModal()"
                            class="px-4 py-2 rounded-xl bg-gray-200 text-xs font-semibold">Batal</button>
                        <button onclick="submitEdit()"
                            class="px-4 py-2 rounded-xl bg-blue-600 text-white text-xs font-semibold">Simpan Revisi</button>
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

        function recalcEditTotal() {
            const qty = parseFloat(document.getElementById('edit_qty_received').value) || 0;
            const price = parseRupiah(document.getElementById('edit_raw_price').value);
            document.getElementById('edit_total').value = formatRupiah(qty * price);
        }

        document.getElementById('edit_qty_received').addEventListener('input', recalcEditTotal);

        function formatInputRupiah(e) {
            let val = parseRupiah(e.target.value);
            e.target.value = val ? formatRupiah(val) : '';
        }

        document.getElementById('edit_raw_price').addEventListener('input', function (e) {
            formatInputRupiah(e);
            recalcEditTotal();
        });
        document.getElementById('edit_discount').addEventListener('input', formatInputRupiah);
        document.getElementById('edit_extra_discount').addEventListener('input', formatInputRupiah);

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
                    setTimeout(() => location.reload(), 800);
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
            if (!confirm('Hapus item ini? Stok akan dikembalikan.')) return;

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
                    setTimeout(() => location.reload(), 800);
                }
            }).catch(err => {
                iziToast.error({
                    title: 'Gagal',
                    message: err.response?.data?.message ?? 'Gagal menghapus item',
                    position: 'topRight'
                });
            });
        }
    </script>
@endsection