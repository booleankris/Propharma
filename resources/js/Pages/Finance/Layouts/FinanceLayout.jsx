import React from 'react';
import { Head, Link, usePage, router } from '@inertiajs/react';
import {
    LayoutDashboard,
    CreditCard,
    Banknote,
    Landmark,
    ShoppingCart,
    ArrowLeft,
    CheckCircle2,
    AlertCircle,
    Building2,
} from 'lucide-react';

export default function FinanceLayout({ title, subtitle, children, stats = {} }) {
    const page = usePage();
    const { auth, flash, errors, branchContext } = page.props || {};
    const currentUrl = page.url || (typeof window !== 'undefined' ? window.location.pathname : '');

    const activeBranchName = branchContext?.activePharmacy?.name || 'Apotek Sahabat';
    const activeBranchId = branchContext?.activePharmacy?.id;
    const canSwitchBranch = !!branchContext?.canSwitchBranch && (branchContext?.branches?.length > 0);
    const branches = branchContext?.branches || [];

    const handleBranchChange = (e) => {
        const branchId = e.target.value;
        router.post('/pharmacy/switch', { pharmacy_id: branchId }, {
            preserveScroll: true,
            preserveState: false,
        });
    };

    const navItems = [
        {
            name: 'Ringkasan',
            href: '/finance',
            icon: LayoutDashboard,
            active: currentUrl === '/finance' || currentUrl === '/finance/',
            badge: null,
        },
        {
            name: 'Hutang Dagang',
            href: '/finance/hutang',
            icon: CreditCard,
            active: currentUrl.startsWith('/finance/hutang'),
            badge: stats.countHutangBelumLunas > 0 ? {
                text: stats.countHutangBelumLunas,
                color: 'bg-red-50 text-red-600',
            } : null,
        },
        {
            name: 'Pembelian Cash',
            href: '/finance/cash',
            icon: Banknote,
            active: currentUrl.startsWith('/finance/cash'),
            badge: stats.totalCashCount ? {
                text: stats.totalCashCount,
                color: 'bg-emerald-50 text-emerald-600',
            } : null,
        },
        {
            name: 'Kas & Bank',
            href: '/finance/kas-bank',
            icon: Landmark,
            active: currentUrl.startsWith('/finance/kas-bank'),
            badge: null,
        },
        {
            name: 'Piutang',
            href: '/finance/piutang',
            icon: ShoppingCart,
            active: currentUrl.startsWith('/finance/piutang'),
            badge: stats.countPiutangBelumBayar > 0 ? {
                text: stats.countPiutangBelumBayar,
                color: 'bg-amber-100 text-amber-700',
            } : null,
        },
    ];

    return (
        <div className="flex h-screen bg-slate-50 font-sans antialiased text-slate-800">
            <Head title={title ? `${title} - Finance ERP` : 'Finance ERP - Sahabat'} />

            {/* ======================================================== */}
            {/* 1. SIDEBAR KIRI PERSISTEN                                 */}
            {/* ======================================================== */}
            <aside className="w-64 bg-white border-r border-slate-200 flex flex-col shrink-0 select-none shadow-xs">
                {/* Brand / Logo Apotek */}
                <div className="h-16 flex items-center px-6 border-b border-slate-100 gap-3">
                    <div className="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white font-black text-sm tracking-wider shadow-sm shadow-blue-500/20 shrink-0">
                        FP
                    </div>
                    <div className="leading-tight overflow-hidden">
                        <span className="font-bold text-slate-900 text-sm block truncate">Sahabat Finance</span>
                        <span className="text-[11px] font-semibold text-blue-600 truncate block" title={activeBranchName}>
                            {activeBranchName}
                        </span>
                    </div>
                </div>

                {/* Navigasi Menu Finance */}
                <nav className="flex-1 p-3 space-y-1 overflow-y-auto text-xs">
                    <div className="pt-2 pb-1 px-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                        Menu Utama
                    </div>

                    {navItems.map((item) => {
                        const Icon = item.icon;
                        return (
                            <Link
                                key={item.name}
                                href={item.href}
                                className={`w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold transition-colors duration-150 ${
                                    item.active
                                        ? 'bg-blue-50/80 text-blue-600 shadow-xs'
                                        : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                                }`}
                            >
                                <div className="flex items-center gap-3">
                                    <Icon className={`w-4 h-4 transition-colors ${item.active ? 'text-blue-600' : 'text-slate-400'}`} />
                                    <span>{item.name}</span>
                                </div>
                                {item.badge && (
                                    <span className={`text-[10px] px-2 py-0.5 rounded-full font-bold ${item.badge.color}`}>
                                        {item.badge.text}
                                    </span>
                                )}
                            </Link>
                        );
                    })}
                </nav>

                {/* User Info Footer & Tombol Back to Home */}
                <div className="p-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div className="flex items-center gap-2.5 overflow-hidden">
                        <div className="w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center shrink-0 text-xs">
                            {auth?.user?.name ? auth.user.name.charAt(0).toUpperCase() : 'F'}
                        </div>
                        <div className="truncate">
                            <div className="text-xs font-semibold text-slate-800 truncate">
                                {auth?.user?.name || auth?.user?.username || 'Finance Staff'}
                            </div>
                            <div className="text-[10px] text-slate-500 truncate">
                                {auth?.user?.role || 'Finance'}
                            </div>
                        </div>
                    </div>
                    <a
                        href="/home"
                        className="text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition p-1.5 rounded-lg border border-slate-200/80 shadow-2xs"
                        title="Kembali ke Dashboard Utama (/home)"
                    >
                        <ArrowLeft className="w-4 h-4" />
                    </a>
                </div>
            </aside>

            {/* ======================================================== */}
            {/* 2. AREA KONTEN UTAMA                                     */}
            {/* ======================================================== */}
            <div className="flex-1 flex flex-col overflow-hidden">
                {/* Header Atas */}
                <header className="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-8 shrink-0">
                    <div className="flex items-center gap-4">
                        <a
                            href="/home"
                            className="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:text-blue-600 hover:bg-slate-100 rounded-lg transition border border-slate-200/90 shadow-2xs"
                            title="Kembali ke Dashboard Utama (/home)"
                        >
                            <ArrowLeft className="w-3.5 h-3.5 text-slate-500" />
                            <span>Dashboard Utama</span>
                        </a>

                        <div className="h-5 w-px bg-slate-200" />

                        <div>
                            <h1 className="text-base font-bold text-slate-900 leading-tight">
                                {title || 'Finance ERP'}
                            </h1>
                            {subtitle && (
                                <p className="text-[11px] text-slate-500 leading-none mt-0.5">
                                    {subtitle}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        {canSwitchBranch ? (
                            <div className="flex items-center gap-1.5 bg-slate-50 border border-slate-200/90 rounded-xl px-2.5 py-1 shadow-2xs hover:border-blue-400 transition-colors">
                                <Building2 className="w-3.5 h-3.5 text-blue-600 shrink-0" />
                                <span className="text-[11px] font-semibold text-slate-500 shrink-0">Cabang:</span>
                                <select
                                    value={activeBranchId || ''}
                                    onChange={handleBranchChange}
                                    className="bg-transparent border-0 text-xs font-bold text-slate-800 focus:ring-0 focus:outline-none cursor-pointer py-0.5 pr-6 pl-1"
                                >
                                    {branches.map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {b.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ) : (
                            <div className="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50/80 border border-blue-200/80 rounded-xl text-xs font-semibold text-blue-700 shadow-2xs">
                                <Building2 className="w-3.5 h-3.5 text-blue-600" />
                                <span>{activeBranchName}</span>
                            </div>
                        )}

                        <span className="text-[11px] font-medium text-slate-400 bg-slate-50 border border-slate-200/80 px-2.5 py-1 rounded-full">
                            Tahun Buku {new Date().getFullYear()}
                        </span>
                    </div>
                </header>

                {/* Flash Messages */}
                {flash?.success && (
                    <div className="bg-emerald-50 border-b border-emerald-200 px-8 py-2.5 flex items-center gap-2 text-xs font-semibold text-emerald-800 animate-in fade-in">
                        <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                        <span>{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="bg-red-50 border-b border-red-200 px-8 py-2.5 flex items-center gap-2 text-xs font-semibold text-red-800 animate-in fade-in">
                        <AlertCircle className="w-4 h-4 text-red-600 shrink-0" />
                        <span>{flash.error}</span>
                    </div>
                )}

                {/* Konten Halaman */}
                <main className="flex-1 overflow-y-auto p-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
