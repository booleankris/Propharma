@extends('layouts.app')
@section('content')
    {{-- Section Product Preview --}}
    <div class="mx-4 max-w-8xl grid grid-cols-12 gap-6">
        <!-- LEFT COLUMN -->
        <section class="col-span-12 lg:col-span-8 space-y-6">
            <!-- Header Card -->
            <div class="card p-6 bg-white dashboard-panel">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-end justify-between md:block">
                        <h1 class="text-4xl font-semibold tracking-tight">Transaksi</h1>
                    </div>
                    <div class="flex items-center justify-between gap-6 w-full md:w-auto">
                        <!-- Date / Time -->
                        <div class="text-right">
                            <div class="text-sm md:text-base font-medium">
                                {{ now()->translatedFormat('l, d F Y') }}
                            </div>
                            <div class="text-xs muted">
                                {{ now()->format('H.i') }} WITA
                            </div>
                            <div class="text-sm md:text-base text-[#37719e] font-nunito !text-[12px] font-medium">
                                @if ($check_transaction == 1)
                                    {{ $transaction->transaction_code }}
                                @endif
                            </div>
                        </div>
                    </div>

                </div>


                <!-- Transaction Type Chips -->
                <div class="flex gap-4 pt-3">
                    <!-- Resep Credit -->
                    <a href="{{ url('transaction/kredit') }}">
                        <button
                            class="flex flex-col items-center justify-center w-40 h-24  {{ request()->is('transaction/kredit') ? 'transaction-item-active shadow-none' : '' }}  border-[#D6D5D5] border rounded-2xl shadow-sm hover:bg-gray-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 mb-2" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.25 6.75h19.5v10.5H2.25zM2.25 9.75h19.5" />
                            </svg>
                            <span class="text-sm font-medium">Resep Credit</span>
                        </button>
                    </a>

                    <!-- Resep Tunai -->
                    <a href="{{ url('transaction/resep') }}">
                        <button
                            class="flex flex-col items-center justify-center w-40 h-24 {{ request()->is('transaction/resep') ? 'transaction-item-active shadow-none' : '' }} border-[#D6D5D5] border rounded-2xl shadow-sm hover:bg-gray-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 mb-2" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.25 6.75h19.5v10.5H2.25zM2.25 9.75h19.5" />
                            </svg>
                            <span class="text-sm font-medium">Resep Tunai</span>
                        </button>
                    </a>

                    <!-- HV/OTC -->
                    <a href="{{ url('transaction/hv') }}">
                        <button
                            class="flex flex-col items-center justify-center w-40 h-24 border-[#D6D5D5] {{ request()->is('transaction/hv') ? 'transaction-item-active shadow-none' : '' }} border rounded-2xl shadow-sm hover:bg-gray-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 mb-2" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.25 6.75h19.5v10.5H2.25zM2.25 9.75h19.5" />
                            </svg>
                            <span class="text-sm font-medium">HV/OTC</span>
                        </button>
                    </a>

                    <!-- UPDS -->
                    <a href="{{ url('transaction/upds') }}">
                        <button
                            class="flex flex-col items-center justify-center w-40 h-24 border-[#D6D5D5] {{ request()->is('transaction/upds') ? 'transaction-item-active shadow-none' : '' }} border rounded-2xl shadow-sm hover:bg-gray-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 mb-2" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.25 6.75h19.5v10.5H2.25zM2.25 9.75h19.5" />
                            </svg>
                            <span class="text-sm font-medium">UPDS</span>
                        </button>
                    </a>
                </div>
                <br>
                @if ($check_transaction == 0)
                    <form method="post" action="{{ route('transaction.createnew') }}" class="">
                        @csrf
                        <input type="hidden" value="{{ request()->segment(2) }}" name="type" id="type">
                        <button type="submit" class="btn btn-pharma !bg-[#2196F3] btn-lg btn-icon icon-right"
                            tabindex="4">
                            Tambah Transaksi
                        </button>
                    </form>
                @endif
            </div>

            @if ($check_transaction == 1)
                <div class="card p-6  flex flex-wrap items-center bg-white dashboard-panel">

                    <div class="w-full">
                        <div class="w-full my-2">
                            <label class="px-2">Cari Obat</label>

                        </div>
                        <input autofocus id="productSearch" type="text" placeholder="Ketik KODE / ID / Nama…"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300"
                            autocomplete="off" />

                        <!-- Dropdown -->
                        <div id="productResults"
                            class="absolute z-50 mt-2 w-[50%] rounded-xl border border-gray-200 bg-white shadow-lg hidden">
                            <ul id="productList" role="listbox" class="max-h-80 overflow-auto py-2"></ul>
                        </div>

                        <!-- Hidden field to hold selection (optional) -->
                        <input type="hidden" id="selectedProductId" />
                    </div>
                    <div class="w-full flex mt-2">
                        <div class="mr-2 w-full">
                            <div class="w-full my-2">
                                <label class="px-2">Nama Obat</label>

                            </div>
                            <input id="name" type="text" name="name" readonly placeholder="Nama obat"
                                class="w-full rounded-xl readonly border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                autocomplete="off" />
                        </div>
                        <div class="mr-2">
                            <div class="w-full my-2">
                                <label class="px-2">Stok</label>

                            </div>
                            <input id="stock" name="stock" readonly type="text" placeholder="Stok"
                                class="w-full rounded-xl readonly border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                autocomplete="off" />
                        </div>
                        <div class="mr-2">
                            <div class="w-full my-2">
                                <label class="px-2">Satuan</label>

                            </div>
                            <input type="text" id="unit" name="unit" readonly placeholder="Satuan"
                                class="w-full rounded-xl readonly border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                autocomplete="off" />
                        </div>
                    </div>
                    <div class="flex justify-start mt-2 w-full">
                        <div class="mr-2 w-[90%]">
                            <div class="w-full my-2">
                                <label class="px-2">Harga</label>

                            </div>
                            <input id="price" type="text" name="price" readonly placeholder="Harga obat"
                                class="w-full rounded-xl readonly border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                autocomplete="off" />
                        </div>
                        <div class="mr-2">
                            <div class="w-full my-2">
                                <label class="px-2">Jumlah Beli</label>

                            </div>
                            <input id="quantity" required name="quantity" onkeyup="count(this.value)" type="number"
                                placeholder="Quantity"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                autocomplete="off" />
                        </div>
                    </div>
                    <div class="flex justify-start mt-2 w-full">
                        <div class="mr-2 w-[40%]">
                            <div class="w-full my-2">
                                <label class="px-2">Discount</label>

                            </div>
                            <input id="discount" name="discount" onkeyup="countDiscount(this.value)" type="text"
                                placeholder="Discount"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                autocomplete="off" />
                        </div>
                        <div class="mr-2 w-full">
                            <div class="w-full my-2">
                                <label class="px-2">Total Harga</label>
                            </div>
                            <input type="text" name="total_price" readonly tabindex="-1" id="total"
                                placeholder="Total Harga"
                                class="w-full readonly rounded-xl border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                autocomplete="off" />
                        </div>
                        <div class="mr-2 w-full self-end">
                            <div class="w-full my-2">
                            </div>
                            <button type="button" onclick="submit()"
                                class="btn btn-pos my-1 !bg-[##FFC107] p-1 py-3 font-poppin font-bold">
                                Lanjutkan
                            </button>
                        </div>

                    </div>
                </div>
            @endif
        </section>

        @if ($check_transaction == 1)
            <!-- RIGHT COLUMN -->
            <aside class="col-span-12 lg:col-span-4 space-y-6 dashboard-panel">
                <div class="card p-6 bg-white">
                    <h2 class="text-xl font-semibold mb-4">Barang Dibeli</h2>
                    <div tabindex="-1" class="mt-4 rounded-2xl bg-gray-100 h-64 overflow-y-scroll md:h-80">
                        <div class="flex flex-col justify-between">
                            <br>
                            <div id="carts">
                                @foreach ($itemInCart as $cart)
                                    <div id="itemincart{{ $cart->id }}">
                                        <div
                                            class="flex justify-between mx-[15px] mt-[5px] py-[20px] mb-[8px] rounded-lg bg-[#fff]">
                                            <div>
                                                <div class="px-[20px] font-poppins font-semibold">
                                                    {{ $cart->medicine->name }}
                                                </div>
                                                <div class="px-[20px] font-poppins text-[10px]">
                                                    Rp.{{ number_format($cart->total_price, 0, ',', '.') }}</div>
                                            </div>
                                            <div class="mx-[10px]">
                                                <button tabindex="-1" onclick="removeItem({{ $cart->id }})"
                                                    class="flex justify-center items-center mr-1 px-2 py-1 text-[#DF1463] border border-[#DF1463] rounded hover:bg-[#DF1463] hover:text-white">
                                                    <svg viewBox="0 0 24 24" fill="none"
                                                        xmlns="http://www.w3.org/2000/svg" class="w-5 h-5">
                                                        <path
                                                            d="M8 6V4.41421C8 3.63317 8.63317 3 9.41421 3H14.5858C15.3668 3 16 3.63317 16 4.41421V6"
                                                            stroke="#DF1463" stroke-width="1.7" stroke-linecap="round" />
                                                        <path
                                                            d="M5.7372 6.54395V18.9857C5.7372 19.7449 6.35269 20.3604 7.11194 20.3604H16.8894C17.6487 20.3604 18.2642 19.7449 18.2642 18.9857V6.54395M2.90918 6.54395H21.091"
                                                            stroke="#c60653" stroke-width="1.7" stroke-linecap="round" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <br>
                        </div>
                    </div>
                    <div class="mr-2 w-[100%]">
                        <div class="w-full my-2">
                            <label class="px-2">Total</label>

                        </div>
                        <input id="carttotal" tabindex="-1" readonly type="text" name="carttotal"
                            placeholder="Total obat"
                            class="w-full rounded-xl readonly border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300"
                            autocomplete="off" />

                    </div>
                    <div class="mr-2 w-[100%]">
                        <div class="w-full my-2">
                            <label class="px-2">Bayar</label>

                        </div>
                        <input id="pay" onkeyup="pay(this.value)" type="text" name="pay"
                            placeholder="Bayar obat"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300"
                            autocomplete="off" />

                    </div>
                    <div class="mr-2 w-[100%]">
                        <div class="w-full my-2">
                            <label class="px-2">Kembalian</label>

                        </div>
                        <input id="change" tabindex="-1" readonly type="text" name="change"
                            placeholder="Bayar obat"
                            class="w-full rounded-xl border readonly border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300"
                            autocomplete="off" />

                    </div>
                    <div class="mt-5">
                        <form id="checkoutForm" action="{{ route('transaction.checkout') }}" method="POST">
                            @csrf
                            <input type="hidden" name="transaction_id" id="transaction_id">
                            <button type="button" id="checkout" disabled onclick="checkoutItem()"
                                class="btn btn-pharma !rounded-[5.3px] !bg-gray-400 btn-lg btn-icon icon-right mb-1">Selesaikan</button>
                        </form>
                    </div>
                </div>
            </aside>
        @endif
    </div>
    {{-- ------------------- Fixed Cart Card --------------------- --}}
    {{-- @if (request()->routeIs('sales*') != true)
        @if ($cart_total != '0')
            <div class="fixed centering-fixed w-[85%]">
                <div class="flex items-center py-2 px-3 bg-white rounded-xl shadow-lg">
                    <div class="flex flex-shrink-0 items-center justify-center bg-green-200 h-16 w-16 rounded">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-7 h-7">
                            <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                            <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                            <g id="SVGRepo_iconCarrier">
                                <path
                                    d="M7.5 18C8.32843 18 9 18.6716 9 19.5C9 20.3284 8.32843 21 7.5 21C6.67157 21 6 20.3284 6 19.5C6 18.6716 6.67157 18 7.5 18Z"
                                    stroke="#07590a" stroke-width="1.5"></path>
                                <path
                                    d="M16.5 18.0001C17.3284 18.0001 18 18.6716 18 19.5001C18 20.3285 17.3284 21.0001 16.5 21.0001C15.6716 21.0001 15 20.3285 15 19.5001C15 18.6716 15.6716 18.0001 16.5 18.0001Z"
                                    stroke="#07590a" stroke-width="1.5"></path>
                                <path
                                    d="M2 3L2.26121 3.09184C3.5628 3.54945 4.2136 3.77826 4.58584 4.32298C4.95808 4.86771 4.95808 5.59126 4.95808 7.03836V9.76C4.95808 12.7016 5.02132 13.6723 5.88772 14.5862C6.75412 15.5 8.14857 15.5 10.9375 15.5H12M16.2404 15.5C17.8014 15.5 18.5819 15.5 19.1336 15.0504C19.6853 14.6008 19.8429 13.8364 20.158 12.3075L20.6578 9.88275C21.0049 8.14369 21.1784 7.27417 20.7345 6.69708C20.2906 6.12 18.7738 6.12 17.0888 6.12H11.0235M4.95808 6.12H7"
                                    stroke="#07590a" stroke-width="1.5" stroke-linecap="round"></path>
                            </g>
                        </svg>
                    </div>
                    <div class="flex-grow flex flex-col ml-4">
                        <span class="text-md font-bold">{{ $cart_total }} Items</span>
                        <div class="flex items-center justify-between">
                            <span class="text-[14px] text-green-500">Rp
                                {{ number_format($cart_subtotal, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    <div class="flex-grow flex text-center flex-col ml-4">
                        <a href="{{ route('sales.index') }}"
                            class="text-[17px] text-[#80cc83] font-bold font-montserrat">Lihat</a>

                    </div>
                </div>
            </div>
        @endif
    @endif --}}
    {{-- ============================================================== Modal Invoice  ============================================================== --}}
    <div id="invoiceModal"
        class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 opacity-0 transition-opacity duration-300">
        <div id="invoiceContent"
            class="bg-white rounded-lg shadow-lg w-96 p-6 transform scale-95 transition-transform duration-300">
            <div class="invoice-container" id="invoiceReceipt">
                <div class="invoice-header">
                    <h4>Propharma</h4>
                    <p class="small-text">Some address goes here</p>
                </div>

                <div class="invoice-info">
                    <p class="invoice-row"><span class="muted-text">Receipt No.:</span> <span id="receipt"></span></p>
                    <p class="invoice-row"><span class="muted-text">Order Type:</span> <span id="type"></span></p>
                    <p class="invoice-row"><span class="muted-text">Cashier:</span> <span id="cashier"></span></p>
                    <p class="invoice-row" id="customer-row"><span class="muted-text">Customer:</span> <span
                            id="customer"></span></p>
                </div>

                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>QTY</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="invoiceItems">
                        <tr>
                            <td>Shawarma Big</td>
                            <td>4</td>
                            <td>$12</td>
                        </tr>
                        <tr>
                            <td>Viju Milk - 100ml</td>
                            <td>1</td>
                            <td>$1</td>
                        </tr>
                    </tbody>
                </table>

                <div class="divider"></div>

                <div class="contact-info">
                    <p>📧 info@example.com</p>
                    <p>📞 +234XXXXXXXX</p>
                </div>

                <div class="button-row">
                    <button class="btn btn-gray" onclick="closeInvoice()">Tutup</button>
                    <button class="btn btn-blue" onclick="printInvoice()">Cetak & Selesai</button>
                </div>
            </div>
        </div>
    </div>
    {{-- ============================================================== Modal Invoice  ============================================================== --}}


    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        // --- EndPioint ---
        const endpoint = "{{ route('products.search') }}";

        const trx_id = {{ $transaction?->id ?? 'null' }};
        const input = document.getElementById('productSearch');
        const name = document.getElementById('name');
        const stock = document.getElementById('stock');
        const unit = document.getElementById('unit');
        const quantity = document.getElementById('quantity');
        const price = document.getElementById('price');
        const totalprice = document.getElementById('total');

        var transaction_id = {{ $transaction?->id ?? 'null' }};
        var discount = document.getElementById('discount');
        var total_discount = "";
        var total_item = "";
        var medicine_id = "";
        var rounding = {{ $rounding }};
        var parameters = {{ $parameters }};
        var price2 = "";
        var subtotal = "";
        var final_price = "";
        var totaltransaction = {{ $totaltransaction }};


        const box = document.getElementById('productResults');
        const list = document.getElementById('productList');
        const hidden = document.getElementById('selectedProductId');

        let items = []; // current results
        let activeIndex = -1;
        let closeTimeout;

        function formatRupiah(value) {
            const number = Number(value) || 0;
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(number);
        }

        function parseRupiah(rupiahString) {
            return Number(
                rupiahString
                .replace(/[^0-9,-]/g, '') // remove "Rp", dots, spaces
                .replace(',', '.') // handle decimal comma if any
            ) || 0;
        }

        // Count All Total Price in Cart
        document.getElementById('carttotal').value = formatRupiah(totaltransaction);

        // Debounce helper
        function debounce(fn, wait = 250) {
            let t;
            return (...args) => {
                clearTimeout(t);
                t = setTimeout(() => fn(...args), wait);
            };
        }

        function openBox() {
            box.classList.remove('hidden');
        }

        function closeBox() {
            box.classList.add('hidden');
            activeIndex = -1;
            highlight();
        }

        function highlight() {
            [...list.children].forEach((li, i) => {
                li.classList.toggle('bg-gray-100', i === activeIndex);
            });
        }

        function render(items) {
            list.innerHTML = '';
            if (!items.length) {
                list.innerHTML = `<li class="px-4 py-3 text-sm text-gray-500">Tidak ada hasil</li>`;
                return;
            }
            for (const it of items) {
                const li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.className = 'cursor-pointer px-4 py-3 hover:bg-gray-100';
                li.dataset.id = it.id;

                li.innerHTML = `
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="font-medium">${escapeHtml(it.name)}</div>
                <div class="text-xs text-gray-500">
                  Kode: ${escapeHtml(it.code)} • Stok: ${it.stock} • Tipe: ${escapeHtml(it.type || '-')}
                </div>
              </div>
              <div class="text-sm font-semibold whitespace-nowrap">${formatRupiah(it.net_price)}</div>
            </div>
          `;

                li.addEventListener('mousedown', (e) => {
                    // mousedown so it fires before input loses focus
                    selectItem(it);
                    e.preventDefault();
                });

                list.appendChild(li);
            }
        }

        function escapeHtml(s) {
            return String(s ?? '').replace(/[&<>"']/g, m => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [m]));
        }

        // Fetch results
        const doSearch = debounce(async (term) => {
            if (!term.trim()) {
                list.innerHTML = '';
                closeBox();
                return;
            }
            const url = `${endpoint}?q=${encodeURIComponent(term)}`;
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json'
                }
            });
            if (!res.ok) return;
            items = await res.json();
            render(items);
            openBox();
        }, 250);

        // Input events
        input.addEventListener('input', (e) => {
            doSearch(e.target.value);
        });

        input.addEventListener('keydown', (e) => {
            const max = items.length - 1;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (max < 0) return;
                activeIndex = Math.min(max, activeIndex + 1);
                highlight();
                ensureVisible();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (max < 0) return;
                activeIndex = Math.max(0, activeIndex - 1);
                highlight();
                ensureVisible();
            } else if (e.key === 'Enter') {
                if (activeIndex >= 0 && items[activeIndex]) {
                    e.preventDefault();
                    selectItem(items[activeIndex]);
                }
            } else if (e.key === 'Escape') {
                closeBox();
            }
        });

        function ensureVisible() {
            const li = list.children[activeIndex];
            if (!li) return;
            const lTop = list.scrollTop;
            const lBottom = lTop + list.clientHeight;
            const liTop = li.offsetTop;
            const liBottom = liTop + li.offsetHeight;
            if (liTop < lTop) list.scrollTop = liTop;
            else if (liBottom > lBottom) list.scrollTop = liBottom - list.clientHeight;
        }

        function count(val) {
            total_item = val;
            var total = price2 * val;
            subtotal = total;
            totalprice.value = formatRupiah(total);
        }

        function countDiscount(val) {

            if (val > 100) {
                totalprice.value = formatRupiah(subtotal - val);
                final_price = subtotal - val;
                total_discount = val;
            } else {
                discount = subtotal * val / 100;
                totalprice.value = formatRupiah(subtotal - discount);
                final_price = subtotal - discount;
                total_discount = `${val}%`;
            }

        }

        function selectItem(it) {
            hidden.value = it.id;
            medicine_id = it.id;
            input.value = ``;
            stock.value = `${it.stock}`;
            unit.value = `${it.unit}`;
            name.value = `${it.name}`;
            // 
            let raw = it.net_price * parameters + rounding;
            let rounded = Math.floor(raw / 1000) * 1000;
            price.value = formatRupiah(rounded);
            // 
            price2 = rounded;
            console.log(parameters);
            quantity.focus();
            quantity.select();
            closeBox();

            // TODO: Integrate action after select (e.g., add to cart)
            // example:
            // addToCart(it.id);
        }
        // set CSRF token for Axios globally

        axios.defaults.headers.common['X-CSRF-TOKEN'] = '{{ csrf_token() }}';

        function addToCart(medicine_id, transaction_id, quantity, discount, total_price) {
            axios.post("{{ route('transaction.addToCart') }}", {
                    medicine_id: medicine_id,
                    transaction_id: transaction_id,
                    quantity: quantity,
                    discount: discount,
                    total_price: total_price
                })
                .then(response => {
                    console.log("✅ Added to cart:", response.data);
                    stock.value = "";
                    unit.value = "";
                    document.getElementById('quantity').value = "";
                    price.value = "";
                    name.value = "";
                    totalprice.value = "";
                    document.getElementById('pay').value = "";
                    document.getElementById('change').value = "";
                    totaltransaction += total_price;

                    // var removeUrl = "http/dawdad";

                    document.getElementById('carttotal').value = formatRupiah(totaltransaction);
                    document.getElementById('pay').focus();
                    document.getElementById('discount').value = "";

                    document.getElementById('productSearch').focus();
                    closeBox();

                    let item = response.data;
                    document.getElementById('carts').innerHTML += `
                    <div id="itemincart${item.id}">
                        <div class="flex justify-between mx-[15px] mt-[5px] py-[20px] mb-[8px] rounded-lg bg-[#fff]">
                            <div>
                                <div class="px-[20px] font-poppins font-semibold">${item.name}</div>
                                <div class="px-[20px] font-poppins text-[10px]">
                                    ${formatRupiah(item.total_price)}
                                </div>
                            </div>
                            <div class="mx-[10px]">
                                <button onclick="removeItem(${item.id})"
                                    class="flex justify-center items-center mr-1 px-2 py-1 text-[#DF1463] border border-[#DF1463] rounded hover:bg-[#DF1463] hover:text-white">
                                    <svg viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg" class="w-5 h-5">
                                        <path
                                            d="M8 6V4.41421C8 3.63317 8.63317 3 9.41421 3H14.5858C15.3668 3 16 3.63317 16 4.41421V6"
                                            stroke="#DF1463" stroke-width="1.7" stroke-linecap="round" />
                                        <path
                                            d="M5.7372 6.54395V18.9857C5.7372 19.7449 6.35269 20.3604 7.11194 20.3604H16.8894C17.6487 20.3604 18.2642 19.7449 18.2642 18.9857V6.54395M2.90918 6.54395H21.091"
                                            stroke="#c60653" stroke-width="1.7" stroke-linecap="round" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                `;


                })
                .catch(error => {
                    console.error("❌ Error adding to cart:", error.response ? error.response.data : error.message);
                });
        }

        function submit() {
            addToCart(medicine_id, transaction_id, total_item, total_discount, final_price);
        }

        function checkoutItem() {
            axios.post("{{ route('transaction.getTransactionItem') }}", {
                    transaction_id: transaction_id,
                })
                .then(response => {
                    console.log("✅ Items In Cart:", response.data);
                    var transaction_items = response.data.itemTransaction;
                    var transaction = response.data.transaction;

                    document.getElementById('receipt').textContent = transaction.transactions.transaction_code;
                    document.getElementById('type').textContent = transaction.transactions.transaction_type;
                    document.getElementById('cashier').textContent = transaction.user.name;
                    document.getElementById('customer').textContent = "Client";
                    document.getElementById('invoiceItems').innerHTML = "";

                    transaction_items.forEach(item => {
                        document.getElementById('invoiceItems').innerHTML += `
                        <tr>
                            <td>${item.medicine.name}</td>
                            <td>${item.quantity}</td>
                            <td>${formatRupiah(item.total_price)}</td>
                        </tr>
                        `;

                    });

                })
                .catch(error => {
                    console.error("❌ Error getting from cart:", error.response ? error.response.data : error.message);
                });
            if (confirm("Apakah anda ingin mencetak struk?")) {
                const modal = document.getElementById("invoiceModal");
                const content = document.getElementById("invoiceContent");
                modal.classList.remove("hidden");

                requestAnimationFrame(() => {
                    modal.classList.add("opacity-100");
                    content.classList.remove("scale-95");
                    content.classList.add("scale-100");
                });

            } else {
                return false;
                document.getElementById("transaction_id").value = transaction_id;
                document.getElementById("checkoutForm").submit();

            }

        }

        function closeInvoice() {
            const modal = document.getElementById("invoiceModal");
            const content = document.getElementById("invoiceContent");

            modal.classList.remove("opacity-100");
            content.classList.remove("scale-100");
            content.classList.add("scale-95");

            // Wait for animation to finish before hiding
            setTimeout(() => {
                document.getElementById("transaction_id").value = transaction_id;
                document.getElementById("checkoutForm").submit();
                modal.classList.add("hidden");
            }, 300);
        }

        function printInvoice() {
            const invoiceContent = document.getElementById('invoiceReceipt').outerHTML;
            const iframe = document.createElement('iframe');

            iframe.style.position = 'absolute';
            iframe.style.width = '0px';
            iframe.style.height = '0px';
            iframe.style.border = 'none';

            document.body.appendChild(iframe);
            const doc = iframe.contentWindow.document;

            doc.open();
            doc.write(`
                <html>
                <head>
                    <title>Invoice</title>

                    <style>
                        @page {
                        size: 80mm auto;
                        margin: 0;
                        }

                        html, body {
                        margin: 0;
                        padding: 0;
                        background: white;
                        }

                        body {
                        display: flex;
                        justify-content: center;
                        align-items: flex-start;
                        min-height: 100%;
                        }

                        .invoice-container {
                        width: 100mm;
                        padding: 4px 8px; 
                        background: #fff;
                        font-family: sans-serif;
                        box-sizing: border-box;
                        }

                        @media print {
                        .invoice-container {
                            box-shadow: none;
                            margin: 0;
                            padding: 4px 8px;
                        }
                        }

                        .invoice-logo {
                        display: block;
                        margin: 0 auto 2px;
                        width: 3rem; 
                        }

                        .invoice-header {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        text-align: center;
                        margin-bottom: 4px;
                        }

                        .invoice-header h4 {
                        margin: 0;
                        font-size: 1rem;
                        }

                        .small-text {
                        font-size: 0.7rem;
                        color: #6b7280;
                        margin: 0;
                        }

                        .invoice-info {
                        display: flex;
                        flex-direction: column;
                        border-bottom: 1px solid #e5e7eb;
                        padding-bottom: 4px;
                        margin-bottom: 4px;
                        font-size: 0.7rem;
                        gap: 2px;
                        }

                        .invoice-row {
                        display: flex;
                        justify-content: space-between;
                        }

                        .muted-text {
                        color: #9ca3af;
                        }

                        .invoice-table {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 0.7rem;
                        }

                        .invoice-table th,
                        .invoice-table td {
                        padding: 2px 0;
                        }

                        .divider {
                        border-bottom: 1px dashed #9ca3af;
                        margin: 4px 0;
                        }

                        .contact-info {
                        text-align: center;
                        font-size: 0.7rem;
                        margin: 4px 0;
                        }

                        .button-row {
                            display:none;
                        }

                        .btn {
                        padding: 0.2rem 0.4rem;
                        border-radius: 0.25rem;
                        cursor: pointer;
                        font-size: 0.7rem;
                        border: none;
                        }

                        .btn-gray {
                        background: #d1d5db;
                        }

                        .btn-blue {
                        background: #2563eb;
                        color: white;
                        }
                    </style>
                </head>
                <body>
                    ${invoiceContent}
                </body>
                </html>
                `);
            doc.close();

            iframe.contentWindow.focus();
            iframe.contentWindow.print();
            document.getElementById("transaction_id").value = transaction_id;
            document.getElementById("checkoutForm").submit();
            document.body.removeChild(iframe);
        }



        function removeItem(id) {
            document.getElementById("itemincart" + id).remove();

            axios.post("{{ route('transaction.removeItem') }}", {
                    id: id,
                })
                .then(response => {
                    console.log("✅ Removed to cart:", response.data);
                    let item = response.data;
                    totaltransaction = totaltransaction - item.total_price;
                    document.getElementById('carttotal').value = formatRupiah(totaltransaction);
                    document.getElementById('pay').value = "";
                    document.getElementById('change').value = "";


                })
                .catch(error => {
                    console.error("❌ Error adding to cart:", error.response ? error.response.data : error.message);
                });
        }

        function onF1Key(e) {
            const key = e.key || e.code;
            const isF1 = key === 'F1' || e.keyCode === 112;

            if (isF1) {
                e.preventDefault();
                document.getElementById('pay').focus();
            }
        }

        function activeButton() {
            document.getElementById("checkout").disabled = false;
            document.getElementById("checkout").classList.remove("!bg-gray-400");
            document.getElementById("checkout").classList.add("!bg-[#2196F3]");
        }

        function resetButton() {
            document.getElementById("checkout").disabled = false;
            document.getElementById("checkout").classList.remove("!bg-[#2196F3]");
            document.getElementById("checkout").classList.add("!bg-gray-400");
        }

        function pay(e) {
            let raw = document.getElementById('pay').value.replace(/\D/g, ""); // only digits
            let bayar = parseInt(raw) || 0;
            document.getElementById('pay').value = "Rp. " + bayar.toLocaleString("id-ID");

            if (bayar < totaltransaction) {
                document.getElementById('change').value = "Duitnya Kurang";
                resetButton();
            } else {
                if (totaltransaction > 0) {
                    activeButton();
                }
                document.getElementById('change').value = formatRupiah(bayar - totaltransaction);
            }
        }

        window.addEventListener('keydown', onF1Key, {
            capture: true
        });

        document.addEventListener('click', (e) => {
            if (!box.contains(e.target) && e.target !== input) closeBox();
        });
        input.addEventListener('blur', () => {
            closeTimeout = setTimeout(closeBox, 120);
        });
        input.addEventListener('focus', () => {
            clearTimeout(closeTimeout);
            if (list.children.length) openBox();
        });
    </script>

    {{-- ------------------- Fixed Cart Card --------------------- --}}
@endsection
