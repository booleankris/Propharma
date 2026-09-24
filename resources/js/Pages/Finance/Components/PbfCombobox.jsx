import React, { useState, useMemo, useEffect, useRef } from 'react';
import { Building2, X, ChevronDown } from 'lucide-react';

export default function PbfCombobox({ pbfs = [], selectedPbf = '', onSelectPbf, placeholder = "Filter PBF (Kreditur)..." }) {
    const [query, setQuery] = useState(selectedPbf || '');
    const [isOpen, setIsOpen] = useState(false);
    const containerRef = useRef(null);

    useEffect(() => {
        setQuery(selectedPbf || '');
    }, [selectedPbf]);

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
        if (!q && isOpen) return pbfs.slice(0, 15);
        return pbfs
            .filter((p) => p.name.toLowerCase().includes(q) || (p.code && p.code.toLowerCase().includes(q)))
            .slice(0, 15);
    }, [pbfs, query, isOpen]);

    const handleSelect = (pbfName) => {
        setQuery(pbfName);
        onSelectPbf(pbfName);
        setIsOpen(false);
    };

    const handleClear = () => {
        setQuery('');
        onSelectPbf('');
        setIsOpen(false);
    };

    return (
        <div ref={containerRef} className="relative w-full sm:w-72">
            <div className="relative flex items-center">
                <Building2 className={`w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none ${selectedPbf ? 'text-blue-600' : 'text-slate-400'}`} />
                <input
                    type="text"
                    value={query}
                    onChange={(e) => {
                        setQuery(e.target.value);
                        setIsOpen(true);
                        if (!e.target.value) {
                            onSelectPbf('');
                        }
                    }}
                    onFocus={() => setIsOpen(true)}
                    placeholder={placeholder}
                    className={`w-full pl-9 pr-8 py-2 bg-slate-50 border rounded-xl text-xs transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white ${selectedPbf
                        ? 'border-blue-500 ring-1 ring-blue-100 font-semibold text-blue-900 bg-blue-50/40'
                        : 'border-slate-200 text-slate-800'
                        }`}
                />
                {query ? (
                    <button
                        type="button"
                        onClick={handleClear}
                        className="absolute right-2.5 top-1/2 -translate-y-1/2 p-0.5 text-slate-400 hover:text-red-500 rounded-full hover:bg-red-50 transition"
                        title="Hapus Filter PBF"
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

            {/* Dropdown Suggestions Popover */}
            {isOpen && (
                <div className="absolute left-0 right-0 top-full mt-1.5 z-50 bg-white rounded-xl border border-slate-200 shadow-xl max-h-64 overflow-y-auto divide-y divide-slate-100 animate-in fade-in zoom-in-95 duration-150">
                    <div className="p-2 bg-slate-50 text-[10px] font-semibold text-slate-500 flex items-center justify-between">
                        <span>Rekomendasi PBF / Vendor</span>
                        <span className="text-slate-400">{suggestions.length} saran</span>
                    </div>
                    {selectedPbf && (
                        <button
                            type="button"
                            onClick={handleClear}
                            className="w-full text-left px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 flex items-center gap-2 transition"
                        >
                            <X className="w-3.5 h-3.5" />
                            <span>Reset Filter (Semua PBF)</span>
                        </button>
                    )}
                    {suggestions.length === 0 ? (
                        <div className="p-3 text-center text-xs text-slate-400">
                            Tidak ada nama PBF yang cocok.
                        </div>
                    ) : (
                        suggestions.map((p) => (
                            <button
                                key={p.name}
                                type="button"
                                onClick={() => handleSelect(p.name)}
                                className={`w-full text-left px-3 py-2.5 text-xs hover:bg-blue-50/70 flex items-center justify-between gap-2 transition ${selectedPbf === p.name ? 'bg-blue-50 font-bold text-blue-700' : 'text-slate-700'
                                    }`}
                            >
                                <div className="flex items-center gap-2 overflow-hidden">
                                    <Building2 className={`w-3.5 h-3.5 shrink-0 ${selectedPbf === p.name ? 'text-blue-600' : 'text-slate-400'}`} />
                                    <span className="truncate">{p.name}</span>
                                </div>
                                {p.code && (
                                    <span className="shrink-0 text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-slate-500">
                                        {p.code}
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
