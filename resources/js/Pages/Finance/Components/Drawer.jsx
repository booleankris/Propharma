import React, { useEffect, useState, useRef } from 'react';
import { X, Maximize2 } from 'lucide-react';

export default function Drawer({
    item,
    isOpen: propIsOpen,
    onClose,
    onExpand,
    title,
    subtitle,
    children,
    renderFooter,
    footer,
    maxWidth = 'max-w-xl',
}) {
    const isActuallyOpen = propIsOpen !== undefined ? Boolean(propIsOpen) : Boolean(item);
    const [rendered, setRendered] = useState(isActuallyOpen);
    const [animating, setAnimating] = useState(false);
    const drawerRef = useRef(null);
    const activeItemRef = useRef(item);

    if (item) {
        activeItemRef.current = item;
    }

    const currentItem = item || activeItemRef.current;

    // Handle mount/unmount timing
    useEffect(() => {
        if (isActuallyOpen) {
            setRendered(true);
        } else {
            setAnimating(false);
            const timer = setTimeout(() => {
                setRendered(false);
            }, 320);
            return () => clearTimeout(timer);
        }
    }, [isActuallyOpen]);

    // Handle open animation: once mounted, force reflow so initial translate-x-full is computed, then animate
    useEffect(() => {
        if (rendered && isActuallyOpen) {
            if (drawerRef.current) {
                // Force synchronous style calculation / reflow
                void drawerRef.current.offsetHeight;
            }
            const frame = requestAnimationFrame(() => {
                setAnimating(true);
            });
            return () => cancelAnimationFrame(frame);
        }
    }, [rendered, isActuallyOpen]);

    // Handle Escape key to close
    useEffect(() => {
        if (!isActuallyOpen) return;
        const handleKeyDown = (e) => {
            if (e.key === 'Escape') {
                e.stopPropagation();
                onClose?.();
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isActuallyOpen, onClose]);

    // Prevent background scrolling while drawer is active
    useEffect(() => {
        if (rendered) {
            const originalOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            return () => {
                document.body.style.overflow = originalOverflow;
            };
        }
    }, [rendered]);

    if (!rendered || (propIsOpen === undefined && !currentItem)) return null;

    const resolvedTitle = typeof title === 'function' ? (currentItem ? title(currentItem) : '') : title;
    const resolvedSubtitle = typeof subtitle === 'function' ? (currentItem ? subtitle(currentItem) : '') : subtitle;
    const resolvedFooter = typeof renderFooter === 'function'
        ? (currentItem ? renderFooter(currentItem) : null)
        : (typeof footer === 'function' ? (currentItem ? footer(currentItem) : null) : footer);

    return (
        <div
            className={`fixed inset-0 z-50 flex justify-end bg-slate-900/40 backdrop-blur-xs transition-opacity duration-300 ease-out ${
                animating ? 'opacity-100' : 'opacity-0 pointer-events-none'
            }`}
            onClick={onClose}
            aria-modal="true"
            role="dialog"
        >
            <div
                ref={drawerRef}
                className={`w-full ${maxWidth} bg-white h-full shadow-2xl flex flex-col transform-gpu transition-transform duration-320 ease-[cubic-bezier(0.16,1,0.3,1)] ${
                    animating ? 'translate-x-0' : 'translate-x-full'
                }`}
                onClick={(e) => e.stopPropagation()}
            >
                {/* Drawer Header */}
                <div className="p-6 border-b border-slate-100 flex items-center justify-between shrink-0 bg-white">
                    <div>
                        <h3 className="font-bold text-slate-900 text-base">{resolvedTitle}</h3>
                        {resolvedSubtitle && (
                            <p className="text-xs text-slate-400 mt-0.5">{resolvedSubtitle}</p>
                        )}
                    </div>
                    <div className="flex items-center gap-1">
                        {onExpand && (
                            <button
                                type="button"
                                onClick={() => {
                                    onClose?.();
                                    onExpand(currentItem);
                                }}
                                className="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 active:scale-95 rounded-xl transition cursor-pointer"
                                title="Buka Halaman Lengkap"
                            >
                                <Maximize2 className="w-4 h-4" />
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={onClose}
                            className="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 active:scale-95 rounded-xl transition cursor-pointer"
                            title="Tutup (Esc)"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>
                </div>

                {/* Drawer Body */}
                <div
                    className={`flex-1 overflow-y-auto p-6 space-y-6 text-xs transition-opacity duration-300 delay-75 ${
                        animating ? 'opacity-100' : 'opacity-0'
                    }`}
                >
                    {typeof children === 'function' ? (currentItem ? children(currentItem) : null) : children}
                </div>

                {/* Drawer Footer */}
                {resolvedFooter && (
                    <div className="p-6 border-t border-slate-100 bg-slate-50 shrink-0">
                        {resolvedFooter}
                    </div>
                )}
            </div>
        </div>
    );
}
