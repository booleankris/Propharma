import React from 'react';
import { formatRupiah } from './Components/Utils';

const amountText = (value) => Number(value || 0) === 0 ? '–' : formatRupiah(value);
const dateText = (date) => new Date(`${date}T00:00:00`).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });

export default function TradeDailyCashflow({ title, description, report = {} }) {
    const dates = report.dates || [];
    const rows = report.rows || [];

    return (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div>
                    <h3 className="font-bold text-slate-900">{title}</h3>
                    <p className="mt-1 text-xs text-slate-500">{description}</p>
                </div>
                <div className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{rows.length} {title === 'Piutang Dagang' ? 'debitur' : 'supplier'} · {dates.length} hari</div>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-max border-collapse text-xs tabular-nums">
                    <thead className="sticky top-0 z-10 bg-slate-200 text-center font-bold text-slate-800">
                        <tr>
                            <th className="sticky left-0 z-20 min-w-12 border border-slate-400 bg-slate-200 px-3 py-2">NO</th>
                            <th className="sticky left-12 z-20 min-w-64 border border-slate-400 bg-slate-200 px-3 py-2 text-left">{title === 'Piutang Dagang' ? 'NAMA DEBITUR' : 'NAMA SUPPLIER'}</th>
                            {dates.map((date) => <th key={date} className="min-w-32 border border-slate-400 px-2 py-2">{dateText(date)}</th>)}
                            <th className="sticky right-0 z-20 min-w-36 border border-slate-400 bg-slate-300 px-3 py-2">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length ? rows.map((row, index) => <tr key={row.key} className="odd:bg-white even:bg-slate-50 hover:bg-blue-50/50">
                            <td className="sticky left-0 z-[1] border border-slate-200 bg-inherit px-3 py-2 text-center">{index + 1}</td>
                            <td className="sticky left-12 z-[1] border border-slate-200 bg-inherit px-3 py-2 font-medium text-slate-800">{row.name}</td>
                            {dates.map((date) => <td key={date} className="border border-slate-200 px-2 py-2 text-right">{amountText(row.amounts?.[date])}</td>)}
                            <td className="sticky right-0 border border-slate-300 bg-slate-50 px-3 py-2 text-right font-bold text-slate-900">{amountText(row.total)}</td>
                        </tr>) : <tr><td colSpan={dates.length + 3} className="border border-slate-200 px-4 py-12 text-center text-slate-400">Belum ada data untuk periode ini.</td></tr>}
                    </tbody>
                    <tfoot className="bg-yellow-100 font-bold text-slate-900">
                        <tr>
                            <th colSpan="2" className="sticky left-0 z-[1] border border-slate-400 bg-yellow-100 px-3 py-2 text-left">GRAND TOTAL</th>
                            {dates.map((date) => <td key={date} className="border border-slate-300 px-2 py-2 text-right">{amountText(report.grandTotalsByDate?.[date])}</td>)}
                            <td className="sticky right-0 border border-slate-400 bg-yellow-200 px-3 py-2 text-right">{amountText(report.grandTotal)}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    );
}
