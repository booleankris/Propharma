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

function MetaRow({ label, value, valueClassName = 'text-slate-800' }) {
    return (
        <div className="fin-meta-row">
            <dt>{label}</dt>
            <dd className={valueClassName}>{value}</dd>
        </div>
    );
}

function DashboardCard({
    href,
    title,
    icon: Icon,
    iconTone,
    value,
    valueClassName = 'text-slate-900',
    valueSizeClass = 'fin-kpi-value',
    accumulation,
    note,
    rows,
    footerLabel,
    footerTone = 'text-slate-600',
    formula,
}) {
    const content = (
        <article className="fin-kpi-card">
            <header className="fin-kpi-header">
                <h2 className="fin-kpi-title">{title}</h2>
                <span className={`fin-kpi-icon ${iconTone}`} aria-hidden="true">
                    <Icon className="w-4 h-4" />
                </span>
            </header>

            <div className="fin-kpi-primary">
                <p className={`${valueSizeClass} ${valueClassName}`}>{value}</p>
                {accumulation && <div className="fin-kpi-sub">{accumulation}</div>}
                {note && <p className="text-xs text-slate-500 mt-1.5 leading-snug">{note}</p>}
            </div>

            {rows && rows.length > 0 && (
                <dl className="fin-kpi-breakdown">
                    {rows.map((row) => (
                        <MetaRow
                            key={row.label}
                            label={row.label}
                            value={row.value}
                            valueClassName={row.valueClassName}
                        />
                    ))}
                </dl>
            )}

            {formula && <p className="fin-kpi-formula">{formula}</p>}

            {footerLabel && (
                <footer className={`fin-kpi-footer ${footerTone}`}>
                    <span>{footerLabel}</span>
                    <ChevronRight className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                </footer>
            )}
        </article>
    );

    if (href) {
        return (
            <Link href={href} className="fin-card-link">
                {content}
            </Link>
        );
    }

    return content;
}

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
    const laba = realtime.laba || { total: 0, labaTanpaStok: 0, selisihPersediaan: 0, marginPersen: 0, akumulasi: 0 };
    const hpp = realtime.hpp || { persen: 0, total: 0, persenAkumulasi: 0, totalAkumulasi: 0 };
    const isProfitable = Number(laba.total || 0) >= 0;
    const isAccumulationProfitable = Number(laba.akumulasi || 0) >= 0;
    const labaTanpaStok = Number(laba.labaTanpaStok || 0);
    const selisihPersediaan = Number(laba.selisihPersediaan || 0);
    const netCashflow = Number(stats.netCashflow || 0);

    return (
        <FinanceLayout
            title="Ringkasan Keuangan"
            subtitle="Ringkasan eksekutif kas, hutang dagang, piutang, dan arus kas apotek"
            stats={stats}
        >
            <Head title="Ringkasan Keuangan - Apotek Sahabat" />

            <div className="finance-dashboard">
                {/* Banner Peringatan Retur Beli */}
                {stats.returAlert && (stats.returAlert.total_count || 0) > 0 && (
                    <section className="fin-alert-banner" aria-label="Peringatan ketentuan retur beli">
                        <div className="flex items-start sm:items-center gap-3 min-w-0 flex-1">
                            <div className="w-9 h-9 rounded-lg bg-amber-50 border border-amber-200/70 text-amber-700 flex items-center justify-center shrink-0">
                                <Undo2 className="w-4 h-4" />
                            </div>
                            <div className="min-w-0">
                                <div className="flex items-center gap-2 flex-wrap">
                                    <h2 className="font-bold text-slate-900 text-sm">
                                        Peringatan Ketentuan Retur Beli Distributor
                                    </h2>
                                    <span className="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200/80 fin-tabular">
                                        {stats.returAlert.total_count} Obat Terdeteksi
                                    </span>
                                </div>
                                <p className="text-xs text-slate-600 mt-0.5 leading-relaxed">
                                    Ada <strong className="text-emerald-700">{stats.returAlert.eligible_count} obat siap diretur sekarang</strong> dan{' '}
                                    <strong className="text-amber-700">{stats.returAlert.approaching_count} obat mendekati batas retur</strong> ({stats.returAlert.total_creditors} PBF).
                                </p>
                            </div>
                        </div>

                        <Link
                            href="/finance/purchase-returns?tab=approaching"
                            className="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-semibold transition w-full sm:w-auto shrink-0"
                        >
                            <span>Buka Rekomendasi Retur</span>
                            <ArrowRight className="w-3.5 h-3.5" />
                        </Link>
                    </section>
                )}

                {/* Filter Periode */}
                <section className="fin-period-bar" aria-label="Filter periode laporan">
                    <div className="flex items-center gap-2 text-xs text-slate-600 min-w-0">
                        <Calendar className="w-4 h-4 text-slate-400 shrink-0" aria-hidden="true" />
                        <span>Periode:</span>
                        <strong className="text-slate-900 font-semibold">{realtime.periodLabel || 'Bulan Ini'}</strong>
                        {isFiltering && (
                            <span className="text-[11px] text-blue-600 font-medium animate-pulse ml-1">
                                Memuat...
                            </span>
                        )}
                    </div>

                    <div className="flex items-center gap-2 w-full sm:w-auto">
                        <select
                            value={selectedMonth}
                            onChange={(e) => handlePeriodChange(Number(e.target.value), selectedYear)}
                            className="flex-1 sm:flex-none min-h-[36px] px-3 py-1.5 text-xs font-semibold bg-slate-50 border border-slate-200 rounded-lg text-slate-700 hover:bg-slate-100 focus:outline-none focus:border-blue-500 cursor-pointer"
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
                            className="min-h-[36px] px-3 py-1.5 text-xs font-semibold bg-slate-50 border border-slate-200 rounded-lg text-slate-700 hover:bg-slate-100 focus:outline-none focus:border-blue-500 cursor-pointer fin-tabular"
                            aria-label="Pilih Tahun"
                        >
                            {yearOptions.map((y) => (
                                <option key={y} value={y}>
                                    {y}
                                </option>
                            ))}
                        </select>
                    </div>
                </section>

                {/* Baris 1: KPI Kinerja Bulanan (adaptif 1 / 2 / 3 / 6 kolom berbasis lebar kontainer) */}
                <section aria-label="Ringkasan kinerja periode berjalan" className="fin-kpi-grid">
                    <DashboardCard
                        href="/finance/cashflow?tab=sheet"
                        title="Penjualan"
                        icon={ShoppingBag}
                        iconTone="bg-emerald-50 text-emerald-700 border border-emerald-200/70"
                        value={formatRupiah(penjualan.total)}
                        accumulation={(
                            <>
                                <span>Akumulasi:</span>
                                <strong className="font-semibold text-slate-700">{formatRupiah(penjualan.akumulasi || 0)}</strong>
                            </>
                        )}
                        rows={[
                            { label: 'Tunai & Bank', value: formatRupiah(penjualan.tunaiBank) },
                            { label: 'Kredit', value: formatRupiah(penjualan.kredit) },
                        ]}
                    />

                    <DashboardCard
                        href="/finance/cashflow?tab=kinerja"
                        title="Pembelian"
                        icon={PackageCheck}
                        iconTone="bg-blue-50 text-blue-700 border border-blue-200/70"
                        value={formatRupiah(pembelian.total)}
                        accumulation={(
                            <>
                                <span>Akumulasi:</span>
                                <strong className="font-semibold text-slate-700">{formatRupiah(pembelian.akumulasi || 0)}</strong>
                            </>
                        )}
                        rows={[
                            { label: 'Total Faktur', value: formatRupiah(pembelian.fakturBruto) },
                            { label: 'Retur Beli', value: `−${formatRupiah(pembelian.retur)}`, valueClassName: 'text-rose-600' },
                        ]}
                    />

                    <DashboardCard
                        href="/finance/biaya"
                        title="Biaya"
                        icon={Wallet}
                        iconTone="bg-rose-50 text-rose-700 border border-rose-200/70"
                        value={formatRupiah(biaya.total)}
                        valueClassName="text-rose-700"
                        accumulation={(
                            <>
                                <span>Akumulasi:</span>
                                <strong className="font-semibold text-rose-600">{formatRupiah(biaya.akumulasi || 0)}</strong>
                            </>
                        )}
                        note="Pengeluaran biaya operasional"
                        footerLabel="Catat Biaya"
                        footerTone="text-rose-600"
                    />

                    <DashboardCard
                        href="/finance/pendapatan-lain-lain"
                        title="Pendapatan"
                        icon={CircleDollarSign}
                        iconTone="bg-teal-50 text-teal-700 border border-teal-200/70"
                        value={formatRupiah(pendapatan.total)}
                        valueClassName="text-teal-700"
                        accumulation={(
                            <>
                                <span>Akumulasi:</span>
                                <strong className="font-semibold text-teal-700">{formatRupiah(pendapatan.akumulasi || 0)}</strong>
                            </>
                        )}
                        rows={[
                            { label: 'Lain-lain', value: formatRupiah(pendapatan.pendapatanLain) },
                            { label: 'Diskon Off', value: formatRupiah(pendapatan.diskonOff), valueClassName: 'text-indigo-600' },
                        ]}
                        footerLabel="Tambahkan Pendapatan"
                        footerTone="text-teal-700"
                    />

                    <DashboardCard
                        href="/finance/cashflow?tab=neraca"
                        title="Laba"
                        icon={isProfitable ? TrendingUp : TrendingDown}
                        iconTone={
                            isProfitable
                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/70'
                                : 'bg-rose-50 text-rose-700 border border-rose-200/70'
                        }
                        value={`${isProfitable ? '' : '−'}${formatRupiah(Math.abs(laba.total))}`}
                        valueClassName={isProfitable ? 'text-emerald-700' : 'text-rose-600'}
                        accumulation={(
                            <>
                                <span>Akumulasi:</span>
                                <strong className={`font-semibold ${isAccumulationProfitable ? 'text-emerald-700' : 'text-rose-600'}`}>
                                    {isAccumulationProfitable ? '' : '−'}{formatRupiah(Math.abs(laba.akumulasi || 0))}
                                </strong>
                            </>
                        )}
                        rows={[
                            {
                                label: 'Laba Tanpa Stok',
                                value: `${labaTanpaStok >= 0 ? '' : '−'}${formatRupiah(Math.abs(labaTanpaStok))}`,
                                valueClassName: labaTanpaStok >= 0 ? 'text-slate-700' : 'text-rose-600',
                            },
                            {
                                label: 'Selisih Persediaan',
                                value: `${selisihPersediaan >= 0 ? '' : '−'}${formatRupiah(Math.abs(selisihPersediaan))}`,
                                valueClassName: selisihPersediaan >= 0 ? 'text-emerald-700' : 'text-rose-600',
                            },
                            {
                                label: 'Persentase Laba',
                                value: `${laba.marginPersen}%`,
                                valueClassName: isProfitable ? 'text-emerald-700' : 'text-rose-600',
                            },
                        ]}
                        formula="Selisih Laba Dengan Stok (Neraca)"
                    />

                    <DashboardCard
                        href="/finance/cashflow?tab=kinerja"
                        title="Presentase HPP"
                        icon={Percent}
                        iconTone="bg-indigo-50 text-indigo-700 border border-indigo-200/70"
                        value={`${hpp.persen}%`}
                        valueClassName="text-indigo-700"
                        accumulation={(
                            <>
                                <span>Akumulasi:</span>
                                <strong className="font-semibold text-indigo-600">{hpp.persenAkumulasi}%</strong>
                            </>
                        )}
                        rows={[
                            { label: 'Nominal HPP', value: formatRupiah(pembelian.total) },
                        ]}
                        formula="Total Pembelian / Penjualan"
                    />
                </section>

                {/* Baris 2: Posisi Saldo Kas, Hutang, Piutang, Nilai Stok (adaptif 1 / 2 / 4 kolom) */}
                <section aria-label="Posisi saldo keuangan dan aset stok" className="fin-balance-grid">
                    <DashboardCard
                        href="/finance/kas-bank"
                        title="Saldo Kas & Bank"
                        icon={Landmark}
                        iconTone="bg-blue-50 text-blue-700 border border-blue-200/70"
                        value={formatRupiah(stats.totalSaldoKasBank || 0)}
                        valueClassName={Number(stats.totalSaldoKasBank || 0) < 0 ? 'text-rose-600' : 'text-slate-900'}
                        valueSizeClass="fin-balance-value"
                        footerLabel={`Lihat ${stats.accountsCount || accounts.length || 0} akun kas & bank`}
                        footerTone="text-blue-600"
                    />

                    <DashboardCard
                        href="/finance/hutang"
                        title="Hutang Dagang (Pembelian)"
                        icon={CreditCard}
                        iconTone="bg-rose-50 text-rose-700 border border-rose-200/70"
                        value={formatRupiah(stats.totalSisaHutang || 0)}
                        valueClassName="text-rose-600"
                        valueSizeClass="fin-balance-value"
                        footerLabel={`${stats.countHutangBelumLunas || 0} Faktur Belum Lunas`}
                        footerTone="text-rose-600"
                    />

                    <DashboardCard
                        href="/finance/piutang"
                        title="Piutang Dagang (Penjualan Kredit)"
                        icon={ShoppingCart}
                        iconTone="bg-amber-50 text-amber-700 border border-amber-200/70"
                        value={formatRupiah(stats.totalSisaPiutang || 0)}
                        valueClassName="text-amber-700"
                        valueSizeClass="fin-balance-value"
                        footerLabel={`${stats.countPiutangBelumBayar || 0} Tagihan Belum Dibayar`}
                        footerTone="text-amber-700"
                    />

                    <DashboardCard
                        href="/stock-data"
                        title="Nilai Stok"
                        icon={Boxes}
                        iconTone="bg-purple-50 text-purple-700 border border-purple-200/70"
                        value={formatRupiah(stats.totalNilaiStok || stats.nilaiStok?.total || 0)}
                        valueClassName="text-purple-700"
                        valueSizeClass="fin-balance-value"
                        rows={
                            stats.nilaiStok?.isPmi
                                ? [
                                    { label: 'Etalase', value: formatRupiah(stats.nilaiStok?.etalase || 0) },
                                    { label: 'Gudang', value: formatRupiah(stats.nilaiStok?.gudang || 0) },
                                ]
                                : undefined
                        }
                        footerLabel={
                            stats.nilaiStok?.isPmi
                                ? undefined
                                : `Stok Etalase (${stats.nilaiStok?.totalItems || 0} Obat)`
                        }
                        footerTone="text-purple-700"
                    />
                </section>

                {/* Baris 3: Ringkasan Arus Kas & Faktur Hutang Jatuh Tempo Terdekat */}
                <section aria-label="Arus kas dan tagihan jatuh tempo" className="fin-split-grid">
                    {/* Ringkasan Arus Kas */}
                    <div className="fin-panel">
                        <div className="fin-panel-header">
                            <h3 className="font-bold text-slate-900 text-sm">Ringkasan Arus Kas</h3>
                            <Link
                                href="/finance/kas-bank"
                                className="text-xs text-blue-600 font-semibold hover:underline inline-flex items-center gap-0.5 shrink-0"
                            >
                                <span>Buku Kas</span>
                                <ChevronRight className="w-3.5 h-3.5" />
                            </Link>
                        </div>

                        <div className="mt-4">
                            <CashFlowChart incoming={stats.kasMasuk} outgoing={stats.kasKeluar} />
                        </div>

                        <div className="fin-flow-summary">
                            <div className="fin-flow-stat">
                                <div className="flex items-center gap-1.5 text-emerald-700">
                                    <ArrowDownLeft className="w-3.5 h-3.5 shrink-0" />
                                    <span className="fin-flow-stat-label text-emerald-900">Kas Masuk (Debit)</span>
                                </div>
                                <div className="fin-flow-stat-value text-emerald-700">
                                    +{formatRupiah(stats.kasMasuk || 0)}
                                </div>
                                <p className="text-[11px] text-slate-500 mt-0.5">Piutang, penjualan &amp; pendapatan</p>
                            </div>

                            <div className="fin-flow-stat">
                                <div className="flex items-center gap-1.5 text-rose-700">
                                    <ArrowUpRight className="w-3.5 h-3.5 shrink-0" />
                                    <span className="fin-flow-stat-label text-rose-900">Kas Keluar (Kredit)</span>
                                </div>
                                <div className="fin-flow-stat-value text-rose-700">
                                    −{formatRupiah(stats.kasKeluar || 0)}
                                </div>
                                <p className="text-[11px] text-slate-500 mt-0.5">Hutang, pembelian tunai &amp; biaya</p>
                            </div>

                            <div className="fin-flow-stat">
                                <div className={`flex items-center gap-1.5 ${netCashflow >= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>
                                    {netCashflow >= 0 ? (
                                        <TrendingUp className="w-3.5 h-3.5 shrink-0" />
                                    ) : (
                                        <TrendingDown className="w-3.5 h-3.5 shrink-0" />
                                    )}
                                    <span className="fin-flow-stat-label text-slate-800">Arus Bersih (Net Flow)</span>
                                </div>
                                <div className={`fin-flow-stat-value ${netCashflow >= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>
                                    {netCashflow >= 0 ? '+ ' : ''}{formatRupiah(netCashflow)}
                                </div>
                                <p className="text-[11px] text-slate-500 mt-0.5">Selisih kas masuk &amp; keluar</p>
                            </div>
                        </div>
                    </div>

                    {/* Faktur Hutang Jatuh Tempo Terdekat */}
                    <div className="fin-panel">
                        <div className="fin-panel-header">
                            <div className="flex items-center gap-2 min-w-0">
                                <AlertTriangle className="w-4 h-4 text-amber-500 shrink-0" />
                                <h3 className="font-bold text-slate-900 text-sm">Faktur Hutang Jatuh Tempo Terdekat</h3>
                            </div>
                            <Link
                                href="/finance/hutang"
                                className="text-xs text-blue-600 font-semibold hover:underline inline-flex items-center gap-1 shrink-0"
                            >
                                <span>Kelola Hutang</span>
                                <ArrowRight className="w-3.5 h-3.5" />
                            </Link>
                        </div>

                        {upcomingHutang.length > 0 ? (
                            <div className="divide-y divide-slate-100 mt-1">
                                {upcomingHutang.map((h) => (
                                    <div key={h.id} className="fin-due-row">
                                        <div className="fin-due-info space-y-1">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="font-semibold text-slate-900 text-xs">
                                                    {h.no_faktur || h.no_penerimaan}
                                                </span>
                                                <span
                                                    className={`px-2 py-0.5 rounded-md text-[11px] font-semibold ${
                                                        h.is_overdue
                                                            ? 'bg-red-50 text-red-700 border border-red-200/80'
                                                            : 'bg-amber-50 text-amber-800 border border-amber-200/80'
                                                    }`}
                                                >
                                                    {!h.due_date || h.due_date === '-'
                                                        ? 'Tanggal belum tersedia'
                                                        : h.is_overdue
                                                          ? `Terlambat ${h.days_diff} hari`
                                                          : h.days_diff === 0
                                                            ? 'Jatuh tempo hari ini'
                                                            : `${h.days_diff} hari lagi`}
                                                </span>
                                            </div>
                                            <div className="text-slate-500 text-xs leading-snug">
                                                PBF: <strong className="text-slate-700 font-semibold">{h.pbf_name}</strong> &bull; Jatuh Tempo: {h.due_date}
                                            </div>
                                        </div>

                                        <div className="fin-due-amount">
                                            <div className="font-bold text-red-600 text-sm fin-tabular">
                                                {formatRupiah(h.sisa_bayar)}
                                            </div>
                                            <Link
                                                href={`/finance/hutang?search=${encodeURIComponent(h.no_faktur || h.no_penerimaan || '')}`}
                                                className="inline-flex items-center mt-0.5 text-xs text-blue-600 font-semibold hover:underline"
                                            >
                                                Bayar
                                            </Link>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="flex-1 flex items-center justify-center py-10 text-center text-slate-400 text-xs">
                                Tidak ada tagihan hutang yang mendekati jatuh tempo atau belum lunas.
                            </div>
                        )}
                    </div>
                </section>

                {/* Baris 4: Tabel Mutasi Kas & Bank Terkini */}
                <section className="fin-panel" aria-labelledby="mutasi-table-title">
                    <div className="fin-panel-header">
                        <div className="min-w-0">
                            <h3 id="mutasi-table-title" className="font-bold text-slate-900 text-sm">
                                Mutasi Kas &amp; Bank Terkini
                            </h3>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Riwayat 10 transaksi pembayaran &amp; kas terakhir
                            </p>
                        </div>

                        <Link
                            href="/finance/kas-bank"
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200/70 rounded-lg transition shrink-0"
                        >
                            <span>Buku Kas Lengkap</span>
                            <ArrowRight className="w-3.5 h-3.5" />
                        </Link>
                    </div>

                    <div
                        tabIndex={0}
                        role="region"
                        aria-labelledby="mutasi-table-title"
                        className="fin-table-wrapper"
                    >
                        <table className="finance-transactions">
                            <thead>
                                <tr>
                                    <th scope="col" className="text-left">Tanggal</th>
                                    <th scope="col" className="text-left">No. Bukti</th>
                                    <th scope="col" className="text-left">Rekening</th>
                                    <th scope="col" className="text-left">Kategori</th>
                                    <th scope="col" className="text-left">Pihak Terkait</th>
                                    <th scope="col" className="text-right">Debit (Masuk)</th>
                                    <th scope="col" className="text-right">Kredit (Keluar)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentMutasi.length > 0 ? (
                                    recentMutasi.map((m) => (
                                        <tr key={m.id} className="hover:bg-slate-50/70 transition">
                                            <th scope="row" className="text-slate-700 whitespace-nowrap">
                                                {m.date}
                                            </th>
                                            <td className="whitespace-nowrap">
                                                <span className="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-medium border border-slate-200/70">
                                                    {m.document_no}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap font-medium text-slate-800">
                                                {m.account_name}
                                            </td>
                                            <td className="whitespace-nowrap">
                                                <TransactionCategory type={m.category_type} />
                                            </td>
                                            <td className="font-medium text-slate-800">{m.party}</td>
                                            <td className="text-right whitespace-nowrap">
                                                {m.direction === 'IN' ? (
                                                    <span className="font-bold text-emerald-600 inline-flex items-center gap-1">
                                                        <ArrowDownLeft className="w-3 h-3" />+{formatRupiah(m.amount)}
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-400">-</span>
                                                )}
                                            </td>
                                            <td className="text-right whitespace-nowrap">
                                                {m.direction === 'OUT' ? (
                                                    <span className="font-bold text-rose-600 inline-flex items-center gap-1">
                                                        <ArrowUpRight className="w-3 h-3" />-{formatRupiah(m.amount)}
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-400">-</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr className="fin-table-empty">
                                        <td colSpan={7} className="px-4 py-8 text-center text-slate-400">
                                            Belum ada mutasi transaksi kas &amp; bank yang tercatat.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </FinanceLayout>
    );
}
