import React, { useMemo } from 'react';
import { formatRupiah } from './Components/Utils';

const numeric = (value) => Number(value || 0);
const money = (value) => formatRupiah(numeric(value));

export default function KinerjaOmzet({ days = [], debtors = [] }) {
    const totals = useMemo(() => days.reduce((sum, day) => {
        sum.cashMorning += numeric(day.sales?.cash?.morning);
        sum.cashEvening += numeric(day.sales?.cash?.evening);
        sum.bankMorning += numeric(day.sales?.bank?.morning);
        sum.bankEvening += numeric(day.sales?.bank?.evening);
        for (const key of ['cashBankTotal', 'cashBankPpn', 'cashBankExVat', 'creditTotal', 'creditPpn', 'creditExVat', 'total', 'ppn', 'exVat']) {
            sum[`sales_${key}`] = (sum[`sales_${key}`] || 0) + numeric(day.sales?.[key]);
        }
        sum.salesReturns += numeric(day.sales?.returns?.morning) + numeric(day.sales?.returns?.evening);
        for (const debtor of debtors) sum[`debtor_${debtor.id}`] = (sum[`debtor_${debtor.id}`] || 0) + numeric(day.sales?.credit?.morning?.[debtor.id]) + numeric(day.sales?.credit?.evening?.[debtor.id]);
        for (const key of ['pbf', 'consignment', 'return', 'cash', 'total', 'ppn', 'exVat']) sum[`purchase_${key}`] = (sum[`purchase_${key}`] || 0) + numeric(day.purchases?.[key]);
        sum.margin += numeric(day.sales?.exVat) - numeric(day.purchases?.exVat);
        return sum;
    }, { cashMorning: 0, cashEvening: 0, bankMorning: 0, bankEvening: 0, salesReturns: 0, margin: 0 }), [days, debtors]);

    const cell = 'border border-slate-400 px-2 py-2 text-right tabular-nums whitespace-nowrap';
    const header = 'border border-slate-500 px-2 py-2 text-center font-bold text-slate-900';
    const dateLabel = (value) => new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: '2-digit' }).format(new Date(`${value}T00:00:00`));
    const hpp = totals.sales_exVat ? totals.purchase_exVat / totals.sales_exVat * 100 : 0;
    const creditCount = debtors.length + 3;

    return <section className="overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <div><h3 className="font-bold text-slate-900">Kinerja Omzet</h3></div>
            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{days.length} hari</span>
        </div>
        <div className="overflow-auto">
            <table className="w-full min-w-max border-collapse text-xs">
                <thead className="sticky top-0 z-20">
                    <tr className="bg-emerald-100">
                        <th rowSpan="3" className={`${header} sticky left-0 z-30 min-w-[108px] bg-slate-300`}>TANGGAL</th>
                        <th colSpan={11 + debtors.length} className={`${header} bg-emerald-200`}>PENJUALAN</th>
                        <th colSpan="9" className={`${header} bg-blue-200`}>PEMBELIAN</th>
                    </tr>
                    <tr className="bg-emerald-50">
                        <th colSpan="2" className={header}>TUNAI</th><th colSpan="2" className={header}>BANK</th>
                        <th rowSpan="2" className={header}>JUMLAH OMS</th><th rowSpan="2" className={header}>PPN</th><th rowSpan="2" className={header}>JUMLAH EXC PPN</th><th rowSpan="2" className={`${header} bg-yellow-100`}>RETUR</th>
                        <th colSpan={creditCount} className={header}>KREDIT</th>
                        <th rowSpan="2" className={header}>PBF</th><th rowSpan="2" className={header}>KONSINYASI</th><th rowSpan="2" className={`${header} bg-yellow-100`}>RETUR PEMBELIAN</th><th rowSpan="2" className={header}>CASH</th><th rowSpan="2" className={header}>JUMLAH BELI</th><th rowSpan="2" className={header}>PPN FAKTUR</th><th rowSpan="2" className={header}>BELI EXC PPN</th><th rowSpan="2" className={header}>MARGIN EXC PPN</th><th rowSpan="2" className={header}>HPP %</th>
                    </tr>
                    <tr className="bg-emerald-50">
                        <th className={header}>PAGI</th><th className={header}>MALAM</th><th className={header}>PAGI</th><th className={header}>MALAM</th>
                        {debtors.map((debtor) => <th key={debtor.id} className={`${header} min-w-[128px]`}>{debtor.name}</th>)}
                        <th className={header}>TOTAL KREDIT</th><th className={header}>PPN</th><th className={header}>KREDIT EXC PPN</th>
                    </tr>
                </thead>
                <tbody>
                    {days.map((day) => {
                        const dailyHpp = numeric(day.sales?.exVat) ? numeric(day.purchases?.exVat) / numeric(day.sales?.exVat) * 100 : 0;
                        return <tr key={day.date} className="odd:bg-emerald-50/70 even:bg-emerald-100/50 hover:bg-blue-50">
                            <th className={`${cell} sticky left-0 z-10 bg-emerald-100 text-left font-semibold`}>{dateLabel(day.date)}</th>
                            <td className={cell}>{money(day.sales?.cash?.morning)}</td><td className={cell}>{money(day.sales?.cash?.evening)}</td>
                            <td className={cell}>{money(day.sales?.bank?.morning)}</td><td className={cell}>{money(day.sales?.bank?.evening)}</td>
                            <td className={`${cell} bg-emerald-200 font-semibold`}>{money(day.sales?.cashBankTotal)}</td><td className={cell}>{money(day.sales?.cashBankPpn)}</td><td className={cell}>{money(day.sales?.cashBankExVat)}</td>
                            <td className={`${cell} bg-yellow-100`}>{money(numeric(day.sales?.returns?.morning) + numeric(day.sales?.returns?.evening))}</td>
                            {debtors.map((debtor) => <td key={debtor.id} className={cell}>{money(numeric(day.sales?.credit?.morning?.[debtor.id]) + numeric(day.sales?.credit?.evening?.[debtor.id]))}</td>)}
                            <td className={`${cell} bg-emerald-200 font-semibold`}>{money(day.sales?.creditTotal)}</td><td className={cell}>{money(day.sales?.creditPpn)}</td><td className={cell}>{money(day.sales?.creditExVat)}</td>
                            <td className={cell}>{money(day.purchases?.pbf)}</td><td className={cell}>{money(day.purchases?.consignment)}</td><td className={`${cell} bg-yellow-100`}>{money(day.purchases?.return)}</td><td className={cell}>{money(day.purchases?.cash)}</td>
                            <td className={`${cell} bg-blue-100 font-semibold`}>{money(day.purchases?.total)}</td><td className={cell}>{money(day.purchases?.ppn)}</td><td className={cell}>{money(day.purchases?.exVat)}</td>
                            <td className={`${cell} bg-blue-100 font-semibold`}>{money(numeric(day.sales?.exVat) - numeric(day.purchases?.exVat))}</td><td className={cell}>{dailyHpp.toFixed(1)}%</td>
                        </tr>;
                    })}
                    <tr className="bg-yellow-200 font-bold">
                        <th className={`${cell} sticky left-0 z-10 bg-yellow-200 text-left`}>TOTAL</th>
                        <td className={cell}>{money(totals.cashMorning)}</td><td className={cell}>{money(totals.cashEvening)}</td><td className={cell}>{money(totals.bankMorning)}</td><td className={cell}>{money(totals.bankEvening)}</td>
                        <td className={cell}>{money(totals.sales_cashBankTotal)}</td><td className={cell}>{money(totals.sales_cashBankPpn)}</td><td className={cell}>{money(totals.sales_cashBankExVat)}</td><td className={cell}>{money(totals.salesReturns)}</td>
                        {debtors.map((debtor) => <td key={debtor.id} className={cell}>{money(totals[`debtor_${debtor.id}`])}</td>)}
                        <td className={cell}>{money(totals.sales_creditTotal)}</td><td className={cell}>{money(totals.sales_creditPpn)}</td><td className={cell}>{money(totals.sales_creditExVat)}</td>
                        {['pbf', 'consignment', 'return', 'cash', 'total', 'ppn', 'exVat'].map((key) => <td key={key} className={cell}>{money(totals[`purchase_${key}`])}</td>)}
                        <td className={cell}>{money(totals.margin)}</td><td className={cell}>{hpp.toFixed(1)}%</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>;
}
