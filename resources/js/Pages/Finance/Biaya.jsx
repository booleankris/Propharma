import React, { useState, useMemo, useRef, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import FinanceLayout from './Layouts/FinanceLayout';
import Drawer from './Components/Drawer';
import BuktiKasKeluarModal from './Components/BuktiKasKeluarModal';
import { formatRupiah, formatNumberOnly, terbilang } from './Components/Utils';
import {
    CircleDollarSign, Search, Plus, Calendar, AlertCircle, ChevronLeft, ChevronRight,
    Printer, FileSpreadsheet, Trash2, ArrowUp, ArrowDown, Landmark,
    CreditCard, User, FileText, CheckCircle2, DollarSign, Wallet,
    Edit3, Eye, X, PieChart, Tag, ArrowRight, RotateCcw, Clock, Building2,
    Layers, HelpCircle, ChevronDown, ChevronUp, Receipt
} from 'lucide-react';
function BebanCombobox({ beban = [], bebanSelected = '', onBebanSelected, placeholder = "Cari beban..." }) {
    const [query, setQuery] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    const containerRef = useRef(null);


    useEffect(() => {
        if (bebanSelected) {
            const found = beban.find((b) => String(b.id) === String(bebanSelected));
            setQuery(found ? found.name : '');
        } else {
            setQuery('');
        }
    }, [bebanSelected, beban]);
    useEffect(() => {
        const handleClickOutside = (e) => {
            if (containerRef.current && !containerRef.current.contains(e.target)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const suggestions = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q && !isOpen) return [];
        if (!q && isOpen) return beban.slice(0, 15);
        return beban
            .filter((p) => p.name.toLowerCase().includes(q) || (p.code && p.code.toLowerCase().includes(q)))
            .slice(0, 15);
    }, [beban, query, isOpen]);

    const handleSelect = (item) => {
        setQuery(item.name);
        onBebanSelected(item.id);
        setIsOpen(false);
    };

    const handleClear = () => {
        setQuery('');
        onBebanSelected('');
        setIsOpen(false);
    };
    return (
        <div ref={containerRef} className="relative w-full sm:w-72">
            <div className="relative flex items-center">
                <CreditCard className={`w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none ${bebanSelected ? 'text-blue-600' : 'text-slate-400'}`} />
                <input
                    type="text"
                    value={query}
                    onChange={(e) => {
                        setQuery(e.target.value);
                        setIsOpen(true);
                        if (!e.target.value) {
                            onBebanSelected('');
                        }
                    }}
                    onFocus={() => setIsOpen(true)}
                    placeholder={placeholder}
                    className={`w-full pl-9 pr-8 py-2 bg-slate-50 border rounded-xl text-xs transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white ${bebanSelected
                        ? 'border-blue-500 ring-1 ring-blue-100 font-semibold text-blue-900 bg-blue-50/40'
                        : 'border-slate-200 text-slate-800'
                        }`}
                />
                {query ? (
                    <button
                        type="button"
                        onClick={handleClear}
                        className="absolute right-2.5 top-1/2 -translate-y-1/2 p-0.5 text-slate-400 hover:text-red-500 rounded-full hover:bg-red-50 transition"
                        title="Hapus Filter Jenis Beban"
                    >
                        <X className="w-3.5 h-3.5" />
                    </button>
                ) : (
                    <button
                        type="button"
                        onClick={() => setIsOpen(!isOpen)}
                        className="absolute right-2.5 top-1/2 -translate-y-1/2 p-0.5 text-slate-400 hover:text-slate-600 rounded"
                    >
                        <ChevronDown className="w-3.5 h-3.5" />
                    </button>
                )}
            </div>

            {/* Dropdown Suggestions Popover */}
            {isOpen && (
                <div className="absolute left-0 right-0 top-full mt-1.5 z-50 bg-white rounded-xl border border-slate-200 shadow-xl max-h-64 overflow-y-auto divide-y divide-slate-100 animate-in fade-in zoom-in-95 duration-150">
                    <div className="p-2 bg-slate-50 text-[10px] font-semibold text-slate-500 flex items-center justify-between">
                        <span>Pilih Kategori Beban...</span>
                        <span className="text-slate-400">{suggestions.length} saran</span>
                    </div>
                    {bebanSelected && (
                        <button
                            type="button"
                            onClick={handleClear}
                            className="w-full text-left px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 flex items-center gap-2 transition"
                        >
                            <X className="w-3.5 h-3.5" />
                            <span>Reset Filter (Semua Jenis Beban)</span>
                        </button>
                    )}
                    {suggestions.length === 0 ? (
                        <div className="p-3 text-center text-xs text-slate-400">
                            Tidak ada jenis beban yang cocok.
                        </div>
                    ) : (
                        suggestions.map((p) => (
                            <button
                                key={p.id}
                                type="button"
                                onClick={() => handleSelect(p)}
                                className={`w-full text-left px-3 py-2.5 text-xs hover:bg-blue-50/70 flex items-center justify-between gap-2 transition ${String(bebanSelected) === String(p.id) ? 'bg-blue-50 font-bold text-blue-700' : 'text-slate-700'
                                    }`}
                            >
                                <div className="flex items-center gap-2 overflow-hidden">
                                    <CreditCard className={`w-3.5 h-3.5 shrink-0 ${String(bebanSelected) === String(p.id) ? 'text-blue-600' : 'text-slate-400'}`} />
                                    <span className="truncate">{p.name}</span>
                                </div>
                                {p.code && (
                                    <span className="shrink-0 text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-slate-500">
                                        {p.code}
                                    </span>
                                )}
                            </button>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}
export default function Biaya({
    biayaList = [],
    expenseAccounts = [],
    kasBankAccounts = [],
    beban = [],
    stats = {},
    summary = {},
    branchContext = {}
}) {
    const { errors: pageErrors } = usePage().props;

    // Filters and Search
    const [searchTerm, setSearchTerm] = useState('');
    const [filterExpenseAccount, setFilterExpenseAccount] = useState('');
    const [filterKasAccount, setFilterKasAccount] = useState('');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [datePreset, setDatePreset] = useState('all'); // 'all' | 'today' | 'this_month' | 'last_month' | 'custom'
    const [sortOrder, setSortOrder] = useState('desc'); // 'desc' | 'asc'
    const [currentPage, setCurrentPage] = useState(1);
    const itemsPerPage = 10;

    // Breakdown Widget Toggle
    const [showTopExpenses, setShowTopExpenses] = useState(true);

    // Modals & Drawer State
    const [isCreateDrawerOpen, setIsCreateDrawerOpen] = useState(false);
    const [editingBiaya, setEditingBiaya] = useState(null);
    const [detailBiaya, setDetailBiaya] = useState(null);
    const [isBuktiModalOpen, setIsBuktiModalOpen] = useState(false);
    const [selectedBiayaForBukti, setSelectedBiayaForBukti] = useState(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Form Data State
    const [formData, setFormData] = useState({
        account_id: kasBankAccounts[0]?.id || '',
        expense_account_id: expenseAccounts[0]?.id || '',
        payment_date: new Date().toISOString().split('T')[0],
        amount: '',
        recipient: '',
        reference_number: '',
        notes: '',
    });
    const [formErrors, setFormErrors] = useState({});

    // Date Preset Handler
    const handleDatePreset = (preset) => {
        setDatePreset(preset);
        setCurrentPage(1);

        const now = new Date();
        const formatDate = (d) => {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        if (preset === 'all') {
            setStartDate('');
            setEndDate('');
        } else if (preset === 'today') {
            const todayStr = formatDate(now);
            setStartDate(todayStr);
            setEndDate(todayStr);
        } else if (preset === 'this_month') {
            const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
            const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            setStartDate(formatDate(firstDay));
            setEndDate(formatDate(lastDay));
        } else if (preset === 'last_month') {
            const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);
            setStartDate(formatDate(firstDay));
            setEndDate(formatDate(lastDay));
        }
    };

    // Filtered & Sorted Biaya List
    const filteredBiaya = useMemo(() => {
        let result = biayaList.filter((item) => {
            const matchesSearch =
                searchTerm === '' ||
                item.reference_number?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.recipient?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.notes?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.expense_account_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.account_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.user_name?.toLowerCase().includes(searchTerm.toLowerCase());

            const matchesExpense =
                filterExpenseAccount === '' || String(item.expense_account_id) === String(filterExpenseAccount);

            const matchesKas =
                filterKasAccount === '' || String(item.account_id) === String(filterKasAccount);

            const matchesStartDate =
                !startDate || (item.payment_date && item.payment_date >= startDate);

            const matchesEndDate =
                !endDate || (item.payment_date && item.payment_date <= endDate);

            return matchesSearch && matchesExpense && matchesKas && matchesStartDate && matchesEndDate;
        });

        return result.sort((a, b) => {
            const dateA = a.payment_date ? new Date(a.payment_date).getTime() : 0;
            const dateB = b.payment_date ? new Date(b.payment_date).getTime() : 0;
            if (sortOrder === 'asc') {
                return dateA - dateB || a.id - b.id;
            }
            return dateB - dateA || b.id - a.id;
        });
    }, [biayaList, searchTerm, filterExpenseAccount, filterKasAccount, startDate, endDate, sortOrder]);

    const totalPages = Math.ceil(filteredBiaya.length / itemsPerPage) || 1;
    const paginatedBiaya = useMemo(() => {
        const start = (currentPage - 1) * itemsPerPage;
        return filteredBiaya.slice(start, start + itemsPerPage);
    }, [filteredBiaya, currentPage, itemsPerPage]);

    // Total Nominal Filtered
    const totalFilteredAmount = useMemo(() => {
        return filteredBiaya.reduce((acc, curr) => acc + (Number(curr.amount) || 0), 0);
    }, [filteredBiaya]);

    // Handle open create drawer
    const handleOpenCreateDrawer = () => {
        setEditingBiaya(null);
        setFormData({
            account_id: kasBankAccounts[0]?.id || '',
            expense_account_id: expenseAccounts[0]?.id || '',
            payment_date: new Date().toISOString().split('T')[0],
            amount: '',
            recipient: '',
            reference_number: '',
            notes: '',
        });
        setFormErrors({});
        setIsCreateDrawerOpen(true);
    };

    // Handle open edit drawer
    const handleOpenEditDrawer = (item) => {
        setEditingBiaya(item);
        setFormData({
            account_id: item.account_id || kasBankAccounts[0]?.id || '',
            expense_account_id: item.expense_account_id || expenseAccounts[0]?.id || '',
            payment_date: item.payment_date ? item.payment_date.split('T')[0] : new Date().toISOString().split('T')[0],
            amount: item.amount || '',
            recipient: item.recipient === '-' ? '' : (item.recipient || ''),
            reference_number: item.reference_number || '',
            notes: item.notes || '',
        });
        setFormErrors({});
        setIsCreateDrawerOpen(true);
        if (detailBiaya && detailBiaya.id === item.id) {
            setDetailBiaya(null);
        }
    };

    // Handle submit (Create or Update)
    const handleSubmitForm = (e) => {
        e.preventDefault();
        setFormErrors({});

        const errors = {};
        if (!formData.account_id) errors.account_id = 'Pilih akun sumber kas/bank';
        if (!formData.expense_account_id) errors.expense_account_id = 'Pilih kategori akun biaya';
        if (!formData.payment_date) errors.payment_date = 'Tanggal wajib diisi';
        if (!formData.amount || Number(formData.amount) <= 0) errors.amount = 'Nominal harus lebih dari 0';

        if (Object.keys(errors).length > 0) {
            setFormErrors(errors);
            return;
        }

        setIsSubmitting(true);

        if (editingBiaya) {
            router.put(`/finance/biaya/${editingBiaya.id}`, formData, {
                onSuccess: () => {
                    setIsSubmitting(false);
                    setIsCreateDrawerOpen(false);
                    setEditingBiaya(null);
                },
                onError: (errs) => {
                    setIsSubmitting(false);
                    setFormErrors(errs);
                },
            });
        } else {
            router.post('/finance/biaya', formData, {
                onSuccess: () => {
                    setIsSubmitting(false);
                    setIsCreateDrawerOpen(false);
                },
                onError: (errs) => {
                    setIsSubmitting(false);
                    setFormErrors(errs);
                },
            });
        }
    };

    // Handle delete Biaya
    const handleDeleteBiaya = (item) => {
        if (confirm(`Apakah Anda yakin ingin menghapus biaya "${item.reference_number}" sebesar ${formatRupiah(item.amount)}? Saldo akun kas terkait akan dikembalikan.`)) {
            router.delete(`/finance/biaya/${item.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    if (detailBiaya && detailBiaya.id === item.id) {
                        setDetailBiaya(null);
                    }
                }
            });
        }
    };

    // Handle open Cetak Bukti
    const handleOpenCetakBukti = (item) => {
        setSelectedBiayaForBukti(item);
        setIsBuktiModalOpen(true);
    };

    const selectedKasAccount = kasBankAccounts.find(
        (a) => String(a.id) === String(formData.account_id)
    );

    const allBeban = useMemo(() => {
        const map = new Map();
        (expenseAccounts || []).forEach((c) => {
            if (c.name && c.id) {
                map.set(String(c.id), { id: c.id, name: c.name.trim(), code: c.code || '' });
            }
        });
        return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name));
    }, [expenseAccounts]);



    // Common expense quick-presets for amount
    const quickAmounts = [50000, 100000, 250000, 500000, 1000000];

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();
        if (searchTerm) params.append('search', searchTerm);
        if (filterExpenseAccount) params.append('expense_account_id', filterExpenseAccount);
        if (filterKasAccount) params.append('account_id', filterKasAccount);
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        if (sortOrder) params.append('sort_order', sortOrder);
        if (branchContext?.activePharmacy?.id) {
            params.append('pharmacy_id', branchContext.activePharmacy.id);
        }
        const qs = params.toString();
        return `/finance/export/biaya${qs ? `?${qs}` : ''}`;
    }, [searchTerm, filterExpenseAccount, filterKasAccount, startDate, endDate, sortOrder, branchContext]);

    return (
        <FinanceLayout
            title="Biaya Operasional"
            subtitle="Pencatatan pengeluaran beban operasional, listrik, air, gaji, ATK, dan beban lainnya"
            stats={stats}
        >
            <div className="space-y-6">
                {/* 1. KARTU STATISTIK RINGKASAN BIAYA */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:shadow-xs transition group">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Biaya Bulan Ini
                            </span>
                            <div className="w-9 h-9 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600 group-hover:scale-105 transition">
                                <Calendar className="w-5 h-5" />
                            </div>
                        </div>
                        <p className="text-xl sm:text-2xl font-black text-rose-700 mt-2 tracking-tight">
                            {formatRupiah(summary.totalMonth || 0)}
                        </p>
                        <p className="text-[11px] text-slate-500 mt-1 font-medium">
                            {summary.countMonth || 0} transaksi bulan ini
                        </p>
                    </div>

                    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:shadow-xs transition group">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Biaya Hari Ini
                            </span>
                            <div className="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600 group-hover:scale-105 transition">
                                <DollarSign className="w-5 h-5" />
                            </div>
                        </div>
                        <p className="text-xl sm:text-2xl font-black text-slate-900 mt-2 tracking-tight">
                            {formatRupiah(summary.totalToday || 0)}
                        </p>
                        <p className="text-[11px] text-slate-500 mt-1 font-medium">
                            Pengeluaran tercatat hari ini
                        </p>
                    </div>

                    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:shadow-xs transition group">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Total Keseluruhan
                            </span>
                            <div className="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-700 group-hover:scale-105 transition">
                                <Wallet className="w-5 h-5" />
                            </div>
                        </div>
                        <p className="text-xl sm:text-2xl font-black text-slate-900 mt-2 tracking-tight">
                            {formatRupiah(summary.totalAllTime || 0)}
                        </p>
                        <p className="text-[11px] text-slate-500 mt-1 font-medium">
                            Akumulasi seluruh transaksi biaya
                        </p>
                    </div>

                    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:shadow-xs transition group">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Total Transaksi
                            </span>
                            <div className="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 group-hover:scale-105 transition">
                                <FileText className="w-5 h-5" />
                            </div>
                        </div>
                        <p className="text-xl sm:text-2xl font-black text-slate-900 mt-2 tracking-tight">
                            {summary.countTotal || 0}
                        </p>
                        <p className="text-[11px] text-slate-500 mt-1 font-medium">
                            Voucher pengeluaran kas (BKK)
                        </p>
                    </div>
                </div>

                {/* 2. TOP EXPENSES BREAKDOWN SECTION */}
                {summary.topExpenses && summary.topExpenses.length > 0 && (
                    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                            <div className="flex items-center gap-2">
                                <div className="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                                    <PieChart className="w-4 h-4" />
                                </div>
                                <div>
                                    <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider">
                                        Komposisi Beban Terbesar
                                    </h3>
                                    <p className="text-[11px] text-slate-400">
                                        5 akun beban operasional dengan alokasi pengeluaran dana tertinggi
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowTopExpenses(!showTopExpenses)}
                                className="text-xs text-slate-500 hover:text-slate-800 flex items-center gap-1 font-medium cursor-pointer"
                            >
                                <span>{showTopExpenses ? 'Sembunyikan' : 'Tampilkan'}</span>
                                {showTopExpenses ? <ChevronUp className="w-3.5 h-3.5" /> : <ChevronDown className="w-3.5 h-3.5" />}
                            </button>
                        </div>

                        {showTopExpenses && (
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3.5 pt-1">
                                {summary.topExpenses.map((exp, idx) => {
                                    const percentage = summary.totalAllTime > 0
                                        ? Math.round((exp.total_amount / summary.totalAllTime) * 100)
                                        : 0;

                                    return (
                                        <div
                                            key={idx}
                                            className="p-3.5 rounded-xl bg-slate-50/70 border border-slate-200/80 hover:bg-slate-50 hover:border-rose-200 transition"
                                        >
                                            <div className="flex items-center justify-between gap-1 text-[11px] text-slate-400 font-mono">
                                                <span>{exp.account_code}</span>
                                                <span className="font-bold text-slate-700 bg-white px-1.5 py-0.5 rounded border border-slate-200 text-[10px]">
                                                    {percentage}%
                                                </span>
                                            </div>
                                            <h4 className="font-bold text-xs text-slate-800 mt-1 truncate" title={exp.account_name}>
                                                {exp.account_name}
                                            </h4>
                                            <p className="font-extrabold text-sm text-rose-700 mt-1">
                                                {formatRupiah(exp.total_amount)}
                                            </p>
                                            <div className="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden mt-2">
                                                <div
                                                    className="bg-rose-500 h-full rounded-full transition-all duration-500"
                                                    style={{ width: `${Math.min(100, Math.max(5, percentage))}%` }}
                                                />
                                            </div>
                                            <div className="text-[10px] text-slate-400 mt-1.5 flex justify-between items-center">
                                                <span>Frekuensi:</span>
                                                <span className="font-semibold text-slate-600">{exp.total_count} transaksi</span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                )}

                {/* 3. TOOLBAR FILTER, PERIODE & AKSI */}
                <div className="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs space-y-3.5">
                    {/* Baris Atas: Quick Period Chips & Actions */}
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                        <div className="flex items-center gap-1.5 flex-wrap">
                            <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wider mr-1">
                                Periode:
                            </span>
                            <button
                                type="button"
                                onClick={() => handleDatePreset('all')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-lg transition cursor-pointer ${datePreset === 'all'
                                    ? 'bg-rose-600 text-white shadow-2xs'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                    }`}
                            >
                                Semua
                            </button>
                            <button
                                type="button"
                                onClick={() => handleDatePreset('today')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-lg transition cursor-pointer ${datePreset === 'today'
                                    ? 'bg-rose-600 text-white shadow-2xs'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                    }`}
                            >
                                Hari Ini
                            </button>
                            <button
                                type="button"
                                onClick={() => handleDatePreset('this_month')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-lg transition cursor-pointer ${datePreset === 'this_month'
                                    ? 'bg-rose-600 text-white shadow-2xs'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                    }`}
                            >
                                Bulan Ini
                            </button>
                            <button
                                type="button"
                                onClick={() => handleDatePreset('last_month')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-lg transition cursor-pointer ${datePreset === 'last_month'
                                    ? 'bg-rose-600 text-white shadow-2xs'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                    }`}
                            >
                                Bulan Lalu
                            </button>
                        </div>

                        {/* Kanan: Export & Tambah Biaya */}
                        <div className="flex items-center gap-2 self-end md:self-center">
                            {/* Tombol Export Excel */}
                            <a
                                href={exportUrl}
                                className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-xl text-xs font-semibold transition shadow-2xs cursor-pointer"
                                title="Export data biaya operasional ke Excel (.xlsx)"
                            >
                                <FileSpreadsheet className="w-3.5 h-3.5 text-emerald-600" />
                                <span>Export Excel</span>
                            </a>

                            {/* Tombol Tambah Biaya */}
                            <button
                                type="button"
                                onClick={handleOpenCreateDrawer}
                                className="inline-flex items-center gap-1.5 px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer"
                            >
                                <Plus className="w-4 h-4" />
                                <span>Catat Biaya Baru</span>
                            </button>
                        </div>
                    </div>

                    {/* Baris Bawah: Search, Dropdowns, Date Range & Sort */}
                    <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                        <div className="flex items-center gap-2.5 flex-wrap flex-1">
                            {/* Input Cari */}
                            <div className="relative min-w-[220px] flex-1 max-w-sm">
                                <Search className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                                <input
                                    type="text"
                                    placeholder="Cari no. bukti, penerima, catatan, petugas..."
                                    value={searchTerm}
                                    onChange={(e) => {
                                        setSearchTerm(e.target.value);
                                        setCurrentPage(1);
                                    }}
                                    className="w-full pl-9 pr-4 py-2 text-xs bg-slate-50 hover:bg-slate-100/80 focus:bg-white border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition"
                                />
                            </div>

                            {/* Filter Akun Beban */}
                            <BebanCombobox
                                beban={allBeban}
                                bebanSelected={filterExpenseAccount}
                                onBebanSelected={(val) => { setFilterExpenseAccount(val); setCurrentPage(1); }}
                                placeholder="Cari Jenis Beban..."
                            />

                            {/* Filter Akun Kas Sumber */}
                            <select
                                value={filterKasAccount}
                                onChange={(e) => {
                                    setFilterKasAccount(e.target.value);
                                    setCurrentPage(1);
                                }}
                                className="px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl text-slate-700 font-medium hover:bg-slate-50 focus:outline-hidden focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition cursor-pointer max-w-[190px]"
                            >
                                <option value="">Semua Sumber Kas/Bank</option>
                                {kasBankAccounts.map((acc) => (
                                    <option key={acc.id} value={acc.id}>
                                        {acc.name}
                                    </option>
                                ))}
                            </select>

                            {/* Input Tanggal Mulai & Akhir */}
                            <div className="flex items-center gap-1.5 text-xs text-slate-500">
                                <div className="relative">
                                    <input
                                        type="date"
                                        value={startDate}
                                        onChange={(e) => {
                                            setStartDate(e.target.value);
                                            setDatePreset('custom');
                                            setCurrentPage(1);
                                        }}
                                        className="px-2.5 py-1.5 text-xs bg-white border border-slate-200 rounded-xl text-slate-700 hover:bg-slate-50 focus:outline-hidden focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition"
                                        title="Tanggal mulai"
                                    />
                                </div>
                                <span className="text-slate-400">s/d</span>
                                <div className="relative">
                                    <input
                                        type="date"
                                        value={endDate}
                                        onChange={(e) => {
                                            setEndDate(e.target.value);
                                            setDatePreset('custom');
                                            setCurrentPage(1);
                                        }}
                                        className="px-2.5 py-1.5 text-xs bg-white border border-slate-200 rounded-xl text-slate-700 hover:bg-slate-50 focus:outline-hidden focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition"
                                        title="Tanggal akhir"
                                    />
                                </div>
                                {(startDate || endDate) && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setStartDate('');
                                            setEndDate('');
                                            setDatePreset('all');
                                            setCurrentPage(1);
                                        }}
                                        className="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition"
                                        title="Hapus filter tanggal"
                                    >
                                        <RotateCcw className="w-3.5 h-3.5" />
                                    </button>
                                )}
                            </div>

                            {/* Sort Tanggal */}
                            <button
                                type="button"
                                onClick={() => {
                                    setSortOrder((prev) => (prev === 'asc' ? 'desc' : 'asc'));
                                    setCurrentPage(1);
                                }}
                                className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border transition cursor-pointer ${sortOrder === 'asc'
                                    ? 'bg-rose-50 border-rose-300 text-rose-800 font-bold'
                                    : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
                                    }`}
                                title="Klik untuk mengubah urutan tanggal"
                            >
                                {sortOrder === 'asc' ? (
                                    <ArrowUp className="w-3.5 h-3.5 text-rose-600" />
                                ) : (
                                    <ArrowDown className="w-3.5 h-3.5 text-slate-500" />
                                )}
                                <span>{sortOrder === 'asc' ? 'Terlama (ASC)' : 'Terbaru (DESC)'}</span>
                            </button>
                        </div>
                    </div>

                    {/* Ringkasan Filter Aktif */}
                    {(searchTerm || filterExpenseAccount || filterKasAccount || startDate || endDate) && (
                        <div className="pt-2 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                            <span className="flex items-center gap-1.5">
                                <span className="font-semibold text-slate-700">{filteredBiaya.length}</span> data ditemukan
                                &bull; Total Filtered: <strong className="text-rose-700 font-bold">{formatRupiah(totalFilteredAmount)}</strong>
                            </span>
                            <button
                                type="button"
                                onClick={() => {
                                    setSearchTerm('');
                                    setFilterExpenseAccount('');
                                    setFilterKasAccount('');
                                    setStartDate('');
                                    setEndDate('');
                                    setDatePreset('all');
                                    setCurrentPage(1);
                                }}
                                className="text-rose-600 hover:underline font-semibold text-[11px] cursor-pointer"
                            >
                                Reset Semua Filter
                            </button>
                        </div>
                    )}
                </div>

                {/* 4. TABEL DATA BIAYA OPERASIONAL */}
                <div className="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-2xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50/80 border-b border-slate-200/80 text-slate-500 font-semibold uppercase tracking-wider text-[11px]">
                                <tr>
                                    <th className="py-3.5 px-4">No. Bukti / Ref</th>
                                    <th className="py-3.5 px-4">Tanggal</th>
                                    <th className="py-3.5 px-4">Kategori Akun Beban</th>
                                    <th className="py-3.5 px-4">Sumber Kas/Bank</th>
                                    <th className="py-3.5 px-4">Penerima & Keperluan</th>
                                    <th className="py-3.5 px-4 text-right">Nominal Pengeluaran</th>
                                    <th className="py-3.5 px-4">Petugas</th>
                                    <th className="py-3.5 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-slate-700">
                                {paginatedBiaya.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="py-12 text-center text-slate-400">
                                            <CircleDollarSign className="w-10 h-10 mx-auto text-slate-300 mb-2 stroke-1" />
                                            <p className="font-semibold text-slate-600">Tidak ada data biaya operasional ditemukan</p>
                                            <p className="text-[11px] text-slate-400 mt-0.5">
                                                Coba sesuaikan kata kunci pencarian atau catat biaya baru
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    paginatedBiaya.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-50/80 transition group">
                                            {/* No. Bukti */}
                                            <td className="py-3 px-4 font-mono font-bold text-slate-800 whitespace-nowrap">
                                                <button
                                                    type="button"
                                                    onClick={() => setDetailBiaya(item)}
                                                    className="hover:text-rose-600 hover:underline cursor-pointer flex items-center gap-1.5"
                                                    title="Klik untuk melihat rincian voucher biaya"
                                                >
                                                    <span className="w-2 h-2 rounded-full bg-rose-500 shrink-0" />
                                                    <span>{item.reference_number}</span>
                                                </button>
                                            </td>

                                            {/* Tanggal */}
                                            <td className="py-3 px-4 font-medium text-slate-600 whitespace-nowrap">
                                                {item.formatted_date}
                                            </td>

                                            {/* Akun Beban */}
                                            <td className="py-3 px-4">
                                                <div>
                                                    <span className="font-bold text-slate-900 block">
                                                        {item.expense_account_name}
                                                    </span>
                                                    <span className="text-[10px] text-slate-400 font-mono">
                                                        {item.expense_account_code}
                                                    </span>
                                                </div>
                                            </td>

                                            {/* Sumber Kas/Bank */}
                                            <td className="py-3 px-4">
                                                <div className="flex items-center gap-1.5">
                                                    <Landmark className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                                    <div>
                                                        <span className="font-semibold text-slate-800 block">
                                                            {item.account_name}
                                                        </span>
                                                        <span className="text-[10px] text-slate-400 font-mono">
                                                            {item.account_code}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>

                                            {/* Penerima & Keperluan */}
                                            <td className="py-3 px-4 max-w-xs">
                                                <div className="font-semibold text-slate-800">
                                                    {item.recipient}
                                                </div>
                                                {item.notes && (
                                                    <p className="text-[11px] text-slate-500 truncate" title={item.notes}>
                                                        {item.notes}
                                                    </p>
                                                )}
                                            </td>

                                            {/* Nominal */}
                                            <td className="py-3 px-4 text-right whitespace-nowrap">
                                                <span className="font-black text-sm text-rose-700">
                                                    - {formatRupiah(item.amount)}
                                                </span>
                                            </td>

                                            {/* Petugas */}
                                            <td className="py-3 px-4 whitespace-nowrap">
                                                <span className="inline-flex items-center gap-1 text-[11px] text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md font-medium">
                                                    <User className="w-3 h-3 text-slate-400" />
                                                    <span>{item.user_name}</span>
                                                </span>
                                            </td>

                                            {/* Aksi */}
                                            <td className="py-3 px-4 text-center whitespace-nowrap">
                                                <div className="flex items-center justify-center gap-1">
                                                    {/* Lihat Detail */}
                                                    <button
                                                        type="button"
                                                        onClick={() => setDetailBiaya(item)}
                                                        className="p-1.5 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition cursor-pointer"
                                                        title="Lihat Rincian Biaya"
                                                    >
                                                        <Eye className="w-3.5 h-3.5" />
                                                    </button>

                                                    {/* Cetak Bukti BKK */}
                                                    <button
                                                        type="button"
                                                        onClick={() => handleOpenCetakBukti(item)}
                                                        className="inline-flex items-center gap-1 px-2.5 py-1 text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-rose-50 hover:border-rose-200 hover:text-rose-700 transition text-[11px] font-semibold cursor-pointer shadow-2xs"
                                                        title="Cetak Bukti Pengeluaran Kas (BKK)"
                                                    >
                                                        <Printer className="w-3 h-3 text-rose-600" />
                                                        <span>Cetak</span>
                                                    </button>

                                                    {/* Edit Biaya */}
                                                    <button
                                                        type="button"
                                                        onClick={() => handleOpenEditDrawer(item)}
                                                        className="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition cursor-pointer"
                                                        title="Edit Transaksi Biaya"
                                                    >
                                                        <Edit3 className="w-3.5 h-3.5" />
                                                    </button>

                                                    {/* Hapus Biaya */}
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDeleteBiaya(item)}
                                                        className="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition cursor-pointer"
                                                        title="Hapus Transaksi Biaya"
                                                    >
                                                        <Trash2 className="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {totalPages > 1 && (
                        <div className="px-5 py-3.5 border-t border-slate-200 bg-slate-50/50 flex items-center justify-between text-xs">
                            <span className="text-slate-500">
                                Menampilkan {(currentPage - 1) * itemsPerPage + 1} -{' '}
                                {Math.min(currentPage * itemsPerPage, filteredBiaya.length)} dari {filteredBiaya.length} data
                            </span>
                            <div className="flex items-center gap-1">
                                <button
                                    type="button"
                                    disabled={currentPage === 1}
                                    onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                                    className="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                                >
                                    <ChevronLeft className="w-4 h-4" />
                                </button>
                                <span className="px-3 py-1 font-semibold text-slate-700">
                                    {currentPage} / {totalPages}
                                </span>
                                <button
                                    type="button"
                                    disabled={currentPage === totalPages}
                                    onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                                    className="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                                >
                                    <ChevronRight className="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* 5. DRAWER CATAT / EDIT BIAYA OPERASIONAL */}
            <Drawer
                isOpen={isCreateDrawerOpen}
                item={isCreateDrawerOpen ? (editingBiaya || { isNew: true }) : null}
                onClose={() => {
                    setIsCreateDrawerOpen(false);
                    setEditingBiaya(null);
                }}
                title={editingBiaya ? 'Edit Biaya Operasional' : 'Catat Biaya Operasional Baru'}
                subtitle={
                    editingBiaya
                        ? `Perbarui informasi pengeluaran voucher ${editingBiaya.reference_number}`
                        : 'Dokumentasikan pengeluaran kas apotek untuk beban operasional dan beban lainnya'
                }
            >
                <form onSubmit={handleSubmitForm} className="space-y-4">
                    {/* Akun Kas & Bank Sumber Dana */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Sumber Dana (Akun Kas & Bank) <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={formData.account_id}
                            onChange={(e) => setFormData({ ...formData, account_id: e.target.value })}
                            className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition cursor-pointer"
                        >
                            {kasBankAccounts.map((acc) => (
                                <option key={acc.id} value={acc.id}>
                                    {acc.name} (Saldo: {formatRupiah(acc.balance)})
                                </option>
                            ))}
                        </select>
                        {formErrors.account_id && (
                            <p className="text-[11px] text-red-500 mt-1">{formErrors.account_id}</p>
                        )}
                        {selectedKasAccount && (
                            <p className="text-[11px] text-slate-500 mt-1">
                                Saldo tersedia saat ini: <strong className="text-slate-800">{formatRupiah(selectedKasAccount.balance)}</strong>
                            </p>
                        )}
                    </div>

                    {/* Akun Beban / Kategori */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Kategori Beban Operasional <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={formData.expense_account_id}
                            onChange={(e) => setFormData({ ...formData, expense_account_id: e.target.value })}
                            className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition cursor-pointer"
                        >
                            {expenseAccounts.map((acc) => (
                                <option key={acc.id} value={acc.id}>
                                    {acc.code} - {acc.name} ({acc.category})
                                </option>
                            ))}
                        </select>
                        {formErrors.expense_account_id && (
                            <p className="text-[11px] text-red-500 mt-1">{formErrors.expense_account_id}</p>
                        )}
                    </div>

                    {/* Tanggal Pengeluaran */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Tanggal Pengeluaran <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            value={formData.payment_date}
                            onChange={(e) => setFormData({ ...formData, payment_date: e.target.value })}
                            className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition"
                        />
                        {formErrors.payment_date && (
                            <p className="text-[11px] text-red-500 mt-1">{formErrors.payment_date}</p>
                        )}
                    </div>

                    {/* Nominal */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Nominal Pengeluaran (Rp) <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            min="1"
                            step="any"
                            placeholder="Contoh: 150000"
                            value={formData.amount}
                            onChange={(e) => setFormData({ ...formData, amount: e.target.value })}
                            className="w-full px-3 py-2 text-xs font-bold text-slate-800 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition"
                        />

                        {/* Quick Nominal Chips */}
                        <div className="flex items-center gap-1.5 flex-wrap mt-2">
                            <span className="text-[10px] text-slate-400">Pilihan cepat:</span>
                            {quickAmounts.map((q) => (
                                <button
                                    key={q}
                                    type="button"
                                    onClick={() => setFormData({ ...formData, amount: q })}
                                    className="px-2 py-0.5 text-[10px] font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-md transition cursor-pointer"
                                >
                                    +{formatNumberOnly(q)}
                                </button>
                            ))}
                        </div>

                        {formData.amount > 0 && (
                            <div className="mt-2 p-2 bg-rose-50/70 border border-rose-200/60 rounded-xl text-xs">
                                <span className="font-extrabold text-rose-700 block">
                                    {formatRupiah(formData.amount)}
                                </span>
                                <span className="text-[10px] text-slate-500 italic block mt-0.5">
                                    "{terbilang(formData.amount)}"
                                </span>
                            </div>
                        )}
                        {formErrors.amount && (
                            <p className="text-[11px] text-red-500 mt-1">{formErrors.amount}</p>
                        )}
                    </div>

                    {/* Penerima / Vendor */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Penerima Dana / Kepada
                        </label>
                        <input
                            type="text"
                            list="common-recipients"
                            placeholder="Contoh: PLN Cabang, PDAM, Toko ATK Sejahtera"
                            value={formData.recipient}
                            onChange={(e) => setFormData({ ...formData, recipient: e.target.value })}
                            className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition"
                        />
                        <datalist id="common-recipients">
                            <option value="PLN (Listrik)" />
                            <option value="PDAM (Air Bersih)" />
                            <option value="Telkom / Indihome (Internet)" />
                            <option value="Toko ATK & Perlengkapan" />
                            <option value="Gaji & Upah Karyawan" />
                            <option value="Uang Lembur Petugas" />
                            <option value="Pemilik Gedung (Sewa)" />
                            <option value="Jasa Kurir & Ekspedisi" />
                            <option value="Iuran Keamanan & Kebersihan" />
                            <option value="Konsumsi & Pantry Apotek" />
                        </datalist>
                    </div>

                    {/* No. Referensi / No. Bukti Nota */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Nomor Referensi / No. Nota / Kuitansi
                        </label>
                        <input
                            type="text"
                            placeholder="Opsional, otomatis dibuat jika dikosongkan (BY-...)"
                            value={formData.reference_number}
                            onChange={(e) => setFormData({ ...formData, reference_number: e.target.value })}
                            className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition font-mono"
                        />
                    </div>

                    {/* Catatan / Keterangan */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Keterangan / Keperluan Lengkap
                        </label>
                        <textarea
                            rows={3}
                            placeholder="Tuliskan peruntukan atau rincian pengeluaran secara lengkap..."
                            value={formData.notes}
                            onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                            className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition"
                        />
                    </div>

                    {/* Submit Actions */}
                    <div className="pt-4 border-t border-slate-200 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => {
                                setIsCreateDrawerOpen(false);
                                setEditingBiaya(null);
                            }}
                            className="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800 bg-white border border-slate-200 rounded-xl transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={isSubmitting}
                            className="px-5 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition shadow-xs flex items-center gap-1.5 disabled:opacity-50 cursor-pointer"
                        >
                            {isSubmitting ? (
                                <span>Menyimpan...</span>
                            ) : (
                                <>
                                    <CheckCircle2 className="w-4 h-4" />
                                    <span>{editingBiaya ? 'Perbarui Biaya' : 'Simpan Biaya'}</span>
                                </>
                            )}
                        </button>
                    </div>
                </form>
            </Drawer>

            {/* 6. MODAL RINCIAN / DETAIL BIAYA OPERASIONAL */}
            {detailBiaya && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-3xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                        {/* Header */}
                        <div className="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                            <div className="flex items-center gap-2.5">
                                <div className="p-2 bg-rose-100 text-rose-800 rounded-xl">
                                    <Receipt className="w-5 h-5" />
                                </div>
                                <div>
                                    <h3 className="text-sm font-bold text-slate-900">Rincian Voucher Pengeluaran</h3>
                                    <p className="text-[11px] text-slate-500 font-mono">
                                        {detailBiaya.reference_number}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setDetailBiaya(null)}
                                className="p-2 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-200 transition cursor-pointer"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Content */}
                        <div className="p-6 space-y-4 text-xs">
                            {/* Big Nominal Callout */}
                            <div className="p-4 bg-rose-50/70 border border-rose-200 rounded-2xl">
                                <span className="text-[10px] font-bold text-rose-950 uppercase tracking-wider block">
                                    Jumlah Pengeluaran Kas:
                                </span>
                                <div className="text-2xl font-black text-rose-700 mt-0.5">
                                    {formatRupiah(detailBiaya.amount)}
                                </div>
                                <div className="text-[11px] italic text-slate-600 mt-1 pt-1.5 border-t border-rose-200/60">
                                    "{terbilang(detailBiaya.amount)}"
                                </div>
                            </div>

                            {/* Details Grid */}
                            <div className="grid grid-cols-2 gap-3.5 bg-slate-50/70 border border-slate-200 rounded-2xl p-4">
                                <div>
                                    <span className="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">
                                        Tanggal Transaksi
                                    </span>
                                    <span className="font-semibold text-slate-800 mt-0.5 block">
                                        {detailBiaya.formatted_date || detailBiaya.payment_date}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">
                                        Petugas Pencatat
                                    </span>
                                    <span className="font-semibold text-slate-800 mt-0.5 block">
                                        {detailBiaya.user_name || '-'}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">
                                        Kategori Akun Beban
                                    </span>
                                    <span className="font-bold text-slate-900 mt-0.5 block">
                                        {detailBiaya.expense_account_name}
                                    </span>
                                    <span className="text-[10px] text-slate-400 font-mono">
                                        {detailBiaya.expense_account_code}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">
                                        Sumber Kas / Bank
                                    </span>
                                    <span className="font-bold text-slate-900 mt-0.5 block">
                                        {detailBiaya.account_name}
                                    </span>
                                    <span className="text-[10px] text-slate-400 font-mono">
                                        {detailBiaya.account_code}
                                    </span>
                                </div>
                                <div className="col-span-2">
                                    <span className="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">
                                        Dibayarkan Kepada
                                    </span>
                                    <span className="font-semibold text-slate-800 mt-0.5 block">
                                        {detailBiaya.recipient || '-'}
                                    </span>
                                </div>
                            </div>

                            {/* Keperluan / Notes */}
                            {detailBiaya.notes && (
                                <div>
                                    <span className="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-1">
                                        Keterangan / Keperluan:
                                    </span>
                                    <p className="bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-700 leading-relaxed font-medium">
                                        {detailBiaya.notes}
                                    </p>
                                </div>
                            )}

                            {/* Jurnal Akuntansi Preview */}
                            <div className="bg-slate-50 border border-slate-200 rounded-xl p-3">
                                <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-2">
                                    Entri Jurnal Akuntansi:
                                </span>
                                <div className="space-y-1 font-mono text-[11px]">
                                    <div className="flex justify-between text-slate-800">
                                        <span>(D) {detailBiaya.expense_account_name}</span>
                                        <span>{formatRupiah(detailBiaya.amount)}</span>
                                    </div>
                                    <div className="flex justify-between text-slate-600 pl-4">
                                        <span>(K) {detailBiaya.account_name}</span>
                                        <span>{formatRupiah(detailBiaya.amount)}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Actions Footer */}
                        <div className="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                            <button
                                type="button"
                                onClick={() => handleDeleteBiaya(detailBiaya)}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-red-600 hover:bg-red-50 rounded-xl text-xs font-semibold transition cursor-pointer"
                            >
                                <Trash2 className="w-3.5 h-3.5" />
                                <span>Hapus Biaya</span>
                            </button>

                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => handleOpenEditDrawer(detailBiaya)}
                                    className="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-xl text-xs font-semibold transition cursor-pointer"
                                >
                                    <Edit3 className="w-3.5 h-3.5" />
                                    <span>Edit</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        const item = detailBiaya;
                                        setDetailBiaya(null);
                                        handleOpenCetakBukti(item);
                                    }}
                                    className="inline-flex items-center gap-1.5 px-4 py-1.5 text-white bg-rose-600 hover:bg-rose-700 rounded-xl text-xs font-bold transition shadow-xs cursor-pointer"
                                >
                                    <Printer className="w-3.5 h-3.5" />
                                    <span>Cetak BKK</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* 7. MODAL CETAK BUKTI KAS KELUAR (BKK) */}
            <BuktiKasKeluarModal
                isOpen={isBuktiModalOpen}
                onClose={() => {
                    setIsBuktiModalOpen(false);
                    setSelectedBiayaForBukti(null);
                }}
                biaya={selectedBiayaForBukti}
                branchContext={branchContext}
            />
        </FinanceLayout>
    );
}
