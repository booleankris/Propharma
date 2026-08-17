<style>
    /* Chrome, Safari, Opera */
    .sidebar-nav::-webkit-scrollbar {
        display: none;
    }

    .modal-show {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }

    .modal-hide {
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.9);
        pointer-events: none;
    }

    .modal-transition {
        transition: all 0.25s ease-out;
    }

    .reportModalContent {
        max-height: 70vh;
        overflow-y: scroll;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fadeIn {
        animation: fadeIn 0.25s ease-out;
    }

    /* ── Sidebar ── */
    .sidebar {
        width: 240px;
        min-height: 100vh;
        background: #0f2744;
        display: flex;
        flex-direction: column;
        position: fixed;
        left: 0;
        top: 0;
        bottom: 0;
        z-index: 100;
        transition: transform 0.3s ease;
    }

    .sidebar-brand {
        padding: 24px 24px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    }

    .brand-logo {
        width: 43px;
        height: 43px;
        padding: 5px;
        background: #ffff;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .brand-name {
        font-size: 12px;
        color: #fff;
        letter-spacing: 0.02em;
    }

    .brand-sub {
        font-size: 11px;
        color: #64748b;
        font-weight: 500;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .sidebar-nav {
        padding: 20px 12px;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 2px;
        overflow-y: auto;
    }

    .nav-section-title {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #afd0ff;
        padding: 8px 12px 6px;
        margin-top: 8px;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 10px;
        color: #94a3b8;
        font-size: 13.5px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.18s ease;
        text-decoration: none;
    }

    .nav-item:hover {
        background: rgba(97, 150, 192, 0.12);
        color: #93c5fd;
    }

    .nav-item.active {
        background: rgba(97, 150, 192, 0.18);
        color: #60a5fa;
    }

    .nav-item svg {
        flex-shrink: 0;
    }

    .sidebar-footer {
        padding: 16px 12px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .user-chip {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.04);
    }

    .avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: linear-gradient(135deg, #6196c0, #3b82f6);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
        color: white;
        flex-shrink: 0;
    }

    /* ── Topbar ── */
    main {
        margin-left: 240px;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .navbar-positions {
        margin-left: 240px;
    }

    .topbar {
        background: #fff;
        border-bottom: 1px solid #e2e8f0;
        padding: 0 32px;
        height: 64px;
        display: flex;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 50;
    }

    .topbar-date {
        font-size: 13px;
        color: #64748b;
        font-weight: 500;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .topbar-btn {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        border: 1.5px solid #e2e8f0;
        background: transparent;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #64748b;
        transition: all 0.18s;
    }

    .topbar-btn:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #374151;
    }

    /* Hamburger — hidden on desktop */
    .hamburger-btn {
        display: none;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        border: 1.5px solid #e2e8f0;
        background: transparent;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #64748b;
    }

    /* Sidebar overlay (mobile) */
    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        z-index: 99;
    }

    /* ── Notification panel ── */
    .notif-overlay {
        position: absolute;
        top: 68px;
        right: 16px;
        width: 360px;
        z-index: 999;
        background: white;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 8px 24px rgba(0, 0, 0, .10);
        overflow: hidden;
    }

    .notif-header {
        padding: 16px 18px 12px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .notif-title {
        font-size: 14px;
        font-weight: 700;
        color: #0f2744;
    }

    .notif-close {
        background: none;
        border: none;
        cursor: pointer;
        color: #94a3b8;
        font-size: 18px;
    }

    .notif-list {
        max-height: 420px;
        overflow-y: auto;
    }

    .notif-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 18px;
        border-bottom: 1px solid #f8fafc;
        transition: background 0.15s;
    }

    .notif-item:hover {
        background: #f8fafc;
    }

    .notif-item:last-child {
        border-bottom: none;
    }

    .notif-dot {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }

    .dot-1 {
        background: #fef2f2;
        color: #dc2626;
    }

    .dot-2 {
        background: #ecfdf5;
        color: #16a34a;
    }

    .dot-3 {
        background: #eff6ff;
        color: #2563eb;
    }

    .dot-4 {
        background: #fff7ed;
        color: #ea580c;
    }

    .dot-5 {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .dot-6 {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .notif-name {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 4px;
    }

    .notif-meta {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 3px;
    }

    .notif-empty {
        padding: 32px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
    }

    .notif-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .badge-1 {
        background: #fef2f2;
        color: #dc2626;
    }

    .badge-2 {
        background: #ecfdf5;
        color: #16a34a;
    }

    .badge-3 {
        background: #eff6ff;
        color: #2563eb;
    }

    .badge-4 {
        background: #fff7ed;
        color: #ea580c;
    }

    .badge-5 {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .badge-6 {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .notif-qty {
        font-size: 13px;
        font-weight: 700;
        margin-left: auto;
        flex-shrink: 0;
        padding-top: 2px;
    }

    .qty-out {
        color: #dc2626;
    }

    .qty-in {
        color: #16a34a;
    }

    .qty-neutral {
        color: #7c3aed;
    }

    /* ── Mobile ── */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .sidebar-overlay.open {
            display: block;
        }

        main {
            margin-left: 0;
        }

        .navbar-positions {
            margin-left: 0;
        }

        .hamburger-btn {
            display: flex;
        }

        .topbar {
            padding: 0 16px;
        }

        .topbar-date {
            display: none;
        }

        .notif-overlay {
            position: fixed;
            top: 68px;
            right: 8px;
            left: 8px;
            width: auto;
        }

        /* Bottom-sheet modals on mobile */
        .modal-hide,
        .modal-show {
            top: auto !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            transform: none !important;
            width: 100% !important;
            max-width: 100% !important;
            border-radius: 24px 24px 0 0 !important;
            max-height: 85vh;
            overflow-y: auto;
        }

        .modal-hide {
            transform: translateY(100%) !important;
            opacity: 0;
        }

        .modal-show {
            transform: translateY(0) !important;
            opacity: 1;
        }
    }

    .select2-container .select2-selection--single {
        height: 41px !important;
    }

    .select2-container {
        z-index: 999 !important;
    }

    .select2-container .select2-selection--single .select2-selection__rendered {
        display: block;
        padding: 6px 18px !important;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>

{{-- Sidebar overlay (mobile tap to close) --}}
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:4px;">
            <div class="brand-logo">
                <img src="{{ asset('img/sahabat-main.png') }}" alt="">
            </div>
            <div>
                @php
                    $pharmacy = \App\Models\Pharmacies::where('id', getActivePharmacyId())->first();
                @endphp
                <div class="brand-name font-poppins">{{ $pharmacy->name }}</div>
                <div class="brand-sub"></div>
            </div>
        </div>
    </div>

    <div class="sidebar-nav">
        <div class="nav-section-title">Menu Utama</div>

        <a href="{{ url('dashboard') }}" class="nav-item {{ request()->is('dashboard') ? 'active' : '' }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7" />
                <rect x="14" y="3" width="7" height="7" />
                <rect x="14" y="14" width="7" height="7" />
                <rect x="3" y="14" width="7" height="7" />
            </svg>
            Dashboard
        </a>

        <a href="{{ url('transaction/upds') }}" class="nav-item {{ request()->is('transaction/*') ? 'active' : '' }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                <line x1="3" y1="6" x2="21" y2="6" />
                <path d="M16 10a4 4 0 0 1-8 0" />
            </svg>
            Penjualan
        </a>

        <a href="{{ route('receiving.index') }}" class="nav-item {{ request()->is('receiving*') ? 'active' : '' }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1" />
                <circle cx="20" cy="21" r="1" />
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
            </svg>
            Pembelian
        </a>

        <a onclick="openModal('logModal')" class="nav-item {{ request()->is('inventory*') ? 'active' : '' }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path
                    d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                <line x1="12" y1="22.08" x2="12" y2="12" />
            </svg>
            Persediaan
        </a>
        <a width="16" height="16" onclick="openModal('transfersModal')"
            class="nav-item {{ request()->is('inventory*') ? 'active' : '' }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="icon icon-tabler icons-tabler-outline icon-tabler-transfer">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M20 10h-16l5.5 -6" />
                <path d="M4 14h16l-5.5 6" />
            </svg>
            Mutasi
        </a>


        <div class="nav-section-title">Statistik</div>

        <a onclick="openModal('salesModal')" class="nav-item {{ request()->is('sales*') ? 'active' : '' }}"
            style="cursor:pointer;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="20" x2="12" y2="10" />
                <line x1="18" y1="20" x2="18" y2="4" />
                <line x1="6" y1="20" x2="6" y2="16" />
                <polyline points="1 20 23 20" />
            </svg>
            Data Penjualan
        </a>

        <a onclick="openModal('receivingModal')" class="nav-item {{ request()->is('purchase*') ? 'active' : '' }}"
            style="cursor:pointer;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <rect x="1" y="3" width="15" height="13" rx="2" />
                <path d="M16 8h4l4 5v4h-8V8z" />
                <circle cx="5.5" cy="18.5" r="2.5" />
                <circle cx="18.5" cy="18.5" r="2.5" />
            </svg>
            Data Pembelian
        </a>

        <a onclick="openModal('reportModal')" class="nav-item" style="cursor:pointer;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
                <line x1="16" y1="13" x2="8" y2="13" />
                <line x1="16" y1="17" x2="8" y2="17" />
            </svg>
            Laporan Penjualan
        </a>
        <a onclick="openModal('orderReportModal')" class="nav-item" style="cursor:pointer;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" />
                <line x1="3" y1="6" x2="21" y2="6" />
                <path d="M16 10a4 4 0 01-8 0" />
            </svg>
            Laporan Pembelian
        </a>
        <a href="{{ route('pareto.index') }}" class="nav-item" style="cursor:pointer;">

            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="icon icon-tabler icons-tabler-outline icon-tabler-chart-sankey">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M3 3v18h18" />
                <path d="M3 6h18" />
                <path d="M3 8c10 0 8 9 18 9" />
            </svg>
            Pareto
        </a>
        <a href="{{ route('invoices.index') }}" class="nav-item" style="cursor:pointer;">

            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"
                class="icon icon-tabler icons-tabler-filled icon-tabler-receipt">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path
                    d="M17 2a3 3 0 0 1 3 3v16a1 1 0 0 1 -1.555 .832l-2.318 -1.545l-1.42 1.42a1 1 0 0 1 -1.32 .083l-.094 -.083l-1.293 -1.292l-1.293 1.292a1 1 0 0 1 -1.32 .083l-.094 -.083l-1.421 -1.42l-2.317 1.545l-.019 .012l-.054 .03l-.028 .017l-.054 .023l-.05 .023l-.049 .015l-.06 .019l-.052 .009l-.057 .011l-.084 .006l-.026 .003h-.022l-.049 -.003h-.039l-.013 -.003h-.016l-.041 -.008l-.038 -.005l-.015 -.005l-.018 -.002l-.034 -.011l-.04 -.01l-.019 -.007l-.015 -.004l-.029 -.013l-.04 -.015l-.021 -.011l-.013 -.005l-.028 -.016l-.036 -.018l-.014 -.01l-.018 -.01l-.038 -.027l-.022 -.014l-.01 -.009l-.02 -.014l-.045 -.041l-.012 -.008l-.024 -.024l-.035 -.039l-.02 -.02l-.007 -.011l-.011 -.012l-.032 -.045l-.02 -.025l-.012 -.019l-.03 -.054l-.017 -.028l-.023 -.054l-.023 -.05a1 1 0 0 1 -.034 -.108l-.01 -.057l-.01 -.053l-.009 -.132v-16a3 3 0 0 1 3 -3zm-2 12h-2a1 1 0 0 0 0 2h2a1 1 0 0 0 0 -2m0 -4h-6a1 1 0 0 0 0 2h6a1 1 0 0 0 0 -2m0 -4h-6a1 1 0 1 0 0 2h6a1 1 0 0 0 0 -2" />
            </svg>
            Klaim Tagihan
        </a>
        <a href="{{ route('orders-payment.index') }}" class="nav-item" style="cursor:pointer;">

            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="icon icon-tabler icons-tabler-outline icon-tabler-notebook">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M6 4h11a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-11a1 1 0 0 1 -1 -1v-14a1 1 0 0 1 1 -1m3 0v18" />
                <path d="M13 8l2 0" />
                <path d="M13 12l2 0" />
            </svg>
            Hutang Dagang
        </a>

        <div class="nav-section-title">Master</div>

        <a onclick="openModal('masterModal')" class="nav-item" style="cursor:pointer;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <ellipse cx="12" cy="5" rx="9" ry="3" />
                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" />
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5" />
            </svg>
            Master Data
        </a>

        <div class="nav-section-title">Akun</div>

        <a href="{{ url('profile') }}" class="nav-item {{ request()->is('profile*') ? 'active' : '' }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
            </svg>
            Profil
        </a>
        <a href="{{ url('staff-stats') }}" class="nav-item {{ request()->is('staff-stats*') ? 'active' : '' }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
            </svg>
            Statistik Kasir
        </a>

        <a href="{{ route('logout') }}"
            onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="nav-item"
            style="color:#f87171;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                <polyline points="16 17 21 12 16 7" />
                <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
            Keluar
        </a>

        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">
            @csrf
        </form>
    </div>

    <div class="sidebar-footer">
        <div class="user-chip">
            <div class="avatar">{{ strtoupper(substr(Auth::user()->name, 0, 2)) }}</div>
            <div>
                <div style="font-size:13px; font-weight:600; color:#e2e8f0;">{{ Auth::user()->name }}</div>
                <div style="font-size:11px; color:#64748b;">{{ auth()->user()->getRoleNames()->first() }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Topbar --}}
<div class="navbar-positions">
    <div class="topbar justify-between md:justify-end">
        <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round">
                <line x1="3" y1="6" x2="21" y2="6" />
                <line x1="3" y1="12" x2="21" y2="12" />
                <line x1="3" y1="18" x2="21" y2="18" />
            </svg>
        </button>

        <div class="topbar-right flex items-center gap-4">

            @role('HO')
            <!-- Pharmacy Selector for HO -->
            <div class="flex items-center gap-2">
                <form action="{{ route('pharmacy.switch') }}" method="POST" class="m-0 flex items-center gap-2">
                    @csrf
                    <select name="pharmacy_id" onchange="this.form.submit()" class="text-xs sm:text-sm w-[110px] sm:w-auto border-gray-200 rounded-lg focus:ring-violet-500 focus:border-violet-500 font-medium text-gray-700 py-1.5 pl-2 pr-6 sm:pl-3 sm:pr-8 text-ellipsis overflow-hidden">
                        @php
                            // Fetch all active pharmacies excluding "Logistik", "ONLINE", "HO" if we only want 5 branches
                            // According to user: SAHABAT PMI, SAHABAT MULAWARMAN, SAHABAT MIM, SAHABAT SUTOMO, SAHABAT ANTASARI
                            $branches = \App\Models\Pharmacies::whereIn('id', [1,2,3,4,5])->get();
                            $activeId = getActivePharmacyId();
                        @endphp
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ $activeId == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
            
            <div class="hidden sm:block h-5 w-px bg-gray-200"></div>
            @endrole

            <!-- Tanggal -->
            <div class="hidden sm:block topbar-date text-sm font-medium text-gray-600">
                {{ \Carbon\Carbon::now()->locale('id')->isoFormat('dddd, D MMMM YYYY') }}
            </div>

            <!-- Garis Pemisah (Opsional, agar terlihat lebih rapi) -->
            <div class="hidden sm:block h-5 w-px bg-gray-200"></div>

            <!-- Grup Tombol Notifikasi -->
            <div class="flex items-center gap-1.5">
                <button
                    class="topbar-btn rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-all focus:outline-none"
                    onclick="toggleModal('notif-modal')" aria-label="Notifikasi stok">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                        <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                    </svg>
                </button>

                <button
                    class="topbar-btn rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-all focus:outline-none"
                    onclick="toggleModal('expiry-modal')" aria-label="Obat kedaluwarsa">
                    <svg width="18" height="18" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12a9 9 0 1 0 3-6.708M3 4v5h5" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 3" />
                    </svg>
                </button>
            </div>

            <!-- Tombol Akhiri Shift -->
            <a href="http://127.0.0.1:8000/logout"
                onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                class="inline-flex items-center gap-2 border-[1px] rounded-lg bg-red-50 px-4 py-2 text-sm font-semibold text-red-600 shadow-sm transition-all hover:bg-red-100 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1">

                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                Akhiri Shift
            </a>
        </div>
    </div>
</div>

{{-- Modal backdrop --}}
<div id="modalBackdrop" class="hidden fixed inset-0 bg-black bg-opacity-40 z-[9998]"></div>



{{-- Orders Report Modal --}}
{{-- Orders Report Modal --}}
<div id="orderReportModal" class="modal-hide modal-transition fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
    md:w-[62%] w-[800px] bg-white rounded-3xl shadow-2xl z-[9999] overflow-hidden">

    {{-- Header --}}
    <div class="bg-slate-800 px-6 py-5 flex items-center justify-between">
        <div>
            <p class="text-slate-400 text-xs font-semibold tracking-widest uppercase mb-1">Manajemen Sistem</p>
            <h2 class="text-white text-xl font-semibold">Laporan Pembelian</h2>
        </div>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white/10 rounded-2xl flex items-center justify-center">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" />
                    <line x1="3" y1="6" x2="21" y2="6" />
                    <path d="M16 10a4 4 0 01-8 0" />
                </svg>
            </div>
            <button
                class="closeModal w-10 h-10 bg-white/10 hover:bg-white/20 rounded-2xl flex items-center justify-center transition">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Scrollable content --}}
    <div class="max-h-[75vh] overflow-y-auto">
        <div id="orderReportContent" class="px-5 py-5 space-y-5">

            {{-- Report type grid --}}
            <div>
                <p class="text-xs font-semibold tracking-widest uppercase text-slate-400 mb-3">Jenis Laporan</p>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2" id="order-report-grid">

                    <button onclick="selectOrderReport(this)" data-active="true"
                        class="order-report-btn flex items-center gap-3 p-3 rounded-2xl border border-violet-200 bg-violet-50 text-left transition-all">
                        <div class="w-8 h-8 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#7c3aed"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7" />
                                <rect x="14" y="3" width="7" height="7" />
                                <rect x="3" y="14" width="7" height="7" />
                                <rect x="14" y="14" width="7" height="7" />
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-violet-700">Laporan Pembelian</span>
                    </button>

                    <button onclick="selectOrderReport(this)"
                        class="order-report-btn flex items-center gap-3 p-3 rounded-2xl border border-slate-100 bg-slate-50 text-left transition-all hover:border-slate-200 hover:bg-white">
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 flex items-center justify-center flex-shrink-0">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#059669"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18" />
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-slate-600">Faktur Pembelian</span>
                    </button>

                </div>
            </div>

            <div class="h-px bg-slate-100"></div>

            {{-- Date range --}}
            <div>
                <p class="text-xs font-semibold tracking-widest uppercase text-slate-400 mb-3">Rentang Tanggal</p>
                <div class="flex items-center gap-2">
                    <input type="date" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" id="order_start_date"
                        class="flex-1 text-sm border border-slate-200 rounded-xl px-3 py-2 text-slate-700 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300" />
                    <span class="text-slate-400 text-sm font-medium">—</span>
                    <input type="date" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" id="order_end_date"
                        class="flex-1 text-sm border border-slate-200 rounded-xl px-3 py-2 text-slate-700 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300" />
                </div>
            </div>

            <div class="h-px bg-slate-100"></div>

            {{-- Conditional filters --}}
            <div id="order_type_filter" style="display:none;">
                <p class="text-xs font-semibold tracking-widest uppercase text-slate-400 mb-3">Filter Tipe</p>
                <div class="flex gap-2">
                    <div data-value="rekap" onclick="selectOrderOption(this)"
                        class="order-opt flex items-center gap-4 p-2 px-3 flex-1 rounded-2xl border-2 border-blue-400 bg-blue-50 cursor-pointer transition-all duration-200 hover:-translate-y-0.5">
                        <div
                            class="order-icon-box w-7 h-7 rounded-xl flex items-center justify-center shrink-0 bg-blue-400 transition-all duration-200">
                            <svg class="order-icon-svg text-white" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                                <line x1="16" y1="13" x2="8" y2="13" />
                                <line x1="16" y1="17" x2="8" y2="17" />
                            </svg>
                        </div>
                        <div>
                            <p class="order-opt-title text-[12px] font-semibold tracking-tight text-blue-900 m-0">Rekap
                            </p>
                            <p class="order-opt-desc text-[9px] text-blue-500 m-0 mt-0.5">Hasil perhitungan keseluruhan
                            </p>
                        </div>
                    </div>
                    <div data-value="detail" onclick="selectOrderOption(this)"
                        class="order-opt flex items-center gap-4 p-2 px-3 flex-1 rounded-2xl border-2 border-gray-200 bg-white cursor-pointer transition-all duration-200 hover:-translate-y-0.5">
                        <div
                            class="order-icon-box w-7 h-7 rounded-xl flex items-center justify-center shrink-0 bg-gray-100 transition-all duration-200">
                            <svg class="order-icon-svg text-gray-400" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                <line x1="11" y1="8" x2="11" y2="14" />
                                <line x1="8" y1="11" x2="14" y2="11" />
                            </svg>
                        </div>
                        <div>
                            <p class="order-opt-title text-[12px] font-semibold tracking-tight text-gray-800 m-0">
                                Detail</p>
                            <p class="order-opt-desc text-[9px] text-gray-400 m-0 mt-0.5">Menampilkan seluruh data</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Supplier/Creditor select — shown only when Per Supplier + Detail --}}
            <div id="order_supplier_select" style="display:none;">
                @php $getcreditor = \App\Models\Creditor::all(); @endphp
                <p class="text-xs font-semibold tracking-widest uppercase text-slate-400 mb-3">Pilih Kreditur</p>
                <select id="order_supplier" name="order_supplier" class="w-full select2-order-supplier">
                    <option></option>
                    @foreach ($getcreditor as $creditor)
                        <option value="{{ $creditor->id }}">{{ $creditor->name }}</option>
                    @endforeach
                </select>
            </div>

        </div>
    </div>

    {{-- Footer submit — outside scroll area, always visible --}}
    <div class="mb-5 mx-5 grid grid-cols-2 gap-3">
        <button onclick="getOrderReport('preview')"
            class="w-full flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 active:scale-[0.98] text-slate-700 text-sm font-semibold py-3 rounded-2xl transition-all">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
            Preview
        </button>
        <button onclick="getOrderReport('download')"
            class="w-full flex items-center justify-center gap-2 bg-[linear-gradient(45deg,_#41a8f4,_#7cd086)] active:scale-[0.98] text-white text-sm font-semibold py-3 rounded-2xl transition-all">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            Download
        </button>
    </div>
</div>


{{-- Report Modal --}}
<div id="reportModal" class="modal-hide modal-transition fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
    w-[92%] max-w-[880px] bg-white rounded-3xl shadow-2xl z-[9999] overflow-hidden">
    <div class="bg-slate-800 px-6 py-5 flex items-center justify-between">
        <div>
            <p class="text-slate-400 text-xs font-semibold tracking-widest uppercase mb-1">Manajemen Sistem</p>
            <h2 class="text-white text-xl font-semibold" id="reporttitle">Laporan Penjualan</h2>
        </div>
        <div class="w-10 h-10 bg-white/10 rounded-2xl flex items-center justify-center">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                <polyline points="14,2 14,8 20,8" />
                <line x1="16" y1="13" x2="8" y2="13" />
                <line x1="16" y1="17" x2="8" y2="17" />
            </svg>
        </div>
    </div>
    {{-- Orders Report Modal --}}

    <div class="reportModalContent bg-red">
        <div id="reportcontent" class="px-5 py-5 space-y-5">
            <div>
                <p class="text-xs font-semibold tracking-widest uppercase text-slate-400 mb-3">Jenis Laporan</p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2" id="report-grid">

                    <button onclick="selectReport(this)" data-active="true"
                        class="report-btn flex items-center gap-3 p-3 rounded-2xl border border-violet-200 bg-violet-50 text-left transition-all">
                        <div class="w-8 h-8 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#7c3aed"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7" />
                                <rect x="14" y="3" width="7" height="7" />
                                <rect x="3" y="14" width="7" height="7" />
                                <rect x="14" y="14" width="7" height="7" />
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-violet-700">LIPH</span>
                    </button>

                    <button onclick="selectReport(this)"
                        class="report-btn flex items-center gap-3 p-3 flex-wrap rounded-2xl border border-slate-100 bg-slate-50 text-left transition-all hover:border-slate-200 hover:bg-white">
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 flex items-center justify-center flex-shrink-0">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#059669"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18" />
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-slate-600">Obat</span>
                    </button>

                    <button onclick="selectReport(this)"
                        class="report-btn flex items-center gap-3 p-3 flex-wrap rounded-2xl border border-slate-100 bg-slate-50 text-left transition-all hover:border-slate-200 hover:bg-white">
                        <div class="w-8 h-8 rounded-xl bg-amber-50 flex items-center justify-center flex-shrink-0">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#d97706"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3" />
                                <path
                                    d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83" />
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-slate-600">Golongan</span>
                    </button>

                    <button onclick="selectReport(this)"
                        class="report-btn flex items-center gap-3 p-3 flex-wrap rounded-2xl border border-slate-100 bg-slate-50 text-left transition-all hover:border-slate-200 hover:bg-white">
                        <div class="w-8 h-8 rounded-xl bg-orange-50 flex items-center justify-center flex-shrink-0">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ea580c"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 7h2l2 9h10l2-9h2M3 7l1-4h16l1 4M9 16v2m6-2v2" />
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-slate-600">Pabrik</span>
                    </button>

                    <button onclick="selectReport(this)"
                        class="report-btn flex items-center gap-3 p-3 flex-wrap rounded-2xl border border-slate-100 bg-slate-50 text-left transition-all hover:border-slate-200 hover:bg-white">
                        <div class="w-8 h-8 rounded-xl bg-pink-50 flex items-center justify-center flex-shrink-0">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#db2777"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-slate-600">Dokter</span>
                    </button>

                    <button onclick="selectReport(this)"
                        class="report-btn flex items-center gap-3 p-3 flex-wrap rounded-2xl border border-slate-100 bg-slate-50 text-left transition-all hover:border-slate-200 hover:bg-white">
                        <div class="w-8 h-8 rounded-xl bg-sky-50 flex items-center justify-center flex-shrink-0">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0284c7"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M9 12h6M9 16h6M17 2H7a2 2 0 00-2 2v18l3-3 2 3 2-3 2 3 2-3 3 3V4a2 2 0 00-2-2z" />
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-slate-600">Daftar Resep</span>
                    </button>

                    <button onclick="selectReport(this)"
                        class="report-btn col-span-2 flex items-center gap-3 p-3 rounded-2xl border border-slate-100 bg-slate-50 text-left transition-all hover:border-slate-200 hover:bg-white">
                        <div class="w-8 h-8 rounded-xl bg-teal-50 flex items-center justify-center flex-shrink-0">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0d9488"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="1 4 1 10 7 10" />
                                <path d="M3.51 15a9 9 0 102.13-9.36L1 10" />
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-slate-600">Retur Jual</span>
                    </button>

                </div>
            </div>

            <div class="h-px bg-slate-100"></div>

            <div>
                <p class="text-xs font-semibold tracking-widest uppercase text-slate-400 mb-3">Rentang Tanggal</p>
                <div class="flex items-center gap-2">
                    <input type="date" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" id="start_date"
                        name="start_date"
                        class="flex-1 text-sm border border-slate-200 rounded-xl px-3 py-2 text-slate-700 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300" />
                    <span class="text-slate-400 text-sm font-medium">—</span>
                    <input type="date" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" id="end_date"
                        name="end_date"
                        class="flex-1 text-sm border border-slate-200 rounded-xl px-3 py-2 text-slate-700 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300" />
                </div>
            </div>

            <div class="h-px bg-slate-100"></div>

            <div>
                <div id="date_filter">
                    <p class="text-xs font-semibold tracking-widest uppercase text-slate-400 mb-3">Filter</p>
                    <div class="flex gap-2 mb-3">
                        <button onclick="setFilter('semua', this)" id="btn-semua"
                            class="flex-1 flex items-center gap-2 px-3 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-600 text-sm font-semibold transition-all">
                            <span class="w-3.5 h-3.5 rounded-full border-2 border-slate-300 flex-shrink-0"></span>
                            Semua
                        </button>
                        <button onclick="setFilter('shift', this)" id="btn-shift"
                            class="flex-1 flex items-center gap-2 px-3 py-2.5 rounded-xl border border-slate-800 bg-slate-800 text-white text-sm font-semibold transition-all">
                            <span class="w-3.5 h-3.5 rounded-full border-2 border-slate-300 flex-shrink-0"></span>
                            Per Shift
                        </button>
                        <button onclick="setFilter('online', this)" id="btn-online" 
                            class="flex-1 flex items-center gap-2 px-3 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-600 text-sm font-semibold transition-all">
                            <span class="w-3.5 h-3.5 rounded-full border-2 border-slate-300 flex-shrink-0"></span>
                            Online
                        </button>
                    </div>
                </div>
                @php
                    $getshift = \App\Models\Shifts::all();
                @endphp
                <div id="shift-select">
                    <select id="shift" name="shift"
                        class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2 text-slate-700 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 pr-8">
                        @foreach ($getshift as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                        @endforeach
                    </select>
                </div>


                <div id="type_filter">

                    <div class="flex flex-col py-3 gap-2 w-full max-w-md">
                        <p class="text-xs font-semibold tracking-widest uppercase text-slate-400 mb-1">Filter Tipe</p>

                        <div class="flex gap-2">
                            <div data-value="rekap" onclick="selectOption(this)"
                                class="opt flex items-center gap-5 p-2 px-2 w-[300px] rounded-2xl border-2 border-blue-400 bg-blue-50 cursor-pointer transition-all duration-200 hover:-translate-y-0.5">
                                <div
                                    class="icon-box w-7 h-7 rounded-xl flex items-center justify-center shrink-0 bg-blue-400 transition-all duration-200">
                                    <svg class="icon-svg text-white" width="14" height="14" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                        <polyline points="14 2 14 8 20 8" />
                                        <line x1="16" y1="13" x2="8" y2="13" />
                                        <line x1="16" y1="17" x2="8" y2="17" />
                                        <polyline points="10 9 9 9 8 9" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="opt-title text-[12px] font-semibold tracking-tight text-blue-900 m-0">
                                        Rekap
                                    </p>
                                    <p class="opt-desc text-[9px] text-blue-500 m-0 mt-0.5">Hasil Perhitungan
                                        Keseluruhan
                                    </p>
                                </div>
                            </div>

                            <div data-value="detail" onclick="selectOption(this)"
                                class="opt flex items-center gap-5 p-2 px-2 w-[300px] rounded-2xl border-2 border-gray-200 bg-white cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:border-gray-300 hover:bg-gray-50">
                                <div
                                    class="icon-box w-7 h-7 rounded-xl flex items-center justify-center shrink-0 bg-gray-100 transition-all duration-200">
                                    <svg class="icon-svg text-gray-400" width="14" height="14" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <circle cx="11" cy="11" r="8" />
                                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                        <line x1="11" y1="8" x2="11" y2="14" />
                                        <line x1="8" y1="11" x2="14" y2="11" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="opt-title text-[12px] font-semibold tracking-tight text-gray-800 m-0">
                                        Detail
                                    </p>
                                    <p class="opt-desc text-[9px] text-gray-400 m-0 mt-0.5">Menampilkan Seluruh Data
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="factory-select">
                    @php
                        $getfactory = \App\Models\Factory::all();
                    @endphp
                    <p class="text-xs font-semibold tracking-widest uppercase text-slate-400 mb-3">Pilih Pabrik</p>
                    <select id="factory" name="factory" class="w-full select2-factory">
                        <option></option>
                        @foreach ($getfactory as $factory)
                            <option value="{{ $factory->id }}">{{ $factory->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="doctor-select">
                    @php
                        $getdoctor = \App\Models\Doctors::all();
                    @endphp
                    <p class="text-xs font-semibold tracking-widest uppercase text-slate-400 mb-3">Pilih Dokter</p>
                    <select id="doctor" name="doctor" class="w-full py-3 select2-doctor">
                        <option></option>
                        @foreach ($getdoctor as $doctor)
                            <option value="{{ $doctor->id }}">{{ $doctor->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            {{-- Submit Report --}}
        </div>
    </div>
    <div class="mb-5 mx-5 grid grid-cols-2 gap-3">
        <button onclick="getReport('preview')"
            class="w-full flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 active:scale-[0.98] text-slate-700 text-sm font-semibold py-3 rounded-2xl transition-all">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
            Preview
        </button>
        <button onclick="getReport('download')"
            class="w-full flex items-center justify-center gap-2 bg-[linear-gradient(45deg,_#41a8f4,_#7cd086)] hover:opacity-90 active:scale-[0.98] text-white text-sm font-semibold py-3 rounded-2xl transition-all">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            Download
        </button>
    </div>

</div>




{{-- Master Modal --}}
<div id="masterModal" class="modal-hide modal-transition fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
    w-[92%] max-w-[480px] bg-white rounded-3xl shadow-2xl z-[9999] overflow-hidden">
    <div class="relative bg-gradient-to-br from-[#4a90d9] via-[#6196c0] to-[#3a7bd5] px-6 pt-6 pb-8">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-4 w-16 h-16 bg-white/10 rounded-full translate-y-1/2"></div>
        <div class="relative flex items-center justify-between">
            <div>
                <p class="text-blue-100 text-xs font-medium tracking-widest uppercase mb-1">Manajemen Sistem</p>
                <h2 class="text-2xl font-bold text-white">Master Data</h2>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
            </div>
        </div>
    </div>
    <div class="px-5 py-5 max-h-[60vh] overflow-y-auto">
        <div class="grid grid-cols-2 gap-3">
            <a href="{{ route('medicines.index') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-blue-50   hover:bg-blue-500   border border-blue-100   hover:border-blue-500   transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-blue-100   group-hover:bg-blue-400   flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-blue-500   group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-blue-700   group-hover:text-white transition-colors">Master
                    Obat</span>
            </a>
            <a href="{{ route('debtors.index') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-emerald-50 hover:bg-emerald-500 border border-emerald-100 hover:border-emerald-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-emerald-100 group-hover:bg-emerald-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-emerald-500 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-emerald-700 group-hover:text-white transition-colors">Master
                    Debitur</span>
            </a>
            <a href="{{ route('categories.index') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-violet-50  hover:bg-violet-500  border border-violet-100  hover:border-violet-500  transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-violet-100  group-hover:bg-violet-400  flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-violet-500  group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-violet-700  group-hover:text-white transition-colors">Kategori
                    Obat</span>
            </a>
            <a href="{{ route('creditors.index') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-amber-50  hover:bg-amber-500  border border-amber-100  hover:border-amber-500  transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-amber-100  group-hover:bg-amber-400  flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-amber-500  group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-amber-700  group-hover:text-white transition-colors">Master
                    Kreditur</span>
            </a>
            <a href="{{ route('compositions.index') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-cyan-50   hover:bg-cyan-500   border border-cyan-100   hover:border-cyan-500   transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-cyan-100   group-hover:bg-cyan-400   flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-cyan-500   group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-cyan-700   group-hover:text-white transition-colors">Master
                    Komposisi</span>
            </a>
            <a href="{{ route('doctors.index') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-rose-50   hover:bg-rose-500   border border-rose-100   hover:border-rose-500   transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-rose-100   group-hover:bg-rose-400   flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-rose-500   group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-rose-700   group-hover:text-white transition-colors">Master
                    Dokter</span>
            </a>
            <a href="{{ route('patients.index') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-teal-50   hover:bg-teal-500   border border-teal-100   hover:border-teal-500   transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-teal-100   group-hover:bg-teal-400   flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-teal-500   group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-teal-700   group-hover:text-white transition-colors">Master
                    Pasien</span>
            </a>
            <a href="{{ route('factories.index') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-orange-50  hover:bg-orange-500  border border-orange-100  hover:border-orange-500  transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-orange-100  group-hover:bg-orange-400  flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-orange-500  group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 7h2l2 9h10l2-9h2M3 7l1-4h16l1 4M9 16v2m6-2v2" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-orange-700  group-hover:text-white transition-colors">Master
                    Pabrik</span>
            </a>
            <a href="{{ route('parameters.index') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-indigo-50  hover:bg-indigo-500  border border-indigo-100  hover:border-indigo-500  transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-indigo-100  group-hover:bg-indigo-400  flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-indigo-500  group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-indigo-700  group-hover:text-white transition-colors">Master
                    Parameter</span>
            </a>
            <a href="{{ route('locations.index') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-pink-50   hover:bg-pink-500   border border-pink-100   hover:border-pink-500   transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-pink-100   group-hover:bg-pink-400   flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-pink-500   group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-pink-700   group-hover:text-white transition-colors">Master
                    Lokasi</span>
            </a>
            <a href="{{ route('items.index') }}"
                class="group col-span-2 flex items-center gap-3 p-3.5 rounded-2xl bg-lime-50 hover:bg-lime-500 border border-lime-100 hover:border-lime-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-lime-100 group-hover:bg-lime-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-lime-600 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-lime-700 group-hover:text-white transition-colors">Master
                    Etalase</span>
            </a>
        </div>
        <button
            class="closeModal mt-5 w-full py-2.5 flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold rounded-2xl transition-all">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Tutup
        </button>
    </div>
</div>

{{-- Persediaan Modal --}}
<div id="logModal" class="modal-hide modal-transition fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
    w-[92%] max-w-[480px] bg-white rounded-3xl shadow-2xl z-[9999] overflow-hidden">
    <div class="relative bg-gradient-to-br from-[#4a90d9] via-[#6196c0] to-[#3a7bd5] px-6 pt-6 pb-8">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-4 w-16 h-16 bg-white/10 rounded-full translate-y-1/2"></div>
        <div class="relative flex items-center justify-between">
            <div>
                <p class="text-blue-100 text-xs font-medium tracking-widest uppercase mb-1">Manajemen Sistem</p>
                <h2 class="text-2xl font-bold text-white">Persediaan</h2>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
            </div>
        </div>
    </div>
    <div class="px-5 py-6 max-h-[60vh] overflow-y-auto">
        <div class="grid grid-cols-2 gap-3">

            <a href="{{ route('supplies.index') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-blue-50 hover:bg-blue-500 border border-blue-100 hover:border-blue-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-blue-100 group-hover:bg-blue-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-blue-600 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 10h18M3 14h18M10 3v18M14 3v18M3 6a3 3 0 013-3h12a3 3 0 013 3v12a3 3 0 01-3 3H6a3 3 0 01-3-3V6z" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-blue-700 group-hover:text-white transition-colors">Kartu
                    Stok</span>
            </a>

            <a href="{{ route('supplies.stockDetail') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-green-50 hover:bg-green-500 border border-green-100 hover:border-green-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-green-100 group-hover:bg-green-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-green-600 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-green-700 group-hover:text-white transition-colors">Stok
                    Pelayanan</span>
            </a>

            <a href="{{ route('supplies.storage') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-purple-50 hover:bg-purple-500 border border-purple-100 hover:border-purple-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-purple-100 group-hover:bg-purple-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-purple-600 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-purple-700 group-hover:text-white transition-colors">Stok
                    Gudang</span>
            </a>

            <a href="{{ route('supplies.stockData') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-orange-50 hover:bg-orange-500 border border-orange-100 hover:border-orange-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-orange-100 group-hover:bg-orange-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-orange-600 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-orange-700 group-hover:text-white transition-colors">Data
                    Stok</span>
            </a>

            {{-- 5th item spans full width --}}
            <a href="{{ route('supplies.stockOpname') }}"
                class="col-span-2 group flex items-center gap-3 p-3.5 rounded-2xl bg-amber-50 hover:bg-amber-500 border border-amber-100 hover:border-amber-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-amber-100 group-hover:bg-amber-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-amber-600 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-amber-700 group-hover:text-white transition-colors">Stok
                    Opname</span>
            </a>

        </div>
        <button
            class="closeModal mt-5 w-full py-2.5 flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold rounded-2xl transition-all">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Tutup
        </button>
    </div>
</div>

{{-- Pembelian Modal --}}
<div id="receivingModal" class="modal-hide modal-transition fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
    w-[92%] max-w-[480px] bg-white rounded-3xl shadow-2xl z-[9999] overflow-hidden">
    <div class="relative bg-gradient-to-br from-[#4a90d9] via-[#6196c0] to-[#3a7bd5] px-6 pt-6 pb-8">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-4 w-16 h-16 bg-white/10 rounded-full translate-y-1/2"></div>
        <div class="relative flex items-center justify-between">
            <div>
                <p class="text-blue-100 text-xs font-medium tracking-widest uppercase mb-1">Manajemen Sistem</p>
                <h2 class="text-2xl font-bold text-white">Pembelian</h2>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </div>
        </div>
    </div>
    <div class="px-5 py-6 max-h-[60vh] overflow-y-auto">
        <div class="grid grid-cols-2 gap-3">

            <a href="{{ route('receiving.index') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-blue-50 hover:bg-blue-500 border border-blue-100 hover:border-blue-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-blue-100 group-hover:bg-blue-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-blue-600 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </div>
                <span
                    class="text-sm font-semibold text-blue-700 group-hover:text-white transition-colors leading-tight">Pemesanan
                    & Penerimaan</span>
            </a>

            <a href="{{ route('receiving.history') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-green-50 hover:bg-green-500 border border-green-100 hover:border-green-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-green-100 group-hover:bg-green-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-green-600 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-green-700 group-hover:text-white transition-colors">History
                    Harga Beli</span>
            </a>

            <a href="{{ route('medicine-order-history.index') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-purple-50 hover:bg-purple-500 border border-purple-100 hover:border-purple-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-purple-100 group-hover:bg-purple-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-purple-600 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-purple-700 group-hover:text-white transition-colors">Data
                    Pembelian</span>
            </a>

            <a href="{{ route('returdata.returorders') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-amber-50 hover:bg-amber-500 border border-amber-100 hover:border-amber-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-amber-100 group-hover:bg-amber-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-amber-600 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-amber-700 group-hover:text-white transition-colors">Retur
                    Pembelian</span>
            </a>

        </div>
        <button
            class="closeModal mt-5 w-full py-2.5 flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold rounded-2xl transition-all">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Tutup
        </button>
    </div>
</div>

{{-- Penjualan Modal --}}
{{-- <div id="salesModal" class="modal-hide modal-transition fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
    w-[90%] max-w-[400px] bg-white rounded-3xl shadow-2xl z-[9999] overflow-hidden">
    <div class="relative bg-gradient-to-br from-[#4a90d9] via-[#6196c0] to-[#3a7bd5] px-6 pt-6 pb-8">
        <div class="relative flex items-center justify-between">
            <div>
                <p class="text-blue-100 text-xs font-medium tracking-widest uppercase mb-1">Manajemen Sistem</p>
                <h2 class="text-2xl font-bold text-white">Penjualan</h2>
            </div>
        </div>
    </div>
    <div class="px-5 py-5">
        <div class="flex flex-col gap-2">
            <a href="{{ route('salesdata.index') }}"
                class="py-3 px-4 bg-[#6196c0] hover:bg-[#4a7aaa] text-center text-white rounded-xl font-medium transition-colors">Data
                Penjualan</a>
            <a href="{{ route('returdata.retur') }}"
                class="py-3 px-4 bg-[#6196c0] hover:bg-[#4a7aaa] text-center text-white rounded-xl font-medium transition-colors">Retur
                Penjualan</a>
            <a href="{{ route('sales.reject') }}"
                class="py-3 px-4 bg-[#6196c0] hover:bg-[#4a7aaa] text-center text-white rounded-xl font-medium transition-colors">Penolakan</a>
        </div>
        <button
            class="closeModal mt-5 w-full py-2.5 flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold rounded-2xl transition-all">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Tutup
        </button>
    </div>
</div>

<div id="salesModal" class="modal-hide modal-transition fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
    w-[90%] max-w-[400px] bg-white rounded-3xl shadow-2xl z-[9999] overflow-hidden">
    <div class="relative bg-gradient-to-br from-[#4a90d9] via-[#6196c0] to-[#3a7bd5] px-6 pt-6 pb-8">
        <div class="relative flex items-center justify-between">
            <div>
                <p class="text-blue-100 text-xs font-medium tracking-widest uppercase mb-1">Manajemen Sistem</p>
                <h2 class="text-2xl font-bold text-white">Penjualan</h2>
            </div>
        </div>
    </div>
    <div class="px-5 py-5">
        <div class="flex flex-col gap-2">
            <a href="{{ route('salesdata.index') }}"
                class="py-3 px-4 bg-[#6196c0] hover:bg-[#4a7aaa] text-center text-white rounded-xl font-medium transition-colors">Data
                Penjualan</a>
            <a href="{{ route('returdata.retur') }}"
                class="py-3 px-4 bg-[#6196c0] hover:bg-[#4a7aaa] text-center text-white rounded-xl font-medium transition-colors">Retur
                Penjualan</a>
            <a href="{{ route('sales.reject') }}"
                class="py-3 px-4 bg-[#6196c0] hover:bg-[#4a7aaa] text-center text-white rounded-xl font-medium transition-colors">Penolakan</a>
        </div>
        <button
            class="closeModal mt-5 w-full py-2.5 flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold rounded-2xl transition-all">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Tutup
        </button>
    </div>
</div> --}}
<div id="salesModal" class="modal-hide modal-transition fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
    w-[92%] max-w-[480px] bg-white rounded-3xl shadow-2xl z-[9999] overflow-hidden">
    <div class="relative bg-gradient-to-br from-[#4a90d9] via-[#6196c0] to-[#3a7bd5] px-6 pt-6 pb-8">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-4 w-16 h-16 bg-white/10 rounded-full translate-y-1/2"></div>
        <div class="relative flex items-center justify-between">
            <div>
                <p class="text-blue-100 text-xs font-medium tracking-widest uppercase mb-1">Manajemen Sistem</p>
                <h2 class="text-2xl font-bold text-white">Penjualan</h2>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
        </div>
    </div>
    <div class="px-5 py-6 max-h-[60vh] overflow-y-auto">
        <div class="grid grid-cols-2 gap-3">

            <a href="{{ route('salesdata.index') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-blue-50 hover:bg-blue-500 border border-blue-100 hover:border-blue-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-blue-100 group-hover:bg-blue-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-blue-600 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-blue-700 group-hover:text-white transition-colors">Data
                    Penjualan</span>
            </a>
            <a href="{{ route('salesdata.pending') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-blue-50 hover:bg-blue-500 border border-blue-100 hover:border-blue-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-blue-100 group-hover:bg-blue-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-blue-600 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-blue-700 group-hover:text-white transition-colors">Data
                    Kunjungan</span>
            </a>

            <a href="{{ route('returdata.retur') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-amber-50 hover:bg-amber-500 border border-amber-100 hover:border-amber-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-amber-100 group-hover:bg-amber-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-amber-600 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-amber-700 group-hover:text-white transition-colors">Retur
                    Penjualan</span>
            </a>

            <a href="{{ route('returdata.index') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-green-50 hover:bg-green-500 border border-green-100 hover:border-green-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-green-100 group-hover:bg-green-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-green-600 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-green-700 group-hover:text-white transition-colors">Data
                    Retur</span>
            </a>

            <a href="{{ route('sales.reject') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-red-50 hover:bg-red-500 border border-red-100 hover:border-red-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-red-100 group-hover:bg-red-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-red-600 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                </div>
                <span
                    class="text-sm font-semibold text-red-700 group-hover:text-white transition-colors">Penolakan</span>
            </a>

        </div>
        <button
            class="closeModal mt-5 w-full py-2.5 flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold rounded-2xl transition-all">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Tutup
        </button>
    </div>
</div>

<div id="transfersModal" class="modal-hide modal-transition fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
    w-[92%] max-w-[480px] bg-white rounded-3xl shadow-2xl z-[9999] overflow-hidden">
    <div class="relative bg-gradient-to-br from-[#4a90d9] via-[#6196c0] to-[#3a7bd5] px-6 pt-6 pb-8">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-4 w-16 h-16 bg-white/10 rounded-full translate-y-1/2"></div>
        <div class="relative flex items-center justify-between">
            <div>
                <p class="text-blue-100 text-xs font-medium tracking-widest uppercase mb-1">Manajemen Sistem</p>
                <h2 class="text-2xl font-bold text-white">Mutasi Stok</h2>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
            </div>
        </div>
    </div>
    <div class="px-5 py-6 max-h-[60vh] overflow-y-auto">
        <div class="grid grid-cols-2 gap-3">
            <a href="{{ route('transfers.create') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-violet-50 hover:bg-violet-500 border border-violet-100 hover:border-violet-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-violet-100 group-hover:bg-violet-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-violet-500 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-violet-700 group-hover:text-white transition-colors">Mutasi
                    Stok</span>
            </a>

            <a href="{{ route('transfers.incoming') }}"
                class="group flex items-center gap-3 p-3.5 rounded-2xl bg-orange-50 hover:bg-orange-500 border border-orange-100 hover:border-orange-500 transition-all duration-200 hover:-translate-y-0.5">
                <div
                    class="w-9 h-9 rounded-xl bg-orange-100 group-hover:bg-orange-400 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-orange-500 group-hover:text-white transition-colors" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 7h2l2 9h10l2-9h2M3 7l1-4h16l1 4M9 16v2m6-2v2" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-orange-700 group-hover:text-white transition-colors">Riwayat
                    Mutasi
                </span>
            </a>
        </div>
        <button
            class="closeModal mt-5 w-full py-2.5 flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold rounded-2xl transition-all">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Tutup
        </button>
    </div>
</div>

{{-- Notif Modal --}}
<div id="notif-modal" class="notif-overlay hidden">
    <div class="notif-header">
        <span class="notif-title">Notifikasi Stok</span>
        <button class="notif-close" onclick="document.getElementById('notif-modal').classList.add('hidden')">×</button>
    </div>
    <div class="notif-list">
        @php
            $logs = \App\Models\ItemsLog::with('medicines')
                ->where('status', '!=', 7)
                ->orderBy('created_at', 'desc')
                ->take(30)
                ->get();
            $typeMap = [
                1 => ['label' => 'Penjualan', 'icon' => '↓', 'sign' => '-', 'class' => 'qty-out', 'color' => 1],
                2 => ['label' => 'Pembelian', 'icon' => '↑', 'sign' => '+', 'class' => 'qty-in', 'color' => 2],
                3 => ['label' => 'Retur Penjualan', 'icon' => '↩', 'sign' => '+', 'class' => 'qty-in', 'color' => 3],
                4 => ['label' => 'Retur Pembelian', 'icon' => '↪', 'sign' => '-', 'class' => 'qty-out', 'color' => 4],
                5 => [
                    'label' => 'Stock Opname (+)',
                    'icon' => '↑',
                    'sign' => '+',
                    'class' => 'qty-neutral',
                    'color' => 5,
                ],
                6 => [
                    'label' => 'Stock Opname (-)',
                    'icon' => '↓',
                    'sign' => '-',
                    'class' => 'qty-neutral',
                    'color' => 6,
                ],
            ];
        @endphp

        @forelse ($logs as $log)
            @php
                $info = $typeMap[$log->status] ?? [
                    'label' => 'Lainnya',
                    'icon' => '•',
                    'sign' => '',
                    'class' => 'qty-neutral',
                    'color' => 0,
                ];
            @endphp
            <div class="notif-item">
                <div class="notif-dot dot-{{ $info['color'] }}">{{ $info['icon'] }}</div>
                <div style="flex:1; min-width:0;">
                    <div class="notif-name">{{ $log->medicines->name ?? '-' }}</div>
                    <div style="display:flex; align-items:center; gap:6px; margin-bottom:2px;">
                        <span class="notif-badge badge-{{ $info['color'] }}">{{ $info['label'] }}</span>
                    </div>
                    <div class="notif-meta">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y H:i') }}</div>
                </div>
                <div class="notif-qty {{ $info['class'] }}">{{ $info['sign'] }} {{ $log->qty }}</div>
            </div>
        @empty
            <div class="notif-empty">Belum ada aktivitas stok</div>
        @endforelse
    </div>
</div>

{{-- Expiry Modal --}}
<div id="expiry-modal" class="notif-overlay hidden">
    <div class="notif-header">
        <span class="notif-title">Obat Kedaluwarsa</span>
        <button class="notif-close" onclick="document.getElementById('expiry-modal').classList.add('hidden')">×</button>
    </div>
    <div class="notif-list">

        <div class="notif-empty">Tidak ada obat kedaluwarsa / mendekati</div>

    </div>
</div>
<div id="loading-overlay"
    class="hidden fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 backdrop-blur-sm">

    <div
        class="bg-white/90 backdrop-blur-xl rounded-2xl shadow-2xl px-10 py-8 flex flex-col items-center gap-5 animate-fadeIn">
        <div class="relative">
            <div class="w-14 h-14 border-4 border-blue-200 rounded-full"></div>
            <div
                class="w-14 h-14 border-4 border-blue-500 border-t-transparent rounded-full animate-spin absolute top-0 left-0">
            </div>
        </div>

        <div class="text-center">
            <p class="text-lg font-semibold text-gray-800">Generating Report</p>
            <p class="text-sm text-gray-500 mt-1">Please wait a moment...</p>
        </div>

        <div class="flex gap-1 mt-2">
            <span class="w-2 h-2 bg-blue-500 rounded-full animate-bounce"></span>
            <span class="w-2 h-2 bg-blue-500 rounded-full animate-bounce delay-150"></span>
            <span class="w-2 h-2 bg-blue-500 rounded-full animate-bounce delay-300"></span>
        </div>

    </div>
</div>