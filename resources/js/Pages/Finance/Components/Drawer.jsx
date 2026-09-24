import React, { useEffect, useState, useRef } from 'react';
import { X } from 'lucide-react';

export default function Drawer({
    item,
    isOpen: propIsOpen,
    onClose,
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
    const activeItemRef = useRef(item);

    if (item) {
        activeItemRef.current = item;
    }

    const currentItem = item || activeItemRef.current;

    useEffect(() => {
        let timer;
        let r1, r2;

        if (isActuallyOpen) {
            setRendered(true);
            // Double requestAnimationFrame ensures DOM is mounted in initial closed state before animating open
            r1 = requestAnimationFrame(() => {
                r2 = requestAnimationFrame(() => {
                    setAnimating(true);
                });
            });
        } else if (rendered) {
            setAnimating(false);
            timer = setTimeout(() => {
                setRendered(false);
            }, 320);
        }

        return () => {
            if (r1) cancelAnimationFrame(r1);
            if (r2) cancelAnimationFrame(r2);
            if (timer) clearTimeout(timer);
        };
    }, [isActuallyOpen, rendered]);

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

    if (!rendered || !currentItem) return null;

    const resolvedTitle = typeof title === 'function' ? title(currentItem) : title;
    const resolvedSubtitle = typeof subtitle === 'function' ? subtitle(currentItem) : subtitle;
    const resolvedFooter = typeof renderFooter === 'function'
        ? renderFooter(currentItem)
        : (typeof footer === 'function' ? footer(currentItem) : footer);

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
                className={`w-full ${maxWidth} bg-white h-full shadow-2xl flex flex-col transform transition-transform duration-320 ease-[cubic-bezier(0.16,1,0.3,1)] ${
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
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 active:scale-95 rounded-xl transition cursor-pointer"
                        title="Tutup (Esc)"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                {/* Drawer Body with subtle reveal animation */}
                <div
                    className={`flex-1 overflow-y-auto p-6 space-y-6 text-xs transition-all duration-300 delay-75 ${
                        animating ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-2'
                    }`}
                >
                    {typeof children === 'function' ? children(currentItem) : children}
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
