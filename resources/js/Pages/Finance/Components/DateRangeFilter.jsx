import React, { useEffect, useRef } from 'react';
import flatpickr from 'flatpickr';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';
import { Calendar, RotateCcw } from 'lucide-react';
import 'flatpickr/dist/flatpickr.min.css';

export default function DateRangeFilter({ startDate, endDate, preset, onChange, onPreset, accent = 'blue' }) {
    const inputRef = useRef(null);
    const pickerRef = useRef(null);
    const activePresetClass = accent === 'amber' ? 'text-amber-700' : 'text-blue-700';
    const iconClass = accent === 'amber' ? 'text-amber-600' : 'text-blue-600';

    useEffect(() => {
        pickerRef.current = flatpickr(inputRef.current, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            altInputClass: 'w-[190px] cursor-pointer border-0 bg-transparent p-0 text-xs text-slate-700 outline-none placeholder:text-slate-400 focus:ring-0',
            locale: { ...Indonesian, rangeSeparator: ' s/d ' },
            allowInput: true,
            defaultDate: [startDate, endDate].filter(Boolean),
            onChange: (dates) => {
                const dateString = (date) => {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                };
                onChange(dates.length === 2 ? [dateString(dates[0]), dateString(dates[1])] : [dates[0] ? dateString(dates[0]) : '', '']);
            },
        });
        return () => pickerRef.current?.destroy();
    }, []);

    useEffect(() => {
        pickerRef.current?.setDate([startDate, endDate].filter(Boolean), false);
    }, [startDate, endDate]);

    return (
        <div className="flex flex-wrap items-center gap-1.5">
            <div className="flex items-center gap-0.5 rounded-xl bg-slate-100 p-1 text-xs">
                {[
                    ['all', 'Semua'],
                    ['today', 'Hari ini'],
                    ['this_month', 'Bulan ini'],
                    ['last_month', 'Bulan lalu'],
                ].map(([value, label]) => (
                    <button
                        key={value}
                        type="button"
                        onClick={() => onPreset(value)}
                        className={`whitespace-nowrap rounded-lg px-2.5 py-1.5 font-semibold transition ${preset === value ? `bg-white ${activePresetClass} shadow-xs` : 'text-slate-500 hover:text-slate-800'}`}
                    >
                        {label}
                    </button>
                ))}
            </div>
            <div className="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 shadow-xs focus-within:ring-2 focus-within:ring-slate-500/10">
                <Calendar className={`h-4 w-4 shrink-0 ${iconClass}`} aria-hidden="true" />
                <input ref={inputRef} type="text" placeholder="Rentang tanggal" aria-label="Rentang tanggal" className="hidden" />
                {(startDate || endDate) && (
                    <button type="button" onClick={() => onPreset('all')} aria-label="Hapus rentang tanggal" title="Hapus rentang tanggal" className="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                        <RotateCcw className="h-3.5 w-3.5" />
                    </button>
                )}
            </div>
        </div>
    );
}
