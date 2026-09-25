import React, { useState, useMemo, useEffect, useRef } from 'react';
import { User, X, ChevronDown } from 'lucide-react';

export default function DebtorCombobox({
    debtors = [],
    selectedDebtorId = '',
    onSelectDebtor,
    placeholder = "Semua Debitur / Instansi..."
}) {
    const selectedItem = useMemo(() => {
        return debtors.find((d) => String(d.id) === String(selectedDebtorId));
    }, [debtors, selectedDebtorId]);

    const [query, setQuery] = useState(selectedItem?.name || '');
    const [isOpen, setIsOpen] = useState(false);
    const containerRef = useRef(null);

    useEffect(() => {
        setQuery(selectedItem?.name || '');
    }, [selectedItem]);

    useEffect(() => {
        const handleClickOutside = (e) => {
            if (containerRef.current && !containerRef.current.contains(e.target)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const suggestions = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q && !isOpen) return [];
        if (!q && isOpen) return debtors.slice(0, 15);
        return debtors
            .filter((d) => {
                const nameMatch = d.name && d.name.toLowerCase().includes(q);
                const codeMatch = d.code && d.code.toLowerCase().includes(q);
                return nameMatch || codeMatch;
            })
            .slice(0, 15);
    }, [debtors, query, isOpen]);

    const handleSelect = (debtor) => {
        setQuery(debtor.name);
        onSelectDebtor(debtor.id);
        setIsOpen(false);
    };

    const handleClear = () => {
        setQuery('');
        onSelectDebtor('');
        setIsOpen(false);
    };

    return (
        <div ref={containerRef} className="relative w-full sm:w-64">
            <div className="relative flex items-center">
                <User className={`w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none ${selectedDebtorId ? 'text-amber-600' : 'text-slate-400'}`} />
                <input
                    type="text"
                    value={query}
                    onChange={(e) => {
                        setQuery(e.target.value);
                        setIsOpen(true);
                        if (!e.target.value) {
                            onSelectDebtor('');
                        }
                    }}
                    onFocus={() => setIsOpen(true)}
                    placeholder={placeholder}
                    className={`w-full pl-9 pr-8 py-2 bg-slate-50 border rounded-xl text-xs transition focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white ${selectedDebtorId
                        ? 'border-amber-500 ring-1 ring-amber-100 font-semibold text-amber-900 bg-amber-50/40'
                        : 'border-slate-200 text-slate-800'
                        }`}
                />
                {selectedDebtorId || query ? (
                    <button
                        type="button"
                        onClick={handleClear}
                        className="absolute right-2.5 top-1/2 -translate-y-1/2 p-0.5 text-slate-400 hover:text-red-500 rounded-full hover:bg-red-50 transition"
                        title="Hapus Filter Debitur"
                    >
                        <X className="w-3.5 h-3.5" />
                    </button>
                ) : (
                    <button
                        type="button"
                        onClick={() => setIsOpen(!isOpen)}
                        className="absolute right-2.5 top-1/2 -translate-y-1/2 p-0.5 text-slate-400 hover:text-slate-600 rounded"
                    >
                        <ChevronDown className="w-3.5 h-3.5" />
                    </button>
                )}
            </div>

            {/* Dropdown Suggestions */}
            {isOpen && (
                <div className="absolute left-0 right-0 top-full mt-1.5 z-50 bg-white rounded-xl border border-slate-200 shadow-xl max-h-64 overflow-y-auto divide-y divide-slate-100 animate-in fade-in zoom-in-95 duration-150">
                    <div className="p-2 bg-slate-50 text-[10px] font-semibold text-slate-500 flex items-center justify-between">
                        <span>Daftar Debitur / Rekanan</span>
                        <span className="text-slate-400">{suggestions.length} ditemukan</span>
                    </div>
                    {selectedDebtorId && (
                        <button
                            type="button"
                            onClick={handleClear}
                            className="w-full text-left px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 flex items-center gap-2 transition"
                        >
                            <X className="w-3.5 h-3.5" />
                            <span>Reset Filter (Semua Debitur)</span>
                        </button>
                    )}
                    {suggestions.length === 0 ? (
                        <div className="p-3 text-center text-xs text-slate-400">
                            Tidak ada debitur yang cocok.
                        </div>
                    ) : (
                        suggestions.map((d) => (
                            <button
                                key={d.id}
                                type="button"
                                onClick={() => handleSelect(d)}
                                className={`w-full text-left px-3 py-2.5 text-xs hover:bg-amber-50/70 flex items-center justify-between gap-2 transition ${String(selectedDebtorId) === String(d.id) ? 'bg-amber-50 font-bold text-amber-800' : 'text-slate-700'
                                    }`}
                            >
                                <div className="flex items-center gap-2 overflow-hidden">
                                    <User className={`w-3.5 h-3.5 shrink-0 ${String(selectedDebtorId) === String(d.id) ? 'text-amber-600' : 'text-slate-400'}`} />
                                    <span className="truncate">{d.name}</span>
                                </div>
                                {d.code && (
                                    <span className="shrink-0 text-[10px] font-medium px-1.5 py-0.5 rounded bg-slate-100 text-slate-500">
                                        {d.code}
                                    </span>
                                )}
                            </button>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}
