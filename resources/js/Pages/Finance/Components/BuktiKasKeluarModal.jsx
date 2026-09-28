import React from 'react';
import { X, Printer, Building2, CreditCard, Calendar, FileText, CheckCircle2 } from 'lucide-react';
import { formatRupiah, terbilang } from './Utils';

export default function BuktiKasKeluarModal({ isOpen, onClose, biaya, branchContext }) {
    if (!isOpen || !biaya) return null;

    const handlePrint = () => {
        window.print();
    };

    const apotekName = biaya.pharmacy_name || branchContext?.activePharmacy?.name || 'APOTEK PROPHARMA';

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <style>{`
                @media print {
                    body * {
                        visibility: hidden;
                    }
                    #printable-bukti-kas-keluar, #printable-bukti-kas-keluar * {
                        visibility: visible;
                    }
                    #printable-bukti-kas-keluar {
                        position: absolute;
                        left: 0;
                        top: 0;
                        width: 100%;
                        margin: 0;
                        padding: 24px;
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
                        <div className="p-2 bg-rose-100 text-rose-800 rounded-xl">
                            <FileText className="w-5 h-5" />
                        </div>
                        <div>
                            <h3 className="text-sm font-bold text-slate-800">Bukti Pengeluaran Kas (BKK)</h3>
                            <p className="text-[11px] text-slate-500">Pratinjau dokumen tanda bukti pengeluaran kas / bank resmi</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={handlePrint}
                            className="inline-flex items-center gap-1.5 px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer"
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
                <div id="printable-bukti-kas-keluar" className="p-8 text-slate-800 space-y-6">
                    {/* Header Lembaga / Apotek */}
                    <div className="flex items-start justify-between border-b-2 border-slate-800 pb-4">
                        <div>
                            <div className="flex items-center gap-2">
                                <Building2 className="w-6 h-6 text-rose-700" />
                                <h2 className="text-lg font-black tracking-tight text-slate-900 uppercase">
                                    {apotekName}
                                </h2>
                            </div>
                            <p className="text-xs text-slate-500 mt-1 font-medium">
                                Sistem Manajemen Keuangan & Operasional Farmasi
                            </p>
                        </div>
                        <div className="text-right">
                            <span className="inline-block px-3 py-1 bg-rose-50 text-rose-700 border border-rose-200 text-xs font-black uppercase tracking-wider rounded-lg">
                                BUKTI KAS KELUAR (BKK)
                            </span>
                            <p className="text-[11px] text-slate-500 mt-1 font-mono">
                                No: {biaya.reference_number || `BY-${biaya.id}`}
                            </p>
                        </div>
                    </div>

                    {/* Meta Info Grid */}
                    <div className="grid grid-cols-2 gap-4 bg-slate-50 border border-slate-200 rounded-2xl p-4 text-xs">
                        <div>
                            <span className="text-slate-500 block text-[10px] uppercase font-bold tracking-wider">Tanggal Transaksi</span>
                            <span className="font-semibold text-slate-800 text-sm mt-0.5 block">
                                {biaya.formatted_date || biaya.payment_date}
                            </span>
                        </div>
                        <div>
                            <span className="text-slate-500 block text-[10px] uppercase font-bold tracking-wider">Sumber Dana (Kas / Bank)</span>
                            <span className="font-semibold text-slate-800 text-sm mt-0.5 block">
                                {biaya.account_code ? `[${biaya.account_code}] ` : ''}{biaya.account_name}
                            </span>
                        </div>
                        <div>
                            <span className="text-slate-500 block text-[10px] uppercase font-bold tracking-wider">Dibayarkan Kepada / Penerima</span>
                            <span className="font-semibold text-slate-800 text-sm mt-0.5 block">
                                {biaya.recipient || '-'}
                            </span>
                        </div>
                        <div>
                            <span className="text-slate-500 block text-[10px] uppercase font-bold tracking-wider">Akun Beban Operasional</span>
                            <span className="font-semibold text-slate-800 text-sm mt-0.5 block">
                                {biaya.expense_account_code ? `[${biaya.expense_account_code}] ` : ''}{biaya.expense_account_name}
                            </span>
                        </div>
                    </div>

                    {/* Nominal Box */}
                    <div className="bg-rose-50/70 border border-rose-200 rounded-2xl p-5">
                        <div className="flex justify-between items-baseline">
                            <span className="text-xs font-bold text-rose-950 uppercase tracking-wider">Jumlah Pengeluaran:</span>
                            <span className="text-2xl font-black text-rose-700 tracking-tight">
                                {formatRupiah(biaya.amount)}
                            </span>
                        </div>
                        <div className="mt-2.5 pt-2.5 border-t border-rose-200/60">
                            <span className="text-[11px] text-rose-900 block font-semibold">Terbilang:</span>
                            <p className="text-xs italic font-medium text-slate-700 mt-0.5">
                                "{terbilang(biaya.amount)}"
                            </p>
                        </div>
                    </div>

                    {/* Keterangan */}
                    {biaya.notes && (
                        <div className="text-xs">
                            <span className="text-slate-500 block text-[10px] uppercase font-bold tracking-wider">Untuk Keperluan / Catatan:</span>
                            <p className="font-medium text-slate-700 bg-white border border-slate-200 rounded-xl p-3 mt-1 leading-relaxed">
                                {biaya.notes}
                            </p>
                        </div>
                    )}

                    {/* Tanda Tangan */}
                    <div className="pt-6 border-t border-slate-200 grid grid-cols-3 gap-6 text-center text-xs">
                        <div>
                            <p className="text-slate-500 font-semibold mb-14">Dibuat Oleh,</p>
                            <p className="font-bold text-slate-800 border-t border-slate-300 pt-1.5">
                                {biaya.user_name || 'Petugas'}
                            </p>
                            <p className="text-[10px] text-slate-400">Kasir / Finance</p>
                        </div>
                        <div>
                            <p className="text-slate-500 font-semibold mb-14">Diperiksa / Disetujui,</p>
                            <p className="font-bold text-slate-800 border-t border-slate-300 pt-1.5">
                                ( .............................. )
                            </p>
                            <p className="text-[10px] text-slate-400">Kepala Apotek / Pimpinan</p>
                        </div>
                        <div>
                            <p className="text-slate-500 font-semibold mb-14">Diterima Oleh,</p>
                            <p className="font-bold text-slate-800 border-t border-slate-300 pt-1.5">
                                {biaya.recipient !== '-' ? biaya.recipient : '( .............................. )'}
                            </p>
                            <p className="text-[10px] text-slate-400">Penerima Dana</p>
                        </div>
                    </div>

                    {/* Footer Nota */}
                    <div className="text-[10px] text-slate-400 text-center pt-4 border-t border-dashed border-slate-200 flex justify-between items-center">
                        <span>Dicetak pada: {new Date().toLocaleString('id-ID')}</span>
                        <span>Dokumen Resmi Transaksi Kas Propharma</span>
                    </div>
                </div>
            </div>
        </div>
    );
}
