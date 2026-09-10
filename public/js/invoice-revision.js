(function () {
    'use strict';

    const config = JSON.parse(document.getElementById('revision-page-data').textContent);
    const ORDER_ID = config.orderId;
    const ALL_DETAILS = config.details;
    const BPBA_ITEMS = config.orderItems;
    const ALL_ITEMS = config.items;

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
            document.getElementById('add_order_items_id').value = '';
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

            const oiId = document.getElementById('add_order_items_id').value;
            if (!oiId) {
                alert('Pilih obat dari faktur ini terlebih dahulu.');
                return;
            }
            payload.order_items_id = oiId;

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


    Object.assign(window, {
        parseRupiah,
        formatRupiah,
        formatInputRupiah,
        recalcEditTotal,
        recalcAddTotal,
        populateInvoiceMedicineOptions,
        openAddModal,
        closeAddModal,
        onSelectOrderItem,
        submitAddMedicine,
        editRow,
        closeModal,
        submitEdit,
        deleteRow,
        openUnifiedMergeModal,
        closeUnifiedMergeModal,
        onTargetDetailsChange,
        renderUnifiedItems,
        toggleSelectAllItems,
        updateUnifiedSelectedCount,
        submitUnifiedMerge,
        openMoveItemModal,
        openMergeModal,
        deleteEmptyDetails
    });
})();
