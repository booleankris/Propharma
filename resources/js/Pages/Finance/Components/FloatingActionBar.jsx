import React from 'react';
import { formatRupiah } from './Utils';
import { CheckSquare, X, AlertTriangle, ArrowRight, CreditCard } from 'lucide-react';

export default function FloatingActionBar({
    selectedCount = 0,
    maxLimit = 10,
    totalAmount = 0,
    totalFullAmount = null,
    itemLabel = 'faktur',
    titleAmount = 'Total Nilai Terpilih',
    actionLabel = 'Bayar Massal',
    actionIcon: ActionIcon = CreditCard,
    onAction,
    onClear,
    isSubmitting = false,
    themeColor = 'blue', // 'blue' | 'amber' | 'emerald'
}) {
    if (selectedCount <= 0) return null;

    const isLimitReached = selectedCount >= maxLimit;

    // Pastikan kalkulasi nilai nominal aman dari floating precision
    const cleanAmount = Math.round((Number(totalAmount) || 0) * 100) / 100;
    const cleanFullAmount = totalFullAmount != null ? Math.round((Number(totalFullAmount) || 0) * 100) / 100 : null;

    // Color theme setups
    const themeStyles = {
        blue: {
            badge: 'bg-blue-500/20 text-blue-300 border-blue-500/30',
            amount: 'text-blue-400',
            btn: 'bg-blue-600 hover:bg-blue-500 text-white shadow-blue-500/25',
            glow: 'shadow-blue-500/10',
            dot: 'bg-blue-400',
        },
        amber: {
            badge: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
            amount: 'text-amber-400',
            btn: 'bg-amber-600 hover:bg-amber-500 text-white shadow-amber-500/25',
            glow: 'shadow-amber-500/10',
            dot: 'bg-amber-400',
        },
        emerald: {
            badge: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
            amount: 'text-emerald-400',
            btn: 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-emerald-500/25',
            glow: 'shadow-emerald-500/10',
            dot: 'bg-emerald-400',
        },
    }[themeColor] || {
        badge: 'bg-blue-500/20 text-blue-300 border-blue-500/30',
        amount: 'text-blue-400',
        btn: 'bg-blue-600 hover:bg-blue-500 text-white shadow-blue-500/25',
        glow: 'shadow-blue-500/10',
        dot: 'bg-blue-400',
    };

    return (
        <div className="fixed bottom-6 inset-x-0 mx-auto w-[92%] max-w-4xl z-40 animate-in slide-in-from-bottom-6 fade-in duration-200 pointer-events-auto">
            <div className={`backdrop-blur-md shadow-2xl ${themeStyles.amount} rounded-2xl p-3.5 sm:p-4 text-white flex flex-col md:flex-row md:items-center justify-between gap-3.5 sm:gap-4`}>

                {/* 1. SEKSI KIRI: Item Count & Batas */}
                <div className="flex items-center gap-3 shrink-0">
                    <div className={`w-10 h-10 rounded-xl flex items-center justify-center text-slate-300 shrink-0 shadow-inner ${themeStyles.btn}`}>
                        <CheckSquare className="w-5 h-5 text-white" />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className={`text-xs font-bold py-[4px] rounded-full px-[8px] text-[#ffffff] tracking-wide ${themeStyles.btn}`}>
                                {selectedCount} {itemLabel}
                            </span>
                            <span className="text-xs text-slate-800">terpilih</span>
                        </div>
                        <div className="flex items-center gap-1.5 mt-0.5">
                            {isLimitReached ? (
                                <span className="inline-flex items-center gap-1 text-[11px] font-bold text-[#eb4d25] bg-amber-500/20 border border-amber-500/40 px-2.5 py-0.5 rounded-full animate-pulse">
                                    <AlertTriangle className="w-3 h-3 text-[#eb4d25]" />
                                    Batas Maksimal ({maxLimit}) Tercapai
                                </span>
                            ) : (
                                <span className="inline-flex items-center text-[11px] text-[#eb4d25] font-bold rounded-full">
                                    Batas: <strong className=" ml-1">{selectedCount}</strong> / {maxLimit} {itemLabel}
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                {/* 2. SEKSI TENGAH: Total Nominal (Sangat Sensitif & Akurat) */}
                <div className="flex-1 md:border-l md:border-r border-slate-800 md:px-5 flex flex-col justify-center">
                    <div className="text-[11px] font-semibold text-slate-800 uppercase tracking-wider flex items-center justify-between">
                        <span>{titleAmount}</span>
                        {cleanFullAmount != null && cleanFullAmount !== cleanAmount && (
                            <span className="text-[10px] text-slate-800 font-medium">
                                Total Tagihan: {formatRupiah(cleanFullAmount)}
                            </span>
                        )}
                    </div>
                    <div className={`text-xl sm:text-2xl font-black ${themeStyles.amount} tracking-tight leading-none mt-1`}>
                        {formatRupiah(cleanAmount)}
                    </div>
                    <div className="text-[10px] text-slate-800 mt-1 flex items-center gap-1.5">
                        <span className={`w-1.5 h-1.5 rounded-full ${themeStyles.dot} inline-block shrink-0`} />
                        <span>Akumulasi nominal {selectedCount} {itemLabel} yang dicentang</span>
                    </div>
                </div>

                {/* 3. SEKSI KANAN: Tombol Aksi */}
                <div className="flex items-center gap-2 shrink-0 justify-end">
                    <button
                        type="button"
                        onClick={onClear}
                        className="px-3 py-2 bg-[#e61818] text-xs font-semibold text-[#fff] hover:text-white hover:bg-slate-800/80 rounded-xl transition flex items-center gap-1.5 cursor-pointer"
                        title="Batalkan semua pilihan"
                    >
                        <X className="w-4 h-4" />
                        <span className="hidden sm:inline">Batal</span>
                    </button>

                    <button
                        type="button"
                        onClick={onAction}
                        disabled={isSubmitting}
                        className={`px-4 sm:px-5 py-2.5 rounded-xl text-xs font-bold transition shadow-lg flex items-center gap-2 cursor-pointer active:scale-98 disabled:opacity-50 ${themeStyles.btn}`}
                    >
                        <ActionIcon className="w-4 h-4" />
                        <span>{actionLabel}</span>
                    </button>
                </div>

            </div>
        </div>
    );
}
