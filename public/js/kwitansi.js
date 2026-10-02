(function (root) {
    'use strict';
    const units = ['nol', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    const max = 999999999999;
    function spell(n) {
        if (n < 12) return units[n];
        if (n < 20) return spell(n - 10) + ' belas';
        if (n < 100) return spell(Math.floor(n / 10)) + ' puluh' + (n % 10 ? ' ' + spell(n % 10) : '');
        if (n < 200) return 'seratus' + (n % 100 ? ' ' + spell(n % 100) : '');
        if (n < 1000) return spell(Math.floor(n / 100)) + ' ratus' + (n % 100 ? ' ' + spell(n % 100) : '');
        if (n < 2000) return 'seribu' + (n % 1000 ? ' ' + spell(n % 1000) : '');
        for (const [scale, word] of [[1000000000, 'miliar'], [1000000, 'juta'], [1000, 'ribu']]) {
            if (n >= scale) return spell(Math.floor(n / scale)) + ' ' + word + (n % scale ? ' ' + spell(n % scale) : '');
        }
    }
    function parseWords(text) {
        const cleaned = text.toLowerCase().trim().replace(/[.]+$/, '').replace(/\s+rupiah$/, '').replace(/\s+/g, ' ');
        let total = 0, group = 0, pending = 0;
        const tokens = cleaned.split(' ');
        for (const token of tokens) {
            if (units.includes(token)) pending += units.indexOf(token);
            else if (token === 'belas') pending += 10;
            else if (token === 'puluh') { group += pending * 10; pending = 0; }
            else if (token === 'ratus') { group += pending * 100; pending = 0; }
            else if (token === 'seratus') group += 100;
            else if (token === 'seribu') { total += 1000; group = 0; }
            else if (['ribu', 'juta', 'miliar'].includes(token)) {
                total += (group + pending) * ({ribu: 1000, juta: 1000000, miliar: 1000000000}[token]); group = 0; pending = 0;
            } else return null;
        }
        const n = total + group + pending;
        return n <= max && spell(n) === cleaned ? n : null;
    }
    function parseAmount(text) {
        const value = text.trim().replace(/^Rp\.?\s*/i, '').replace(/,00(?:,-)?$/, '');
        if (!/^(?:\d+|\d{1,3}(?:\.\d{3})+)$/.test(value)) return null;
        const n = Number(value.replace(/\./g, ''));
        return Number.isSafeInteger(n) && n <= max ? n : null;
    }
    root.initializeKwitansi = function ({claimUrl, returnUrl, transactionId}) {
        const amount = document.getElementById('amount'), words = document.getElementById('words');
        const button = document.getElementById('print-button'), status = document.getElementById('status');
        const back = document.getElementById('back-button'), cancel = document.getElementById('cancel-button');
        const viewButton = document.getElementById('view-button'), viewHint = document.getElementById('view-hint');
        let valid = true, used = false, busy = false;
        function leave(event) {
            event.preventDefault();
            if (busy) return;
            if (window.opener && !window.opener.closed) {
                window.opener.focus();
                window.close();
                setTimeout(() => { if (!window.closed) window.location.assign(returnUrl); }, 100);
            } else window.location.assign(returnUrl);
        }
        back.addEventListener('click', leave);
        cancel.addEventListener('click', leave);
        function notifyPrinted() {
            const message = {type: 'kwitansi-printed', transactionId: String(transactionId), at: Date.now()};
            try { localStorage.setItem('kwitansi-printed', JSON.stringify(message)); } catch (_) { /* Storage may be disabled. */ }
            if (window.opener && !window.opener.closed) window.opener.postMessage(message, window.location.origin);
        }
        function sync(source) {
            if (used || busy) return;
            const n = source === amount ? parseAmount(amount.textContent) : parseWords(words.textContent);
            valid = n !== null;
            status.textContent = valid ? '' : 'Nominal atau terbilang tidak valid. Gunakan rupiah bulat dan terbilang baku (maksimal 999.999.999.999).';
            button.disabled = !valid || used || busy;
            if (valid) {
                if (source === words) amount.textContent = n.toLocaleString('id-ID');
                else words.textContent = spell(n) + ' rupiah';
                fit(words);
                fit(amount);
            }
        }
        amount.addEventListener('input', () => sync(amount));
        words.addEventListener('input', () => sync(words));
        function fit(el) {
            const initial = Number(el.dataset.fitSize), minimum = Number(el.dataset.fitMin);
            const fits = () => el.scrollHeight <= el.clientHeight + 1 && el.scrollWidth <= el.clientWidth + 1;
            el.style.fontSize = initial + 'px';
            for (let size = initial; !fits() && size > minimum;) el.style.fontSize = (--size) + 'px';
            return fits();
        }
        const fitFields = Array.from(document.querySelectorAll('[data-fit-size]'));
        fitFields.forEach(el => el.addEventListener('input', () => fit(el)));
        viewButton.addEventListener('click', () => {
            if (busy || used) return;
            const editing = document.body.classList.toggle('edit-mode');
            viewButton.textContent = editing ? 'Lihat Format Cetak' : 'Kembali ke Mode Edit';
            viewButton.setAttribute('aria-pressed', String(!editing));
            viewHint.textContent = editing
                ? 'Mode edit tegak. Saat dicetak, isi otomatis menyamping mengikuti format kwitansi fisik.'
                : 'Pratinjau format cetak. Pilih Kembali ke Mode Edit untuk mengisi dengan posisi tegak.';
            fitFields.forEach(fit);
        });
        const fontsReady = document.fonts ? document.fonts.ready : Promise.resolve();
        fontsReady.then(() => fitFields.forEach(fit));
        button.addEventListener('click', async () => {
            if (!valid || used || busy) return;
            busy = true; button.disabled = true; cancel.disabled = true; viewButton.disabled = true;
            await fontsReady;
            const fits = fitFields.map(fit);
            if (fits.includes(false)) { busy = false; button.disabled = false; cancel.disabled = false; viewButton.disabled = false; status.textContent = 'Teks terlalu panjang. Ringkas penerima atau keterangan agar seluruh isi tercetak dan ruang tanda tangan tetap tersedia.'; return; }
            const editableFields = Array.from(document.querySelectorAll('[contenteditable="plaintext-only"]'));
            editableFields.forEach(el => el.contentEditable = 'false');
            try {
                const response = await fetch(claimUrl, {method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'}});
                if (!response.ok) {
                    if (response.status === 409) { used = true; notifyPrinted(); }
                    throw new Error(response.status === 409 ? 'Kwitansi sudah pernah dicetak.' : 'Tidak dapat mengizinkan cetak. Muat ulang halaman untuk memeriksa status.');
                }
                used = true;
                notifyPrinted();
                button.textContent = 'Sudah Dicetak';
                document.querySelectorAll('[contenteditable]').forEach(el => el.contentEditable = 'false');
                document.body.classList.add('print-authorized');
                window.print();
            } catch (error) { status.textContent = error.message; }
            finally {
                busy = false; button.disabled = used || !valid; cancel.disabled = false; viewButton.disabled = used;
                if (!used) editableFields.forEach(el => el.contentEditable = 'plaintext-only');
            }
        });
        window.addEventListener('afterprint', () => {
            document.body.classList.remove('print-authorized');
            if (used) { document.querySelector('.paper').hidden = true; status.textContent = 'Kesempatan cetak kwitansi telah digunakan.'; }
        });
        sync(amount);
    };
    if (typeof module !== 'undefined') module.exports = {spell, parseWords, parseAmount};
})(typeof window === 'undefined' ? globalThis : window);
