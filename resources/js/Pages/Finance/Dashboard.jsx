import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import TransactionCategory from './Components/TransactionCategory';
import CashFlowChart from './Components/CashFlowChart';
import FinanceLayout from './Layouts/FinanceLayout';
import { formatRupiah } from './Components/Utils';
import {
    CreditCard,
    Landmark,
    ShoppingCart,
    ArrowDownLeft,
    ArrowUpRight,
    TrendingUp,
    TrendingDown,
    AlertTriangle,
    ChevronRight,
    Calendar,
    ArrowRight,
    CircleDollarSign,
    Undo2,
    ShoppingBag,
    PackageCheck,
    Wallet,
    CheckCircle2,
    Percent,
    Boxes,
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

export default function Dashboard({
    stats = {},
    realtime = {},
    recentMutasi = [],
    upcomingHutang = [],
    accounts = [],
}) {
    const now = new Date();
    const selectedMonth = Number(realtime.month || now.getMonth() + 1);
    const selectedYear = Number(realtime.year || now.getFullYear());
    const [isFiltering, setIsFiltering] = useState(false);

    const currentYear = now.getFullYear();
    const yearOptions = Array.from({ length: 6 }, (_, i) => currentYear - 4 + i);

    const handlePeriodChange = (nextMonth, nextYear) => {
        setIsFiltering(true);
        router.get(
            '/finance',
            { month: nextMonth, year: nextYear },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setIsFiltering(false),
            }
        );
    };

    const penjualan = realtime.penjualan || { total: 0, tunaiBank: 0, kredit: 0, akumulasi: 0 };
    const pembelian = realtime.pembelian || { total: 0, fakturBruto: 0, retur: 0, pbf: 0, cash: 0, konsinyasi: 0, akumulasi: 0 };
    const biaya = realtime.biaya || { total: 0, akumulasi: 0 };
    const pendapatan = realtime.pendapatan || { total: 0, pendapatanLain: 0, diskonOff: 0, akumulasi: 0 };
    const laba = realtime.laba || { total: 0, marginPersen: 0, akumulasi: 0 };
    const hpp = realtime.hpp || { persen: 0, total: 0, persenAkumulasi: 0, totalAkumulasi: 0 };
    const isProfitable = Number(laba.total || 0) >= 0;

    return (
        <FinanceLayout
            title="Ringkasan Keuangan"
            subtitle="Ringkasan eksekutif kas, hutang dagang, piutang, dan arus kas apotek"
            stats={stats}
        >
            <Head title="Ringkasan Keuangan - Apotek Sahabat" />

            <div className="finance-dashboard space-y-5">
                {/* Banner Peringatan Retur Beli */}
                {stats.returAlert && (stats.returAlert.total_count || 0) > 0 && (
                    <div className="bg-white p-4 rounded-2xl border border-amber-200 border-l-4 border-l-amber-500 shadow-2xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div className="flex items-center gap-3.5">
                            <div className="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                                <Undo2 className="w-5 h-5" />
                            </div>
                            <div>
                                <div className="flex items-center gap-2 flex-wrap">
                                    <span className="font-bold text-slate-900 text-sm">Peringatan Ketentuan Retur Beli Distributor</span>
                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                        {stats.returAlert.total_count} Obat Terdeteksi
                                    </span>
                                </div>
                                <p className="text-xs text-slate-600 mt-0.5">
                                    Ada <strong className="text-emerald-700">{stats.returAlert.eligible_count} obat siap diretur sekarang</strong> dan <strong className="text-amber-700">{stats.returAlert.approaching_count} obat mendekati batas retur</strong> ({stats.returAlert.total_creditors} PBF).
                                </p>
                            </div>
                        </div>
                        <Link
                            href="/finance/purchase-returns?tab=approaching"
                            className="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-semibold transition shrink-0"
                        >
                            <span>Buka Rekomendasi Retur</span>
                            <ArrowRight className="w-3.5 h-3.5" />
                        </Link>
                    </div>
                )}

                {/* BAR FILTER PERIODE (BULAN & TAHUN) */}
                <div className="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div className="flex items-center gap-2 text-xs text-slate-600">
                        <Calendar className="w-4 h-4 text-slate-400 shrink-0" />
                        <span>Periode:</span>
                        <strong className="text-slate-900">{realtime.periodLabel || 'Bulan Ini'}</strong>
                        {isFiltering && (
                            <span className="text-[11px] text-blue-600 font-medium animate-pulse ml-1">
                                Memuat...
                            </span>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        <select
                            value={selectedMonth}
                            onChange={(e) => handlePeriodChange(Number(e.target.value), selectedYear)}
                            className="px-3 py-1.5 text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl text-slate-700 hover:bg-slate-100 focus:outline-hidden focus:border-blue-500 cursor-pointer"
                            aria-label="Pilih Bulan"
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
                            className="px-3 py-1.5 text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl text-slate-700 hover:bg-slate-100 focus:outline-hidden focus:border-blue-500 cursor-pointer"
                            aria-label="Pilih Tahun"
                        >
                            {yearOptions.map((y) => (
                                <option key={y} value={y}>
                                    {y}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* BARIS 1: PENJUALAN, PEMBELIAN, BIAYA, PENDAPATAN, LABA, PRESENTASE HPP */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                    {/* 1. Penjualan */}
                    <Link
                        href="/finance/cashflow?tab=sheet"
                        className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:border-emerald-300 hover:shadow-xs transition group flex flex-col justify-between"
                    >
                        <div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Penjualan</span>
                                <div className="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:scale-105 transition">
                                    <ShoppingBag className="w-5 h-5" />
                                </div>
                            </div>
                            <p className="text-xl sm:text-2xl font-black text-slate-900 mt-2 tracking-tight tabular-nums">
                                {formatRupiah(penjualan.total)}
                            </p>
                            <p className="text-[11px] text-slate-500 font-medium mt-0.5">
                                Akumulasi: <span className="font-semibold text-slate-700">{formatRupiah(penjualan.akumulasi || 0)}</span>
                            </p>
                        </div>

                        <div className="mt-4 pt-3 border-t border-slate-100 space-y-1.5 text-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-slate-500">Tunai &amp; Bank</span>
                                <span className="font-semibold text-slate-800 tabular-nums">{formatRupiah(penjualan.tunaiBank)}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-slate-500">Kredit</span>
                                <span className="font-semibold text-slate-800 tabular-nums">{formatRupiah(penjualan.kredit)}</span>
                            </div>
                        </div>
                    </Link>

                    {/* 2. Pembelian */}
                    <Link
                        href="/finance/cashflow?tab=kinerja"
                        className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:border-blue-300 hover:shadow-xs transition group flex flex-col justify-between"
                    >
                        <div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pembelian</span>
                                <div className="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:scale-105 transition">
                                    <PackageCheck className="w-5 h-5" />
                                </div>
                            </div>
                            <p className="text-xl sm:text-2xl font-black text-slate-900 mt-2 tracking-tight tabular-nums">
                                {formatRupiah(pembelian.total)}
                            </p>
                            <p className="text-[11px] text-slate-500 font-medium mt-0.5">
                                Akumulasi: <span className="font-semibold text-slate-700">{formatRupiah(pembelian.akumulasi || 0)}</span>
                            </p>
                        </div>

                        <div className="mt-4 pt-3 border-t border-slate-100 space-y-1.5 text-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-slate-500">Total Faktur</span>
                                <span className="font-semibold text-slate-800 tabular-nums">{formatRupiah(pembelian.fakturBruto)}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-slate-500">Retur Beli</span>
                                <span className="font-semibold text-rose-600 tabular-nums">−{formatRupiah(pembelian.retur)}</span>
                            </div>
                        </div>
                    </Link>

                    {/* 3. Biaya */}
                    <Link
                        href="/finance/biaya"
                        className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:border-rose-300 hover:shadow-xs transition group flex flex-col justify-between"
                    >
                        <div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Biaya</span>
                                <div className="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center group-hover:scale-105 transition">
                                    <Wallet className="w-5 h-5" />
                                </div>
                            </div>
                            <p className="text-xl sm:text-2xl font-black text-rose-700 mt-2 tracking-tight tabular-nums">
                                {formatRupiah(biaya.total)}
                            </p>
                            <p className="text-[11px] text-slate-500 font-medium mt-0.5">
                                Akumulasi: <span className="font-semibold text-rose-600">{formatRupiah(biaya.akumulasi || 0)}</span>
                            </p>
                            <p className="text-xs text-slate-500 mt-1">
                                Pengeluaran biaya operasional
                            </p>
                        </div>

                        <div className="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-rose-600">
                            <span>Catat Biaya</span>
                            <ChevronRight className="w-3.5 h-3.5 group-hover:translate-x-0.5 transition" />
                        </div>
                    </Link>

                    {/* 4. Pendapatan */}
                    <Link
                        href="/finance/pendapatan-lain-lain"
                        className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:border-teal-300 hover:shadow-xs transition group flex flex-col justify-between"
                    >
                        <div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pendapatan</span>
                                <div className="w-9 h-9 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center group-hover:scale-105 transition">
                                    <CircleDollarSign className="w-5 h-5" />
                                </div>
                            </div>
                            <p className="text-xl sm:text-2xl font-black text-teal-700 mt-2 tracking-tight tabular-nums">
                                {formatRupiah(pendapatan.total)}
                            </p>
                            <p className="text-[11px] text-slate-500 font-medium mt-0.5">
                                Akumulasi: <span className="font-semibold text-teal-700">{formatRupiah(pendapatan.akumulasi || 0)}</span>
                            </p>
                        </div>

                        <div className="mt-4 pt-3 border-t border-slate-100 space-y-2 text-xs">
                            <div className="space-y-1">
                                <div className="flex items-center justify-between">
                                    <span className="text-slate-500">Lain-lain</span>
                                    <span className="font-semibold text-slate-800 tabular-nums">{formatRupiah(pendapatan.pendapatanLain)}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-slate-500">Diskon Off</span>
                                    <span className="font-semibold text-indigo-600 tabular-nums">{formatRupiah(pendapatan.diskonOff)}</span>
                                </div>
                            </div>
                            <div className="pt-2 border-t border-slate-100 flex items-center justify-between font-semibold text-teal-600">
                                <span>Tambahkan Pendapatan</span>
                                <ChevronRight className="w-3.5 h-3.5 group-hover:translate-x-0.5 transition" />
                            </div>
                        </div>
                    </Link>

                    {/* 5. Laba */}
                    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Laba</span>
                                <div className={`w-9 h-9 rounded-xl flex items-center justify-center ${isProfitable ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'}`}>
                                    {isProfitable ? <TrendingUp className="w-5 h-5" /> : <TrendingDown className="w-5 h-5" />}
                                </div>
                            </div>
                            <p className={`text-xl sm:text-2xl font-black mt-2 tracking-tight tabular-nums ${isProfitable ? 'text-emerald-700' : 'text-rose-600'}`}>
                                {isProfitable ? '' : '−'}{formatRupiah(Math.abs(laba.total))}
                            </p>
                            <p className="text-[11px] text-slate-500 font-medium mt-0.5">
                                Akumulasi: <span className={`font-semibold ${Number(laba.akumulasi || 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'}`}>
                                    {Number(laba.akumulasi || 0) < 0 ? '−' : ''}{formatRupiah(Math.abs(laba.akumulasi || 0))}
                                </span>
                            </p>
                        </div>

                        <div className="mt-4 pt-3 border-t border-slate-100 space-y-1.5 text-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-slate-500">Persentase Laba</span>
                                <span className={`font-semibold tabular-nums ${isProfitable ? 'text-emerald-700' : 'text-rose-600'}`}>
                                    {laba.marginPersen}%
                                </span>
                            </div>
                            <p className="text-[11px] text-slate-400 leading-snug">
                                Penjualan − Pembelian − Biaya + Pendapatan
                            </p>
                        </div>
                    </div>

                    {/* 6. Presentase HPP */}
                    <Link
                        href="/finance/cashflow?tab=kinerja"
                        className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs hover:border-indigo-300 hover:shadow-xs transition group flex flex-col justify-between"
                    >
                        <div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Presentase HPP</span>
                                <div className="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:scale-105 transition">
                                    <Percent className="w-5 h-5" />
                                </div>
                            </div>
                            <p className="text-xl sm:text-2xl font-black text-indigo-700 mt-2 tracking-tight tabular-nums">
                                {hpp.persen}%
                            </p>
                            <p className="text-[11px] text-slate-500 font-medium mt-0.5">
                                Akumulasi: <span className="font-semibold text-indigo-600">{hpp.persenAkumulasi}%</span>
                            </p>
                        </div>

                        <div className="mt-4 pt-3 border-t border-slate-100 space-y-1.5 text-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-slate-500">Nominal HPP</span>
                                <span className="font-semibold text-slate-800 tabular-nums">{formatRupiah(pembelian.total)}</span>
                            </div>
                            <p className="text-[11px] text-slate-400 leading-snug">
                                Total Pembelian / Penjualan
                            </p>
                        </div>
                    </Link>
                </div>

                {/* BARIS 2: SALDO KAS & BANK, HUTANG DAGANG, PIUTANG DAGANG, NILAI STOK */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* 1. Saldo Kas & Bank */}
                    <Link
                        href="/finance/kas-bank"
                        className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-2xs hover:border-blue-300 hover:shadow-xs transition group flex flex-col justify-between"
                    >
                        <div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Saldo Kas &amp; Bank</span>
                                <div className="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:scale-105 transition">
                                    <Landmark className="w-5 h-5" />
                                </div>
                            </div>
                            <p className={`text-xl sm:text-2xl font-black mt-2 tracking-tight tabular-nums ${Number(stats.totalSaldoKasBank || 0) < 0 ? 'text-rose-600' : 'text-slate-900'}`}>
                                {formatRupiah(stats.totalSaldoKasBank || 0)}
                            </p>
                        </div>
                        <div className="text-[11px] text-blue-600 font-medium mt-2 pt-2 border-t border-slate-100 flex items-center justify-between">
                            <span>Lihat {stats.accountsCount || accounts.length || 0} akun kas &amp; bank</span>
                            <ChevronRight className="w-3.5 h-3.5 group-hover:translate-x-0.5 transition" />
                        </div>
                    </Link>

                    {/* 2. Hutang Dagang (Pembelian) */}
                    <Link
                        href="/finance/hutang"
                        className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-2xs hover:border-rose-300 hover:shadow-xs transition group flex flex-col justify-between"
                    >
                        <div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Hutang Dagang (Pembelian)</span>
                                <div className="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center group-hover:scale-105 transition">
                                    <CreditCard className="w-5 h-5" />
                                </div>
                            </div>
                            <p className="text-xl sm:text-2xl font-black text-rose-600 mt-2 tracking-tight tabular-nums">
                                {formatRupiah(stats.totalSisaHutang || 0)}
                            </p>
                        </div>
                        <div className="text-[11px] text-rose-600 font-medium mt-2 pt-2 border-t border-slate-100 flex items-center justify-between">
                            <span>{stats.countHutangBelumLunas || 0} Faktur Belum Lunas</span>
                            <ChevronRight className="w-3.5 h-3.5 group-hover:translate-x-0.5 transition" />
                        </div>
                    </Link>

                    {/* 3. Piutang Dagang (Penjualan Kredit) */}
                    <Link
                        href="/finance/piutang"
                        className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-2xs hover:border-amber-300 hover:shadow-xs transition group flex flex-col justify-between"
                    >
                        <div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Piutang Dagang (Penjualan Kredit)</span>
                                <div className="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:scale-105 transition">
                                    <ShoppingCart className="w-5 h-5" />
                                </div>
                            </div>
                            <p className="text-xl sm:text-2xl font-black text-amber-700 mt-2 tracking-tight tabular-nums">
                                {formatRupiah(stats.totalSisaPiutang || 0)}
                            </p>
                        </div>
                        <div className="text-[11px] text-amber-600 font-medium mt-2 pt-2 border-t border-slate-100 flex items-center justify-between">
                            <span>{stats.countPiutangBelumBayar || 0} Tagihan Belum Dibayar</span>
                            <ChevronRight className="w-3.5 h-3.5 group-hover:translate-x-0.5 transition" />
                        </div>
                    </Link>

                    {/* 4. Nilai Stok */}
                    <Link
                        href="/stock-data"
                        className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-2xs hover:border-purple-300 hover:shadow-xs transition group flex flex-col justify-between"
                    >
                        <div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Nilai Stok</span>
                                <div className="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center group-hover:scale-105 transition">
                                    <Boxes className="w-5 h-5" />
                                </div>
                            </div>
                            <p className="text-xl sm:text-2xl font-black text-purple-700 mt-2 tracking-tight tabular-nums">
                                {formatRupiah(stats.totalNilaiStok || stats.nilaiStok?.total || 0)}
                            </p>
                        </div>
                        <div className="mt-2 pt-2 border-t border-slate-100 text-[11px]">
                            {stats.nilaiStok?.isPmi ? (
                                <div className="space-y-0.5 text-slate-600">
                                    <div className="flex items-center justify-between">
                                        <span>Etalase:</span>
                                        <span className="font-semibold text-slate-800 tabular-nums">{formatRupiah(stats.nilaiStok?.etalase || 0)}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span>Gudang:</span>
                                        <span className="font-semibold text-slate-800 tabular-nums">{formatRupiah(stats.nilaiStok?.gudang || 0)}</span>
                                    </div>
                                </div>
                            ) : (
                                <div className="text-purple-600 font-medium flex items-center justify-between">
                                    <span>Stok Etalase ({stats.nilaiStok?.totalItems || 0} Obat)</span>
                                    <ChevronRight className="w-3.5 h-3.5 group-hover:translate-x-0.5 transition" />
                                </div>
                            )}
                        </div>
                    </Link>
                </div>

                {/* BARIS 3: RINGKASAN ARUS KAS & HUTANG JATUH TEMPO */}
                <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    {/* Ringkasan Arus Kas Masuk vs Keluar */}
                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 className="font-bold text-slate-900 text-sm">Ringkasan Arus Kas</h3>
                            <Link
                                href="/finance/kas-bank"
                                className="text-xs text-blue-600 font-semibold hover:underline flex items-center gap-0.5"
                            >
                                Buku Kas <ChevronRight className="w-3 h-3" />
                            </Link>
                        </div>

                        <CashFlowChart incoming={stats.kasMasuk} outgoing={stats.kasKeluar} />

                        <div className="space-y-2.5">
                            <div className="p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-100 flex items-center justify-between">
                                <div className="flex items-center gap-2.5">
                                    <div className="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center">
                                        <ArrowDownLeft className="w-4 h-4" />
                                    </div>
                                    <div>
                                        <div className="text-xs font-bold text-emerald-950">Kas Masuk (Debit)</div>
                                        <div className="text-[10px] text-emerald-700">Piutang, penjualan &amp; pendapatan</div>
                                    </div>
                                </div>
                                <div className="text-right font-bold text-emerald-700 text-sm tabular-nums">
                                    +{formatRupiah(stats.kasMasuk || 0)}
                                </div>
                            </div>

                            <div className="p-3.5 rounded-xl bg-rose-50/70 border border-rose-100 flex items-center justify-between">
                                <div className="flex items-center gap-2.5">
                                    <div className="w-8 h-8 rounded-lg bg-rose-600 text-white flex items-center justify-center">
                                        <ArrowUpRight className="w-4 h-4" />
                                    </div>
                                    <div>
                                        <div className="text-xs font-bold text-rose-950">Kas Keluar (Kredit)</div>
                                        <div className="text-[10px] text-rose-700">Hutang, pembelian tunai &amp; biaya</div>
                                    </div>
                                </div>
                                <div className="text-right font-bold text-rose-700 text-sm tabular-nums">
                                    −{formatRupiah(stats.kasKeluar || 0)}
                                </div>
                            </div>

                            <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                                <div className="flex items-center gap-2.5">
                                    <div className={`w-8 h-8 rounded-lg flex items-center justify-center ${Number(stats.netCashflow || 0) >= 0 ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'}`}>
                                        {Number(stats.netCashflow || 0) >= 0 ? <TrendingUp className="w-4 h-4" /> : <TrendingDown className="w-4 h-4" />}
                                    </div>
                                    <div>
                                        <div className="text-xs font-bold text-slate-900">Arus Bersih (Net Flow)</div>
                                        <div className="text-[10px] text-slate-500">Selisih kas masuk &amp; keluar</div>
                                    </div>
                                </div>
                                <div className={`text-right font-bold text-sm tabular-nums ${Number(stats.netCashflow || 0) >= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>
                                    {Number(stats.netCashflow || 0) >= 0 ? '+ ' : ''}{formatRupiah(stats.netCashflow || 0)}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Faktur Hutang Jatuh Tempo Terdekat */}
                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div className="flex items-center gap-2">
                                <AlertTriangle className="w-4 h-4 text-amber-500" />
                                <h3 className="font-bold text-slate-900 text-sm">Faktur Hutang Jatuh Tempo Terdekat</h3>
                            </div>
                            <Link
                                href="/finance/hutang"
                                className="text-xs text-blue-600 font-semibold hover:underline flex items-center gap-0.5"
                            >
                                Kelola Hutang <ArrowRight className="w-3.5 h-3.5" />
                            </Link>
                        </div>

                        {upcomingHutang.length > 0 ? (
                            <div className="divide-y divide-slate-100">
                                {upcomingHutang.map((h) => (
                                    <div key={h.id} className="finance-due-row py-2.5 flex items-center justify-between text-xs hover:bg-slate-50/60 px-2 rounded-lg transition">
                                        <div className="space-y-0.5">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-slate-900">{h.no_faktur || h.no_penerimaan}</span>
                                                <span className={`px-2 py-0.5 rounded-full text-[10px] font-semibold ${h.is_overdue ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'}`}>
                                                    {!h.due_date || h.due_date === '-' ? 'Tanggal belum tersedia' : h.is_overdue ? `Terlambat ${h.days_diff} hari` : h.days_diff === 0 ? 'Jatuh tempo hari ini' : `${h.days_diff} hari lagi`}
                                                </span>
                                            </div>
                                            <div className="text-slate-500 text-[11px]">
                                                PBF: <strong className="text-slate-700">{h.pbf_name}</strong> &bull; Jatuh Tempo: {h.due_date}
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <div className="font-bold text-red-600 text-sm tabular-nums">
                                                {formatRupiah(h.sisa_bayar)}
                                            </div>
                                            <Link
                                                href={`/finance/hutang?search=${encodeURIComponent(h.no_faktur || h.no_penerimaan || '')}`}
                                                className="text-[11px] text-blue-600 font-semibold hover:underline"
                                            >
                                                Bayar
                                            </Link>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="py-8 text-center text-slate-400 text-xs">
                                Tidak ada tagihan hutang yang mendekati jatuh tempo atau belum lunas.
                            </div>
                        )}
                    </div>
                </div>

                {/* BARIS 4: TABEL MUTASI KAS & BANK TERKINI */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
                    <div className="p-5 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 className="font-bold text-slate-900 text-sm">Mutasi Kas &amp; Bank Terkini</h3>
                            <p className="text-xs text-slate-400 mt-0.5">Riwayat 10 transaksi pembayaran &amp; kas terakhir</p>
                        </div>
                        <Link
                            href="/finance/kas-bank"
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-xl transition"
                        >
                            <span>Buku Kas Lengkap</span>
                            <ArrowRight className="w-3.5 h-3.5" />
                        </Link>
                    </div>

                    <div className="overflow-x-auto" tabIndex={0} role="region" aria-label="Tabel mutasi kas, geser untuk melihat semua kolom">
                        <table className="finance-transactions w-full text-left text-xs">
                            <thead className="bg-[#F8FAFC] border-b border-slate-200 text-slate-600 font-semibold">
                                <tr>
                                    <th className="px-4 py-3">Tanggal</th>
                                    <th className="px-4 py-3">No. Bukti</th>
                                    <th className="px-4 py-3">Rekening</th>
                                    <th className="px-4 py-3">Kategori</th>
                                    <th className="px-4 py-3">Pihak Terkait</th>
                                    <th className="px-4 py-3 text-right">Debit (Masuk)</th>
                                    <th className="px-4 py-3 text-right">Kredit (Keluar)</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {recentMutasi.length > 0 ? (
                                    recentMutasi.map((m) => (
                                        <tr key={m.id} className="hover:bg-slate-50/70 transition">
                                            <td className="px-4 py-3 font-medium text-slate-700 whitespace-nowrap">{m.date}</td>
                                            <td className="px-4 py-3 font-semibold text-slate-900 whitespace-nowrap">
                                                <span className="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-medium border border-slate-200/70">
                                                    {m.document_no}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap font-medium text-slate-800">{m.account_name}</td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <TransactionCategory type={m.category_type} />
                                            </td>
                                            <td className="px-4 py-3 font-medium text-slate-800">{m.party}</td>
                                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                                {m.direction === 'IN' ? (
                                                    <span className="font-bold text-emerald-600 inline-flex items-center gap-1">
                                                        <ArrowDownLeft className="w-3 h-3" />+{formatRupiah(m.amount)}
                                                    </span>
                                                ) : '-'}
                                            </td>
                                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                                {m.direction === 'OUT' ? (
                                                    <span className="font-bold text-rose-600 inline-flex items-center gap-1">
                                                        <ArrowUpRight className="w-3 h-3" />-{formatRupiah(m.amount)}
                                                    </span>
                                                ) : '-'}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-8 text-center text-slate-400">
                                            Belum ada mutasi transaksi kas &amp; bank yang tercatat.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </FinanceLayout>
    );
}
