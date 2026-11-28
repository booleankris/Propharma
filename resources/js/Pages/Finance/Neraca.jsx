import React from 'react';
import { CircleHelp } from 'lucide-react';

function formatNeracaNumber(val, showZero = false) {
    if (val === '-' || val === null || val === undefined) return showZero ? '0' : '-';
    const num = Number(val || 0);
    if (Math.abs(num) < 0.005) return showZero ? '0' : '-';
    const isNegative = num < 0;
    const absVal = Math.abs(num);
    const hasDecimals = Math.abs(absVal - Math.trunc(absVal)) >= 0.005;
    const formatted = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: hasDecimals ? 2 : 0,
        maximumFractionDigits: 2,
    }).format(absVal);
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
    const nonOp = data.penyesuaian_non_operasional || {};
    const hasNonOp = Boolean(nonOp.has_adjustments);

    const startDate = data.startDate || '';
    const endDate = data.endDate || '';
    const dayBeforeStart = data.dayBeforeStart || '';
    const periodEnd = data.periodEnd || '';
    const hasEstimatedOpening = Boolean(data.hasEstimatedOpening);
    const hartaSelisih = Number(harta.selisih || 0);
    const kewajibanSelisih = Number(kewajiban.selisih || 0);
    const saldoSelisih = Number(saldo.selisih || 0);

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
                    <strong>Neraca Posisi Keuangan:</strong> Saldo AWAL per cutoff {formatDateIndo(dayBeforeStart)} dan Saldo AKHIR per {formatDateIndo(periodEnd)}.
                    {hasEstimatedOpening ? ' Rekening Kas/Bank yang belum memiliki Saldo Awal resmi dihitung mundur otomatis dari Saldo Buku aktual dikurangi mutasi berjalan.' : ''} Nilai persediaan dihitung otomatis dari snapshot stok fisik dan pergerakan mutasi stok.
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
                                    <div>{labaTanpaStok.label || 'LABA TANPA STOK'}</div>
                                    <div className="mt-0.5 text-[10px] font-semibold normal-case text-slate-700/80">
                                        {hasNonOp
                                            ? '(Kas + Bank + Piutang) − Hutang Dagang (Laba operasional di luar Opname & Cut-off periode)'
                                            : '(Kas + Bank + Piutang) − Hutang Dagang'}
                                    </div>
                                </td>
                                <td className="border border-yellow-400 px-4 py-3 text-right tabular-nums">
                                    {formatNeracaNumber(labaTanpaStok.awal, true)}
                                </td>
                                <td className="border border-yellow-400 px-4 py-3 text-right tabular-nums">
                                    {formatNeracaNumber(labaTanpaStok.akhir, true)}
                                </td>
                                <td className="border border-yellow-400 px-4 py-3 text-right tabular-nums">
                                    {formatNeracaNumber(labaTanpaStok.selisih, true)}
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
                                    <div>{labaDenganStok.label || 'LABA DENGAN STOK'}</div>
                                    <div className="mt-0.5 text-[10px] font-semibold normal-case text-slate-700/80">
                                        {hasNonOp
                                            ? 'Laba Tanpa Stok (Operasional) + Selisih Persediaan'
                                            : 'Laba Tanpa Stok + Persediaan'}
                                    </div>
                                </td>
                                <td className="border border-yellow-400 px-4 py-3 text-right tabular-nums">
                                    {formatNeracaNumber(labaDenganStok.awal, true)}
                                </td>
                                <td className="border border-yellow-400 px-4 py-3 text-right tabular-nums">
                                    {formatNeracaNumber(labaDenganStok.akhir, true)}
                                </td>
                                <td className="border border-yellow-400 px-4 py-3 text-right tabular-nums">
                                    {formatNeracaNumber(labaDenganStok.selisih, true)}
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
                                <td
                                    className={`border border-slate-200 px-4 py-2.5 text-right tabular-nums ${
                                        hartaSelisih < 0 ? 'text-rose-600' : hartaSelisih > 0 ? 'text-emerald-700' : 'text-slate-400'
                                    }`}
                                >
                                    {formatNeracaNumber(harta.selisih)}
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
                                <td
                                    className={`border border-slate-200 px-4 py-2.5 text-right tabular-nums ${
                                        kewajibanSelisih > 0 ? 'text-rose-600' : kewajibanSelisih < 0 ? 'text-emerald-700' : 'text-slate-400'
                                    }`}
                                >
                                    {formatNeracaNumber(kewajiban.selisih)}
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
                                <td
                                    className={`border border-slate-300 px-4 py-3 text-right tabular-nums ${
                                        saldoSelisih < 0 ? 'text-rose-600' : saldoSelisih > 0 ? 'text-emerald-700' : 'text-slate-600'
                                    }`}
                                >
                                    {formatNeracaNumber(saldo.selisih)}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {hasNonOp && (
                <section className="rounded-2xl border border-amber-200 bg-amber-50/70 px-5 py-4 text-xs text-slate-800 shadow-sm">
                    <div className="font-bold uppercase tracking-wide text-amber-900">
                        Penyesuaian Non-Operasional Periode (Dikecualikan dari Laba)
                    </div>
                    <p className="mt-1 text-[11px] text-amber-900/80">
                        Saldo AWAL &amp; AKHIR tetap menampilkan posisi riil Kas/Bank, Piutang, dan Hutang, sedangkan selisih akibat Opname Kas/Bank maupun input Tagihan Saldo Awal / Pelunasan Pra-Cut-off pada periode ini dikeluarkan agar angka <strong>Laba</strong> tetap murni operasional:
                    </p>
                    <div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        {Math.abs(Number(nonOp.opname_kas_bank || 0)) >= 0.005 && (
                            <div className="rounded-xl border border-amber-200/80 bg-white px-3 py-2">
                                <div className="text-[11px] text-slate-500">Opname Kas &amp; Bank</div>
                                <div className="mt-0.5 font-bold tabular-nums text-slate-900">
                                    {formatNeracaNumber(nonOp.opname_kas_bank, true)}
                                </div>
                            </div>
                        )}
                        {Math.abs(Number(nonOp.opening_hutang || 0)) >= 0.005 && (
                            <div className="rounded-xl border border-amber-200/80 bg-white px-3 py-2">
                                <div className="text-[11px] text-slate-500">Dampak Saldo Awal Hutang</div>
                                <div className="mt-0.5 font-bold tabular-nums text-slate-900">
                                    {formatNeracaNumber(nonOp.opening_hutang, true)}
                                </div>
                            </div>
                        )}
                        {Math.abs(Number(nonOp.opening_piutang || 0)) >= 0.005 && (
                            <div className="rounded-xl border border-amber-200/80 bg-white px-3 py-2">
                                <div className="text-[11px] text-slate-500">Dampak Saldo Awal Piutang</div>
                                <div className="mt-0.5 font-bold tabular-nums text-slate-900">
                                    {formatNeracaNumber(nonOp.opening_piutang, true)}
                                </div>
                            </div>
                        )}
                        {Math.abs(Number(nonOp.cutoff_settlement || 0)) >= 0.005 && (
                            <div className="rounded-xl border border-amber-200/80 bg-white px-3 py-2">
                                <div className="text-[11px] text-slate-500">Pelunasan Pra-Cut-off Non-Kas</div>
                                <div className="mt-0.5 font-bold tabular-nums text-slate-900">
                                    {formatNeracaNumber(nonOp.cutoff_settlement, true)}
                                </div>
                            </div>
                        )}
                        <div className="rounded-xl border border-amber-300 bg-amber-100/70 px-3 py-2">
                            <div className="text-[11px] font-semibold text-amber-900">Total Penyesuaian Non-Op</div>
                            <div className="mt-0.5 font-extrabold tabular-nums text-amber-950">
                                {formatNeracaNumber(nonOp.total, true)}
                            </div>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-white px-3 py-2">
                            <div className="text-[11px] text-slate-500">Selisih Posisi Bersih (AKHIR − AWAL)</div>
                            <div className="mt-0.5 font-bold tabular-nums text-slate-900">
                                {formatNeracaNumber(labaTanpaStok.raw_selisih ?? (Number(labaTanpaStok.akhir || 0) - Number(labaTanpaStok.awal || 0)), true)}
                            </div>
                        </div>
                    </div>
                </section>
            )}
        </div>
    );
}

