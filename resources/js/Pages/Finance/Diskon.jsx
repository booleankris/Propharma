import React, { useState, useMemo } from 'react';
import { router, usePage } from '@inertiajs/react';
import FinanceLayout from './Layouts/FinanceLayout';
import Drawer from './Components/Drawer';
import { formatRupiah, formatNumberOnly, terbilang } from './Components/Utils';
import {
    Percent, Search, Plus, Calendar, ChevronLeft, ChevronRight,
    Trash2, ArrowUp, ArrowDown, Landmark, FileText,
    CheckCircle2, Wallet, Edit3, X, PieChart, Tag,
    HelpCircle, BadgeCheck
} from 'lucide-react';

export default function Diskon({
    diskonList = [],
    discountAccounts = [],
    kasBankAccounts = [],
    stats = {},
    summary = {},
    branchContext = {}
}) {
    const { errors: pageErrors } = usePage().props;

    // Filters and Search
    const [searchTerm, setSearchTerm] = useState('');
    const [filterDiscountAccount, setFilterDiscountAccount] = useState('');
    const [filterKasAccount, setFilterKasAccount] = useState('');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [datePreset, setDatePreset] = useState('all');
    const [sortOrder, setSortOrder] = useState('desc');
    const [currentPage, setCurrentPage] = useState(1);
    const itemsPerPage = 10;

    // Breakdown Widget Toggle
    const [showTopDiscounts, setShowTopDiscounts] = useState(true);

    // Modals & Drawer State
    const [isCreateDrawerOpen, setIsCreateDrawerOpen] = useState(false);
    const [editingDiskon, setEditingDiskon] = useState(null);
    const [detailDiskon, setDetailDiskon] = useState(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Form Data State
    const [formData, setFormData] = useState({
        account_id: kasBankAccounts[0]?.id || '',
        expense_account_id: discountAccounts[0]?.id || '',
        payment_date: new Date().toISOString().split('T')[0],
        amount: '',
        recipient: '',
        reference_number: '',
        notes: '',
    });
    const [formErrors, setFormErrors] = useState({});
// Handle date preset & filtering
    const handleDatePreset = (preset) => {
        setDatePreset(preset);
        setCurrentPage(1);
        const now = new Date();
        const fmt = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        if (preset === 'all') {
            setStartDate('');
            setEndDate('');
        } else if (preset === 'today') {
            setStartDate(fmt(now));
            setEndDate(fmt(now));
        } else if (preset === 'this_month') {
            setStartDate(fmt(new Date(now.getFullYear(), now.getMonth(), 1)));
            setEndDate(fmt(new Date(now.getFullYear(), now.getMonth() + 1, 0)));
        } else if (preset === 'last_month') {
            setStartDate(fmt(new Date(now.getFullYear(), now.getMonth() - 1, 1)));
            setEndDate(fmt(new Date(now.getFullYear(), now.getMonth(), 0)));
        }
    };

    const filteredDiskon = useMemo(() => {
        let result = diskonList.filter((item) => {
            const matchesSearch =
                searchTerm === '' ||
                item.reference_number?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.recipient?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.notes?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.discount_account_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.account_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.user_name?.toLowerCase().includes(searchTerm.toLowerCase());
            const matchesDiscount =
                filterDiscountAccount === '' || String(item.discount_account_id) === String(filterDiscountAccount);
            const matchesKas =
                filterKasAccount === '' || String(item.account_id) === String(filterKasAccount);
            const matchesStartDate = !startDate || (item.payment_date && item.payment_date >= startDate);
            const matchesEndDate = !endDate || (item.payment_date && item.payment_date <= endDate);
            return matchesSearch && matchesDiscount && matchesKas && matchesStartDate && matchesEndDate;
        });

        return result.sort((a, b) => {
            const dateA = a.payment_date ? new Date(a.payment_date).getTime() : 0;
            const dateB = b.payment_date ? new Date(b.payment_date).getTime() : 0;
            if (sortOrder === 'asc') {
                return dateA - dateB || a.id - b.id;
            }
            return dateB - dateA || b.id - a.id;
        });
    }, [diskonList, searchTerm, filterDiscountAccount, filterKasAccount, startDate, endDate, sortOrder]);

    const totalPages = Math.ceil(filteredDiskon.length / itemsPerPage) || 1;
    const paginatedDiskon = useMemo(() => {
        const start = (currentPage - 1) * itemsPerPage;
        return filteredDiskon.slice(start, start + itemsPerPage);
    }, [filteredDiskon, currentPage, itemsPerPage]);

    const totalFilteredAmount = useMemo(() => {
        return filteredDiskon.reduce((acc, curr) => acc + (Number(curr.amount) || 0), 0);
    }, [filteredDiskon]);
const handleOpenCreateDrawer = () => {
        setEditingDiskon(null);
        setDetailDiskon(null);
        setFormData({
            account_id: kasBankAccounts[0]?.id || '',
            expense_account_id: discountAccounts[0]?.id || '',
            payment_date: new Date().toISOString().split('T')[0],
            amount: '',
            recipient: '',
            reference_number: '',
            notes: '',
        });
        setFormErrors({});
        setIsCreateDrawerOpen(true);
    };

    const handleOpenEditDrawer = (item) => {
        setEditingDiskon(item);
        setFormData({
            account_id: item.account_id || kasBankAccounts[0]?.id || '',
            expense_account_id: item.discount_account_id || discountAccounts[0]?.id || '',
            payment_date: item.payment_date ? item.payment_date.split('T')[0] : new Date().toISOString().split('T')[0],
            amount: item.amount || '',
            recipient: item.recipient === '-' ? '' : (item.recipient || ''),
            reference_number: item.reference_number || '',
            notes: item.notes || '',
        });
        setFormErrors({});
        setIsCreateDrawerOpen(true);
        if (detailDiskon && detailDiskon.id === item.id) {
            setDetailDiskon(null);
        }
    };

    const handleSubmitForm = (e) => {
        e.preventDefault();
        setFormErrors({});

        const errors = {};
        if (!formData.account_id) errors.account_id = 'Pilih akun sumber kas/bank';
        if (!formData.expense_account_id) errors.expense_account_id = 'Pilih jenis diskon (akun Diskon Lain-lain)';
        if (!formData.payment_date) errors.payment_date = 'Tanggal wajib diisi';
        if (!formData.amount || Number(formData.amount) <= 0) errors.amount = 'Nominal harus lebih dari 0';

        if (Object.keys(errors).length > 0) {
            setFormErrors(errors);
            return;
        }

        setIsSubmitting(true);

        if (editingDiskon) {
            router.put(`/finance/diskon/${editingDiskon.id}`, formData, {
                onSuccess: () => {
                    setIsSubmitting(false);
                    setIsCreateDrawerOpen(false);
                    setEditingDiskon(null);
                },
                onError: (errs) => {
                    setIsSubmitting(false);
                    setFormErrors(errs);
                },
            });
        } else {
            router.post('/finance/diskon', formData, {
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

    const handleDeleteDiskon = (item) => {
        if (confirm(`Apakah Anda yakin ingin menghapus diskon off "${item.reference_number}" sebesar ${formatRupiah(item.amount)}?`)) {
            router.delete(`/finance/diskon/${item.id}`, {
                preserveScroll: true,
            });
            if (detailDiskon && detailDiskon.id === item.id) {
                setDetailDiskon(null);
            }
        }
    };

    const quickAmounts = [10000, 50000, 100000, 250000, 500000];
    const selectedKasAccount = kasBankAccounts.find((acc) => String(acc.id) === String(formData.account_id));

    return (
        <FinanceLayout title="Diskon Off" subtitle="Catat diskon tambahan (discount off) saat pembelian baru" stats={stats}>
            <div className="space-y-5">
{/* 1. RINGKASAN */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:shadow-xs transition group">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Diskon Hari Ini</span>
                            <div className="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 group-hover:scale-105 transition">
                                <Percent className="w-5 h-5" />
                            </div>
                        </div>
                        <p className="text-xl sm:text-2xl font-black text-slate-900 mt-2 tracking-tight">{formatRupiah(summary.totalToday || 0)}</p>
                        <p className="text-[11px] text-slate-500 mt-1 font-medium">Diskon off tercatat hari ini</p>
                    </div>

                    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:shadow-xs transition group">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Diskon Bulan Ini</span>
                            <div className="w-9 h-9 rounded-xl bg-violet-50 flex items-center justify-center text-violet-600 group-hover:scale-105 transition">
                                <Calendar className="w-5 h-5" />
                            </div>
                        </div>
                        <p className="text-xl sm:text-2xl font-black text-slate-900 mt-2 tracking-tight">{formatRupiah(summary.totalMonth || 0)}</p>
                        <p className="text-[11px] text-slate-500 mt-1 font-medium">Akumulasi diskon bulan berjalan</p>
                    </div>

                    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:shadow-xs transition group">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Keseluruhan</span>
                            <div className="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-700 group-hover:scale-105 transition">
                                <Wallet className="w-5 h-5" />
                            </div>
                        </div>
                        <p className="text-xl sm:text-2xl font-black text-slate-900 mt-2 tracking-tight">{formatRupiah(summary.totalAllTime || 0)}</p>
                        <p className="text-[11px] text-slate-500 mt-1 font-medium">Akumulasi seluruh diskon off</p>
                    </div>

                    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:shadow-xs transition group">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Transaksi</span>
                            <div className="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 group-hover:scale-105 transition">
                                <BadgeCheck className="w-5 h-5" />
                            </div>
                        </div>
                        <p className="text-xl sm:text-2xl font-black text-slate-900 mt-2 tracking-tight">{summary.countTotal || 0}</p>
                        <p className="text-[11px] text-slate-500 mt-1 font-medium">Jumlah pencatatan diskon off</p>
                    </div>
                </div>
{/* 2. TOP DISKON BREAKDOWN */}
                {summary.topDiscounts && summary.topDiscounts.length > 0 && (
                    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                            <div className="flex items-center gap-2">
                                <div className="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                    <PieChart className="w-4 h-4" />
                                </div>
                                <div>
                                    <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider">Jenis Diskon Terbesar</h3>
                                    <p className="text-[11px] text-slate-400">Akun Diskon Lain-lain dengan nilai diskon off tertinggi</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowTopDiscounts(!showTopDiscounts)}
                                className="text-xs text-slate-500 hover:text-slate-800 flex items-center gap-1 font-medium cursor-pointer"
                            >
                                <span>{showTopDiscounts ? 'Sembunyikan' : 'Tampilkan'}</span>
                                {showTopDiscounts ? <ArrowUp className="w-3.5 h-3.5" /> : <ArrowDown className="w-3.5 h-3.5" />}
                            </button>
                        </div>

                        {showTopDiscounts && (
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3.5 pt-1">
                                {summary.topDiscounts.map((exp, idx) => {
                                    const percentage = summary.totalAllTime > 0
                                        ? Math.round((exp.total_amount / summary.totalAllTime) * 100)
                                        : 0;
                                    return (
                                        <div key={idx} className="p-3.5 rounded-xl bg-slate-50/70 border border-slate-200/80 hover:bg-slate-50 hover:border-indigo-200 transition">
                                            <div className="flex items-center justify-between gap-1 text-[11px] text-slate-400 font-mono">
                                                <span>{exp.account_code}</span>
                                                <span className="font-bold text-slate-700 bg-white px-1.5 py-0.5 rounded border border-slate-200 text-[10px]">{percentage}%</span>
                                            </div>
                                            <h4 className="font-bold text-xs text-slate-800 mt-1 truncate" title={exp.account_name}>{exp.account_name}</h4>
                                            <p className="font-extrabold text-sm text-indigo-700 mt-1">{formatRupiah(exp.total_amount)}</p>
                                            <div className="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden mt-2">
                                                <div className="bg-indigo-500 h-full rounded-full transition-all duration-500" style={{ width: `${Math.min(100, Math.max(5, percentage))}%` }} />
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
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                        <div className="flex items-center gap-1.5 flex-wrap">
                            {[
                                { key: 'all', label: 'Semua' },
                                { key: 'today', label: 'Hari Ini' },
                                { key: 'this_month', label: 'Bulan Ini' },
                                { key: 'last_month', label: 'Bulan Lalu' },
                            ].map((chip) => (
                                <button
                                    key={chip.key}
                                    type="button"
                                    onClick={() => handleDatePreset(chip.key)}
                                    className={`px-3 py-1.5 rounded-xl text-[11px] font-semibold transition cursor-pointer ${datePreset === chip.key
                                        ? 'bg-indigo-600 text-white shadow-xs'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                        }`}
                                >
                                    {chip.label}
                                </button>
                            ))}
                        </div>

                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => setSortOrder(sortOrder === 'desc' ? 'asc' : 'desc')}
                                className="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition cursor-pointer"
                                title={sortOrder === 'desc' ? 'Urutkan terlama dulu' : 'Urutkan terbaru dulu'}
                            >
                                {sortOrder === 'desc' ? <ArrowDown className="w-4 h-4" /> : <ArrowUp className="w-4 h-4" />}
                                <span>{sortOrder === 'desc' ? 'Terbaru' : 'Terlama'}</span>
                            </button>

                            <button
                                type="button"
                                onClick={handleOpenCreateDrawer}
                                className="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer"
                            >
                                <Plus className="w-4 h-4" />
                                <span>Catat Diskon Off</span>
                            </button>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 flex-wrap">
                        <div className="relative min-w-[220px] flex-1 max-w-sm">
                            <Search className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Cari no. ref, supplier, jenis diskon, petugas..."
                                value={searchTerm}
                                onChange={(e) => { setSearchTerm(e.target.value); setCurrentPage(1); }}
                                className="w-full pl-9 pr-4 py-2 text-xs bg-slate-50 hover:bg-slate-100/80 focus:bg-white border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                            />
                        </div>

                        <select
                            value={filterDiscountAccount}
                            onChange={(e) => { setFilterDiscountAccount(e.target.value); setCurrentPage(1); }}
                            className="px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl text-slate-700 font-medium hover:bg-slate-50 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition cursor-pointer max-w-[220px]"
                        >
                            <option value="">Semua Jenis Diskon</option>
                            {discountAccounts.map((acc) => (
                                <option key={acc.id} value={acc.id}>
                                    {acc.code} - {acc.name}
                                </option>
                            ))}
                        </select>

                        <select
                            value={filterKasAccount}
                            onChange={(e) => { setFilterKasAccount(e.target.value); setCurrentPage(1); }}
                            className="px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl text-slate-700 font-medium hover:bg-slate-50 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition cursor-pointer max-w-[190px]"
                        >
                            <option value="">Semua Sumber Kas/Bank</option>
                            {kasBankAccounts.map((acc) => (
                                <option key={acc.id} value={acc.id}>{acc.name}</option>
                            ))}
                        </select>

                        <div className="flex items-center gap-1.5 text-xs text-slate-500">
                            <input
                                type="date"
                                value={startDate}
                                onChange={(e) => { setStartDate(e.target.value); setDatePreset('custom'); setCurrentPage(1); }}
                                className="px-2.5 py-1.5 text-xs bg-white border border-slate-200 rounded-xl text-slate-700 hover:bg-slate-50 focus:outline-hidden transition"
                                title="Tanggal mulai"
                            />
                            <span className="text-slate-400">s/d</span>
                            <input
                                type="date"
                                value={endDate}
                                onChange={(e) => { setEndDate(e.target.value); setDatePreset('custom'); setCurrentPage(1); }}
                                className="px-2.5 py-1.5 text-xs bg-white border border-slate-200 rounded-xl text-slate-700 hover:bg-slate-50 focus:outline-hidden transition"
                                title="Tanggal akhir"
                            />
                            {(startDate || endDate) && (
                                <button
                                    type="button"
                                    onClick={() => handleDatePreset('all')}
                                    className="text-[11px] text-indigo-600 hover:underline font-semibold cursor-pointer"
                                >
                                    Reset
                                </button>
                            )}
                        </div>
                    </div>

                    {(searchTerm || filterDiscountAccount || filterKasAccount || startDate || endDate) && (
                        <div className="pt-2 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                            <span className="flex items-center gap-1.5">
                                <span className="font-semibold text-slate-700">{filteredDiskon.length}</span> data ditemukan
                                &bull; Total Filtered: <strong className="text-indigo-700 font-bold">{formatRupiah(totalFilteredAmount)}</strong>
                            </span>
                            <button
                                type="button"
                                onClick={() => {
                                    setSearchTerm('');
                                    setFilterDiscountAccount('');
                                    setFilterKasAccount('');
                                    setStartDate('');
                                    setEndDate('');
                                    setDatePreset('all');
                                    setCurrentPage(1);
                                }}
                                className="text-indigo-600 hover:underline font-semibold text-[11px] cursor-pointer"
                            >
                                Reset Semua Filter
                            </button>
                        </div>
                    )}
                </div>

                {/* 4. TABEL DATA DISKON OFF */}
<div className="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-2xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50/80 border-b border-slate-200/80 text-slate-500 font-semibold uppercase tracking-wider text-[11px]">
                                <tr>
                                    <th className="py-3.5 px-4">No. Bukti / Ref</th>
                                    <th className="py-3.5 px-4">Tanggal</th>
                                    <th className="py-3.5 px-4">Jenis Diskon</th>
                                    <th className="py-3.5 px-4">Sumber Kas/Bank</th>
                                    <th className="py-3.5 px-4">Supplier / Keterangan</th>
                                    <th className="py-3.5 px-4 text-right">Nominal Diskon</th>
                                    <th className="py-3.5 px-4">Petugas</th>
                                    <th className="py-3.5 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-slate-700">
                                {paginatedDiskon.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="py-12 text-center text-slate-400">
                                            <Percent className="w-10 h-10 mx-auto text-slate-300 mb-2 stroke-1" />
                                            <p className="font-semibold text-slate-600">Tidak ada data diskon off ditemukan</p>
                                            <p className="text-[11px] text-slate-400 mt-0.5">Coba sesuaikan kata kunci pencarian atau catat diskon off baru</p>
                                        </td>
                                    </tr>
                                ) : (
                                    paginatedDiskon.map((item) => (
                                        <tr key={item.id} className="hover:bg-indigo-50/40 transition">
                                            <td className="py-3 px-4 font-mono font-semibold text-indigo-700">{item.reference_number}</td>
                                            <td className="py-3 px-4 whitespace-nowrap">
                                                <div className="font-semibold text-slate-800">{item.formatted_date}</div>
                                                {item.pharmacy_name && <div className="text-[10px] text-slate-400">{item.pharmacy_name}</div>}
                                            </td>
                                            <td className="py-3 px-4">
                                                <span className="inline-flex items-center gap-1.5">
                                                    <Tag className="w-3.5 h-3.5 text-indigo-500 shrink-0" />
                                                    <span className="text-slate-700">{item.discount_account_name}</span>
                                                </span>
                                                {item.discount_account_code && (
                                                    <span className="ml-1.5 text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-slate-500">{item.discount_account_code}</span>
                                                )}
                                            </td>
                                            <td className="py-3 px-4">
                                                <span className="inline-flex items-center gap-1.5 text-slate-600">
                                                    <Landmark className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                                    {item.account_name}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 max-w-[240px]">
                                                <div className="truncate font-medium text-slate-700" title={item.recipient ? `${item.recipient} — ${item.notes}` : item.notes}>
                                                    {item.recipient || '-'}
                                                </div>
                                                {item.notes && <div className="text-[10px] text-slate-400 truncate">{item.notes}</div>}
                                            </td>
                                            <td className="py-3 px-4 text-right">
                                                <span className="inline-flex items-center -mt-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-600">{formatRupiah(item.amount)}</span>
                                            </td>
<td className="py-3 px-4 whitespace-nowrap text-slate-500">{item.user_name}</td>
                                            <td className="py-3 px-4">
                                                <div className="flex items-center justify-center gap-1">
                                                    <button
                                                        type="button"
                                                        onClick={() => setDetailDiskon(detailDiskon && detailDiskon.id === item.id ? null : item)}
                                                        className="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-blue-50 transition cursor-pointer"
                                                        title="Lihat detail"
                                                    >
                                                        <FileText className="w-4 h-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => handleOpenEditDrawer(item)}
                                                        className="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 transition cursor-pointer"
                                                        title="Edit"
                                                    >
                                                        <Edit3 className="w-4 h-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDeleteDiskon(item)}
                                                        className="p-1.5 rounded-lg text-slate-500 hover:text-red-600 hover:bg-red-50 transition cursor-pointer"
                                                        title="Hapus"
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {paginatedDiskon.length > 0 && (
                        <div className="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-xs text-slate-500">
                            <span>
                                Menampilkan <strong className="text-slate-700">{(currentPage - 1) * itemsPerPage + 1}</strong>–<strong className="text-slate-700">{Math.min(currentPage * itemsPerPage, filteredDiskon.length)}</strong> dari <strong className="text-slate-700">{filteredDiskon.length}</strong> data
                            </span>
                            <div className="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    disabled={currentPage <= 1}
                                    onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                                    className="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                                >
                                    <ChevronLeft className="w-4 h-4" />
                                </button>
                                <span className="px-2 py-1 rounded-lg bg-slate-100 font-bold text-slate-700">{currentPage} / {totalPages}</span>
                                <button
                                    type="button"
                                    disabled={currentPage >= totalPages}
                                    onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                                    className="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                                >
                                    <ChevronRight className="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    )}
                </div>
{/* 5. DRAWER CATAT / EDIT DISKON OFF */}
            <Drawer
                isOpen={isCreateDrawerOpen}
                item={isCreateDrawerOpen ? (editingDiskon || { isNew: true }) : null}
                onClose={() => {
                    setIsCreateDrawerOpen(false);
                    setEditingDiskon(null);
                }}
                title={editingDiskon ? 'Edit Diskon Off' : 'Catat Diskon Off Baru'}
                subtitle={editingDiskon
                    ? `Perbarui informasi diskon off ${editingDiskon.reference_number}`
                    : 'Catat diskon tambahan yang diterima saat pembelian baru (tidak mengurangi saldo kas/bank)'}
            >
                <form onSubmit={handleSubmitForm} className="space-y-4">
                    {/* Sumber Kas & Bank (Info) */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Sumber Kas & Bank <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={formData.account_id}
                            onChange={(e) => setFormData({ ...formData, account_id: e.target.value })}
                            className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition cursor-pointer"
                        >
                            {kasBankAccounts.map((acc) => (
                                <option key={acc.id} value={acc.id}>
                                    {acc.name} (Saldo: {formatRupiah(acc.balance)})
                                </option>
                            ))}
                        </select>
                        {selectedKasAccount && (
                            <p className="text-[11px] text-indigo-600 mt-1 flex items-center gap-1">
                                <HelpCircle className="w-3 h-3" />
                                Info saja — diskon off tidak mengurangi saldo {selectedKasAccount.name}
                            </p>
                        )}
                        {formErrors.account_id && (
                            <p className="text-[11px] text-red-500 mt-1">{formErrors.account_id}</p>
                        )}
                    </div>

                    {/* Jenis Diskon (akun Diskon Lain-lain) */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Jenis Diskon (Akun Diskon Lain-lain) <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={formData.expense_account_id}
                            onChange={(e) => setFormData({ ...formData, expense_account_id: e.target.value })}
                            className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition cursor-pointer"
                        >
                            {discountAccounts.map((acc) => (
                                <option key={acc.id} value={acc.id}>
                                    {acc.code} - {acc.name} ({acc.category})
                                </option>
                            ))}
                        </select>
                        {formErrors.expense_account_id && (
                            <p className="text-[11px] text-red-500 mt-1">{formErrors.expense_account_id}</p>
                        )}
                    </div>

                    {/* Tanggal Diskon */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Tanggal <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            value={formData.payment_date}
                            onChange={(e) => setFormData({ ...formData, payment_date: e.target.value })}
                            className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                        />
                        {formErrors.payment_date && (
                            <p className="text-[11px] text-red-500 mt-1">{formErrors.payment_date}</p>
                        )}
                    </div>
{/* Nominal Diskon */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Nominal Diskon (Rp) <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            min="1"
                            step="any"
                            placeholder="Contoh: 150000"
                            value={formData.amount}
                            onChange={(e) => setFormData({ ...formData, amount: e.target.value })}
                            className="w-full px-3 py-2 text-xs font-bold text-slate-800 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                        />

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
                            <div className="mt-2 p-2 bg-indigo-50/70 border border-indigo-200/60 rounded-xl text-xs">
                                <span className="font-extrabold text-indigo-700 block">
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

                    {/* Penerima / Supplier */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Penerima / Supplier
                        </label>
                        <input
                            type="text"
                            placeholder="Contoh: PBF Anugrah, PT. Kimia Farma..."
                            value={formData.recipient}
                            onChange={(e) => setFormData({ ...formData, recipient: e.target.value })}
                            className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                        />
                    </div>

                    {/* No. Referensi */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Nomor Referensi / No. Nota / Kuitansi
                        </label>
                        <input
                            type="text"
                            placeholder="Opsional, otomatis dibuat jika dikosongkan (DC-...)"
                            value={formData.reference_number}
                            onChange={(e) => setFormData({ ...formData, reference_number: e.target.value })}
                            className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition font-mono"
                        />
                    </div>

                    {/* Catatan */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Keterangan / Keperluan Lengkap
                        </label>
                        <textarea
                            rows={3}
                            placeholder="Tuliskan keterangan diskon off yang diterima..."
                            value={formData.notes}
                            onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                            className="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                        />
                    </div>

                    {/* Submit Actions */}
                    <div className="pt-4 border-t border-slate-200 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => {
                                setIsCreateDrawerOpen(false);
                                setEditingDiskon(null);
                            }}
                            className="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={isSubmitting}
                            className="inline-flex items-center gap-1.5 px-5 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer"
                        >
                            {isSubmitting ? (
                                <span className="flex items-center gap-1.5">Menyimpan...</span>
                            ) : (
                                <span className="flex items-center gap-1.5">
                                    <CheckCircle2 className="w-4 h-4" />
                                    {editingDiskon ? 'Simpan Perubahan' : 'Catat Diskon Off'}
                                </span>
                            )}
                        </button>
                    </div>
                </form>
            </Drawer>
{/* 6. MODAL DETAIL DISKON */}
            {detailDiskon && (
                <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4" onClick={() => setDetailDiskon(null)}>
                    <div className="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden" onClick={(e) => e.stopPropagation()}>
                        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-indigo-50/50">
                            <div className="flex items-center gap-2">
                                <div className="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center">
                                    <Percent className="w-4 h-4" />
                                </div>
                                <div>
                                    <h3 className="font-bold text-sm text-slate-900">Detail Diskon Off</h3>
                                    <p className="text-[11px] text-slate-500">{detailDiskon.reference_number}</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setDetailDiskon(null)}
                                className="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition cursor-pointer"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <div className="p-5 space-y-3 text-xs">
                            <div className="flex justify-between gap-3">
                                <span className="text-slate-500">Tanggal</span>
                                <span className="font-semibold text-slate-800">{detailDiskon.formatted_date}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-slate-500">Jenis Diskon</span>
                                <span className="font-semibold text-slate-800 text-right">{detailDiskon.discount_account_name}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-slate-500">Sumber Kas/Bank</span>
                                <span className="font-semibold text-slate-800 text-right">{detailDiskon.account_name}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-slate-500">Penerima / Supplier</span>
                                <span className="font-semibold text-slate-800 text-right">{detailDiskon.recipient || '-'}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-slate-500">Petugas</span>
                                <span className="font-semibold text-slate-800 text-right">{detailDiskon.user_name}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-slate-500">Nominal Diskon</span>
                                <span className="font-extrabold text-indigo-700 text-right">{formatRupiah(detailDiskon.amount)}</span>
                            </div>
                            {detailDiskon.notes && (
                                <div className="bg-slate-50 border border-slate-200 rounded-xl p-3">
                                    <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Keterangan:</span>
                                    <p className="text-slate-700">{detailDiskon.notes}</p>
                                </div>
                            )}
                        </div>
<div className="px-5 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                            <button
                                type="button"
                                onClick={() => handleDeleteDiskon(detailDiskon)}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-red-600 hover:bg-red-50 rounded-xl text-xs font-semibold transition cursor-pointer"
                            >
                                <Trash2 className="w-3.5 h-3.5" />
                                <span>Hapus</span>
                            </button>
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => handleOpenEditDrawer(detailDiskon)}
                                    className="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-xl text-xs font-semibold transition cursor-pointer"
                                >
                                    <Edit3 className="w-3.5 h-3.5" />
                                    <span>Edit</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setDetailDiskon(null)}
                                    className="inline-flex items-center gap-1.5 px-4 py-1.5 text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl text-xs font-bold transition shadow-xs cursor-pointer"
                                >
                                    <CheckCircle2 className="w-3.5 h-3.5" />
                                    <span>Tutup</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
            </div>
        </FinanceLayout>
    );
}
