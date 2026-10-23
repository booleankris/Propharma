import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import FinanceLayout from './Layouts/FinanceLayout';
import {
    BookOpenCheck, Search, Filter, Plus, Edit2, Trash2, Download,
    CheckCircle2, XCircle, AlertCircle, Building2, HelpCircle, X, ChevronLeft, ChevronRight
} from 'lucide-react';

export default function ReturnPolicies({ policies, filters, stats, creditors, factories, branchContext }) {
    const [search, setSearch] = useState(filters.search || '');
    const [creditorFilter, setCreditorFilter] = useState(filters.creditor_code || '');
    const [returnableFilter, setReturnableFilter] = useState(filters.is_returnable || '');

    // Modal state
    const [modalOpen, setModalOpen] = useState(false);
    const [modalMode, setModalMode] = useState('add'); // 'add' | 'edit'
    const [editingPolicy, setEditingPolicy] = useState(null);

    // Form state
    const [formData, setFormData] = useState({
        factory_id: '',
        factory_name: '',
        creditor_code: '',
        is_returnable: true,
        months_before_ed: 3,
        months_after_ed: 0,
        allow_on_ed_month: false,
        notes: '',
    });

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        router.get('/finance/return-policies', {
            search,
            creditor_code: creditorFilter,
            is_returnable: returnableFilter,
        }, { preserveState: true });
    };

    const handleFilterChange = (creditor, returnable) => {
        router.get('/finance/return-policies', {
            search,
            creditor_code: creditor,
            is_returnable: returnable,
        }, { preserveState: true });
    };

    const openAddModal = () => {
        setModalMode('add');
        setEditingPolicy(null);
        setFormData({
            factory_id: '',
            factory_name: '',
            creditor_code: creditors[0]?.code || '',
            is_returnable: true,
            months_before_ed: 3,
            months_after_ed: 0,
            allow_on_ed_month: false,
            notes: '3 BULAN SEBELUM ED',
        });
        setModalOpen(true);
    };

    const openEditModal = (p) => {
        setModalMode('edit');
        setEditingPolicy(p);
        setFormData({
            factory_id: p.factory_id || '',
            factory_name: p.factory_name || (p.factory?.name || ''),
            creditor_code: p.creditor_code || '',
            is_returnable: !!p.is_returnable,
            months_before_ed: p.months_before_ed ?? 0,
            months_after_ed: p.months_after_ed ?? 0,
            allow_on_ed_month: !!p.allow_on_ed_month,
            notes: p.notes || '',
        });
        setModalOpen(true);
    };

    const handleSubmitModal = (e) => {
        e.preventDefault();
        if (modalMode === 'add') {
            router.post('/finance/return-policies', formData, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            router.put(`/finance/return-policies/${editingPolicy.id}`, formData, {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const handleDelete = (p) => {
        if (confirm(`Yakin ingin menghapus ketentuan retur untuk ${p.factory?.name || p.factory_name} di ${p.creditor?.name || p.creditor_code}?`)) {
            router.delete(`/finance/return-policies/${p.id}`);
        }
    };

    return (
        <FinanceLayout
            title="Master Ketentuan Retur"
            subtitle="Konfigurasi batas waktu retur obat berdasarkan kombinasi Pabrik & Distributor (PBF)"
        >
            <Head title="Master Ketentuan Retur - Finance ERP" />

            <div className="mx-auto space-y-6">
                {/* Header Title & Actions */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <BookOpenCheck className="w-5 h-5" />
                            </div>
                            <div>
                                <h1 className="text-lg font-bold text-slate-800">Master Ketentuan Retur Pabrik & PBF</h1>
                                <p className="text-xs text-slate-500">
                                    Pedoman batas H- bulan sebelum ED untuk menentukan eligibilitas retur pembelian ke distributor
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-2.5">
                        <a
                            href="/finance/return-policies/export"
                            className="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all"
                        >
                            <Download className="w-4 h-4 text-slate-500" /> Export Excel
                        </a>
                        <button
                            onClick={openAddModal}
                            className="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-xs transition-all"
                        >
                            <Plus className="w-4 h-4" /> Tambah Aturan Baru
                        </button>
                    </div>
                </div>

                {/* Metrics Summary Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                        <div>
                            <span className="text-xs font-medium text-slate-500">Total Kebijakan Terdaftar</span>
                            <div className="text-2xl font-bold text-slate-800 mt-1">{stats.total} Aturan</div>
                        </div>
                        <div className="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                            <Building2 className="w-5 h-5" />
                        </div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                        <div>
                            <span className="text-xs font-medium text-slate-500">Bisa Diretur (0 s/d 9 Bulan)</span>
                            <div className="text-2xl font-bold text-emerald-600 mt-1">{stats.returnable} Pabrik/PBF</div>
                        </div>
                        <div className="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <CheckCircle2 className="w-5 h-5" />
                        </div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                        <div>
                            <span className="text-xs font-medium text-slate-500">Tidak Bisa Retur (No Retur / X)</span>
                            <div className="text-2xl font-bold text-rose-600 mt-1">{stats.non_returnable} Pabrik/PBF</div>
                        </div>
                        <div className="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                            <XCircle className="w-5 h-5" />
                        </div>
                    </div>
                </div>

                {/* Filters & Table Card */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    {/* Filters Toolbar */}
                    <div className="p-4 border-b border-slate-200 flex flex-col md:flex-row items-center justify-between gap-3 bg-slate-50/50">
                        <form onSubmit={handleSearchSubmit} className="relative w-full md:w-80">
                            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                type="text"
                                placeholder="Cari nama pabrik, PBF, atau catatan..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                            />
                        </form>

                        <div className="flex items-center gap-2 w-full md:w-auto">
                            <select
                                value={creditorFilter}
                                onChange={(e) => {
                                    setCreditorFilter(e.target.value);
                                    handleFilterChange(e.target.value, returnableFilter);
                                }}
                                className="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 max-w-[200px]"
                            >
                                <option value="">Semua PBF ({creditors.length})</option>
                                {creditors.map((c) => (
                                    <option key={c.code} value={c.code}>{c.name}</option>
                                ))}
                            </select>

                            <select
                                value={returnableFilter}
                                onChange={(e) => {
                                    setReturnableFilter(e.target.value);
                                    handleFilterChange(creditorFilter, e.target.value);
                                }}
                                className="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">Semua Status Retur</option>
                                <option value="1">Bisa Retur</option>
                                <option value="0">Tidak Bisa Retur (X)</option>
                            </select>
                        </div>
                    </div>

                    {/* Table View */}
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs text-slate-600">
                            <thead className="bg-slate-100/70 text-slate-700 font-semibold uppercase text-[11px] tracking-wider border-b border-slate-200">
                                <tr>
                                    <th className="py-3 px-4 w-12 text-center">No</th>
                                    <th className="py-3 px-4">Pabrik / Principle</th>
                                    <th className="py-3 px-4">PBF / Distributor</th>
                                    <th className="py-3 px-4 text-center">Ketentuan Retur</th>
                                    <th className="py-3 px-4 text-center">Bulan ED</th>
                                    <th className="py-3 px-4">Keterangan Khusus</th>
                                    <th className="py-3 px-4 text-center w-24">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {policies.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="7" className="text-center py-10 text-slate-400">
                                            Tidak ada data ketentuan retur yang cocok.
                                        </td>
                                    </tr>
                                ) : (
                                    policies.data.map((p, idx) => (
                                        <tr key={p.id} className="hover:bg-blue-50/40 transition-colors">
                                            <td className="py-3 px-4 text-center text-slate-400">
                                                {(policies.current_page - 1) * policies.per_page + idx + 1}
                                            </td>
                                            <td className="py-3 px-4 font-semibold text-slate-800">
                                                {p.factory?.name || p.factory_name || '-'}
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="font-medium text-slate-800">{p.creditor?.name || p.creditor_code}</div>
                                                <div className="text-[10px] text-slate-400 font-mono">{p.creditor_code}</div>
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                {p.is_returnable ? (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        H- {p.months_before_ed ?? 0} Bulan
                                                        {p.months_after_ed > 0 && ` s/d H+ ${p.months_after_ed}`}
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                        No Retur (X)
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                {p.allow_on_ed_month ? (
                                                    <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-700">
                                                        Boleh
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-300">-</span>
                                                )}
                                            </td>
                                            <td className="py-3 px-4 text-slate-500 max-w-xs truncate" title={p.notes}>
                                                {p.notes || '-'}
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                <div className="flex items-center justify-center gap-1">
                                                    <button
                                                        onClick={() => openEditModal(p)}
                                                        className="p-1.5 hover:bg-slate-100 rounded-lg text-slate-600 hover:text-blue-600 transition-colors"
                                                        title="Edit Aturan"
                                                    >
                                                        <Edit2 className="w-3.5 h-3.5" />
                                                    </button>
                                                    <button
                                                        onClick={() => handleDelete(p)}
                                                        className="p-1.5 hover:bg-rose-50 rounded-lg text-slate-400 hover:text-rose-600 transition-colors"
                                                        title="Hapus Aturan"
                                                    >
                                                        <Trash2 className="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {policies.total > policies.per_page && (
                        <div className="p-4 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
                            <div>
                                Menampilkan {policies.from || 0} - {policies.to || 0} dari {policies.total} data
                            </div>
                            <div className="flex items-center gap-1">
                                {policies.links.map((link, i) => (
                                    <button
                                        key={i}
                                        disabled={!link.url || link.active}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                        className={`px-3 py-1.5 rounded-lg font-medium transition-all ${link.active
                                                ? 'bg-blue-600 text-white font-bold'
                                                : link.url
                                                    ? 'bg-slate-50 hover:bg-slate-100 text-slate-700'
                                                    : 'text-slate-300 cursor-not-allowed'
                                            }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal Tambah / Edit Ketentuan */}
            {modalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4">
                    <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                        <div className="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
                            <div>
                                <h3 className="font-bold text-slate-800 text-sm">
                                    {modalMode === 'add' ? 'Tambah Ketentuan Retur Baru' : 'Edit Ketentuan Retur'}
                                </h3>
                                <p className="text-xs text-slate-500">Konfigurasi batas waktu retur untuk Pabrik dan PBF</p>
                            </div>
                            <button
                                onClick={() => setModalOpen(false)}
                                className="p-1 rounded-lg hover:bg-slate-200 text-slate-400 hover:text-slate-700"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleSubmitModal} className="p-5 space-y-4 text-xs">
                            {/* PBF */}
                            <div>
                                <label className="block font-semibold text-slate-700 mb-1">Distributor / PBF</label>
                                <select
                                    required
                                    value={formData.creditor_code}
                                    onChange={(e) => setFormData({ ...formData, creditor_code: e.target.value })}
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">-- Pilih PBF --</option>
                                    {creditors.map((c) => (
                                        <option key={c.code} value={c.code}>{c.name} ({c.code})</option>
                                    ))}
                                </select>
                            </div>

                            {/* Pabrik */}
                            <div>
                                <label className="block font-semibold text-slate-700 mb-1">Pabrik / Principle (Opsional dari Master)</label>
                                <select
                                    value={formData.factory_id}
                                    onChange={(e) => {
                                        const fId = e.target.value;
                                        const selected = factories.find(f => f.id == fId);
                                        setFormData({
                                            ...formData,
                                            factory_id: fId,
                                            factory_name: selected ? selected.name : formData.factory_name,
                                        });
                                    }}
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">-- Pilih Pabrik Master --</option>
                                    {factories.map((f) => (
                                        <option key={f.id} value={f.id}>{f.name}</option>
                                    ))}
                                </select>
                            </div>

                            {/* Custom Factory Name if not in master */}
                            <div>
                                <label className="block font-semibold text-slate-700 mb-1">Nama Pabrik / Kategori Khusus (Label)</label>
                                <input
                                    type="text"
                                    required
                                    placeholder="Contoh: KALBE, ALL NARKOTIKA, DEXA MEDICA"
                                    value={formData.factory_name}
                                    onChange={(e) => setFormData({ ...formData, factory_name: e.target.value })}
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            {/* Bisa Retur? */}
                            <div className="bg-slate-50 p-3 rounded-xl border border-slate-200">
                                <label className="flex items-center gap-2 cursor-pointer font-semibold text-slate-800">
                                    <input
                                        type="checkbox"
                                        checked={formData.is_returnable}
                                        onChange={(e) => setFormData({ ...formData, is_returnable: e.target.checked })}
                                        className="rounded text-blue-600 focus:ring-blue-500"
                                    />
                                    <span>Pabrik / PBF ini BISA DIRETUR</span>
                                </label>
                                <p className="text-[11px] text-slate-500 mt-1 pl-5">
                                    Centang jika produk memiliki toleransi retur sebelum ED. Jangan centang jika 'No Retur' / 'Tidak Bisa Retur'.
                                </p>
                            </div>

                            {formData.is_returnable && (
                                <div className="space-y-3 p-3 bg-blue-50/50 rounded-xl border border-blue-100">
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block font-semibold text-slate-700 mb-1">H- Bulan Sebelum ED</label>
                                            <input
                                                type="number"
                                                min="0"
                                                max="12"
                                                value={formData.months_before_ed}
                                                onChange={(e) => setFormData({ ...formData, months_before_ed: parseInt(e.target.value) || 0 })}
                                                className="w-full border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            />
                                            <span className="text-[10px] text-slate-400">0 = Saat bulan ED</span>
                                        </div>

                                        <div>
                                            <label className="block font-semibold text-slate-700 mb-1">H+ Bulan Sesudah ED (Khusus)</label>
                                            <input
                                                type="number"
                                                min="0"
                                                max="6"
                                                value={formData.months_after_ed}
                                                onChange={(e) => setFormData({ ...formData, months_after_ed: parseInt(e.target.value) || 0 })}
                                                className="w-full border border-slate-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            />
                                            <span className="text-[10px] text-slate-400">Misal 2 jika boleh s/d 2 bln sesudah ED</span>
                                        </div>
                                    </div>

                                    <div>
                                        <label className="flex items-center gap-2 cursor-pointer font-medium text-slate-700">
                                            <input
                                                type="checkbox"
                                                checked={formData.allow_on_ed_month}
                                                onChange={(e) => setFormData({ ...formData, allow_on_ed_month: e.target.checked })}
                                                className="rounded text-blue-600 focus:ring-blue-500"
                                            />
                                            <span>Boleh diretur saat bulan ED berjalan</span>
                                        </label>
                                    </div>
                                </div>
                            )}

                            {/* Catatan / Keterangan */}
                            <div>
                                <label className="block font-semibold text-slate-700 mb-1">Keterangan Tambahan</label>
                                <textarea
                                    rows="2"
                                    placeholder="Contoh: 3 BULAN SEBELUM ED & 2 BULAN SESUDAH ED"
                                    value={formData.notes}
                                    onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div className="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setModalOpen(false)}
                                    className="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 font-semibold transition-all"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow-xs transition-all"
                                >
                                    {modalMode === 'add' ? 'Simpan Ketentuan' : 'Perbarui'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </FinanceLayout>
    );
}
