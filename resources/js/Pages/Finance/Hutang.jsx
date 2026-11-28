import React, { useState, useMemo, useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import FinanceLayout from './Layouts/FinanceLayout';
import PbfCombobox from './Components/PbfCombobox';
import DateRangeFilter from './Components/DateRangeFilter';
import Drawer from './Components/Drawer';
import FloatingActionBar from './Components/FloatingActionBar';
import { formatRupiah, formatNumberOnly } from './Components/Utils';
import {
    CreditCard, Search, Filter, Check, X, Building2,
    Calendar, CheckCircle2, AlertCircle, Eye, ChevronLeft, ChevronRight,
    ArrowLeft, Printer, Share2, MoreVertical, ChevronDown, History, Landmark, Maximize2, Download, FileSpreadsheet,
    AlertTriangle, Clock, ArrowUp, ArrowDown, ArrowUpDown, Upload, RotateCcw, Loader2, Plus, Trash2, Pencil
} from 'lucide-react';

// Helper mengecek apakah faktur sudah dianggap lunas atau diretur penuh
const isItemSettled = (item) => {
    if (!item) return false;
    return (
        item.status === 'Lunas' ||
        item.status === 'Diretur Penuh' ||
        item.status === 'Lunas (Ada Retur)' ||
        item.status === 'Koreksi Saldo Awal' ||
        Boolean(item.is_negative_adjustment) ||
        (Number(item.sisa) || 0) <= 0.005
    );
};

// Helper fungsi menghitung status jatuh tempo secara presisi
const computeDueInfo = (item) => {
    if (!item) return { isLunas: false, isOverdue: false, daysOverdue: 0, daysRemaining: 0, hasDate: false };
    const isLunas = isItemSettled(item);
    const hasDate = !!item.raw_jatuh_tempo || (!!item.jatuhTempo && item.jatuhTempo !== '-');

    if (isLunas) {
        return { isLunas: true, isOverdue: false, daysOverdue: 0, daysRemaining: 0, hasDate };
    }

    if (!hasDate) {
        return { isLunas: false, isOverdue: false, daysOverdue: 0, daysRemaining: 0, hasDate: false };
    }

    if (typeof item.is_overdue === 'boolean' && item.is_overdue !== undefined) {
        return {
            isLunas: false,
            isOverdue: item.is_overdue,
            daysOverdue: Number(item.days_overdue) || 0,
            daysRemaining: Number(item.days_remaining) || 0,
            hasDate: true
        };
    }

    try {
        let dueDate;
        if (item.raw_jatuh_tempo) {
            dueDate = new Date(item.raw_jatuh_tempo);
        } else if (item.jatuhTempo && item.jatuhTempo.includes('/')) {
            const [d, m, y] = item.jatuhTempo.split('/');
            dueDate = new Date(y, m - 1, d);
        }
        if (dueDate && !isNaN(dueDate.getTime())) {
            dueDate.setHours(23, 59, 59, 999);
            const now = new Date();
            const diffMs = dueDate.getTime() - now.getTime();
            const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
            return {
                isLunas: false,
                isOverdue: diffDays < 0,
                daysOverdue: diffDays < 0 ? Math.abs(diffDays) : 0,
                daysRemaining: diffDays >= 0 ? diffDays : 0,
                hasDate: true
            };
        }
    } catch (e) { }

    return { isLunas: false, isOverdue: false, daysOverdue: 0, daysRemaining: 0, hasDate: false };
};

export default function Hutang({
    hutangDagang = [],
    pagination = null,
    dueStats: serverDueStats = null,
    filters: serverFilters = {},
    creditors = [],
    kasBankAccounts = [],
    stats = {},
}) {
    // State Filter & Pagination (disinkronkan dengan server)
    const [searchTerm, setSearchTerm] = useState(serverFilters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(serverFilters.status ?? 'ALL');
    const [filterPbf, setFilterPbf] = useState(serverFilters.pbf ?? '');
    const [startDate, setStartDate] = useState(serverFilters.start_date ?? '');
    const [endDate, setEndDate] = useState(serverFilters.end_date ?? '');
    const [datePreset, setDatePreset] = useState(serverFilters.date_preset ?? 'all');
    const [dateField, setDateField] = useState(serverFilters.date_field ?? 'due');
    const [sortOrder, setSortOrder] = useState(serverFilters.sort_order ?? 'desc');
    const [dueFilter, setDueFilter] = useState(serverFilters.due_mode ?? 'all');
    const [isDueDropdownOpen, setIsDueDropdownOpen] = useState(false);
    const [isLoadingList, setIsLoadingList] = useState(false);

    const currentPage = pagination?.current_page ?? 1;
    const totalPages = pagination?.last_page ?? 1;
    const totalFilteredItems = pagination?.total ?? hutangDagang.length;
    const pageFrom = pagination?.from ?? (hutangDagang.length > 0 ? 1 : 0);
    const pageTo = pagination?.to ?? hutangDagang.length;

    const navigateHutang = (overrides = {}) => {
        const next = {
            search: overrides.search !== undefined ? overrides.search : searchTerm,
            pbf: overrides.pbf !== undefined ? overrides.pbf : filterPbf,
            status: overrides.status !== undefined ? overrides.status : statusFilter,
            due_mode: overrides.due_mode !== undefined ? overrides.due_mode : dueFilter,
            date_field: overrides.date_field !== undefined ? overrides.date_field : dateField,
            date_preset: overrides.date_preset !== undefined ? overrides.date_preset : datePreset,
            start_date: overrides.start_date !== undefined ? overrides.start_date : startDate,
            end_date: overrides.end_date !== undefined ? overrides.end_date : endDate,
            sort_order: overrides.sort_order !== undefined ? overrides.sort_order : sortOrder,
            page: overrides.page !== undefined ? overrides.page : currentPage,
        };
        const params = {};
        if (next.search && next.search.trim() !== '') params.search = next.search.trim();
        if (next.pbf) params.pbf = next.pbf;
        if (next.status && next.status !== 'ALL') params.status = next.status;
        if (next.due_mode && next.due_mode !== 'all') params.due_mode = next.due_mode;
        if (next.date_field && next.date_field !== 'due') params.date_field = next.date_field;
        if (next.date_preset && next.date_preset !== 'all') params.date_preset = next.date_preset;
        if (next.start_date) params.start_date = next.start_date;
        if (next.end_date) params.end_date = next.end_date;
        if (next.sort_order && next.sort_order !== 'desc') params.sort_order = next.sort_order;
        if (next.page && next.page > 1) params.page = next.page;

        router.get('/finance/hutang', params, {
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
            navigateHutang({ search: searchTerm, page: 1 });
        }, 300);
        return () => clearTimeout(timer);
    }, [searchTerm]);

    const handleDatePreset = (preset) => {
        setDatePreset(preset);
        if (preset === 'all') {
            setStartDate('');
            setEndDate('');
            navigateHutang({ date_preset: 'all', start_date: '', end_date: '', page: 1 });
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
        navigateHutang({ date_preset: preset, start_date: nextStart, end_date: nextEnd, page: 1 });
    };

    // Selection lintas halaman untuk Pelunasan Massal (Maks 10)
    const [selectedHutangIds, setSelectedHutangIds] = useState([]);
    const [selectedItemsMap, setSelectedItemsMap] = useState({});
    const [selectionWarning, setSelectionWarning] = useState('');

    // State Halaman Detil Penuh & Quick Preview Drawer + Lazy Cache
    const [detailViewHutang, setDetailViewHutang] = useState(null);
    const [drawerHutang, setDrawerHutang] = useState(null);
    const [isShippingOpen, setIsShippingOpen] = useState(true);
    const [isLoadingDetail, setIsLoadingDetail] = useState(false);
    const detailCacheRef = useRef({});

    useEffect(() => {
        if (!hutangDagang.length) return;
        setSelectedItemsMap((prev) => {
            const next = { ...prev };
            let changed = false;
            hutangDagang.forEach((item) => {
                if (next[item.id]) {
                    next[item.id] = item;
                    changed = true;
                }
            });
            return changed ? next : prev;
        });
    }, [hutangDagang]);

    const loadHutangDetail = async (item) => {
        if (!item?.id) return;
        if (detailCacheRef.current[item.id]) {
            const cached = detailCacheRef.current[item.id];
            setDrawerHutang((curr) => (curr?.id === item.id ? cached : curr));
            setDetailViewHutang((curr) => (curr?.id === item.id ? cached : curr));
            return;
        }
        setIsLoadingDetail(true);
        try {
            const res = await axios.get(`/finance/hutang/${item.id}/detail`);
            const full = res?.data?.data;
            if (full) {
                detailCacheRef.current[item.id] = full;
                setDrawerHutang((curr) => (curr?.id === item.id ? full : curr));
                setDetailViewHutang((curr) => (curr?.id === item.id ? full : curr));
            }
        } catch (err) {
            console.error('Gagal memuat detail hutang:', err);
        } finally {
            setIsLoadingDetail(false);
        }
    };

    const openHutangDrawer = (item) => {
        const initial = detailCacheRef.current[item.id] || item;
        setDrawerHutang(initial);
        loadHutangDetail(item);
    };

    const openHutangDetail = (item) => {
        const initial = detailCacheRef.current[item.id] || item;
        setDetailViewHutang(initial);
        loadHutangDetail(item);
    };

    // State Modal Revisi Akun Kas & Bank Pembayaran
    const [isReviseAccountModalOpen, setIsReviseAccountModalOpen] = useState(false);
    const [isSubmittingReviseAccount, setIsSubmittingReviseAccount] = useState(false);
    const [reviseAccountForm, setReviseAccountForm] = useState({
        payment_id: '',
        parent_id: '',
        account_id: '',
        old_account_name: '',
        tanggal: '',
        nominal: 0,
        reference_number: '',
        revision_reason: '',
    });

    const openReviseAccountModal = (pay, parentItem) => {
        if (!pay?.id || !pay?.akunId) return;
        setReviseAccountForm({
            payment_id: pay.id,
            parent_id: parentItem?.id || '',
            account_id: String(pay.akunId),
            old_account_name: `${pay.akunNama} (${pay.akunKode})`,
            tanggal: pay.tanggal || '-',
            nominal: Number(pay.nominal || 0),
            reference_number: pay.noReferensi && pay.noReferensi !== '-' ? pay.noReferensi : '',
            revision_reason: '',
        });
        setIsReviseAccountModalOpen(true);
    };

    const handleReviseAccountSubmit = (e) => {
        e.preventDefault();
        if (!reviseAccountForm.account_id) {
            alert('Pilih akun Kas & Bank terlebih dahulu.');
            return;
        }
        setIsSubmittingReviseAccount(true);
        router.put(`/finance/payments/${reviseAccountForm.payment_id}/account`, {
            account_id: reviseAccountForm.account_id,
            reference_number: reviseAccountForm.reference_number,
            revision_reason: reviseAccountForm.revision_reason,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsReviseAccountModalOpen(false);
                setIsSubmittingReviseAccount(false);
                const targetParentId = reviseAccountForm.parent_id;
                if (targetParentId) {
                    delete detailCacheRef.current[targetParentId];
                    if (drawerHutang?.id === targetParentId || detailViewHutang?.id === targetParentId) {
                        loadHutangDetail({ id: targetParentId });
                    }
                } else {
                    detailCacheRef.current = {};
                }
            },
            onError: (errs) => {
                setIsSubmittingReviseAccount(false);
                alert(Object.values(errs).flat()[0] || 'Gagal merevisi akun Kas & Bank.');
            },
        });
    };

    // State Modal Bayar Satuan
    const [isPaymentModalOpen, setIsPaymentModalOpen] = useState(false);
    const [paymentForm, setPaymentForm] = useState({
        receiving_detail_id: '',
        account_id: '',
        payment_date: new Date().toISOString().split('T')[0],
        amount: 0,
        sisa: 0,
        reference_number: '',
        notes: '',
        is_cutoff: false,
    });
    const [isSubmittingPayment, setIsSubmittingPayment] = useState(false);

    // State Modal Pelunasan Massal
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

    // State Modal Tambah Tagihan Saldo Awal Hutang (Per Faktur)
    const getDefaultCutoffDate = () => {
        const now = new Date();
        const prevEnd = new Date(now.getFullYear(), now.getMonth(), 0);
        return `${prevEnd.getFullYear()}-${String(prevEnd.getMonth() + 1).padStart(2, '0')}-${String(prevEnd.getDate()).padStart(2, '0')}`;
    };
    const [isOpeningModalOpen, setIsOpeningModalOpen] = useState(false);
    const [openingPreviewBalance, setOpeningPreviewBalance] = useState(null);
    const [isLoadingOpeningPreview, setIsLoadingOpeningPreview] = useState(false);
    const [openingCreditorQuery, setOpeningCreditorQuery] = useState('');
    const [openingForm, setOpeningForm] = useState({
        creditor_code: '',
        invoice_number: '',
        invoice_date: getDefaultCutoffDate(),
        due_date: '',
        amount: '',
        notes: '',
    });
    const [isSubmittingOpening, setIsSubmittingOpening] = useState(false);
    const filteredOpeningCreditors = useMemo(() => {
        const q = openingCreditorQuery.trim().toLowerCase();
        if (!q) return creditors;
        return creditors.filter(
            (c) => (c.name && c.name.toLowerCase().includes(q)) || (c.code && c.code.toLowerCase().includes(q))
        );
    }, [creditors, openingCreditorQuery]);

    const fetchOpeningPreview = async (dateStr) => {
        setIsLoadingOpeningPreview(true);
        try {
            const res = await axios.get('/finance/opening-balance-preview', {
                params: { type: 'HUTANG', date: dateStr || getDefaultCutoffDate() },
            });
            setOpeningPreviewBalance(Number(res?.data?.balance ?? 0));
        } catch (err) {
            console.error('Gagal memuat saldo awal hutang:', err);
            setOpeningPreviewBalance(null);
        } finally {
            setIsLoadingOpeningPreview(false);
        }
    };

    const openOpeningModal = () => {
        const defaultDate = getDefaultCutoffDate();
        setOpeningCreditorQuery('');
        setOpeningForm({
            creditor_code: '',
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
        router.post('/finance/hutang/opening', {
            ...openingForm,
            creditor_code: openingForm.creditor_code || null,
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
                alert(Object.values(errs).flat()[0] || 'Gagal menambah tagihan saldo awal hutang.');
            },
        });
    };

    const handleDeleteOpening = (item) => {
        if (!item?.is_opening || !item?.opening_id) return;
        if (!window.confirm(`Hapus tagihan saldo awal "${item.nomor}" (${formatRupiah(item.total)})?`)) return;
        router.delete(`/finance/hutang/opening/${item.opening_id}`, {
            preserveScroll: true,
            onSuccess: () => {
                delete detailCacheRef.current[item.id];
                if (drawerHutang?.id === item.id) setDrawerHutang(null);
                if (detailViewHutang?.id === item.id) setDetailViewHutang(null);
            },
            onError: (errs) => {
                alert(Object.values(errs).flat()[0] || 'Gagal menghapus tagihan saldo awal.');
            },
        });
    };

    // Statistik Jatuh Tempo (dari server jika tersedia)
    const dueStats = useMemo(() => {
        if (serverDueStats && typeof serverDueStats.overdue === 'number') {
            return serverDueStats;
        }
        let overdue = 0;
        let dueSoon = 0;
        hutangDagang.forEach((item) => {
            const info = computeDueInfo(item);
            if (!info.isLunas) {
                if (info.isOverdue) overdue++;
                else if (info.hasDate && info.daysRemaining <= 7) dueSoon++;
            }
        });
        return { overdue, dueSoon };
    }, [serverDueStats, hutangDagang]);

    const paginatedHutang = hutangDagang;

    // Items terpilih untuk bulk payment (lintas halaman via selectedItemsMap)
    const selectedHutangItems = useMemo(() => {
        const byId = new Map(hutangDagang.map((item) => [item.id, item]));
        return selectedHutangIds
            .map((id) => byId.get(id) || selectedItemsMap[id])
            .filter(Boolean);
    }, [hutangDagang, selectedHutangIds, selectedItemsMap]);

    const totalSelectedHutangSisa = useMemo(() => {
        const sum = selectedHutangItems.reduce((acc, curr) => acc + (Number(curr.sisa) || 0), 0);
        return Math.round(sum * 100) / 100;
    }, [selectedHutangItems]);

    const totalSelectedHutangTotal = useMemo(() => {
        const sum = selectedHutangItems.reduce((acc, curr) => acc + (Number(curr.total) || 0), 0);
        return Math.round(sum * 100) / 100;
    }, [selectedHutangItems]);

    // Total kuantitas barang pada halaman detil penuh
    const totalDetailQty = useMemo(() => {
        if (!detailViewHutang || !detailViewHutang.items) return 0;
        return detailViewHutang.items.reduce((acc, curr) => acc + (Number(curr.qty) || 0), 0);
    }, [detailViewHutang]);

    // Helper status badge render
    const renderStatusBadge = (item) => {
        if (item.status === 'Koreksi Saldo Awal' || item.is_negative_adjustment) {
            return (
                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200" title="Koreksi pengurang Saldo Awal Hutang Dagang">
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
        if (item.status === 'Lunas' || isItemSettled(item)) {
            return (
                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700">
                    <Check className="w-3 h-3 text-emerald-600" />
                    <span>Lunas</span>
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
                Belum Dibayar
            </span>
        );
    };

    // Handlers Checkbox
    const handleToggleHutang = (item) => {
        if (isItemSettled(item)) return;
        if (selectedHutangIds.includes(item.id)) {
            setSelectedHutangIds((prev) => prev.filter((id) => id !== item.id));
            setSelectedItemsMap((prev) => {
                const next = { ...prev };
                delete next[item.id];
                return next;
            });
        } else {
            if (selectedHutangIds.length >= 10) {
                setSelectionWarning('Maksimal 10 faktur yang dapat dipilih sekaligus untuk pelunasan massal.');
                setTimeout(() => setSelectionWarning(''), 4000);
                return;
            }
            setSelectedHutangIds((prev) => [...prev, item.id]);
            setSelectedItemsMap((prev) => ({ ...prev, [item.id]: item }));
        }
    };

    const handleSelectAllHutang = (e) => {
        if (e.target.checked) {
            const eligible = paginatedHutang.filter((item) => !isItemSettled(item));
            const remainingSlots = 10 - selectedHutangIds.length;
            if (remainingSlots <= 0) {
                setSelectionWarning('Maksimal 10 faktur yang dapat dipilih sekaligus.');
                setTimeout(() => setSelectionWarning(''), 4000);
                return;
            }
            const addedItems = eligible
                .filter((item) => !selectedHutangIds.includes(item.id))
                .slice(0, remainingSlots);
            const toAdd = addedItems.map((item) => item.id);

            if (toAdd.length < eligible.filter((item) => !selectedHutangIds.includes(item.id)).length) {
                setSelectionWarning('Hanya 10 faktur pertama yang dipilih (batas maksimal 10 faktur).');
                setTimeout(() => setSelectionWarning(''), 4000);
            }
            setSelectedHutangIds((prev) => [...prev, ...toAdd]);
            setSelectedItemsMap((prev) => {
                const next = { ...prev };
                addedItems.forEach((it) => { next[it.id] = it; });
                return next;
            });
        } else {
            const currentPageIds = paginatedHutang.map((item) => item.id);
            setSelectedHutangIds((prev) => prev.filter((id) => !currentPageIds.includes(id)));
            setSelectedItemsMap((prev) => {
                const next = { ...prev };
                currentPageIds.forEach((id) => { delete next[id]; });
                return next;
            });
        }
    };

    // Buka Modal Bayar Satuan
    const openPaymentModal = (item) => {
        const defaultAccount = kasBankAccounts[0]?.id || '';
        setPaymentForm({
            receiving_detail_id: item.id,
            account_id: defaultAccount,
            payment_date: new Date().toISOString().split('T')[0],
            amount: item.sisa,
            sisa: item.sisa,
            reference_number: '',
            notes: `Pelunasan tagihan faktur ${item.nomor} kepada ${item.vendor}`,
            is_cutoff: false,
        });
        setIsPaymentModalOpen(true);
    };

    // Submit Pembayaran Satuan
    const handlePaymentSubmit = (e) => {
        e.preventDefault();
        if (!paymentForm.is_cutoff && !paymentForm.account_id) {
            alert('Pilih akun kas & bank terlebih dahulu.');
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
        router.post('/finance/payments', {
            ...paymentForm,
            account_id: paymentForm.is_cutoff ? null : paymentForm.account_id,
            is_cutoff: Boolean(paymentForm.is_cutoff),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsPaymentModalOpen(false);
                setIsSubmittingPayment(false);
                delete detailCacheRef.current[paymentForm.receiving_detail_id];
                if (drawerHutang && drawerHutang.id === paymentForm.receiving_detail_id) {
                    setDrawerHutang(null);
                }
                if (detailViewHutang && detailViewHutang.id === paymentForm.receiving_detail_id) {
                    setDetailViewHutang(null);
                }
            },
            onError: (errs) => {
                setIsSubmittingPayment(false);
                alert(Object.values(errs)[0] || 'Gagal memproses pembayaran.');
            }
        });
    };

    // Buka Modal Pelunasan Massal
    const openBulkModal = () => {
        if (selectedHutangIds.length === 0) return;
        const defaultAccount = kasBankAccounts[0]?.id || '';
        setBulkForm({
            account_id: defaultAccount,
            payment_date: new Date().toISOString().split('T')[0],
            reference_number: '',
            notes: `Pelunasan massal ${selectedHutangIds.length} faktur hutang pembelian`,
            is_cutoff: false,
        });
        setIsBulkModalOpen(true);
    };

    // Submit Pelunasan Massal
    const handleBulkSubmit = (e) => {
        e.preventDefault();
        if (!bulkForm.is_cutoff && !bulkForm.account_id) {
            alert('Pilih akun kas & bank terlebih dahulu.');
            return;
        }
        setIsSubmittingBulk(true);
        router.post('/finance/bulk-payments', {
            receiving_detail_ids: selectedHutangIds,
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
                setSelectedHutangIds([]);
                setSelectedItemsMap({});
            },
            onError: (errs) => {
                setIsSubmittingBulk(false);
                alert(Object.values(errs)[0] || 'Gagal memproses pelunasan massal.');
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
        router.post('/finance/hutang/import-payments', formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                setIsImportModalOpen(false);
                setImportFile(null);
                setImportIsCutoff(false);
                setIsImporting(false);
                detailCacheRef.current = {};
                setSelectedHutangIds([]);
                setSelectedItemsMap({});
                setImportSuccessMessage(page?.props?.flash?.success || 'Impor pembayaran Hutang Dagang berhasil.');
            },
            onError: (errs) => {
                setIsImporting(false);
                alert(Object.values(errs).flat().join('\n') || 'Gagal mengimpor pembayaran.');
            },
        });
    };

    // Handle Pengajuan Pelunasan (Submit / Batalkan)
    const handleBulkSubmitHutang = (isSubmit) => {
        if (selectedHutangIds.length === 0) return;
        setIsSubmittingBulk(true);
        router.post('/finance/hutang/bulk-submit', {
            ids: selectedHutangIds,
            is_submitted: isSubmit
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsSubmittingBulk(false);
                detailCacheRef.current = {};
                setSelectedHutangIds([]);
                setSelectedItemsMap({});
            },
            onError: () => {
                setIsSubmittingBulk(false);
            }
        });
    };

    const allSelectedHutangSubmitted = selectedHutangIds.length > 0 && selectedHutangItems.length === selectedHutangIds.length && selectedHutangItems.every(item => Boolean(item?.is_submitted));

    return (
        <FinanceLayout
            title={detailViewHutang ? `Detil Tagihan Hutang Dagang ${detailViewHutang.nomor}` : "Hutang Dagang"}
            subtitle={detailViewHutang ? undefined : "Tagihan faktur pembelian kredit kepada PBF / Distributor obat"}
            stats={detailViewHutang ? undefined : stats}
        >
            {detailViewHutang ? (
                /* SUB-VIEW A: DETIL TAGIHAN HUTANG DAGANG (PERSIS SCREENSHOT) */
                <div className="space-y-6 max-w-[1300px] mx-auto animate-in fade-in duration-200">
                    {/* Link Kembali ke Daftar */}
                    <button
                        type="button"
                        onClick={() => setDetailViewHutang(null)}
                        className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-800 font-medium transition cursor-pointer"
                    >
                        <ArrowLeft className="w-3.5 h-3.5" />
                        <span>Kembali ke Daftar</span>
                    </button>

                    {/* Judul & Action Kanan */}
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                            Detil Tagihan Hutang Dagang {detailViewHutang.nomor}
                        </h1>

                        <div className="flex items-center gap-2 flex-wrap">
                            {detailViewHutang.is_opening && (
                                <button
                                    type="button"
                                    onClick={() => handleDeleteOpening(detailViewHutang)}
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition cursor-pointer"
                                >
                                    <Trash2 className="w-3.5 h-3.5" />
                                    <span>Hapus Saldo Awal</span>
                                </button>
                            )}
                            {detailViewHutang.sisa > 0.005 && (
                                <button
                                    type="button"
                                    onClick={() => openPaymentModal(detailViewHutang)}
                                    className="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-xs cursor-pointer"
                                >
                                    <CreditCard className="w-3.5 h-3.5" />
                                    <span>Bayar Tagihan</span>
                                </button>
                            )}

                            <div className="relative">
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (navigator.clipboard) {
                                            navigator.clipboard.writeText(window.location.href);
                                            alert('Tautan halaman berhasil disalin!');
                                        }
                                    }}
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition cursor-pointer"
                                >
                                    <Share2 className="w-3.5 h-3.5 text-slate-500" />
                                    <span>Bagikan</span>
                                    <ChevronDown className="w-3 h-3 text-slate-400" />
                                </button>
                            </div>

                            <button
                                type="button"
                                onClick={() => window.print()}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition cursor-pointer"
                            >
                                <Printer className="w-3.5 h-3.5 text-slate-500" />
                                <span>Print</span>
                                <ChevronDown className="w-3 h-3 text-slate-400" />
                            </button>

                            <button
                                type="button"
                                className="p-1.5 text-slate-400 hover:text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition cursor-pointer"
                            >
                                <MoreVertical className="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    {/* KARTU UTAMA DETIL FAKTUR */}
                    <div className="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs space-y-6">
                        {/* Status Badge */}
                        <div className="flex items-center justify-between">
                            <div className="flex flex-col items-start gap-1">
                                <span
                                    className={`inline-block px-3 py-1 text-xs font-semibold rounded-full ${detailViewHutang.status === 'Lunas'
                                        ? 'bg-emerald-50 text-emerald-600 border border-emerald-100'
                                        : detailViewHutang.status === 'Dibayar Sebagian'
                                            ? 'bg-amber-50 text-amber-600 border border-amber-100'
                                            : 'bg-red-50 text-red-600 border border-red-100'
                                        }`}
                                >
                                    {detailViewHutang.status}
                                </span>
                                {detailViewHutang.status === 'Lunas' && detailViewHutang.tanggalBayar && <span className="text-[11px] font-medium text-emerald-600">Lunas pada {detailViewHutang.tanggalBayar}</span>}
                            </div>

                            <span className="text-xs text-slate-400">
                                Jenis Pembayaran: <strong className="text-slate-700 font-semibold">KREDIT (Tempo)</strong>
                            </span>
                        </div>

                        {/* Metadata Faktur (2 Kolom) */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 text-xs">
                            <div className="space-y-4">
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1">Vendor</span>
                                    <span className="text-blue-600 font-semibold text-sm hover:underline cursor-pointer">
                                        {detailViewHutang.vendor}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1">Tgl. Transaksi</span>
                                    <span className="font-semibold text-slate-800 text-sm">{detailViewHutang.tanggal}</span>
                                </div>
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1">Gudang</span>
                                    <span className="text-blue-600 font-medium hover:underline cursor-pointer">
                                        {detailViewHutang.gudang}
                                    </span>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1">Nomor</span>
                                    <span className="font-bold text-slate-900 text-sm">{detailViewHutang.nomor}</span>
                                </div>
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1 font-semibold uppercase">Tgl. Jatuh Tempo</span>
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="font-extrabold text-slate-900 text-base">{detailViewHutang.jatuhTempo || '-'}</span>
                                        {(() => {
                                            const dueInfo = computeDueInfo(detailViewHutang);
                                            if (dueInfo.isLunas) {
                                                return (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                        <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                                                        <span>Lunas</span>
                                                    </span>
                                                );
                                            }
                                            if (!dueInfo.hasDate) return null;
                                            if (dueInfo.isOverdue || dueInfo.daysOverdue > 0) {
                                                return (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-black bg-red-100 text-red-800 border border-red-300 animate-pulse">
                                                        <AlertTriangle className="w-3.5 h-3.5 text-red-600" />
                                                        <span>Lewat {dueInfo.daysOverdue} Hari</span>
                                                    </span>
                                                );
                                            }
                                            if (dueInfo.daysRemaining <= 7) {
                                                return (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                                        <Clock className="w-3.5 h-3.5 text-amber-600" />
                                                        <span>Sisa {dueInfo.daysRemaining} Hari</span>
                                                    </span>
                                                );
                                            }
                                            return (
                                                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                                    <Calendar className="w-3.5 h-3.5 text-slate-400" />
                                                    <span>Sisa {dueInfo.daysRemaining} Hari</span>
                                                </span>
                                            );
                                        })()}
                                    </div>
                                </div>
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1">Referensi</span>
                                    <span className="font-medium text-slate-800">{detailViewHutang.referensi}</span>
                                </div>
                            </div>
                        </div>

                        {/* Informasi Pengiriman */}
                        <div className="pt-2 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setIsShippingOpen(!isShippingOpen)}
                                className="flex items-center gap-1.5 text-xs font-semibold text-slate-700 hover:text-slate-900 cursor-pointer"
                            >
                                {isShippingOpen ? <ChevronDown className="w-3.5 h-3.5" /> : <ChevronRight className="w-3.5 h-3.5" />}
                                <span>Informasi pengiriman</span>
                            </button>
                            {isShippingOpen && (
                                <div className="pl-5 pt-3 text-xs">
                                    <span className="text-slate-400 block text-[11px]">Tanggal Pengiriman</span>
                                    <span className="font-medium text-slate-800">{detailViewHutang.tglKirim || detailViewHutang.tanggal}</span>
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
                                        <th className="py-2.5 font-normal text-center">Diskon / Ex. Diskon</th>
                                        <th className="py-2.5 font-normal text-right">Harga</th>
                                        <th className="py-2.5 font-normal text-center">Pajak</th>
                                        <th className="py-2.5 font-normal text-right">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 text-slate-700">
                                    {isLoadingDetail && !(detailViewHutang.items && detailViewHutang.items.length > 0) ? (
                                        <tr>
                                            <td colSpan={8} className="py-8 text-center text-slate-500">
                                                <span className="inline-flex items-center gap-2">
                                                    <Loader2 className="w-4 h-4 animate-spin text-blue-600" />
                                                    <span>Memuat rincian item, retur, dan pembayaran...</span>
                                                </span>
                                            </td>
                                        </tr>
                                    ) : detailViewHutang.items && detailViewHutang.items.length > 0 ? (
                                        detailViewHutang.items.map((it, idx) => (
                                            <tr key={idx}>
                                                <td className="py-3">
                                                    <span className="text-blue-600 font-medium">
                                                        {it.sku} - {it.nama}
                                                    </span>
                                                </td>
                                                <td className="py-3 text-slate-400">-</td>
                                                <td className="py-3 text-center font-medium">{it.qty}</td>
                                                <td className="py-3">{it.satuan}</td>
                                                <td className="py-3 text-center">
                                                    <div>{it.diskon || '0%'}</div>
                                                    <div className="text-[10px] text-slate-400">Ex. {it.extraDiskon || '0%'}</div>
                                                </td>
                                                <td className="py-3 text-right">{formatNumberOnly(it.harga)}</td>
                                                <td className="py-3 text-center text-slate-500">{it.pajak || 'PPN11'}</td>
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
                                    <span className="font-medium text-slate-800">{formatNumberOnly(detailViewHutang.subtotal)}</span>
                                </div>
                                <div className="flex justify-between text-slate-600">
                                    <span>PPN11</span>
                                    <span className="font-medium text-slate-800">{formatNumberOnly(detailViewHutang.ppn)}</span>
                                </div>
                                <div className="flex justify-between text-slate-800 font-bold pt-1">
                                    <span>Total Tagihan (Bruto)</span>
                                    <span>{formatNumberOnly(detailViewHutang.total)}</span>
                                </div>

                                {detailViewHutang.total_retur > 0 && (
                                    <>
                                        <div className="flex justify-between text-rose-600 font-semibold pt-1">
                                            <span className="flex items-center gap-1">
                                                <RotateCcw className="w-3 h-3" />
                                                <span>Retur Pembelian</span>
                                            </span>
                                            <span>- {formatNumberOnly(detailViewHutang.total_retur)}</span>
                                        </div>
                                        <div className="flex justify-between text-slate-700 font-semibold pt-1 border-t border-dashed border-slate-200">
                                            <span>Tagihan Bersih (Net)</span>
                                            <span className="font-bold">{formatNumberOnly(detailViewHutang.net_total)}</span>
                                        </div>
                                    </>
                                )}

                                {detailViewHutang.terbayar > 0 && (
                                    <div className="flex justify-between text-emerald-600 font-semibold pt-1">
                                        <span>Sudah Dibayar</span>
                                        <span>- {formatNumberOnly(detailViewHutang.terbayar)}</span>
                                    </div>
                                )}

                                <div className="bg-slate-50 border border-slate-200/80 p-4 rounded-xl flex justify-between items-center text-slate-900 font-bold mt-3">
                                    <span className="text-xs text-slate-700">Sisa Tagihan</span>
                                    <span className="text-lg font-black text-slate-900">{formatNumberOnly(detailViewHutang.sisa)}</span>
                                </div>

                                {detailViewHutang.sisa > 0.005 && (
                                    <button
                                        type="button"
                                        onClick={() => openPaymentModal(detailViewHutang)}
                                        className="w-full mt-2 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold transition flex items-center justify-center gap-2 shadow-xs cursor-pointer"
                                    >
                                        <CreditCard className="w-4 h-4" />
                                        <span>Bayar Tagihan Sekarang</span>
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* KARTU RIWAYAT RETUR PEMBELIAN JIKA ADA */}
                    {detailViewHutang.returs && detailViewHutang.returs.length > 0 && (
                        <div className="bg-white rounded-2xl border border-rose-200 p-6 shadow-xs space-y-4">
                            <div className="flex items-center justify-between border-b border-rose-100 pb-4">
                                <div className="flex items-center gap-2.5">
                                    <div className="p-2 rounded-xl bg-rose-50 text-rose-600">
                                        <RotateCcw className="w-5 h-5" />
                                    </div>
                                    <div>
                                        <h3 className="font-bold text-slate-900 text-sm">
                                            Riwayat Retur Pembelian
                                        </h3>
                                        <p className="text-[11px] text-slate-400">
                                            Item obat yang diretur ke PBF/Vendor dan memotong tagihan faktur ini jika statusnya "Sudah Potong Tagihan".
                                        </p>
                                    </div>
                                </div>

                                <span className="text-xs font-semibold text-slate-600">
                                    Total Potongan Retur: <strong className="text-rose-600">- {formatRupiah(detailViewHutang.total_retur)}</strong>
                                </span>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="text-slate-400 bg-rose-50/40 border-b border-rose-100">
                                        <tr>
                                            <th className="py-2.5 px-3 font-medium">Tanggal</th>
                                            <th className="py-2.5 px-3 font-medium">No. Retur</th>
                                            <th className="py-2.5 px-3 font-medium">Item Obat</th>
                                            <th className="py-2.5 px-3 font-medium">Batch / ED</th>
                                            <th className="py-2.5 px-3 font-medium text-center">Qty Diretur</th>
                                            <th className="py-2.5 px-3 font-medium text-right">Potongan Hutang</th>
                                            <th className="py-2.5 px-3 font-medium text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-rose-50 text-slate-700">
                                        {detailViewHutang.returs.map((ret, idx) => (
                                            <tr key={ret.id || idx} className="hover:bg-rose-50/30">
                                                <td className="py-3 px-3 font-medium text-slate-900">{ret.tanggal}</td>
                                                <td className="py-3 px-3 font-mono text-[11px] font-semibold text-rose-700">{ret.code}</td>
                                                <td className="py-3 px-3">
                                                    <div className="font-semibold text-slate-800">{ret.nama}</div>
                                                    {ret.reason && ret.reason !== '-' && (
                                                        <div className="text-[10px] text-slate-400">Alasan: {ret.reason}</div>
                                                    )}
                                                </td>
                                                <td className="py-3 px-3 text-[11px] text-slate-600">
                                                    <div>{ret.batch_number}</div>
                                                    <div className="text-[10px] text-slate-400">ED: {ret.expired_date}</div>
                                                </td>
                                                <td className="py-3 px-3 text-center font-bold text-slate-700">
                                                    {ret.qty} {ret.satuan}
                                                </td>
                                                <td className="py-3 px-3 text-right font-extrabold text-rose-600">
                                                    - {formatRupiah(ret.nominal)}
                                                </td>
                                                <td className="py-3 px-3 text-center">
                                                    {ret.status === 'sudah_potong' ? (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                                            <Check className="w-3 h-3 text-purple-600" />
                                                            <span>Sudah Potong Tagihan</span>
                                                        </span>
                                                    ) : ret.status === 'sudah_diambil' ? (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                                            <span>Sudah Diambil PBF</span>
                                                        </span>
                                                    ) : ret.status === 'dikonfirmasi' ? (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                                            <span>Dikonfirmasi</span>
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                                            <span>{ret.status_label || ret.status}</span>
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* KARTU AUDIT TRAIL / LOG PERUBAHAN DATA */}
                    <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div className="flex items-center gap-2.5">
                                <History className="w-5 h-5 text-blue-600" />
                                <div>
                                    <h3 className="font-bold text-slate-900 text-sm">
                                        Pantau log perubahan data
                                    </h3>
                                    <p className="text-[11px] text-slate-400">
                                        Catatan audit pembayaran faktur kredit, akun bank, dan nama staf pemroses.
                                    </p>
                                </div>
                            </div>

                            <span className="text-xs font-semibold text-slate-600">
                                Total Terbayar: <strong className="text-emerald-600">{formatRupiah(detailViewHutang.terbayar)}</strong>
                            </span>
                        </div>

                        {detailViewHutang.payments && detailViewHutang.payments.length > 0 ? (
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
                                            <th className="py-2.5 px-3 font-medium text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 text-slate-700">
                                        {detailViewHutang.payments.map((pay) => (
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
                                                <td className="py-3 px-3 text-slate-500 text-[11px] whitespace-pre-line">{pay.catatan}</td>
                                                <td className="py-3 px-3 text-right">
                                                    {pay.akunId && pay.tipe === 'KREDIT' ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => openReviseAccountModal(pay, detailViewHutang)}
                                                            className="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg transition cursor-pointer"
                                                        >
                                                            <Pencil className="w-3 h-3" />
                                                            <span>Ubah Akun</span>
                                                        </button>
                                                    ) : (
                                                        <span className="text-[11px] text-slate-300">-</span>
                                                    )}
                                                </td>
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
                /* SUB-VIEW B: TABEL DAFTAR HUTANG DAGANG */
                <div className="space-y-6">
                    {/* Banner Ringkasan Sisa Hutang */}
                    <div className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Total Sisa Tagihan Hutang
                            </div>
                            <div className="text-2xl font-black text-slate-900 mt-1">
                                {formatRupiah(stats.totalHutangSisa || 0)}
                            </div>
                            <p className="text-xs text-slate-400 mt-0.5">
                                Dari total {stats.totalHutangCount ?? totalFilteredItems} faktur kredit yang tercatat
                            </p>
                        </div>

                        <div className="flex items-center gap-2.5 flex-wrap">
                            {dueStats.overdue > 0 && (
                                <span className="px-3 py-1.5 rounded-xl bg-red-100 text-red-800 text-xs font-black border border-red-200 flex items-center gap-1.5 shadow-2xs">
                                    <AlertTriangle className="w-3.5 h-3.5 text-red-600" />
                                    <span>{dueStats.overdue} Lewat Tempo</span>
                                </span>
                            )}
                            {dueStats.dueSoon > 0 && (
                                <span className="px-3 py-1.5 rounded-xl bg-amber-100 text-amber-900 text-xs font-bold border border-amber-200 flex items-center gap-1.5 shadow-2xs">
                                    <Clock className="w-3.5 h-3.5 text-amber-700" />
                                    <span>{dueStats.dueSoon} Tempo ≤ 7 Hari</span>
                                </span>
                            )}
                            <span className="px-3 py-1.5 rounded-xl bg-red-50 text-red-700 text-xs font-bold border border-red-100">
                                {stats.countBelumBayar || 0} Belum Dibayar
                            </span>
                            <span className="px-3 py-1.5 rounded-xl bg-amber-50 text-amber-700 text-xs font-bold border border-amber-100">
                                {stats.countSebagian || 0} Sebagian
                            </span>
                            {Number(stats.countDiajukan || 0) > 0 && (
                                <span className="px-3 py-1.5 rounded-xl bg-blue-50 text-blue-700 text-xs font-bold border border-blue-200">
                                    {stats.countDiajukan} Diajukan
                                </span>
                            )}
                            <span className="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-100">
                                {stats.countLunas || 0} Lunas
                            </span>
                        </div>
                    </div>

                    {/* Filter & Toolbar */}
                    <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-wrap items-center gap-2.5 flex-1">

                                {/* Search Input */}
                                <div className="relative w-full sm:w-64">
                                    <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                                    <input
                                        type="text"
                                        placeholder="Cari nomor faktur, PBF, kode PBF, atau penerimaan..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:bg-white focus:outline-none"
                                    />
                                </div>

                                {/* PBF Filter */}
                                <PbfCombobox
                                    pbfs={creditors}
                                    selectedPbf={filterPbf}
                                    onSelectPbf={(val) => {
                                        setFilterPbf(val);
                                        navigateHutang({ pbf: val, page: 1 });
                                    }}
                                    placeholder="Filter PBF (Kreditur)..."
                                />

                                {/* Status Filter */}
                                <div className="flex items-center bg-slate-100 p-1 rounded-xl text-xs">
                                    <button
                                        onClick={() => {
                                            setStatusFilter('ALL');
                                            navigateHutang({ status: 'ALL', page: 1 });
                                        }}
                                        className={`px-3 py-1.5 rounded-lg font-semibold transition ${statusFilter === 'ALL' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'}`}
                                    >
                                        Semua
                                    </button>
                                    <button
                                        onClick={() => {
                                            setStatusFilter('BELUM_LUNAS');
                                            navigateHutang({ status: 'BELUM_LUNAS', page: 1 });
                                        }}
                                        className={`px-3 py-1.5 rounded-lg font-semibold transition ${statusFilter === 'BELUM_LUNAS' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'}`}
                                    >
                                        Belum Lunas
                                    </button>
                                    <button
                                        onClick={() => {
                                            setStatusFilter('DIAJUKAN');
                                            navigateHutang({ status: 'DIAJUKAN', page: 1 });
                                        }}
                                        className={`px-3 py-1.5 rounded-lg font-semibold transition ${statusFilter === 'DIAJUKAN' ? 'bg-white text-blue-700 shadow-xs font-bold' : 'text-slate-500 hover:text-slate-800'}`}
                                    >
                                        Diajukan
                                    </button>
                                    <button
                                        onClick={() => {
                                            setStatusFilter('RETUR');
                                            navigateHutang({ status: 'RETUR', page: 1 });
                                        }}
                                        className={`px-3 py-1.5 rounded-lg font-semibold transition ${statusFilter === 'RETUR' ? 'bg-white text-rose-700 shadow-xs font-bold' : 'text-slate-500 hover:text-slate-800'}`}
                                    >
                                        Retur
                                    </button>
                                    <button
                                        onClick={() => {
                                            setStatusFilter('LUNAS');
                                            navigateHutang({ status: 'LUNAS', page: 1 });
                                        }}
                                        className={`px-3 py-1.5 rounded-lg font-semibold transition ${statusFilter === 'LUNAS' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'}`}
                                    >
                                        Lunas
                                    </button>
                                </div>



                                {/* Urutan tanggal faktur */}
                                <button
                                    type="button"
                                    onClick={() => {
                                        const nextOrder = sortOrder === 'asc' ? 'desc' : 'asc';
                                        setSortOrder(nextOrder);
                                        navigateHutang({ sort_order: nextOrder, page: 1 });
                                    }}
                                    className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border transition cursor-pointer ${sortOrder === 'asc'
                                        ? 'bg-amber-50 border-amber-300 text-amber-800 font-bold shadow-2xs'
                                        : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
                                        }`}
                                    title="Klik untuk mengubah urutan tanggal faktur"
                                >
                                    {sortOrder === 'asc' ? (
                                        <ArrowUp className="w-3.5 h-3.5 text-amber-600" />
                                    ) : (
                                        <ArrowDown className="w-3.5 h-3.5 text-slate-500" />
                                    )}
                                    <span>{dateField === 'invoice' ? 'Faktur' : dateField === 'received' ? 'Terima' : 'Jatuh tempo'}: {sortOrder === 'asc' ? 'Terlama (ASC)' : 'Terbaru (DESC)'}</span>
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
                                                onClick={() => { setDueFilter('all'); setIsDueDropdownOpen(false); navigateHutang({ due_mode: 'all', page: 1 }); }}
                                                className={`w-full text-left px-3.5 py-2 hover:bg-slate-50 flex items-center justify-between cursor-pointer ${dueFilter === 'all' ? 'font-bold text-amber-700 bg-amber-50/50' : 'text-slate-700'}`}
                                            >
                                                <span>Semua Jatuh Tempo</span>
                                                {dueFilter === 'all' && <Check className="w-3.5 h-3.5 text-amber-600" />}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => { setDueFilter('overdue'); setIsDueDropdownOpen(false); navigateHutang({ due_mode: 'overdue', page: 1 }); }}
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
                                                onClick={() => { setDueFilter('due_soon'); setIsDueDropdownOpen(false); navigateHutang({ due_mode: 'due_soon', page: 1 }); }}
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
                                                onClick={() => { setDueFilter('due_nearest'); setIsDueDropdownOpen(false); navigateHutang({ due_mode: 'due_nearest', page: 1 }); }}
                                                className={`w-full text-left px-3.5 py-2 hover:bg-slate-50 flex items-center justify-between cursor-pointer ${dueFilter === 'due_nearest' ? 'font-bold text-blue-700 bg-blue-50/50' : 'text-slate-700'}`}
                                            >
                                                <span>Tempo Terdekat (Urutkan)</span>
                                                {dueFilter === 'due_nearest' && <Check className="w-3.5 h-3.5 text-blue-600" />}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => { setDueFilter('due_furthest'); setIsDueDropdownOpen(false); navigateHutang({ due_mode: 'due_furthest', page: 1 }); }}
                                                className={`w-full text-left px-3.5 py-2 hover:bg-slate-50 flex items-center justify-between cursor-pointer ${dueFilter === 'due_furthest' ? 'font-bold text-blue-700 bg-blue-50/50' : 'text-slate-700'}`}
                                            >
                                                <span>Tempo Terjauh (Urutkan)</span>
                                                {dueFilter === 'due_furthest' && <Check className="w-3.5 h-3.5 text-blue-600" />}
                                            </button>
                                        </div>
                                    )}
                                </div>

                                {/* Tombol Tambah Saldo Awal, Import, & Export Excel */}
                                <button
                                    type="button"
                                    onClick={openOpeningModal}
                                    className="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold transition shadow-2xs cursor-pointer"
                                    title="Tambah faktur hutang dagang pra-sistem (Saldo Awal) tanpa mengubah stok & Kas/Bank"
                                >
                                    <Plus className="w-3.5 h-3.5" />
                                    <span>Tambah Saldo Awal</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setIsImportModalOpen(true)}
                                    className="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-50 hover:bg-blue-100 text-blue-800 border border-blue-200 rounded-xl text-xs font-semibold transition shadow-2xs"
                                >
                                    <Upload className="w-3.5 h-3.5" />
                                    <span>Import Pembayaran</span>
                                </button>
                                <a
                                    href={`/finance/export/hutang?search=${encodeURIComponent(searchTerm)}&status=${encodeURIComponent(statusFilter === 'ALL' ? '' : statusFilter)}&pbf=${encodeURIComponent(filterPbf)}&sort_order=${encodeURIComponent(sortOrder)}&due_mode=${encodeURIComponent(dueFilter)}&date_field=${encodeURIComponent(dateField)}&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`}
                                    className="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-50 hover:bg-emerald-100/80 text-emerald-800 border border-emerald-200 rounded-xl text-xs font-semibold transition shadow-2xs cursor-pointer"
                                    title="Export data hutang dagang ke Excel (.xlsx) dengan format rapi dan estetik"
                                >
                                    <FileSpreadsheet className="w-3.5 h-3.5 text-emerald-600" />
                                    <span>Export Excel</span>
                                </a>
                            </div>

                            {/* Tombol Pelunasan Massal */}
                            {selectedHutangIds.length > 0 && (
                                <button
                                    onClick={openBulkModal}
                                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center gap-2 animate-in zoom-in-95 duration-150"
                                >
                                    <CreditCard className="w-4 h-4" />
                                    <span>Bayar Massal ({selectedHutangIds.length} Faktur)</span>
                                </button>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3">
                            <label className="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                                <span>Filter tanggal berdasarkan</span>
                                <select
                                    value={dateField}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        setDateField(val);
                                        navigateHutang({ date_field: val, page: 1 });
                                    }}
                                    className="px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                >
                                    <option value="due">Jatuh tempo</option>
                                    <option value="invoice">Tanggal faktur</option>
                                    <option value="received">Tanggal terima</option>
                                </select>
                            </label>
                            <DateRangeFilter
                                startDate={startDate}
                                endDate={endDate}
                                preset={datePreset}
                                accent="blue"
                                onPreset={handleDatePreset}
                                onChange={([start, end]) => {
                                    const nextPreset = start || end ? 'custom' : 'all';
                                    setStartDate(start);
                                    setEndDate(end);
                                    setDatePreset(nextPreset);
                                    navigateHutang({ date_preset: nextPreset, start_date: start, end_date: end, page: 1 });
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

                    {/* Tabel Hutang Dagang */}
                    <div className={`bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden transition-opacity ${isLoadingList ? 'opacity-60 pointer-events-none' : ''}`}>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr className="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider text-[11px]">
                                        <th className="px-4 py-3.5 w-10">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    paginatedHutang.filter(i => !isItemSettled(i)).length > 0 &&
                                                    paginatedHutang
                                                        .filter(i => !isItemSettled(i))
                                                        .every(i => selectedHutangIds.includes(i.id))
                                                }
                                                onChange={handleSelectAllHutang}
                                                className="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                title="Pilih semua di halaman ini"
                                            />
                                        </th>
                                        <th className="px-4 py-3.5">Faktur & Tanggal</th>
                                        <th className="px-4 py-3.5 whitespace-nowrap">Jatuh Tempo</th>
                                        <th className="px-4 py-3.5">Vendor (PBF)</th>
                                        <th className="px-4 py-3.5">Gudang</th>
                                        <th className="px-4 py-3.5 text-right">Total Faktur</th>
                                        <th className="px-4 py-3.5 text-right">Retur</th>
                                        <th className="px-4 py-3.5 text-right">Terbayar</th>
                                        <th className="px-4 py-3.5 text-right">Sisa Tagihan</th>
                                        <th className="px-4 py-3.5 text-center">Status</th>
                                        <th className="px-4 py-3.5 text-center w-28">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {paginatedHutang.length === 0 ? (
                                        <tr>
                                            <td colSpan={11} className="text-center py-12 text-slate-400">
                                                Tidak ada data hutang dagang yang sesuai dengan filter.
                                            </td>
                                        </tr>
                                    ) : (
                                        paginatedHutang.map((item) => (
                                            <tr
                                                key={item.id}
                                                onClick={() => openHutangDrawer(item)}
                                                className="hover:bg-blue-50/40 cursor-pointer transition group"
                                            >
                                                <td className="px-4 py-3.5" onClick={(e) => e.stopPropagation()}>
                                                    {isItemSettled(item) ? (
                                                        <span title="Faktur sudah lunas / diretur penuh" className="inline-block p-0.5 text-emerald-500">
                                                            <Check className="w-3.5 h-3.5" />
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
                                                    <div className="flex items-center gap-1.5 flex-wrap">
                                                        <button
                                                            type="button"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                openHutangDetail(item);
                                                            }}
                                                            className="font-bold text-slate-900 hover:text-blue-600 text-left transition group-hover:text-blue-600 cursor-pointer"
                                                            title="Buka Halaman Detil Lengkap"
                                                        >
                                                            {item.nomor}
                                                        </button>
                                                        {item.is_opening && (
                                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                                Saldo Awal
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="text-[11px] text-slate-400 mt-0.5">
                                                        {item.referensi} • Tgl: {item.tanggal}
                                                    </div>
                                                </td>
                                                {/* Kolom Jatuh Tempo Visual Jelas & Menonjol */}
                                                <td className="px-4 py-3.5 whitespace-nowrap">
                                                    {(() => {
                                                        const dueInfo = computeDueInfo(item);
                                                        if (dueInfo.isLunas) {
                                                            return (
                                                                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200">
                                                                    <Check className="w-3.5 h-3.5 text-emerald-600" />
                                                                    <span>{item.jatuhTempo || '-'}</span>
                                                                    <span className="text-[10px] text-emerald-600 font-bold ml-0.5">• Lunas</span>
                                                                </span>
                                                            );
                                                        }
                                                        if (!dueInfo.hasDate) {
                                                            return <span className="text-slate-400 text-xs italic">-</span>;
                                                        }
                                                        if (dueInfo.isOverdue || dueInfo.daysOverdue > 0) {
                                                            return (
                                                                <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-red-100 text-red-800 text-xs font-bold border border-red-300 shadow-2xs">
                                                                    <AlertTriangle className="w-3.5 h-3.5 text-red-600 shrink-0" />
                                                                    <span>{item.jatuhTempo}</span>
                                                                    <span className="text-[10px] bg-red-600 text-white px-1.5 py-0.5 rounded-md font-black">
                                                                        Lewat {dueInfo.daysOverdue} hr
                                                                    </span>
                                                                </div>
                                                            );
                                                        }
                                                        if (dueInfo.daysRemaining <= 7) {
                                                            return (
                                                                <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-100 text-amber-900 text-xs font-bold border border-amber-300 shadow-2xs">
                                                                    <Clock className="w-3.5 h-3.5 text-amber-700 shrink-0" />
                                                                    <span>{item.jatuhTempo}</span>
                                                                    <span className="text-[10px] bg-amber-600 text-white px-1.5 py-0.5 rounded-md font-bold">
                                                                        Sisa {dueInfo.daysRemaining} hr
                                                                    </span>
                                                                </div>
                                                            );
                                                        }
                                                        return (
                                                            <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-medium border border-slate-200">
                                                                <Calendar className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                                                <span>{item.jatuhTempo}</span>
                                                                <span className="text-[10px] text-slate-500 font-normal">({dueInfo.daysRemaining} hr)</span>
                                                            </div>
                                                        );
                                                    })()}
                                                </td>
                                                <td className="px-4 py-3.5">
                                                    <div className="font-semibold text-slate-800">{item.vendor}</div>
                                                </td>
                                                <td className="px-4 py-3.5 text-slate-600">
                                                    {item.gudang}
                                                </td>
                                                <td className="px-4 py-3.5 text-right font-medium text-slate-800">
                                                    {formatNumberOnly(item.total)}
                                                </td>
                                                <td className="px-4 py-3.5 text-right font-medium">
                                                    {item.total_retur > 0 ? (
                                                        <span className="font-semibold text-rose-600" title={`Total Retur Pembelian: ${formatRupiah(item.total_retur)}`}>
                                                            - {formatNumberOnly(item.total_retur)}
                                                        </span>
                                                    ) : (
                                                        <span className="text-slate-300">-</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3.5 text-right text-emerald-600 font-medium">
                                                    {formatNumberOnly(item.terbayar)}
                                                </td>
                                                <td className="px-4 py-3.5 text-right font-bold text-red-600">
                                                    {formatNumberOnly(item.sisa)}
                                                </td>
                                                <td className="px-4 py-3.5 text-center">
                                                    <div className="flex flex-col items-center gap-1">
                                                        {renderStatusBadge(item)}
                                                        {item.status === 'Lunas' && item.tanggalBayar && (
                                                            <span className="text-[10px] font-medium text-emerald-600">Lunas {item.tanggalBayar}</span>
                                                        )}
                                                        {item.is_submitted && !isItemSettled(item) && (
                                                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 border border-blue-200">
                                                                Diajukan
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3.5 text-center" onClick={(e) => e.stopPropagation()}>
                                                    <div className="flex items-center justify-center gap-1.5">
                                                        {item.sisa > 0.005 ? (
                                                            <button
                                                                type="button"
                                                                onClick={() => openPaymentModal(item)}
                                                                className="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold transition cursor-pointer"
                                                            >
                                                                Bayar
                                                            </button>
                                                        ) : null}
                                                        <button
                                                            type="button"
                                                            onClick={() => openHutangDetail(item)}
                                                            className="px-2.5 py-1 border border-slate-200 text-slate-600 hover:bg-slate-100 rounded-lg text-xs font-semibold transition cursor-pointer"
                                                            title="Buka Halaman Detil Lengkap"
                                                        >
                                                            Detil
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
                        {(totalPages > 1 || totalFilteredItems > 0) && (
                            <div className="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                                <div>
                                    Menampilkan {pageFrom} - {pageTo} dari {totalFilteredItems} faktur
                                </div>
                                {totalPages > 1 && (
                                    <div className="flex items-center gap-1">
                                        <button
                                            onClick={() => navigateHutang({ page: Math.max(1, currentPage - 1) })}
                                            disabled={currentPage <= 1 || isLoadingList}
                                            className="p-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50 cursor-pointer"
                                        >
                                            <ChevronLeft className="w-4 h-4" />
                                        </button>
                                        <span className="px-3 py-1 font-semibold text-slate-800">
                                            {currentPage} / {totalPages}
                                        </span>
                                        <button
                                            onClick={() => navigateHutang({ page: Math.min(totalPages, currentPage + 1) })}
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
            )}

            {/* Modal Bayar Satuan */}
            {isPaymentModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-200">
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
                                    <div className="font-bold text-slate-800">Pelunasan Pra-Cut-off (Tanpa Potong Kas &amp; Bank)</div>
                                    <div className="text-[11px] text-slate-500 mt-0.5 leading-snug">
                                        Aktifkan untuk faktur lama sebelum tanggal cut-off yang sudah lunas di luar sistem. Saldo Kas &amp; Bank tidak akan berkurang.
                                    </div>
                                </div>
                            </label>

                            {paymentForm.is_cutoff ? (
                                <div className="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-[11px] leading-relaxed">
                                    <strong>Mode Pra-Cut-off aktif:</strong> Pembayaran ini hanya melunasi tagihan &amp; mengurangi Saldo Hutang Dagang tanpa memotong saldo Kas &amp; Bank. Isi <strong>Tanggal Bayar</strong> pada atau sebelum tanggal cut-off Saldo Awal (misal 31 Okt).
                                </div>
                            ) : (
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Bayar Dari Akun (Kas &amp; Bank) <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={paymentForm.account_id}
                                        onChange={(e) => setPaymentForm({ ...paymentForm, account_id: e.target.value })}
                                        required
                                        className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    >
                                        <option value="">-- Pilih Akun Kas &amp; Bank --</option>
                                        {kasBankAccounts.map((acc) => (
                                            <option key={acc.id} value={acc.id}>
                                                {acc.name} ({acc.code}) {acc.account_number ? `- ${acc.account_number}` : ''} - Saldo: {formatRupiah(acc.balance)}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

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
                                        step="any"
                                        min="0.01"
                                        value={paymentForm.amount}
                                        onChange={(e) => setPaymentForm({ ...paymentForm, amount: e.target.value === '' ? '' : parseFloat(e.target.value) })}
                                        required
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    />
                                    {paymentForm.sisa > 0 && (
                                        <div className="flex items-center justify-between mt-1 text-[11px] text-slate-400">
                                            <span>Sisa: {formatRupiah(paymentForm.sisa)}</span>
                                            <button
                                                type="button"
                                                onClick={() => setPaymentForm({ ...paymentForm, amount: paymentForm.sisa })}
                                                className="text-blue-600 hover:underline font-semibold"
                                            >
                                                Set Lunas Penuh
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Nomor Referensi / No. Transaksi Bank
                                </label>
                                <input
                                    type="text"
                                    placeholder="Contoh: TRF-BCA-20260924-001"
                                    value={paymentForm.reference_number}
                                    onChange={(e) => setPaymentForm({ ...paymentForm, reference_number: e.target.value })}
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                />
                            </div>

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
                                    className={`px-4 py-2 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50 ${paymentForm.is_cutoff ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-blue-600 hover:bg-blue-700'}`}
                                >
                                    {isSubmittingPayment ? 'Memproses...' : (paymentForm.is_cutoff ? 'Simpan Pelunasan Pra-Cut-off' : 'Simpan Pembayaran')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Pelunasan Massal */}
            {isImportModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Upload className="w-5 h-5 text-blue-600" />
                                <h3 className="font-bold text-slate-900 text-sm">Impor Pembayaran Hutang Dagang</h3>
                            </div>
                            <button type="button" onClick={() => setIsImportModalOpen(false)} className="text-slate-400 hover:text-slate-600 p-1 rounded-lg" aria-label="Tutup">
                                <X className="w-4 h-4" />
                            </button>
                        </div>
                        <form onSubmit={handleImportPayments} className="p-6 space-y-4 text-xs">
                            <p className="text-slate-600 leading-relaxed">
                                Isi satu faktur per baris. Sistem akan membayar seluruh sisa tagihan dan memilih akun berdasarkan Kode Bank.
                            </p>
                            <label className={`flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition ${importIsCutoff ? 'bg-indigo-50 border-indigo-300' : 'bg-slate-50 border-slate-200 hover:bg-slate-100/70'}`}>
                                <input
                                    type="checkbox"
                                    checked={importIsCutoff}
                                    onChange={(e) => setImportIsCutoff(e.target.checked)}
                                    className="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <div>
                                    <div className="font-bold text-slate-800">Mode Pelunasan Pra-Cut-off (Tanpa Potong Kas &amp; Bank)</div>
                                    <div className="text-[11px] text-slate-500 mt-0.5 leading-snug">
                                        Aktifkan jika seluruh baris di file adalah faktur lama pra-cut-off. Kolom Kode Bank boleh dikosongkan (atau isi <code className="px-1 bg-white rounded border">CUTOFF</code> per baris).
                                    </div>
                                </div>
                            </label>
                            <div className="rounded-xl bg-slate-50 border border-slate-200 p-3 text-slate-600">
                                <div className="font-semibold text-slate-700 mb-1">Kolom wajib</div>
                                <div>No. Faktur · Tanggal Bayar · {importIsCutoff ? 'Kode Bank (opsional)' : 'Kode Bank'}</div>
                                <div className="mt-1 text-slate-500">Tanggal: YYYY-MM-DD atau DD/MM/YYYY. Kode Bank harus sama dengan kode akun Kas &amp; Bank (atau <code className="px-1 bg-white rounded border">CUTOFF</code> untuk non-kas).</div>
                            </div>
                            <a href="/finance/hutang/import-template" className="inline-flex items-center gap-1.5 text-blue-700 font-semibold hover:text-blue-800">
                                <Download className="w-3.5 h-3.5" /> Unduh template CSV
                            </a>
                            <input
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                onChange={(e) => setImportFile(e.target.files?.[0] || null)}
                                className="block w-full rounded-xl border border-slate-200 p-2.5 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:font-semibold file:text-blue-700"
                                required
                            />
                            {importFile && <div className="text-slate-500 truncate">File dipilih: {importFile.name}</div>}
                            <div className="pt-3 border-t border-slate-100 flex justify-end gap-2">
                                <button type="button" onClick={() => setIsImportModalOpen(false)} className="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50">Batal</button>
                                <button type="submit" disabled={isImporting || !importFile} className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold disabled:opacity-50">
                                    {isImporting ? 'Memproses...' : 'Impor & Catat Pembayaran'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Pelunasan Massal */}
            {isBulkModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <CreditCard className="w-5 h-5 text-blue-600" />
                                <h3 className="font-bold text-slate-900 text-sm">
                                    Pelunasan Massal ({selectedHutangIds.length} Faktur Terpilih)
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
                            <div className="p-3 bg-blue-50/70 border border-blue-100 rounded-xl">
                                <div className="text-slate-500 font-medium">Total Nominal Pelunasan:</div>
                                <div className="text-xl font-black text-blue-700 mt-0.5">
                                    {formatRupiah(totalSelectedHutangSisa)}
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
                                    <div className="font-bold text-slate-800">Pelunasan Pra-Cut-off (Tanpa Potong Kas &amp; Bank)</div>
                                    <div className="text-[11px] text-slate-500 mt-0.5 leading-snug">
                                        Untuk menandai lunas faktur-faktur lama sebelum cut-off yang sudah terbayar di luar sistem. Saldo Kas &amp; Bank tetap utuh.
                                    </div>
                                </div>
                            </label>

                            {bulkForm.is_cutoff ? (
                                <div className="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-[11px] leading-relaxed">
                                    <strong>Mode Pra-Cut-off aktif:</strong> {selectedHutangIds.length} faktur terpilih akan ditandai lunas tanpa memotong saldo Kas &amp; Bank. Pastikan <strong>Tanggal Bayar</strong> diisi ≤ tanggal cut-off Saldo Awal.
                                </div>
                            ) : (
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Bayar Dari Akun (Kas &amp; Bank) <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={bulkForm.account_id}
                                        onChange={(e) => setBulkForm({ ...bulkForm, account_id: e.target.value })}
                                        required
                                        className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-blue-500 focus:outline-none"
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
                                    <label className="block text-slate-700 font-semibold mb-1">Tanggal Bayar</label>
                                    <input
                                        type="date"
                                        value={bulkForm.payment_date}
                                        onChange={(e) => setBulkForm({ ...bulkForm, payment_date: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">No. Referensi Bank</label>
                                    <input
                                        type="text"
                                        placeholder="Contoh: BULK-TRF-001"
                                        value={bulkForm.reference_number}
                                        onChange={(e) => setBulkForm({ ...bulkForm, reference_number: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">Catatan</label>
                                <textarea
                                    rows={2}
                                    value={bulkForm.notes}
                                    onChange={(e) => setBulkForm({ ...bulkForm, notes: e.target.value })}
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
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
                                    className={`px-4 py-2 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50 ${bulkForm.is_cutoff ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-blue-600 hover:bg-blue-700'}`}
                                >
                                    {isSubmittingBulk ? 'Memproses...' : (bulkForm.is_cutoff ? 'Konfirmasi Pelunasan Pra-Cut-off' : 'Konfirmasi Pelunasan Massal')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Drawer Detail Faktur (Quick Preview Slide-in) */}
            <Drawer
                item={drawerHutang}
                onClose={() => setDrawerHutang(null)}
                onExpand={(item) => {
                    setDrawerHutang(null);
                    openHutangDetail(item);
                }}
                title="Rincian Faktur Hutang"
                subtitle={(item) => `${item.nomor} • ${item.referensi}`}
                renderFooter={(item) => (
                    <div className="space-y-2 w-full">
                        <div className="flex items-center gap-2 w-full">
                            <button
                                type="button"
                                onClick={() => {
                                    const target = item;
                                    setDrawerHutang(null);
                                    openHutangDetail(target);
                                }}
                                className="flex-1 py-2.5 border border-slate-200 text-slate-700 hover:bg-slate-50 active:scale-[0.99] rounded-xl text-xs font-bold transition text-center cursor-pointer"
                            >
                                Buka Halaman Lengkap
                            </button>
                            {item.sisa > 0.005 ? (
                                <button
                                    type="button"
                                    onClick={() => {
                                        const target = item;
                                        setDrawerHutang(null);
                                        openPaymentModal(target);
                                    }}
                                    className="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 active:scale-[0.99] text-white rounded-xl text-xs font-bold transition shadow-xs text-center cursor-pointer"
                                >
                                    Bayar Tagihan
                                </button>
                            ) : null}
                        </div>
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
                        {/* Ringkasan Vendor */}
                        <div className="p-4 bg-slate-50 rounded-xl space-y-2">
                            <div className="flex justify-between">
                                <span className="text-slate-500">Vendor / PBF:</span>
                                <span className="font-bold text-slate-800">{item.vendor}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Tanggal Faktur:</span>
                                <span className="font-medium text-slate-700">{item.tanggal}</span>
                            </div>
                            <div className="flex justify-between items-center">
                                <span className="text-slate-500">Jatuh Tempo:</span>
                                {(() => {
                                    const dueInfo = computeDueInfo(item);
                                    if (dueInfo.isLunas) {
                                        return (
                                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200">
                                                <Check className="w-3 h-3 text-emerald-600" />
                                                <span>{item.jatuhTempo || '-'}</span>
                                                <span className="text-[10px] text-emerald-600 font-bold ml-0.5">• Lunas</span>
                                            </span>
                                        );
                                    }
                                    if (!dueInfo.hasDate) {
                                        return <span className="text-slate-400 text-xs italic">-</span>;
                                    }
                                    if (dueInfo.isOverdue || dueInfo.daysOverdue > 0) {
                                        return (
                                            <div className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-red-100 text-red-800 text-xs font-bold border border-red-300">
                                                <AlertTriangle className="w-3 h-3 text-red-600 shrink-0" />
                                                <span>{item.jatuhTempo}</span>
                                                <span className="text-[10px] bg-red-600 text-white px-1.5 py-0.2 rounded font-black">
                                                    Lewat {dueInfo.daysOverdue} hr
                                                </span>
                                            </div>
                                        );
                                    }
                                    if (dueInfo.daysRemaining <= 7) {
                                        return (
                                            <div className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-amber-100 text-amber-900 text-xs font-bold border border-amber-300">
                                                <Clock className="w-3 h-3 text-amber-700 shrink-0" />
                                                <span>{item.jatuhTempo}</span>
                                                <span className="text-[10px] bg-amber-600 text-white px-1.5 py-0.2 rounded font-bold">
                                                    Sisa {dueInfo.daysRemaining} hr
                                                </span>
                                            </div>
                                        );
                                    }
                                    return (
                                        <div className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-slate-100 text-slate-700 text-xs font-medium border border-slate-200">
                                            <Calendar className="w-3 h-3 text-slate-400 shrink-0" />
                                            <span>{item.jatuhTempo}</span>
                                            <span className="text-[10px] text-slate-500">({dueInfo.daysRemaining} hr)</span>
                                        </div>
                                    );
                                })()}
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Gudang Penerima:</span>
                                <span className="font-medium text-slate-700">{item.gudang}</span>
                            </div>
                        </div>

                        {/* Daftar Obat / Barang */}
                        <div>
                            <h4 className="font-bold text-slate-900 mb-3">Item Barang / Obat</h4>
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
                                                        <Loader2 className="w-3.5 h-3.5 animate-spin text-blue-600" />
                                                        <span>Memuat rincian obat...</span>
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
                                                <td className="p-3 text-right">
                                                    {formatNumberOnly(it.harga)}
                                                </td>
                                                <td className="p-3 text-right font-semibold text-slate-900">
                                                    {formatNumberOnly(it.jumlah)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* Subtotal, PPN, dan Total */}
                        <div className="p-4 bg-slate-50 rounded-xl space-y-1.5 font-medium">
                            <div className="flex justify-between text-slate-600">
                                <span>Subtotal:</span>
                                <span>{formatNumberOnly(item.subtotal)}</span>
                            </div>
                            <div className="flex justify-between text-slate-600">
                                <span>PPN:</span>
                                <span>{formatNumberOnly(item.ppn)}</span>
                            </div>
                            <div className="flex justify-between text-base font-bold text-slate-900 pt-2 border-t border-slate-200">
                                <span>Total Tagihan (Bruto):</span>
                                <span>{formatRupiah(item.total)}</span>
                            </div>
                            {item.total_retur > 0 && (
                                <>
                                    <div className="flex justify-between text-rose-600 font-semibold text-xs">
                                        <span className="flex items-center gap-1">
                                            <RotateCcw className="w-3.5 h-3.5" />
                                            <span>Retur Pembelian:</span>
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
                            <div className="flex justify-between text-red-600 font-bold text-sm pt-1 border-t border-slate-200">
                                <span>Sisa Tagihan:</span>
                                <span>{formatRupiah(item.sisa)}</span>
                            </div>
                        </div>

                        {/* Riwayat Retur Pembelian di Drawer */}
                        {item.returs && item.returs.length > 0 && (
                            <div>
                                <div className="flex items-center justify-between mb-2">
                                    <h4 className="font-bold text-rose-700 text-xs flex items-center gap-1.5">
                                        <RotateCcw className="w-3.5 h-3.5" />
                                        <span>Riwayat Retur Beli ({item.returs.length})</span>
                                    </h4>
                                    <span className="text-[11px] font-bold text-rose-600">
                                        - {formatRupiah(item.total_retur)}
                                    </span>
                                </div>
                                <div className="space-y-2">
                                    {item.returs.map((r, idx) => (
                                        <div key={r.id || idx} className="p-2.5 bg-rose-50/60 border border-rose-100 rounded-xl text-xs space-y-1">
                                            <div className="flex justify-between items-center">
                                                <span className="font-bold text-slate-800">{r.nama}</span>
                                                <span className="font-bold text-rose-700">- {formatRupiah(r.nominal)}</span>
                                            </div>
                                            <div className="flex justify-between text-[11px] text-slate-500">
                                                <span>{r.code} • {r.qty} {r.satuan}</span>
                                                <span>{r.tanggal}</span>
                                            </div>
                                            <div className="flex justify-between items-center text-[10px] pt-0.5">
                                                <span className="text-slate-400">Batch: {r.batch_number} (ED: {r.expired_date})</span>
                                                <span className={`px-1.5 py-0.5 rounded font-semibold ${r.status === 'sudah_potong'
                                                        ? 'bg-purple-100 text-purple-700'
                                                        : 'bg-slate-200/70 text-slate-700'
                                                    }`}>
                                                    {r.status_label || r.status}
                                                </span>
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
                                <p className="text-slate-400 italic">Belum ada riwayat pembayaran untuk faktur ini.</p>
                            ) : (
                                <div className="space-y-2">
                                    {item.payments.map((p) => (
                                        <div key={p.id} className="p-3 bg-slate-50 border border-slate-100 rounded-xl space-y-1.5">
                                            <div className="flex justify-between items-center gap-2">
                                                <span className="font-bold text-slate-900">{formatRupiah(p.nominal)}</span>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-[11px] text-slate-400">{p.tanggal}</span>
                                                    {p.akunId && p.tipe === 'KREDIT' && (
                                                        <button
                                                            type="button"
                                                            onClick={() => openReviseAccountModal(p, item)}
                                                            className="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-md transition cursor-pointer"
                                                        >
                                                            <Pencil className="w-2.5 h-2.5" />
                                                            <span>Ubah Akun</span>
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="text-[11px] text-slate-500">
                                                Via: <strong>{p.akunNama}</strong> ({p.akunKode}) • Ref: {p.noReferensi}
                                            </div>
                                            {p.catatan && p.catatan !== '-' && (
                                                <div className="text-[10px] text-slate-400 whitespace-pre-line">{p.catatan}</div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </>
                )}
            </Drawer>

            {/* Modal Tambah Tagihan Saldo Awal Hutang (Per Faktur) */}
            {isOpeningModalOpen && (() => {
                const parsedInvoiceAmt = openingForm.amount === '' ? 0 : (parseFloat(openingForm.amount) || 0);

                return (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto animate-in fade-in zoom-in-95 duration-200">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
                            <div className="flex items-center gap-2">
                                <Plus className="w-5 h-5 text-indigo-600" />
                                <h3 className="font-bold text-slate-900 text-sm">Tambah Faktur Saldo Awal Hutang Dagang</h3>
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
                                        Saldo Awal Hutang di Neraca (per {openingForm.invoice_date || '-'})
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
                                <strong>Input Per Faktur Lama (Belum Tercatat):</strong> Masukkan <strong>nominal faktur yang belum masuk sistem</strong> (bukan total seluruh saldo awal). Tidak mengubah stok obat maupun Kas &amp; Bank.
                            </div>

                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Vendor / PBF <span className="text-slate-400 font-normal">(Opsional)</span>
                                </label>
                                <input
                                    type="text"
                                    placeholder="Ketik untuk mencari nama atau kode PBF..."
                                    value={openingCreditorQuery}
                                    onChange={(e) => setOpeningCreditorQuery(e.target.value)}
                                    className="w-full px-3 py-2 mb-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none"
                                />
                                <select
                                    value={openingForm.creditor_code}
                                    onChange={(e) => setOpeningForm({ ...openingForm, creditor_code: e.target.value })}
                                    className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                >
                                    <option value="">-- Tanpa PBF / Saldo Awal Umum ({filteredOpeningCreditors.length} PBF tersedia) --</option>
                                    {filteredOpeningCreditors.map((c) => (
                                        <option key={c.code} value={c.code}>
                                            {c.name} ({c.code})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Nomor Faktur <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Contoh: SA-PBF-001"
                                        value={openingForm.invoice_number}
                                        onChange={(e) => setOpeningForm({ ...openingForm, invoice_number: e.target.value })}
                                        required
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">
                                        Nominal Faktur Ini (Rp) <span className="text-red-500">*</span>
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

                            <div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-slate-700 font-semibold mb-1">
                                            Tanggal Faktur / Cut-off <span className="text-red-500">*</span>
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
                                {openingForm.invoice_date && openingForm.invoice_date > getDefaultCutoffDate() && (
                                    <div className="mt-2 p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-[11px] leading-relaxed">
                                        <strong>Catatan Tanggal Bulan Berjalan:</strong> Untuk hutang bawaan periode sebelumnya, disarankan memakai tanggal akhir bulan lalu (<strong>{getDefaultCutoffDate()}</strong>). Meski memakai tanggal bulan ini, sistem otomatis menjaga angka <strong>Laba</strong> &amp; <strong>Cashflow Hutang</strong> agar tetap terpisah.
                                    </div>
                                )}
                            </div>

                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">Catatan</label>
                                <textarea
                                    rows={2}
                                    placeholder="Contoh: Faktur lama pra-migrasi belum sempat diinput di penerimaan..."
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
                                    {isSubmittingOpening ? 'Menyimpan...' : 'Tambah Faktur Saldo Awal'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                );
            })()}

            {/* Floating Action Bar saat faktur dicentang */}
            <FloatingActionBar
                selectedCount={selectedHutangIds.length}
                maxLimit={10}
                totalAmount={totalSelectedHutangSisa}
                totalFullAmount={totalSelectedHutangTotal}
                itemLabel="faktur"
                titleAmount="Total Sisa Tagihan Hutang"
                actionLabel={`Bayar Massal (${selectedHutangIds.length} Faktur)`}
                actionIcon={CreditCard}
                onAction={openBulkModal}
                secondaryActionLabel={allSelectedHutangSubmitted ? "Batalkan Pengajuan" : "Ajukan Pelunasan"}
                secondaryActionColor={allSelectedHutangSubmitted ? "slate" : "blue"}
                onSecondaryAction={() => handleBulkSubmitHutang(!allSelectedHutangSubmitted)}
                onClear={() => {
                    setSelectedHutangIds([]);
                    setSelectedItemsMap({});
                }}
                isSubmitting={isSubmittingBulk}
                themeColor="blue"
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
            {/* Modal Revisi Akun Kas & Bank Pembayaran */}
            {isReviseAccountModalOpen && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Landmark className="w-5 h-5 text-blue-600" />
                                <h3 className="font-bold text-slate-900 text-sm">Revisi Akun Kas & Bank</h3>
                            </div>
                            <button
                                type="button"
                                onClick={() => setIsReviseAccountModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg cursor-pointer"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <form onSubmit={handleReviseAccountSubmit} className="p-6 space-y-4 text-xs">
                            <div className="p-3 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1">
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Tanggal Bayar:</span>
                                    <span className="font-semibold text-slate-800">{reviseAccountForm.tanggal}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Nominal Pembayaran:</span>
                                    <span className="font-bold text-emerald-600">{formatRupiah(reviseAccountForm.nominal)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Akun Sebelumnya:</span>
                                    <span className="font-semibold text-slate-800">{reviseAccountForm.old_account_name}</span>
                                </div>
                            </div>

                            <div className="p-2.5 rounded-xl bg-blue-50/70 border border-blue-100 text-[11px] text-blue-700">
                                Revisi memindahkan mutasi saldo ke akun yang benar tanpa mengubah nominal, tanggal bayar, status faktur, atau Saldo Awal.
                            </div>

                            <div>
                                <label className="block font-semibold text-slate-700 mb-1.5">Akun Kas & Bank Baru *</label>
                                <select
                                    value={reviseAccountForm.account_id}
                                    onChange={(e) => setReviseAccountForm({ ...reviseAccountForm, account_id: e.target.value })}
                                    className="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none bg-white"
                                    required
                                >
                                    <option value="">-- Pilih Akun Kas & Bank --</option>
                                    {kasBankAccounts.map((acc) => (
                                        <option key={acc.id} value={acc.id}>
                                            {acc.name} ({acc.code}) — Saldo: {formatRupiah(acc.balance)}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block font-semibold text-slate-700 mb-1.5">No. Referensi / Bukti Transfer</label>
                                <input
                                    type="text"
                                    value={reviseAccountForm.reference_number}
                                    onChange={(e) => setReviseAccountForm({ ...reviseAccountForm, reference_number: e.target.value })}
                                    placeholder="Opsional, misal TRF-12345"
                                    className="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none"
                                />
                            </div>

                            <div>
                                <label className="block font-semibold text-slate-700 mb-1.5">Alasan Revisi</label>
                                <input
                                    type="text"
                                    value={reviseAccountForm.revision_reason}
                                    onChange={(e) => setReviseAccountForm({ ...reviseAccountForm, revision_reason: e.target.value })}
                                    placeholder="Opsional, misal Salah pilih rekening bank saat input"
                                    className="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none"
                                />
                            </div>

                            <div className="pt-2 flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setIsReviseAccountModalOpen(false)}
                                    className="px-4 py-2 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 font-medium transition cursor-pointer"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmittingReviseAccount}
                                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition disabled:opacity-50 cursor-pointer"
                                >
                                    {isSubmittingReviseAccount ? 'Menyimpan...' : 'Simpan Revisi Akun'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </FinanceLayout>
    );
}
