import React from 'react';
import { X, Printer, CheckCircle2, AlertCircle, Building2, User, Calendar, CreditCard, ShieldCheck } from 'lucide-react';
import { formatRupiah, formatNumberOnly } from './Utils';

export default function BuktiPelunasanModal({ isOpen, onClose, piutang }) {
    if (!isOpen || !piutang) return null;

    const isLunas = piutang.status === 'Lunas' || (Number(piutang.sisa) || 0) <= 0.005;
    const payments = piutang.payments || [];

    const handlePrint = () => {
        window.print();
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <style>{`
                @media print {
                    body * {
                        visibility: hidden;
                    }
                    #printable-bukti-pelunasan, #printable-bukti-pelunasan * {
                        visibility: visible;
                    }
                    #printable-bukti-pelunasan {
                        position: absolute;
                        left: 0;
                        top: 0;
                        width: 100%;
                        margin: 0;
                        padding: 20px;
                        background: white !important;
                    }
                    .no-print {
                        display: none !important;
                    }
                }
            `}</style>

            <div className="bg-white rounded-3xl max-w-2xl w-full shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                {/* Modal Top Bar (Screen Only) */}
                <div className="no-print px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="p-2 bg-amber-100 text-amber-800 rounded-xl">
                            <CreditCard className="w-5 h-5" />
                        </div>
                        <div>
                            <h3 className="text-sm font-bold text-slate-800">Bukti Pelunasan & Penerimaan Piutang</h3>
                            <p className="text-[11px] text-slate-500">Pratinjau dokumen tanda terima resmi</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={handlePrint}
                            className="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer"
                        >
                            <Printer className="w-4 h-4" />
                            <span>Cetak Bukti</span>
                        </button>
                        <button
                            type="button"
                            onClick={onClose}
                            className="p-2 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-200 transition cursor-pointer"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>
                </div>

                {/* Printable Receipt Body */}
                <div id="printable-bukti-pelunasan" className="p-8 text-slate-800 space-y-6">
                    {/* Header Lembaga / Apotek */}
                    <div className="flex items-start justify-between border-b-2 border-slate-800 pb-5">
                        <div>
                            <div className="flex items-center gap-2">
                                <Building2 className="w-6 h-6 text-amber-700" />
                                <h2 className="text-lg font-black tracking-tight text-slate-900 uppercase">
                                    {piutang.apotek || 'APOTEK PROPHARMA'}
                                </h2>
                            </div>
                            <p className="text-xs text-slate-500 mt-1">
                                Sistem Manajemen Keuangan & Farmasi Terintegrasi
                            </p>
                        </div>
                        <div className="text-right">
                            <span className="text-[11px] font-bold text-slate-400 uppercase tracking-widest block">
                                DOKUMEN RESMI
                            </span>
                            <div className="text-base font-extrabold text-amber-700">
                                BUKTI PELUNASAN PIUTANG
                            </div>
                            <div className="text-xs font-mono font-bold text-slate-600 mt-0.5">
                                Ref: {piutang.nomor}
                            </div>
                        </div>
                    </div>

                    {/* Status Badge & Summary Bar */}
                    <div className={`p-4 rounded-2xl flex items-center justify-between border ${isLunas
                        ? 'bg-emerald-50/70 border-emerald-200 text-emerald-900'
                        : 'bg-amber-50/70 border-amber-200 text-amber-900'
                        }`}>
                        <div className="flex items-center gap-3">
                            {isLunas ? (
                                <CheckCircle2 className="w-6 h-6 text-emerald-600 shrink-0" />
                            ) : (
                                <AlertCircle className="w-6 h-6 text-amber-600 shrink-0" />
                            )}
                            <div>
                                <div className="text-xs font-bold uppercase tracking-wider">
                                    {isLunas ? 'STATUS: LUNAS PENUH' : 'STATUS: DIBAYAR SEBAGIAN'}
                                </div>
                                <div className="text-[11px] text-slate-600 mt-0.5">
                                    {isLunas
                                        ? 'Semua kewajiban pembayaran tagihan ini telah diselesaikan.'
                                        : `Tersisa saldo tertunggak sebesar ${formatRupiah(piutang.sisa)}.`
                                    }
                                </div>
                            </div>
                        </div>

                        <div className="text-right">
                            <div className="text-[11px] text-slate-500">Total Terbayar</div>
                            <div className="text-lg font-black text-emerald-700">
                                {formatRupiah(piutang.terbayar || 0)}
                            </div>
                        </div>
                    </div>

                    {/* Metadata Transaksi */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 bg-slate-50 rounded-2xl text-xs border border-slate-200">
                        <div>
                            <span className="text-slate-400 block text-[10px] uppercase font-bold">Debitur / Instansi</span>
                            <span className="font-bold text-slate-800 text-sm mt-0.5 block">{piutang.debtor || '-'}</span>
                            {piutang.debtor_code && (
                                <span className="text-[10px] text-slate-500 font-mono">Kode: {piutang.debtor_code}</span>
                            )}
                        </div>
                        <div>
                            <span className="text-slate-400 block text-[10px] uppercase font-bold">Pasien / Dokter</span>
                            <span className="font-semibold text-slate-700 mt-0.5 block">{piutang.pasien || '-'}</span>
                            <span className="text-[10px] text-slate-400 block">Dokter: {piutang.dokter || '-'}</span>
                        </div>
                        <div>
                            <span className="text-slate-400 block text-[10px] uppercase font-bold">Tgl. Transaksi</span>
                            <span className="font-semibold text-slate-700 mt-0.5 block">{piutang.tanggal || '-'}</span>
                        </div>
                        <div>
                            <span className="text-slate-400 block text-[10px] uppercase font-bold">Jatuh Tempo</span>
                            <span className="font-bold text-slate-800 mt-0.5 block">{piutang.jatuhTempo || '-'}</span>
                        </div>
                    </div>

                    {/* Rincian Finansial */}
                    <div className="border border-slate-200 rounded-2xl overflow-hidden">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-100 text-slate-600 font-bold uppercase text-[10px] border-b border-slate-200">
                                <tr>
                                    <th className="px-4 py-2.5">Keterangan</th>
                                    <th className="px-4 py-2.5 text-right">Nominal</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                <tr>
                                    <td className="px-4 py-2.5 text-slate-700">Total Nilai Tagihan Penjualan Obat</td>
                                    <td className="px-4 py-2.5 text-right font-medium text-slate-900">{formatRupiah(piutang.total || 0)}</td>
                                </tr>
                                <tr>
                                    <td className="px-4 py-2.5 text-slate-700 font-medium">Akumulasi Pembayaran Diterima</td>
                                    <td className="px-4 py-2.5 text-right font-bold text-emerald-700">{formatRupiah(piutang.terbayar || 0)}</td>
                                </tr>
                                <tr className="bg-slate-50/70 font-bold">
                                    <td className="px-4 py-2.5 text-slate-800">Sisa Piutang Tertunggak</td>
                                    <td className={`px-4 py-2.5 text-right ${isLunas ? 'text-emerald-700' : 'text-amber-700'}`}>
                                        {formatRupiah(piutang.sisa || 0)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {/* Riwayat Mutasi Pembayaran */}
                    <div>
                        <div className="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <Calendar className="w-3.5 h-3.5 text-slate-400" />
                            <span>Riwayat Penerimaan Pembayaran Kas / Bank</span>
                        </div>
                        {payments.length === 0 ? (
                            <div className="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-500 text-center">
                                Belum ada riwayat pembayaran tercatat di modul Finance.
                            </div>
                        ) : (
                            <div className="border border-slate-200 rounded-xl overflow-hidden">
                                <table className="w-full text-left text-xs">
                                    <thead className="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200 text-[10px] uppercase">
                                        <tr>
                                            <th className="px-3 py-2">Tanggal</th>
                                            <th className="px-3 py-2">Akun Penerima</th>
                                            <th className="px-3 py-2">No. Bukti / Ref</th>
                                            <th className="px-3 py-2">Catatan</th>
                                            <th className="px-3 py-2 text-right">Nominal</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {payments.map((p, idx) => (
                                            <tr key={idx}>
                                                <td className="px-3 py-2 text-slate-600 whitespace-nowrap">{p.tanggal}</td>
                                                <td className="px-3 py-2 font-medium text-slate-800">{p.akunNama}</td>
                                                <td className="px-3 py-2 text-slate-500 font-mono text-[11px]">{p.noReferensi}</td>
                                                <td className="px-3 py-2 text-slate-500 truncate max-w-xs">{p.catatan}</td>
                                                <td className="px-3 py-2 text-right font-bold text-emerald-700 whitespace-nowrap">
                                                    {formatRupiah(p.nominal)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* Area Tanda Tangan */}
                    <div className="grid grid-cols-2 pt-8 gap-12 text-center text-xs">
                        <div>
                            <p className="text-slate-400 text-[11px] mb-14">Diterima / Diserahkan Oleh Debitur,</p>
                            <div className="border-t border-slate-400 mx-8 pt-1">
                                <span className="font-bold text-slate-800">{piutang.debtor || '( ...................................... )'}</span>
                            </div>
                        </div>
                        <div>
                            <p className="text-slate-400 text-[11px] mb-14">Petugas Keuangan / Kasir,</p>
                            <div className="border-t border-slate-400 mx-8 pt-1">
                                <span className="font-bold text-slate-800">( Staf Finance Apotek )</span>
                            </div>
                        </div>
                    </div>

                    {/* Watermark / Footer */}
                    <div className="text-[10px] text-slate-400 text-center pt-4 border-t border-slate-100 flex items-center justify-between">
                        <span>Dicetak pada: {new Date().toLocaleString('id-ID')}</span>
                        <span className="flex items-center gap-1 text-slate-500">
                            <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
                            Dokumen Sah Keuangan Propharma ERP
                        </span>
                    </div>
                </div>

                {/* Footer Modal (Screen Only) */}
                <div className="no-print px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800 hover:bg-slate-200 rounded-xl transition cursor-pointer"
                    >
                        Tutup
                    </button>
                    <button
                        type="button"
                        onClick={handlePrint}
                        className="px-5 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-xl transition shadow-xs cursor-pointer flex items-center gap-1.5"
                    >
                        <Printer className="w-4 h-4" />
                        <span>Cetak Bukti Pelunasan</span>
                    </button>
                </div>
            </div>
        </div>
    );
}
