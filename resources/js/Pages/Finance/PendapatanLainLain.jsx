import React, { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import FinanceLayout from './Layouts/FinanceLayout';
import { formatRupiah } from './Components/Utils';
import { ArrowDownLeft, CalendarDays, CircleDollarSign } from 'lucide-react';

const today = () => {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};

export default function PendapatanLainLain({ accounts = [], incomeAccounts = [], pendapatan = [], filters = {}, stats = {} }) {
    const [incomeForm, setIncomeForm] = useState({
        account_id: accounts[0]?.id || '', income_account_id: incomeAccounts[0]?.id || '', payment_date: today(),
        amount: '', recipient: '', reference_number: '', notes: '',
    });
    const [savingIncome, setSavingIncome] = useState(false);
    const [month, setMonth] = useState(filters.month || new Date().getMonth() + 1);
    const [year, setYear] = useState(filters.year || new Date().getFullYear());
    const availableYears = useMemo(() => {
        const currentYear = new Date().getFullYear();
        return Array.from({ length: 6 }, (_, index) => currentYear - 3 + index);
    }, []);
    const totalPendapatan = pendapatan.reduce((sum, row) => sum + Number(row.amount || 0), 0);

    const submitIncome = (event) => {
        event.preventDefault();
        if (!incomeForm.account_id || !incomeForm.income_account_id) return alert('Pilih akun Kas & Bank dan akun Pendapatan.');
        if (!incomeForm.amount || Number(incomeForm.amount) <= 0) return alert('Nominal pendapatan harus lebih dari 0.');
        setSavingIncome(true);
        router.post('/finance/pendapatan-lain-lain', incomeForm, {
            preserveScroll: true,
            onSuccess: () => setIncomeForm((current) => ({ ...current, amount: '', recipient: '', reference_number: '', notes: '' })),
            onFinish: () => setSavingIncome(false),
            onError: (errors) => alert(Object.values(errors)[0] || 'Gagal mencatat pendapatan lain-lain.'),
        });
    };

    const changePeriod = (field, value) => {
        const nextMonth = field === 'month' ? Number(value) : Number(month);
        const nextYear = field === 'year' ? Number(value) : Number(year);
        setMonth(nextMonth);
        setYear(nextYear);
        router.get('/finance/pendapatan-lain-lain', { month: nextMonth, year: nextYear }, { preserveState: true, preserveScroll: true });
    };

    return (
        <FinanceLayout title="Pendapatan Lain-lain" subtitle="Catat pemasukan selain pelunasan piutang" stats={stats}>
            <Head title="Pendapatan Lain-lain - Keuangan Apotek" />
            <div className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="bg-white rounded-2xl border border-slate-200 p-5">
                        <div className="flex items-center justify-between text-xs text-slate-500"><span>Pendapatan periode terpilih</span><CircleDollarSign size={18} className="text-teal-600" /></div>
                        <div className="mt-3 text-xl font-bold text-teal-700">{formatRupiah(totalPendapatan)}</div>
                        <p className="mt-1 text-[11px] text-slate-400">Tidak termasuk saldo awal dan pembayaran piutang.</p>
                    </div>
                    <div className="rounded-2xl border border-teal-100 bg-teal-50/70 p-5">
                        <div className="text-xs font-semibold text-teal-800">Pencatatan pendapatan</div>
                        <p className="mt-2 text-xs leading-5 text-teal-900/75">Gunakan untuk uang masuk selain pembayaran piutang, misalnya jasa atau pendapatan non-penjualan. Pengaturan saldo awal tersedia di Kas & Bank.</p>
                    </div>
                </div>

                <section className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div className="px-5 py-4 border-b border-slate-100">
                        <h2 className="font-bold text-slate-900">Catat Pendapatan Lain-lain</h2>
                        <p className="text-xs text-slate-500 mt-1">Pilih akun tujuan kas/bank dan akun pendapatan untuk pencatatan yang tepat.</p>
                    </div>
                    <form onSubmit={submitIncome} className="p-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <label className="block text-xs font-semibold text-slate-600">Tanggal diterima
                            <input required type="date" value={incomeForm.payment_date} onChange={(e) => setIncomeForm({ ...incomeForm, payment_date: e.target.value })} className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal" />
                        </label>
                        <label className="block text-xs font-semibold text-slate-600">Nominal diterima
                            <input required type="number" min="0.01" step="0.01" value={incomeForm.amount} onChange={(e) => setIncomeForm({ ...incomeForm, amount: e.target.value })} placeholder="Contoh: 500000" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal" />
                        </label>
                        <label className="block text-xs font-semibold text-slate-600">Masuk ke akun kas / bank
                            <select required value={incomeForm.account_id} onChange={(e) => setIncomeForm({ ...incomeForm, account_id: e.target.value })} className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal">
                                {accounts.map((account) => <option key={account.id} value={account.id}>{account.code} · {account.name}</option>)}
                            </select>
                        </label>
                        <label className="block text-xs font-semibold text-slate-600">Akun pendapatan
                            <select required value={incomeForm.income_account_id} onChange={(e) => setIncomeForm({ ...incomeForm, income_account_id: e.target.value })} className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal">
                                {incomeAccounts.map((account) => <option key={account.id} value={account.id}>{account.code} · {account.name}</option>)}
                            </select>
                            {incomeAccounts.length === 0 && <span className="mt-1 block font-normal text-amber-700">Tambahkan akun kategori Pendapatan melalui menu Akun sebelum mencatat penerimaan.</span>}
                        </label>
                        <label className="block text-xs font-semibold text-slate-600">Sumber / pihak
                            <input value={incomeForm.recipient} onChange={(e) => setIncomeForm({ ...incomeForm, recipient: e.target.value })} placeholder="Contoh: jasa layanan" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal" />
                        </label>
                        <label className="block text-xs font-semibold text-slate-600">Nomor bukti
                            <input value={incomeForm.reference_number} onChange={(e) => setIncomeForm({ ...incomeForm, reference_number: e.target.value })} placeholder="Opsional" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal" />
                        </label>
                        <label className="block text-xs font-semibold text-slate-600 lg:col-span-2">Catatan
                            <input value={incomeForm.notes} onChange={(e) => setIncomeForm({ ...incomeForm, notes: e.target.value })} placeholder="Keterangan tambahan" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal" />
                        </label>
                        <div className="lg:col-span-2 flex justify-end pt-1">
                            <button disabled={savingIncome || accounts.length === 0 || incomeAccounts.length === 0} className="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-teal-700 disabled:opacity-50"><ArrowDownLeft size={15} />{savingIncome ? 'Menyimpan…' : 'Catat Pendapatan'}</button>
                        </div>
                    </form>
                </section>

                <section className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div className="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div><h2 className="font-bold text-slate-900">Riwayat Pendapatan Lain-lain</h2><p className="text-xs text-slate-500 mt-1">Hanya penerimaan di luar pembayaran piutang.</p></div>
                        <div className="flex items-center gap-2"><CalendarDays size={16} className="text-slate-400" /><select value={month} onChange={(e) => changePeriod('month', e.target.value)} className="rounded-lg border border-slate-200 px-2.5 py-2 text-xs">{Array.from({ length: 12 }, (_, index) => <option key={index + 1} value={index + 1}>{new Date(2000, index, 1).toLocaleDateString('id-ID', { month: 'long' })}</option>)}</select><select value={year} onChange={(e) => changePeriod('year', e.target.value)} className="rounded-lg border border-slate-200 px-2.5 py-2 text-xs">{availableYears.map((value) => <option key={value} value={value}>{value}</option>)}</select></div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[800px] text-left text-xs">
                            <thead className="bg-slate-50 text-slate-500"><tr><th className="px-5 py-3">Tanggal</th><th className="px-5 py-3">Bukti</th><th className="px-5 py-3">Sumber</th><th className="px-5 py-3">Akun Pendapatan</th><th className="px-5 py-3">Masuk ke</th><th className="px-5 py-3 text-right">Nominal</th><th className="px-5 py-3">Petugas</th></tr></thead>
                            <tbody className="divide-y divide-slate-100">{pendapatan.length ? pendapatan.map((row) => <tr key={row.id} className="hover:bg-slate-50"><td className="px-5 py-3 whitespace-nowrap">{row.formatted_date}</td><td className="px-5 py-3 font-semibold text-slate-700">{row.reference_number}</td><td className="px-5 py-3">{row.recipient}</td><td className="px-5 py-3">{row.income_account_name}</td><td className="px-5 py-3">{row.account_name}</td><td className="px-5 py-3 text-right font-bold text-teal-700">+{formatRupiah(row.amount)}</td><td className="px-5 py-3 text-slate-500">{row.user_name}</td></tr>) : <tr><td colSpan="7" className="px-5 py-12 text-center text-slate-400">Belum ada pendapatan lain-lain pada periode ini.</td></tr>}</tbody>
                        </table>
                    </div>
                    <div className="border-t border-slate-100 bg-slate-50 px-5 py-3 flex justify-end text-xs"><span className="text-slate-500 mr-3">Total periode</span><strong className="text-teal-700">{formatRupiah(totalPendapatan)}</strong></div>
                </section>
            </div>
        </FinanceLayout>
    );
}
