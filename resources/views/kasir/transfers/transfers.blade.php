@extends('layouts.app')

@section('title', 'Transfer Stok')

@section('style')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
        }

        .shell {
            min-height: 100vh;
            background: #f0f2f5;
            padding: 28px 20px;
        }

        .card {
            margin: 0 auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 16px rgba(0, 0, 0, .07);
            overflow: hidden;
        }

        /* Header */
        .card-header {
            background: #0f172a;
            padding: 22px 28px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .header-icon {
            width: 38px;
            height: 38px;
            background: rgba(59, 130, 246, .2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .card-header h1 {
            color: #f8fafc;
            font-size: 15px;
            font-weight: 600;
            margin: 0;
        }

        .card-header span {
            font-family: 'DM Mono', monospace;
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
            display: block;
        }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #f1f5f9;
            background: #fff;
        }

        .tab-btn {
            flex: 1;
            padding: 14px 10px;
            font-size: 13px;
            font-weight: 500;
            color: #94a3b8;
            background: none;
            border: none;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all .15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        .tab-btn:hover {
            color: #475569;
        }

        .tab-btn.active {
            color: #0f172a;
            border-bottom-color: #0f172a;
            font-weight: 600;
        }

        .tab-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 99px;
            font-family: 'DM Mono', monospace;
        }

        .badge-pending {
            background: #fff7ed;
            color: #ea580c;
        }

        .badge-accepted {
            background: #f0fdf4;
            color: #16a34a;
        }

        .badge-denied {
            background: #fff1f1;
            color: #ef4444;
        }

        /* Tab panels */
        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        /* Flash */
        .alert {
            padding: 12px 28px;
            font-size: 13px;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border-left: 3px solid #16a34a;
        }

        .alert-danger {
            background: #fff1f1;
            color: #ef4444;
            border-left: 3px solid #ef4444;
        }

        /* Transfer row */
        .transfer-row {
            padding: 16px 28px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 16px;
            cursor: pointer;
            transition: background .12s;
        }

        .transfer-row:last-child {
            border-bottom: none;
        }

        .transfer-row:hover {
            background: #f8fafc;
        }

        .tr-info {
            flex: 1;
            min-width: 0;
        }

        .tr-code {
            font-family: 'DM Mono', monospace;
            font-size: 11px;
            color: #64748b;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 5px;
        }

        .tr-name {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tr-meta {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 3px;
        }

        .tr-stock {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #f0fdf4;
            color: #16a34a;
            font-size: 12px;
            font-weight: bold;
            font-family: "Poppins";
            padding: 4px 10px;
            border-radius: 7px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .status-pill {
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 99px;
            flex-shrink: 0;
        }

        .pill-accepted {
            background: #f0fdf4;
            color: #16a34a;
        }

        .pill-denied {
            background: #fff1f1;
            color: #ef4444;
        }

        /* Action buttons */
        .tr-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            border: none;
            transition: opacity .15s, transform .1s;
        }

        .btn:active {
            transform: scale(.96);
        }

        .btn-accept {
            background: #0f172a;
            color: #fff;
        }

        .btn-accept:hover {
            opacity: .85;
        }


        .btn-pending {
            background: #f49108;
            color: #fff;
        }

        .btn-pending:hover {
            opacity: .85;
        }

        .btn-confirmed {
            background: #16a34a;
            color: #fff;
        }

        .btn-confirmed:hover {
            opacity: .85;
        }

        .btn-deny {
            background: #fff;
            color: #ef4444;
            border: 1.5px solid #fecaca;
        }

        .btn-deny:hover {
            background: #fff1f1;
        }

        /* Empty state */
        .empty-state {
            padding: 48px 28px;
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
        }

        .empty-state svg {
            margin-bottom: 12px;
            opacity: .35;
        }

        /* ── Detail Modal ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, .45);
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-box {
            position: relative;
            background: #fff;
            border-radius: 18px;
            width: 100%;
            max-width: 460px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, .18);
            overflow: hidden;
            animation: slideUp .2s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: #0f172a;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            color: #f8fafc;
            font-size: 14px;
            font-weight: 600;
            margin: 0;
        }

        .modal-header .modal-code {
            font-family: 'DM Mono', monospace;
            font-size: 11px;
            color: #64748b;
        }

        .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            color: #64748b;
            display: flex;
            align-items: center;
            padding: 2px;
        }

        .modal-close:hover {
            color: #f8fafc;
        }

        .modal-body {
            padding: 22px;
        }

        .detail-section-label {
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #f1f5f9;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 18px;
        }

        .detail-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
        }

        .detail-item .lbl {
            font-size: 10px;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 4px;
        }

        .detail-item .val {
            font-size: 13px;
            color: #1e293b;
            font-weight: 500;
            font-family: 'DM Mono', monospace;
        }

        .detail-item .val.green {
            color: #16a34a;
        }

        .detail-item.full {
            grid-column: span 2;
        }

        .modal-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 8px;
            margin-top: 4px;
        }
    </style>
@endsection

@section('content')
    <section class="shell">
        <div class="card">

            {{-- Header --}}
            <div class="card-header">
                <div class="header-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                        <polyline points="17 6 23 6 23 12" />
                    </svg>
                </div>
                <div>
                    <h1>Riwayat Mutasi</h1>
                    <span>Kelola permintaan transfer masuk</span>
                </div>
            </div>

            {{-- Flash --}}
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('message'))
                <div class="alert alert-danger">{{ session('message') }}</div>
            @endif

            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('pending', this)">
                    Mutasi Keluar
                    <span class="tab-badge badge-pending">{{ $pending->count() }}</span>
                </button>
                <button class="tab-btn" onclick="switchTab('accepted', this)">
                    Mutasi Masuk
                    <span class="tab-badge badge-accepted">{{ $accepted->count() }}</span>
                </button>
                <button class="tab-btn" onclick="switchTab('denied', this)">
                    Ditolak
                    <span class="tab-badge badge-denied">{{ $denied->count() }}</span>
                </button>
            </div>

            {{-- ── Outgoing Transfer ── --}}
            <div id="tab-pending" class="tab-panel active">
                @forelse($pending as $t)
                    <div class="transfer-row" onclick="openDetail({{ $t->id }})">
                        <div class="tr-info">
                            <div class="tr-code">{{ $t->code }}</div>
                            <div class="tr-name">{{ $t->batches?->medicines?->name ?? '—' }}</div>
                            <div class="tr-meta">
                                Batch: {{ $t->batches?->name ?? '—' }}
                                &nbsp;·&nbsp; Etalase: {{ $t->etalases?->name ?? '—' }}
                                &nbsp;·&nbsp; {{ $t->created_at->format('d M Y, H:i') }}
                            </div>
                            <div class="tr-meta flex items-center gap-1 !text-[10px]">
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12"
                                        viewBox="0 0 24 24" fill="currentColor"
                                        class="icon icon-tabler icons-tabler-filled icon-tabler-send">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path
                                            d="M21.864 3.549l-6.454 17.868a1.55 1.55 0 0 1 -1.41 .903a1.54 1.54 0 0 1 -1.394 -.874l-2.88 -5.759zm-1.414 -1.414l-12.139 12.138l-5.728 -2.864a1.55 1.55 0 0 1 -.903 -1.409c0 -.606 .353 -1.157 .981 -1.44z" />
                                    </svg>

                                </span>
                                <span>
                                    Apotek Asal: {{ $t->users?->pharmacy?->name ?? '—' }}
                                </span>
                            </div>
                            <div class="tr-meta flex items-center gap-1 !text-[10px]">
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12"
                                        viewBox="0 0 24 24" fill="currentColor"
                                        class="icon icon-tabler icons-tabler-filled icon-tabler-map-pin">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path
                                            d="M18.364 4.636a9 9 0 0 1 .203 12.519l-.203 .21l-4.243 4.242a3 3 0 0 1 -4.097 .135l-.144 -.135l-4.244 -4.243a9 9 0 0 1 12.728 -12.728zm-6.364 3.364a3 3 0 1 0 0 6a3 3 0 0 0 0 -6" />
                                    </svg>

                                </span>
                                <span>
                                    Apotek Tujuan: {{ $t->batches?->pharmacy?->name ?? '—' }}
                                </span>
                            </div>
                        </div>
                        <div class="tr-stock">{{ $t->stock }}</div>

                        <div class="tr-actions" onclick="event.stopPropagation()">
                            @if ($t->status == 0)
                                <button type="button" class="btn btn-pending">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
                                        <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
                                    </svg>
                                    Pending
                                </button>
                            @elseif($t->status == 1)
                                <button type="submit" class="btn btn-confirmed">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    Diterima
                                </button>
                                <button type="button" class="btn !bg-[#5085ff] btn-confirmed"
                                    onclick="window.open('{{ route('transfers.print', $t->id) }}', '_blank')">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path
                                            d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2" />
                                        <path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4" />
                                        <path
                                            d="M7 15a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2l0 -4" />
                                    </svg>
                                    Print
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#94a3b8"
                            stroke-width="1.5" stroke-linecap="round">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                        <p>Tidak ada transfer yang menunggu.</p>
                    </div>
                @endforelse
            </div>

            {{-- ── Ingoing Transfer ── --}}
            <div id="tab-accepted" class="tab-panel">
                @forelse($accepted as $t)
                    <div class="transfer-row" onclick="openDetail({{ $t->id }})">
                        <div class="tr-info">
                            <div class="tr-code">{{ $t->code }}</div>
                            <div class="tr-name">{{ $t->batches?->medicines?->name ?? '—' }}</div>
                            <div class="tr-meta">
                                Batch: {{ $t->batches?->name ?? '—' }}
                                &nbsp;·&nbsp; Etalase: {{ $t->etalases?->name ?? '—' }}
                                &nbsp;·&nbsp; {{ $t->created_at->format('d M Y, H:i') }}
                            </div>
                            <div class="tr-meta flex items-center gap-1 !text-[10px]">
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12"
                                        viewBox="0 0 24 24" fill="currentColor"
                                        class="icon icon-tabler icons-tabler-filled icon-tabler-send">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path
                                            d="M21.864 3.549l-6.454 17.868a1.55 1.55 0 0 1 -1.41 .903a1.54 1.54 0 0 1 -1.394 -.874l-2.88 -5.759zm-1.414 -1.414l-12.139 12.138l-5.728 -2.864a1.55 1.55 0 0 1 -.903 -1.409c0 -.606 .353 -1.157 .981 -1.44z" />
                                    </svg>

                                </span>
                                <span>
                                    Apotek Asal: {{ $t->users?->pharmacy?->name ?? '—' }}
                                </span>
                            </div>
                            <div class="tr-meta flex items-center gap-1 !text-[10px]">
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12"
                                        viewBox="0 0 24 24" fill="currentColor"
                                        class="icon icon-tabler icons-tabler-filled icon-tabler-map-pin">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path
                                            d="M18.364 4.636a9 9 0 0 1 .203 12.519l-.203 .21l-4.243 4.242a3 3 0 0 1 -4.097 .135l-.144 -.135l-4.244 -4.243a9 9 0 0 1 12.728 -12.728zm-6.364 3.364a3 3 0 1 0 0 6a3 3 0 0 0 0 -6" />
                                    </svg>

                                </span>
                                <span>
                                    Apotek Tujuan: {{ $t->batches?->pharmacy?->name ?? '—' }}
                                </span>
                            </div>
                        </div>
                        <div class="tr-stock">{{ $t->stock }}</div>
                        @if ($t->status == 1)
                            <div class="tr-actions" onclick="event.stopPropagation()">

                                <button type="submit" class="btn btn-confirmed">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    Diterima
                                </button>
                                <button type="button" class="btn !bg-[#5085ff] btn-confirmed"
                                    onclick="window.open('{{ route('transfers.print', $t->id) }}', '_blank')">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path
                                            d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2" />
                                        <path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4" />
                                        <path
                                            d="M7 15a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2l0 -4" />
                                    </svg>
                                    Print
                                </button>
                            </div>
                        @else
                            <div class="tr-actions" onclick="event.stopPropagation()">
                                <form method="POST" action="{{ route('transfers.accept', $t) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-accept"
                                        onclick="return confirm('Terima transfer ini?')">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                            <polyline points="20 6 9 17 4 12" />
                                        </svg>
                                        Terima
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('transfers.deny', $t) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-deny"
                                        onclick="return confirm('Tolak transfer ini?')">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                            <line x1="18" y1="6" x2="6" y2="18" />
                                            <line x1="6" y1="6" x2="18" y2="18" />
                                        </svg>
                                        Tolak
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="empty-state">
                        <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#94a3b8"
                            stroke-width="1.5" stroke-linecap="round">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        <p>Belum ada transfer yang diterima.</p>
                    </div>
                @endforelse
            </div>

            {{-- ── DENIED ── --}}
            <div id="tab-denied" class="tab-panel">
                @forelse($denied as $t)
                    <div class="transfer-row" onclick="openDetail({{ $t->id }})">
                        <div class="tr-info">
                            <div class="tr-code">{{ $t->code }}</div>
                            <div class="tr-name">{{ $t->batches?->medicines?->name ?? '—' }}</div>
                            <div class="tr-meta">
                                Batch: {{ $t->batches?->name ?? '—' }}
                                &nbsp;·&nbsp; Etalase: {{ $t->etalases?->name ?? '—' }}
                                &nbsp;·&nbsp; {{ $t->created_at->format('d M Y, H:i') }}
                            </div>
                        </div>
                        <div class="tr-stock">{{ $t->stock }}</div>
                        <span class="status-pill pill-denied">✕ Ditolak</span>
                    </div>
                @empty
                    <div class="empty-state">
                        <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#94a3b8"
                            stroke-width="1.5" stroke-linecap="round">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                        <p>Belum ada transfer yang ditolak.</p>
                    </div>
                @endforelse
            </div>

        </div>
    </section>

    {{-- ── Detail Modal ── --}}
    <div class="modal-overlay" id="detailModal" onclick="closeDetail(event)">
        <div class="modal-box">
            <div class="modal-header">
                <div>
                    <h3>Detail Transfer</h3>
                    <div class="modal-code" id="modal-code">—</div>
                </div>
                <button class="modal-close" onclick="closeModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.5" stroke-linecap="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">

                <div class="detail-section-label">Informasi Obat</div>
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="lbl">Nama Obat</div>
                        <div class="val" id="modal-med-name">—</div>
                    </div>
                    <div class="detail-item">
                        <div class="lbl">Kode Obat</div>
                        <div class="val" id="modal-med-code">—</div>
                    </div>
                    <div class="detail-item full">
                        <div class="lbl">Nama Batch</div>
                        <div class="val" id="modal-batch-name">—</div>
                    </div>
                </div>

                <div class="detail-section-label">Informasi Transfer</div>
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="lbl">Qty Transfer</div>
                        <div class="val green" id="modal-stock">—</div>
                    </div>
                    <div class="detail-item">
                        <div class="lbl">Etalase Tujuan</div>
                        <div class="val" id="modal-etalase">—</div>
                    </div>
                    <div class="detail-item">
                        <div class="lbl">Tanggal</div>
                        <div class="val" id="modal-date">—</div>
                    </div>
                    <div class="detail-item">
                        <div class="lbl">Status</div>
                        <div id="modal-status">—</div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- Embed transfer data for JS --}}
    <script>
        const transferData = @json($transferData);


        const statusLabel = {
            0: ['⏳ Menunggu', '#fff7ed', '#ea580c'],
            1: ['✓ Diterima', '#f0fdf4', '#16a34a'],
            2: ['✕ Ditolak', '#fff1f1', '#ef4444'],
        };

        function switchTab(name, btn) {
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + name).classList.add('active');
            btn.classList.add('active');
        }

        function openDetail(id) {
            const t = transferData[id];
            if (!t) return;

            document.getElementById('modal-code').textContent = t.code;
            document.getElementById('modal-med-name').textContent = t.med_name;
            document.getElementById('modal-med-code').textContent = t.med_code;
            document.getElementById('modal-batch-name').textContent = t.batch_name;
            document.getElementById('modal-stock').textContent = t.stock;
            document.getElementById('modal-etalase').textContent = t.etalase;
            document.getElementById('modal-date').textContent = t.date;

            const [label, bg, color] = statusLabel[t.status] ?? ['—', '#f1f5f9', '#64748b'];
            const statusEl = document.getElementById('modal-status');
            statusEl.innerHTML = `<span class="modal-status" style="background:${bg};color:${color}">${label}</span>`;

            document.getElementById('detailModal').classList.add('open');
        }

        function closeDetail(e) {
            if (e.target === document.getElementById('detailModal')) closeModal();
        }

        function closeModal() {
            document.getElementById('detailModal').classList.remove('open');
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeModal();
        });
    </script>
@endsection
