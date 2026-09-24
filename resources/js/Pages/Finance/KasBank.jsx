import React, { useState, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import FinanceLayout from './Layouts/FinanceLayout';
import { formatRupiah, formatNumberOnly } from './Components/Utils';
import {
    Landmark,
    ArrowDownLeft,
    ArrowUpRight,
    TrendingUp,
    TrendingDown,
    BarChart3,
    PieChart,
    Search,
    X,
    Receipt,
    Layers,
    Check,
    Edit3,
    Trash2,
    Plus,
    HelpCircle,
} from 'lucide-react';

export default function KasBank({
    accounts = [],
    categories = [],
    mutasiKasBank = [],
    stats = {},
}) {
    // State Filter & Search Mutasi
    const [selectedKasBankFilter, setSelectedKasBankFilter] = useState('all'); // 'all' | account_id
    const [mutasiTypeFilter, setMutasiTypeFilter] = useState('all'); // 'all' | 'IN' | 'OUT'
    const [mutasiSearchQuery, setMutasiSearchQuery] = useState('');
    const [mutasiCurrentPage, setMutasiCurrentPage] = useState(1);
    const mutasiItemsPerPage = 15;

    // State Account Modal
    const [isAccountModalOpen, setIsAccountModalOpen] = useState(false);
    const [isSubmittingAccount, setIsSubmittingAccount] = useState(false);
    const [editingAccount, setEditingAccount] = useState(null);
    const [deletingAccount, setDeletingAccount] = useState(null);
    const [isDeletingAccount, setIsDeletingAccount] = useState(false);
    const [isCustomCategory, setIsCustomCategory] = useState(false);
    const [customCategoryInput, setCustomCategoryInput] = useState('');

    const [accountForm, setAccountForm] = useState({
        name: '',
        name_en: '',
        code: '',
        category: 'Kas & Bank',
        account_number: '',
    });

    const prefixMap = {
        'Kas & Bank': '1-100',
        'Piutang Usaha': '1-102',
        'Persediaan': '1-104',
        'Aktiva Lancar Lainnya': '1-108',
        'Aktiva Tetap': '1-120',
        'Hutang Usaha': '2-201',
        'Kewajiban Lancar Lainnya': '2-202',
        'Kewajiban Jangka Panjang': '2-205',
        'Ekuitas / Modal': '3-300',
        'Pendapatan': '4-400',
        'Harga Pokok Penjualan': '5-500',
        'Beban Operasional': '6-600',
        'Beban Lainnya': '8-800',
    };

    const getNextCodeForCategory = (cat) => {
        const prefix = prefixMap[cat] || '9-900';
        const existing = accounts
            .map((a) => a.code)
            .filter((c) => c && c.startsWith(prefix));

        if (!existing.length) {
            return `${prefix}01`;
        }

        let maxNum = 0;
        existing.forEach((c) => {
            const numPart = c.slice(prefix.length);
            const n = parseInt(numPart, 10);
            if (!isNaN(n) && n > maxNum) {
                maxNum = n;
            }
        });

        const next = maxNum + 1;
        return `${prefix}${String(next).padStart(2, '0')}`;
    };

    const categoryList = useMemo(() => {
        const defaults = [
            'Kas & Bank',
            'Piutang Usaha',
            'Persediaan',
            'Aktiva Lancar Lainnya',
            'Aktiva Tetap',
            'Hutang Usaha',
            'Kewajiban Lancar Lainnya',
            'Kewajiban Jangka Panjang',
            'Ekuitas / Modal',
            'Pendapatan',
            'Harga Pokok Penjualan',
            'Beban Operasional',
            'Beban Lainnya',
        ];
        return Array.from(new Set([...defaults, ...(categories || []), ...accounts.map((a) => a.category).filter(Boolean)]));
    }, [categories, accounts]);

    const openAccountModal = (initialCat = 'Kas & Bank') => {
        setEditingAccount(null);
        setIsCustomCategory(false);
        setCustomCategoryInput('');
        const nextCode = getNextCodeForCategory(initialCat);
        setAccountForm({
            name: '',
            name_en: '',
            code: nextCode,
            category: initialCat,
            account_number: '',
        });
        setIsAccountModalOpen(true);
    };

    const openEditAccountModal = (acc) => {
        setEditingAccount(acc);
        setIsCustomCategory(false);
        setCustomCategoryInput('');
        setAccountForm({
            name: acc.name || '',
            name_en: acc.name_en || '',
            code: acc.code || '',
            category: acc.category || 'Kas & Bank',
            account_number: acc.account_number || '',
        });
        setIsAccountModalOpen(true);
    };

    const handleDeleteAccount = () => {
        if (!deletingAccount) return;
        setIsDeletingAccount(true);
        router.delete(`/finance/accounts/${deletingAccount.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeletingAccount(null);
                setIsDeletingAccount(false);
            },
            onError: (errors) => {
                setIsDeletingAccount(false);
                alert(Object.values(errors)[0] || 'Gagal menghapus akun.');
            }
        });
    };

    const handleCategoryChange = (cat) => {
        if (cat === '__NEW__') {
            setIsCustomCategory(true);
            setCustomCategoryInput('');
            setAccountForm((prev) => ({
                ...prev,
                category: '',
                code: getNextCodeForCategory('Kategori Baru'),
            }));
        } else {
            setIsCustomCategory(false);
            const nextCode = getNextCodeForCategory(cat);
            setAccountForm((prev) => ({
                ...prev,
                category: cat,
                code: nextCode,
            }));
        }
    };

    const handleAccountSubmit = (e) => {
        e.preventDefault();
        const finalCategory = isCustomCategory ? customCategoryInput.trim() : accountForm.category;
        if (!accountForm.name || !finalCategory) {
            alert('Nama (ID) dan Kategori akun wajib diisi.');
            return;
        }

        setIsSubmittingAccount(true);
        const url = editingAccount ? `/finance/accounts/${editingAccount.id}` : '/finance/accounts';
        const method = editingAccount ? 'put' : 'post';

        router[method](url, {
            ...accountForm,
            category: finalCategory,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsAccountModalOpen(false);
                setIsSubmittingAccount(false);
                setEditingAccount(null);
                setIsCustomCategory(false);
                setCustomCategoryInput('');
                setAccountForm({
                    name: '',
                    name_en: '',
                    code: '',
                    category: 'Kas & Bank',
                    account_number: '',
                });
            },
            onError: (errors) => {
                setIsSubmittingAccount(false);
                alert(Object.values(errors)[0] || 'Gagal memproses data akun.');
            }
        });
    };

    // Filter Hanya Akun Berkategori 'Kas & Bank'
    const kasBankAccounts = useMemo(() => {
        return accounts.filter((acc) => acc.category === 'Kas & Bank' && acc.is_active);
    }, [accounts]);

    // Ringkasan Arus Kas & Saldo
    const kasBankSummary = useMemo(() => {
        let totalSaldo = 0;
        let totalMasuk = 0;
        let totalKeluar = 0;

        const accountStats = {};
        kasBankAccounts.forEach((acc) => {
            totalSaldo += Number(acc.balance || 0);
            accountStats[acc.id] = {
                ...acc,
                inflow: 0,
                outflow: 0,
                net: 0,
                txCount: 0,
            };
        });

        (mutasiKasBank || []).forEach((m) => {
            const amt = Number(m.amount || 0);
            if (m.direction === 'IN') {
                totalMasuk += amt;
                if (accountStats[m.account_id]) {
                    accountStats[m.account_id].inflow += amt;
                    accountStats[m.account_id].txCount += 1;
                }
            } else {
                totalKeluar += amt;
                if (accountStats[m.account_id]) {
                    accountStats[m.account_id].outflow += amt;
                    accountStats[m.account_id].txCount += 1;
                }
            }
        });

        const statsList = Object.values(accountStats);
        statsList.forEach((acc) => {
            acc.net = acc.inflow - acc.outflow;
        });

        const totalFlowVolume = totalMasuk + totalKeluar;
        statsList.forEach((acc) => {
            const accVolume = acc.inflow + acc.outflow;
            acc.sharePercent = totalFlowVolume > 0 ? Math.round((accVolume / totalFlowVolume) * 100) : 0;
        });

        return {
            totalSaldo,
            totalMasuk,
            totalKeluar,
            netCashflow: totalMasuk - totalKeluar,
            totalFlowVolume,
            accountStats: statsList,
        };
    }, [kasBankAccounts, mutasiKasBank]);

    // Filter Mutasi Transaksi Kas & Bank
    const filteredMutasiKasBank = useMemo(() => {
        return (mutasiKasBank || []).filter((item) => {
            if (selectedKasBankFilter !== 'all' && String(item.account_id) !== String(selectedKasBankFilter)) {
                return false;
            }
            if (mutasiTypeFilter !== 'all' && item.direction !== mutasiTypeFilter) {
                return false;
            }
            if (mutasiSearchQuery.trim()) {
                const q = mutasiSearchQuery.toLowerCase();
                const docNo = String(item.document_no || '').toLowerCase();
                const party = String(item.party || '').toLowerCase();
                const desc = String(item.description || '').toLowerCase();
                const accName = String(item.account_name || '').toLowerCase();
                const catType = String(item.category_type || '').toLowerCase();
                if (
                    !docNo.includes(q) &&
                    !party.includes(q) &&
                    !desc.includes(q) &&
                    !accName.includes(q) &&
                    !catType.includes(q)
                ) {
                    return false;
                }
            }
            return true;
        });
    }, [mutasiKasBank, selectedKasBankFilter, mutasiTypeFilter, mutasiSearchQuery]);

    // Pagination Mutasi
    const totalMutasiPages = Math.ceil(filteredMutasiKasBank.length / mutasiItemsPerPage) || 1;
    const paginatedMutasi = useMemo(() => {
        const start = (mutasiCurrentPage - 1) * mutasiItemsPerPage;
        return filteredMutasiKasBank.slice(start, start + mutasiItemsPerPage);
    }, [filteredMutasiKasBank, mutasiCurrentPage, mutasiItemsPerPage]);

    return (
        <FinanceLayout
            title="Kas & Bank"
            subtitle="Kelola rekening kas & bank, mutasi buku kas, dan daftar bagan akun (Chart of Accounts)"
            stats={stats}
        >
            <Head title="Kas & Bank - Keuangan Apotek" />

            <div className="space-y-6">
                {/* Header Action Toolbar */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                    <div>
                        <h2 className="text-base font-bold text-slate-900">Rekening Kas, Bank & Bagan Akun</h2>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Total {kasBankAccounts.length} rekening aktif terdaftar dalam sistem
                        </p>
                    </div>

                    <div className="flex items-center gap-2.5">
                        <button
                            onClick={() => openAccountModal('Kas & Bank')}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm"
                        >
                            <Plus className="w-4 h-4" />
                            <span>Tambah Akun Kas/Bank</span>
                        </button>
                    </div>
                </div>

                {/* 1. KARTU METRIK KPI ARUS KAS & SALDO */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Total Saldo */}
                    <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs relative overflow-hidden group hover:border-blue-300 transition">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">Total Saldo Kas & Bank</span>
                            <div className="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <Landmark className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className={`text-xl font-bold tracking-tight ${Number(kasBankSummary.totalSaldo || 0) < 0 ? 'text-rose-600' : 'text-slate-900'}`}>
                                {formatRupiah(kasBankSummary.totalSaldo)}
                            </div>
                            <div className="text-[11px] text-slate-400 mt-1 flex items-center gap-1">
                                <span>Dari <strong>{kasBankAccounts.length}</strong> akun kas & rekening</span>
                            </div>
                        </div>
                    </div>

                    {/* Total Kas Masuk (Debit) */}
                    <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs relative overflow-hidden group hover:border-emerald-300 transition">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">Total Kas Masuk (Debit)</span>
                            <div className="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <ArrowDownLeft className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className="text-xl font-bold text-emerald-600 tracking-tight">
                                + {formatRupiah(kasBankSummary.totalMasuk)}
                            </div>
                            <div className="text-[11px] text-slate-400 mt-1">
                                Penerimaan piutang & penjualan
                            </div>
                        </div>
                    </div>

                    {/* Total Kas Keluar (Kredit) */}
                    <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs relative overflow-hidden group hover:border-rose-300 transition">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">Total Kas Keluar (Kredit)</span>
                            <div className="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                                <ArrowUpRight className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className="text-xl font-bold text-rose-600 tracking-tight">
                                - {formatRupiah(kasBankSummary.totalKeluar)}
                            </div>
                            <div className="text-[11px] text-slate-400 mt-1">
                                Pembelian cash & bayar hutang
                            </div>
                        </div>
                    </div>

                    {/* Arus Kas Bersih (Net Cashflow) */}
                    <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs relative overflow-hidden group hover:border-indigo-300 transition">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">Arus Kas Bersih (Net Flow)</span>
                            <div className={`w-9 h-9 rounded-xl flex items-center justify-center ${kasBankSummary.netCashflow >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'}`}>
                                {kasBankSummary.netCashflow >= 0 ? <TrendingUp className="w-4 h-4" /> : <TrendingDown className="w-4 h-4" />}
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className={`text-xl font-bold tracking-tight ${kasBankSummary.netCashflow >= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>
                                {kasBankSummary.netCashflow >= 0 ? '+ ' : ''}{formatRupiah(kasBankSummary.netCashflow)}
                            </div>
                            <div className="text-[11px] text-slate-400 mt-1">
                                Total {(mutasiKasBank || []).length} transaksi mutasi
                            </div>
                        </div>
                    </div>
                </div>

                {/* 2. VISUALISASI CHART & GRAPH AKUN KAS & BANK */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    {/* Panel Kiri (8 cols): Distribusi Aliran Kas & Kartu Rekening Interaktif */}
                    <div className="lg:col-span-8 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-5">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-4">
                            <div>
                                <h3 className="font-bold text-slate-900 text-sm flex items-center gap-2">
                                    <BarChart3 className="w-4 h-4 text-blue-600" />
                                    Visualisasi Aliran Dana per Rekening Kas & Bank
                                </h3>
                                <p className="text-xs text-slate-400 mt-0.5">
                                    Klik pada salah satu akun untuk menyaring rincian mutasi transaksi di bawah.
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={() => {
                                    setSelectedKasBankFilter('all');
                                    setMutasiCurrentPage(1);
                                }}
                                className={`px-3 py-1.5 text-xs font-semibold rounded-xl border transition ${selectedKasBankFilter === 'all'
                                    ? 'bg-blue-600 text-white border-blue-600 shadow-xs'
                                    : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'
                                    }`}
                            >
                                Tampilkan Semua ({kasBankAccounts.length})
                            </button>
                        </div>

                        {/* Grid Kartu Akun Interaktif dengan Visual Bar */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            {kasBankSummary.accountStats.map((acc) => {
                                const isSelected = String(selectedKasBankFilter) === String(acc.id);
                                const flowVolume = acc.inflow + acc.outflow;
                                const inPercent = flowVolume > 0 ? Math.round((acc.inflow / flowVolume) * 100) : 0;
                                const outPercent = flowVolume > 0 ? 100 - inPercent : 0;

                                return (
                                    <div
                                        key={acc.id}
                                        onClick={() => {
                                            setSelectedKasBankFilter(isSelected ? 'all' : String(acc.id));
                                            setMutasiCurrentPage(1);
                                        }}
                                        className={`p-4 rounded-xl border-2 transition cursor-pointer relative ${isSelected
                                            ? 'bg-blue-50/40 border-blue-500 shadow-sm'
                                            : 'bg-slate-50/60 border-slate-200/80 hover:border-blue-300 hover:bg-white'
                                            }`}
                                    >
                                        {isSelected && (
                                            <span className="absolute top-2.5 right-2.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-600 text-white shadow-xs">
                                                Terpilih
                                            </span>
                                        )}

                                        <div className="flex items-start gap-3">
                                            <div className={`w-9 h-9 rounded-xl flex items-center justify-center font-bold text-sm shrink-0 ${isSelected ? 'bg-blue-600 text-white shadow-xs' : 'bg-white text-blue-600 border border-slate-200'}`}>
                                                <Landmark className="w-4 h-4" />
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <div className="font-bold text-slate-900 text-xs truncate">
                                                    {acc.name}
                                                </div>
                                                <div className="text-[11px] text-slate-500 font-medium mt-0.5">
                                                    {acc.code} {acc.account_number ? `• ${acc.account_number}` : ''}
                                                </div>
                                            </div>
                                        </div>

                                        {/* Visual Bar Distribusi Aliran (Inflow vs Outflow) */}
                                        <div className="mt-3.5 space-y-1.5">
                                            <div className="flex items-center justify-between text-[11px]">
                                                <span className="text-emerald-700 font-semibold flex items-center gap-1">
                                                    <ArrowDownLeft className="w-3 h-3" /> +{formatRupiah(acc.inflow)}
                                                </span>
                                                <span className="text-rose-700 font-semibold flex items-center gap-1">
                                                    <ArrowUpRight className="w-3 h-3" /> -{formatRupiah(acc.outflow)}
                                                </span>
                                            </div>

                                            <div className="h-2 w-full bg-slate-200 rounded-full overflow-hidden flex">
                                                <div
                                                    style={{ width: `${flowVolume > 0 ? inPercent : 50}%` }}
                                                    className="bg-emerald-500 h-full transition-all duration-500"
                                                    title={`Masuk: ${inPercent}%`}
                                                />
                                                <div
                                                    style={{ width: `${flowVolume > 0 ? outPercent : 50}%` }}
                                                    className="bg-rose-500 h-full transition-all duration-500"
                                                    title={`Keluar: ${outPercent}%`}
                                                />
                                            </div>

                                            <div className="flex items-center justify-between text-[10px] text-slate-400 pt-0.5">
                                                <span>Saldo Buku: <strong className="font-semibold text-slate-700">{formatRupiah(acc.balance)}</strong></span>
                                                <span className="font-medium text-slate-500">{acc.txCount} Mutasi</span>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Panel Kanan (4 cols): Rasio Arus Kas Donut / Ring Graph */}
                    <div className="lg:col-span-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between space-y-5">
                        <div>
                            <h3 className="font-bold text-slate-900 text-sm flex items-center gap-2 border-b border-slate-100 pb-4">
                                <PieChart className="w-4 h-4 text-emerald-600" />
                                Rasio Arus Kas (In vs Out)
                            </h3>

                            <div className="mt-6 flex flex-col items-center justify-center">
                                <div className="relative w-40 h-40 flex items-center justify-center">
                                    <svg className="w-full h-full -rotate-90" viewBox="0 0 100 100">
                                        <circle
                                            cx="50"
                                            cy="50"
                                            r="38"
                                            fill="transparent"
                                            stroke="#F1F5F9"
                                            strokeWidth="12"
                                        />
                                        {kasBankSummary.totalFlowVolume > 0 && (
                                            <circle
                                                cx="50"
                                                cy="50"
                                                r="38"
                                                fill="transparent"
                                                stroke="#10B981"
                                                strokeWidth="12"
                                                strokeDasharray={`${(kasBankSummary.totalMasuk / kasBankSummary.totalFlowVolume) * 238.76} 238.76`}
                                                strokeDashoffset="0"
                                                strokeLinecap="round"
                                                className="transition-all duration-700"
                                            />
                                        )}
                                        {kasBankSummary.totalFlowVolume > 0 && (
                                            <circle
                                                cx="50"
                                                cy="50"
                                                r="38"
                                                fill="transparent"
                                                stroke="#F43F5E"
                                                strokeWidth="12"
                                                strokeDasharray={`${(kasBankSummary.totalKeluar / kasBankSummary.totalFlowVolume) * 238.76} 238.76`}
                                                strokeDashoffset={`-${(kasBankSummary.totalMasuk / kasBankSummary.totalFlowVolume) * 238.76}`}
                                                strokeLinecap="round"
                                                className="transition-all duration-700"
                                            />
                                        )}
                                    </svg>

                                    <div className="absolute inset-0 flex flex-col items-center justify-center text-center p-2">
                                        <span className="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">
                                            Net Flow
                                        </span>
                                        <span className={`text-xs font-bold ${kasBankSummary.netCashflow >= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>
                                            {kasBankSummary.netCashflow >= 0 ? '+' : ''}
                                            {Math.abs(kasBankSummary.netCashflow) > 1000000
                                                ? `${(kasBankSummary.netCashflow / 1000000).toFixed(1)}Jt`
                                                : formatNumberOnly(kasBankSummary.netCashflow)
                                            }
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="space-y-2.5 pt-4 border-t border-slate-100 text-xs">
                            <div className="flex items-center justify-between p-2 rounded-xl bg-emerald-50/60 border border-emerald-100">
                                <div className="flex items-center gap-2">
                                    <div className="w-2.5 h-2.5 rounded-full bg-emerald-500" />
                                    <span className="font-semibold text-emerald-900">Uang Masuk (Debit)</span>
                                </div>
                                <span className="font-bold text-emerald-700">
                                    {formatRupiah(kasBankSummary.totalMasuk)}
                                </span>
                            </div>

                            <div className="flex items-center justify-between p-2 rounded-xl bg-rose-50/60 border border-rose-100">
                                <div className="flex items-center gap-2">
                                    <div className="w-2.5 h-2.5 rounded-full bg-rose-500" />
                                    <span className="font-semibold text-rose-900">Uang Keluar (Kredit)</span>
                                </div>
                                <span className="font-bold text-rose-700">
                                    {formatRupiah(kasBankSummary.totalKeluar)}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* 3. TABEL MUTASI TRANSAKSI KAS & BANK */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden space-y-4">
                    <div className="p-5 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-2.5">
                                <h3 className="font-bold text-slate-900 text-sm">
                                    Buku Mutasi Kas & Bank (Transaksi Keluar / Masuk)
                                </h3>
                                <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                    {filteredMutasiKasBank.length} Baris
                                </span>
                            </div>
                            <p className="text-xs text-slate-400 mt-0.5">
                                {selectedKasBankFilter === 'all'
                                    ? 'Menampilkan riwayat transaksi seluruh rekening kas & bank.'
                                    : `Menampilkan mutasi akun: ${kasBankAccounts.find(a => String(a.id) === String(selectedKasBankFilter))?.name || 'Rekening'}`
                                }
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2.5">
                            {/* Direction Pills */}
                            <div className="flex items-center bg-slate-100 p-1 rounded-xl text-xs font-semibold text-slate-600">
                                <button
                                    type="button"
                                    onClick={() => { setMutasiTypeFilter('all'); setMutasiCurrentPage(1); }}
                                    className={`px-3 py-1.5 rounded-lg transition ${mutasiTypeFilter === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'hover:text-slate-900'}`}
                                >
                                    Semua
                                </button>
                                <button
                                    type="button"
                                    onClick={() => { setMutasiTypeFilter('IN'); setMutasiCurrentPage(1); }}
                                    className={`px-3 py-1.5 rounded-lg transition flex items-center gap-1 ${mutasiTypeFilter === 'IN' ? 'bg-emerald-600 text-white shadow-xs' : 'hover:text-emerald-700 text-emerald-700'}`}
                                >
                                    <ArrowDownLeft className="w-3.5 h-3.5" />
                                    Masuk (Debit)
                                </button>
                                <button
                                    type="button"
                                    onClick={() => { setMutasiTypeFilter('OUT'); setMutasiCurrentPage(1); }}
                                    className={`px-3 py-1.5 rounded-lg transition flex items-center gap-1 ${mutasiTypeFilter === 'OUT' ? 'bg-rose-600 text-white shadow-xs' : 'hover:text-rose-700 text-rose-700'}`}
                                >
                                    <ArrowUpRight className="w-3.5 h-3.5" />
                                    Keluar (Kredit)
                                </button>
                            </div>

                            {/* Search Box Mutasi */}
                            <div className="relative">
                                <Search className="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <input
                                    type="text"
                                    value={mutasiSearchQuery}
                                    onChange={(e) => { setMutasiSearchQuery(e.target.value); setMutasiCurrentPage(1); }}
                                    placeholder="Cari no. bukti, pihak, catatan..."
                                    className="pl-8 pr-3 py-1.5 text-xs rounded-xl border border-slate-200 focus:outline-hidden focus:ring-2 focus:ring-blue-500 w-56"
                                />
                            </div>

                            {/* Reset Filter Button */}
                            {(selectedKasBankFilter !== 'all' || mutasiTypeFilter !== 'all' || mutasiSearchQuery) && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSelectedKasBankFilter('all');
                                        setMutasiTypeFilter('all');
                                        setMutasiSearchQuery('');
                                        setMutasiCurrentPage(1);
                                    }}
                                    className="px-2.5 py-1.5 text-xs text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-xl transition"
                                    title="Reset Semua Filter"
                                >
                                    <X className="w-4 h-4" />
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Table Mutasi */}
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-[#F8FAFC] border-b border-slate-200 text-slate-600 font-semibold">
                                <tr>
                                    <th className="px-4 py-3.5">Tanggal</th>
                                    <th className="px-4 py-3.5">No. Dokumen / Bukti</th>
                                    <th className="px-4 py-3.5">Rekening Kas/Bank</th>
                                    <th className="px-4 py-3.5">Kategori Transaksi</th>
                                    <th className="px-4 py-3.5">Pihak Terkait</th>
                                    <th className="px-4 py-3.5">Deskripsi / Catatan</th>
                                    <th className="px-4 py-3.5 text-right">Debit (Masuk)</th>
                                    <th className="px-4 py-3.5 text-right">Kredit (Keluar)</th>
                                    <th className="px-4 py-3.5 text-center">Petugas</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {paginatedMutasi.length > 0 ? (
                                    paginatedMutasi.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-50/70 transition">
                                            <td className="px-4 py-3 font-medium text-slate-700 whitespace-nowrap">
                                                {item.date}
                                            </td>
                                            <td className="px-4 py-3 font-semibold text-slate-900 whitespace-nowrap">
                                                <span className="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-medium border border-slate-200/70">
                                                    {item.document_no}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <div className="font-bold text-slate-800 flex items-center gap-1.5">
                                                    <Landmark className="w-3.5 h-3.5 text-blue-600" />
                                                    <span>{item.account_name}</span>
                                                </div>
                                                <div className="text-[10px] text-slate-500 font-medium">
                                                    {item.account_code}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {item.category_type === 'PIUTANG' && (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                        Piutang Penjualan
                                                    </span>
                                                )}
                                                {item.category_type === 'CASH' && (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        Pembelian Cash
                                                    </span>
                                                )}
                                                {item.category_type === 'KREDIT' && (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                                        Hutang Dagang
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 font-medium text-slate-800">
                                                {item.party}
                                            </td>
                                            <td className="px-4 py-3 text-slate-500 max-w-xs truncate" title={item.description}>
                                                {item.description}
                                            </td>
                                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                                {item.direction === 'IN' ? (
                                                    <span className="font-bold text-emerald-600 inline-flex items-center justify-end gap-1">
                                                        <ArrowDownLeft className="w-3 h-3" />
                                                        +{formatRupiah(item.amount)}
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-300">-</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                                {item.direction === 'OUT' ? (
                                                    <span className="font-bold text-rose-600 inline-flex items-center justify-end gap-1">
                                                        <ArrowUpRight className="w-3 h-3" />
                                                        -{formatRupiah(item.amount)}
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-300">-</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-center text-slate-400 text-[11px] whitespace-nowrap">
                                                {item.user}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={9} className="text-center py-12 text-slate-400">
                                            <div className="flex flex-col items-center justify-center gap-2">
                                                <div className="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                                                    <Receipt className="w-5 h-5" />
                                                </div>
                                                <div className="font-semibold text-slate-600 text-xs">
                                                    Belum ada riwayat mutasi transaksi kas & bank yang sesuai filter.
                                                </div>
                                                <div className="text-[11px] text-slate-400">
                                                    Transaksi pembayaran hutang, pembelian tunai, atau penerimaan piutang akan otomatis tercatat di sini.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination Mutasi */}
                    {totalMutasiPages > 1 && (
                        <div className="p-4 border-t border-slate-100 flex items-center justify-between text-xs">
                            <span className="text-slate-500">
                                Halaman <strong>{mutasiCurrentPage}</strong> dari <strong>{totalMutasiPages}</strong> ({filteredMutasiKasBank.length} Transaksi)
                            </span>
                            <div className="flex items-center gap-1">
                                <button
                                    type="button"
                                    onClick={() => setMutasiCurrentPage(prev => Math.max(prev - 1, 1))}
                                    disabled={mutasiCurrentPage <= 1}
                                    className="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-30 disabled:cursor-not-allowed"
                                >
                                    Sebelumnya
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setMutasiCurrentPage(prev => Math.min(prev + 1, totalMutasiPages))}
                                    disabled={mutasiCurrentPage >= totalMutasiPages}
                                    className="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-30 disabled:cursor-not-allowed"
                                >
                                    Selanjutnya
                                </button>
                            </div>
                        </div>
                    )}
                </div>

                {/* 4. MASTER SELURUH AKUN KEUANGAN (BAGAN AKUN / COA) */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div className="p-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 className="font-bold text-slate-900 text-xs flex items-center gap-2">
                                <Layers className="w-4 h-4 text-blue-600" />
                                Master Seluruh Bagan Akun (Chart of Accounts)
                            </h3>
                            <p className="text-[11px] text-slate-400 mt-0.5">
                                Daftar akun akuntansi apotek terintegrasi standar Kledo.
                            </p>
                        </div>
                        <span className="text-[11px] text-slate-400 font-semibold">{accounts.length} Akun Terdaftar</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-[#F8FAFC] border-b border-slate-200 text-slate-600 font-semibold">
                                <tr>
                                    <th className="px-4 py-3">Kode Akun</th>
                                    <th className="px-4 py-3">Nama Akun (ID)</th>
                                    <th className="px-4 py-3">Nama (EN)</th>
                                    <th className="px-4 py-3">Kategori</th>
                                    <th className="px-4 py-3">No. Rekening</th>
                                    <th className="px-4 py-3 text-right">Saldo Saat Ini</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {accounts.map((acc) => (
                                    <tr key={acc.id} className="hover:bg-slate-50/70 transition">
                                        <td className="px-4 py-3 font-semibold text-blue-600">{acc.code}</td>
                                        <td className="px-4 py-3 font-bold text-slate-900">{acc.name}</td>
                                        <td className="px-4 py-3 text-slate-500">{acc.name_en || '-'}</td>
                                        <td className="px-4 py-3">
                                            <span className={`px-2 py-0.5 rounded text-[11px] font-semibold ${acc.category === 'Kas & Bank'
                                                ? 'bg-blue-50 text-blue-600'
                                                : 'bg-slate-100 text-slate-600'
                                                }`}>
                                                {acc.category}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{acc.account_number || '-'}</td>
                                        <td className="px-4 py-3 text-slate-800 font-semibold text-right">
                                            {acc.category === 'Kas & Bank' ? formatRupiah(acc.balance) : '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="text-emerald-600 font-semibold text-[11px] flex items-center gap-1">
                                                <Check className="w-3 h-3" /> Aktif
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <div className="flex items-center justify-center gap-1">
                                                <button
                                                    onClick={() => openEditAccountModal(acc)}
                                                    className="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                                                    title="Edit Akun"
                                                >
                                                    <Edit3 className="w-3.5 h-3.5" />
                                                </button>
                                                <button
                                                    onClick={() => setDeletingAccount(acc)}
                                                    className="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                                                    title="Hapus Akun"
                                                >
                                                    <Trash2 className="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* MODAL TAMBAH / EDIT AKUN */}
            {isAccountModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in duration-200">
                        <div className="px-6 py-4 flex items-center justify-between border-b border-slate-100">
                            <h3 className="font-bold text-slate-900 text-base">
                                {editingAccount ? 'Edit Akun' : 'Tambah Akun'}
                            </h3>
                            <button
                                onClick={() => {
                                    setIsAccountModalOpen(false);
                                    setEditingAccount(null);
                                }}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleAccountSubmit} className="p-6 space-y-4 text-xs">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        <span className="text-red-500">*</span> Nama (ID)
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Nama"
                                        value={accountForm.name}
                                        onChange={(e) => setAccountForm({ ...accountForm, name: e.target.value })}
                                        required
                                        className="w-full px-3 py-2 bg-white border border-blue-500 ring-2 ring-blue-100 rounded-lg text-xs focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Nama (EN)
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Nama"
                                        value={accountForm.name_en}
                                        onChange={(e) => setAccountForm({ ...accountForm, name_en: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div className="p-3 bg-blue-50/70 border border-blue-100 rounded-xl flex items-center gap-2 text-blue-700 text-xs">
                                <HelpCircle className="w-4 h-4 shrink-0 text-blue-600" />
                                <span>Jika EN kosong, sistem otomatis menggunakan ID</span>
                            </div>

                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <label className="block text-slate-700 font-semibold">
                                        Kode <span className="text-[11px] font-normal text-slate-400">(Otomatis jika kosong)</span>
                                    </label>
                                    {!editingAccount && (
                                        <span className="text-[10px] text-blue-600 font-medium">
                                            Saran: {getNextCodeForCategory(accountForm.category || 'Kas & Bank')}
                                        </span>
                                    )}
                                </div>
                                <input
                                    type="text"
                                    placeholder={`Otomatis (contoh: ${getNextCodeForCategory(accountForm.category || 'Kas & Bank')})`}
                                    value={accountForm.code}
                                    onChange={(e) => setAccountForm({ ...accountForm, code: e.target.value })}
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <label className="block text-slate-700 font-semibold">
                                        <span className="text-red-500">*</span> Kategori
                                    </label>
                                    {!isCustomCategory ? (
                                        <button
                                            type="button"
                                            onClick={() => handleCategoryChange('__NEW__')}
                                            className="text-blue-600 hover:text-blue-800 text-[11px] font-semibold flex items-center gap-1 transition"
                                        >
                                            <Plus className="w-3 h-3" />
                                            <span>+ Kategori Baru</span>
                                        </button>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() => handleCategoryChange('Kas & Bank')}
                                            className="text-slate-500 hover:text-slate-800 text-[11px] font-medium flex items-center gap-1 transition"
                                        >
                                            <X className="w-3 h-3" />
                                            <span>Pilih dari Daftar</span>
                                        </button>
                                    )}
                                </div>

                                {isCustomCategory ? (
                                    <div className="space-y-1.5">
                                        <div className="flex items-center gap-2">
                                            <input
                                                type="text"
                                                placeholder="Ketik nama kategori baru (contoh: Pajak & Retribusi)..."
                                                value={customCategoryInput}
                                                onChange={(e) => {
                                                    const val = e.target.value;
                                                    setCustomCategoryInput(val);
                                                    setAccountForm((prev) => ({
                                                        ...prev,
                                                        category: val,
                                                    }));
                                                }}
                                                autoFocus
                                                required
                                                className="w-full px-3 py-2 bg-white border border-blue-500 ring-2 ring-blue-100 rounded-lg text-xs font-semibold focus:outline-none"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => handleCategoryChange('Kas & Bank')}
                                                className="px-2.5 py-2 text-xs border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-lg shrink-0 font-medium transition"
                                            >
                                                Batal
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <select
                                        value={accountForm.category}
                                        onChange={(e) => handleCategoryChange(e.target.value)}
                                        required
                                        className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-lg text-xs font-medium text-slate-800 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                    >
                                        {categoryList.map((cat) => (
                                            <option key={cat} value={cat}>{cat}</option>
                                        ))}
                                        <option value="__NEW__" className="font-bold text-blue-600">
                                            + Tambah Kategori Baru...
                                        </option>
                                    </select>
                                )}
                            </div>

                            {(accountForm.category === 'Kas & Bank' || isCustomCategory) && (
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Nomor Rekening Bank (Opsional)
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Nomor rekening bank..."
                                        value={accountForm.account_number}
                                        onChange={(e) => setAccountForm({ ...accountForm, account_number: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>
                            )}

                            <div className="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setIsAccountModalOpen(false);
                                        setEditingAccount(null);
                                    }}
                                    className="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 text-slate-700 rounded-lg text-xs font-semibold hover:bg-slate-50 transition"
                                >
                                    <X className="w-3.5 h-3.5" />
                                    <span>Batal</span>
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmittingAccount}
                                    className="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold transition disabled:opacity-50 shadow-sm"
                                >
                                    {editingAccount ? <Check className="w-3.5 h-3.5" /> : <Plus className="w-3.5 h-3.5" />}
                                    <span>
                                        {isSubmittingAccount ? 'Menyimpan...' : editingAccount ? 'Simpan Perubahan' : 'Tambah'}
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* MODAL HAPUS AKUN */}
            {deletingAccount && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden animate-in fade-in duration-200">
                        <div className="p-6 space-y-4 text-xs">
                            <div className="w-11 h-11 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center mx-auto">
                                <Trash2 className="w-6 h-6" />
                            </div>
                            <div className="text-center space-y-1.5">
                                <h3 className="font-bold text-slate-900 text-base">Hapus Akun Keuangan?</h3>
                                <p className="text-slate-600">
                                    Apakah Anda yakin ingin menghapus akun <strong className="text-slate-900 font-semibold">{deletingAccount.name}</strong> ({deletingAccount.code})?
                                </p>
                                <p className="text-[11px] text-amber-600 bg-amber-50 p-2.5 rounded-xl border border-amber-200 text-left">
                                    <strong>Catatan:</strong> Akun yang sudah pernah digunakan pada transaksi pembayaran tidak dapat dihapus demi integritas pembukuan apotek.
                                </p>
                            </div>
                            <div className="pt-2 flex items-center justify-center gap-2.5">
                                <button
                                    type="button"
                                    onClick={() => setDeletingAccount(null)}
                                    className="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="button"
                                    onClick={handleDeleteAccount}
                                    disabled={isDeletingAccount}
                                    className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50 flex items-center gap-1.5"
                                >
                                    <Trash2 className="w-3.5 h-3.5" />
                                    <span>{isDeletingAccount ? 'Menghapus...' : 'Hapus Akun'}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </FinanceLayout>
    );
}
