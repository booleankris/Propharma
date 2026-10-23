import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import FinanceLayout from './Layouts/FinanceLayout';
import {
    Undo2, Search, Filter, Plus, Download, Edit2, Trash2, CheckCircle2,
    Calendar, AlertTriangle, Clock, Building2, Package, X, Check, ArrowRight, Lock
} from 'lucide-react';
import { formatRupiah } from './Components/Utils';

export default function PurchaseReturns({ items, filters, stats, creditors, branchContext }) {
    const [search, setSearch] = useState(filters.search || '');
    const [creditorFilter, setCreditorFilter] = useState(filters.creditor_code || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [reasonFilter, setReasonFilter] = useState(filters.reason || '');

    // Modal Input Retur (Custom / Dari Faktur)
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [entryType, setEntryType] = useState('manual'); // 'invoice' | 'manual'

    // Faktur search state
    const [invoiceSearchQuery, setInvoiceSearchQuery] = useState('');
    const [invoiceSearchResults, setInvoiceSearchResults] = useState([]);
    const [isSearchingInvoice, setIsSearchingInvoice] = useState(false);

    // Obat search state (untuk baris obat custom)
    const [medSearchQuery, setMedSearchQuery] = useState('');
    const [medSearchResults, setMedSearchResults] = useState([]);

    // Form data retur baru
    const [formData, setFormData] = useState({
        invoice_number: '',
        invoice_date: new Date().toISOString().split('T')[0],
        received_date: new Date().toISOString().split('T')[0],
        creditor_code: '',
        receiving_id: null,
        notes: '',
        items: [],
    });

    // Modal Edit Item Rincian (Update Tgl Diambil & Potong Tagihan)
    const [editItemModalOpen, setEditItemModalOpen] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [editItemForm, setEditItemForm] = useState({
        pickup_date: '',
        deduction_note: '',
        reason: 'ED DEKAT',
        qty: 1,
        unit_price: 0,
    });

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        router.get('/finance/purchase-returns', {
            search,
            creditor_code: creditorFilter,
            status: statusFilter,
            reason: reasonFilter,
        }, { preserveState: true });
    };

    const handleFilterChange = (creditor, status, reason) => {
        router.get('/finance/purchase-returns', {
            search,
            creditor_code: creditor,
            status,
            reason,
        }, { preserveState: true });
    };

    // Debounce cari faktur
    useEffect(() => {
        if (invoiceSearchQuery.length < 2) {
            setInvoiceSearchResults([]);
            return;
        }
        const timer = setTimeout(() => {
            setIsSearchingInvoice(true);
            fetch(`/finance/purchase-returns/search-invoices?query=${encodeURIComponent(invoiceSearchQuery)}`)
                .then(res => res.json())
                .then(data => {
                    setInvoiceSearchResults(data);
                    setIsSearchingInvoice(false);
                })
                .catch(() => setIsSearchingInvoice(false));
        }, 300);
        return () => clearTimeout(timer);
    }, [invoiceSearchQuery]);

    // Debounce cari obat
    useEffect(() => {
        if (medSearchQuery.length < 2) {
            setMedSearchResults([]);
            return;
        }
        const timer = setTimeout(() => {
            const creditorParam = formData.creditor_code ? `&creditor_code=${encodeURIComponent(formData.creditor_code)}` : '';
            fetch(`/finance/purchase-returns/search-medicines?query=${encodeURIComponent(medSearchQuery)}${creditorParam}`)
                .then(res => res.json())
                .then(data => setMedSearchResults(data))
                .catch(() => {});
        }, 300);
        return () => clearTimeout(timer);
    }, [medSearchQuery, formData.creditor_code]);

    const selectInvoice = (inv) => {
        fetch(`/finance/purchase-returns/invoice-items/${inv.id}`)
            .then(res => res.json())
            .then(data => {
                setFormData({
                    ...formData,
                    invoice_number: inv.invoice_number,
                    invoice_date: inv.invoice_date || '',
                    creditor_code: inv.creditor_code,
                    receiving_id: inv.id,
                    items: (data.items || []).map(it => ({
                        selected: false, // User selects which items to return
                        medicine_id: it.medicine_id,
                        medicine_name: it.medicine_name,
                        unit: it.unit || 'BOX',
                        batch_number: it.batch || '-',
                        expired_date: it.expired_date || '',
                        max_qty: Number(it.qty_received || it.qty || 1),
                        qty: Number(it.qty_received || it.qty || 1),
                        unit_price: Number(it.raw_price || 0),
                        reason: 'ED DEKAT',
                        pickup_date: '',
                        deduction_note: '',
                        factory_id: it.factory_id,
                        factory_name: it.factory_name,
                        return_policy: it.return_policy,
                    })),
                });
                setInvoiceSearchResults([]);
                setInvoiceSearchQuery(inv.invoice_number);
            });
    };

    const addManualItemRow = (med = null) => {
        const newItem = {
            selected: true,
            medicine_id: med ? med.id : null,
            medicine_name: med ? med.name : '',
            unit: med ? (med.unit || 'BOX') : 'BOX',
            batch_number: '',
            expired_date: '',
            max_qty: 9999,
            qty: 1,
            unit_price: med ? med.raw_price : 0,
            reason: 'ED DEKAT',
            pickup_date: '',
            deduction_note: '',
            factory_id: med ? med.factory_id : null,
            factory_name: med ? med.factory_name : null,
            return_policy: med ? med.return_policy : null,
        };
        setFormData({
            ...formData,
            items: [...formData.items, newItem],
        });
        setMedSearchQuery('');
        setMedSearchResults([]);
    };

    const updateItemRow = (index, field, value) => {
        const updated = [...formData.items];
        updated[index][field] = value;
        setFormData({ ...formData, items: updated });
    };

    const removeItemRow = (index) => {
        const updated = formData.items.filter((_, i) => i !== index);
        setFormData({ ...formData, items: updated });
    };

    const toggleSelectAll = (checked) => {
        const updated = formData.items.map(it => ({ ...it, selected: checked }));
        setFormData({ ...formData, items: updated });
    };

    const handleCreateSubmit = (e) => {
        e.preventDefault();
        
        // Filter items that are selected if in invoice mode, or all items if in manual mode
        const itemsToSubmit = entryType === 'invoice'
            ? formData.items.filter(it => it.selected)
            : formData.items;

        if (itemsToSubmit.length === 0) {
            alert(entryType === 'invoice' 
                ? 'Silakan centang minimal 1 obat dari faktur yang ingin Anda retur.'
                : 'Silakan masukkan minimal 1 obat yang akan diretur.'
            );
            return;
        }

        const payload = {
            ...formData,
            items: itemsToSubmit,
        };

        router.post('/finance/purchase-returns', payload, {
            onSuccess: () => {
                setCreateModalOpen(false);
                setFormData({
                    invoice_number: '',
                    invoice_date: new Date().toISOString().split('T')[0],
                    received_date: new Date().toISOString().split('T')[0],
                    creditor_code: '',
                    receiving_id: null,
                    notes: '',
                    items: [],
                });
            },
        });
    };

    const openEditItemModal = (item) => {
        setEditingItem(item);
        setEditItemForm({
            pickup_date: item.pickup_date || '',
            deduction_note: item.deduction_note || '',
            reason: item.reason || 'ED DEKAT',
            qty: Number(item.qty || 1),
            unit_price: Number(item.unit_price || 0),
        });
        setEditItemModalOpen(true);
    };

    const handleUpdateItemSubmit = (e) => {
        e.preventDefault();
        router.put(`/finance/purchase-returns/items/${editingItem.id}`, editItemForm, {
            onSuccess: () => setEditItemModalOpen(false),
        });
    };

    const handleDeleteItem = (item) => {
        if (confirm(`Yakin ingin menghapus item ${item.medicine_name} dari daftar retur?`)) {
            router.delete(`/finance/purchase-returns/items/${item.id}`);
        }
    };

    return (
        <FinanceLayout
            title="Monitoring Retur Beli"
            subtitle="Pusat pemantauan pengembalian barang ke distributor (PBF), jadwal pengambilan, dan pemotongan tagihan faktur"
        >
            <Head title="Monitoring Retur Beli - Finance ERP" />

            <div className="p-6 max-w-7xl mx-auto space-y-6">
                {/* Header Title & Actions */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <div className="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                <Undo2 className="w-5 h-5" />
                            </div>
                            <div>
                                <h1 className="text-lg font-bold text-slate-800">Monitoring Retur Beli</h1>
                                <p className="text-xs text-slate-500">
                                    Catatan pengembalian obat ke PBF, tanggal pengambilan distributor, dan verifikasi potong tagihan
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-2.5">
                        <a
                            href="/finance/purchase-returns/export"
                            className="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all"
                        >
                            <Download className="w-4 h-4 text-slate-500" /> Export Excel
                        </a>
                        <button
                            onClick={() => {
                                setFormData({
                                    invoice_number: '',
                                    invoice_date: new Date().toISOString().split('T')[0],
                                    received_date: new Date().toISOString().split('T')[0],
                                    creditor_code: creditors[0]?.code || '',
                                    receiving_id: null,
                                    notes: '',
                                    items: [],
                                });
                                setCreateModalOpen(true);
                            }}
                            className="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-xs transition-all"
                        >
                            <Plus className="w-4 h-4" /> Input Retur Baru
                        </button>
                    </div>
                </div>

                {/* Metrics Summary Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                        <span className="text-xs font-medium text-slate-500">Total Item Diretur</span>
                        <div className="text-2xl font-bold text-slate-800 mt-1">{stats.total_items} Obat</div>
                        <div className="text-[11px] text-slate-400 mt-0.5">Nilai: {formatRupiah(stats.total_value)}</div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                        <span className="text-xs font-medium text-emerald-600">Sudah Potong Tagihan</span>
                        <div className="text-2xl font-bold text-emerald-600 mt-1">{stats.sudah_potong} Item</div>
                        <div className="text-[11px] text-emerald-500 mt-0.5">Faktur telah dikurangi</div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                        <span className="text-xs font-medium text-amber-600">Belum Potong Tagihan</span>
                        <div className="text-2xl font-bold text-amber-600 mt-1">{stats.belum_potong} Item</div>
                        <div className="text-[11px] text-amber-500 mt-0.5">Menunggu konfirmasi PBF</div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                        <span className="text-xs font-medium text-blue-600">Barang Sudah Diambil</span>
                        <div className="text-2xl font-bold text-blue-600 mt-1">{stats.sudah_diambil} Item</div>
                        <div className="text-[11px] text-blue-500 mt-0.5">Oleh kurir / sales PBF</div>
                    </div>
                </div>

                {/* Main Monitoring Table */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    {/* Filters */}
                    <div className="p-4 border-b border-slate-200 flex flex-col md:flex-row items-center justify-between gap-3 bg-slate-50/50">
                        <form onSubmit={handleSearchSubmit} className="relative w-full md:w-80">
                            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                type="text"
                                placeholder="Cari nama obat, no batch, atau faktur..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                            />
                        </form>

                        <div className="flex flex-wrap items-center gap-2 w-full md:w-auto">
                            <select
                                value={creditorFilter}
                                onChange={(e) => {
                                    setCreditorFilter(e.target.value);
                                    handleFilterChange(e.target.value, statusFilter, reasonFilter);
                                }}
                                className="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">Semua PBF</option>
                                {creditors.map((c) => (
                                    <option key={c.code} value={c.code}>{c.name}</option>
                                ))}
                            </select>

                            <select
                                value={statusFilter}
                                onChange={(e) => {
                                    setStatusFilter(e.target.value);
                                    handleFilterChange(creditorFilter, e.target.value, reasonFilter);
                                }}
                                className="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">Semua Status Potong</option>
                                <option value="sudah_potong">Sudah Potong Tagihan</option>
                                <option value="belum_potong">Belum Potong Tagihan</option>
                                <option value="sudah_ambil">Barang Sudah Diambil</option>
                                <option value="belum_ambil">Barang Belum Diambil</option>
                            </select>

                            <select
                                value={reasonFilter}
                                onChange={(e) => {
                                    setReasonFilter(e.target.value);
                                    handleFilterChange(creditorFilter, statusFilter, e.target.value);
                                }}
                                className="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">Semua Keterangan</option>
                                <option value="ED DEKAT">ED DEKAT</option>
                                <option value="SALAH PESAN">SALAH PESAN</option>
                                <option value="DISKON TIDAK SESUAI">DISKON TIDAK SESUAI</option>
                                <option value="TERDOUBLE">TERDOUBLE</option>
                                <option value="PESAN YANG INJ">PESAN YANG INJ</option>
                            </select>
                        </div>
                    </div>

                    {/* Table View */}
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs text-slate-600">
                            <thead className="bg-slate-100/70 text-slate-700 font-semibold uppercase text-[11px] tracking-wider border-b border-slate-200">
                                <tr>
                                    <th className="py-3 px-3 w-10 text-center">No</th>
                                    <th className="py-3 px-3 min-w-[200px]">Nama Obat</th>
                                    <th className="py-3 px-2 text-center w-14">Jumlah</th>
                                    <th className="py-3 px-2 text-center w-16">Satuan</th>
                                    <th className="py-3 px-3">Batch</th>
                                    <th className="py-3 px-3 min-w-[140px]">No Faktur & PBF</th>
                                    <th className="py-3 px-3 text-center">ED</th>
                                    <th className="py-3 px-3 text-center min-w-[150px]">Ketentuan Retur</th>
                                    <th className="py-3 px-3">Tgl Entri / Faktur</th>
                                    <th className="py-3 px-3">Keterangan</th>
                                    <th className="py-3 px-3 text-center">Tgl Brg Di Ambil</th>
                                    <th className="py-3 px-3">Potong Tagihan</th>
                                    <th className="py-3 px-3 text-center w-20">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {items.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="13" className="text-center py-12 text-slate-400">
                                            Belum ada data monitoring retur beli. Klik tombol <strong>Input Retur Baru</strong> untuk memulai.
                                        </td>
                                    </tr>
                                ) : (
                                    items.data.map((item, idx) => {
                                        const p = item.purchase_return;
                                        const hasDeduction = !!item.deduction_note;
                                        const pbfName = p && p.creditor ? p.creditor.name : (p?.creditor_code || '-');
                                        const policyInfo = item.return_policy_info;
                                        return (
                                            <tr
                                                key={item.id}
                                                className={`transition-colors hover:bg-amber-50/30 ${
                                                    hasDeduction ? 'bg-emerald-50/40' : ''
                                                }`}
                                            >
                                                <td className="py-2.5 px-3 text-center text-slate-400 font-mono text-[11px]">
                                                    {(items.current_page - 1) * items.per_page + idx + 1}
                                                </td>
                                                <td className="py-2.5 px-3 font-bold text-slate-800">
                                                    <div>{item.medicine_name}</div>
                                                    {item.factory_name && (
                                                        <div className="text-[10px] text-slate-400 font-medium">
                                                            Pabrik: {item.factory_name}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="py-2.5 px-2 text-center font-bold text-blue-700">
                                                    {Number(item.qty)}
                                                </td>
                                                <td className="py-2.5 px-2 text-center font-semibold text-slate-600 uppercase text-[11px]">
                                                    {item.unit || 'BOX'}
                                                </td>
                                                <td className="py-2.5 px-3 font-mono text-slate-700">
                                                    {item.batch_number || '-'}
                                                </td>
                                                <td className="py-2.5 px-3">
                                                    <div className="font-mono font-bold text-slate-800">
                                                        {p ? p.invoice_number : '-'}
                                                    </div>
                                                    <div className="text-xs font-semibold text-blue-800 mt-0.5 break-words">
                                                        {pbfName}
                                                    </div>
                                                </td>
                                                <td className="py-2.5 px-3 text-center font-medium text-rose-600 whitespace-nowrap">
                                                    {item.expired_date || '-'}
                                                </td>
                                                <td className="py-2.5 px-3 text-center">
                                                    {policyInfo ? (
                                                        <div className="inline-flex flex-col items-center">
                                                            <span
                                                                className={`inline-block px-2 py-0.5 rounded text-[10px] font-bold border ${policyInfo.badge}`}
                                                                title={policyInfo.message}
                                                            >
                                                                {policyInfo.label}
                                                            </span>
                                                            {policyInfo.policy?.notes && (
                                                                <span className="text-[10px] text-slate-500 mt-0.5 max-w-[140px] truncate" title={policyInfo.policy.notes}>
                                                                    {policyInfo.policy.notes}
                                                                </span>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <span className="text-slate-300 text-[11px]">-</span>
                                                    )}
                                                </td>
                                                <td className="py-2.5 px-3 text-slate-600 whitespace-nowrap">
                                                    {p?.invoice_date || '-'}
                                                </td>
                                                <td className="py-2.5 px-3">
                                                    <span className="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 uppercase">
                                                        {item.reason || 'ED DEKAT'}
                                                    </span>
                                                </td>
                                                <td className="py-2.5 px-3 text-center whitespace-nowrap">
                                                    {item.pickup_date ? (
                                                        <span className="font-semibold text-blue-700 bg-blue-50 px-2 py-0.5 rounded">
                                                            {item.pickup_date}
                                                        </span>
                                                    ) : (
                                                        <span className="text-slate-300 text-[11px]">-</span>
                                                    )}
                                                </td>
                                                <td className="py-2.5 px-3">
                                                    {hasDeduction ? (
                                                        <span className="inline-flex items-center gap-1 font-bold text-emerald-800 bg-emerald-100/90 px-2 py-0.5 rounded text-[11px]">
                                                            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                                                            {item.deduction_note}
                                                        </span>
                                                    ) : (
                                                        <span className="text-slate-400 italic text-[11px]">Belum dipotong</span>
                                                    )}
                                                </td>
                                                <td className="py-2.5 px-3 text-center">
                                                    <div className="flex items-center justify-center gap-1">
                                                        <button
                                                            onClick={() => openEditItemModal(item)}
                                                            className="p-1 hover:bg-slate-100 text-slate-600 hover:text-blue-600 rounded-lg transition-colors"
                                                            title="Update Status / Edit"
                                                        >
                                                            <Edit2 className="w-3.5 h-3.5" />
                                                        </button>
                                                        <button
                                                            onClick={() => handleDeleteItem(item)}
                                                            className="p-1 hover:bg-rose-50 text-slate-400 hover:text-rose-600 rounded-lg transition-colors"
                                                            title="Hapus Item"
                                                        >
                                                            <Trash2 className="w-3.5 h-3.5" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {items.total > items.per_page && (
                        <div className="p-4 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
                            <div>
                                Menampilkan {items.from || 0} - {items.to || 0} dari {items.total} item
                            </div>
                            <div className="flex items-center gap-1">
                                {items.links.map((link, i) => (
                                    <button
                                        key={i}
                                        disabled={!link.url || link.active}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                        className={`px-3 py-1.5 rounded-lg font-medium transition-all ${
                                            link.active
                                                ? 'bg-blue-600 text-white font-bold'
                                                : link.url
                                                ? 'bg-slate-50 hover:bg-slate-100 text-slate-700'
                                                : 'text-slate-300 cursor-not-allowed'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal Input Retur Baru */}
            {createModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3 sm:p-6">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-6xl h-[90vh] border border-slate-200 flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                        {/* Modal Header (Fixed at top) */}
                        <div className="p-4 sm:p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50 shrink-0">
                            <div>
                                <h3 className="font-bold text-slate-800 text-base sm:text-lg">Input Retur Pembelian</h3>
                                <p className="text-xs text-slate-500">
                                    Pilih faktur penerimaan yang sudah ada di sistem ATAU masukkan faktur secara manual (custom)
                                </p>
                            </div>
                            <button
                                onClick={() => setCreateModalOpen(false)}
                                className="p-1.5 rounded-xl hover:bg-slate-200 text-slate-400 hover:text-slate-700 transition-colors"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Mode Switcher (Fixed at top) */}
                        <div className="px-5 py-3 bg-blue-50/40 border-b border-blue-100 flex flex-wrap items-center justify-between gap-3 shrink-0">
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => setEntryType('manual')}
                                    className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all ${
                                        entryType === 'manual'
                                            ? 'bg-blue-600 text-white shadow-xs'
                                            : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'
                                    }`}
                                >
                                    Input Manual / Custom Retur
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setEntryType('invoice')}
                                    className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all ${
                                        entryType === 'invoice'
                                            ? 'bg-blue-600 text-white shadow-xs'
                                            : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'
                                    }`}
                                >
                                    Cari dari Faktur Penerimaan Sistem
                                </button>
                            </div>
                            <span className="text-[11px] text-slate-500 font-medium">
                                {entryType === 'manual'
                                    ? 'Ketik nomor faktur & rincian obat yang ingin diretur'
                                    : 'Otomatis mengisi daftar obat dari database faktur'}
                            </span>
                        </div>

                        {/* Form Container with Flex-1 and Scrollable Body */}
                        <form onSubmit={handleCreateSubmit} className="flex flex-col flex-1 min-h-0 overflow-hidden">
                            <div className="p-5 space-y-5 overflow-y-auto flex-1">
                            {/* Search Invoice if invoice mode */}
                            {entryType === 'invoice' && (
                                <div className="p-4 rounded-xl bg-slate-50 border border-slate-200 relative">
                                    <label className="block font-semibold text-slate-700 mb-1">Cari No Faktur Penerimaan</label>
                                    <div className="relative">
                                        <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                        <input
                                            type="text"
                                            placeholder="Ketik nomor faktur (misal: 14_506659 atau nama PBF)..."
                                            value={invoiceSearchQuery}
                                            onChange={(e) => setInvoiceSearchQuery(e.target.value)}
                                            className="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        />
                                    </div>
                                    {invoiceSearchResults.length > 0 && (
                                        <div className="absolute left-4 right-4 top-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-20 max-h-48 overflow-y-auto">
                                            {invoiceSearchResults.map((inv) => (
                                                <button
                                                    key={inv.id}
                                                    type="button"
                                                    onClick={() => selectInvoice(inv)}
                                                    className="w-full text-left px-3.5 py-2 hover:bg-blue-50 flex items-center justify-between border-b border-slate-100 last:border-b-0"
                                                >
                                                    <div>
                                                        <span className="font-bold text-slate-800">{inv.invoice_number}</span>
                                                        <span className="text-slate-400 ml-2">({inv.creditor_name})</span>
                                                    </div>
                                                    <span className="text-[11px] text-slate-400">{inv.invoice_date}</span>
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Faktur Headers */}
                            <div className={`p-4 rounded-xl border space-y-3 ${
                                entryType === 'invoice' && formData.receiving_id
                                    ? 'bg-slate-100/80 border-slate-300'
                                    : 'bg-slate-50 border-slate-200'
                            }`}>
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <div className="flex items-center justify-between mb-1">
                                            <label className="font-semibold text-slate-700">Nomor Faktur</label>
                                            {entryType === 'invoice' && formData.receiving_id && (
                                                <span className="inline-flex items-center gap-1 text-[10px] font-bold text-slate-500 bg-slate-200 px-1.5 py-0.5 rounded">
                                                    <Lock className="w-3 h-3 text-slate-500" /> Terkunci
                                                </span>
                                            )}
                                        </div>
                                        <input
                                            type="text"
                                            required
                                            readOnly={entryType === 'invoice' && !!formData.receiving_id}
                                            placeholder="Contoh: 63250024422"
                                            value={formData.invoice_number}
                                            onChange={(e) => setFormData({ ...formData, invoice_number: e.target.value })}
                                            className={`w-full border rounded-xl px-3 py-2 font-mono ${
                                                entryType === 'invoice' && formData.receiving_id
                                                    ? 'bg-slate-200/70 border-slate-300 text-slate-700 cursor-not-allowed font-bold'
                                                    : 'bg-white border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500'
                                            }`}
                                        />
                                    </div>

                                    <div>
                                        <div className="flex items-center justify-between mb-1">
                                            <label className="font-semibold text-slate-700">Tanggal Faktur</label>
                                            {entryType === 'invoice' && formData.receiving_id && (
                                                <Lock className="w-3 h-3 text-slate-400" />
                                            )}
                                        </div>
                                        <input
                                            type="date"
                                            readOnly={entryType === 'invoice' && !!formData.receiving_id}
                                            value={formData.invoice_date}
                                            onChange={(e) => setFormData({ ...formData, invoice_date: e.target.value })}
                                            className={`w-full border rounded-xl px-3 py-2 ${
                                                entryType === 'invoice' && formData.receiving_id
                                                    ? 'bg-slate-200/70 border-slate-300 text-slate-700 cursor-not-allowed'
                                                    : 'bg-white border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500'
                                            }`}
                                        />
                                    </div>

                                    <div>
                                        <label className="block font-semibold text-slate-700 mb-1">Tanggal Terima Barang</label>
                                        <input
                                            type="date"
                                            value={formData.received_date}
                                            onChange={(e) => setFormData({ ...formData, received_date: e.target.value })}
                                            className="w-full border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        />
                                    </div>
                                </div>

                                <div className="pt-2 border-t border-slate-200/80">
                                    <div className="flex items-center justify-between mb-1">
                                        <label className="font-semibold text-slate-700">PBF / Distributor</label>
                                        {entryType === 'invoice' && formData.receiving_id && (
                                            <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-500">
                                                <Lock className="w-3 h-3 text-slate-400" /> Sesuai Faktur Penerimaan
                                            </span>
                                        )}
                                    </div>
                                    {entryType === 'invoice' && formData.receiving_id ? (
                                        <div className="bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 flex items-center justify-between shadow-xs">
                                            <span className="font-bold text-slate-800 text-sm">
                                                {creditors.find((c) => c.code === formData.creditor_code)?.name || formData.creditor_code || '-'}
                                            </span>
                                            <span className="font-mono text-xs text-slate-400 bg-slate-100 px-2 py-0.5 rounded">
                                                {formData.creditor_code}
                                            </span>
                                        </div>
                                    ) : (
                                        <select
                                            required
                                            value={formData.creditor_code}
                                            onChange={(e) => setFormData({ ...formData, creditor_code: e.target.value })}
                                            className="w-full border rounded-xl px-3.5 py-2.5 bg-white border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium"
                                        >
                                            <option value="">-- Pilih PBF / Distributor --</option>
                                            {creditors.map((c) => (
                                                <option key={c.code} value={c.code}>{c.name}</option>
                                            ))}
                                        </select>
                                    )}
                                </div>
                            </div>

                            {/* Daftar Obat Yang Diretur */}
                            <div>
                                <div className="flex items-center justify-between mb-2">
                                    <div>
                                        <h4 className="font-bold text-slate-800 text-sm">
                                            {entryType === 'invoice'
                                                ? 'Pilih Obat Yang Akan Diretur dari Faktur Ini'
                                                : 'Obat / Barang Yang Diretur'}
                                        </h4>
                                        {entryType === 'invoice' && (
                                            <p className="text-[11px] text-slate-500">
                                                Centang obat yang ingin diretur dan sesuaikan jumlah retur (maksimal sejumlah barang di faktur). Detail obat, batch & harga dikunci sesuai faktur asli.
                                            </p>
                                        )}
                                    </div>

                                    {entryType === 'manual' && (
                                        <div className="relative">
                                            <input
                                                type="text"
                                                placeholder="Ketik & pilih obat dari master..."
                                                value={medSearchQuery}
                                                onChange={(e) => setMedSearchQuery(e.target.value)}
                                                className="border border-slate-200 rounded-xl px-3 py-1.5 text-xs w-64 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            />
                                            {medSearchResults.length > 0 && (
                                                <div className="absolute right-0 top-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-20 w-88 max-h-56 overflow-y-auto">
                                                    {medSearchResults.map((m) => (
                                                        <button
                                                            key={m.id}
                                                            type="button"
                                                            onClick={() => addManualItemRow(m)}
                                                            className="w-full text-left px-3 py-2 hover:bg-blue-50 flex items-center justify-between border-b border-slate-100 last:border-b-0 text-xs"
                                                        >
                                                            <div className="flex-1 pr-2">
                                                                <div className="font-bold text-slate-800">{m.name}</div>
                                                                <div className="text-[10px] text-slate-400">
                                                                    {m.factory_name ? `${m.factory_name} • ` : ''}{m.unit} • {formatRupiah(m.raw_price)}
                                                                </div>
                                                                {m.return_policy && (
                                                                    <div className="mt-0.5">
                                                                        <span className={`inline-block px-1.5 py-0.2 rounded text-[9px] font-bold border ${m.return_policy.badge}`}>
                                                                            {m.return_policy.label}
                                                                        </span>
                                                                    </div>
                                                                )}
                                                            </div>
                                                            <Plus className="w-4 h-4 text-blue-600 shrink-0" />
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="border border-slate-200 rounded-xl overflow-hidden">
                                    <table className="w-full text-left text-xs text-slate-600">
                                        <thead className="bg-slate-100 text-slate-700 font-semibold uppercase text-[10px]">
                                            <tr>
                                                {entryType === 'invoice' && (
                                                    <th className="py-2.5 px-3 w-10 text-center">
                                                        <input
                                                            type="checkbox"
                                                            title="Pilih Semua"
                                                            onChange={(e) => toggleSelectAll(e.target.checked)}
                                                            className="rounded text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                        />
                                                    </th>
                                                )}
                                                <th className="py-2.5 px-3">Nama Obat</th>
                                                <th className="py-2.5 px-2 w-36 text-center">Ketentuan Retur</th>
                                                <th className="py-2.5 px-2 w-24 text-center">
                                                    {entryType === 'invoice' ? 'Qty Retur / Faktur' : 'Jumlah'}
                                                </th>
                                                <th className="py-2.5 px-2 w-16 text-center">Satuan</th>
                                                <th className="py-2.5 px-2 w-28">Batch</th>
                                                <th className="py-2.5 px-2 w-28">Expired Date</th>
                                                <th className="py-2.5 px-2 w-24 text-right">Harga Beli</th>
                                                <th className="py-2.5 px-2 w-32">Keterangan</th>
                                                <th className="py-2.5 px-2 w-10"></th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {formData.items.length === 0 ? (
                                                <tr>
                                                    <td colSpan={entryType === 'invoice' ? 10 : 9} className="text-center py-8 text-slate-400">
                                                        {entryType === 'invoice'
                                                            ? 'Cari dan pilih nomor faktur penerimaan di atas untuk memuat daftar obat.'
                                                            : (
                                                                <>
                                                                    Belum ada obat yang dimasukkan. Cari obat di atas atau klik{' '}
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => addManualItemRow()}
                                                                        className="text-blue-600 font-bold underline"
                                                                    >
                                                                        + Tambah Baris Kosong
                                                                    </button>
                                                                </>
                                                            )}
                                                    </td>
                                                </tr>
                                            ) : (
                                                formData.items.map((row, idx) => {
                                                    const isFromInvoice = entryType === 'invoice';
                                                    return (
                                                        <tr
                                                            key={idx}
                                                            className={`transition-colors ${
                                                                isFromInvoice
                                                                    ? row.selected
                                                                        ? 'bg-blue-50/70 font-medium'
                                                                        : 'bg-white opacity-70 hover:opacity-100 hover:bg-slate-50'
                                                                    : 'hover:bg-slate-50'
                                                            }`}
                                                        >
                                                            {isFromInvoice && (
                                                                <td className="py-2 px-3 text-center">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={!!row.selected}
                                                                        onChange={(e) => updateItemRow(idx, 'selected', e.target.checked)}
                                                                        className="rounded text-blue-600 focus:ring-blue-500 cursor-pointer w-4 h-4"
                                                                    />
                                                                </td>
                                                            )}
                                                            <td className="py-2 px-3">
                                                                {isFromInvoice ? (
                                                                    <div>
                                                                        <div className="font-bold text-slate-800 flex items-center gap-1.5">
                                                                            <Lock className="w-3 h-3 text-slate-400 shrink-0" />
                                                                            <span>{row.medicine_name}</span>
                                                                        </div>
                                                                        {row.factory_name && (
                                                                            <div className="text-[10px] text-slate-400 font-medium pl-4.5">
                                                                                Pabrik: {row.factory_name}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                ) : (
                                                                    <div>
                                                                        <input
                                                                            type="text"
                                                                            required
                                                                            placeholder="Nama obat..."
                                                                            value={row.medicine_name}
                                                                            onChange={(e) => updateItemRow(idx, 'medicine_name', e.target.value)}
                                                                            className="w-full border border-slate-200 rounded-lg px-2 py-1 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                                        />
                                                                        {row.factory_name && (
                                                                            <div className="text-[10px] text-slate-400 font-medium mt-0.5">
                                                                                Pabrik: {row.factory_name}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                )}
                                                            </td>
                                                            <td className="py-2 px-2 text-center">
                                                                {row.return_policy ? (
                                                                    <div className="inline-flex flex-col items-center">
                                                                        <span
                                                                            className={`inline-block px-2 py-0.5 rounded text-[10px] font-bold border ${row.return_policy.badge}`}
                                                                            title={row.return_policy.message}
                                                                        >
                                                                            {row.return_policy.label}
                                                                        </span>
                                                                        {row.return_policy.policy?.notes && (
                                                                            <span className="text-[9px] text-slate-500 mt-0.5 max-w-[120px] truncate" title={row.return_policy.policy.notes}>
                                                                                {row.return_policy.policy.notes}
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                ) : (
                                                                    <span className="text-slate-300 text-[10px]">-</span>
                                                                )}
                                                            </td>
                                                            <td className="py-2 px-2 text-center">
                                                                <div className="flex items-center justify-center gap-1">
                                                                    <input
                                                                        type="number"
                                                                        min="0.1"
                                                                        max={isFromInvoice ? (row.max_qty || 9999) : undefined}
                                                                        step="any"
                                                                        required
                                                                        disabled={isFromInvoice && !row.selected}
                                                                        value={row.qty === '' ? '' : row.qty}
                                                                        onFocus={(e) => e.target.select()}
                                                                        onChange={(e) => {
                                                                            let raw = e.target.value;
                                                                            if (raw === '') {
                                                                                updateItemRow(idx, 'qty', '');
                                                                                return;
                                                                            }
                                                                            let cleaned = raw.replace(/^0+(?=\d)/, '');
                                                                            let val = parseFloat(cleaned);
                                                                            if (isNaN(val)) val = 0;
                                                                            if (isFromInvoice && row.max_qty && val > row.max_qty) {
                                                                                val = row.max_qty;
                                                                            }
                                                                            updateItemRow(idx, 'qty', val);
                                                                        }}
                                                                        className={`w-14 border rounded-lg px-1.5 py-1 text-xs text-center font-bold ${
                                                                            isFromInvoice && !row.selected
                                                                                ? 'bg-slate-100 text-slate-400 border-slate-200'
                                                                                : 'border-blue-300 text-blue-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500'
                                                                        }`}
                                                                    />
                                                                    {isFromInvoice && (
                                                                        <span className="text-[11px] text-slate-400 font-normal">
                                                                            / {row.max_qty}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </td>
                                                            <td className="py-2 px-2 text-center">
                                                                {isFromInvoice ? (
                                                                    <span className="font-semibold text-slate-700 uppercase text-[11px]">
                                                                        {row.unit || 'BOX'}
                                                                    </span>
                                                                ) : (
                                                                    <select
                                                                        value={row.unit}
                                                                        onChange={(e) => updateItemRow(idx, 'unit', e.target.value)}
                                                                        className="w-full border border-slate-200 rounded-lg px-1 py-1 text-xs text-center uppercase focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                                    >
                                                                        <option value="BOX">BOX</option>
                                                                        <option value="BTL">BTL</option>
                                                                        <option value="TUBE">TUBE</option>
                                                                        <option value="PCS">PCS</option>
                                                                        <option value="STRIP">STRIP</option>
                                                                        <option value="AMP">AMP</option>
                                                                        <option value="VIAL">VIAL</option>
                                                                    </select>
                                                                )}
                                                            </td>
                                                            <td className="py-2 px-2">
                                                                {isFromInvoice ? (
                                                                    <span className="font-mono text-slate-700 text-[11px]">
                                                                        {row.batch_number || '-'}
                                                                    </span>
                                                                ) : (
                                                                    <input
                                                                        type="text"
                                                                        placeholder="No batch..."
                                                                        value={row.batch_number}
                                                                        onChange={(e) => updateItemRow(idx, 'batch_number', e.target.value)}
                                                                        className="w-full border border-slate-200 rounded-lg px-2 py-1 text-xs font-mono focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                                    />
                                                                )}
                                                            </td>
                                                            <td className="py-2 px-2">
                                                                {isFromInvoice ? (
                                                                    <span className="text-slate-700 text-[11px]">
                                                                        {row.expired_date || '-'}
                                                                    </span>
                                                                ) : (
                                                                    <input
                                                                        type="date"
                                                                        value={row.expired_date}
                                                                        onChange={(e) => updateItemRow(idx, 'expired_date', e.target.value)}
                                                                        className="w-full border border-slate-200 rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                                    />
                                                                )}
                                                            </td>
                                                            <td className="py-2 px-2 text-right">
                                                                {isFromInvoice ? (
                                                                    <span className="font-mono text-slate-800 font-semibold text-[11px]">
                                                                        {formatRupiah(row.unit_price)}
                                                                    </span>
                                                                ) : (
                                                                    <input
                                                                        type="number"
                                                                        min="0"
                                                                        value={row.unit_price === '' ? '' : row.unit_price}
                                                                        onFocus={(e) => e.target.select()}
                                                                        onChange={(e) => {
                                                                            let raw = e.target.value;
                                                                            if (raw === '') {
                                                                                updateItemRow(idx, 'unit_price', '');
                                                                                return;
                                                                            }
                                                                            let cleaned = raw.replace(/^0+(?=\d)/, '');
                                                                            let val = parseFloat(cleaned);
                                                                            updateItemRow(idx, 'unit_price', isNaN(val) ? 0 : val);
                                                                        }}
                                                                        className="w-full border border-slate-200 rounded-lg px-2 py-1 text-xs text-right font-mono focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                                    />
                                                                )}
                                                            </td>
                                                            <td className="py-2 px-2">
                                                                <select
                                                                    disabled={isFromInvoice && !row.selected}
                                                                    value={row.reason}
                                                                    onChange={(e) => updateItemRow(idx, 'reason', e.target.value)}
                                                                    className="w-full border border-slate-200 rounded-lg px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500 bg-white"
                                                                >
                                                                    <option value="ED DEKAT">ED DEKAT</option>
                                                                    <option value="SALAH PESAN">SALAH PESAN</option>
                                                                    <option value="DISKON TIDAK SESUAI">DISKON TIDAK SESUAI</option>
                                                                    <option value="TERDOUBLE">TERDOUBLE</option>
                                                                    <option value="PESAN YANG INJ">PESAN YANG INJ</option>
                                                                    <option value="RUSAK">RUSAK</option>
                                                                </select>
                                                            </td>
                                                            <td className="py-2 px-2 text-center">
                                                                {!isFromInvoice && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => removeItemRow(idx)}
                                                                        className="p-1 text-slate-400 hover:text-rose-600 rounded-lg"
                                                                    >
                                                                        <Trash2 className="w-3.5 h-3.5" />
                                                                    </button>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    );
                                                })
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                                {entryType === 'manual' && (
                                    <div className="mt-2 flex justify-end">
                                        <button
                                            type="button"
                                            onClick={() => addManualItemRow()}
                                            className="text-xs font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-1"
                                        >
                                            <Plus className="w-3.5 h-3.5" /> Tambah Baris Obat Lain
                                        </button>
                                    </div>
                                )}
                            </div>

                            {/* Catatan / Keterangan Umum */}
                            <div>
                                <label className="block font-semibold text-slate-700 mb-1">Catatan Tambahan (Opsional)</label>
                                <textarea
                                    rows="2"
                                    placeholder="Contoh: Titipan retur sudah diserahkan ke sales saat kunjungan..."
                                    value={formData.notes}
                                    onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            </div>

                            {/* Modal Footer (Fixed at bottom) */}
                            <div className="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between gap-3 shrink-0">
                                <div className="text-xs text-slate-500">
                                    {entryType === 'invoice' ? (
                                        <span>
                                            Dipilih: <strong className="text-blue-700 font-bold">{formData.items.filter(it => it.selected).length}</strong> dari {formData.items.length} obat faktur
                                        </span>
                                    ) : (
                                        <span>
                                            Total: <strong className="text-blue-700 font-bold">{formData.items.length}</strong> obat yang diretur
                                        </span>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setCreateModalOpen(false)}
                                        className="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-200/80 font-semibold transition-all text-xs"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        type="submit"
                                        className="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow-xs transition-all text-xs"
                                    >
                                        Simpan Retur Pembelian
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Update Item (Tgl Diambil & Potong Tagihan) */}
            {editItemModalOpen && editingItem && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl shadow-xl w-full max-w-md border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                        <div className="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                            <div>
                                <h3 className="font-bold text-slate-800 text-sm">Update Status Retur Obat</h3>
                                <p className="text-xs text-slate-500 font-semibold truncate max-w-xs">{editingItem.medicine_name}</p>
                            </div>
                            <button
                                onClick={() => setEditItemModalOpen(false)}
                                className="p-1 rounded-lg hover:bg-slate-200 text-slate-400 hover:text-slate-700"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleUpdateItemSubmit} className="p-5 space-y-4 text-xs">
                            <div>
                                <label className="block font-semibold text-slate-700 mb-1">Tanggal Barang Diambil PBF</label>
                                <input
                                    type="date"
                                    value={editItemForm.pickup_date}
                                    onChange={(e) => setEditItemForm({ ...editItemForm, pickup_date: e.target.value })}
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label className="block font-semibold text-slate-700 mb-1">Status Potong Tagihan</label>
                                <input
                                    type="text"
                                    placeholder="Contoh: SUDAH POTONG TAGIHAN (20/7/2026)"
                                    value={editItemForm.deduction_note}
                                    onChange={(e) => setEditItemForm({ ...editItemForm, deduction_note: e.target.value })}
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium"
                                />
                                <div className="mt-1 flex gap-1">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            const today = new Date().toLocaleDateString('id-ID');
                                            setEditItemForm({ ...editItemForm, deduction_note: `SUDAH POTONG TAGIHAN (${today})` });
                                        }}
                                        className="text-[10px] text-blue-600 bg-blue-50 hover:bg-blue-100 px-2 py-0.5 rounded font-semibold"
                                    >
                                        Set Potong Hari Ini
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setEditItemForm({ ...editItemForm, deduction_note: '' })}
                                        className="text-[10px] text-slate-500 hover:bg-slate-100 px-2 py-0.5 rounded"
                                    >
                                        Kosongkan
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label className="block font-semibold text-slate-700 mb-1">Keterangan Alasan Retur</label>
                                <select
                                    value={editItemForm.reason}
                                    onChange={(e) => setEditItemForm({ ...editItemForm, reason: e.target.value })}
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="ED DEKAT">ED DEKAT</option>
                                    <option value="SALAH PESAN">SALAH PESAN</option>
                                    <option value="DISKON TIDAK SESUAI">DISKON TIDAK SESUAI</option>
                                    <option value="TERDOUBLE">TERDOUBLE</option>
                                    <option value="PESAN YANG INJ">PESAN YANG INJ</option>
                                    <option value="RUSAK">RUSAK</option>
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block font-semibold text-slate-700 mb-1">Jumlah (Qty)</label>
                                    <input
                                        type="number"
                                        min="0.01"
                                        step="any"
                                        value={editItemForm.qty === '' ? '' : editItemForm.qty}
                                        onFocus={(e) => e.target.select()}
                                        onChange={(e) => {
                                            let raw = e.target.value;
                                            if (raw === '') {
                                                setEditItemForm({ ...editItemForm, qty: '' });
                                                return;
                                            }
                                            let cleaned = raw.replace(/^0+(?=\d)/, '');
                                            let val = parseFloat(cleaned);
                                            setEditItemForm({ ...editItemForm, qty: isNaN(val) ? '' : val });
                                        }}
                                        className="w-full border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 font-bold"
                                    />
                                </div>
                                <div>
                                    <label className="block font-semibold text-slate-700 mb-1">Harga Satuan</label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={editItemForm.unit_price === '' ? '' : editItemForm.unit_price}
                                        onFocus={(e) => e.target.select()}
                                        onChange={(e) => {
                                            let raw = e.target.value;
                                            if (raw === '') {
                                                setEditItemForm({ ...editItemForm, unit_price: '' });
                                                return;
                                            }
                                            let cleaned = raw.replace(/^0+(?=\d)/, '');
                                            let val = parseFloat(cleaned);
                                            setEditItemForm({ ...editItemForm, unit_price: isNaN(val) ? 0 : val });
                                        }}
                                        className="w-full border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                                    />
                                </div>
                            </div>

                            <div className="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setEditItemModalOpen(false)}
                                    className="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 font-semibold transition-all"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow-xs transition-all"
                                >
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </FinanceLayout>
    );
}
