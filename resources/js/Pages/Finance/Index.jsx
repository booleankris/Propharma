import React, { useState, useMemo, useEffect, useRef } from 'react';
import { Head, Link, usePage, router } from '@inertiajs/react';
import {
    LayoutDashboard, ShoppingCart, ShoppingBag, CreditCard,
    Wallet, Receipt, Search, Bell, ArrowUpRight, ArrowDownRight, ArrowDownLeft,
    CheckCircle2, Clock, AlertCircle, ChevronRight, ArrowLeft,
    Filter, Download, Eye, ExternalLink, Building2, ChevronDown, Scale, ChevronLeft, ChevronUp,
    Printer, MoreVertical, Plus, Calendar, Edit3, HelpCircle,
    BarChart3, FileSpreadsheet, ArrowRightLeft, TrendingUp, TrendingDown, SlidersHorizontal, List, Share2,
    Check, X, ShieldCheck, History, Landmark, Banknote, Trash2, Layers, PieChart, ArrowDownUp,
    LogOut, Home
} from 'lucide-react';

// Komponen Autocomplete Dropdown PBF / Kreditur dengan Rekomendasi Pintar
function PbfCombobox({ pbfs = [], selectedPbf = '', onSelectPbf, placeholder = "Filter PBF (Kreditur)..." }) {
    const [query, setQuery] = useState(selectedPbf || '');
    const [isOpen, setIsOpen] = useState(false);
    const containerRef = useRef(null);

    useEffect(() => {
        setQuery(selectedPbf || '');
    }, [selectedPbf]);

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
        if (!q && isOpen) return pbfs.slice(0, 15);
        return pbfs
            .filter((p) => p.name.toLowerCase().includes(q) || (p.code && p.code.toLowerCase().includes(q)))
            .slice(0, 15);
    }, [pbfs, query, isOpen]);

    const handleSelect = (pbfName) => {
        setQuery(pbfName);
        onSelectPbf(pbfName);
        setIsOpen(false);
    };

    const handleClear = () => {
        setQuery('');
        onSelectPbf('');
        setIsOpen(false);
    };

    return (
        <div ref={containerRef} className="relative w-full sm:w-72">
            <div className="relative flex items-center">
                <Building2 className={`w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none ${selectedPbf ? 'text-blue-600' : 'text-slate-400'}`} />
                <input
                    type="text"
                    value={query}
                    onChange={(e) => {
                        setQuery(e.target.value);
                        setIsOpen(true);
                        if (!e.target.value) {
                            onSelectPbf('');
                        }
                    }}
                    onFocus={() => setIsOpen(true)}
                    placeholder={placeholder}
                    className={`w-full pl-9 pr-8 py-2 bg-slate-50 border rounded-xl text-xs transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white ${selectedPbf
                        ? 'border-blue-500 ring-1 ring-blue-100 font-semibold text-blue-900 bg-blue-50/40'
                        : 'border-slate-200 text-slate-800'
                        }`}
                />
                {query ? (
                    <button
                        type="button"
                        onClick={handleClear}
                        className="absolute right-2.5 top-1/2 -translate-y-1/2 p-0.5 text-slate-400 hover:text-red-500 rounded-full hover:bg-red-50 transition"
                        title="Hapus Filter PBF"
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
                        <span>Rekomendasi PBF / Vendor</span>
                        <span className="text-slate-400">{suggestions.length} saran</span>
                    </div>
                    {selectedPbf && (
                        <button
                            type="button"
                            onClick={handleClear}
                            className="w-full text-left px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 flex items-center gap-2 transition"
                        >
                            <X className="w-3.5 h-3.5" />
                            <span>Reset Filter (Semua PBF)</span>
                        </button>
                    )}
                    {suggestions.length === 0 ? (
                        <div className="p-3 text-center text-xs text-slate-400">
                            Tidak ada nama PBF yang cocok.
                        </div>
                    ) : (
                        suggestions.map((p) => (
                            <button
                                key={p.name}
                                type="button"
                                onClick={() => handleSelect(p.name)}
                                className={`w-full text-left px-3 py-2.5 text-xs hover:bg-blue-50/70 flex items-center justify-between gap-2 transition ${selectedPbf === p.name ? 'bg-blue-50 font-bold text-blue-700' : 'text-slate-700'
                                    }`}
                            >
                                <div className="flex items-center gap-2 overflow-hidden">
                                    <Building2 className={`w-3.5 h-3.5 shrink-0 ${selectedPbf === p.name ? 'text-blue-600' : 'text-slate-400'}`} />
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

// Komponen Autocomplete Dropdown Debtor / Pelanggan Kredit
function DebtorCombobox({ debtors = [], selectedDebtor = '', onSelectDebtor, placeholder = "Filter Debtor / Instansi..." }) {
    const [query, setQuery] = useState(selectedDebtor || '');
    const [isOpen, setIsOpen] = useState(false);
    const containerRef = useRef(null);

    useEffect(() => {
        setQuery(selectedDebtor || '');
    }, [selectedDebtor]);

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
        if (!q && isOpen) return debtors.slice(0, 15);
        return debtors
            .filter((d) => d.name.toLowerCase().includes(q) || (d.code && d.code.toLowerCase().includes(q)))
            .slice(0, 15);
    }, [debtors, query, isOpen]);

    const handleSelect = (name) => {
        setQuery(name);
        onSelectDebtor(name);
        setIsOpen(false);
    };

    const handleClear = () => {
        setQuery('');
        onSelectDebtor('');
        setIsOpen(false);
    };

    return (
        <div ref={containerRef} className="relative w-full sm:w-72">
            <div className="relative flex items-center">
                <Building2 className={`w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none ${selectedDebtor ? 'text-amber-600' : 'text-slate-400'}`} />
                <input
                    type="text"
                    value={query}
                    onChange={(e) => {
                        setQuery(e.target.value);
                        setIsOpen(true);
                        if (!e.target.value) {
                            onSelectDebtor('');
                        }
                    }}
                    onFocus={() => setIsOpen(true)}
                    placeholder={placeholder}
                    className={`w-full pl-9 pr-8 py-2 bg-slate-50 border rounded-xl text-xs transition focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white ${selectedDebtor
                        ? 'border-amber-500 ring-1 ring-amber-100 font-semibold text-amber-900 bg-amber-50/40'
                        : 'border-slate-200 text-slate-800'
                        }`}
                />
                {query ? (
                    <button
                        type="button"
                        onClick={handleClear}
                        className="absolute right-2.5 p-1 text-slate-400 hover:text-slate-600 rounded-full hover:bg-slate-200/50"
                        title="Hapus filter"
                    >
                        <X className="w-3.5 h-3.5" />
                    </button>
                ) : (
                    <ChevronDown className="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                )}
            </div>

            {isOpen && suggestions.length > 0 && (
                <div className="absolute z-50 mt-1.5 w-full bg-white rounded-xl border border-slate-200 shadow-xl max-h-56 overflow-y-auto py-1 text-xs divide-y divide-slate-100">
                    <div className="px-3 py-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/70">
                        Pilih Debtor / Instansi
                    </div>
                    {suggestions.map((d) => (
                        <button
                            key={d.id}
                            type="button"
                            onClick={() => handleSelect(d.name)}
                            className="w-full px-3 py-2 text-left hover:bg-amber-50 flex items-center justify-between text-slate-700 transition group"
                        >
                            <span className="font-medium group-hover:text-amber-700 truncate">{d.name}</span>
                            {d.code && (
                                <span className="shrink-0 text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-slate-500">
                                    {d.code}
                                </span>
                            )}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function Index({
    hutangDagang = [],
    pembelianCash = [],
    piutangPenjualan = [],
    accounts = [],
    categories = [],
    creditors = [],
    debtors = [],
    mutasiKasBank = []
}) {
    const { auth, flash } = usePage().props;

    // State Navigasi Tab Utama
    const [activeTab, setActiveTab] = useState('beranda'); // 'beranda' | 'hutang' | 'cash' | 'konsinyasi' | 'kas_bank' | 'piutang' | 'biaya'
    const [currentPage, setCurrentPage] = useState(1);
    const [itemsPerPage, setItemsPerPage] = useState(10);

    // State Filter & Search pada Hutang Dagang
    const [statusSubTab, setStatusSubTab] = useState('Semua'); // 'Semua' | 'Belum Dibayar' | 'Dibayar Sebagian' | 'Lunas'
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedPbf, setSelectedPbf] = useState('');
    const [dateRange, setDateRange] = useState('05/09/2025 - 05/09/2026');

    // State Faktur yang Sedang Dilihat Detilnya
    const [selectedHutang, setSelectedHutang] = useState(null);
    const [selectedCash, setSelectedCash] = useState(null);
    const [selectedPiutang, setSelectedPiutang] = useState(null);
    const [isShippingOpen, setIsShippingOpen] = useState(true);
    const [isCashShippingOpen, setIsCashShippingOpen] = useState(true);
    const [isPiutangShippingOpen, setIsPiutangShippingOpen] = useState(true);

    // Sinkronisasi data detail cash jika data pembelianCash ter-update dari backend
    useEffect(() => {
        if (selectedCash) {
            const updated = pembelianCash.find((c) => c.id === selectedCash.id);
            if (updated) {
                setSelectedCash(updated);
            }
        }
    }, [pembelianCash]);

    // Sinkronisasi data detail piutang jika data piutangPenjualan ter-update dari backend
    useEffect(() => {
        if (selectedPiutang) {
            const updated = piutangPenjualan.find((p) => p.id === selectedPiutang.id);
            if (updated) {
                setSelectedPiutang(updated);
            }
        }
    }, [piutangPenjualan]);

    // State Multi-Selection (Maksimal 10 Item)
    const [selectedHutangIds, setSelectedHutangIds] = useState([]);
    const [selectedCashIds, setSelectedCashIds] = useState([]);
    const [selectedPiutangIds, setSelectedPiutangIds] = useState([]);
    const [selectionWarning, setSelectionWarning] = useState('');

    // State Filter & Search pada Piutang Penjualan
    const [searchPiutangQuery, setSearchPiutangQuery] = useState('');
    const [selectedDebtor, setSelectedDebtor] = useState('');
    const [statusPiutangFilter, setStatusPiutangFilter] = useState('Semua'); // 'Semua' | 'Belum Lunas' | 'Dibayar Sebagian' | 'Lunas'
    const [piutangCurrentPage, setPiutangCurrentPage] = useState(1);

    // State Modal Terima Pembayaran Piutang (Single)
    const [isPiutangPaymentModalOpen, setIsPiutangPaymentModalOpen] = useState(false);
    const [piutangPaymentForm, setPiutangPaymentForm] = useState({
        medicine_transaction_id: '',
        account_id: '',
        payment_date: new Date().toISOString().split('T')[0],
        amount: 0,
        reference_number: '',
        notes: '',
        invoice_number: '',
        debtor: '',
        total: 0,
        sisa: 0,
    });
    const [isSubmittingPiutangPayment, setIsSubmittingPiutangPayment] = useState(false);

    // State Modal Pelunasan Massal Piutang (Multi)
    const [isBulkPiutangModalOpen, setIsBulkPiutangModalOpen] = useState(false);
    const [bulkPiutangForm, setBulkPiutangForm] = useState({
        account_id: '',
        payment_date: new Date().toISOString().split('T')[0],
        reference_number: '',
        notes: '',
    });
    const [isSubmittingBulkPiutang, setIsSubmittingBulkPiutang] = useState(false);

    // State Modal Pelunasan Massal Kredit (Hutang Dagang)
    const [isBulkPaymentModalOpen, setIsBulkPaymentModalOpen] = useState(false);
    const [bulkPaymentForm, setBulkPaymentForm] = useState({
        account_id: '',
        payment_date: new Date().toISOString().split('T')[0],
        reference_number: '',
        notes: '',
    });
    const [isSubmittingBulkPayment, setIsSubmittingBulkPayment] = useState(false);

    // State Modal Atur Akun Massal (Pembelian Cash)
    const [isBulkCashModalOpen, setIsBulkCashModalOpen] = useState(false);
    const [bulkCashForm, setBulkCashForm] = useState({
        account_id: '',
        payment_date: new Date().toISOString().split('T')[0],
        reference_number: '',
        notes: '',
    });
    const [isSubmittingBulkCash, setIsSubmittingBulkCash] = useState(false);

    // State Filter & Navigasi Kas & Bank & Mutasi
    const [selectedKasBankFilter, setSelectedKasBankFilter] = useState('all'); // 'all' | account_id
    const [mutasiTypeFilter, setMutasiTypeFilter] = useState('all'); // 'all' | 'IN' | 'OUT'
    const [mutasiSearchQuery, setMutasiSearchQuery] = useState('');
    const [mutasiCurrentPage, setMutasiCurrentPage] = useState(1);
    const mutasiItemsPerPage = 10;

    // State Modal Pembayaran Kredit (Hutang Dagang)
    const [isPaymentModalOpen, setIsPaymentModalOpen] = useState(false);
    const [paymentForm, setPaymentForm] = useState({
        receiving_detail_id: '',
        account_id: '',
        payment_date: new Date().toISOString().split('T')[0],
        amount: 0,
        reference_number: '',
        notes: '',
    });
    const [isSubmittingPayment, setIsSubmittingPayment] = useState(false);

    // State Modal Pilih Akun Pembelian Cash
    const [isCashModalOpen, setIsCashModalOpen] = useState(false);
    const [cashForm, setCashForm] = useState({
        receiving_detail_id: '',
        account_id: '',
        payment_date: new Date().toISOString().split('T')[0],
        reference_number: '',
        notes: '',
        invoice_number: '',
        vendor: '',
        total: 0,
    });
    const [isSubmittingCash, setIsSubmittingCash] = useState(false);

    // State Modal Tambah Akun (Persis Kledo)
    const [isAccountModalOpen, setIsAccountModalOpen] = useState(false);
    const [accountForm, setAccountForm] = useState({
        name: '',
        name_en: '',
        code: '',
        category: 'Kas & Bank',
        account_number: '',
    });
    const [isCustomCategory, setIsCustomCategory] = useState(false);
    const [customCategoryInput, setCustomCategoryInput] = useState('');
    const [isSubmittingAccount, setIsSubmittingAccount] = useState(false);

    // Mapping Kode Akun Standar Akuntansi per Kategori
    const prefixMap = {
        'Kas & Bank': '1-100',
        'Piutang Usaha': '1-102',
        'Persediaan': '1-103',
        'Aktiva Lancar Lainnya': '1-108',
        'Aktiva Tetap': '1-109',
        'Hutang Usaha': '2-201',
        'Kewajiban Lancar Lainnya': '2-202',
        'Kewajiban Jangka Panjang': '2-205',
        'Ekuitas / Modal': '3-300',
        'Pendapatan': '4-400',
        'Harga Pokok Penjualan': '5-500',
        'Beban Operasional': '6-600',
        'Beban Lainnya': '8-800',
    };

    // Helper: Hitung nomor kode berikutnya berdasarkan kategori
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

    // Daftar Semua Kategori Akun
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

    // State Edit & Hapus Akun
    const [editingAccount, setEditingAccount] = useState(null);
    const [deletingAccount, setDeletingAccount] = useState(null);
    const [isDeletingAccount, setIsDeletingAccount] = useState(false);

    // Buka Modal Tambah Akun Baru
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

    // Buka Modal Edit Akun
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

    // Eksekusi Hapus Akun
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

    // Ubah Kategori Akun & Auto-generate Kode Baru
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

    // Filter Hanya Akun Berkategori 'Kas & Bank' untuk Pembayaran (Persis Aturan Kledo)
    const kasBankAccounts = useMemo(() => {
        return accounts.filter((acc) => acc.category === 'Kas & Bank' && acc.is_active);
    }, [accounts]);

    // Statistik Ringkasan & Kalkulasi Arus Kas per Akun Kas & Bank
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

        // Hitung persentase share untuk chart per akun
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

    // Filter Mutasi Transaksi Kas & Bank (Debit / Kredit)
    const filteredMutasiKasBank = useMemo(() => {
        return (mutasiKasBank || []).filter((item) => {
            // Filter rekening
            if (selectedKasBankFilter !== 'all' && String(item.account_id) !== String(selectedKasBankFilter)) {
                return false;
            }

            // Filter jenis mutasi (IN / OUT)
            if (mutasiTypeFilter !== 'all' && item.direction !== mutasiTypeFilter) {
                return false;
            }

            // Pencarian
            if (mutasiSearchQuery.trim()) {
                const q = mutasiSearchQuery.toLowerCase().trim();
                const matchDoc = (item.document_no || '').toLowerCase().includes(q);
                const matchParty = (item.party || '').toLowerCase().includes(q);
                const matchDesc = (item.description || '').toLowerCase().includes(q);
                const matchAcc = (item.account_name || '').toLowerCase().includes(q);
                const matchUser = (item.user || '').toLowerCase().includes(q);
                if (!matchDoc && !matchParty && !matchDesc && !matchAcc && !matchUser) {
                    return false;
                }
            }

            return true;
        });
    }, [mutasiKasBank, selectedKasBankFilter, mutasiTypeFilter, mutasiSearchQuery]);

    const totalMutasiPages = Math.ceil(filteredMutasiKasBank.length / mutasiItemsPerPage) || 1;
    const paginatedMutasi = useMemo(() => {
        const start = (mutasiCurrentPage - 1) * mutasiItemsPerPage;
        return filteredMutasiKasBank.slice(start, start + mutasiItemsPerPage);
    }, [filteredMutasiKasBank, mutasiCurrentPage, mutasiItemsPerPage]);

    // Helper Format Rupiah Standar
    const formatRupiah = (num) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(num || 0);
    };

    // Helper Format Angka Bersih (sesuai format screenshot: 299.700)
    const formatNumberOnly = (num) => {
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
        }).format(num || 0);
    };

    // Master Daftar Seluruh PBF / Kreditur
    const allPbfs = useMemo(() => {
        const map = new Map();
        (creditors || []).forEach((c) => {
            if (c.name) {
                map.set(c.name.trim().toLowerCase(), { name: c.name.trim(), code: c.code || '' });
            }
        });
        hutangDagang.forEach((h) => {
            if (h.vendor && !map.has(h.vendor.trim().toLowerCase())) {
                map.set(h.vendor.trim().toLowerCase(), { name: h.vendor.trim(), code: '' });
            }
        });
        pembelianCash.forEach((c) => {
            if (c.vendor && !map.has(c.vendor.trim().toLowerCase())) {
                map.set(c.vendor.trim().toLowerCase(), { name: c.vendor.trim(), code: '' });
            }
        });
        return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name));
    }, [creditors, hutangDagang, pembelianCash]);

    // 1. Filter Data Faktur Hutang Dagang (KREDIT)
    const filteredHutang = useMemo(() => {
        return hutangDagang.filter((item) => {
            let matchesStatus = true;
            if (statusSubTab === 'Belum Dibayar') {
                matchesStatus = item.status === 'Belum Dibayar';
            } else if (statusSubTab === 'Lunas') {
                matchesStatus = item.status === 'Lunas';
            } else if (statusSubTab === 'Dibayar Sebagian') {
                matchesStatus = item.status === 'Dibayar Sebagian';
            }

            let matchesPbf = true;
            if (selectedPbf) {
                matchesPbf = (item.vendor || '').toLowerCase() === selectedPbf.toLowerCase();
            }

            const q = searchQuery.toLowerCase();
            const matchesSearch =
                (item.nomor || '').toLowerCase().includes(q) ||
                (item.vendor || '').toLowerCase().includes(q) ||
                (item.referensi || '').toLowerCase().includes(q);

            return matchesStatus && matchesPbf && matchesSearch;
        });
    }, [hutangDagang, statusSubTab, selectedPbf, searchQuery]);

    const totalPages = Math.ceil(filteredHutang.length / itemsPerPage) || 1;
    const paginatedHutang = useMemo(() => {
        const start = (currentPage - 1) * itemsPerPage;
        return filteredHutang.slice(start, start + itemsPerPage);
    }, [filteredHutang, currentPage, itemsPerPage]);

    // 2. Filter Data Pembelian Cash (TUNAI)
    const [searchCashQuery, setSearchCashQuery] = useState('');
    const [selectedCashPbf, setSelectedCashPbf] = useState('');
    const filteredCash = useMemo(() => {
        return pembelianCash.filter((item) => {
            let matchesPbf = true;
            if (selectedCashPbf) {
                matchesPbf = (item.vendor || '').toLowerCase() === selectedCashPbf.toLowerCase();
            }

            const q = searchCashQuery.toLowerCase();
            const matchesSearch =
                (item.nomor || '').toLowerCase().includes(q) ||
                (item.vendor || '').toLowerCase().includes(q) ||
                (item.referensi || '').toLowerCase().includes(q);

            return matchesPbf && matchesSearch;
        });
    }, [pembelianCash, selectedCashPbf, searchCashQuery]);

    // 3. Filter Data Piutang Penjualan (KREDIT)
    const filteredPiutang = useMemo(() => {
        return piutangPenjualan.filter((item) => {
            let matchesStatus = true;
            if (statusPiutangFilter === 'Belum Bayar' || statusPiutangFilter === 'Belum Lunas') {
                matchesStatus = item.status === 'Belum Bayar' || item.status === 'Belum Lunas';
            } else if (statusPiutangFilter === 'Lunas') {
                matchesStatus = item.status === 'Lunas';
            } else if (statusPiutangFilter === 'Dibayar Sebagian') {
                matchesStatus = item.status === 'Dibayar Sebagian';
            }

            let matchesDebtor = true;
            if (selectedDebtor) {
                matchesDebtor = (item.debtor || '').toLowerCase() === selectedDebtor.toLowerCase();
            }

            const q = searchPiutangQuery.toLowerCase().trim();
            const matchesSearch = !q ||
                (item.nomor || '').toLowerCase().includes(q) ||
                (item.debtor || '').toLowerCase().includes(q) ||
                (item.pasien || '').toLowerCase().includes(q) ||
                (item.dokter || '').toLowerCase().includes(q) ||
                (item.apotek || '').toLowerCase().includes(q);

            return matchesStatus && matchesDebtor && matchesSearch;
        });
    }, [piutangPenjualan, statusPiutangFilter, selectedDebtor, searchPiutangQuery]);

    const piutangItemsPerPage = 10;
    const totalPiutangPages = Math.ceil(filteredPiutang.length / piutangItemsPerPage) || 1;
    const paginatedPiutang = useMemo(() => {
        const start = (piutangCurrentPage - 1) * piutangItemsPerPage;
        return filteredPiutang.slice(start, start + piutangItemsPerPage);
    }, [filteredPiutang, piutangCurrentPage, piutangItemsPerPage]);

    // Statistik Ringkasan
    const stats = useMemo(() => {
        let totalHutangSisa = 0;
        let countBelumBayar = 0;
        let countLunas = 0;
        let countSebagian = 0;

        hutangDagang.forEach((item) => {
            totalHutangSisa += (item.sisa || 0);
            if (item.status === 'Belum Dibayar') countBelumBayar++;
            else if (item.status === 'Lunas') countLunas++;
            else if (item.status === 'Dibayar Sebagian') countSebagian++;
        });

        let totalCashPurchases = 0;
        pembelianCash.forEach((item) => {
            totalCashPurchases += (item.total || 0);
        });

        let totalPiutangNominal = 0;
        let totalPiutangSisa = 0;
        let totalPiutangTerbayar = 0;
        let countPiutangBelumBayar = 0;
        let countPiutangLunas = 0;
        let countPiutangSebagian = 0;

        piutangPenjualan.forEach((item) => {
            totalPiutangNominal += (item.total || 0);
            totalPiutangSisa += (item.sisa || 0);
            totalPiutangTerbayar += (item.terbayar || 0);
            if (item.status === 'Belum Bayar' || item.status === 'Belum Lunas') countPiutangBelumBayar++;
            else if (item.status === 'Lunas') countPiutangLunas++;
            else if (item.status === 'Dibayar Sebagian') countPiutangSebagian++;
        });

        return {
            totalHutangSisa,
            countBelumBayar,
            countLunas,
            countSebagian,
            totalCashPurchases,
            totalCashCount: pembelianCash.length,
            totalPiutangNominal,
            totalPiutangSisa,
            totalPiutangTerbayar,
            countPiutangBelumBayar,
            countPiutangLunas,
            countPiutangSebagian,
            totalPiutangCount: piutangPenjualan.length,
        };
    }, [hutangDagang, pembelianCash, piutangPenjualan]);

    // Data Piutang Terpilih untuk Pelunasan Massal (Maksimal 10)
    const selectedPiutangItems = useMemo(() => {
        return piutangPenjualan.filter((item) => selectedPiutangIds.includes(item.id));
    }, [piutangPenjualan, selectedPiutangIds]);

    const totalSelectedPiutangSisa = useMemo(() => {
        return selectedPiutangItems.reduce((acc, curr) => acc + (curr.sisa || 0), 0);
    }, [selectedPiutangItems]);

    // Handler Checkbox Piutang Penjualan (Kredit)
    const handleTogglePiutang = (item) => {
        if (item.status === 'Lunas' || item.sisa <= 0) return;
        if (selectedPiutangIds.includes(item.id)) {
            setSelectedPiutangIds((prev) => prev.filter((id) => id !== item.id));
        } else {
            if (selectedPiutangIds.length >= 10) {
                setSelectionWarning('Maksimal 10 transaksi piutang yang dapat dipilih sekaligus untuk pelunasan massal.');
                setTimeout(() => setSelectionWarning(''), 4000);
                return;
            }
            setSelectedPiutangIds((prev) => [...prev, item.id]);
        }
    };

    const handleSelectAllPiutang = (e) => {
        if (e.target.checked) {
            const eligible = paginatedPiutang.filter((item) => item.status !== 'Lunas' && item.sisa > 0);
            const remainingSlots = 10 - selectedPiutangIds.length;
            if (remainingSlots <= 0) {
                setSelectionWarning('Maksimal 10 tagihan piutang yang dapat dipilih sekaligus.');
                setTimeout(() => setSelectionWarning(''), 4000);
                return;
            }
            const toAdd = eligible
                .map((item) => item.id)
                .filter((id) => !selectedPiutangIds.includes(id))
                .slice(0, remainingSlots);

            if (toAdd.length < eligible.length) {
                setSelectionWarning('Hanya 10 tagihan pertama yang dipilih (batas maksimal 10 transaksi).');
                setTimeout(() => setSelectionWarning(''), 4000);
            }
            setSelectedPiutangIds((prev) => [...prev, ...toAdd]);
        } else {
            const currentPageIds = paginatedPiutang.map((item) => item.id);
            setSelectedPiutangIds((prev) => prev.filter((id) => !currentPageIds.includes(id)));
        }
    };

    // Buka Modal Bayar Piutang (Single)
    const openPiutangPaymentModal = (item) => {
        const defaultAccount = kasBankAccounts[0]?.id || '';
        setPiutangPaymentForm({
            medicine_transaction_id: item.id,
            account_id: defaultAccount,
            payment_date: new Date().toISOString().split('T')[0],
            amount: item.sisa,
            reference_number: '',
            notes: `Penerimaan piutang transaksi ${item.nomor} dari ${item.debtor}`,
            invoice_number: item.nomor,
            debtor: item.debtor,
            total: item.total,
            sisa: item.sisa,
        });
        setIsPiutangPaymentModalOpen(true);
    };

    // Submit Pembayaran Piutang (Single)
    const handlePiutangPaymentSubmit = (e) => {
        e.preventDefault();
        if (!piutangPaymentForm.account_id) {
            alert('Silakan pilih akun Kas & Bank penerima.');
            return;
        }
        if (piutangPaymentForm.amount <= 0) {
            alert('Nominal pembayaran harus lebih dari 0.');
            return;
        }
        if (piutangPaymentForm.amount > piutangPaymentForm.sisa) {
            alert(`Nominal pembayaran melebihi sisa tagihan (${formatRupiah(piutangPaymentForm.sisa)}).`);
            return;
        }

        setIsSubmittingPiutangPayment(true);
        router.post('/finance/piutang-payments', {
            medicine_transaction_id: piutangPaymentForm.medicine_transaction_id,
            account_id: piutangPaymentForm.account_id,
            payment_date: piutangPaymentForm.payment_date,
            amount: piutangPaymentForm.amount,
            reference_number: piutangPaymentForm.reference_number,
            notes: piutangPaymentForm.notes,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsPiutangPaymentModalOpen(false);
                setIsSubmittingPiutangPayment(false);
            },
            onError: (errors) => {
                setIsSubmittingPiutangPayment(false);
                alert(Object.values(errors)[0] || 'Gagal memproses pembayaran piutang.');
            }
        });
    };

    // Buka Modal Pelunasan Massal Piutang (Multi)
    const openBulkPiutangModal = () => {
        if (selectedPiutangIds.length === 0) return;
        const defaultAccount = kasBankAccounts[0]?.id || '';
        setBulkPiutangForm({
            account_id: defaultAccount,
            payment_date: new Date().toISOString().split('T')[0],
            reference_number: '',
            notes: `Pelunasan massal ${selectedPiutangIds.length} transaksi piutang penjualan`,
        });
        setIsBulkPiutangModalOpen(true);
    };

    // Submit Pelunasan Massal Piutang
    const handleBulkPiutangSubmit = (e) => {
        e.preventDefault();
        if (!bulkPiutangForm.account_id) {
            alert('Silakan pilih akun Kas & Bank penerima.');
            return;
        }

        setIsSubmittingBulkPiutang(true);
        router.post('/finance/bulk-piutang-payments', {
            transaction_ids: selectedPiutangIds,
            medicine_transaction_ids: selectedPiutangIds,
            ...bulkPiutangForm,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsBulkPiutangModalOpen(false);
                setIsSubmittingBulkPiutang(false);
                setSelectedPiutangIds([]);
            },
            onError: (errors) => {
                1
                setIsSubmittingBulkPiutang(false);
                alert(Object.values(errors)[0] || 'Gagal memproses pelunasan massal piutang.');
            }
        });
    };

    // Data Faktur Hutang Terpilih untuk Pelunasan Massal (Maksimal 10)
    const selectedHutangItems = useMemo(() => {
        return hutangDagang.filter((item) => selectedHutangIds.includes(item.id));
    }, [hutangDagang, selectedHutangIds]);

    const totalSelectedHutangSisa = useMemo(() => {
        return selectedHutangItems.reduce((acc, curr) => acc + (curr.sisa || 0), 0);
    }, [selectedHutangItems]);

    // Data Pembelian Cash Terpilih untuk Penetapan Akun Massal (Maksimal 10)
    const selectedCashItems = useMemo(() => {
        return pembelianCash.filter((item) => selectedCashIds.includes(item.id));
    }, [pembelianCash, selectedCashIds]);

    const totalSelectedCashNominal = useMemo(() => {
        return selectedCashItems.reduce((acc, curr) => acc + (curr.total || 0), 0);
    }, [selectedCashItems]);

    // Handler Checkbox Hutang Dagang (Kredit)
    const handleToggleHutang = (item) => {
        if (item.status === 'Lunas' || item.sisa <= 0) return;
        if (selectedHutangIds.includes(item.id)) {
            setSelectedHutangIds((prev) => prev.filter((id) => id !== item.id));
        } else {
            if (selectedHutangIds.length >= 10) {
                setSelectionWarning('Maksimal 10 faktur yang dapat dipilih sekaligus untuk pelunasan massal.');
                setTimeout(() => setSelectionWarning(''), 4000);
                return;
            }
            setSelectedHutangIds((prev) => [...prev, item.id]);
        }
    };

    const handleSelectAllHutang = (e) => {
        if (e.target.checked) {
            const eligible = paginatedHutang.filter((item) => item.status !== 'Lunas' && item.sisa > 0);
            const remainingSlots = 10 - selectedHutangIds.length;
            if (remainingSlots <= 0) {
                setSelectionWarning('Maksimal 10 faktur yang dapat dipilih sekaligus.');
                setTimeout(() => setSelectionWarning(''), 4000);
                return;
            }
            const toAdd = eligible
                .map((item) => item.id)
                .filter((id) => !selectedHutangIds.includes(id))
                .slice(0, remainingSlots);

            if (toAdd.length < eligible.length) {
                setSelectionWarning('Hanya 10 faktur pertama yang dipilih (batas maksimal 10 faktur).');
                setTimeout(() => setSelectionWarning(''), 4000);
            }
            setSelectedHutangIds((prev) => [...prev, ...toAdd]);
        } else {
            const currentPageIds = paginatedHutang.map((item) => item.id);
            setSelectedHutangIds((prev) => prev.filter((id) => !currentPageIds.includes(id)));
        }
    };

    // Handler Checkbox Pembelian Cash (Tunai)
    const handleToggleCash = (item) => {
        if (selectedCashIds.includes(item.id)) {
            setSelectedCashIds((prev) => prev.filter((id) => id !== item.id));
        } else {
            if (selectedCashIds.length >= 10) {
                setSelectionWarning('Maksimal 10 pembelian cash yang dapat dipilih sekaligus.');
                setTimeout(() => setSelectionWarning(''), 4000);
                return;
            }
            setSelectedCashIds((prev) => [...prev, item.id]);
        }
    };

    const handleSelectAllCash = (e) => {
        if (e.target.checked) {
            const remainingSlots = 10 - selectedCashIds.length;
            if (remainingSlots <= 0) {
                setSelectionWarning('Maksimal 10 pembelian cash yang dapat dipilih sekaligus.');
                setTimeout(() => setSelectionWarning(''), 4000);
                return;
            }
            const toAdd = filteredCash
                .map((item) => item.id)
                .filter((id) => !selectedCashIds.includes(id))
                .slice(0, remainingSlots);

            if (toAdd.length < filteredCash.length) {
                setSelectionWarning('Hanya 10 transaksi pertama yang dipilih (batas maksimal 10 item).');
                setTimeout(() => setSelectionWarning(''), 4000);
            }
            setSelectedCashIds((prev) => [...prev, ...toAdd]);
        } else {
            const currentIds = filteredCash.map((item) => item.id);
            setSelectedCashIds((prev) => prev.filter((id) => !currentIds.includes(id)));
        }
    };

    // Buka Modal Pelunasan Massal Kredit
    const openBulkPaymentModal = () => {
        if (selectedHutangIds.length === 0) return;
        const defaultAccount = kasBankAccounts[0]?.id || '';
        setBulkPaymentForm({
            account_id: defaultAccount,
            payment_date: new Date().toISOString().split('T')[0],
            reference_number: '',
            notes: `Pelunasan massal ${selectedHutangIds.length} faktur hutang dagang`,
        });
        setIsBulkPaymentModalOpen(true);
    };

    // Submit Pelunasan Massal Kredit
    const handleBulkPaymentSubmit = (e) => {
        e.preventDefault();
        if (!bulkPaymentForm.account_id) {
            alert('Silakan pilih akun Kas & Bank untuk pembayaran.');
            return;
        }

        setIsSubmittingBulkPayment(true);
        router.post('/finance/bulk-payments', {
            receiving_detail_ids: selectedHutangIds,
            ...bulkPaymentForm,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsBulkPaymentModalOpen(false);
                setIsSubmittingBulkPayment(false);
                setSelectedHutangIds([]);
            },
            onError: (errors) => {
                setIsSubmittingBulkPayment(false);
                alert(Object.values(errors)[0] || 'Gagal memproses pelunasan massal.');
            }
        });
    };

    // Buka Modal Penetapan Akun Massal Cash
    const openBulkCashModal = () => {
        if (selectedCashIds.length === 0) return;
        const defaultAccount = kasBankAccounts[0]?.id || '';
        setBulkCashForm({
            account_id: defaultAccount,
            payment_date: new Date().toISOString().split('T')[0],
            reference_number: '',
            notes: `Penetapan akun massal untuk ${selectedCashIds.length} pembelian cash`,
        });
        setIsBulkCashModalOpen(true);
    };

    // Submit Penetapan Akun Massal Cash
    const handleBulkCashSubmit = (e) => {
        e.preventDefault();
        if (!bulkCashForm.account_id) {
            alert('Silakan pilih akun Kas & Bank.');
            return;
        }

        setIsSubmittingBulkCash(true);
        router.post('/finance/bulk-cash-payment', {
            receiving_detail_ids: selectedCashIds,
            ...bulkCashForm,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsBulkCashModalOpen(false);
                setIsSubmittingBulkCash(false);
                setSelectedCashIds([]);
            },
            onError: (errors) => {
                setIsSubmittingBulkCash(false);
                alert(Object.values(errors)[0] || 'Gagal menetapkan akun pembelian cash.');
            }
        });
    };

    // Buka Modal Bayar Hutang
    const openPaymentModal = (item) => {
        const defaultAccount = kasBankAccounts[0]?.id || '';
        setPaymentForm({
            receiving_detail_id: item.id,
            account_id: defaultAccount,
            payment_date: new Date().toISOString().split('T')[0],
            amount: item.sisa,
            reference_number: '',
            notes: `Pelunasan tagihan faktur ${item.nomor} kepada ${item.vendor}`,
        });
        setIsPaymentModalOpen(true);
    };

    // Submit Pembayaran Kredit
    const handlePaymentSubmit = (e) => {
        e.preventDefault();
        if (!paymentForm.account_id) {
            alert('Pilih akun kas & bank terlebih dahulu.');
            return;
        }
        if (paymentForm.amount <= 0) {
            alert('Nominal pembayaran harus lebih dari 0.');
            return;
        }

        setIsSubmittingPayment(true);
        router.post('/finance/payments', paymentForm, {
            preserveScroll: true,
            onSuccess: () => {
                setIsPaymentModalOpen(false);
                setIsSubmittingPayment(false);
                // Update state selectedHutang jika sedang dibuka
                if (selectedHutang && selectedHutang.id === paymentForm.receiving_detail_id) {
                    setSelectedHutang(null);
                }
            },
            onError: (errors) => {
                setIsSubmittingPayment(false);
                alert(Object.values(errors)[0] || 'Gagal memproses pembayaran.');
            }
        });
    };

    // Buka Modal Pilih Akun Pembelian Cash
    const openCashModal = (item) => {
        const defaultAccount = item.akunPembayaran?.id || kasBankAccounts[0]?.id || '';
        setCashForm({
            receiving_detail_id: item.id,
            account_id: defaultAccount,
            payment_date: new Date().toISOString().split('T')[0],
            reference_number: item.akunPembayaran?.noReferensi || '',
            notes: `Pembelian Cash via transfer/tunai`,
            invoice_number: item.nomor,
            vendor: item.vendor,
            total: item.total,
        });
        setIsCashModalOpen(true);
    };

    // Submit Akun Pembelian Cash
    const handleCashSubmit = (e) => {
        e.preventDefault();
        if (!cashForm.account_id) {
            alert('Pilih akun kas & bank yang digunakan.');
            return;
        }

        setIsSubmittingCash(true);
        router.post('/finance/cash-payment', cashForm, {
            preserveScroll: true,
            onSuccess: () => {
                setIsCashModalOpen(false);
                setIsSubmittingCash(false);
            },
            onError: (errors) => {
                setIsSubmittingCash(false);
                alert(Object.values(errors)[0] || 'Gagal menyimpan akun pembayaran.');
            }
        });
    };

    // Submit Tambah / Edit Akun
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

    // Hitung total kuantitas obat pada faktur terpilih
    const totalSelectedQty = selectedHutang?.items?.reduce((acc, curr) => acc + curr.qty, 0) || 0;
    const totalCashSelectedQty = selectedCash?.items?.reduce((acc, curr) => acc + (curr.qty || 0), 0) || 0;
    const totalPiutangSelectedQty = selectedPiutang?.items?.reduce((acc, curr) => acc + (curr.qty || 0), 0) || 0;

    return (
        <div className="flex h-screen bg-[#F8FAFC] font-sans antialiased text-slate-800">
            <Head title="SAHABAT Finances - ERP Keuangan Apotek" />

            {/* ======================================================== */}
            {/* 1. SIDEBAR NAVIGASI KIRI                                 */}
            {/* ======================================================== */}
            <aside className="w-64 bg-white border-r border-slate-200 flex flex-col shrink-0">
                {/* Brand Header */}
                <div className="h-16 flex items-center px-6 border-b border-slate-100 gap-1">
                    <div className="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-base shadow-sm">
                        <img src="/img/sahabat-mascot.png" alt="Logo" className="w-8 h-8 object-contain" />
                    </div>
                    <div>
                        <div className="font-bold text-slate-900 text-sm tracking-tight flex items-center gap-1.5">
                            SAHABAT Finances
                        </div>
                        <div className="text-[10px] text-slate-400 font-medium -mt-0.5">
                            Manajemen Keuangan Apotek
                        </div>
                    </div>
                </div>

                {/* Navigasi Menu */}
                <nav className="flex-1 p-4 space-y-1.5 overflow-y-auto text-xs font-medium">
                    {/* Tombol Navigasi Kembali ke Dashboard Utama */}
                    <a
                        href="/home"
                        className="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-slate-700 hover:text-blue-700 bg-slate-100/80 hover:bg-blue-50/80 rounded-xl transition border border-slate-200/70 mb-3 group"
                        title="Kembali ke Dashboard Utama Apotek (/home)"
                    >
                        <ArrowLeft className="w-4 h-4 text-slate-500 group-hover:text-blue-600 transition" />
                        <span>Menu Dashboard Utama</span>
                    </a>

                    <div className="pb-1 px-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                        Utama
                    </div>

                    {/* Beranda Eksekutif */}
                    <button
                        onClick={() => { setActiveTab('beranda'); setSelectedHutang(null); setSelectedCash(null); setSelectedPiutang(null); }}
                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl transition text-left ${activeTab === 'beranda'
                            ? 'bg-blue-50 text-blue-600 font-semibold'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                            }`}
                    >
                        <LayoutDashboard className="w-4 h-4" />
                        <span>Beranda</span>
                    </button>

                    <div className="pt-3 pb-1 px-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                        Transaksi Pembelian
                    </div>

                    {/* Hutang Dagang (KREDIT) */}
                    <button
                        onClick={() => { setActiveTab('hutang'); setSelectedHutang(null); setSelectedCash(null); }}
                        className={`w-full flex items-center justify-between px-3 py-2.5 rounded-xl transition text-left ${activeTab === 'hutang'
                            ? 'bg-blue-50 text-blue-600 font-semibold'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                            }`}
                    >
                        <div className="flex items-center gap-3">
                            <ShoppingBag className="w-4 h-4" />
                            <span>Hutang Dagang (Kredit)</span>
                        </div>
                        {stats.countBelumBayar > 0 && (
                            <span className="text-[11px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-600 font-bold">
                                {stats.countBelumBayar}
                            </span>
                        )}
                    </button>

                    {/* Pembelian Cash (TUNAI) */}
                    <button
                        onClick={() => { setActiveTab('cash'); setSelectedHutang(null); setSelectedCash(null); }}
                        className={`w-full flex items-center justify-between px-3 py-2.5 rounded-xl transition text-left ${activeTab === 'cash'
                            ? 'bg-blue-50 text-blue-600 font-semibold'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                            }`}
                    >
                        <div className="flex items-center gap-3">
                            <Banknote className="w-4 h-4" />
                            <span>Pembelian Cash</span>
                        </div>
                        <span className="text-[11px] px-1.5 py-0.5 rounded-full bg-emerald-50 text-emerald-600 font-medium">
                            {stats.totalCashCount}
                        </span>
                    </button>

                    {/* Konsinyasi (DEFERRED) */}
                    <button
                        onClick={() => { setActiveTab('konsinyasi'); setSelectedHutang(null); setSelectedCash(null); }}
                        className={`w-full flex items-center justify-between px-3 py-2.5 rounded-xl transition text-left ${activeTab === 'konsinyasi'
                            ? 'bg-blue-50 text-blue-600 font-semibold'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                            }`}
                    >
                        <div className="flex items-center gap-3">
                            <ArrowRightLeft className="w-4 h-4" />
                            <span>Konsinyasi</span>
                        </div>
                        <span className="text-[10px] px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-500 font-normal">
                            Coming Soon
                        </span>
                    </button>

                    <div className="pt-3 pb-1 px-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                        Keuangan & Penjualan
                    </div>

                    {/* Kas & Bank (CHART OF ACCOUNTS ALA KLEDO) */}
                    <button
                        onClick={() => { setActiveTab('kas_bank'); setSelectedHutang(null); setSelectedCash(null); setSelectedPiutang(null); }}
                        className={`w-full flex items-center justify-between px-3 py-2.5 rounded-xl transition text-left ${activeTab === 'kas_bank'
                            ? 'bg-blue-50 text-blue-600 font-semibold'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                            }`}
                    >
                        <div className="flex items-center gap-3">
                            <Landmark className="w-4 h-4" />
                            <span>Akun</span>
                        </div>
                        <span className="text-[11px] px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700 font-semibold">
                            {accounts.length}
                        </span>
                    </button>

                    <button onClick={() => { setActiveTab('kas_bank'); setSelectedHutang(null); setSelectedCash(null); setSelectedPiutang(null); }}

                        className='w-full flex items-center justify-between px-3 py-2.5 rounded-xl transition text-left'>
                        <div className='flex items-center gap-3'>
                            <Scale className='w-4 h-4' />
                            <span>Kas & Bank</span>
                        </div>
                    </button>
                    {/* Piutang (KREDIT) */}
                    <button
                        onClick={() => { setActiveTab('piutang'); setSelectedHutang(null); setSelectedCash(null); setSelectedPiutang(null); }}
                        className={`w-full flex items-center justify-between px-3 py-2.5 rounded-xl transition text-left ${activeTab === 'piutang'
                            ? 'bg-blue-50 text-blue-600 font-semibold'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                            }`}
                    >
                        <div className="flex items-center gap-3">
                            <ShoppingCart className="w-4 h-4" />
                            <span>Piutang</span>
                        </div>
                        {stats.countPiutangBelumBayar > 0 ? (
                            <span className="text-[11px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 font-semibold">
                                {stats.countPiutangBelumBayar}
                            </span>
                        ) : (
                            <span className="text-[11px] px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500 font-medium">
                                {stats.totalPiutangCount}
                            </span>
                        )}
                    </button>

                    {/* Biaya (Placeholder) */}
                    <button
                        onClick={() => { setActiveTab('biaya'); setSelectedHutang(null); setSelectedCash(null); setSelectedPiutang(null); }}
                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl transition text-left ${activeTab === 'biaya'
                            ? 'bg-blue-50 text-blue-600 font-semibold'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                            }`}
                    >
                        <Receipt className="w-4 h-4" />
                        <span>Biaya</span>
                    </button>
                </nav>

                {/* User Info Footer */}
                <div className="p-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div className="flex items-center gap-2.5 overflow-hidden">
                        <div className="w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center shrink-0 text-xs">
                            {auth?.user?.name ? auth.user.name.charAt(0).toUpperCase() : 'F'}
                        </div>
                        <div className="truncate">
                            <div className="text-xs font-semibold text-slate-800 truncate">
                                {auth?.user?.name || auth?.user?.username || 'Finance Staff'}
                            </div>
                            <div className="text-[10px] text-slate-500 truncate">
                                {auth?.user?.role || 'Finance'}
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-1 shrink-0">
                        <a
                            href="/home"
                            className="text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition p-1.5 rounded-lg"
                            title="Kembali ke Dashboard Utama (/home)"
                        >
                            <ArrowLeft className="w-4 h-4" />
                        </a>

                    </div>
                </div>
            </aside>

            {/* ======================================================== */}
            {/* 2. AREA KONTEN UTAMA                                     */}
            {/* ======================================================== */}
            <div className="flex-1 flex flex-col overflow-hidden">
                {/* Header Atas */}
                <header className="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-8 shrink-0">
                    <div className="flex items-center gap-3">
                        <a
                            href="/home"
                            className="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:text-blue-600 hover:bg-slate-100 rounded-lg transition border border-slate-200/90 shadow-2xs"
                            title="Kembali ke Dashboard Utama (/home)"
                        >
                            <ArrowLeft className="w-3.5 h-3.5 text-slate-500" />
                            <span>Dashboard Utama</span>
                        </a>
                        <span className="text-slate-300">/</span>
                        <span className="text-xs text-slate-400 font-medium">Modul Keuangan Apotek</span>
                        <span className="text-slate-300">/</span>
                        <span className="text-xs font-semibold text-slate-700 capitalize">
                            {activeTab === 'beranda' && 'Beranda Eksekutif'}
                            {activeTab === 'hutang' && (selectedHutang ? `Detil Tagihan #${selectedHutang.nomor}` : 'Hutang Dagang (Kredit)')}
                            {activeTab === 'cash' && (selectedCash ? `Detil Pembelian Cash #${selectedCash.nomor}` : 'Pembelian Tunai (Cash)')}
                            {activeTab === 'konsinyasi' && 'Konsinyasi'}
                            {activeTab === 'kas_bank' && 'Kas & Bank (Bagan Akun Kledo)'}
                            {activeTab === 'piutang' && (selectedPiutang ? `Detil Tagihan Piutang #${selectedPiutang.nomor}` : 'Piutang Penjualan (Kredit)')}
                            {activeTab === 'biaya' && 'Beban & Biaya'}
                        </span>
                    </div>

                    <div className="flex items-center gap-3">
                        {/* Tombol Cepat Tambah Akun jika di tab Kas & Bank */}
                        {activeTab === 'kas_bank' && (
                            <button
                                onClick={() => openAccountModal('Kas & Bank')}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm"
                            >
                                <Plus className="w-3.5 h-3.5" />
                                <span>Tambah Akun</span>
                            </button>
                        )}

                        <div className="h-4 w-px bg-slate-200 mx-1"></div>

                        <span className="text-xs font-medium text-slate-600 flex items-center gap-2">
                            <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            {auth?.user?.name || 'Staf Finance'}
                        </span>
                    </div>
                </header>

                {/* Konten Tab Aktif */}
                <div className="flex-1 overflow-y-auto p-8">
                    {/* Flash Success Message */}
                    {flash?.success && (
                        <div className="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center justify-between shadow-xs">
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                                <span>{flash.success}</span>
                            </div>
                        </div>
                    )}

                    {/* ==================================================== */}
                    {/* TAB 1: BERANDA EKSEKUTIF                             */}
                    {/* ==================================================== */}
                    {activeTab === 'beranda' && (
                        <div className="space-y-8 max-w-7xl mx-auto">
                            <div>
                                <h1 className="text-2xl font-bold text-slate-900 tracking-tight">
                                    Halo, {auth?.user?.name || 'Tim Keuangan'} 👋
                                </h1>
                                <p className="text-xs text-slate-500 mt-1">
                                    Ringkasan finansial, hutang dagang tempo, pembelian cash, dan rekening bank.
                                </p>
                            </div>

                            {/* Stat Cards Grid */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                                {/* Total Hutang Dagang Sisa */}
                                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                                    <div className="flex items-center justify-between mb-3">
                                        <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">Hutang Dagang</span>
                                        <div className="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center">
                                            <ShoppingBag className="w-4 h-4" />
                                        </div>
                                    </div>
                                    <div className="text-lg font-bold text-slate-900 truncate">
                                        {formatRupiah(stats.totalHutangSisa)}
                                    </div>
                                    <div className="text-[11px] text-red-500 font-medium mt-1">
                                        {stats.countBelumBayar} faktur belum lunas
                                    </div>
                                </div>

                                {/* Total Pembelian Cash */}
                                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                                    <div className="flex items-center justify-between mb-3">
                                        <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">Pembelian Cash</span>
                                        <div className="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                            <Banknote className="w-4 h-4" />
                                        </div>
                                    </div>
                                    <div className="text-lg font-bold text-slate-900 truncate">
                                        {formatRupiah(stats.totalCashPurchases)}
                                    </div>
                                    <div className="text-[11px] text-emerald-600 font-medium mt-1">
                                        {stats.totalCashCount} transaksi cash
                                    </div>
                                </div>

                                {/* Total Piutang Penjualan */}
                                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                                    <div className="flex items-center justify-between mb-3">
                                        <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">Piutang Penjualan</span>
                                        <div className="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                                            <ShoppingCart className="w-4 h-4" />
                                        </div>
                                    </div>
                                    <div className="text-lg font-bold text-slate-900 truncate">
                                        {formatRupiah(stats.totalPiutangSisa)}
                                    </div>
                                    <div className="text-[11px] text-amber-600 font-medium mt-1">
                                        {stats.countPiutangBelumBayar} tagihan belum lunas
                                    </div>
                                </div>

                                {/* Faktur Kredit Lunas */}
                                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                                    <div className="flex items-center justify-between mb-3">
                                        <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">Kredit Lunas</span>
                                        <div className="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                                            <CheckCircle2 className="w-4 h-4" />
                                        </div>
                                    </div>
                                    <div className="text-lg font-bold text-slate-900">
                                        {stats.countLunas} Faktur
                                    </div>
                                    <div className="text-[11px] text-blue-600 font-medium mt-1">
                                        {stats.countSebagian} bayar bertahap
                                    </div>
                                </div>

                                {/* Rekening Bank Aktif */}
                                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                                    <div className="flex items-center justify-between mb-3">
                                        <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">Kas & Bank</span>
                                        <div className="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center">
                                            <Landmark className="w-4 h-4" />
                                        </div>
                                    </div>
                                    <div className="text-lg font-bold text-slate-900">
                                        {kasBankAccounts.length} Akun
                                    </div>
                                    <div className="text-[11px] text-purple-600 font-medium mt-1 truncate">
                                        Kas & Rekening Bank
                                    </div>
                                </div>
                            </div>

                            {/* Shortcut Navigasi Cepat */}
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div className="bg-white p-6 rounded-2xl border border-slate-200 space-y-4">
                                    <div className="flex items-center justify-between">
                                        <h3 className="font-bold text-slate-900 text-sm">Hutang Dagang Tempo (Kredit)</h3>
                                        <button
                                            onClick={() => { setActiveTab('hutang'); setSelectedHutang(null); setSelectedCash(null); setSelectedPiutang(null); }}
                                            className="text-xs text-blue-600 hover:underline font-semibold"
                                        >
                                            Kelola Tagihan &rarr;
                                        </button>
                                    </div>
                                    <p className="text-xs text-slate-500 leading-relaxed">
                                        Kelola faktur pembelian dari PBF dengan tempo kredit. Catat pelunasan bertahap/lunas lengkap dengan audit trail Kas & Bank.
                                    </p>
                                    <button
                                        onClick={() => { setActiveTab('hutang'); setSelectedHutang(null); setSelectedCash(null); setSelectedPiutang(null); }}
                                        className="w-full py-2.5 px-4 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold text-center transition"
                                    >
                                        Buka Hutang Dagang
                                    </button>
                                </div>

                                <div className="bg-white p-6 rounded-2xl border border-slate-200 space-y-4">
                                    <div className="flex items-center justify-between">
                                        <h3 className="font-bold text-slate-900 text-sm">Pembelian Tunai (Cash)</h3>
                                        <button
                                            onClick={() => { setActiveTab('cash'); setSelectedHutang(null); setSelectedCash(null); setSelectedPiutang(null); }}
                                            className="text-xs text-emerald-600 hover:underline font-semibold"
                                        >
                                            Kelola Pembelian Cash &rarr;
                                        </button>
                                    </div>
                                    <p className="text-xs text-slate-500 leading-relaxed">
                                        Catat dan kelola faktur tunai serta tetapkan rekening kas & bank (BCA, Mandiri, BNI, BTN, Kasir) yang dipakai.
                                    </p>
                                    <button
                                        onClick={() => { setActiveTab('cash'); setSelectedHutang(null); setSelectedCash(null); setSelectedPiutang(null); }}
                                        className="w-full py-2.5 px-4 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold text-center transition"
                                    >
                                        Buka Pembelian Cash
                                    </button>
                                </div>

                                <div className="bg-white p-6 rounded-2xl border border-slate-200 space-y-4">
                                    <div className="flex items-center justify-between">
                                        <h3 className="font-bold text-slate-900 text-sm">Piutang Penjualan (Kredit)</h3>
                                        <button
                                            onClick={() => { setActiveTab('piutang'); setSelectedHutang(null); setSelectedCash(null); setSelectedPiutang(null); }}
                                            className="text-xs text-amber-600 hover:underline font-semibold"
                                        >
                                            Kelola Piutang &rarr;
                                        </button>
                                    </div>
                                    <p className="text-xs text-slate-500 leading-relaxed">
                                        Kelola tagihan penjualan tempo, resep kredit pelanggan/instansi/debitur. Terima pembayaran dan pelunasan massal.
                                    </p>
                                    <button
                                        onClick={() => { setActiveTab('piutang'); setSelectedHutang(null); setSelectedCash(null); setSelectedPiutang(null); }}
                                        className="w-full py-2.5 px-4 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold text-center transition"
                                    >
                                        Buka Piutang Penjualan
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* ==================================================== */}
                    {/* TAB 2: HUTANG DAGANG (KREDIT)                        */}
                    {/* ==================================================== */}
                    {activeTab === 'hutang' && (
                        <>
                            {selectedHutang ? (
                                /* SUB-VIEW A: DETIL TAGIHAN HUTANG DAGANG (PERSIS SCREENSHOT) */
                                <div className="space-y-6 max-w-[1300px] mx-auto">
                                    {/* Link Kembali ke Daftar */}
                                    <button
                                        onClick={() => setSelectedHutang(null)}
                                        className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-800 font-medium transition"
                                    >
                                        <ArrowLeft className="w-3.5 h-3.5" />
                                        <span>Kembali ke Daftar Tagihan</span>
                                    </button>

                                    {/* Judul & Action Kanan */}
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                                            Detil Tagihan Hutang Dagang {selectedHutang.nomor}
                                        </h1>

                                        <div className="flex items-center gap-2">
                                            {selectedHutang.sisa > 0 && (
                                                <button
                                                    onClick={() => openPaymentModal(selectedHutang)}
                                                    className="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm"
                                                >
                                                    <CreditCard className="w-3.5 h-3.5" />
                                                    <span>Bayar Tagihan</span>
                                                </button>
                                            )}

                                            <button
                                                onClick={() => window.print()}
                                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition"
                                            >
                                                <Printer className="w-3.5 h-3.5 text-slate-500" />
                                                <span>Print</span>
                                            </button>
                                        </div>
                                    </div>

                                    {/* KARTU UTAMA DETIL FAKTUR */}
                                    <div className="bg-white rounded-2xl border border-slate-200 p-8 shadow-xs space-y-6">
                                        {/* Status Badge */}
                                        <div className="flex items-center justify-between">
                                            <span
                                                className={`inline-block px-3 py-1 text-xs font-semibold rounded-full ${selectedHutang.status === 'Lunas'
                                                    ? 'bg-emerald-50 text-emerald-600'
                                                    : selectedHutang.status === 'Dibayar Sebagian'
                                                        ? 'bg-amber-50 text-amber-600'
                                                        : 'bg-red-50 text-red-600'
                                                    }`}
                                            >
                                                {selectedHutang.status}
                                            </span>

                                            <span className="text-xs text-slate-400">
                                                Jenis Pembayaran: <strong className="text-slate-700 font-semibold">KREDIT (Tempo)</strong>
                                            </span>
                                        </div>

                                        {/* Metadata Faktur (2 Kolom) */}
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 text-xs">
                                            <div className="space-y-4">
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">PBF</span>
                                                    <span className="text-blue-600 font-semibold text-sm">
                                                        {selectedHutang.vendor}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">Tgl. Transaksi</span>
                                                    <span className="font-semibold text-slate-800 text-sm">{selectedHutang.tanggal}</span>
                                                </div>
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">Apotek Penerima</span>
                                                    <span className="text-blue-600 font-medium">
                                                        {selectedHutang.gudang}
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="space-y-4">
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">Nomor</span>
                                                    <span className="font-bold text-slate-900 text-sm">{selectedHutang.nomor}</span>
                                                </div>
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">Tgl. Jatuh Tempo</span>
                                                    <span className="font-semibold text-slate-800 text-sm">{selectedHutang.jatuhTempo}</span>
                                                </div>
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">Referensi</span>
                                                    <span className="font-medium text-slate-800">{selectedHutang.referensi}</span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Informasi Pengiriman */}
                                        <div className="pt-2 border-t border-slate-100">
                                            <button
                                                onClick={() => setIsShippingOpen(!isShippingOpen)}
                                                className="flex items-center gap-1.5 text-xs font-semibold text-slate-700 hover:text-slate-900"
                                            >
                                                {isShippingOpen ? <ChevronDown className="w-3.5 h-3.5" /> : <ChevronRight className="w-3.5 h-3.5" />}
                                                <span>Informasi pengiriman</span>
                                            </button>
                                            {isShippingOpen && (
                                                <div className="pl-5 pt-3 text-xs">
                                                    <span className="text-slate-400 block text-[11px]">Tanggal Pengiriman</span>
                                                    <span className="font-medium text-slate-800">{selectedHutang.tglKirim || selectedHutang.tanggal}</span>
                                                </div>
                                            )}
                                        </div>

                                        {/* Tabel Item Obat */}
                                        <div className="pt-4 overflow-x-auto">
                                            <table className="w-full text-left text-xs">
                                                <thead className="text-slate-400 border-b border-slate-100 pb-2 text-[11px]">
                                                    <tr>
                                                        <th className="py-2.5 font-normal">Produk</th>
                                                        <th className="py-2.5 font-normal">Deskripsi</th>
                                                        <th className="py-2.5 font-normal text-center">Kuantitas</th>
                                                        <th className="py-2.5 font-normal">Satuan</th>
                                                        <th className="py-2.5 font-normal text-center">Discount</th>
                                                        <th className="py-2.5 font-normal text-right">Harga</th>
                                                        <th className="py-2.5 font-normal text-center">Pajak</th>
                                                        <th className="py-2.5 font-normal text-right">Jumlah</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-100 text-slate-700">
                                                    {selectedHutang.items && selectedHutang.items.length > 0 ? (
                                                        selectedHutang.items.map((it, idx) => (
                                                            <tr key={idx}>
                                                                <td className="py-3">
                                                                    <span className="text-blue-600 font-medium">
                                                                        {it.sku} - {it.nama}
                                                                    </span>
                                                                </td>
                                                                <td className="py-3 text-slate-400">-</td>
                                                                <td className="py-3 text-center font-medium">{it.qty}</td>
                                                                <td className="py-3">{it.satuan}</td>
                                                                <td className="py-3 text-center">{it.diskon}</td>
                                                                <td className="py-3 text-right">{formatNumberOnly(it.harga)}</td>
                                                                <td className="py-3 text-center text-slate-500">{it.pajak}</td>
                                                                <td className="py-3 text-right font-semibold text-slate-900">
                                                                    {formatNumberOnly(it.jumlah)}
                                                                </td>
                                                            </tr>
                                                        ))
                                                    ) : (
                                                        <tr>
                                                            <td colSpan={8} className="py-6 text-center text-slate-400">
                                                                Tidak ada rincian item.
                                                            </td>
                                                        </tr>
                                                    )}
                                                </tbody>
                                                <tfoot className="border-t border-slate-200">
                                                    <tr>
                                                        <td colSpan={2} className="py-3 text-right font-semibold text-slate-600">
                                                            Total Kuantitas
                                                        </td>
                                                        <td className="py-3 text-center font-bold text-slate-900">{totalSelectedQty}</td>
                                                        <td colSpan={5}></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>

                                        {/* Summary Bawah */}
                                        <div className="pt-6 border-t border-slate-100 flex justify-end">
                                            <div className="w-80 space-y-2 text-xs">
                                                <div className="flex justify-between text-slate-600">
                                                    <span>Sub Total</span>
                                                    <span className="font-medium text-slate-800">{formatNumberOnly(selectedHutang.subtotal)}</span>
                                                </div>
                                                <div className="flex justify-between text-slate-600">
                                                    <span>PPN11</span>
                                                    <span className="font-medium text-slate-800">{formatNumberOnly(selectedHutang.ppn)}</span>
                                                </div>
                                                <div className="flex justify-between text-slate-800 font-bold pt-1">
                                                    <span>Total</span>
                                                    <span>{formatNumberOnly(selectedHutang.total)}</span>
                                                </div>

                                                {selectedHutang.terbayar > 0 && (
                                                    <div className="flex justify-between text-emerald-600 font-semibold pt-1">
                                                        <span>Sudah Dibayar</span>
                                                        <span>- {formatNumberOnly(selectedHutang.terbayar)}</span>
                                                    </div>
                                                )}

                                                <div className="bg-slate-100 p-3.5 rounded-xl flex justify-between items-center text-slate-900 font-bold mt-3">
                                                    <span className="text-xs">Sisa Tagihan</span>
                                                    <span className="text-sm">{formatNumberOnly(selectedHutang.sisa)}</span>
                                                </div>

                                                {selectedHutang.sisa > 0 && (
                                                    <button
                                                        onClick={() => openPaymentModal(selectedHutang)}
                                                        className="w-full mt-2 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold transition flex items-center justify-center gap-2 shadow-sm"
                                                    >
                                                        <CreditCard className="w-4 h-4" />
                                                        <span>Bayar Tagihan Sekarang</span>
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {/* KARTU AUDIT TRAIL & RIWAYAT PEMBAYARAN */}
                                    <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                                        <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                                            <div className="flex items-center gap-2.5">
                                                <History className="w-5 h-5 text-blue-600" />
                                                <div>
                                                    <h3 className="font-bold text-slate-900 text-sm">
                                                        Riwayat Pembayaran & Audit Trail
                                                    </h3>
                                                    <p className="text-[11px] text-slate-400">
                                                        Catatan audit pencatatan pembayaran faktur kredit, akun bank, dan nama staf pemroses.
                                                    </p>
                                                </div>
                                            </div>

                                            <span className="text-xs font-semibold text-slate-600">
                                                Total Masuk: <strong className="text-emerald-600">{formatRupiah(selectedHutang.terbayar)}</strong>
                                            </span>
                                        </div>

                                        {selectedHutang.payments && selectedHutang.payments.length > 0 ? (
                                            <div className="overflow-x-auto">
                                                <table className="w-full text-left text-xs">
                                                    <thead className="text-slate-400 bg-slate-50/50 border-b border-slate-100">
                                                        <tr>
                                                            <th className="py-2.5 px-3 font-medium">Tanggal Bayar</th>
                                                            <th className="py-2.5 px-3 font-medium">Akun Kas & Bank</th>
                                                            <th className="py-2.5 px-3 font-medium">No. Referensi / Bukti</th>
                                                            <th className="py-2.5 px-3 font-medium text-right">Nominal Bayar</th>
                                                            <th className="py-2.5 px-3 font-medium">Diproses Oleh</th>
                                                            <th className="py-2.5 px-3 font-medium">Waktu Audit</th>
                                                            <th className="py-2.5 px-3 font-medium">Catatan</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-100 text-slate-700">
                                                        {selectedHutang.payments.map((pay) => (
                                                            <tr key={pay.id} className="hover:bg-slate-50/50">
                                                                <td className="py-3 px-3 font-medium text-slate-900">{pay.tanggal}</td>
                                                                <td className="py-3 px-3">
                                                                    <div className="flex items-center gap-1.5">
                                                                        <Landmark className="w-3.5 h-3.5 text-blue-500" />
                                                                        <span className="font-semibold text-slate-800">{pay.akunNama}</span>
                                                                        <span className="text-[10px] text-slate-400">({pay.akunKode})</span>
                                                                    </div>
                                                                </td>
                                                                <td className="py-3 px-3 font-mono text-[11px] text-slate-600">{pay.noReferensi}</td>
                                                                <td className="py-3 px-3 text-right font-bold text-emerald-600">
                                                                    {formatRupiah(pay.nominal)}
                                                                </td>
                                                                <td className="py-3 px-3 font-medium text-slate-800">{pay.diprosesOleh}</td>
                                                                <td className="py-3 px-3 text-slate-500 text-[11px]">{pay.waktuAudit}</td>
                                                                <td className="py-3 px-3 text-slate-500 text-[11px]">{pay.catatan}</td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <div className="py-8 text-center text-slate-400 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                                                <History className="w-8 h-8 mx-auto text-slate-300 mb-2" />
                                                <p className="text-xs font-medium text-slate-600">Belum ada pembayaran yang dicatat untuk faktur ini.</p>
                                                <p className="text-[11px] text-slate-400 mt-0.5">
                                                    Gunakan tombol <strong>"Bayar Tagihan"</strong> untuk mencatat pelunasan bertahap atau lunas.
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                /* SUB-VIEW B: TABEL DAFTAR FAKTUR HUTANG DAGANG */
                                <div className="space-y-4 max-w-[1400px] mx-auto">
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <div>
                                            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Tagihan Hutang Dagang</h1>
                                            <p className="text-xs text-slate-500 mt-0.5">
                                                Faktur pembelian tempo (kredit) dari PBF / Vendor.
                                            </p>
                                        </div>

                                        <div className="flex items-center gap-2 flex-wrap">
                                            <button className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                                                <Filter className="w-3.5 h-3.5 text-slate-500" />
                                                <span>Filter</span>
                                            </button>
                                            <button className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                                                <Download className="w-3.5 h-3.5 text-slate-500" />
                                                <span>Ekspor</span>
                                            </button>
                                        </div>
                                    </div>

                                    {/* Tabs Filter Status */}
                                    <div className="flex items-center border-b border-slate-200 text-xs font-semibold">
                                        {[
                                            { label: 'Semua', count: hutangDagang.length },
                                            { label: 'Belum Dibayar', count: stats.countBelumBayar },
                                            { label: 'Dibayar Sebagian', count: stats.countSebagian },
                                            { label: 'Lunas', count: stats.countLunas },
                                        ].map((t) => (
                                            <button
                                                key={t.label}
                                                onClick={() => {
                                                    setStatusSubTab(t.label);
                                                    setCurrentPage(1);
                                                }}
                                                className={`pb-3 px-4 border-b-2 transition flex items-center gap-2 ${statusSubTab === t.label
                                                    ? 'border-blue-600 text-blue-600 font-bold'
                                                    : 'border-transparent text-slate-500 hover:text-slate-800'
                                                    }`}
                                            >
                                                <span>{t.label}</span>
                                                {t.count > 0 && (
                                                    <span className={`text-[10px] px-1.5 py-0.2 rounded-full ${statusSubTab === t.label ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600'}`}>
                                                        {t.count}
                                                    </span>
                                                )}
                                            </button>
                                        ))}
                                    </div>

                                    {/* Search & Filter Bar */}
                                    <div className="bg-white p-3.5 rounded-2xl border border-slate-200 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 shadow-xs">
                                        <div className="flex flex-col sm:flex-row items-center gap-2.5 flex-1">
                                            <div className="relative w-full sm:w-72">
                                                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                                <input
                                                    type="text"
                                                    value={searchQuery}
                                                    onChange={(e) => {
                                                        setSearchQuery(e.target.value);
                                                        setCurrentPage(1);
                                                    }}
                                                    placeholder="Cari nomor faktur, referensi..."
                                                    className="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"
                                                />
                                            </div>

                                            {/* Autocomplete PBF Combobox */}
                                            <PbfCombobox
                                                pbfs={allPbfs}
                                                selectedPbf={selectedPbf}
                                                onSelectPbf={(pbf) => {
                                                    setSelectedPbf(pbf);
                                                    setCurrentPage(1);
                                                }}
                                                placeholder="Filter PBF / Kreditur..."
                                            />
                                        </div>

                                        <div className="text-xs text-slate-500 shrink-0">
                                            Total Sisa Hutang: <strong className="text-red-600 font-bold">{formatRupiah(stats.totalHutangSisa)}</strong>
                                        </div>
                                    </div>

                                    {/* Active Filter Indicator Badge */}
                                    {selectedPbf && (
                                        <div className="flex items-center gap-2 text-xs animate-in fade-in">
                                            <span className="text-slate-400 font-medium">Filter Aktif:</span>
                                            <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-full font-semibold">
                                                <Building2 className="w-3.5 h-3.5 text-blue-600" />
                                                <span>PBF: {selectedPbf}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => setSelectedPbf('')}
                                                    className="hover:bg-blue-200/60 p-0.5 rounded-full transition ml-0.5"
                                                    title="Hapus filter PBF"
                                                >
                                                    <X className="w-3 h-3" />
                                                </button>
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => setSelectedPbf('')}
                                                className="text-[11px] text-slate-500 hover:text-red-600 underline font-medium"
                                            >
                                                Tampilkan Semua PBF
                                            </button>
                                        </div>
                                    )}

                                    {/* Tabel Hutang Dagang */}
                                    <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-xs">
                                                <thead className="bg-[#F8FAFC] border-b border-slate-200 text-slate-600 font-semibold">
                                                    <tr>
                                                        <th className="w-10 px-4 py-3.5">
                                                            <input
                                                                type="checkbox"
                                                                checked={
                                                                    paginatedHutang.filter(i => i.status !== 'Lunas' && i.sisa > 0).length > 0 &&
                                                                    paginatedHutang
                                                                        .filter(i => i.status !== 'Lunas' && i.sisa > 0)
                                                                        .every(i => selectedHutangIds.includes(i.id))
                                                                }
                                                                onChange={handleSelectAllHutang}
                                                                className="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                                title="Pilih semua faktur belum lunas di halaman ini (maks. 10)"
                                                            />
                                                        </th>
                                                        <th className="px-4 py-3.5">Nomor</th>
                                                        <th className="px-4 py-3.5">Vendor (PBF)</th>
                                                        <th className="px-4 py-3.5">Referensi</th>
                                                        <th className="px-4 py-3.5">Tanggal</th>
                                                        <th className="px-4 py-3.5">Jatuh Tempo</th>
                                                        <th className="px-4 py-3.5">Status</th>
                                                        <th className="px-4 py-3.5 text-right">Sisa Tagihan (Rp)</th>
                                                        <th className="px-4 py-3.5 text-center">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-100">
                                                    {paginatedHutang.length === 0 ? (
                                                        <tr>
                                                            <td colSpan={9} className="px-4 py-8 text-center text-slate-400">
                                                                Tidak ada data tagihan yang sesuai.
                                                            </td>
                                                        </tr>
                                                    ) : (
                                                        paginatedHutang.map((item) => (
                                                            <tr key={item.id} className="hover:bg-slate-50/70 transition">
                                                                <td className="px-4 py-3.5">
                                                                    {item.status === 'Lunas' || item.sisa <= 0 ? (
                                                                        <span title="Faktur sudah lunas" className="inline-block p-0.5 text-slate-300 cursor-not-allowed">
                                                                            <Check className="w-3.5 h-3.5 text-emerald-500" />
                                                                        </span>
                                                                    ) : (
                                                                        <input
                                                                            type="checkbox"
                                                                            checked={selectedHutangIds.includes(item.id)}
                                                                            onChange={() => handleToggleHutang(item)}
                                                                            className="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                                        />
                                                                    )}
                                                                </td>
                                                                <td className="px-4 py-3.5">
                                                                    <button
                                                                        onClick={() => setSelectedHutang(item)}
                                                                        className="text-blue-600 hover:text-blue-800 font-semibold hover:underline"
                                                                    >
                                                                        {item.nomor}
                                                                    </button>
                                                                </td>
                                                                <td className="px-4 py-3.5 font-medium text-slate-800">
                                                                    {item.vendor}
                                                                </td>
                                                                <td className="px-4 py-3.5 text-slate-600 font-mono text-[11px]">
                                                                    {item.referensi}
                                                                </td>
                                                                <td className="px-4 py-3.5 text-slate-600">{item.tanggal}</td>
                                                                <td className="px-4 py-3.5 text-slate-600">{item.jatuhTempo}</td>
                                                                <td className="px-4 py-3.5">
                                                                    <span
                                                                        className={`font-semibold text-[11px] px-2 py-0.5 rounded-full ${item.status === 'Lunas'
                                                                            ? 'bg-emerald-50 text-emerald-600'
                                                                            : item.status === 'Dibayar Sebagian'
                                                                                ? 'bg-amber-50 text-amber-600'
                                                                                : 'bg-red-50 text-red-500'
                                                                            }`}
                                                                    >
                                                                        {item.status}
                                                                    </span>
                                                                </td>
                                                                <td className="px-4 py-3.5 text-right font-semibold text-slate-900">
                                                                    {formatNumberOnly(item.sisa)}
                                                                </td>
                                                                <td className="px-4 py-3.5 text-center">
                                                                    {item.sisa > 0 ? (
                                                                        <button
                                                                            onClick={() => openPaymentModal(item)}
                                                                            className="px-2.5 py-1 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition"
                                                                        >
                                                                            Bayar
                                                                        </button>
                                                                    ) : (
                                                                        <button
                                                                            onClick={() => setSelectedHutang(item)}
                                                                            className="px-2.5 py-1 text-xs font-medium text-slate-600 bg-slate-50 hover:bg-slate-100 rounded-lg transition"
                                                                        >
                                                                            Detil
                                                                        </button>
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        ))
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>

                                        {/* Pagination Footer */}
                                        <div className="px-6 py-3.5 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-500">
                                            <div className="flex items-center gap-2">
                                                <span>Menampilkan</span>
                                                <select
                                                    value={itemsPerPage}
                                                    onChange={(e) => {
                                                        setItemsPerPage(Number(e.target.value));
                                                        setCurrentPage(1);
                                                    }}
                                                    className="border border-slate-200 rounded-md py-1 px-2 text-xs font-semibold text-slate-700 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                >
                                                    <option value={10}>10</option>
                                                    <option value={25}>25</option>
                                                    <option value={50}>50</option>
                                                    <option value={100}>100</option>
                                                </select>
                                                <span>
                                                    dari <strong className="font-semibold text-slate-800">{filteredHutang.length}</strong> tagihan
                                                </span>
                                            </div>

                                            <div className="flex items-center gap-1.5">
                                                <button
                                                    onClick={() => setCurrentPage((prev) => Math.max(prev - 1, 1))}
                                                    disabled={currentPage === 1}
                                                    className="px-2.5 py-1.5 rounded-lg border border-slate-200 font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                                                >
                                                    Sebelumnya
                                                </button>
                                                <span className="px-2 py-1 font-semibold text-slate-700">
                                                    Halaman {currentPage} dari {totalPages}
                                                </span>
                                                <button
                                                    onClick={() => setCurrentPage((prev) => Math.min(prev + 1, totalPages))}
                                                    disabled={currentPage >= totalPages}
                                                    className="px-2.5 py-1.5 rounded-lg border border-slate-200 font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                                                >
                                                    Selanjutnya
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </>
                    )}

                    {/* ==================================================== */}
                    {/* TAB 3: PEMBELIAN CASH (TUNAI)                        */}
                    {/* ==================================================== */}
                    {activeTab === 'cash' && (
                        <>
                            {selectedCash ? (
                                /* SUB-VIEW A: DETIL PEMBELIAN CASH (PERSIS SEPERTI KREDIT) */
                                <div className="space-y-6 max-w-[1300px] mx-auto">
                                    {/* Link Kembali ke Daftar */}
                                    <button
                                        onClick={() => setSelectedCash(null)}
                                        className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-800 font-medium transition"
                                    >
                                        <ArrowLeft className="w-3.5 h-3.5" />
                                        <span>Kembali ke Daftar Pembelian Cash</span>
                                    </button>

                                    {/* Judul & Action Kanan */}
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                                            Detil Pembelian Tunai (Cash) {selectedCash.nomor}
                                        </h1>

                                        <div className="flex items-center gap-2">
                                            <button
                                                onClick={() => openCashModal(selectedCash)}
                                                className="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm"
                                            >
                                                <Landmark className="w-3.5 h-3.5" />
                                                <span>{selectedCash.akunPembayaran ? 'Ubah Akun Kas/Bank' : 'Pilih Akun Kas/Bank'}</span>
                                            </button>

                                            <button
                                                onClick={() => window.print()}
                                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition"
                                            >
                                                <Printer className="w-3.5 h-3.5 text-slate-500" />
                                                <span>Print</span>
                                            </button>
                                        </div>
                                    </div>

                                    {/* KARTU UTAMA DETIL FAKTUR CASH */}
                                    <div className="bg-white rounded-2xl border border-slate-200 p-8 shadow-xs space-y-6">
                                        {/* Status Badge */}
                                        <div className="flex items-center justify-between">
                                            <span className="inline-block px-3 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-600">
                                                {selectedCash.status || 'Lunas (Cash)'}
                                            </span>

                                            <span className="text-xs text-slate-400">
                                                Jenis Pembayaran: <strong className="text-slate-700 font-semibold">TUNAI (Langsung Lunas)</strong>
                                            </span>
                                        </div>

                                        {/* Metadata Faktur (2 Kolom) */}
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 text-xs">
                                            <div className="space-y-4">
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">PBF / Vendor</span>
                                                    <span className="text-blue-600 font-semibold text-sm">
                                                        {selectedCash.vendor}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">Tgl. Transaksi</span>
                                                    <span className="font-semibold text-slate-800 text-sm">{selectedCash.tanggal}</span>
                                                </div>
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">Apotek Penerima</span>
                                                    <span className="text-blue-600 font-medium">
                                                        {selectedCash.gudang}
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="space-y-4">
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">Nomor Faktur</span>
                                                    <span className="font-bold text-slate-900 text-sm">{selectedCash.nomor}</span>
                                                </div>
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">Referensi / Kode Penerimaan</span>
                                                    <span className="font-medium text-slate-800">{selectedCash.referensi}</span>
                                                </div>
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">Akun Pembayaran (Kas & Bank)</span>
                                                    {selectedCash.akunPembayaran ? (
                                                        <div className="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-100 rounded-lg">
                                                            <Landmark className="w-3.5 h-3.5 text-blue-600" />
                                                            <span className="font-semibold">{selectedCash.akunPembayaran.nama}</span>
                                                            {selectedCash.akunPembayaran.noRekening && (
                                                                <span className="text-[10px] text-slate-500 font-mono">({selectedCash.akunPembayaran.noRekening})</span>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-amber-600 bg-amber-50 px-2 py-0.5 rounded text-[11px] font-medium border border-amber-200">
                                                                Belum Ditentukan Akun
                                                            </span>
                                                            <button
                                                                type="button"
                                                                onClick={() => openCashModal(selectedCash)}
                                                                className="text-xs text-blue-600 hover:text-blue-800 font-semibold underline"
                                                            >
                                                                Pilih Akun
                                                            </button>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        {/* Informasi Pengiriman */}
                                        <div className="pt-2 border-t border-slate-100">
                                            <button
                                                onClick={() => setIsCashShippingOpen(!isCashShippingOpen)}
                                                className="flex items-center gap-1.5 text-xs font-semibold text-slate-700 hover:text-slate-900"
                                            >
                                                {isCashShippingOpen ? <ChevronDown className="w-3.5 h-3.5" /> : <ChevronRight className="w-3.5 h-3.5" />}
                                                <span>Informasi pengiriman</span>
                                            </button>
                                            {isCashShippingOpen && (
                                                <div className="pl-5 pt-3 text-xs">
                                                    <span className="text-slate-400 block text-[11px]">Tanggal Pengiriman / Penerimaan</span>
                                                    <span className="font-medium text-slate-800">{selectedCash.tanggal}</span>
                                                </div>
                                            )}
                                        </div>

                                        {/* Tabel Item Obat */}
                                        <div className="pt-4 overflow-x-auto">
                                            <table className="w-full text-left text-xs">
                                                <thead className="text-slate-400 border-b border-slate-100 pb-2 text-[11px]">
                                                    <tr>
                                                        <th className="py-2.5 font-normal">Produk</th>
                                                        <th className="py-2.5 font-normal">Deskripsi</th>
                                                        <th className="py-2.5 font-normal text-center">Kuantitas</th>
                                                        <th className="py-2.5 font-normal">Satuan</th>
                                                        <th className="py-2.5 font-normal text-center">Discount</th>
                                                        <th className="py-2.5 font-normal text-right">Harga</th>
                                                        <th className="py-2.5 font-normal text-center">Pajak</th>
                                                        <th className="py-2.5 font-normal text-right">Jumlah</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-100 text-slate-700">
                                                    {selectedCash.items && selectedCash.items.length > 0 ? (
                                                        selectedCash.items.map((it, idx) => (
                                                            <tr key={idx}>
                                                                <td className="py-3">
                                                                    <span className="text-blue-600 font-medium">
                                                                        {it.sku} - {it.nama}
                                                                    </span>
                                                                </td>
                                                                <td className="py-3 text-slate-400">-</td>
                                                                <td className="py-3 text-center font-medium">{it.qty}</td>
                                                                <td className="py-3">{it.satuan}</td>
                                                                <td className="py-3 text-center">{it.diskon}</td>
                                                                <td className="py-3 text-right">{formatNumberOnly(it.harga)}</td>
                                                                <td className="py-3 text-center text-slate-500">{it.pajak}</td>
                                                                <td className="py-3 text-right font-semibold text-slate-900">
                                                                    {formatNumberOnly(it.jumlah)}
                                                                </td>
                                                            </tr>
                                                        ))
                                                    ) : (
                                                        <tr>
                                                            <td colSpan={8} className="py-6 text-center text-slate-400">
                                                                Tidak ada rincian item.
                                                            </td>
                                                        </tr>
                                                    )}
                                                </tbody>
                                                <tfoot className="border-t border-slate-200">
                                                    <tr>
                                                        <td colSpan={2} className="py-3 text-right font-semibold text-slate-600">
                                                            Total Kuantitas
                                                        </td>
                                                        <td className="py-3 text-center font-bold text-slate-900">{totalCashSelectedQty}</td>
                                                        <td colSpan={5}></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>

                                        {/* Summary Bawah */}
                                        <div className="pt-6 border-t border-slate-100 flex justify-end">
                                            <div className="w-80 space-y-2 text-xs">
                                                <div className="flex justify-between text-slate-600">
                                                    <span>Sub Total</span>
                                                    <span className="font-medium text-slate-800">{formatNumberOnly(selectedCash.subtotal)}</span>
                                                </div>
                                                <div className="flex justify-between text-slate-600">
                                                    <span>PPN11</span>
                                                    <span className="font-medium text-slate-800">{formatNumberOnly(selectedCash.ppn)}</span>
                                                </div>
                                                <div className="flex justify-between text-slate-800 font-bold pt-1">
                                                    <span>Total Pembelian Cash</span>
                                                    <span>{formatNumberOnly(selectedCash.total)}</span>
                                                </div>

                                                <div className="bg-emerald-50 border border-emerald-200 p-3.5 rounded-xl flex justify-between items-center text-emerald-800 font-bold mt-3">
                                                    <span className="text-xs">Status Pembayaran</span>
                                                    <span className="text-xs px-2.5 py-1 bg-emerald-600 text-white rounded-lg">LUNAS (CASH)</span>
                                                </div>

                                                {!selectedCash.akunPembayaran && (
                                                    <button
                                                        onClick={() => openCashModal(selectedCash)}
                                                        className="w-full mt-2 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold transition flex items-center justify-center gap-2 shadow-sm"
                                                    >
                                                        <Landmark className="w-4 h-4" />
                                                        <span>Pilih Akun Kas & Bank</span>
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {/* KARTU AUDIT TRAIL & INFORMASI AKUN KAS / BANK */}
                                    <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                                        <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                                            <div className="flex items-center gap-2.5">
                                                <History className="w-5 h-5 text-blue-600" />
                                                <div>
                                                    <h3 className="font-bold text-slate-900 text-sm">
                                                        Informasi Akun Kas & Bank (Pembayaran Tunai)
                                                    </h3>
                                                    <p className="text-[11px] text-slate-400">
                                                        Rincian akun kas drawer atau rekening bank yang digunakan untuk mendanai pembelian obat tunai ini.
                                                    </p>
                                                </div>
                                            </div>

                                            <span className="text-xs font-semibold text-slate-600">
                                                Total Pembayaran: <strong className="text-emerald-600">{formatRupiah(selectedCash.total)}</strong>
                                            </span>
                                        </div>

                                        {selectedCash.akunPembayaran ? (
                                            <div className="overflow-x-auto">
                                                <table className="w-full text-left text-xs">
                                                    <thead className="text-slate-400 bg-slate-50/50 border-b border-slate-100">
                                                        <tr>
                                                            <th className="py-2.5 px-3 font-medium">Tanggal Transaksi</th>
                                                            <th className="py-2.5 px-3 font-medium">Akun Kas & Bank</th>
                                                            <th className="py-2.5 px-3 font-medium">No. Rekening</th>
                                                            <th className="py-2.5 px-3 font-medium">No. Referensi / Bukti</th>
                                                            <th className="py-2.5 px-3 font-medium text-right">Nominal Bayar</th>
                                                            <th className="py-2.5 px-3 font-medium">Diproses Oleh</th>
                                                            <th className="py-2.5 px-3 font-medium text-center">Aksi</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-100 text-slate-700">
                                                        <tr className="hover:bg-slate-50/50">
                                                            <td className="py-3 px-3 font-medium text-slate-900">{selectedCash.akunPembayaran.tanggal || selectedCash.tanggal}</td>
                                                            <td className="py-3 px-3">
                                                                <div className="flex items-center gap-1.5">
                                                                    <Landmark className="w-3.5 h-3.5 text-blue-500" />
                                                                    <span className="font-semibold text-slate-800">{selectedCash.akunPembayaran.nama}</span>
                                                                    <span className="text-[10px] text-slate-400">({selectedCash.akunPembayaran.kategori || 'Kas & Bank'})</span>
                                                                </div>
                                                            </td>
                                                            <td className="py-3 px-3 font-mono text-[11px] text-slate-600">{selectedCash.akunPembayaran.noRekening || '-'}</td>
                                                            <td className="py-3 px-3 font-mono text-[11px] text-slate-600">{selectedCash.akunPembayaran.noReferensi || selectedCash.referensi}</td>
                                                            <td className="py-3 px-3 text-right font-bold text-emerald-600">
                                                                {formatRupiah(selectedCash.total)}
                                                            </td>
                                                            <td className="py-3 px-3 font-medium text-slate-800">{selectedCash.akunPembayaran.diprosesOleh || 'Staf Finance'}</td>
                                                            <td className="py-3 px-3 text-center">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => openCashModal(selectedCash)}
                                                                    className="px-2.5 py-1 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition"
                                                                >
                                                                    Ubah Akun
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <div className="py-8 text-center text-slate-400 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                                                <Landmark className="w-8 h-8 mx-auto text-slate-300 mb-2" />
                                                <p className="text-xs font-medium text-slate-600">Belum ada akun kas & bank yang dialokasikan untuk transaksi ini.</p>
                                                <p className="text-[11px] text-slate-400 mt-0.5">
                                                    Tentukan metode pembayaran kasir / transfer bank untuk pencatatan buku kas yang akurat.
                                                </p>
                                                <button
                                                    type="button"
                                                    onClick={() => openCashModal(selectedCash)}
                                                    className="mt-3 inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm"
                                                >
                                                    <Landmark className="w-3.5 h-3.5" />
                                                    <span>Pilih Akun Kas & Bank</span>
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                /* SUB-VIEW B: TABEL DAFTAR PEMBELIAN CASH */
                                <div className="space-y-4 max-w-[1400px] mx-auto">
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <div>
                                            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Pembelian Tunai (Cash)</h1>
                                            <p className="text-xs text-slate-500 mt-0.5">
                                                Transaksi pembelian obat langsung lunas. Tentukan metode pembayaran akun/bank (BNI, Mandiri, BCA, BTN, atau Kasir).
                                            </p>
                                        </div>

                                        <div className="text-xs text-slate-600 bg-white border border-slate-200 px-4 py-2 rounded-xl">
                                            Total Pembelian Cash: <strong className="text-emerald-600 font-bold">{formatRupiah(stats.totalCashPurchases)}</strong>
                                        </div>
                                    </div>

                                    {/* Search & Filter Bar Cash */}
                                    <div className="bg-white p-3.5 rounded-2xl border border-slate-200 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 shadow-xs">
                                        <div className="flex flex-col sm:flex-row items-center gap-2.5 flex-1">
                                            <div className="relative w-full sm:w-72">
                                                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                                <input
                                                    type="text"
                                                    value={searchCashQuery}
                                                    onChange={(e) => setSearchCashQuery(e.target.value)}
                                                    placeholder="Cari faktur cash..."
                                                    className="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"
                                                />
                                            </div>

                                            {/* Autocomplete PBF Combobox untuk Cash */}
                                            <PbfCombobox
                                                pbfs={allPbfs}
                                                selectedPbf={selectedCashPbf}
                                                onSelectPbf={(pbf) => setSelectedCashPbf(pbf)}
                                                placeholder="Filter PBF / Vendor..."
                                            />
                                        </div>

                                        <span className="text-xs text-slate-500 shrink-0">
                                            {filteredCash.length} transaksi tunai ditemukan
                                        </span>
                                    </div>

                                    {/* Active Filter Indicator Badge Cash */}
                                    {selectedCashPbf && (
                                        <div className="flex items-center gap-2 text-xs animate-in fade-in">
                                            <span className="text-slate-400 font-medium">Filter Aktif:</span>
                                            <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full font-semibold">
                                                <Building2 className="w-3.5 h-3.5 text-emerald-600" />
                                                <span>PBF: {selectedCashPbf}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => setSelectedCashPbf('')}
                                                    className="hover:bg-emerald-200/60 p-0.5 rounded-full transition ml-0.5"
                                                    title="Hapus filter PBF"
                                                >
                                                    <X className="w-3 h-3" />
                                                </button>
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => setSelectedCashPbf('')}
                                                className="text-[11px] text-slate-500 hover:text-red-600 underline font-medium"
                                            >
                                                Tampilkan Semua PBF
                                            </button>
                                        </div>
                                    )}

                                    {/* Tabel Pembelian Cash */}
                                    <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-xs">
                                                <thead className="bg-[#F8FAFC] border-b border-slate-200 text-slate-600 font-semibold">
                                                    <tr>
                                                        <th className="w-10 px-4 py-3.5">
                                                            <input
                                                                type="checkbox"
                                                                checked={
                                                                    filteredCash.length > 0 &&
                                                                    filteredCash.slice(0, 10).every((i) => selectedCashIds.includes(i.id))
                                                                }
                                                                onChange={handleSelectAllCash}
                                                                className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                                                title="Pilih semua pembelian di halaman ini (maks. 10)"
                                                            />
                                                        </th>
                                                        <th className="px-4 py-3.5">Nomor Faktur</th>
                                                        <th className="px-4 py-3.5">Vendor (PBF)</th>
                                                        <th className="px-4 py-3.5">Tanggal</th>
                                                        <th className="px-4 py-3.5">Status</th>
                                                        <th className="px-4 py-3.5">Akun Pembayaran (Kas & Bank)</th>
                                                        <th className="px-4 py-3.5 text-right">Total (Rp)</th>
                                                        <th className="px-4 py-3.5 text-center">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-100">
                                                    {filteredCash.length === 0 ? (
                                                        <tr>
                                                            <td colSpan={8} className="px-4 py-8 text-center text-slate-400">
                                                                Belum ada transaksi pembelian tunai (cash).
                                                            </td>
                                                        </tr>
                                                    ) : (
                                                        filteredCash.map((item) => (
                                                            <tr key={item.id} className="hover:bg-slate-50/70 transition">
                                                                <td className="px-4 py-3.5">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={selectedCashIds.includes(item.id)}
                                                                        onChange={() => handleToggleCash(item)}
                                                                        className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                                                    />
                                                                </td>
                                                                <td className="px-4 py-3.5">
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => setSelectedCash(item)}
                                                                        className="text-blue-600 hover:text-blue-800 font-semibold hover:underline text-left"
                                                                        title="Lihat rincian item pembelian cash"
                                                                    >
                                                                        {item.nomor}
                                                                    </button>
                                                                </td>
                                                                <td className="px-4 py-3.5 font-medium text-slate-800">
                                                                    {item.vendor}
                                                                </td>
                                                                <td className="px-4 py-3.5 text-slate-600">{item.tanggal}</td>
                                                                <td className="px-4 py-3.5">
                                                                    <span className="font-semibold text-[11px] px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-600">
                                                                        Lunas (Cash)
                                                                    </span>
                                                                </td>
                                                                <td className="px-4 py-3.5">
                                                                    {item.akunPembayaran ? (
                                                                        <div className="flex items-center gap-1.5">
                                                                            <Landmark className="w-3.5 h-3.5 text-blue-500" />
                                                                            <span className="font-semibold text-slate-800">
                                                                                {item.akunPembayaran.nama}
                                                                            </span>
                                                                            {item.akunPembayaran.noRekening && (
                                                                                <span className="text-[10px] text-slate-400 font-mono">
                                                                                    ({item.akunPembayaran.noRekening})
                                                                                </span>
                                                                            )}
                                                                        </div>
                                                                    ) : (
                                                                        <span className="text-amber-600 bg-amber-50 px-2 py-0.5 rounded text-[11px] font-medium">
                                                                            Belum Ditentukan Akun
                                                                        </span>
                                                                    )}
                                                                </td>
                                                                <td className="px-4 py-3.5 text-right font-bold text-slate-900">
                                                                    {formatNumberOnly(item.total)}
                                                                </td>
                                                                <td className="px-4 py-3.5 text-center whitespace-nowrap">
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => setSelectedCash(item)}
                                                                        className="px-2.5 py-1 text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition mr-1.5"
                                                                        title="Lihat detil faktur dan item"
                                                                    >
                                                                        Detil
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => openCashModal(item)}
                                                                        className="px-2.5 py-1 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition"
                                                                    >
                                                                        {item.akunPembayaran ? 'Ubah Akun' : 'Pilih Akun'}
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        ))
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </>
                    )}

                    {/* ==================================================== */}
                    {/* TAB 4: KAS & BANK (BAGAN AKUN / CHART OF ACCOUNTS)   */}
                    {/* ==================================================== */}
                    {activeTab === 'kas_bank' && (
                        <div className="space-y-6 max-w-[1400px] mx-auto">
                            {/* Header Kas & Bank */}
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Kas & Bank & Buku Kas</h1>

                                    </div>
                                    <p className="text-xs text-slate-500 mt-0.5">
                                        Visualisasi saldo rekening, analisis perputaran arus kas, serta pencatatan mutasi transaksi masuk (Debit) & keluar (Kredit).
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
                                        <div className="text-xl font-bold text-slate-900 tracking-tight font-mono">
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
                                        <div className="text-xl font-bold text-emerald-600 tracking-tight font-mono">
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
                                        <div className="text-xl font-bold text-rose-600 tracking-tight font-mono">
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
                                        <div className={`text-xl font-bold tracking-tight font-mono ${kasBankSummary.netCashflow >= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>
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
                                                            <div className="text-[11px] text-slate-400 font-mono mt-0.5">
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

                                                        {/* Progress bar grafis */}
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
                                                            <span>Saldo Buku: <strong className="font-mono text-slate-700">{formatRupiah(acc.balance)}</strong></span>
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

                                        {/* SVG Ring Graph Visual */}
                                        <div className="mt-6 flex flex-col items-center justify-center">
                                            <div className="relative w-40 h-40 flex items-center justify-center">
                                                <svg className="w-full h-full -rotate-90" viewBox="0 0 100 100">
                                                    {/* Background Circle */}
                                                    <circle
                                                        cx="50"
                                                        cy="50"
                                                        r="38"
                                                        fill="transparent"
                                                        stroke="#F1F5F9"
                                                        strokeWidth="12"
                                                    />
                                                    {/* Inflow Segment (Emerald) */}
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
                                                    {/* Outflow Segment (Rose) */}
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

                                                {/* Center Content */}
                                                <div className="absolute inset-0 flex flex-col items-center justify-center text-center p-2">
                                                    <span className="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">
                                                        Net Flow
                                                    </span>
                                                    <span className={`text-xs font-bold font-mono ${kasBankSummary.netCashflow >= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>
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

                                    {/* Breakdown Legend */}
                                    <div className="space-y-2.5 pt-4 border-t border-slate-100 text-xs">
                                        <div className="flex items-center justify-between p-2 rounded-xl bg-emerald-50/60 border border-emerald-100">
                                            <div className="flex items-center gap-2">
                                                <div className="w-2.5 h-2.5 rounded-full bg-emerald-500" />
                                                <span className="font-semibold text-emerald-900">Uang Masuk (Debit)</span>
                                            </div>
                                            <span className="font-mono font-bold text-emerald-700">
                                                {formatRupiah(kasBankSummary.totalMasuk)}
                                            </span>
                                        </div>

                                        <div className="flex items-center justify-between p-2 rounded-xl bg-rose-50/60 border border-rose-100">
                                            <div className="flex items-center gap-2">
                                                <div className="w-2.5 h-2.5 rounded-full bg-rose-500" />
                                                <span className="font-semibold text-rose-900">Uang Keluar (Kredit)</span>
                                            </div>
                                            <span className="font-mono font-bold text-rose-700">
                                                {formatRupiah(kasBankSummary.totalKeluar)}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* 3. TABEL MUTASI TRANSAKSI KAS & BANK (BUKU KAS / REKENING KORAN) */}
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

                                    {/* Filter Toolbar Mutasi */}
                                    <div className="flex flex-wrap items-center gap-2.5">
                                        {/* Filter Direction Pills */}
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

                                {/* Tabel Data Mutasi */}
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
                                                        <td className="px-4 py-3 font-mono font-semibold text-slate-900 whitespace-nowrap">
                                                            <span className="px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200">
                                                                {item.document_no}
                                                            </span>
                                                        </td>
                                                        <td className="px-4 py-3 whitespace-nowrap">
                                                            <div className="font-bold text-slate-800 flex items-center gap-1.5">
                                                                <Landmark className="w-3.5 h-3.5 text-blue-600" />
                                                                <span>{item.account_name}</span>
                                                            </div>
                                                            <div className="text-[10px] text-slate-400 font-mono">
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
                                                        {/* Kolom Debit (Masuk) */}
                                                        <td className="px-4 py-3 text-right font-mono whitespace-nowrap">
                                                            {item.direction === 'IN' ? (
                                                                <span className="font-bold text-emerald-600 inline-flex items-center justify-end gap-1">
                                                                    <ArrowDownLeft className="w-3 h-3" />
                                                                    +{formatRupiah(item.amount)}
                                                                </span>
                                                            ) : (
                                                                <span className="text-slate-300">-</span>
                                                            )}
                                                        </td>
                                                        {/* Kolom Kredit (Keluar) */}
                                                        <td className="px-4 py-3 text-right font-mono whitespace-nowrap">
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
                                                    <td className="px-4 py-3 font-mono font-semibold text-blue-600">{acc.code}</td>
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
                                                    <td className="px-4 py-3 font-mono text-slate-600">{acc.account_number || '-'}</td>
                                                    <td className="px-4 py-3 font-mono text-slate-800 font-semibold text-right">
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
                    )}

                    {/* ==================================================== */}
                    {/* TAB 5: KONSINYASI (DEFERRED / NANTI SAJA)            */}
                    {/* ==================================================== */}
                    {activeTab === 'konsinyasi' && (
                        <div className="bg-white p-12 rounded-2xl border border-slate-200 text-center text-slate-500 max-w-xl mx-auto space-y-3">
                            <div className="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mx-auto">
                                <ArrowRightLeft className="w-6 h-6" />
                            </div>
                            <h3 className="text-base font-bold text-slate-900">Modul Konsinyasi Sedang Disiapkan</h3>
                            <p className="text-xs text-slate-500 leading-relaxed">
                                Sesuai arahan, modul konsinyasi akan dikembangkan berikutnya. Saat ini sistem fokus pada <strong>Pembayaran Hutang Dagang (Kredit)</strong> dan <strong>Pembelian Tunai (Cash)</strong>.
                            </p>
                            <div className="pt-2">
                                <button
                                    onClick={() => setActiveTab('hutang')}
                                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold transition"
                                >
                                    Buka Hutang Dagang (Kredit)
                                </button>
                            </div>
                        </div>
                    )}

                    {/* ==================================================== */}
                    {/* TAB 6: PIUTANG PENJUALAN (KREDIT)                    */}
                    {/* ==================================================== */}
                    {activeTab === 'piutang' && (
                        <>
                            {selectedPiutang ? (
                                /* SUB-VIEW A: DETIL TAGIHAN PIUTANG PENJUALAN */
                                <div className="space-y-6 max-w-[1300px] mx-auto">
                                    {/* Link Kembali ke Daftar */}
                                    <button
                                        onClick={() => setSelectedPiutang(null)}
                                        className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-800 font-medium transition"
                                    >
                                        <ArrowLeft className="w-3.5 h-3.5" />
                                        <span>Kembali ke Daftar Piutang</span>
                                    </button>

                                    {/* Judul & Action Kanan */}
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                                            Detil Tagihan Piutang {selectedPiutang.nomor}
                                        </h1>

                                        <div className="flex items-center gap-2">
                                            {selectedPiutang.sisa > 0 && (
                                                <button
                                                    onClick={() => openPiutangPaymentModal(selectedPiutang)}
                                                    className="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-white bg-amber-600 rounded-lg hover:bg-amber-700 transition shadow-sm"
                                                >
                                                    <CreditCard className="w-3.5 h-3.5" />
                                                    <span>Terima Pembayaran</span>
                                                </button>
                                            )}

                                            <button
                                                onClick={() => window.print()}
                                                className="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition"
                                            >
                                                <Printer className="w-3.5 h-3.5 text-slate-500" />
                                                <span>Print</span>
                                            </button>
                                        </div>
                                    </div>

                                    {/* KARTU UTAMA DETIL PIUTANG */}
                                    <div className="bg-white rounded-2xl border border-slate-200 p-8 shadow-xs space-y-6">
                                        {/* Status Badge */}
                                        <div className="flex items-center justify-between">
                                            <span
                                                className={`inline-block px-3 py-1 text-xs font-semibold rounded-full ${selectedPiutang.status === 'Lunas'
                                                    ? 'bg-emerald-50 text-emerald-600'
                                                    : selectedPiutang.status === 'Dibayar Sebagian'
                                                        ? 'bg-amber-50 text-amber-600'
                                                        : 'bg-red-50 text-red-600'
                                                    }`}
                                            >
                                                {selectedPiutang.status}
                                            </span>

                                            <span className="text-xs text-slate-400">
                                                Jenis Transaksi: <strong className="text-slate-700 font-semibold">PENJUALAN KREDIT (Tempo)</strong>
                                            </span>
                                        </div>

                                        {/* Metadata Piutang (2 Kolom) */}
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 text-xs">
                                            <div className="space-y-4">
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">Pelanggan / Debitur</span>
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-amber-600 font-bold text-sm">
                                                            {selectedPiutang.debtor}
                                                        </span>
                                                        {selectedPiutang.debtor_code && selectedPiutang.debtor_code !== '-' && (
                                                            <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 font-semibold border border-amber-200">
                                                                {selectedPiutang.debtor_code}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">Pasien Terkait</span>
                                                    <span className="font-semibold text-slate-800 text-sm">{selectedPiutang.pasien}</span>
                                                </div>
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">Dokter Pemeriksa</span>
                                                    <span className="text-slate-700 font-medium">{selectedPiutang.dokter}</span>
                                                </div>
                                            </div>

                                            <div className="space-y-4">
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">No. Faktur / Resep</span>
                                                    <span className="font-bold text-slate-900 text-sm">{selectedPiutang.nomor}</span>
                                                </div>
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">Tgl. Transaksi</span>
                                                    <span className="font-semibold text-slate-800 text-sm">{selectedPiutang.tanggal}</span>
                                                </div>
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">Tgl. Jatuh Tempo</span>
                                                    <span className="font-semibold text-red-600 text-sm">{selectedPiutang.jatuhTempo}</span>
                                                </div>
                                                <div>
                                                    <span className="text-slate-400 block text-[11px] mb-1">Apotek / Unit Layanan</span>
                                                    <span className="font-medium text-slate-800">{selectedPiutang.apotek}</span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Informasi Pelayanan & Penyerahan */}
                                        <div className="pt-2 border-t border-slate-100">
                                            <button
                                                onClick={() => setIsPiutangShippingOpen(!isPiutangShippingOpen)}
                                                className="flex items-center gap-1.5 text-xs font-semibold text-slate-700 hover:text-slate-900"
                                            >
                                                {isPiutangShippingOpen ? <ChevronDown className="w-3.5 h-3.5" /> : <ChevronRight className="w-3.5 h-3.5" />}
                                                <span>Informasi Penyerahan & Resep</span>
                                            </button>
                                            {isPiutangShippingOpen && (
                                                <div className="pl-5 pt-3 text-xs grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50/70 p-3 rounded-xl mt-2">
                                                    <div>
                                                        <span className="text-slate-400 block text-[11px]">Tanggal Transaksi</span>
                                                        <span className="font-medium text-slate-800">{selectedPiutang.tanggal}</span>
                                                    </div>
                                                    <div>
                                                        <span className="text-slate-400 block text-[11px]">Unit Apotek Penyerah</span>
                                                        <span className="font-medium text-slate-800">{selectedPiutang.apotek}</span>
                                                    </div>
                                                </div>
                                            )}
                                        </div>

                                        {/* Tabel Item Obat */}
                                        <div className="pt-4 overflow-x-auto">
                                            <table className="w-full text-left text-xs">
                                                <thead className="text-slate-400 border-b border-slate-100 pb-2 text-[11px]">
                                                    <tr>
                                                        <th className="py-2.5 font-normal">Produk / Obat</th>
                                                        <th className="py-2.5 font-normal">Deskripsi</th>
                                                        <th className="py-2.5 font-normal text-center">Kuantitas</th>
                                                        <th className="py-2.5 font-normal">Satuan</th>
                                                        <th className="py-2.5 font-normal text-center">Discount</th>
                                                        <th className="py-2.5 font-normal text-right">Harga</th>
                                                        <th className="py-2.5 font-normal text-center">Pajak</th>
                                                        <th className="py-2.5 font-normal text-right">Jumlah</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-100 text-slate-700">
                                                    {selectedPiutang.items && selectedPiutang.items.length > 0 ? (
                                                        selectedPiutang.items.map((it, idx) => (
                                                            <tr key={idx}>
                                                                <td className="py-3">
                                                                    <span className="text-amber-700 font-medium">
                                                                        {it.sku} - {it.nama}
                                                                    </span>
                                                                </td>
                                                                <td className="py-3 text-slate-400">-</td>
                                                                <td className="py-3 text-center font-medium">{it.qty}</td>
                                                                <td className="py-3">{it.satuan}</td>
                                                                <td className="py-3 text-center">{it.diskon}</td>
                                                                <td className="py-3 text-right">{formatNumberOnly(it.harga)}</td>
                                                                <td className="py-3 text-center text-slate-500">{it.pajak}</td>
                                                                <td className="py-3 text-right font-semibold text-slate-900">
                                                                    {formatNumberOnly(it.jumlah)}
                                                                </td>
                                                            </tr>
                                                        ))
                                                    ) : (
                                                        <tr>
                                                            <td colSpan={8} className="py-6 text-center text-slate-400">
                                                                Tidak ada rincian item.
                                                            </td>
                                                        </tr>
                                                    )}
                                                </tbody>
                                                <tfoot className="border-t border-slate-200">
                                                    <tr>
                                                        <td colSpan={2} className="py-3 text-right font-semibold text-slate-600">
                                                            Total Kuantitas
                                                        </td>
                                                        <td className="py-3 text-center font-bold text-slate-900">{totalPiutangSelectedQty}</td>
                                                        <td colSpan={5}></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>

                                        {/* Summary Bawah */}
                                        <div className="pt-6 border-t border-slate-100 flex justify-end">
                                            <div className="w-80 space-y-2 text-xs">
                                                <div className="flex justify-between text-slate-600">
                                                    <span>Sub Total</span>
                                                    <span className="font-medium text-slate-800">{formatNumberOnly(selectedPiutang.subtotal)}</span>
                                                </div>
                                                <div className="flex justify-between text-slate-800 font-bold pt-1">
                                                    <span>Total Tagihan</span>
                                                    <span>{formatNumberOnly(selectedPiutang.total)}</span>
                                                </div>

                                                {selectedPiutang.terbayar > 0 && (
                                                    <div className="flex justify-between text-emerald-600 font-semibold pt-1">
                                                        <span>Sudah Diterima</span>
                                                        <span>- {formatNumberOnly(selectedPiutang.terbayar)}</span>
                                                    </div>
                                                )}

                                                <div className="bg-amber-50/80 border border-amber-200 p-3.5 rounded-xl flex justify-between items-center text-amber-900 font-bold mt-3">
                                                    <span className="text-xs">Sisa Piutang</span>
                                                    <span className="text-sm font-extrabold text-amber-700">{formatNumberOnly(selectedPiutang.sisa)}</span>
                                                </div>

                                                {selectedPiutang.sisa > 0 && (
                                                    <button
                                                        onClick={() => openPiutangPaymentModal(selectedPiutang)}
                                                        className="w-full mt-2 py-2.5 px-4 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-semibold transition flex items-center justify-center gap-2 shadow-sm"
                                                    >
                                                        <CreditCard className="w-4 h-4" />
                                                        <span>Terima Pembayaran Sekarang</span>
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {/* KARTU AUDIT TRAIL & RIWAYAT PENERIMAAN PEMBAYARAN */}
                                    <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                                        <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                                            <div className="flex items-center gap-2.5">
                                                <History className="w-5 h-5 text-amber-600" />
                                                <div>
                                                    <h3 className="font-bold text-slate-900 text-sm">
                                                        Riwayat Penerimaan Pembayaran & Audit Trail
                                                    </h3>
                                                    <p className="text-[11px] text-slate-400">
                                                        Catatan pembayaran yang diterima ke rekening Kas & Bank apotek dan staf yang memproses.
                                                    </p>
                                                </div>
                                            </div>

                                            <span className="text-xs font-semibold text-slate-600">
                                                Total Diterima: <strong className="text-emerald-600">{formatRupiah(selectedPiutang.terbayar)}</strong>
                                            </span>
                                        </div>

                                        {selectedPiutang.payments && selectedPiutang.payments.length > 0 ? (
                                            <div className="overflow-x-auto">
                                                <table className="w-full text-left text-xs">
                                                    <thead className="bg-[#F8FAFC] border-b border-slate-100 text-slate-500 font-medium">
                                                        <tr>
                                                            <th className="py-2 px-3">Tanggal</th>
                                                            <th className="py-2 px-3">Akun Kas / Bank Penerima</th>
                                                            <th className="py-2 px-3">No. Referensi</th>
                                                            <th className="py-2 px-3">Catatan</th>
                                                            <th className="py-2 px-3 text-right">Nominal Diterima</th>
                                                            <th className="py-2 px-3 text-right">Dicatat Oleh</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-100 text-slate-700">
                                                        {selectedPiutang.payments.map((p, idx) => (
                                                            <tr key={idx} className="hover:bg-slate-50/50">
                                                                <td className="py-3 px-3 font-medium text-slate-900">
                                                                    {p.tanggal}
                                                                </td>
                                                                <td className="py-3 px-3">
                                                                    <span className="font-semibold text-blue-600">{p.akunNama}</span>
                                                                    {p.akunKode && (
                                                                        <span className="text-[10px] text-slate-400 ml-1.5 font-mono">({p.akunKode})</span>
                                                                    )}
                                                                </td>
                                                                <td className="py-3 px-3 font-mono text-slate-500 text-[11px]">
                                                                    {p.noReferensi}
                                                                </td>
                                                                <td className="py-3 px-3 text-slate-500 italic max-w-xs truncate">
                                                                    {p.catatan}
                                                                </td>
                                                                <td className="py-3 px-3 text-right font-bold text-emerald-600">
                                                                    {formatRupiah(p.nominal)}
                                                                </td>
                                                                <td className="py-3 px-3 text-right">
                                                                    <div className="font-semibold text-slate-900">{p.diprosesOleh}</div>
                                                                    <div className="text-[10px] text-slate-400">{p.waktuAudit}</div>
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <div className="py-8 text-center text-slate-400 text-xs bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                                                Belum ada catatan penerimaan pembayaran untuk transaksi ini.
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                /* SUB-VIEW B: TABEL DAFTAR FAKTUR PIUTANG PENJUALAN */
                                <div className="space-y-4 max-w-[1400px] mx-auto">
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <div>
                                            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Piutang Penjualan (Kredit)</h1>
                                            <p className="text-xs text-slate-500 mt-0.5">
                                                Faktur dan resep penjualan tempo pelanggan/instansi yang belum lunas.
                                            </p>
                                        </div>

                                        <div className="flex items-center gap-2 flex-wrap">
                                            <button className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                                                <Filter className="w-3.5 h-3.5 text-slate-500" />
                                                <span>Filter</span>
                                            </button>
                                            <button className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                                                <Download className="w-3.5 h-3.5 text-slate-500" />
                                                <span>Ekspor</span>
                                            </button>
                                        </div>
                                    </div>

                                    {/* Tabs Filter Status */}
                                    <div className="flex items-center border-b border-slate-200 text-xs font-semibold">
                                        {[
                                            { label: 'Semua', count: piutangPenjualan.length },
                                            { label: 'Belum Bayar', count: stats.countPiutangBelumBayar },
                                            { label: 'Dibayar Sebagian', count: stats.countPiutangSebagian },
                                            { label: 'Lunas', count: stats.countPiutangLunas },
                                        ].map((t) => (
                                            <button
                                                key={t.label}
                                                onClick={() => {
                                                    setStatusPiutangFilter(t.label);
                                                    setPiutangCurrentPage(1);
                                                }}
                                                className={`pb-3 px-4 border-b-2 transition flex items-center gap-2 ${statusPiutangFilter === t.label
                                                    ? 'border-amber-600 text-amber-600 font-bold'
                                                    : 'border-transparent text-slate-500 hover:text-slate-800'
                                                    }`}
                                            >
                                                <span>{t.label}</span>
                                                {t.count > 0 && (
                                                    <span className={`text-[10px] px-1.5 py-0.2 rounded-full ${statusPiutangFilter === t.label ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'}`}>
                                                        {t.count}
                                                    </span>
                                                )}
                                            </button>
                                        ))}
                                    </div>

                                    {/* Search & Filter Bar */}
                                    <div className="bg-white p-3.5 rounded-2xl border border-slate-200 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 shadow-xs">
                                        <div className="flex flex-col sm:flex-row items-center gap-2.5 flex-1">
                                            <div className="relative w-full sm:w-72">
                                                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                                <input
                                                    type="text"
                                                    value={searchPiutangQuery}
                                                    onChange={(e) => {
                                                        setSearchPiutangQuery(e.target.value);
                                                        setPiutangCurrentPage(1);
                                                    }}
                                                    placeholder="Cari no invoice, pelanggan, pasien, dokter..."
                                                    className="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white transition"
                                                />
                                            </div>

                                            {/* Autocomplete Debitur Combobox */}
                                            <DebtorCombobox
                                                debtors={debtors}
                                                selectedDebtor={selectedDebtor}
                                                onSelectDebtor={(val) => {
                                                    setSelectedDebtor(val);
                                                    setPiutangCurrentPage(1);
                                                }}
                                                placeholder="Filter Pelanggan / Debitur..."
                                            />
                                        </div>

                                        <div className="text-xs text-slate-500 shrink-0">
                                            Total Sisa Piutang: <strong className="text-amber-600 font-bold">{formatRupiah(stats.totalPiutangSisa)}</strong>
                                        </div>
                                    </div>

                                    {/* Active Filter Indicator Badge */}
                                    {selectedDebtor && (
                                        <div className="flex items-center gap-2 text-xs animate-in fade-in">
                                            <span className="text-slate-400 font-medium">Filter Aktif:</span>
                                            <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-800 border border-amber-200 rounded-full font-semibold">
                                                <Building2 className="w-3.5 h-3.5 text-amber-600" />
                                                <span>Debitur: {selectedDebtor}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => setSelectedDebtor('')}
                                                    className="hover:bg-amber-200/60 p-0.5 rounded-full transition ml-0.5"
                                                    title="Hapus filter debitur"
                                                >
                                                    <X className="w-3 h-3" />
                                                </button>
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => setSelectedDebtor('')}
                                                className="text-[11px] text-slate-500 hover:text-amber-600 underline font-medium"
                                            >
                                                Tampilkan Semua Debitur
                                            </button>
                                        </div>
                                    )}

                                    {/* Selection Warning Alert */}
                                    {selectionWarning && (
                                        <div className="p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-xs flex items-center gap-2">
                                            <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0" />
                                            <span>{selectionWarning}</span>
                                        </div>
                                    )}

                                    {/* Tabel Piutang Penjualan */}
                                    <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-xs">
                                                <thead className="bg-[#F8FAFC] border-b border-slate-200 text-slate-600 font-semibold">
                                                    <tr>
                                                        <th className="w-10 px-4 py-3.5">
                                                            <input
                                                                type="checkbox"
                                                                onChange={handleSelectAllPiutang}
                                                                checked={
                                                                    paginatedPiutang.filter((i) => i.status !== 'Lunas' && i.sisa > 0).length > 0 &&
                                                                    paginatedPiutang
                                                                        .filter((i) => i.status !== 'Lunas' && i.sisa > 0)
                                                                        .every((i) => selectedPiutangIds.includes(i.id))
                                                                }
                                                                className="rounded text-amber-600 focus:ring-amber-500 border-slate-300"
                                                                title="Pilih semua tagihan belum lunas pada halaman ini (Maks. 10)"
                                                            />
                                                        </th>
                                                        <th className="px-4 py-3.5">Nomor Transaksi</th>
                                                        <th className="px-4 py-3.5">Pelanggan / Debitur</th>
                                                        <th className="px-4 py-3.5">Pasien & Dokter</th>
                                                        <th className="px-4 py-3.5">Tgl. Transaksi</th>
                                                        <th className="px-4 py-3.5">Jatuh Tempo</th>
                                                        <th className="px-4 py-3.5 text-right">Total Tagihan</th>
                                                        <th className="px-4 py-3.5 text-right">Sisa Piutang</th>
                                                        <th className="px-4 py-3.5 text-center">Status</th>
                                                        <th className="px-4 py-3.5 text-center">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-100">
                                                    {paginatedPiutang.length > 0 ? (
                                                        paginatedPiutang.map((item) => {
                                                            const isSelected = selectedPiutangIds.includes(item.id);
                                                            const isLunas = item.status === 'Lunas' || item.sisa <= 0;

                                                            return (
                                                                <tr
                                                                    key={item.id}
                                                                    className={`hover:bg-slate-50/70 transition ${isSelected ? 'bg-amber-50/50' : ''}`}
                                                                >
                                                                    <td className="px-4 py-3.5">
                                                                        {isLunas ? (
                                                                            <span title="Tagihan piutang sudah lunas" className="inline-block p-0.5 text-slate-300 cursor-not-allowed">
                                                                                <Check className="w-3.5 h-3.5 text-emerald-500" />
                                                                            </span>
                                                                        ) : (
                                                                            <input
                                                                                type="checkbox"
                                                                                checked={isSelected}
                                                                                onChange={() => handleTogglePiutang(item)}
                                                                                className="rounded text-amber-600 focus:ring-amber-500 border-slate-300 cursor-pointer"
                                                                            />
                                                                        )}
                                                                    </td>
                                                                    <td className="px-4 py-3.5">
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => setSelectedPiutang(item)}
                                                                            className="font-bold text-amber-700 hover:text-amber-900 hover:underline text-left cursor-pointer"
                                                                        >
                                                                            {item.nomor}
                                                                        </button>
                                                                        <div className="text-[10px] text-slate-400 font-mono">
                                                                            {item.apotek}
                                                                        </div>
                                                                    </td>
                                                                    <td className="px-4 py-3.5">
                                                                        <div className="font-semibold text-slate-800">
                                                                            {item.debtor}
                                                                        </div>
                                                                        {item.debtor_code && item.debtor_code !== '-' && (
                                                                            <span className="text-[10px] text-slate-400 font-mono">
                                                                                Kode: {item.debtor_code}
                                                                            </span>
                                                                        )}
                                                                    </td>
                                                                    <td className="px-4 py-3.5 text-slate-600">
                                                                        <div className="font-medium text-slate-900">{item.pasien}</div>
                                                                        <div className="text-[10px] text-slate-400">Dr. {item.dokter}</div>
                                                                    </td>
                                                                    <td className="px-4 py-3.5 text-slate-600">
                                                                        {item.tanggal}
                                                                    </td>
                                                                    <td className="px-4 py-3.5">
                                                                        <span className={`font-medium ${item.status !== 'Lunas' ? 'text-red-600' : 'text-slate-600'}`}>
                                                                            {item.jatuhTempo}
                                                                        </span>
                                                                    </td>
                                                                    <td className="px-4 py-3.5 text-right font-medium text-slate-700">
                                                                        {formatRupiah(item.total)}
                                                                    </td>
                                                                    <td className="px-4 py-3.5 text-right font-bold text-amber-700">
                                                                        {formatRupiah(item.sisa)}
                                                                    </td>
                                                                    <td className="px-4 py-3.5 text-center">
                                                                        <span
                                                                            className={`inline-block px-2.5 py-1 rounded-full text-[11px] font-semibold ${item.status === 'Lunas'
                                                                                ? 'bg-emerald-50 text-emerald-600'
                                                                                : item.status === 'Dibayar Sebagian'
                                                                                    ? 'bg-amber-50 text-amber-600'
                                                                                    : 'bg-red-50 text-red-600'
                                                                                }`}
                                                                        >
                                                                            {item.status}
                                                                        </span>
                                                                    </td>
                                                                    <td className="px-4 py-3.5 text-center">
                                                                        <div className="flex items-center justify-center gap-1.5">
                                                                            {item.sisa > 0 && (
                                                                                <button
                                                                                    onClick={() => openPiutangPaymentModal(item)}
                                                                                    className="px-2.5 py-1 text-[11px] font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 rounded-lg transition"
                                                                                    title="Terima Pembayaran"
                                                                                >
                                                                                    Terima
                                                                                </button>
                                                                            )}
                                                                            <button
                                                                                onClick={() => setSelectedPiutang(item)}
                                                                                className="px-2.5 py-1 text-[11px] font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition"
                                                                                title="Lihat Detil Tagihan"
                                                                            >
                                                                                Detil
                                                                            </button>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            );
                                                        })
                                                    ) : (
                                                        <tr>
                                                            <td colSpan={10} className="px-4 py-12 text-center text-slate-400">
                                                                <ShoppingCart className="w-8 h-8 mx-auto text-slate-300 mb-2" />
                                                                <p className="font-semibold text-slate-600 text-sm">Tidak ada transaksi piutang ditemukan</p>
                                                                <p className="text-xs text-slate-400 mt-0.5">Coba sesuaikan kata kunci pencarian atau filter status.</p>
                                                            </td>
                                                        </tr>
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>

                                        {/* Pagination Footer */}
                                        <div className="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 bg-slate-50/50">
                                            <span>
                                                Menampilkan {filteredPiutang.length > 0 ? (piutangCurrentPage - 1) * piutangItemsPerPage + 1 : 0} -{' '}
                                                {Math.min(piutangCurrentPage * piutangItemsPerPage, filteredPiutang.length)} dari {filteredPiutang.length} transaksi piutang
                                            </span>
                                            <div className="flex items-center gap-1">
                                                <button
                                                    onClick={() => setPiutangCurrentPage((p) => Math.max(1, p - 1))}
                                                    disabled={piutangCurrentPage === 1}
                                                    className="p-1.5 rounded-lg border border-slate-200 hover:bg-white disabled:opacity-40 transition"
                                                >
                                                    <ChevronLeft className="w-4 h-4" />
                                                </button>
                                                <span className="px-3 py-1 font-semibold text-slate-700">
                                                    Halaman {piutangCurrentPage} dari {totalPiutangPages}
                                                </span>
                                                <button
                                                    onClick={() => setPiutangCurrentPage((p) => Math.min(totalPiutangPages, p + 1))}
                                                    disabled={piutangCurrentPage === totalPiutangPages}
                                                    className="p-1.5 rounded-lg border border-slate-200 hover:bg-white disabled:opacity-40 transition"
                                                >
                                                    <ChevronRight className="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </>
                    )}

                    {activeTab === 'biaya' && (
                        <div className="bg-white p-12 rounded-2xl border border-slate-200 text-center text-slate-500 max-w-xl mx-auto">
                            <Receipt className="w-10 h-10 mx-auto text-slate-300 mb-2" />
                            <h3 className="text-base font-bold text-slate-800">Modul Biaya & Beban Operasional</h3>
                            <p className="text-xs mt-1">Pencatatan pengeluaran operasional apotek (listrik, gaji, sewa, perlengkapan).</p>
                        </div>
                    )}
                </div>
            </div>

            {/* ======================================================== */}
            {/* 3. MODAL BAYAR HUTANG (KREDIT)                           */}
            {/* ======================================================== */}
            {isPaymentModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in duration-200">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <CreditCard className="w-5 h-5 text-blue-600" />
                                <h3 className="font-bold text-slate-900 text-sm">Catat Pembayaran Hutang Dagang</h3>
                            </div>
                            <button
                                onClick={() => setIsPaymentModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <form onSubmit={handlePaymentSubmit} className="p-6 space-y-4 text-xs">
                            {/* Sumber Pembayaran: Akun Kategori Kas & Bank */}
                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Bayar Dari Akun (Kategori Kas & Bank) <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={paymentForm.account_id}
                                    onChange={(e) => setPaymentForm({ ...paymentForm, account_id: e.target.value })}
                                    required
                                    className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                >
                                    <option value="">-- Pilih Akun Kas & Bank --</option>
                                    {kasBankAccounts.map((acc) => (
                                        <option key={acc.id} value={acc.id}>
                                            {acc.name} ({acc.code}) {acc.account_number ? `- ${acc.account_number}` : ''}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-[11px] text-slate-400 mt-1">
                                    Hanya menampilkan akun dengan kategori <strong>Kas & Bank</strong> (ala Kledo).
                                </p>
                            </div>

                            {/* Tanggal & Nominal */}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Tanggal Bayar <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        value={paymentForm.payment_date}
                                        onChange={(e) => setPaymentForm({ ...paymentForm, payment_date: e.target.value })}
                                        required
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Jumlah Pembayaran (Rp) <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="number"
                                        min="1"
                                        value={paymentForm.amount}
                                        onChange={(e) => setPaymentForm({ ...paymentForm, amount: Number(e.target.value) })}
                                        required
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            {/* Nomor Referensi Bukti Transfer */}
                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Nomor Referensi / No. Transaksi Bank
                                </label>
                                <input
                                    type="text"
                                    placeholder="Contoh: TRF-BCA-20260922-001"
                                    value={paymentForm.reference_number}
                                    onChange={(e) => setPaymentForm({ ...paymentForm, reference_number: e.target.value })}
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                />
                            </div>

                            {/* Catatan / Keterangan */}
                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Catatan / Keterangan Pembayaran
                                </label>
                                <textarea
                                    rows={2}
                                    placeholder="Catatan tambahan untuk audit pembayaran..."
                                    value={paymentForm.notes}
                                    onChange={(e) => setPaymentForm({ ...paymentForm, notes: e.target.value })}
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                />
                            </div>

                            <div className="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setIsPaymentModalOpen(false)}
                                    className="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmittingPayment}
                                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50 flex items-center gap-1.5"
                                >
                                    {isSubmittingPayment ? 'Menyimpan...' : 'Konfirmasi Pembayaran'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ======================================================== */}
            {/* 4. MODAL PILIH AKUN PEMBELIAN CASH                       */}
            {/* ======================================================== */}
            {isCashModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in duration-200">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Banknote className="w-5 h-5 text-emerald-600" />
                                <div>
                                    <h3 className="font-bold text-slate-900 text-sm">Pilih Akun Pembayaran Cash</h3>
                                    <span className="text-[11px] text-slate-500">Faktur {cashForm.invoice_number} ({cashForm.vendor})</span>
                                </div>
                            </div>
                            <button
                                onClick={() => setIsCashModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <form onSubmit={handleCashSubmit} className="p-6 space-y-4 text-xs">
                            <div className="p-3.5 bg-emerald-50/60 border border-emerald-100 rounded-xl flex items-center justify-between text-emerald-900">
                                <span>Total Pembelian Tunai</span>
                                <span className="font-bold text-sm">{formatRupiah(cashForm.total)}</span>
                            </div>

                            {/* Pilihan Akun Kas & Bank */}
                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Metode / Akun Pembayaran (Kas & Bank) <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={cashForm.account_id}
                                    onChange={(e) => setCashForm({ ...cashForm, account_id: e.target.value })}
                                    required
                                    className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                >
                                    <option value="">-- Pilih Akun Pembayaran (BCA, Mandiri, BNI, BTN, Kas) --</option>
                                    {kasBankAccounts.map((acc) => (
                                        <option key={acc.id} value={acc.id}>
                                            {acc.name} ({acc.code}) {acc.account_number ? `- ${acc.account_number}` : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">Tanggal Transaksi</label>
                                    <input
                                        type="date"
                                        value={cashForm.payment_date}
                                        onChange={(e) => setCashForm({ ...cashForm, payment_date: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">No. Referensi Transfer</label>
                                    <input
                                        type="text"
                                        placeholder="Contoh: REF-CASH-001"
                                        value={cashForm.reference_number}
                                        onChange={(e) => setCashForm({ ...cashForm, reference_number: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div className="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setIsCashModalOpen(false)}
                                    className="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmittingCash}
                                    className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50"
                                >
                                    {isSubmittingCash ? 'Menyimpan...' : 'Simpan Akun Pembayaran'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ======================================================== */}
            {/* 5. MODAL TAMBAH / EDIT AKUN (PERSIS SCREENSHOT KLEDO)    */}
            {/* ======================================================== */}
            {isAccountModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in duration-200">
                        {/* Header Modal Persis Kledo */}
                        <div className="px-6 py-4 flex items-center justify-between">
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

                        <form onSubmit={handleAccountSubmit} className="p-6 pt-0 space-y-4 text-xs">
                            {/* Baris 1: Nama (ID) & Nama (EN) */}
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

                            {/* Hint Info Persis Kledo */}
                            <div className="p-3 bg-blue-50/70 border border-blue-100 rounded-xl flex items-center gap-2 text-blue-700 text-xs">
                                <HelpCircle className="w-4 h-4 shrink-0 text-blue-600" />
                                <span>Jika EN kosong, sistem otomatis menggunakan ID</span>
                            </div>

                            {/* Baris 2: Kode (Otomatis jika tidak diisi) */}
                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <label className="block text-slate-700 font-semibold">
                                        Kode <span className="text-[11px] font-normal text-slate-400">(Otomatis melanjutkan jika kosong)</span>
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
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-mono focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                />
                                <p className="text-[10px] text-slate-400 mt-1">
                                    Jika dikosongkan, sistem akan otomatis memberi nomor berikutnya sesuai kategori.
                                </p>
                            </div>

                            {/* Baris 3: Kategori (Dapat Langsung Ditambah dengan Mudah) */}
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
                                        <p className="text-[10px] text-blue-600">
                                            Kategori baru ini akan langsung disimpan dan muncul di daftar kategori.
                                        </p>
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

                            {/* Nomor Rekening (jika Kas & Bank) */}
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
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-mono focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>
                            )}

                            {/* Tombol Aksi Persis Kledo: X Batal dan + Tambah / Simpan */}
                            <div className="pt-4 flex items-center justify-end gap-3">
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
                                    {editingAccount ? (
                                        <Check className="w-3.5 h-3.5" />
                                    ) : (
                                        <Plus className="w-3.5 h-3.5" />
                                    )}
                                    <span>
                                        {isSubmittingAccount
                                            ? 'Menyimpan...'
                                            : editingAccount
                                                ? 'Simpan Perubahan'
                                                : 'Tambah'}
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ======================================================== */}
            {/* 6. MODAL KONFIRMASI HAPUS AKUN                           */}
            {/* ======================================================== */}
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

            {/* ======================================================== */}
            {/* 7. TOAST NOTIFIKASI PERINGATAN SELEKSI (LIMIT 10 ITEM)   */}
            {/* ======================================================== */}
            {selectionWarning && (
                <div className="fixed top-6 right-6 z-50 bg-amber-600 text-white px-4 py-3 rounded-2xl shadow-2xl flex items-center gap-2.5 text-xs font-semibold animate-in fade-in slide-in-from-top-4 duration-200 border border-amber-400">
                    <AlertCircle className="w-4 h-4 shrink-0 text-amber-200" />
                    <span>{selectionWarning}</span>
                    <button
                        type="button"
                        onClick={() => setSelectionWarning('')}
                        className="ml-2 hover:bg-amber-700/60 p-1 rounded-lg transition"
                    >
                        <X className="w-3.5 h-3.5" />
                    </button>
                </div>
            )}

            {/* ======================================================== */}
            {/* 8. FLOATING ACTION BAR: MULTI ATUR LUNAS (KREDIT)        */}
            {/* ======================================================== */}
            {activeTab === 'hutang' && selectedHutangIds.length > 0 && !selectedHutang && (
                <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-white/95 backdrop-blur-md border-2 border-blue-500 shadow-2xl rounded-2xl px-6 py-3.5 flex items-center gap-6 animate-in slide-in-from-bottom-5 duration-200">
                    <div className="flex items-center gap-3">
                        <div className="w-9 h-9 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold text-sm shadow-md">
                            {selectedHutangIds.length}
                        </div>
                        <div>
                            <div className="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                                <span>{selectedHutangIds.length} dari 10 Faktur Dipilih</span>
                                <span className="text-[10px] text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full font-semibold border border-blue-100">
                                    Maks. 10
                                </span>
                            </div>
                            <div className="text-xs text-slate-500">
                                Total Pelunasan:{' '}
                                <strong className="text-blue-600 font-bold">
                                    {formatRupiah(totalSelectedHutangSisa)}
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div className="h-8 w-px bg-slate-200" />

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setSelectedHutangIds([])}
                            className="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition"
                        >
                            Batalkan
                        </button>
                        <button
                            type="button"
                            onClick={openBulkPaymentModal}
                            className="inline-flex items-center gap-2 px-5 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 active:scale-95 rounded-xl shadow-lg shadow-blue-500/25 transition"
                        >
                            <CheckCircle2 className="w-4 h-4" />
                            <span>Atur Lunas ({selectedHutangIds.length} Faktur)</span>
                        </button>
                    </div>
                </div>
            )}

            {/* ======================================================== */}
            {/* 9. FLOATING ACTION BAR: MULTI ATUR AKUN (CASH)           */}
            {/* ======================================================== */}
            {activeTab === 'cash' && selectedCashIds.length > 0 && !selectedCash && (
                <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-white/95 backdrop-blur-md border-2 border-emerald-500 shadow-2xl rounded-2xl px-6 py-3.5 flex items-center gap-6 animate-in slide-in-from-bottom-5 duration-200">
                    <div className="flex items-center gap-3">
                        <div className="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-sm shadow-md">
                            {selectedCashIds.length}
                        </div>
                        <div>
                            <div className="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                                <span>{selectedCashIds.length} dari 10 Pembelian Dipilih</span>
                                <span className="text-[10px] text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full font-semibold border border-emerald-100">
                                    Maks. 10
                                </span>
                            </div>
                            <div className="text-xs text-slate-500">
                                Total Pembelian:{' '}
                                <strong className="text-emerald-600 font-bold">
                                    {formatRupiah(totalSelectedCashNominal)}
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div className="h-8 w-px bg-slate-200" />

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setSelectedCashIds([])}
                            className="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition"
                        >
                            Batalkan
                        </button>
                        <button
                            type="button"
                            onClick={openBulkCashModal}
                            className="inline-flex items-center gap-2 px-5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 active:scale-95 rounded-xl shadow-lg shadow-emerald-500/25 transition"
                        >
                            <Wallet className="w-4 h-4" />
                            <span>Atur Akun ({selectedCashIds.length} Pembelian)</span>
                        </button>
                    </div>
                </div>
            )}

            {/* ======================================================== */}
            {/* 10. FLOATING ACTION BAR: MULTI ATUR LUNAS (PIUTANG)      */}
            {/* ======================================================== */}
            {activeTab === 'piutang' && selectedPiutangIds.length > 0 && !selectedPiutang && (
                <div
                    style={{ borderColor: '#ffba2abf' }}
                    className="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-white/95 backdrop-blur-md border-2 border-amber-400 shadow-2xl rounded-2xl px-6 py-3.5 flex items-center gap-6 animate-in slide-in-from-bottom-5 duration-200"
                >
                    <div className="flex items-center gap-3">
                        <div className="w-9 h-9 rounded-xl bg-amber-600 text-white flex items-center justify-center font-bold text-sm shadow-md">
                            {selectedPiutangIds.length}
                        </div>
                        <div>
                            <div className="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                                <span>{selectedPiutangIds.length} dari 10 Tagihan Dipilih</span>
                                <span className="text-[10px] text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full font-semibold border border-amber-200">
                                    Maks. 10
                                </span>
                            </div>
                            <div className="text-xs text-slate-500">
                                Total Pelunasan:{' '}
                                <strong className="text-amber-700 font-bold">
                                    {formatRupiah(totalSelectedPiutangSisa)}
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div className="h-8 w-px bg-slate-200" />

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setSelectedPiutangIds([])}
                            className="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition"
                        >
                            Batalkan
                        </button>
                        <button
                            type="button"
                            onClick={openBulkPiutangModal}
                            className="inline-flex items-center gap-2 px-5 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 active:scale-95 rounded-xl shadow-lg shadow-amber-500/25 transition"
                        >
                            <CheckCircle2 className="w-4 h-4" />
                            <span>Atur Lunas ({selectedPiutangIds.length} Tagihan)</span>
                        </button>
                    </div>
                </div>
            )}

            {/* ======================================================== */}
            {/* 10. MODAL PELUNASAN MASSAL KREDIT (MAKSIMAL 10 FAKTUR)   */}
            {/* ======================================================== */}
            {isBulkPaymentModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-xl w-full overflow-hidden animate-in fade-in duration-200">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3 className="font-bold text-slate-900 text-base flex items-center gap-2">
                                    <CheckCircle2 className="w-5 h-5 text-blue-600" />
                                    <span>Pelunasan Massal ({selectedHutangIds.length} Faktur)</span>
                                </h3>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    Melunasi sisa tagihan kredit terpilih sekaligus menggunakan akun Kas & Bank.
                                </p>
                            </div>
                            <button
                                onClick={() => setIsBulkPaymentModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleBulkPaymentSubmit} className="p-6 space-y-4 text-xs">
                            {/* Ringkasan Faktur Terpilih */}
                            <div className="bg-slate-50 rounded-xl p-3 border border-slate-200 max-h-44 overflow-y-auto divide-y divide-slate-200/70">
                                <div className="text-[11px] font-semibold text-slate-500 pb-1.5 flex justify-between">
                                    <span>Faktur Terpilih ({selectedHutangItems.length})</span>
                                    <span>Sisa Tagihan</span>
                                </div>
                                {selectedHutangItems.map((item) => (
                                    <div key={item.id} className="py-1.5 flex items-center justify-between text-xs">
                                        <div>
                                            <span className="font-semibold text-slate-900">{item.nomor}</span>
                                            <span className="text-[11px] text-slate-500 ml-2">({item.vendor})</span>
                                        </div>
                                        <span className="font-bold text-slate-900">{formatRupiah(item.sisa)}</span>
                                    </div>
                                ))}
                            </div>

                            {/* Total Pelunasan */}
                            <div className="p-3.5 bg-blue-50/70 border border-blue-200 rounded-xl flex items-center justify-between">
                                <span className="font-semibold text-blue-900">Total Dana Pelunasan:</span>
                                <span className="text-lg font-bold text-blue-700">{formatRupiah(totalSelectedHutangSisa)}</span>
                            </div>

                            {/* Akun Kas & Bank */}
                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    <span className="text-red-500">*</span> Bayar Menggunakan Akun (Kas & Bank)
                                </label>
                                <select
                                    value={bulkPaymentForm.account_id}
                                    onChange={(e) => setBulkPaymentForm({ ...bulkPaymentForm, account_id: e.target.value })}
                                    required
                                    className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                >
                                    <option value="">-- Pilih Akun Kas & Bank --</option>
                                    {kasBankAccounts.map((acc) => (
                                        <option key={acc.id} value={acc.id}>
                                            {acc.code} - {acc.name} {acc.account_number ? `(${acc.account_number})` : ''} - Saldo: {formatRupiah(acc.balance)}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Tanggal & No Referensi */}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        <span className="text-red-500">*</span> Tanggal Pembayaran
                                    </label>
                                    <input
                                        type="date"
                                        value={bulkPaymentForm.payment_date}
                                        onChange={(e) => setBulkPaymentForm({ ...bulkPaymentForm, payment_date: e.target.value })}
                                        required
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        No. Referensi / Bukti Transfer
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Contoh: TRF-202609-001"
                                        value={bulkPaymentForm.reference_number}
                                        onChange={(e) => setBulkPaymentForm({ ...bulkPaymentForm, reference_number: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            {/* Catatan */}
                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Catatan Pembayaran (Opsional)
                                </label>
                                <input
                                    type="text"
                                    placeholder="Contoh: Pelunasan faktur tempo batch..."
                                    value={bulkPaymentForm.notes}
                                    onChange={(e) => setBulkPaymentForm({ ...bulkPaymentForm, notes: e.target.value })}
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                />
                            </div>

                            {/* Footer Buttons */}
                            <div className="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
                                <button
                                    type="button"
                                    onClick={() => setIsBulkPaymentModalOpen(false)}
                                    className="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmittingBulkPayment}
                                    className="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50 flex items-center gap-1.5 shadow-sm"
                                >
                                    <CheckCircle2 className="w-3.5 h-3.5" />
                                    <span>{isSubmittingBulkPayment ? 'Memproses...' : `Konfirmasi Pelunasan (${selectedHutangIds.length} Faktur)`}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ======================================================== */}
            {/* 11. MODAL ATUR AKUN MASSAL (PEMBELIAN CASH, MAKS. 10)    */}
            {/* ======================================================== */}
            {isBulkCashModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-xl w-full overflow-hidden animate-in fade-in duration-200">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3 className="font-bold text-slate-900 text-base flex items-center gap-2">
                                    <Wallet className="w-5 h-5 text-emerald-600" />
                                    <span>Atur Akun Pembayaran Massal ({selectedCashIds.length} Pembelian)</span>
                                </h3>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    Tetapkan rekening Kas & Bank untuk transaksi pembelian tunai terpilih.
                                </p>
                            </div>
                            <button
                                onClick={() => setIsBulkCashModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleBulkCashSubmit} className="p-6 space-y-4 text-xs">
                            {/* Ringkasan Pembelian Cash Terpilih */}
                            <div className="bg-slate-50 rounded-xl p-3 border border-slate-200 max-h-44 overflow-y-auto divide-y divide-slate-200/70">
                                <div className="text-[11px] font-semibold text-slate-500 pb-1.5 flex justify-between">
                                    <span>Pembelian Terpilih ({selectedCashItems.length})</span>
                                    <span>Total Transaksi</span>
                                </div>
                                {selectedCashItems.map((item) => (
                                    <div key={item.id} className="py-1.5 flex items-center justify-between text-xs">
                                        <div>
                                            <span className="font-semibold text-slate-900">{item.nomor}</span>
                                            <span className="text-[11px] text-slate-500 ml-2">({item.vendor})</span>
                                        </div>
                                        <span className="font-bold text-slate-900">{formatRupiah(item.total)}</span>
                                    </div>
                                ))}
                            </div>

                            {/* Total Transaksi */}
                            <div className="p-3.5 bg-emerald-50/70 border border-emerald-200 rounded-xl flex items-center justify-between">
                                <span className="font-semibold text-emerald-900">Total Nominal Pembelian:</span>
                                <span className="text-lg font-bold text-emerald-700">{formatRupiah(totalSelectedCashNominal)}</span>
                            </div>

                            {/* Akun Kas & Bank */}
                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    <span className="text-red-500">*</span> Tetapkan Akun Pembayaran (Kas & Bank)
                                </label>
                                <select
                                    value={bulkCashForm.account_id}
                                    onChange={(e) => setBulkCashForm({ ...bulkCashForm, account_id: e.target.value })}
                                    required
                                    className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                >
                                    <option value="">-- Pilih Akun Kas & Bank --</option>
                                    {kasBankAccounts.map((acc) => (
                                        <option key={acc.id} value={acc.id}>
                                            {acc.code} - {acc.name} {acc.account_number ? `(${acc.account_number})` : ''} - Saldo: {formatRupiah(acc.balance)}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Tanggal & No Referensi */}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Tanggal Pembayaran
                                    </label>
                                    <input
                                        type="date"
                                        value={bulkCashForm.payment_date}
                                        onChange={(e) => setBulkCashForm({ ...bulkCashForm, payment_date: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        No. Referensi / No. Transaksi
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Contoh: CASH-BCA-001"
                                        value={bulkCashForm.reference_number}
                                        onChange={(e) => setBulkCashForm({ ...bulkCashForm, reference_number: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            {/* Catatan */}
                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Catatan (Opsional)
                                </label>
                                <input
                                    type="text"
                                    placeholder="Contoh: Pembelian tunai dibayar via..."
                                    value={bulkCashForm.notes}
                                    onChange={(e) => setBulkCashForm({ ...bulkCashForm, notes: e.target.value })}
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                />
                            </div>

                            {/* Footer Buttons */}
                            <div className="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
                                <button
                                    type="button"
                                    onClick={() => setIsBulkCashModalOpen(false)}
                                    className="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmittingBulkCash}
                                    className="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50 flex items-center gap-1.5 shadow-sm"
                                >
                                    <Check className="w-3.5 h-3.5" />
                                    <span>{isSubmittingBulkCash ? 'Menyimpan...' : `Terapkan Akun (${selectedCashIds.length} Pembelian)`}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ======================================================== */}
            {/* 12. MODAL TERIMA PEMBAYARAN PIUTANG (SINGLE)             */}
            {/* ======================================================== */}
            {isPiutangPaymentModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in duration-200">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <CreditCard className="w-5 h-5 text-amber-600" />
                                <h3 className="font-bold text-slate-900 text-sm">Terima Pembayaran Piutang Penjualan</h3>
                            </div>
                            <button
                                onClick={() => setIsPiutangPaymentModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <form onSubmit={handlePiutangPaymentSubmit} className="p-6 space-y-4 text-xs">
                            {/* Info Tagihan */}
                            <div className="bg-amber-50/70 rounded-xl p-3.5 border border-amber-200 space-y-1">
                                <div className="flex justify-between text-slate-700">
                                    <span className="font-medium">No. Transaksi / Resep:</span>
                                    <span className="font-bold text-slate-900">{piutangPaymentForm.invoice_number}</span>
                                </div>
                                <div className="flex justify-between text-slate-700">
                                    <span className="font-medium">Pelanggan / Debitur:</span>
                                    <span className="font-semibold text-amber-900">{piutangPaymentForm.debtor}</span>
                                </div>
                                <div className="flex justify-between text-slate-700 pt-1 border-t border-amber-200/60">
                                    <span className="font-medium">Total Tagihan:</span>
                                    <span className="font-bold text-slate-900">{formatRupiah(piutangPaymentForm.total)}</span>
                                </div>
                                <div className="flex justify-between text-slate-700">
                                    <span className="font-semibold text-amber-800">Sisa Piutang:</span>
                                    <span className="font-extrabold text-amber-700 text-sm">{formatRupiah(piutangPaymentForm.sisa)}</span>
                                </div>
                            </div>

                            {/* Akun Kas & Bank Penerima */}
                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Terima ke Akun (Kas & Bank) <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={piutangPaymentForm.account_id}
                                    onChange={(e) => setPiutangPaymentForm({ ...piutangPaymentForm, account_id: e.target.value })}
                                    required
                                    className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                >
                                    <option value="">-- Pilih Akun Kas & Bank --</option>
                                    {kasBankAccounts.map((acc) => (
                                        <option key={acc.id} value={acc.id}>
                                            {acc.code} - {acc.name} {acc.account_number ? `(${acc.account_number})` : ''} - Saldo: {formatRupiah(acc.balance)}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Jumlah Pembayaran */}
                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Jumlah Diterima (Rp) <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    max={piutangPaymentForm.sisa}
                                    value={piutangPaymentForm.amount}
                                    onChange={(e) => setPiutangPaymentForm({ ...piutangPaymentForm, amount: parseFloat(e.target.value) || 0 })}
                                    required
                                    className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-amber-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                                <div className="flex items-center justify-between mt-1 text-[11px] text-slate-400">
                                    <span>Maksimal: {formatRupiah(piutangPaymentForm.sisa)}</span>
                                    <button
                                        type="button"
                                        onClick={() => setPiutangPaymentForm({ ...piutangPaymentForm, amount: piutangPaymentForm.sisa })}
                                        className="text-amber-600 hover:underline font-semibold"
                                    >
                                        Set Lunas Penuh
                                    </button>
                                </div>
                            </div>

                            {/* Tanggal & No Referensi */}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Tanggal Penerimaan
                                    </label>
                                    <input
                                        type="date"
                                        value={piutangPaymentForm.payment_date}
                                        onChange={(e) => setPiutangPaymentForm({ ...piutangPaymentForm, payment_date: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        No. Referensi / Bukti
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Contoh: BKT-2026-001"
                                        value={piutangPaymentForm.reference_number}
                                        onChange={(e) => setPiutangPaymentForm({ ...piutangPaymentForm, reference_number: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            {/* Catatan */}
                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Catatan Pembayaran
                                </label>
                                <input
                                    type="text"
                                    placeholder="Contoh: Pembayaran cicilan dari pelanggan..."
                                    value={piutangPaymentForm.notes}
                                    onChange={(e) => setPiutangPaymentForm({ ...piutangPaymentForm, notes: e.target.value })}
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            {/* Footer Buttons */}
                            <div className="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
                                <button
                                    type="button"
                                    onClick={() => setIsPiutangPaymentModalOpen(false)}
                                    className="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmittingPiutangPayment}
                                    className="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50 flex items-center gap-1.5 shadow-sm"
                                >
                                    <Check className="w-3.5 h-3.5" />
                                    <span>{isSubmittingPiutangPayment ? 'Memproses...' : 'Konfirmasi Penerimaan'}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ======================================================== */}
            {/* 13. MODAL PELUNASAN MASSAL PIUTANG (MULTI, MAKS. 10)     */}
            {/* ======================================================== */}
            {isBulkPiutangModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-xl w-full overflow-hidden animate-in fade-in duration-200">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3 className="font-bold text-slate-900 text-base flex items-center gap-2">
                                    <CheckCircle2 className="w-5 h-5 text-amber-600" />
                                    <span>Pelunasan Massal Piutang ({selectedPiutangIds.length} Tagihan)</span>
                                </h3>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    Lunasi sekaligus hingga 10 transaksi piutang penjualan ke rekening Kas & Bank.
                                </p>
                            </div>
                            <button
                                onClick={() => setIsBulkPiutangModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleBulkPiutangSubmit} className="p-6 space-y-4 text-xs">
                            {/* Ringkasan Tagihan Terpilih */}
                            <div className="bg-slate-50 rounded-xl p-3 border border-slate-200 max-h-44 overflow-y-auto divide-y divide-slate-200/70">
                                <div className="text-[11px] font-semibold text-slate-500 pb-1.5 flex justify-between">
                                    <span>Transaksi Terpilih ({selectedPiutangItems.length})</span>
                                    <span>Sisa Tagihan</span>
                                </div>
                                {selectedPiutangItems.map((item) => (
                                    <div key={item.id} className="py-1.5 flex items-center justify-between text-xs">
                                        <div>
                                            <span className="font-semibold text-slate-900">{item.nomor}</span>
                                            <span className="text-[11px] text-slate-500 ml-2">({item.debtor})</span>
                                        </div>
                                        <span className="font-bold text-amber-700">{formatRupiah(item.sisa)}</span>
                                    </div>
                                ))}
                            </div>

                            {/* Total Pelunasan */}
                            <div className="p-3.5 bg-amber-50/80 border border-amber-200 rounded-xl flex items-center justify-between">
                                <span className="font-semibold text-amber-950">Total Dana Diterima (Pelunasan):</span>
                                <span className="text-lg font-bold text-amber-700">{formatRupiah(totalSelectedPiutangSisa)}</span>
                            </div>

                            {/* Akun Kas & Bank Penerima */}
                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    <span className="text-red-500">*</span> Terima ke Akun (Kas & Bank)
                                </label>
                                <select
                                    value={bulkPiutangForm.account_id}
                                    onChange={(e) => setBulkPiutangForm({ ...bulkPiutangForm, account_id: e.target.value })}
                                    required
                                    className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                >
                                    <option value="">-- Pilih Akun Kas & Bank --</option>
                                    {kasBankAccounts.map((acc) => (
                                        <option key={acc.id} value={acc.id}>
                                            {acc.code} - {acc.name} {acc.account_number ? `(${acc.account_number})` : ''} - Saldo: {formatRupiah(acc.balance)}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Tanggal & No Referensi */}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Tanggal Pembayaran
                                    </label>
                                    <input
                                        type="date"
                                        value={bulkPiutangForm.payment_date}
                                        onChange={(e) => setBulkPiutangForm({ ...bulkPiutangForm, payment_date: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        No. Referensi / Bukti Transfer
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Contoh: PIUTANG-MASSAL-001"
                                        value={bulkPiutangForm.reference_number}
                                        onChange={(e) => setBulkPiutangForm({ ...bulkPiutangForm, reference_number: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            {/* Catatan */}
                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Catatan Pembayaran (Opsional)
                                </label>
                                <input
                                    type="text"
                                    placeholder="Contoh: Pelunasan piutang debitur kolektif..."
                                    value={bulkPiutangForm.notes}
                                    onChange={(e) => setBulkPiutangForm({ ...bulkPiutangForm, notes: e.target.value })}
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            {/* Footer Buttons */}
                            <div className="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
                                <button
                                    type="button"
                                    onClick={() => setIsBulkPiutangModalOpen(false)}
                                    className="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmittingBulkPiutang}
                                    className="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50 flex items-center gap-1.5 shadow-sm"
                                >
                                    <CheckCircle2 className="w-3.5 h-3.5" />
                                    <span>{isSubmittingBulkPiutang ? 'Memproses...' : `Konfirmasi Pelunasan (${selectedPiutangIds.length} Tagihan)`}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
