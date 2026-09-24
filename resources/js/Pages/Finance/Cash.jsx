import React, { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';
import FinanceLayout from './Layouts/FinanceLayout';
import PbfCombobox from './Components/PbfCombobox';
import { formatRupiah, formatNumberOnly } from './Components/Utils';
import {
    Banknote, Search, Check, X, Calendar, AlertCircle, ChevronLeft, ChevronRight
} from 'lucide-react';

export default function Cash({ pembelianCash = [], creditors = [], kasBankAccounts = [], stats = {} }) {
    const [searchTerm, setSearchTerm] = useState('');
    const [filterPbf, setFilterPbf] = useState('');
    const [currentPage, setCurrentPage] = useState(1);
    const itemsPerPage = 10;

    // Selection untuk Penetapan Akun Massal (Maks 10)
    const [selectedCashIds, setSelectedCashIds] = useState([]);
    const [selectionWarning, setSelectionWarning] = useState('');

    // State Drawer Detail Faktur
    const [selectedCash, setSelectedCash] = useState(null);

    // State Modal Tetapkan Akun Kas Satuan
    const [isCashModalOpen, setIsCashModalOpen] = useState(false);
    const [cashForm, setCashForm] = useState({
        receiving_detail_id: '',
        account_id: '',
        payment_date: new Date().toISOString().split('T')[0],
        reference_number: '',
        notes: '',
    });
    const [isSubmittingCash, setIsSubmittingCash] = useState(false);

    // State Modal Penetapan Akun Massal
    const [isBulkCashModalOpen, setIsBulkCashModalOpen] = useState(false);
    const [bulkCashForm, setBulkCashForm] = useState({
        account_id: '',
        payment_date: new Date().toISOString().split('T')[0],
        reference_number: '',
        notes: '',
    });
    const [isSubmittingBulkCash, setIsSubmittingBulkCash] = useState(false);

    // Filter
    const filteredCash = useMemo(() => {
        return pembelianCash.filter((item) => {
            const matchSearch =
                (item.nomor && item.nomor.toLowerCase().includes(searchTerm.toLowerCase())) ||
                (item.vendor && item.vendor.toLowerCase().includes(searchTerm.toLowerCase())) ||
                (item.referensi && item.referensi.toLowerCase().includes(searchTerm.toLowerCase()));

            const matchPbf = filterPbf
                ? (item.vendor && item.vendor.toLowerCase().includes(filterPbf.toLowerCase()))
                : true;

            return matchSearch && matchPbf;
        });
    }, [pembelianCash, searchTerm, filterPbf]);

    const totalPages = Math.ceil(filteredCash.length / itemsPerPage) || 1;
    const paginatedCash = useMemo(() => {
        const start = (currentPage - 1) * itemsPerPage;
        return filteredCash.slice(start, start + itemsPerPage);
    }, [filteredCash, currentPage, itemsPerPage]);

    const selectedCashItems = useMemo(() => {
        return pembelianCash.filter((item) => selectedCashIds.includes(item.id));
    }, [pembelianCash, selectedCashIds]);

    const totalSelectedCashNominal = useMemo(() => {
        return selectedCashItems.reduce((acc, curr) => acc + (curr.total || 0), 0);
    }, [selectedCashItems]);

    // Handlers Checkbox
    const handleToggleCash = (item) => {
        if (selectedCashIds.includes(item.id)) {
            setSelectedCashIds((prev) => prev.filter((id) => id !== item.id));
        } else {
            if (selectedCashIds.length >= 10) {
                setSelectionWarning('Maksimal 10 faktur yang dapat dipilih sekaligus.');
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
                setSelectionWarning('Maksimal 10 faktur yang dapat dipilih.');
                setTimeout(() => setSelectionWarning(''), 4000);
                return;
            }
            const toAdd = paginatedCash
                .map((item) => item.id)
                .filter((id) => !selectedCashIds.includes(id))
                .slice(0, remainingSlots);

            if (toAdd.length < paginatedCash.length) {
                setSelectionWarning('Hanya 10 faktur pertama yang dipilih (maks. 10 faktur).');
                setTimeout(() => setSelectionWarning(''), 4000);
            }
            setSelectedCashIds((prev) => [...prev, ...toAdd]);
        } else {
            const currentPageIds = paginatedCash.map((item) => item.id);
            setSelectedCashIds((prev) => prev.filter((id) => !currentPageIds.includes(id)));
        }
    };

    // Buka Modal Tetapkan Akun Satuan
    const openCashModal = (item) => {
        const defaultAccount = item.akunPembayaran?.id || kasBankAccounts[0]?.id || '';
        setCashForm({
            receiving_detail_id: item.id,
            account_id: defaultAccount,
            payment_date: new Date().toISOString().split('T')[0],
            reference_number: item.akunPembayaran?.noReferensi || '',
            notes: `Pembelian tunai faktur ${item.nomor} kepada ${item.vendor}`,
        });
        setIsCashModalOpen(true);
    };

    const handleCashSubmit = (e) => {
        e.preventDefault();
        if (!cashForm.account_id) {
            alert('Pilih akun kas & bank terlebih dahulu.');
            return;
        }
        setIsSubmittingCash(true);
        router.post('/finance/cash-payment', cashForm, {
            preserveScroll: true,
            onSuccess: () => {
                setIsCashModalOpen(false);
                setIsSubmittingCash(false);
            },
            onError: (errs) => {
                setIsSubmittingCash(false);
                alert(Object.values(errs)[0] || 'Gagal menetapkan akun pembelian cash.');
            }
        });
    };

    // Buka Modal Tetapkan Akun Massal
    const openBulkCashModal = () => {
        if (selectedCashIds.length === 0) return;
        const defaultAccount = kasBankAccounts[0]?.id || '';
        setBulkCashForm({
            account_id: defaultAccount,
            payment_date: new Date().toISOString().split('T')[0],
            reference_number: '',
            notes: `Penetapan akun massal untuk ${selectedCashIds.length} pembelian tunai`,
        });
        setIsBulkCashModalOpen(true);
    };

    const handleBulkCashSubmit = (e) => {
        e.preventDefault();
        if (!bulkCashForm.account_id) {
            alert('Pilih akun kas & bank terlebih dahulu.');
            return;
        }
        setIsSubmittingBulkCash(true);
        router.post('/finance/bulk-cash-payment', {
            receiving_detail_ids: selectedCashIds,
            account_id: bulkCashForm.account_id,
            payment_date: bulkCashForm.payment_date,
            reference_number: bulkCashForm.reference_number,
            notes: bulkCashForm.notes,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsBulkCashModalOpen(false);
                setIsSubmittingBulkCash(false);
                setSelectedCashIds([]);
            },
            onError: (errs) => {
                setIsSubmittingBulkCash(false);
                alert(Object.values(errs)[0] || 'Gagal menetapkan akun pembelian cash massal.');
            }
        });
    };

    return (
        <FinanceLayout
            title="Pembelian Cash (Tunai)"
            subtitle="Faktur pembelian barang/obat tunai dan pembebanan akun Kas & Bank"
            stats={stats}
        >
            <div className="space-y-6">
                {/* Banner Summary */}
                <div className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            Total Pembelian Tunai (Cash)
                        </div>
                        <div className="text-2xl font-black text-slate-900 mt-1">
                            {formatRupiah(stats.totalCashPurchases || 0)}
                        </div>
                        <p className="text-xs text-slate-400 mt-0.5">
                            Dari {pembelianCash.length} faktur pembelian tunai
                        </p>
                    </div>

                    <span className="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-100">
                        {pembelianCash.length} Faktur Lunas (Cash)
                    </span>
                </div>

                {/* Toolbar Filter */}
                <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2.5 flex-1">
                            <div className="relative w-full sm:w-64">
                                <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                                <input
                                    type="text"
                                    placeholder="Cari faktur, PBF, atau kode NT..."
                                    value={searchTerm}
                                    onChange={(e) => { setSearchTerm(e.target.value); setCurrentPage(1); }}
                                    className="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:outline-none"
                                />
                            </div>

                            <PbfCombobox
                                pbfs={creditors}
                                selectedPbf={filterPbf}
                                onSelectPbf={(val) => { setFilterPbf(val); setCurrentPage(1); }}
                                placeholder="Filter PBF (Kreditur)..."
                            />
                        </div>

                        {selectedCashIds.length > 0 && (
                            <button
                                onClick={openBulkCashModal}
                                className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center gap-2 animate-in zoom-in-95 duration-150"
                            >
                                <Banknote className="w-4 h-4" />
                                <span>Tetapkan Akun ({selectedCashIds.length} Faktur)</span>
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

                {/* Tabel Pembelian Cash */}
                <div className="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr className="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider text-[11px]">
                                    <th className="px-4 py-3.5 w-10">
                                        <input
                                            type="checkbox"
                                            checked={
                                                paginatedCash.length > 0 &&
                                                paginatedCash.every(i => selectedCashIds.includes(i.id))
                                            }
                                            onChange={handleSelectAllCash}
                                            className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                            title="Pilih semua di halaman ini"
                                        />
                                    </th>
                                    <th className="px-4 py-3.5">Faktur & Tanggal</th>
                                    <th className="px-4 py-3.5">Vendor (PBF)</th>
                                    <th className="px-4 py-3.5">Akun Kas / Bank Pembebanan</th>
                                    <th className="px-4 py-3.5 text-right">Total Faktur</th>
                                    <th className="px-4 py-3.5 text-center">Status</th>
                                    <th className="px-4 py-3.5 text-center w-28">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {paginatedCash.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="text-center py-12 text-slate-400">
                                            Tidak ada data pembelian cash yang sesuai dengan filter.
                                        </td>
                                    </tr>
                                ) : (
                                    paginatedCash.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-50/80 transition">
                                            <td className="px-4 py-3.5">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedCashIds.includes(item.id)}
                                                    onChange={() => handleToggleCash(item)}
                                                    className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                                />
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="font-bold text-slate-900">{item.nomor}</div>
                                                <div className="text-[11px] text-slate-400 mt-0.5">
                                                    {item.referensi} • Tgl: {item.tanggal}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="font-semibold text-slate-800">{item.vendor}</div>
                                                <div className="text-[10px] text-slate-400">{item.gudang}</div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {item.akunPembayaran ? (
                                                    <div>
                                                        <span className="font-bold text-slate-800">{item.akunPembayaran.nama}</span>
                                                        <span className="text-[10px] text-slate-400 block font-mono">
                                                            {item.akunPembayaran.kode}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-amber-600 font-semibold italic text-[11px]">
                                                        Belum ditetapkan
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 text-right font-bold text-slate-900">
                                                {formatNumberOnly(item.total)}
                                            </td>
                                            <td className="px-4 py-3.5 text-center">
                                                <span className="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700">
                                                    Lunas (Cash)
                                                </span>
                                            </td>
                                            <td className="px-4 py-3.5 text-center">
                                                <div className="flex items-center justify-center gap-1.5">
                                                    <button
                                                        onClick={() => openCashModal(item)}
                                                        className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold transition"
                                                    >
                                                        {item.akunPembayaran ? 'Ubah Akun' : 'Tetapkan'}
                                                    </button>
                                                    <button
                                                        onClick={() => setSelectedCash(item)}
                                                        className="px-2.5 py-1 border border-slate-200 text-slate-600 hover:bg-slate-100 rounded-lg text-xs font-semibold transition"
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
                                Menampilkan {(currentPage - 1) * itemsPerPage + 1} - {Math.min(currentPage * itemsPerPage, filteredCash.length)} dari {filteredCash.length} faktur
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

            {/* Modal Tetapkan Akun Kas Satuan */}
            {isCashModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Banknote className="w-5 h-5 text-emerald-600" />
                                <h3 className="font-bold text-slate-900 text-sm">Tetapkan Akun Kas Pembelian Tunai</h3>
                            </div>
                            <button
                                onClick={() => setIsCashModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <form onSubmit={handleCashSubmit} className="p-6 space-y-4 text-xs">
                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Bebankan Ke Akun (Kas & Bank) <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={cashForm.account_id}
                                    onChange={(e) => setCashForm({ ...cashForm, account_id: e.target.value })}
                                    required
                                    className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
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
                                        value={cashForm.payment_date}
                                        onChange={(e) => setCashForm({ ...cashForm, payment_date: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-700 font-semibold mb-1">No. Bukti / Referensi</label>
                                    <input
                                        type="text"
                                        placeholder="Contoh: KWT-CASH-001"
                                        value={cashForm.reference_number}
                                        onChange={(e) => setCashForm({ ...cashForm, reference_number: e.target.value })}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">Catatan</label>
                                <textarea
                                    rows={2}
                                    value={cashForm.notes}
                                    onChange={(e) => setCashForm({ ...cashForm, notes: e.target.value })}
                                    className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                />
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
                                    {isSubmittingCash ? 'Menyimpan...' : 'Simpan Penetapan'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Penetapan Akun Massal */}
            {isBulkCashModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Banknote className="w-5 h-5 text-emerald-600" />
                                <h3 className="font-bold text-slate-900 text-sm">
                                    Penetapan Akun Massal ({selectedCashIds.length} Faktur)
                                </h3>
                            </div>
                            <button
                                onClick={() => setIsBulkCashModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <form onSubmit={handleBulkCashSubmit} className="p-6 space-y-4 text-xs">
                            <div className="p-3 bg-emerald-50/70 border border-emerald-100 rounded-xl">
                                <div className="text-slate-500 font-medium">Total Nominal Terpilih:</div>
                                <div className="text-xl font-black text-emerald-700 mt-0.5">
                                    {formatRupiah(totalSelectedCashNominal)}
                                </div>
                            </div>

                            <div>
                                <label className="block text-slate-700 font-semibold mb-1">
                                    Bebankan Ke Akun (Kas & Bank) <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={bulkCashForm.account_id}
                                    onChange={(e) => setBulkCashForm({ ...bulkCashForm, account_id: e.target.value })}
                                    required
                                    className="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                >
                                    <option value="">-- Pilih Akun Kas & Bank --</option>
                                    {kasBankAccounts.map((acc) => (
                                        <option key={acc.id} value={acc.id}>
                                            {acc.name} ({acc.code}) - Saldo: {formatRupiah(acc.balance)}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
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
                                    className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50"
                                >
                                    {isSubmittingBulkCash ? 'Menyimpan...' : 'Konfirmasi Penetapan Massal'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Drawer Detail Faktur */}
            {selectedCash && (
                <div className="fixed inset-0 z-50 flex justify-end bg-slate-900/40 backdrop-blur-2xs animate-in fade-in duration-200">
                    <div className="w-full max-w-xl bg-white h-full shadow-2xl flex flex-col animate-in slide-in-from-right duration-200">
                        <div className="p-6 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3 className="font-bold text-slate-900 text-base">Rincian Pembelian Tunai</h3>
                                <p className="text-xs text-slate-400 mt-0.5">
                                    {selectedCash.nomor} • {selectedCash.referensi}
                                </p>
                            </div>
                            <button
                                onClick={() => setSelectedCash(null)}
                                className="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <div className="flex-1 overflow-y-auto p-6 space-y-6 text-xs">
                            <div className="p-4 bg-slate-50 rounded-xl space-y-2">
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Vendor / PBF:</span>
                                    <span className="font-bold text-slate-800">{selectedCash.vendor}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Tanggal Faktur:</span>
                                    <span className="font-medium text-slate-700">{selectedCash.tanggal}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Akun Kas / Bank:</span>
                                    <span className="font-bold text-emerald-700">
                                        {selectedCash.akunPembayaran ? selectedCash.akunPembayaran.nama : 'Belum Ditetapkan'}
                                    </span>
                                </div>
                            </div>

                            {/* Item Table */}
                            <div>
                                <h4 className="font-bold text-slate-900 mb-3">Item Pembelian</h4>
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
                                            {(selectedCash.items || []).map((it, idx) => (
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

                            {/* Total summary */}
                            <div className="p-4 bg-slate-50 rounded-xl space-y-1.5 font-medium">
                                <div className="flex justify-between text-slate-600">
                                    <span>Subtotal:</span>
                                    <span>{formatNumberOnly(selectedCash.subtotal)}</span>
                                </div>
                                <div className="flex justify-between text-slate-600">
                                    <span>PPN:</span>
                                    <span>{formatNumberOnly(selectedCash.ppn)}</span>
                                </div>
                                <div className="flex justify-between text-base font-bold text-slate-900 pt-2 border-t border-slate-200">
                                    <span>Total Pembelian (Cash):</span>
                                    <span>{formatRupiah(selectedCash.total)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </FinanceLayout>
    );
}
