<a href="{{ route('master-data.export', ['type' => $type]) }}"
    class="ml-auto inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700"
    title="Export semua data ke CSV">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-8-4 4 4m0 0 4-4m-4 4V4" />
    </svg>
    <span>Export CSV</span>
</a>
