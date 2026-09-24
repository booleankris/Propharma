import React, { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';
import FinanceLayout from './Layouts/FinanceLayout';
import Drawer from './Components/Drawer';
import { formatRupiah, formatNumberOnly } from './Components/Utils';
import {
    ShoppingCart, Search, Check, X, Calendar, AlertCircle, ChevronLeft, ChevronRight, User
} from 'lucide-react';

export default function Piutang({ piutangPenjualan = [], debtors = [], kasBankAccounts = [], stats = {} }) {
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('ALL');
    const [debtorFilter, setDebtorFilter] = useState('');
    const [currentPage, setCurrentPage] = useState(1);
    const itemsPerPage = 10;

    // Selection untuk Pelunasan Massal (Maks 10)
    const [selectedPiutangIds, setSelectedPiutangIds] = useState([]);
    const [selectionWarning, setSelectionWarning] = useState('');

    // State Drawer Detail Transaksi
    const [selectedPiutang, setSelectedPiutang] = useState(null);

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

    // Filter
    const filteredPiutang = useMemo(() => {
        return piutangPenjualan.filter((item) => {
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

            return matchSearch && matchStatus && matchDebtor;
        });
    }, [piutangPenjualan, searchTerm, statusFilter, debtorFilter]);

    const totalPages = Math.ceil(filteredPiutang.length / itemsPerPage) || 1;
    const paginatedPiutang = useMemo(() => {
        const start = (currentPage - 1) * itemsPerPage;
        return filteredPiutang.slice(start, start + itemsPerPage);
    }, [filteredPiutang, currentPage, itemsPerPage]);

    const selectedPiutangItems = useMemo(() => {
        return piutangPenjualan.filter((item) => selectedPiutangIds.includes(item.id));
    }, [piutangPenjualan, selectedPiutangIds]);

    const totalSelectedPiutangSisa = useMemo(() => {
        return selectedPiutangItems.reduce((acc, curr) => acc + (curr.sisa || 0), 0);
    }, [selectedPiutangItems]);

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
                if (selectedPiutang && selectedPiutang.id === paymentForm.medicine_transaction_id) {
                    setSelectedPiutang(null);
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

                            {/* Filter Debitur Dropdown */}
                            <select
                                value={debtorFilter}
                                onChange={(e) => { setDebtorFilter(e.target.value); setCurrentPage(1); }}
                                className="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:bg-white focus:outline-none"
                            >
                                <option value="">Semua Debitur / Instansi</option>
                                {debtors.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name} {d.code ? `(${d.code})` : ''}
                                    </option>
                                ))}
                            </select>

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
                                        <td colSpan={9} className="text-center py-12 text-slate-400">
                                            Tidak ada data piutang yang sesuai dengan filter.
                                        </td>
                                    </tr>
                                ) : (
                                    paginatedPiutang.map((item) => {
                                        const isLunas = item.status === 'Lunas' || item.sisa <= 0.005;
                                        return (
                                            <tr key={item.id} className="hover:bg-slate-50/80 transition">
                                                <td className="px-4 py-3.5">
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
                                                    <div className="font-bold text-slate-900">{item.nomor}</div>
                                                    <div className="text-[11px] text-slate-400 mt-0.5">{item.tanggal}</div>
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
                                                    <span className={`inline-block px-2.5 py-1 rounded-full text-[11px] font-bold ${
                                                        isLunas
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
                                                        {item.sisa > 0.005 && (
                                                            <button
                                                                onClick={() => openPaymentModal(item)}
                                                                className="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-semibold transition"
                                                            >
                                                                Terima
                                                            </button>
                                                        )}
                                                        <button
                                                            onClick={() => setSelectedPiutang(item)}
                                                            className="px-2.5 py-1 border border-slate-200 text-slate-600 hover:bg-slate-100 rounded-lg text-xs font-semibold transition"
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
                item={selectedPiutang}
                onClose={() => setSelectedPiutang(null)}
                title="Rincian Transaksi Piutang"
                subtitle={(item) => item.nomor}
                renderFooter={(item) => (
                    item.sisa > 0.005 ? (
                        <button
                            type="button"
                            onClick={() => {
                                const target = item;
                                setSelectedPiutang(null);
                                openPaymentModal(target);
                            }}
                            className="w-full py-2.5 bg-amber-600 hover:bg-amber-700 active:scale-[0.99] text-white rounded-xl text-xs font-bold transition shadow-xs text-center cursor-pointer"
                        >
                            Terima Pembayaran ({formatRupiah(item.sisa)})
                        </button>
                    ) : null
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
        </FinanceLayout>
    );
}
