import React, { useState, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import FinanceLayout from './Layouts/FinanceLayout';
import { formatRupiah } from './Components/Utils';
import {
    Layers,
    Search,
    Plus,
    X,
    Check,
    Edit3,
    Trash2,
    HelpCircle,
    Building2,
    Landmark,
    FileSpreadsheet,
    DollarSign,
    BookOpen,
    Filter
} from 'lucide-react';

export default function Accounts({
    accounts = [],
    categories = [],
    stats = {},
    branchContext = {}
}) {
    // Search and Category Filter
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('ALL');

    // Modals
    const [isAccountModalOpen, setIsAccountModalOpen] = useState(false);
    const [editingAccount, setEditingAccount] = useState(null);
    const [isSubmittingAccount, setIsSubmittingAccount] = useState(false);
    const [deletingAccount, setDeletingAccount] = useState(null);
    const [isDeletingAccount, setIsDeletingAccount] = useState(false);

    // Custom Category Support
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
        const merged = Array.from(new Set([...defaults, ...(categories || [])]));
        return merged.filter(Boolean);
    }, [categories]);

    // Open Modal Tambah Akun
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

    // Open Modal Edit Akun
    const openEditAccountModal = (acc) => {
        setEditingAccount(acc);
        setIsCustomCategory(false);
        setCustomCategoryInput('');
        setAccountForm({
            name: acc.name,
            name_en: acc.name_en || '',
            code: acc.code,
            category: acc.category || 'Kas & Bank',
            account_number: acc.account_number || '',
        });
        setIsAccountModalOpen(true);
    };

    // Handle Kategori Ganti
    const handleCategoryChange = (val) => {
        if (val === '__NEW__') {
            setIsCustomCategory(true);
            setCustomCategoryInput('');
            setAccountForm((prev) => ({
                ...prev,
                category: '',
                code: '',
            }));
            return;
        }

        setIsCustomCategory(false);
        const nextCode = getNextCodeForCategory(val);
        setAccountForm((prev) => ({
            ...prev,
            category: val,
            code: !editingAccount ? nextCode : prev.code,
        }));
    };

    // Submit Tambah/Edit Akun
    const handleAccountSubmit = (e) => {
        e.preventDefault();
        setIsSubmittingAccount(true);

        const url = editingAccount ? `/finance/accounts/${editingAccount.id}` : '/finance/accounts';
        const method = editingAccount ? 'put' : 'post';

        router[method](url, accountForm, {
            preserveScroll: true,
            onSuccess: () => {
                setIsAccountModalOpen(false);
                setIsSubmittingAccount(false);
                setEditingAccount(null);
            },
            onError: (errs) => {
                setIsSubmittingAccount(false);
                alert(Object.values(errs)[0] || 'Gagal menyimpan data akun.');
            }
        });
    };

    // Submit Hapus Akun
    const handleDeleteAccount = () => {
        if (!deletingAccount) return;
        setIsDeletingAccount(true);

        router.delete(`/finance/accounts/${deletingAccount.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeletingAccount(null);
                setIsDeletingAccount(false);
            },
            onError: (errs) => {
                setIsDeletingAccount(false);
                alert(Object.values(errs)[0] || 'Akun tidak dapat dihapus.');
            }
        });
    };

    // Filtered Accounts
    const filteredAccounts = useMemo(() => {
        return accounts.filter((item) => {
            const matchesSearch =
                searchTerm === '' ||
                item.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.code?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.name_en?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.account_number?.toLowerCase().includes(searchTerm.toLowerCase());

            const matchesCategory =
                selectedCategory === 'ALL' || item.category === selectedCategory;

            return matchesSearch && matchesCategory;
        });
    }, [accounts, searchTerm, selectedCategory]);

    // Statistics counts
    const countKasBank = useMemo(() => accounts.filter((a) => a.category === 'Kas & Bank').length, [accounts]);
    const countBeban = useMemo(() => accounts.filter((a) => a.category?.startsWith('Beban')).length, [accounts]);
    const countPendapatan = useMemo(() => accounts.filter((a) => a.category === 'Pendapatan' || a.category === 'Harga Pokok Penjualan').length, [accounts]);

    const getCategoryBadgeClass = (category) => {
        switch (category) {
            case 'Kas & Bank':
                return 'bg-blue-50 text-blue-700 border-blue-200';
            case 'Piutang Usaha':
            case 'Aktiva Lancar Lainnya':
            case 'Aktiva Tetap':
                return 'bg-indigo-50 text-indigo-700 border-indigo-200';
            case 'Hutang Usaha':
            case 'Kewajiban Lancar Lainnya':
            case 'Kewajiban Jangka Panjang':
                return 'bg-amber-50 text-amber-700 border-amber-200';
            case 'Pendapatan':
                return 'bg-emerald-50 text-emerald-700 border-emerald-200';
            case 'Harga Pokok Penjualan':
                return 'bg-teal-50 text-teal-700 border-teal-200';
            case 'Beban Operasional':
            case 'Beban Lainnya':
                return 'bg-rose-50 text-rose-700 border-rose-200';
            case 'Ekuitas / Modal':
                return 'bg-purple-50 text-purple-700 border-purple-200';
            default:
                return 'bg-slate-100 text-slate-700 border-slate-200';
        }
    };

    return (
        <FinanceLayout
            title="Bagan Akun"
            subtitle="Master struktur bagan akun (Chart of Accounts / COA) akuntansi apotek terintegrasi"
            stats={stats}
        >
            <Head title="Bagan Akun - Finance ERP" />

            <div className="space-y-6">
                {/* 1. KARTU STATISTIK RINGKASAN AKUN */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:shadow-xs transition">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Total Bagan Akun
                            </span>
                            <div className="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                                <Layers className="w-5 h-5" />
                            </div>
                        </div>
                        <p className="text-2xl font-black text-slate-900 mt-2 tracking-tight">
                            {accounts.length}
                        </p>
                        <p className="text-[11px] text-slate-500 mt-1 font-medium">
                            Akun akuntansi terdaftar
                        </p>
                    </div>

                    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:shadow-xs transition">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Akun Kas & Bank
                            </span>
                            <div className="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                                <Landmark className="w-5 h-5" />
                            </div>
                        </div>
                        <p className="text-2xl font-black text-indigo-700 mt-2 tracking-tight">
                            {countKasBank}
                        </p>
                        <p className="text-[11px] text-slate-500 mt-1 font-medium">
                            Drawer kas & rekening bank
                        </p>
                    </div>

                    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:shadow-xs transition">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Akun Beban & Biaya
                            </span>
                            <div className="w-9 h-9 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600">
                                <DollarSign className="w-5 h-5" />
                            </div>
                        </div>
                        <p className="text-2xl font-black text-rose-700 mt-2 tracking-tight">
                            {countBeban}
                        </p>
                        <p className="text-[11px] text-slate-500 mt-1 font-medium">
                            Pos pengeluaran operasional
                        </p>
                    </div>

                    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:shadow-xs transition">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Kategori Akun
                            </span>
                            <div className="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                                <BookOpen className="w-5 h-5" />
                            </div>
                        </div>
                        <p className="text-2xl font-black text-emerald-700 mt-2 tracking-tight">
                            {categoryList.length}
                        </p>
                        <p className="text-[11px] text-slate-500 mt-1 font-medium">
                            Kelompok klasifikasi COA
                        </p>
                    </div>
                </div>

                {/* 2. TOOLBAR FILTER, SEARCH & TAMBAH AKUN */}
                <div className="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs space-y-3.5">
                    {/* Header Action Row */}
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div className="relative min-w-[260px] flex-1 max-w-md">
                            <Search className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Cari kode akun, nama akun, nomor rekening..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="w-full pl-9 pr-4 py-2 text-xs bg-slate-50 hover:bg-slate-100/80 focus:bg-white border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition"
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => openAccountModal('Beban Operasional')}
                                className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition cursor-pointer"
                            >
                                <Plus className="w-4 h-4" />
                                <span>+ Akun Beban</span>
                            </button>

                            <button
                                type="button"
                                onClick={() => openAccountModal('Kas & Bank')}
                                className="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer"
                            >
                                <Plus className="w-4 h-4" />
                                <span>Tambah Akun Baru</span>
                            </button>
                        </div>
                    </div>

                    {/* Category Filter Pills */}
                    <div className="pt-2 border-t border-slate-100 flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
                        <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1 shrink-0 flex items-center gap-1">
                            <Filter className="w-3 h-3" /> Kategori:
                        </span>
                        <button
                            type="button"
                            onClick={() => setSelectedCategory('ALL')}
                            className={`px-3 py-1 rounded-xl text-xs font-semibold whitespace-nowrap transition cursor-pointer ${
                                selectedCategory === 'ALL'
                                    ? 'bg-blue-600 text-white shadow-2xs'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            }`}
                        >
                            Semua ({accounts.length})
                        </button>
                        {categoryList.map((cat) => {
                            const count = accounts.filter((a) => a.category === cat).length;
                            return (
                                <button
                                    key={cat}
                                    type="button"
                                    onClick={() => setSelectedCategory(cat)}
                                    className={`px-3 py-1 rounded-xl text-xs font-semibold whitespace-nowrap transition cursor-pointer ${
                                        selectedCategory === cat
                                            ? 'bg-blue-600 text-white shadow-2xs'
                                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                    }`}
                                >
                                    {cat} ({count})
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* 3. TABEL MASTER BAGAN AKUN */}
                <div className="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-2xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50/80 border-b border-slate-200/80 text-slate-500 font-semibold uppercase tracking-wider text-[11px]">
                                <tr>
                                    <th className="py-3.5 px-4">Kode Akun</th>
                                    <th className="py-3.5 px-4">Nama Akun (Indonesia)</th>
                                    <th className="py-3.5 px-4">Nama (English)</th>
                                    <th className="py-3.5 px-4">Klasifikasi / Kategori</th>
                                    <th className="py-3.5 px-4">No. Rekening</th>
                                    <th className="py-3.5 px-4 text-right">Saldo Terakhir</th>
                                    <th className="py-3.5 px-4 text-center">Status</th>
                                    <th className="py-3.5 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-slate-700">
                                {filteredAccounts.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="py-12 text-center text-slate-400">
                                            <Layers className="w-10 h-10 mx-auto text-slate-300 mb-2 stroke-1" />
                                            <p className="font-semibold text-slate-600">Tidak ada akun yang sesuai kriteria pencarian</p>
                                            <p className="text-[11px] text-slate-400 mt-0.5">
                                                Coba sesuaikan kata kunci atau pilih kategori lain
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    filteredAccounts.map((acc) => (
                                        <tr key={acc.id} className="hover:bg-slate-50/80 transition group">
                                            <td className="py-3 px-4 font-mono font-bold text-blue-700 whitespace-nowrap">
                                                {acc.code}
                                            </td>
                                            <td className="py-3 px-4 font-bold text-slate-900">
                                                {acc.name}
                                            </td>
                                            <td className="py-3 px-4 text-slate-500 italic">
                                                {acc.name_en || '-'}
                                            </td>
                                            <td className="py-3 px-4 whitespace-nowrap">
                                                <span className={`px-2.5 py-0.5 rounded-lg text-[11px] font-bold border ${getCategoryBadgeClass(acc.category)}`}>
                                                    {acc.category}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 font-mono text-slate-600 whitespace-nowrap">
                                                {acc.account_number || '-'}
                                            </td>
                                            <td className="py-3 px-4 text-right whitespace-nowrap font-semibold">
                                                {acc.category === 'Kas & Bank' ? (
                                                    <span className={Number(acc.balance) < 0 ? 'text-rose-600 font-bold' : 'text-slate-800'}>
                                                        {formatRupiah(acc.balance)}
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-400">-</span>
                                                )}
                                            </td>
                                            <td className="py-3 px-4 text-center whitespace-nowrap">
                                                <span className="inline-flex items-center gap-1 text-[11px] text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md font-semibold">
                                                    <Check className="w-3 h-3 text-emerald-600" />
                                                    <span>Aktif</span>
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 text-center whitespace-nowrap">
                                                <div className="flex items-center justify-center gap-1">
                                                    <button
                                                        type="button"
                                                        onClick={() => openEditAccountModal(acc)}
                                                        className="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition cursor-pointer"
                                                        title="Edit Akun"
                                                    >
                                                        <Edit3 className="w-3.5 h-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => setDeletingAccount(acc)}
                                                        className="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition cursor-pointer"
                                                        title="Hapus Akun"
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
                </div>
            </div>

            {/* MODAL TAMBAH / EDIT AKUN */}
            {isAccountModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in duration-200">
                        <div className="px-6 py-4 flex items-center justify-between border-b border-slate-100">
                            <h3 className="font-bold text-slate-900 text-base">
                                {editingAccount ? 'Edit Akun Keuangan' : 'Tambah Akun Keuangan Baru'}
                            </h3>
                            <button
                                onClick={() => {
                                    setIsAccountModalOpen(false);
                                    setEditingAccount(null);
                                }}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg cursor-pointer"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleAccountSubmit} className="p-6 space-y-4 text-xs">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        <span className="text-red-500">*</span> Nama Akun (ID)
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Contoh: Beban Listrik & Air"
                                        value={accountForm.name}
                                        onChange={(e) => setAccountForm({ ...accountForm, name: e.target.value })}
                                        required
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Nama Akun (EN)
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Electricity & Water Expense"
                                        value={accountForm.name_en}
                                        onChange={(e) => setAccountForm({ ...accountForm, name_en: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition"
                                    />
                                </div>
                            </div>

                            <div className="p-3 bg-blue-50/70 border border-blue-100 rounded-xl flex items-center gap-2 text-blue-700 text-xs">
                                <HelpCircle className="w-4 h-4 shrink-0 text-blue-600" />
                                <span>Jika nama EN dikosongkan, sistem otomatis menggunakan nama ID.</span>
                            </div>

                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <label className="block text-slate-700 font-semibold">
                                        Kode Akun <span className="text-[11px] font-normal text-slate-400">(Wajib unik)</span>
                                    </label>
                                    {!editingAccount && (
                                        <span className="text-[10px] text-blue-600 font-medium">
                                            Saran: {getNextCodeForCategory(accountForm.category || 'Kas & Bank')}
                                        </span>
                                    )}
                                </div>
                                <input
                                    type="text"
                                    placeholder={`Contoh: ${getNextCodeForCategory(accountForm.category || 'Kas & Bank')}`}
                                    value={accountForm.code}
                                    onChange={(e) => setAccountForm({ ...accountForm, code: e.target.value })}
                                    required
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition"
                                />
                            </div>

                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <label className="block text-slate-700 font-semibold">
                                        <span className="text-red-500">*</span> Klasifikasi Kategori
                                    </label>
                                    {!isCustomCategory ? (
                                        <button
                                            type="button"
                                            onClick={() => handleCategoryChange('__NEW__')}
                                            className="text-blue-600 hover:text-blue-800 text-[11px] font-semibold flex items-center gap-1 transition cursor-pointer"
                                        >
                                            <Plus className="w-3 h-3" />
                                            <span>+ Kategori Baru</span>
                                        </button>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() => handleCategoryChange('Kas & Bank')}
                                            className="text-slate-500 hover:text-slate-800 text-[11px] font-medium flex items-center gap-1 transition cursor-pointer"
                                        >
                                            <X className="w-3 h-3" />
                                            <span>Pilih dari Daftar</span>
                                        </button>
                                    )}
                                </div>

                                {isCustomCategory ? (
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="text"
                                            placeholder="Ketik nama kategori baru (contoh: Beban Pemasaran)..."
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
                                            className="w-full px-3 py-2 bg-white border border-blue-500 ring-2 ring-blue-100 rounded-xl text-xs font-semibold focus:outline-hidden"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => handleCategoryChange('Kas & Bank')}
                                            className="px-2.5 py-2 text-xs border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-xl shrink-0 font-medium transition cursor-pointer"
                                        >
                                            Batal
                                        </button>
                                    </div>
                                ) : (
                                    <select
                                        value={accountForm.category}
                                        onChange={(e) => handleCategoryChange(e.target.value)}
                                        required
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition cursor-pointer"
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
                                        Nomor Rekening Bank (Opsional, khusus Kas & Bank)
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Contoh: 123-456-7890 (BCA)"
                                        value={accountForm.account_number}
                                        onChange={(e) => setAccountForm({ ...accountForm, account_number: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition font-mono"
                                    />
                                </div>
                            )}

                            <div className="pt-4 flex items-center justify-end gap-2.5 border-t border-slate-100">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setIsAccountModalOpen(false);
                                        setEditingAccount(null);
                                    }}
                                    className="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 transition cursor-pointer"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmittingAccount}
                                    className="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition disabled:opacity-50 shadow-xs flex items-center gap-1.5 cursor-pointer"
                                >
                                    {editingAccount ? <Check className="w-3.5 h-3.5" /> : <Plus className="w-3.5 h-3.5" />}
                                    <span>
                                        {isSubmittingAccount ? 'Menyimpan...' : editingAccount ? 'Simpan Perubahan' : 'Simpan Akun'}
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
                                    className="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 transition cursor-pointer"
                                >
                                    Batal
                                </button>
                                <button
                                    type="button"
                                    onClick={handleDeleteAccount}
                                    disabled={isDeletingAccount}
                                    className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
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
