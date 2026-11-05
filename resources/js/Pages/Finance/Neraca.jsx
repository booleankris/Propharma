import React from 'react';
import { CircleHelp } from 'lucide-react';

function formatNeracaNumber(val) {
    if (val === '-' || val === null || val === undefined) return '-';
    const num = Number(val || 0);
    if (Math.abs(num) < 0.01) return '-';
    const isNegative = num < 0;
    const absVal = Math.round(Math.abs(num));
    const formatted = new Intl.NumberFormat('id-ID').format(absVal);
    return isNegative ? `(${formatted})` : formatted;
}

export default function Neraca({ data = {}, monthLabel = '' }) {
    const items = data.items || [];
    const labaTanpaStok = data.laba_tanpa_stok || {};
    const persediaan = data.persediaan || {};
    const labaDenganStok = data.laba_dengan_stok || {};
    const harta = data.harta || {};
    const kewajiban = data.kewajiban || {};
    const saldo = data.saldo || {};

    const startDate = data.startDate || '';
    const endDate = data.endDate || '';
    const dayBeforeStart = data.dayBeforeStart || '';
    const periodEnd = data.periodEnd || '';

    const formatDateIndo = (dStr) => {
        if (!dStr) return '';
        try {
            return new Date(`${dStr}T00:00:00`).toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            });
        } catch {
            return dStr;
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center gap-2 rounded-xl border border-blue-200 bg-blue-50/60 px-4 py-3 text-xs text-blue-900 shadow-sm">
                <CircleHelp className="h-4 w-4 shrink-0 text-blue-600" />
                <span>
                    <strong>Neraca Posisi Keuangan:</strong> Saldo AWAL per cutoff {formatDateIndo(dayBeforeStart)} dan Saldo AKHIR per {formatDateIndo(periodEnd)}. Nilai persediaan dihitung otomatis dari snapshot stok fisik dan pergerakan mutasi transfer/inflow/outflow.
                </span>
            </div>

            <section className="overflow-hidden rounded-2xl border border-slate-300 bg-white shadow-sm">
                <div className="border-b border-slate-200 bg-slate-50/70 px-6 py-4">
                    <h3 className="text-base font-extrabold uppercase tracking-wider text-slate-800">
                        Neraca Keuangan & Harta
                    </h3>
                    <p className="text-xs text-slate-500">
                        Perbandingan saldo kas, bank, piutang, hutang dagang, dan valuasi persediaan
                    </p>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] border-collapse text-sm">
                        <thead className="bg-slate-800 text-white font-bold uppercase tracking-wider text-xs">
                            <tr>
                                <th className="w-16 border border-slate-700 px-4 py-3 text-center">NO</th>
                                <th className="border border-slate-700 px-4 py-3 text-left">KETERANGAN</th>
                                <th className="w-48 border border-slate-700 px-4 py-3 text-right">AWAL</th>
                                <th className="w-48 border border-slate-700 px-4 py-3 text-right">AKHIR</th>
                                <th className="w-48 border border-slate-700 px-4 py-3 text-right">SELISIH</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200 font-medium">
                            {/* Items: Kas, Banks, Piutang Dagang, Piutang Usaha, Hutang Dagang */}
                            {items.map((item, idx) => {
                                const isNegativeSelisih = Number(item.selisih || 0) < 0;
                                return (
                                    <tr key={`item-${idx}`} className="hover:bg-slate-50/80 transition-colors">
                                        <td className="border border-slate-200 px-4 py-2.5 text-center text-slate-600">
                                            {item.no ?? idx + 1}
                                        </td>
                                        <td className="border border-slate-200 px-4 py-2.5 text-slate-800 font-semibold">
                                            {item.label}
                                        </td>
                                        <td className="border border-slate-200 px-4 py-2.5 text-right tabular-nums text-slate-700">
                                            {formatNeracaNumber(item.awal)}
                                        </td>
                                        <td className="border border-slate-200 px-4 py-2.5 text-right tabular-nums text-slate-700">
                                            {formatNeracaNumber(item.akhir)}
                                        </td>
                                        <td
                                            className={`border border-slate-200 px-4 py-2.5 text-right tabular-nums ${
                                                isNegativeSelisih ? 'text-rose-600 font-bold' : 'text-slate-800'
                                            }`}
                                        >
                                            {formatNeracaNumber(item.selisih)}
                                        </td>
                                    </tr>
                                );
                            })}

                            {/* LABA TANPA STOK (Yellow Highlight) */}
                            <tr className="bg-yellow-300 font-extrabold text-slate-900 border-y-2 border-yellow-400">
                                <td className="border border-yellow-400 px-4 py-3 text-center"></td>
                                <td className="border border-yellow-400 px-4 py-3 tracking-wide">
                                    {labaTanpaStok.label || 'LABA TANPA STOK'}
                                </td>
                                <td className="border border-yellow-400 px-4 py-3 text-right tabular-nums">
                                    {formatNeracaNumber(labaTanpaStok.awal)}
                                </td>
                                <td className="border border-yellow-400 px-4 py-3 text-right tabular-nums">
                                    {formatNeracaNumber(labaTanpaStok.akhir)}
                                </td>
                                <td className="border border-yellow-400 px-4 py-3 text-right tabular-nums">
                                    {formatNeracaNumber(labaTanpaStok.selisih)}
                                </td>
                            </tr>

                            {/* PERSEDIAAN */}
                            <tr className="hover:bg-slate-50/80 transition-colors">
                                <td className="border border-slate-200 px-4 py-2.5 text-center text-slate-600">
                                    {persediaan.no ?? 10}
                                </td>
                                <td className="border border-slate-200 px-4 py-2.5 text-slate-800 font-semibold">
                                    {persediaan.label || 'PERSEDIAAN'}
                                </td>
                                <td className="border border-slate-200 px-4 py-2.5 text-right tabular-nums text-slate-700">
                                    {formatNeracaNumber(persediaan.awal)}
                                </td>
                                <td className="border border-slate-200 px-4 py-2.5 text-right tabular-nums text-slate-700">
                                    {formatNeracaNumber(persediaan.akhir)}
                                </td>
                                <td
                                    className={`border border-slate-200 px-4 py-2.5 text-right tabular-nums ${
                                        Number(persediaan.selisih || 0) < 0 ? 'text-rose-600 font-bold' : 'text-slate-800'
                                    }`}
                                >
                                    {formatNeracaNumber(persediaan.selisih)}
                                </td>
                            </tr>

                            {/* LABA DENGAN STOK (Yellow Highlight) */}
                            <tr className="bg-yellow-300 font-extrabold text-slate-900 border-y-2 border-yellow-400">
                                <td className="border border-yellow-400 px-4 py-3 text-center"></td>
                                <td className="border border-yellow-400 px-4 py-3 tracking-wide">
                                    {labaDenganStok.label || 'LABA DENGAN STOK'}
                                </td>
                                <td className="border border-yellow-400 px-4 py-3 text-right tabular-nums">
                                    {formatNeracaNumber(labaDenganStok.awal)}
                                </td>
                                <td className="border border-yellow-400 px-4 py-3 text-right tabular-nums">
                                    {formatNeracaNumber(labaDenganStok.akhir)}
                                </td>
                                <td className="border border-yellow-400 px-4 py-3 text-right tabular-nums">
                                    {formatNeracaNumber(labaDenganStok.selisih)}
                                </td>
                            </tr>

                            {/* Blank Spacer */}
                            <tr className="bg-slate-50 h-4">
                                <td colSpan="5" className="border border-slate-200"></td>
                            </tr>

                            {/* HARTA */}
                            <tr className="bg-white font-bold text-slate-900">
                                <td className="border border-slate-200 px-4 py-2.5 text-center"></td>
                                <td className="border border-slate-200 px-4 py-2.5 tracking-wide">
                                    {harta.label || 'HARTA'}
                                </td>
                                <td className="border border-slate-200 px-4 py-2.5 text-right tabular-nums">
                                    {formatNeracaNumber(harta.awal)}
                                </td>
                                <td className="border border-slate-200 px-4 py-2.5 text-right tabular-nums">
                                    {formatNeracaNumber(harta.akhir)}
                                </td>
                                <td className="border border-slate-200 px-4 py-2.5 text-right tabular-nums text-slate-400">
                                    -
                                </td>
                            </tr>

                            {/* KEWAJIBAN */}
                            <tr className="bg-white font-bold text-slate-900">
                                <td className="border border-slate-200 px-4 py-2.5 text-center"></td>
                                <td className="border border-slate-200 px-4 py-2.5 tracking-wide">
                                    {kewajiban.label || 'KEWAJIBAN'}
                                </td>
                                <td className="border border-slate-200 px-4 py-2.5 text-right tabular-nums">
                                    {formatNeracaNumber(kewajiban.awal)}
                                </td>
                                <td className="border border-slate-200 px-4 py-2.5 text-right tabular-nums">
                                    {formatNeracaNumber(kewajiban.akhir)}
                                </td>
                                <td className="border border-slate-200 px-4 py-2.5 text-right tabular-nums text-slate-400">
                                    -
                                </td>
                            </tr>

                            {/* SALDO */}
                            <tr className="bg-slate-100 font-extrabold text-slate-900 border-t-2 border-slate-300">
                                <td className="border border-slate-300 px-4 py-3 text-center"></td>
                                <td className="border border-slate-300 px-4 py-3 tracking-wide">
                                    {saldo.label || 'SALDO'}
                                </td>
                                <td className="border border-slate-300 px-4 py-3 text-right tabular-nums">
                                    {formatNeracaNumber(saldo.awal)}
                                </td>
                                <td className="border border-slate-300 px-4 py-3 text-right tabular-nums">
                                    {formatNeracaNumber(saldo.akhir)}
                                </td>
                                <td className="border border-slate-300 px-4 py-3 text-right tabular-nums text-emerald-700">
                                    {formatNeracaNumber(saldo.selisih)}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    );
}

