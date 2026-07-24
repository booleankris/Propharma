@extends('layouts.app')
@section('content')
@section('style')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?time={{ time() }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    @if ($check_transaction != 0)
        @if ($transaction->transaction_type == 'HV/OTC')
            <style>
                .transaction-item-active {
                    background: #0074ce;
                    color: #fff;
                    border: none;
                    box-shadow: 0px 0px 11px 2px #0097dd !important;
                }

                .transaction-item-active:hover {
                    background: #fff;
                    color: #1e7221 !important;
                }
            </style>
        @elseif($transaction->transaction_type == 'UPDS')
            <style>
                .transaction-item-active {
                    background: #1e7221;
                    color: #fff;
                    border: none;
                    box-shadow: 0px 0px 11px 2px #1e7221 !important;
                    transition: 0.2s;
                }

                .transaction-item-active:hover .svg-item {
                    color: #1e7221 !important;
                }

                .transaction-item-active:hover {
                    background: #fff;
                    color: #1e7221 !important;
                }
            </style>
        @else
            <style>
                .transaction-item-active {
                    background: #e02113;
                    color: #fff;
                    border: none;
                    box-shadow: 0px 0px 11px 2px #c91e12 !important;

                }

                .transaction-item-active:hover {
                    background: #fff;
                    color: #1e7221 !important;
                }
            </style>
        @endif
    @endif
@endsection
{{-- Section Product Preview --}}
<div class="mx-4 max-w-8xl grid grid-cols-12 gap-6">
    <!-- LEFT COLUMN -->
    <section class="col-span-12 lg:col-span-5 space-y-3">
        <!-- Header Card -->
        @php
            $typeClass = 'type-idle';
            if ($check_transaction != 0) {
                $typeClass = match ($transaction->transaction_type) {
                    'UPDS' => 'type-upds',
                    'HV/OTC' => 'type-hv',
                    'KREDIT' => 'type-kredit',
                    default => 'type-resep',
                };
            }
        @endphp
        <div class="trx-card {{ $typeClass }} dashboard-panel">

            {{-- Header --}}
            <div class="trx-header">
                <div>
                    <h1 class="trx-title">Transaksi</h1>
                    @if ($check_transaction != 0)
                        <div class="trx-badge">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="5" width="20" height="14" rx="2" />
                                <line x1="2" y1="10" x2="22" y2="10" />
                            </svg>
                            {{ $transaction_code }}
                        </div>
                    @endif
                </div>
                @php
                    $shift = currentShift();
                    $activeshift = activeShift();
                @endphp

                <div class="trx-datetime">
                    <div id="live-date" class="trx-date">Memuat tanggal...</div>
                    <div id="live-time" class="trx-time">Memuat waktu...</div>
                    <div class="trx-badge mt-2">
                        {{ 'Shift : ' . $shift->name }}
                    </div>
                </div>

            </div>

            <div class="trx-divider"></div>
            {{-- Transaction Type Chips --}}
            <div class="trx-chips">

                {{-- Resep Credit --}}
                <a href="{{ url('transaction/kredit/' . ($trx_id != 0 ? $trx_id : 0)) }}"
                    class="trx-chip @if ($type == 'kredit') active @endif">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <rect x="2" y="5" width="20" height="14" rx="2" />
                        <line x1="2" y1="10" x2="22" y2="10" />
                    </svg>
                    <span class="trx-chip-lbl">Resep Credit</span>
                </a>

                {{-- Resep Tunai --}}
                <a href="{{ url('transaction/resep/' . ($trx_id != 0 ? $trx_id : 0)) }}"
                    class="trx-chip  @if ($type == 'resep') active @endif">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
                        <rect x="9" y="3" width="6" height="4" rx="1" />
                    </svg>
                    <span class="trx-chip-lbl">Resep Tunai</span>
                </a>

                {{-- HV/OTC --}}
                <a href="{{ url('transaction/hv/' . ($trx_id != 0 ? $trx_id : 0)) }}"
                    class="trx-chip @if ($type == 'hv') active @endif">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path
                            d="M19.428 15.428a2 2 0 0 0-1.022-.547l-2.387-.477a6 6 0 0 0-3.86.517l-.318.158a6 6 0 0 1-3.86.517L6.05 15.21a2 2 0 0 0-1.806.547M8 4h8l-1 1v5.172a2 2 0 0 0 .586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 0 0 9 10.172V5L8 4z" />
                    </svg>
                    <span class="trx-chip-lbl">HV</span>
                </a>

                {{-- UPDS --}}
                <a href="{{ url('transaction/upds/' . ($trx_id != 0 ? $trx_id : 0)) }}"
                    class="trx-chip @if ($type == 'upds') active @endif">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                        <line x1="3" y1="6" x2="21" y2="6" />
                        <path d="M16 10a4 4 0 0 1-8 0" />
                    </svg>
                    <span class="trx-chip-lbl">UPDS</span>
                </a>

            </div>

            <form method="post" action="{{ route('transaction.createnew') }}" target="_blank">
                @csrf
                <input type="hidden" value="{{ request()->segment(2) }}" name="type">
                <div class="trx-actions">
                    <button type="submit" class="trx-btn trx-btn-add" tabindex="4">
                        <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9" />
                            <line x1="12" y1="8" x2="12" y2="16" />
                            <line x1="8" y1="12" x2="16" y2="12" />
                        </svg>
                        Tambah Transaksi
                    </button>
                    <button type="button" onclick="back()" class="trx-btn trx-btn-back" tabindex="5">
                        <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 14 4 9 9 4" />
                            <path d="M20 20v-7a4 4 0 0 0-4-4H4" />
                        </svg>
                        Kembali
                    </button>
                </div>
            </form>
        </div>
        @if ($check_transaction != 0)
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
                        <input id="quantity" required name="quantity"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')" step="1"
                            onkeyup="count(this.value)" type="number" placeholder="QTY"
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
                        <input id="discount" name="discount" onkeyup="countDiscount(this.value)" type="number"
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
        @if ($check_transaction != 0)
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
                        <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true"
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                            viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-width="2"
                                d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="card p-4 px-6 gap-3 flex flex-wrap items-center bg-white dashboard-panel">
                <div id="openMasterModal"
                    class="flex flex-col py-1 items-center bg-[#0074ce] shadow-[0_0_11px_2px_#0074ce] cursor-pointer border-none px-[10px] rounded-md">
                    <div class="py-[4px]">
                        <svg class="w-5 h-5 text-gray-800 dark:text-white" aria-hidden="true"
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                            viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 6c0 1.657-3.134 3-7 3S5 7.657 5 6m14 0c0-1.657-3.134-3-7-3S5 4.343 5 6m14 0v6M5 6v6m0 0c0 1.657 3.134 3 7 3s7-1.343 7-3M5 12v6c0 1.657 3.134 3 7 3s7-1.343 7-3v-6" />
                        </svg>

                    </div>
                    <div class="px-1 font-semibold font-poppins text-[11px] text-white">
                        Master
                    </div>
                </div>
                <div id="openSearchModal"
                    class="flex flex-col py-1 items-center bg-[#009688] shadow-[0_0_11px_2px_#009688] cursor-pointer border-none px-[10px] rounded-md">
                    <div class="py-[4px]">
                        <svg class="w-5 h-5 text-gray-800 dark:text-white" aria-hidden="true"
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                            viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-width="2"
                                d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                        </svg>
                    </div>
                    <div class="px-1 font-semibold font-poppins text-[11px] text-white">
                        Cari
                    </div>

                </div>
                <a href="{{ route('sales.reject') }}">
                    <div
                        class="flex flex-col py-1 items-center bg-[#d81e1e] shadow-[0_0_11px_2px_#d05a5ac9] cursor-pointer border-none px-[10px] rounded-md">
                        <div class="py-[4px] text-white">

                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-circle-dashed-x">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M8.56 3.69a9 9 0 0 0 -2.92 1.95" />
                                <path d="M3.69 8.56a9 9 0 0 0 -.69 3.44" />
                                <path d="M3.69 15.44a9 9 0 0 0 1.95 2.92" />
                                <path d="M8.56 20.31a9 9 0 0 0 3.44 .69" />
                                <path d="M15.44 20.31a9 9 0 0 0 2.92 -1.95" />
                                <path d="M20.31 15.44a9 9 0 0 0 .69 -3.44" />
                                <path d="M20.31 8.56a9 9 0 0 0 -1.95 -2.92" />
                                <path d="M15.44 3.69a9 9 0 0 0 -3.44 -.69" />
                                <path d="M14 14l-4 -4" />
                                <path d="M10 14l4 -4" />
                            </svg>
                        </div>
                        <div class="px-1 font-semibold font-poppins text-[11px] text-white">
                            Tolak
                        </div>
                    </div>
                </a>
            </div>
        @endif
    </section>

    @if ($check_transaction != 0)
        <!-- RIGHT COLUMN -->
        <aside class="col-span-12 lg:col-span-7  dashboard-panel">
            <div class="card px-6 bg-white mt-5">
                <div class="w-full flex">
                    <div class="w-1/2 flex">
                        <div class="mr-2">
                            <div class="w-full my-1">
                                <label class="text-[13px] font-poppins font-semibold">Faktur</label>

                            </div>
                            @if ($check_transaction != 0)
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
                        @if ($check_transaction != 0)
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
                                    <label
                                        class="text-[13px] font-poppins font-semibold text-right pr-2">Jumlah</label>

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
                <div
                    class="mt-4 rounded-2xl @if ($transaction->transaction_type == 'UPDS') bg-[#eff8ef] @elseif($transaction->transaction_type == 'HV/OTC') bg-[#e8f5ff] @elseif($transaction->transaction_type == 'KREDIT') bg-[#f8f4e3] @else bg-[#ffeaea] @endif h-[40vh] overflow-y-scroll md:h-[53vh]">
                    <div class="flex flex-col justify-between">
                        <table class="min-w-full text-sm text-left font-poppins text-gray-700">
                            <thead class="text-gray-600 uppercase text-xs border-b border-[#d6d6d6]">
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
                                        class="cart-row @if ($cart->recipe_status != null) bg-[#eefff8] @endif border-b hover:bg-blue-50 transition text-[10px] cursor-pointer">
                                        <td class="px-1 py-1 text-center text-gray-600">{{ $index + 1 }}</td>
                                        <td colspan="7"
                                            class="text-[10px] leading-normal px-1 py-1 font-semibold text-gray-800">
                                            {{ $cart->medicine->name }}
                                        </td>
                                        <td class="px-1 py-1 text-center">{{ $cart->medicine->unit }}</td>
                                        <td class="px-1 py-1 text-center">Rp
                                            {{ number_format($cart->item_price, 0, ',', '.') }}</td>
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
            {{-- <div class="button-container flex gap-2 mt-3 mb-4 mx-auto w-[95%]">
                <div class="w-[50%]">
                    <form method="POST" action="{{ route('sales.deletetransaction') }}">
                        @csrf
                        
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

            </div> --}}
            <div class="flex gap-2 mt-3 mb-4 mx-auto w-[95%]">

                {{-- Cancel button with confirmation --}}
                <div class="w-1/2">
                    <form method="POST" action="{{ route('sales.deletetransaction') }}">
                        @csrf
                        <input type="hidden" name="trxtype"
                            value="{{ $transaction?->transaction_type ?? 'null' }}">
                        <input type="hidden" name="trxid" value="{{ $transaction?->id ?? 'null' }}">
                        <button type="submit" id="deletebtn"
                            class="flex items-center justify-center gap-2 py-[14px] mt-2 w-full
                               font-poppins text-[15px] font-medium bg-[#e95050] text-white
                               border-none px-[16px] rounded-md
                               transition-all duration-150
                               hover:bg-[#d43e3e] active:scale-[0.97]
                               disabled:opacity-55 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round">
                                <line x1="4" y1="4" x2="12" y2="12" />
                                <line x1="12" y1="4" x2="4" y2="12" />
                            </svg>
                            Batal
                        </button>
                    </form>
                </div>

                {{-- Payment button --}}
                <div class="w-1/2">
                    <button id="openModalPayment" type="button"
                        class="flex items-center justify-center gap-2 py-[14px] mt-2 w-full
                               font-poppins text-[15px] font-medium bg-[#0074ce] text-white
                               border-none px-[16px] rounded-md
                               transition-all duration-150
                               hover:bg-[#005fab] active:scale-[0.97]
                               disabled:opacity-55 disabled:cursor-not-allowed">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="4" width="14" height="9" rx="2" />
                            <path d="M1 7h14" />
                            <path d="M4 11h3" />
                        </svg>
                        Pembayaran
                    </button>
                </div>
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
    class="hidden z-[999999] fixed inset-0 bg-black/50 flex items-center justify-center opacity-0 transition-opacity duration-300">
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

            {{-- <div id="paymentstotal" class="payment">
                    <p id="paid">📧 info@example.com</p>
                    <p id="change">📞 +234XXXXXXXX</p>
                </div> --}}

            <div class="divider"></div>

            <div class="button-row">
                <button class="btn btn-gray" onclick="closeInvoice()">Tutup</button>
                <button class="btn btn-blue" id="print" onclick="printInvoice()">Cetak & Selesai</button>
            </div>
        </div>
    </div>
</div>
{{-- ============================================================== Modal Invoice  ============================================================== --}}

{{-- ============================================================== Patient Invoice  ============================================================== --}}
<div id="newPatientModal"
    class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[999999]">
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

<!-- Doctor Modal -->
<div id="newDoctorModal"
    class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center hidden z-[999999]">
    <div id="newDoctorModalContent" class="bg-white p-6 rounded-lg shadow w-96">
        <h2 class="text-lg font-bold mb-4">Tambah Dokter</h2>

        <form id="newDoctorForm">
            @csrf

            <label class="block text-sm font-medium mb-1">Nama Dokter</label>
            <input type="text" name="name" id="doctorName"
                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300"
                required>

            <label class="block text-sm font-medium mb-1">Spesialis</label>
            <input type="text" name="specialist" id="doctorSpecialist"
                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300">

            <label class="block text-sm font-medium mb-1">Alamat</label>
            <input type="text" name="address" id="doctorAddress"
                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300">

            <label class="block text-sm font-medium mb-1">Kota</label>
            <input type="text" name="city" id="doctorCity"
                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300">

            <label class="block text-sm font-medium mb-1">Telepon</label>
            <input type="text" name="phone" id="doctorPhone"
                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-gray-300">

            <div class="flex justify-end mt-4">
                <button type="button" id="closeNewDoctorBtn" class="px-4 py-2 bg-gray-300 rounded mr-2">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">
                    Tambah Dokter
                </button>
            </div>
        </form>
    </div>
</div>

@if ($check_transaction != 0)
    {{-- Modal Pembayaran --}}
    <div id="paymentModal" class="fixed inset-0 bg-black/50 hidden justify-center items-center z-[99999]">
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
                @if ($check_transaction != 0)
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
                                    <input autofocus required id="doctorSearch"
                                        @if ($transaction->transaction_type == 'KREDIT') onclick="creditsubmit()" @endif
                                        type="text" placeholder="Ketik ID / Nama…"
                                        class="w-full rounded-xl border my-1 border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                        autocomplete="off" />
                                    <!-- Dropdown -->
                                    <div id="doctorResults"
                                        class="absolute z-50 mt-2 w-[50%] rounded-xl border border-gray-200 bg-white shadow-lg hidden">
                                        <ul id="doctorList" role="listbox" class="max-h-80 overflow-auto py-2">
                                        </ul>
                                    </div>
                                </div>
                                <div class="adddebtors w-[120px] ml-[10px]">
                                    <button type="button" id="openNewDoctorBtn"
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
                                <input type="hidden" id="selectedDoctorId" />
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            <div class="mr-2 w-[100%] mt-3 flex gap-2">
                @if ($check_transaction != 0)
                    @if ($transaction->transaction_type == 'KREDIT')
                        <div class="w-[40%] hidden">
                            <label class="text-[13px] font-poppins font-semibold pb-1">Embalase</label>
                            <input id="embalase" tabindex="-1" readonly type="text" name="embalase"
                                placeholder="Embalase"
                                class="w-full rounded-xl my-1 readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                autocomplete="off" />
                        </div>
                    @endif
                @endif
                @if ($check_transaction != 0)
                    <div class="w-full gap-2 @if ($transaction->transaction_type == 'KREDIT') hidden @else flex @endif ">
                        @if ($transaction->transaction_type != 'KREDIT')
                            <div class="w-full">
                                <label class="text-[13px] font-poppins font-semibold pb-1">Sub Total</label>
                                <input id="subtotal" tabindex="-1" readonly type="text" name="subtotal"
                                    placeholder="Subtotal obat"
                                    class="w-full rounded-xl my-1 readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                    autocomplete="off" />
                            </div>
                        @endif
                        <div class="w-full">
                            <label class="text-[13px] font-poppins font-semibold pb-1">Total</label>
                            <input id="carttotal" tabindex="-1" readonly type="text" name="carttotal"
                                placeholder="Total obat"
                                class="w-full rounded-xl my-1 readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                autocomplete="off" />
                        </div>

                        <div class="w-full">
                            <label class="text-[13px] font-poppins font-semibold pb-1">Discount</label>
                            <input @if ($transaction->transaction_type == 'KREDIT') value="0" @endif
                                onkeyup="countSubtotalDiscount(this.value)" id="discounsubtotal" tabindex="-1"
                                type="number" name="discounsubtotal" placeholder="Discount"
                                class="w-full rounded-xl my-1 border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                autocomplete="off" />
                        </div>
                    </div>
                @endif
            </div>
            @if ($transaction->transaction_type != 'KREDIT')
                <div class="mb-5">
                    <label class="block text-[12px] font-medium text-gray-500 mb-3">Metode Pembayaran</label>

                    <div class="flex gap-2" id="paymentTypeGroup">
                        {{-- Cash --}}
                        <label class="payment-option flex-1 cursor-pointer">
                            <input type="radio" id="payment_type" name="payment_type" onclick="getPaymentType()"
                                value="CASH" class="sr-only">
                            <div
                                class="payment-card cash-card flex flex-col items-center gap-2 py-3 px-2 rounded-xl border text-center">
                                <div class="payment-icon w-9 h-9 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="2" y="6" width="20" height="12" rx="2" />
                                        <circle cx="12" cy="12" r="3" />
                                        <path d="M6 12h.01M18 12h.01" />
                                    </svg>
                                </div>
                                <span class="payment-label text-[12px] font-medium">Cash</span>
                            </div>
                        </label>

                        {{-- QRIS --}}
                        <label class="payment-option flex-1 cursor-pointer">
                            <input type="radio" name="payment_type" onclick="getPaymentType()" value="QRIS"
                                class="sr-only" required>
                            <div
                                class="payment-card qris-card flex flex-col items-center gap-2 py-3 px-2 rounded-xl border text-center">
                                <div class="payment-icon w-9 h-9 rounded-lg flex items-center justify-center">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M6.5 6.5H6.51M17.5 6.5H17.51M6.5 17.5H6.51M13 13H13.01M17.5 17.5H17.51M17 21H21V17M14 16.5V21M21 14H16.5M15.6 10H19.4C19.9601 10 20.2401 10 20.454 9.89101C20.6422 9.79513 20.7951 9.64215 20.891 9.45399C21 9.24008 21 8.96005 21 8.4V4.6C21 4.03995 21 3.75992 20.891 3.54601C20.7951 3.35785 20.6422 3.20487 20.454 3.10899C20.2401 3 19.9601 3 14.546 3.10899C14.3578 3.20487 14.2049 3.35785 14.109 3.54601C14 3.75992 14 4.03995 14 4.6V8.4C14 8.96005 14 9.24008 14.109 9.45399C14.2049 9.64215 14.3578 9.79513 14.546 9.89101C14.7599 10 15.0399 10 15.6 10ZM4.6 10H8.4C8.96005 10 9.24008 10 9.45399 9.89101C9.64215 9.79513 9.79513 9.64215 9.89101 9.45399C10 9.24008 10 8.96005 10 8.4V4.6C10 4.03995 10 3.75992 9.89101 3.54601C9.79513 3.35785 9.64215 3.20487 9.45399 3.10899C9.24008 3 8.96005 3 8.4 3H4.6C4.03995 3 3.75992 3 3.54601 3.10899C3.35785 3.20487 3.20487 3.35785 3.10899 3.54601C3 3.75992 3 4.03995 3 4.6V8.4C3 8.96005 3 9.24008 3.10899 9.45399C3.20487 9.64215 3.35785 9.79513 3.54601 9.89101C3.75992 10 4.03995 10 4.6 10ZM4.6 21H8.4C8.96005 21 9.24008 21 9.45399 20.891C9.64215 20.7951 9.79513 20.6422 9.89101 20.454C10 20.2401 10 19.9601 10 19.4V15.6C10 15.0399 10 14.7599 9.89101 14.546C9.79513 14.3578 9.64215 14.2049 9.45399 14.109C9.24008 14 8.96005 14 8.4 14H4.6C4.03995 14 3.75992 14 3.54601 14.109C3.35785 14.2049 3.20487 14.3578 3.10899 14.546C3 14.7599 3 15.0399 3 15.6V19.4C3 19.9601 3 20.2401 3.10899 20.454C3.20487 20.6422 3.35785 20.7951 3.54601 20.891C3.75992 21 4.03995 21 4.6 21Z"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                    </svg>
                                </div>
                                <span class="payment-label text-[12px] font-medium">QRIS</span>
                            </div>
                        </label>

                        {{-- Debit --}}
                        <label class="payment-option flex-1 cursor-pointer">
                            <input type="radio" name="payment_type" onclick="getPaymentType()" value="DEBIT"
                                class="sr-only">
                            <div
                                class="payment-card debit-card flex flex-col items-center gap-2 py-3 px-2 rounded-xl border text-center">
                                <div class="payment-icon w-9 h-9 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="1" y="4" width="22" height="16" rx="2" />
                                        <path d="M1 10h22" />
                                        <path d="M4 15h3M10 15h2" />
                                    </svg>
                                </div>
                                <span class="payment-label text-[12px] font-medium">Debit</span>
                            </div>
                        </label>

                        {{-- Transfer --}}
                        <label class="payment-option flex-1 cursor-pointer">
                            <input type="radio" name="payment_type" onclick="getPaymentType()" value="TRANSFER"
                                class="sr-only">
                            <div
                                class="payment-card transfer-card flex flex-col items-center gap-2 py-3 px-2 rounded-xl border text-center">
                                <div class="payment-icon w-9 h-9 rounded-lg flex items-center justify-center">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="M4 17h16M4 17l4-4M4 17l4 4" />
                                        <path d="M20 7H4M20 7l-4-4M20 7l-4 4" />
                                    </svg>
                                </div>
                                <span class="payment-label text-[12px] font-medium">Transfer</span>
                            </div>
                        </label>
                    </div>

                    {{-- Bank name input — only visible when Transfer is selected --}}
                    <div id="bankNameWrapper" class="hidden mt-3">
                        <label class="block text-[12px] font-medium text-gray-500 mb-1">Nama bank</label>
                        <select
                            class="w-full rounded-xl border my-1 border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                            name="bank_name" id="bank_name">
                            <option value="">-- Pilih Bank --</option>
                            <option value="Mandiri">Mandiri</option>
                            <option value="BCA">BCA</option>
                            <option value="BRI">BRI</option>
                            <option value="BSI">BSI</option>
                            <option value="BNI">BNI</option>
                            <option value="BTN">BTN</option>
                        </select>
                    </div>
                </div>
            @endif

            @if ($check_transaction != 0)
                @if ($transaction->transaction_type == 'KREDIT')
                    <div class="w-full">
                        <label class="text-[13px] font-poppins font-semibold pb-1">Debitur</label>
                        <input id="debtor_name" tabindex="-1" readonly type="text" name="change"
                            placeholder="Nama Debitur"
                            class="w-full rounded-xl border my-1 readonly border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                            autocomplete="off" />
                    </div>
                @endif
            @endif
            @if ($check_transaction != 0)
                <div
                    class="mr-2 flex gap-2 w-[100%] mt-3 @if ($transaction->transaction_type == 'KREDIT') hidden @else flex @endif">
                    <div class="w-full">
                        <label class="text-[13px] font-poppins font-semibold pb-1">Bayar</label>
                        <input id="pay" onkeyup="pay(this.value)"
                            @if ($transaction->transaction_type == 'KREDIT') value="0" @endif type="text" required
                            name="pay" placeholder="Bayar obat"
                            class="w-full rounded-xl border my-1 border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                            autocomplete="off" />
                    </div>
                    <div class="w-full">
                        <label class="text-[13px] font-poppins font-semibold pb-1">Kembalian</label>
                        <input id="trchange" tabindex="-1" @if ($transaction->transaction_type == 'KREDIT') value="0" @endif
                            readonly type="text" name="change" placeholder="Bayar obat"
                            class="w-full rounded-xl border my-1 readonly border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                            autocomplete="off" />
                    </div>
                </div>
            @endif
            <div class="mt-5">
                <form id="checkoutForm">
                    @csrf
                    <input type="hidden" name="paid" id="paid">
                    <input type="hidden" name="changes" id="changes">
                    <input type="hidden" name="transaction_id" id="transaction_id">
                    <input type="hidden" name="print_receipt" id="print_receipt">
                    <input type="hidden" required name="patient_id" id="patient_id" />
                    <input type="hidden" @if ($transaction->transaction_type == 'RESEP TUNAI') value="0" @endif required
                        name="doctor_id" id="doctor_id" />
                    <input type="hidden" required name="debtor_id" id="debtor_id" />

                    @if ($check_transaction != 0)
                        @if ($transaction->transaction_type == 'KREDIT')
                            <button type="button" id="checkout" onclick="checkoutItem()"
                                class="w-full mt-3 rounded-lg bg-[#2196F3] hover:bg-gray-500 text-white font-semibold py-4 transition">
                                Simpan
                            </button>
                        @else
                            <button type="button" @if ($transaction->transaction_type != 'KREDIT') disabled @endif id="checkout"
                                onclick="checkoutItem()"
                                class="w-full mt-3 rounded-lg bg-gray-400 hover:bg-gray-500 text-white font-semibold py-4 transition">
                                Selesaikan
                            </button>
                        @endif
                    @endif
                </form>
            </div>
            <div id="checkoutLoading"
                class="hidden fixed inset-0 z-[9999999] flex items-center justify-center bg-black/40 backdrop-blur-sm">
                <div class="bg-white rounded-2xl p-6 flex flex-col items-center gap-3 shadow-xl">
                    <svg class="animate-spin h-8 w-8 text-[#2196F3]" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    <span class="text-[13px] text-gray-500 font-medium">Memproses...</span>
                </div>
            </div>
        </div>
    </div>
    {{-- Print Confirmation Modal --}}
    <div id="printConfirmModal" class="hidden fixed inset-0 z-[999999] items-center justify-center bg-black/40">
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm mx-4" style="border: 0.5px solid rgba(0,0,0,0.08);">

            {{-- Icon --}}
            <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4" style="background: #EFF6FF;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2196F3"
                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9" />
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                    <rect x="6" y="14" width="12" height="8" />
                </svg>
            </div>

            <h3 class="text-[15px] font-medium text-gray-800 mb-1">Cetak struk?</h3>
            <p class="text-[12px] text-gray-400 mb-4">
                Gunakan
                <kbd
                    class="inline-flex items-center px-1 py-0.5 rounded text-[11px] border border-gray-200 bg-gray-50 text-gray-500 font-mono">↑</kbd>
                <kbd
                    class="inline-flex items-center px-1 py-0.5 rounded text-[11px] border border-gray-200 bg-gray-50 text-gray-500 font-mono">↓</kbd>
                untuk navigasi,
                <kbd
                    class="inline-flex items-center px-1 py-0.5 rounded text-[11px] border border-gray-200 bg-gray-50 text-gray-500 font-mono">↵</kbd>
                untuk pilih.
            </p>

            {{-- RESEP TUNAI buttons --}}
            <div id="btnGroupResep" class="hidden flex-col gap-2">

                <button id="btnStrukPelanggan" data-modal-btn data-mode="pelanggan"
                    class="modal-print-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border border-gray-100 bg-white hover:bg-gray-50 text-left transition-colors">
                    <div
                        class="modal-btn-icon w-8 h-8 rounded-lg flex items-center justify-center bg-gray-100 text-gray-500 flex-shrink-0 transition-colors">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 6 2 18 2 18 9" />
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                            <rect x="6" y="14" width="12" height="8" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <span class="block text-[13px] font-medium text-gray-700 modal-btn-label">Struk
                            pelanggan</span>
                        <span class="block text-[11px] text-gray-400">Print struk pembayaran saja</span>
                    </div>
                    <span
                        class="modal-btn-enter text-[11px] text-gray-300 opacity-0 transition-opacity font-mono">↵</span>
                </button>

                <button id="btnStrukPelangganResep" data-modal-btn data-mode="pelanggan_resep"
                    class="modal-print-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border border-gray-100 bg-white hover:bg-gray-50 text-left transition-colors">
                    <div
                        class="modal-btn-icon w-8 h-8 rounded-lg flex items-center justify-center bg-gray-100 text-gray-500 flex-shrink-0 transition-colors">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="16" y1="13" x2="8" y2="13" />
                            <line x1="16" y1="17" x2="8" y2="17" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <span class="block text-[13px] font-medium text-gray-700 modal-btn-label">Struk + resep</span>
                        <span class="block text-[11px] text-gray-400">Print struk dan lembar resep</span>
                    </div>
                    <span
                        class="modal-btn-enter text-[11px] text-gray-300 opacity-0 transition-opacity font-mono">↵</span>
                </button>

                <div class="h-px bg-gray-100 my-1"></div>

                <button id="btnCancelResep" data-modal-btn data-mode="cancel"
                    class="modal-print-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border border-gray-100 bg-white hover:bg-gray-50 text-left transition-colors">
                    <div
                        class="modal-btn-icon w-8 h-8 rounded-lg flex items-center justify-center bg-gray-100 text-gray-500 flex-shrink-0 transition-colors">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 12H5M5 12l7-7M5 12l7 7" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <span class="block text-[13px] font-medium text-gray-700 modal-btn-label">Batal</span>
                        <span class="block text-[11px] text-gray-400">Kembali ke transaksi</span>
                    </div>
                    <span
                        class="modal-btn-enter text-[11px] text-gray-300 opacity-0 transition-opacity font-mono">↵</span>
                </button>
            </div>

            {{-- Default buttons --}}
            <div id="btnGroupDefault" class="hidden flex-col gap-2">

                <button id="btnPrint" data-modal-btn data-mode="pelanggan"
                    class="modal-print-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border border-gray-100 bg-white hover:bg-gray-50 text-left transition-colors">
                    <div
                        class="modal-btn-icon w-8 h-8 rounded-lg flex items-center justify-center bg-gray-100 text-gray-500 flex-shrink-0 transition-colors">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 6 2 18 2 18 9" />
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                            <rect x="6" y="14" width="12" height="8" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <span class="block text-[13px] font-medium text-gray-700 modal-btn-label">Cetak struk</span>
                        <span class="block text-[11px] text-gray-400">Print struk untuk pelanggan</span>
                    </div>
                    <span
                        class="modal-btn-enter text-[11px] text-gray-300 opacity-0 transition-opacity font-mono">↵</span>
                </button>

                <button id="btnSkipPrint" data-modal-btn data-mode="none"
                    class="modal-print-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border border-gray-100 bg-white hover:bg-gray-50 text-left transition-colors">
                    <div
                        class="modal-btn-icon w-8 h-8 rounded-lg flex items-center justify-center bg-gray-100 text-gray-500 flex-shrink-0 transition-colors">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                            stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <span class="block text-[13px] font-medium text-gray-700 modal-btn-label">Tanpa struk</span>
                        <span class="block text-[11px] text-gray-400">Selesaikan tanpa mencetak</span>
                    </div>
                    <span
                        class="modal-btn-enter text-[11px] text-gray-300 opacity-0 transition-opacity font-mono">↵</span>
                </button>

                <div class="h-px bg-gray-100 my-1"></div>

                <button id="btnCancel" data-modal-btn data-mode="cancel"
                    class="modal-print-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border border-gray-100 bg-white hover:bg-gray-50 text-left transition-colors">
                    <div
                        class="modal-btn-icon w-8 h-8 rounded-lg flex items-center justify-center bg-gray-100 text-gray-500 flex-shrink-0 transition-colors">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M19 12H5M5 12l7-7M5 12l7 7" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <span class="block text-[13px] font-medium text-gray-700 modal-btn-label">Batal</span>
                        <span class="block text-[11px] text-gray-400">Kembali ke transaksi</span>
                    </div>
                    <span
                        class="modal-btn-enter text-[11px] text-gray-300 opacity-0 transition-opacity font-mono">↵</span>
                </button>
            </div>

        </div>
    </div>
@endif
{{-- ================================= All Sales Modal =============================== --}}

{{-- Master Modal --}}
<div id="masterModalMedicine" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-[99999]">
    <div class="bg-white rounded-2xl w-[90%] overflow-hidden shadow-xl relative">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2v-4M9 21H5a2 2 0 01-2-2v-4m0 0h18" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-800 leading-tight">Master data medicine</p>
                    <p class="text-xs text-gray-400 leading-tight">Medicine inventory &amp; stock</p>
                </div>
            </div>
            <button id="closeMasterModal"
                class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-400 hover:text-red-500 hover:border-red-200 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Search --}}
        <div class="px-6 pt-4 pb-3">
            <div class="relative">
                <svg xmlns="http://www.w3.org/2000/svg"
                    class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                </svg>
                <input type="text" placeholder="Search medicine, manufacturer, composition…"
                    onkeyup="onSearch(this.value)"
                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400 placeholder-gray-400 transition" />
            </div>
        </div>

        {{-- Table --}}
        <div id="medicineScroll" class="overflow-x-auto overflow-y-auto max-h-[400px] px-6">
            <table class="w-full masterTable text-sm" style="table-layout: fixed;">
                <colgroup>
                    <col style="width: 40px">
                    <col style="width: 160px">
                    <col style="width: 120px">
                    <col style="width: 140px">
                    <col style="width: 80px">
                    <col style="width: 100px">
                    <col style="width: 60px">
                    <col style="width: 80px">
                </colgroup>
                <thead class="sticky top-0 z-10">
                    <tr class="bg-amber-400">
                        <th
                            class="py-2.5 px-2 text-left text-[11px] font-bold uppercase tracking-wide text-amber-900">
                            #</th>
                        <th
                            class="py-2.5 px-2 text-left text-[11px] font-bold uppercase tracking-wide text-amber-900">
                            Name</th>
                        <th
                            class="py-2.5 px-2 text-left text-[11px] font-bold uppercase tracking-wide text-amber-900">
                            Pabrik</th>
                        <th
                            class="py-2.5 px-2 text-left text-[11px] font-bold uppercase tracking-wide text-amber-900">
                            Komposisi</th>
                        <th
                            class="py-2.5 px-2 text-left text-[11px] font-bold uppercase tracking-wide text-amber-900">
                            Kemasan</th>
                        <th
                            class="py-2.5 px-2 text-right text-[11px] font-bold uppercase tracking-wide text-amber-900">
                            Harga</th>
                        <th
                            class="py-2.5 px-2 text-right text-[11px] font-bold uppercase tracking-wide text-amber-900">
                            Stok</th>
                        <th
                            class="py-2.5 px-2 text-center text-[11px] font-bold uppercase tracking-wide text-amber-900">
                            Status</th>
                    </tr>
                </thead>
                <tbody id="masterTable" class="divide-y divide-gray-100">
                    {{-- Rows injected via JS --}}
                </tbody>
            </table>

            <div id="loader" class="text-center py-4 text-sm text-gray-400 hidden">
                Loading…
            </div>
        </div>

        {{-- Footer --}}
        <div class="px-6 py-3 border-t border-gray-100 flex items-center justify-between">
            <p id="masterTableCount" class="text-xs text-gray-400">Showing 0 items</p>
            <div class="flex items-center gap-1">
                <button id="masterPrevPage"
                    class="px-2 py-1 text-xs border border-gray-200 rounded-md text-gray-500 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    ‹
                </button>
                <span id="masterPageInfo" class="text-xs text-gray-400 px-2">Page 1</span>
                <button id="masterNextPage"
                    class="px-2 py-1 text-xs border border-gray-200 rounded-md text-gray-500 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    ›
                </button>
            </div>
        </div>

    </div>
</div>
<div id="pinModal" class="hidden fixed inset-0 z-[999999] items-center justify-center bg-black/40">
    <div id="pinModalCard" class="bg-white rounded-2xl p-6 w-full max-w-sm mx-4"
        style="border: 0.5px solid rgba(0,0,0,0.08);">

        {{-- Icon --}}
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4" style="background: #FFF7ED;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#F97316"
                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
            </svg>
        </div>
        <h3 class="text-[15px] font-medium text-gray-800 mb-1">Masukkan PIN rahasia</h3>
        <p class="text-[12px] text-gray-400 mb-5">Masukkan 4 digit PIN untuk melanjutkan aksi ini.</p>

        {{-- PIN Dots --}}
        <div class="flex justify-center gap-3 mb-2" id="pinModalDots">
            <div class="pin-modal-dot w-3 h-3 rounded-full border-2 border-gray-300 transition-all duration-150">
            </div>
            <div class="pin-modal-dot w-3 h-3 rounded-full border-2 border-gray-300 transition-all duration-150">
            </div>
            <div class="pin-modal-dot w-3 h-3 rounded-full border-2 border-gray-300 transition-all duration-150">
            </div>
            <div class="pin-modal-dot w-3 h-3 rounded-full border-2 border-gray-300 transition-all duration-150">
            </div>

        </div>

        {{-- Error --}}
        <p id="pinModalError" class="hidden text-center text-[11px] text-red-500 mb-3">PIN salah. Coba lagi.</p>

        {{-- Numpad --}}
        <div class="grid grid-cols-3 gap-2 mb-4" id="pinModalNumpad">

            @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $key)
                <button data-pin-key="{{ $key }}"
                    class="h-12 rounded-xl border border-gray-100 bg-white text-[15px] font-medium text-gray-700 hover:bg-gray-50 active:scale-95 transition-all duration-100">
                    {{ $key }}
                </button>
            @endforeach

            {{-- Empty --}}
            <div></div>

            {{-- 0 --}}
            <button data-pin-key="0"
                class="h-12 rounded-xl border border-gray-100 bg-white text-[15px] font-medium text-gray-700 hover:bg-gray-50 active:scale-95 transition-all duration-100">
                0
            </button>

            {{-- Backspace --}}
            <button data-pin-key="backspace"
                class="h-12 rounded-xl border border-gray-100 bg-white flex items-center justify-center text-gray-400 hover:bg-gray-50 active:scale-95 transition-all duration-100">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z" />
                    <line x1="18" y1="9" x2="12" y2="15" />
                    <line x1="12" y1="9" x2="18" y2="15" />
                </svg>
            </button>

        </div>

        {{-- Cancel --}}
        <button id="pinModalCancelBtn"
            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border border-gray-100 bg-white hover:bg-gray-50 text-left transition-colors">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-gray-100 text-gray-500 flex-shrink-0">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M5 12l7-7M5 12l7 7" />
                </svg>
            </div>
            <div class="flex-1">
                <span class="block text-[13px] font-medium text-gray-700">Batal</span>
                <span class="block text-[11px] text-gray-400">Kembali ke transaksi</span>
            </div>
        </button>

    </div>
</div>
<div id="transactionModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-[99999]">

    <div class="bg-white rounded-xl w-[95%] p-6 relative">

        <div class="card w-full shadow-md rounded-2xl p-6 bg-white">
            <div class="flex items-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-600 mr-3" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                </svg>
                <h2 class="text-2xl font-bold text-gray-800">
                    Transaksi
                </h2>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="border rounded-lg p-3">
                    <input type="text" placeholder="Search transaction..."
                        onkeyup="onTransactionSearch(this.value)"
                        class="w-full border rounded px-3 py-2 text-sm mb-2">

                    <div id="transactionScroll" class="overflow-y-auto max-h-[420px]">
                        <table class="w-full transactionTable">
                            <thead class="sticky top-0 bg-white">
                                <tr>
                                    <th>#</th>
                                    <th>Tanggal</th>
                                    <th>Jam</th>
                                    <th>Code</th>
                                    <th>Nama Pasien</th>
                                    <th>Total</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="transactionTable"></tbody>
                        </table>

                        <div id="transactionLoader" class="text-center py-3 hidden">
                            Loading...
                        </div>
                    </div>
                </div>
                <div class="border rounded-lg p-3">
                    <h3 class="font-semibold mb-2">
                        Barang Dibeli
                    </h3>

                    <div id="itemScroll" class="overflow-y-auto max-h-[420px]">
                        <table class="w-full transactionTable">
                            <thead class="sticky top-0 bg-white">
                                <tr>
                                    <th>#</th>
                                    <th>Nama Obat</th>
                                    <th>Qty</th>
                                    <th>Harga</th>
                                    <th>Disc</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody id="itemTable">
                                <tr>
                                    <td colspan="5" class="text-center text-gray-400 py-6">
                                        Select a transaction
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- CLOSE -->
        <button id="closeTransactionModal" class="absolute top-3 right-3 text-gray-500 hover:text-red-500">
            ✕
        </button>
    </div>
</div>

{{-- Print Confirmation --}}


<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.4/qz-tray.js"></script>

<script>
    window.QZ = {
        async connect() {
            if (!qz.websocket.isActive()) {
                await qz.websocket.connect();
            }
        }
    };
    // WHEN PAGE LOADED
    let page = 1;
    let loading = false;
    let lastPage = false;
    let search = '';
    let isScanning = false;

    function back() {
        window.location.href = "{{ route('home') }}";
    }
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.cart-row').forEach(row => {
            attachRowEvents(row);
        });

        loadData();

        const scrollBox = document.getElementById('medicineScroll');

        scrollBox.addEventListener('scroll', () => {
            if (
                scrollBox.scrollTop + scrollBox.clientHeight >=
                scrollBox.scrollHeight - 50
            ) {
                loadData();
            }
        });
        const transactionScroll = document.getElementById('transactionScroll');
        transactionScroll.addEventListener('scroll', () => {
            if (
                transactionScroll.scrollTop + scroll.clientHeight >=
                transactionScroll.scrollHeight - 50
            ) {
                loadTransactionData();
            }
        });
    });

    // Global Variable

    const faktur = document.getElementById('faktur');
    let checkoutPayload = {};
    let checkoutProcessing = false;


    // Modals JS
    const newPatientModal = document.getElementById('newPatientModal');
    const newPatientModalContent = document.getElementById('newPatientModalContent');
    const openNewPatientBtn = document.getElementById('btnNewPatient');
    const closeNewPatientBtn = document.getElementById('closeNewPatientModal');
    const newPatientForm = document.getElementById('newPatientForm');

    const openNewDoctorBtn = document.getElementById('openNewDoctorBtn');
    const newDoctorModal = document.getElementById('newDoctorModal');
    const newDoctorModalContent = document.getElementById('newDoctorModalContent');
    const closeNewDoctorBtn = document.getElementById('closeNewDoctorBtn');
    const newDoctorForm = document.getElementById('newDoctorForm');

    const openMasterModal = document.getElementById('openMasterModal');
    const openSearchModal = document.getElementById('openSearchModal');

    const masterModal = document.getElementById('masterModalMedicine');
    const transactionModal = document.getElementById('transactionModal');

    const closeMasterModal = document.getElementById('closeMasterModal');
    const closeTransactionModal = document.getElementById('closeTransactionModal');

    // Pin JS
    let pinModalValue = '';

    const pinModal = document.getElementById('pinModal');
    const pinModalCard = document.getElementById('pinModalCard');
    const pinModalDots = document.querySelectorAll('#pinModalDots .pin-modal-dot');
    const pinModalError = document.getElementById('pinModalError');
    const pinModalNumpad = document.getElementById('pinModalNumpad');
    const pinModalCancel = document.getElementById('pinModalCancelBtn');

    // Endpoints
    const endpoint = "{{ route('products.search') }}";
    const endpointDebtor = "{{ route('debtors.search') }}";
    const endpointPatient = "{{ route('patients.search') }}";
    const endpointDoctor = "{{ route('doctors.search') }}";


    const trx_id = {{ $trx_id ?? 'null' }};
    console.log({{ $parameters }});
    var rounding = {{ $rounding }};
    var parameters = {{ $parameters }};
    var service = @json($service) || 0;

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
    const user_id = {{ auth()->user()->id }};
    const shift_logs_id = {{ $activeshift->id }}
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

    // Print Modal
    const printModal = document.getElementById('printConfirmModal');
    const btnGroupResep = document.getElementById('btnGroupResep');
    const btnGroupDefault = document.getElementById('btnGroupDefault');


    // Variabel Transaksi & Cart
    var transaction_id = trx_id;
    var total_discount = {{ $discount_total }};
    var discount = "";
    var subtotal_discount = "";
    var paymentType = "";

    const subtotalpreview = document.getElementById('subtotal');

    var total_item = "";
    var medicine_id = "";
    var price2 = "";
    var item_finalprice = "";
    let grossprice = "";
    let rawprice
    let het;
    var payInput = document.getElementById('pay');
    var bank_name_input = document.getElementById('bank_name');
    var discounsubtotal = document.getElementById('discounsubtotal');
    var discounsubtotalvalue = "";
    var payment_total = document.getElementById('payment_total');
    var edit_status = 0;
    // var debtor_id = "";
    // var patient_id = "";
    // var doctor_id = "";

    let totalbought = {{ $total_price->total_price + $total_price->embalase }};
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


    // Set Initial Value

    // Total Beli Input
    cartTotalInput.value = formatRupiah(totaltransaction);

    // Total Transaksi Input
    payment_total.value = formatRupiah(totalbought);


    if (subtotalpreview) {
        subtotalpreview.value = formatRupiah(totaltransaction);
    }
    // Total Discount Input
    previewdiscounttotal.value = formatRupiah(total_discount);

    previewtransactiontotal.value = formatRupiah(totaltransaction);
    if (packageInput) {
        document.getElementById('package').value = existingpackage;
    }



    // Table

    let selectedRowId = null;
    let selectedTransactionId = null;

    // Button / Submitting
    let isSubmitting = false;


    // ===============================
    // Helper Functions
    // ===============================


    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('newPatientForm');
        const doctorFormEl = document.getElementById('newDoctorForm');

        const inputs = Array.from(
            form.querySelectorAll('input:not([type="hidden"]):not([disabled])')
        );

        inputs.forEach((input, index) => {
            input.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;

                e.preventDefault();

                const next = inputs[index + 1];

                if (next) {
                    next.focus();
                } else {
                    form.requestSubmit(); // submit only on last input
                }
            });
        });
        if (!doctorFormEl) return;

        const doctorInputs = Array.from(
            doctorFormEl.querySelectorAll('input:not([type="hidden"]):not([disabled])')
        );

        doctorInputs.forEach((doctorInput, doctorIndex) => {
            doctorInput.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;

                e.preventDefault();

                const nextDoctorInput = doctorInputs[doctorIndex + 1];

                if (nextDoctorInput) {
                    nextDoctorInput.focus();
                } else {
                    doctorFormEl.requestSubmit(); // submit on last input
                }
            });
        });
    });

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('newDoctorForm');
        if (!form) return;

        const inputs = Array.from(
            form.querySelectorAll('input:not([type="hidden"]):not([disabled])')
        );

        inputs.forEach((input, index) => {
            input.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;

                e.preventDefault();

                const nextInput = inputs[index + 1];

                if (nextInput) {
                    nextInput.focus();
                } else {
                    form.requestSubmit(); // submit on last input
                }
            });
        });
    });

    function formatRupiah(value) {
        const number = Number(value) || 0;
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(number);
    }
    document.addEventListener('input', function(e) {
        if (e.target.matches('input[type="number"]')) {
            if (e.target.value < 0) {
                e.target.value = Math.abs(e.target.value);
            }
        }
    });
    document.addEventListener('click', function(e) {
        if (
            !patientbox.contains(e.target)
        ) {
            closepatientBox();
        }
    });
    patientbox.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    if (doctorbox) {
        document.addEventListener('click', function(e) {
            if (
                !doctorbox.contains(e.target)
            ) {
                closedoctorBox();
            }
        });
        doctorbox.addEventListener('click', function(e) {
            e.stopPropagation();
        });
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

    function cleanRupiah(value) {
        return value.replace(/\D/g, '') || 0;
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
    // 

    // checkout loading 
    function showCheckoutLoading() {
        document.getElementById('checkoutLoading').classList.remove('hidden');
        document.getElementById('checkoutLoading').classList.add('flex');
    }

    function hideCheckoutLoading() {
        document.getElementById('checkoutLoading').classList.add('hidden');
        document.getElementById('checkoutLoading').classList.remove('flex');
    }

    // Print Modal JS
    const CheckoutModal = (() => {
        const modal = document.getElementById('printConfirmModal');
        const btnGroupDefault = document.getElementById('btnGroupDefault');
        const btnGroupResep = document.getElementById('btnGroupResep');

        let activeButtons = [];
        let focusedIndex = 0;

        // ── Focus helpers ─────────────────────────────────────────────────────────
        function applyFocus(index) {
            // Clear all
            activeButtons.forEach(btn => {
                btn.classList.remove('bg-blue-50', 'border-blue-200');
                btn.querySelector('.modal-btn-icon').classList.remove('bg-blue-100', 'text-blue-600');
                btn.querySelector('.modal-btn-icon').classList.add('bg-gray-100', 'text-gray-500');
                btn.querySelector('.modal-btn-label').classList.remove('text-blue-700');
                btn.querySelector('.modal-btn-label').classList.add('text-gray-700');
                btn.querySelector('.modal-btn-enter').classList.add('opacity-0');
                btn.querySelector('.modal-btn-enter').classList.remove('opacity-100');
            });

            // Apply to focused
            focusedIndex = (index + activeButtons.length) % activeButtons.length;
            const btn = activeButtons[focusedIndex];
            btn.classList.add('bg-blue-50', 'border-blue-200');
            btn.querySelector('.modal-btn-icon').classList.remove('bg-gray-100', 'text-gray-500');
            btn.querySelector('.modal-btn-icon').classList.add('bg-blue-100', 'text-blue-600');
            btn.querySelector('.modal-btn-label').classList.remove('text-gray-700');
            btn.querySelector('.modal-btn-label').classList.add('text-blue-700');
            btn.querySelector('.modal-btn-enter').classList.remove('opacity-0');
            btn.querySelector('.modal-btn-enter').classList.add('opacity-100');
            btn.focus({
                preventScroll: true
            });
        }

        // ── Keyboard handler ──────────────────────────────────────────────────────
        function onKeyDown(e) {
            if (!activeButtons.length) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                applyFocus(focusedIndex + 1);
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                applyFocus(focusedIndex - 1);
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                activeButtons[focusedIndex].click();
            }
            if (e.key === 'Escape') {
                close();
            }
        }

        // ── Open ──────────────────────────────────────────────────────────────────
        function open(transactionType) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            if (transactionType === 'RESEP TUNAI') {
                btnGroupResep.classList.remove('hidden');
                btnGroupResep.classList.add('flex');
                btnGroupDefault.classList.add('hidden');
                btnGroupDefault.classList.remove('flex');
                activeButtons = Array.from(btnGroupResep.querySelectorAll('[data-modal-btn]'));
            } else {
                btnGroupDefault.classList.remove('hidden');
                btnGroupDefault.classList.add('flex');
                btnGroupResep.classList.add('hidden');
                btnGroupResep.classList.remove('flex');
                activeButtons = Array.from(btnGroupDefault.querySelectorAll('[data-modal-btn]'));
            }

            focusedIndex = 0;
            applyFocus(0);
            document.addEventListener('keydown', onKeyDown);
        }

        // ── Close ─────────────────────────────────────────────────────────────────
        function close() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            // Reset all button styles
            document.querySelectorAll('[data-modal-btn]').forEach(btn => {
                btn.classList.remove('bg-blue-50', 'border-blue-200');
                btn.querySelector('.modal-btn-icon').classList.remove('bg-blue-100', 'text-blue-600');
                btn.querySelector('.modal-btn-icon').classList.add('bg-gray-100', 'text-gray-500');
                btn.querySelector('.modal-btn-label').classList.remove('text-blue-700');
                btn.querySelector('.modal-btn-label').classList.add('text-gray-700');
                btn.querySelector('.modal-btn-enter').classList.add('opacity-0');
                btn.querySelector('.modal-btn-enter').classList.remove('opacity-100');
            });
            activeButtons = [];
            document.removeEventListener('keydown', onKeyDown);
            document.getElementById('pay').focus();
        }

        // ── Bind button handlers (called each checkout to capture fresh closure) ──
        function bindButtons(handlers) {
            document.getElementById('btnPrint').onclick = handlers.onBtnPrint;
            document.getElementById('btnSkipPrint').onclick = handlers.onBtnSkipPrint;
            document.getElementById('btnCancel').onclick = handlers.onBtnCancel;
            document.getElementById('btnStrukPelanggan').onclick = handlers.onBtnStrukPelanggan;
            document.getElementById('btnStrukPelangganResep').onclick = handlers.onBtnStrukPelangganResep;
            document.getElementById('btnCancelResep').onclick = handlers.onBtnCancelResep;
        }

        return {
            open,
            close,
            bindButtons
        };
    })();




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

    // Searchbar / Search Medicine
    function render(items) {
        list.innerHTML = '';
        activeIndex = -1;

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
        <div class="flex items-start justify-between gap-3 p-1">
            <div class="flex flex-col gap-1 min-w-0">
                <div class="font-semibold text-sm text-gray-800">${escapeHtml(it.name)}</div>
                <div class="font-mono text-[11px] text-blue-400 bg-blue-50 px-2 py-0.5 rounded-md w-fit">${escapeHtml(it.code)}</div>

                <div class="grid grid-cols-2 gap-x-4 gap-y-1 mt-1">
                    <div>
                        <div class="text-[10px] uppercase tracking-wide text-gray-400">Etalase</div>
                        <div class="text-xs text-gray-700">${escapeHtml(it.etalases?.name || '—')}</div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase tracking-wide text-gray-400">Lokasi</div>
                        <div class="text-xs text-gray-700">${escapeHtml(it.locations?.name || '—')}</div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase tracking-wide text-gray-400">Barcode</div>
                        <div class="text-xs text-gray-700 font-mono">${escapeHtml(it.barcode || '—')}</div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase tracking-wide text-gray-400">Strip</div>
                        <div class="text-xs text-gray-700 font-mono">${escapeHtml(it.strip || '—')}</div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase tracking-wide text-gray-400">Etalase</div>
                        <div class="text-xs text-gray-700">${escapeHtml(it.etalases?.name || '—')}</div>
                    </div>
                </div>

                <div class="flex gap-2 flex-wrap mt-1">
                    <div class="flex items-center gap-1 bg-emerald-50 border border-emerald-200 rounded-md px-2 py-1">
                        <span class="text-[10px] text-emerald-500 font-medium">Stok</span>
                        <span class="text-xs font-bold text-emerald-700">${escapeHtml(String(it.storage_stock + it.counter_stock || '—'))}</span>
                    </div>
                    <div class="flex items-center gap-1 bg-violet-50 border border-violet-200 rounded-md px-2 py-1">
                        <span class="text-[10px] text-violet-500 font-medium">Gudang</span>
                        <span class="text-xs font-bold text-violet-700">${escapeHtml(String(it.storage_stock || '—'))}</span>
                    </div>
                    <div class="flex items-center gap-1 bg-amber-50 border border-amber-200 rounded-md px-2 py-1">
                        <span class="text-[10px] text-amber-500 font-medium">Pelayanan</span>
                        <span class="text-xs font-bold text-amber-700">${escapeHtml(String(it.counter_stock || '—'))}</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col items-end flex-shrink-0">
                <div class="text-sm font-bold text-gray-800 whitespace-nowrap">${formatRupiah(it.net_price)}</div>
                ${it.het_price && Number(it.het_price)
                    ? `<div class="text-xs font-semibold text-red-500 bg-red-50 border border-red-200 rounded px-1.5 py-0.5 mt-1 whitespace-nowrap">HET: ${formatRupiah(it.het_price)}</div>`
                    : ''}
            </div>
        </div>
    `;

            li.addEventListener('mousedown', (e) => {
                e.preventDefault();
                selectItem(it);
            });

            list.appendChild(li);
        }
    }

    function selectItem(it) {
        if (!it) {
            isScanning = false;
            return;
        }

        isScanning = true;
        clearTimeout(debounceTimer);

        hidden.value = it.id;
        medicine_id = it.id;
        input.value = '';
        quantity.value = '';
        totalprice.value = '';
        discount = '';
        stock.value = it.stock;
        unit.value = it.unit;
        name.value = it.name;

        if (currenttransaction == 'KREDIT' || currenttransaction == 'RESEP TUNAI') {
            dosage.value = it.dosage;
        }

        let raw;
        if (it.het_price !== null && it.het_price !== 0 && it.het_price !== '') {
            raw = Number(it.het_price);
            rawprice = raw;
            het = 1;
        } else {
            raw = Number(it.net_price) * Number(parameters || 1);
            rawprice = it.net_price;
            het = 0;
        }

        price.value = formatRupiah(raw);
        price2 = raw;
        item_finalprice = raw;

        items = [];
        list.innerHTML = '';
        closeBox();

        setTimeout(() => {
            isScanning = false;

            if (currenttransaction == 'RESEP TUNAI' && racikstatus != 0) {
                dosageRInput.focus();
            } else if (currenttransaction == 'KREDIT' && racikstatus != 0) {
                dosageRInput.focus();
            } else {
                quantity.focus();
            }
        }, 50);
    }

    async function renderFix(term) {
        const url = `${endpoint}?q=${encodeURIComponent(term)}`;

        try {
            const res = await fetch(url, {
                headers: {
                    Accept: 'application/json'
                }
            });

            if (!res.ok) {
                console.error(`Request failed: ${res.status}`);
                isScanning = false;
                return;
            }

            const results = await res.json();
            if (results && results.length > 0) {
                selectItem(results[0]);
            } else {
                isScanning = false;
                closeBox();
            }
        } catch (err) {
            console.error('Fetch error:', err);
            isScanning = false;
        }
    }

    // Search (Debounced)
    let debounceTimer = null;

    function debounce(fn, delay) {
        return (...args) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => fn(...args), delay);
        };
    }

    const doSearch = debounce(async (term) => {
        if (isScanning) return;

        if (!term.trim()) {
            list.innerHTML = '';
            closeBox();
            return;
        }

        const url = `${endpoint}?q=${encodeURIComponent(term)}`;
        const res = await fetch(url, {
            headers: {
                Accept: 'application/json'
            }
        });
        if (!res.ok) return;

        if (isScanning) return;

        items = await res.json();
        render(items);
        openBox();
    }, 250);

    // Single input handler
    input.addEventListener('input', (e) => {
        if (isScanning) return;

        const term = e.target.value.trim();
        if (term.length > 0) {
            doSearch(e.target.value);
        } else {
            clearTimeout(debounceTimer);
            list.innerHTML = '';
            closeBox();
        }
    });

    // Single keydown handler
    input.addEventListener('keydown', (e) => {
        const max = items.length - 1;

        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(debounceTimer);

            if (activeIndex >= 0 && items[activeIndex]) {
                selectItem(items[activeIndex]);
            } else {
                isScanning = true;
                renderFix(input.value);
            }
        } else if (e.key === 'ArrowDown') {
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
        } else if (e.key === 'Escape') {
            clearTimeout(debounceTimer);
            closeBox();
        }
    });

    // ================ Pencarian Pasien =========================

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
    if (bank_name_input) {
        // ================ Pilih Tipe Pembayaran =========================

        function getPaymentType() {
            const selected = document.querySelector('input[name="payment_type"]:checked');
            paymentType = selected ? selected.value : null;

            // Show / hide bank name field
            const bankWrapper = document.getElementById('bankNameWrapper');
            const bankInput = document.getElementById('bank_name');

            if (paymentType === 'TRANSFER') {
                bankWrapper.classList.remove('hidden');
                // Small delay so the element is visible before focusing
                setTimeout(() => bankInput.focus(), 50);
            } else {
                bankWrapper.classList.add('hidden');
                bankInput.value = '';
            }

            console.log('Payment type:', paymentType);
            return paymentType;
        }

        const paymentRadios = document.querySelectorAll('input[name="payment_type"]');
        const nextInput = document.getElementById('pay');

        paymentRadios.forEach(radio => {
            radio.addEventListener('keydown', function(event) {
                if (event.key !== 'Enter') return;
                event.preventDefault();

                paymentType = radio.value;
                console.log('Payment type:', radio.value);

                // If Transfer is selected via Enter, focus the bank name field instead of pay
                if (radio.value === 'TRANSFER') {
                    const bankWrapper = document.getElementById('bankNameWrapper');
                    const bankInput = document.getElementById('bank_name');
                    bankWrapper.classList.remove('hidden');
                    setTimeout(() => bankInput.focus(), 50);
                } else {
                    document.getElementById('bankNameWrapper').classList.add('hidden');
                    document.getElementById('bank_name').value = '';
                    nextInput.focus();
                }
            });
        });

        bank_name_input.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                nextInput.focus();
            }
        });
    }

    function selectPatient(it) {
        document.getElementById('patient_id').value = it.id;
        if (currenttransaction == 'RESEP TUNAI' || currenttransaction == 'KREDIT') {
            document.getElementById('doctorSearch').focus();
        } else {
            document.getElementById('payment_type').focus();
            document.getElementById('payment_type').checked = true;


        }
        document.getElementById('patientSearch').value = it.name;
        closepatientBox();
    }


    // ==================== Pencarian Dokter ====================

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
            document.getElementById('payment_type').focus();
            document.getElementById('payment_type').checked = true;
        }
        document.getElementById('doctor_id').value = it.id;
        document.getElementById('doctorSearch').value = it.name;


        closedoctorBox();
    }


    // Pencarian Debitur

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
            document.getElementById('debtor_name').value = it.name;
            document.getElementById('embalase').value = it.parameters[0].embalas;;

            closedebtorBox();
        }
    }

    // Perhitungan Harga & Diskon
    function count(val) {

        val = Math.floor(Number(val) || 0);
        console.log('harga diskon adalah : ' + discount);
        if (discount == "") {
            discount = 0;
        }
        console.log('harganya itu adalah : ' + price2);
        total_item = val;
        roundedtotal = price2 * val - discount;
        if (currenttransaction == "KREDIT") {
            subtotal = roundedtotal;
        } else if (transaction_type == "RESEP TUNAI") {
            subtotal = Math.ceil(roundedtotal / 1000) * 1000 + parseInt(service);
        } else {
            subtotal = Math.ceil(roundedtotal / 1000) * 1000;
        }
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
            discount = Math.round(val / 1000) * 1000;
        } else {
            const discountAmount = subtotal * val / 100;
            final_price = subtotal - discountAmount;
            discount = Math.round(discountAmount / 1000) * 1000;
        }
        final_price = Math.round(final_price / 1000) * 1000;
        totalprice.value = formatRupiah(final_price);
    }


    function countSubtotalDiscount(val) {

        let discount = 0;
        let final_price = 0;
        if (val > 100) {
            discount = val;
        } else {
            discount = totaltransaction * val / 100;
        }
        discount = Math.ceil(discount / 1000) * 1000;
        final_price = totaltransaction - discount;
        discounsubtotalvalue = discount;
        final_price = Math.ceil(final_price / 1000) * 1000;
        subtotal_discount = discount;
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

    function addToCart(medicine_id, transaction_id, quantity, discount, embalase, cart_type, package, dosage_r, price2,
        raw_total, total_price, final_price, racikstatus) {

        var medicine_type = currenttransaction;
        if (document.getElementById('quantity').value != "") {
            if (edit_status != 0) {
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
                        price2,
                        raw_total,
                        total_price,
                        final_price,
                        grossprice,
                        racikstatus,
                        medicine_type,
                        service,

                    })
                    .then(response => {

                        const item = response.data.item;
                        totaltransaction = response.data.total_transaction;
                        total_discount = response.data.total_discount;
                        totalbought = response.data.totalbought;

                        console.log("Updated cart item:", item);
                        // Reset Inputs and variables value
                        [stock, unit, quantity, price, name, totalprice].forEach(el => el.value = "");
                        discountInput.value = "";
                        discount = "";

                        resetInputs();
                        document.getElementById('productSearch').focus();
                        closeBox();
                        cartTotalInput.value = formatRupiah(totaltransaction);
                        if (subtotalpreview) {

                            subtotalpreview.value = formatRupiah(totaltransaction);
                        }
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
                        checkbox.checked = false;
                        checkbox.dispatchEvent(new Event('change'));
                        packageInput.value = "";
                        // Reattach event listeners if needed
                        attachRowEvents(document.getElementById(`itemincart${item.id}`));
                    })
                    .catch(error => {
                        console.error("Error updating cart:", error.response ? error.response.data : error.message);
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
                    price2,
                    raw_total,
                    total_price,
                    final_price,
                    grossprice,
                    racikstatus,
                    medicine_type,
                    service

                }).then(response => {
                    const item = response.data;
                    let recipe_row = "";

                    totaltransaction += total_price;
                    totalbought = parseFloat(totalbought) + parseFloat(grossprice);
                    total_discount += parseFloat(discount);
                    // price2 = formatRupiah(totaltransaction);
                    // Reset input fields
                    [stock, unit, quantity, price, name, totalprice].forEach(el => el.value = "");
                    discount = "";
                    console.log("Discount item:", discount);
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
                    if (subtotalpreview) {
                        subtotalpreview.value = formatRupiah(totaltransaction);
                    }

                    previewdiscounttotal.value = formatRupiah(total_discount);

                    previewtransactiontotal.value = formatRupiah(totaltransaction);
                    payment_total.value = formatRupiah(totalbought);



                    if (racikstatus != 0) {
                        recipe_row = "bg-[#eefff8]";
                    }

                    document.getElementById('carts').insertAdjacentHTML('beforeend', `
                    <tr id="itemincart${item.id}" data-id="${item.id}" 
                        class="cart-row border-b ${recipe_row} hover:bg-blue-50 transition text-[10px] cursor-pointer">
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

                    const newRow = document.getElementById(`itemincart${item.id}`); // safer
                    console.log('Newly inserted row:', newRow);
                    attachRowEvents(newRow);
                    discount = 0;
                    console.log('Reseting the discount variable', discount);


                }).catch(error => {
                    console.error("Error adding to cart:", error.response ? error.response.data : error
                        .message);
                });
            }
        } else {
            alert('isi transaksi dengan benar!');
        }


    }

    function submit() {

        if (isSubmitting) return; // guard against double-fire
        isSubmitting = true;

        const btn = document.querySelector('button[onclick="submit()"]');
        if (btn) btn.disabled = true;

        try {
            if (edit_status != 0) {
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
                    price2,
                    pharmacy_price,
                    true_price,
                    grossprice,
                    racikstatus
                });
                addToCart(
                    medicine_id, transaction_id, total_item, discount, jasa,
                    cart_type, pkg, dose, price2, pharmacy_price, true_price,
                    grossprice, racikstatus
                );
            } else {
                console.log('grossprice =', grossprice);

                if (discountInput.value === "") {
                    final_price = subtotal;
                    discount = 0;
                }
                if (jasa === "") {
                    jasa = 0;
                }

                if (transaction_type === "RESEP TUNAI") {
                    cart_type = "UM";
                } else if (transaction_type === "KREDIT") {
                    cart_type = "UK";
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
                    medicine_id, transaction_id, total_item, discount, jasa,
                    cart_type, pkg, dose, price2, pharmacy_price, true_price,
                    grossprice, racikstatus
                );
            }

            edit_status = 0;
            discount = 0;

        } finally {
            setTimeout(() => {
                isSubmitting = false;
                if (btn) btn.disabled = false;
            }, 1000);
        }
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

    function renumberRows() {
        const rows = document.querySelectorAll('#carts tr');
        rows.forEach((row, index) => {
            const numberCell = row.querySelector('td'); // Get The first recors
            if (numberCell) {
                numberCell.textContent = index + 1;
            }
        });
    }

    function deleteCartItem(id) {
        console.log('Deleting item:', id);
        axios.delete(`/transaction/cartItem/${id}`)
            .then(response => {
                console.log('Deleted:', response.data);
                const row = document.querySelector(`#itemincart${id}`);
                if (row) row.remove();
                // Renumbering
                renumberRows();
                selectedRowId = null;

                // Update Preview
                cartTotalInput.value = formatRupiah(response.data.total_transaction);
                if (subtotalpreview) {

                    subtotalpreview.value = formatRupiah(response.data.total_transaction);
                }
                previewdiscounttotal.value = formatRupiah(response.data.total_discount);
                previewtransactiontotal.value = formatRupiah(response.data.total_transaction);
                payment_total.value = formatRupiah(response.data.totalbought);
                totaltransaction = response.data.total_transaction;
                totalbought = response.data.totalbought;
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
                return;
            }

            if (confirm('Hapus item ini dari keranjang?')) {
                deleteCartItem(selectedRowId);
            }
        }
    });



    function editCartItem(id) {

        axios.get(`/getcart/cartItem/${id}`)
            .then(response => {
                edit_status = 1;
                const item = response.data;
                let raw = item.item_price;
                let rounded = raw;
                let totalval;

                medicine_id = item.medicine_id;
                total_item = item.quantity;
                item_finalprice = rounded;
                price2 = rounded;

                // Checkbox trigger if racikan

                if (transaction_type == "RESEP TUNAI" || currenttransaction == "KREDIT") {
                    totalval = price2 * item.quantity + parseInt(service);
                } else {
                    totalval = price2 * item.quantity;
                }
                subtotal = Math.ceil(totalval / 1000) * 1000;
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
                if (item.cart_type == "UP") {
                    transaction_type = "UPDS";
                    price2 = item.item_price;
                    price.value = formatRupiah(item.item_price);

                } else if (item.cart_type == "HV") {
                    transaction_type = "HV/OTC";
                    price2 = item.item_price;
                    price.value = formatRupiah(item.item_price);

                }
                totalprice.value = formatRupiah(item.total_price - item.discount);
                quantity.value = item.quantity;
                // price.value = formatRupiah(rounded);
                discountInput.value = item.discount;



                console.log('Received item data:', response.data);

            })
            .catch(err => console.error('Failed to load item data:', err));
    }

    // ======================== Checkout & Invoice ========================
    const Printer = (() => {
        let connected = false;

        function setupSecurity() {
            qz.security.setCertificatePromise(function(resolve, reject) {
                fetch('/qz/digital-certificate.txt', {
                        cache: 'no-store'
                    })
                    .then(r => r.text())
                    .then(cert => {
                        console.log(cert);
                        resolve(cert);
                    })
                    .catch(reject);
            });

            qz.security.setSignatureAlgorithm('SHA256');
            qz.security.setSignaturePromise(function(toSign) {
                return function(resolve, reject) {
                    fetch('/qz/sign', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector(
                                    'meta[name="csrf-token"]')?.content ?? '',
                            },
                            body: JSON.stringify({
                                data: toSign
                            }),
                        })
                        .then(r => r.json())
                        .then(d => d.signature ? resolve(d.signature) : reject(
                            'Signing failed'))
                        .catch(reject);
                };
            });
        }

        // Call once immediately when the module loads
        if (typeof qz !== 'undefined') {
            setupSecurity(); // ← moved here
        }

        async function connect() {
            if (connected) return;
            // setupSecurity() no longer called here
            await qz.websocket.connect();
            connected = true;
            qz.websocket.setClosedCallbacks(() => {
                connected = false;
            });
        }

        async function print(commands) {
            await connect();
            const config = qz.configs.create('EPSON TM-U220', {
                encoding: 'Cp866',
                copies: 1,
            });
            await qz.print(config, commands);
        }

        async function printReceipt(commands, fallbackUrl) {

            window.open(fallbackUrl, '_blank');

        }

        return {
            print,
            printReceipt,
            connect
        };
    })();

    // ── QZ Tray Status on page load ───────────────────────────────
    document.addEventListener('DOMContentLoaded', async () => {
        const badge = document.getElementById('qzStatus');
        if (!badge) return;

        if (typeof qz === 'undefined') {
            badge.className = 'badge bg-danger';
            badge.textContent = '🖨️ QZ Tray: Not Installed';
            return;
        }
        try {
            await Printer.connect();
            badge.className = 'badge bg-success';
            badge.textContent = '🖨️ Printer: Ready';
        } catch (e) {
            badge.className = 'badge bg-warning';
            badge.textContent = '🖨️ QZ Tray: Not Running';
        }
    });


    async function checkoutItem() {

        const paid = cleanRupiah(
            document.getElementById('pay').value
        );

        const changes = cleanRupiah(
            document.getElementById('trchange').value
        );

        let doctor_id = null;
        let debtor_id = null;

        // TRANSACTION TYPE
        if (transaction_type === 'RESEP TUNAI') {

            doctor_id =
                document.getElementById('doctor_id').value || null;

            debtor_id = null;

        } else if (
            transaction_type === 'UPDS' ||
            transaction_type === 'HV/OTC'
        ) {

            doctor_id = null;
            debtor_id = null;

        } else {

            doctor_id =
                document.getElementById('doctor_id').value || null;

            debtor_id =
                document.getElementById('debtor_id').value || null;
        }

        const patient_id =
            document.getElementById('patient_id').value || null;

        // VALIDATION
        if (
            transaction_type === 'RESEP TUNAI' &&
            !doctor_id
        ) {

            iziToast.warning({
                title: 'Peringatan',
                message: 'Silahkan Pilih Dokter Dulu',
                position: 'topRight'
            });

            return;
        }

        if (
            (
                transaction_type === 'RESEP KREDIT' ||
                transaction_type === 'RESEP ASURANSI'
            ) &&
            !debtor_id
        ) {

            iziToast.warning({
                title: 'Peringatan',
                message: 'Silahkan Pilih Debitur Dulu',
                position: 'topRight'
            });

            return;
        }

        if (!patient_id) {

            iziToast.warning({
                title: 'Peringatan',
                message: 'Silahkan Pilih Pasien',
                position: 'topRight'
            });

            return;
        }

        // PREVENT DOUBLE CLICK
        if (checkoutProcessing) {
            return;
        }

        // SAVE PAYLOAD
        checkoutPayload = {
            transaction_id,
            paid,
            subtotal,
            doctor_id,
            debtor_id,
            patient_id,
            changes,
        };

        showCheckoutLoading();

        try {

            // GET TRANSACTION ITEMS
            const response = await axios.post(
                "{{ route('transaction.getTransactionItem') }}", {
                    transaction_id,
                    paid,
                    subtotal,
                    doctor_id,
                    debtor_id,
                    patient_id,
                    changes
                }
            );

            const transaction_items =
                response.data.itemTransaction;

            const transaction =
                response.data.transaction;

            // FILL HEADER
            document.getElementById('receipt').textContent =
                transaction.transactions.transaction_code;

            document.getElementById('type').textContent =
                transaction.transactions.transaction_type;

            document.getElementById('cashier').textContent =
                transaction.user.name;

            document.getElementById('customer').textContent =
                'Client';

            // CLEAR ITEMS
            document.getElementById('invoiceItems').innerHTML = '';

            // FILL ITEMS
            transaction_items.forEach(item => {

                document.getElementById('invoiceItems').innerHTML += `
                <tr>
                    <td>${item.medicine.name}</td>
                    <td>${item.quantity}</td>
                    <td>${formatRupiah(item.total_price)}</td>
                </tr>
            `;
            });

            // TOTAL
            document.getElementById('invoiceTotal').innerHTML = `
            <tr>
                <td>Total</td>
                <td></td>
                <td>${formatRupiah(totaltransaction)}</td>
            </tr>

            <tr>
                <td>Tunai</td>
                <td></td>
                <td>${formatRupiah(paid)}</td>
            </tr>

            <tr>
                <td>Kembali</td>
                <td></td>
                <td>${formatRupiah(changes)}</td>
            </tr>
        `;

            // BIND BUTTONS
            CheckoutModal.bindButtons({

                onBtnPrint: () =>
                    doCheckout('pelanggan'),

                onBtnSkipPrint: () =>
                    doCheckout('none'),

                onBtnCancel: () => {

                    checkoutProcessing = false;

                    CheckoutModal.close();
                },

                onBtnStrukPelanggan: () =>
                    doCheckout('pelanggan'),

                onBtnStrukPelangganResep: () =>
                    doCheckout('pelanggan_resep'),

                onBtnCancelResep: () => {

                    checkoutProcessing = false;

                    CheckoutModal.close();
                },
            });

            hideCheckoutLoading();

            CheckoutModal.open(transaction_type);

        } catch (error) {

            hideCheckoutLoading();

            checkoutProcessing = false;

            console.error(
                'Error:',
                error.response ?
                error.response.data :
                error.message
            );

            iziToast.error({
                title: 'Gagal',
                message: 'Gagal memuat transaksi',
                position: 'topRight'
            });
        }
    }

    async function doCheckout(printMode) {

        if (checkoutProcessing) {
            return;
        }

        checkoutProcessing = true;

        CheckoutModal.close();

        openPinModal(printMode);
        setTimeout(() => {
            document.getElementById('pinInput').focus();
        }, 100);
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


    // ======================== Pembayaran & Tombol ========================

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

    function pay(value) {

        let raw = value.replace(/\D/g, "");
        let bayar = parseInt(raw) || 0;
        let price = totaltransaction - subtotal_discount;
        value = "Rp. " + bayar.toLocaleString("id-ID");
        payInput.value = value;
        if (bayar < price) {
            document.getElementById('trchange').value = "Pembayaran Kurang";
            resetButton();
            final_price = "";
        } else {
            activeButton()
            final_price = bayar - price;
            document.getElementById('trchange').value = formatRupiah(bayar - price);
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
                inputpatient.focus();
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
            if (currenttransaction == "RESEP TUNAI") {
                if (transaction_type == 'RESEP TUNAI') {
                    parameters = {{ $parameterUP }};
                    transaction_type = "UPDS";
                    faktur.innerHTML = `
                            <span
                            class="bg-green-100 text-green-800 text-xs font-medium me-2 px-2.5 py-3 rounded-md dark:bg-green-900 dark:text-green-300">UPDS</span>
                    `;
                } else if (transaction_type == "UPDS") {
                    parameters = {{ $parameterHV }};
                    transaction_type = "HV/OTC";
                    faktur.innerHTML = `
                        <span class="bg-blue-100 text-blue-800 text-xs font-medium me-2 px-2.5 py-3 rounded-md dark:bg-blue-900 dark:text-blue-300">HV/OTC</span>
                    `;
                } else if (transaction_type == "HV/OTC") {
                    parameters = {{ $parameterRT }};
                    transaction_type = "RESEP TUNAI";
                    faktur.innerHTML = `
                            <span class="bg-red-100 text-red-800 text-xs font-medium me-2 px-2.5 py-3 rounded-md dark:bg-red-900 dark:text-red-300">Resep Tunai</span>
                    `;
                }
            } else if (currenttransaction == "HV/OTC") {
             
            } else {
                if (transaction_type == 'HV/OTC') {
                    parameters = {{ $parameterUP }};
                    transaction_type = "UPDS";
                    faktur.innerHTML = `
                            <span
                            class="bg-green-100 text-green-800 text-xs font-medium me-2 px-2.5 py-3 rounded-md dark:bg-green-900 dark:text-green-300">UPDS</span>
                    `;
                } else if (transaction_type == "UPDS") {
                    parameters = {{ $parameterHV }};
                    transaction_type = "HV/OTC";
                    faktur.innerHTML = `
                        <span class="bg-blue-100 text-blue-800 text-xs font-medium me-2 px-2.5 py-3 rounded-md dark:bg-blue-900 dark:text-blue-300">HV/OTC</span>
                    `;
                }
                // } else if (transaction_type == "HV/OTC") {
                //     parameters = {{ $parameterRT }};
                //     transaction_type = "RESEP TUNAI";
                //     faktur.innerHTML = `
                //             <span class="bg-red-100 text-red-800 text-xs font-medium me-2 px-2.5 py-3 rounded-md dark:bg-red-900 dark:text-red-300">Resep Tunai</span>
                //     `;
                // }
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
            console.log("Changed the Parameter To : " + parameters + "Harga ASLI : " + rawprice);
            // let raw;
            // // console.log(it);

            // // if (it.het_price !== null && it.het_price !== 0 && it.het_price !== '') {
            // //     raw = Number(it.het_price);
            // // } else {
            // //     raw = Number(it.net_price) * Number(parameters || 1);
            // // }

            // // let rounded;
            // // if (currenttransaction === 'KREDIT') {
            // //     rounded = Math.round(raw);
            // // } else {
            // //     rounded = Math.floor(raw / 1000) * 1000;
            // // }

            if (het === 1) {
                raw = Number(it.het_price);
            } else {
                raw = Number(rawprice) * Number(parameters || 1);
            }

            price.value = formatRupiah(raw);
            console.log("harga Total : " + raw);
            price2 = raw;
            item_finalprice = raw;

        }
    }

    function onF4Key(e) {
        const isF4 = e.key === 'F4' || e.keyCode === 115;
        if (isF4) {
            e.preventDefault();
            if (checkbox) {
                checkbox.checked = !checkbox.checked; // toggle
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


    // Modals
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
                }
            })
            .then(response => {
                if (response.data.success) {
                    alert("Pasien berhasil ditambahkan!");
                    newPatientModal.classList.add('hidden');
                    newPatientForm.reset();

                    // Auto-fill patient field with new patient
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

    // Open modal
    if (openNewDoctorBtn) {
        openNewDoctorBtn.addEventListener('click', () => {
            newDoctorModal.classList.remove('hidden');
            document.getElementById('doctorName').focus();
        });

        // Close modal
        closeNewDoctorBtn.addEventListener('click', () => {
            newDoctorModal.classList.add('hidden');
        });

        // Close on outside click
        newDoctorModal.addEventListener('click', (e) => {
            if (!newDoctorModalContent.contains(e.target)) {
                newDoctorModal.classList.add('hidden');
            }
        });

        // Submit Doctor Form
        newDoctorForm.addEventListener('submit', function(e) {
            e.preventDefault();

            let formData = new FormData(this);

            axios.post("{{ route('transaction.addDoctor') }}", formData, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content,
                        'Content-Type': 'multipart/form-data'
                    }
                })
                .then(response => {
                    if (response.data.success) {

                        alert("Dokter berhasil ditambahkan!");

                        newDoctorModal.classList.add('hidden');
                        newDoctorForm.reset();

                        // Auto-fill doctor fields
                        inputdoctor.value = response.data.doctor.name;
                        document.getElementById("doctor_id").value = response.data.doctor.id;

                        if (document.getElementById("selectedDoctorId")) {
                            document.getElementById("selectedDoctorId").value = response.data.doctor.id;
                        }

                    } else {
                        alert("Gagal menambahkan dokter.");
                    }
                })
                .catch(error => {
                    console.error(error);
                    alert("Terjadi kesalahan, coba lagi.");
                });
        });
    }


    // Payment
    document.getElementById('openModalPayment').addEventListener('click', () => {
        document.getElementById('paymentModal').classList.remove('hidden');
        document.getElementById('paymentModal').classList.add('flex');
        inputpatient.focus();
    });

    document.getElementById('closeModal').addEventListener('click', () => {
        document.getElementById('paymentModal').classList.add('hidden');
        document.getElementById('paymentModal').classList.remove('flex');
    });

    document.getElementById('paymentModal').addEventListener('click', (e) => {
        if (e.target.id === 'paymentModal') {
            e.currentTarget.classList.add('hidden');
            e.currentTarget.classList.remove('flex');
        }
    });

    // Modals & Search

    closeMasterModal.addEventListener('click', () => {
        masterModal.classList.add('hidden');
        masterModal.classList.remove('flex');
    });

    // Close when clicking outside
    masterModal.addEventListener('click', e => {
        if (e.target === masterModal) {
            masterModal.classList.add('hidden');
            masterModal.classList.remove('flex');
        }
    });
    openSearchModal.addEventListener('click', () => {
        transactionModal.classList.remove('hidden');
        transactionModal.classList.add('flex');
        resetTransaction();
        loadTransactionData();
    });

    function resetTransaction() {
        page = 1;
        lastPage = false;
        document.getElementById('transactionTable').innerHTML = '';
    }

    openMasterModal.addEventListener('click', () => {

        masterModal.classList.remove('hidden');
        masterModal.classList.add('flex');
    });
    closeTransactionModal.addEventListener('click', () => {
        transactionModal.classList.add('hidden');
        transactionModal.classList.remove('flex');
    });

    [masterModal, transactionModal].forEach(modal => {
        modal.addEventListener('click', e => {
            if (e.target === modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        });
    });

    function loadData() {
        if (loading || lastPage) return;

        loading = true;
        document.getElementById('loader').style.display = 'block';

        axios.get('/transactions/getmedicinemaster', {
            params: {
                page,
                search
            }
        }).then(res => {
            const data = res.data.data;

            if (data.length === 0) lastPage = true;

            appendRows(data);
            page++;

            loading = false;
            document.getElementById('loader').style.display = 'none';
        });
    }

    function appendRows(rows) {
        const tbody = document.getElementById('masterTable');

        rows.forEach((item, index) => {
            tbody.insertAdjacentHTML('beforeend', `
            <tr>
                <td>${tbody.rows.length + 1}</td>
                <td>${item.name}</td>
                <td>${item.factory?.name ?? '-'}</td>
                <td>${item.composition?.name ?? '-'}</td>
                <td>${item.packaging ?? '-'}${item.content != 1 ? item.content ?? '' : ''}${item.unit ?? '-'}${item.strip != 1 ? item.strip ?? '' : ''}</td>
                <td>${formatRupiah(item.net_price)}</td>
                <td>${item.stock ?? '-'}</td>
                <td>
                    ${item.status
                        ? '<span style="color:green">Active</span>'
                        : '<span style="color:red">Inactive</span>'}
                </td>
            </tr>
        `);
        });
    }


    // ==================== PIN Function ====================
    function openPinModal(printMode) {
        pinModalReset();
        document.getElementById('pay').blur();
        pinModal.classList.remove('hidden');
        pinModal.classList.add('flex');
        pinModal._printMode = printMode;
    }

    function closePinModal() {
        pinModal.classList.add('hidden');
        pinModal.classList.remove('flex');
        pinModalReset();
    }

    // ── Helpers ───────────────────────────────────────────────────
    function pinModalReset() {
        pinModalValue = '';
        pinModalError.classList.add('hidden');
        pinModalRenderDots();
    }

    function pinModalRenderDots() {
        pinModalDots.forEach((dot, i) => {
            if (i < pinModalValue.length) {
                dot.classList.add('bg-orange-400', 'border-orange-400');
                dot.classList.remove('border-gray-300');
            } else {
                dot.classList.remove('bg-orange-400', 'border-orange-400');
                dot.classList.add('border-gray-300');
            }
        });
    }

    function pinModalShakeAndReset(message = 'PIN salah. Coba lagi.') {
        pinModalError.textContent = message;
        pinModalError.classList.remove('hidden');
        pinModalCard.classList.add('animate-shake');
        setTimeout(() => pinModalCard.classList.remove('animate-shake'), 500);
        pinModalValue = '';
        pinModalRenderDots();
    }

    function pinModalHandleKey(key) {
        pinModalError.classList.add('hidden');

        if (key === 'backspace') {
            pinModalValue = pinModalValue.slice(0, -1);
            pinModalRenderDots();
            return;
        }

        if (pinModalValue.length >= 4) return;
        pinModalValue += key;
        pinModalRenderDots();

        if (pinModalValue.length === 4) {
            setTimeout(pinModalVerify, 150);
        }
    }

    async function pinModalVerify() {

        pinModalNumpad.style.pointerEvents = 'none';

        try {

            // VERIFY PIN
            const {
                data
            } = await axios.post(
                '{{ route('transactions.verifypin') }}', {
                    pin: pinModalValue,
                }
            );

            // INVALID PIN
            if (!data.success) {

                checkoutProcessing = false;

                pinModalShakeAndReset(
                    data.message ?? 'PIN salah. Coba lagi.'
                );

                return;
            }

            closePinModal();

            showCheckoutLoading();

            try {

                const cleanPaid = cleanRupiah(
                    document.getElementById('pay').value
                );

                const cleanChanges = cleanRupiah(
                    document.getElementById('trchange').value
                );

                const bank_name =
                    bank_name_input ?
                    bank_name_input.value || null :
                    null;

                // SAVE HIDDEN INPUTS
                document.getElementById('paid').value = cleanPaid;

                document.getElementById('changes').value = cleanChanges;

                document.getElementById('transaction_id').value =
                    checkoutPayload.transaction_id;

                // FINAL CHECKOUT
                var userbypin = data.user.id;
                const res = await axios.post(
                    "{{ route('transaction.checkout') }}", {
                        transaction_id: checkoutPayload.transaction_id,

                        paid: checkoutPayload.paid,

                        discounsubtotalvalue,

                        totaltransaction,

                        changes: checkoutPayload.changes,

                        doctor_id: checkoutPayload.doctor_id,

                        debtor_id: checkoutPayload.debtor_id,

                        patient_id: Number(
                            checkoutPayload.patient_id
                        ),

                        print_receipt: pinModal._printMode !== 'none' ?
                            1 : 0,

                        print_mode: pinModal._printMode,

                        paymentType,

                        bank_name,

                        user_id: userbypin,

                        shift_logs_id,
                    }
                );

                // SUCCESS
                if (res.data.success) {

                    // PRINT RECEIPT
                    if (pinModal._printMode === 'pelanggan') {

                        await Printer.printReceipt(
                            res.data.commands,
                            res.data.print_url
                        );
                    }

                    // PRINT RECEIPT + RESEP
                    else if (
                        pinModal._printMode ===
                        'pelanggan_resep'
                    ) {

                        await Printer.printReceipt(
                            res.data.commands,
                            res.data.print_url
                        );

                        window.open(
                            res.data.print_resep_url,
                            '_blank'
                        );
                    }

                    iziToast.success({
                        title: 'Berhasil',
                        message: 'Transaksi berhasil disimpan',
                        position: 'topRight'
                    });

                    // WAIT FOR PRINTER
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);

                } else {

                    iziToast.error({
                        title: 'Gagal',
                        message: res.data.message ||
                            'Checkout gagal',
                        position: 'topRight'
                    });

                    checkoutProcessing = false;
                }

            } catch (err) {

                console.error(err);

                iziToast.error({
                    title: 'Gagal',
                    message: err.response?.data?.message ||
                        'Gagal menyimpan transaksi',
                    position: 'topRight'
                });

                checkoutProcessing = false;

            } finally {

                hideCheckoutLoading();
            }

        } catch (err) {

            console.error(err);

            checkoutProcessing = false;

            const message =
                err.response?.data?.message ??
                'Terjadi kesalahan. Coba lagi.';

            pinModalShakeAndReset(message);

        } finally {

            pinModalNumpad.style.pointerEvents = '';
        }
    }

    // ── Events ────────────────────────────────────────────────────
    pinModalNumpad.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-pin-key]');
        if (btn) pinModalHandleKey(btn.dataset.pinKey);
    });

    document.addEventListener('keydown', (e) => {
        if (pinModal.classList.contains('hidden')) return;
        if (e.key >= '0' && e.key <= '9') pinModalHandleKey(e.key);
        if (e.key === 'Backspace') pinModalHandleKey('backspace');
        if (e.key === 'Escape') closePinModal();
    });

    pinModalCancel.addEventListener('click', closePinModal);

    // ── Expose ────────────────────────────────────────────────────
    window.openPinModal = openPinModal;
    window.closePinModal = closePinModal;
    window.onPinModalSuccess = function(data) {
        console.log('PIN verified', data);
    };

    // Transaction Data
    function loadTransactionData() {
        if (loading || lastPage) return;

        loading = true;
        document.getElementById('loader').classList.remove('hidden');

        axios.get('/transactions/gettransactiondata', {
            params: {
                page,
                search
            }
        }).then(res => {
            const data = res.data.data ?? [];

            if (!data.length) {
                lastPage = true;
            } else {
                appendTransactionRows(data);
                page++;
            }

            loading = false;
            document.getElementById('loader').classList.add('hidden');
        });
    }


    function appendTransactionRows(rows) {
        const tbody = document.getElementById('transactionTable');

        rows.forEach(item => {
            tbody.insertAdjacentHTML('beforeend', `
            <tr
                class="cursor-pointer hover:bg-blue-50"
                onclick="selectTransaction(this, ${item.transaction_id})"
            >
                <td>${tbody.rows.length + 1}</td>
                <td>${item.date}</td>
                <td>${item.time}</td>
                <td>${item.code}</td>
                <td>${item.name}</td>
                <td>${formatRupiah(item.final_price)}</td>
                <td>
                    ${
                        item.status == 1
                            ? '<span class="text-green-600">Selesai</span>'
                            : `
                            <a href="/transaction/${item.type}/${item.transaction_id}">                                    <div class="px-6 sm:px-0 max-w-sm">
                                        <button
                                            type="button"
                                            class="w-full text-white bg-[#4285F4] hover:bg-[#4285F4]/90 focus:ring-4 focus:outline-none focus:ring-[#4285F4]/50 font-medium rounded-lg text-sm px-5 py-2.5 text-center inline-flex items-center justify-center"
                                        >
                                            Buka
                                        </button>
                                    </div>
                                </a>
                            `
                    }                
                </td>

            </tr>
        `);
        });
    }

    function updateDateTime() {
        const sekarang = new Date();

        const opsiTanggal = {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        };

        const formatTanggal = sekarang.toLocaleDateString('id-ID', opsiTanggal);

        const jam = String(sekarang.getHours()).padStart(2, '0');
        const menit = String(sekarang.getMinutes()).padStart(2, '0');
        const formatWaktu = `${jam}.${menit} WITA`;

        document.getElementById('live-date').innerText = formatTanggal;
        document.getElementById('live-time').innerText = formatWaktu;
    }

    // Jalankan fungsi pertama kali saat halaman dimuat
    updateDateTime();

    // Perbarui waktu setiap 1 detik (1000 milidetik) tanpa refresh
    setInterval(updateDateTime, 1000);

    function selectTransaction(row, transactionId) {

        /* Highlight selected row */
        document.querySelectorAll('#transactionTable tr')
            .forEach(tr => tr.classList.remove('bg-blue-100'));

        row.classList.add('bg-blue-100');
        selectedTransactionId = transactionId;

        /* Show loading */
        const tbody = document.getElementById('itemTable');
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-6">Loading...</td>
            </tr>`;

        axios.get(`/transactions/${transactionId}/items`)
            .then(res => {
                renderTransactionItems(res.data.data);
            });
    }

    function renderTransactionItems(items) {
        const tbody = document.getElementById('itemTable');
        tbody.innerHTML = '';

        if (!items.length) {
            tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-6 text-gray-400">
                    No items
                </td>
            </tr>
        `;
            return;
        }

        items.forEach((item, index) => {
            tbody.insertAdjacentHTML('beforeend', `
            <tr>
                <td>${index + 1}</td>
                <td>${item.medicine}</td>
                <td>${item.quantity}</td>
                <td>${formatRupiah(item.total_price)}</td>
                <td>${formatRupiah(item.discount)}</td>
                <td>${formatRupiah(item.total)}</td>
            </tr>
        `);
        });
    }


    window.addEventListener('scroll', () => {
        if (
            window.innerHeight + window.scrollY >=
            document.body.offsetHeight - 200
        ) {
            loadData();
        }
    });

    let timer;
    let searchTimer;

    function onSearch(val) {
        clearTimeout(timer);
        timer = setTimeout(() => {
            search = val;
            page = 1;
            lastPage = false;
            document.getElementById('masterTable').innerHTML = '';
            loadData();
        }, 400);
    }

    function onTransactionSearch(value) {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            search = value;
            resetTransaction();
            loadTransactionData();
        }, 400);
    }
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
            subtotalpreview.value = formatRupiah(roundedtotal);

            console.log('Before:', totaltransaction);
            console.log('After:', roundUpToNearestThousand(totaltransaction));

            previewtransactiontotal.value = formatRupiah(totaltransaction);
            payment_total.value = formatRupiah(totaltransaction);


        }).catch(error => {
            console.error("Error Updating Embalase:", error.response ? error.response.data : error.message);
        });

    }
    // Recipe Redirecting inputs
    if (packageInput) {
        packageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('productSearch').focus();
            }
        });
    }


    if (dosageRInput) {
        dosageRInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                quantity.focus();

            } else if (e.key === 'Tab') {
                e.preventDefault();
                document.getElementById('discount').focus();
            }
        });
    }

    quantity.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (quantity.value == 0) {
                alert("Isi Qty Barang!");
            } else {
                submit();

            }
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
                cancelButtonText: "Batal",
                focusConfirm: true,
                focusCancel: false,
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: "Racikan Telah Diselesaikan!",
                        icon: "success",
                        confirmButtonText: "OK",
                        focusConfirm: true,
                        allowOutsideClick: false,
                    }).then(() => {
                        sendEmbalase(this.value);
                        document.getElementById('package').value = "";
                    });
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
    discounsubtotal.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('payment_type').focus();
            document.getElementById('payment_type').checked = true;
        }
    })
    payInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {

            let price = totaltransaction - subtotal_discount;
            let raw = payInput.value.replace(/\D/g, "");
            let bayar = parseInt(raw) || 0;
            if (price > bayar) {
                alert("Pembayaran Kurang");
            } else {
                checkoutItem();
            }
        } else if (e.key === 'Tab') {
            e.preventDefault();
            discounsubtotal.focus();
        }
    });

    if (currenttransaction == "KREDIT") {
        doctorSearch.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {


                checkoutItem();

            } else if (e.key === 'Tab') {
                e.preventDefault();
                discounsubtotal.focus();
            }
        });
    }
</script>

{{-- ------------------- Fixed Cart Card --------------------- --}}
@endsection
