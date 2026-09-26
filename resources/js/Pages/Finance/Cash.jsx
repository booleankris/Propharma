import React, { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';
import FinanceLayout from './Layouts/FinanceLayout';
import PbfCombobox from './Components/PbfCombobox';
import Drawer from './Components/Drawer';
import FloatingActionBar from './Components/FloatingActionBar';
import { formatRupiah, formatNumberOnly } from './Components/Utils';
import {
    Banknote, Search, Check, X, Calendar, AlertCircle, ChevronLeft, ChevronRight,
    ArrowLeft, Printer, Share2, MoreVertical, ChevronDown, History, Landmark, Maximize2, CreditCard, Download, FileSpreadsheet,
    CheckCircle2, AlertTriangle, ArrowUp, ArrowDown
} from 'lucide-react';

export default function Cash({ pembelianCash = [], creditors = [], kasBankAccounts = [], stats = {} }) {
    const [searchTerm, setSearchTerm] = useState('');
    const [filterPbf, setFilterPbf] = useState('');
    const [accountFilter, setAccountFilter] = useState('ALL'); // 'ALL' | 'UNASSIGNED' | 'ASSIGNED'
    const [sortOrder, setSortOrder] = useState('desc'); // 'desc' | 'asc'
    const [currentPage, setCurrentPage] = useState(1);
    const itemsPerPage = 10;

    // Selection untuk Penetapan Akun Massal (Maks 10)
    const [selectedCashIds, setSelectedCashIds] = useState([]);
    const [selectionWarning, setSelectionWarning] = useState('');

    // State Halaman Detil Penuh & Quick Preview Drawer
    const [detailViewCash, setDetailViewCash] = useState(null);
    const [drawerCash, setDrawerCash] = useState(null);
    const [isShippingOpen, setIsShippingOpen] = useState(true);

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

    // Statistik penetapan akun kas
    const accountStats = useMemo(() => {
        let sudah = 0;
        let belum = 0;
        let nominalBelum = 0;
        pembelianCash.forEach((item) => {
            if (item.akunPembayaran) {
                sudah++;
            } else {
                belum++;
                nominalBelum += Number(item.total) || 0;
            }
        });
        return {
            sudah: stats.countSudahPilihAkun ?? sudah,
            belum: stats.countBelumPilihAkun ?? belum,
            nominalBelum: stats.totalNominalBelumPilihAkun ?? nominalBelum,
        };
    }, [pembelianCash, stats]);

    // Filter
    const filteredCash = useMemo(() => {
        const result = pembelianCash.filter((item) => {
            const matchSearch =
                (item.nomor && item.nomor.toLowerCase().includes(searchTerm.toLowerCase())) ||
                (item.vendor && item.vendor.toLowerCase().includes(searchTerm.toLowerCase())) ||
                (item.referensi && item.referensi.toLowerCase().includes(searchTerm.toLowerCase()));

            const matchPbf = filterPbf
                ? (item.vendor && item.vendor.toLowerCase().includes(filterPbf.toLowerCase()))
                : true;

            let matchAccount = true;
            if (accountFilter === 'ASSIGNED') {
                matchAccount = !!item.akunPembayaran;
            } else if (accountFilter === 'UNASSIGNED') {
                matchAccount = !item.akunPembayaran;
            }

            return matchSearch && matchPbf && matchAccount;
        });

        return result.sort((a, b) => {
            const timeA = a.raw_tanggal ? new Date(a.raw_tanggal).getTime() : (Number(a.id) || 0);
            const timeB = b.raw_tanggal ? new Date(b.raw_tanggal).getTime() : (Number(b.id) || 0);
            return sortOrder === 'asc' ? timeA - timeB : timeB - timeA;
        });
    }, [pembelianCash, searchTerm, filterPbf, accountFilter, sortOrder]);

    const totalPages = Math.ceil(filteredCash.length / itemsPerPage) || 1;
    const paginatedCash = useMemo(() => {
        const start = (currentPage - 1) * itemsPerPage;
        return filteredCash.slice(start, start + itemsPerPage);
    }, [filteredCash, currentPage, itemsPerPage]);

    const selectedCashItems = useMemo(() => {
        return pembelianCash.filter((item) => selectedCashIds.includes(item.id));
    }, [pembelianCash, selectedCashIds]);

    const totalSelectedCashNominal = useMemo(() => {
        const sum = selectedCashItems.reduce((acc, curr) => acc + (Number(curr.total) || 0), 0);
        return Math.round(sum * 100) / 100;
    }, [selectedCashItems]);

    // Total kuantitas barang pada halaman detil penuh Cash
    const totalDetailCashQty = useMemo(() => {
        if (!detailViewCash || !detailViewCash.items) return 0;
        return detailViewCash.items.reduce((acc, curr) => acc + (Number(curr.qty) || 0), 0);
    }, [detailViewCash]);

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
                setDrawerCash(null);
                setDetailViewCash(null);
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
            title={detailViewCash ? `Detil Pembelian Tunai (Cash) ${detailViewCash.nomor}` : "Pembelian Cash (Tunai)"}
            subtitle={detailViewCash ? undefined : "Faktur pembelian barang/obat tunai dan pembebanan akun Kas & Bank"}
            stats={detailViewCash ? undefined : stats}
        >
            {detailViewCash ? (
                /* SUB-VIEW A: DETIL PEMBELIAN CASH (PERSIS SEPERTI KREDIT) */
                <div className="space-y-6 max-w-[1300px] mx-auto animate-in fade-in duration-200">
                    {/* Link Kembali ke Daftar */}
                    <button
                        type="button"
                        onClick={() => setDetailViewCash(null)}
                        className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-800 font-medium transition cursor-pointer"
                    >
                        <ArrowLeft className="w-3.5 h-3.5" />
                        <span>Kembali ke Daftar</span>
                    </button>

                    {/* Judul & Action Kanan */}
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                            Detil Pembelian Tunai (Cash) {detailViewCash.nomor}
                        </h1>

                        <div className="flex items-center gap-2 flex-wrap">
                            <button
                                type="button"
                                onClick={() => openCashModal(detailViewCash)}
                                className="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition shadow-xs cursor-pointer"
                            >
                                <Landmark className="w-3.5 h-3.5" />
                                <span>{detailViewCash.akunPembayaran ? 'Ubah Akun Kas/Bank' : 'Tetapkan Akun Kas/Bank'}</span>
                            </button>

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

                    {/* KARTU UTAMA DETIL FAKTUR CASH */}
                    <div className="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs space-y-6">
                        {/* Status Badge */}
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span className="inline-block px-3 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Lunas (Cash)
                                </span>
                                {detailViewCash.akunPembayaran ? (
                                    <span className="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300">
                                        <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                                        <span>Akun Kas Terhubung</span>
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold rounded-full bg-amber-100 text-amber-900 border border-amber-300">
                                        <AlertCircle className="w-3.5 h-3.5 text-amber-700" />
                                        <span>Belum Pilih Akun Kas</span>
                                    </span>
                                )}
                            </div>

                            <span className="text-xs text-slate-400">
                                Jenis Pembayaran: <strong className="text-slate-700 font-semibold">TUNAI (Langsung Lunas)</strong>
                            </span>
                        </div>

                        {/* Metadata Faktur (2 Kolom) */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 text-xs">
                            <div className="space-y-4">
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1">PBF / Vendor</span>
                                    <span className="text-blue-600 font-semibold text-sm hover:underline cursor-pointer">
                                        {detailViewCash.vendor}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1">Tgl. Transaksi</span>
                                    <span className="font-semibold text-slate-800 text-sm">{detailViewCash.tanggal}</span>
                                </div>
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1">Apotek Penerima</span>
                                    <span className="text-blue-600 font-medium hover:underline cursor-pointer">
                                        {detailViewCash.gudang}
                                    </span>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1">Nomor Faktur</span>
                                    <span className="font-bold text-slate-900 text-sm">{detailViewCash.nomor}</span>
                                </div>
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1">Referensi / Kode Penerimaan</span>
                                    <span className="font-medium text-slate-800">{detailViewCash.referensi}</span>
                                </div>
                                <div>
                                    <span className="text-slate-400 block text-[11px] mb-1">Akun Pembayaran (Kas & Bank)</span>
                                    {detailViewCash.akunPembayaran ? (
                                        <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-xl shadow-2xs">
                                            <Landmark className="w-4 h-4 text-emerald-600" />
                                            <div>
                                                <span className="font-bold text-xs block">{detailViewCash.akunPembayaran.nama}</span>
                                                <span className="text-[10px] text-slate-500 font-mono block">
                                                    {detailViewCash.akunPembayaran.kode} {detailViewCash.akunPembayaran.noRekening ? `• ${detailViewCash.akunPembayaran.noRekening}` : ''}
                                                </span>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="flex items-center gap-2">
                                            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-100/90 text-amber-900 border border-amber-300 shadow-2xs">
                                                <AlertCircle className="w-3.5 h-3.5 text-amber-700" />
                                                <span>Belum Ditetapkan Akun</span>
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => openCashModal(detailViewCash)}
                                                className="px-3 py-1 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-lg shadow-2xs transition cursor-pointer"
                                            >
                                                Pilih Akun Sekarang
                                            </button>
                                        </div>
                                    )}
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
                                    <span className="text-slate-400 block text-[11px]">Tanggal Pengiriman / Penerimaan</span>
                                    <span className="font-medium text-slate-800">{detailViewCash.tanggal}</span>
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
                                    {detailViewCash.items && detailViewCash.items.length > 0 ? (
                                        detailViewCash.items.map((it, idx) => (
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
                                        <td className="py-3 text-center font-bold text-slate-900">{totalDetailCashQty}</td>
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
                                    <span className="font-medium text-slate-800">{formatNumberOnly(detailViewCash.subtotal)}</span>
                                </div>
                                <div className="flex justify-between text-slate-600">
                                    <span>PPN11</span>
                                    <span className="font-medium text-slate-800">{formatNumberOnly(detailViewCash.ppn)}</span>
                                </div>
                                <div className="flex justify-between text-slate-800 font-bold pt-1">
                                    <span>Total Pembelian Cash</span>
                                    <span>{formatNumberOnly(detailViewCash.total)}</span>
                                </div>

                                <div className="bg-emerald-50 border border-emerald-200/80 p-4 rounded-xl flex justify-between items-center text-emerald-800 font-bold mt-3">
                                    <span className="text-xs">Status Pembayaran</span>
                                    <span className="text-xs px-2.5 py-1 bg-emerald-600 text-white rounded-lg">LUNAS (CASH)</span>
                                </div>

                                {!detailViewCash.akunPembayaran && (
                                    <button
                                        type="button"
                                        onClick={() => openCashModal(detailViewCash)}
                                        className="w-full mt-2 py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition flex items-center justify-center gap-2 shadow-xs cursor-pointer"
                                    >
                                        <Landmark className="w-4 h-4" />
                                        <span>Pilih Akun Kas & Bank</span>
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* KARTU AUDIT TRAIL / AKUN PEMBAYARAN */}
                    <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div className="flex items-center gap-2.5">
                                <History className="w-5 h-5 text-emerald-600" />
                                <div>
                                    <h3 className="font-bold text-slate-900 text-sm">
                                        Pantau log perubahan data
                                    </h3>
                                    <p className="text-[11px] text-slate-400">
                                        Rincian akun kas drawer atau rekening bank yang digunakan untuk mendanai pembelian obat tunai ini.
                                    </p>
                                </div>
                            </div>

                            <span className="text-xs font-semibold text-slate-600">
                                Total Pembayaran: <strong className="text-emerald-600">{formatRupiah(detailViewCash.total)}</strong>
                            </span>
                        </div>

                        {detailViewCash.akunPembayaran ? (
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
                                            <td className="py-3 px-3 font-medium text-slate-900">{detailViewCash.akunPembayaran.tanggal || detailViewCash.tanggal}</td>
                                            <td className="py-3 px-3">
                                                <div className="flex items-center gap-1.5">
                                                    <Landmark className="w-3.5 h-3.5 text-emerald-500" />
                                                    <span className="font-semibold text-slate-800">{detailViewCash.akunPembayaran.nama}</span>
                                                    <span className="text-[10px] text-slate-400">({detailViewCash.akunPembayaran.kategori || 'Kas & Bank'})</span>
                                                </div>
                                            </td>
                                            <td className="py-3 px-3 font-mono text-[11px] text-slate-600">{detailViewCash.akunPembayaran.noRekening || '-'}</td>
                                            <td className="py-3 px-3 font-mono text-[11px] text-slate-600">{detailViewCash.akunPembayaran.noReferensi || detailViewCash.referensi}</td>
                                            <td className="py-3 px-3 text-right font-bold text-emerald-600">
                                                {formatRupiah(detailViewCash.total)}
                                            </td>
                                            <td className="py-3 px-3 font-medium text-slate-800">{detailViewCash.akunPembayaran.diprosesOleh || 'Staff Kasir'}</td>
                                            <td className="py-3 px-3 text-center">
                                                <button
                                                    type="button"
                                                    onClick={() => openCashModal(detailViewCash)}
                                                    className="px-2.5 py-1 text-xs font-semibold text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition cursor-pointer"
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
                                <History className="w-8 h-8 mx-auto text-slate-300 mb-2" />
                                <p className="text-xs font-medium text-slate-600">Belum ada akun kas/bank yang ditetapkan untuk transaksi pembelian tunai ini.</p>
                                <p className="text-[11px] text-slate-400 mt-0.5">
                                    Klik tombol <strong>"Tetapkan Akun Kas/Bank"</strong> untuk mencatat sumber pengeluaran dana.
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            ) : (
                /* SUB-VIEW B: TABEL DAFTAR PEMBELIAN CASH */
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

                        <div className="flex flex-wrap items-center gap-2">
                            <div className="px-3.5 py-2 rounded-xl bg-emerald-50 text-emerald-800 text-xs font-bold border border-emerald-200 flex items-center gap-1.5 shadow-2xs">
                                <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                                <span>{accountStats.sudah} Sudah Pilih Akun</span>
                            </div>

                            {accountStats.belum > 0 ? (
                                <div className="px-3.5 py-2 rounded-xl bg-amber-50 text-amber-900 text-xs font-bold border border-amber-300 flex items-center gap-1.5 shadow-2xs">
                                    <AlertCircle className="w-4 h-4 text-amber-600" />
                                    <span>{accountStats.belum} Belum Pilih Akun ({formatRupiah(accountStats.nominalBelum)})</span>
                                </div>
                            ) : (
                                <div className="px-3.5 py-2 rounded-xl bg-slate-50 text-slate-600 text-xs font-semibold border border-slate-200 flex items-center gap-1.5">
                                    <Check className="w-4 h-4 text-emerald-500" />
                                    <span>Semua Akun Sudah Ditetapkan</span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Toolbar Filter */}
                    <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-wrap items-center gap-2.5 flex-1">
                                <div className="relative w-full sm:w-60">
                                    <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                                    <input
                                        type="text"
                                        placeholder="Cari faktur, PBF, kode NT..."
                                        value={searchTerm}
                                        onChange={(e) => { setSearchTerm(e.target.value); setCurrentPage(1); }}
                                        className="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:outline-none"
                                    />
                                </div>

                                <PbfCombobox
                                    pbfs={creditors}
                                    selectedPbf={filterPbf}
                                    onSelectPbf={(val) => { setFilterPbf(val); setCurrentPage(1); }}
                                    placeholder="Filter PBF..."
                                />

                                {/* Segmented Filter Status Akun: Semua / Belum Pilih / Sudah Pilih */}
                                <div className="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200/80 text-xs">
                                    <button
                                        type="button"
                                        onClick={() => { setAccountFilter('ALL'); setCurrentPage(1); }}
                                        className={`px-3 py-1.5 rounded-lg font-semibold transition cursor-pointer ${
                                            accountFilter === 'ALL'
                                                ? 'bg-white text-slate-900 shadow-2xs font-bold'
                                                : 'text-slate-600 hover:text-slate-900'
                                        }`}
                                    >
                                        Semua ({pembelianCash.length})
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => { setAccountFilter('UNASSIGNED'); setCurrentPage(1); }}
                                        className={`px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5 cursor-pointer ${
                                            accountFilter === 'UNASSIGNED'
                                                ? 'bg-amber-500 text-white shadow-2xs font-bold'
                                                : 'text-amber-800 hover:text-amber-900 bg-amber-50/50'
                                        }`}
                                        title="Tampilkan hanya faktur tunai yang belum ditetapkan akun kas/bank"
                                    >
                                        <AlertCircle className={`w-3.5 h-3.5 ${accountFilter === 'UNASSIGNED' ? 'text-white' : 'text-amber-600'}`} />
                                        <span>Belum Pilih ({accountStats.belum})</span>
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => { setAccountFilter('ASSIGNED'); setCurrentPage(1); }}
                                        className={`px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5 cursor-pointer ${
                                            accountFilter === 'ASSIGNED'
                                                ? 'bg-emerald-600 text-white shadow-2xs font-bold'
                                                : 'text-emerald-800 hover:text-emerald-900 bg-emerald-50/40'
                                        }`}
                                        title="Tampilkan hanya faktur tunai yang sudah memiliki akun kas/bank"
                                    >
                                        <CheckCircle2 className={`w-3.5 h-3.5 ${accountFilter === 'ASSIGNED' ? 'text-white' : 'text-emerald-600'}`} />
                                        <span>Sudah Pilih ({accountStats.sudah})</span>
                                    </button>
                                </div>

                                {/* Filter: Waktu Pembelian ASC / DESC */}
                                <button
                                    type="button"
                                    onClick={() => setSortOrder(prev => prev === 'desc' ? 'asc' : 'desc')}
                                    className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold transition border cursor-pointer ${
                                        sortOrder === 'asc'
                                            ? 'bg-blue-50 text-blue-800 border-blue-200'
                                            : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
                                    }`}
                                    title={sortOrder === 'asc' ? 'Urutan: Waktu Pembelian Terlama (ASC)' : 'Urutan: Waktu Pembelian Terbaru (DESC)'}
                                >
                                    {sortOrder === 'asc' ? <ArrowUp className="w-3.5 h-3.5 text-blue-600" /> : <ArrowDown className="w-3.5 h-3.5 text-slate-500" />}
                                    <span>Waktu: {sortOrder === 'asc' ? 'Terlama (ASC)' : 'Terbaru (DESC)'}</span>
                                </button>

                                {/* Tombol Export Excel */}
                                <a
                                    href={`/finance/export/cash?search=${encodeURIComponent(searchTerm)}&pbf=${encodeURIComponent(filterPbf)}&account_status=${encodeURIComponent(accountFilter)}&sort_order=${encodeURIComponent(sortOrder)}`}
                                    className="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-50 hover:bg-emerald-100/80 text-emerald-800 border border-emerald-200 rounded-xl text-xs font-semibold transition shadow-2xs cursor-pointer"
                                    title="Export data pembelian cash ke Excel (.xlsx) dengan format rapi dan estetik"
                                >
                                    <FileSpreadsheet className="w-3.5 h-3.5 text-emerald-600" />
                                    <span>Export Excel</span>
                                </a>
                            </div>

                            {selectedCashIds.length > 0 && (
                                <button
                                    onClick={openBulkCashModal}
                                    className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center gap-2 animate-in zoom-in-95 duration-150 cursor-pointer"
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
                                        <th className="px-4 py-3.5">Akun Kas & Bank</th>
                                        <th className="px-4 py-3.5 text-right">Total Faktur</th>
                                        <th className="px-4 py-3.5 text-center">Status</th>
                                        <th className="px-4 py-3.5 text-center">Aksi</th>
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
                                        paginatedCash.map((item) => {
                                            const hasAccount = !!item.akunPembayaran;
                                            return (
                                                <tr
                                                    key={item.id}
                                                    onClick={() => setDrawerCash(item)}
                                                    className={`cursor-pointer transition group ${
                                                        hasAccount
                                                            ? 'hover:bg-slate-50/70'
                                                            : 'bg-amber-50/25 hover:bg-amber-50/45 border-l-4 border-l-amber-400'
                                                    }`}
                                                >
                                                    <td className="px-4 py-3.5" onClick={(e) => e.stopPropagation()}>
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
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                setDetailViewCash(item);
                                                            }}
                                                            className="font-bold text-slate-900 hover:text-emerald-600 text-left transition group-hover:text-emerald-600 cursor-pointer"
                                                            title="Buka Halaman Detil Lengkap"
                                                        >
                                                            {item.nomor}
                                                        </button>
                                                        <div className="text-[11px] text-slate-400 mt-0.5">
                                                            {item.referensi} • Tgl: {item.tanggal}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3.5">
                                                        <div className="font-semibold text-slate-800">{item.vendor}</div>
                                                        <div className="text-[10px] text-slate-400">{item.gudang}</div>
                                                    </td>
                                                    {/* Kolom Akun Kas & Bank - Diberi Pembeda Visual Jelas */}
                                                    <td className="px-4 py-3.5">
                                                        {hasAccount ? (
                                                            <div className="flex items-center gap-2">
                                                                <div className="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 border border-emerald-200/60">
                                                                    <Landmark className="w-3.5 h-3.5" />
                                                                </div>
                                                                <div>
                                                                    <span className="font-bold text-slate-800 block text-xs">
                                                                        {item.akunPembayaran.nama}
                                                                    </span>
                                                                    <span className="text-[10px] text-slate-500 font-mono block">
                                                                        {item.akunPembayaran.kode} {item.akunPembayaran.noRekening ? `• ${item.akunPembayaran.noRekening}` : ''}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-100/80 text-amber-900 text-xs font-bold border border-amber-300 shadow-2xs">
                                                                <AlertCircle className="w-3.5 h-3.5 text-amber-700 shrink-0" />
                                                                <span>Belum Pilih Akun</span>
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3.5 text-right font-bold text-slate-900">
                                                        {formatNumberOnly(item.total)}
                                                    </td>
                                                    {/* Kolom Status - Diberi Pembeda Visual Jelas */}
                                                    <td className="px-4 py-3.5 text-center">
                                                        {hasAccount ? (
                                                            <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                                <CheckCircle2 className="w-3 h-3 text-emerald-600" />
                                                                <span>Lunas • Akun Siap</span>
                                                            </span>
                                                        ) : (
                                                            <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-300">
                                                                <AlertCircle className="w-3 h-3 text-amber-600" />
                                                                <span>Perlu Pilih Akun</span>
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3.5 text-center" onClick={(e) => e.stopPropagation()}>
                                                        <div className="flex items-center justify-center gap-1.5">
                                                            {hasAccount ? (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => openCashModal(item)}
                                                                    className="px-2.5 py-1.5 border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-lg text-xs font-semibold transition cursor-pointer flex items-center gap-1"
                                                                    title="Ubah akun kas & bank yang ditetapkan"
                                                                >
                                                                    <Landmark className="w-3.5 h-3.5 text-slate-500" />
                                                                    <span>Ubah</span>
                                                                </button>
                                                            ) : (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => openCashModal(item)}
                                                                    className="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 active:scale-95 text-white rounded-lg text-xs font-bold transition shadow-xs cursor-pointer flex items-center gap-1"
                                                                    title="Tetapkan akun kas & bank untuk faktur tunai ini"
                                                                >
                                                                    <Landmark className="w-3.5 h-3.5" />
                                                                    <span>Pilih Akun</span>
                                                                </button>
                                                            )}
                                                            <button
                                                                type="button"
                                                                onClick={() => setDetailViewCash(item)}
                                                                className="px-2.5 py-1.5 border border-slate-200 text-slate-600 hover:bg-slate-100 rounded-lg text-xs font-semibold transition cursor-pointer"
                                                                title="Buka Halaman Detil Lengkap"
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
            )}

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

            {/* Drawer Detail Faktur (Quick Preview Slide-in) */}
            <Drawer
                item={drawerCash}
                onClose={() => setDrawerCash(null)}
                onExpand={(item) => {
                    setDrawerCash(null);
                    setDetailViewCash(item);
                }}
                title="Rincian Pembelian Tunai"
                subtitle={(item) => `${item.nomor} • ${item.referensi}`}
                renderFooter={(item) => (
                    <div className="flex items-center gap-2 w-full">
                        <button
                            type="button"
                            onClick={() => {
                                const target = item;
                                setDrawerCash(null);
                                setDetailViewCash(target);
                            }}
                            className="flex-1 py-2.5 border border-slate-200 text-slate-700 hover:bg-slate-50 active:scale-[0.99] rounded-xl text-xs font-bold transition text-center cursor-pointer"
                        >
                            Buka Halaman Lengkap
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                const target = item;
                                setDrawerCash(null);
                                openCashModal(target);
                            }}
                            className="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white rounded-xl text-xs font-bold transition shadow-xs text-center cursor-pointer"
                        >
                            {item.akunPembayaran ? 'Ubah Akun' : 'Tetapkan Akun'}
                        </button>
                    </div>
                )}
            >
                {(item) => (
                    <>
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
                                <span className="text-slate-500">Akun Kas / Bank:</span>
                                {item.akunPembayaran ? (
                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-emerald-50 text-emerald-800 text-xs font-bold border border-emerald-200">
                                        <Landmark className="w-3 h-3 text-emerald-600" />
                                        <span>{item.akunPembayaran.nama}</span>
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-amber-50 text-amber-800 text-xs font-bold border border-amber-300">
                                        <AlertCircle className="w-3 h-3 text-amber-600" />
                                        <span>Belum Ditetapkan</span>
                                    </span>
                                )}
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

                        {/* Total summary */}
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
                                <span>Total Pembelian (Cash):</span>
                                <span>{formatRupiah(item.total)}</span>
                            </div>
                        </div>
                    </>
                )}
            </Drawer>

            {/* Floating Action Bar saat faktur cash dicentang */}
            <FloatingActionBar
                selectedCount={selectedCashIds.length}
                maxLimit={10}
                totalAmount={totalSelectedCashNominal}
                itemLabel="faktur"
                titleAmount="Total Pembelian Tunai (Cash)"
                actionLabel={`Tetapkan Akun (${selectedCashIds.length} Faktur)`}
                actionIcon={Landmark}
                onAction={openBulkCashModal}
                onClear={() => setSelectedCashIds([])}
                isSubmitting={isSubmittingBulkCash}
                themeColor="emerald"
            />
        </FinanceLayout>
    );
}
