@extends('layouts.app')

@section('title', 'Statistik Staf & Kasir')

@section('style')
    <style>
        .ss-page {
            --ss-surface: #ffffff;
            --ss-surface-soft: #f8fafc;
            --ss-surface-subtle: #f1f5f9;
            --ss-border: #e2e8f0;
            --ss-border-hover: #cbd5e1;
            --ss-ink: #0f172a;
            --ss-ink-secondary: #334155;
            --ss-ink-muted: #64748b;
            --ss-accent: #2563eb;
            --ss-accent-dark: #1d4ed8;
            --ss-accent-soft: #eff6ff;
            --ss-accent-border: #bfdbfe;
            --ss-green: #059669;
            --ss-green-soft: #ecfdf5;
            --ss-red: #dc2626;
            --ss-red-soft: #fef2f2;
            --ss-amber: #d97706;
            --ss-amber-soft: #fffbeb;
            --ss-sky: #0284c7;
            --ss-sky-soft: #f0f9ff;
            --ss-radius: 16px;
            --ss-radius-sm: 10px;
            --ss-shadow: 0 2px 10px -2px rgba(15, 23, 42, 0.05), 0 1px 2px rgba(15, 23, 42, 0.03);
            --ss-shadow-hover: 0 8px 20px -6px rgba(15, 23, 42, 0.09), 0 2px 4px rgba(15, 23, 42, 0.04);
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            padding: 20px 16px 56px;
            color: var(--ss-ink);
            font-family: "Nunito Sans", "Nunito", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.45;
        }

        @media (min-width: 768px) {
            .ss-page {
                padding: 24px 30px 64px;
            }
        }

        .ss-page *,
        .ss-page *::before,
        .ss-page *::after {
            box-sizing: border-box;
        }

        .ss-page button:focus:not(:focus-visible),
        .ss-page input:focus:not(:focus-visible),
        .ss-page select:focus:not(:focus-visible) {
            outline: none;
        }

        .ss-sr-only {
            position: absolute;
            clip-path: inset(50%);
            overflow: hidden;
            width: 1px;
            height: 1px;
            margin: -1px;
            padding: 0;
            border: 0;
            white-space: nowrap;
        }

        .ss-num {
            font-variant-numeric: tabular-nums;
        }

        /* ── Header ── */
        .ss-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 14px 20px;
            margin-block-end: 20px;
        }

        .ss-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .ss-header-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px -2px rgba(37, 99, 235, 0.35);
        }

        .ss-title {
            margin: 0;
            font-size: 21px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--ss-ink);
            line-height: 1.2;
        }

        .ss-subtitle-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
        }

        .ss-context-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: var(--ss-surface);
            border: 1px solid var(--ss-border);
            color: var(--ss-ink-secondary);
        }

        .ss-context-pill-accent {
            background: var(--ss-accent-soft);
            border-color: var(--ss-accent-border);
            color: var(--ss-accent-dark);
        }

        .ss-header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ── Buttons ── */
        .ss-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            height: 38px;
            padding: 0 15px;
            border-radius: var(--ss-radius-sm);
            border: 1px solid var(--ss-border);
            background: var(--ss-surface);
            color: var(--ss-ink-secondary);
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        }

        .ss-btn:hover {
            background: var(--ss-surface-soft);
            border-color: var(--ss-border-hover);
            color: var(--ss-ink);
        }

        .ss-btn:focus-visible {
            outline: 2px solid var(--ss-accent);
            outline-offset: 2px;
        }

        .ss-btn-primary {
            background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
            border-color: #1d4ed8;
            color: #ffffff;
            box-shadow: 0 2px 8px -2px rgba(37, 99, 235, 0.45);
        }

        .ss-btn-primary:hover {
            background: linear-gradient(180deg, #1d4ed8 0%, #1e40af 100%);
            border-color: #1e40af;
            color: #ffffff;
        }

        .ss-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* ── Elevated Card Shell ── */
        .ss-card {
            width: 100%;
            background: var(--ss-surface);
            border: 1px solid var(--ss-border);
            border-radius: var(--ss-radius);
            box-shadow: var(--ss-shadow);
        }

        /* ── Filter Bar ── */
        .ss-filter-card {
            padding: 14px 18px;
            margin-block-end: 18px;
        }

        .ss-filter-form {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .ss-filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px 16px;
        }

        .ss-period-tabs {
            display: inline-flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px;
            padding: 4px;
            background: var(--ss-surface-subtle);
            border-radius: 12px;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .ss-period-btn {
            height: 34px;
            padding: 0 13px;
            border: 0;
            border-radius: 9px;
            background: transparent;
            color: var(--ss-ink-muted);
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        }

        .ss-period-btn:hover {
            color: var(--ss-ink);
            background: rgba(255, 255, 255, 0.7);
        }

        .ss-period-btn[aria-pressed="true"] {
            background: var(--ss-accent);
            color: #ffffff;
            box-shadow: 0 2px 8px -2px rgba(37, 99, 235, 0.45);
        }

        .ss-period-btn:focus-visible {
            outline: 2px solid var(--ss-accent);
            outline-offset: 2px;
        }

        .ss-filter-right {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .ss-select-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .ss-select-icon {
            position: absolute;
            inset-inline-start: 11px;
            width: 15px;
            height: 15px;
            color: var(--ss-ink-muted);
            pointer-events: none;
        }

        .ss-input,
        .ss-select {
            font: inherit;
            font-size: 13px;
            font-weight: 500;
            height: 38px;
            padding: 0 12px;
            border: 1px solid var(--ss-border-hover);
            border-radius: var(--ss-radius-sm);
            background: var(--ss-surface);
            color: var(--ss-ink);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .ss-select {
            min-width: 210px;
            padding-inline-start: 33px;
            padding-inline-end: 30px;
            cursor: pointer;
        }

        .ss-input:focus-visible,
        .ss-select:focus-visible {
            outline: none;
            border-color: var(--ss-accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.14);
        }

        .ss-custom-dates {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px 16px;
            padding-top: 14px;
            border-top: 1px dashed var(--ss-border);
        }

        .ss-custom-dates[hidden] {
            display: none;
        }

        .ss-date-group {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .ss-inline-label {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ss-ink-muted);
        }

        .ss-field-error {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ss-red);
            margin: 0;
        }

        @media (pointer: coarse) {
            .ss-btn,
            .ss-input,
            .ss-select {
                height: 44px;
                font-size: 1rem;
            }

            .ss-period-btn {
                height: 40px;
                font-size: 0.9375rem;
            }
        }

        /* ── 4 Modern KPI Cards ── */
        .ss-kpi-grid {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 14px;
            margin-block-end: 18px;
        }

        @media (min-width: 640px) {
            .ss-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1120px) {
            .ss-kpi-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        .ss-kpi-card {
            background: var(--ss-surface);
            border: 1px solid var(--ss-border);
            border-radius: var(--ss-radius);
            box-shadow: var(--ss-shadow);
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 12px;
            min-width: 0;
            transition: border-color 0.16s ease, box-shadow 0.16s ease;
        }

        .ss-kpi-card:hover {
            border-color: var(--ss-border-hover);
            box-shadow: var(--ss-shadow-hover);
        }

        .ss-kpi-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .ss-kpi-label {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ss-ink-muted);
            line-height: 1.3;
        }

        .ss-kpi-value {
            margin-top: 6px;
            font-size: 23px;
            font-weight: 800;
            color: var(--ss-ink);
            letter-spacing: -0.025em;
            line-height: 1.15;
            word-break: break-word;
        }

        .ss-kpi-icon {
            width: 40px;
            height: 40px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .ss-kpi-icon-blue {
            background: var(--ss-accent-soft);
            color: var(--ss-accent);
        }

        .ss-kpi-icon-sky {
            background: var(--ss-sky-soft);
            color: var(--ss-sky);
        }

        .ss-kpi-icon-green {
            background: var(--ss-green-soft);
            color: var(--ss-green);
        }

        .ss-kpi-icon-amber {
            background: var(--ss-amber-soft);
            color: var(--ss-amber);
        }

        .ss-kpi-footer {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
            font-size: 12px;
            color: var(--ss-ink-muted);
        }

        .ss-kpi-footer strong {
            color: var(--ss-ink-secondary);
            font-weight: 700;
        }

        .ss-delta-pill {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 700;
            line-height: 1.3;
        }

        .ss-delta-up {
            background: var(--ss-green-soft);
            color: var(--ss-green);
        }

        .ss-delta-down {
            background: var(--ss-red-soft);
            color: var(--ss-red);
        }

        .ss-delta-flat {
            background: var(--ss-surface-subtle);
            color: var(--ss-ink-muted);
        }

        /* ── 2-Column Visual Insights Row ── */
        .ss-insights-grid {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 16px;
            margin-block-end: 18px;
        }

        @media (min-width: 1024px) {
            .ss-insights-grid {
                grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr);
            }
        }

        .ss-panel {
            padding: 18px 20px 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            min-width: 0;
        }

        .ss-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .ss-panel-title {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: var(--ss-ink);
        }

        .ss-panel-sub {
            margin: 2px 0 0;
            font-size: 12px;
            color: var(--ss-ink-muted);
        }

        .ss-panel-badge {
            padding: 3px 9px;
            border-radius: 999px;
            background: var(--ss-surface-subtle);
            color: var(--ss-ink-secondary);
            font-size: 11.5px;
            font-weight: 700;
            white-space: nowrap;
        }

        .ss-bar-list {
            display: flex;
            flex-direction: column;
            gap: 13px;
        }

        .ss-bar-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .ss-bar-item-btn {
            width: 100%;
            padding: 6px 8px;
            margin: -6px -8px;
            border: 0;
            border-radius: 10px;
            background: transparent;
            text-align: left;
            font: inherit;
            cursor: pointer;
            transition: background-color 0.14s ease;
        }

        .ss-bar-item-btn:hover {
            background: var(--ss-surface-soft);
        }

        .ss-bar-item-btn:focus-visible {
            outline: 2px solid var(--ss-accent);
            outline-offset: 2px;
        }

        .ss-bar-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .ss-bar-label-wrap {
            display: flex;
            align-items: center;
            gap: 9px;
            min-width: 0;
        }

        .ss-rank-chip {
            width: 24px;
            height: 24px;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11.5px;
            font-weight: 800;
            flex-shrink: 0;
            background: var(--ss-surface-subtle);
            color: var(--ss-ink-muted);
        }

        .ss-rank-1 {
            background: #fef3c7;
            color: #b45309;
        }

        .ss-rank-2 {
            background: #e2e8f0;
            color: #334155;
        }

        .ss-rank-3 {
            background: #ffedd5;
            color: #c2410c;
        }

        .ss-bar-name {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--ss-ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ss-bar-sub {
            font-size: 11.5px;
            color: var(--ss-ink-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ss-bar-figures {
            display: flex;
            align-items: baseline;
            gap: 7px;
            flex-shrink: 0;
        }

        .ss-bar-amount {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--ss-ink);
        }

        .ss-bar-pct {
            font-size: 11.5px;
            font-weight: 600;
            color: var(--ss-ink-muted);
            min-width: 38px;
            text-align: right;
        }

        .ss-progress-track {
            width: 100%;
            height: 7px;
            border-radius: 999px;
            background: #f1f5f9;
            overflow: hidden;
        }

        .ss-progress-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #2563eb 0%, #60a5fa 100%);
            transition: width 0.25s ease;
        }

        .ss-progress-fill-top {
            background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 100%);
        }

        .ss-progress-fill-branch {
            background: linear-gradient(90deg, #0284c7 0%, #38bdf8 100%);
        }

        .ss-panel-empty {
            padding: 28px 12px;
            text-align: center;
            color: var(--ss-ink-muted);
            font-size: 13px;
            background: var(--ss-surface-soft);
            border-radius: 12px;
            border: 1px dashed var(--ss-border);
        }

        /* ── Table Card ── */
        .ss-table-card {
            width: 100%;
            overflow: hidden;
        }

        .ss-table-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px 16px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--ss-border);
            background: var(--ss-surface);
        }

        .ss-table-title-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ss-table-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            flex: 1 1 360px;
            justify-content: flex-end;
        }

        .ss-search-wrap {
            position: relative;
            flex: 1 1 220px;
            max-width: 310px;
        }

        .ss-search-wrap .ss-input {
            width: 100%;
            height: 36px;
            padding-inline-start: 34px;
            background: var(--ss-surface-soft);
            border-color: var(--ss-border);
        }

        .ss-search-wrap .ss-input:focus {
            background: var(--ss-surface);
        }

        .ss-search-icon {
            position: absolute;
            inset-inline-start: 11px;
            top: 50%;
            transform: translateY(-50%);
            width: 15px;
            height: 15px;
            color: var(--ss-ink-muted);
            pointer-events: none;
        }

        .ss-seg-toggle {
            display: inline-flex;
            padding: 3px;
            background: var(--ss-surface-subtle);
            border: 1px solid var(--ss-border);
            border-radius: 10px;
            gap: 2px;
        }

        .ss-seg-btn {
            height: 30px;
            padding: 0 11px;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: var(--ss-ink-muted);
            font: inherit;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: background-color 0.14s ease, color 0.14s ease, box-shadow 0.14s ease;
        }

        .ss-seg-btn[aria-pressed="true"] {
            background: var(--ss-surface);
            color: var(--ss-ink);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .ss-seg-btn:focus-visible {
            outline: 2px solid var(--ss-accent);
            outline-offset: 2px;
        }

        /* ── Responsive & Sortable Table ── */
        .ss-table-wrapper {
            width: 100%;
            overflow-x: auto;
            overflow-y: auto;
            max-block-size: min(680px, 72vh);
            container-type: inline-size;
            position: relative;
        }

        .ss-table-wrapper:focus-visible {
            outline: 2px solid var(--ss-accent);
            outline-offset: -2px;
        }

        .ss-table-wrapper[aria-busy="true"] {
            opacity: 0.55;
            pointer-events: none;
        }

        .ss-table {
            width: 100%;
            min-inline-size: 780px;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13.5px;
            text-align: left;
            --label-1: "No";
            --label-2: "Staf";
            --label-3: "Omset";
            --label-4: "Transaksi";
            --label-5: "Rata-rata Struk";
            --label-6: "Shift";
            --label-7: "Omset / Shift";
        }

        .ss-table thead {
            position: sticky;
            inset-block-start: 0;
            z-index: 2;
        }

        .ss-table thead th {
            background: var(--ss-surface-soft);
            border-bottom: 1px solid var(--ss-border);
            color: var(--ss-ink-muted);
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
            padding: 12px 16px;
        }

        .ss-table thead th:has(> button) {
            padding: 0;
        }

        .ss-sort-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            width: 100%;
            min-height: 44px;
            padding: 12px 16px;
            border: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            text-transform: inherit;
            letter-spacing: inherit;
            cursor: pointer;
        }

        .ss-col-num .ss-sort-btn {
            justify-content: flex-end;
        }

        .ss-sort-btn:hover {
            color: var(--ss-ink);
            background: #f1f5f9;
        }

        .ss-sort-btn:focus-visible {
            outline: 2px solid var(--ss-accent);
            outline-offset: -2px;
        }

        .ss-sort-icon {
            width: 11px;
            height: 11px;
            flex-shrink: 0;
            color: #94a3b8;
        }

        .ss-table thead th[aria-sort] .ss-sort-btn {
            color: var(--ss-accent-dark);
        }

        .ss-table thead th[aria-sort] .ss-sort-icon {
            color: var(--ss-accent);
        }

        .ss-table thead th[aria-sort="ascending"] .ss-sort-icon .ss-arrow-down {
            opacity: 0.25;
        }

        .ss-table thead th[aria-sort="descending"] .ss-sort-icon .ss-arrow-up {
            opacity: 0.25;
        }

        .ss-table tbody th,
        .ss-table tbody td {
            padding: 13px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            background: var(--ss-surface);
        }

        .ss-table tbody th {
            font-weight: 600;
            position: sticky;
            inset-inline-start: 0;
            z-index: 1;
        }

        .ss-table tbody tr:hover th,
        .ss-table tbody tr:hover td {
            background: #f8fafc;
        }

        .ss-table tbody tr[hidden] {
            display: none;
        }

        .ss-table tfoot th,
        .ss-table tfoot td {
            padding: 13px 16px;
            border-top: 2px solid var(--ss-border);
            background: var(--ss-surface-soft);
            font-weight: 800;
            color: var(--ss-ink);
            white-space: nowrap;
        }

        .ss-col-rank {
            width: 56px;
            text-align: center;
        }

        .ss-col-num {
            text-align: right;
            white-space: nowrap;
        }

        .ss-staff-cell {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .ss-avatar {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: var(--ss-accent-soft);
            color: var(--ss-accent-dark);
            border: 1px solid rgba(191, 219, 254, 0.7);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 800;
            flex-shrink: 0;
            letter-spacing: 0.02em;
        }

        .ss-avatar-muted {
            background: var(--ss-surface-subtle);
            color: var(--ss-ink-muted);
            border-color: var(--ss-border);
        }

        .ss-staff-name {
            color: var(--ss-ink);
            font-size: 13.5px;
            font-weight: 700;
            line-height: 1.25;
        }

        .ss-staff-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            margin-top: 3px;
            font-size: 12px;
            font-weight: 500;
            color: var(--ss-ink-muted);
        }

        .ss-branch-tag {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 5px;
            background: var(--ss-surface-subtle);
            color: var(--ss-ink-secondary);
            font-size: 11px;
            font-weight: 700;
        }

        .ss-sales-cell {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
        }

        .ss-sales-amount {
            font-weight: 700;
            color: var(--ss-ink);
        }

        .ss-share-row {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .ss-share-track {
            width: 68px;
            height: 5px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .ss-share-fill {
            height: 100%;
            background: linear-gradient(90deg, #2563eb, #60a5fa);
            border-radius: 999px;
        }

        .ss-share-text {
            font-size: 11.5px;
            font-weight: 600;
            color: var(--ss-ink-muted);
            min-width: 36px;
            text-align: right;
        }

        .ss-shift-pill {
            display: inline-flex;
            align-items: center;
            padding: 2px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            background: var(--ss-sky-soft);
            color: var(--ss-sky);
        }

        .ss-retur-pill {
            display: inline-block;
            margin-top: 3px;
            padding: 1px 6px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
            background: var(--ss-red-soft);
            color: var(--ss-red);
        }

        .ss-muted-zero {
            color: #94a3b8;
            font-weight: 400;
        }

        .ss-empty-state {
            padding: 44px 16px;
            text-align: center;
            color: var(--ss-ink-muted);
            font-size: 13.5px;
        }

        .ss-empty-state[hidden] {
            display: none;
        }

        /* Stacked mobile layout only when the table container itself is < 640px */
        @container (width < 640px) {
            .ss-table {
                min-inline-size: 0;
            }

            .ss-table,
            .ss-table thead,
            .ss-table tbody,
            .ss-table tfoot,
            .ss-table tr,
            .ss-table th,
            .ss-table td {
                display: block;
            }

            .ss-table thead,
            .ss-table tfoot {
                display: none;
            }

            .ss-table tbody tr {
                margin: 12px;
                border: 1px solid var(--ss-border);
                border-radius: 12px;
                overflow: hidden;
            }

            .ss-table tbody th {
                position: sticky;
                inset-block-start: 0;
                z-index: 2;
                background: var(--ss-surface-soft);
                border-bottom: 1px solid var(--ss-border);
                text-align: left;
            }

            .ss-table tbody td {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                text-align: right;
                padding: 10px 14px;
                border-bottom: 1px solid #f1f5f9;
            }

            .ss-table tbody td:last-child {
                border-bottom: 0;
            }

            .ss-table tbody td.ss-col-rank {
                width: auto;
                text-align: right;
            }

            .ss-table tbody td:nth-child(1)::before { content: var(--label-1); }
            .ss-table tbody td:nth-child(3)::before { content: var(--label-3); }
            .ss-table tbody td:nth-child(4)::before { content: var(--label-4); }
            .ss-table tbody td:nth-child(5)::before { content: var(--label-5); }
            .ss-table tbody td:nth-child(6)::before { content: var(--label-6); }
            .ss-table tbody td:nth-child(7)::before { content: var(--label-7); }

            .ss-table tbody td::before {
                font-size: 12px;
                font-weight: 600;
                color: var(--ss-ink-muted);
                text-align: left;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition: none !important;
                animation: none !important;
            }
        }
    </style>
@endsection

@section('content')
    @php
        $totals = $initialSummary['totals'];
        $comparison = $initialSummary['comparison'];
        $topPerformer = $initialSummary['top_performer'];
        $staffList = $initialSummary['staff'];
        $branches = $initialSummary['branches'] ?? [];
        $activePeriod = $initialSummary['period'] ?? 'today';
        $selectedPharmacy = $initialSummary['pharmacy_id'] ?? 'all';
        $periodOptions = [
            'today' => 'Hari Ini',
            'yesterday' => 'Kemarin',
            'this_week' => 'Minggu Ini',
            'this_month' => 'Bulan Ini',
            'this_year' => 'Tahun Ini',
            'custom' => 'Kustom',
        ];
        $topFive = array_values(array_filter(array_slice($staffList, 0, 5), fn ($s) => $s['sales'] > 0));
        $maxTopSales = !empty($topFive) ? max(1, (float) $topFive[0]['sales']) : 1;
        $activeBranches = array_values(array_filter($branches, fn ($b) => $b['sales'] > 0 || $b['txns'] > 0));
        $maxBranchSales = !empty($activeBranches) ? max(1, (float) $activeBranches[0]['sales']) : 1;
        $formatRp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
        $formatNum = fn ($n) => number_format((int) $n, 0, ',', '.');
    @endphp

    <div class="ss-page">
        {{-- Page Header --}}
        <header class="ss-header">
            <div class="ss-header-left">
                <div class="ss-header-icon" aria-hidden="true">
                    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                </div>
                <div>
                    <h1 id="staff-stats-heading" class="ss-title">Statistik Staf &amp; Kasir</h1>
                    <div class="ss-subtitle-row">
                        <span id="ss-period-pill" class="ss-context-pill ss-context-pill-accent">{{ $initialSummary['period_label'] }}</span>
                        <span id="ss-branch-pill" class="ss-context-pill">{{ $initialSummary['pharmacy_label'] }}</span>
                    </div>
                </div>
            </div>

            <div class="ss-header-actions">
                <button type="button" id="ss-refresh-btn" class="ss-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    <span>Muat Ulang</span>
                </button>
                <button type="button" id="ss-export-csv" class="ss-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M7 10l5 5 5-5M12 15V3" />
                    </svg>
                    <span>Export CSV</span>
                </button>
            </div>
        </header>

        {{-- Filter Bar --}}
        <search>
            <section class="ss-card ss-filter-card" aria-label="Filter laporan performa staf">
                <form id="ss-filter-form" class="ss-filter-form" method="GET" action="{{ route('admin.staff-stats-page') }}" aria-labelledby="staff-stats-heading">
                    <input type="hidden" id="ss-period-input" name="period" value="{{ $activePeriod }}">

                    <div class="ss-filter-row">
                        <div class="ss-period-tabs" role="group" aria-label="Pilih periode laporan">
                            @foreach ($periodOptions as $key => $label)
                                <button
                                    type="button"
                                    class="ss-period-btn"
                                    data-period="{{ $key }}"
                                    aria-pressed="{{ $activePeriod === $key ? 'true' : 'false' }}"
                                >{{ $label }}</button>
                            @endforeach
                        </div>

                        <div class="ss-filter-right">
                            <label for="ss-pharmacy-select" class="ss-sr-only">Pilih cabang apotek</label>
                            <div class="ss-select-wrap">
                                <svg class="ss-select-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                </svg>
                                <select id="ss-pharmacy-select" name="pharmacy_id" class="ss-select">
                                    <option value="all" {{ $selectedPharmacy === 'all' ? 'selected' : '' }}>Semua Cabang</option>
                                    @foreach ($pharmacies as $pharmacy)
                                        <option value="{{ $pharmacy->id }}" {{ (string) $selectedPharmacy === (string) $pharmacy->id ? 'selected' : '' }}>
                                            {{ $pharmacy->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" id="ss-apply-btn" class="ss-btn ss-btn-primary">Tampilkan</button>
                        </div>
                    </div>

                    <div id="ss-custom-dates" class="ss-custom-dates" {{ $activePeriod === 'custom' ? '' : 'hidden' }}>
                        <div class="ss-date-group">
                            <label for="ss-start-date" class="ss-inline-label">Dari Tanggal</label>
                            <input
                                type="date"
                                id="ss-start-date"
                                name="start_date"
                                class="ss-input"
                                value="{{ $initialSummary['start_date'] }}"
                            >
                        </div>
                        <div class="ss-date-group">
                            <label for="ss-end-date" class="ss-inline-label">Sampai Tanggal</label>
                            <input
                                type="date"
                                id="ss-end-date"
                                name="end_date"
                                class="ss-input"
                                value="{{ $initialSummary['end_date'] }}"
                            >
                        </div>
                        <p id="ss-date-error" class="ss-field-error" role="alert" hidden></p>
                    </div>
                </form>
            </section>
        </search>

        {{-- 4 KPI Cards --}}
        <section class="ss-kpi-grid" aria-label="Ringkasan performa periode terpilih">
            <article class="ss-kpi-card">
                <div class="ss-kpi-top">
                    <div>
                        <div class="ss-kpi-label">Total Omset Bersih</div>
                        <div id="ss-kpi-sales" class="ss-kpi-value ss-num">{{ $formatRp($totals['sales']) }}</div>
                    </div>
                    <div class="ss-kpi-icon ss-kpi-icon-blue" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                        </svg>
                    </div>
                </div>
                <div id="ss-kpi-sales-meta" class="ss-kpi-footer ss-num">
                    @if ($comparison && $comparison['sales_delta_pct'] !== null)
                        @php $d = $comparison['sales_delta_pct']; @endphp
                        <span class="ss-delta-pill {{ $d > 0 ? 'ss-delta-up' : ($d < 0 ? 'ss-delta-down' : 'ss-delta-flat') }}">
                            {{ $d > 0 ? '↑ +' : ($d < 0 ? '↓ ' : '') }}{{ number_format($d, 1, ',', '.') }}%
                        </span>
                        <span>{{ $comparison['label'] }}</span>
                    @elseif ($comparison)
                        <span>{{ $comparison['label'] }}: Rp 0</span>
                    @else
                        <span>Akumulasi seluruh periode</span>
                    @endif
                </div>
            </article>

            <article class="ss-kpi-card">
                <div class="ss-kpi-top">
                    <div>
                        <div class="ss-kpi-label">Total Transaksi</div>
                        <div id="ss-kpi-txns" class="ss-kpi-value ss-num">{{ $formatNum($totals['transactions']) }} struk</div>
                    </div>
                    <div class="ss-kpi-icon ss-kpi-icon-sky" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
                        </svg>
                    </div>
                </div>
                <div id="ss-kpi-txns-meta" class="ss-kpi-footer ss-num">
                    @if ($comparison && $comparison['txns_delta_pct'] !== null)
                        @php $td = $comparison['txns_delta_pct']; @endphp
                        <span class="ss-delta-pill {{ $td > 0 ? 'ss-delta-up' : ($td < 0 ? 'ss-delta-down' : 'ss-delta-flat') }}">
                            {{ $td > 0 ? '↑ +' : ($td < 0 ? '↓ ' : '') }}{{ number_format($td, 1, ',', '.') }}%
                        </span>
                        <span>{{ $comparison['label'] }}</span>
                    @elseif ($totals['retur_count'] > 0)
                        <span>{{ $formatNum($totals['retur_count']) }} retur tercatat</span>
                    @else
                        <span>Tidak ada retur tercatat</span>
                    @endif
                </div>
            </article>

            <article class="ss-kpi-card">
                <div class="ss-kpi-top">
                    <div>
                        <div class="ss-kpi-label">Rata-rata / Struk</div>
                        <div id="ss-kpi-aov" class="ss-kpi-value ss-num">{{ $formatRp($totals['aov']) }}</div>
                    </div>
                    <div class="ss-kpi-icon ss-kpi-icon-green" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                        </svg>
                    </div>
                </div>
                <div id="ss-kpi-aov-meta" class="ss-kpi-footer ss-num">
                    <span><strong>{{ $formatNum($totals['shifts']) }}</strong> shift selesai</span>
                    @if ($totals['shifts'] > 0)
                        <span>&middot; {{ $formatRp($totals['sales_per_shift']) }}/shift</span>
                    @endif
                </div>
            </article>

            <article class="ss-kpi-card">
                <div class="ss-kpi-top">
                    <div>
                        <div class="ss-kpi-label">Staf Bertransaksi</div>
                        <div id="ss-kpi-active" class="ss-kpi-value ss-num">
                            {{ $formatNum($totals['active_staff']) }} <span style="font-size: 14px; font-weight: 600; color: #64748b;">/ {{ $formatNum($totals['total_staff']) }} staf</span>
                        </div>
                    </div>
                    <div class="ss-kpi-icon ss-kpi-icon-amber" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                </div>
                <div id="ss-kpi-top-meta" class="ss-kpi-footer ss-num">
                    @if ($topPerformer)
                        <span>Tertinggi: <strong>{{ $topPerformer['name'] }}</strong> ({{ number_format($topPerformer['sales_share'], 1, ',', '.') }}%)</span>
                    @else
                        <span>Belum ada transaksi pada periode ini</span>
                    @endif
                </div>
            </article>
        </section>

        {{-- 2-Column Visual Insights Row --}}
        <section class="ss-insights-grid" aria-label="Ringkasan visual kontribusi staf dan cabang">
            {{-- Top 5 Contributors --}}
            <div class="ss-card ss-panel">
                <div class="ss-panel-header">
                    <div>
                        <h2 class="ss-panel-title">Top 5 Kontributor Omset</h2>
                        <p class="ss-panel-sub">Klik nama staf untuk langsung memfilter tabel di bawah</p>
                    </div>
                    <span id="ss-top5-badge" class="ss-panel-badge ss-num">
                        {{ count($topFive) }} teratas
                    </span>
                </div>

                <div id="ss-top5-list" class="ss-bar-list">
                    @forelse ($topFive as $idx => $item)
                        @php
                            $rank = $idx + 1;
                            $relWidth = round(($item['sales'] / $maxTopSales) * 100, 1);
                            $rankClass = $rank <= 3 ? 'ss-rank-' . $rank : '';
                        @endphp
                        <button type="button" class="ss-bar-item ss-bar-item-btn" data-quick-search="{{ $item['name'] }}">
                            <div class="ss-bar-head">
                                <div class="ss-bar-label-wrap">
                                    <span class="ss-rank-chip {{ $rankClass }} ss-num">{{ $rank }}</span>
                                    <div style="min-width: 0;">
                                        <div class="ss-bar-name">{{ $item['name'] }}</div>
                                        <div class="ss-bar-sub">{{ $item['pharmacy_name'] }} &middot; {{ $formatNum($item['txns']) }} struk</div>
                                    </div>
                                </div>
                                <div class="ss-bar-figures ss-num">
                                    <span class="ss-bar-amount">{{ $formatRp($item['sales']) }}</span>
                                    <span class="ss-bar-pct">{{ number_format($item['sales_share'], 1, ',', '.') }}%</span>
                                </div>
                            </div>
                            <div class="ss-progress-track" aria-hidden="true">
                                <div class="ss-progress-fill {{ $rank === 1 ? 'ss-progress-fill-top' : '' }}" style="width: {{ max(4, $relWidth) }}%"></div>
                            </div>
                        </button>
                    @empty
                        <div class="ss-panel-empty">Belum ada kontribusi penjualan pada periode ini.</div>
                    @endforelse
                </div>
            </div>

            {{-- Branch Breakdown --}}
            <div class="ss-card ss-panel">
                <div class="ss-panel-header">
                    <div>
                        <h2 class="ss-panel-title">Distribusi per Cabang</h2>
                        <p class="ss-panel-sub">Perbandingan omset &amp; aktivitas antar cabang</p>
                    </div>
                    <span id="ss-branch-badge" class="ss-panel-badge ss-num">
                        {{ count($activeBranches) }} cabang aktif
                    </span>
                </div>

                <div id="ss-branch-list" class="ss-bar-list">
                    @forelse (array_slice($activeBranches, 0, 5) as $branch)
                        @php $bWidth = round(($branch['sales'] / $maxBranchSales) * 100, 1); @endphp
                        <div class="ss-bar-item">
                            <div class="ss-bar-head">
                                <div style="min-width: 0;">
                                    <div class="ss-bar-name">{{ $branch['name'] }}</div>
                                    <div class="ss-bar-sub">{{ $formatNum($branch['active_staff']) }} staf &middot; {{ $formatNum($branch['txns']) }} struk</div>
                                </div>
                                <div class="ss-bar-figures ss-num">
                                    <span class="ss-bar-amount">{{ $formatRp($branch['sales']) }}</span>
                                    <span class="ss-bar-pct">{{ number_format($branch['share'], 1, ',', '.') }}%</span>
                                </div>
                            </div>
                            <div class="ss-progress-track" aria-hidden="true">
                                <div class="ss-progress-fill ss-progress-fill-branch" style="width: {{ max(4, $bWidth) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="ss-panel-empty">Belum ada data cabang untuk periode terpilih.</div>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- Unified Staff Performance Table --}}
        <section class="ss-card ss-table-card" aria-label="Tabel rincian performa staf">
            <div class="ss-table-header">
                <div class="ss-table-title-wrap">
                    <h2 class="ss-panel-title">Rincian Performa Staf</h2>
                    <span id="ss-row-count" class="ss-panel-badge ss-num">
                        {{ $formatNum($totals['active_staff']) }} dari {{ $formatNum($totals['total_staff']) }} staf
                    </span>
                </div>

                <div class="ss-table-controls">
                    <div class="ss-search-wrap">
                        <label for="ss-table-search" class="ss-sr-only">Cari nama staf, username, cabang, atau peran</label>
                        <svg class="ss-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" />
                            <path stroke-linecap="round" d="m20 20-3.5-3.5" />
                        </svg>
                        <input
                            type="search"
                            id="ss-table-search"
                            class="ss-input"
                            placeholder="Cari nama, cabang, atau peran..."
                            autocorrect="off"
                            autocapitalize="none"
                            spellcheck="false"
                            enterkeyhint="search"
                        >
                    </div>

                    <div class="ss-seg-toggle" role="group" aria-label="Filter keaktifan staf">
                        <button type="button" class="ss-seg-btn" data-active-mode="active" aria-pressed="true">Ada Transaksi</button>
                        <button type="button" class="ss-seg-btn" data-active-mode="all" aria-pressed="false">Semua Staf</button>
                    </div>
                </div>
            </div>

            <div id="ss-status-live" role="status" class="ss-sr-only"></div>

            <div id="ss-table-wrapper" tabindex="0" role="region" aria-labelledby="ss-table-caption" class="ss-table-wrapper">
                <table id="ss-table" class="ss-table">
                    <caption id="ss-table-caption" class="ss-sr-only">
                        Daftar performa penjualan, transaksi, rata-rata struk, dan shift per staf
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col" class="ss-col-rank">#</th>
                            <th scope="col" data-type="text" data-col-key="name">
                                <button type="button" class="ss-sort-btn" commandfor="ss-table" command="--sort">
                                    <span>Staf</span>
                                    <svg class="ss-sort-icon" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
                                        <path class="ss-arrow-up" d="M6 1.5L9.5 5H2.5L6 1.5Z" />
                                        <path class="ss-arrow-down" d="M6 10.5L2.5 7H9.5L6 10.5Z" />
                                    </svg>
                                </button>
                            </th>
                            <th scope="col" class="ss-col-num" data-type="number" data-col-key="sales" aria-sort="descending">
                                <button type="button" class="ss-sort-btn" commandfor="ss-table" command="--sort">
                                    <span>Omset</span>
                                    <svg class="ss-sort-icon" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
                                        <path class="ss-arrow-up" d="M6 1.5L9.5 5H2.5L6 1.5Z" />
                                        <path class="ss-arrow-down" d="M6 10.5L2.5 7H9.5L6 10.5Z" />
                                    </svg>
                                </button>
                            </th>
                            <th scope="col" class="ss-col-num" data-type="number" data-col-key="txns">
                                <button type="button" class="ss-sort-btn" commandfor="ss-table" command="--sort">
                                    <span>Transaksi</span>
                                    <svg class="ss-sort-icon" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
                                        <path class="ss-arrow-up" d="M6 1.5L9.5 5H2.5L6 1.5Z" />
                                        <path class="ss-arrow-down" d="M6 10.5L2.5 7H9.5L6 10.5Z" />
                                    </svg>
                                </button>
                            </th>
                            <th scope="col" class="ss-col-num" data-type="number" data-col-key="aov">
                                <button type="button" class="ss-sort-btn" commandfor="ss-table" command="--sort">
                                    <span>Rata-rata Struk</span>
                                    <svg class="ss-sort-icon" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
                                        <path class="ss-arrow-up" d="M6 1.5L9.5 5H2.5L6 1.5Z" />
                                        <path class="ss-arrow-down" d="M6 10.5L2.5 7H9.5L6 10.5Z" />
                                    </svg>
                                </button>
                            </th>
                            <th scope="col" class="ss-col-num" data-type="number" data-col-key="shifts">
                                <button type="button" class="ss-sort-btn" commandfor="ss-table" command="--sort">
                                    <span>Shift</span>
                                    <svg class="ss-sort-icon" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
                                        <path class="ss-arrow-up" d="M6 1.5L9.5 5H2.5L6 1.5Z" />
                                        <path class="ss-arrow-down" d="M6 10.5L2.5 7H9.5L6 10.5Z" />
                                    </svg>
                                </button>
                            </th>
                            <th scope="col" class="ss-col-num" data-type="number" data-col-key="sales_per_shift">
                                <button type="button" class="ss-sort-btn" commandfor="ss-table" command="--sort">
                                    <span>Omset / Shift</span>
                                    <svg class="ss-sort-icon" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
                                        <path class="ss-arrow-up" d="M6 1.5L9.5 5H2.5L6 1.5Z" />
                                        <path class="ss-arrow-down" d="M6 10.5L2.5 7H9.5L6 10.5Z" />
                                    </svg>
                                </button>
                            </th>
                        </tr>
                    </thead>

                    <tbody id="ss-tbody">
                        @php $visibleRank = 0; @endphp
                        @foreach ($staffList as $row)
                            @php
                                $isActive = $row['sales'] > 0 || $row['txns'] > 0;
                                if ($isActive) {
                                    $visibleRank++;
                                }
                                $rankChipClass = ($isActive && $visibleRank <= 3) ? 'ss-rank-' . $visibleRank : '';
                                $searchHaystack = strtolower(trim($row['name'] . ' ' . $row['username'] . ' ' . $row['pharmacy_name'] . ' ' . $row['role_name']));
                                $subText = implode(' · ', array_filter([
                                    $row['username'] !== '' ? '@' . $row['username'] : null,
                                    $row['role_name'] !== '' ? $row['role_name'] : null,
                                ]));
                            @endphp
                            <tr
                                data-search="{{ $searchHaystack }}"
                                data-active="{{ $isActive ? '1' : '0' }}"
                                data-sales="{{ $row['sales'] }}"
                                data-txns="{{ $row['txns'] }}"
                                data-shifts="{{ $row['shifts'] }}"
                                data-retur="{{ $row['retur_count'] }}"
                                data-name="{{ $row['name'] }}"
                                data-username="{{ $row['username'] }}"
                                data-pharmacy="{{ $row['pharmacy_name'] }}"
                                data-role="{{ $row['role_name'] }}"
                                data-aov="{{ $row['aov'] }}"
                                data-sales-per-shift="{{ $row['sales_per_shift'] }}"
                                data-share="{{ $row['sales_share'] }}"
                                {{ $isActive ? '' : 'hidden' }}
                            >
                                <td class="ss-col-rank ss-num">
                                    <span class="ss-rank-chip {{ $rankChipClass }}">{{ $isActive ? $visibleRank : '-' }}</span>
                                </td>
                                <th scope="row" data-value="{{ $row['name'] }}">
                                    <div class="ss-staff-cell">
                                        <span class="ss-avatar {{ $isActive ? '' : 'ss-avatar-muted' }}" aria-hidden="true">{{ $row['initials'] ?: '?' }}</span>
                                        <div>
                                            <div class="ss-staff-name">{{ $row['name'] }}</div>
                                            <div class="ss-staff-meta">
                                                <span class="ss-branch-tag">{{ $row['pharmacy_name'] }}</span>
                                                @if ($subText !== '')
                                                    <span>{{ $subText }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </th>
                                <td class="ss-col-num ss-num" data-value="{{ $row['sales'] }}">
                                    <div class="ss-sales-cell">
                                        <span class="ss-sales-amount {{ $row['sales'] <= 0 ? 'ss-muted-zero' : '' }}">
                                            {{ $formatRp($row['sales']) }}
                                        </span>
                                        <div class="ss-share-row">
                                            <div class="ss-share-track" aria-hidden="true">
                                                <div class="ss-share-fill" style="width: {{ min(100, max(0, $row['sales_share'])) }}%"></div>
                                            </div>
                                            <span class="ss-share-text">{{ number_format($row['sales_share'], 1, ',', '.') }}%</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="ss-col-num ss-num" data-value="{{ $row['txns'] }}">
                                    <div style="font-weight: 700;" class="{{ $row['txns'] <= 0 ? 'ss-muted-zero' : '' }}">{{ $formatNum($row['txns']) }}</div>
                                    @if ($row['retur_count'] > 0)
                                        <span class="ss-retur-pill">{{ $formatNum($row['retur_count']) }} retur</span>
                                    @endif
                                </td>
                                <td class="ss-col-num ss-num {{ $row['aov'] <= 0 ? 'ss-muted-zero' : '' }}" data-value="{{ $row['aov'] }}">
                                    {{ $row['txns'] > 0 ? $formatRp($row['aov']) : '-' }}
                                </td>
                                <td class="ss-col-num ss-num" data-value="{{ $row['shifts'] }}">
                                    @if ($row['shifts'] > 0)
                                        <span class="ss-shift-pill">{{ $formatNum($row['shifts']) }} shift</span>
                                    @else
                                        <span class="ss-muted-zero">-</span>
                                    @endif
                                </td>
                                <td class="ss-col-num ss-num {{ $row['sales_per_shift'] <= 0 ? 'ss-muted-zero' : '' }}" data-value="{{ $row['sales_per_shift'] }}">
                                    {{ $row['shifts'] > 0 ? $formatRp($row['sales_per_shift']) : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot id="ss-tfoot" {{ $totals['active_staff'] > 0 ? '' : 'hidden' }}>
                        <tr>
                            <td></td>
                            <th scope="row" id="ss-foot-label">Total ({{ $formatNum($totals['active_staff']) }} staf)</th>
                            <td id="ss-foot-sales" class="ss-col-num ss-num">{{ $formatRp($totals['sales']) }}</td>
                            <td id="ss-foot-txns" class="ss-col-num ss-num">{{ $formatNum($totals['transactions']) }}</td>
                            <td id="ss-foot-aov" class="ss-col-num ss-num">{{ $totals['transactions'] > 0 ? $formatRp($totals['aov']) : '-' }}</td>
                            <td id="ss-foot-shifts" class="ss-col-num ss-num">{{ $formatNum($totals['shifts']) }}</td>
                            <td id="ss-foot-sps" class="ss-col-num ss-num">{{ $totals['shifts'] > 0 ? $formatRp($totals['sales_per_shift']) : '-' }}</td>
                        </tr>
                    </tfoot>
                </table>

                <div id="ss-empty-state" class="ss-empty-state" {{ $totals['active_staff'] > 0 ? 'hidden' : '' }}>
                    Tidak ada data staf yang cocok dengan filter saat ini.
                </div>
            </div>
        </section>
    </div>
@endsection

@section('scripts')
    <script>
        (() => {
            const SUMMARY_URL = @json(route('admin.staff-stats.summary'));
            const PAGE_URL = @json(route('admin.staff-stats-page'));
            let currentSummary = @json($initialSummary);
            let onlyActiveMode = true;

            const filterForm = document.getElementById('ss-filter-form');
            const periodInput = document.getElementById('ss-period-input');
            const periodButtons = Array.from(document.querySelectorAll('.ss-period-btn'));
            const pharmacySelect = document.getElementById('ss-pharmacy-select');
            const customDatesWrap = document.getElementById('ss-custom-dates');
            const startDateInput = document.getElementById('ss-start-date');
            const endDateInput = document.getElementById('ss-end-date');
            const dateErrorEl = document.getElementById('ss-date-error');
            const applyBtn = document.getElementById('ss-apply-btn');
            const refreshBtn = document.getElementById('ss-refresh-btn');

            const periodPillEl = document.getElementById('ss-period-pill');
            const branchPillEl = document.getElementById('ss-branch-pill');
            const kpiSalesEl = document.getElementById('ss-kpi-sales');
            const kpiSalesMetaEl = document.getElementById('ss-kpi-sales-meta');
            const kpiTxnsEl = document.getElementById('ss-kpi-txns');
            const kpiTxnsMetaEl = document.getElementById('ss-kpi-txns-meta');
            const kpiAovEl = document.getElementById('ss-kpi-aov');
            const kpiAovMetaEl = document.getElementById('ss-kpi-aov-meta');
            const kpiActiveEl = document.getElementById('ss-kpi-active');
            const kpiTopMetaEl = document.getElementById('ss-kpi-top-meta');

            const top5BadgeEl = document.getElementById('ss-top5-badge');
            const top5ListEl = document.getElementById('ss-top5-list');
            const branchBadgeEl = document.getElementById('ss-branch-badge');
            const branchListEl = document.getElementById('ss-branch-list');

            const searchInput = document.getElementById('ss-table-search');
            const segButtons = Array.from(document.querySelectorAll('.ss-seg-btn'));
            const rowCountEl = document.getElementById('ss-row-count');
            const statusLiveEl = document.getElementById('ss-status-live');
            const tableWrapper = document.getElementById('ss-table-wrapper');
            const table = document.getElementById('ss-table');
            const tbody = document.getElementById('ss-tbody');
            const tfoot = document.getElementById('ss-tfoot');
            const emptyStateEl = document.getElementById('ss-empty-state');
            const exportCsvBtn = document.getElementById('ss-export-csv');

            const footLabelEl = document.getElementById('ss-foot-label');
            const footSalesEl = document.getElementById('ss-foot-sales');
            const footTxnsEl = document.getElementById('ss-foot-txns');
            const footAovEl = document.getElementById('ss-foot-aov');
            const footShiftsEl = document.getElementById('ss-foot-shifts');
            const footSpsEl = document.getElementById('ss-foot-sps');

            const collator = new Intl.Collator('id-ID', { numeric: true, sensitivity: 'base' });
            const numFmt = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
            const pctFmt = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

            let activeController = null;
            let currentSort = { colIndex: 2, type: 'number', direction: 'descending' };

            function formatRp(val) {
                return 'Rp ' + numFmt.format(Math.round(Number(val) || 0));
            }

            function formatNum(val) {
                return numFmt.format(Math.round(Number(val) || 0));
            }

            function formatPct(val) {
                return pctFmt.format(Number(val) || 0) + '%';
            }

            function announce(message) {
                statusLiveEl.textContent = '';
                window.requestAnimationFrame(() => {
                    statusLiveEl.textContent = message;
                });
            }

            function setDateError(message) {
                if (message) {
                    dateErrorEl.textContent = message;
                    dateErrorEl.hidden = false;
                    startDateInput.setAttribute('aria-invalid', 'true');
                    endDateInput.setAttribute('aria-invalid', 'true');
                } else {
                    dateErrorEl.textContent = '';
                    dateErrorEl.hidden = true;
                    startDateInput.removeAttribute('aria-invalid');
                    endDateInput.removeAttribute('aria-invalid');
                }
            }

            function buildDeltaFragment(deltaPct, label) {
                const frag = document.createDocumentFragment();
                const pill = document.createElement('span');
                if (deltaPct > 0) {
                    pill.className = 'ss-delta-pill ss-delta-up';
                    pill.textContent = '↑ +' + pctFmt.format(deltaPct) + '%';
                } else if (deltaPct < 0) {
                    pill.className = 'ss-delta-pill ss-delta-down';
                    pill.textContent = '↓ ' + pctFmt.format(deltaPct) + '%';
                } else {
                    pill.className = 'ss-delta-pill ss-delta-flat';
                    pill.textContent = '0,0%';
                }
                const labelSpan = document.createElement('span');
                labelSpan.textContent = label;
                frag.append(pill, labelSpan);
                return frag;
            }

            function updateKpis(summary) {
                const t = summary.totals || {};
                const cmp = summary.comparison || null;
                const top = summary.top_performer || null;

                periodPillEl.textContent = summary.period_label || '-';
                branchPillEl.textContent = summary.pharmacy_label || 'Semua Cabang';

                kpiSalesEl.textContent = formatRp(t.sales);
                kpiSalesMetaEl.replaceChildren();
                if (cmp && cmp.sales_delta_pct !== null && cmp.sales_delta_pct !== undefined) {
                    kpiSalesMetaEl.append(buildDeltaFragment(cmp.sales_delta_pct, cmp.label));
                } else if (cmp) {
                    const s = document.createElement('span');
                    s.textContent = `${cmp.label}: Rp 0`;
                    kpiSalesMetaEl.append(s);
                } else {
                    const s = document.createElement('span');
                    s.textContent = 'Akumulasi seluruh periode';
                    kpiSalesMetaEl.append(s);
                }

                kpiTxnsEl.textContent = `${formatNum(t.transactions)} struk`;
                kpiTxnsMetaEl.replaceChildren();
                if (cmp && cmp.txns_delta_pct !== null && cmp.txns_delta_pct !== undefined) {
                    kpiTxnsMetaEl.append(buildDeltaFragment(cmp.txns_delta_pct, cmp.label));
                } else {
                    const s = document.createElement('span');
                    s.textContent = (t.retur_count || 0) > 0
                        ? `${formatNum(t.retur_count)} retur tercatat`
                        : 'Tidak ada retur tercatat';
                    kpiTxnsMetaEl.append(s);
                }

                kpiAovEl.textContent = formatRp(t.aov);
                kpiAovMetaEl.replaceChildren();
                const shiftWrap = document.createElement('span');
                const shiftStrong = document.createElement('strong');
                shiftStrong.textContent = formatNum(t.shifts);
                shiftWrap.append(shiftStrong, ' shift selesai');
                kpiAovMetaEl.append(shiftWrap);
                if ((t.shifts || 0) > 0) {
                    const spsSpan = document.createElement('span');
                    spsSpan.textContent = `· ${formatRp(t.sales_per_shift)}/shift`;
                    kpiAovMetaEl.append(spsSpan);
                }

                kpiActiveEl.replaceChildren();
                kpiActiveEl.append(document.createTextNode(formatNum(t.active_staff) + ' '));
                const totalSub = document.createElement('span');
                totalSub.style.fontSize = '14px';
                totalSub.style.fontWeight = '600';
                totalSub.style.color = '#64748b';
                totalSub.textContent = `/ ${formatNum(t.total_staff)} staf`;
                kpiActiveEl.append(totalSub);

                kpiTopMetaEl.replaceChildren();
                const topSpan = document.createElement('span');
                if (top) {
                    topSpan.append('Tertinggi: ');
                    const strong = document.createElement('strong');
                    strong.textContent = top.name;
                    topSpan.append(strong, ` (${formatPct(top.sales_share)})`);
                } else {
                    topSpan.textContent = 'Belum ada transaksi pada periode ini';
                }
                kpiTopMetaEl.append(topSpan);
            }

            function renderInsights(summary) {
                const staff = summary.staff || [];
                const top5 = staff.filter((s) => (Number(s.sales) || 0) > 0).slice(0, 5);
                const maxTopSales = top5.length ? Math.max(1, Number(top5[0].sales) || 1) : 1;

                top5BadgeEl.textContent = `${top5.length} teratas`;
                top5ListEl.replaceChildren();

                if (!top5.length) {
                    const empty = document.createElement('div');
                    empty.className = 'ss-panel-empty';
                    empty.textContent = 'Belum ada kontribusi penjualan pada periode ini.';
                    top5ListEl.append(empty);
                } else {
                    top5.forEach((item, idx) => {
                        const rank = idx + 1;
                        const relWidth = Math.max(4, Math.round(((Number(item.sales) || 0) / maxTopSales) * 1000) / 10);

                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'ss-bar-item ss-bar-item-btn';
                        btn.dataset.quickSearch = item.name || '';

                        const head = document.createElement('div');
                        head.className = 'ss-bar-head';

                        const labelWrap = document.createElement('div');
                        labelWrap.className = 'ss-bar-label-wrap';

                        const rankChip = document.createElement('span');
                        rankChip.className = `ss-rank-chip ss-num${rank <= 3 ? ` ss-rank-${rank}` : ''}`;
                        rankChip.textContent = String(rank);

                        const nameBox = document.createElement('div');
                        nameBox.style.minWidth = '0';
                        const nameEl = document.createElement('div');
                        nameEl.className = 'ss-bar-name';
                        nameEl.textContent = item.name || '-';
                        const subEl = document.createElement('div');
                        subEl.className = 'ss-bar-sub';
                        subEl.textContent = `${item.pharmacy_name || 'Pusat / HO'} · ${formatNum(item.txns)} struk`;
                        nameBox.append(nameEl, subEl);
                        labelWrap.append(rankChip, nameBox);

                        const figures = document.createElement('div');
                        figures.className = 'ss-bar-figures ss-num';
                        const amt = document.createElement('span');
                        amt.className = 'ss-bar-amount';
                        amt.textContent = formatRp(item.sales);
                        const pct = document.createElement('span');
                        pct.className = 'ss-bar-pct';
                        pct.textContent = formatPct(item.sales_share);
                        figures.append(amt, pct);

                        head.append(labelWrap, figures);

                        const track = document.createElement('div');
                        track.className = 'ss-progress-track';
                        track.setAttribute('aria-hidden', 'true');
                        const fill = document.createElement('div');
                        fill.className = `ss-progress-fill${rank === 1 ? ' ss-progress-fill-top' : ''}`;
                        fill.style.width = `${relWidth}%`;
                        track.append(fill);

                        btn.append(head, track);
                        top5ListEl.append(btn);
                    });
                }

                const branches = (summary.branches || []).filter((b) => (Number(b.sales) || 0) > 0 || (Number(b.txns) || 0) > 0);
                const maxBranchSales = branches.length ? Math.max(1, Number(branches[0].sales) || 1) : 1;

                branchBadgeEl.textContent = `${branches.length} cabang aktif`;
                branchListEl.replaceChildren();

                if (!branches.length) {
                    const empty = document.createElement('div');
                    empty.className = 'ss-panel-empty';
                    empty.textContent = 'Belum ada data cabang untuk periode terpilih.';
                    branchListEl.append(empty);
                } else {
                    branches.slice(0, 5).forEach((branch) => {
                        const relWidth = Math.max(4, Math.round(((Number(branch.sales) || 0) / maxBranchSales) * 1000) / 10);

                        const row = document.createElement('div');
                        row.className = 'ss-bar-item';

                        const head = document.createElement('div');
                        head.className = 'ss-bar-head';

                        const nameBox = document.createElement('div');
                        nameBox.style.minWidth = '0';
                        const nameEl = document.createElement('div');
                        nameEl.className = 'ss-bar-name';
                        nameEl.textContent = branch.name || 'Pusat / HO';
                        const subEl = document.createElement('div');
                        subEl.className = 'ss-bar-sub';
                        subEl.textContent = `${formatNum(branch.active_staff)} staf · ${formatNum(branch.txns)} struk`;
                        nameBox.append(nameEl, subEl);

                        const figures = document.createElement('div');
                        figures.className = 'ss-bar-figures ss-num';
                        const amt = document.createElement('span');
                        amt.className = 'ss-bar-amount';
                        amt.textContent = formatRp(branch.sales);
                        const pct = document.createElement('span');
                        pct.className = 'ss-bar-pct';
                        pct.textContent = formatPct(branch.share);
                        figures.append(amt, pct);

                        head.append(nameBox, figures);

                        const track = document.createElement('div');
                        track.className = 'ss-progress-track';
                        track.setAttribute('aria-hidden', 'true');
                        const fill = document.createElement('div');
                        fill.className = 'ss-progress-fill ss-progress-fill-branch';
                        fill.style.width = `${relWidth}%`;
                        track.append(fill);

                        row.append(head, track);
                        branchListEl.append(row);
                    });
                }
            }

            function buildStaffRowElement(row) {
                const tr = document.createElement('tr');
                const isActive = (Number(row.sales) || 0) > 0 || (Number(row.txns) || 0) > 0;
                const searchHaystack = [row.name, row.username, row.pharmacy_name, row.role_name]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase();

                tr.dataset.search = searchHaystack;
                tr.dataset.active = isActive ? '1' : '0';
                tr.dataset.sales = String(row.sales || 0);
                tr.dataset.txns = String(row.txns || 0);
                tr.dataset.shifts = String(row.shifts || 0);
                tr.dataset.retur = String(row.retur_count || 0);
                tr.dataset.name = row.name || '';
                tr.dataset.username = row.username || '';
                tr.dataset.pharmacy = row.pharmacy_name || '';
                tr.dataset.role = row.role_name || '';
                tr.dataset.aov = String(row.aov || 0);
                tr.dataset.salesPerShift = String(row.sales_per_shift || 0);
                tr.dataset.share = String(row.sales_share || 0);

                // 1. Rank
                const tdRank = document.createElement('td');
                tdRank.className = 'ss-col-rank ss-num';
                const rankChip = document.createElement('span');
                rankChip.className = 'ss-rank-chip';
                rankChip.textContent = '-';
                tdRank.append(rankChip);

                // 2. Staff row header
                const thStaff = document.createElement('th');
                thStaff.scope = 'row';
                thStaff.dataset.value = row.name || '';
                const staffCell = document.createElement('div');
                staffCell.className = 'ss-staff-cell';
                const avatar = document.createElement('span');
                avatar.className = `ss-avatar${isActive ? '' : ' ss-avatar-muted'}`;
                avatar.setAttribute('aria-hidden', 'true');
                avatar.textContent = row.initials || '?';
                const info = document.createElement('div');
                const nameDiv = document.createElement('div');
                nameDiv.className = 'ss-staff-name';
                nameDiv.textContent = row.name || '-';
                const metaDiv = document.createElement('div');
                metaDiv.className = 'ss-staff-meta';
                const branchTag = document.createElement('span');
                branchTag.className = 'ss-branch-tag';
                branchTag.textContent = row.pharmacy_name || 'Pusat / HO';
                metaDiv.append(branchTag);
                const subText = [
                    row.username ? `@${row.username}` : '',
                    row.role_name || '',
                ].filter(Boolean).join(' · ');
                if (subText) {
                    const subSpan = document.createElement('span');
                    subSpan.textContent = subText;
                    metaDiv.append(subSpan);
                }
                info.append(nameDiv, metaDiv);
                staffCell.append(avatar, info);
                thStaff.append(staffCell);

                // 3. Omset + share bar
                const tdSales = document.createElement('td');
                tdSales.className = 'ss-col-num ss-num';
                tdSales.dataset.value = String(row.sales || 0);
                const salesWrap = document.createElement('div');
                salesWrap.className = 'ss-sales-cell';
                const salesAmount = document.createElement('span');
                salesAmount.className = 'ss-sales-amount' + ((Number(row.sales) || 0) <= 0 ? ' ss-muted-zero' : '');
                salesAmount.textContent = formatRp(row.sales);
                const shareRow = document.createElement('div');
                shareRow.className = 'ss-share-row';
                const shareTrack = document.createElement('div');
                shareTrack.className = 'ss-share-track';
                shareTrack.setAttribute('aria-hidden', 'true');
                const shareFill = document.createElement('div');
                shareFill.className = 'ss-share-fill';
                shareFill.style.width = `${Math.min(100, Math.max(0, Number(row.sales_share) || 0))}%`;
                shareTrack.append(shareFill);
                const shareText = document.createElement('span');
                shareText.className = 'ss-share-text';
                shareText.textContent = formatPct(row.sales_share);
                shareRow.append(shareTrack, shareText);
                salesWrap.append(salesAmount, shareRow);
                tdSales.append(salesWrap);

                // 4. Transaksi
                const tdTxns = document.createElement('td');
                tdTxns.className = 'ss-col-num ss-num';
                tdTxns.dataset.value = String(row.txns || 0);
                const txnDiv = document.createElement('div');
                txnDiv.style.fontWeight = '700';
                if ((Number(row.txns) || 0) <= 0) {
                    txnDiv.className = 'ss-muted-zero';
                }
                txnDiv.textContent = formatNum(row.txns);
                tdTxns.append(txnDiv);
                if ((Number(row.retur_count) || 0) > 0) {
                    const retSpan = document.createElement('span');
                    retSpan.className = 'ss-retur-pill';
                    retSpan.textContent = `${formatNum(row.retur_count)} retur`;
                    tdTxns.append(retSpan);
                }

                // 5. AOV
                const tdAov = document.createElement('td');
                tdAov.className = 'ss-col-num ss-num' + ((Number(row.aov) || 0) <= 0 ? ' ss-muted-zero' : '');
                tdAov.dataset.value = String(row.aov || 0);
                tdAov.textContent = (Number(row.txns) || 0) > 0 ? formatRp(row.aov) : '-';

                // 6. Shift
                const tdShifts = document.createElement('td');
                tdShifts.className = 'ss-col-num ss-num';
                tdShifts.dataset.value = String(row.shifts || 0);
                if ((Number(row.shifts) || 0) > 0) {
                    const pill = document.createElement('span');
                    pill.className = 'ss-shift-pill';
                    pill.textContent = `${formatNum(row.shifts)} shift`;
                    tdShifts.append(pill);
                } else {
                    const dash = document.createElement('span');
                    dash.className = 'ss-muted-zero';
                    dash.textContent = '-';
                    tdShifts.append(dash);
                }

                // 7. Omset / Shift
                const tdSps = document.createElement('td');
                tdSps.className = 'ss-col-num ss-num' + ((Number(row.sales_per_shift) || 0) <= 0 ? ' ss-muted-zero' : '');
                tdSps.dataset.value = String(row.sales_per_shift || 0);
                tdSps.textContent = (Number(row.shifts) || 0) > 0 ? formatRp(row.sales_per_shift) : '-';

                tr.append(tdRank, thStaff, tdSales, tdTxns, tdAov, tdShifts, tdSps);
                return tr;
            }

            function renderStaffRows(staffList) {
                const frag = document.createDocumentFragment();
                for (const item of staffList) {
                    frag.append(buildStaffRowElement(item));
                }
                tbody.replaceChildren(frag);
            }

            function moveRowsInPlace(orderedRows) {
                const canMoveBefore = typeof tbody.moveBefore === 'function';
                for (const row of orderedRows) {
                    if (canMoveBefore) {
                        tbody.moveBefore(row, null);
                    } else {
                        tbody.appendChild(row);
                    }
                }
            }

            function sortTableByColumn(th, forcedDirection = null) {
                const colIndex = th.cellIndex;
                const type = th.dataset.type || 'text';
                const currentDir = th.getAttribute('aria-sort');
                const direction = forcedDirection
                    || (currentDir === 'ascending' ? 'descending' : 'ascending');

                for (const cell of table.tHead.rows[0].cells) {
                    cell.removeAttribute('aria-sort');
                }
                th.setAttribute('aria-sort', direction);
                currentSort = { colIndex, type, direction };

                const rows = Array.from(tbody.rows);
                const factor = direction === 'ascending' ? 1 : -1;

                const keyed = rows.map((row) => {
                    const cell = row.cells[colIndex];
                    const raw = cell.dataset.value ?? cell.textContent.trim();
                    return {
                        row,
                        val: type === 'number' ? Number(raw) || 0 : raw,
                    };
                });

                keyed.sort((a, b) => {
                    if (type === 'number') {
                        return factor * (a.val - b.val);
                    }
                    return factor * collator.compare(a.val, b.val);
                });

                moveRowsInPlace(keyed.map((item) => item.row));
                applyTableFilters(false);

                const colLabel = th.querySelector('button')?.textContent.trim() || th.textContent.trim();
                const dirText = direction === 'ascending' ? 'menaik' : 'menurun';
                const visibleCount = Array.from(tbody.rows).filter((r) => !r.hidden).length;
                announce(`Diurutkan berdasarkan ${colLabel}, ${dirText}, ${visibleCount} staf`);
            }

            function applyTableFilters(announceChange = false) {
                const query = searchInput.value.trim().toLowerCase();
                const rows = Array.from(tbody.rows);

                let visibleCount = 0;
                let sumSales = 0;
                let sumTxns = 0;
                let sumShifts = 0;

                for (const row of rows) {
                    const matchesActive = !onlyActiveMode || row.dataset.active === '1';
                    const matchesQuery = query === '' || (row.dataset.search || '').includes(query);
                    const show = matchesActive && matchesQuery;

                    row.hidden = !show;
                    const rankChip = row.cells[0].querySelector('.ss-rank-chip');
                    if (show) {
                        visibleCount++;
                        if (rankChip) {
                            rankChip.textContent = String(visibleCount);
                            rankChip.className = `ss-rank-chip${visibleCount <= 3 && row.dataset.active === '1' ? ` ss-rank-${visibleCount}` : ''}`;
                        }
                        sumSales += Number(row.dataset.sales) || 0;
                        sumTxns += Number(row.dataset.txns) || 0;
                        sumShifts += Number(row.dataset.shifts) || 0;
                    } else if (rankChip) {
                        rankChip.textContent = '-';
                        rankChip.className = 'ss-rank-chip';
                    }
                }

                rowCountEl.textContent = `${formatNum(visibleCount)} dari ${formatNum(rows.length)} staf`;
                emptyStateEl.hidden = visibleCount > 0;
                tfoot.hidden = visibleCount === 0;

                if (visibleCount > 0) {
                    const footAov = sumTxns > 0 ? sumSales / sumTxns : 0;
                    const footSps = sumShifts > 0 ? sumSales / sumShifts : 0;
                    footLabelEl.textContent = `Total (${formatNum(visibleCount)} staf)`;
                    footSalesEl.textContent = formatRp(sumSales);
                    footTxnsEl.textContent = formatNum(sumTxns);
                    footAovEl.textContent = sumTxns > 0 ? formatRp(footAov) : '-';
                    footShiftsEl.textContent = formatNum(sumShifts);
                    footSpsEl.textContent = sumShifts > 0 ? formatRp(footSps) : '-';
                }

                if (announceChange) {
                    announce(`Menampilkan ${visibleCount} dari ${rows.length} staf`);
                }
            }

            async function fetchSummary() {
                const period = periodInput.value || 'today';
                const pharmacyId = pharmacySelect.value || 'all';
                const params = new URLSearchParams();
                params.set('period', period);
                params.set('pharmacy_id', pharmacyId);

                if (period === 'custom') {
                    const startVal = startDateInput.value;
                    const endVal = endDateInput.value;
                    if (!startVal || !endVal) {
                        setDateError('Pilih tanggal mulai dan tanggal akhir terlebih dahulu.');
                        (!startVal ? startDateInput : endDateInput).focus();
                        return;
                    }
                    setDateError('');
                    params.set('start_date', startVal);
                    params.set('end_date', endVal);
                } else {
                    setDateError('');
                }

                if (activeController) {
                    activeController.abort();
                }
                activeController = new AbortController();

                tableWrapper.setAttribute('aria-busy', 'true');
                applyBtn.disabled = true;
                refreshBtn.disabled = true;

                try {
                    const response = await fetch(`${SUMMARY_URL}?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: activeController.signal,
                    });

                    if (response.status === 401 || response.status === 419) {
                        window.location.reload();
                        return;
                    }
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const data = await response.json();
                    currentSummary = data;

                    if (data.start_date) startDateInput.value = data.start_date;
                    if (data.end_date) endDateInput.value = data.end_date;

                    updateKpis(data);
                    renderInsights(data);
                    renderStaffRows(data.staff || []);

                    const activeHeader = table.tHead.rows[0].cells[currentSort.colIndex];
                    if (activeHeader && activeHeader.querySelector('button')) {
                        sortTableByColumn(activeHeader, currentSort.direction);
                    } else {
                        applyTableFilters(false);
                    }

                    const nextUrl = `${PAGE_URL}?${params.toString()}`;
                    window.history.replaceState(null, '', nextUrl);

                    announce(`Data diperbarui untuk ${data.period_label}, ${data.pharmacy_label}`);
                } catch (err) {
                    if (err.name !== 'AbortError') {
                        console.error('Failed to load staff stats:', err);
                        announce('Gagal memuat data statistik staf.');
                    }
                } finally {
                    tableWrapper.removeAttribute('aria-busy');
                    applyBtn.disabled = false;
                    refreshBtn.disabled = false;
                }
            }

            // Period preset buttons
            for (const btn of periodButtons) {
                btn.addEventListener('click', () => {
                    const nextPeriod = btn.dataset.period || 'today';
                    periodInput.value = nextPeriod;

                    for (const b of periodButtons) {
                        b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
                    }

                    const isCustom = nextPeriod === 'custom';
                    customDatesWrap.hidden = !isCustom;
                    setDateError('');

                    if (isCustom) {
                        startDateInput.focus();
                    } else {
                        fetchSummary();
                    }
                });
            }

            // Form submit & Refresh
            filterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                fetchSummary();
            });
            refreshBtn.addEventListener('click', () => fetchSummary());

            startDateInput.addEventListener('input', () => setDateError(''));
            endDateInput.addEventListener('input', () => setDateError(''));

            // Quick filter from clicking a Top 5 bar
            top5ListEl.addEventListener('click', (event) => {
                const btn = event.target.closest('button[data-quick-search]');
                if (!btn) return;
                const name = btn.dataset.quickSearch || '';
                searchInput.value = searchInput.value.trim().toLowerCase() === name.toLowerCase() ? '' : name;
                applyTableFilters(true);
                table.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });

            // Live search & segmented active/all toggle
            searchInput.addEventListener('input', () => applyTableFilters(false));
            searchInput.addEventListener('keydown', (e) => {
                if (e.isComposing) return;
                if (e.key === 'Escape' && searchInput.value !== '') {
                    searchInput.value = '';
                    applyTableFilters(true);
                }
            });

            for (const btn of segButtons) {
                btn.addEventListener('click', () => {
                    onlyActiveMode = btn.dataset.activeMode === 'active';
                    for (const b of segButtons) {
                        b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
                    }
                    applyTableFilters(true);
                });
            }

            // Sortable table: Invoker Commands API + progressive fallback
            table.addEventListener('command', (event) => {
                if (event.command !== '--sort') return;
                const th = event.source?.closest('th');
                if (th) {
                    sortTableByColumn(th);
                }
            });

            if (!('commandForElement' in HTMLButtonElement.prototype)) {
                table.tHead.addEventListener('click', (event) => {
                    const btn = event.target.closest('button[command="--sort"]');
                    if (!btn) return;
                    const th = btn.closest('th');
                    if (th) {
                        sortTableByColumn(th);
                    }
                });
            }

            // Export visible & sorted rows as UTF-8 CSV
            exportCsvBtn.addEventListener('click', () => {
                const visibleRows = Array.from(tbody.rows).filter((r) => !r.hidden);
                const escapeCsv = (val) => {
                    const str = String(val ?? '');
                    return /[",\n\r]/.test(str) ? `"${str.replace(/"/g, '""')}"` : str;
                };

                const headers = [
                    'No',
                    'Nama Staf',
                    'Username',
                    'Cabang',
                    'Peran',
                    'Omset (Rp)',
                    'Kontribusi (%)',
                    'Transaksi',
                    'Retur',
                    'Rata-rata Struk (Rp)',
                    'Shift Selesai',
                    'Omset per Shift (Rp)',
                ];

                const lines = [headers.map(escapeCsv).join(',')];
                visibleRows.forEach((row, idx) => {
                    lines.push([
                        idx + 1,
                        row.dataset.name,
                        row.dataset.username,
                        row.dataset.pharmacy,
                        row.dataset.role,
                        Math.round(Number(row.dataset.sales) || 0),
                        row.dataset.share,
                        Number(row.dataset.txns) || 0,
                        Number(row.dataset.retur) || 0,
                        Math.round(Number(row.dataset.aov) || 0),
                        Number(row.dataset.shifts) || 0,
                        Math.round(Number(row.dataset.salesPerShift) || 0),
                    ].map(escapeCsv).join(','));
                });

                const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                const safeStart = (currentSummary.start_date || 'data').replace(/[^0-9-]/g, '');
                const safeEnd = (currentSummary.end_date || '').replace(/[^0-9-]/g, '');
                link.href = url;
                link.download = `statistik-staf-${safeStart}${safeEnd && safeEnd !== safeStart ? '_sd_' + safeEnd : ''}.csv`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            });
        })();
    </script>
@endsection
