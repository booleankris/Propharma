import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import FinanceLayout from './Layouts/FinanceLayout';
import {
    Undo2, Search, Filter, Plus, Download, Edit2, Trash2, CheckCircle2,
    Calendar, AlertTriangle, Clock, Building2, Package, X, Check, ArrowRight, Lock, RotateCcw,
    AlertCircle, Sparkles, ShieldAlert
} from 'lucide-react';
import { formatRupiah } from './Components/Utils';

export default function PurchaseReturns({
    activeTab = 'monitoring',
    items,
    filters,
    stats,
    approachingItems = { data: [] },
    approachingStats = {},
    approachingFilters = {},
    creditors = [],
    branchContext,
}) {
    const [currentTab, setCurrentTab] = useState(activeTab || 'monitoring');

    // Monitoring filters
    const [search, setSearch] = useState(filters.search || '');
    const [creditorFilter, setCreditorFilter] = useState(filters.creditor_code || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [reasonFilter, setReasonFilter] = useState(filters.reason || '');

    // Approaching filters
    const [approachingSearch, setApproachingSearch] = useState(approachingFilters.search || '');
    const [approachingCreditor, setApproachingCreditor] = useState(approachingFilters.creditor_code || '');
    const [approachingUrgency, setApproachingUrgency] = useState(approachingFilters.urgency || '');

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

    const toSecureUrl = (url) => {
        if (!url) return null;
        if (typeof window !== 'undefined' && window.location.protocol === 'https:' && url.startsWith('http://')) {
            return url.replace('http://', 'https://');
        }
        return url;
    };

    // Format display tanggal rapi (DD/MM/YYYY)
    const formatDateDisplay = (val) => {
        if (!val) return '-';
        const datePart = String(val).split('T')[0];
        const parts = datePart.split('-');
        if (parts.length === 3) {
            const [y, m, d] = parts;
            return `${d}/${m}/${y}`;
        }
        return val;
    };

    const getStatusBadge = (status) => {
        switch (status) {
            case 'dikonfirmasi':
                return {
                    label: 'Sudah Dikonfirmasi',
                    badgeClass: 'bg-blue-100 text-blue-800 border-blue-200',
                    dotClass: 'bg-blue-500',
                };
            case 'sudah_diambil':
                return {
                    label: 'Sudah Diambil',
                    badgeClass: 'bg-cyan-100 text-cyan-800 border-cyan-200',
                    dotClass: 'bg-cyan-500',
                };
            case 'sudah_potong':
                return {
                    label: 'Sudah Potong Tagihan',
                    badgeClass: 'bg-emerald-100 text-emerald-800 border-emerald-200',
                    dotClass: 'bg-emerald-500',
                };
            case 'barang_kembali_pmi':
                return {
                    label: 'Barang Kembali PMI',
                    badgeClass: 'bg-purple-100 text-purple-800 border-purple-200',
                    dotClass: 'bg-purple-500',
                };
            case 'diajukan':
            default:
                return {
                    label: 'Diajukan Retur',
                    badgeClass: 'bg-amber-100 text-amber-800 border-amber-200',
                    dotClass: 'bg-amber-500',
                };
        }
    };

    // Modal Konfirmasi Retur (Kurangi Stok)
    const [confirmModalOpen, setConfirmModalOpen] = useState(false);
    const [confirmingItem, setConfirmingItem] = useState(null);
    const [confirmForm, setConfirmForm] = useState({
        confirmed_date: new Date().toISOString().split('T')[0],
        notes: '',
    });

    const openConfirmModal = (item) => {
        setConfirmingItem(item);
        setConfirmForm({
            confirmed_date: new Date().toISOString().split('T')[0],
            notes: '',
        });
        setConfirmModalOpen(true);
    };

    const handleConfirmSubmit = (e) => {
        e.preventDefault();
        router.post(`/finance/purchase-returns/items/${confirmingItem.id}/confirm`, confirmForm, {
            onSuccess: () => setConfirmModalOpen(false),
        });
    };

    // Modal Ubah Status Retur
    const [statusModalOpen, setStatusModalOpen] = useState(false);
    const [statusItem, setStatusItem] = useState(null);
    const [statusForm, setStatusForm] = useState({
        status: 'dikonfirmasi',
        status_date: new Date().toISOString().split('T')[0],
        notes: '',
    });

    const openStatusModal = (item) => {
        setStatusItem(item);
        setStatusForm({
            status: item.status && item.status !== 'diajukan' ? item.status : 'dikonfirmasi',
            status_date: item.status_date ? String(item.status_date).split('T')[0] : new Date().toISOString().split('T')[0],
            notes: item.notes || '',
        });
        setStatusModalOpen(true);
    };

    const handleStatusSubmit = (e) => {
        e.preventDefault();
        router.post(`/finance/purchase-returns/items/${statusItem.id}/change-status`, statusForm, {
            onSuccess: () => setStatusModalOpen(false),
        });
    };

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
            tab: currentTab,
            search,
            creditor_code: creditor,
            status,
            reason,
        }, { preserveState: true });
    };

    const handleTabSwitch = (newTab) => {
        setCurrentTab(newTab);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', newTab);
        window.history.replaceState({}, '', url.toString());
    };

    const handleApproachingSearchSubmit = (e) => {
        if (e) e.preventDefault();
        router.get('/finance/purchase-returns', {
            tab: 'approaching',
            approaching_search: approachingSearch,
            approaching_creditor: approachingCreditor,
            approaching_urgency: approachingUrgency,
        }, { preserveState: true });
    };

    const handleApproachingFilterChange = (cred, urg) => {
        router.get('/finance/purchase-returns', {
            tab: 'approaching',
            approaching_search: approachingSearch,
            approaching_creditor: cred,
            approaching_urgency: urg,
        }, { preserveState: true });
    };

    const handleApplyReturnFromAlert = (item) => {
        setEntryType('manual');
        const hasPack = Boolean(item.has_pack);
        const content = Number(item.content || 1);
        const packaging = item.packaging || (content > 1 ? 'BOX' : (item.unit || 'PCS'));
        const stockUnit = item.stock_unit || (content > 1 ? 'PCS' : (item.unit || 'PCS'));
        const returType = item.retur_type || (hasPack ? 'packaging' : 'unit');

        setFormData({
            invoice_number: item.invoice_number && item.invoice_number !== '-' ? item.invoice_number : '',
            invoice_date: item.invoice_date || new Date().toISOString().split('T')[0],
            received_date: new Date().toISOString().split('T')[0],
            creditor_code: item.creditor_code || (creditors[0]?.code || ''),
            receiving_id: item.receiving_id || null,
            notes: `Pengajuan retur ED sesuai ketentuan: ${item.rule_text}`,
            items: [{
                selected: true,
                medicine_id: item.medicine_id,
                batch_id: item.batch_id || null,
                medicine_name: item.medicine_name,
                unit: returType === 'packaging' ? packaging : stockUnit,
                retur_type: returType,
                stock_unit_factor: returType === 'packaging' ? content : 1,
                packaging: packaging,
                stock_unit: stockUnit,
                content: content,
                has_pack: hasPack,
                pack_price: Number(item.pack_price || 0),
                price_per_unit: Number(item.price_per_unit || 0),
                current_stock_pack: Number(item.stock_pack || 0),
                current_stock_unit: Number(item.stock || 0),
                batch_number: item.batch_number || '',
                expired_date: item.expired_date || '',
                max_qty: returType === 'packaging' ? Number(item.stock_pack || 1) : Number(item.stock || 1),
                qty: returType === 'packaging' ? Number(item.stock_pack || 1) : Number(item.stock || 1),
                unit_price: Number(item.unit_price || 0),
                reason: 'ED DEKAT',
                pickup_date: '',
                deduction_note: '',
                factory_id: item.factory_id,
                factory_name: item.factory_name,
                return_policy: null,
                is_already_returned: false,
            }],
        });
        setCreateModalOpen(true);
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
                .catch(() => { });
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
                        selected: !it.is_already_returned, // Default selected if not returned
                        medicine_id: it.medicine_id,
                        batch_id: it.batch_id || null,
                        medicine_name: it.medicine_name,
                        unit: it.unit || 'PCS',
                        retur_type: it.retur_type || 'unit',
                        stock_unit_factor: it.stock_unit_factor || 1,
                        packaging: it.packaging || 'BOX',
                        stock_unit: it.stock_unit || 'PCS',
                        content: it.content || 1,
                        has_pack: Boolean(it.has_pack),
                        pack_price: Number(it.pack_price || 0),
                        price_per_unit: Number(it.price_per_unit || 0),
                        qty_received_pack: Number(it.qty_received_pack || 0),
                        qty_received_unit: Number(it.qty_received_unit || 0),
                        current_stock_pack: Number(it.current_stock_pack || 0),
                        current_stock_unit: Number(it.current_stock_unit || 0),
                        batch_number: it.batch || '-',
                        expired_date: it.expired_date || '',
                        max_qty: Number(it.qty || 1),
                        qty: Number(it.qty || 1),
                        unit_price: Number(it.unit_price || it.raw_price || 0),
                        reason: 'ED DEKAT',
                        pickup_date: '',
                        deduction_note: '',
                        factory_id: it.factory_id,
                        factory_name: it.factory_name,
                        return_policy: it.return_policy,
                        is_already_returned: !!it.is_already_returned,
                    })),
                });
                setInvoiceSearchResults([]);
                setInvoiceSearchQuery(inv.invoice_number);
            });
    };

    const addManualItemRow = (med = null) => {
        const hasPack = med ? Boolean(med.has_pack) : false;
        const content = med ? Number(med.content || 1) : 1;
        const packaging = med ? (med.packaging || (content > 1 ? 'BOX' : (med.unit || 'PCS'))) : 'BOX';
        const stockUnit = med ? (med.stock_unit || (content > 1 ? 'PCS' : (med.unit || 'PCS'))) : 'PCS';
        const returType = hasPack ? 'packaging' : 'unit';

        const newItem = {
            selected: true,
            medicine_id: med ? med.id : null,
            batch_id: null,
            medicine_name: med ? med.name : '',
            unit: returType === 'packaging' ? packaging : stockUnit,
            retur_type: returType,
            stock_unit_factor: returType === 'packaging' ? content : 1,
            packaging: packaging,
            stock_unit: stockUnit,
            content: content,
            has_pack: hasPack,
            pack_price: med ? Number(med.pack_price || med.raw_price || 0) : 0,
            price_per_unit: med ? Number(med.price_per_unit || (content > 1 ? (med.raw_price / content) : med.raw_price) || 0) : 0,
            current_stock_pack: med ? Number(med.current_stock_pack || 0) : 0,
            current_stock_unit: med ? Number(med.current_stock_unit || med.stock || 0) : 0,
            batch_number: '',
            expired_date: '',
            max_qty: 9999,
            qty: 1,
            unit_price: returType === 'packaging' ? (med ? Number(med.pack_price || med.raw_price || 0) : 0) : (med ? Number(med.price_per_unit || 0) : 0),
            reason: 'ED DEKAT',
            pickup_date: '',
            deduction_note: '',
            factory_id: med ? med.factory_id : null,
            factory_name: med ? med.factory_name : null,
            return_policy: med ? med.return_policy : null,
            is_already_returned: false,
        };
        setFormData({
            ...formData,
            items: [...formData.items, newItem],
        });
        setMedSearchQuery('');
        setMedSearchResults([]);
    };

    const switchItemReturType = (index, targetType) => {
        const updated = [...formData.items];
        const it = updated[index];
        if (!it || it.retur_type === targetType) return;

        if (targetType === 'packaging') {
            it.retur_type = 'packaging';
            it.unit = it.packaging || 'BOX';
            it.stock_unit_factor = it.content || 1;
            it.unit_price = it.pack_price;
            if (it.content > 1) {
                it.qty = Math.round((Number(it.qty) / it.content) * 100) / 100;
                if (it.max_qty && it.max_qty !== 9999) {
                    it.max_qty = it.qty_received_pack || Math.round((Number(it.max_qty) / it.content) * 100) / 100;
                }
            }
        } else {
            it.retur_type = 'unit';
            it.unit = it.stock_unit || 'PCS';
            it.stock_unit_factor = 1;
            it.unit_price = it.price_per_unit;
            if (it.content > 1) {
                it.qty = Math.round(Number(it.qty) * it.content);
                if (it.max_qty && it.max_qty !== 9999) {
                    it.max_qty = it.qty_received_unit || Math.round(Number(it.max_qty) * it.content);
                }
            }
        }
        setFormData({ ...formData, items: updated });
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
        const updated = formData.items.map(it => {
            if (it.is_already_returned) return { ...it, selected: false };
            return { ...it, selected: checked };
        });
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

            <div className="mx-auto space-y-6">
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

                {/* TAB NAVIGASI UTAMA */}
                <div className="flex items-center gap-2 border-b border-slate-200">
                    <button
                        type="button"
                        onClick={() => handleTabSwitch('monitoring')}
                        className={`inline-flex items-center gap-2 px-5 py-3 text-xs font-bold border-b-2 transition-all cursor-pointer ${currentTab === 'monitoring'
                            ? 'border-blue-600 text-blue-700 bg-white rounded-t-xl shadow-2xs'
                            : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'
                            }`}
                    >
                        <Undo2 className="w-4 h-4" />
                        <span>Monitoring Retur Beli</span>
                        <span className="px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-700 font-semibold">
                            {stats.total_items || 0}
                        </span>
                    </button>
                    <button
                        type="button"
                        onClick={() => handleTabSwitch('approaching')}
                        className={`inline-flex items-center gap-2 px-5 py-3 text-xs font-bold border-b-2 transition-all cursor-pointer ${currentTab === 'approaching'
                            ? 'border-amber-500 text-amber-800 bg-white rounded-t-xl shadow-2xs'
                            : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'
                            }`}
                    >
                        <AlertTriangle className="w-4 h-4 text-amber-500" />
                        <span>Mendekati Masa Retur (Ketentuan PBF)</span>
                        {(approachingStats?.total_count || 0) > 0 && (
                            <span className="px-2.5 py-0.5 rounded-full text-[10px] bg-amber-100 text-amber-800 font-bold border border-amber-300">
                                {approachingStats.total_count} Obat
                            </span>
                        )}
                    </button>
                </div>

                {currentTab === 'monitoring' ? (
                    <>
                        {/* Metrics Summary Cards */}
                        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                        <span className="text-xs font-medium text-slate-500">Total Item Diretur</span>
                        <div className="text-xl font-bold text-slate-800 mt-1">{stats.total_items} Obat</div>
                        <div className="text-[11px] text-slate-400 mt-0.5 truncate">Nilai: {formatRupiah(stats.total_value)}</div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-amber-200/80 shadow-xs bg-amber-50/20">
                        <span className="text-xs font-semibold text-amber-700">Diajukan Retur</span>
                        <div className="text-xl font-bold text-amber-700 mt-1">{stats.diajukan || 0} Item</div>
                        <div className="text-[11px] text-amber-600 mt-0.5">Menunggu konfirmasi</div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-blue-200/80 shadow-xs bg-blue-50/20">
                        <span className="text-xs font-semibold text-blue-700">Sudah Dikonfirmasi</span>
                        <div className="text-xl font-bold text-blue-700 mt-1">{stats.dikonfirmasi || 0} Item</div>
                        <div className="text-[11px] text-blue-600 mt-0.5">Stok telah dipotong</div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-emerald-200/80 shadow-xs bg-emerald-50/20">
                        <span className="text-xs font-semibold text-emerald-700">Sudah Potong Tagihan</span>
                        <div className="text-xl font-bold text-emerald-700 mt-1">{stats.sudah_potong || 0} Item</div>
                        <div className="text-[11px] text-emerald-600 mt-0.5">Faktur telah dikurangi</div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-purple-200/80 shadow-xs bg-purple-50/20 col-span-2 sm:col-span-1">
                        <span className="text-xs font-semibold text-purple-700">Barang Kembali PMI</span>
                        <div className="text-xl font-bold text-purple-700 mt-1">{stats.barang_kembali_pmi || 0} Item</div>
                        <div className="text-[11px] text-purple-600 mt-0.5">Pengembalian PMI</div>
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
                                className="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium"
                            >
                                <option value="">Semua Status Retur</option>
                                <option value="diajukan">Diajukan Retur</option>
                                <option value="dikonfirmasi">Sudah Dikonfirmasi</option>
                                <option value="sudah_diambil">Sudah Diambil</option>
                                <option value="sudah_potong">Sudah Potong Tagihan</option>
                                <option value="barang_kembali_pmi">Barang Kembali PMI</option>
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
                                <option value="Barang Rusak">Barang Rusak</option>
                                <option value="Salah Barang">Salah Barang</option>
                                <option value="Salah Pesan">Salah Pesan</option>
                                <option value="Recall">Recall</option>
                                <option value="Retur Pabrik">Retur Pabrik</option>
                                <option value="Double">Double</option>
                                <option value="dlsbnya">dlsbnya</option>
                            </select>
                        </div>
                    </div>

                    {/* Table View */}
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs text-slate-600">
                            <thead className="bg-slate-100/70 text-slate-700 font-semibold uppercase text-[11px] tracking-wider border-b border-slate-200">
                                <tr>
                                    <th className="py-3 px-2 w-8 text-center">No</th>
                                    <th className="py-3 px-3 min-w-[180px]">Nama Obat & Pabrik</th>
                                    <th className="py-3 px-2 text-center w-20">Qty</th>
                                    <th className="py-3 px-3 text-center min-w-[110px]">Batch & ED</th>
                                    <th className="py-3 px-3 min-w-[150px]">No Faktur & PBF</th>
                                    <th className="py-3 px-2 text-center min-w-[120px]">Ketentuan Retur</th>
                                    <th className="py-3 px-2 text-center whitespace-nowrap">Tgl Faktur</th>
                                    <th className="py-3 px-3 text-center whitespace-nowrap">Keterangan</th>
                                    <th className="py-3 px-3 text-center min-w-[155px]">Status Retur</th>
                                    <th className="py-3 px-3 text-center min-w-[210px] sticky right-0 bg-slate-100 z-10 shadow-[-6px_0_10px_-2px_rgba(0,0,0,0.06)]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {items.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="10" className="text-center py-12 text-slate-400">
                                            Belum ada data monitoring retur beli. Klik tombol <strong>Input Retur Baru</strong> untuk memulai.
                                        </td>
                                    </tr>
                                ) : (
                                    items.data.map((item, idx) => {
                                        const p = item.purchase_return;
                                        const hasDeduction = !!item.deduction_note || item.status === 'sudah_potong';
                                        const pbfName = p && p.creditor ? p.creditor.name : (p?.creditor_code || '-');
                                        const policyInfo = item.return_policy_info;
                                        const isDiajukan = !item.status || item.status === 'diajukan';
                                        const statusBadge = getStatusBadge(item.status);

                                        const dateToShow = item.status === 'dikonfirmasi'
                                            ? (item.confirmed_date || item.status_date)
                                            : item.status === 'sudah_potong'
                                                ? (item.deduction_date || item.status_date)
                                                : item.status === 'barang_kembali_pmi'
                                                    ? (item.pickup_date || item.status_date)
                                                    : (item.status_date || item.created_at);

                                        return (
                                            <tr
                                                key={item.id}
                                                className={`transition-colors hover:bg-amber-50/30 group ${hasDeduction ? 'bg-emerald-50/30' : ''
                                                    }`}
                                            >
                                                <td className="py-2.5 px-2 text-center text-slate-400 font-mono text-[11px]">
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
                                                <td className="py-2.5 px-2 text-center whitespace-nowrap">
                                                    <span className="font-extrabold text-blue-700 text-xs">{Number(item.qty)}</span>{' '}
                                                    <span className="text-[10px] font-semibold text-slate-500 uppercase">{item.unit || 'PCS'}</span>
                                                    {item.retur_type === 'packaging' && Number(item.stock_unit_factor) > 1 && (
                                                        <div className="text-[10px] text-slate-400 font-medium">
                                                            ({Number(item.qty) * Number(item.stock_unit_factor)} {item.medicine?.unit || 'TAB'})
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="py-2.5 px-3 text-center whitespace-nowrap">
                                                    <div className="font-mono text-slate-700 text-xs font-semibold">{item.batch_number || '-'}</div>
                                                    <div className="text-[10px] font-medium text-rose-600 mt-0.5">ED: {formatDateDisplay(item.expired_date)}</div>
                                                </td>
                                                <td className="py-2.5 px-3">
                                                    <div className="font-mono font-bold text-slate-800 text-xs">
                                                        {p ? p.invoice_number : '-'}
                                                    </div>
                                                    <div className="text-[11px] font-semibold text-blue-800 mt-0.5 break-words line-clamp-1" title={pbfName}>
                                                        {pbfName}
                                                    </div>
                                                </td>
                                                <td className="py-2.5 px-2 text-center">
                                                    {policyInfo ? (
                                                        <div className="inline-flex flex-col items-center">
                                                            <span
                                                                className={`inline-block px-2 py-0.5 rounded text-[10px] font-bold border ${policyInfo.badge}`}
                                                                title={policyInfo.message}
                                                            >
                                                                {policyInfo.label}
                                                            </span>
                                                            {policyInfo.policy?.notes && (
                                                                <span className="text-[9px] text-slate-500 mt-0.5 max-w-[120px] truncate" title={policyInfo.policy.notes}>
                                                                    {policyInfo.policy.notes}
                                                                </span>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <span className="text-slate-300 text-[11px]">-</span>
                                                    )}
                                                </td>
                                                <td className="py-2.5 px-2 text-center text-slate-600 whitespace-nowrap text-xs">
                                                    {formatDateDisplay(p?.invoice_date || item.created_at)}
                                                </td>
                                                <td className="py-2.5 px-3 text-center whitespace-nowrap">
                                                    <span className="inline-block px-2.5 py-1 rounded-md text-[10px] font-bold bg-amber-100/90 text-amber-800 uppercase whitespace-nowrap border border-amber-200 shadow-2xs">
                                                        {item.reason || 'ED DEKAT'}
                                                    </span>
                                                </td>
                                                <td className="py-2.5 px-3 text-center whitespace-nowrap">
                                                    <div className="inline-flex flex-col items-center gap-0.5">
                                                        {item.status === 'sudah_potong' ? (
                                                            <span
                                                                className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-emerald-300 bg-emerald-100 text-emerald-800 whitespace-nowrap shadow-2xs"
                                                                title={item.deduction_note || 'Sudah Potong Tagihan'}
                                                            >
                                                                <CheckCircle2 className="w-3 h-3 text-emerald-600 shrink-0" />
                                                                <span>Sudah Potong Tagihan</span>
                                                            </span>
                                                        ) : (
                                                            <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold border ${statusBadge.badgeClass} whitespace-nowrap shadow-2xs`}>
                                                                <span className={`w-1.5 h-1.5 rounded-full ${statusBadge.dotClass}`} />
                                                                <span>{statusBadge.label}</span>
                                                            </span>
                                                        )}
                                                        <span className="text-[10px] text-slate-500 font-mono font-semibold">
                                                            {formatDateDisplay(dateToShow)}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="py-2.5 px-3 text-center sticky right-0 bg-white group-hover:bg-amber-50/50 shadow-[-6px_0_10px_-2px_rgba(0,0,0,0.06)] z-10 whitespace-nowrap">
                                                    <div className="flex items-center justify-center gap-1.5">
                                                        {isDiajukan ? (
                                                            <button
                                                                type="button"
                                                                onClick={() => openConfirmModal(item)}
                                                                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white rounded-lg text-xs font-semibold shadow-2xs hover:shadow-xs transition-all active:scale-[0.98] cursor-pointer shrink-0 border border-emerald-700/30"
                                                                title="Konfirmasi Retur & Kurangi Stok Obat"
                                                            >
                                                                <CheckCircle2 className="w-3.5 h-3.5 text-emerald-100 shrink-0" />
                                                                <span>Konfirmasi Retur</span>
                                                            </button>
                                                        ) : (
                                                            <button
                                                                type="button"
                                                                onClick={() => openStatusModal(item)}
                                                                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white rounded-lg text-xs font-semibold shadow-2xs hover:shadow-xs transition-all active:scale-[0.98] cursor-pointer shrink-0 border border-blue-700/30"
                                                                title="Ubah Status Retur Beli"
                                                            >
                                                                <RotateCcw className="w-3.5 h-3.5 text-blue-100 shrink-0" />
                                                                <span>Ubah Status</span>
                                                            </button>
                                                        )}

                                                        <div className="flex items-center bg-slate-50 border border-slate-200/90 rounded-lg p-0.5 shadow-2xs shrink-0">
                                                            <button
                                                                type="button"
                                                                onClick={() => openEditItemModal(item)}
                                                                className="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-white rounded-md transition-all cursor-pointer"
                                                                title="Edit Rincian"
                                                            >
                                                                <Edit2 className="w-3.5 h-3.5" />
                                                            </button>
                                                            <div className="w-px h-3.5 bg-slate-200 mx-0.5" />
                                                            <button
                                                                type="button"
                                                                onClick={() => handleDeleteItem(item)}
                                                                className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-white rounded-md transition-all cursor-pointer"
                                                                title="Hapus Item"
                                                            >
                                                                <Trash2 className="w-3.5 h-3.5" />
                                                            </button>
                                                        </div>
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
                                        onClick={() => link.url && router.get(toSecureUrl(link.url), {}, { preserveState: true })}
                                        className={`px-3 py-1.5 rounded-lg font-medium transition-all ${link.active
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
            </>
        ) : (
            <>
                {/* Approaching Metrics Summary Cards */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div className="bg-white p-4 rounded-xl border border-emerald-200/80 shadow-xs bg-emerald-50/20">
                        <span className="text-xs font-semibold text-emerald-700">Siap Diretur Sekarang</span>
                        <div className="text-xl font-bold text-emerald-700 mt-1">{approachingStats?.eligible_count || 0} Obat</div>
                        <div className="text-[11px] text-emerald-600 mt-0.5">Sudah masuk batas retur</div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-amber-200/80 shadow-xs bg-amber-50/20">
                        <span className="text-xs font-semibold text-amber-700">Mendekati Batas Retur</span>
                        <div className="text-xl font-bold text-amber-700 mt-1">{approachingStats?.approaching_count || 0} Obat</div>
                        <div className="text-[11px] text-amber-600 mt-0.5">Dalam 1-2 bulan ke depan</div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                        <span className="text-xs font-medium text-slate-500">Estimasi Nilai Terancam</span>
                        <div className="text-xl font-bold text-slate-800 mt-1">{formatRupiah(approachingStats?.total_value_at_risk || 0)}</div>
                        <div className="text-[11px] text-slate-400 mt-0.5">Potensi kerugian stok ED</div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                        <span className="text-xs font-medium text-slate-500">Total Stok Fisik</span>
                        <div className="text-xl font-bold text-blue-700 mt-1">{approachingStats?.total_stock_at_risk || 0} Unit</div>
                        <div className="text-[11px] text-slate-400 mt-0.5">Stok aktif di apotek</div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-purple-200/80 shadow-xs bg-purple-50/20 col-span-2 sm:col-span-1">
                        <span className="text-xs font-semibold text-purple-700">Distributor Terkait</span>
                        <div className="text-xl font-bold text-purple-700 mt-1">{approachingStats?.total_creditors || 0} PBF</div>
                        <div className="text-[11px] text-purple-600 mt-0.5">Ketentuan retur aktif</div>
                    </div>
                </div>

                {/* Approaching Table Container */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    {/* Filters Bar */}
                    <div className="p-4 border-b border-slate-200 flex flex-col md:flex-row items-center justify-between gap-3 bg-slate-50/50">
                        <form onSubmit={handleApproachingSearchSubmit} className="relative w-full md:w-80">
                            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                type="text"
                                placeholder="Cari obat, no batch, pabrik, faktur..."
                                value={approachingSearch}
                                onChange={(e) => setApproachingSearch(e.target.value)}
                                className="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white"
                            />
                        </form>

                        <div className="flex flex-wrap items-center gap-2 w-full md:w-auto">
                            <select
                                value={approachingCreditor}
                                onChange={(e) => {
                                    setApproachingCreditor(e.target.value);
                                    handleApproachingFilterChange(e.target.value, approachingUrgency);
                                }}
                                className="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                            >
                                <option value="">Semua PBF</option>
                                {creditors.map((c) => (
                                    <option key={c.code} value={c.code}>{c.name}</option>
                                ))}
                            </select>

                            <select
                                value={approachingUrgency}
                                onChange={(e) => {
                                    setApproachingUrgency(e.target.value);
                                    handleApproachingFilterChange(approachingCreditor, e.target.value);
                                }}
                                className="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                            >
                                <option value="">Semua Status Urgensi</option>
                                <option value="eligible">Siap Diretur Sekarang</option>
                                <option value="approaching">Mendekati Batas Retur (1-2 Bln)</option>
                                <option value="critical">Bulan Terakhir (Segera Hangus!)</option>
                            </select>

                            {(approachingSearch || approachingCreditor || approachingUrgency) && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setApproachingSearch('');
                                        setApproachingCreditor('');
                                        setApproachingUrgency('');
                                        handleApproachingFilterChange('', '');
                                    }}
                                    className="text-xs text-rose-600 hover:text-rose-700 font-semibold px-2 py-1 cursor-pointer"
                                >
                                    Reset Filter
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Table */}
                    <div className="overflow-x-auto relative">
                        <table className="w-full text-left text-xs text-slate-600">
                            <thead className="bg-slate-50 text-slate-700 font-semibold uppercase text-[10px] border-b border-slate-200 tracking-wider">
                                <tr>
                                    <th className="py-3 px-2 w-8 text-center">No</th>
                                    <th className="py-3 px-3 min-w-[180px]">Nama Obat & Pabrik</th>
                                    <th className="py-3 px-3 min-w-[150px]">PBF / Distributor</th>
                                    <th className="py-3 px-3 text-center min-w-[110px]">Batch & ED</th>
                                    <th className="py-3 px-2 text-center w-20">Sisa Stok</th>
                                    <th className="py-3 px-3 text-center min-w-[130px]">Ketentuan Retur</th>
                                    <th className="py-3 px-2 text-center whitespace-nowrap">Batas Akhir Retur</th>
                                    <th className="py-3 px-3 text-center min-w-[150px]">Status Urgensi</th>
                                    <th className="py-3 px-3 text-right min-w-[110px]">Nilai Terancam</th>
                                    <th className="py-3 px-3 text-center min-w-[140px] sticky right-0 bg-slate-100 z-10 shadow-[-6px_0_10px_-2px_rgba(0,0,0,0.06)]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {(!approachingItems?.data || approachingItems.data.length === 0) ? (
                                    <tr>
                                        <td colSpan="10" className="text-center py-12 text-slate-400">
                                            Tidak ada obat yang mendekati batas ketentuan retur saat ini.
                                        </td>
                                    </tr>
                                ) : (
                                    approachingItems.data.map((item, idx) => (
                                        <tr
                                            key={item.batch_id}
                                            className={`transition-colors hover:bg-amber-50/30 group ${item.urgency_key === 'critical' ? 'bg-rose-50/20' : item.is_eligible ? 'bg-emerald-50/20' : ''
                                                }`}
                                        >
                                            <td className="py-2.5 px-2 text-center text-slate-400 font-mono text-[11px]">
                                                {((approachingItems.current_page || 1) - 1) * (approachingItems.per_page || 25) + idx + 1}
                                            </td>
                                            <td className="py-2.5 px-3 font-bold text-slate-800">
                                                <div>{item.medicine_name}</div>
                                                <div className="text-[10px] text-slate-400 font-medium">
                                                    Pabrik: {item.factory_name}
                                                </div>
                                            </td>
                                            <td className="py-2.5 px-3">
                                                <div className="text-xs font-semibold text-blue-900 break-words line-clamp-1" title={item.creditor_name}>
                                                    {item.creditor_name}
                                                </div>
                                                <div className="font-mono text-[10px] text-slate-400">
                                                    Faktur: {item.invoice_number}
                                                </div>
                                            </td>
                                            <td className="py-2.5 px-3 text-center whitespace-nowrap">
                                                <div className="font-mono text-slate-700 text-xs font-semibold">{item.batch_number}</div>
                                                <div className="text-[10px] font-semibold text-rose-600 mt-0.5">ED: {item.expired_date_display}</div>
                                            </td>
                                            <td className="py-2.5 px-2 text-center whitespace-nowrap">
                                                <span className="font-extrabold text-blue-700 text-xs">{Number(item.stock)}</span>{' '}
                                                <span className="text-[10px] font-semibold text-slate-500 uppercase">{item.unit || 'BOX'}</span>
                                            </td>
                                            <td className="py-2.5 px-3 text-center">
                                                <span className="inline-block px-2 py-0.5 rounded text-[10px] font-bold border bg-slate-50 border-slate-200 text-slate-700 whitespace-nowrap">
                                                    H-{item.months_before_ed} Bln sebelum ED
                                                </span>
                                                {item.rule_text && (
                                                    <div className="text-[9px] text-slate-400 mt-0.5 truncate max-w-[130px]" title={item.rule_text}>
                                                        {item.rule_text}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-2.5 px-2 text-center whitespace-nowrap">
                                                <div className="font-semibold text-slate-700 text-xs">{item.deadline_display}</div>
                                                <div className="text-[9px] text-slate-400">
                                                    {item.diff_months <= 0 ? 'Bulan Ini' : `Sisa ${item.diff_months} bln ke ED`}
                                                </div>
                                            </td>
                                            <td className="py-2.5 px-3 text-center whitespace-nowrap">
                                                <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold border ${item.urgency_badge}`}>
                                                    <span className={`w-1.5 h-1.5 rounded-full ${item.urgency_key === 'critical' ? 'bg-rose-500 animate-ping' : item.is_eligible ? 'bg-emerald-500' : 'bg-blue-500'
                                                        }`} />
                                                    {item.urgency_label}
                                                </span>
                                            </td>
                                            <td className="py-2.5 px-3 text-right font-mono font-semibold text-slate-800 text-xs whitespace-nowrap">
                                                {formatRupiah(item.estimated_value || 0)}
                                            </td>
                                            <td className="py-2.5 px-3 text-center sticky right-0 bg-white group-hover:bg-amber-50/50 shadow-[-6px_0_10px_-2px_rgba(0,0,0,0.06)] z-10 whitespace-nowrap">
                                                {item.active_return_status ? (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                                        <Check className="w-3 h-3 text-blue-600" />
                                                        <span>Sedang Diajukan</span>
                                                    </span>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleApplyReturnFromAlert(item)}
                                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white rounded-lg text-xs font-semibold shadow-2xs hover:shadow-xs transition-all active:scale-[0.98] cursor-pointer shrink-0 border border-emerald-700/30"
                                                        title="Ajukan retur otomatis untuk obat ini"
                                                    >
                                                        <Plus className="w-3.5 h-3.5 text-emerald-100 shrink-0" />
                                                        <span>Ajukan Retur</span>
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination Approaching Items */}
                    {approachingItems?.total > approachingItems?.per_page && (
                        <div className="p-4 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
                            <div>
                                Menampilkan {approachingItems.from || 0} - {approachingItems.to || 0} dari {approachingItems.total} item
                            </div>
                            <div className="flex items-center gap-1">
                                {approachingItems.links.map((link, i) => (
                                    <button
                                        key={i}
                                        disabled={!link.url || link.active}
                                        onClick={() => link.url && router.get(toSecureUrl(link.url), {}, { preserveState: true })}
                                        className={`px-3 py-1.5 rounded-lg font-medium transition-all ${link.active
                                            ? 'bg-amber-600 text-white font-bold'
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
            </>
        )}
    </div>

            {/* Modal Input Retur Baru */}
            {createModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-2 sm:p-4">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-[96vw] xl:max-w-[1700px] h-[94vh] border border-slate-200 flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
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
                                    className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all ${entryType === 'manual'
                                        ? 'bg-blue-600 text-white shadow-xs'
                                        : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'
                                        }`}
                                >
                                    Input Manual / Custom Retur
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setEntryType('invoice')}
                                    className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all ${entryType === 'invoice'
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
                                <div className={`p-4 rounded-xl border space-y-3 ${entryType === 'invoice' && formData.receiving_id
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
                                                className={`w-full border rounded-xl px-3 py-2 font-mono ${entryType === 'invoice' && formData.receiving_id
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
                                                className={`w-full border rounded-xl px-3 py-2 ${entryType === 'invoice' && formData.receiving_id
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
                                                    <th className="py-2.5 px-2 w-32 text-center">Ketentuan Retur</th>
                                                    <th className="py-2.5 px-2 w-28 text-center">Satuan Retur</th>
                                                    <th className="py-2.5 px-2 w-28 text-center">
                                                        {entryType === 'invoice' ? 'Qty Retur / Faktur' : 'Jumlah'}
                                                    </th>
                                                    <th className="py-2.5 px-2 w-24">Batch</th>
                                                    <th className="py-2.5 px-2 w-24">Expired Date</th>
                                                    <th className="py-2.5 px-2 w-28 text-right">Harga Beli</th>
                                                    <th className="py-2.5 px-2 w-28">Keterangan</th>
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
                                                                className={`transition-colors ${isFromInvoice
                                                                    ? row.is_already_returned
                                                                        ? 'bg-slate-100/90 text-slate-400 opacity-60 cursor-not-allowed'
                                                                        : row.selected
                                                                            ? 'bg-blue-50/70 font-medium'
                                                                            : 'bg-white opacity-70 hover:opacity-100 hover:bg-slate-50'
                                                                    : 'hover:bg-slate-50'
                                                                    }`}
                                                            >
                                                                {isFromInvoice && (
                                                                    <td className="py-2 px-3 text-center">
                                                                        <input
                                                                            type="checkbox"
                                                                            disabled={!!row.is_already_returned}
                                                                            checked={row.is_already_returned ? false : !!row.selected}
                                                                            onChange={(e) => updateItemRow(idx, 'selected', e.target.checked)}
                                                                            className={`rounded text-blue-600 focus:ring-blue-500 w-4 h-4 ${row.is_already_returned ? 'cursor-not-allowed opacity-40' : 'cursor-pointer'}`}
                                                                        />
                                                                    </td>
                                                                )}
                                                                <td className="py-2 px-3">
                                                                    {isFromInvoice ? (
                                                                        <div>
                                                                            <div className="font-bold text-slate-800 flex items-center gap-1.5 flex-wrap">
                                                                                {row.is_already_returned ? (
                                                                                    <Lock className="w-3.5 h-3.5 text-rose-500 shrink-0" />
                                                                                ) : (
                                                                                    <Package className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                                                                )}
                                                                                <span className={row.is_already_returned ? 'line-through text-slate-500' : ''}>
                                                                                    {row.medicine_name}
                                                                                </span>
                                                                                {row.is_already_returned && (
                                                                                    <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700 border border-rose-200">
                                                                                        <Lock className="w-2.5 h-2.5" /> Terkunci (Sudah Diretur)
                                                                                    </span>
                                                                                )}
                                                                            </div>
                                                                            {row.factory_name && (
                                                                                <div className="text-[10px] text-slate-400 font-medium pl-5">
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
                                                                    {row.has_pack ? (
                                                                        <div>
                                                                            <div className="inline-flex p-0.5 bg-slate-100 rounded-lg border border-slate-200 shadow-2xs">
                                                                                <button
                                                                                    type="button"
                                                                                    disabled={isFromInvoice && (!row.selected || !!row.is_already_returned)}
                                                                                    onClick={() => switchItemReturType(idx, 'packaging')}
                                                                                    className={`px-1.5 py-0.5 text-[10px] font-bold rounded-md transition-all ${row.retur_type === 'packaging'
                                                                                        ? 'bg-blue-600 text-white shadow-xs'
                                                                                        : 'text-slate-600 hover:text-slate-900'
                                                                                    }`}
                                                                                >
                                                                                    {row.packaging || 'BOX'}
                                                                                </button>
                                                                                <button
                                                                                    type="button"
                                                                                    disabled={isFromInvoice && (!row.selected || !!row.is_already_returned)}
                                                                                    onClick={() => switchItemReturType(idx, 'unit')}
                                                                                    className={`px-1.5 py-0.5 text-[10px] font-bold rounded-md transition-all ${row.retur_type === 'unit'
                                                                                        ? 'bg-blue-600 text-white shadow-xs'
                                                                                        : 'text-slate-600 hover:text-slate-900'
                                                                                    }`}
                                                                                >
                                                                                    {row.stock_unit || 'PCS'}
                                                                                </button>
                                                                            </div>
                                                                            <div className="text-[9px] text-slate-400 mt-0.5">
                                                                                Isi @{row.content} {row.stock_unit || 'PCS'}
                                                                            </div>
                                                                        </div>
                                                                    ) : isFromInvoice ? (
                                                                        <span className="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                                                            {row.unit || 'PCS'}
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
                                                                <td className="py-2 px-2 text-center">
                                                                    <div className="flex items-center justify-center gap-1">
                                                                        <input
                                                                            type="number"
                                                                            min="0.01"
                                                                            max={isFromInvoice ? (row.max_qty || 9999) : undefined}
                                                                            step="any"
                                                                            required
                                                                            disabled={isFromInvoice && (!row.selected || !!row.is_already_returned)}
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
                                                                            className={`w-14 border rounded-lg px-1.5 py-1 text-xs text-center font-bold ${isFromInvoice && (!row.selected || row.is_already_returned)
                                                                                ? 'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed'
                                                                                : 'border-blue-300 text-blue-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500'
                                                                                }`}
                                                                        />
                                                                        <span className="text-[10px] font-semibold text-slate-500">
                                                                            {row.unit || 'PCS'}
                                                                        </span>
                                                                        {isFromInvoice && (
                                                                            <span className="text-[10px] text-slate-400 font-normal">
                                                                                / {row.max_qty}
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                    <div className="text-[9px] text-slate-400 mt-0.5">
                                                                        Stok: {row.retur_type === 'packaging' && row.has_pack
                                                                            ? `${row.current_stock_pack} ${row.packaging} (${row.current_stock_unit} ${row.stock_unit})`
                                                                            : `${row.current_stock_unit} ${row.stock_unit || 'PCS'}`}
                                                                    </div>
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
                                                                            {formatDateDisplay(row.expired_date)}
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
                                                                        <div>
                                                                            <span className="font-mono text-slate-800 font-semibold text-[11px]">
                                                                                {formatRupiah(row.unit_price)}
                                                                            </span>
                                                                            <div className="text-[9px] text-slate-400">Per {row.unit || 'PCS'}</div>
                                                                        </div>
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
                                                                        disabled={isFromInvoice && (!row.selected || !!row.is_already_returned)}
                                                                        value={row.reason || 'ED DEKAT'}
                                                                        onChange={(e) => updateItemRow(idx, 'reason', e.target.value)}
                                                                        className={`w-full border border-slate-200 rounded-lg px-1.5 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500 ${isFromInvoice && (!row.selected || row.is_already_returned) ? 'bg-slate-100 text-slate-400 cursor-not-allowed' : 'bg-white'}`}
                                                                    >
                                                                        <option value="ED DEKAT">ED DEKAT</option>
                                                                        <option value="Barang Rusak">Barang Rusak</option>
                                                                        <option value="Salah Barang">Salah Barang</option>
                                                                        <option value="Salah Pesan">Salah Pesan</option>
                                                                        <option value="Recall">Recall</option>
                                                                        <option value="Retur Pabrik">Retur Pabrik</option>
                                                                        <option value="Double">Double</option>
                                                                        <option value="dlsbnya">dlsbnya</option>
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
                                    <option value="Barang Rusak">Barang Rusak</option>
                                    <option value="Salah Barang">Salah Barang</option>
                                    <option value="Salah Pesan">Salah Pesan</option>
                                    <option value="Recall">Recall</option>
                                    <option value="Retur Pabrik">Retur Pabrik</option>
                                    <option value="Double">Double</option>
                                    <option value="dlsbnya">dlsbnya</option>
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
            {/* Modal Konfirmasi Retur (Kurangi Stok) */}
            {confirmModalOpen && confirmingItem && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                        <div className="p-5 border-b border-slate-200 flex items-center justify-between bg-amber-50/80">
                            <div className="flex items-center gap-2.5">
                                <div className="p-2 bg-amber-100 rounded-xl text-amber-700">
                                    <AlertTriangle className="w-5 h-5" />
                                </div>
                                <div>
                                    <h3 className="font-bold text-slate-800 text-sm">Konfirmasi Retur Pembelian</h3>
                                    <p className="text-[11px] text-slate-500">Kurangi stok barang dan catat riwayat stok</p>
                                </div>
                            </div>
                            <button
                                onClick={() => setConfirmModalOpen(false)}
                                className="p-1 rounded-lg hover:bg-slate-200 text-slate-400 hover:text-slate-700"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleConfirmSubmit} className="p-5 space-y-4 text-xs">
                            {/* Ringkasan Item */}
                            <div className="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                                <div className="flex justify-between items-center">
                                    <span className="text-slate-500">Nama Obat:</span>
                                    <span className="font-bold text-slate-800 text-right">{confirmingItem.medicine_name}</span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-slate-500">No Faktur:</span>
                                    <span className="font-semibold text-slate-700 font-mono">{confirmingItem.purchase_return?.invoice_number || '-'}</span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-slate-500">PBF / Supplier:</span>
                                    <span className="font-semibold text-slate-700">{confirmingItem.purchase_return?.creditor?.name || confirmingItem.purchase_return?.creditor_code || '-'}</span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-slate-500">Jumlah Diretur:</span>
                                    <span className="font-bold text-blue-700">{confirmingItem.qty} {confirmingItem.unit}</span>
                                </div>
                                <div className="flex justify-between items-center pt-1 border-t border-slate-200">
                                    <span className="text-slate-500">Estimasi Nilai Retur:</span>
                                    <span className="font-bold text-emerald-700 font-mono">{formatRupiah(confirmingItem.qty * confirmingItem.unit_price)}</span>
                                </div>
                            </div>

                            {/* Alert Pengurangan Stok */}
                            <div className="p-3 bg-amber-50 border border-amber-200 rounded-xl flex gap-2.5 text-amber-900 leading-relaxed">
                                <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                <div className="text-[11px]">
                                    <strong className="block font-bold mb-0.5">Pengurangan Stok Barang</strong>
                                    Konfirmasi ini akan <strong>langsung memotong stok master obat & stok batch</strong> terkait, mencatat ke Kartu Stok (Items Log status 4 Retur Beli), dan <strong>diperbolehkan minus stok</strong> jika fisik barang sudah ditarik.
                                </div>
                            </div>

                            <div>
                                <label className="block font-bold text-slate-700 mb-1">
                                    Tanggal Konfirmasi Retur <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="date"
                                    required
                                    value={confirmForm.confirmed_date}
                                    onChange={(e) => setConfirmForm({ ...confirmForm, confirmed_date: e.target.value })}
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium"
                                />
                                <span className="text-[10px] text-slate-400 mt-1 block">Wajib mencatat tanggal pelaksanaan konfirmasi retur.</span>
                            </div>

                            <div>
                                <label className="block font-bold text-slate-700 mb-1">Catatan Konfirmasi (Opsional)</label>
                                <textarea
                                    rows="2"
                                    placeholder="Contoh: Barang fisik sudah diserahkan ke sales PBF..."
                                    value={confirmForm.notes}
                                    onChange={(e) => setConfirmForm({ ...confirmForm, notes: e.target.value })}
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div className="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setConfirmModalOpen(false)}
                                    className="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 font-semibold transition-all"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    className="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold shadow-xs transition-all flex items-center gap-1.5"
                                >
                                    <CheckCircle2 className="w-4 h-4" /> Konfirmasi & Kurangi Stok
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Ubah Status Retur */}
            {statusModalOpen && statusItem && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                        <div className="p-5 border-b border-slate-200 flex items-center justify-between bg-blue-50/80">
                            <div className="flex items-center gap-2.5">
                                <div className="p-2 bg-blue-100 rounded-xl text-blue-700">
                                    <ArrowRight className="w-5 h-5" />
                                </div>
                                <div>
                                    <h3 className="font-bold text-slate-800 text-sm">Ubah Status Retur Pembelian</h3>
                                    <p className="text-[11px] text-slate-500">Pembaruan alur status dan pemotongan tagihan hutang</p>
                                </div>
                            </div>
                            <button
                                onClick={() => setStatusModalOpen(false)}
                                className="p-1 rounded-lg hover:bg-slate-200 text-slate-400 hover:text-slate-700"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleStatusSubmit} className="p-5 space-y-4 text-xs">
                            {/* Ringkasan Item */}
                            <div className="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                                <div className="flex justify-between items-center">
                                    <span className="text-slate-500">Nama Obat:</span>
                                    <span className="font-bold text-slate-800 text-right">{statusItem.medicine_name}</span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-slate-500">No Faktur:</span>
                                    <span className="font-semibold text-slate-700 font-mono">{statusItem.purchase_return?.invoice_number || '-'}</span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-slate-500">Status Saat Ini:</span>
                                    {(() => {
                                        const badge = getStatusBadge(statusItem.status);
                                        return (
                                            <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold border ${badge.badgeClass}`}>
                                                {badge.label}
                                            </span>
                                        );
                                    })()}
                                </div>
                                <div className="flex justify-between items-center pt-1 border-t border-slate-200">
                                    <span className="text-slate-500">Nilai Retur Obat:</span>
                                    <span className="font-bold text-emerald-700 font-mono">{formatRupiah(statusItem.qty * statusItem.unit_price)}</span>
                                </div>
                            </div>

                            {/* Pilihan Status */}
                            <div>
                                <label className="block font-bold text-slate-700 mb-1">
                                    Pilih Status Baru <span className="text-rose-500">*</span>
                                </label>
                                <select
                                    required
                                    value={statusForm.status}
                                    onChange={(e) => setStatusForm({ ...statusForm, status: e.target.value })}
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-bold text-slate-800"
                                >
                                    <option value="diajukan">Diajukan Retur</option>
                                    <option value="dikonfirmasi">Sudah Dikonfirmasi</option>
                                    <option value="sudah_diambil">Sudah Diambil</option>
                                    <option value="sudah_potong">Sudah Potong Tagihan</option>
                                    <option value="barang_kembali_pmi">Barang Kembali PMI</option>
                                </select>
                            </div>

                            {/* Tanggal Perubahan Status (Wajib dicatat sesuai instruksi) */}
                            <div>
                                <label className="block font-bold text-slate-700 mb-1">
                                    Tanggal Perubahan Status <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="date"
                                    required
                                    value={statusForm.status_date}
                                    onChange={(e) => setStatusForm({ ...statusForm, status_date: e.target.value })}
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium"
                                />
                                <span className="text-[10px] text-slate-400 mt-1 block">Wajib: Setiap pergantian status harus tercatat tanggalnya.</span>
                            </div>

                            {/* Notifikasi Khusus jika Potong Tagihan */}
                            {statusForm.status === 'sudah_potong' && (
                                <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-900 leading-relaxed text-[11px] flex gap-2">
                                    <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                                    <div>
                                        <strong className="block font-bold mb-0.5">Pemotongan Hutang Dagang Otomatis</strong>
                                        Memilih status ini akan langsung memotong sisa tagihan Hutang Dagang pada faktur penerimaan sebesar <strong className="font-mono">{formatRupiah(statusItem.qty * statusItem.unit_price)}</strong> dan menjaga konsistensi data hutang.
                                    </div>
                                </div>
                            )}

                            <div>
                                <label className="block font-bold text-slate-700 mb-1">Keterangan / Catatan Status (Opsional)</label>
                                <textarea
                                    rows="2"
                                    placeholder="Contoh: No nota retur dari PBF / nomor credit note..."
                                    value={statusForm.notes}
                                    onChange={(e) => setStatusForm({ ...statusForm, notes: e.target.value })}
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div className="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setStatusModalOpen(false)}
                                    className="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 font-semibold transition-all"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    className="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold shadow-xs transition-all flex items-center gap-1.5"
                                >
                                    <Check className="w-4 h-4" /> Simpan Perubahan Status
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </FinanceLayout>
    );
}
