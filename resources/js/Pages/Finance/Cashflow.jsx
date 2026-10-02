import React, { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { ArrowDownUp, CalendarDays, CreditCard, Landmark, Wallet, CircleHelp, Download } from 'lucide-react';
import FinanceLayout from './Layouts/FinanceLayout';
import { formatRupiah } from './Components/Utils';

const banks = [
    { key: 'BNI', label: 'BNI' },
    { key: 'BRI', label: 'BRI' },
    { key: 'MANDIRI', label: 'Mandiri' },
    { key: 'BCA', label: 'BCA' },
    { key: 'BPD', label: 'BPD' },
    { key: 'BTN', label: 'BTN' },
    { key: 'OTHER', label: 'LAIN' },
];
const shifts = [
    { key: 'morning', label: 'PAGI' },
    { key: 'evening', label: 'MALAM' },
];
const otherShiftLabel = 'Shift belum terpetakan';

function Metric({ label, value, note, icon: Icon, tone }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between gap-3">
                <span className="text-sm font-semibold text-slate-600">{label}</span>
                <span className={`rounded-xl p-2 ${tone}`}><Icon className="h-5 w-5" /></span>
            </div>
            <div className="mt-4 text-2xl font-bold tabular-nums text-slate-900">{formatRupiah(value)}</div>
            <p className="mt-1 text-xs text-slate-500">{note}</p>
        </div>
    );
}

function AmountCell({ amount = 0, count = 0, detail = null }) {
    return (
        <div className="min-w-[112px] text-right tabular-nums">
            <strong className="block whitespace-nowrap text-xs text-slate-800">{formatRupiah(amount)}</strong>
            <span className="block text-[10px] text-slate-500">{Number(count || 0).toLocaleString('id-ID')} trx{detail ? ` · ${detail}` : ''}</span>
        </div>
    );
}

function shiftColumnCount() { return 1 + banks.length * 2 + 2; }

export default function Cashflow({ days = [], filters = {} }) {
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');
    const totals = useMemo(() => days.reduce((acc, day) => {
        for (const shift of [...shifts.map((item) => item.key), 'otherShift']) {
            const row = day[shift] || {};
            acc.cash += Number(row.cash || 0);
            acc.edc += Number(row.edcPending || 0);
            acc.deposit += Number(row.directDeposit || 0);
            acc.discount += Number(row.discount || 0) + Number(row.itemDiscount || 0);
            acc.other += (row.otherMethods || []).reduce((sum, method) => sum + Number(method.amount || 0), 0);
        }
        return acc;
    }, { cash: 0, edc: 0, deposit: 0, discount: 0, other: 0 }), [days]);

    const applyFilters = (event) => {
        event.preventDefault();
        router.get('/finance/cashflow', { start_date: startDate, end_date: endDate }, { preserveState: true, preserveScroll: true });
    };

    const sumShift = (shiftKey, getter) => days.reduce((sum, day) => sum + Number(getter(day[shiftKey] || {}) || 0), 0);
    const money = (value) => formatRupiah(value);

    return (
        <FinanceLayout title="Cashflow · Omzet" subtitle="Rekap omzet harian dengan susunan rinci mengikuti lembar kerja">
            <div className="mx-auto max-w-[1800px] space-y-6">
                <section className="flex flex-col gap-4 rounded-2xl border border-blue-100 bg-gradient-to-r from-blue-50 to-white p-5 xl:flex-row xl:items-center xl:justify-between">
                    <div className="flex items-start gap-3">
                        <span className="rounded-xl bg-blue-100 p-2.5 text-blue-700"><ArrowDownUp className="h-5 w-5" /></span>
                        <div>
                            <div className="text-xs font-bold uppercase tracking-wider text-blue-700">Modul Cashflow</div>
                            <h2 className="mt-1 text-xl font-bold text-slate-900">Omzet per tanggal dan shift</h2>
                            <p className="mt-1 max-w-3xl text-sm text-slate-600">Rincian diambil dari transaksi kasir selesai, dikelompokkan menurut Cash, EDC, Transfer/QRIS, diskon, jumlah, dan total setoran. Tanggal tanpa transaksi tetap ditampilkan.</p>
                        </div>
                    </div>
                    <form onSubmit={applyFilters} className="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-white p-3">
                        <label className="grid gap-1 text-xs font-semibold text-slate-600">Dari tanggal<input aria-label="Dari tanggal" type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} className="rounded-lg border-slate-300 text-sm" /></label>
                        <label className="grid gap-1 text-xs font-semibold text-slate-600">Sampai tanggal<input aria-label="Sampai tanggal" type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} className="rounded-lg border-slate-300 text-sm" /></label>
                        <button className="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Terapkan</button>
                        <a href={`/finance/cashflow/export?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`} className="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100"><Download className="h-4 w-4" />Export Excel</a>
                    </form>
                </section>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric label="Total setoran" value={totals.deposit} note="Cash + Transfer/QRIS; EDC pending dikecualikan" icon={CalendarDays} tone="bg-blue-50 text-blue-700" />
                    <Metric label="Cash" value={totals.cash} note="Penerimaan tunai" icon={Wallet} tone="bg-emerald-50 text-emerald-700" />
                    <Metric label="EDC menunggu settlement" value={totals.edc} note="Belum dihitung sebagai setoran masuk" icon={CreditCard} tone="bg-amber-50 text-amber-700" />
                    <Metric label="Transfer / QRIS" value={totals.deposit - totals.cash} note="Langsung masuk ke bank yang dipilih" icon={Landmark} tone="bg-cyan-50 text-cyan-700" />
                </section>

                <div className="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-600">
                    <CircleHelp className="h-4 w-4 shrink-0 text-blue-600" />
                    <span>EDC tetap tercatat sebagai omzet tetapi dipisahkan dari total setoran sampai dana settlement masuk. Diskon transaksi dan diskon item ditampilkan bersama di kolom Disc.</span>
                    <span className="font-semibold text-slate-800">Diskon periode: {money(totals.discount)}</span>
                    {totals.other > 0 && <span className="font-semibold text-violet-700">Metode lain / belum dibayar: {money(totals.other)} (tetap dipisahkan)</span>}
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                        <div><h3 className="font-bold text-slate-900">Lembar omzet harian</h3><p className="mt-1 text-xs text-slate-500">Susunan kolom per bank mengikuti sheet; geser tabel ke samping untuk melihat seluruh rincian.</p></div>
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{days.length} hari</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[2800px] border-collapse text-left tabular-nums">
                            <thead className="sticky top-0 z-10 text-center text-[11px] font-bold uppercase tracking-wide text-slate-800">
                                <tr className="bg-slate-300">
                                    <th rowSpan="3" className="sticky left-0 z-20 border border-slate-500 bg-slate-300 px-3 py-2">TANGGAL</th>
                                    {shifts.map((shift) => <th key={shift.key} colSpan={shiftColumnCount()} className="border border-slate-500 px-2 py-2">{shift.label}</th>)}
                                    <th colSpan="3" className="border border-slate-500 bg-slate-300 px-2 py-2">TOTAL SETORAN</th>
                                </tr>
                                <tr className="bg-slate-200">
                                    {shifts.flatMap((shift) => [
                                        <th key={shift.key + '-cash'} rowSpan="2" className="border border-slate-500 px-2 py-2">CASH</th>,
                                        ...banks.map((bank) => <th key={shift.key + bank.key} colSpan="2" className="border border-slate-500 px-2 py-2">BANK {bank.label}</th>),
                                        <th key={shift.key + '-disc'} rowSpan="2" className="border border-slate-500 px-2 py-2">DISC</th>,
                                        <th key={shift.key + '-total'} rowSpan="2" className="border border-slate-500 px-2 py-2">JUMLAH</th>,
                                    ])}
                                    <th rowSpan="2" className="border border-slate-500 px-2 py-2">PAGI</th><th rowSpan="2" className="border border-slate-500 px-2 py-2">MALAM</th><th rowSpan="2" className="border border-slate-500 px-2 py-2">JUMLAH</th>
                                </tr>
                                <tr className="bg-slate-200">
                                    {shifts.flatMap((shift) => banks.flatMap((bank) => [
                                        <th key={shift.key + bank.key + 'edc'} className="border border-slate-500 px-2 py-2">EDC</th>,
                                        <th key={shift.key + bank.key + 'transfer'} className="border border-slate-500 px-2 py-2">TRANSFER / QRIS</th>,
                                    ]))}
                                </tr>
                            </thead>
                            <tbody className="text-xs">
                                {days.map((day) => {
                                    const morning = day.morning || {};
                                    const evening = day.evening || {};
                                    const setoran = (shift) => Number(shift.directDeposit || 0);
                                    const regularCells = (shift, shiftKey) => <>
                                        <td className="border border-slate-300 bg-emerald-50 px-2 py-2"><AmountCell amount={shift.cash} count={shift.cashCount} /></td>
                                        {banks.flatMap((bank) => {
                                            const values = shift.banks?.[bank.key] || {};
                                            return [
                                                <td key={shiftKey + bank.key + 'edc'} className="border border-slate-300 bg-sky-100 px-2 py-2"><AmountCell amount={values.edc} count={values.edcCount} /></td>,
                                                <td key={shiftKey + bank.key + 'transfer'} className="border border-slate-300 bg-sky-100 px-2 py-2"><AmountCell amount={values.transfer} count={values.transferCount} detail={values.qris > 0 ? `QRIS ${money(values.qris)}` : null} /></td>,
                                            ];
                                        })}
                                        <td className="border border-slate-300 bg-emerald-50 px-2 py-2"><div className="min-w-[120px] text-right"><strong className="block text-xs">{money(Number(shift.discount || 0) + Number(shift.itemDiscount || 0))}</strong><span className="text-[10px] text-slate-500">Transaksi {money(shift.discount)} · Item {money(shift.itemDiscount)}</span></div></td>
                                        <td className="border border-slate-300 bg-orange-100 px-2 py-2"><AmountCell amount={shift.total} count={shift.count} /></td>
                                    </>;
                                    return <tr key={day.date} className="hover:brightness-[0.98]">
                                        <th className="sticky left-0 z-[1] whitespace-nowrap border border-slate-500 bg-white px-3 py-2 text-left font-semibold">{new Date(`${day.date}T00:00:00`).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}</th>
                                        {regularCells(morning, 'morning')}
                                        {regularCells(evening, 'evening')}
                                        <td className="border border-slate-300 bg-slate-100 px-2 py-2 text-right font-semibold">{money(setoran(morning))}</td>
                                        <td className="border border-slate-300 bg-slate-100 px-2 py-2 text-right font-semibold">{money(setoran(evening))}</td>
                                        <td className="border border-slate-300 bg-slate-200 px-2 py-2 text-right font-bold">{money(setoran(morning) + setoran(evening) + setoran(day.otherShift || {}))}</td>
                                    </tr>;
                                })}
                            </tbody>
                            <tfoot className="bg-slate-100 text-xs font-bold">
                                <tr>
                                    <th className="sticky left-0 z-[1] border border-slate-500 bg-slate-200 px-3 py-2 text-left">TOTAL PERIODE</th>
                                    {shifts.flatMap((shift) => {
                                        const key = shift.key;
                                        const total = (getter) => sumShift(key, getter);
                                        return <React.Fragment key={key}>
                                            <td key={key + 'cash'} className="border border-slate-300 px-2 py-2 text-right">{money(total((row) => row.cash))}</td>
                                            {banks.flatMap((bank) => [
                                                <td key={key + bank.key + 'edc'} className="border border-slate-300 px-2 py-2 text-right">{money(total((row) => row.banks?.[bank.key]?.edc))}</td>,
                                                <td key={key + bank.key + 'transfer'} className="border border-slate-300 px-2 py-2 text-right">{money(total((row) => row.banks?.[bank.key]?.transfer))}</td>,
                                            ])}
                                            <td key={key + 'disc'} className="border border-slate-300 px-2 py-2 text-right">{money(total((row) => Number(row.discount || 0) + Number(row.itemDiscount || 0)))}</td>
                                            <td key={key + 'jumlah'} className="border border-slate-300 px-2 py-2 text-right">{money(total((row) => row.total))}</td>
                                        </React.Fragment>;
                                    })}
                                    <td className="border border-slate-300 px-2 py-2 text-right">{money(sumShift('morning', (row) => row.directDeposit))}</td>
                                    <td className="border border-slate-300 px-2 py-2 text-right">{money(sumShift('evening', (row) => row.directDeposit))}</td>
                                    <td className="border border-slate-300 bg-slate-200 px-2 py-2 text-right">{money(totals.deposit)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                {days.some((day) => day.otherShift?.count > 0) && <section className="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <h3 className="font-bold text-amber-900">Transaksi dengan shift belum terpetakan</h3>
                    <p className="mt-1 text-xs text-amber-800">Transaksi ini tetap masuk total periode, tetapi shift-nya tidak dikenali sebagai Pagi atau Malam.</p>
                    <div className="mt-3 overflow-x-auto"><table className="w-full min-w-[640px] text-sm"><thead><tr className="text-left text-xs text-amber-900"><th className="py-2">Tanggal</th><th>Jumlah transaksi</th><th>Omzet</th><th>EDC pending</th><th>Total setoran</th></tr></thead><tbody>{days.filter((day) => day.otherShift?.count > 0).map((day) => <tr key={day.date} className="border-t border-amber-200"><td className="py-2">{day.date}</td><td>{day.otherShift.count}</td><td>{money(day.otherShift.total)}</td><td>{money(day.otherShift.edcPending)}</td><td>{money(day.otherShift.directDeposit)}</td></tr>)}</tbody></table></div>
                </section>}

                {days.some((day) => ['morning', 'evening', 'otherShift'].some((key) => day[key]?.otherMethods?.length > 0)) && <section className="rounded-2xl border border-violet-200 bg-violet-50 p-4">
                    <h3 className="font-bold text-violet-900">Metode lain / belum dibayar</h3>
                    <p className="mt-1 text-xs text-violet-800">Tetap dihitung dalam omzet, namun tidak dimasukkan ke setoran Cash, EDC, atau Transfer.</p>
                    <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {days.flatMap((day) => ['morning', 'evening', 'otherShift'].flatMap((key) => (day[key]?.otherMethods || []).map((method) => (
                            <div key={`${day.date}-${key}-${method.label}`} className="rounded-xl border border-violet-200 bg-white p-3">
                                <div className="text-xs font-semibold text-violet-900">{day.date} · {key === 'morning' ? 'Pagi' : key === 'evening' ? 'Malam' : otherShiftLabel} · {method.label}</div>
                                <div className="mt-1 font-bold text-slate-900">{money(method.amount)}</div>
                                <div className="text-xs text-slate-500">{method.count} transaksi</div>
                            </div>
                        ))))}
                    </div>
                </section>}
            </div>
        </FinanceLayout>
    );
}
