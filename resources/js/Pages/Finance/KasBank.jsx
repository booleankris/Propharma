import React, { useState, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import TransactionCategory from './Components/TransactionCategory';
import CashFlowChart from './Components/CashFlowChart';
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
    Save,
    HelpCircle,
    History,
    SlidersHorizontal,
    Scale,
    Clock,
    UserCheck,
    AlertCircle,
    Calendar,
} from 'lucide-react';

const MONTH_OPTIONS = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];

const localToday = () => {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};

const formatDepositAmount = (value) => {
    const cleaned = String(value ?? '').replace(/[^\d,]/g, '');
    const [whole = '', ...fractionParts] = cleaned.split(',');
    const fraction = fractionParts.join('').slice(0, 2);
    const formattedWhole = whole.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return fractionParts.length ? `${formattedWhole},${fraction}` : formattedWhole;
};

const parseDepositAmount = (value) => String(value ?? '').replace(/\./g, '').replace(',', '.');

export default function KasBank({
    accounts = [],
    categories = [],
    mutasiKasBank = [],
    stats = {},
    openingBalances = {},
    monthlyOpeningBalances = {},
    adjustmentLogs = [],
    selectedMonth: propMonth,
    selectedYear: propYear,
    monthLabel: propMonthLabel,
}) {
    const now = new Date();
    const selectedMonth = Number(propMonth || (now.getMonth() + 1));
    const selectedYear = Number(propYear || now.getFullYear());
    const currentYear = now.getFullYear();
    const yearOptions = Array.from({ length: 6 }, (_, i) => currentYear - 4 + i);
    const [isFilteringPeriod, setIsFilteringPeriod] = useState(false);

    const monthLabel = propMonthLabel || `${MONTH_OPTIONS[selectedMonth - 1]?.label || ''} ${selectedYear}`;

    const handlePeriodChange = (nextMonth, nextYear) => {
        setIsFilteringPeriod(true);
        router.get(
            '/finance/kas-bank',
            { month: nextMonth, year: nextYear },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setIsFilteringPeriod(false),
            }
        );
    };

    // State Filter & Search Mutasi
    const [selectedKasBankFilter, setSelectedKasBankFilter] = useState('all'); // 'all' | account_id
    const [mutasiTypeFilter, setMutasiTypeFilter] = useState('all'); // 'all' | 'IN' | 'OUT'
    const [mutasiSearchQuery, setMutasiSearchQuery] = useState('');
    const [mutasiCurrentPage, setMutasiCurrentPage] = useState(1);
    const mutasiItemsPerPage = 15;

    // State Account Modal (Khusus Kas & Bank)
    const [isAccountModalOpen, setIsAccountModalOpen] = useState(false);
    const [isSubmittingAccount, setIsSubmittingAccount] = useState(false);
    const [editingAccount, setEditingAccount] = useState(null);
    const [deletingAccount, setDeletingAccount] = useState(null);
    const [isDeletingAccount, setIsDeletingAccount] = useState(false);

    // State Opname / Penyesuaian Saldo Riil
    const [isOpnameModalOpen, setIsOpnameModalOpen] = useState(false);
    const [opnameMode, setOpnameMode] = useState('bulk'); // 'bulk' | 'single'
    const [opnameTargetAccountId, setOpnameTargetAccountId] = useState('');
    const [opnameDate, setOpnameDate] = useState(localToday());
    const [opnameNotes, setOpnameNotes] = useState('');
    const [opnameValues, setOpnameValues] = useState({});
    const [opnameNotesMap, setOpnameNotesMap] = useState({});
    const [isSubmittingOpname, setIsSubmittingOpname] = useState(false);

    // State Log Riwayat Opname
    const [isHistoryModalOpen, setIsHistoryModalOpen] = useState(false);
    const [historySearchQuery, setHistorySearchQuery] = useState('');
    const [historyAccountFilter, setHistoryAccountFilter] = useState('all');

    const [accountForm, setAccountForm] = useState({
        name: '',
        name_en: '',
        code: '',
        category: 'Kas & Bank',
        account_type: 'bank',
        account_number: '',
    });
    const [openingForm, setOpeningForm] = useState(() => {
        const accountId = accounts[0]?.id || '';
        const saved = openingBalances[accountId];
        return {
            account_id: accountId,
            payment_date: saved?.payment_date || localToday(),
            amount: saved?.amount ?? '',
            notes: saved?.notes || '',
        };
    });
    const [isSavingOpening, setIsSavingOpening] = useState(false);
    const [isOpeningModalOpen, setIsOpeningModalOpen] = useState(false);
    const [isDepositModalOpen, setIsDepositModalOpen] = useState(false);
    const [isSavingDeposit, setIsSavingDeposit] = useState(false);
    const [depositForm, setDepositForm] = useState({ transfer_account_id: '', payment_date: localToday(), amount: '', reference_number: '', notes: 'Setoran tunai kasir ke rekening bank' });
    const [isWithdrawModalOpen, setIsWithdrawModalOpen] = useState(false);
    const [isSavingWithdraw, setIsSavingWithdraw] = useState(false);
    const [withdrawForm, setWithdrawForm] = useState({ account_id: '', payment_date: localToday(), amount: '', reference_number: '', notes: 'Tarik tunai dari rekening bank ke kas apotek' });
    const [isBackfillModalOpen, setIsBackfillModalOpen] = useState(false);
    const [isBackfillingSales, setIsBackfillingSales] = useState(false);
    const [backfillForm, setBackfillForm] = useState({ from_date: '', to_date: localToday() });
    const [isBackfillNonCashModalOpen, setIsBackfillNonCashModalOpen] = useState(false);
    const [isBackfillingNonCash, setIsBackfillingNonCash] = useState(false);
    const [backfillNonCashForm, setBackfillNonCashForm] = useState({ from_date: '', to_date: localToday() });
    const selectedOpening = openingBalances[openingForm.account_id];

    const submitDeposit = (event) => {
        event.preventDefault();
        if (!depositForm.transfer_account_id || !depositForm.amount) return alert('Pilih rekening tujuan dan masukkan nominal setoran.');
        setIsSavingDeposit(true);
        router.post('/finance/setoran', { ...depositForm, amount: parseDepositAmount(depositForm.amount) }, {
            preserveScroll: true,
            onSuccess: () => setIsDepositModalOpen(false),
            onFinish: () => setIsSavingDeposit(false),
            onError: (errors) => alert(Object.values(errors)[0] || 'Gagal mencatat setoran.'),
        });
    };

    const submitWithdraw = (event) => {
        event.preventDefault();
        if (!withdrawForm.account_id || !withdrawForm.amount) return alert('Pilih rekening bank sumber dan masukkan nominal penarikan.');
        setIsSavingWithdraw(true);
        router.post('/finance/tarik-tunai', { ...withdrawForm, amount: parseDepositAmount(withdrawForm.amount) }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsWithdrawModalOpen(false);
                setWithdrawForm({ account_id: '', payment_date: localToday(), amount: '', reference_number: '', notes: 'Tarik tunai dari rekening bank ke kas apotek' });
            },
            onFinish: () => setIsSavingWithdraw(false),
            onError: (errors) => alert(Object.values(errors)[0] || 'Gagal mencatat penarikan tunai.'),
        });
    };

    const openWithdrawModal = (targetAccount = null) => {
        const source = targetAccount?.account_type === 'bank'
            ? targetAccount
            : kasBankAccounts.find((account) => account.account_type === 'bank');
        setWithdrawForm({
            account_id: source?.id || '',
            payment_date: localToday(),
            amount: '',
            reference_number: '',
            notes: 'Tarik tunai dari rekening bank ke kas apotek',
        });
        setIsWithdrawModalOpen(true);
    };

    const submitOpening = (event) => {
        event.preventDefault();
        if (!openingForm.account_id || openingForm.amount === '') return alert('Pilih akun Kas & Bank dan masukkan saldo awal.');
        setIsSavingOpening(true);
        router.post('/finance/saldo-awal', openingForm, {
            preserveScroll: true,
            onFinish: () => setIsSavingOpening(false),
            onError: (errors) => alert(Object.values(errors)[0] || 'Gagal menyimpan saldo awal.'),
        });
    };

    const submitBackfill = (event) => {
        event.preventDefault();
        if (!backfillForm.from_date || !backfillForm.to_date) return alert('Pilih rentang tanggal penjualan yang akan dicatat.');
        const confirmed = window.confirm(`Catat penjualan tunai selesai yang belum ada di Kas & Bank untuk ${backfillForm.from_date} sampai ${backfillForm.to_date}? Transaksi yang sudah tercatat akan dilewati.`);
        if (!confirmed) return;

        setIsBackfillingSales(true);
        router.post('/finance/kas-bank/backfill-penjualan-tunai', backfillForm, {
            preserveScroll: true,
            onSuccess: () => setIsBackfillModalOpen(false),
            onFinish: () => setIsBackfillingSales(false),
            onError: (errors) => alert(Object.values(errors)[0] || 'Gagal mencatat penjualan tunai lama.'),
        });
    };

    const submitBackfillNonCash = (event) => {
        event.preventDefault();
        if (!backfillNonCashForm.from_date || !backfillNonCashForm.to_date) return alert('Pilih rentang tanggal transaksi non-tunai yang akan dicatat.');
        const confirmed = window.confirm(`Catat transaksi QRIS, Debit, dan Transfer lama yang belum ada di rekening bank untuk ${backfillNonCashForm.from_date} sampai ${backfillNonCashForm.to_date}? Rekening tujuan dicocokkan dari nama bank pada transaksi.`);
        if (!confirmed) return;

        setIsBackfillingNonCash(true);
        router.post('/finance/kas-bank/backfill-penjualan-non-tunai', backfillNonCashForm, {
            preserveScroll: true,
            onSuccess: () => setIsBackfillNonCashModalOpen(false),
            onFinish: () => setIsBackfillingNonCash(false),
            onError: (errors) => alert(Object.values(errors)[0] || 'Gagal mencatat transaksi non-tunai lama.'),
        });
    };

    const openOpnameModal = (targetAccount = null) => {
        if (targetAccount) {
            setOpnameMode('single');
            setOpnameTargetAccountId(String(targetAccount.id));
            setOpnameValues({
                [targetAccount.id]: formatDepositAmount(targetAccount.balance ?? 0),
            });
            setOpnameNotesMap({});
        } else {
            setOpnameMode('bulk');
            const initialValues = {};
            const initialNotes = {};
            kasBankAccounts.forEach((acc) => {
                initialValues[acc.id] = formatDepositAmount(acc.balance ?? 0);
                initialNotes[acc.id] = '';
            });
            setOpnameValues(initialValues);
            setOpnameNotesMap(initialNotes);
            if (kasBankAccounts.length > 0) {
                setOpnameTargetAccountId(String(kasBankAccounts[0].id));
            }
        }
        setOpnameDate(localToday());
        setOpnameNotes('');
        setIsOpnameModalOpen(true);
    };

    const handleOpnameSubmit = (e) => {
        e.preventDefault();
        if (opnameMode === 'single') {
            const rawVal = opnameValues[opnameTargetAccountId];
            if (rawVal === undefined || rawVal === '') {
                alert('Silakan masukkan nominal saldo riil yang valid.');
                return;
            }
            const cleanVal = parseDepositAmount(rawVal);
            if (isNaN(Number(cleanVal)) || Number(cleanVal) < 0) {
                alert('Nominal saldo riil tidak boleh bernilai negatif.');
                return;
            }

            const targetAcc = kasBankAccounts.find((a) => String(a.id) === String(opnameTargetAccountId));
            const diff = Number(cleanVal) - Number(targetAcc?.balance || 0);
            const diffFmt = (diff >= 0 ? '+' : '-') + formatRupiah(Math.abs(diff));
            const confirmMsg = `Sesuaikan saldo ${targetAcc?.name} dari ${formatRupiah(targetAcc?.balance || 0)} menjadi ${formatRupiah(Number(cleanVal))} (Selisih: ${diffFmt})?\n\nPerubahan ini akan langsung dicatat di Riwayat Log Opname.`;
            if (!window.confirm(confirmMsg)) return;

            setIsSubmittingOpname(true);
            router.post('/finance/kas-bank/adjust-balance', {
                account_id: opnameTargetAccountId,
                real_balance: Number(cleanVal),
                adjustment_date: opnameDate,
                notes: opnameNotes,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpnameModalOpen(false);
                },
                onFinish: () => setIsSubmittingOpname(false),
                onError: (errs) => alert(Object.values(errs)[0] || 'Gagal menyesuaikan saldo.'),
            });
        } else {
            const payloadAdjustments = kasBankAccounts.map((acc) => {
                const rawVal = opnameValues[acc.id];
                const cleanVal = rawVal !== undefined && rawVal !== ''
                    ? Number(parseDepositAmount(rawVal))
                    : Number(acc.balance || 0);
                return {
                    account_id: acc.id,
                    real_balance: cleanVal,
                    notes: opnameNotesMap[acc.id] || opnameNotes || null,
                };
            });

            const changedCount = payloadAdjustments.filter((item) => {
                const acc = kasBankAccounts.find((a) => a.id === item.account_id);
                return Math.abs(Number(item.real_balance) - Number(acc?.balance || 0)) >= 0.01;
            }).length;

            const confirmMsg = changedCount > 0
                ? `Simpan penyesuaian saldo riil untuk ${changedCount} rekening yang mengalami perubahan saldo?\n\nRiwayat lengkap perubahan dan nama staf akan dicatat di Log Opname.`
                : 'Simpan penyesuaian saldo riil untuk seluruh rekening kas & bank?';
            if (!window.confirm(confirmMsg)) return;

            setIsSubmittingOpname(true);
            router.post('/finance/kas-bank/adjust-balance', {
                adjustments: payloadAdjustments,
                adjustment_date: opnameDate,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpnameModalOpen(false);
                },
                onFinish: () => setIsSubmittingOpname(false),
                onError: (errs) => alert(Object.values(errs)[0] || 'Gagal menyesuaikan saldo.'),
            });
        }
    };

    const filteredAdjustmentLogs = useMemo(() => {
        return (adjustmentLogs || []).filter((log) => {
            if (historyAccountFilter !== 'all' && String(log.account_id) !== String(historyAccountFilter)) {
                return false;
            }
            if (historySearchQuery.trim()) {
                const q = historySearchQuery.toLowerCase();
                const accName = String(log.account_name || '').toLowerCase();
                const userName = String(log.user_name || '').toLowerCase();
                const notes = String(log.notes || '').toLowerCase();
                const date = String(log.adjustment_date || '').toLowerCase();
                if (!accName.includes(q) && !userName.includes(q) && !notes.includes(q) && !date.includes(q)) {
                    return false;
                }
            }
            return true;
        });
    }, [adjustmentLogs, historyAccountFilter, historySearchQuery]);

    const chooseOpeningAccount = (accountId) => {
        const saved = openingBalances[accountId];
        setOpeningForm({
            account_id: accountId,
            payment_date: saved?.payment_date || localToday(),
            amount: saved?.amount ?? '',
            notes: saved?.notes || '',
        });
    };

    const getNextCodeForKasBank = () => {
        const prefix = '1-100';
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

    const openAccountModal = () => {
        setEditingAccount(null);
        const nextCode = getNextCodeForKasBank();
        setAccountForm({
            name: '',
            name_en: '',
            code: nextCode,
            category: 'Kas & Bank',
            account_type: 'bank',
            account_number: '',
        });
        setIsAccountModalOpen(true);
    };

    const openEditAccountModal = (acc) => {
        setEditingAccount(acc);
        setAccountForm({
            name: acc.name || '',
            name_en: acc.name_en || '',
            code: acc.code || '',
            category: 'Kas & Bank',
            account_type: acc.account_type || (acc.code === '1-10001' ? 'kas_apotek' : 'bank'),
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

    const handleAccountSubmit = (e) => {
        e.preventDefault();
        if (!accountForm.name) {
            alert('Nama akun kas / rekening bank wajib diisi.');
            return;
        }

        setIsSubmittingAccount(true);
        const url = editingAccount ? `/finance/accounts/${editingAccount.id}` : '/finance/accounts';
        const method = editingAccount ? 'put' : 'post';

        router[method](url, {
            ...accountForm,
            category: 'Kas & Bank',
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsAccountModalOpen(false);
                setIsSubmittingAccount(false);
                setEditingAccount(null);
                setAccountForm({
                    name: '',
                    name_en: '',
                    code: '',
                    category: 'Kas & Bank',
                    account_type: 'bank',
                    account_number: '',
                });
            },
            onError: (errors) => {
                setIsSubmittingAccount(false);
                alert(Object.values(errors)[0] || 'Gagal memproses data akun kas/bank.');
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
            } else if (m.direction === 'OUT') {
                totalKeluar += amt;
                if (accountStats[m.account_id]) {
                    accountStats[m.account_id].outflow += amt;
                    accountStats[m.account_id].txCount += 1;
                }
            } else if (m.direction === 'OPENING' && accountStats[m.account_id]) {
                accountStats[m.account_id].txCount += 1;
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
            subtitle="Kelola rekening kas & bank dan mutasi buku kas apotek"
            stats={stats}
        >
            <Head title="Kas & Bank - Keuangan Apotek" />

            <div className="finance-kas-bank space-y-6">
                {/* Header Action Toolbar */}
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3 bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                    <div>
                        <div className="flex items-center gap-2">
                            <h2 className="text-base font-bold text-slate-900">Buku Kas & Rekening Bank</h2>
                            {isFilteringPeriod && (
                                <span className="text-[11px] text-blue-600 font-medium animate-pulse">
                                    Memuat...
                                </span>
                            )}
                        </div>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Total {kasBankAccounts.length} rekening aktif • Periode Mutasi: <strong className="text-blue-700 font-semibold">{monthLabel}</strong> (Reset tiap bulan)
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {/* Filter Periode Bulan & Tahun (Otomatis Reset Tiap Awal Bulan) */}
                        <div className="flex items-center gap-1.5 bg-slate-50 p-1 rounded-xl border border-slate-200">
                            <Calendar className="w-3.5 h-3.5 text-slate-400 ml-1.5" />
                            <select
                                value={selectedMonth}
                                onChange={(e) => handlePeriodChange(Number(e.target.value), selectedYear)}
                                className="px-2 py-1 text-xs font-semibold bg-white border border-slate-200 rounded-lg text-slate-700 hover:bg-slate-50 focus:outline-hidden focus:border-blue-500 cursor-pointer shadow-2xs"
                                aria-label="Pilih Bulan Mutasi"
                            >
                                {MONTH_OPTIONS.map((m) => (
                                    <option key={m.value} value={m.value}>
                                        {m.label}
                                    </option>
                                ))}
                            </select>

                            <select
                                value={selectedYear}
                                onChange={(e) => handlePeriodChange(selectedMonth, Number(e.target.value))}
                                className="px-2 py-1 text-xs font-semibold bg-white border border-slate-200 rounded-lg text-slate-700 hover:bg-slate-50 focus:outline-hidden focus:border-blue-500 cursor-pointer shadow-2xs"
                                aria-label="Pilih Tahun Mutasi"
                            >
                                {yearOptions.map((y) => (
                                    <option key={y} value={y}>
                                        {y}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <button
                            type="button"
                            onClick={() => openOpnameModal(null)}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-emerald-800 bg-emerald-100/80 border border-emerald-300 rounded-xl hover:bg-emerald-200/80 transition shadow-xs"
                        >
                            <SlidersHorizontal className="w-4 h-4 text-emerald-700" />
                            <span>Sesuaikan Saldo Riil (Opname)</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setIsHistoryModalOpen(true)}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition shadow-xs"
                        >
                            <History className="w-4 h-4 text-indigo-600" />
                            <span>Log Opname ({adjustmentLogs.length})</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setBackfillForm({ from_date: '', to_date: localToday() });
                                setIsBackfillModalOpen(true);
                            }}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition shadow-xs"
                        >
                            <History className="w-4 h-4" />
                            <span>Catat Penjualan Lama</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setBackfillNonCashForm({ from_date: '', to_date: localToday() });
                                setIsBackfillNonCashModalOpen(true);
                            }}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-violet-700 bg-violet-50 border border-violet-200 rounded-xl hover:bg-violet-100 transition shadow-xs"
                        >
                            <History className="w-4 h-4" />
                            <span>Catat Non-Tunai Lama</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                const destination = kasBankAccounts.find((account) => account.account_type === 'bank');
                                setDepositForm({ transfer_account_id: destination?.id || '', payment_date: localToday(), amount: '', reference_number: '', notes: 'Setoran tunai kasir ke rekening bank' });
                                setIsDepositModalOpen(true);
                            }}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl hover:bg-emerald-100 transition shadow-xs"
                        >
                            <ArrowUpRight className="w-4 h-4" />
                            <span>Setor Kas ke Bank</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => openWithdrawModal(null)}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-xl hover:bg-amber-100 transition shadow-xs"
                        >
                            <ArrowDownLeft className="w-4 h-4" />
                            <span>Tarik Tunai ke Kas</span>
                        </button>
                        <Link
                            href="/finance/accounts"
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 hover:text-blue-600 transition shadow-xs"
                        >
                            <Layers className="w-4 h-4 text-blue-600" />
                            <span>Bagan Akun (COA)</span>
                        </Link>
                        <button
                            onClick={openAccountModal}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm"
                        >
                            <Plus className="w-4 h-4" />
                            <span>Tambah Kas / Bank</span>
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
                            <span className="text-xs font-medium text-slate-500">Total Kas Masuk ({monthLabel})</span>
                            <div className="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <ArrowDownLeft className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className="text-xl font-bold text-emerald-600 tracking-tight">
                                + {formatRupiah(kasBankSummary.totalMasuk)}
                            </div>
                            <div className="text-[11px] text-slate-400 mt-1">
                                Penjualan kasir, piutang & pendapatan ({monthLabel})
                            </div>
                        </div>
                    </div>

                    {/* Total Kas Keluar (Kredit) */}
                    <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs relative overflow-hidden group hover:border-rose-300 transition">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">Total Kas Keluar ({monthLabel})</span>
                            <div className="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                                <ArrowUpRight className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className="text-xl font-bold text-rose-600 tracking-tight">
                                - {formatRupiah(kasBankSummary.totalKeluar)}
                            </div>
                            <div className="text-[11px] text-slate-400 mt-1">
                                Pembelian cash & bayar hutang ({monthLabel})
                            </div>
                        </div>
                    </div>

                    {/* Arus Kas Bersih (Net Cashflow) */}
                    <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs relative overflow-hidden group hover:border-indigo-300 transition">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">Arus Kas Bersih ({monthLabel})</span>
                            <div className={`w-9 h-9 rounded-xl flex items-center justify-center ${kasBankSummary.netCashflow >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'}`}>
                                {kasBankSummary.netCashflow >= 0 ? <TrendingUp className="w-4 h-4" /> : <TrendingDown className="w-4 h-4" />}
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className={`text-xl font-bold tracking-tight ${kasBankSummary.netCashflow >= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>
                                {kasBankSummary.netCashflow >= 0 ? '+ ' : ''}{formatRupiah(kasBankSummary.netCashflow)}
                            </div>
                            <div className="text-[11px] text-slate-400 mt-1">
                                Total {(mutasiKasBank || []).length} transaksi mutasi {monthLabel}
                            </div>
                        </div>
                    </div>
                </div>

                {/* 2. VISUALISASI CHART & GRAPH AKUN KAS & BANK */}
                <div className="finance-account-panels grid grid-cols-1 xl:grid-cols-12 gap-6">
                    {/* Panel Kiri (8 cols): Distribusi Aliran Kas & Kartu Rekening Interaktif */}
                    <div className="xl:col-span-8 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-5">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-4">
                            <div>
                                <h3 className="font-bold text-slate-900 text-sm flex items-center gap-2">
                                    <BarChart3 className="w-4 h-4 text-blue-600" />
                                    Rekening Kas & Bank
                                    <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                                        {monthLabel}
                                    </span>
                                </h3>
                                <p className="text-xs text-slate-400 mt-0.5">
                                    Pilih rekening untuk melihat mutasi. Masuk dan keluar menunjukkan arus dana periode {monthLabel} (reset tiap bulan).
                                </p>
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => openOpnameModal(null)}
                                    className="px-3 py-1.5 text-xs font-semibold rounded-xl border border-emerald-300 bg-emerald-50 text-emerald-800 transition hover:bg-emerald-100 flex items-center gap-1.5 shadow-2xs"
                                >
                                    <SlidersHorizontal className="w-3.5 h-3.5 text-emerald-600" />
                                    <span>Sesuaikan Saldo Riil</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setIsHistoryModalOpen(true)}
                                    className="px-3 py-1.5 text-xs font-semibold rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-50 flex items-center gap-1.5 shadow-2xs"
                                >
                                    <History className="w-3.5 h-3.5 text-indigo-600" />
                                    <span>Log Opname ({adjustmentLogs.length})</span>
                                </button>

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
                        </div>

                        {/* Grid Kartu Akun Interaktif dengan Visual Bar */}
                        <div className="finance-account-grid grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            {kasBankSummary.accountStats.map((acc) => {
                                const isSelected = String(selectedKasBankFilter) === String(acc.id);
                                const flowVolume = acc.inflow + acc.outflow;
                                const inPercent = flowVolume > 0 ? Math.round((acc.inflow / flowVolume) * 100) : 0;
                                const outPercent = flowVolume > 0 ? 100 - inPercent : 0;

                                return (
                                    <div
                                        key={acc.id}
                                        role="button"
                                        tabIndex={0}
                                        aria-label={`Filter mutasi ${acc.name}`}
                                        aria-pressed={isSelected}
                                        onKeyDown={(event) => {
                                            if (event.target !== event.currentTarget) return;
                                            if (event.key === 'Enter' || event.key === ' ') {
                                                event.preventDefault();
                                                setSelectedKasBankFilter(isSelected ? 'all' : String(acc.id));
                                                setMutasiCurrentPage(1);
                                            }
                                        }}
                                        onClick={() => {
                                            setSelectedKasBankFilter(isSelected ? 'all' : String(acc.id));
                                            setMutasiCurrentPage(1);
                                        }}
                                        className={`finance-account-card p-4 rounded-xl border-2 transition cursor-pointer relative group ${isSelected
                                            ? 'bg-blue-50/40 border-blue-500 shadow-sm'
                                            : 'bg-slate-50/60 border-slate-200/80 hover:border-blue-300 hover:bg-white'
                                            }`}
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="flex items-start gap-3 min-w-0">
                                                <div className={`w-9 h-9 rounded-xl flex items-center justify-center font-bold text-sm shrink-0 ${isSelected ? 'bg-blue-600 text-white shadow-xs' : 'bg-white text-blue-600 border border-slate-200'}`}>
                                                    <Landmark className="w-4 h-4" />
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center gap-1.5 flex-wrap">
                                                        <span className="font-bold text-slate-900 text-xs truncate">
                                                            {acc.name}
                                                        </span>
                                                        <span className={`rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide ${acc.account_type === 'kas_apotek' ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-700'}`}>
                                                            {acc.account_type === 'kas_apotek' ? 'Kas Apotek' : 'Bank'}
                                                        </span>
                                                        {isSelected && (
                                                            <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-600 text-white">
                                                                Terpilih
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="text-[11px] text-slate-500 font-medium mt-0.5">
                                                        {acc.code} {acc.account_number ? `• ${acc.account_number}` : ''}
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Quick Actions (Adjust, Edit & Delete) */}
                                            <div className="flex items-center gap-0.5 shrink-0 opacity-80 group-hover:opacity-100 transition" onClick={(e) => e.stopPropagation()}>
                                                {acc.account_type === 'bank' && (
                                                    <button
                                                        type="button"
                                                        onClick={() => openWithdrawModal(acc)}
                                                        className="p-1 text-slate-400 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition"
                                                        title="Tarik Tunai dari Rekening Ini ke Kas Apotek"
                                                    >
                                                        <ArrowDownLeft className="w-3.5 h-3.5" />
                                                    </button>
                                                )}
                                                <button
                                                    type="button"
                                                    onClick={() => openOpnameModal(acc)}
                                                    className="p-1 text-slate-400 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition"
                                                    title="Sesuaikan Saldo Riil Akun Ini"
                                                >
                                                    <SlidersHorizontal className="w-3.5 h-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => openEditAccountModal(acc)}
                                                    className="p-1 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                                                    title="Edit Rekening"
                                                >
                                                    <Edit3 className="w-3.5 h-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setDeletingAccount(acc)}
                                                    className="p-1 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                                                    title="Hapus Rekening"
                                                >
                                                    <Trash2 className="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </div>

                                        {/* Visual Bar Distribusi Aliran (Inflow vs Outflow) */}
                                        <div className="mt-3.5 space-y-1.5">
                                            <div className="finance-account-amounts flex items-center justify-between text-[11px]">
                                                <span className="text-emerald-700 font-semibold flex items-center gap-1">
                                                    <ArrowDownLeft className="w-3 h-3" /> Masuk {formatRupiah(acc.inflow)}
                                                </span>
                                                <span className="text-rose-700 font-semibold flex items-center gap-1">
                                                    <ArrowUpRight className="w-3 h-3" /> Keluar {formatRupiah(acc.outflow)}
                                                </span>
                                            </div>

                                            <div className="h-2 w-full bg-slate-200 rounded-full overflow-hidden flex">
                                                <div
                                                    style={{ width: `${flowVolume > 0 ? inPercent : 0}%` }}
                                                    className="bg-emerald-500 h-full transition-all duration-500"
                                                    title={`Masuk: ${inPercent}%`}
                                                />
                                                <div
                                                    style={{ width: `${flowVolume > 0 ? outPercent : 0}%` }}
                                                    className="bg-rose-500 h-full transition-all duration-500"
                                                    title={`Keluar: ${outPercent}%`}
                                                />
                                            </div>

                                            <div className="finance-account-balance flex items-center justify-between text-[10px] text-slate-400 pt-0.5">
                                                <span>Saldo Buku: <strong className={`font-semibold ${acc.balance < 0 ? 'text-rose-700' : 'text-slate-900'}`}>{formatRupiah(acc.balance)}</strong></span>
                                                <span className="font-medium text-slate-500">{acc.txCount} Mutasi ({monthLabel})</span>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Panel Kanan (4 cols): Rasio Arus Kas Donut / Ring Graph */}
                    <div className="finance-cash-panel xl:col-span-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-5">
                        <div>
                            <h3 className="font-bold text-slate-900 text-sm flex items-center gap-2 border-b border-slate-100 pb-4">
                                <PieChart className="w-4 h-4 text-emerald-600" />
                                Perbandingan Arus Kas ({monthLabel})
                            </h3>

                            <div className="mt-5">
                                <CashFlowChart incoming={kasBankSummary.totalMasuk} outgoing={kasBankSummary.totalKeluar} compact />
                                <div className="mt-4 text-sm text-slate-600">Arus kas bersih ({monthLabel})
                                    <strong className={`block mt-1 text-lg ${kasBankSummary.netCashflow >= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>{formatRupiah(kasBankSummary.netCashflow)}</strong>
                                </div>
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
                                    Buku Mutasi Kas & Bank ({monthLabel})
                                </h3>
                                <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                    {filteredMutasiKasBank.length} Baris
                                </span>
                            </div>
                            <p className="text-xs text-slate-400 mt-0.5">
                                {selectedKasBankFilter === 'all'
                                    ? `Menampilkan riwayat transaksi seluruh rekening kas & bank periode ${monthLabel}.`
                                    : `Menampilkan mutasi akun: ${kasBankAccounts.find(a => String(a.id) === String(selectedKasBankFilter))?.name || 'Rekening'} periode ${monthLabel}.`
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
                                                <TransactionCategory type={item.category_type} />
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
                                                ) : item.direction === 'OPENING' ? (
                                                    <span className="font-bold text-indigo-600 inline-flex items-center justify-end gap-1">
                                                        {formatRupiah(item.amount)} <span className="text-[10px]">Saldo awal</span>
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

            </div>

            {isBackfillModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <form onSubmit={submitBackfill} className="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                            <div>
                                <h3 className="text-base font-bold text-slate-900">Catat Penjualan Tunai Lama</h3>
                                <p className="mt-0.5 text-[11px] text-slate-500">Melengkapi mutasi yang belum tercatat untuk cabang ini.</p>
                            </div>
                            <button type="button" onClick={() => setIsBackfillModalOpen(false)} className="rounded-lg p-1 text-slate-400 hover:bg-slate-100" aria-label="Tutup modal"><X className="h-5 w-5" /></button>
                        </div>
                        <div className="grid gap-3 p-5">
                            <label className="text-xs font-semibold text-slate-600">Dari tanggal<input required type="date" max={backfillForm.to_date || localToday()} value={backfillForm.from_date} onChange={(event) => setBackfillForm({ ...backfillForm, from_date: event.target.value })} className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal" /></label>
                            <label className="text-xs font-semibold text-slate-600">Sampai tanggal<input required type="date" min={backfillForm.from_date || undefined} max={localToday()} value={backfillForm.to_date} onChange={(event) => setBackfillForm({ ...backfillForm, to_date: event.target.value })} className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal" /></label>
                            <div className="rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-[11px] leading-relaxed text-amber-800">
                                Hanya transaksi penjualan berstatus selesai dengan metode Cash yang dicatat. Transaksi yang sudah memiliki mutasi akan dilewati. Jika tanggal transaksi sebelum tanggal saldo awal, saldo aktual tidak berubah.
                            </div>
                        </div>
                        <div className="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
                            <button type="button" onClick={() => setIsBackfillModalOpen(false)} className="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600">Batal</button>
                            <button type="submit" disabled={isBackfillingSales || !backfillForm.from_date} className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50"><History size={15} />{isBackfillingSales ? 'Memproses…' : 'Catat yang Belum Masuk'}</button>
                        </div>
                    </form>
                </div>
            )}

            {isBackfillNonCashModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <form onSubmit={submitBackfillNonCash} className="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                            <div>
                                <h3 className="text-base font-bold text-slate-900">Catat Transaksi Non-Tunai Lama</h3>
                                <p className="mt-0.5 text-[11px] text-slate-500">Memasukkan QRIS, Debit, dan Transfer lama ke rekening bank cabang ini.</p>
                            </div>
                            <button type="button" onClick={() => setIsBackfillNonCashModalOpen(false)} className="rounded-lg p-1 text-slate-400 hover:bg-slate-100" aria-label="Tutup modal"><X className="h-5 w-5" /></button>
                        </div>
                        <div className="grid gap-3 p-5">
                            <label className="text-xs font-semibold text-slate-600">Dari tanggal<input required type="date" max={backfillNonCashForm.to_date || localToday()} value={backfillNonCashForm.from_date} onChange={(event) => setBackfillNonCashForm({ ...backfillNonCashForm, from_date: event.target.value })} className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal" /></label>
                            <label className="text-xs font-semibold text-slate-600">Sampai tanggal<input required type="date" min={backfillNonCashForm.from_date || undefined} max={localToday()} value={backfillNonCashForm.to_date} onChange={(event) => setBackfillNonCashForm({ ...backfillNonCashForm, to_date: event.target.value })} className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal" /></label>
                            <div className="rounded-xl border border-violet-200 bg-violet-50 px-3.5 py-3 text-[11px] leading-relaxed text-violet-800">
                                Hanya transaksi selesai dengan metode QRIS, Debit, atau Transfer yang belum memiliki mutasi non-tunai yang dicatat. Rekening ditentukan dari nama bank tersimpan di transaksi; bank tersebut harus cocok dengan tepat satu rekening aktif cabang ini. Jika tanggal transaksi sebelum saldo awal, saldo aktual tidak berubah.
                            </div>
                        </div>
                        <div className="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
                            <button type="button" onClick={() => setIsBackfillNonCashModalOpen(false)} className="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600">Batal</button>
                            <button type="submit" disabled={isBackfillingNonCash || !backfillNonCashForm.from_date || !kasBankAccounts.some((account) => account.account_type === 'bank')} className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2 text-xs font-bold text-white hover:bg-violet-700 disabled:opacity-50"><History size={15} />{isBackfillingNonCash ? 'Memproses…' : 'Catat yang Belum Masuk'}</button>
                        </div>
                    </form>
                </div>
            )}

            {isDepositModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <form
                        onSubmit={submitDeposit}
                        onKeyDown={(event) => {
                            if (event.key !== 'Enter' || event.nativeEvent.isComposing) return;
                            const fields = Array.from(event.currentTarget.querySelectorAll('select, input:not([type="hidden"])'))
                                .filter((field) => !field.disabled);
                            const currentIndex = fields.indexOf(event.target);
                            if (currentIndex < 0) return;
                            event.preventDefault();
                            const nextField = fields[currentIndex + 1];
                            if (nextField) nextField.focus();
                            else event.currentTarget.requestSubmit();
                        }}
                        className="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                    >
                        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                            <div><h3 className="text-base font-bold text-slate-900">Setor Kasir ke Rekening</h3><p className="mt-0.5 text-[11px] text-slate-500">Saldo Kas Apotek / Drawer berkurang dan saldo rekening tujuan bertambah.</p></div>
                            <button type="button" onClick={() => setIsDepositModalOpen(false)} className="rounded-lg p-1 text-slate-400 hover:bg-slate-100" aria-label="Tutup modal"><X className="h-5 w-5" /></button>
                        </div>
                        <div className="grid gap-3 p-5">
                            <label className="text-xs font-semibold text-slate-600">Rekening tujuan<select required value={depositForm.transfer_account_id} onChange={(e) => setDepositForm({ ...depositForm, transfer_account_id: e.target.value })} className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal"><option value="">Pilih rekening</option>{kasBankAccounts.filter((account) => account.account_type === 'bank').map((account) => <option key={account.id} value={account.id}>{account.code} · {account.name}</option>)}</select></label>
                            <label className="text-xs font-semibold text-slate-600">Tanggal setoran<input required type="date" max={localToday()} value={depositForm.payment_date} onChange={(e) => setDepositForm({ ...depositForm, payment_date: e.target.value })} className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal" /></label>
                            <label className="text-xs font-semibold text-slate-600">Nominal (Rupiah)<input required type="text" inputMode="decimal" value={depositForm.amount} onChange={(e) => setDepositForm({ ...depositForm, amount: formatDepositAmount(e.target.value) })} placeholder="Contoh: 500.000" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal" /></label>
                            <label className="text-xs font-semibold text-slate-600">No. bukti (opsional)<input value={depositForm.reference_number} onChange={(e) => setDepositForm({ ...depositForm, reference_number: e.target.value })} placeholder="Opsional, contoh: TRF-20261012-001" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal placeholder:text-slate-400" /></label>
                            <label className="text-xs font-semibold text-slate-600">Catatan<input value={depositForm.notes} onChange={(e) => setDepositForm({ ...depositForm, notes: e.target.value })} className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal" /></label>
                        </div>
                        <div className="flex justify-end gap-2 border-t border-slate-100 px-5 py-4"><button type="button" onClick={() => setIsDepositModalOpen(false)} className="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600">Batal</button><button type="submit" disabled={isSavingDeposit || !kasBankAccounts.some((account) => account.account_type === 'bank')} className="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50">{isSavingDeposit ? 'Menyimpan…' : 'Catat Setoran'}</button></div>
                    </form>
                </div>
            )}

            {/* MODAL TARIK TUNAI KE KAS APOTEK */}
            {isWithdrawModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <form
                        onSubmit={submitWithdraw}
                        onKeyDown={(event) => {
                            if (event.key !== 'Enter' || event.nativeEvent.isComposing) return;
                            const fields = Array.from(event.currentTarget.querySelectorAll('select, input:not([type="hidden"])'))
                                .filter((field) => !field.disabled);
                            const currentIndex = fields.indexOf(event.target);
                            if (currentIndex < 0) return;
                            event.preventDefault();
                            const nextField = fields[currentIndex + 1];
                            if (nextField) nextField.focus();
                            else event.currentTarget.requestSubmit();
                        }}
                        className="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl animate-in fade-in duration-150"
                    >
                        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 flex items-center gap-2">
                                    <ArrowDownLeft className="w-5 h-5 text-amber-600" />
                                    Tarik Tunai ke Kas Apotek
                                </h3>
                                <p className="mt-0.5 text-[11px] text-slate-500">
                                    Saldo rekening bank berkurang dan saldo Kas Apotek / Drawer (laci kasir) bertambah.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setIsWithdrawModalOpen(false)}
                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-100"
                                aria-label="Tutup modal"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="grid gap-3 p-5">
                            <div className="rounded-xl border border-amber-200 bg-amber-50/70 p-3 text-xs text-amber-800 leading-relaxed">
                                💡 <strong>Tarik Tunai</strong> memindahkan dana dari rekening bank ke kas fisik laci kasir (Kas Apotek). Saldo bank berkurang, saldo kas bertambah.
                            </div>
                            <label className="text-xs font-semibold text-slate-600">
                                Rekening Bank (Sumber Dana)
                                <select
                                    required
                                    value={withdrawForm.account_id}
                                    onChange={(e) => setWithdrawForm({ ...withdrawForm, account_id: e.target.value })}
                                    className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal focus:ring-2 focus:ring-amber-500"
                                >
                                    <option value="">Pilih rekening bank sumber</option>
                                    {kasBankAccounts.filter((account) => account.account_type === 'bank').map((account) => (
                                        <option key={account.id} value={account.id}>
                                            {account.code} · {account.name} (Saldo: {formatRupiah(account.balance ?? 0)})
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="text-xs font-semibold text-slate-600">
                                Tanggal Penarikan
                                <input
                                    required
                                    type="date"
                                    max={localToday()}
                                    value={withdrawForm.payment_date}
                                    onChange={(e) => setWithdrawForm({ ...withdrawForm, payment_date: e.target.value })}
                                    className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal focus:ring-2 focus:ring-amber-500"
                                />
                            </label>
                            <label className="text-xs font-semibold text-slate-600">
                                Nominal Penarikan (Rupiah)
                                <input
                                    required
                                    type="text"
                                    inputMode="decimal"
                                    value={withdrawForm.amount}
                                    onChange={(e) => setWithdrawForm({ ...withdrawForm, amount: formatDepositAmount(e.target.value) })}
                                    placeholder="Contoh: 1.000.000"
                                    className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal focus:ring-2 focus:ring-amber-500"
                                />
                            </label>
                            <label className="text-xs font-semibold text-slate-600">
                                No. Bukti / Slip / Ref (opsional)
                                <input
                                    value={withdrawForm.reference_number}
                                    onChange={(e) => setWithdrawForm({ ...withdrawForm, reference_number: e.target.value })}
                                    placeholder="Opsional, contoh: ATM-20261108-001 / Slip Tarik"
                                    className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal placeholder:text-slate-400 focus:ring-2 focus:ring-amber-500"
                                />
                            </label>
                            <label className="text-xs font-semibold text-slate-600">
                                Catatan
                                <input
                                    value={withdrawForm.notes}
                                    onChange={(e) => setWithdrawForm({ ...withdrawForm, notes: e.target.value })}
                                    placeholder="Keterangan penarikan tunai"
                                    className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal focus:ring-2 focus:ring-amber-500"
                                />
                            </label>
                        </div>
                        <div className="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
                            <button
                                type="button"
                                onClick={() => setIsWithdrawModalOpen(false)}
                                className="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={isSavingWithdraw || !kasBankAccounts.some((account) => account.account_type === 'bank')}
                                className="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white hover:bg-amber-700 transition disabled:opacity-50 shadow-sm"
                            >
                                <ArrowDownLeft className="w-4 h-4" />
                                {isSavingWithdraw ? 'Menyimpan…' : 'Catat Tarik Tunai'}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* MODAL ATUR SALDO AWAL */}
            {isOpeningModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl animate-in fade-in duration-200">
                        <div className="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white/95 px-6 py-4 backdrop-blur">
                            <div>
                                <h3 className="font-bold text-slate-900 text-base">Atur Saldo Awal Kas & Bank</h3>
                                <p className="mt-0.5 text-[11px] text-slate-500">Saldo awal bulan berikutnya dihitung dari saldo akhir bulan sebelumnya.</p>
                            </div>
                            <button type="button" onClick={() => setIsOpeningModalOpen(false)} className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup modal"><X className="h-5 w-5" /></button>
                        </div>

                        <div className="space-y-5 p-5 sm:p-6">
                            <div className="overflow-x-auto rounded-xl border border-slate-200">
                                <table className="w-full min-w-[650px] text-left text-xs">
                                    <thead className="bg-slate-50 text-slate-500"><tr><th className="px-4 py-3">Rekening</th><th className="px-4 py-3 text-right">Saldo Awal Tercatat</th><th className="px-4 py-3 text-right">Saldo Awal Bulan Ini</th><th className="px-4 py-3 text-right">Saldo Buku</th></tr></thead>
                                    <tbody className="divide-y divide-slate-100">{kasBankAccounts.map((account) => {
                                        const opening = openingBalances[account.id];
                                        return <tr key={account.id} className={String(openingForm.account_id) === String(account.id) ? 'bg-indigo-50/50' : ''}>
                                            <td className="px-4 py-3"><button type="button" onClick={() => chooseOpeningAccount(String(account.id))} className="text-left font-semibold text-slate-800 hover:text-indigo-700">{account.name}</button><div className="mt-0.5 text-[10px] text-slate-400">{account.code} · {opening?.payment_date ? `tanggal ${opening.payment_date}` : 'belum diatur'}</div></td>
                                            <td className="px-4 py-3 text-right">{opening ? formatRupiah(opening.amount) : '—'}</td>
                                            <td className="px-4 py-3 text-right">{formatRupiah(monthlyOpeningBalances[account.id] || 0)}</td>
                                            <td className="px-4 py-3 text-right font-bold text-slate-900">{formatRupiah(account.balance || 0)}</td>
                                        </tr>;
                                    })}</tbody>
                                </table>
                            </div>

                            <form onSubmit={submitOpening} className="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4 sm:p-5">
                                <div className="mb-4">
                                    <h4 className="text-sm font-bold text-slate-900">{selectedOpening ? 'Perbarui saldo awal rekening' : 'Isi saldo awal rekening'}</h4>
                                    <p className="mt-1 text-[11px] text-slate-500">Nilai saldo aktual pada tanggal mulai pencatatan. Tidak dicatat sebagai pendapatan.</p>
                                </div>
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <label className="block text-xs font-semibold text-slate-600">Rekening
                                        <select required value={openingForm.account_id} onChange={(event) => chooseOpeningAccount(event.target.value)} className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal">
                                            {kasBankAccounts.map((account) => <option key={account.id} value={account.id}>{account.code} · {account.name}</option>)}
                                        </select>
                                    </label>
                                    <label className="block text-xs font-semibold text-slate-600">Tanggal saldo aktual
                                        <input required type="date" max={localToday()} value={openingForm.payment_date} onChange={(event) => setOpeningForm({ ...openingForm, payment_date: event.target.value })} className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal" />
                                    </label>
                                    <label className="block text-xs font-semibold text-slate-600">Saldo aktual
                                        <input required type="number" min="0" step="0.01" value={openingForm.amount} onChange={(event) => setOpeningForm({ ...openingForm, amount: event.target.value })} placeholder="Contoh: 25000000" className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal" />
                                    </label>
                                    <label className="block text-xs font-semibold text-slate-600">Catatan
                                        <input value={openingForm.notes} onChange={(event) => setOpeningForm({ ...openingForm, notes: event.target.value })} placeholder="Opsional" className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal" />
                                    </label>
                                </div>
                                <div className="mt-4 flex flex-col gap-3 border-t border-indigo-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                    <p className="text-[11px] text-slate-500">{selectedOpening ? `Saldo awal tersimpan ${formatRupiah(selectedOpening.amount)} per ${selectedOpening.payment_date}.` : 'Saldo awal belum dicatat untuk rekening ini.'}</p>
                                    <button type="submit" disabled={isSavingOpening || kasBankAccounts.length === 0} className="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50"><Save size={15} />{isSavingOpening ? 'Menyimpan…' : selectedOpening ? 'Simpan Perubahan' : 'Simpan Saldo Awal'}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}

            {/* MODAL TAMBAH / EDIT REKENING KAS & BANK */}
            {isAccountModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in duration-200">
                        <div className="px-6 py-4 flex items-center justify-between border-b border-slate-100">
                            <div>
                                <h3 className="font-bold text-slate-900 text-base">
                                    {editingAccount ? 'Edit Rekening Kas / Bank' : 'Tambah Rekening Kas / Bank'}
                                </h3>
                                <p className="text-[11px] text-slate-400 mt-0.5">
                                    Rekening khusus pencatatan kas laci, kasir, atau bank operasional apotek.
                                </p>
                            </div>
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
                                        <span className="text-red-500">*</span> Nama Rekening / Kas
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Contoh: BCA Operasional Apotek"
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
                                        placeholder="Contoh: Operational Bank"
                                        value={accountForm.name_en}
                                        onChange={(e) => setAccountForm({ ...accountForm, name_en: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <label className="block text-slate-700 font-semibold">Jenis Akun
                                    <select value={accountForm.account_type} onChange={(e) => setAccountForm({ ...accountForm, account_type: e.target.value, account_number: e.target.value === 'kas_apotek' ? '' : accountForm.account_number })} className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs">
                                        <option value="kas_apotek">Kas Apotek</option>
                                        <option value="bank">Rekening Bank</option>
                                    </select>
                                </label>
                                <div className="p-3 bg-blue-50/70 border border-blue-100 rounded-xl flex items-center gap-2 text-blue-700 text-xs">
                                    <HelpCircle className="w-4 h-4 shrink-0 text-blue-600" />
                                    <span>Akun <strong>{accountForm.account_type === 'kas_apotek' ? 'Kas Apotek' : 'Bank'}</strong> ini khusus cabang aktif.</span>
                                </div>
                            </div>

                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <label className="block text-slate-700 font-semibold">
                                        Kode Akun <span className="text-[11px] font-normal text-slate-400">(Otomatis jika kosong)</span>
                                    </label>
                                    {!editingAccount && (
                                        <span className="text-[10px] text-blue-600 font-medium">
                                            Saran: {getNextCodeForKasBank()}
                                        </span>
                                    )}
                                </div>
                                <input
                                    type="text"
                                    placeholder={`Otomatis (contoh: ${getNextCodeForKasBank()})`}
                                    value={accountForm.code}
                                    onChange={(e) => setAccountForm({ ...accountForm, code: e.target.value })}
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                />
                            </div>

                            {accountForm.account_type === 'bank' && <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Nomor Rekening Bank (Opsional)
                                </label>
                                <input
                                    type="text"
                                    placeholder="Contoh: 8271928192 (BCA a/n Apotek)"
                                    value={accountForm.account_number}
                                    onChange={(e) => setAccountForm({ ...accountForm, account_number: e.target.value })}
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                />
                            </div>}

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
                                        {isSubmittingAccount ? 'Menyimpan...' : editingAccount ? 'Simpan Perubahan' : 'Tambah Rekening'}
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

            {/* MODAL SESUAIKAN SALDO RIIL (OPNAME KAS & BANK) */}
            {isOpnameModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4 overflow-y-auto">
                    <div className="max-h-[92vh] w-full max-w-4xl flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl animate-in fade-in duration-200">
                        {/* Modal Header */}
                        <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-6 py-4">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                    <SlidersHorizontal className="w-5 h-5" />
                                </div>
                                <div>
                                    <h3 className="text-base font-bold text-slate-900">
                                        Sesuaikan Saldo Riil (Opname Kas & Bank)
                                    </h3>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        Masukkan posisi uang riil yang ada di rekening bank atau kas fisik saat ini. Bisa dilakukan kapan saja.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setIsOpnameModalOpen(false)}
                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
                                aria-label="Tutup modal"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        {/* Mode Switcher & Date */}
                        <div className="px-6 pt-4 pb-2 border-b border-slate-100 bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div className="flex items-center p-1 bg-slate-100 rounded-xl text-xs font-semibold text-slate-600">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setOpnameMode('bulk');
                                        const initialValues = {};
                                        kasBankAccounts.forEach((acc) => {
                                            initialValues[acc.id] = formatDepositAmount(acc.balance ?? 0);
                                        });
                                        setOpnameValues(initialValues);
                                    }}
                                    className={`px-3.5 py-1.5 rounded-lg transition ${opnameMode === 'bulk' ? 'bg-white text-slate-900 shadow-xs' : 'hover:text-slate-900'}`}
                                >
                                    Semua Rekening Sekaligus ({kasBankAccounts.length})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setOpnameMode('single');
                                        if (!opnameTargetAccountId && kasBankAccounts[0]) {
                                            setOpnameTargetAccountId(String(kasBankAccounts[0].id));
                                            setOpnameValues({ [kasBankAccounts[0].id]: formatDepositAmount(kasBankAccounts[0].balance ?? 0) });
                                        }
                                    }}
                                    className={`px-3.5 py-1.5 rounded-lg transition ${opnameMode === 'single' ? 'bg-white text-slate-900 shadow-xs' : 'hover:text-slate-900'}`}
                                >
                                    Satu Rekening Saja
                                </button>
                            </div>

                            <div className="flex items-center gap-2">
                                <span className="text-xs text-slate-500 font-medium">Tanggal Opname:</span>
                                <input
                                    type="date"
                                    max={localToday()}
                                    value={opnameDate}
                                    onChange={(e) => setOpnameDate(e.target.value)}
                                    className="px-3 py-1.5 text-xs rounded-xl border border-slate-200 bg-white font-medium text-slate-700 focus:ring-2 focus:ring-emerald-500"
                                />
                            </div>
                        </div>

                        {/* Modal Body */}
                        <form onSubmit={handleOpnameSubmit} className="flex-1 overflow-y-auto p-6 space-y-5">
                            {opnameMode === 'single' ? (
                                /* Single Account Mode */
                                <div className="space-y-4 max-w-xl mx-auto py-2">
                                    <div>
                                        <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                            Pilih Rekening / Kas yang Disesuaikan
                                        </label>
                                        <select
                                            value={opnameTargetAccountId}
                                            onChange={(e) => {
                                                const accId = e.target.value;
                                                setOpnameTargetAccountId(accId);
                                                const acc = kasBankAccounts.find((a) => String(a.id) === String(accId));
                                                setOpnameValues({ [accId]: formatDepositAmount(acc?.balance ?? 0) });
                                            }}
                                            className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500"
                                        >
                                            {kasBankAccounts.map((acc) => (
                                                <option key={acc.id} value={acc.id}>
                                                    {acc.code} — {acc.name} ({acc.account_type === 'kas_apotek' ? 'Kas Apotek' : 'Bank'}) • Saldo: {formatRupiah(acc.balance || 0)}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {(() => {
                                        const acc = kasBankAccounts.find((a) => String(a.id) === String(opnameTargetAccountId)) || kasBankAccounts[0];
                                        const currentSysBalance = Number(acc?.balance || 0);
                                        const rawInput = opnameValues[opnameTargetAccountId] ?? '';
                                        const cleanNum = rawInput !== '' ? Number(parseDepositAmount(rawInput)) : currentSysBalance;
                                        const diff = cleanNum - currentSysBalance;

                                        return (
                                            <div className="space-y-4">
                                                <div className="grid grid-cols-2 gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-200">
                                                    <div>
                                                        <span className="text-[11px] font-medium text-slate-400 block">Saldo Sistem Saat Ini</span>
                                                        <span className="text-base font-bold text-slate-800 mt-0.5 block">
                                                            {formatRupiah(currentSysBalance)}
                                                        </span>
                                                        <span className="text-[10px] text-slate-400">{acc?.code} • {acc?.account_type === 'kas_apotek' ? 'Kas Fisik' : 'Rekening Bank'}</span>
                                                    </div>
                                                    <div>
                                                        <span className="text-[11px] font-medium text-slate-400 block">Selisih Penyesuaian</span>
                                                        <div className="mt-0.5">
                                                            {Math.abs(diff) < 0.01 ? (
                                                                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-200/80 text-slate-700">
                                                                    Rp 0 (Sesuai)
                                                                </span>
                                                            ) : diff > 0 ? (
                                                                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                                    +{formatRupiah(diff)} (Bertambah)
                                                                </span>
                                                            ) : (
                                                                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                                                    -{formatRupiah(Math.abs(diff))} (Berkurang)
                                                                </span>
                                                            )}
                                                        </div>
                                                        <span className="text-[10px] text-slate-400">Otomatis dihitung live</span>
                                                    </div>
                                                </div>

                                                <div>
                                                    <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                                        Saldo Riil Hasil Pengecekan / Opname (Rupiah) <span className="text-rose-500">*</span>
                                                    </label>
                                                    <div className="relative">
                                                        <span className="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">Rp</span>
                                                        <input
                                                            type="text"
                                                            inputMode="decimal"
                                                            required
                                                            placeholder="0"
                                                            value={opnameValues[opnameTargetAccountId] ?? ''}
                                                            onChange={(e) => {
                                                                setOpnameValues({
                                                                    ...opnameValues,
                                                                    [opnameTargetAccountId]: formatDepositAmount(e.target.value),
                                                                });
                                                            }}
                                                            className="w-full pl-10 pr-4 py-2.5 text-base font-bold text-slate-900 rounded-xl border border-emerald-500 ring-2 ring-emerald-100 focus:outline-hidden"
                                                        />
                                                    </div>
                                                    <p className="text-[11px] text-slate-400 mt-1">
                                                        Ketikkan saldo aktual yang ada di mutasi m-Banking / uang fisik laci kasir saat ini.
                                                    </p>
                                                </div>

                                                <div>
                                                    <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                                        Catatan / Alasan Penyesuaian (Opsional)
                                                    </label>
                                                    <input
                                                        type="text"
                                                        placeholder="Contoh: Cek m-Banking BCA sore, selisih administrasi bank"
                                                        value={opnameNotes}
                                                        onChange={(e) => setOpnameNotes(e.target.value)}
                                                        className="w-full px-3.5 py-2 text-xs rounded-xl border border-slate-200 focus:ring-2 focus:ring-emerald-500 placeholder:text-slate-400"
                                                    />
                                                </div>
                                            </div>
                                        );
                                    })()}
                                </div>
                            ) : (
                                /* Bulk / Lembar Kerja Mode */
                                <div className="space-y-4">
                                    <div className="overflow-x-auto rounded-xl border border-slate-200">
                                        <table className="w-full min-w-[700px] text-left text-xs">
                                            <thead className="bg-[#F8FAFC] border-b border-slate-200 text-slate-600 font-semibold">
                                                <tr>
                                                    <th className="px-4 py-3">Rekening Kas / Bank</th>
                                                    <th className="px-4 py-3 text-right">Saldo di Sistem</th>
                                                    <th className="px-4 py-3 w-56 text-left">Input Saldo Riil (Rp)</th>
                                                    <th className="px-4 py-3 text-right">Selisih Otomatis</th>
                                                    <th className="px-4 py-3 w-56">Catatan Singkat</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100">
                                                {kasBankAccounts.map((acc) => {
                                                    const sysBalance = Number(acc.balance || 0);
                                                    const rawVal = opnameValues[acc.id] ?? '';
                                                    const cleanNum = rawVal !== '' ? Number(parseDepositAmount(rawVal)) : sysBalance;
                                                    const diff = cleanNum - sysBalance;
                                                    const hasChange = Math.abs(diff) >= 0.01;

                                                    return (
                                                        <tr key={acc.id} className={`transition ${hasChange ? 'bg-emerald-50/30' : 'hover:bg-slate-50/60'}`}>
                                                            <td className="px-4 py-3">
                                                                <div className="font-bold text-slate-800 flex items-center gap-1.5">
                                                                    <Landmark className="w-3.5 h-3.5 text-blue-600 shrink-0" />
                                                                    <span>{acc.name}</span>
                                                                </div>
                                                                <div className="text-[10px] text-slate-400 mt-0.5">
                                                                    {acc.code} {acc.account_number ? `• ${acc.account_number}` : ''} ({acc.account_type === 'kas_apotek' ? 'Kas Apotek' : 'Bank'})
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3 text-right whitespace-nowrap font-medium text-slate-700">
                                                                {formatRupiah(sysBalance)}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <div className="relative">
                                                                    <span className="absolute left-2.5 top-1/2 -translate-y-1/2 text-[11px] font-semibold text-slate-400">Rp</span>
                                                                    <input
                                                                        type="text"
                                                                        inputMode="decimal"
                                                                        value={opnameValues[acc.id] ?? ''}
                                                                        onChange={(e) => {
                                                                            setOpnameValues({
                                                                                ...opnameValues,
                                                                                [acc.id]: formatDepositAmount(e.target.value),
                                                                            });
                                                                        }}
                                                                        className={`w-full pl-8 pr-2.5 py-1.5 text-xs font-semibold rounded-lg border transition ${hasChange ? 'border-emerald-500 bg-white ring-1 ring-emerald-200' : 'border-slate-200 bg-white'}`}
                                                                    />
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                                                {!hasChange ? (
                                                                    <span className="text-slate-400 font-medium">Rp 0</span>
                                                                ) : diff > 0 ? (
                                                                    <span className="font-bold text-emerald-600 inline-flex items-center gap-0.5">
                                                                        +{formatRupiah(diff)}
                                                                    </span>
                                                                ) : (
                                                                    <span className="font-bold text-rose-600 inline-flex items-center gap-0.5">
                                                                        -{formatRupiah(Math.abs(diff))}
                                                                    </span>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <input
                                                                    type="text"
                                                                    placeholder="Opsional (misal: cek m-banking)"
                                                                    value={opnameNotesMap[acc.id] ?? ''}
                                                                    onChange={(e) => {
                                                                        setOpnameNotesMap({
                                                                            ...opnameNotesMap,
                                                                            [acc.id]: e.target.value,
                                                                        });
                                                                    }}
                                                                    className="w-full px-2.5 py-1.5 text-[11px] rounded-lg border border-slate-200 placeholder:text-slate-300"
                                                                />
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-600 mb-1">
                                            Catatan Umum Opname Seluruh Rekening (Opsional)
                                        </label>
                                        <input
                                            type="text"
                                            placeholder="Contoh: Opname fisik kasir dan rekening bank akhir shift / penyesuaian berkala"
                                            value={opnameNotes}
                                            onChange={(e) => setOpnameNotes(e.target.value)}
                                            className="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-emerald-500"
                                        />
                                    </div>
                                </div>
                            )}

                            {/* Info Alert */}
                            <div className="flex items-start gap-2.5 p-3.5 bg-amber-50 border border-amber-200/80 rounded-xl text-amber-800 text-[11px] leading-relaxed">
                                <AlertCircle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                <div>
                                    <strong>Penting:</strong> Penyesuaian saldo riil ini murni mencocokkan saldo buku kas/bank dengan uang fisik atau rekening koran.
                                    <strong> Tidak menambah penjualan kasir dan tidak memotong laba apotek</strong> di Ringkasan Keuangan.
                                    Seluruh riwayat penyesuaian dan nama staf yang menyimpan akan tercatat rapi di <strong>Log Opname</strong>.
                                </div>
                            </div>

                            {/* Footer Buttons */}
                            <div className="flex items-center justify-between pt-4 border-t border-slate-100">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setIsOpnameModalOpen(false);
                                        setIsHistoryModalOpen(true);
                                    }}
                                    className="inline-flex items-center gap-1.5 text-xs text-indigo-600 hover:text-indigo-800 font-semibold"
                                >
                                    <History className="w-3.5 h-3.5" />
                                    <span>Lihat Riwayat Log Sebelumnya ({adjustmentLogs.length})</span>
                                </button>

                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setIsOpnameModalOpen(false)}
                                        className="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-50 transition"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={isSubmittingOpname || kasBankAccounts.length === 0}
                                        className="inline-flex items-center gap-2 px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs transition disabled:opacity-50"
                                    >
                                        <Save className="w-4 h-4" />
                                        <span>{isSubmittingOpname ? 'Menyimpan...' : 'Simpan Penyesuaian Saldo Riil'}</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* MODAL RIWAYAT LOG PENYESUAIAN SALDO RIIL (OPNAME) */}
            {isHistoryModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4 overflow-y-auto">
                    <div className="max-h-[92vh] w-full max-w-5xl flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl animate-in fade-in duration-200">
                        {/* Modal Header */}
                        <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-6 py-4">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
                                    <History className="w-5 h-5" />
                                </div>
                                <div>
                                    <h3 className="text-base font-bold text-slate-900">
                                        Riwayat Log Penyesuaian Saldo Riil (Opname Kas & Bank)
                                    </h3>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        Audit trail lengkap kapan saldo diubah, nominal sebelum vs sesudah, selisih koreksi, dan staf yang melakukan perubahan.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setIsHistoryModalOpen(false)}
                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
                                aria-label="Tutup modal"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        {/* Filter Bar */}
                        <div className="p-4 border-b border-slate-100 bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                            <div className="flex flex-wrap items-center gap-2">
                                <select
                                    value={historyAccountFilter}
                                    onChange={(e) => setHistoryAccountFilter(e.target.value)}
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="all">Semua Rekening Kas & Bank</option>
                                    {kasBankAccounts.map((acc) => (
                                        <option key={acc.id} value={acc.id}>
                                            {acc.name} ({acc.code})
                                        </option>
                                    ))}
                                </select>

                                <span className="text-slate-400 font-medium">
                                    Ditemukan {filteredAdjustmentLogs.length} catatan log
                                </span>
                            </div>

                            <div className="relative">
                                <Search className="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <input
                                    type="text"
                                    value={historySearchQuery}
                                    onChange={(e) => setHistorySearchQuery(e.target.value)}
                                    placeholder="Cari rekening, nama petugas, catatan..."
                                    className="pl-8 pr-3 py-1.5 text-xs rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 w-64"
                                />
                            </div>
                        </div>

                        {/* Log Table */}
                        <div className="flex-1 overflow-y-auto p-4 sm:p-6">
                            {filteredAdjustmentLogs.length > 0 ? (
                                <div className="overflow-x-auto rounded-xl border border-slate-200">
                                    <table className="w-full text-left text-xs min-w-[750px]">
                                        <thead className="bg-[#F8FAFC] border-b border-slate-200 text-slate-600 font-semibold">
                                            <tr>
                                                <th className="px-4 py-3">Waktu & Tanggal</th>
                                                <th className="px-4 py-3">Rekening Kas/Bank</th>
                                                <th className="px-4 py-3 text-right">Saldo Sistem (Sebelum)</th>
                                                <th className="px-4 py-3 text-right">Saldo Riil (Sesudah)</th>
                                                <th className="px-4 py-3 text-right">Selisih Koreksi</th>
                                                <th className="px-4 py-3">Catatan / Alasan</th>
                                                <th className="px-4 py-3 text-center">Diubah Oleh</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {filteredAdjustmentLogs.map((log) => {
                                                const diff = Number(log.difference || 0);
                                                const hasDiff = Math.abs(diff) >= 0.01;

                                                return (
                                                    <tr key={log.id} className="hover:bg-slate-50/70 transition">
                                                        <td className="px-4 py-3 whitespace-nowrap">
                                                            <div className="font-semibold text-slate-800 flex items-center gap-1.5">
                                                                <Clock className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                                                <span>{log.adjusted_at}</span>
                                                            </div>
                                                            <div className="text-[10px] text-slate-400 mt-0.5">
                                                                Tanggal Buku: {log.adjustment_date}
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3 whitespace-nowrap">
                                                            <div className="font-bold text-slate-800 flex items-center gap-1.5">
                                                                <Landmark className="w-3.5 h-3.5 text-blue-600 shrink-0" />
                                                                <span>{log.account_name}</span>
                                                            </div>
                                                            <div className="text-[10px] text-slate-400">
                                                                {log.account_code} • {log.account_type === 'kas_apotek' ? 'Kas Apotek' : 'Bank'}
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3 text-right whitespace-nowrap text-slate-600 font-medium">
                                                            {formatRupiah(log.system_balance)}
                                                        </td>
                                                        <td className="px-4 py-3 text-right whitespace-nowrap font-bold text-slate-900">
                                                            {formatRupiah(log.real_balance)}
                                                        </td>
                                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                                            {!hasDiff ? (
                                                                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-600">
                                                                    Rp 0 (Sesuai)
                                                                </span>
                                                            ) : diff > 0 ? (
                                                                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                                    +{formatRupiah(diff)}
                                                                </span>
                                                            ) : (
                                                                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                                    -{formatRupiah(Math.abs(diff))}
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 max-w-xs text-slate-600 truncate" title={log.notes || '-'}>
                                                            {log.notes || <span className="text-slate-300">-</span>}
                                                        </td>
                                                        <td className="px-4 py-3 text-center whitespace-nowrap">
                                                            <div className="font-semibold text-slate-800 flex items-center justify-center gap-1">
                                                                <UserCheck className="w-3.5 h-3.5 text-indigo-600 shrink-0" />
                                                                <span>{log.user_name}</span>
                                                            </div>
                                                            <div className="text-[10px] text-slate-400">
                                                                {log.user_role}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="text-center py-12 text-slate-400">
                                    <div className="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400 mx-auto mb-3">
                                        <History className="w-6 h-6" />
                                    </div>
                                    <div className="font-semibold text-slate-700 text-sm">
                                        Belum Ada Riwayat Penyesuaian Saldo (Opname)
                                    </div>
                                    <div className="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                                        Setiap kali tim Finance mengecek rekening bank atau kas fisik dan menyesuaikan saldo lewat tombol "Sesuaikan Saldo Riil", riwayat perubahan dan selisihnya akan tercatat di sini secara permanen.
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Modal Footer */}
                        <div className="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between">
                            <span className="text-xs text-slate-500">
                                Audit trail dijamin permanen dan tidak dapat diubah-ubah.
                            </span>
                            <button
                                type="button"
                                onClick={() => setIsHistoryModalOpen(false)}
                                className="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-semibold transition"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </FinanceLayout>
    );
}
