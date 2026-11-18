{{-- Fallback Toast Container (jika iziToast belum termuat) --}}
<div id="transferToastFallback" class="fixed top-4 right-4 z-[1000010] flex flex-col gap-2 pointer-events-none max-w-sm"></div>

{{-- Floating Searchable Combobox Popover untuk Etalase --}}
<div id="etalaseSearchPopover" class="hidden fixed z-[999850] w-64 bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden" role="listbox">
    <div class="p-2 border-b border-slate-100 bg-slate-50/80">
        <div class="relative">
            <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <circle cx="11" cy="11" r="7" />
                <path stroke-linecap="round" d="m20 20-3.5-3.5" />
            </svg>
            <input type="search" id="etalasePopoverSearchInput" autocomplete="off" placeholder="Ketik cari etalase..."
                class="w-full pl-8 pr-2.5 py-1.5 text-xs bg-white border border-slate-200 rounded-lg outline-none focus:border-indigo-500 transition">
        </div>
    </div>
    <div id="etalasePopoverOptions" class="max-h-60 overflow-y-auto py-1 divide-y divide-slate-50 bg-white"></div>
    <div class="border-t border-slate-100 bg-slate-50/70 p-1.5">
        <button type="button" id="etalasePopoverManageBtn"
            class="w-full flex items-center justify-center gap-1.5 px-2.5 py-1.5 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-50 rounded-lg transition">
            <span>⚙</span>
            <span>Kelola / Ubah / Hapus Etalase</span>
        </button>
    </div>
</div>

{{-- Modal Konfirmasi Modern (pengganti confirm() bawaan browser) --}}
<div id="transferConfirmModal" class="hidden fixed inset-0 z-[999980] items-center justify-center bg-slate-900/45 backdrop-blur-[1px] p-4" aria-modal="true" role="dialog">
    <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl border border-slate-200 overflow-hidden">
        <div class="p-5">
            <div class="flex items-start gap-3">
                <div id="transferConfirmIconWrap" class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0Z" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 id="transferConfirmTitle" class="text-sm font-bold text-slate-800">Konfirmasi Aksi</h3>
                    <p id="transferConfirmMessage" class="text-xs text-slate-600 mt-1 leading-relaxed"></p>
                </div>
            </div>
        </div>
        <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2">
            <button type="button" id="transferConfirmCancel"
                class="px-3.5 py-2 text-xs font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-100 rounded-xl transition">
                Batal
            </button>
            <button type="button" id="transferConfirmOk"
                class="px-4 py-2 text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition shadow-sm">
                Ya, Lanjutkan
            </button>
        </div>
    </div>
</div>

{{-- Modal Kelola Etalase Cabang (Tambah, Pencarian, Ubah Inline, Hapus Inline + Sesi PIN) --}}
<div id="manageEtalaseModal" class="hidden fixed inset-0 z-[999900] items-center justify-center bg-slate-900/45 backdrop-blur-[1px] p-4" aria-modal="true" role="dialog">
    <div class="bg-white rounded-2xl w-full max-w-xl shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="text-sm font-bold text-slate-800">Kelola Etalase Cabang</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">Tambah, ubah, atau hapus etalase secara cepat. Setiap perubahan dicatat atas pemilik PIN.</p>
                <div class="mt-2.5 flex flex-wrap items-center gap-2">
                    <span id="manageEtalasePinBadge" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                        Belum verifikasi PIN
                    </span>
                    <button type="button" id="manageEtalaseSwitchPinBtn"
                        class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-700 underline underline-offset-2">
                        Verifikasi PIN
                    </button>
                </div>
            </div>
            <button type="button" onclick="window.closeManageEtalaseModal()" aria-label="Tutup"
                class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-slate-800 shrink-0">×</button>
        </div>

        <div class="p-5 space-y-3">
            <form id="manageEtalaseAddForm" class="flex items-center gap-2">
                <input type="text" id="manageEtalaseNewName" placeholder="Nama etalase baru (mis. Rak Depan A1)"
                    class="flex-1 px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl outline-none focus:border-indigo-500 transition">
                <button type="submit"
                    class="px-3.5 py-2 text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl transition shadow-sm whitespace-nowrap">
                    + Tambah
                </button>
            </form>

            <div class="relative">
                <svg class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <circle cx="11" cy="11" r="7" />
                    <path stroke-linecap="round" d="m20 20-3.5-3.5" />
                </svg>
                <input type="search" id="manageEtalaseSearch" placeholder="Cari etalase..."
                    class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:border-indigo-500 transition">
            </div>

            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <div id="manageEtalaseList" class="max-h-80 overflow-y-auto divide-y divide-slate-100 bg-white">
                    <div class="p-6 text-center text-xs text-slate-400">Memuat daftar etalase...</div>
                </div>
            </div>
        </div>

        <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/50 flex justify-end">
            <button type="button" onclick="window.closeManageEtalaseModal()"
                class="px-4 py-2 text-xs font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-100 rounded-xl transition">
                Tutup
            </button>
        </div>
    </div>
</div>

{{-- Modal PIN Pribadi untuk Mutasi & Perubahan Etalase.
     Pakai: const res = await window.requestTransferPin({ title, subtitle, confirmMessage });
     res = { pin, user: { id, name } } atau null jika dibatalkan. --}}
<div id="transferPinModal" class="hidden fixed inset-0 z-[999999] items-center justify-center bg-slate-900/45 backdrop-blur-[1px]" aria-modal="true" role="dialog">
    <div id="transferPinCard" class="bg-white rounded-2xl p-6 w-full max-w-sm mx-4 shadow-2xl" style="border: 0.5px solid rgba(0,0,0,0.08);">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3" style="background:#EEF2FF;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
            </svg>
        </div>
        <h3 id="transferPinTitle" class="text-[15px] font-semibold text-gray-800 mb-1">Masukkan PIN Pribadi</h3>
        <p id="transferPinSubtitle" class="text-[12px] text-gray-400 mb-3">Masukkan 4 digit PIN Anda untuk melanjutkan mutasi.</p>

        <div id="transferPinSummary" class="hidden mb-4 px-3 py-2.5 rounded-xl bg-indigo-50/80 border border-indigo-100 text-[11px] text-indigo-900 leading-relaxed font-medium"></div>

        <div class="flex justify-center gap-3 mb-2 mt-2" id="transferPinDots">
            @for ($i = 0; $i < 4; $i++)
                <div class="transfer-pin-dot w-3 h-3 rounded-full border-2 border-gray-300 transition-all duration-150"></div>
            @endfor
        </div>

        <p id="transferPinError" class="hidden text-center text-[11px] text-red-500 mb-3">PIN salah. Coba lagi.</p>
        <p id="transferPinLoading" class="hidden text-center text-[11px] text-indigo-500 mb-3">Memverifikasi PIN...</p>

        <div class="grid grid-cols-3 gap-2 mb-4 mt-3" id="transferPinNumpad">
            @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $key)
                <button type="button" data-transfer-pin-key="{{ $key }}"
                    class="h-12 rounded-xl border border-gray-100 bg-white text-[15px] font-medium text-gray-700 hover:bg-gray-50 active:scale-95 transition-all duration-100">{{ $key }}</button>
            @endforeach
            <div></div>
            <button type="button" data-transfer-pin-key="0"
                class="h-12 rounded-xl border border-gray-100 bg-white text-[15px] font-medium text-gray-700 hover:bg-gray-50 active:scale-95 transition-all duration-100">0</button>
            <button type="button" data-transfer-pin-key="backspace" aria-label="Hapus"
                class="h-12 rounded-xl border border-gray-100 bg-white flex items-center justify-center text-gray-400 hover:bg-gray-50 active:scale-95 transition-all duration-100">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z" />
                    <line x1="18" y1="9" x2="12" y2="15" />
                    <line x1="12" y1="9" x2="18" y2="15" />
                </svg>
            </button>
        </div>

        <button type="button" id="transferPinCancel"
            class="w-full px-3 py-2.5 rounded-xl border border-gray-100 bg-white hover:bg-gray-50 text-[13px] font-medium text-gray-600 transition-colors">
            Batal
        </button>
    </div>
</div>

<script>
    (function () {
        if (window.requestTransferPin) return;

        const VERIFY_URL = @json(route('transfers.verifypin'));
        const ETALASE_INDEX_URL = @json(route('etalases.index'));
        const ETALASE_STORE_URL = @json(route('etalases.store'));
        const ETALASE_UPDATE_TPL = @json(route('etalases.update', ['etalase' => '__ID__']));
        const ETALASE_DESTROY_TPL = @json(route('etalases.destroy', ['etalase' => '__ID__']));
        const ITEM_ETALASE_TPL = @json(route('transfers.updateItemEtalase', ['item' => '__ID__']));
        const PIN_SESSION_TTL_MS = 5 * 60 * 1000;

        const modal = document.getElementById('transferPinModal');
        const card = document.getElementById('transferPinCard');
        const dots = modal.querySelectorAll('.transfer-pin-dot');
        const errorEl = document.getElementById('transferPinError');
        const loadingEl = document.getElementById('transferPinLoading');
        const numpad = document.getElementById('transferPinNumpad');
        const titleEl = document.getElementById('transferPinTitle');
        const subtitleEl = document.getElementById('transferPinSubtitle');
        const summaryEl = document.getElementById('transferPinSummary');

        const confirmModal = document.getElementById('transferConfirmModal');
        const confirmTitleEl = document.getElementById('transferConfirmTitle');
        const confirmMsgEl = document.getElementById('transferConfirmMessage');
        const confirmOkBtn = document.getElementById('transferConfirmOk');
        const confirmCancelBtn = document.getElementById('transferConfirmCancel');
        const confirmIconWrap = document.getElementById('transferConfirmIconWrap');

        const manageModal = document.getElementById('manageEtalaseModal');
        const manageList = document.getElementById('manageEtalaseList');
        const manageAddForm = document.getElementById('manageEtalaseAddForm');
        const manageNewNameInput = document.getElementById('manageEtalaseNewName');
        const manageSearchInput = document.getElementById('manageEtalaseSearch');
        const managePinBadge = document.getElementById('manageEtalasePinBadge');
        const manageSwitchPinBtn = document.getElementById('manageEtalaseSwitchPinBtn');

        const etalasePopover = document.getElementById('etalaseSearchPopover');
        const etalasePopoverInput = document.getElementById('etalasePopoverSearchInput');
        const etalasePopoverOptions = document.getElementById('etalasePopoverOptions');
        const etalasePopoverManageBtn = document.getElementById('etalasePopoverManageBtn');

        let value = '';
        let busy = false;
        let resolver = null;
        let confirmResolver = null;
        let etalasePinSession = null;
        let cachedEtalases = [];
        let activeTargetSelect = null;
        let popoverSelect = null;
        let popoverTrigger = null;
        let popoverActiveIdx = -1;

        function csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token());
        }

        // ── Toast & Validasi Visual ─────────────────────────────────────
        window.notifyTransfer = function (type, message, title) {
            const safeType = ['success', 'warning', 'error', 'info'].includes(type) ? type : 'info';
            const defaultTitles = { success: 'Berhasil', warning: 'Perhatian', error: 'Gagal', info: 'Info' };
            const heading = title || defaultTitles[safeType];

            if (window.iziToast && typeof window.iziToast[safeType] === 'function') {
                window.iziToast[safeType]({
                    title: heading,
                    message: message || '',
                    position: 'topRight',
                    timeout: 3200,
                });
                return;
            }

            const wrap = document.getElementById('transferToastFallback');
            if (!wrap) return;
            const colors = {
                success: 'bg-emerald-600 text-white border-emerald-700',
                warning: 'bg-amber-500 text-white border-amber-600',
                error: 'bg-rose-600 text-white border-rose-700',
                info: 'bg-slate-800 text-white border-slate-900',
            };
            const el = document.createElement('div');
            el.className = `pointer-events-auto px-4 py-3 rounded-xl border shadow-lg text-xs transition-all duration-200 ${colors[safeType]}`;
            const strong = document.createElement('div');
            strong.className = 'font-bold';
            strong.textContent = heading;
            const body = document.createElement('div');
            body.className = 'mt-0.5 opacity-95';
            body.textContent = message || '';
            el.append(strong, body);
            wrap.appendChild(el);
            setTimeout(() => {
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 200);
            }, 3200);
        };

        window.highlightInvalidField = function (el, message) {
            if (message) {
                window.notifyTransfer('warning', message);
            }
            const target = el?._comboboxTrigger || el;
            if (!target) return;
            target.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
            target.focus?.();
            target.classList.add('!border-rose-500', 'ring-2', 'ring-rose-500/20');
            target.animate?.([
                { transform: 'translateX(0)' }, { transform: 'translateX(-5px)' },
                { transform: 'translateX(5px)' }, { transform: 'translateX(0)' }
            ], { duration: 260 });

            const clear = () => {
                target.classList.remove('!border-rose-500', 'ring-2', 'ring-rose-500/20');
                target.removeEventListener('change', clear);
                target.removeEventListener('input', clear);
                el?.removeEventListener?.('change', clear);
            };
            target.addEventListener('change', clear, { once: true });
            target.addEventListener('input', clear, { once: true });
            el?.addEventListener?.('change', clear, { once: true });
            setTimeout(clear, 2600);
        };

        // ── Modal Konfirmasi Modern ─────────────────────────────────────
        function closeConfirmModal(accepted) {
            confirmModal.classList.add('hidden');
            confirmModal.classList.remove('flex');
            document.removeEventListener('keydown', onConfirmKeydown, true);
            const r = confirmResolver;
            confirmResolver = null;
            if (r) r(Boolean(accepted));
        }

        function onConfirmKeydown(e) {
            if (confirmModal.classList.contains('hidden')) return;
            if (e.key === 'Escape') { e.preventDefault(); closeConfirmModal(false); }
            else if (e.key === 'Enter') { e.preventDefault(); closeConfirmModal(true); }
        }

        confirmOkBtn.addEventListener('click', () => closeConfirmModal(true));
        confirmCancelBtn.addEventListener('click', () => closeConfirmModal(false));
        confirmModal.addEventListener('click', (e) => { if (e.target === confirmModal) closeConfirmModal(false); });

        window.confirmTransferAction = function (opts = {}) {
            if (confirmResolver) closeConfirmModal(false);
            const message = typeof opts === 'string' ? opts : (opts.message || 'Apakah Anda yakin ingin melanjutkan?');
            const title = (typeof opts === 'object' && opts.title) ? opts.title : 'Konfirmasi Aksi';
            const okText = (typeof opts === 'object' && opts.confirmText) ? opts.confirmText : 'Ya, Lanjutkan';
            const cancelText = (typeof opts === 'object' && opts.cancelText) ? opts.cancelText : 'Batal';
            const variant = (typeof opts === 'object' && opts.variant) ? opts.variant : 'danger';

            confirmTitleEl.textContent = title;
            confirmMsgEl.textContent = message;
            confirmOkBtn.textContent = okText;
            confirmCancelBtn.textContent = cancelText;

            if (variant === 'primary') {
                confirmOkBtn.className = 'px-4 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition shadow-sm';
                confirmIconWrap.className = 'w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0';
            } else {
                confirmOkBtn.className = 'px-4 py-2 text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition shadow-sm';
                confirmIconWrap.className = 'w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0';
            }

            if (document.activeElement) document.activeElement.blur();
            confirmModal.classList.remove('hidden');
            confirmModal.classList.add('flex');
            document.addEventListener('keydown', onConfirmKeydown, true);
            setTimeout(() => confirmOkBtn.focus(), 30);
            return new Promise((resolve) => { confirmResolver = resolve; });
        };

        // ── Modal PIN Pribadi ───────────────────────────────────────────
        function renderDots() {
            dots.forEach((dot, i) => {
                const filled = i < value.length;
                dot.classList.toggle('bg-indigo-500', filled);
                dot.classList.toggle('border-indigo-500', filled);
                dot.classList.toggle('border-gray-300', !filled);
            });
        }

        function showError(msg) {
            errorEl.textContent = msg || 'PIN salah. Coba lagi.';
            errorEl.classList.remove('hidden');
            card.animate?.([
                { transform: 'translateX(0)' }, { transform: 'translateX(-8px)' },
                { transform: 'translateX(8px)' }, { transform: 'translateX(0)' }
            ], { duration: 300 });
            value = '';
            renderDots();
        }

        function close(result) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.removeEventListener('keydown', onKeydown, true);
            const r = resolver;
            resolver = null;
            value = '';
            busy = false;
            if (r) r(result);
        }

        async function verify() {
            busy = true;
            loadingEl.classList.remove('hidden');
            numpad.style.pointerEvents = 'none';
            try {
                const res = await fetch(VERIFY_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf(),
                    },
                    body: JSON.stringify({ pin: value }),
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    close({ pin: value, user: data.user || null });
                    return;
                }
                showError(data.message || 'PIN salah. Coba lagi.');
            } catch (e) {
                showError('Gagal memverifikasi PIN. Periksa koneksi.');
            } finally {
                busy = false;
                loadingEl.classList.add('hidden');
                numpad.style.pointerEvents = '';
            }
        }

        function handleKey(key) {
            if (busy) return;
            errorEl.classList.add('hidden');
            if (key === 'backspace') {
                value = value.slice(0, -1);
                renderDots();
                return;
            }
            if (value.length >= 4) return;
            value += key;
            renderDots();
            if (value.length === 4) setTimeout(verify, 120);
        }

        function onKeydown(e) {
            if (modal.classList.contains('hidden')) return;
            if (/^\d$/.test(e.key)) { e.preventDefault(); handleKey(e.key); }
            else if (e.key === 'Backspace') { e.preventDefault(); handleKey('backspace'); }
            else if (e.key === 'Escape') { e.preventDefault(); close(null); }
        }

        numpad.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-transfer-pin-key]');
            if (btn) handleKey(btn.dataset.transferPinKey);
        });
        document.getElementById('transferPinCancel').addEventListener('click', () => close(null));
        modal.addEventListener('click', (e) => { if (e.target === modal) close(null); });

        window.requestTransferPin = function (opts = {}) {
            if (resolver) close(null);
            titleEl.textContent = opts.title || 'Masukkan PIN Pribadi';
            subtitleEl.textContent = opts.subtitle || 'Masukkan 4 digit PIN Anda untuk melanjutkan mutasi.';
            if (opts.confirmMessage) {
                summaryEl.textContent = opts.confirmMessage;
                summaryEl.classList.remove('hidden');
            } else {
                summaryEl.textContent = '';
                summaryEl.classList.add('hidden');
            }
            value = '';
            busy = false;
            errorEl.classList.add('hidden');
            loadingEl.classList.add('hidden');
            renderDots();
            if (document.activeElement) document.activeElement.blur();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.addEventListener('keydown', onKeydown, true);
            return new Promise((resolve) => { resolver = resolve; });
        };

        // ── Sesi PIN untuk Kelola Etalase ───────────────────────────────
        function updatePinSessionBadge() {
            const active = etalasePinSession && etalasePinSession.expiresAt > Date.now();
            if (!active) {
                etalasePinSession = null;
                managePinBadge.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200';
                managePinBadge.textContent = 'Belum verifikasi PIN';
                manageSwitchPinBtn.textContent = 'Verifikasi PIN';
                return;
            }
            managePinBadge.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200';
            managePinBadge.textContent = `Dicatat atas nama: ${etalasePinSession.user?.name || 'User'}`;
            manageSwitchPinBtn.textContent = 'Ganti PIN';
        }

        async function ensureEtalasePin(title, subtitle, forceNew = false) {
            if (!forceNew && etalasePinSession && etalasePinSession.expiresAt > Date.now()) {
                return etalasePinSession;
            }
            const res = await window.requestTransferPin({ title, subtitle });
            if (!res) return null;
            etalasePinSession = {
                pin: res.pin,
                user: res.user || null,
                expiresAt: Date.now() + PIN_SESSION_TTL_MS,
            };
            updatePinSessionBadge();
            return etalasePinSession;
        }

        manageSwitchPinBtn?.addEventListener('click', async () => {
            await ensureEtalasePin('Ganti User PIN Etalase', 'Masukkan 4 digit PIN pribadi untuk mencatat perubahan etalase.', true);
        });

        async function apiJson(url, method, body) {
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf(),
                },
                body: body ? JSON.stringify(body) : undefined,
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                if (res.status === 422 && (data.message || '').toLowerCase().includes('pin')) {
                    etalasePinSession = null;
                    updatePinSessionBadge();
                }
                const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Permintaan gagal.');
                throw new Error(msg);
            }
            return data;
        }

        function resolveSelectValue(sel, rawValue) {
            if (String(rawValue) === 'default' && sel?.classList.contains('item-etalase-select')) {
                const resolvedDefault = String(sel.dataset.defaultEtalaseId || '').trim();
                if (resolvedDefault) {
                    return resolvedDefault;
                }
            }
            return String(rawValue);
        }

        function applyEtalaseToSelect(sel, etalaseId) {
            if (!sel) return;
            sel.value = resolveSelectValue(sel, etalaseId);
            window.syncEtalaseTriggerLabel(sel);
            if (sel.classList.contains('transfer-bulk-etalase-select')) {
                sel.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }
            if (sel.classList.contains('item-etalase-select') && String(sel.dataset.itemStatus) === '1') {
                window.saveAcceptedItemEtalase(sel);
            }
        }

        // ── Searchable Combobox Popover untuk Etalase ───────────────────
        window.syncEtalaseTriggerLabel = function (select) {
            const btn = select?._comboboxTrigger;
            if (!btn) return;
            const textEl = btn.querySelector('.etalase-combobox-text');
            const selectedOpt = select.options[select.selectedIndex];
            const hasValue = Boolean(select.value);
            const nextText = selectedOpt?.textContent || '— Pilih Etalase —';
            const nextClass = `etalase-combobox-text truncate ${hasValue ? 'text-slate-800 font-medium' : 'text-slate-500'}`;
            if (textEl) {
                if (textEl.textContent !== nextText) textEl.textContent = nextText;
                if (textEl.className !== nextClass) textEl.className = nextClass;
            }
            btn.disabled = Boolean(select.disabled);
            btn.classList.toggle('opacity-60', Boolean(select.disabled));
            btn.classList.toggle('cursor-not-allowed', Boolean(select.disabled));
        };

        function closeEtalasePopover(returnFocus = false) {
            if (!etalasePopover || etalasePopover.classList.contains('hidden')) return;
            etalasePopover.classList.add('hidden');
            const prevTrigger = popoverTrigger;
            popoverSelect = null;
            popoverTrigger = null;
            popoverActiveIdx = -1;
            if (returnFocus && prevTrigger) {
                prevTrigger.focus();
            }
        }

        function positionEtalasePopover() {
            if (!etalasePopover || !popoverTrigger) return;
            const rect = popoverTrigger.getBoundingClientRect();
            const width = Math.max(256, rect.width);
            etalasePopover.style.width = `${width}px`;

            const popHeight = etalasePopover.offsetHeight || 290;
            const spaceBelow = window.innerHeight - rect.bottom;
            const openUp = spaceBelow < popHeight + 12 && rect.top > popHeight + 12;

            const top = openUp ? Math.max(8, rect.top - popHeight - 6) : Math.min(window.innerHeight - popHeight - 8, rect.bottom + 6);
            const left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));

            etalasePopover.style.top = `${top}px`;
            etalasePopover.style.left = `${left}px`;
        }

        function highlightPopoverOption(idx) {
            const btns = [...etalasePopoverOptions.querySelectorAll('.etalase-popover-option')];
            if (!btns.length) {
                popoverActiveIdx = -1;
                return;
            }
            popoverActiveIdx = Math.max(0, Math.min(idx, btns.length - 1));
            btns.forEach((b, i) => {
                b.classList.toggle('bg-indigo-50', i === popoverActiveIdx);
                b.classList.toggle('text-indigo-800', i === popoverActiveIdx);
            });
            btns[popoverActiveIdx].scrollIntoView({ block: 'nearest' });
        }

        function renderPopoverOptions() {
            if (!popoverSelect || !etalasePopoverOptions) return;
            const rawQ = (etalasePopoverInput?.value || '').trim();
            const q = rawQ.toLowerCase();
            const currentVal = String(popoverSelect.value || '');
            const allOpts = [...popoverSelect.options];

            const filtered = allOpts.filter((opt) => {
                if (q === '') return true;
                if (opt.value === '') return false;
                return opt.textContent.toLowerCase().includes(q);
            });

            etalasePopoverOptions.replaceChildren();
            popoverActiveIdx = -1;

            filtered.forEach((opt) => {
                const isSelected = String(opt.value) === currentVal;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `etalase-popover-option w-full px-3 py-2 text-left text-xs flex items-center justify-between gap-2 hover:bg-indigo-50 transition-colors ${isSelected ? 'bg-indigo-50/70 text-indigo-700 font-semibold' : (opt.value === '' ? 'text-slate-400 italic' : (opt.value === 'default' ? 'text-emerald-700 font-semibold bg-emerald-50/40' : 'text-slate-700'))}`;

                const label = document.createElement('span');
                label.className = 'truncate';
                label.textContent = opt.textContent;
                btn.appendChild(label);

                if (isSelected) {
                    const check = document.createElement('span');
                    check.className = 'text-indigo-600 font-bold shrink-0';
                    check.textContent = '✓';
                    btn.appendChild(check);
                }

                btn.onclick = () => {
                    const targetSel = popoverSelect;
                    closeEtalasePopover(true);
                    applyEtalaseToSelect(targetSel, opt.value);
                };
                etalasePopoverOptions.appendChild(btn);
            });

            const exactMatch = q !== '' && allOpts.some((o) => o.value !== '' && o.textContent.trim().toLowerCase() === q);
            if (rawQ !== '' && !exactMatch) {
                const createBtn = document.createElement('button');
                createBtn.type = 'button';
                createBtn.className = 'etalase-popover-option w-full px-3 py-2 text-left text-xs font-semibold text-emerald-700 bg-emerald-50/60 hover:bg-emerald-100/80 flex items-center gap-1.5 transition-colors';
                const plus = document.createElement('span');
                plus.textContent = '+';
                const txt = document.createElement('span');
                txt.className = 'truncate';
                txt.textContent = `Buat Etalase "${rawQ}"`;
                createBtn.append(plus, txt);

                createBtn.onclick = async () => {
                    const targetSel = popoverSelect;
                    closeEtalasePopover();
                    const session = await ensureEtalasePin('PIN Tambah Etalase', `Masukkan PIN pribadi Anda untuk menambah etalase "${rawQ}".`);
                    if (!session) return;
                    try {
                        const created = await apiJson(ETALASE_STORE_URL, 'POST', { name: rawQ, pin: session.pin });
                        window.notifyTransfer('success', `Etalase "${created.name}" ditambahkan & dipilih.`);
                        await window.refreshAllEtalaseSelects({ targetSelect: targetSel, selectId: created.id });
                        applyEtalaseToSelect(targetSel, created.id);
                    } catch (err) {
                        window.notifyTransfer('error', err.message);
                    }
                };
                etalasePopoverOptions.appendChild(createBtn);
            } else if (filtered.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'px-3 py-4 text-center text-xs text-slate-400';
                empty.textContent = 'Tidak ada etalase ditemukan.';
                etalasePopoverOptions.appendChild(empty);
            }

            positionEtalasePopover();
        }

        function openEtalasePopover(select, triggerBtn) {
            if (!etalasePopover || select.disabled) return;
            if (popoverSelect === select && !etalasePopover.classList.contains('hidden')) {
                closeEtalasePopover();
                return;
            }
            popoverSelect = select;
            popoverTrigger = triggerBtn;
            if (etalasePopoverInput) etalasePopoverInput.value = '';
            etalasePopover.classList.remove('hidden');
            renderPopoverOptions();
            setTimeout(() => etalasePopoverInput?.focus(), 10);
        }

        etalasePopoverInput?.addEventListener('input', renderPopoverOptions);
        etalasePopoverInput?.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightPopoverOption(popoverActiveIdx < 0 ? 0 : popoverActiveIdx + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const count = etalasePopoverOptions.querySelectorAll('.etalase-popover-option').length;
                highlightPopoverOption(popoverActiveIdx <= 0 ? count - 1 : popoverActiveIdx - 1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const btns = [...etalasePopoverOptions.querySelectorAll('.etalase-popover-option')];
                const chosen = btns[popoverActiveIdx >= 0 ? popoverActiveIdx : 0];
                chosen?.click();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                closeEtalasePopover(true);
            }
        });

        etalasePopoverManageBtn?.addEventListener('click', () => {
            const targetSel = popoverSelect;
            closeEtalasePopover();
            window.openManageEtalaseModal(targetSel);
        });

        document.addEventListener('click', (e) => {
            if (!etalasePopover || etalasePopover.classList.contains('hidden')) return;
            if (etalasePopover.contains(e.target) || e.target.closest?.('.etalase-combobox-trigger')) return;
            closeEtalasePopover();
        });
        window.addEventListener('resize', () => closeEtalasePopover());
        window.addEventListener('scroll', (e) => {
            if (!etalasePopover || etalasePopover.classList.contains('hidden')) return;
            if (e.target instanceof Node && etalasePopover.contains(e.target)) return;
            closeEtalasePopover();
        }, true);

        window.initEtalaseSearchableSelects = function (root = document) {
            if (!root || !root.querySelectorAll) return;
            root.querySelectorAll('.transfer-bulk-etalase-select, .item-etalase-select').forEach((sel) => {
                if (sel._comboboxTrigger) {
                    window.syncEtalaseTriggerLabel(sel);
                    return;
                }
                const isBulk = sel.classList.contains('transfer-bulk-etalase-select');
                sel.classList.add('sr-only');
                sel.tabIndex = -1;
                sel.setAttribute('aria-hidden', 'true');

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = isBulk
                    ? 'etalase-combobox-trigger inline-flex items-center justify-between gap-2 text-xs bg-white border border-slate-200 hover:border-slate-300 rounded-lg px-2.5 py-1.5 w-[170px] text-left outline-none focus:border-emerald-500 transition'
                    : 'etalase-combobox-trigger inline-flex items-center justify-between gap-2 text-xs bg-white border border-slate-200 hover:border-slate-300 rounded-lg px-2.5 py-1 w-[180px] text-left outline-none focus:border-emerald-500 transition';
                btn.title = sel.title || 'Cari & pilih etalase';

                const text = document.createElement('span');
                text.className = 'etalase-combobox-text truncate text-slate-500';

                const arrow = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                arrow.setAttribute('class', 'w-3.5 h-3.5 text-slate-400 shrink-0');
                arrow.setAttribute('fill', 'none');
                arrow.setAttribute('viewBox', '0 0 24 24');
                arrow.setAttribute('stroke', 'currentColor');
                arrow.setAttribute('stroke-width', '2');
                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('stroke-linecap', 'round');
                path.setAttribute('stroke-linejoin', 'round');
                path.setAttribute('d', 'M19 9l-7 7-7-7');
                arrow.appendChild(path);

                btn.append(text, arrow);
                sel.insertAdjacentElement('afterend', btn);
                sel._comboboxTrigger = btn;
                btn._sourceSelect = sel;

                btn.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    ev.stopPropagation();
                    openEtalasePopover(sel, btn);
                });

                window.syncEtalaseTriggerLabel(sel);
            });
        };

        function renderManageEtalaseList(list) {
            if (!manageList) return;
            const q = (manageSearchInput?.value || '').trim().toLowerCase();
            const filtered = q
                ? (list || []).filter((et) => String(et.name || '').toLowerCase().includes(q))
                : (list || []);

            manageList.replaceChildren();
            if (!Array.isArray(filtered) || filtered.length === 0) {
                manageList.innerHTML = `<div class="p-6 text-center text-xs text-slate-400">${q ? 'Tidak ada etalase yang cocok dengan pencarian.' : 'Belum ada etalase untuk cabang ini.'}</div>`;
                return;
            }

            filtered.forEach((et) => {
                const row = document.createElement('div');
                row.className = 'p-3 flex items-center justify-between gap-2 hover:bg-slate-50/70 transition-colors';

                function showNormalMode() {
                    row.replaceChildren();
                    const left = document.createElement('div');
                    left.className = 'min-w-0 flex-1';

                    const title = document.createElement('div');
                    title.className = 'text-xs font-semibold text-slate-800 truncate';
                    title.textContent = et.name || '';

                    const audit = document.createElement('div');
                    audit.className = 'text-[10px] text-slate-400 mt-0.5';
                    audit.textContent = et.updated_by_name
                        ? `Diubah oleh: ${et.updated_by_name}${et.updated_at_formatted ? ` · ${et.updated_at_formatted}` : ''}`
                        : 'Belum ada catatan perubahan';

                    left.append(title, audit);

                    const actions = document.createElement('div');
                    actions.className = 'flex items-center gap-1 shrink-0';

                    if (activeTargetSelect) {
                        const pickBtn = document.createElement('button');
                        pickBtn.type = 'button';
                        pickBtn.textContent = 'Pilih';
                        pickBtn.className = 'px-2.5 py-1.5 text-[11px] font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition shadow-xs';
                        pickBtn.onclick = () => {
                            const sel = activeTargetSelect;
                            window.closeManageEtalaseModal();
                            applyEtalaseToSelect(sel, et.id);
                        };
                        actions.appendChild(pickBtn);
                    }

                    const editBtn = document.createElement('button');
                    editBtn.type = 'button';
                    editBtn.title = 'Ubah nama';
                    editBtn.className = 'px-2.5 py-1.5 text-[11px] font-semibold bg-white border border-slate-200 hover:bg-amber-50 hover:border-amber-200 hover:text-amber-700 text-slate-600 rounded-lg transition';
                    editBtn.textContent = 'Ubah';
                    editBtn.onclick = showEditMode;

                    const delBtn = document.createElement('button');
                    delBtn.type = 'button';
                    delBtn.title = 'Hapus etalase';
                    delBtn.className = 'px-2.5 py-1.5 text-[11px] font-semibold bg-white border border-slate-200 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-700 text-slate-600 rounded-lg transition';
                    delBtn.textContent = 'Hapus';
                    delBtn.onclick = showDeleteConfirmMode;

                    actions.append(editBtn, delBtn);
                    row.append(left, actions);
                }

                function showEditMode() {
                    row.replaceChildren();
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.value = et.name || '';
                    input.className = 'flex-1 px-2.5 py-1.5 text-xs bg-white border border-indigo-300 rounded-lg outline-none ring-2 ring-indigo-500/15';

                    const actions = document.createElement('div');
                    actions.className = 'flex items-center gap-1 shrink-0';

                    const saveBtn = document.createElement('button');
                    saveBtn.type = 'button';
                    saveBtn.textContent = 'Simpan';
                    saveBtn.className = 'px-2.5 py-1.5 text-[11px] font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition';

                    const cancelBtn = document.createElement('button');
                    cancelBtn.type = 'button';
                    cancelBtn.textContent = 'Batal';
                    cancelBtn.className = 'px-2.5 py-1.5 text-[11px] font-semibold bg-white border border-slate-200 hover:bg-slate-100 text-slate-600 rounded-lg transition';
                    cancelBtn.onclick = showNormalMode;

                    const submitRename = async () => {
                        const newName = input.value.trim();
                        if (!newName) {
                            window.highlightInvalidField(input, 'Nama etalase tidak boleh kosong.');
                            return;
                        }
                        if (newName === et.name) {
                            showNormalMode();
                            return;
                        }
                        const session = await ensureEtalasePin('PIN Ubah Etalase', `Masukkan PIN pribadi Anda untuk mengubah etalase "${et.name}".`);
                        if (!session) return;
                        saveBtn.disabled = true;
                        input.disabled = true;
                        try {
                            await apiJson(ETALASE_UPDATE_TPL.replace('__ID__', et.id), 'PUT', { name: newName, pin: session.pin });
                            window.notifyTransfer('success', `Etalase diubah menjadi "${newName}".`);
                            await window.refreshAllEtalaseSelects();
                        } catch (err) {
                            window.notifyTransfer('error', err.message);
                            saveBtn.disabled = false;
                            input.disabled = false;
                        }
                    };

                    saveBtn.onclick = submitRename;
                    input.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') { e.preventDefault(); submitRename(); }
                        else if (e.key === 'Escape') { e.preventDefault(); showNormalMode(); }
                    });

                    actions.append(saveBtn, cancelBtn);
                    row.append(input, actions);
                    setTimeout(() => { input.focus(); input.select(); }, 10);
                }

                function showDeleteConfirmMode() {
                    row.replaceChildren();
                    const msg = document.createElement('div');
                    msg.className = 'text-xs text-rose-700 font-medium flex-1 min-w-0';
                    msg.textContent = `Hapus etalase "${et.name}"? Item mutasi terkait akan dikosongkan.`;

                    const actions = document.createElement('div');
                    actions.className = 'flex items-center gap-1.5 shrink-0';

                    const yesBtn = document.createElement('button');
                    yesBtn.type = 'button';
                    yesBtn.textContent = 'Ya, Hapus';
                    yesBtn.className = 'px-2.5 py-1.5 text-[11px] font-semibold bg-rose-600 hover:bg-rose-700 text-white rounded-lg transition';
                    yesBtn.onclick = async () => {
                        const session = await ensureEtalasePin('PIN Hapus Etalase', `Masukkan PIN pribadi Anda untuk menghapus etalase "${et.name}".`);
                        if (!session) return;
                        yesBtn.disabled = true;
                        try {
                            await apiJson(ETALASE_DESTROY_TPL.replace('__ID__', et.id), 'DELETE', { pin: session.pin });
                            window.notifyTransfer('success', `Etalase "${et.name}" dihapus.`);
                            await window.refreshAllEtalaseSelects({ deletedId: et.id });
                        } catch (err) {
                            window.notifyTransfer('error', err.message);
                            yesBtn.disabled = false;
                        }
                    };

                    const noBtn = document.createElement('button');
                    noBtn.type = 'button';
                    noBtn.textContent = 'Batal';
                    noBtn.className = 'px-2.5 py-1.5 text-[11px] font-semibold bg-white border border-slate-200 hover:bg-slate-100 text-slate-600 rounded-lg transition';
                    noBtn.onclick = showNormalMode;

                    actions.append(yesBtn, noBtn);
                    row.append(msg, actions);
                }

                showNormalMode();
                manageList.appendChild(row);
            });
        }

        window.refreshAllEtalaseSelects = async function (opts = {}) {
            const list = await apiJson(ETALASE_INDEX_URL, 'GET');
            cachedEtalases = Array.isArray(list) ? list : [];
            const deletedId = opts.deletedId ? String(opts.deletedId) : null;

            document.querySelectorAll('.transfer-bulk-etalase-select, .item-etalase-select').forEach((sel) => {
                const isBulk = sel.classList.contains('transfer-bulk-etalase-select');
                const prevVal = opts.targetSelect === sel && opts.selectId
                    ? String(opts.selectId)
                    : (deletedId && String(sel.value) === deletedId ? '' : String(sel.value || ''));

                const placeholder = sel.querySelector('option[value=""]')?.textContent || (isBulk ? '— Pilih Etalase —' : '— Tanpa / Pilih Etalase —');
                const prevDefaultOpt = sel.querySelector('option[value="default"]');
                const prevDefaultText = prevDefaultOpt?.textContent || '';
                sel.replaceChildren();

                const emptyOpt = document.createElement('option');
                emptyOpt.value = '';
                emptyOpt.textContent = placeholder;
                sel.appendChild(emptyOpt);

                if ((isBulk && cachedEtalases.length > 0) || (!isBulk && prevDefaultOpt)) {
                    const defaultOpt = document.createElement('option');
                    defaultOpt.value = 'default';
                    defaultOpt.textContent = isBulk ? 'Default (Riwayat / Acak)' : (prevDefaultText || 'Default (Riwayat / Acak)');
                    sel.appendChild(defaultOpt);
                }

                cachedEtalases.forEach((et) => {
                    const opt = document.createElement('option');
                    opt.value = String(et.id);
                    opt.textContent = et.name;
                    sel.appendChild(opt);
                });

                if (!isBulk && cachedEtalases.length > 0) {
                    const defId = String(sel.dataset.defaultEtalaseId || '');
                    const defStillValid = defId && cachedEtalases.some((et) => String(et.id) === defId);
                    if (!defStillValid) {
                        sel.dataset.defaultEtalaseId = String(cachedEtalases[Math.floor(Math.random() * cachedEtalases.length)].id);
                        sel.dataset.defaultEtalaseSource = 'random';
                    }
                    const refreshedDefOpt = sel.querySelector('option[value="default"]');
                    const resolvedDef = cachedEtalases.find((et) => String(et.id) === String(sel.dataset.defaultEtalaseId));
                    if (refreshedDefOpt && resolvedDef) {
                        const prefix = sel.dataset.defaultEtalaseSource === 'previous' ? 'Sebelumnya' : 'Acak';
                        refreshedDefOpt.textContent = `Default (${prefix}: ${resolvedDef.name})`;
                    }
                }

                const exists = [...sel.options].some((o) => o.value === prevVal);
                sel.value = exists ? prevVal : '';
                if (sel.classList.contains('item-etalase-select') && deletedId && sel.dataset.currentEtalaseId === deletedId) {
                    sel.dataset.currentEtalaseId = '';
                }
                window.syncEtalaseTriggerLabel(sel);
            });

            window.initEtalaseSearchableSelects();
            if (manageModal && !manageModal.classList.contains('hidden')) {
                renderManageEtalaseList(cachedEtalases);
            }
            return cachedEtalases;
        };

        window.openManageEtalaseModal = async function (triggerEl = null) {
            if (!manageModal) return;
            activeTargetSelect = triggerEl instanceof HTMLSelectElement
                ? triggerEl
                : (triggerEl?.parentElement?.querySelector('select') || null);
            if (manageSearchInput) manageSearchInput.value = '';
            updatePinSessionBadge();

            manageModal.classList.remove('hidden');
            manageModal.classList.add('flex');
            manageList.innerHTML = '<div class="p-6 text-center text-xs text-slate-400">Memuat daftar etalase...</div>';
            try {
                const list = await apiJson(ETALASE_INDEX_URL, 'GET');
                cachedEtalases = Array.isArray(list) ? list : [];
                renderManageEtalaseList(cachedEtalases);
                setTimeout(() => manageNewNameInput?.focus(), 40);
            } catch (err) {
                manageList.innerHTML = `<div class="p-6 text-center text-xs text-rose-500">${err.message}</div>`;
            }
        };

        window.closeManageEtalaseModal = function () {
            if (!manageModal) return;
            manageModal.classList.add('hidden');
            manageModal.classList.remove('flex');
            activeTargetSelect = null;
        };

        manageModal?.addEventListener('click', (e) => {
            if (e.target === manageModal) window.closeManageEtalaseModal();
        });

        manageSearchInput?.addEventListener('input', () => {
            renderManageEtalaseList(cachedEtalases);
        });

        manageAddForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = manageNewNameInput?.value.trim() || '';
            if (!name) {
                window.highlightInvalidField(manageNewNameInput, 'Nama etalase wajib diisi.');
                return;
            }
            const session = await ensureEtalasePin('PIN Tambah Etalase', `Masukkan PIN pribadi Anda untuk menambah etalase "${name}".`);
            if (!session) return;
            const btn = manageAddForm.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;
            try {
                const created = await apiJson(ETALASE_STORE_URL, 'POST', { name, pin: session.pin });
                manageNewNameInput.value = '';
                window.notifyTransfer('success', `Etalase "${created.name}" berhasil ditambahkan.`);
                const targetSel = activeTargetSelect;
                await window.refreshAllEtalaseSelects({ targetSelect: targetSel, selectId: created.id });
                if (targetSel) {
                    applyEtalaseToSelect(targetSel, created.id);
                }
            } catch (err) {
                window.notifyTransfer('error', err.message);
            } finally {
                if (btn) btn.disabled = false;
            }
        });

        // Simpan perubahan / pengosongan pilihan etalase pada item mutasi yang sudah diterima (wajib PIN)
        window.saveAcceptedItemEtalase = async function (triggerEl) {
            const wrapper = triggerEl?.closest('div');
            const select = triggerEl instanceof HTMLSelectElement
                ? triggerEl
                : wrapper?.querySelector('.item-etalase-select');
            if (!select) return;

            const itemId = select.dataset.itemId;
            const prevId = select.dataset.currentEtalaseId || '';
            const nextId = select.value || '';
            if (nextId === prevId) return;

            const nextLabel = nextId ? (select.options[select.selectedIndex]?.textContent || 'etalase terpilih') : 'Tanpa Etalase';
            const pinRes = await window.requestTransferPin({
                title: 'PIN Ubah Etalase Obat',
                subtitle: 'Masukkan 4 digit PIN Anda untuk menyimpan perubahan etalase.',
                confirmMessage: nextId
                    ? `Ubah etalase obat ini ke "${nextLabel}"?`
                    : 'Kosongkan pilihan etalase pada obat ini?',
            });
            if (!pinRes) {
                select.value = prevId;
                window.syncEtalaseTriggerLabel(select);
                return;
            }

            select.disabled = true;
            select.classList.add('opacity-60');
            window.syncEtalaseTriggerLabel(select);

            try {
                const res = await apiJson(ITEM_ETALASE_TPL.replace('__ID__', itemId), 'POST', {
                    etalases_id: nextId || null,
                    pin: pinRes.pin,
                });
                select.dataset.currentEtalaseId = res.etalases_id ? String(res.etalases_id) : '';
                select.value = select.dataset.currentEtalaseId;
                window.syncEtalaseTriggerLabel(select);

                const auditEl = document.querySelector(`.item-etalase-audit[data-item-id="${itemId}"]`);
                if (auditEl) {
                    auditEl.classList.remove('hidden');
                    auditEl.innerHTML = `Diubah oleh: <strong class="font-medium text-slate-600"></strong>${res.updated_at_formatted ? ` (${res.updated_at_formatted})` : ''}`;
                    auditEl.querySelector('strong').textContent = res.updated_by_name || 'User';
                }
                window.notifyTransfer('success', res.message || 'Etalase obat diperbarui.');
            } catch (err) {
                select.value = prevId;
                window.syncEtalaseTriggerLabel(select);
                window.notifyTransfer('error', err.message || 'Gagal memperbarui etalase obat.');
            } finally {
                select.disabled = false;
                select.classList.remove('opacity-60');
                window.syncEtalaseTriggerLabel(select);
            }
        };

        // Sinkronkan pilihan etalase header transfer ke seluruh dropdown item pending,
        // serta simpan otomatis saat user mengganti etalase pada item berstatus diterima (1).
        document.addEventListener('change', function (e) {
            const target = e.target;
            if (!(target instanceof HTMLSelectElement)) return;

            if (target.classList.contains('transfer-bulk-etalase-select')) {
                const transferId = target.dataset.transferId;
                window.syncEtalaseTriggerLabel(target);
                if (!transferId) return;
                document.querySelectorAll(`.item-etalase-select[data-transfer-id="${transferId}"]`).forEach((sel) => {
                    if (String(sel.dataset.itemStatus || '0') === '0') {
                        sel.value = target.value === 'default'
                            ? (sel.dataset.defaultEtalaseId || 'default')
                            : target.value;
                        window.syncEtalaseTriggerLabel(sel);
                    }
                });
                return;
            }

            if (target.classList.contains('item-etalase-select')) {
                if (target.value === 'default' && target.dataset.defaultEtalaseId) {
                    target.value = target.dataset.defaultEtalaseId;
                }
                window.syncEtalaseTriggerLabel(target);
                if (String(target.dataset.itemStatus) === '1' && e.isTrusted) {
                    window.saveAcceptedItemEtalase(target);
                }
            }
        });

        // Inisialisasi combobox awal + otomatis untuk tabel item yang dimuat via AJAX
        window.initEtalaseSearchableSelects();
        if (window.MutationObserver) {
            let initQueued = false;
            const observer = new MutationObserver((mutations) => {
                const hasNewSelect = mutations.some((m) => [...m.addedNodes].some((node) => {
                    if (!(node instanceof Element)) return false;
                    return node.matches?.('.transfer-bulk-etalase-select, .item-etalase-select')
                        || Boolean(node.querySelector?.('.transfer-bulk-etalase-select, .item-etalase-select'));
                }));
                if (!hasNewSelect || initQueued) return;
                initQueued = true;
                requestAnimationFrame(() => {
                    initQueued = false;
                    window.initEtalaseSearchableSelects();
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }

        function setHiddenInput(form, name, value) {
            let input = form.querySelector(`input[name="${name}"]`);
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                form.appendChild(input);
            }
            input.value = value;
        }

        // Delegasi konfirmasi modern untuk form non-PIN (data-modern-confirm)
        document.addEventListener('submit', async function (e) {
            const form = e.target;
            if (!(form instanceof HTMLFormElement) || form.classList.contains('pin-required')) return;
            const confirmMsg = form.dataset.modernConfirm;
            if (!confirmMsg) return;

            e.preventDefault();
            e.stopImmediatePropagation();
            if (form.dataset.submitting) return;

            const ok = await window.confirmTransferAction({
                title: form.dataset.confirmTitle || 'Konfirmasi Aksi',
                message: confirmMsg,
                confirmText: form.dataset.confirmOk || 'Ya, Lanjutkan',
                variant: form.dataset.confirmVariant || 'danger',
            });
            if (!ok) return;

            form.dataset.submitting = '1';
            form.querySelectorAll('button[type="submit"], button:not([type])').forEach((btn) => {
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            });
            form.submit();
        }, true);

        // Form dengan class "pin-required" otomatis memvalidasi etalase, lalu menggabungkan
        // konfirmasi + input PIN dalam satu modal (#transferPinModal).
        document.addEventListener('submit', async function (e) {
            const form = e.target;
            if (!(form instanceof HTMLFormElement) || !form.classList.contains('pin-required')) return;

            e.preventDefault();
            e.stopImmediatePropagation();
            if (form.dataset.submitting) return;

            form.querySelectorAll('input[name="etalases_id"], input[name^="item_etalases["]').forEach((el) => el.remove());

            if (form.dataset.acceptItemId) {
                const itemId = form.dataset.acceptItemId;
                const transferId = form.dataset.transferId;
                const itemSelect = document.querySelector(`.item-etalase-select[data-item-id="${itemId}"]`);
                const bulkSelect = transferId ? document.querySelector(`.transfer-bulk-etalase-select[data-transfer-id="${transferId}"]`) : null;
                const chosen = (itemSelect?.value || bulkSelect?.value || '').trim();

                if (form.dataset.requireEtalase === '1' && !chosen) {
                    window.highlightInvalidField(itemSelect || bulkSelect, 'Pilih etalase tujuan terlebih dahulu sebelum menerima obat.');
                    return;
                }
                if (chosen) {
                    setHiddenInput(form, 'etalases_id', chosen);
                }
            } else if (form.dataset.acceptTransferId) {
                const transferId = form.dataset.acceptTransferId;
                const bulkSelect = document.querySelector(`.transfer-bulk-etalase-select[data-transfer-id="${transferId}"]`);
                const itemSelects = [...document.querySelectorAll(`.item-etalase-select[data-transfer-id="${transferId}"][data-item-status="0"]`)];
                const bulkVal = (bulkSelect?.value || '').trim();
                const emptyItemSelect = itemSelects.find((s) => !String(s.value || '').trim());
                const allItemsChosen = itemSelects.length > 0 && !emptyItemSelect;

                if (form.dataset.requireEtalase === '1' && !bulkVal && !allItemsChosen) {
                    window.highlightInvalidField(emptyItemSelect || bulkSelect, 'Pilih etalase tujuan terlebih dahulu sebelum menerima mutasi.');
                    return;
                }

                if (bulkVal) {
                    setHiddenInput(form, 'etalases_id', bulkVal);
                }
                itemSelects.forEach((s) => {
                    const id = s.dataset.itemId;
                    const val = String(s.value || '').trim();
                    if (id && val) {
                        setHiddenInput(form, `item_etalases[${id}]`, val);
                    }
                });
            }

            const result = await window.requestTransferPin({
                title: form.dataset.pinTitle,
                subtitle: form.dataset.pinSubtitle,
                confirmMessage: form.dataset.confirm || '',
            });
            if (!result) return;

            setHiddenInput(form, 'pin', result.pin);

            form.dataset.submitting = '1';
            form.querySelectorAll('button[type="submit"], button:not([type])').forEach((btn) => {
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            });
            form.submit();
        }, true);
    })();
</script>
