import React, { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';
import FinanceLayout from './Layouts/FinanceLayout';
import PbfCombobox from './Components/PbfCombobox';
import Drawer from './Components/Drawer';
import { formatRupiah, formatNumberOnly } from './Components/Utils';
import {
    CreditCard, Search, Filter, Check, X, Building2,
    Calendar, CheckCircle2, AlertCircle, Eye, ChevronLeft, ChevronRight
} from 'lucide-react';

export default function Hutang({ hutangDagang = [], creditors = [], kasBankAccounts = [], stats = {} }) {
    // State Filter & Pagination
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('ALL');
    const [filterPbf, setFilterPbf] = useState('');
    const [currentPage, setCurrentPage] = useState(1);
    const itemsPerPage = 10;

    // Selection untuk Pelunasan Massal (Maks 10)
    const [selectedHutangIds, setSelectedHutangIds] = useState([]);
    const [selectionWarning, setSelectionWarning] = useState('');

    // State Drawer Detail Faktur
    const [selectedHutang, setSelectedHutang] = useState(null);

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

    // Filter data
    const filteredHutang = useMemo(() => {
        return hutangDagang.filter((item) => {
            const matchSearch =
                (item.nomor && item.nomor.toLowerCase().includes(searchTerm.toLowerCase())) ||
                (item.vendor && item.vendor.toLowerCase().includes(searchTerm.toLowerCase())) ||
                (item.referensi && item.referensi.toLowerCase().includes(searchTerm.toLowerCase()));

            let matchStatus = true;
            if (statusFilter === 'LUNAS') {
                matchStatus = item.status === 'Lunas';
            } else if (statusFilter === 'BELUM_LUNAS') {
                matchStatus = item.status === 'Belum Dibayar' || item.status === 'Dibayar Sebagian';
            }

            const matchPbf = filterPbf
                ? (item.vendor && item.vendor.toLowerCase().includes(filterPbf.toLowerCase()))
                : true;

            return matchSearch && matchStatus && matchPbf;
        });
    }, [hutangDagang, searchTerm, statusFilter, filterPbf]);

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
        return selectedHutangItems.reduce((acc, curr) => acc + (curr.sisa || 0), 0);
    }, [selectedHutangItems]);

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
                if (selectedHutang && selectedHutang.id === paymentForm.receiving_detail_id) {
                    setSelectedHutang(null);
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
            title="Hutang Dagang"
            subtitle="Tagihan faktur pembelian kredit kepada PBF / Distributor obat"
            stats={stats}
        >
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

                    <div className="flex items-center gap-3">
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
                                        <td colSpan={9} className="text-center py-12 text-slate-400">
                                            Tidak ada data hutang dagang yang sesuai dengan filter.
                                        </td>
                                    </tr>
                                ) : (
                                    paginatedHutang.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-50/80 transition">
                                            <td className="px-4 py-3.5">
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
                                                <div className="font-bold text-slate-900">{item.nomor}</div>
                                                <div className="text-[11px] text-slate-400 mt-0.5">
                                                    {item.referensi} • Tgl: {item.tanggal}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="font-semibold text-slate-800">{item.vendor}</div>
                                                <div className="text-[10px] text-slate-400">Tempo: {item.jatuhTempo}</div>
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
                                            <td className="px-4 py-3.5 text-center">
                                                <div className="flex items-center justify-center gap-1.5">
                                                    {item.sisa > 0.005 ? (
                                                        <button
                                                            onClick={() => openPaymentModal(item)}
                                                            className="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold transition"
                                                        >
                                                            Bayar
                                                        </button>
                                                    ) : null}
                                                    <button
                                                        onClick={() => setSelectedHutang(item)}
                                                        className="px-2.5 py-1 border border-slate-200 text-slate-600 hover:bg-slate-100 rounded-lg text-xs font-semibold transition"
                                                        title="Lihat Rincian Faktur"
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

            {/* Drawer Detail Faktur */}
            <Drawer
                item={selectedHutang}
                onClose={() => setSelectedHutang(null)}
                title="Rincian Faktur Hutang"
                subtitle={(item) => `${item.nomor} • ${item.referensi}`}
                renderFooter={(item) => (
                    item.sisa > 0.005 ? (
                        <button
                            type="button"
                            onClick={() => {
                                const target = item;
                                setSelectedHutang(null);
                                openPaymentModal(target);
                            }}
                            className="w-full py-2.5 bg-blue-600 hover:bg-blue-700 active:scale-[0.99] text-white rounded-xl text-xs font-bold transition shadow-xs text-center cursor-pointer"
                        >
                            Bayar Tagihan Ini ({formatRupiah(item.sisa)})
                        </button>
                    ) : null
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
                            <div className="flex justify-between">
                                <span className="text-slate-500">Jatuh Tempo:</span>
                                <span className="font-medium text-red-600">{item.jatuhTempo}</span>
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
        </FinanceLayout>
    );
}
