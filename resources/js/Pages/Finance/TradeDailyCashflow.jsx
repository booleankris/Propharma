import React from 'react';
const amountText = (value, showZero = false) => {
    const amount = Number(value || 0);
    if (amount === 0) return showZero ? 'Rp 0' : '–';
    const isNegative = amount < 0;
    const formatted = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: Math.abs(amount % 1) > 0.0000001 ? 2 : 0,
        maximumFractionDigits: 2,
    }).format(Math.abs(amount));
    return `${isNegative ? '-' : ''}Rp\u00A0${formatted}`;
};
const dateText = (date) => new Date(`${date}T00:00:00`).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
const previousDateText = (date) => {
    if (!date) return '';
    const previous = new Date(`${date}T00:00:00`);
    previous.setDate(previous.getDate() - 1);
    return previous.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
};

const summaryMoney = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 2,
}).format(Number(value || 0));

export default function TradeDailyCashflow({ title, description, report = {}, reconciliation = null }) {
    const dates = report.dates || [];
    const rows = report.rows || [];
    const isReceivable = title === 'Piutang Dagang';
    const partyLabel = isReceivable ? 'DEBITUR' : 'SUPPLIER';
    const paymentTitle = isReceivable ? 'PEMBAYARAN PIUTANG DAGANG' : 'PEMBAYARAN HUTANG DAGANG';
    const paymentAmountLabel = isReceivable ? 'DITERIMA' : 'DIBAYARKAN';
    const balanceLabel = isReceivable ? 'SALDO PIUTANG' : 'SALDO HUTANG';
    const balanceBeforePaymentLabel = `${balanceLabel} SEBELUM PEMBAYARAN`;
    const summaryItems = reconciliation ? [
        { label: 'Saldo awal', value: reconciliation.opening, tone: 'text-slate-900' },
        { label: reconciliation.activityLabel, value: reconciliation.activity, tone: 'text-blue-700' },
        { label: reconciliation.paymentLabel, value: -Math.abs(Number(reconciliation.payments || 0)), tone: 'text-rose-700' },
        { label: reconciliation.closingLabel, value: reconciliation.closing, tone: 'text-emerald-700', emphasized: true },
    ] : [];

    return (
        <section className="space-y-4">
            {reconciliation && <div className="rounded-2xl border border-blue-100 bg-white p-4 shadow-sm">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 className="font-bold text-slate-900">Rekonsiliasi saldo periode</h3>
                        <p className="mt-1 text-xs text-slate-500">{reconciliation.note}</p>
                    </div>
                    <span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{reconciliation.periodLabel}</span>
                </div>
                <div className="grid grid-cols-2 gap-2 lg:grid-cols-4">
                    {summaryItems.map((item) => <div key={item.label} className={`rounded-xl border px-3 py-3 ${item.emphasized ? 'border-emerald-200 bg-emerald-50' : 'border-slate-100 bg-slate-50'}`}>
                        <div className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{item.label}</div>
                        <div className={`mt-1 text-sm font-extrabold tabular-nums ${item.tone}`}>{summaryMoney(item.value)}</div>
                    </div>)}
                </div>
                <p className="mt-3 text-[11px] font-medium text-slate-500">
                    Saldo akhir = saldo awal + tagihan/retur periode − pembayaran yang dicatat di Finance.
                </p>
            </div>}
            <div className="space-y-4">
                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <h3 className="text-sm font-extrabold tracking-wide text-slate-900">{title.toUpperCase()}</h3>
                        <p className="mt-1 text-[11px] text-slate-500">Saldo awal adalah sisa hutang dari penerimaan kredit sebelum periode, setelah dikurangi pembayaran yang tercatat sampai {previousDateText(dates[0])}. Saldo ini dibawa dari periode sebelumnya.</p>
                    </div>
                    <div className="max-h-[30rem] overflow-auto">
                        <table className="w-full min-w-max border-collapse text-xs tabular-nums">
                            <thead className="sticky top-0 z-10 bg-slate-200 text-center font-bold text-slate-800">
                                <tr>
                                    <th className="sticky left-0 z-30 w-12 min-w-12 border border-slate-400 bg-slate-200 px-2 py-2">NO</th>
                                    <th className="sticky left-12 z-30 min-w-48 border border-slate-400 bg-slate-200 px-3 py-2 text-left">NAMA {partyLabel}</th>
                                    <th className="min-w-36 border border-slate-400 px-2 py-2">SALDO AWAL<br />(SISA SEBELUM PERIODE)</th>
                                    {dates.map((date) => <th key={date} className="min-w-32 border border-slate-400 px-2 py-2">{dateText(date)}</th>)}
                                    <th className="min-w-36 border border-slate-400 bg-slate-100 px-2 py-2">TOTAL PERIODE</th>
                                    <th className="min-w-36 border border-slate-400 bg-yellow-100 px-2 py-2">GRAND TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row, index) => <tr key={`due-${row.key}`} className="odd:bg-white even:bg-slate-50">
                                    <td className="sticky left-0 z-10 w-12 min-w-12 border border-slate-200 bg-inherit px-2 py-2 text-center">{index + 1}</td>
                                    <td className="sticky left-12 z-10 min-w-48 border border-slate-200 bg-inherit px-3 py-2 font-medium text-slate-800">{row.name}</td>
                                    <td className="border border-slate-200 px-2 py-2 text-right">{amountText(row.openingBalance)}</td>
                                    {dates.map((date) => <td key={date} className="border border-slate-200 px-2 py-2 text-right">{amountText(row.amounts?.[date])}</td>)}
                                    <td className="border border-slate-200 bg-slate-50 px-2 py-2 text-right font-semibold">{amountText(row.periodTotal ?? row.total)}</td>
                                    <td className="border border-slate-300 bg-yellow-50 px-2 py-2 text-right font-bold">{amountText(Number(row.openingBalance || 0) + Number(row.periodTotal ?? row.total ?? 0))}</td>
                                </tr>)}
                            </tbody>
                            <tfoot className="bg-yellow-100 font-bold text-slate-900">
                                <tr>
                                    <th colSpan="2" className="border border-slate-400 px-3 py-2 text-left">GRAND TOTAL</th>
                                    <td className="border border-slate-400 px-2 py-2 text-right">{amountText(report.openingBalance)}</td>
                                    {dates.map((date) => <td key={date} className="border border-slate-400 px-2 py-2 text-right">{amountText(report.grandTotalsByDate?.[date])}</td>)}
                                    <td className="border border-slate-400 bg-slate-100 px-2 py-2 text-right">{amountText(report.periodTotal ?? report.grandTotal)}</td>
                                    <td className="border border-slate-400 bg-yellow-200 px-2 py-2 text-right">{amountText(report.grandDue ?? (Number(report.openingBalance || 0) + Number(report.periodTotal ?? report.grandTotal ?? 0)))}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <h3 className="text-sm font-extrabold tracking-wide text-slate-900">{paymentTitle}</h3>
                        <p className="mt-1 text-[11px] text-slate-500">{paymentAmountLabel} dan sisa tagihan setelah pembayaran periode ini.</p>
                    </div>
                    <div className="max-h-[30rem] overflow-auto">
                        <table className="w-full min-w-max border-collapse text-xs tabular-nums">
                            <thead className="sticky top-0 z-10 bg-slate-200 text-center font-bold text-slate-800">
                                <tr>
                                    <th className="sticky left-0 z-30 w-12 min-w-12 border border-slate-400 bg-slate-200 px-2 py-2">NO</th>
                                    <th className="sticky left-12 z-30 min-w-48 border border-slate-400 bg-slate-200 px-3 py-2 text-left">NAMA {partyLabel}</th>
                                    <th className="min-w-40 border border-slate-400 bg-blue-100 px-2 py-2">{balanceBeforePaymentLabel}</th>
                                    {dates.map((date) => <th key={date} className="min-w-32 border border-slate-400 px-2 py-2">{dateText(date)}</th>)}
                                    <th className="min-w-36 border border-slate-400 bg-slate-100 px-2 py-2">TOTAL {paymentAmountLabel} PERIODE</th>
                                    <th className="min-w-36 border border-slate-400 bg-emerald-100 px-2 py-2">{balanceLabel} AKHIR</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row, index) => <tr key={`paid-${row.key}`} className="odd:bg-white even:bg-slate-50">
                                    <td className="sticky left-0 z-10 w-12 min-w-12 border border-slate-200 bg-inherit px-2 py-2 text-center">{index + 1}</td>
                                    <td className="sticky left-12 z-10 min-w-48 border border-slate-200 bg-inherit px-3 py-2 font-medium text-slate-800">{row.name}</td>
                                    <td className="border border-blue-200 bg-blue-50 px-2 py-2 text-right font-semibold text-blue-900">{amountText(Number(row.openingBalance || 0) + Number(row.periodTotal ?? row.total ?? 0))}</td>
                                    {dates.map((date) => <td key={date} className="border border-slate-200 px-2 py-2 text-right text-rose-700">{amountText(row.paymentAmounts?.[date])}</td>)}
                                    <td className="border border-slate-200 bg-slate-50 px-2 py-2 text-right font-semibold text-rose-700">{amountText(row.periodPayments)}</td>
                                    <td className="border border-emerald-200 bg-emerald-50 px-2 py-2 text-right font-bold text-emerald-800">{amountText(row.closingBalance, Number(row.periodPayments || 0) > 0)}</td>
                                </tr>)}
                            </tbody>
                            <tfoot className="bg-yellow-100 font-bold text-slate-900">
                                <tr>
                                    <th colSpan="2" className="border border-slate-400 px-3 py-2 text-left">GRAND TOTAL</th>
                                    <td className="border border-blue-300 bg-blue-100 px-2 py-2 text-right">{amountText(report.grandDue ?? (Number(report.openingBalance || 0) + Number(report.periodTotal || 0)))}</td>
                                    {dates.map((date) => <td key={date} className="border border-slate-400 px-2 py-2 text-right">{amountText(report.paymentTotalsByDate?.[date])}</td>)}
                                    <td className="border border-slate-400 px-2 py-2 text-right">{amountText(report.periodPayments)}</td>
                                    <td className="border border-emerald-300 bg-emerald-100 px-2 py-2 text-right">{amountText(report.closingBalance, Number(report.periodPayments || 0) > 0)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            </div>
        </section>
    );
}
