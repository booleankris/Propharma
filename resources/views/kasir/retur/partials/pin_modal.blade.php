{{-- Native <dialog> PIN Pribadi untuk Otorisasi Retur Penjualan.
     Pakai: const res = await window.requestReturPin({ title, subtitle, confirmMessage });
     res = { pin, user: { id, name, username } } atau null jika dibatalkan. --}}
<style>
    dialog.retur-pin-dialog {
        border: none;
        padding: 0;
        margin: auto;
        background: transparent;
        max-width: 24rem;
        width: calc(100% - 2rem);
        overflow: visible;
    }

    dialog.retur-pin-dialog[open] {
        display: block;
    }

    dialog.retur-pin-dialog:modal {
        overscroll-behavior: contain;
    }

    dialog.retur-pin-dialog::backdrop {
        background: rgba(15, 23, 42, 0.48);
        backdrop-filter: blur(2px);
        overflow: hidden;
        overscroll-behavior: contain;
    }

    @keyframes returPinShake {
        0%, 100% { transform: translateX(0); }
        20% { transform: translateX(-6px); }
        40% { transform: translateX(6px); }
        60% { transform: translateX(-4px); }
        80% { transform: translateX(4px); }
    }

    .retur-pin-shake {
        animation: returPinShake 0.28s ease-in-out;
    }
</style>

<dialog id="returPinModal" aria-labelledby="returPinTitle" class="retur-pin-dialog">
    <div id="returPinCard" class="bg-white rounded-2xl p-6 w-full shadow-2xl border border-slate-200/80">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3 bg-indigo-50 text-indigo-600">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
            </svg>
        </div>

        <h3 id="returPinTitle" class="text-[15px] font-semibold text-gray-800 mb-1">Masukkan PIN Retur</h3>
        <p id="returPinSubtitle" class="text-[12px] text-gray-500 mb-3">
            Masukkan 4 digit PIN user role Retur, General Manager, atau administrator.
        </p>

        <div id="returPinSummary"
            class="hidden mb-4 px-3 py-2.5 rounded-xl bg-blue-50/80 border border-blue-100 text-[11px] text-blue-900 leading-relaxed font-medium">
        </div>

        <div class="flex justify-center gap-3 my-3" id="returPinDots" aria-live="polite">
            @for ($i = 0; $i < 4; $i++)
                <div class="retur-pin-dot w-3.5 h-3.5 rounded-full border-2 border-gray-300 transition-all duration-150"></div>
            @endfor
        </div>

        <div class="min-h-[20px] mb-3 text-center">
            <p id="returPinStatus" class="invisible text-[11px] text-gray-500 leading-snug">&nbsp;</p>
        </div>

        <div class="grid grid-cols-3 gap-2 mb-4" id="returPinNumpad">
            @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $key)
                <button type="button" data-retur-pin-key="{{ $key }}"
                    @if ($key === '1') autofocus @endif
                    class="h-12 rounded-xl border border-gray-100 bg-white text-[15px] font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-200 active:scale-95 transition-all duration-100">
                    {{ $key }}
                </button>
            @endforeach
            <div></div>
            <button type="button" data-retur-pin-key="0"
                class="h-12 rounded-xl border border-gray-100 bg-white text-[15px] font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-200 active:scale-95 transition-all duration-100">
                0
            </button>
            <button type="button" data-retur-pin-key="backspace" aria-label="Hapus digit terakhir"
                class="h-12 rounded-xl border border-gray-100 bg-white flex items-center justify-center text-gray-400 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-200 active:scale-95 transition-all duration-100">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z" />
                    <line x1="18" y1="9" x2="12" y2="15" />
                    <line x1="12" y1="9" x2="18" y2="15" />
                </svg>
            </button>
        </div>

        <button type="button" id="returPinCancel"
            class="w-full px-3 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-[13px] font-medium text-gray-600 transition-colors">
            Batal
        </button>
    </div>
</dialog>

<script>
    (function () {
        if (window.requestReturPin) return;

        const VERIFY_URL = @json(route('returdata.verifypin'));
        const modal = document.getElementById('returPinModal');
        if (!modal) return;

        const card = document.getElementById('returPinCard');
        const dots = modal.querySelectorAll('.retur-pin-dot');
        const statusEl = document.getElementById('returPinStatus');
        const numpad = document.getElementById('returPinNumpad');
        const titleEl = document.getElementById('returPinTitle');
        const subtitleEl = document.getElementById('returPinSubtitle');
        const summaryEl = document.getElementById('returPinSummary');
        const cancelBtn = document.getElementById('returPinCancel');

        let value = '';
        let busy = false;
        let resolver = null;
        let verifiedResult = null;

        function csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token());
        }

        function setStatus(type, message) {
            if (!type || !message) {
                statusEl.textContent = '\u00A0';
                statusEl.className = 'invisible text-[11px] text-gray-500 leading-snug';
                return;
            }
            statusEl.textContent = message;
            if (type === 'error') {
                statusEl.className = 'text-[11px] text-rose-600 font-medium leading-snug';
            } else {
                statusEl.className = 'text-[11px] text-indigo-600 font-medium leading-snug';
            }
        }

        function renderDots() {
            dots.forEach((dot, idx) => {
                if (idx < value.length) {
                    dot.classList.remove('border-gray-300', 'bg-transparent');
                    dot.classList.add('border-indigo-600', 'bg-indigo-600', 'scale-110');
                } else {
                    dot.classList.remove('border-indigo-600', 'bg-indigo-600', 'scale-110');
                    dot.classList.add('border-gray-300', 'bg-transparent');
                }
            });
        }

        function showError(message) {
            setStatus('error', message || 'PIN salah, silakan coba lagi.');
            value = '';
            renderDots();
            card.classList.remove('retur-pin-shake');
            void card.offsetWidth;
            card.classList.add('retur-pin-shake');
        }

        async function verify() {
            busy = true;
            setStatus('loading', 'Memverifikasi PIN...');
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
                    verifiedResult = { pin: value, user: data.user || null };
                    modal.close('verified');
                    return;
                }

                showError(data.message || 'PIN salah, silakan coba lagi.');
            } catch (err) {
                showError('Gagal memverifikasi PIN. Periksa koneksi.');
            } finally {
                busy = false;
                numpad.style.pointerEvents = '';
            }
        }

        function handleKey(key) {
            if (busy) return;
            setStatus(null, '');
            if (key === 'backspace') {
                value = value.slice(0, -1);
                renderDots();
                return;
            }
            if (value.length >= 4) return;
            value += key;
            renderDots();
            if (value.length === 4) {
                setTimeout(verify, 120);
            }
        }

        modal.addEventListener('keydown', (e) => {
            if (!modal.open) return;
            if (/^\d$/.test(e.key)) {
                e.preventDefault();
                handleKey(e.key);
            } else if (e.key === 'Backspace') {
                e.preventDefault();
                handleKey('backspace');
            }
        });

        modal.addEventListener('cancel', (e) => {
            if (busy) {
                e.preventDefault();
            }
        });

        modal.addEventListener('close', () => {
            const reason = modal.returnValue;
            modal.returnValue = '';

            const result = reason === 'verified' ? verifiedResult : null;
            const resolveFn = resolver;

            resolver = null;
            verifiedResult = null;
            value = '';
            busy = false;
            numpad.style.pointerEvents = '';
            setStatus(null, '');
            renderDots();

            if (resolveFn) {
                resolveFn(result);
            }
        });

        numpad.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-retur-pin-key]');
            if (btn) handleKey(btn.dataset.returPinKey);
        });

        cancelBtn.addEventListener('click', () => {
            if (!busy && modal.open) {
                modal.close('cancel');
            }
        });

        window.requestReturPin = function (opts = {}) {
            if (modal.open) {
                modal.close('cancel');
            }

            titleEl.textContent = opts.title || 'Masukkan PIN Retur';
            subtitleEl.textContent = opts.subtitle || 'Masukkan 4 digit PIN user role Retur, General Manager, atau administrator.';

            if (opts.confirmMessage) {
                summaryEl.textContent = opts.confirmMessage;
                summaryEl.classList.remove('hidden');
            } else {
                summaryEl.textContent = '';
                summaryEl.classList.add('hidden');
            }

            value = '';
            busy = false;
            verifiedResult = null;
            setStatus(null, '');
            renderDots();
            modal.returnValue = '';
            modal.showModal();

            return new Promise((resolve) => {
                resolver = resolve;
            });
        };
    })();
</script>
