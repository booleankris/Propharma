import React from 'react';
import { Link } from '@inertiajs/react';
import { formatRupiah } from './Components/Utils';

const groups = [
    { title: 'PEMASUKAN', tone: 'bg-emerald-100', cols: [
        ['pagi', 'PAGI'], ['malam', 'MALAM'], ['jumlah', 'JUMLAH'], ['piutang', 'PIUTANG'],
        ['pendapatan_lain', 'PENDAPATAN LAIN-LAIN'], ['bank', 'BANK'], ['cashbond_masuk', 'CASHBOND'], ['total_pemasukan', 'TOTAL PEMASUKAN'],
    ] },
    { title: 'PENGELUARAN', tone: 'bg-rose-100', cols: [
        ['hutang_dagang', 'H. DAGANG'], ['retur_pembelian', 'RETUR PEMBELIAN'], ['pembelian_cash', 'PEMBELIAN CASH'],
        ['biaya_pajak', 'BIAYA PAJAK'], ['biaya_operasional', 'BIAYA OPERASIONAL'], ['total_biaya', 'TOTAL BIAYA'],
        ['cashbond_keluar', 'CASHBOND'],
    ] },
];

const money = (value) => Number(value || 0) === 0 ? '–' : formatRupiah(value);

export default function KeuanganDaily({ days = [], saldoAwal = 0, setorAccounts = [] }) {
    const deposits = setorAccounts.map((account) => account.name);
    const totalColumns = groups.reduce((sum, group) => sum + group.cols.length, 0) + deposits.length + 4;

    return <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <div>
                <h3 className="font-bold text-slate-900">Laporan Keuangan Harian</h3>
                <p className="mt-1 text-xs text-slate-500">Saldo berjalan memakai saldo awal Kas Apotek / Drawer, pemasukan tunai, pengeluaran, dan setoran kas ke bank.</p>
            </div>
            <div className="text-right text-xs text-slate-500">Saldo awal tercatat<br /><strong className="text-sm text-slate-900">{formatRupiah(saldoAwal)}</strong><br /><Link href="/finance/kas-bank" className="font-semibold text-blue-600 hover:underline">Atur di Kas & Bank</Link></div>
        </div>
        <div className="overflow-x-auto">
            <table className="w-full min-w-max border-collapse text-right text-[11px] tabular-nums">
                <thead className="sticky top-0 z-10 text-center font-bold text-slate-800">
                    <tr><th rowSpan="2" className="sticky left-0 z-20 border border-slate-400 bg-slate-200 px-3 py-2">TANGGAL</th>{groups.map((group) => <th key={group.title} colSpan={group.cols.length} className={`border border-slate-400 px-2 py-2 ${group.tone}`}>{group.title}</th>)}{deposits.map((bank) => <th key={bank} rowSpan="2" className="min-w-28 border border-slate-400 bg-rose-100 px-2 py-2">SETOR {bank.toUpperCase()}</th>)}<th rowSpan="2" className="border border-slate-400 bg-rose-100 px-2 py-2">TOTAL PENGELUARAN</th><th rowSpan="2" className="border border-slate-400 bg-slate-200 px-2 py-2">SISA</th><th rowSpan="2" className="border border-slate-400 bg-emerald-200 px-2 py-2">SALDO S/D</th></tr>
                    <tr>{groups.flatMap((group) => group.cols.map(([key, label]) => <th key={key} className={`min-w-28 border border-slate-400 px-2 py-2 ${group.tone}`}>{label}</th>))}</tr>
                </thead>
                <tbody>{days.map((day) => <tr key={day.date} className="odd:bg-white even:bg-slate-50">
                    <th className="sticky left-0 z-[1] whitespace-nowrap border border-slate-300 bg-inherit px-3 py-2 text-left font-semibold">{new Date(`${day.date}T00:00:00`).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}</th>
                    {groups.flatMap((group) => group.cols.map(([key]) => <td key={key} className={`border border-slate-300 px-2 py-2 ${key.startsWith('total') ? 'bg-yellow-100 font-bold' : ''}`}>{money(day[key])}</td>))}
                    {deposits.map((bank) => <td key={bank} className="border border-slate-300 px-2 py-2">{money(day.setoran?.[bank])}</td>)}
                    <td className="border border-slate-300 bg-yellow-100 px-2 py-2 font-bold">{money(day.total_pengeluaran)}</td><td className="border border-slate-300 bg-slate-100 px-2 py-2 font-bold">{money(day.sisa)}</td><td className="border border-slate-300 bg-emerald-100 px-2 py-2 font-bold">{money(day.saldo_sd)}</td>
                </tr>)}</tbody>
                <tfoot className="bg-slate-100 font-bold"><tr><th colSpan={totalColumns} className="border border-slate-400 px-3 py-2 text-left">Saldo S/D akhir periode: {formatRupiah(days.length ? days[days.length - 1].saldo_sd : saldoAwal)}</th></tr></tfoot>
            </table>
        </div>
        <div className="border-t border-slate-200 px-5 py-3 text-[11px] text-slate-500">Kolom Bank pada pemasukan diabaikan sesuai arahan. Setoran bank adalah pemindahan saldo internal dari Kas Apotek / Drawer ke rekening.</div>
    </section>;
}
