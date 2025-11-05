@extends('layouts.app')
@section('content')
    {{-- Section Product Preview --}}
    <div class="mx-4 max-w-8xl grid grid-cols-12 gap-6">
        <!-- LEFT COLUMN -->
        <section class="col-span-12 lg:col-span-5 space-y-3">
            <!-- Header Card -->
            <div class="card py-3 px-6 bg-white dashboard-panel">
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
                <div class="flex flex-wrap justify-start gap-4 pt-3">
                    <!-- Resep Credit -->
                    <a href="{{ url('transaction/kredit') }}">
                        <button
                            class="flex flex-col items-center justify-center w-[90px] h-[60px]  {{ request()->is('transaction/kredit') ? 'transaction-item-active shadow-none' : '' }}  border-[#D6D5D5] border rounded-2xl shadow-sm hover:bg-gray-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mb-2" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.25 6.75h19.5v10.5H2.25zM2.25 9.75h19.5" />
                            </svg>
                            <span class="text-[10px] font-semibold font-poppins">Resep Credit</span>
                        </button>
                    </a>

                    <!-- Resep Tunai -->
                    <a href="{{ url('transaction/resep') }}">
                        <button
                            class="flex flex-col items-center justify-center w-[90px] h-[60px] {{ request()->is('transaction/resep') ? 'transaction-item-active shadow-none' : '' }} border-[#D6D5D5] border rounded-2xl shadow-sm hover:bg-gray-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mb-2" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.25 6.75h19.5v10.5H2.25zM2.25 9.75h19.5" />
                            </svg>
                            <span class="text-[10px] font-semibold font-poppins">Resep Tunai</span>
                        </button>
                    </a>

                    <!-- HV/OTC -->
                    <a href="{{ url('transaction/hv') }}">
                        <button
                            class="flex flex-col items-center justify-center w-[90px] h-[60px] border-[#D6D5D5] {{ request()->is('transaction/hv') ? 'transaction-item-active shadow-none' : '' }} border rounded-2xl shadow-sm hover:bg-gray-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mb-2" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.25 6.75h19.5v10.5H2.25zM2.25 9.75h19.5" />
                            </svg>
                            <span class="text-[10px] font-semibold font-poppins">HV/OTC</span>
                        </button>
                    </a>

                    <!-- UPDS -->
                    <a href="{{ url('transaction/upds') }}">
                        <button
                            class="flex flex-col items-center justify-center w-[90px] h-[60px] border-[#D6D5D5] {{ request()->is('transaction/upds') ? 'transaction-item-active shadow-none' : '' }} border rounded-2xl shadow-sm hover:bg-gray-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mb-2" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.25 6.75h19.5v10.5H2.25zM2.25 9.75h19.5" />
                            </svg>
                            <span class="text-[10px] font-semibold font-poppins">UPDS</span>
                        </button>
                    </a>
                </div>
                @if ($check_transaction == 0)
                    <form method="post" action="{{ route('transaction.createnew') }}" class="mt-3">
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

                        <div class="flex gap-2 items-center">

                            @if ($transaction->transaction_type == 'KREDIT')
                                <div class="w-full">
                                    <div class="w-full my-1">
                                        <label class="text-[13px] font-poppins font-semibold">Cari Debitur</label>

                                    </div>
                                    <input autofocus required id="debtorSearch" type="text"
                                        placeholder="Ketik ID / Nama…"
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 text-[11px] font-poppins"
                                        autocomplete="off" />

                                    <!-- Dropdown -->
                                    <div id="debtorResults"
                                        class="absolute z-50 mt-2 w-[50%] rounded-xl border border-gray-200 bg-white shadow-lg hidden">
                                        <ul id="debtorList" role="listbox" class="max-h-80 overflow-auto py-2"></ul>
                                    </div>

                                    <!-- Hidden field to hold selection (optional) -->
                                    <input type="hidden" id="selectedDebtorId" />
                                </div>
                                <div class="mr-2 w-full hidden">
                                    <div class="w-full my-1">
                                        <label class="text-[13px] font-poppins font-semibold">Nama Debitur</label>

                                    </div>
                                    <input id="debtorname" type="text" name="debtorname" readonly
                                        placeholder="Nama Debitur"
                                        class="w-full rounded-md readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 text-[11px] font-poppins"
                                        autocomplete="off" />
                                </div>
                            @endif
                            <div class="w-full">
                                <div class="w-full my-1">
                                    <label class="text-[13px] font-poppins font-semibold">Cari Obat</label>

                                </div>
                                <input autofocus id="productSearch" type="text" placeholder="Ketik KODE / ID / Nama…"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 text-[11px] font-poppins"
                                    autocomplete="off" />

                                <!-- Dropdown -->
                                <div id="productResults"
                                    class="absolute z-50 mt-2 w-[50%] rounded-xl border border-gray-200 bg-white shadow-lg hidden">
                                    <ul id="productList" role="listbox" class="max-h-80 overflow-auto py-2"></ul>
                                </div>

                                <!-- Hidden field to hold selection (optional) -->
                                <input type="hidden" id="selectedProductId" />
                            </div>
                        </div>

                    </div>

                    <div class="w-full flex">

                        <div class="mr-2 w-full">
                            <div class="w-full my-1">
                                <label class="text-[13px] font-poppins font-semibold">Nama Obat</label>

                            </div>
                            <input id="name" type="text" name="name" readonly placeholder="Nama obat"
                                class="w-full rounded-md readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 text-[11px] font-poppins"
                                autocomplete="off" />
                        </div>
                        <div class="mr-2 hidden">
                            <div class="w-full my-1">
                                <label class="text-[13px] font-poppins font-semibold">Stok</label>

                            </div>
                            <input id="stock" name="stock" readonly type="text" placeholder="Stok"
                                class="w-full rounded-md readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 text-[11px] font-poppins"
                                autocomplete="off" />
                        </div>
                        <div class="mr-2">
                            <div class="w-full my-1">
                                <label class="text-[13px] font-poppins font-semibold">Satuan</label>

                            </div>
                            <input type="text" id="unit" name="unit" readonly placeholder="Satuan"
                                class="w-full rounded-md readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 text-[11px] font-poppins"
                                autocomplete="off" />
                        </div>
                    </div>
                    @if ($transaction->transaction_type == 'KREDIT' || $transaction->transaction_type == 'RESEP TUNAI')
                        <div>
                            <div class="flex pt-2 items-center">
                                <input id="receiptbox" type="checkbox" value=""
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600">
                                <label for="receiptbox" class="ms-2 text-sm font-medium text-gray-900"><a href="#"
                                        class="text-blue-600 dark:text-blue-500 hover:underline">Resep Racik?</a></label>
                            </div>
                        </div>
                    @endif
                    <div class="w-full flex">
                        @if ($transaction->transaction_type == 'KREDIT' || $transaction->transaction_type == 'RESEP TUNAI')
                            <div class="mr-2">
                                <div class="w-full my-1">
                                    <label class="text-[13px] font-poppins font-semibold">Dosis</label>

                                </div>
                                <input id="dosage" name="dosage" readonly type="text" placeholder="Dosis"
                                    class="w-full rounded-md readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 text-[11px] font-poppins"
                                    autocomplete="off" />
                            </div>
                            <div class="mr-2">
                                <div class="w-full my-1">
                                    <label class="text-[13px] font-poppins font-semibold">Bungkus</label>

                                </div>
                                <input type="text" id="package" name="package" readonly placeholder="Bungkus"
                                    class="w-full rounded-md readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 text-[11px] font-poppins"
                                    autocomplete="off" />
                            </div>
                            <div class="mr-2">
                                <div class="w-full my-1">
                                    <label class="text-[13px] font-poppins font-semibold">Dosis R/</label>

                                </div>
                                <input onkeyup="calculatePackage()" id="dosage_r" name="dosage_r" readonly
                                    type="text" placeholder="Dosis Resep"
                                    class="w-full rounded-md readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 text-[11px] font-poppins"
                                    autocomplete="off" />
                            </div>
                        @endif
                        <div class="mr-2 w-[90%]">
                            <div class="w-full my-1">
                                <label class="text-[13px] font-poppins font-semibold">Harga</label>

                            </div>
                            <input id="price" type="text" name="price" readonly placeholder="Harga obat"
                                class="w-full rounded-md readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 text-[11px] font-poppins"
                                autocomplete="off" />
                        </div>
                        <div class="mr-2">
                            <div class="w-full my-1">
                                <label class="text-[13px] font-poppins font-semibold">Qty</label>

                            </div>
                            <input id="quantity" required name="quantity" onkeyup="count(this.value)" type="number"
                                placeholder="QTY"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 text-[11px] font-poppins"
                                autocomplete="off" />
                        </div>
                    </div>
                    {{-- <div class="flex justify-start mt-1 w-full"> --}}

                    {{-- </div> --}}
                    <div class="flex justify-start w-full">
                        <div class="mr-2 w-[40%]">
                            <div class="w-full my-1">
                                <label class="text-[13px] font-poppins font-semibold">Discount</label>

                            </div>
                            <input id="discount" name="discount" onkeyup="countDiscount(this.value)" type="text"
                                placeholder="Discount"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 text-[11px] font-poppins"
                                autocomplete="off" />
                        </div>
                        <div class="mr-2 w-full">
                            <div class="w-full my-1">
                                <label class="text-[13px] font-poppins font-semibold">Total Harga</label>
                            </div>
                            <input type="text" name="total_price" readonly tabindex="-1" id="total"
                                placeholder="Total Harga"
                                class="w-full readonly rounded-xl border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 text-[11px] font-poppins"
                                autocomplete="off" />
                        </div>
                        <div class="mr-2 w-full self-end">
                            <div class="w-full my-1">
                            </div>
                            <button type="button" onclick="submit()"
                                class="btn btn-pos !bg-[##FFC107] p-1 py-3 font-poppin font-bold">
                                Lanjutkan
                            </button>
                        </div>

                    </div>
                </div>
            @endif
            @if ($check_transaction == 1)
                <div class="card p-4 px-6  flex flex-wrap items-center bg-white dashboard-panel">


                    <div class="w-full">
                        <div class="flex flex-wrap gap-4 items-center">

                            {{-- F1 - Pembayaran --}}
                            <div class="flex items-center gap-2">
                                <div
                                    class="py-[5px] bg-[#0074ce] text-white shadow-[0_0_11px_2px_#0097dd] border-none px-[10px] rounded-md">
                                    F1
                                </div>
                                <div class="font-semibold font-poppins text-[11px]">
                                    Pembayaran
                                </div>
                            </div>

                            {{-- F2 - Resep --}}
                            @if ($transaction->transaction_type == 'KREDIT' || $transaction->transaction_type == 'RESEP TUNAI')
                                <div class="flex items-center gap-2">
                                    <div
                                        class="py-[5px] bg-[#16a34a] text-white shadow-[0_0_11px_2px_#22c55e] border-none px-[10px] rounded-md">
                                        F2
                                    </div>
                                    <div class="font-semibold font-poppins text-[11px]">
                                        Resep
                                    </div>
                                </div>
                            @endif

                            {{-- F3 - Pembayaran --}}
                            <div class="flex items-center gap-2">
                                <div
                                    class="py-[5px] bg-[#eab308] text-white shadow-[0_0_11px_2px_#facc15] border-none px-[10px] rounded-md">
                                    F3
                                </div>
                                <div class="font-semibold font-poppins text-[11px]">
                                    Ganti Faktor
                                </div>
                            </div>


                        </div>
                    </div>
                </div>
            @endif
        </section>

        @if ($check_transaction == 1)
            <!-- RIGHT COLUMN -->
            <aside class="col-span-12 lg:col-span-7  dashboard-panel">
                <div class="card px-6 bg-white">
                    <div class="w-full flex">
                        <div class="w-1/2 flex">
                            <div class="mr-2">
                                <div class="w-full my-1">
                                    <label class="text-[13px] font-poppins font-semibold">Faktur</label>

                                </div>
                                @if ($check_transaction == 1)
                                    @if ($transaction->transaction_type == 'KREDIT')
                                        <div id="faktur" class="py-2">
                                            <span
                                                class="bg-yellow-100 text-yellow-800 text-xs font-medium me-2 px-2.5 py-3 rounded-md dark:bg-yellow-900 dark:text-yellow-300">Resep
                                                Kredit</span>
                                        </div>
                                    @elseif($transaction->transaction_type == 'RESEP TUNAI')
                                        <div id="faktur" class="py-2">
                                            <span
                                                class="bg-red-100 text-red-800 text-xs font-medium me-2 px-2.5 py-3 rounded-md dark:bg-red-900 dark:text-red-300">Resep
                                                Tunai</span>
                                        </div>
                                    @elseif($transaction->transaction_type == 'UPDS')
                                        <div id="faktur" class="py-2">
                                            <span
                                                class="bg-green-100 text-green-800 text-xs font-medium me-2 px-2.5 py-3 rounded-md dark:bg-green-900 dark:text-green-300">UPDS</span>
                                        </div>
                                    @elseif($transaction->transaction_type == 'HV/OTC')
                                        <div id="faktur" class="py-2">
                                            <span
                                                class="bg-blue-100 text-blue-800 text-xs font-medium me-2 px-2.5 py-3 rounded-md dark:bg-blue-900 dark:text-blue-300">HV/OTC</span>
                                        </div>
                                    @endif
                                @endif
                            </div>
                            @if ($check_transaction == 1)
                                <div class="mr-2 w-[20%] @if ($transaction->transaction_type != 'RESEP TUNAI' && $transaction->transaction_type != 'KREDIT') hidden @endif">
                                    <div class="w-full my-1">
                                        <label class="text-[13px] font-poppins font-semibold">Racik?</label>

                                    </div>
                                    <input id="dosage_r2" name="dosage_r2" readonly type="text" placeholder="Tidak"
                                        class="w-full rounded-md readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 text-[11px] font-poppins"
                                        autocomplete="off" />
                                </div>
                                <div class="mr-2 w-[35%] @if ($transaction->transaction_type != 'RESEP TUNAI' && $transaction->transaction_type != 'KREDIT') hidden @endif">
                                    <div class="w-[full] my-1">
                                        <label class="text-[13px] font-poppins font-semibold">Jasa</label>

                                    </div>
                                    <input type="text" onkeyup="countEmbalase(this.value)" id="jasa"
                                        name="jasa" placeholder="Jasa"
                                        class="w-full rounded-md  border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300 text-[11px] font-poppins"
                                        autocomplete="off" />
                                </div>
                            @endif
                        </div>
                        <div class="w-1/2">
                            <div class="flex gap-2 justify-end flex-col w-full">
                                <div class="mr-2 flex items-center justify-end">

                                    <div class="w-[40%] text-right">
                                        <label class="text-[13px] font-poppins font-semibold text-right pr-2">Total
                                            Biaya</label>

                                    </div>
                                    <div class="w-[100%]">
                                        <input type="text" readonly id="payment_total" name="payment_total"
                                            placeholder="Total"
                                            class="w-full text-right rounded-md text-[20px] font-bold readonly border border-gray-300 bg-white px-3 py-2  focus:outline-none focus:ring-2 focus:ring-gray-300  font-poppins"
                                            autocomplete="off" />
                                    </div>
                                </div>

                                <div class="mr-2 flex items-center justify-end">

                                    <div class="w-[40%] text-right">
                                        <label class="text-[13px] font-poppins font-semibold text-right pr-2">Total
                                            Diskon</label>

                                    </div>
                                    <div class="w-[100%]">
                                        <input type="text" readonly id="discount_total" name="discount_total"
                                            placeholder="Total"
                                            class="w-full text-right rounded-md text-[20px] font-bold readonly border border-gray-300 bg-white px-3 py-2  focus:outline-none focus:ring-2 focus:ring-gray-300  font-poppins"
                                            autocomplete="off" />
                                    </div>
                                </div>

                                <div class="mr-2 flex items-center justify-end">

                                    <div class="w-[40%] text-right">
                                        <label class="text-[13px] font-poppins font-semibold text-right pr-2">Total
                                            Beli</label>
                                    </div>

                                    <div class="w-[100%]">
                                        <input type="text" readonly id="price2" name="price2" placeholder="Total"
                                            class="w-full text-right rounded-md text-[20px] font-bold readonly border border-gray-300 bg-white px-3 py-2  focus:outline-none focus:ring-2 focus:ring-gray-300  font-poppins"
                                            autocomplete="off" />
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <h2 class="text-xl font-semibold mb-3 mt-2">Barang Dibeli</h2>
                    <div class="mt-4 rounded-2xl bg-gray-100 h-[40vh] overflow-y-scroll md:h-[53vh]">
                        <div class="flex flex-col justify-between">
                            <table class="min-w-full text-sm text-left font-poppins text-gray-700">
                                <thead class="bg-blue-50 text-gray-600 uppercase text-xs border-b border-blue-200">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-center w-[40px]">No</th>
                                        <th scope="col" colspan="7" class="px-4 py-3">Nama Obat</th>
                                        <th scope="col" class="px-2 py-3 text-center">Satuan</th>
                                        <th scope="col" class="px-4 py-3 text-center">Harga</th>
                                        <th scope="col" class="px-4 py-3 text-center">Qty</th>
                                        <th scope="col" class="px-4 py-3 text-center">Diskon</th>
                                        <th scope="col" class="px-4 py-3 text-center">Subtotal</th>
                                        @if ($transaction->transaction_type == 'KREDIT' || $transaction->transaction_type == 'RESEP TUNAI')
                                            <th scope="col" class="px-4 py-3 text-center">Jasa</th>
                                        @endif
                                        <th scope="col" class="px-4 py-3 text-center">Total</th>
                                        <th scope="col" class="px-4 py-3 text-center">Tipe</th>
                                    </tr>
                                </thead>
                                <tbody id="carts">
                                    @foreach ($itemInCart as $index => $cart)
                                        @php

                                            $raw = $cart->medicine->net_price * $parameters + $rounding;
                                            $rounded = floor($raw / 1000) * 1000;

                                        @endphp
                                        <tr id="itemincart{{ $cart->id }}" data-id="{{ $cart->id }}"
                                            class="cart-row border-b hover:bg-blue-50 transition text-[10px] cursor-pointer">
                                            <td class="px-1 py-1 text-center text-gray-600">{{ $index + 1 }}</td>
                                            <td colspan="7"
                                                class="text-[10px] leading-normal px-1 py-1 font-semibold text-gray-800">
                                                {{ $cart->medicine->name }}
                                            </td>
                                            <td class="px-1 py-1 text-center">{{ $cart->medicine->unit }}</td>
                                            <td class="px-1 py-1 text-center">Rp
                                                {{ number_format($rounded, 0, ',', '.') }}</td>
                                            <td class="px-1 py-1 text-center">{{ $cart->quantity }}</td>
                                            <td class="px-1 py-1 text-center">
                                                Rp.{{ number_format($cart->discount, 0, ',', ',') }}</td>
                                            <td class="px-1 py-1 text-center">Rp
                                                {{ number_format($cart->total_price, 0, ',', '.') }}</td>
                                            @if ($transaction->transaction_type == 'KREDIT' || $transaction->transaction_type == 'RESEP TUNAI')
                                                <td class="clEmbalase px-1 py-1 text-center">Rp
                                                    {{ number_format($cart->embalase, 0, ',', '.') }}</td>
                                            @endif
                                            <td class="clFinalprice px-1 py-1 text-center font-semibold text-blue-600">
                                                Rp {{ number_format($cart->final_price, 0, ',', '.') }}
                                            </td>
                                            <td class="px-1 py-1 text-center font-semibold text-blue-600">
                                                {{ $cart->cart_type }}
                                            </td>

                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            {{-- @foreach ($itemInCart as $cart)
                                    <div id="itemincart{{ $cart->id }}">

                                    </div>
                                @endforeach --}}
                        </div>
                        <br>
                    </div>
                </div>
                <div class="button-container flex gap-2 mt-3 mx-auto w-[95%]">
                    <div class="w-[50%]">
                        <form method="POST" action="{{ route('sales.deletetransaction') }}">
                            @csrf
                            <input type="hidden" name="trxtype"
                                value="{{ $transaction?->transaction_type ?? 'null' }}">
                            <input type="hidden" name="trxid" value="{{ $transaction?->id ?? 'null' }}">
                            <button type="submit" id="deletebtn"
                                class="py-[15px] mt-2 w-full font-poppins bg-[#e95050] text-white shadow-[0_0_11px_2px_#e95050] border-none px-[16px] rounded-md transition">
                                Batal
                            </button>
                        </form>
                    </div>
                    <button id="openModalPayment"
                        class="py-[15px] mt-2 w-full font-poppins bg-[#0074ce] text-white shadow-[0_0_11px_2px_#0097dd] border-none px-[16px] rounded-md transition">
                        Pembayaran
                    </button>

                </div>
        @endif
    </div>
    </aside>
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
                    <h4>Apotek Sahabat</h4>
                    <p class="small-text">Jl. Palang Merah Indonesia</p>
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

                <div>
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="invoiceTotal">
                            <tr>
                                <td>Shawarma Big</td>
                                <td>4</td>
                                <td>$12</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="divider"></div>

                <div id="paymentstotal" class="payment">
                    <p id="paid">📧 info@example.com</p>
                    <p id="change">📞 +234XXXXXXXX</p>
                </div>

                <div class="divider"></div>

                <div class="button-row">
                    <button class="btn btn-gray" onclick="closeInvoice()">Tutup</button>
                    <button class="btn btn-blue" onclick="printInvoice()">Cetak & Selesai</button>
                </div>
            </div>
        </div>
    </div>
    {{-- ============================================================== Modal Invoice  ============================================================== --}}

    {{-- ============================================================== Patient Invoice  ============================================================== --}}
    <div id="newPatientModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6 relative" id="newPatientModalContent">
            <h3 class="text-lg font-semibold mb-4">Tambah Pasien Baru</h3>
            <form id="newPatientForm" method="POST" class="space-y-3">

                <div>
                    <label for="patientName" class="block mb-1 text-sm font-medium text-gray-700">Nama</label>
                    <input id="patientName" type="text" name="name" placeholder="Nama"
                        class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300">
                </div>

                <div>
                    <label for="patientAddress" class="block mb-1 text-sm font-medium text-gray-700">Alamat</label>
                    <input id="patientAddress" type="text" name="address" placeholder="Alamat"
                        class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300">
                </div>

                <div>
                    <label for="patientPhone" class="block mb-1 text-sm font-medium text-gray-700">No. Telepon</label>
                    <input id="patientPhone" type="text" name="phone" placeholder="No. Telepon"
                        class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300">
                </div>

                <div>
                    <label for="patientCity" class="block mb-1 text-sm font-medium text-gray-700">Kota</label>
                    <input id="patientCity" type="text" name="city" placeholder="Kota"
                        class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300">
                </div>

                <div>
                    <label for="patientBirth" class="block mb-1 text-sm font-medium text-gray-700">Tanggal Lahir</label>
                    <input id="patientBirth" type="date" name="birth"
                        class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300">
                </div>

                <div class="flex justify-end space-x-2 mt-4">
                    <button type="button" id="closeNewPatientModal" class="px-4 py-2 bg-gray-300 rounded">Batal</button>
                    <button type="submit"
                        class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    @if ($check_transaction == 1)
        {{-- Modal Pembayaran --}}
        <div id="paymentModal" class="fixed inset-0 bg-black/50 hidden justify-center items-center z-40">
            <!-- Modal content -->
            <div class="bg-white w-full max-w-3xl rounded-xl shadow-xl p-6 relative overflow-y-auto max-h-[90vh]">

                <!-- Close button -->
                <button id="closeModal"
                    class="absolute top-3 right-3 text-gray-500 hover:text-gray-800 text-xl font-bold">
                    ×
                </button>

                <!-- === Your existing form section === -->
                <div class="mr-2 w-[100%]">
                    <div class="flex items-end justify-between md:block py-2">
                        <h1 class="text-4xl font-semibold tracking-tight">Pembayaran</h1>

                    </div>
                    @if ($check_transaction == 1)
                        <div class="w-full">
                            <label class="text-[13px] font-poppins font-semibold">Cari Pasien</label>

                            <div class="flex items-center">
                                <div class="searchdebtors w-full">
                                    <input autofocus required id="patientSearch" type="text"
                                        placeholder="Ketik ID / Nama…"
                                        class="w-full rounded-xl border my-1 border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                        autocomplete="off" />
                                    <!-- Dropdown -->
                                    <div id="patientResults"
                                        class="absolute z-50 mt-2 w-[50%] rounded-xl border border-gray-200 bg-white shadow-lg hidden">
                                        <ul id="patientList" role="listbox" class="max-h-80 overflow-auto py-2">
                                        </ul>
                                    </div>
                                </div>

                                <div class="adddebtors w-[120px] ml-[10px]">
                                    <button type="button" id="btnNewPatient"
                                        class="rounded-[10px] border border-[#62b9ff] px-3 py-2 font-poppins font-bold text-[#62b9ff] flex items-center justify-center gap-2 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                            class="w-6 h-6 text-[#62b9ff]">
                                            <circle cx="15" cy="8" r="4" fill="currentColor" />
                                            <path d="M15,14c-6.1,0-8,4-8,4v2h16v-2C23,18,21.1,14,15,14z"
                                                fill="currentColor" />
                                            <line stroke="currentColor" stroke-miterlimit="10" stroke-width="2"
                                                x1="5" x2="5" y1="7" y2="15" />
                                            <line stroke="currentColor" stroke-miterlimit="10" stroke-width="2"
                                                x1="9" x2="1" y1="11" y2="11" />
                                        </svg>
                                        Baru
                                    </button>
                                </div>
                                <input type="hidden" id="selectedPatientId" />
                            </div>
                        </div>
                        @if ($transaction->transaction_type == 'KREDIT' || $transaction->transaction_type == 'RESEP TUNAI')
                            <div class="w-full mt-3">
                                <label class="text-[13px] font-poppins font-semibold pb-1">Cari Dokter</label>
                                <div class="flex items-center">
                                    <div class="searchdoctors w-full">
                                        <input autofocus required id="doctorSearch" type="text"
                                            placeholder="Ketik ID / Nama…"
                                            class="w-full rounded-xl border my-1 border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                            autocomplete="off" />
                                        <!-- Dropdown -->
                                        <div id="doctorResults"
                                            class="absolute z-50 mt-2 w-[50%] rounded-xl border border-gray-200 bg-white shadow-lg hidden">
                                            <ul id="doctorList" role="listbox" class="max-h-80 overflow-auto py-2">
                                            </ul>
                                        </div>
                                    </div>
                                    <input type="hidden" id="selectedDoctorId" />
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

                <div class="mr-2 w-[100%] mt-3 flex gap-2">
                    @if ($check_transaction == 1)
                        @if ($transaction->transaction_type == 'KREDIT')
                            <div class="w-[40%]">
                                <label class="text-[13px] font-poppins font-semibold pb-1">Embalase</label>
                                <input id="embalase" tabindex="-1" readonly type="text" name="embalase"
                                    placeholder="Embalase"
                                    class="w-full rounded-xl my-1 readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                    autocomplete="off" />
                            </div>
                        @endif
                    @endif
                    <div class="w-full gap-2 flex">
                        <div class="w-full">
                            <label class="text-[13px] font-poppins font-semibold pb-1">Total</label>
                            <input id="carttotal" tabindex="-1" readonly type="text" name="carttotal"
                                placeholder="Total obat"
                                class="w-full rounded-xl my-1 readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                autocomplete="off" />
                        </div>
                        <div class="w-full">
                            <label class="text-[13px] font-poppins font-semibold pb-1">Discount</label>
                            <input onkeyup="countSubtotalDiscount(this.value)" id="discounsubtotal" tabindex="-1"
                                type="number" name="discounsubtotal" placeholder="Discount"
                                class="w-full rounded-xl my-1 border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                autocomplete="off" />
                        </div>
                    </div>
                </div>

                <div class="mr-2 flex gap-2 w-[100%] mt-3">
                    <div class="w-full">
                        <label class="text-[13px] font-poppins font-semibold pb-1">Bayar</label>
                        <input id="pay" onkeyup="pay(this.value)" type="text" name="pay"
                            placeholder="Bayar obat"
                            class="w-full rounded-xl border my-1 border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                            autocomplete="off" />
                    </div>
                    <div class="w-full">
                        <label class="text-[13px] font-poppins font-semibold pb-1">Kembalian</label>
                        <input id="trchange" tabindex="-1" readonly type="text" name="change"
                            placeholder="Bayar obat"
                            class="w-full rounded-xl border my-1 readonly border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                            autocomplete="off" />
                    </div>
                </div>

                <div class="mt-5">
                    <form id="checkoutForm" action="{{ route('transaction.checkout') }}" method="POST">
                        @csrf
                        <input type="hidden" name="paid" id="paid">
                        <input type="hidden" name="changes" id="changes">
                        <input type="hidden" name="transaction_id" id="transaction_id">
                        <input type="hidden" required name="patient_id" id="patient_id" />
                        <input type="hidden" @if ($transaction->transaction_type == 'RESEP TUNAI') value="0" @endif required
                            name="doctor_id" id="doctor_id" />
                        <input type="hidden" required name="debtor_id" id="debtor_id" />

                        <button type="button" id="checkout" disabled onclick="checkoutItem()"
                            class="w-full mt-3 rounded-lg bg-gray-400 hover:bg-gray-500 text-white font-semibold py-4 transition">
                            Selesaikan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================== Patient Invoice  ============================================================== --}}

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        // WHEN PAGE LOADED

        document.addEventListener('DOMContentLoaded', () => {
            // Attach events to all existing rows
            document.querySelectorAll('.cart-row').forEach(row => {
                attachRowEvents(row);
            });
        });
        // ===============================
        // Konstanta & Variabel Global
        // ===============================

        const faktur = document.getElementById('faktur');



        // Patien Modal Variable
        const newPatientModal = document.getElementById('newPatientModal');
        const newPatientModalContent = document.getElementById('newPatientModalContent');
        const openNewPatientBtn = document.getElementById('btnNewPatient');
        const closeNewPatientBtn = document.getElementById('closeNewPatientModal');
        const newPatientForm = document.getElementById('newPatientForm');


        // Endpoints
        const endpoint = "{{ route('products.search') }}";
        const endpointDebtor = "{{ route('debtors.search') }}";
        const endpointPatient = "{{ route('patients.search') }}";
        const endpointDoctor = "{{ route('doctors.search') }}";


        const trx_id = {{ $transaction?->id ?? 'null' }};
        var rounding = {{ $rounding }};
        var parameters = {{ $parameters }};
        var totaltransaction = {{ $totaltransaction }};
        var transaction_type = "{{ $transaction?->transaction_type ?? 'null' }}";

        // Elemen DOM
        const input = document.getElementById('productSearch');
        const name = document.getElementById('name');
        const stock = document.getElementById('stock');
        const unit = document.getElementById('unit');
        const quantity = document.getElementById('quantity');
        const dosage = document.getElementById('dosage');
        const price = document.getElementById('price');
        const totalprice = document.getElementById('total');
        const discountInput = document.getElementById('discount');
        const cartTotalInput = document.getElementById('carttotal');
        const box = document.getElementById('productResults');
        const list = document.getElementById('productList');
        const hidden = document.getElementById('selectedProductId');

        // Debtor
        const inputdebtor = document.getElementById('debtorSearch');
        const debtorname = document.getElementById('debtorname');
        const debtorbox = document.getElementById('debtorResults');
        const debtorlist = document.getElementById('debtorList');
        const debtorhidden = document.getElementById('selectedDebtorId');
        let debtoritems = [];


        // Patient
        const inputpatient = document.getElementById('patientSearch');
        const patientname = document.getElementById('patientname');
        const patientbox = document.getElementById('patientResults');
        const patientlist = document.getElementById('patientList');
        const patienthidden = document.getElementById('selectedPatientId');

        // Doctor
        const inputdoctor = document.getElementById('doctorSearch');
        const doctorname = document.getElementById('doctorname');
        const doctorbox = document.getElementById('doctorResults');
        const doctorlist = document.getElementById('doctorList');
        const doctorhidden = document.getElementById('selectedDoctorId');


        // Dosage
        const checkbox = document.getElementById('receiptbox');
        const dosageInput = document.getElementById('dosage');
        const dosageRInput = document.getElementById('dosage_r');
        const packageInput = document.getElementById('package');

        // Variabel Transaksi & Cart
        var transaction_id = trx_id;
        var total_discount = {{ $discount_total }};
        var discount = "";
        var subtotal_discount = "";
        var total_item = "";
        var medicine_id = "";
        var price2 = "";
        var item_finalprice = "";
        let grossprice = "";
        var payInput = document.getElementById('pay');
        var payment_total = document.getElementById('payment_total');
        var edit_status = 0;
        // var debtor_id = "";
        // var patient_id = "";
        // var doctor_id = "";

        let totalbought = {{ $rawtotal }};
        var subtotal = "";
        var final_price = "";
        let items = [];
        let activeIndex = -1;
        let closeTimeout;
        let true_price = "";
        var embalase = 0;
        var jasa = "";
        var cart_type = "";
        let existingpackage = {!! json_encode($existingpackage->package ?? '') !!};
        var currenttransaction = transaction_type;

        var racikstatus = 0;
        var racikembalaseprice = 0;
        var previewdiscounttotal = document.getElementById('discount_total');
        var previewtransactiontotal = document.getElementById('price2');


        // Set nilai awal
        cartTotalInput.value = formatRupiah(totaltransaction);
        payment_total.value = formatRupiah(totalbought);


        previewdiscounttotal.value = formatRupiah(total_discount);
        previewtransactiontotal.value = formatRupiah(totaltransaction);
        if (packageInput) {
            document.getElementById('package').value = existingpackage;
        }



        // Table

        let selectedRowId = null;


        // ===============================
        // Helper Functions
        // ===============================
        function formatRupiah(value) {
            const number = Number(value) || 0;
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(number);
        }

        function roundUpToNearestThousand(value) {
            // remove everything except digits and comma
            const num = parseInt(String(value).replace(/\D/g, ""), 10) || 0;
            return Math.ceil(num / 1000) * 1000;
        }

        function parseRupiah(rupiahString) {
            return Number(
                rupiahString.replace(/[^0-9,-]/g, '').replace(',', '.')
            ) || 0;
        }

        function debounce(fn, wait = 250) {
            let t;
            return (...args) => {
                clearTimeout(t);
                t = setTimeout(() => fn(...args), wait);
            };
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

        // ===============================
        // Autocomplete Box Control
        // ===============================
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
                    selectItem(it);
                    e.preventDefault();
                });

                list.appendChild(li);
            }
        }

        function selectItem(it) {
            hidden.value = it.id;
            medicine_id = it.id;
            input.value = '';
            stock.value = it.stock;
            unit.value = it.unit;
            name.value = it.name;
            if (currenttransaction == 'KREDIT' || currenttransaction == 'RESEP TUNAI') {
                dosage.value = it.dosage;
            }
            console.log("Harga : " + it.net_price + "Parameter : " + parameters + "Pembulatan : " + rounding);
            let raw = (+it.net_price * +parameters) + +rounding;
            let rounded = Math.floor(raw / 1000) * 1000;
            price.value = formatRupiah(rounded);
            console.log("harga Total : " + raw);
            price2 = rounded;
            item_finalprice = rounded;
            if (currenttransaction == 'RESEP TUNAI' && racikstatus == 1) {
                dosageRInput.focus();
                closeBox();

            } else {
                quantity.focus();
                closeBox();

            }
        }
        // ===============================
        // Search (Debounced)
        // ===============================
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
        input.addEventListener('input', (e) => doSearch(e.target.value));

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
            } else if (e.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
                e.preventDefault();
                selectItem(items[activeIndex]);
            } else if (e.key === 'Escape') {
                closeBox();
            }
        });


        // ===============================
        // Pencarian Pasien
        // ===============================

        function openpatientBox() {
            patientbox.classList.remove('hidden');
        }

        function closepatientBox() {
            patientbox.classList.add('hidden');
            activeIndex = -1;
            highlight();
        }

        function patienthighlight() {
            [...patientlist.children].forEach((li, i) => {
                li.classList.toggle('bg-gray-100', i === activeIndex);
            });
        }

        function ensurepatientVisible() {
            const li = patientlist.children[activeIndex];
            if (!li) return;
            const lTop = patientlist.scrollTop;
            const lBottom = lTop + patientlist.clientHeight;
            const liTop = li.offsetTop;
            const liBottom = liTop + li.offsetHeight;

            if (liTop < lTop) patientlist.scrollTop = liTop;
            else if (liBottom > lBottom) patientlist.scrollTop = liBottom - patientlist.clientHeight;
        }

        function renderpatient(items) {
            patientlist.innerHTML = '';
            if (!items.length) {
                patientlist.innerHTML = `<li class="px-4 py-3 text-sm text-gray-500">Tidak ada hasil</li>`;
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
                            <div class="font-bold font-poppins text-[#E91E63] capitalize">${escapeHtml(it.name)}</div>
                            <div class="text-xs pb-1 text-gray-500">
                                <b>Kode:</b> ${escapeHtml(it.code)}
                            </div>
                            <div class="text-xs pb-1 text-gray-500">
                                <b>Alamat:</b> ${escapeHtml(it.address)}
                            </div>
                            <div class="text-xs pb-1 text-gray-500">
                                <b>Phone:</b> ${escapeHtml(it.phone)}
                            </div>
                        </div>
                    </div>
                `;

                li.addEventListener('mousedown', (e) => {
                    selectPatient(it);
                    e.preventDefault();
                });

                patientlist.appendChild(li);
            }
        }

        // ===============================
        // Search (Debounced)
        // ===============================
        const dopatientSearch = debounce(async (term) => {
            if (!term.trim()) {
                patientlist.innerHTML = '';
                closepatientBox();
                return;
            }
            const url = `${endpointPatient}?q=${encodeURIComponent(term)}`;
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json'
                }
            });
            if (!res.ok) return;

            items = await res.json();
            renderpatient(items);
            openpatientBox();
        }, 250);
        if (inputpatient) {
            inputpatient.addEventListener('input', (e) => dopatientSearch(e.target.value));

            inputpatient.addEventListener('keydown', (e) => {
                const max = items.length - 1;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (max < 0) return;
                    activeIndex = Math.min(max, activeIndex + 1);
                    patienthighlight();
                    ensurepatientVisible();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (max < 0) return;
                    activeIndex = Math.max(0, activeIndex - 1);
                    patienthighlight();
                    ensurepatientVisible();
                } else if (e.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
                    e.preventDefault();
                    selectPatient(items[activeIndex]);
                } else if (e.key === 'Escape') {
                    closepatientBox();
                }
            });
        }

        function selectPatient(it) {
            document.getElementById('patient_id').value = it.id;
            if (currenttransaction == 'RESEP TUNAI' || currenttransaction == 'KREDIT') {
                document.getElementById('doctorSearch').focus();
            } else {
                document.getElementById('pay').focus();
            }
            document.getElementById('patientSearch').value = it.name;
            closepatientBox();
        }


        // ===============================
        // Pencarian Dokter
        // ===============================

        function opendoctorBox() {
            doctorbox.classList.remove('hidden');
        }

        function closedoctorBox() {
            doctorbox.classList.add('hidden');
            activeIndex = -1;
            highlight();
        }

        function doctorhighlight() {
            [...doctorlist.children].forEach((li, i) => {
                li.classList.toggle('bg-gray-100', i === activeIndex);
            });
        }

        function ensuredoctorVisible() {
            const li = doctorlist.children[activeIndex];
            if (!li) return;
            const lTop = doctorlist.scrollTop;
            const lBottom = lTop + doctorlist.clientHeight;
            const liTop = li.offsetTop;
            const liBottom = liTop + li.offsetHeight;

            if (liTop < lTop) doctorlist.scrollTop = liTop;
            else if (liBottom > lBottom) doctorlist.scrollTop = liBottom - doctorlist.clientHeight;
        }

        function renderdoctor(items) {
            doctorlist.innerHTML = '';
            if (!items.length) {
                doctorlist.innerHTML = `<li class="px-4 py-3 text-sm text-gray-500">Tidak ada hasil</li>`;
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
                            <div class="font-bold font-poppins text-[#E91E63] capitalize">${escapeHtml(it.name)}</div>
                            <div class="text-xs pb-1 text-gray-500">
                                <b>Kode:</b> ${escapeHtml(it.code)}
                            </div>
                            <div class="text-xs pb-1 text-gray-500">
                                <b>Alamat:</b> ${escapeHtml(it.address)}
                            </div>
                            <div class="text-xs pb-1 text-gray-500">
                                <b>Phone:</b> ${escapeHtml(it.phone)}
                            </div>
                        </div>
                    </div>
                `;

                li.addEventListener('mousedown', (e) => {
                    selectDoctor(it);
                    e.preventDefault();
                });

                doctorlist.appendChild(li);
            }
        }

        // ===============================
        // Search (Debounced)
        // ===============================
        const dodoctorSearch = debounce(async (term) => {
            if (!term.trim()) {
                doctorlist.innerHTML = '';
                closedoctorBox();
                return;
            }
            const url = `${endpointDoctor}?q=${encodeURIComponent(term)}`;
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json'
                }
            });
            if (!res.ok) return;

            items = await res.json();
            renderdoctor(items);
            opendoctorBox();
        }, 250);
        if (inputdoctor) {
            inputdoctor.addEventListener('input', (e) => dodoctorSearch(e.target.value));

            inputdoctor.addEventListener('keydown', (e) => {
                const max = items.length - 1;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (max < 0) return;
                    activeIndex = Math.min(max, activeIndex + 1);
                    doctorhighlight();
                    ensuredoctorVisible();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (max < 0) return;
                    activeIndex = Math.max(0, activeIndex - 1);
                    doctorhighlight();
                    ensuredoctorVisible();
                } else if (e.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
                    e.preventDefault();
                    selectDoctor(items[activeIndex]);
                } else if (e.key === 'Escape') {
                    closedoctorBox();
                }
            });
        }

        function selectDoctor(it) {

            if (currenttransaction == 'RESEP TUNAI') {
                document.getElementById('pay').focus();
            } else if (currenttransaction == 'KREDIT') {
                document.getElementById('debtorSearch').focus();
            }
            document.getElementById('doctor_id').value = it.id;
            document.getElementById('doctorSearch').value = it.name;


            closedoctorBox();
        }


        // ===============================
        // Pencarian Debitur
        // ===============================

        if (debtorbox) {
            function opendebtorBox() {
                debtorbox.classList.remove('hidden');
            }

            function closedebtorBox() {
                debtorbox.classList.add('hidden');
                activeIndex = -1;
                highlight();
            }

            function debtorhighlight() {
                [...debtorlist.children].forEach((li, i) => {
                    li.classList.toggle('bg-gray-100', i === activeIndex);
                });
            }

            function ensuredebtorVisible() {
                const li = debtorlist.children[activeIndex];
                if (!li) return;
                const lTop = debtorlist.scrollTop;
                const lBottom = lTop + debtorlist.clientHeight;
                const liTop = li.offsetTop;
                const liBottom = liTop + li.offsetHeight;

                if (liTop < lTop) debtorlist.scrollTop = liTop;
                else if (liBottom > lBottom) debtorlist.scrollTop = liBottom - debtorlist.clientHeight;
            }

            function renderdebtor(debtoritems) {
                debtorlist.innerHTML = '';
                if (!debtoritems.length) {
                    debtorlist.innerHTML = `<li class="px-4 py-3 text-sm text-gray-500">Tidak ada hasil</li>`;
                    return;
                }

                for (const it of debtoritems) {
                    const li = document.createElement('li');
                    li.setAttribute('role', 'option');
                    li.className = 'cursor-pointer px-4 py-3 hover:bg-gray-100';
                    li.dataset.id = it.id;

                    li.innerHTML = `
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-medium">${escapeHtml(it.name)}</div>
                            <div class="text-xs text-gray-500">
                                Kode: ${escapeHtml(it.code)}
                            </div>
                            <div class="text-xs text-gray-500">
                                Alamat: ${escapeHtml(it.address)}
                            </div>
                        </div>
                    </div>
                `;

                    li.addEventListener('mousedown', (e) => {
                        selectDebtor(it);
                        e.preventDefault();
                    });

                    debtorlist.appendChild(li);
                }
            }

            // ===============================
            // Search (Debounced)
            // ===============================
            const dodebtorSearch = debounce(async (term) => {
                if (!term.trim()) {
                    debtorlist.innerHTML = '';
                    closedebtorBox();
                    return;
                }
                const url = `${endpointDebtor}?q=${encodeURIComponent(term)}`;
                const res = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                if (!res.ok) return;

                debtoritems = await res.json();
                renderdebtor(debtoritems);
                opendebtorBox();
            }, 250);
            if (inputdebtor) {
                inputdebtor.addEventListener('input', (e) => dodebtorSearch(e.target.value));

                inputdebtor.addEventListener('keydown', (e) => {
                    const max = debtoritems.length - 1;

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (max < 0) return;
                        activeIndex = Math.min(max, activeIndex + 1);
                        debtorhighlight();
                        ensuredebtorVisible();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (max < 0) return;
                        activeIndex = Math.max(0, activeIndex - 1);
                        debtorhighlight();
                        ensuredebtorVisible();
                    } else if (e.key === 'Enter' && activeIndex >= 0 && debtoritems[activeIndex]) {
                        e.preventDefault();
                        selectDebtor(debtoritems[activeIndex]);
                    } else if (e.key === 'Escape') {
                        closedebtorBox();
                    }
                });
            }

            function selectDebtor(it) {
                debtorname.value = it.name;
                debtorSearch.value = it.name;

                parameters = it.parameters[0].receipt;
                rounding = it.parameters[0].rounding;
                document.getElementById('debtor_id').value = it.id;
                console.log(rounding);
                if (transaction_type == 'KREDIT') {
                    input.focus();
                } else {
                    discountInput.focus();
                }
                document.getElementById('debtorSearch').value = it.name;
                document.getElementById('embalase').value = it.parameters[0].embalas;;


                closedebtorBox();
            }
        }
        // ===============================
        // Perhitungan Harga & Diskon
        // ===============================
        function count(val) {
            console.log('harga diskon adalah : ' + discount);
            if (discount == "") {
                discount = 0;
            }
            console.log('harganya itu adalah : ' + price2);
            total_item = val;
            subtotal = price2 * val - discount;
            totalprice.value = formatRupiah(subtotal);
            pharmacy_price = price2 * val;
            final_price = subtotal;
            grossprice = subtotal;
            console.log("count() => subtotal:", subtotal, "grossprice:", grossprice, "pharmacy_price price :",
                pharmacy_price);

        }

        function countDiscount(val) {
            if (val > 100) {
                final_price = subtotal - val;
                discount = val;
            } else {
                const discountAmount = subtotal * val / 100;
                final_price = subtotal - discountAmount;
                discount = discountAmount; 
            }

            final_price = Math.ceil(final_price / 1000) * 1000;
            totalprice.value = formatRupiah(final_price);
        }

        function countSubtotalDiscount(val) {
            let final_price;

            if (val > 100) {
                final_price = totaltransaction - val;
                subtotal_discount = val;
            } else {
                const d = totaltransaction * val / 100;
                final_price = totaltransaction - d;
                subtotal_discount = `${val}%`;
            }
            totaltransaction = final_price;
            // Round the final_price up to the nearest 1000
            final_price = Math.ceil(final_price / 1000) * 1000;

            cartTotalInput.value = formatRupiah(final_price);
        }

        // ===============================
        // Cart Actions
        // ===============================
        axios.defaults.headers.common['X-CSRF-TOKEN'] = '{{ csrf_token() }}';

        function resetInputs() {
            const ids = ['pay', 'change', 'quantity', 'dosage', 'dosage_r'];

            ids.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = "";
            });

            const pkg = document.getElementById('package');
            if (pkg) pkg.focus();

        }

        function addToCart(medicine_id, transaction_id, quantity, discount, embalase, cart_type, package, dosage_r,
            raw_total, total_price, final_price, racikstatus) {
            if (edit_status == 1) {
                axios.post("{{ route('transaction.updateCart') }}", {
                        id: selectedRowId,
                        medicine_id,
                        transaction_id,
                        quantity,
                        discount,
                        embalase,
                        cart_type,
                        package,
                        dosage_r,
                        raw_total,
                        total_price,
                        final_price,
                        grossprice,
                        racikstatus,
                    })
                    .then(response => {

                        const item = response.data.item;
                        totaltransaction = response.data.total_transaction;
                        total_discount = response.data.total_discount;
                        totalbought = response.data.totalbought;

                        console.log("✅ Updated cart item:", item);
                        // Reset Inputs and variables value
                        [stock, unit, quantity, price, name, totalprice].forEach(el => el.value = "");
                        discountInput.value = "";
                        discount = 0;

                        resetInputs();
                        document.getElementById('productSearch').focus();
                        closeBox();
                        cartTotalInput.value = formatRupiah(totaltransaction);
                        previewdiscounttotal.value = formatRupiah(total_discount);
                        previewtransactiontotal.value = formatRupiah(totaltransaction);
                        payment_total.value = formatRupiah(totalbought);

                        const existingRow = document.getElementById(`itemincart${item.id}`);

                        if (existingRow) {
                            //   bakcup
                            existingRow.innerHTML = `
                            <td class="px-1 py-1 text-center text-gray-600">${existingRow.rowIndex}</td>
                            <td colspan="7" class="leading-normal text-[10px] px-1 py-1 font-semibold text-gray-800">
                                ${item.medicine.name}
                            </td>
                            <td class="px-1 py-1 text-center">${item.medicine.unit}</td>
                            <td class="px-1 py-1 text-center">${formatRupiah(item_finalprice)}</td>
                            <td class="px-1 py-1 text-center">${item.quantity}</td>
                            <td class="px-1 py-1 text-center">${formatRupiah(item.discount)}</td>
                            <td class="px-1 py-1 text-center">${formatRupiah(item.total_price)}</td>
                            ${(currenttransaction === 'RESEP TUNAI' || currenttransaction === 'KREDIT')
                                ? `<td class="px-1 py-1 text-center clEmbalase">${formatRupiah(item.embalase)}</td>`
                                : ''
                            }
                            <td class="px-1 py-1 text-center clFinalprice font-semibold text-blue-600">
                                ${formatRupiah(item.final_price)}
                            </td>
                            <td class="px-1 py-1 text-center font-semibold text-blue-600">${item.cart_type}</td>
                        `;
                        } else {
                            // If not found (failsafe)
                            console.warn("⚠️ Row not found, inserting new one");
                            document.getElementById('carts').insertAdjacentHTML('beforeend', `
                            <tr id="itemincart${item.id}" data-id="${item.id}" class="cart-row border-b hover:bg-blue-50 transition text-[10px] cursor-pointer">
                                <td class="px-1 py-1 text-center text-gray-600">${document.querySelectorAll('#carts tr').length + 1}</td>
                                <td colspan="7" class="leading-normal text-[10px] px-1 py-1 font-semibold text-gray-800">${item.medicine.name}</td>
                                <td class="px-1 py-1 text-center">${item.medicine.unit}</td>
                                <td class="px-1 py-1 text-center">${formatRupiah(item.final_price)}</td>
                                <td class="px-1 py-1 text-center">${item.quantity}</td>
                                <td class="px-1 py-1 text-center">${formatRupiah(item.discount)}</td>
                                <td class="px-1 py-1 text-center">${formatRupiah(item.total_price)}</td>
                                ${(currenttransaction === 'RESEP TUNAI' || currenttransaction === 'KREDIT')
                                    ? `<td class="px-1 py-1 text-center clEmbalase">${formatRupiah(item.embalase)}</td>`
                                    : ''
                                }
                                <td class="px-1 py-1 text-center clFinalprice font-semibold text-blue-600">${formatRupiah(item.final_price)}</td>
                                <td class="px-1 py-1 text-center font-semibold text-blue-600">${item.cart_type}</td>
                            </tr>
                        `);
                        }

                        // Reattach event listeners if needed
                        attachRowEvents(document.getElementById(`itemincart${item.id}`));
                    })
                    .catch(error => {
                        console.error("❌ Error updating cart:", error.response ? error.response.data : error.message);
                    });
            } else {
                axios.post("{{ route('transaction.addToCart') }}", {
                    medicine_id,
                    transaction_id,
                    quantity,
                    discount,
                    embalase,
                    cart_type,
                    package,
                    dosage_r,
                    raw_total,
                    total_price,
                    final_price,
                    grossprice,
                    racikstatus,

                }).then(response => {
                    const item = response.data;

                    totaltransaction += total_price;
                    totalbought = parseFloat(totalbought) + parseFloat(grossprice);
                    total_discount += parseFloat(discount);
                    price2 = formatRupiah(totaltransaction);
                    // Reset input fields
                    [stock, unit, quantity, price, name, totalprice].forEach(el => el.value = "");
                    discount = 0;
                    console.log("✅ Discount item:", discount);
                    discountInput.value = "";

                    resetInputs();


                    discountInput.value = "";
                    if (racikstatus == 1 && transaction_type != 'UPDS' && transaction_type != 'HV/OTC') {
                        document.getElementById('productSearch').focus();
                        closeBox();

                    } else {
                        document.getElementById('productSearch').focus();
                        closeBox();

                    }
                    console.log(item);

                    // UPDATE PREVIEW 
                    cartTotalInput.value = formatRupiah(totaltransaction);
                    previewdiscounttotal.value = formatRupiah(total_discount);
                    previewtransactiontotal.value = formatRupiah(totaltransaction);
                    payment_total.value = formatRupiah(totalbought);

                    // Reset Variables


                    document.getElementById('carts').insertAdjacentHTML('beforeend', `
                    <tr id="itemincart${item.id}" data-id="${item.id}" 
                        class="cart-row border-b hover:bg-blue-50 transition text-[10px] cursor-pointer">
                        <td class="px-1 py-1 text-center text-gray-600">
                            ${document.querySelectorAll('#carts tr').length + 1}
                        </td>
                        <td colspan="7" class="leading-normal text-[10px] px-1 py-1 font-semibold text-gray-800">
                            ${item.name}
                        </td>
                        <td class="px-1 py-1 text-center">${item.unit}</td>
                        <td class="px-1 py-1 text-center">${formatRupiah(item_finalprice)}</td>
                        <td class="px-1 py-1 text-center">${item.quantity}</td>
                        <td class="px-1 py-1 text-center">${formatRupiah(item.discount)}</td>
                        <td class="px-1 py-1 text-center">${formatRupiah(item.total_price)}</td>
                        ${(currenttransaction === 'RESEP TUNAI' || currenttransaction === 'KREDIT')
                            ? `<td class="px-1 py-1 text-center clEmbalase">${formatRupiah(item.jasa)}</td>`
                            : ''
                        }
                        <td class="px-1 py-1 text-center clFinalprice font-semibold text-blue-600">
                            ${formatRupiah(item.final_price)}
                        </td>
                        <td class="px-1 py-1 text-center font-semibold text-blue-600">${item.cart_type}</td>
                    </tr>
                `);

                    const newRow = document.getElementById(`itemincart${item.id}`); // ✅ safer
                    console.log('Newly inserted row:', newRow);
                    attachRowEvents(newRow);
                    discount = 0;
                    console.log('Reseting the discount variable', discount);


                }).catch(error => {
                    console.error("❌ Error adding to cart:", error.response ? error.response.data : error.message);
                });
            }


        }



        function submit() {

            if (edit_status == 1) {
                true_price = final_price + jasa;
                const pkg = document.getElementById('package')?.value || '';
                const dose = document.getElementById('dosage_r')?.value || '';
                console.log("🧾 Cart Item Details:", {
                    medicine_id,
                    transaction_id,
                    total_item,
                    discount,
                    jasa,
                    cart_type,
                    pkg,
                    dose,
                    pharmacy_price,
                    true_price,
                    grossprice,
                    racikstatus
                });
                addToCart(
                    medicine_id,
                    transaction_id,
                    total_item,
                    discount,
                    jasa,
                    cart_type,
                    pkg,
                    dose,
                    pharmacy_price,
                    true_price,
                    grossprice,
                    racikstatus
                );
                pkg.value = pkg;



            } else {
                console.log('grossprice =', grossprice);

                if (discountInput.value === "") {
                    final_price = subtotal;
                    discount = 0;
                }
                if (jasa === "") {
                    jasa = 0;
                }

                // Determine cart type
                if (transaction_type === "KREDIT" || transaction_type === "RESEP TUNAI") {
                    cart_type = "UM";
                } else if (transaction_type === "UPDS") {
                    cart_type = "UP";
                } else if (transaction_type === "HV/OTC") {
                    cart_type = "HV";
                }

                true_price = final_price + jasa;
                const pkg = document.getElementById('package')?.value || '';
                const dose = document.getElementById('dosage_r')?.value || '';
                console.log("count() => subtotal:", subtotal, "grossprice:", grossprice);

                addToCart(
                    medicine_id,
                    transaction_id,
                    total_item,
                    discount,
                    jasa,
                    cart_type,
                    pkg,
                    dose,
                    pharmacy_price,
                    true_price,
                    grossprice,
                    racikstatus
                );
                pkg.value = pkg;
            }
            edit_status = 0;
            discount = 0;

        }

        // edit cart
        function attachRowEvents(row) {
            row.addEventListener('click', function() {
                document.querySelectorAll('.cart-row').forEach(r => r.classList.remove('bg-blue-100'));
                this.classList.add('bg-blue-100');
                selectedRowId = this.dataset.id;
                console.log('Selected item ID:', selectedRowId);
            });

            row.addEventListener('dblclick', function() {
                const id = this.dataset.id;
                console.log('Editing item:', id);
                editCartItem(id);
            });
        }

        function deleteCartItem(id) {
            console.log('Deleting item:', id);
            axios.delete(`/transaction/cartItem/${id}`)
                .then(response => {
                    console.log('Deleted:', response.data);
                    const row = document.querySelector(`#itemincart${id}`);
                    if (row) row.remove();
                    selectedRowId = null;

                    // UPDATE PREVIEW
                    cartTotalInput.value = formatRupiah(response.data.total_transaction);
                    previewdiscounttotal.value = formatRupiah(response.data.total_discount);
                    previewtransactiontotal.value = formatRupiah(response.data.total_transaction);
                    payment_total.value = formatRupiah(response.data.totalbought);
                    totaltransaction = response.data.total_transaction;
                })
                .catch(err => console.error('Failed to delete item:', err));
        }
        document.addEventListener('keydown', e => {
            const activeTag = e.target.tagName.toLowerCase();

            if (['input', 'textarea', 'select'].includes(activeTag)) return;

            if (e.key === 'Delete' || e.key === 'Backspace') {
                console.log('Delete key');
                console.log('SelectedRowId =', selectedRowId);

                if (!selectedRowId) {
                    alert('Pilih Item Dulu.');
                    return;
                }

                if (confirm('Hapus item ini dari keranjang?')) {
                    deleteCartItem(selectedRowId);
                }
            }
        });



        function editCartItem(id) {

            axios.get(`/transaction/cartItem/${id}`)
                .then(response => {
                    edit_status = 1;
                    const item = response.data;
                    let raw = (+item.medicine.net_price * +parameters) + +rounding;
                    let rounded = Math.floor(raw / 1000) * 1000;

                    medicine_id = item.medicine_id;
                    total_item = item.quantity;
                    item_finalprice = rounded;
                    price2 = rounded;
                    subtotal = price2 * item.quantity;
                    pharmacy_price = subtotal;
                    final_price = subtotal - item.discount;
                    discount = item.discount;
                    grossprice = subtotal;
                    name.value = item.medicine.name;
                    unit.value = item.medicine.unit;
                    if (currenttransaction == 'RESEP TUNAI') {
                        dosage.value = item.medicine.dosage;
                        if (dosageRInput || packageInput) {
                            dosageRInput.value = item.dosage_r;
                            packageInput.value = item.package;
                        }
                        const dosageR2 = document.getElementById('dosage_r2');
                        if (item.cart_type == "UM") {
                            if (checkbox) {
                                packageInput?.removeAttribute('readonly');
                                dosageRInput?.removeAttribute('readonly');
                                packageInput?.classList.remove('readonly');
                                dosageRInput?.classList.remove('readonly');
                                if (dosageR2) dosageR2.value = "Ya";
                                racikstatus = 1;
                            }
                        } else {
                            if (checkbox) {
                                packageInput?.setAttribute('readonly', true);
                                dosageRInput?.setAttribute('readonly', true);
                                packageInput?.classList.add('readonly');
                                dosageRInput?.classList.add('readonly');
                                if (dosageR2) dosageR2.value = "Tidak";
                                racikstatus = 0;
                            }
                        }
                    }
                    totalprice.value = formatRupiah(item.total_price);
                    quantity.value = item.quantity;
                    price.value = formatRupiah(rounded);
                    discountInput.value = item.discount;



                    console.log('Received item data:', response.data);

                })
                .catch(err => console.error('Failed to load item data:', err));
        }



        // ===============================
        // Checkout & Invoice
        // ===============================
        function checkoutItem() {
            const paid = document.getElementById('pay').value;
            const changes = document.getElementById('change').value;
            if (transaction_type == 'RESEP TUNAI') {
                const debtor_id = 0;
            } else if (transaction_type == 'UPDS' || transaction_type == 'HV/OTC') {
                const debtor_id = 0;
                const doctor_id = 0;
            } else {
                const debtor_id = document.getElementById('debtor_id').value;
                const doctor_id = document.getElementById('doctor_id').value;
            }
            const patient_id = document.getElementById('patient_id').value;

            if (doctor_id == "") {
                alert("Silahkan Pilih Dokter Dulu")
            } else if (debtor_id == "") {
                alert("Silahkan Pilih Debitur DUlu")

            } else if (patient_id == "") {
                alert("Silahkan Pilih Pasien")

            } else {
                axios.post("{{ route('transaction.getTransactionItem') }}", {
                    transaction_id,
                    paid,
                    subtotal,
                    doctor_id,
                    debtor_id,
                    patient_id,
                    changes
                }).then(response => {
                    var transaction_items = response.data.itemTransaction;
                    var transaction = response.data.transaction;

                    document.getElementById('receipt').textContent = transaction.transactions.transaction_code;
                    document.getElementById('type').textContent = transaction.transactions.transaction_type;
                    document.getElementById('cashier').textContent = transaction.user.name;
                    document.getElementById('customer').textContent = "Client";
                    document.getElementById('invoiceItems').innerHTML = "";
                    document.getElementById('invoiceTotal').innerHTML = "";

                    transaction_items.forEach(item => {
                        document.getElementById('invoiceItems').innerHTML += `
                        <tr>
                            <td>${item.medicine.name}</td>
                            <td>${item.quantity}</td>
                            <td>${formatRupiah(item.total_price)}</td>
                        </tr>
                    `;
                    });

                    document.getElementById('invoiceTotal').innerHTML += `
                    <tr><td>Total</td><td></td><td>${formatRupiah(totaltransaction)}</td></tr>
                    <tr><td>Tunai</td><td></td><td>${paid}</td></tr>
                    <tr><td>Kembali</td><td></td><td>${changes}</td></tr>
                `;

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
                        document.getElementById("transaction_id").value = transaction_id;
                        document.getElementById("checkoutForm").submit();
                    }
                }).catch(error => {
                    console.error("❌ Error getting cart:", error.response ? error.response.data : error.message);
                });
            }

        }

        function closeInvoice() {
            const modal = document.getElementById("invoiceModal");
            const content = document.getElementById("invoiceContent");

            modal.classList.remove("opacity-100");
            content.classList.remove("scale-100");
            content.classList.add("scale-95");

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
            doc.write(`<!DOCTYPE html><html><head><title>Invoice</title>
                <style>
                    @page { size: 80mm auto; margin: 0; }
                    body { margin:0; padding:0; background:#fff; font-family:sans-serif; }
                    .invoice-container { width:100mm; padding:4px 8px; }
                    .invoice-table { width:100%; font-size:0.7rem; }
                    .divider { border-bottom:1px dashed #9ca3af; margin:4px 0; }
                </style>
            </head><body>${invoiceContent}</body></html>`);
            doc.close();

            iframe.contentWindow.focus();
            iframe.contentWindow.print();

            document.getElementById("transaction_id").value = transaction_id;
            document.getElementById("checkoutForm").submit();
            document.body.removeChild(iframe);
        }

        // ===============================
        // Payment & Button State
        // ===============================
        function activeButton() {
            const btn = document.getElementById("checkout");
            btn.disabled = false;
            btn.classList.remove("!bg-gray-400");
            btn.classList.add("!bg-[#2196F3]");
        }

        function resetButton() {
            const btn = document.getElementById("checkout");
            btn.disabled = true;
            btn.classList.remove("!bg-[#2196F3]");
            btn.classList.add("!bg-gray-400");
        }

        function payment() {

        }

        function pay() {
            const paidInput = document.getElementById('paid');
            const changesInput = document.getElementById('changes');
            let raw = document.getElementById('pay').value.replace(/\D/g, "");
            let bayar = parseInt(raw) || 0;
            document.getElementById('pay').value = "Rp. " + bayar.toLocaleString("id-ID");

            if (bayar < totaltransaction) {
                document.getElementById('trchange').value = "Duitnya Kurang";
                resetButton();
            } else {
                if (totaltransaction > 0) activeButton();
                paidInput.value = totaltransaction;
                changesInput.value = bayar - totaltransaction;
                document.getElementById('trchange').value = formatRupiah(bayar - totaltransaction);
            }
        }

        function countEmbalase() {

            let raw = document.getElementById('jasa').value.replace(/\D/g, "");
            let bayar = parseInt(raw) || 0;
            document.getElementById('jasa').value = "Rp. " + bayar.toLocaleString("id-ID");
            embalase = bayar;

        }

        // ===============================
        // Keyboard Shortcut
        // ===============================
        function onF1Key(e) {
            const isF1 = e.key === 'F1' || e.keyCode === 112;
            if (isF1) {
                e.preventDefault();
                const modal = document.getElementById('paymentModal');
                const isHidden = modal.classList.contains('hidden');

                if (isHidden) {
                    // Open modal
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                } else {
                    // Close modal
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            }
        }
        // ===============================
        function onF2Key(e) {
            const isF2 = e.key === 'F2' || e.keyCode === 113;
            if (isF2) {
                e.preventDefault();
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;

                    checkbox.dispatchEvent(new Event('change'));
                }
                // if (checkbox.checked) {
                //     document.getElementById('receiptbox').checked = false;

                //     packageInput.removeAttribute('readonly');
                //     dosageRInput.removeAttribute('readonly');
                //     packageInput.classList.remove('readonly');
                //     dosageRInput.classList.remove('readonly');
                //     quantity.setAttribute('readonly', true);
                //     quantity.classList.add('readonly');
                // } else {
                //     document.getElementById('receiptbox').checked = true;
                //     packageInput.setAttribute('readonly', true);
                //     dosageRInput.setAttribute('readonly', true);
                //     packageInput.classList.add('readonly');
                //     dosageRInput.classList.add('readonly');
                // }

            }
        }

        function onF3Key(e) {
            const isF3 = e.key === 'F3' || e.keyCode === 114;
            if (isF3) {
                e.preventDefault();
                if (transaction_type == 'RESEP TUNAI') {
                    parameters = {{ $ChangeFakturParameters }};
                    transaction_type = "UPDS";
                    faktur.innerHTML = `
                            <span
                            class="bg-green-100 text-green-800 text-xs font-medium me-2 px-2.5 py-3 rounded-md dark:bg-green-900 dark:text-green-300">UPDS</span>
                    `;
                } else if (transaction_type == "UPDS") {
                    parameters = {{ $ChangeFakturParameters }};
                    transaction_type = "HV/OTC";
                    faktur.innerHTML = `
                        <span class="bg-blue-100 text-blue-800 text-xs font-medium me-2 px-2.5 py-3 rounded-md dark:bg-blue-900 dark:text-blue-300">HV/OTC</span>
                    `;
                } else if (transaction_type == "HV/OTC") {
                    parameters = {{ $ChangeFakturParameters }};
                    transaction_type = "RESEP TUNAI";
                    faktur.innerHTML = `
                            <span class="bg-red-100 text-red-800 text-xs font-medium me-2 px-2.5 py-3 rounded-md dark:bg-red-900 dark:text-red-300">Resep Tunai</span>
                    `;
                }


                // if (checkbox.checked) {
                //     document.getElementById('receiptbox').checked = false;

                //     packageInput.removeAttribute('readonly');
                //     dosageRInput.removeAttribute('readonly');
                //     packageInput.classList.remove('readonly');
                //     dosageRInput.classList.remove('readonly');
                //     quantity.setAttribute('readonly', true);
                //     quantity.classList.add('readonly');
                // } else {
                //     document.getElementById('receiptbox').checked = true;
                //     packageInput.setAttribute('readonly', true);
                //     dosageRInput.setAttribute('readonly', true);
                //     packageInput.classList.add('readonly');
                //     dosageRInput.classList.add('readonly');
                // }

            }
        }

        function onF4Key(e) {
            const isF4 = e.key === 'F4' || e.keyCode === 115;
            if (isF4) {
                e.preventDefault();
                if (checkbox) {
                    checkbox.checked = !checkbox.checked; // ✅ toggle
                    checkbox.dispatchEvent(new Event('change')); // optional: trigger change event
                }
                // if (checkbox.checked) {
                //     document.getElementById('receiptbox').checked = false;

                //     packageInput.removeAttribute('readonly');
                //     dosageRInput.removeAttribute('readonly');
                //     packageInput.classList.remove('readonly');
                //     dosageRInput.classList.remove('readonly');
                //     quantity.setAttribute('readonly', true);
                //     quantity.classList.add('readonly');
                // } else {
                //     document.getElementById('receiptbox').checked = true;
                //     packageInput.setAttribute('readonly', true);
                //     dosageRInput.setAttribute('readonly', true);
                //     packageInput.classList.add('readonly');
                //     dosageRInput.classList.add('readonly');
                // }

            }
        }

        function onF10Key(e) {
            if (e.key === 'F10' || e.keyCode === 121) {
                e.preventDefault();

                // do something
            }
        }
        // ===============================
        // Global Event Listeners
        // ===============================
        window.addEventListener('keydown', onF1Key, {
            capture: true
        });
        window.addEventListener('keydown', onF2Key, {
            capture: true
        });
        window.addEventListener('keydown', onF3Key, {
            capture: true
        });
        window.addEventListener('keydown', onF10Key, {
            capture: true
        });

        document.addEventListener('click', (e) => {
            if (!box.contains(e.target) && e.target !== input) closeBox();
            if (debtorbox) {
                if (!box.contains(e.target) && e.target !== input) closedebtorBox();
            }
        });
        input.addEventListener('blur', () => closeTimeout = setTimeout(closeBox, 120));
        input.addEventListener('focus', () => {
            clearTimeout(closeTimeout);
            if (list.children.length) openBox();
        });

        // ===============================
        // Patient Modal Control
        // ===============================
        openNewPatientBtn.addEventListener('click', () => {
            newPatientModal.classList.remove('hidden');
            document.getElementById('patientName').focus();
        });

        // Close button
        closeNewPatientBtn.addEventListener('click', () => {
            newPatientModal.classList.add('hidden');
        });

        // Outside click
        newPatientModal.addEventListener('click', (e) => {
            if (!newPatientModalContent.contains(e.target)) {
                newPatientModal.classList.add('hidden');
            }
        });

        // Inset Patient
        newPatientForm.addEventListener('submit', function(e) {
            e.preventDefault();

            let formData = new FormData(this);

            axios.post("{{ route('transaction.addPatient') }}", formData, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'multipart/form-data'
                    }
                })
                .then(response => {
                    if (response.data.success) {
                        alert("Pasien berhasil ditambahkan!");
                        newPatientModal.classList.add('hidden');
                        newPatientForm.reset();

                        // Auto-fill patient field with new patient
                        document.getElementById("patientname").value = response.data.patient.name;
                        inputpatient.value = response.data.patient.name;
                        document.getElementById("patient_id").value = response.data.patient.id;



                        document.getElementById("selectedPatientId").value = response.data.patient.id;
                    } else {
                        alert("Gagal menambahkan pasien.");
                    }
                })
                .catch(error => {
                    console.error(error);
                    alert("Terjadi kesalahan, coba lagi.");
                });
        });


        // ===============================
        // Modal Pembayaran
        // ===============================
        document.getElementById('openModalPayment').addEventListener('click', () => {
            document.getElementById('paymentModal').classList.remove('hidden');
            document.getElementById('paymentModal').classList.add('flex');
        });

        document.getElementById('closeModal').addEventListener('click', () => {
            document.getElementById('paymentModal').classList.add('hidden');
            document.getElementById('paymentModal').classList.remove('flex');
        });

        // Optional: close when clicking outside modal
        document.getElementById('paymentModal').addEventListener('click', (e) => {
            if (e.target.id === 'paymentModal') {
                e.currentTarget.classList.add('hidden');
                e.currentTarget.classList.remove('flex');
            }
        });
        // ===============================
        // Resep
        // ===============================
        if (checkbox) {
            checkbox.addEventListener('change', function() {
                const packageInput = document.getElementById('package');
                const dosageRInput = document.getElementById('dosage_r');
                const dosageR2 = document.getElementById('dosage_r2');

                if (this.checked) {
                    packageInput?.removeAttribute('readonly');
                    dosageRInput?.removeAttribute('readonly');
                    packageInput?.classList.remove('readonly');
                    dosageRInput?.classList.remove('readonly');
                    if (dosageR2) dosageR2.value = "Ya";
                    racikstatus = 1;

                    packageInput?.focus();
                } else {
                    packageInput?.setAttribute('readonly', true);
                    dosageRInput?.setAttribute('readonly', true);
                    packageInput?.classList.add('readonly');
                    dosageRInput?.classList.add('readonly');
                    if (dosageR2) dosageR2.value = "Tidak";
                    racikstatus = 0;
                }
            });
        }

        function calculatePackage() {
            const dosage = parseFloat(dosageInput.value) || 0;
            const dosageR = parseFloat(dosageRInput.value) || 0;
            const packageVal = parseFloat(packageInput.value) || 0;

            if (dosageR > 0 && dosage > 0) {
                const result = (dosageR / dosage) * packageVal;
                const rounded = Math.ceil(result);
                quantity.value = rounded;
                count(rounded);
            } else {
                quantity.value = '';
            }
        }

        function sendEmbalase(valEmbalase) {
            var valEmbalase = document.getElementById('jasa').value;
            let jasaValue = parseInt(valEmbalase.replace(/[^0-9]/g, ""), 10);
            axios.post("{{ route('sales.sendembalase') }}", {
                jasaValue,
                transaction_id,
            }).then(response => {
                const item = response.data;
                console.log(item);
                // Reset input fields
                // [stock, unit, quantity, price, name, totalprice].forEach(el => el.value = "");
                // document.getElementById('pay').value = "";
                // document.getElementById('change').value = "";
                // document.getElementById('package').value = "";
                // document.getElementById('quantity').value = "";
                // document.getElementById('dosage').value = "";
                racikembalaseprice = item.finalprice;


                let embalaseEls = document.querySelectorAll('.clEmbalase');
                let finalPriceEls = document.querySelectorAll('.clFinalprice');


                // Get The Last Record
                let lastEmbalase = embalaseEls[embalaseEls.length - 1];
                let lastFinalPrice = finalPriceEls[finalPriceEls.length - 1];

                // Update The Last Record
                if (lastEmbalase && lastFinalPrice) {
                    lastEmbalase.innerHTML = formatRupiah(jasaValue);
                    lastFinalPrice.innerHTML = formatRupiah(racikembalaseprice);
                }

                document.getElementById('jasa').value = "";
                document.getElementById('dosage_r2').value = "Tidak";
                checkbox.checked = false;
                checkbox.dispatchEvent(new Event('change'));
                document.getElementById('productSearch').focus();
                totaltransaction = response.data.totaltransaction;
                const roundedtotal = roundUpToNearestThousand(totaltransaction);
                cartTotalInput.value = formatRupiah(roundedtotal);
                console.log('Before:', totaltransaction);
                console.log('After:', roundUpToNearestThousand(totaltransaction));

                previewtransactiontotal.value = formatRupiah(totaltransaction);



            }).catch(error => {
                console.error("Error Updating Embalase:", error.response ? error.response.data : error.message);
            });

        }
        // Recipe Redirecting inputs
        if (packageInput) {
            packageInput.addEventListener('keydown', (e) => {
                if (e.key === 'Tab') {
                    e.preventDefault();
                    document.getElementById('productSearch').focus();
                }
            });
        }


        if (dosageRInput) {
            dosageRInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submit();
                    packageInput.focus();

                } else if (e.key === 'Tab') {
                    e.preventDefault();
                    document.getElementById('quantity').focus();
                }
            });
        }

        quantity.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                submit();
            } else if (e.key === 'Tab') {
                e.preventDefault();
                document.getElementById('discount').focus();
            }
        });

        document.getElementById('jasa').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();

                Swal.fire({
                    title: "Selesai Untuk Racikan?",
                    icon: "question",
                    showCancelButton: true,
                    confirmButtonText: "Save",
                    denyButtonText: `Don't save`
                }).then((result) => {
                    /* Read more about isConfirmed, isDenied below */
                    if (result.isConfirmed) {
                        Swal.fire("Racikan Telah Diselesaikan!", "", "success");
                        sendEmbalase(this.value);

                    }
                });
            }
        });
        discountInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                submit();
            }
        });
        payInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                pay(this.value);
            }
        });

        // ===============================
        // TABLE CONTROL
        // ===============================






        // Listen for Delete key
    </script>

    {{-- ------------------- Fixed Cart Card --------------------- --}}
@endsection
