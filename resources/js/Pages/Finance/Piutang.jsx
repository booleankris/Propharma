import React, { useState, useMemo, useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import FinanceLayout from './Layouts/FinanceLayout';
import DateRangeFilter from './Components/DateRangeFilter';
import Drawer from './Components/Drawer';
import FloatingActionBar from './Components/FloatingActionBar';
import DebtorCombobox from './Components/DebtorCombobox';
import BuktiPelunasanModal from './Components/BuktiPelunasanModal';
import { formatRupiah, formatNumberOnly } from './Components/Utils';
import {
    ShoppingCart, Search, Check, X, Calendar, AlertCircle, ChevronLeft, ChevronRight, User,
    ArrowLeft, Printer, MoreVertical, ChevronDown, History, Landmark, Maximize2, CreditCard,
    Download, ArrowUp, ArrowDown, Clock, AlertTriangle, FileText, CheckCircle2, FileSpreadsheet, Upload,
    RotateCcw, Loader2, Plus, Trash2
} from 'lucide-react';

export default function Piutang({
    piutangPenjualan = [],
    pagination = null,
    dueStats: serverDueStats = null,
    creditReturns = [],
    returPagination = null,
    filters: serverFilters = {},
    debtors = [],
    kasBankAccounts = [],
    stats = {},
}) {
    const [mainTab, setMainTab] = useState(serverFilters.tab ?? 'faktur'); // 'faktur' | 'retur'
    const [searchTerm, setSearchTerm] = useState(serverFilters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(serverFilters.status ?? 'ALL');
    const [debtorFilter, setDebtorFilter] = useState(serverFilters.debtor_id ?? '');
    const [startDate, setStartDate] = useState(serverFilters.start_date ?? '');
    const [endDate, setEndDate] = useState(serverFilters.end_date ?? '');
    const [datePreset, setDatePreset] = useState(serverFilters.date_preset ?? 'all');
    const [sortCreatedAt, setSortCreatedAt] = useState(serverFilters.sort_order ?? 'desc');
    const [dueFilter, setDueFilter] = useState(serverFilters.due_mode ?? 'all');
    const [isDueDropdownOpen, setIsDueDropdownOpen] = useState(false);
    const [isLoadingList, setIsLoadingList] = useState(false);

    // State Khusus Tab Riwayat Retur Kredit
    const [returSearchTerm, setReturSearchTerm] = useState(serverFilters.retur_search ?? '');
    const [returDebtorFilter, setReturDebtorFilter] = useState(serverFilters.retur_debtor_id ?? '');

    const currentPage = pagination?.current_page ?? 1;
    const totalPages = pagination?.last_page ?? 1;
    const totalFilteredItems = pagination?.total ?? piutangPenjualan.length;
    const pageFrom = pagination?.from ?? (piutangPenjualan.length > 0 ? 1 : 0);
    const pageTo = pagination?.to ?? piutangPenjualan.length;

    const returCurrentPage = returPagination?.current_page ?? 1;
    const returTotalPages = returPagination?.last_page ?? 1;
    const returTotalItems = returPagination?.total ?? creditReturns.length;
    const returPageFrom = returPagination?.from ?? (creditReturns.length > 0 ? 1 : 0);
    const returPageTo = returPagination?.to ?? creditReturns.length;

    const isItemSettled = (item) => {
        return !item || item.status === 'Lunas' || item.status === 'Diretur Penuh' || item.status === 'Lunas (Ada Retur)' || item.status === 'Koreksi Saldo Awal' || Boolean(item.is_negative_adjustment) || (Number(item.sisa) || 0) <= 0.005;
    };

    const navigatePiutang = (overrides = {}) => {
        const next = {
            tab: overrides.tab !== undefined ? overrides.tab : mainTab,
            search: overrides.search !== undefined ? overrides.search : searchTerm,
            debtor_id: overrides.debtor_id !== undefined ? overrides.debtor_id : debtorFilter,
            status: overrides.status !== undefined ? overrides.status : statusFilter,
            due_mode: overrides.due_mode !== undefined ? overrides.due_mode : dueFilter,
            date_preset: overrides.date_preset !== undefined ? overrides.date_preset : datePreset,
            start_date: overrides.start_date !== undefined ? overrides.start_date : startDate,
            end_date: overrides.end_date !== undefined ? overrides.end_date : endDate,
            sort_order: overrides.sort_order !== undefined ? overrides.sort_order : sortCreatedAt,
            page: overrides.page !== undefined ? overrides.page : currentPage,
            retur_search: overrides.retur_search !== undefined ? overrides.retur_search : returSearchTerm,
            retur_debtor_id: overrides.retur_debtor_id !== undefined ? overrides.retur_debtor_id : returDebtorFilter,
            retur_page: overrides.retur_page !== undefined ? overrides.retur_page : returCurrentPage,
        };
        const params = {};
        if (next.tab && next.tab !== 'faktur') params.tab = next.tab;
        if (next.search && next.search.trim() !== '') params.search = next.search.trim();
        if (next.debtor_id) params.debtor_id = next.debtor_id;
        if (next.status && next.status !== 'ALL') params.status = next.status;
        if (next.due_mode && next.due_mode !== 'all') params.due_mode = next.due_mode;
        if (next.date_preset && next.date_preset !== 'all') params.date_preset = next.date_preset;
        if (next.start_date) params.start_date = next.start_date;
        if (next.end_date) params.end_date = next.end_date;
        if (next.sort_order && next.sort_order !== 'desc') params.sort_order = next.sort_order;
        if (next.page && next.page > 1) params.page = next.page;
        if (next.retur_search && next.retur_search.trim() !== '') params.retur_search = next.retur_search.trim();
        if (next.retur_debtor_id) params.retur_debtor_id = next.retur_debtor_id;
        if (next.retur_page && next.retur_page > 1) params.retur_page = next.retur_page;

        router.get('/finance/piutang', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setIsLoadingList(true),
            onFinish: () => setIsLoadingList(false),
        });
    };

    const didMountSearchRef = useRef(false);
    useEffect(() => {
        if (!didMountSearchRef.current) {
            didMountSearchRef.current = true;
            return;
        }
        const timer = setTimeout(() => {
            navigatePiutang({ search: searchTerm, page: 1 });
        }, 300);
        return () => clearTimeout(timer);
    }, [searchTerm]);

    const didMountReturSearchRef = useRef(false);
    useEffect(() => {
        if (!didMountReturSearchRef.current) {
            didMountReturSearchRef.current = true;
            return;
        }
        const timer = setTimeout(() => {
            navigatePiutang({ retur_search: returSearchTerm, retur_page: 1 });
        }, 300);
        return () => clearTimeout(timer);
    }, [returSearchTerm]);

    const handleDatePreset = (preset) => {
        setDatePreset(preset);
        if (preset === 'all') {
            setStartDate('');
            setEndDate('');
            navigatePiutang({ date_preset: 'all', start_date: '', end_date: '', page: 1, retur_page: 1 });
            return;
        }
        const today = new Date();
        const formatDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };
        const start = preset === 'today'
            ? today
            : preset === 'this_month'
                ? new Date(today.getFullYear(), today.getMonth(), 1)
                : new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const end = preset === 'today'
            ? today
            : preset === 'this_month'
                ? new Date(today.getFullYear(), today.getMonth() + 1, 0)
                : new Date(today.getFullYear(), today.getMonth(), 0);
        const nextStart = formatDate(start);
        const nextEnd = formatDate(end);
        setStartDate(nextStart);
        setEndDate(nextEnd);
        navigatePiutang({ date_preset: preset, start_date: nextStart, end_date: nextEnd, page: 1, retur_page: 1 });
    };

    // Selection lintas halaman untuk Pelunasan Massal (Maks 10)
    const [selectedPiutangIds, setSelectedPiutangIds] = useState([]);
    const [selectedItemsMap, setSelectedItemsMap] = useState({});
    const [selectionWarning, setSelectionWarning] = useState('');

    // State Halaman Detil Penuh & Quick Preview Drawer + Lazy Cache
    const [detailViewPiutang, setDetailViewPiutang] = useState(null);
    const [drawerPiutang, setDrawerPiutang] = useState(null);
    const [isShippingOpen, setIsShippingOpen] = useState(true);
    const [isBuktiModalOpen, setIsBuktiModalOpen] = useState(false);
    const [isLoadingDetail, setIsLoadingDetail] = useState(false);
    const detailCacheRef = useRef({});

    useEffect(() => {
        if (!piutangPenjualan.length) return;
        setSelectedItemsMap((prev) => {
            const next = { ...prev };
            let changed = false;
            piutangPenjualan.forEach((item) => {
                if (next[item.id]) {
                    next[item.id] = item;
                    changed = true;
                }
            });
            return changed ? next : prev;
        });
    }, [piutangPenjualan]);

    const loadPiutangDetail = async (txId) => {
        if (!txId) return;
        if (detailCacheRef.current[txId]) {
            const cached = detailCacheRef.current[txId];
            setDrawerPiutang((curr) => (curr?.id === txId ? cached : curr));
            setDetailViewPiutang((curr) => (curr?.id === txId ? cached : curr));
            return;
        }
        setIsLoadingDetail(true);
        try {
            const res = await axios.get(`/finance/piutang/${txId}/detail`);
            const full = res?.data?.data;
            if (full) {
                detailCacheRef.current[txId] = full;
                setDrawerPiutang((curr) => (curr?.id === txId ? full : curr));
                setDetailViewPiutang((curr) => (curr?.id === txId ? full : curr));
            }
        } catch (err) {
            console.error('Gagal memuat detail piutang:', err);
        } finally {
            setIsLoadingDetail(false);
        }
    };

    const openPiutangDrawer = (item) => {
        const initial = detailCacheRef.current[item.id] || item;
        setDrawerPiutang(initial);
        loadPiutangDetail(item.id);
    };

    const openPiutangDetail = (item) => {
        const initial = detailCacheRef.current[item.id] || item;
        setDetailViewPiutang(initial);
        loadPiutangDetail(item.id);
    };

    const openPiutangDetailById = (txId, fallbackRet = null) => {
        if (!txId) return;
        const fromList = piutangPenjualan.find((p) => p.id === txId);
        const initial = detailCacheRef.current[txId] || fromList || {
            id: txId,
            nomor: fallbackRet?.transaction_code || `TRX-${txId}`,
            debtor: fallbackRet?.debtor || '-',
            debtor_code: fallbackRet?.debtor_code || '-',
            pasien: fallbackRet?.pasien || '-',
            dokter: fallbackRet?.dokter || '-',
            apotek: fallbackRet?.apotek || '-',
            tanggal: fallbackRet?.tanggal || '-',
            jatuhTempo: '-',
            status: 'Belum Bayar',
            subtotal: 0,
            total: 0,
            total_retur: 0,
            net_total: 0,
            terbayar: 0,
            sisa: 0,
            items: [],
            payments: [],
            returs: [],
        };
        setDetailViewPiutang(initial);
        loadPiutangDetail(txId);
    };

    // State Modal Terima Pembayaran Satuan
    const [isPaymentModalOpen, setIsPaymentModalOpen] = useState(false);
    const [paymentForm, setPaymentForm] = useState({
        medicine_transaction_id: '',
        account_id: '',
        payment_date: new Date().toISOString().split('T')[0],
        amount: 0,
        sisa: 0,
        reference_number: '',
        notes: '',
        invoice_number: '',
        debtor: '',
        total: 0,
        is_cutoff: false,
    });
    const [isSubmittingPayment, setIsSubmittingPayment] = useState(false);

    // State Modal Pelunasan Massal Piutang
    const [isBulkModalOpen, setIsBulkModalOpen] = useState(false);
    const [bulkForm, setBulkForm] = useState({
        account_id: '',
        payment_date: new Date().toISOString().split('T')[0],
        reference_number: '',
        notes: '',
        is_cutoff: false,
    });
    const [isSubmittingBulk, setIsSubmittingBulk] = useState(false);
    const [isImportModalOpen, setIsImportModalOpen] = useState(false);
    const [importFile, setImportFile] = useState(null);
    const [importIsCutoff, setImportIsCutoff] = useState(false);
    const [isImporting, setIsImporting] = useState(false);
    const [importSuccessMessage, setImportSuccessMessage] = useState('');

    // State Modal Tambah Tagihan Saldo Awal Piutang (Per Tagihan)
    const getDefaultCutoffDate = () => {
        const now = new Date();
        const prevEnd = new Date(now.getFullYear(), now.getMonth(), 0);
        return `${prevEnd.getFullYear()}-${String(prevEnd.getMonth() + 1).padStart(2, '0')}-${String(prevEnd.getDate()).padStart(2, '0')}`;
    };
    const [isOpeningModalOpen, setIsOpeningModalOpen] = useState(false);
    const [openingPreviewBalance, setOpeningPreviewBalance] = useState(null);
    const [isLoadingOpeningPreview, setIsLoadingOpeningPreview] = useState(false);
    const [openingDebtorQuery, setOpeningDebtorQuery] = useState('');
    const [openingForm, setOpeningForm] = useState({
        debtor_id: '',
        invoice_number: '',
        invoice_date: getDefaultCutoffDate(),
        due_date: '',
        amount: '',
        notes: '',
    });
    const [isSubmittingOpening, setIsSubmittingOpening] = useState(false);
    const filteredOpeningDebtors = useMemo(() => {
        const q = openingDebtorQuery.trim().toLowerCase();
        if (!q) return debtors;
        return debtors.filter(
            (d) => (d.name && d.name.toLowerCase().includes(q)) || (d.code && d.code.toLowerCase().includes(q))
        );
    }, [debtors, openingDebtorQuery]);

    const fetchOpeningPreview = async (dateStr) => {
        setIsLoadingOpeningPreview(true);
        try {
            const res = await axios.get('/finance/opening-balance-preview', {
                params: { type: 'PIUTANG', date: dateStr || getDefaultCutoffDate() },
            });
            setOpeningPreviewBalance(Number(res?.data?.balance ?? 0));
        } catch (err) {
            console.error('Gagal memuat saldo awal piutang:', err);
            setOpeningPreviewBalance(null);
        } finally {
            setIsLoadingOpeningPreview(false);
        }
    };

    const openOpeningModal = () => {
        const defaultDate = getDefaultCutoffDate();
        setOpeningDebtorQuery('');
        setOpeningForm({
            debtor_id: '',
            invoice_number: '',
            invoice_date: defaultDate,
            due_date: '',
            amount: '',
            notes: '',
        });
        setIsOpeningModalOpen(true);
        fetchOpeningPreview(defaultDate);
    };

    const handleOpeningSubmit = (e) => {
        e.preventDefault();
        const amt = parseFloat(openingForm.amount);
        if (isNaN(amt) || Math.abs(amt) < 0.01) {
            alert('Nominal tidak boleh 0.');
            return;
        }
        setIsSubmittingOpening(true);
        router.post('/finance/piutang/opening', {
            ...openingForm,
            debtor_id: openingForm.debtor_id ? parseInt(openingForm.debtor_id, 10) : null,
            amount: amt,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsOpeningModalOpen(false);
                setIsSubmittingOpening(false);
                detailCacheRef.current = {};
            },
            onError: (errs) => {
                setIsSubmittingOpening(false);
                alert(Object.values(errs).flat()[0] || 'Gagal menambah tagihan saldo awal piutang.');
            },
        });
    };

    const handleDeleteOpening = (item) => {
        if (!item?.is_opening || !item?.opening_id) return;
        if (!window.confirm(`Hapus tagihan saldo awal piutang "${item.nomor}" (${formatRupiah(item.total)})?`)) return;
        router.delete(`/finance/piutang/opening/${item.opening_id}`, {
            preserveScroll: true,
            onSuccess: () => {
                delete detailCacheRef.current[item.id];
                if (drawerPiutang?.id === item.id) setDrawerPiutang(null);
                if (detailViewPiutang?.id === item.id) setDetailViewPiutang(null);
            },
            onError: (errs) => {
                alert(Object.values(errs).flat()[0] || 'Gagal menghapus tagihan saldo awal piutang.');
            },
        });
    };

    const paginatedPiutang = piutangPenjualan;
    const paginatedCreditReturns = creditReturns;

    const selectedPiutangItems = useMemo(() => {
        const byId = new Map(piutangPenjualan.map((item) => [item.id, item]));
        return selectedPiutangIds
            .map((id) => byId.get(id) || selectedItemsMap[id])
            .filter(Boolean);
    }, [piutangPenjualan, selectedPiutangIds, selectedItemsMap]);

    const totalSelectedPiutangSisa = useMemo(() => {
        const sum = selectedPiutangItems.reduce((acc, curr) => acc + (Number(curr.sisa) || 0), 0);
        return Math.round(sum * 100) / 100;
    }, [selectedPiutangItems]);

    const totalSelectedPiutangTotal = useMemo(() => {
        const sum = selectedPiutangItems.reduce((acc, curr) => acc + (Number(curr.total) || 0), 0);
        return Math.round(sum * 100) / 100;
    }, [selectedPiutangItems]);

    // Total kuantitas barang pada halaman detil penuh
    const totalDetailQty = useMemo(() => {
        if (!detailViewPiutang || !detailViewPiutang.items) return 0;
        return detailViewPiutang.items.reduce((acc, curr) => acc + (Number(curr.qty) || 0), 0);
    }, [detailViewPiutang]);

    // Checkbox Handlers
    const handleTogglePiutang = (item) => {
        if (isItemSettled(item)) return;
        if (selectedPiutangIds.includes(item.id)) {
            setSelectedPiutangIds((prev) => prev.filter((id) => id !== item.id));
            setSelectedItemsMap((prev) => {
                const next = { ...prev };
                delete next[item.id];
                return next;
            });
        } else {
            if (selectedPiutangIds.length >= 10) {
                setSelectionWarning('Maksimal 10 transaksi yang dapat dipilih sekaligus.');
                setTimeout(() => setSelectionWarning(''), 4000);
                return;
            }
            setSelectedPiutangIds((prev) => [...prev, item.id]);
            setSelectedItemsMap((prev) => ({ ...prev, [item.id]: item }));
        }
    };

    const handleSelectAllPiutang = (e) => {
        if (e.target.checked) {
            const eligible = paginatedPiutang.filter((item) => !isItemSettled(item));
            const remainingSlots = 10 - selectedPiutangIds.length;
            if (remainingSlots <= 0) {
                setSelectionWarning('Maksimal 10 transaksi yang dapat dipilih.');
                setTimeout(() => setSelectionWarning(''), 4000);
                return;
            }
            const addedItems = eligible
                .filter((item) => !selectedPiutangIds.includes(item.id))
                .slice(0, remainingSlots);
            const toAdd = addedItems.map((item) => item.id);

            if (toAdd.length < eligible.filter((item) => !selectedPiutangIds.includes(item.id)).length) {
                setSelectionWarning('Hanya 10 transaksi pertama yang dipilih (maks. 10).');
                setTimeout(() => setSelectionWarning(''), 4000);
            }
            setSelectedPiutangIds((prev) => [...prev, ...toAdd]);
            setSelectedItemsMap((prev) => {
                const next = { ...prev };
                addedItems.forEach((it) => { next[it.id] = it; });
                return next;
            });
        } else {
            const currentPageIds = paginatedPiutang.map((item) => item.id);
            setSelectedPiutangIds((prev) => prev.filter((id) => !currentPageIds.includes(id)));
            setSelectedItemsMap((prev) => {
                const next = { ...prev };
                currentPageIds.forEach((id) => { delete next[id]; });
                return next;
            });
        }
    };

    const renderStatusBadge = (item) => {
        if (item.status === 'Koreksi Saldo Awal' || item.is_negative_adjustment) {
            return (
                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200" title="Koreksi pengurang Saldo Awal Piutang">
                    <span>Koreksi Saldo Awal</span>
                </span>
            );
        }
        if (item.status === 'Diretur Penuh') {
            return (
                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-purple-100 text-purple-800 border border-purple-200" title="Tagihan diretur penuh (Sisa Rp 0)">
                    <RotateCcw className="w-3 h-3 text-purple-600" />
                    <span>Diretur Penuh</span>
                </span>
            );
        }
        if (item.status === 'Diretur Sebagian') {
            return (
                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-200" title="Sebagian item diretur dan memotong tagihan">
                    <RotateCcw className="w-3 h-3 text-rose-600" />
                    <span>Diretur Sebagian</span>
                </span>
            );
        }
        if (item.status === 'Lunas (Ada Retur)') {
            return (
                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200" title="Lunas setelah pemotongan retur">
                    <Check className="w-3 h-3 text-emerald-600" />
                    <span>Lunas (Ada Retur)</span>
                </span>
            );
        }
        if (item.status === 'Lunas' || (Number(item.sisa) || 0) <= 0.005) {
            return (
                <span className="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700">
                    Lunas
                </span>
            );
        }
        if (item.status === 'Dibayar Sebagian') {
            return (
                <span className="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700">
                    Dibayar Sebagian
                </span>
            );
        }
        return (
            <span className="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold bg-red-50 text-red-700">
                Belum Bayar
            </span>
        );
    };

    // Buka Modal Terima Pembayaran Satuan
    const openPaymentModal = (item) => {
        const defaultAccount = kasBankAccounts[0]?.id || '';
        setPaymentForm({
            medicine_transaction_id: item.id,
            account_id: defaultAccount,
            payment_date: new Date().toISOString().split('T')[0],
            amount: item.sisa,
            sisa: item.sisa,
            reference_number: '',
            notes: `Penerimaan piutang transaksi ${item.nomor} dari ${item.debtor}`,
            invoice_number: item.nomor,
            debtor: item.debtor,
            total: item.total,
            is_cutoff: false,
        });
        setIsPaymentModalOpen(true);
    };

    // Submit Pembayaran Piutang
    const handlePaymentSubmit = (e) => {
        e.preventDefault();
        if (!paymentForm.is_cutoff && !paymentForm.account_id) {
            alert('Silakan pilih akun Kas & Bank penerima.');
            return;
        }
        const amt = parseFloat(paymentForm.amount);
        if (isNaN(amt) || amt <= 0) {
            alert('Nominal pembayaran harus lebih dari 0.');
            return;
        }
        if (paymentForm.sisa && Math.round(amt * 100) > Math.round(parseFloat(paymentForm.sisa) * 100)) {
            alert(`Nominal pembayaran melebihi sisa tagihan (${formatRupiah(paymentForm.sisa)}).`);
            return;
        }

        setIsSubmittingPayment(true);
        router.post('/finance/piutang-payments', {
            medicine_transaction_id: paymentForm.medicine_transaction_id,
            account_id: paymentForm.is_cutoff ? null : paymentForm.account_id,
            payment_date: paymentForm.payment_date,
            amount: paymentForm.amount,
            reference_number: paymentForm.reference_number,
            notes: paymentForm.notes,
            is_cutoff: Boolean(paymentForm.is_cutoff),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsPaymentModalOpen(false);
                setIsSubmittingPayment(false);
                delete detailCacheRef.current[paymentForm.medicine_transaction_id];
                if (drawerPiutang && drawerPiutang.id === paymentForm.medicine_transaction_id) {
                    setDrawerPiutang(null);
                }
                if (detailViewPiutang && detailViewPiutang.id === paymentForm.medicine_transaction_id) {
                    setDetailViewPiutang(null);
                }
            },
            onError: (errs) => {
                setIsSubmittingPayment(false);
                alert(Object.values(errs)[0] || 'Gagal memproses pembayaran piutang.');
            }
        });
    };

    // Buka Modal Pelunasan Massal Piutang
    const openBulkModal = () => {
        if (selectedPiutangIds.length === 0) return;
        const defaultAccount = kasBankAccounts[0]?.id || '';
        setBulkForm({
            account_id: defaultAccount,
            payment_date: new Date().toISOString().split('T')[0],
            reference_number: '',
            notes: `Pelunasan massal ${selectedPiutangIds.length} transaksi piutang penjualan`,
            is_cutoff: false,
        });
        setIsBulkModalOpen(true);
    };

    // Submit Pelunasan Massal Piutang
    const handleBulkSubmit = (e) => {
        e.preventDefault();
        if (!bulkForm.is_cutoff && !bulkForm.account_id) {
            alert('Silakan pilih akun Kas & Bank penerima.');
            return;
        }
        setIsSubmittingBulk(true);
        router.post('/finance/bulk-piutang-payments', {
            transaction_ids: selectedPiutangIds,
            account_id: bulkForm.is_cutoff ? null : bulkForm.account_id,
            payment_date: bulkForm.payment_date,
            reference_number: bulkForm.reference_number,
            notes: bulkForm.notes,
            is_cutoff: Boolean(bulkForm.is_cutoff),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsBulkModalOpen(false);
                setIsSubmittingBulk(false);
                detailCacheRef.current = {};
                setSelectedPiutangIds([]);
                setSelectedItemsMap({});
            },
            onError: (errs) => {
                setIsSubmittingBulk(false);
                alert(Object.values(errs)[0] || 'Gagal memproses pelunasan massal piutang.');
            }
        });
    };

    const handleImportPayments = (e) => {
        e.preventDefault();
        if (!importFile) return alert('Pilih file Excel atau CSV terlebih dahulu.');
        const formData = new FormData();
        formData.append('file', importFile);
        formData.append('is_cutoff', importIsCutoff ? '1' : '0');
        setIsImporting(true);
        router.post('/finance/piutang/import-payments', formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                setIsImportModalOpen(false);
                setImportFile(null);
                setImportIsCutoff(false);
                setIsImporting(false);
                detailCacheRef.current = {};
                setSelectedPiutangIds([]);
                setSelectedItemsMap({});
                setImportSuccessMessage(page?.props?.flash?.success || 'Impor pembayaran Piutang berhasil.');
            },
            onError: (errs) => {
                setIsImporting(false);
                alert(Object.values(errs).flat().join('\n') || 'Gagal mengimpor pembayaran piutang.');
            },
        });
    };

    // Handle Pengajuan Pelunasan (Submit / Batalkan)
    const handleBulkSubmitPiutang = (isSubmit) => {
        if (selectedPiutangIds.length === 0) return;
        setIsSubmittingBulk(true);
        router.post('/finance/piutang/bulk-submit', {
            ids: selectedPiutangIds,
            is_submitted: isSubmit
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsSubmittingBulk(false);
                detailCacheRef.current = {};
                setSelectedPiutangIds([]);
                setSelectedItemsMap({});
            },
            onError: () => {
                setIsSubmittingBulk(false);
            }
        });
    };

    const allSelectedPiutangSubmitted = selectedPiutangIds.length > 0 && selectedPiutangItems.length === selectedPiutangIds.length && selectedPiutangItems.every(item => Boolean(item?.is_submitted));

    return (
        <FinanceLayout
            title="Piutang Penjualan"
            subtitle="Tagihan piutang transaksi penjualan obat kepada pelanggan & instansi mitra"
            stats={stats}
        >
            {detailViewPiutang ? (
                /* SUB-VIEW A: DETIL TAGIHAN PIUTANG LENGKAP */
                <div className="space-y-6">
                    {/* Navigasi Kembali */}
                    <button
                        type="button"
                        onClick={() => setDetailViewPiutang(null)}
                        className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-800 font-medium transition cursor-pointer"
                    >
                        <ArrowLeft className="w-3.5 h-3.5" />
                        <span>Kembali ke Daftar</span>
                    </button>

                    {/* Judul & Action Kanan */}
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                            Detil Tagihan Piutang {detailViewPiutang.nomor}
                        </h1>

                        <div className="flex items-center gap-2 flex-wrap">
                            {detailViewPiutang.is_opening && (
                                <button
                                    type="button"
                                    onClick={() => handleDeleteOpening(detailViewPiutang)}
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition cursor-pointer"
                                >
                                    <Trash2 className="w-3.5 h-3.5" />
                                    <span>Hapus Saldo Awal</span>
                                </button>
                            )}
                            {detailViewPiutang.sisa > 0.005 && (
                                <button
                                    type="button"
                                    onClick={() => openPaymentModal(detailViewPiutang)}
                                    className="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-white bg-amber-600 rounded-lg hover:bg-amber-700 transition shadow-xs cursor-pointer"
                                >
                                    <CreditCard className="w-3.5 h-3.5" />
                                    <span>Terima Pembayaran</span>
                                </button>
                            )}

                            <button
                                type="button"
                                onClick={() => setIsBuktiModalOpen(true)}
                                className="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-amber-50 hover:border-amber-300 hover:text-amber-800 transition shadow-xs cursor-pointer"
                                title="Cetak kuitansi / tanda terima pelunasan resmi"
                            >
                                <FileText className="w-3.5 h-3.5 text-amber-600" />
                                <span>Cetak Bukti Pelunasan</span>
                            </button>

                            <button
                                type="button"
                                onClick={() => window.print()}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition cursor-pointer"
                            >
                                <Printer className="w-3.5 h-3.5 text-slate-500" />
                                <span>Print</span>
                            </button>

                            <button
                                type="button"
                                className="p-1.5 text-slate-400 hover:text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition cursor-pointer"
                            >
                                <MoreVertical className="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    {/* KARTU UTAMA DETIL PIUTANG */}
                    <div className="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs space-y-6">
                        {/* Status Badge */}
                        <div className="flex items-center justify-between">
                            <div className="flex flex-col items-start gap-1">
                                {renderStatusBadge(detailViewPiutang)}
                                {isItemSettled(detailViewPiutang) && detailViewPiutang.tanggalLunas && detailViewPiutang.status !== 'Diretur Penuh' && (
                                    <span className="text-[11px] font-medium text-emerald-600">Lunas pada {detailViewPiutang.tanggalLunas}</span>
                                )}
                            </div>

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
                                            {detailViewPiutang.debtor}
                                        </span>
                                        {detailViewPiutang.debtor_code && detailViewPiutang.debtor_code !== '-' && (
                                            <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 font-semibold border border-amber-200">
                                                {detailViewPiutang.debtor_code}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1">Pasien Terkait</span>
                                    <span className="font-semibold text-slate-800 text-sm">{detailViewPiutang.pasien || '-'}</span>
                                </div>
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1">Dokter Pemeriksa</span>
                                    <span className="text-slate-700 font-medium">{detailViewPiutang.dokter || '-'}</span>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1">No. Faktur / Resep</span>
                                    <span className="font-bold text-slate-900 text-sm">{detailViewPiutang.nomor}</span>
                                </div>
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1">Tgl. Transaksi</span>
                                    <span className="font-semibold text-slate-800 text-sm">{detailViewPiutang.tanggal}</span>
                                </div>
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1 font-semibold uppercase">Tgl. Jatuh Tempo</span>
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="font-extrabold text-slate-900 text-base">{detailViewPiutang.jatuhTempo}</span>
                                        {detailViewPiutang.status === 'Lunas' || detailViewPiutang.sisa <= 0.005 ? (
                                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                <CheckCircle2 className="w-3 h-3 text-emerald-600" />
                                                <span>Lunas</span>
                                            </span>
                                        ) : detailViewPiutang.is_overdue || detailViewPiutang.days_overdue > 0 ? (
                                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-black bg-red-100 text-red-800 border border-red-300 animate-pulse">
                                                <AlertTriangle className="w-3.5 h-3.5 text-red-600" />
                                                <span>Lewat {detailViewPiutang.days_overdue} Hari</span>
                                            </span>
                                        ) : detailViewPiutang.days_remaining <= 7 ? (
                                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                                <Clock className="w-3.5 h-3.5 text-amber-600" />
                                                <span>Sisa {detailViewPiutang.days_remaining} Hari</span>
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                                <Calendar className="w-3.5 h-3.5 text-slate-400" />
                                                <span>Sisa {detailViewPiutang.days_remaining} Hari</span>
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1">Apotek / Unit Layanan</span>
                                    <span className="font-medium text-slate-800">{detailViewPiutang.apotek}</span>
                                </div>
                            </div>
                        </div>

                        {/* Informasi Penyerahan & Resep */}
                        <div className="pt-2 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setIsShippingOpen(!isShippingOpen)}
                                className="flex items-center gap-1.5 text-xs font-semibold text-slate-700 hover:text-slate-900 transition cursor-pointer"
                            >
                                {isShippingOpen ? <ChevronDown className="w-3.5 h-3.5" /> : <ChevronRight className="w-3.5 h-3.5" />}
                                <span>Informasi Penyerahan & Resep</span>
                            </button>
                            {isShippingOpen && (
                                <div className="pl-5 pt-3 text-xs grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50/70 p-3 rounded-xl mt-2">
                                    <div>
                                        <span className="text-slate-400 block text-[11px]">Tanggal Transaksi</span>
                                        <span className="font-medium text-slate-800">{detailViewPiutang.tanggal}</span>
                                    </div>
                                    <div>
                                        <span className="text-slate-400 block text-[11px]">Unit Apotek Penyerah</span>
                                        <span className="font-medium text-slate-800">{detailViewPiutang.apotek}</span>
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
                                    {isLoadingDetail && !(detailViewPiutang.items && detailViewPiutang.items.length > 0) ? (
                                        <tr>
                                            <td colSpan={8} className="py-8 text-center text-slate-500">
                                                <span className="inline-flex items-center gap-2">
                                                    <Loader2 className="w-4 h-4 animate-spin text-amber-600" />
                                                    <span>Memuat rincian item obat, retur, dan pembayaran...</span>
                                                </span>
                                            </td>
                                        </tr>
                                    ) : detailViewPiutang.items && detailViewPiutang.items.length > 0 ? (
                                        detailViewPiutang.items.map((it, idx) => (
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
                                                Tidak ada rincian item obat.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                                <tfoot className="border-t border-slate-200">
                                    <tr>
                                        <td colSpan={2} className="py-3 text-right font-semibold text-slate-600">
                                            Total Kuantitas
                                        </td>
                                        <td className="py-3 text-center font-bold text-slate-900">{totalDetailQty}</td>
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
                                    <span className="font-medium text-slate-800">{formatNumberOnly(detailViewPiutang.subtotal)}</span>
                                </div>
                                <div className="flex justify-between text-slate-800 font-bold pt-1">
                                    <span>Total Tagihan (Bruto)</span>
                                    <span>{formatNumberOnly(detailViewPiutang.total)}</span>
                                </div>

                                {detailViewPiutang.total_retur > 0 && (
                                    <>
                                        <div className="flex justify-between text-rose-600 font-semibold pt-1">
                                            <span className="flex items-center gap-1">
                                                <RotateCcw className="w-3 h-3" />
                                                <span>Retur Penjualan</span>
                                            </span>
                                            <span>- {formatNumberOnly(detailViewPiutang.total_retur)}</span>
                                        </div>
                                        <div className="flex justify-between text-slate-700 font-semibold pt-1 border-t border-dashed border-slate-200">
                                            <span>Tagihan Bersih (Net)</span>
                                            <span className="font-bold">{formatNumberOnly(detailViewPiutang.net_total)}</span>
                                        </div>
                                    </>
                                )}

                                {detailViewPiutang.terbayar > 0 && (
                                    <div className="flex justify-between text-emerald-600 font-semibold pt-1">
                                        <span>Sudah Diterima</span>
                                        <span>- {formatNumberOnly(detailViewPiutang.terbayar)}</span>
                                    </div>
                                )}

                                <div className="bg-amber-50/80 border border-amber-200 p-3.5 rounded-xl flex justify-between items-center text-amber-900 font-bold mt-3">
                                    <span className="text-xs">Sisa Piutang</span>
                                    <span className="text-sm font-extrabold text-amber-700">{formatNumberOnly(detailViewPiutang.sisa)}</span>
                                </div>

                                {detailViewPiutang.sisa > 0.005 && (
                                    <button
                                        type="button"
                                        onClick={() => openPaymentModal(detailViewPiutang)}
                                        className="w-full mt-2 py-2.5 px-4 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-semibold transition flex items-center justify-center gap-2 shadow-xs cursor-pointer"
                                    >
                                        <CreditCard className="w-4 h-4" />
                                        <span>Terima Pembayaran Sekarang</span>
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* KARTU RIWAYAT RETUR PENJUALAN (KREDIT) JIKA ADA */}
                    {detailViewPiutang.returs && detailViewPiutang.returs.length > 0 && (
                        <div className="bg-white rounded-2xl border border-rose-200 p-6 shadow-xs space-y-4">
                            <div className="flex items-center justify-between border-b border-rose-100 pb-4">
                                <div className="flex items-center gap-2.5">
                                    <div className="p-2 rounded-xl bg-rose-50 text-rose-600">
                                        <RotateCcw className="w-5 h-5" />
                                    </div>
                                    <div>
                                        <h3 className="font-bold text-slate-900 text-sm">
                                            Riwayat Retur Penjualan Kredit
                                        </h3>
                                        <p className="text-[11px] text-slate-400">
                                            Item obat yang dikembalikan dan langsung memotong sisa tagihan piutang faktur ini.
                                        </p>
                                    </div>
                                </div>

                                <span className="text-xs font-semibold text-slate-600">
                                    Total Potongan Retur: <strong className="text-rose-600">- {formatRupiah(detailViewPiutang.total_retur)}</strong>
                                </span>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="text-slate-400 bg-rose-50/40 border-b border-rose-100">
                                        <tr>
                                            <th className="py-2.5 px-3 font-medium">Tanggal</th>
                                            <th className="py-2.5 px-3 font-medium">No. Retur</th>
                                            <th className="py-2.5 px-3 font-medium">Item Obat</th>
                                            <th className="py-2.5 px-3 font-medium text-center">Qty Diretur</th>
                                            <th className="py-2.5 px-3 font-medium text-right">Potongan Piutang</th>
                                            <th className="py-2.5 px-3 font-medium text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-rose-50 text-slate-700">
                                        {detailViewPiutang.returs.map((ret, idx) => (
                                            <tr key={ret.id || idx} className="hover:bg-rose-50/30">
                                                <td className="py-3 px-3 font-medium text-slate-900">{ret.tanggal}</td>
                                                <td className="py-3 px-3 font-mono text-[11px] font-semibold text-rose-700">{ret.code}</td>
                                                <td className="py-3 px-3">
                                                    <div className="font-semibold text-slate-800">{ret.nama}</div>
                                                    <div className="text-[10px] text-slate-400 font-mono">{ret.sku}</div>
                                                </td>
                                                <td className="py-3 px-3 text-center font-bold text-slate-700">
                                                    {ret.qty} {ret.satuan}
                                                </td>
                                                <td className="py-3 px-3 text-right font-extrabold text-rose-600">
                                                    - {formatRupiah(ret.nominal)}
                                                </td>
                                                <td className="py-3 px-3 text-center">
                                                    <span className="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-700 border border-purple-200">
                                                        Memotong Tagihan
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* KARTU AUDIT TRAIL & RIWAYAT PENERIMAAN PEMBAYARAN */}
                    <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div className="flex items-center gap-2.5">
                                <History className="w-5 h-5 text-amber-600" />
                                <div>
                                    <h3 className="font-bold text-slate-900 text-sm">
                                        Pantau log perubahan data
                                    </h3>
                                    <p className="text-[11px] text-slate-400">
                                        Catatan penerimaan pembayaran piutang penjualan, akun kas/bank, dan nama staf pemroses.
                                    </p>
                                </div>
                            </div>

                            <span className="text-xs font-semibold text-slate-600">
                                Total Diterima: <strong className="text-emerald-600">{formatRupiah(detailViewPiutang.terbayar)}</strong>
                            </span>
                        </div>

                        {detailViewPiutang.payments && detailViewPiutang.payments.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="text-slate-400 bg-slate-50/50 border-b border-slate-100">
                                        <tr>
                                            <th className="py-2.5 px-3 font-medium">Tanggal</th>
                                            <th className="py-2.5 px-3 font-medium">Akun Kas & Bank</th>
                                            <th className="py-2.5 px-3 font-medium">No. Referensi</th>
                                            <th className="py-2.5 px-3 font-medium text-right">Nominal Diterima</th>
                                            <th className="py-2.5 px-3 font-medium">Dicatat Oleh</th>
                                            <th className="py-2.5 px-3 font-medium">Waktu Audit</th>
                                            <th className="py-2.5 px-3 font-medium">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 text-slate-700">
                                        {detailViewPiutang.payments.map((pay) => (
                                            <tr key={pay.id} className="hover:bg-slate-50/50">
                                                <td className="py-3 px-3 font-medium text-slate-900">{pay.tanggal}</td>
                                                <td className="py-3 px-3">
                                                    <div className="flex items-center gap-1.5">
                                                        <Landmark className="w-3.5 h-3.5 text-amber-500" />
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
                                <p className="text-xs font-medium text-slate-600">Belum ada penerimaan pembayaran yang dicatat untuk piutang ini.</p>
                                <p className="text-[11px] text-slate-400 mt-0.5">
                                    Gunakan tombol <strong>"Terima Pembayaran"</strong> untuk mencatat pembayaran bertahap atau lunas.
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            ) : (
                /* SUB-VIEW B: TABEL DAFTAR PIUTANG PENJUALAN & RETUR KREDIT */
                <div className="space-y-6">
                    {/* Ringkasan Eksekutif KPI: Piutang & Retur Kredit */}
                    <div className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                            <div>
                                <h2 className="text-base font-bold text-slate-900">Ringkasan Eksekutif Piutang &amp; Retur Kredit</h2>
                                <p className="text-xs text-slate-400 mt-0.5">
                                    Monitoring total omset kredit, potongan retur, penerimaan kas/bank, dan sisa piutang bersih apotek.
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="px-3 py-1 rounded-xl bg-amber-50 text-amber-700 text-xs font-bold border border-amber-100">
                                    {stats.countPiutangBelumBayar || 0} Belum Lunas
                                </span>
                                {Number(stats.countPiutangDiajukan || 0) > 0 && (
                                    <span className="px-3 py-1 rounded-xl bg-blue-50 text-blue-700 text-xs font-bold border border-blue-200">
                                        {stats.countPiutangDiajukan} Diajukan
                                    </span>
                                )}
                                {Number(stats.countPiutangDiretur || 0) > 0 && (
                                    <span className="px-3 py-1 rounded-xl bg-purple-50 text-purple-700 text-xs font-bold border border-purple-200">
                                        {stats.countPiutangDiretur} Diretur Penuh
                                    </span>
                                )}
                                <span className="px-3 py-1 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-100">
                                    {stats.countPiutangLunas || 0} Lunas
                                </span>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-1">
                            {/* Total Piutang Bruto */}
                            <div className="p-4 rounded-xl bg-slate-50 border border-slate-100">
                                <div className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                    Total Piutang Bruto
                                </div>
                                <div className="text-xl font-extrabold text-slate-800 mt-1">
                                    {formatRupiah(stats.totalPiutangBruto || stats.totalPiutang || 0)}
                                </div>
                                <p className="text-[11px] text-slate-400 mt-0.5">
                                    Dari {stats.totalPiutangCount ?? totalFilteredItems} transaksi penjualan
                                </p>
                            </div>

                            {/* Total Retur Kredit */}
                            <div className="p-4 rounded-xl bg-rose-50/60 border border-rose-100">
                                <div className="text-[11px] font-semibold text-rose-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <RotateCcw className="w-3.5 h-3.5 text-rose-600" />
                                    <span>Total Retur Kredit</span>
                                </div>
                                <div className="text-xl font-extrabold text-rose-700 mt-1">
                                    - {formatRupiah(stats.totalPiutangRetur || 0)}
                                </div>
                                <p className="text-[11px] text-rose-600/70 mt-0.5">
                                    {returTotalItems} item retur memotong piutang
                                </p>
                            </div>

                            {/* Sudah Diterima */}
                            <div className="p-4 rounded-xl bg-emerald-50/60 border border-emerald-100">
                                <div className="text-[11px] font-semibold text-emerald-700 uppercase tracking-wider">
                                    Sudah Diterima (Kas/Bank)
                                </div>
                                <div className="text-xl font-extrabold text-emerald-700 mt-1">
                                    {formatRupiah(stats.totalPiutangTerbayar || 0)}
                                </div>
                                <p className="text-[11px] text-emerald-600/70 mt-0.5">
                                    Total pelunasan yang telah masuk
                                </p>
                            </div>

                            {/* Sisa Piutang Bersih */}
                            <div className="p-4 rounded-xl bg-amber-50/80 border border-amber-200">
                                <div className="text-[11px] font-semibold text-amber-800 uppercase tracking-wider">
                                    Sisa Piutang Bersih (Net)
                                </div>
                                <div className="text-2xl font-black text-amber-700 mt-1">
                                    {formatRupiah(stats.totalPiutangSisa || 0)}
                                </div>
                                <p className="text-[11px] text-amber-700/80 mt-0.5 font-medium">
                                    Kewajiban aktif yang wajib ditagih
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Tab Switcher: Faktur Piutang Penjualan vs Riwayat Retur Kredit */}
                    <div className="flex items-center gap-2 border-b border-slate-200">
                        <button
                            type="button"
                            onClick={() => {
                                setMainTab('faktur');
                                navigatePiutang({ tab: 'faktur' });
                            }}
                            className={`inline-flex items-center gap-2 px-4 py-3 text-xs font-bold border-b-2 transition cursor-pointer ${
                                mainTab === 'faktur'
                                    ? 'border-amber-600 text-amber-700'
                                    : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                        >
                            <FileText className="w-4 h-4" />
                            <span>Faktur Piutang Penjualan</span>
                            <span className={`px-2 py-0.5 rounded-full text-[10px] font-extrabold ${
                                mainTab === 'faktur' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'
                            }`}>
                                {stats.totalPiutangCount ?? totalFilteredItems}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() => {
                                setMainTab('retur');
                                navigatePiutang({ tab: 'retur' });
                            }}
                            className={`inline-flex items-center gap-2 px-4 py-3 text-xs font-bold border-b-2 transition cursor-pointer ${
                                mainTab === 'retur'
                                    ? 'border-rose-600 text-rose-700'
                                    : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                        >
                            <RotateCcw className="w-4 h-4 text-rose-600" />
                            <span>Riwayat Retur Kredit</span>
                            <span className={`px-2 py-0.5 rounded-full text-[10px] font-extrabold ${
                                mainTab === 'retur' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-600'
                            }`}>
                                {returTotalItems}
                            </span>
                        </button>
                    </div>

                    {mainTab === 'faktur' ? (
                        /* KONTEN TAB 1: FAKTUR PIUTANG PENJUALAN */
                        <div className="space-y-4">
                            {/* Filter & Toolbar */}
                            <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs space-y-3">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="flex flex-wrap items-center gap-2.5 flex-1">

                                        {/* Search Input */}
                                        <div className="relative w-full sm:w-64">
                                            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                                            <input
                                                type="text"
                                                placeholder="Cari transaksi, debitur, pasien..."
                                                value={searchTerm}
                                                onChange={(e) => setSearchTerm(e.target.value)}
                                                className="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:bg-white focus:outline-none"
                                            />
                                        </div>

                                        {/* Debtor Filter Combobox */}
                                        <DebtorCombobox
                                            debtors={debtors}
                                            selectedDebtorId={debtorFilter}
                                            onSelectDebtor={(val) => {
                                                setDebtorFilter(val);
                                                navigatePiutang({ debtor_id: val, page: 1 });
                                            }}
                                            placeholder="Filter Debitur (Instansi)..."
                                        />

                                        {/* Status Filter Segmented */}
                                        <div className="flex items-center bg-slate-100 p-1 rounded-xl text-xs flex-wrap gap-0.5">
                                            <button
                                                type="button"
                                                onClick={() => { setStatusFilter('ALL'); navigatePiutang({ status: 'ALL', page: 1 }); }}
                                                className={`px-3 py-1.5 rounded-lg font-semibold transition cursor-pointer ${statusFilter === 'ALL' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'}`}
                                            >
                                                Semua
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => { setStatusFilter('BELUM_LUNAS'); navigatePiutang({ status: 'BELUM_LUNAS', page: 1 }); }}
                                                className={`px-3 py-1.5 rounded-lg font-semibold transition cursor-pointer ${statusFilter === 'BELUM_LUNAS' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'}`}
                                            >
                                                Belum Lunas
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => { setStatusFilter('DIAJUKAN'); navigatePiutang({ status: 'DIAJUKAN', page: 1 }); }}
                                                className={`px-3 py-1.5 rounded-lg font-semibold transition cursor-pointer ${statusFilter === 'DIAJUKAN' ? 'bg-white text-blue-700 shadow-xs font-bold' : 'text-slate-500 hover:text-slate-800'}`}
                                            >
                                                Diajukan
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => { setStatusFilter('RETUR'); navigatePiutang({ status: 'RETUR', page: 1 }); }}
                                                className={`px-3 py-1.5 rounded-lg font-semibold transition cursor-pointer ${statusFilter === 'RETUR' ? 'bg-white text-rose-700 shadow-xs font-bold' : 'text-slate-500 hover:text-slate-800'}`}
                                            >
                                                Ada Retur
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => { setStatusFilter('LUNAS'); navigatePiutang({ status: 'LUNAS', page: 1 }); }}
                                                className={`px-3 py-1.5 rounded-lg font-semibold transition cursor-pointer ${statusFilter === 'LUNAS' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'}`}
                                            >
                                                Lunas
                                            </button>
                                        </div>

                                        {/* Filter 1: Waktu Pembuatan ASC / DESC */}
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const nextOrder = sortCreatedAt === 'asc' ? 'desc' : 'asc';
                                                setSortCreatedAt(nextOrder);
                                                navigatePiutang({ sort_order: nextOrder, page: 1 });
                                            }}
                                            className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border transition cursor-pointer ${sortCreatedAt === 'asc'
                                                    ? 'bg-amber-50 border-amber-300 text-amber-800 font-bold shadow-2xs'
                                                    : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
                                                }`}
                                            title="Klik untuk mengubah urutan waktu pembuatan"
                                        >
                                            {sortCreatedAt === 'asc' ? (
                                                <ArrowUp className="w-3.5 h-3.5 text-amber-600" />
                                            ) : (
                                                <ArrowDown className="w-3.5 h-3.5 text-slate-500" />
                                            )}
                                            <span>Waktu: {sortCreatedAt === 'asc' ? 'Terlama (ASC)' : 'Terbaru (DESC)'}</span>
                                        </button>

                                        {/* Filter 2: Berdasarkan Jatuh Tempo */}
                                        <div className="relative">
                                            <button
                                                type="button"
                                                onClick={() => setIsDueDropdownOpen(!isDueDropdownOpen)}
                                                className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border transition cursor-pointer ${dueFilter !== 'all'
                                                        ? 'bg-amber-50 border-amber-300 text-amber-800 font-bold shadow-2xs'
                                                        : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
                                                    }`}
                                            >
                                                <Clock className={`w-3.5 h-3.5 ${dueFilter !== 'all' ? 'text-amber-600' : 'text-slate-500'}`} />
                                                <span>
                                                    {dueFilter === 'overdue' && 'Tempo: Lewat Tempo'}
                                                    {dueFilter === 'due_soon' && 'Tempo: Mendekati (≤ 7 Hari)'}
                                                    {dueFilter === 'due_nearest' && 'Tempo: Terdekat'}
                                                    {dueFilter === 'due_furthest' && 'Tempo: Terjauh'}
                                                    {dueFilter === 'all' && 'Filter Jatuh Tempo'}
                                                </span>
                                                <ChevronDown className="w-3 h-3 text-slate-400" />
                                            </button>
                                            {isDueDropdownOpen && (
                                                <div className="absolute left-0 mt-1.5 w-60 bg-white border border-slate-200 rounded-xl shadow-xl z-50 py-1 divide-y divide-slate-100 text-xs animate-in fade-in zoom-in-95 duration-150">
                                                    <button
                                                        type="button"
                                                        onClick={() => { setDueFilter('all'); setIsDueDropdownOpen(false); navigatePiutang({ due_mode: 'all', page: 1 }); }}
                                                        className={`w-full text-left px-3.5 py-2 hover:bg-slate-50 flex items-center justify-between cursor-pointer ${dueFilter === 'all' ? 'font-bold text-amber-700 bg-amber-50/50' : 'text-slate-700'}`}
                                                    >
                                                        <span>Semua Jatuh Tempo</span>
                                                        {dueFilter === 'all' && <Check className="w-3.5 h-3.5 text-amber-600" />}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => { setDueFilter('overdue'); setIsDueDropdownOpen(false); navigatePiutang({ due_mode: 'overdue', page: 1 }); }}
                                                        className={`w-full text-left px-3.5 py-2 hover:bg-red-50 flex items-center justify-between text-red-700 cursor-pointer ${dueFilter === 'overdue' ? 'font-bold bg-red-50' : ''}`}
                                                    >
                                                        <div className="flex items-center gap-1.5">
                                                            <AlertTriangle className="w-3.5 h-3.5 text-red-600" />
                                                            <span>Lewat Jatuh Tempo (Overdue)</span>
                                                        </div>
                                                        {dueFilter === 'overdue' && <Check className="w-3.5 h-3.5 text-red-600" />}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => { setDueFilter('due_soon'); setIsDueDropdownOpen(false); navigatePiutang({ due_mode: 'due_soon', page: 1 }); }}
                                                        className={`w-full text-left px-3.5 py-2 hover:bg-amber-50 flex items-center justify-between text-amber-800 cursor-pointer ${dueFilter === 'due_soon' ? 'font-bold bg-amber-50' : ''}`}
                                                    >
                                                        <div className="flex items-center gap-1.5">
                                                            <Clock className="w-3.5 h-3.5 text-amber-600" />
                                                            <span>Mendekati Tempo (≤ 7 Hari)</span>
                                                        </div>
                                                        {dueFilter === 'due_soon' && <Check className="w-3.5 h-3.5 text-amber-600" />}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => { setDueFilter('due_nearest'); setIsDueDropdownOpen(false); navigatePiutang({ due_mode: 'due_nearest', page: 1 }); }}
                                                        className={`w-full text-left px-3.5 py-2 hover:bg-slate-50 flex items-center justify-between cursor-pointer ${dueFilter === 'due_nearest' ? 'font-bold text-amber-700 bg-amber-50/50' : 'text-slate-700'}`}
                                                    >
                                                        <span>Urutkan Tempo Terdekat</span>
                                                        {dueFilter === 'due_nearest' && <Check className="w-3.5 h-3.5 text-amber-600" />}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => { setDueFilter('due_furthest'); setIsDueDropdownOpen(false); navigatePiutang({ due_mode: 'due_furthest', page: 1 }); }}
                                                        className={`w-full text-left px-3.5 py-2 hover:bg-slate-50 flex items-center justify-between cursor-pointer ${dueFilter === 'due_furthest' ? 'font-bold text-amber-700 bg-amber-50/50' : 'text-slate-700'}`}
                                                    >
                                                        <span>Urutkan Tempo Terjauh</span>
                                                        {dueFilter === 'due_furthest' && <Check className="w-3.5 h-3.5 text-amber-600" />}
                                                    </button>
                                                </div>
                                            )}
                                        </div>

                                        {/* Tombol Tambah Saldo Awal, Import & Export Excel */}
                                        <button
                                            type="button"
                                            onClick={openOpeningModal}
                                            className="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold transition shadow-2xs cursor-pointer"
                                            title="Tambah tagihan piutang pra-sistem (Saldo Awal) tanpa mengubah stok, omzet, & Kas/Bank"
                                        >
                                            <Plus className="w-3.5 h-3.5" />
                                            <span>Tambah Saldo Awal</span>
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setIsImportModalOpen(true)}
                                            className="inline-flex items-center gap-1.5 px-3 py-2 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-xl text-xs font-semibold transition shadow-2xs"
                                        >
                                            <Upload className="w-3.5 h-3.5" />
                                            <span>Import Pembayaran</span>
                                        </button>
                                        <a
                                            href={`/finance/export/piutang?search=${encodeURIComponent(searchTerm)}&status=${encodeURIComponent(statusFilter === 'ALL' ? '' : statusFilter)}&debtor_id=${encodeURIComponent(debtorFilter)}&sort_by=${dueFilter.startsWith('due_') ? 'due_date' : 'created_at'}&sort_order=${sortCreatedAt}&due_mode=${encodeURIComponent(dueFilter)}&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`}
                                            className="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-50 hover:bg-emerald-100/80 text-emerald-800 border border-emerald-200 rounded-xl text-xs font-semibold transition shadow-2xs cursor-pointer"
                                            title="Export data piutang ke Excel (.xlsx) dengan kolom retur penjualan dan sisa piutang bersih"
                                        >
                                            <FileSpreadsheet className="w-3.5 h-3.5 text-emerald-600" />
                                            <span>Export Excel</span>
                                        </a>
                                    </div>

                                    {selectedPiutangIds.length > 0 && (
                                        <button
                                            onClick={openBulkModal}
                                            className="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center gap-2 animate-in zoom-in-95 duration-150"
                                        >
                                            <ShoppingCart className="w-4 h-4" />
                                            <span>Pelunasan Massal ({selectedPiutangIds.length} Transaksi)</span>
                                        </button>
                                    )}
                                </div>

                                <div className="flex items-center border-t border-slate-100 pt-3">
                                    <DateRangeFilter
                                        startDate={startDate}
                                        endDate={endDate}
                                        preset={datePreset}
                                        accent="amber"
                                        onPreset={handleDatePreset}
                                        onChange={([start, end]) => {
                                            const nextPreset = start || end ? 'custom' : 'all';
                                            setStartDate(start);
                                            setEndDate(end);
                                            setDatePreset(nextPreset);
                                            navigatePiutang({ date_preset: nextPreset, start_date: start, end_date: end, page: 1 });
                                        }}
                                    />
                                </div>

                                {selectionWarning && (
                                    <div className="text-xs text-amber-700 bg-amber-50 px-3 py-2 rounded-xl flex items-center gap-2 border border-amber-200">
                                        <AlertCircle className="w-4 h-4 text-amber-600 shrink-0" />
                                        <span>{selectionWarning}</span>
                                    </div>
                                )}
                            </div>

                            {/* Tabel Piutang Penjualan */}
                            <div className={`bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden transition-opacity ${isLoadingList ? 'opacity-60 pointer-events-none' : ''}`}>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr className="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider text-[11px]">
                                                <th className="px-4 py-3.5 w-10">
                                                    <input
                                                        type="checkbox"
                                                        checked={
                                                            paginatedPiutang.filter(i => !isItemSettled(i)).length > 0 &&
                                                            paginatedPiutang
                                                                .filter(i => !isItemSettled(i))
                                                                .every(i => selectedPiutangIds.includes(i.id))
                                                        }
                                                        onChange={handleSelectAllPiutang}
                                                        className="rounded border-slate-300 text-amber-600 focus:ring-amber-500 cursor-pointer"
                                                        title="Pilih semua di halaman ini"
                                                    />
                                                </th>
                                                <th className="px-4 py-3.5">No. Transaksi &amp; Tgl</th>
                                                <th className="px-4 py-3.5">Jatuh Tempo</th>
                                                <th className="px-4 py-3.5">Debitur / Pasien</th>
                                                <th className="px-4 py-3.5">Apotek</th>
                                                <th className="px-4 py-3.5 text-right">Total Bruto</th>
                                                <th className="px-4 py-3.5 text-right">Retur</th>
                                                <th className="px-4 py-3.5 text-right">Terbayar</th>
                                                <th className="px-4 py-3.5 text-right">Sisa Piutang</th>
                                                <th className="px-4 py-3.5 text-center">Status</th>
                                                <th className="px-4 py-3.5 text-center w-28">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {paginatedPiutang.length === 0 ? (
                                                <tr>
                                                    <td colSpan={11} className="text-center py-12 text-slate-400">
                                                        Tidak ada data piutang yang sesuai dengan filter.
                                                    </td>
                                                </tr>
                                            ) : (
                                                paginatedPiutang.map((item) => {
                                                    const isLunas = isItemSettled(item);
                                                    return (
                                                        <tr
                                                            key={item.id}
                                                            onClick={() => openPiutangDrawer(item)}
                                                            className="hover:bg-amber-50/40 cursor-pointer transition group"
                                                        >
                                                            <td className="px-4 py-3.5" onClick={(e) => e.stopPropagation()}>
                                                                {item.status === 'Diretur Penuh' ? (
                                                                    <span title="Tagihan diretur penuh (Sisa Rp 0)" className="inline-flex items-center justify-center w-5 h-5 rounded text-purple-600 bg-purple-50">
                                                                        <RotateCcw className="w-3.5 h-3.5" />
                                                                    </span>
                                                                ) : isLunas ? (
                                                                    <span title="Piutang sudah lunas" className="inline-block p-0.5 text-emerald-500">
                                                                        <Check className="w-3.5 h-3.5" />
                                                                    </span>
                                                                ) : (
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={selectedPiutangIds.includes(item.id)}
                                                                        onChange={() => handleTogglePiutang(item)}
                                                                        className="rounded border-slate-300 text-amber-600 focus:ring-amber-500 cursor-pointer"
                                                                    />
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3.5">
                                                                <div className="flex items-center gap-1.5 flex-wrap">
                                                                    <button
                                                                        type="button"
                                                                        onClick={(e) => {
                                                                            e.stopPropagation();
                                                                            openPiutangDetail(item);
                                                                        }}
                                                                        className="font-bold text-amber-700 hover:text-amber-800 hover:underline transition text-left cursor-pointer"
                                                                    >
                                                                        {item.nomor}
                                                                    </button>
                                                                    {item.is_opening && (
                                                                        <span className="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                                            Saldo Awal
                                                                        </span>
                                                                    )}
                                                                </div>
                                                                <div className="text-[11px] text-slate-400 mt-0.5">{item.tanggal}</div>
                                                            </td>
                                                            {/* Kolom Jatuh Tempo Visual Jelas */}
                                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                                {isLunas ? (
                                                                    <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200">
                                                                        <Check className="w-3.5 h-3.5 text-emerald-600" />
                                                                        <span>{item.jatuhTempo}</span>
                                                                        <span className="text-[10px] text-emerald-600 font-bold ml-0.5">• Lunas</span>
                                                                    </span>
                                                                ) : item.is_overdue || item.days_overdue > 0 ? (
                                                                    <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-red-100 text-red-800 text-xs font-bold border border-red-300 shadow-2xs">
                                                                        <AlertTriangle className="w-3.5 h-3.5 text-red-600 shrink-0" />
                                                                        <span>{item.jatuhTempo}</span>
                                                                        <span className="text-[10px] bg-red-600 text-white px-1.5 py-0.5 rounded-md font-black">
                                                                            Lewat {item.days_overdue} hr
                                                                        </span>
                                                                    </div>
                                                                ) : item.days_remaining <= 7 ? (
                                                                    <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-100 text-amber-900 text-xs font-bold border border-amber-300 shadow-2xs">
                                                                        <Clock className="w-3.5 h-3.5 text-amber-700 shrink-0" />
                                                                        <span>{item.jatuhTempo}</span>
                                                                        <span className="text-[10px] bg-amber-600 text-white px-1.5 py-0.5 rounded-md font-bold">
                                                                            Sisa {item.days_remaining} hr
                                                                        </span>
                                                                    </div>
                                                                ) : (
                                                                    <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-medium border border-slate-200">
                                                                        <Calendar className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                                                        <span>{item.jatuhTempo}</span>
                                                                        <span className="text-[10px] text-slate-500 font-normal">({item.days_remaining} hr)</span>
                                                                    </div>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3.5">
                                                                <div className="font-semibold text-slate-800">{item.debtor}</div>
                                                                <div className="text-[10px] text-slate-400">
                                                                    {item.pasien && item.pasien !== '-' ? `Pasien: ${item.pasien}` : ''}
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3.5 text-slate-600">{item.apotek}</td>
                                                            <td className="px-4 py-3.5 text-right font-medium text-slate-800">
                                                                {formatNumberOnly(item.total)}
                                                            </td>
                                                            <td className="px-4 py-3.5 text-right font-medium">
                                                                {item.total_retur > 0 ? (
                                                                    <span className="font-semibold text-rose-600" title={`Total Retur Kredit: ${formatRupiah(item.total_retur)}`}>
                                                                        - {formatNumberOnly(item.total_retur)}
                                                                    </span>
                                                                ) : (
                                                                    <span className="text-slate-300">-</span>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3.5 text-right text-emerald-600 font-medium">
                                                                {formatNumberOnly(item.terbayar)}
                                                            </td>
                                                            <td className="px-4 py-3.5 text-right font-bold text-amber-700">
                                                                {formatNumberOnly(item.sisa)}
                                                            </td>
                                                            <td className="px-4 py-3.5 text-center">
                                                                <div className="flex flex-col items-center gap-1">
                                                                    {renderStatusBadge(item)}
                                                                    {item.is_submitted && !isLunas && (
                                                                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 border border-blue-200">
                                                                            Diajukan
                                                                        </span>
                                                                    )}
                                                                    {isLunas && item.tanggalLunas && item.status !== 'Diretur Penuh' && (
                                                                        <span className="text-[10px] font-medium text-emerald-600">Lunas {item.tanggalLunas}</span>
                                                                    )}
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3.5 text-center" onClick={(e) => e.stopPropagation()}>
                                                                <div className="flex items-center justify-center gap-1.5">
                                                                    {item.sisa > 0.005 && (
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => openPaymentModal(item)}
                                                                            className="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-semibold transition cursor-pointer"
                                                                        >
                                                                            Terima
                                                                        </button>
                                                                    )}
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => openPiutangDetail(item)}
                                                                        className="px-2.5 py-1 border border-slate-200 text-slate-600 hover:bg-slate-100 rounded-lg text-xs font-semibold transition cursor-pointer"
                                                                    >
                                                                        Detil
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
                                {(totalPages > 1 || totalFilteredItems > 0) && (
                                    <div className="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                                        <div>
                                            Menampilkan {pageFrom} - {pageTo} dari {totalFilteredItems} transaksi
                                        </div>
                                        {totalPages > 1 && (
                                            <div className="flex items-center gap-1">
                                                <button
                                                    onClick={() => navigatePiutang({ page: Math.max(1, currentPage - 1) })}
                                                    disabled={currentPage <= 1 || isLoadingList}
                                                    className="p-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50 cursor-pointer"
                                                >
                                                    <ChevronLeft className="w-4 h-4" />
                                                </button>
                                                <span className="px-3 py-1 font-semibold text-slate-800">
                                                    {currentPage} / {totalPages}
                                                </span>
                                                <button
                                                    onClick={() => navigatePiutang({ page: Math.min(totalPages, currentPage + 1) })}
                                                    disabled={currentPage >= totalPages || isLoadingList}
                                                    className="p-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50 cursor-pointer"
                                                >
                                                    <ChevronRight className="w-4 h-4" />
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    ) : (
                        /* KONTEN TAB 2: RIWAYAT RETUR KREDIT */
                        <div className="space-y-4">
                            {/* Filter Toolbar Retur */}
                            <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs space-y-3">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="flex flex-wrap items-center gap-2.5 flex-1">
                                        {/* Search Retur */}
                                        <div className="relative w-full sm:w-72">
                                            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                                            <input
                                                type="text"
                                                placeholder="Cari no. retur, faktur, debitur, obat..."
                                                value={returSearchTerm}
                                                onChange={(e) => setReturSearchTerm(e.target.value)}
                                                className="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-rose-500 focus:bg-white focus:outline-none"
                                            />
                                        </div>

                                        {/* Debtor Combobox */}
                                        <DebtorCombobox
                                            debtors={debtors}
                                            selectedDebtorId={returDebtorFilter}
                                            onSelectDebtor={(val) => {
                                                setReturDebtorFilter(val);
                                                navigatePiutang({ tab: 'retur', retur_debtor_id: val, retur_page: 1 });
                                            }}
                                            placeholder="Filter Debitur (Instansi)..."
                                        />
                                    </div>

                                    <div className="flex items-center gap-2 text-xs">
                                        <span className="text-slate-500">Total Potongan Retur:</span>
                                        <span className="font-extrabold text-rose-700 bg-rose-50 px-2.5 py-1 rounded-lg border border-rose-200">
                                            - {formatRupiah(stats.totalPiutangRetur || 0)}
                                        </span>
                                    </div>
                                </div>

                                <div className="flex items-center border-t border-slate-100 pt-3">
                                    <DateRangeFilter
                                        startDate={startDate}
                                        endDate={endDate}
                                        preset={datePreset}
                                        accent="rose"
                                        onPreset={handleDatePreset}
                                        onChange={([start, end]) => {
                                            const nextPreset = start || end ? 'custom' : 'all';
                                            setStartDate(start);
                                            setEndDate(end);
                                            setDatePreset(nextPreset);
                                            navigatePiutang({ tab: 'retur', date_preset: nextPreset, start_date: start, end_date: end, retur_page: 1 });
                                        }}
                                    />
                                </div>
                            </div>

                            {/* Tabel Riwayat Retur Penjualan Kredit */}
                            <div className="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr className="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider text-[11px]">
                                                <th className="px-4 py-3.5">No. Retur &amp; Tgl</th>
                                                <th className="px-4 py-3.5">Faktur Asal</th>
                                                <th className="px-4 py-3.5">Debitur / Pasien</th>
                                                <th className="px-4 py-3.5">Apotek</th>
                                                <th className="px-4 py-3.5">Item Obat Diretur</th>
                                                <th className="px-4 py-3.5 text-center">Qty Retur</th>
                                                <th className="px-4 py-3.5 text-right">Potongan Piutang</th>
                                                <th className="px-4 py-3.5 text-center">Status</th>
                                                <th className="px-4 py-3.5 text-center w-28">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {paginatedCreditReturns.length === 0 ? (
                                                <tr>
                                                    <td colSpan={9} className="text-center py-12 text-slate-400">
                                                        <RotateCcw className="w-8 h-8 mx-auto text-slate-300 mb-2" />
                                                        <p className="font-semibold text-slate-600">Tidak ada data retur kredit yang sesuai filter.</p>
                                                        <p className="text-[11px] text-slate-400 mt-0.5">
                                                            Retur dari transaksi kredit otomatis memotong piutang dan dicatat di sini.
                                                        </p>
                                                    </td>
                                                </tr>
                                            ) : (
                                                paginatedCreditReturns.map((ret) => {
                                                    return (
                                                        <tr key={ret.id} className="hover:bg-rose-50/30 transition">
                                                            <td className="px-4 py-3.5">
                                                                <div className="font-bold text-rose-700 font-mono">{ret.code}</div>
                                                                <div className="text-[11px] text-slate-400 mt-0.5">{ret.tanggal}</div>
                                                            </td>
                                                            <td className="px-4 py-3.5">
                                                                {ret.transaction_id ? (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => openPiutangDetailById(ret.transaction_id, ret)}
                                                                        className="font-bold text-amber-700 hover:text-amber-800 hover:underline transition text-left cursor-pointer"
                                                                        title="Klik untuk melihat rincian faktur piutang asal"
                                                                    >
                                                                        {ret.transaction_code}
                                                                    </button>
                                                                ) : (
                                                                    <span className="font-semibold text-slate-700">{ret.transaction_code}</span>
                                                                )}
                                                                <div className="text-[10px] text-slate-400">Faktur Kredit</div>
                                                            </td>
                                                            <td className="px-4 py-3.5">
                                                                <div className="font-semibold text-slate-800">{ret.debtor}</div>
                                                                <div className="text-[10px] text-slate-400">
                                                                    {ret.pasien && ret.pasien !== '-' ? `Pasien: ${ret.pasien}` : ''}
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3.5 text-slate-600">{ret.apotek}</td>
                                                            <td className="px-4 py-3.5">
                                                                <div className="font-semibold text-slate-800">{ret.medicine_name}</div>
                                                                <div className="text-[10px] text-slate-400 font-mono">{ret.medicine_sku}</div>
                                                            </td>
                                                            <td className="px-4 py-3.5 text-center font-bold text-slate-700">
                                                                {ret.qty_retur} {ret.satuan}
                                                            </td>
                                                            <td className="px-4 py-3.5 text-right font-extrabold text-rose-600">
                                                                - {formatNumberOnly(ret.total_retur)}
                                                            </td>
                                                            <td className="px-4 py-3.5 text-center">
                                                                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                                                    <Check className="w-3 h-3 text-purple-600" />
                                                                    <span>Memotong Piutang</span>
                                                                </span>
                                                            </td>
                                                            <td className="px-4 py-3.5 text-center">
                                                                {ret.transaction_id ? (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => openPiutangDetailById(ret.transaction_id, ret)}
                                                                        className="px-2.5 py-1 border border-slate-200 hover:border-amber-300 text-slate-600 hover:text-amber-800 hover:bg-amber-50 rounded-lg text-xs font-semibold transition cursor-pointer"
                                                                    >
                                                                        Buka Faktur
                                                                    </button>
                                                                ) : (
                                                                    <span className="text-slate-300 text-xs">-</span>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    );
                                                })
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Pagination Retur */}
                                {(returTotalPages > 1 || returTotalItems > 0) && (
                                    <div className="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                                        <div>
                                            Menampilkan {returPageFrom} - {returPageTo} dari {returTotalItems} data retur kredit
                                        </div>
                                        {returTotalPages > 1 && (
                                            <div className="flex items-center gap-1">
                                                <button
                                                    onClick={() => navigatePiutang({ tab: 'retur', retur_page: Math.max(1, returCurrentPage - 1) })}
                                                    disabled={returCurrentPage <= 1 || isLoadingList}
                                                    className="p-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50 cursor-pointer"
                                                >
                                                    <ChevronLeft className="w-4 h-4" />
                                                </button>
                                                <span className="px-3 py-1 font-semibold text-slate-800">
                                                    {returCurrentPage} / {returTotalPages}
                                                </span>
                                                <button
                                                    onClick={() => navigatePiutang({ tab: 'retur', retur_page: Math.min(returTotalPages, returCurrentPage + 1) })}
                                                    disabled={returCurrentPage >= returTotalPages || isLoadingList}
                                                    className="p-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50 cursor-pointer"
                                                >
                                                    <ChevronRight className="w-4 h-4" />
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Modal Terima Pembayaran Piutang Satuan */}
            {isPaymentModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <ShoppingCart className="w-5 h-5 text-amber-600" />
                                <h3 className="font-bold text-slate-900 text-sm">Penerimaan Pembayaran Piutang</h3>
                            </div>
                            <button
                                onClick={() => setIsPaymentModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <form onSubmit={handlePaymentSubmit} className="p-6 space-y-4 text-xs">
                            <div className="p-3 bg-amber-50/70 border border-amber-200 rounded-xl space-y-1">
                                <div className="flex justify-between text-slate-600">
                                    <span>No. Transaksi:</span>
                                    <span className="font-bold text-slate-900">{paymentForm.invoice_number}</span>
                                </div>
                                <div className="flex justify-between text-slate-600">
                                    <span>Debitur:</span>
                                    <span className="font-semibold text-slate-800">{paymentForm.debtor}</span>
                                </div>
                                <div className="flex justify-between text-amber-800 pt-1 border-t border-amber-200 font-bold">
                                    <span>Sisa Piutang:</span>
                                    <span>{formatRupiah(paymentForm.sisa)}</span>
                                </div>
                            </div>

                            <label className={`flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition ${paymentForm.is_cutoff ? 'bg-indigo-50 border-indigo-300' : 'bg-slate-50 border-slate-200 hover:bg-slate-100/70'}`}>
                                <input
                                    type="checkbox"
                                    checked={Boolean(paymentForm.is_cutoff)}
                                    onChange={(e) => {
                                        const checked = e.target.checked;
                                        setPaymentForm({
                                            ...paymentForm,
                                            is_cutoff: checked,
                                            payment_date: checked ? getDefaultCutoffDate() : new Date().toISOString().split('T')[0],
                                        });
                                    }}
                                    className="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <div>
                                    <div className="font-bold text-slate-800">Pelunasan Pra-Cut-off (Tanpa Tambah Kas &amp; Bank)</div>
                                    <div className="text-[11px] text-slate-500 mt-0.5 leading-snug">
                                        Aktifkan untuk transaksi piutang lama sebelum tanggal cut-off yang sudah lunas di luar sistem. Saldo Kas &amp; Bank tidak akan bertambah.
                                    </div>
                                </div>
                            </label>

                            {paymentForm.is_cutoff ? (
                                <div className="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-[11px] leading-relaxed">
                                    <strong>Mode Pra-Cut-off aktif:</strong> Pelunasan ini hanya melunasi tagihan &amp; mengurangi Saldo Piutang Dagang tanpa menambah saldo Kas &amp; Bank. Isi <strong>Tanggal Terima</strong> pada atau sebelum tanggal cut-off Saldo Awal (misal 31 Okt).
                                </div>
                            ) : (
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Terima Ke Akun (Kas &amp; Bank) <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={paymentForm.account_id}
                                        onChange={(e) => setPaymentForm({ ...paymentForm, account_id: e.target.value })}
                                        required
                                        className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                    >
                                        <option value="">-- Pilih Akun Kas &amp; Bank --</option>
                                        {kasBankAccounts.map((acc) => (
                                            <option key={acc.id} value={acc.id}>
                                                {acc.name} ({acc.code}) - Saldo: {formatRupiah(acc.balance)}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Tanggal Terima <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        value={paymentForm.payment_date}
                                        onChange={(e) => setPaymentForm({ ...paymentForm, payment_date: e.target.value })}
                                        required
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Jumlah Pembayaran (Rp) <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="number"
                                        step="any"
                                        min="0.01"
                                        value={paymentForm.amount}
                                        onChange={(e) => setPaymentForm({ ...paymentForm, amount: e.target.value === '' ? '' : parseFloat(e.target.value) })}
                                        required
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-amber-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                    />
                                    {paymentForm.sisa > 0 && (
                                        <div className="flex items-center justify-between mt-1 text-[11px] text-slate-400">
                                            <span>Sisa: {formatRupiah(paymentForm.sisa)}</span>
                                            <button
                                                type="button"
                                                onClick={() => setPaymentForm({ ...paymentForm, amount: paymentForm.sisa })}
                                                className="text-amber-600 hover:underline font-semibold"
                                            >
                                                Set Lunas Penuh
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    No. Referensi / Bukti Transfer
                                </label>
                                <input
                                    type="text"
                                    placeholder="Contoh: TRF-PIUTANG-001"
                                    value={paymentForm.reference_number}
                                    onChange={(e) => setPaymentForm({ ...paymentForm, reference_number: e.target.value })}
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">Catatan</label>
                                <textarea
                                    rows={2}
                                    value={paymentForm.notes}
                                    onChange={(e) => setPaymentForm({ ...paymentForm, notes: e.target.value })}
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
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
                                    className={`px-4 py-2 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50 ${paymentForm.is_cutoff ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-amber-600 hover:bg-amber-700'}`}
                                >
                                    {isSubmittingPayment ? 'Memproses...' : (paymentForm.is_cutoff ? 'Simpan Pelunasan Pra-Cut-off' : 'Konfirmasi Penerimaan')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Impor Pembayaran Piutang */}
            {isImportModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Upload className="w-5 h-5 text-amber-600" />
                                <h3 className="font-bold text-slate-900 text-sm">Impor Pembayaran Piutang</h3>
                            </div>
                            <button type="button" onClick={() => setIsImportModalOpen(false)} className="text-slate-400 hover:text-slate-600 p-1 rounded-lg" aria-label="Tutup">
                                <X className="w-4 h-4" />
                            </button>
                        </div>
                        <form onSubmit={handleImportPayments} className="p-6 space-y-4 text-xs">
                            <p className="text-slate-600 leading-relaxed">
                                Satu transaksi per baris. Sistem mencatat seluruh sisa piutang sebagai penerimaan ke akun Kas &amp; Bank.
                            </p>
                            <label className={`flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition ${importIsCutoff ? 'bg-indigo-50 border-indigo-300' : 'bg-slate-50 border-slate-200 hover:bg-slate-100/70'}`}>
                                <input
                                    type="checkbox"
                                    checked={importIsCutoff}
                                    onChange={(e) => setImportIsCutoff(e.target.checked)}
                                    className="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <div>
                                    <div className="font-bold text-slate-800">Mode Pelunasan Pra-Cut-off (Tanpa Tambah Kas &amp; Bank)</div>
                                    <div className="text-[11px] text-slate-500 mt-0.5 leading-snug">
                                        Aktifkan jika seluruh baris di file adalah transaksi piutang lama pra-cut-off. Kolom Kode Bank boleh dikosongkan (atau isi <code className="px-1 bg-white rounded border">CUTOFF</code> per baris).
                                    </div>
                                </div>
                            </label>
                            <div className="rounded-xl bg-slate-50 border border-slate-200 p-3 text-slate-600">
                                <div className="font-semibold text-slate-700 mb-1">Urutan kolom wajib</div>
                                <div>A: No. Faktur/Transaksi · B: Tanggal Bayar · {importIsCutoff ? 'C: Kode Bank (opsional)' : 'C: Kode Bank'}</div>
                                <div className="mt-1 text-slate-500">Baris pertama dilewati. Tanggal: YYYY-MM-DD atau DD/MM/YYYY. Kode Bank harus cocok dengan kode akun Kas &amp; Bank (atau <code className="px-1 bg-white rounded border">CUTOFF</code> untuk non-kas).</div>
                            </div>
                            <a href="/finance/piutang/import-template" className="inline-flex items-center gap-1.5 text-amber-700 font-semibold hover:text-amber-800">
                                <Download className="w-3.5 h-3.5" /> Unduh template CSV
                            </a>
                            <input
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                onChange={(e) => setImportFile(e.target.files?.[0] || null)}
                                className="block w-full rounded-xl border border-slate-200 p-2.5 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-amber-50 file:px-3 file:py-1.5 file:font-semibold file:text-amber-700"
                                required
                            />
                            {importFile && <div className="text-slate-500 truncate">File dipilih: {importFile.name}</div>}
                            <div className="pt-3 border-t border-slate-100 flex justify-end gap-2">
                                <button type="button" onClick={() => setIsImportModalOpen(false)} className="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50">Batal</button>
                                <button type="submit" disabled={isImporting || !importFile} className="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-semibold disabled:opacity-50">
                                    {isImporting ? 'Memproses...' : 'Impor & Catat Pembayaran'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Pelunasan Massal Piutang */}
            {isBulkModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <ShoppingCart className="w-5 h-5 text-amber-600" />
                                <h3 className="font-bold text-slate-900 text-sm">
                                    Pelunasan Massal ({selectedPiutangIds.length} Transaksi Terpilih)
                                </h3>
                            </div>
                            <button
                                onClick={() => setIsBulkModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <form onSubmit={handleBulkSubmit} className="p-6 space-y-4 text-xs">
                            <div className="p-3 bg-amber-50/70 border border-amber-200 rounded-xl">
                                <div className="text-slate-500 font-medium">Total Nominal Pelunasan Piutang:</div>
                                <div className="text-xl font-black text-amber-700 mt-0.5">
                                    {formatRupiah(totalSelectedPiutangSisa)}
                                </div>
                            </div>

                            <label className={`flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition ${bulkForm.is_cutoff ? 'bg-indigo-50 border-indigo-300' : 'bg-slate-50 border-slate-200 hover:bg-slate-100/70'}`}>
                                <input
                                    type="checkbox"
                                    checked={Boolean(bulkForm.is_cutoff)}
                                    onChange={(e) => {
                                        const checked = e.target.checked;
                                        setBulkForm({
                                            ...bulkForm,
                                            is_cutoff: checked,
                                            payment_date: checked ? getDefaultCutoffDate() : new Date().toISOString().split('T')[0],
                                        });
                                    }}
                                    className="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <div>
                                    <div className="font-bold text-slate-800">Pelunasan Pra-Cut-off (Tanpa Tambah Kas &amp; Bank)</div>
                                    <div className="text-[11px] text-slate-500 mt-0.5 leading-snug">
                                        Untuk menandai lunas transaksi piutang lama sebelum cut-off yang sudah terbayar di luar sistem. Saldo Kas &amp; Bank tetap utuh.
                                    </div>
                                </div>
                            </label>

                            {bulkForm.is_cutoff ? (
                                <div className="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-[11px] leading-relaxed">
                                    <strong>Mode Pra-Cut-off aktif:</strong> {selectedPiutangIds.length} transaksi terpilih akan ditandai lunas tanpa menambah saldo Kas &amp; Bank. Pastikan <strong>Tanggal</strong> diisi ≤ tanggal cut-off Saldo Awal.
                                </div>
                            ) : (
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Terima Ke Akun (Kas &amp; Bank) <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={bulkForm.account_id}
                                        onChange={(e) => setBulkForm({ ...bulkForm, account_id: e.target.value })}
                                        required
                                        className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                    >
                                        <option value="">-- Pilih Akun Kas &amp; Bank --</option>
                                        {kasBankAccounts.map((acc) => (
                                            <option key={acc.id} value={acc.id}>
                                                {acc.name} ({acc.code}) - Saldo: {formatRupiah(acc.balance)}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">Tanggal</label>
                                    <input
                                        type="date"
                                        value={bulkForm.payment_date}
                                        onChange={(e) => setBulkForm({ ...bulkForm, payment_date: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">No. Referensi</label>
                                    <input
                                        type="text"
                                        placeholder="BULK-TRF-001"
                                        value={bulkForm.reference_number}
                                        onChange={(e) => setBulkForm({ ...bulkForm, reference_number: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">Catatan</label>
                                <textarea
                                    rows={2}
                                    value={bulkForm.notes}
                                    onChange={(e) => setBulkForm({ ...bulkForm, notes: e.target.value })}
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <div className="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setIsBulkModalOpen(false)}
                                    className="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmittingBulk}
                                    className={`px-4 py-2 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50 ${bulkForm.is_cutoff ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-amber-600 hover:bg-amber-700'}`}
                                >
                                    {isSubmittingBulk ? 'Memproses...' : (bulkForm.is_cutoff ? 'Konfirmasi Pelunasan Pra-Cut-off' : 'Konfirmasi Pelunasan')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Drawer Detail Piutang */}
            <Drawer
                item={drawerPiutang}
                onClose={() => setDrawerPiutang(null)}
                onExpand={(item) => {
                    setDrawerPiutang(null);
                    openPiutangDetail(item);
                }}
                title="Rincian Transaksi Piutang"
                subtitle={(item) => item.nomor}
                renderFooter={(item) => (
                    <div className="space-y-2">
                        {item.sisa > 0.005 && (
                            <button
                                type="button"
                                onClick={() => {
                                    const target = item;
                                    setDrawerPiutang(null);
                                    openPaymentModal(target);
                                }}
                                className="w-full py-2.5 bg-amber-600 hover:bg-amber-700 active:scale-[0.99] text-white rounded-xl text-xs font-bold transition shadow-xs text-center cursor-pointer"
                            >
                                Terima Pembayaran ({formatRupiah(item.sisa)})
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={() => {
                                const target = item;
                                setDrawerPiutang(null);
                                openPiutangDetail(target);
                            }}
                            className="w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition text-center cursor-pointer"
                        >
                            Buka Halaman Lengkap
                        </button>
                        {item.is_opening && (
                            <button
                                type="button"
                                onClick={() => handleDeleteOpening(item)}
                                className="w-full py-2 border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 rounded-xl text-xs font-semibold transition flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <Trash2 className="w-3.5 h-3.5" />
                                <span>Hapus Tagihan Saldo Awal</span>
                            </button>
                        )}
                    </div>
                )}
            >
                {(item) => (
                    <>
                        <div className="p-4 bg-slate-50 rounded-xl space-y-2">
                            <div className="flex justify-between">
                                <span className="text-slate-500">Debitur / Instansi:</span>
                                <span className="font-bold text-slate-800">{item.debtor}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Pasien:</span>
                                <span className="font-medium text-slate-700">{item.pasien || '-'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Dokter:</span>
                                <span className="font-medium text-slate-700">{item.dokter || '-'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Tanggal Transaksi:</span>
                                <span className="font-medium text-slate-700">{item.tanggal}</span>
                            </div>
                            <div className="flex justify-between items-center">
                                <span className="text-slate-500">Jatuh Tempo:</span>
                                <div className="flex items-center gap-1.5">
                                    <span className="font-bold text-slate-800">{item.jatuhTempo}</span>
                                    {item.status === 'Diretur Penuh' ? (
                                        <span className="text-[10px] bg-purple-100 text-purple-700 font-bold px-1.5 py-0.5 rounded">Diretur Penuh</span>
                                    ) : item.status === 'Diretur Sebagian' ? (
                                        <span className="text-[10px] bg-rose-100 text-rose-700 font-bold px-1.5 py-0.5 rounded">Diretur Sebagian</span>
                                    ) : item.status === 'Lunas' || item.status === 'Lunas (Ada Retur)' || item.sisa <= 0.005 ? (
                                        <span className="text-[10px] bg-emerald-50 text-emerald-700 font-bold px-1.5 py-0.5 rounded">Lunas</span>
                                    ) : item.is_overdue || item.days_overdue > 0 ? (
                                        <span className="text-[10px] bg-red-100 text-red-700 font-black px-1.5 py-0.5 rounded">Lewat {item.days_overdue} hr</span>
                                    ) : (
                                        <span className="text-[10px] bg-amber-50 text-amber-700 font-medium px-1.5 py-0.5 rounded">Sisa {item.days_remaining} hr</span>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Daftar Obat */}
                        <div>
                            <h4 className="font-bold text-slate-900 mb-3">Item Obat Terjual</h4>
                            <div className="border border-slate-200 rounded-xl overflow-hidden">
                                <table className="w-full text-left text-xs divide-y divide-slate-100">
                                    <thead className="bg-slate-50 text-slate-600 text-[11px] font-semibold uppercase">
                                        <tr>
                                            <th className="p-3">Nama Obat</th>
                                            <th className="p-3 text-center">Qty</th>
                                            <th className="p-3 text-right">Harga</th>
                                            <th className="p-3 text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {isLoadingDetail && !(item.items && item.items.length > 0) ? (
                                            <tr>
                                                <td colSpan={4} className="p-4 text-center text-slate-500">
                                                    <span className="inline-flex items-center gap-1.5">
                                                        <Loader2 className="w-3.5 h-3.5 animate-spin text-amber-600" />
                                                        <span>Memuat rincian item obat...</span>
                                                    </span>
                                                </td>
                                            </tr>
                                        ) : (item.items || []).map((it, idx) => (
                                            <tr key={idx}>
                                                <td className="p-3">
                                                    <div className="font-semibold text-slate-800">{it.nama}</div>
                                                    <div className="text-[10px] text-slate-400">{it.sku}</div>
                                                </td>
                                                <td className="p-3 text-center font-medium">
                                                    {it.qty} {it.satuan}
                                                </td>
                                                <td className="p-3 text-right">{formatNumberOnly(it.harga)}</td>
                                                <td className="p-3 text-right font-semibold text-slate-900">{formatNumberOnly(it.jumlah)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* Ringkasan Biaya */}
                        <div className="p-4 bg-slate-50 rounded-xl space-y-1.5 font-medium">
                            <div className="flex justify-between text-base font-bold text-slate-900">
                                <span>Total Tagihan (Bruto):</span>
                                <span>{formatRupiah(item.total)}</span>
                            </div>
                            {item.total_retur > 0 && (
                                <>
                                    <div className="flex justify-between text-rose-600 font-semibold text-xs">
                                        <span className="flex items-center gap-1">
                                            <RotateCcw className="w-3.5 h-3.5" />
                                            <span>Retur Penjualan:</span>
                                        </span>
                                        <span>- {formatRupiah(item.total_retur)}</span>
                                    </div>
                                    <div className="flex justify-between text-slate-700 font-semibold text-xs pt-1 border-t border-dashed border-slate-200">
                                        <span>Tagihan Bersih (Net):</span>
                                        <span className="font-bold">{formatRupiah(item.net_total)}</span>
                                    </div>
                                </>
                            )}
                            <div className="flex justify-between text-emerald-600 font-semibold">
                                <span>Sudah Terbayar:</span>
                                <span>{formatRupiah(item.terbayar)}</span>
                            </div>
                            <div className="flex justify-between text-amber-700 font-bold text-sm pt-1 border-t border-slate-200">
                                <span>Sisa Piutang:</span>
                                <span>{formatRupiah(item.sisa)}</span>
                            </div>
                        </div>

                        {/* Riwayat Retur Penjualan Jika Ada */}
                        {item.returs && item.returs.length > 0 && (
                            <div>
                                <div className="flex items-center justify-between mb-2">
                                    <h4 className="font-bold text-rose-700 text-xs flex items-center gap-1.5">
                                        <RotateCcw className="w-3.5 h-3.5" />
                                        <span>Riwayat Retur Kredit ({item.returs.length})</span>
                                    </h4>
                                    <span className="text-[11px] font-bold text-rose-600">
                                        - {formatRupiah(item.total_retur)}
                                    </span>
                                </div>
                                <div className="space-y-2">
                                    {item.returs.map((r, idx) => (
                                        <div key={r.id || idx} className="p-2.5 bg-rose-50/60 border border-rose-100 rounded-xl text-xs space-y-0.5">
                                            <div className="flex justify-between items-center">
                                                <span className="font-bold text-slate-800">{r.nama}</span>
                                                <span className="font-bold text-rose-700">- {formatRupiah(r.nominal)}</span>
                                            </div>
                                            <div className="flex justify-between text-[11px] text-slate-500">
                                                <span>{r.code} • {r.qty} {r.satuan}</span>
                                                <span>{r.tanggal}</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Riwayat Pembayaran */}
                        <div>
                            <h4 className="font-bold text-slate-900 mb-3">Riwayat Pembayaran</h4>
                            {(item.payments || []).length === 0 ? (
                                <p className="text-slate-400 italic">Belum ada pembayaran yang dicatat untuk transaksi ini.</p>
                            ) : (
                                <div className="space-y-2">
                                    {item.payments.map((p) => (
                                        <div key={p.id} className="p-3 bg-slate-50 border border-slate-100 rounded-xl space-y-1">
                                            <div className="flex justify-between items-center">
                                                <span className="font-bold text-slate-900">{formatRupiah(p.nominal)}</span>
                                                <span className="text-[11px] text-slate-400">{p.tanggal}</span>
                                            </div>
                                            <div className="text-[11px] text-slate-500">
                                                Masuk ke: <strong>{p.akunNama}</strong> ({p.akunKode}) • Ref: {p.noReferensi}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </>
                )}
            </Drawer>

            {/* Modal Tambah Tagihan Saldo Awal Piutang (Per Tagihan) */}
            {isOpeningModalOpen && (() => {
                const parsedInvoiceAmt = openingForm.amount === '' ? 0 : (parseFloat(openingForm.amount) || 0);

                return (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto animate-in fade-in zoom-in-95 duration-200">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
                            <div className="flex items-center gap-2">
                                <Plus className="w-5 h-5 text-indigo-600" />
                                <h3 className="font-bold text-slate-900 text-sm">Tambah Tagihan Saldo Awal Piutang</h3>
                            </div>
                            <button
                                type="button"
                                onClick={() => setIsOpeningModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <form onSubmit={handleOpeningSubmit} className="p-6 space-y-4 text-xs">
                            {/* Box Saldo Awal Neraca Saat Ini */}
                            <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                                <div>
                                    <div className="text-[11px] font-semibold text-slate-500">
                                        Saldo Awal Piutang di Neraca (per {openingForm.invoice_date || '-'})
                                    </div>
                                    <div className="text-lg font-black text-slate-900 mt-0.5">
                                        {isLoadingOpeningPreview
                                            ? 'Menghitung...'
                                            : (openingPreviewBalance !== null ? formatRupiah(openingPreviewBalance) : '-')}
                                    </div>
                                </div>
                                <span className="px-2 py-1 rounded-lg bg-white border border-slate-200 text-[10px] font-bold text-slate-500">
                                    Cut-off Neraca
                                </span>
                            </div>

                            <div className="p-3 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-900 text-[11px] leading-relaxed">
                                <strong>Input Per Tagihan Lama (Belum Tercatat):</strong> Masukkan <strong>nominal tagihan yang belum masuk sistem</strong> (bukan total seluruh saldo awal). Tidak mengubah stok obat, omzet LIPH, maupun Kas &amp; Bank.
                            </div>

                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Debitur / Instansi <span className="text-slate-400 font-normal">(Opsional)</span>
                                </label>
                                <input
                                    type="text"
                                    placeholder="Ketik untuk mencari nama atau kode Debitur..."
                                    value={openingDebtorQuery}
                                    onChange={(e) => setOpeningDebtorQuery(e.target.value)}
                                    className="w-full px-3 py-2 mb-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none"
                                />
                                <select
                                    value={openingForm.debtor_id}
                                    onChange={(e) => setOpeningForm({ ...openingForm, debtor_id: e.target.value })}
                                    className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                >
                                    <option value="">-- Tanpa Debitur / Saldo Awal Umum ({filteredOpeningDebtors.length} Debitur tersedia) --</option>
                                    {filteredOpeningDebtors.map((d) => (
                                        <option key={d.id} value={d.id}>
                                            {d.name} {d.code ? `(${d.code})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Nomor Tagihan / Faktur <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Contoh: SA-PIU-001"
                                        value={openingForm.invoice_number}
                                        onChange={(e) => setOpeningForm({ ...openingForm, invoice_number: e.target.value })}
                                        required
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Nominal Tagihan Ini (Rp) <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="number"
                                        step="any"
                                        placeholder="0 (gunakan minus - untuk pengurang/koreksi)"
                                        value={openingForm.amount}
                                        onChange={(e) => setOpeningForm({ ...openingForm, amount: e.target.value })}
                                        required
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            {openingPreviewBalance !== null && Math.abs(parsedInvoiceAmt) >= 0.01 && (
                                <div className="p-3 rounded-xl bg-slate-50 border border-slate-200 text-[11px] flex items-center justify-between text-slate-700">
                                    <span>Saldo Neraca (per {openingForm.invoice_date || '-'}) akan menjadi:</span>
                                    <strong className="text-indigo-700 text-xs">{formatRupiah(openingPreviewBalance + parsedInvoiceAmt)}</strong>
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Tanggal Tagihan / Cut-off <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        value={openingForm.invoice_date}
                                        onChange={(e) => {
                                            const val = e.target.value;
                                            setOpeningForm({ ...openingForm, invoice_date: val });
                                            if (val) fetchOpeningPreview(val);
                                        }}
                                        required
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Tanggal Jatuh Tempo
                                    </label>
                                    <input
                                        type="date"
                                        value={openingForm.due_date}
                                        onChange={(e) => setOpeningForm({ ...openingForm, due_date: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">Catatan</label>
                                <textarea
                                    rows={2}
                                    placeholder="Contoh: Tagihan piutang lama pra-migrasi belum sempat diinput di penjualan..."
                                    value={openingForm.notes}
                                    onChange={(e) => setOpeningForm({ ...openingForm, notes: e.target.value })}
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                />
                            </div>

                            <div className="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setIsOpeningModalOpen(false)}
                                    className="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmittingOpening}
                                    className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50"
                                >
                                    {isSubmittingOpening ? 'Menyimpan...' : 'Tambah Tagihan Saldo Awal'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                );
            })()}

            {/* Floating Action Bar saat transaksi piutang dicentang */}
            <FloatingActionBar
                selectedCount={selectedPiutangIds.length}
                maxLimit={10}
                totalAmount={totalSelectedPiutangSisa}
                totalFullAmount={totalSelectedPiutangTotal}
                itemLabel="transaksi"
                titleAmount="Total Sisa Piutang Penjualan"
                actionLabel={`Terima Pembayaran (${selectedPiutangIds.length} Transaksi)`}
                actionIcon={CreditCard}
                onAction={openBulkModal}
                secondaryActionLabel={allSelectedPiutangSubmitted ? "Batalkan Pengajuan" : "Ajukan Pelunasan"}
                secondaryActionColor={allSelectedPiutangSubmitted ? "slate" : "amber"}
                onSecondaryAction={() => handleBulkSubmitPiutang(!allSelectedPiutangSubmitted)}
                onClear={() => {
                    setSelectedPiutangIds([]);
                    setSelectedItemsMap({});
                }}
                isSubmitting={isSubmittingBulk}
                themeColor="amber"
            />

            {/* Modal Cetak Bukti Pelunasan Resmi */}
            <BuktiPelunasanModal
                isOpen={isBuktiModalOpen}
                onClose={() => setIsBuktiModalOpen(false)}
                piutang={detailViewPiutang}
            />
            {importSuccessMessage && (
                <div className="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/45 backdrop-blur-sm p-4" style={{ animation: 'finance-popup-fade 220ms ease-out both' }} role="dialog" aria-modal="true" aria-labelledby="import-success-title">
                    <style>{`@keyframes finance-popup-fade { from { opacity: 0; } to { opacity: 1; } } @keyframes finance-popup-enter { from { opacity: 0; transform: translateY(18px) scale(.96); } to { opacity: 1; transform: translateY(0) scale(1); } } @keyframes finance-success-pop { 0% { transform: scale(.55); } 70% { transform: scale(1.12); } 100% { transform: scale(1); } }`}</style>
                    <div className="w-full max-w-sm rounded-2xl bg-white p-7 text-center shadow-2xl ring-1 ring-slate-200" style={{ animation: 'finance-popup-enter 360ms cubic-bezier(.16,1,.3,1) both' }}>
                        <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 ring-8 ring-emerald-50/60" style={{ animation: 'finance-success-pop 420ms cubic-bezier(.16,1,.3,1) 100ms both' }}>
                            <CheckCircle2 className="h-7 w-7 text-emerald-600" />
                        </div>
                        <h2 id="import-success-title" className="text-lg font-bold text-slate-900">Impor Berhasil</h2>
                        <p className="mt-2 text-sm leading-relaxed text-slate-600">{importSuccessMessage}</p>
                        <button type="button" onClick={() => setImportSuccessMessage('')} className="mt-6 w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                            Selesai
                        </button>
                    </div>
                </div>
            )}
        </FinanceLayout>
    );
}
