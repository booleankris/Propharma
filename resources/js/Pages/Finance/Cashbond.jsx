import React, { useState, useMemo } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import FinanceLayout from './Layouts/FinanceLayout';
import Drawer from './Components/Drawer';
import { formatRupiah, formatNumberOnly, terbilang } from './Components/Utils';
import {
    WalletCards,
    Clock,
    CheckCircle2,
    AlertCircle,
    Plus,
    Search,
    Calendar,
    ChevronLeft,
    ChevronRight,
    Trash2,
    Edit3,
    Eye,
    X,
    Landmark,
    FileText,
    Check,
    RotateCcw,
    UploadCloud,
    Info,
    ExternalLink,
    Sparkles,
    ShieldAlert,
    User,
    ArrowUpRight
} from 'lucide-react';

export default function Cashbond({
    cashbondList = [],
    kasBankAccounts = [],
    summary = {
        totalActive: 0,
        countActive: 0,
        totalSettled: 0,
        countSettled: 0,
        totalAll: 0,
        countAll: 0,
    },
    stats = {},
    branchContext = {},
    filters = {
        search: '',
        status: 'ALL',
        account_id: '',
        start_date: '',
        end_date: '',
    }
}) {
    const { errors: pageErrors, flash = {} } = usePage().props;

    // Local filters
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || 'ALL');
    const [selectedAccountId, setSelectedAccountId] = useState(filters.account_id || '');
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');
    const [currentPage, setCurrentPage] = useState(1);
    const itemsPerPage = 12;

    // Modals & Drawer State
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [settlingItem, setSettlingItem] = useState(null);
    const [detailItem, setDetailItem] = useState(null);
    const [previewImage, setPreviewImage] = useState(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Form State (Create / Edit)
    const [formData, setFormData] = useState({
        account_id: kasBankAccounts[0]?.id ? String(kasBankAccounts[0].id) : '',
        borrower_name: 'Direktur',
        amount: '',
        transaction_date: new Date().toISOString().split('T')[0],
        purpose: '',
        proof: null,
    });
    const [formErrors, setFormErrors] = useState({});

    // Settle Modal Form State
    const [settleForm, setSettleForm] = useState({
        settlement_date: new Date().toISOString().split('T')[0],
        settlement_notes: 'Telah dikembalikan/dibayar oleh Direktur',
    });

    // Top accounts analysis (for fourth card)
    const topAccountUsage = useMemo(() => {
        const counts = {};
        cashbondList.forEach((item) => {
            const accName = item.account_name || 'Kas / Bank';
            if (!counts[accName]) {
                counts[accName] = { count: 0, total: 0 };
            }
            counts[accName].count += 1;
            counts[accName].total += Number(item.amount || 0);
        });
        const sorted = Object.entries(counts).sort((a, b) => b[1].total - a[1].total);
        if (sorted.length > 0) {
            return {
                name: sorted[0][0],
                total: sorted[0][1].total,
                count: sorted[0][1].count,
            };
        }
        return null;
    }, [cashbondList]);

    // Client-side filtering
    const filteredList = useMemo(() => {
        return cashbondList.filter((item) => {
            const term = searchTerm.toLowerCase().trim();
            const matchesSearch =
                !term ||
                item.borrower_name?.toLowerCase().includes(term) ||
                item.purpose?.toLowerCase().includes(term) ||
                item.settlement_notes?.toLowerCase().includes(term) ||
                item.account_name?.toLowerCase().includes(term) ||
                item.account_code?.toLowerCase().includes(term) ||
                item.created_by_name?.toLowerCase().includes(term);

            const matchesStatus =
                selectedStatus === 'ALL' ||
                item.status === selectedStatus;

            const matchesAccount =
                !selectedAccountId ||
                String(item.account_id) === String(selectedAccountId);

            const matchesDateRange =
                (!startDate || (item.transaction_date && item.transaction_date >= startDate)) &&
                (!endDate || (item.transaction_date && item.transaction_date <= endDate));

            return matchesSearch && matchesStatus && matchesAccount && matchesDateRange;
        });
    }, [cashbondList, searchTerm, selectedStatus, selectedAccountId, startDate, endDate]);

    // Pagination
    const totalPages = Math.ceil(filteredList.length / itemsPerPage) || 1;
    const paginatedList = useMemo(() => {
        const start = (currentPage - 1) * itemsPerPage;
        return filteredList.slice(start, start + itemsPerPage);
    }, [filteredList, currentPage, itemsPerPage]);

    // Open Create Drawer
    const handleOpenCreate = () => {
        setFormData({
            account_id: kasBankAccounts[0]?.id ? String(kasBankAccounts[0].id) : '',
            borrower_name: 'Direktur',
            amount: '',
            transaction_date: new Date().toISOString().split('T')[0],
            purpose: '',
            proof: null,
        });
        setFormErrors({});
        setEditingItem(null);
        setIsCreateOpen(true);
    };

    // Open Edit Drawer
    const handleOpenEdit = (item) => {
        setEditingItem(item);
        setFormData({
            account_id: String(item.account_id),
            borrower_name: item.borrower_name,
            amount: String(item.amount),
            transaction_date: item.transaction_date || new Date().toISOString().split('T')[0],
            purpose: item.purpose || '',
            proof: null,
        });
        setFormErrors({});
        setIsCreateOpen(true);
    };

    // Open Settle Modal
    const handleOpenSettle = (item) => {
        setSettlingItem(item);
        setSettleForm({
            settlement_date: new Date().toISOString().split('T')[0],
            settlement_notes: 'Telah dikembalikan/dibayar oleh Direktur',
        });
    };

    // Submit Create or Edit
    const handleSubmit = (e) => {
        e.preventDefault();
        setIsSubmitting(true);
        setFormErrors({});

        const payload = new FormData();
        payload.append('account_id', formData.account_id);
        payload.append('borrower_name', formData.borrower_name);
        payload.append('amount', formData.amount);
        payload.append('transaction_date', formData.transaction_date);
        payload.append('purpose', formData.purpose || '');
        if (formData.proof) {
            payload.append('proof', formData.proof);
        }

        if (editingItem) {
            payload.append('_method', 'PUT');
            router.post(`/finance/cashbond/${editingItem.id}`, payload, {
                forceFormData: true,
                onSuccess: () => {
                    setIsCreateOpen(false);
                    setEditingItem(null);
                    setIsSubmitting(false);
                },
                onError: (errs) => {
                    setFormErrors(errs);
                    setIsSubmitting(false);
                },
            });
        } else {
            router.post('/finance/cashbond', payload, {
                forceFormData: true,
                onSuccess: () => {
                    setIsCreateOpen(false);
                    setIsSubmitting(false);
                },
                onError: (errs) => {
                    setFormErrors(errs);
                    setIsSubmitting(false);
                },
            });
        }
    };

    // Submit Settle
    const handleConfirmSettle = (e) => {
        e.preventDefault();
        if (!settlingItem) return;
        setIsSubmitting(true);

        router.post(`/finance/cashbond/${settlingItem.id}/settle`, settleForm, {
            onSuccess: () => {
                setSettlingItem(null);
                setIsSubmitting(false);
            },
            onError: () => {
                setIsSubmitting(false);
            },
        });
    };

    // Reopen Cashbond
    const handleReopen = (item) => {
        if (!confirm(`Kembalikan status cashbond #${item.id} (${formatRupiah(item.amount)}) menjadi Belum Lunas?`)) {
            return;
        }
        router.post(`/finance/cashbond/${item.id}/reopen`, {}, {
            preserveScroll: true,
        });
    };

    // Delete Cashbond
    const handleDelete = (item) => {
        if (!confirm(`Hapus catatan cashbond #${item.id} senilai ${formatRupiah(item.amount)}? Data yang dihapus tidak dapat dipulihkan.`)) {
            return;
        }
        router.delete(`/finance/cashbond/${item.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <FinanceLayout stats={stats} branchContext={branchContext}>
            <Head title="Cashbond (Talangan Sementara) - Sahabat Finance" />

            <div className="space-y-6 pb-12">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <div className="p-2.5 rounded-xl bg-amber-500/10 text-amber-600 border border-amber-200/60">
                                <WalletCards className="w-6 h-6" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight text-slate-900">
                                    Cashbond (Talangan Sementara)
                                </h1>
                                <p className="text-sm text-slate-500">
                                    Catatan talangan pengeluaran mendadak apotek yang menggunakan uang pribadi Direktur secara sementara.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={handleOpenCreate}
                            className="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 shadow-sm transition active:scale-[0.98]"
                        >
                            <Plus className="w-4 h-4 stroke-[2.5]" />
                            <span>Catat Cashbond Baru</span>
                        </button>
                    </div>
                </div>

                {/* Accounting Isolation Notice Banner */}
                <div className="p-4 rounded-2xl bg-amber-50/70 border border-amber-200/80 text-amber-900 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 shadow-xs">
                    <div className="flex items-start gap-3">
                        <div className="p-1.5 rounded-lg bg-amber-100 text-amber-800 shrink-0 mt-0.5">
                            <ShieldAlert className="w-5 h-5" />
                        </div>
                        <div className="text-xs leading-relaxed">
                            <span className="font-bold uppercase tracking-wider text-amber-800 block text-[11px] mb-0.5">
                                Prinsip Akuntansi & Arus Kas Sistem:
                            </span>
                            <p className="text-amber-950">
                                Transaksi Cashbond ini <strong>TIDAK MENGURANGI SALDO BUKU KAS & BANK</strong> dan <strong>TIDAK MASUK KE LAPORAN KEUANGAN APAPUN</strong> (Arus Kas, Laba Rugi, LIPH, dsb).
                                Pilihan akun kas/bank hanya berperan sebagai <em>penanda sumber dana apotek yang terpakai sementara</em> sampai uang tersebut diganti/dibayar kembali oleh Direktur.
                            </p>
                        </div>
                    </div>
                    <span className="shrink-0 px-2.5 py-1 text-[11px] font-semibold bg-amber-200/70 text-amber-900 rounded-full border border-amber-300">
                        Isolasi Buku Kas Aktif
                    </span>
                </div>

                {/* 4 Summary Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Card 1: Belum Lunas */}
                    <div className="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs relative overflow-hidden">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Cashbond Belum Lunas
                            </span>
                            <span className="p-2 rounded-xl bg-amber-50 text-amber-600 border border-amber-100">
                                <Clock className="w-4 h-4" />
                            </span>
                        </div>
                        <div className="mt-3">
                            <div className="text-2xl font-bold text-slate-900">
                                {formatRupiah(summary.totalActive)}
                            </div>
                            <div className="mt-1 flex items-center gap-1.5 text-xs text-amber-700 font-medium">
                                <span className="inline-block w-2 h-2 rounded-full bg-amber-500 animate-pulse" />
                                {summary.countActive} transaksi membutuhkan pelunasan
                            </div>
                        </div>
                    </div>

                    {/* Card 2: Sudah Lunas */}
                    <div className="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs relative overflow-hidden">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Telah Dikembalikan
                            </span>
                            <span className="p-2 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100">
                                <CheckCircle2 className="w-4 h-4" />
                            </span>
                        </div>
                        <div className="mt-3">
                            <div className="text-2xl font-bold text-slate-900">
                                {formatRupiah(summary.totalSettled)}
                            </div>
                            <div className="mt-1 text-xs text-emerald-700 font-medium">
                                {summary.countSettled} transaksi telah diselesaikan
                            </div>
                        </div>
                    </div>

                    {/* Card 3: Total Riwayat */}
                    <div className="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs relative overflow-hidden">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Total Akumulasi
                            </span>
                            <span className="p-2 rounded-xl bg-blue-50 text-blue-600 border border-blue-100">
                                <FileText className="w-4 h-4" />
                            </span>
                        </div>
                        <div className="mt-3">
                            <div className="text-2xl font-bold text-slate-900">
                                {formatRupiah(summary.totalAll)}
                            </div>
                            <div className="mt-1 text-xs text-slate-500">
                                {summary.countAll} total riwayat tercatat
                            </div>
                        </div>
                    </div>

                    {/* Card 4: Akun Sering Dipakai */}
                    <div className="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs relative overflow-hidden">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Sumber Terbanyak
                            </span>
                            <span className="p-2 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100">
                                <Landmark className="w-4 h-4" />
                            </span>
                        </div>
                        <div className="mt-3">
                            <div className="text-base font-bold text-slate-900 truncate" title={topAccountUsage?.name || 'Belum Ada'}>
                                {topAccountUsage ? topAccountUsage.name : 'Belum Ada Transaksi'}
                            </div>
                            <div className="mt-1 text-xs text-slate-500">
                                {topAccountUsage ? `${formatRupiah(topAccountUsage.total)} (${topAccountUsage.count}x)` : 'Data talangan masih kosong'}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Filter Bar & Controls */}
                <div className="p-4 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-3">
                    <div className="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
                        {/* Status Tabs */}
                        <div className="flex items-center p-1 bg-slate-100 rounded-xl w-full lg:w-auto">
                            <button
                                type="button"
                                onClick={() => { setSelectedStatus('ALL'); setCurrentPage(1); }}
                                className={`flex-1 lg:flex-none px-3.5 py-1.5 rounded-lg text-xs font-semibold transition ${
                                    selectedStatus === 'ALL'
                                        ? 'bg-white text-slate-900 shadow-xs'
                                        : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                Semua ({cashbondList.length})
                            </button>
                            <button
                                type="button"
                                onClick={() => { setSelectedStatus('BELUM LUNAS'); setCurrentPage(1); }}
                                className={`flex-1 lg:flex-none px-3.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center justify-center gap-1.5 ${
                                    selectedStatus === 'BELUM LUNAS'
                                        ? 'bg-white text-amber-800 shadow-xs'
                                        : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                <span className="w-1.5 h-1.5 rounded-full bg-amber-500" />
                                Belum Lunas ({summary.countActive})
                            </button>
                            <button
                                type="button"
                                onClick={() => { setSelectedStatus('LUNAS'); setCurrentPage(1); }}
                                className={`flex-1 lg:flex-none px-3.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center justify-center gap-1.5 ${
                                    selectedStatus === 'LUNAS'
                                        ? 'bg-white text-emerald-800 shadow-xs'
                                        : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                                Lunas ({summary.countSettled})
                            </button>
                        </div>

                        {/* Search & Account Dropdown */}
                        <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 flex-1 lg:justify-end">
                            {/* Search */}
                            <div className="relative flex-1 max-w-sm">
                                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <input
                                    type="text"
                                    value={searchTerm}
                                    onChange={(e) => { setSearchTerm(e.target.value); setCurrentPage(1); }}
                                    placeholder="Cari peminjam, keperluan, akun..."
                                    className="w-full pl-9 pr-8 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white"
                                />
                                {searchTerm && (
                                    <button
                                        type="button"
                                        onClick={() => setSearchTerm('')}
                                        className="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                    >
                                        <X className="w-3.5 h-3.5" />
                                    </button>
                                )}
                            </div>

                            {/* Filter Akun Kas & Bank */}
                            <select
                                value={selectedAccountId}
                                onChange={(e) => { setSelectedAccountId(e.target.value); setCurrentPage(1); }}
                                className="px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white text-slate-700"
                            >
                                <option value="">Semua Akun Kas / Bank</option>
                                {kasBankAccounts.map((acc) => (
                                    <option key={acc.id} value={acc.id}>
                                        {acc.code ? `${acc.code} - ` : ''}{acc.name}
                                    </option>
                                ))}
                            </select>

                            {/* Date inputs */}
                            <div className="flex items-center gap-1.5">
                                <input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => { setStartDate(e.target.value); setCurrentPage(1); }}
                                    className="px-2.5 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl text-slate-700"
                                    title="Tanggal Mulai"
                                />
                                <span className="text-slate-400 text-xs">-</span>
                                <input
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => { setEndDate(e.target.value); setCurrentPage(1); }}
                                    className="px-2.5 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl text-slate-700"
                                    title="Tanggal Selesai"
                                />
                            </div>

                            {(searchTerm || selectedAccountId || startDate || endDate || selectedStatus !== 'ALL') && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSearchTerm('');
                                        setSelectedAccountId('');
                                        setStartDate('');
                                        setEndDate('');
                                        setSelectedStatus('ALL');
                                        setCurrentPage(1);
                                    }}
                                    className="px-2.5 py-2 text-xs text-red-600 hover:text-red-700 font-medium hover:bg-red-50 rounded-xl transition"
                                    title="Reset Semua Filter"
                                >
                                    Reset
                                </button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Table of Cashbond Records */}
                <div className="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-slate-700">
                            <thead className="bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase text-slate-500 tracking-wider">
                                <tr>
                                    <th className="px-4 py-3.5">Tanggal & Inputer</th>
                                    <th className="px-4 py-3.5">Peminjam</th>
                                    <th className="px-4 py-3.5">Akun Sumber Dana</th>
                                    <th className="px-4 py-3.5">Keperluan Mendadak</th>
                                    <th className="px-4 py-3.5 text-right">Nominal</th>
                                    <th className="px-4 py-3.5 text-center">Status</th>
                                    <th className="px-4 py-3.5">Pelunasan / Kembali</th>
                                    <th className="px-4 py-3.5 text-center">Bukti</th>
                                    <th className="px-4 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {paginatedList.length === 0 ? (
                                    <tr>
                                        <td colSpan={9} className="px-4 py-12 text-center">
                                            <div className="max-w-xs mx-auto text-slate-400">
                                                <WalletCards className="w-10 h-10 mx-auto stroke-1 text-slate-300 mb-2" />
                                                <p className="font-semibold text-slate-700 text-sm">Belum Ada Data Cashbond</p>
                                                <p className="text-xs text-slate-400 mt-1">
                                                    Klik tombol "+ Catat Cashbond Baru" untuk mencatat pengeluaran talangan darurat.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    paginatedList.map((item) => {
                                        const isSettled = item.status === 'LUNAS';
                                        return (
                                            <tr key={item.id} className="hover:bg-slate-50/70 transition">
                                                {/* Tanggal & Inputer */}
                                                <td className="px-4 py-3.5 whitespace-nowrap">
                                                    <div className="font-semibold text-slate-900 text-xs">
                                                        {item.transaction_date || '-'}
                                                    </div>
                                                    <div className="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5">
                                                        <User className="w-3 h-3" />
                                                        <span>{item.created_by_name}</span>
                                                    </div>
                                                </td>

                                                {/* Peminjam */}
                                                <td className="px-4 py-3.5">
                                                    <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-800 font-semibold text-xs border border-slate-200">
                                                        <span>{item.borrower_name}</span>
                                                    </div>
                                                </td>

                                                {/* Akun Sumber */}
                                                <td className="px-4 py-3.5">
                                                    <div className="font-medium text-slate-900 text-xs">
                                                        {item.account_name}
                                                    </div>
                                                    <div className="text-[11px] text-slate-400 font-mono">
                                                        {item.account_code || 'Akun Kas/Bank'}
                                                    </div>
                                                </td>

                                                {/* Keperluan */}
                                                <td className="px-4 py-3.5 max-w-xs">
                                                    <p className="text-xs text-slate-700 line-clamp-2" title={item.purpose}>
                                                        {item.purpose || <span className="text-slate-400 italic">Tanpa catatan keperluan</span>}
                                                    </p>
                                                </td>

                                                {/* Nominal */}
                                                <td className="px-4 py-3.5 text-right whitespace-nowrap">
                                                    <span className="font-bold text-slate-900 text-sm">
                                                        {formatRupiah(item.amount)}
                                                    </span>
                                                </td>

                                                {/* Status */}
                                                <td className="px-4 py-3.5 text-center whitespace-nowrap">
                                                    {isSettled ? (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                            <Check className="w-3 h-3 stroke-[2.5]" />
                                                            Lunas
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                                            <span className="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse" />
                                                            Belum Lunas
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Pelunasan / Kembali */}
                                                <td className="px-4 py-3.5 text-xs">
                                                    {isSettled ? (
                                                        <div>
                                                            <div className="font-medium text-emerald-800">
                                                                {item.settlement_date || '-'}
                                                            </div>
                                                            <div className="text-[11px] text-slate-500 line-clamp-1" title={item.settlement_notes}>
                                                                {item.settlement_notes || 'Telah diganti Direktur'}
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <span className="text-slate-400 text-xs italic">Menunggu pembayaran</span>
                                                    )}
                                                </td>

                                                {/* Bukti Foto / Struk */}
                                                <td className="px-4 py-3.5 text-center whitespace-nowrap">
                                                    {item.proof_image ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => setPreviewImage(item.proof_image)}
                                                            className="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition"
                                                            title="Lihat Bukti Struk/Nota"
                                                        >
                                                            <Eye className="w-3.5 h-3.5" />
                                                            <span>Lihat</span>
                                                        </button>
                                                    ) : (
                                                        <span className="text-slate-300 text-xs">-</span>
                                                    )}
                                                </td>

                                                {/* Actions */}
                                                <td className="px-4 py-3.5 text-right whitespace-nowrap">
                                                    <div className="inline-flex items-center gap-1.5">
                                                        {!isSettled ? (
                                                            <>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => handleOpenSettle(item)}
                                                                    className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition shadow-2xs"
                                                                    title="Tandai Direktur telah membayar/mengembalikan uang"
                                                                >
                                                                    <Check className="w-3.5 h-3.5 stroke-[2.5]" />
                                                                    <span>Settle / Lunas</span>
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => handleOpenEdit(item)}
                                                                    className="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                                                                    title="Edit Data Cashbond"
                                                                >
                                                                    <Edit3 className="w-3.5 h-3.5" />
                                                                </button>
                                                            </>
                                                        ) : (
                                                            <button
                                                                type="button"
                                                                onClick={() => handleReopen(item)}
                                                                className="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-amber-700 hover:bg-amber-50 border border-amber-200 rounded-lg transition"
                                                                title="Batal Lunas (Kembalikan ke status Belum Lunas)"
                                                            >
                                                                <RotateCcw className="w-3 h-3" />
                                                                <span>Batal Lunas</span>
                                                            </button>
                                                        )}

                                                        <button
                                                            type="button"
                                                            onClick={() => handleDelete(item)}
                                                            className="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                                                            title="Hapus Catatan"
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
                    {totalPages > 1 && (
                        <div className="px-4 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs text-slate-600">
                            <div>
                                Menampilkan {(currentPage - 1) * itemsPerPage + 1} -{' '}
                                {Math.min(currentPage * itemsPerPage, filteredList.length)} dari {filteredList.length} transaksi
                            </div>
                            <div className="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    disabled={currentPage === 1}
                                    onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                                    className="p-1.5 rounded-lg border border-slate-200 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    <ChevronLeft className="w-4 h-4" />
                                </button>
                                <span className="font-semibold text-slate-800 px-2">
                                    {currentPage} / {totalPages}
                                </span>
                                <button
                                    type="button"
                                    disabled={currentPage === totalPages}
                                    onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                                    className="p-1.5 rounded-lg border border-slate-200 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    <ChevronRight className="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Drawer Form Tambah / Edit Cashbond */}
            <Drawer
                isOpen={isCreateOpen}
                onClose={() => { if (!isSubmitting) setIsCreateOpen(false); }}
                title={editingItem ? `Edit Cashbond #${editingItem.id}` : 'Catat Cashbond Baru'}
                subtitle="Catatan talangan pengeluaran mendadak apotek yang menggunakan uang pribadi Direktur."
                size="md"
            >
                <form onSubmit={handleSubmit} className="space-y-4 text-xs">
                    {/* Notice */}
                    <div className="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 flex items-start gap-2.5">
                        <Info className="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
                        <div className="leading-relaxed">
                            <strong>Perhatian:</strong> Transaksi ini tidak mengurangi saldo kas/bank yang dipilih.
                            Hanya sebagai catatan penanda sumber uang apotek yang dipakai sementara sampai Direktur membayar kembali.
                        </div>
                    </div>

                    {/* Peminjam */}
                    <div>
                        <label className="block font-semibold text-slate-700 mb-1">
                            Peminjam / Penanggung Jawab <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            required
                            value={formData.borrower_name}
                            onChange={(e) => setFormData({ ...formData, borrower_name: e.target.value })}
                            placeholder="Contoh: Direktur"
                            className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white text-xs"
                        />
                        {formErrors.borrower_name && (
                            <p className="text-red-500 mt-1 text-[11px]">{formErrors.borrower_name}</p>
                        )}
                    </div>

                    {/* Akun Kas & Bank yang Terpakai */}
                    <div>
                        <label className="block font-semibold text-slate-700 mb-1">
                            Akun Kas & Bank (Sumber Dana Sementara) <span className="text-red-500">*</span>
                        </label>
                        <select
                            required
                            value={formData.account_id}
                            onChange={(e) => setFormData({ ...formData, account_id: e.target.value })}
                            className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white text-xs text-slate-800"
                        >
                            {kasBankAccounts.map((acc) => (
                                <option key={acc.id} value={acc.id}>
                                    {acc.code ? `${acc.code} - ` : ''}{acc.name} (Saldo: {formatRupiah(acc.balance)})
                                </option>
                            ))}
                        </select>
                        <p className="text-[11px] text-slate-400 mt-1">
                            Pilih akun yang uang fisiknya terpakai (saldo di sistem tidak akan berkurang).
                        </p>
                        {formErrors.account_id && (
                            <p className="text-red-500 mt-1 text-[11px]">{formErrors.account_id}</p>
                        )}
                    </div>

                    {/* Tanggal & Nominal */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label className="block font-semibold text-slate-700 mb-1">
                                Tanggal Pemakaian <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                required
                                value={formData.transaction_date}
                                onChange={(e) => setFormData({ ...formData, transaction_date: e.target.value })}
                                className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white text-xs"
                            />
                            {formErrors.transaction_date && (
                                <p className="text-red-500 mt-1 text-[11px]">{formErrors.transaction_date}</p>
                            )}
                        </div>

                        <div>
                            <label className="block font-semibold text-slate-700 mb-1">
                                Nominal Talangan (Rp) <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                required
                                min="1"
                                step="any"
                                value={formData.amount}
                                onChange={(e) => setFormData({ ...formData, amount: e.target.value })}
                                placeholder="0"
                                className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white text-xs font-semibold"
                            />
                            {formData.amount > 0 && (
                                <div className="mt-1 text-[11px] text-blue-600 font-semibold">
                                    {formatRupiah(formData.amount)} &bull; <span className="text-slate-500 font-normal">{terbilang(formData.amount)}</span>
                                </div>
                            )}
                            {formErrors.amount && (
                                <p className="text-red-500 mt-1 text-[11px]">{formErrors.amount}</p>
                            )}
                        </div>
                    </div>

                    {/* Keperluan / Keterangan Mendadak */}
                    <div>
                        <label className="block font-semibold text-slate-700 mb-1">
                            Keperluan Mendadak / Keterangan
                        </label>
                        <textarea
                            rows={3}
                            value={formData.purpose}
                            onChange={(e) => setFormData({ ...formData, purpose: e.target.value })}
                            placeholder="Contoh: Talangan beli perlengkapan darurat / keperluan mendadak Direktur..."
                            className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white text-xs"
                        />
                        {formErrors.purpose && (
                            <p className="text-red-500 mt-1 text-[11px]">{formErrors.purpose}</p>
                        )}
                    </div>

                    {/* Bukti Foto / Struk */}
                    <div>
                        <label className="block font-semibold text-slate-700 mb-1">
                            Unggah Bukti Struk / Nota / Transfer (Opsional)
                        </label>
                        <div className="border border-dashed border-slate-300 rounded-xl p-3 bg-slate-50 hover:bg-slate-100 transition text-center cursor-pointer relative">
                            <input
                                type="file"
                                accept="image/*,application/pdf"
                                onChange={(e) => setFormData({ ...formData, proof: e.target.files[0] || null })}
                                className="absolute inset-0 opacity-0 w-full h-full cursor-pointer"
                            />
                            <div className="flex flex-col items-center justify-center">
                                <UploadCloud className="w-6 h-6 text-slate-400 mb-1" />
                                <span className="text-slate-600 text-xs font-medium">
                                    {formData.proof ? formData.proof.name : 'Pilih file gambar atau PDF (Maks. 5MB)'}
                                </span>
                            </div>
                        </div>
                        {editingItem && editingItem.proof_image && !formData.proof && (
                            <p className="text-[11px] text-slate-500 mt-1">
                                Bukti tersimpan sebelumnya:{' '}
                                <button
                                    type="button"
                                    onClick={() => setPreviewImage(editingItem.proof_image)}
                                    className="text-blue-600 underline"
                                >
                                    Lihat Bukti
                                </button>
                            </p>
                        )}
                        {formErrors.proof && (
                            <p className="text-red-500 mt-1 text-[11px]">{formErrors.proof}</p>
                        )}
                    </div>

                    {/* Actions */}
                    <div className="pt-4 border-t border-slate-200 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            disabled={isSubmitting}
                            onClick={() => setIsCreateOpen(false)}
                            className="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 font-medium text-xs transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={isSubmitting}
                            className="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs rounded-xl shadow-xs transition disabled:opacity-50"
                        >
                            {isSubmitting ? (
                                <span>Menyimpan...</span>
                            ) : (
                                <>
                                    <Check className="w-3.5 h-3.5 stroke-[2.5]" />
                                    <span>{editingItem ? 'Simpan Perubahan' : 'Catat Cashbond'}</span>
                                </>
                            )}
                        </button>
                    </div>
                </form>
            </Drawer>

            {/* Modal Pelunasan (Settle) */}
            {settlingItem && (
                <div className="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full p-5 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div className="flex items-center gap-2">
                                <div className="p-2 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100">
                                    <CheckCircle2 className="w-5 h-5" />
                                </div>
                                <div>
                                    <h3 className="font-bold text-slate-900 text-sm">
                                        Pelunasan Cashbond #{settlingItem.id}
                                    </h3>
                                    <p className="text-xs text-slate-500">
                                        Konfirmasi bahwa dana telah dibayar/dikembalikan oleh Direktur.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setSettlingItem(null)}
                                className="p-1 rounded-lg text-slate-400 hover:text-slate-600"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        {/* Summary detail */}
                        <div className="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5 text-xs text-slate-600">
                            <div className="flex justify-between">
                                <span>Peminjam:</span>
                                <span className="font-semibold text-slate-800">{settlingItem.borrower_name}</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Nominal yang Harus Dibayar:</span>
                                <span className="font-bold text-slate-900 text-sm">{formatRupiah(settlingItem.amount)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Sumber Kas/Bank:</span>
                                <span className="font-medium text-slate-700">{settlingItem.account_name}</span>
                            </div>
                            {settlingItem.purpose && (
                                <div className="pt-1 border-t border-slate-200/60 text-[11px] text-slate-500">
                                    Keperluan: "{settlingItem.purpose}"
                                </div>
                            )}
                        </div>

                        <form onSubmit={handleConfirmSettle} className="space-y-3 text-xs">
                            <div>
                                <label className="block font-semibold text-slate-700 mb-1">
                                    Tanggal Pengembalian / Pelunasan <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="date"
                                    required
                                    value={settleForm.settlement_date}
                                    onChange={(e) => setSettleForm({ ...settleForm, settlement_date: e.target.value })}
                                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white text-xs"
                                />
                            </div>

                            <div>
                                <label className="block font-semibold text-slate-700 mb-1">
                                    Catatan / Cara Pengembalian
                                </label>
                                <textarea
                                    rows={2}
                                    value={settleForm.settlement_notes}
                                    onChange={(e) => setSettleForm({ ...settleForm, settlement_notes: e.target.value })}
                                    placeholder="Contoh: Ditransfer langsung ke rekening BCA apotek / Diserahkan tunai ke kasir..."
                                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white text-xs"
                                />
                            </div>

                            <div className="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setSettlingItem(null)}
                                    className="px-3.5 py-2 text-xs font-medium text-slate-600 hover:bg-slate-100 rounded-xl transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmitting}
                                    className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-xs transition disabled:opacity-50"
                                >
                                    {isSubmitting ? 'Memproses...' : 'Tandai Lunas'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Preview Bukti Gambar */}
            {previewImage && (
                <div
                    onClick={() => setPreviewImage(null)}
                    className="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4"
                >
                    <div
                        onClick={(e) => e.stopPropagation()}
                        className="bg-white rounded-2xl max-w-2xl w-full p-4 space-y-3 overflow-hidden shadow-2xl relative"
                    >
                        <div className="flex items-center justify-between pb-2 border-b border-slate-200">
                            <h4 className="font-bold text-slate-900 text-sm">Bukti Dokumen / Nota Struk</h4>
                            <button
                                type="button"
                                onClick={() => setPreviewImage(null)}
                                className="p-1 rounded-lg text-slate-400 hover:text-slate-600"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <div className="max-h-[75vh] overflow-auto flex items-center justify-center bg-slate-50 rounded-xl p-2">
                            {previewImage.endsWith('.pdf') ? (
                                <iframe src={previewImage} className="w-full h-[60vh] rounded-lg" title="Bukti PDF" />
                            ) : (
                                <img
                                    src={previewImage}
                                    alt="Bukti Cashbond"
                                    className="max-h-[70vh] w-auto object-contain rounded-lg shadow-xs"
                                />
                            )}
                        </div>
                        <div className="flex justify-end pt-2">
                            <a
                                href={previewImage}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50 rounded-lg transition"
                            >
                                <ExternalLink className="w-3.5 h-3.5" />
                                <span>Buka di Tab Baru</span>
                            </a>
                        </div>
                    </div>
                </div>
            )}
        </FinanceLayout>
    );
}

