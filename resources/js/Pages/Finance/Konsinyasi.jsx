import React, { useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import {
    Banknote,
    Boxes,
    ChevronLeft,
    ChevronRight,
    Clock3,
    Eye,
    HandCoins,
    CheckCircle2,
    CreditCard,
    PackageCheck,
    Search,
    ShoppingBag,
    X,
} from 'lucide-react';
import FinanceLayout from './Layouts/FinanceLayout';
import { formatNumberOnly, formatRupiah } from './Components/Utils';
import PbfCombobox from './Components/PbfCombobox';
import FloatingActionBar from './Components/FloatingActionBar';

const statusStyle = {
    'Belum Ada Penjualan': 'bg-slate-100 text-slate-600',
    'Siap Dibayar': 'bg-amber-100 text-amber-800',
    'Dibayar Sebagian': 'bg-blue-100 text-blue-700',
    'Terbayar s.d. Penjualan': 'bg-emerald-100 text-emerald-700',
    Selesai: 'bg-violet-100 text-violet-700',
};

const SummaryCard = ({ icon: Icon, label, value, note, tone = 'blue' }) => {
    const tones = {
        blue: 'bg-blue-50 text-blue-700',
        violet: 'bg-violet-50 text-violet-700',
        emerald: 'bg-emerald-50 text-emerald-700',
        amber: 'bg-amber-50 text-amber-700',
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-[11px] font-bold uppercase tracking-wide text-slate-400">{label}</p>
                    <p className="mt-1 text-xl font-black text-slate-900">{value}</p>
                    <p className="mt-1 text-[11px] text-slate-500">{note}</p>
                </div>
                <div className={`rounded-xl p-2.5 ${tones[tone]}`}><Icon className="h-5 w-5" /></div>
            </div>
        </div>
    );
};

export default function Konsinyasi({ consignments = [], kasBankAccounts = [], summary = {}, stats = {} }) {
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('ALL');
    const [vendor, setVendor] = useState('ALL');
    const [page, setPage] = useState(1);
    const [detail, setDetail] = useState(null);
    const [paymentTarget, setPaymentTarget] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [selectedIds, setSelectedIds] = useState([]);
    const [selectionWarning, setSelectionWarning] = useState('');
    const [bulkPaymentOpen, setBulkPaymentOpen] = useState(false);
    const [bulkSubmitting, setBulkSubmitting] = useState(false);
    const [bulkForm, setBulkForm] = useState({ account_id: '', payment_date: new Date().toISOString().slice(0, 10), reference_number: '', notes: '' });
    const [form, setForm] = useState({
        account_id: '',
        payment_date: new Date().toISOString().slice(0, 10),
        amount: '',
        reference_number: '',
        notes: '',
    });
    const perPage = 10;

    const vendors = useMemo(
        () => [...new Set(consignments.map((item) => item.vendor).filter(Boolean))].sort(),
        [consignments],
    );

    const filtered = useMemo(() => {
        const needle = search.trim().toLowerCase();
        return consignments.filter((item) => {
            const matchesSearch = !needle || [item.nomor, item.referensi, item.vendor, item.gudang, ...(item.transaction_numbers || [])]
                .some((value) => String(value || '').toLowerCase().includes(needle));
            const matchesStatus = status === 'ALL'
                || (status === 'READY' && Number(item.due) > 0)
                || (status === 'PAID' && Number(item.due) <= 0 && Number(item.qty_sold) > 0)
                || (status === 'NO_SALES' && Number(item.qty_sold) <= 0);
            const matchesVendor = vendor === 'ALL' || item.vendor === vendor;
            return matchesSearch && matchesStatus && matchesVendor;
        });
    }, [consignments, search, status, vendor]);

    const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
    const rows = filtered.slice((page - 1) * perPage, page * perPage);
    const selectedRows = consignments.filter((item) => selectedIds.includes(item.id));
    const selectedDue = selectedRows.reduce((sum, item) => sum + Number(item.due || 0), 0);
    const payableRows = rows.filter((item) => Number(item.due) > 0);
    const allPageSelected = payableRows.length > 0 && payableRows.every((item) => selectedIds.includes(item.id));

    const changeFilter = (setter) => (event) => {
        setter(event.target.value);
        setPage(1);
    };

    const toggleSelection = (item) => {
        if (selectedIds.includes(item.id)) {
            setSelectedIds((current) => current.filter((id) => id !== item.id));
            return;
        }
        if (selectedIds.length >= 10) {
            setSelectionWarning('Maksimal 10 faktur yang dapat dipilih sekaligus untuk pelunasan massal.');
            window.setTimeout(() => setSelectionWarning(''), 4000);
            return;
        }
        setSelectedIds((current) => [...current, item.id]);
    };

    const openBulkPayment = () => {
        if (!selectedIds.length) return;
        setBulkForm({
            account_id: kasBankAccounts[0]?.id || '',
            payment_date: new Date().toISOString().slice(0, 10),
            reference_number: '',
            notes: `Pelunasan massal ${selectedIds.length} faktur konsinyasi`,
        });
        setBulkPaymentOpen(true);
    };

    const openPayment = (item) => {
        setPaymentTarget(item);
        setForm({
            account_id: '',
            payment_date: new Date().toISOString().slice(0, 10),
            amount: Number(item.due || 0),
            reference_number: '',
            notes: `Pembayaran konsinyasi ${item.nomor}`,
        });
    };

    const submitPayment = (event) => {
        event.preventDefault();
        if (!paymentTarget || submitting) return;

        setSubmitting(true);
        router.post('/finance/consignment-payments', {
            ...form,
            receiving_detail_id: paymentTarget.id,
        }, {
            preserveScroll: true,
            onSuccess: () => setPaymentTarget(null),
            onFinish: () => setSubmitting(false),
        });
    };

    const submitBulkPayment = (event) => {
        event.preventDefault();
        if (!selectedIds.length || !bulkForm.account_id || bulkSubmitting) return;
        setBulkSubmitting(true);
        router.post('/finance/consignment-bulk-payments', {
            receiving_detail_ids: selectedIds,
            ...bulkForm,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setBulkPaymentOpen(false);
                setSelectedIds([]);
            },
            onError: (errors) => alert(Object.values(errors).flat().join('\n') || 'Gagal memproses pelunasan konsinyasi.'),
            onFinish: () => setBulkSubmitting(false),
        });
    };

    return (
        <FinanceLayout
            title="Konsinyasi"
            subtitle="Bayar pemasok hanya untuk barang yang sudah terjual"
            stats={stats}
        >
            <div className="h-full overflow-y-auto bg-slate-50 p-6">
                <div className="mx-auto max-w-[1500px] space-y-5">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <SummaryCard icon={Boxes} label="Barang diterima" value={formatNumberOnly(summary.qty_received || 0)} note={`${summary.invoice_count || 0} faktur konsinyasi`} />
                        <SummaryCard icon={ShoppingBag} label="Barang terjual" value={formatNumberOnly(summary.qty_sold || 0)} note="Sudah dikurangi retur penjualan" tone="violet" />
                        <SummaryCard icon={PackageCheck} label="Nilai terjual" value={formatRupiah(summary.payable || 0)} note="Kewajiban kumulatif ke pemasok" tone="emerald" />
                        <SummaryCard icon={Clock3} label="Siap dibayar" value={formatRupiah(summary.due || 0)} note={`${summary.ready_count || 0} faktur memiliki tagihan`} tone="amber" />
                    </div>

                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                        <div className="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h2 className="text-sm font-black text-slate-900">Daftar Faktur Konsinyasi</h2>
                                <p className="text-[11px] text-slate-500">Nilai tagihan bertambah otomatis mengikuti penjualan bersih per batch.</p>
                            </div>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <label className="relative min-w-64">
                                    <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                    <input value={search} onChange={changeFilter(setSearch)} placeholder="Cari faktur, PBF, referensi..." className="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-xs focus:border-violet-400 focus:ring-violet-400" />
                                </label>
                                <PbfCombobox
                                    pbfs={vendors.map((name) => ({ name }))}
                                    selectedPbf={vendor === 'ALL' ? '' : vendor}
                                    onSelectPbf={(name) => {
                                        setVendor(name || 'ALL');
                                        setPage(1);
                                    }}
                                    placeholder="Filter PBF..."
                                />
                                <select value={status} onChange={changeFilter(setStatus)} className="rounded-xl border border-slate-200 px-3 py-2 text-xs">
                                    <option value="ALL">Semua status</option>
                                    <option value="READY">Siap dibayar</option>
                                    <option value="PAID">Sudah terbayar</option>
                                    <option value="NO_SALES">Belum terjual</option>
                                </select>
                            </div>
                        </div>

                        {selectionWarning && <div className="border-b border-amber-100 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-800">{selectionWarning}</div>}

                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1180px] text-left text-xs">
                                <thead className="bg-slate-50 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-3 py-3 text-center"><input aria-label="Pilih semua faktur siap dibayar pada halaman ini" type="checkbox" checked={allPageSelected} onChange={(event) => {
                                            const pageIds = payableRows.map((item) => item.id);
                                            if (!event.target.checked) {
                                                setSelectedIds((current) => current.filter((id) => !pageIds.includes(id)));
                                                return;
                                            }
                                            const remainingSlots = Math.max(0, 10 - selectedIds.length);
                                            const toAdd = pageIds.filter((id) => !selectedIds.includes(id)).slice(0, remainingSlots);
                                            setSelectedIds((current) => [...current, ...toAdd]);
                                            if (toAdd.length < pageIds.filter((id) => !selectedIds.includes(id)).length) {
                                                setSelectionWarning('Maksimal 10 faktur dapat dipilih. Sebagian faktur di halaman ini belum dipilih.');
                                                window.setTimeout(() => setSelectionWarning(''), 4000);
                                            }
                                        }} /></th>
                                        <th className="px-4 py-3">Faktur / PBF</th>
                                        <th className="px-4 py-3">Tanggal</th>
                                        <th className="px-4 py-3">Progres barang</th>
                                        <th className="px-4 py-3 text-right">Nilai terjual</th>
                                        <th className="px-4 py-3 text-right">Terbayar</th>
                                        <th className="px-4 py-3 text-right">Siap dibayar</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {rows.map((item) => {
                                        const progress = Number(item.qty_received) > 0
                                            ? Math.min(100, (Number(item.qty_sold) / Number(item.qty_received)) * 100)
                                            : 0;
                                        return (
                                            <tr key={item.id} className="hover:bg-slate-50/70">
                                                <td className="px-3 py-3 text-center"><input aria-label={`Pilih faktur ${item.nomor}`} type="checkbox" disabled={Number(item.due) <= 0} checked={selectedIds.includes(item.id)} onChange={() => toggleSelection(item)} /></td>
                                                <td className="px-4 py-3">
                                                    <button onClick={() => setDetail(item)} className="font-bold text-violet-700 hover:underline">{item.nomor}</button>
                                                    <div className="mt-0.5 text-[11px] font-semibold text-slate-700">{item.vendor}</div>
                                                    {item.transaction_numbers?.length > 0 && <div className="mt-0.5 max-w-[240px] truncate text-[10px] text-violet-600" title={item.transaction_numbers.join(', ')}>Transaksi terjual: {item.transaction_numbers.slice(0, 2).join(', ')}{item.transaction_numbers.length > 2 ? ` +${item.transaction_numbers.length - 2}` : ''}</div>}
                                                    <div className="text-[10px] text-slate-400">{item.referensi} · {item.gudang}</div>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">{item.tanggal}</td>
                                                <td className="px-4 py-3">
                                                    <div className="flex justify-between text-[10px] font-semibold text-slate-500">
                                                        <span>{formatNumberOnly(item.qty_sold)} terjual</span>
                                                        <span>{formatNumberOnly(item.qty_received)} diterima</span>
                                                    </div>
                                                    <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                                        <div className="h-full rounded-full bg-violet-500" style={{ width: `${progress}%` }} />
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-right font-semibold text-slate-700">{formatRupiah(item.payable)}</td>
                                                <td className="px-4 py-3 text-right text-slate-500">{formatRupiah(item.paid)}</td>
                                                <td className="px-4 py-3 text-right font-black text-amber-700">{formatRupiah(item.due)}</td>
                                                <td className="px-4 py-3"><span className={`rounded-full px-2.5 py-1 text-[10px] font-bold ${statusStyle[item.status] || statusStyle['Belum Ada Penjualan']}`}>{item.status}</span></td>
                                                <td className="px-4 py-3">
                                                    <div className="flex justify-end gap-1.5">
                                                        <button onClick={() => setDetail(item)} className="rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-100" title="Lihat rincian"><Eye className="h-3.5 w-3.5" /></button>
                                                        <button disabled={Number(item.due) <= 0} onClick={() => openPayment(item)} className="inline-flex items-center gap-1 rounded-lg bg-violet-600 px-3 py-2 text-[10px] font-bold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:bg-slate-300"><HandCoins className="h-3.5 w-3.5" /> Bayar</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    {rows.length === 0 && (
                                        <tr><td colSpan="9" className="px-4 py-16 text-center text-slate-400">Belum ada data konsinyasi yang sesuai filter.</td></tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-[11px] text-slate-500">
                            <span>Menampilkan {rows.length} dari {filtered.length} faktur</span>
                            <div className="flex items-center gap-2">
                                <button disabled={page <= 1} onClick={() => setPage((value) => value - 1)} className="rounded-lg border border-slate-200 p-1.5 disabled:opacity-30"><ChevronLeft className="h-4 w-4" /></button>
                                <span className="font-bold text-slate-700">{page} / {totalPages}</span>
                                <button disabled={page >= totalPages} onClick={() => setPage((value) => value + 1)} className="rounded-lg border border-slate-200 p-1.5 disabled:opacity-30"><ChevronRight className="h-4 w-4" /></button>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            {detail && (
                <div className="fixed inset-0 z-50 flex justify-end bg-slate-950/35" onClick={() => setDetail(null)}>
                    <aside className="h-full w-full max-w-2xl overflow-y-auto bg-white shadow-2xl" onClick={(event) => event.stopPropagation()}>
                        <div className="sticky top-0 flex items-start justify-between border-b border-slate-100 bg-white p-5">
                            <div><h3 className="font-black text-slate-900">Rincian {detail.nomor}</h3><p className="text-xs text-slate-500">{detail.vendor} · {detail.gudang}</p></div>
                            <button onClick={() => setDetail(null)} className="rounded-lg p-2 hover:bg-slate-100"><X className="h-5 w-5" /></button>
                        </div>
                        <div className="space-y-5 p-5">
                            <div className="grid grid-cols-3 gap-3">
                                <div className="rounded-xl bg-slate-50 p-3"><p className="text-[10px] font-bold uppercase text-slate-400">Diterima</p><p className="mt-1 font-black">{formatNumberOnly(detail.qty_received)}</p></div>
                                <div className="rounded-xl bg-violet-50 p-3"><p className="text-[10px] font-bold uppercase text-violet-500">Terjual</p><p className="mt-1 font-black text-violet-800">{formatNumberOnly(detail.qty_sold)}</p></div>
                                <div className="rounded-xl bg-amber-50 p-3"><p className="text-[10px] font-bold uppercase text-amber-600">Siap dibayar</p><p className="mt-1 font-black text-amber-800">{formatRupiah(detail.due)}</p></div>
                            </div>
                            <div className="overflow-hidden rounded-xl border border-slate-200">
                                <table className="w-full min-w-[760px] text-xs">
                                    <thead className="bg-slate-50 text-left text-[10px] uppercase text-slate-500"><tr><th className="p-3">Obat</th><th className="p-3">No. Transaksi Terjual</th><th className="p-3 text-right">Diterima</th><th className="p-3 text-right">Terjual</th><th className="p-3 text-right">Nilai terjual</th></tr></thead>
                                    <tbody className="divide-y divide-slate-100">{detail.items.map((item) => <tr key={item.id}><td className="p-3"><div className="font-bold text-slate-800">{item.nama}</div><div className="text-[10px] text-slate-400">{item.sku} · Batch {item.batch}</div></td><td className="p-3"><div className="max-w-[240px] whitespace-normal font-mono text-[10px] text-slate-600">{item.transaction_numbers?.length ? item.transaction_numbers.join(', ') : '—'}</div></td><td className="p-3 text-right">{formatNumberOnly(item.qty_received)}</td><td className="p-3 text-right font-bold text-violet-700">{formatNumberOnly(item.qty_sold)}</td><td className="p-3 text-right font-semibold">{formatRupiah(item.sold_amount)}</td></tr>)}</tbody>
                                </table>
                            </div>
                            <div>
                                <h4 className="mb-2 text-xs font-black uppercase tracking-wide text-slate-500">Riwayat pembayaran</h4>
                                <div className="space-y-2">{detail.payments.length ? detail.payments.map((payment) => <div key={payment.id} className="flex items-center justify-between rounded-xl border border-slate-200 p-3"><div><p className="text-xs font-bold">{payment.akunNama}</p><p className="text-[10px] text-slate-400">{payment.tanggal} · {payment.noReferensi}</p></div><p className="text-sm font-black text-emerald-700">{formatRupiah(payment.nominal)}</p></div>) : <p className="rounded-xl bg-slate-50 p-4 text-center text-xs text-slate-400">Belum ada pembayaran.</p>}</div>
                            </div>
                            {Number(detail.due) > 0 && <button onClick={() => { setDetail(null); openPayment(detail); }} className="w-full rounded-xl bg-violet-600 py-3 text-xs font-bold text-white hover:bg-violet-700">Bayar barang yang sudah terjual</button>}
                        </div>
                    </aside>
                </div>
            )}

            {paymentTarget && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/45 p-4" onClick={() => setPaymentTarget(null)}>
                    <form onSubmit={submitPayment} onClick={(event) => event.stopPropagation()} className="w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
                        <div className="flex items-start justify-between">
                            <div><h3 className="font-black text-slate-900">Bayar Konsinyasi</h3><p className="text-xs text-slate-500">{paymentTarget.nomor} · {paymentTarget.vendor}</p></div>
                            <button type="button" onClick={() => setPaymentTarget(null)} className="rounded-lg p-2 hover:bg-slate-100"><X className="h-5 w-5" /></button>
                        </div>
                        <div className="my-4 rounded-xl border border-violet-100 bg-violet-50 p-4">
                            <p className="text-[10px] font-bold uppercase text-violet-500">Maksimal dapat dibayar</p>
                            <p className="mt-1 text-2xl font-black text-violet-800">{formatRupiah(paymentTarget.due)}</p>
                            <p className="mt-1 text-[11px] text-violet-600">Berasal dari {formatNumberOnly(paymentTarget.qty_sold)} barang terjual bersih.</p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="text-xs font-bold text-slate-600">Akun Kas & Bank<select required value={form.account_id} onChange={(event) => setForm({ ...form, account_id: event.target.value })} className="mt-1 w-full rounded-xl border-slate-200 text-xs"><option value="">Pilih akun</option>{kasBankAccounts.map((account) => <option key={account.id} value={account.id}>{account.code} — {account.name}</option>)}</select></label>
                            <label className="text-xs font-bold text-slate-600">Tanggal pembayaran<input required type="date" value={form.payment_date} onChange={(event) => setForm({ ...form, payment_date: event.target.value })} className="mt-1 w-full rounded-xl border-slate-200 text-xs" /></label>
                            <label className="text-xs font-bold text-slate-600">Nominal<input required type="number" min="1" max={paymentTarget.due} step="0.01" value={form.amount} onChange={(event) => setForm({ ...form, amount: event.target.value })} className="mt-1 w-full rounded-xl border-slate-200 text-xs" /></label>
                            <label className="text-xs font-bold text-slate-600">Nomor referensi<input value={form.reference_number} onChange={(event) => setForm({ ...form, reference_number: event.target.value })} className="mt-1 w-full rounded-xl border-slate-200 text-xs" placeholder="Opsional" /></label>
                        </div>
                        <label className="mt-3 block text-xs font-bold text-slate-600">Catatan<textarea value={form.notes} onChange={(event) => setForm({ ...form, notes: event.target.value })} className="mt-1 w-full rounded-xl border-slate-200 text-xs" rows="2" /></label>
                        <div className="mt-5 flex justify-end gap-2"><button type="button" onClick={() => setPaymentTarget(null)} className="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600">Batal</button><button disabled={submitting} className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-xs font-bold text-white disabled:opacity-60"><Banknote className="h-4 w-4" />{submitting ? 'Menyimpan...' : 'Catat Pembayaran'}</button></div>
                    </form>
                </div>
            )}

            <FloatingActionBar
                selectedCount={selectedIds.length}
                maxLimit={10}
                totalAmount={selectedDue}
                itemLabel="faktur"
                titleAmount="Total Sisa Kewajiban Konsinyasi"
                actionLabel={`Lunasi Faktur (${selectedIds.length})`}
                actionIcon={CreditCard}
                onAction={openBulkPayment}
                onClear={() => setSelectedIds([])}
                isSubmitting={bulkSubmitting}
                themeColor="blue"
            />

            {bulkPaymentOpen && createPortal(
                <div className="fixed inset-0 z-[65] flex items-center justify-center bg-slate-950/45 p-4" onClick={() => setBulkPaymentOpen(false)}>
                    <form onSubmit={submitBulkPayment} onClick={(event) => event.stopPropagation()} className="w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
                        <div className="flex items-start justify-between">
                            <div><h3 className="font-black text-slate-900">Pelunasan Massal Konsinyasi</h3><p className="text-xs text-slate-500">{selectedIds.length} faktur terpilih</p></div>
                            <button type="button" onClick={() => setBulkPaymentOpen(false)} className="rounded-lg p-2 hover:bg-slate-100"><X className="h-5 w-5" /></button>
                        </div>
                        <div className="my-4 rounded-xl border border-violet-100 bg-violet-50 p-4"><p className="text-[10px] font-bold uppercase text-violet-500">Total sisa kewajiban</p><p className="mt-1 text-2xl font-black text-violet-800">{formatRupiah(selectedDue)}</p><p className="mt-1 text-[11px] text-violet-600">Setiap faktur dibayar sebesar nilai barang yang sudah terjual dan belum dibayar.</p></div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="text-xs font-bold text-slate-600">Akun Kas &amp; Bank<select required value={bulkForm.account_id} onChange={(event) => setBulkForm({ ...bulkForm, account_id: event.target.value })} className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-xs focus:border-violet-400 focus:ring-violet-400"><option value="">Pilih akun</option>{kasBankAccounts.map((account) => <option key={account.id} value={account.id}>{account.code} — {account.name}</option>)}</select></label>
                            <label className="text-xs font-bold text-slate-600">Tanggal pembayaran<input required type="date" value={bulkForm.payment_date} onChange={(event) => setBulkForm({ ...bulkForm, payment_date: event.target.value })} className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-xs focus:border-violet-400 focus:ring-violet-400" /></label>
                            <label className="text-xs font-bold text-slate-600">Nomor referensi<input value={bulkForm.reference_number} onChange={(event) => setBulkForm({ ...bulkForm, reference_number: event.target.value })} className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-xs focus:border-violet-400 focus:ring-violet-400" placeholder="Opsional" /></label>
                            <label className="text-xs font-bold text-slate-600">Catatan<input value={bulkForm.notes} onChange={(event) => setBulkForm({ ...bulkForm, notes: event.target.value })} className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-xs focus:border-violet-400 focus:ring-violet-400" /></label>
                        </div>
                        <div className="mt-5 flex justify-end gap-2"><button type="button" onClick={() => setBulkPaymentOpen(false)} className="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600">Batal</button><button disabled={bulkSubmitting} className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-xs font-bold text-white disabled:opacity-60"><CheckCircle2 className="h-4 w-4" />{bulkSubmitting ? 'Memproses...' : 'Lunasi Faktur Terpilih'}</button></div>
                    </form>
                </div>,
                document.body,
            )}
        </FinanceLayout>
    );
}
