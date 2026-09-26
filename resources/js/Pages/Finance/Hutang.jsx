import React, { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';
import FinanceLayout from './Layouts/FinanceLayout';
import PbfCombobox from './Components/PbfCombobox';
import Drawer from './Components/Drawer';
import FloatingActionBar from './Components/FloatingActionBar';
import { formatRupiah, formatNumberOnly } from './Components/Utils';
import {
    CreditCard, Search, Filter, Check, X, Building2,
    Calendar, CheckCircle2, AlertCircle, Eye, ChevronLeft, ChevronRight,
    ArrowLeft, Printer, Share2, MoreVertical, ChevronDown, History, Landmark, Maximize2, Download, FileSpreadsheet,
    AlertTriangle, Clock, ArrowUp, ArrowDown, ArrowUpDown
} from 'lucide-react';

// Helper fungsi menghitung status jatuh tempo secara presisi
const computeDueInfo = (item) => {
    if (!item) return { isLunas: false, isOverdue: false, daysOverdue: 0, daysRemaining: 0, hasDate: false };
    const isLunas = item.status === 'Lunas' || (Number(item.sisa) || 0) <= 0.005;
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
    } catch (e) {}

    return { isLunas: false, isOverdue: false, daysOverdue: 0, daysRemaining: 0, hasDate: false };
};

export default function Hutang({ hutangDagang = [], creditors = [], kasBankAccounts = [], stats = {} }) {
    // State Filter & Pagination
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('ALL');
    const [filterPbf, setFilterPbf] = useState('');
    const [currentPage, setCurrentPage] = useState(1);
    const itemsPerPage = 10;

    // State Sorting & Filter Jatuh Tempo
    const [sortOrder, setSortOrder] = useState('desc'); // 'desc' | 'asc'
    const [dueFilter, setDueFilter] = useState('all'); // 'all' | 'overdue' | 'due_soon' | 'due_nearest' | 'due_furthest'
    const [isDueDropdownOpen, setIsDueDropdownOpen] = useState(false);

    // Selection untuk Pelunasan Massal (Maks 10)
    const [selectedHutangIds, setSelectedHutangIds] = useState([]);
    const [selectionWarning, setSelectionWarning] = useState('');

    // State Halaman Detil Penuh (seperti screenshot) & Quick Preview Drawer
    const [detailViewHutang, setDetailViewHutang] = useState(null);
    const [drawerHutang, setDrawerHutang] = useState(null);
    const [isShippingOpen, setIsShippingOpen] = useState(true);

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
    });
    const [isSubmittingPayment, setIsSubmittingPayment] = useState(false);

    // State Modal Pelunasan Massal
    const [isBulkModalOpen, setIsBulkModalOpen] = useState(false);
    const [bulkForm, setBulkForm] = useState({
        account_id: '',
        payment_date: new Date().toISOString().split('T')[0],
        reference_number: '',
        notes: '',
    });
    const [isSubmittingBulk, setIsSubmittingBulk] = useState(false);

    // Statistik Jatuh Tempo
    const dueStats = useMemo(() => {
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
    }, [hutangDagang]);

    // Filter & Sorting data
    const filteredHutang = useMemo(() => {
        let result = hutangDagang.filter((item) => {
            const matchSearch =
                (item.nomor && item.nomor.toLowerCase().includes(searchTerm.toLowerCase())) ||
                (item.vendor && item.vendor.toLowerCase().includes(searchTerm.toLowerCase())) ||
                (item.referensi && item.referensi.toLowerCase().includes(searchTerm.toLowerCase()));

            let matchStatus = true;
            if (statusFilter === 'LUNAS') {
                matchStatus = item.status === 'Lunas' || item.sisa <= 0.005;
            } else if (statusFilter === 'BELUM_LUNAS') {
                matchStatus = item.status !== 'Lunas' && item.sisa > 0.005;
            }

            const matchPbf = filterPbf
                ? (item.vendor && item.vendor.toLowerCase().includes(filterPbf.toLowerCase()))
                : true;

            const dueInfo = computeDueInfo(item);
            let matchDue = true;
            if (dueFilter === 'overdue') {
                matchDue = dueInfo.isOverdue && !dueInfo.isLunas;
            } else if (dueFilter === 'due_soon') {
                matchDue = !dueInfo.isOverdue && dueInfo.daysRemaining <= 7 && !dueInfo.isLunas && dueInfo.hasDate;
            }

            return matchSearch && matchStatus && matchPbf && matchDue;
        });

        // Sorting berdasarkan pilihan pengguna
        return result.sort((a, b) => {
            if (dueFilter === 'due_nearest') {
                const dateA = a.raw_jatuh_tempo ? new Date(a.raw_jatuh_tempo).getTime() : 9999999999999;
                const dateB = b.raw_jatuh_tempo ? new Date(b.raw_jatuh_tempo).getTime() : 9999999999999;
                return dateA - dateB;
            }
            if (dueFilter === 'due_furthest') {
                const dateA = a.raw_jatuh_tempo ? new Date(a.raw_jatuh_tempo).getTime() : 0;
                const dateB = b.raw_jatuh_tempo ? new Date(b.raw_jatuh_tempo).getTime() : 0;
                return dateB - dateA;
            }

            // Default: Urutkan Waktu Faktur/Penerimaan (ASC atau DESC)
            const timeA = a.raw_tanggal ? new Date(a.raw_tanggal).getTime() : (Number(a.id) || 0);
            const timeB = b.raw_tanggal ? new Date(b.raw_tanggal).getTime() : (Number(b.id) || 0);
            return sortOrder === 'asc' ? timeA - timeB : timeB - timeA;
        });
    }, [hutangDagang, searchTerm, statusFilter, filterPbf, sortOrder, dueFilter]);

    const totalPages = Math.ceil(filteredHutang.length / itemsPerPage) || 1;
    const paginatedHutang = useMemo(() => {
        const start = (currentPage - 1) * itemsPerPage;
        return filteredHutang.slice(start, start + itemsPerPage);
    }, [filteredHutang, currentPage, itemsPerPage]);

    // Items terpilih untuk bulk payment
    const selectedHutangItems = useMemo(() => {
        return hutangDagang.filter((item) => selectedHutangIds.includes(item.id));
    }, [hutangDagang, selectedHutangIds]);

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

    // Handlers Checkbox
    const handleToggleHutang = (item) => {
        if (item.status === 'Lunas' || item.sisa <= 0.005) return;
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
            const eligible = paginatedHutang.filter((item) => item.status !== 'Lunas' && item.sisa > 0.005);
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
        });
        setIsPaymentModalOpen(true);
    };

    // Submit Pembayaran Satuan
    const handlePaymentSubmit = (e) => {
        e.preventDefault();
        if (!paymentForm.account_id) {
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
        router.post('/finance/payments', paymentForm, {
            preserveScroll: true,
            onSuccess: () => {
                setIsPaymentModalOpen(false);
                setIsSubmittingPayment(false);
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
        });
        setIsBulkModalOpen(true);
    };

    // Submit Pelunasan Massal
    const handleBulkSubmit = (e) => {
        e.preventDefault();
        if (!bulkForm.account_id) {
            alert('Pilih akun kas & bank terlebih dahulu.');
            return;
        }
        setIsSubmittingBulk(true);
        router.post('/finance/bulk-payments', {
            receiving_detail_ids: selectedHutangIds,
            account_id: bulkForm.account_id,
            payment_date: bulkForm.payment_date,
            reference_number: bulkForm.reference_number,
            notes: bulkForm.notes,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsBulkModalOpen(false);
                setIsSubmittingBulk(false);
                setSelectedHutangIds([]);
            },
            onError: (errs) => {
                setIsSubmittingBulk(false);
                alert(Object.values(errs)[0] || 'Gagal memproses pelunasan massal.');
            }
        });
    };

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
                            <span
                                className={`inline-block px-3 py-1 text-xs font-semibold rounded-full ${
                                    detailViewHutang.status === 'Lunas'
                                        ? 'bg-emerald-50 text-emerald-600 border border-emerald-100'
                                        : detailViewHutang.status === 'Dibayar Sebagian'
                                        ? 'bg-amber-50 text-amber-600 border border-amber-100'
                                        : 'bg-red-50 text-red-600 border border-red-100'
                                }`}
                            >
                                {detailViewHutang.status}
                            </span>

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
                                        <th className="py-2.5 font-normal text-center">Discount</th>
                                        <th className="py-2.5 font-normal text-right">Harga</th>
                                        <th className="py-2.5 font-normal text-center">Pajak</th>
                                        <th className="py-2.5 font-normal text-right">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 text-slate-700">
                                    {detailViewHutang.items && detailViewHutang.items.length > 0 ? (
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
                                                <td className="py-3 text-center">{it.diskon || '0%'}</td>
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
                                    <span>Total</span>
                                    <span>{formatNumberOnly(detailViewHutang.total)}</span>
                                </div>

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
                            Dari total {hutangDagang.length} faktur kredit yang tercatat
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
                                    placeholder="Cari faktur, PBF, atau kode NT..."
                                    value={searchTerm}
                                    onChange={(e) => { setSearchTerm(e.target.value); setCurrentPage(1); }}
                                    className="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:bg-white focus:outline-none"
                                />
                            </div>

                            {/* PBF Filter */}
                            <PbfCombobox
                                pbfs={creditors}
                                selectedPbf={filterPbf}
                                onSelectPbf={(val) => { setFilterPbf(val); setCurrentPage(1); }}
                                placeholder="Filter PBF (Kreditur)..."
                            />

                            {/* Status Filter */}
                            <div className="flex items-center bg-slate-100 p-1 rounded-xl text-xs">
                                <button
                                    onClick={() => { setStatusFilter('ALL'); setCurrentPage(1); }}
                                    className={`px-3 py-1.5 rounded-lg font-semibold transition ${statusFilter === 'ALL' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'}`}
                                >
                                    Semua
                                </button>
                                <button
                                    onClick={() => { setStatusFilter('BELUM_LUNAS'); setCurrentPage(1); }}
                                    className={`px-3 py-1.5 rounded-lg font-semibold transition ${statusFilter === 'BELUM_LUNAS' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'}`}
                                >
                                    Belum Lunas
                                </button>
                                <button
                                    onClick={() => { setStatusFilter('LUNAS'); setCurrentPage(1); }}
                                    className={`px-3 py-1.5 rounded-lg font-semibold transition ${statusFilter === 'LUNAS' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'}`}
                                >
                                    Lunas
                                </button>
                            </div>

                            {/* Filter 1: Waktu Pembuatan ASC / DESC */}
                            <button
                                type="button"
                                onClick={() => {
                                    setSortOrder((prev) => (prev === 'asc' ? 'desc' : 'asc'));
                                    setCurrentPage(1);
                                }}
                                className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border transition cursor-pointer ${
                                    sortOrder === 'asc'
                                        ? 'bg-amber-50 border-amber-300 text-amber-800 font-bold shadow-2xs'
                                        : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
                                }`}
                                title="Klik untuk mengubah urutan waktu faktur"
                            >
                                {sortOrder === 'asc' ? (
                                    <ArrowUp className="w-3.5 h-3.5 text-amber-600" />
                                ) : (
                                    <ArrowDown className="w-3.5 h-3.5 text-slate-500" />
                                )}
                                <span>Waktu: {sortOrder === 'asc' ? 'Terlama (ASC)' : 'Terbaru (DESC)'}</span>
                            </button>

                            {/* Filter 2: Berdasarkan Jatuh Tempo */}
                            <div className="relative">
                                <button
                                    type="button"
                                    onClick={() => setIsDueDropdownOpen(!isDueDropdownOpen)}
                                    className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border transition cursor-pointer ${
                                        dueFilter !== 'all'
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
                                            onClick={() => { setDueFilter('all'); setIsDueDropdownOpen(false); setCurrentPage(1); }}
                                            className={`w-full text-left px-3.5 py-2 hover:bg-slate-50 flex items-center justify-between cursor-pointer ${dueFilter === 'all' ? 'font-bold text-amber-700 bg-amber-50/50' : 'text-slate-700'}`}
                                        >
                                            <span>Semua Jatuh Tempo</span>
                                            {dueFilter === 'all' && <Check className="w-3.5 h-3.5 text-amber-600" />}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => { setDueFilter('overdue'); setIsDueDropdownOpen(false); setCurrentPage(1); }}
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
                                            onClick={() => { setDueFilter('due_soon'); setIsDueDropdownOpen(false); setCurrentPage(1); }}
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
                                            onClick={() => { setDueFilter('due_nearest'); setIsDueDropdownOpen(false); setCurrentPage(1); }}
                                            className={`w-full text-left px-3.5 py-2 hover:bg-slate-50 flex items-center justify-between cursor-pointer ${dueFilter === 'due_nearest' ? 'font-bold text-blue-700 bg-blue-50/50' : 'text-slate-700'}`}
                                        >
                                            <span>Tempo Terdekat (Urutkan)</span>
                                            {dueFilter === 'due_nearest' && <Check className="w-3.5 h-3.5 text-blue-600" />}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => { setDueFilter('due_furthest'); setIsDueDropdownOpen(false); setCurrentPage(1); }}
                                            className={`w-full text-left px-3.5 py-2 hover:bg-slate-50 flex items-center justify-between cursor-pointer ${dueFilter === 'due_furthest' ? 'font-bold text-blue-700 bg-blue-50/50' : 'text-slate-700'}`}
                                        >
                                            <span>Tempo Terjauh (Urutkan)</span>
                                            {dueFilter === 'due_furthest' && <Check className="w-3.5 h-3.5 text-blue-600" />}
                                        </button>
                                    </div>
                                )}
                            </div>

                            {/* Tombol Export Excel */}
                            <a
                                href={`/finance/export/hutang?search=${encodeURIComponent(searchTerm)}&status=${encodeURIComponent(statusFilter === 'ALL' ? '' : statusFilter)}&pbf=${encodeURIComponent(filterPbf)}&sort_order=${encodeURIComponent(sortOrder)}&due_mode=${encodeURIComponent(dueFilter)}`}
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

                    {selectionWarning && (
                        <div className="text-xs text-amber-700 bg-amber-50 px-3 py-2 rounded-xl flex items-center gap-2 border border-amber-200">
                            <AlertCircle className="w-4 h-4 text-amber-600 shrink-0" />
                            <span>{selectionWarning}</span>
                        </div>
                    )}
                </div>

                {/* Tabel Hutang Dagang */}
                <div className="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr className="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider text-[11px]">
                                    <th className="px-4 py-3.5 w-10">
                                        <input
                                            type="checkbox"
                                            checked={
                                                paginatedHutang.filter(i => i.status !== 'Lunas' && i.sisa > 0.005).length > 0 &&
                                                paginatedHutang
                                                    .filter(i => i.status !== 'Lunas' && i.sisa > 0.005)
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
                                    <th className="px-4 py-3.5 text-right">Terbayar</th>
                                    <th className="px-4 py-3.5 text-right">Sisa Tagihan</th>
                                    <th className="px-4 py-3.5 text-center">Status</th>
                                    <th className="px-4 py-3.5 text-center w-28">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {paginatedHutang.length === 0 ? (
                                    <tr>
                                        <td colSpan={10} className="text-center py-12 text-slate-400">
                                            Tidak ada data hutang dagang yang sesuai dengan filter.
                                        </td>
                                    </tr>
                                ) : (
                                    paginatedHutang.map((item) => (
                                        <tr
                                            key={item.id}
                                            onClick={() => setDrawerHutang(item)}
                                            className="hover:bg-blue-50/40 cursor-pointer transition group"
                                        >
                                            <td className="px-4 py-3.5" onClick={(e) => e.stopPropagation()}>
                                                {item.status === 'Lunas' || item.sisa <= 0.005 ? (
                                                    <span title="Faktur sudah lunas" className="inline-block p-0.5 text-emerald-500">
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
                                                <button
                                                    type="button"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        setDetailViewHutang(item);
                                                    }}
                                                    className="font-bold text-slate-900 hover:text-blue-600 text-left transition group-hover:text-blue-600 cursor-pointer"
                                                    title="Buka Halaman Detil Lengkap"
                                                >
                                                    {item.nomor}
                                                </button>
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
                                            <td className="px-4 py-3.5 text-right text-emerald-600 font-medium">
                                                {formatNumberOnly(item.terbayar)}
                                            </td>
                                            <td className="px-4 py-3.5 text-right font-bold text-red-600">
                                                {formatNumberOnly(item.sisa)}
                                            </td>
                                            <td className="px-4 py-3.5 text-center">
                                                <span className={`inline-block px-2.5 py-1 rounded-full text-[11px] font-bold ${
                                                    item.status === 'Lunas'
                                                        ? 'bg-emerald-50 text-emerald-700'
                                                        : item.status === 'Dibayar Sebagian'
                                                        ? 'bg-amber-50 text-amber-700'
                                                        : 'bg-red-50 text-red-700'
                                                }`}>
                                                    {item.status}
                                                </span>
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
                                                        onClick={() => setDetailViewHutang(item)}
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
                    {totalPages > 1 && (
                        <div className="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                            <div>
                                Menampilkan {(currentPage - 1) * itemsPerPage + 1} - {Math.min(currentPage * itemsPerPage, filteredHutang.length)} dari {filteredHutang.length} faktur
                            </div>
                            <div className="flex items-center gap-1">
                                <button
                                    onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                                    disabled={currentPage === 1}
                                    className="p-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50"
                                >
                                    <ChevronLeft className="w-4 h-4" />
                                </button>
                                <span className="px-3 py-1 font-semibold text-slate-800">
                                    {currentPage} / {totalPages}
                                </span>
                                <button
                                    onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                                    disabled={currentPage === totalPages}
                                    className="p-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50"
                                >
                                    <ChevronRight className="w-4 h-4" />
                                </button>
                            </div>
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
                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Bayar Dari Akun (Kas & Bank) <span className="text-red-500">*</span>
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
                                            {acc.name} ({acc.code}) {acc.account_number ? `- ${acc.account_number}` : ''} - Saldo: {formatRupiah(acc.balance)}
                                        </option>
                                    ))}
                                </select>
                            </div>

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
                                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50"
                                >
                                    {isSubmittingPayment ? 'Memproses...' : 'Simpan Pembayaran'}
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

                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Bayar Dari Akun (Kas & Bank) <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={bulkForm.account_id}
                                    onChange={(e) => setBulkForm({ ...bulkForm, account_id: e.target.value })}
                                    required
                                    className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                >
                                    <option value="">-- Pilih Akun Kas & Bank --</option>
                                    {kasBankAccounts.map((acc) => (
                                        <option key={acc.id} value={acc.id}>
                                            {acc.name} ({acc.code}) - Saldo: {formatRupiah(acc.balance)}
                                        </option>
                                    ))}
                                </select>
                            </div>

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
                                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50"
                                >
                                    {isSubmittingBulk ? 'Memproses...' : 'Konfirmasi Pelunasan Massal'}
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
                    setDetailViewHutang(item);
                }}
                title="Rincian Faktur Hutang"
                subtitle={(item) => `${item.nomor} • ${item.referensi}`}
                renderFooter={(item) => (
                    <div className="flex items-center gap-2 w-full">
                        <button
                            type="button"
                            onClick={() => {
                                const target = item;
                                setDrawerHutang(null);
                                setDetailViewHutang(target);
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
                                        {(item.items || []).map((it, idx) => (
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
                                <span>Total Tagihan:</span>
                                <span>{formatRupiah(item.total)}</span>
                            </div>
                            <div className="flex justify-between text-emerald-600 font-semibold">
                                <span>Sudah Terbayar:</span>
                                <span>{formatRupiah(item.terbayar)}</span>
                            </div>
                            <div className="flex justify-between text-red-600 font-bold text-sm pt-1">
                                <span>Sisa Tagihan:</span>
                                <span>{formatRupiah(item.sisa)}</span>
                            </div>
                        </div>

                        {/* Riwayat Pembayaran */}
                        <div>
                            <h4 className="font-bold text-slate-900 mb-3">Riwayat Pembayaran</h4>
                            {(item.payments || []).length === 0 ? (
                                <p className="text-slate-400 italic">Belum ada riwayat pembayaran untuk faktur ini.</p>
                            ) : (
                                <div className="space-y-2">
                                    {item.payments.map((p) => (
                                        <div key={p.id} className="p-3 bg-slate-50 border border-slate-100 rounded-xl space-y-1">
                                            <div className="flex justify-between items-center">
                                                <span className="font-bold text-slate-900">{formatRupiah(p.nominal)}</span>
                                                <span className="text-[11px] text-slate-400">{p.tanggal}</span>
                                            </div>
                                            <div className="text-[11px] text-slate-500">
                                                Via: <strong>{p.akunNama}</strong> ({p.akunKode}) • Ref: {p.noReferensi}
                                            </div>
                                            {p.catatan && p.catatan !== '-' && (
                                                <div className="text-[10px] text-slate-400">{p.catatan}</div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </>
                )}
            </Drawer>

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
                onClear={() => setSelectedHutangIds([])}
                isSubmitting={isSubmittingBulk}
                themeColor="blue"
            />
        </FinanceLayout>
    );
}
