import React, { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';
import FinanceLayout from './Layouts/FinanceLayout';
import Drawer from './Components/Drawer';
import FloatingActionBar from './Components/FloatingActionBar';
import DebtorCombobox from './Components/DebtorCombobox';
import BuktiPelunasanModal from './Components/BuktiPelunasanModal';
import { formatRupiah, formatNumberOnly } from './Components/Utils';
import {
    ShoppingCart, Search, Check, X, Calendar, AlertCircle, ChevronLeft, ChevronRight, User,
    ArrowLeft, Printer, MoreVertical, ChevronDown, History, Landmark, Maximize2, CreditCard,
    Download, ArrowUp, ArrowDown, Clock, AlertTriangle, FileText, CheckCircle2, FileSpreadsheet
} from 'lucide-react';

export default function Piutang({ piutangPenjualan = [], debtors = [], kasBankAccounts = [], stats = {} }) {
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('ALL');
    const [debtorFilter, setDebtorFilter] = useState('');
    const [sortCreatedAt, setSortCreatedAt] = useState('asc'); // Filter 1: Waktu Pembuatan ASC/DESC
    const [dueFilter, setDueFilter] = useState('all'); // Filter 2: Berdasarkan Jatuh Tempo
    const [isDueDropdownOpen, setIsDueDropdownOpen] = useState(false);
    const [currentPage, setCurrentPage] = useState(1);
    const itemsPerPage = 10;

    // Selection untuk Pelunasan Massal (Maks 10)
    const [selectedPiutangIds, setSelectedPiutangIds] = useState([]);
    const [selectionWarning, setSelectionWarning] = useState('');

    // State Halaman Detil Penuh & Quick Preview Drawer
    const [detailViewPiutang, setDetailViewPiutang] = useState(null);
    const [drawerPiutang, setDrawerPiutang] = useState(null);
    const [isShippingOpen, setIsShippingOpen] = useState(true);
    const [isBuktiModalOpen, setIsBuktiModalOpen] = useState(false);

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
    });
    const [isSubmittingPayment, setIsSubmittingPayment] = useState(false);

    // State Modal Pelunasan Massal Piutang
    const [isBulkModalOpen, setIsBulkModalOpen] = useState(false);
    const [bulkForm, setBulkForm] = useState({
        account_id: '',
        payment_date: new Date().toISOString().split('T')[0],
        reference_number: '',
        notes: '',
    });
    const [isSubmittingBulk, setIsSubmittingBulk] = useState(false);

    // Filter & Sorting Data Piutang
    const filteredPiutang = useMemo(() => {
        let result = piutangPenjualan.filter((item) => {
            const matchSearch =
                (item.nomor && item.nomor.toLowerCase().includes(searchTerm.toLowerCase())) ||
                (item.debtor && item.debtor.toLowerCase().includes(searchTerm.toLowerCase())) ||
                (item.pasien && item.pasien.toLowerCase().includes(searchTerm.toLowerCase())) ||
                (item.dokter && item.dokter.toLowerCase().includes(searchTerm.toLowerCase()));

            let matchStatus = true;
            if (statusFilter === 'LUNAS') {
                matchStatus = item.status === 'Lunas' || item.sisa <= 0.005;
            } else if (statusFilter === 'BELUM_LUNAS') {
                matchStatus = item.status !== 'Lunas' && item.sisa > 0.005;
            }

            const matchDebtor = debtorFilter
                ? String(item.debtor_id) === String(debtorFilter)
                : true;

            let matchDue = true;
            if (dueFilter === 'overdue') {
                matchDue = item.is_overdue && item.status !== 'Lunas' && item.sisa > 0.005;
            } else if (dueFilter === 'due_soon') {
                matchDue = !item.is_overdue && item.days_remaining <= 7 && item.status !== 'Lunas' && item.sisa > 0.005;
            }

            return matchSearch && matchStatus && matchDebtor && matchDue;
        });

        // Sorting berdasarkan pilihan pengguna
        return result.sort((a, b) => {
            if (dueFilter === 'due_nearest') {
                const dateA = a.raw_jatuh_tempo ? new Date(a.raw_jatuh_tempo).getTime() : 0;
                const dateB = b.raw_jatuh_tempo ? new Date(b.raw_jatuh_tempo).getTime() : 0;
                return dateA - dateB;
            }
            if (dueFilter === 'due_furthest') {
                const dateA = a.raw_jatuh_tempo ? new Date(a.raw_jatuh_tempo).getTime() : 0;
                const dateB = b.raw_jatuh_tempo ? new Date(b.raw_jatuh_tempo).getTime() : 0;
                return dateB - dateA;
            }

            // Default: Urutkan Waktu Pembuatan (ASC atau DESC)
            const timeA = a.raw_created_at ? new Date(a.raw_created_at).getTime() : (Number(a.id) || 0);
            const timeB = b.raw_created_at ? new Date(b.raw_created_at).getTime() : (Number(b.id) || 0);
            return sortCreatedAt === 'asc' ? timeA - timeB : timeB - timeA;
        });
    }, [piutangPenjualan, searchTerm, statusFilter, debtorFilter, sortCreatedAt, dueFilter]);

    const totalPages = Math.ceil(filteredPiutang.length / itemsPerPage) || 1;
    const paginatedPiutang = useMemo(() => {
        const start = (currentPage - 1) * itemsPerPage;
        return filteredPiutang.slice(start, start + itemsPerPage);
    }, [filteredPiutang, currentPage, itemsPerPage]);

    const selectedPiutangItems = useMemo(() => {
        return piutangPenjualan.filter((item) => selectedPiutangIds.includes(item.id));
    }, [piutangPenjualan, selectedPiutangIds]);

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
        if (item.status === 'Lunas' || item.sisa <= 0.005) return;
        if (selectedPiutangIds.includes(item.id)) {
            setSelectedPiutangIds((prev) => prev.filter((id) => id !== item.id));
        } else {
            if (selectedPiutangIds.length >= 10) {
                setSelectionWarning('Maksimal 10 transaksi yang dapat dipilih sekaligus.');
                setTimeout(() => setSelectionWarning(''), 4000);
                return;
            }
            setSelectedPiutangIds((prev) => [...prev, item.id]);
        }
    };

    const handleSelectAllPiutang = (e) => {
        if (e.target.checked) {
            const eligible = paginatedPiutang.filter((item) => item.status !== 'Lunas' && item.sisa > 0.005);
            const remainingSlots = 10 - selectedPiutangIds.length;
            if (remainingSlots <= 0) {
                setSelectionWarning('Maksimal 10 transaksi yang dapat dipilih.');
                setTimeout(() => setSelectionWarning(''), 4000);
                return;
            }
            const toAdd = eligible
                .map((item) => item.id)
                .filter((id) => !selectedPiutangIds.includes(id))
                .slice(0, remainingSlots);

            if (toAdd.length < eligible.length) {
                setSelectionWarning('Hanya 10 transaksi pertama yang dipilih (maks. 10).');
                setTimeout(() => setSelectionWarning(''), 4000);
            }
            setSelectedPiutangIds((prev) => [...prev, ...toAdd]);
        } else {
            const currentPageIds = paginatedPiutang.map((item) => item.id);
            setSelectedPiutangIds((prev) => prev.filter((id) => !currentPageIds.includes(id)));
        }
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
        });
        setIsPaymentModalOpen(true);
    };

    // Submit Pembayaran Piutang
    const handlePaymentSubmit = (e) => {
        e.preventDefault();
        if (!paymentForm.account_id) {
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
            account_id: paymentForm.account_id,
            payment_date: paymentForm.payment_date,
            amount: paymentForm.amount,
            reference_number: paymentForm.reference_number,
            notes: paymentForm.notes,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsPaymentModalOpen(false);
                setIsSubmittingPayment(false);
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
        });
        setIsBulkModalOpen(true);
    };

    // Submit Pelunasan Massal Piutang
    const handleBulkSubmit = (e) => {
        e.preventDefault();
        if (!bulkForm.account_id) {
            alert('Silakan pilih akun Kas & Bank penerima.');
            return;
        }
        setIsSubmittingBulk(true);
        router.post('/finance/bulk-piutang-payments', {
            transaction_ids: selectedPiutangIds,
            account_id: bulkForm.account_id,
            payment_date: bulkForm.payment_date,
            reference_number: bulkForm.reference_number,
            notes: bulkForm.notes,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsBulkModalOpen(false);
                setIsSubmittingBulk(false);
                setSelectedPiutangIds([]);
            },
            onError: (errs) => {
                setIsSubmittingBulk(false);
                alert(Object.values(errs)[0] || 'Gagal memproses pelunasan massal piutang.');
            }
        });
    };

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
                            <span
                                className={`inline-block px-3 py-1 text-xs font-semibold rounded-full ${detailViewPiutang.status === 'Lunas' || detailViewPiutang.sisa <= 0.005
                                        ? 'bg-emerald-50 text-emerald-600 border border-emerald-100'
                                        : detailViewPiutang.status === 'Dibayar Sebagian'
                                            ? 'bg-amber-50 text-amber-600 border border-amber-100'
                                            : 'bg-red-50 text-red-600 border border-red-100'
                                    }`}
                            >
                                {detailViewPiutang.status}
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
                                    {detailViewPiutang.items && detailViewPiutang.items.length > 0 ? (
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
                                    <span>Total Tagihan</span>
                                    <span>{formatNumberOnly(detailViewPiutang.total)}</span>
                                </div>

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
                /* SUB-VIEW B: TABEL DAFTAR PIUTANG PENJUALAN */
                <div className="space-y-6">
                    {/* Banner Summary */}
                    <div className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Total Sisa Piutang Usaha
                            </div>
                            <div className="text-2xl font-black text-amber-700 mt-1">
                                {formatRupiah(stats.totalPiutangSisa || 0)}
                            </div>
                            <p className="text-xs text-slate-400 mt-0.5">
                                Dari total {piutangPenjualan.length} transaksi piutang penjualan
                            </p>
                        </div>

                        <div className="flex items-center gap-3">
                            <span className="px-3 py-1.5 rounded-xl bg-amber-50 text-amber-700 text-xs font-bold border border-amber-100">
                                {stats.countPiutangBelumBayar || 0} Belum Lunas
                            </span>
                            <span className="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-100">
                                {stats.countPiutangLunas || 0} Lunas
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
                                        placeholder="Cari transaksi, debitur, pasien..."
                                        value={searchTerm}
                                        onChange={(e) => { setSearchTerm(e.target.value); setCurrentPage(1); }}
                                        className="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:bg-white focus:outline-none"
                                    />
                                </div>

                                {/* Debtor Filter Combobox */}
                                <DebtorCombobox
                                    debtors={debtors}
                                    selectedDebtorId={debtorFilter}
                                    onSelectDebtor={(val) => { setDebtorFilter(val); setCurrentPage(1); }}
                                    placeholder="Filter Debitur (Instansi)..."
                                />

                                {/* Status Filter Segmented */}
                                <div className="flex items-center bg-slate-100 p-1 rounded-xl text-xs">
                                    <button
                                        type="button"
                                        onClick={() => { setStatusFilter('ALL'); setCurrentPage(1); }}
                                        className={`px-3 py-1.5 rounded-lg font-semibold transition cursor-pointer ${statusFilter === 'ALL' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'}`}
                                    >
                                        Semua
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => { setStatusFilter('BELUM_LUNAS'); setCurrentPage(1); }}
                                        className={`px-3 py-1.5 rounded-lg font-semibold transition cursor-pointer ${statusFilter === 'BELUM_LUNAS' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'}`}
                                    >
                                        Belum Lunas
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => { setStatusFilter('LUNAS'); setCurrentPage(1); }}
                                        className={`px-3 py-1.5 rounded-lg font-semibold transition cursor-pointer ${statusFilter === 'LUNAS' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'}`}
                                    >
                                        Lunas
                                    </button>
                                </div>

                                {/* Filter 1: Waktu Pembuatan ASC / DESC */}
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSortCreatedAt((prev) => (prev === 'asc' ? 'desc' : 'asc'));
                                        setCurrentPage(1);
                                    }}
                                    className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border transition cursor-pointer ${
                                        sortCreatedAt === 'asc'
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
                                                className={`w-full text-left px-3.5 py-2 hover:bg-slate-50 flex items-center justify-between cursor-pointer ${dueFilter === 'due_nearest' ? 'font-bold text-amber-700 bg-amber-50/50' : 'text-slate-700'}`}
                                            >
                                                <span>Urutkan Tempo Terdekat</span>
                                                {dueFilter === 'due_nearest' && <Check className="w-3.5 h-3.5 text-amber-600" />}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => { setDueFilter('due_furthest'); setIsDueDropdownOpen(false); setCurrentPage(1); }}
                                                className={`w-full text-left px-3.5 py-2 hover:bg-slate-50 flex items-center justify-between cursor-pointer ${dueFilter === 'due_furthest' ? 'font-bold text-amber-700 bg-amber-50/50' : 'text-slate-700'}`}
                                            >
                                                <span>Urutkan Tempo Terjauh</span>
                                                {dueFilter === 'due_furthest' && <Check className="w-3.5 h-3.5 text-amber-600" />}
                                            </button>
                                        </div>
                                    )}
                                </div>

                                {/* Tombol Export Excel */}
                                <a
                                    href={`/finance/export/piutang?search=${encodeURIComponent(searchTerm)}&status=${encodeURIComponent(statusFilter === 'ALL' ? '' : statusFilter)}&debtor_id=${encodeURIComponent(debtorFilter)}&sort_by=${dueFilter.startsWith('due_') ? 'due_date' : 'created_at'}&sort_order=${sortCreatedAt}&due_mode=${encodeURIComponent(dueFilter)}`}
                                    className="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-50 hover:bg-emerald-100/80 text-emerald-800 border border-emerald-200 rounded-xl text-xs font-semibold transition shadow-2xs cursor-pointer"
                                    title="Export data piutang ke Excel (.xlsx) dengan format rapi dan estetik"
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

                        {selectionWarning && (
                            <div className="text-xs text-amber-700 bg-amber-50 px-3 py-2 rounded-xl flex items-center gap-2 border border-amber-200">
                                <AlertCircle className="w-4 h-4 text-amber-600 shrink-0" />
                                <span>{selectionWarning}</span>
                            </div>
                        )}
                    </div>

                    {/* Tabel Piutang Penjualan */}
                    <div className="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr className="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider text-[11px]">
                                        <th className="px-4 py-3.5 w-10">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    paginatedPiutang.filter(i => i.status !== 'Lunas' && i.sisa > 0.005).length > 0 &&
                                                    paginatedPiutang
                                                        .filter(i => i.status !== 'Lunas' && i.sisa > 0.005)
                                                        .every(i => selectedPiutangIds.includes(i.id))
                                                }
                                                onChange={handleSelectAllPiutang}
                                                className="rounded border-slate-300 text-amber-600 focus:ring-amber-500 cursor-pointer"
                                                title="Pilih semua di halaman ini"
                                            />
                                        </th>
                                        <th className="px-4 py-3.5">No. Transaksi & Tgl</th>
                                        <th className="px-4 py-3.5">Jatuh Tempo</th>
                                        <th className="px-4 py-3.5">Debitur / Pasien</th>
                                        <th className="px-4 py-3.5">Apotek</th>
                                        <th className="px-4 py-3.5 text-right">Total Tagihan</th>
                                        <th className="px-4 py-3.5 text-right">Terbayar</th>
                                        <th className="px-4 py-3.5 text-right">Sisa Piutang</th>
                                        <th className="px-4 py-3.5 text-center">Status</th>
                                        <th className="px-4 py-3.5 text-center w-28">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {paginatedPiutang.length === 0 ? (
                                        <tr>
                                            <td colSpan={10} className="text-center py-12 text-slate-400">
                                                Tidak ada data piutang yang sesuai dengan filter.
                                            </td>
                                        </tr>
                                    ) : (
                                        paginatedPiutang.map((item) => {
                                            const isLunas = item.status === 'Lunas' || item.sisa <= 0.005;
                                            return (
                                                <tr
                                                    key={item.id}
                                                    onClick={() => setDrawerPiutang(item)}
                                                    className="hover:bg-amber-50/40 cursor-pointer transition group"
                                                >
                                                    <td className="px-4 py-3.5" onClick={(e) => e.stopPropagation()}>
                                                        {isLunas ? (
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
                                                        <button
                                                            type="button"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                setDetailViewPiutang(item);
                                                            }}
                                                            className="font-bold text-amber-700 hover:text-amber-800 hover:underline transition text-left cursor-pointer"
                                                        >
                                                            {item.nomor}
                                                        </button>
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
                                                    <td className="px-4 py-3.5 text-right text-emerald-600 font-medium">
                                                        {formatNumberOnly(item.terbayar)}
                                                    </td>
                                                    <td className="px-4 py-3.5 text-right font-bold text-amber-700">
                                                        {formatNumberOnly(item.sisa)}
                                                    </td>
                                                    <td className="px-4 py-3.5 text-center">
                                                        <span className={`inline-block px-2.5 py-1 rounded-full text-[11px] font-bold ${isLunas
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
                                                                onClick={() => setDetailViewPiutang(item)}
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
                        {totalPages > 1 && (
                            <div className="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                                <div>
                                    Menampilkan {(currentPage - 1) * itemsPerPage + 1} - {Math.min(currentPage * itemsPerPage, filteredPiutang.length)} dari {filteredPiutang.length} transaksi
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

                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Terima Ke Akun (Kas & Bank) <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={paymentForm.account_id}
                                    onChange={(e) => setPaymentForm({ ...paymentForm, account_id: e.target.value })}
                                    required
                                    className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none"
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
                                    className="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50"
                                >
                                    {isSubmittingPayment ? 'Memproses...' : 'Konfirmasi Penerimaan'}
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

                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Terima Ke Akun (Kas & Bank) <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={bulkForm.account_id}
                                    onChange={(e) => setBulkForm({ ...bulkForm, account_id: e.target.value })}
                                    required
                                    className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none"
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
                                    className="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50"
                                >
                                    {isSubmittingBulk ? 'Memproses...' : 'Konfirmasi Pelunasan'}
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
                    setDetailViewPiutang(item);
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
                                setDrawerPiutang(null);
                                setDetailViewPiutang(item);
                            }}
                            className="w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition text-center cursor-pointer"
                        >
                            Buka Halaman Lengkap
                        </button>
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
                                    {item.status === 'Lunas' || item.sisa <= 0.005 ? (
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
                                        {(item.items || []).map((it, idx) => (
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
                                <span>Total Tagihan:</span>
                                <span>{formatRupiah(item.total)}</span>
                            </div>
                            <div className="flex justify-between text-emerald-600 font-semibold">
                                <span>Sudah Terbayar:</span>
                                <span>{formatRupiah(item.terbayar)}</span>
                            </div>
                            <div className="flex justify-between text-amber-700 font-bold text-sm pt-1 border-t border-slate-200">
                                <span>Sisa Piutang:</span>
                                <span>{formatRupiah(item.sisa)}</span>
                            </div>
                        </div>

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
                onClear={() => setSelectedPiutangIds([])}
                isSubmitting={isSubmittingBulk}
                themeColor="amber"
            />

            {/* Modal Cetak Bukti Pelunasan Resmi */}
            <BuktiPelunasanModal
                isOpen={isBuktiModalOpen}
                onClose={() => setIsBuktiModalOpen(false)}
                piutang={detailViewPiutang}
            />
        </FinanceLayout>
    );
}
