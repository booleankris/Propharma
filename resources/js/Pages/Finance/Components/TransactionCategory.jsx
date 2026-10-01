import React from 'react';

const categories = {
    PIUTANG: { label: 'Piutang Penjualan', color: 'bg-amber-50 text-amber-700 border-amber-200' },
    CASH: { label: 'Pembelian Cash', color: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    KREDIT: { label: 'Hutang Dagang', color: 'bg-blue-50 text-blue-700 border-blue-200' },
    KONSINYASI: { label: 'Konsinyasi', color: 'bg-violet-50 text-violet-700 border-violet-200' },
    BIAYA: { label: 'Biaya Operasional', color: 'bg-rose-50 text-rose-700 border-rose-200' },
};

export default function TransactionCategory({ type }) {
    const key = String(type || '').trim().toUpperCase();
    const category = categories[key] || { label: key || 'Lainnya', color: 'bg-slate-50 text-slate-700 border-slate-200' };
    return <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border whitespace-nowrap ${category.color}`}>{category.label}</span>;
}
