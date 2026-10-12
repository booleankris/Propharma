import React from 'react';
import { formatRupiah } from './Components/Utils';
import { CircleHelp } from 'lucide-react';

const money = (value) => Number(value || 0) === 0 ? '–' : formatRupiah(value);
const sum = (values) => values.reduce((total, value) => total + Number(value || 0), 0);

function SectionRow({ number, title }) {
    return <tr className="bg-slate-100 font-bold uppercase text-slate-900"><td className="border border-slate-700 px-3 py-2 text-center">{number}</td><td colSpan="4" className="border border-slate-700 px-3 py-2">{title}</td></tr>;
}

function AmountRow({ label, amount = 0, number = '', total = null, grandTotal = null, emphasized = false, muted = false }) {
    return <tr className={`${emphasized ? 'bg-slate-100 font-bold' : 'bg-white'} ${muted ? 'text-slate-500' : 'text-slate-800'}`}>
        <td className="border border-slate-500 px-3 py-1.5 text-center">{number}</td>
        <td className="border border-slate-500 px-3 py-1.5">{label}</td>
        <td className="border border-slate-500 px-3 py-1.5 text-right tabular-nums">{money(amount)}</td>
        <td className="border border-slate-500 px-3 py-1.5 text-right tabular-nums">{total === null ? '' : money(total)}</td>
        <td className="border border-slate-500 px-3 py-1.5 text-right tabular-nums">{grandTotal === null ? '' : money(grandTotal)}</td>
    </tr>;
}

export default function RekapCashflow({ recap = {}, monthLabel = '' }) {
    const openingAccounts = recap.openingAccounts || [];
    const closingAccounts = recap.closingAccounts || [];
    const openingAssetRows = [
        ...openingAccounts.map((account) => ({ label: account.type === 'kas_apotek' ? 'Kas' : account.name.replace(/^Rekening\s+/i, '').toUpperCase(), amount: account.balance })),
        { label: 'PIUTANG DAGANG', amount: recap.openingReceivable || 0 },
        { label: 'PIUTANG USAHA', amount: recap.openingOtherReceivables || 0, muted: true },
    ];
    const closingAssetRows = [
        ...closingAccounts.map((account) => ({ label: account.type === 'kas_apotek' ? 'Kas' : account.name.replace(/^Rekening\s+/i, '').toUpperCase(), amount: account.balance })),
        { label: 'PIUTANG DAGANG', amount: recap.closingReceivable || 0 },
        { label: 'PIUTANG USAHA', amount: recap.closingOtherReceivables || 0, muted: true },
    ];
    const openingAssets = Number(recap.openingAssets || 0);
    const closingAssets = Number(recap.closingAssets || 0);
    const incomeRows = [
        ['Hasil Penjualan Tunai', recap.periodCashSales],
        ['Hasil Penjualan EDC / Transfer', recap.periodEdcTransferSales],
        ['Hasil Penjualan Kredit', recap.periodCreditSales],
        ['Penerimaan Piutang', recap.periodReceivablePayments],
        ['Pendapatan Lain-Lain', recap.periodOtherIncome],
        ['Penerimaan Cash Bond', recap.periodCashbondReceipts],
        ['Penyesuaian', recap.periodAdjustments],
    ];
    const totalIncome = sum(incomeRows.map((row) => row[1]));
    const openingPlusIncome = openingAssets + totalIncome;
    const expenseRows = [
        ['Hutang Dagang By Cash', recap.periodSupplierCashPayments],
        ['Hutang Dagang By Cek', recap.periodSupplierBankPayments],
        ['Pembelian Cash', recap.periodCashPurchases],
        ['Cash Bond', recap.periodCashbondPayments],
        ['Biaya Operasional', recap.periodOperatingExpenses],
    ];
    const totalExpenses = sum(expenseRows.map((row) => row[1]));
    const cashflowSurplus = openingPlusIncome - totalExpenses;
    const totalDebtBeforePayments = Number(recap.openingPayables || 0) + Number(recap.periodCreditPurchases || 0) - Number(recap.periodPurchaseReturns || 0);
    const periodSupplierPayments = Number(recap.periodSupplierCashPayments || 0) + Number(recap.periodSupplierBankPayments || 0);
    const netAssets = closingAssets - Number(recap.closingPayables || 0);
    const effectiveEnd = recap.periodEffectiveEnd || recap.periodEnd;
    const endingDateLabel = effectiveEnd ? new Date(`${effectiveEnd}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '';

    return <section className="overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm">
        <header className="border-b-2 border-slate-900 px-4 py-5 text-center">
            <h3 className="text-lg font-extrabold tracking-wide text-slate-900">APOTEK SAHABAT</h3>
            <p className="mt-1 text-lg font-extrabold tracking-wide text-slate-900">REKAP CASH FLOW</p>
            <p className="mt-1 text-sm font-bold uppercase text-slate-700">PERIODE {monthLabel}</p>
            <p className="mt-1 text-[11px] text-slate-500">Saldo akhir dihitung sampai {endingDateLabel} untuk cabang yang dipilih.</p>
        </header>
        <div className="overflow-x-auto">
            <table className="w-full min-w-[850px] border-collapse text-xs">
                <thead className="bg-slate-200 text-center font-extrabold uppercase text-slate-900">
                    <tr><th className="w-14 border-2 border-slate-700 px-2 py-2">No</th><th className="border-2 border-slate-700 px-3 py-2 text-left">Keterangan</th><th className="w-48 border-2 border-slate-700 px-3 py-2">Nominal</th><th className="w-48 border-2 border-slate-700 px-3 py-2">Total</th><th className="w-52 border-2 border-slate-700 px-3 py-2">Grand Total</th></tr>
                </thead>
                <tbody>
                    <SectionRow number="1" title="Saldo Awal" />
                    {openingAssetRows.map((row, index) => <AmountRow key={`opening-${row.label}-${index}`} label={row.label} amount={row.amount} muted={row.muted} />)}
                    <AmountRow label="TOTAL SALDO AWAL AKTIVA" total={openingAssets} emphasized />

                    <SectionRow number="2" title="Pemasukan" />
                    {incomeRows.map(([label, amount]) => <AmountRow key={label} label={label} amount={amount} />)}
                    <AmountRow label="TOTAL PEMASUKAN PERIODE" total={totalIncome} emphasized />
                    <AmountRow label="GRAND TOTAL (SALDO AWAL + PEMASUKAN)" grandTotal={openingPlusIncome} emphasized />

                    <SectionRow number="3" title="Pengeluaran" />
                    {expenseRows.map(([label, amount]) => <AmountRow key={label} label={label} amount={amount} />)}
                    <AmountRow label="TOTAL PENGELUARAN" total={totalExpenses} emphasized />
                    <AmountRow label="SISA PERIODE" grandTotal={cashflowSurplus} emphasized />

                    <SectionRow number="4" title={`Saldo Akhir per ${endingDateLabel}`} />
                    {closingAssetRows.map((row, index) => <AmountRow key={`closing-${row.label}-${index}`} label={row.label} amount={row.amount} muted={row.muted} />)}
                    <AmountRow label="TOTAL SALDO AKHIR AKTIVA" grandTotal={closingAssets} emphasized />

                    <SectionRow number="5" title="Hutang Dagang" />
                    <AmountRow label="SALDO AWAL" amount={recap.openingPayables || 0} />
                    <AmountRow label="PEMBELIAN KREDIT" amount={recap.periodCreditPurchases || 0} />
                    <AmountRow label="RETUR PEMBELIAN" amount={-(Number(recap.periodPurchaseReturns || 0))} />
                    <AmountRow label="TOTAL HUTANG DAGANG SEBELUM PEMBAYARAN" total={totalDebtBeforePayments} emphasized />
                    <AmountRow label="PEMBAYARAN" amount={-periodSupplierPayments} />
                    <AmountRow label="SALDO AKHIR HUTANG DAGANG" grandTotal={recap.closingPayables || 0} emphasized />
                    <AmountRow label="SALDO AKHIR AKTIVA (AKTIVA − HUTANG)" grandTotal={netAssets} emphasized />
                </tbody>
            </table>
        </div>
        <footer className="flex items-start gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 text-[11px] leading-relaxed text-slate-600">
            <CircleHelp className="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
            <span>Piutang Dagang berisi sisa tagihan penjualan kredit. Piutang Usaha belum memiliki sumber data terpisah di aplikasi, jadi ditampilkan nol agar tidak menggandakan Piutang Dagang. Saldo kas/bank mengikuti saldo rekening cabang; penerimaan Cash Bond dan Penyesuaian masih nol karena belum ada pencatatan sumbernya.</span>
        </footer>
    </section>;
}
