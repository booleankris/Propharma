import React from 'react';
import { Head, Link } from '@inertiajs/react';
import FinanceLayout from './Layouts/FinanceLayout';
import { formatRupiah } from './Components/Utils';
import {
    CreditCard,
    Banknote,
    Landmark,
    ShoppingCart,
    ArrowDownLeft,
    ArrowUpRight,
    TrendingUp,
    TrendingDown,
    AlertTriangle,
    ChevronRight,
    Receipt,
    Calendar,
    ArrowRight,
} from 'lucide-react';

export default function Dashboard({
    stats = {},
    recentMutasi = [],
    upcomingHutang = [],
    accounts = [],
}) {
    return (
        <FinanceLayout
            title="Ringkasan Keuangan"
            subtitle="Ringkasan eksekutif kas, hutang dagang, piutang, dan arus kas apotek"
            stats={stats}
        >
            <Head title="Ringkasan Keuangan - Apotek Sahabat" />

            <div className="space-y-6">
                {/* 1. TOP EXECUTIVE KPI CARDS */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Total Saldo Kas & Bank */}
                    <Link
                        href="/finance/kas-bank"
                        className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:border-blue-400 hover:shadow-md transition group block"
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500">Saldo Kas & Bank</span>
                            <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:scale-105 transition">
                                <Landmark className="w-5 h-5" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className={`text-2xl font-bold tracking-tight ${Number(stats.totalSaldoKasBank || 0) < 0 ? 'text-rose-600' : 'text-slate-900'}`}>
                                {formatRupiah(stats.totalSaldoKasBank || 0)}
                            </div>
                            <div className="text-[11px] text-blue-600 font-medium mt-1 flex items-center gap-1">
                                <span>Lihat {stats.accountsCount || accounts.length || 0} akun kas & bank</span>
                                <ChevronRight className="w-3 h-3 group-hover:translate-x-0.5 transition" />
                            </div>
                        </div>
                    </Link>

                    {/* Sisa Hutang Dagang */}
                    <Link
                        href="/finance/hutang"
                        className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:border-red-400 hover:shadow-md transition group block"
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500">Sisa Hutang Dagang</span>
                            <div className="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center group-hover:scale-105 transition">
                                <CreditCard className="w-5 h-5" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className="text-2xl font-bold text-red-600 tracking-tight">
                                {formatRupiah(stats.totalSisaHutang || 0)}
                            </div>
                            <div className="text-[11px] text-red-600 font-medium mt-1 flex items-center gap-1">
                                <span>{stats.countHutangBelumLunas || 0} Faktur Belum Lunas</span>
                                <ChevronRight className="w-3 h-3 group-hover:translate-x-0.5 transition" />
                            </div>
                        </div>
                    </Link>

                    {/* Pembelian Cash */}
                    <Link
                        href="/finance/cash"
                        className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:border-emerald-400 hover:shadow-md transition group block"
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500">Pembelian Cash (Tunai)</span>
                            <div className="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:scale-105 transition">
                                <Banknote className="w-5 h-5" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className="text-2xl font-bold text-slate-900 tracking-tight">
                                {formatRupiah(stats.totalCashSpent || 0)}
                            </div>
                            <div className="text-[11px] text-emerald-600 font-medium mt-1 flex items-center gap-1">
                                <span>{stats.totalCashCount || 0} Faktur Tunai</span>
                                <ChevronRight className="w-3 h-3 group-hover:translate-x-0.5 transition" />
                            </div>
                        </div>
                    </Link>

                    {/* Sisa Piutang Penjualan */}
                    <Link
                        href="/finance/piutang"
                        className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:border-amber-400 hover:shadow-md transition group block"
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500">Piutang Penjualan</span>
                            <div className="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:scale-105 transition">
                                <ShoppingCart className="w-5 h-5" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className="text-2xl font-bold text-amber-700 tracking-tight">
                                {formatRupiah(stats.totalSisaPiutang || 0)}
                            </div>
                            <div className="text-[11px] text-amber-600 font-medium mt-1 flex items-center gap-1">
                                <span>{stats.countPiutangBelumBayar || 0} Tagihan Belum Dibayar</span>
                                <ChevronRight className="w-3 h-3 group-hover:translate-x-0.5 transition" />
                            </div>
                        </div>
                    </Link>
                </div>

                {/* 2. ARUS KAS & NAVIGATION QUICK LAUNCHER */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Ringkasan Arus Kas Masuk vs Keluar */}
                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 className="font-bold text-slate-900 text-sm">Ringkasan Arus Kas</h3>
                            <Link
                                href="/finance/kas-bank"
                                className="text-xs text-blue-600 font-semibold hover:underline flex items-center gap-0.5"
                            >
                                Buku Kas <ChevronRight className="w-3 h-3" />
                            </Link>
                        </div>

                        <div className="space-y-3">
                            <div className="p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-100 flex items-center justify-between">
                                <div className="flex items-center gap-2.5">
                                    <div className="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center">
                                        <ArrowDownLeft className="w-4 h-4" />
                                    </div>
                                    <div>
                                        <div className="text-xs font-bold text-emerald-950">Kas Masuk (Debit)</div>
                                        <div className="text-[10px] text-emerald-700">Piutang & penjualan</div>
                                    </div>
                                </div>
                                <div className="text-right font-bold text-emerald-700 text-sm">
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
                                        <div className="text-[10px] text-rose-700">Hutang & pembelian</div>
                                    </div>
                                </div>
                                <div className="text-right font-bold text-rose-700 text-sm">
                                    -{formatRupiah(stats.kasKeluar || 0)}
                                </div>
                            </div>

                            <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                                <div className="flex items-center gap-2.5">
                                    <div className={`w-8 h-8 rounded-lg flex items-center justify-center ${stats.netCashflow >= 0 ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'}`}>
                                        {stats.netCashflow >= 0 ? <TrendingUp className="w-4 h-4" /> : <TrendingDown className="w-4 h-4" />}
                                    </div>
                                    <div>
                                        <div className="text-xs font-bold text-slate-900">Arus Bersih (Net Flow)</div>
                                        <div className="text-[10px] text-slate-500">Selisih masuk & keluar</div>
                                    </div>
                                </div>
                                <div className={`text-right font-bold text-sm ${stats.netCashflow >= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>
                                    {stats.netCashflow >= 0 ? '+ ' : ''}{formatRupiah(stats.netCashflow || 0)}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Hutang Jatuh Tempo Terdekat */}
                    <div className="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
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
                                    <div key={h.id} className="py-2.5 flex items-center justify-between text-xs hover:bg-slate-50/60 px-2 rounded-lg transition">
                                        <div className="space-y-0.5">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-slate-900">{h.no_faktur || h.no_penerimaan}</span>
                                                <span className={`px-2 py-0.5 rounded-full text-[10px] font-semibold ${h.is_overdue ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'}`}>
                                                    {h.is_overdue ? `Terlambat ${h.days_diff} hari` : `${h.days_diff} hari lagi`}
                                                </span>
                                            </div>
                                            <div className="text-slate-500 text-[11px]">
                                                PBF: <strong className="text-slate-700">{h.pbf_name}</strong> • Jatuh Tempo: {h.due_date}
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <div className="font-bold text-red-600 text-sm">
                                                {formatRupiah(h.sisa_bayar)}
                                            </div>
                                            <Link
                                                href={`/finance/hutang?search=${h.no_faktur || h.no_penerimaan}`}
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

                {/* 3. RECENT MUTASI TABLE */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div className="p-5 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 className="font-bold text-slate-900 text-sm">Mutasi Kas & Bank Terkini</h3>
                            <p className="text-xs text-slate-400 mt-0.5">Riwayat 10 transaksi pembayaran & kas terakhir</p>
                        </div>
                        <Link
                            href="/finance/kas-bank"
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-xl transition"
                        >
                            <span>Buku Kas Lengkap</span>
                            <ArrowRight className="w-3.5 h-3.5" />
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
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
                                                {m.category_type === 'PIUTANG' && (
                                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                        Piutang Penjualan
                                                    </span>
                                                )}
                                                {m.category_type === 'CASH' && (
                                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        Pembelian Cash
                                                    </span>
                                                )}
                                                {m.category_type === 'KREDIT' && (
                                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                                        Hutang Dagang
                                                    </span>
                                                )}
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
                                        <td colSpan={7} className="text-center py-8 text-slate-400">
                                            Belum ada transaksi mutasi kas & bank.
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
