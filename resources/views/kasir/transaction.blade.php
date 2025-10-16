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
                <div class="flex flex-wrap justify-start gap-4 pt-3">
                    <!-- Resep Credit -->
                    <a href="{{ url('transaction/kredit') }}">
                        <button
                            class="flex flex-col items-center justify-center w-[90px] h-[75px]  {{ request()->is('transaction/kredit') ? 'transaction-item-active shadow-none' : '' }}  border-[#D6D5D5] border rounded-2xl shadow-sm hover:bg-gray-50">
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
                            class="flex flex-col items-center justify-center w-[90px] h-[75px] {{ request()->is('transaction/resep') ? 'transaction-item-active shadow-none' : '' }} border-[#D6D5D5] border rounded-2xl shadow-sm hover:bg-gray-50">
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
                            class="flex flex-col items-center justify-center w-[90px] h-[75px] border-[#D6D5D5] {{ request()->is('transaction/hv') ? 'transaction-item-active shadow-none' : '' }} border rounded-2xl shadow-sm hover:bg-gray-50">
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
                            class="flex flex-col items-center justify-center w-[90px] h-[75px] border-[#D6D5D5] {{ request()->is('transaction/upds') ? 'transaction-item-active shadow-none' : '' }} border rounded-2xl shadow-sm hover:bg-gray-50">
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
                                <label class="text-[13px] font-poppins font-semibold">Jumlah Beli</label>

                            </div>
                            <input id="quantity" required name="quantity" onkeyup="count(this.value)" type="number"
                                placeholder="Quantity"
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
                    <div tabindex="-1" class="mt-4 rounded-2xl bg-gray-100 h-64 overflow-y-scroll md:h-[230px]">
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
                        @if ($check_transaction == 1)
                            @if ($transaction->transaction_type == 'KREDIT' || $transaction->transaction_type == 'RESEP TUNAI')
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
                                                class="btn btn-add !rounded-[10px] !bg-[##FFC107] p-1 py-2 font-poppin font-bold">
                                                <div class="flex items-center justify-center gap-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                        class="w-6 h-6 text-[#62b9ff]"> <!-- color controlled by text-* -->
                                                        <circle cx="15" cy="8" r="4"
                                                            fill="currentColor" />
                                                        <path d="M15,14c-6.1,0-8,4-8,4v2h16v-2C23,18,21.1,14,15,14z"
                                                            fill="currentColor" />
                                                        <line stroke="currentColor" stroke-miterlimit="10"
                                                            stroke-width="2" x1="5" x2="5"
                                                            y1="7" y2="15" />
                                                        <line stroke="currentColor" stroke-miterlimit="10"
                                                            stroke-width="2" x1="9" x2="1"
                                                            y1="11" y2="11" />
                                                    </svg>

                                                    <div class="text-[#62b9ff]">
                                                        Baru
                                                    </div>
                                                </div>
                                            </button>
                                        </div>
                                        <!-- Hidden field to hold selection (optional) -->
                                        <input type="hidden" id="selectedPatientId" />

                                    </div>
                                    <div class="w-full hidden mt-1 mb-1">
                                        <div class="mr-2 w-full">
                                            <div class="w-full">
                                                <label class="text-[13px] font-poppins font-semibold pb-1">Nama
                                                    Pasien</label>
                                            </div>
                                            <input id="patientname" class="hidden" type="text" name="patientname"
                                                readonly placeholder="Nama Pasien"
                                                class="w-full rounded-xl readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                                autocomplete="off" />
                                        </div>
                                    </div>
                                </div>
                                <div class="w-full">
                                    <div class="w-full">
                                        <label class="text-[13px] font-poppins font-semibold pb-1">Cari Dokter</label>

                                    </div>
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
                                        <!-- Hidden field to hold selection (optional) -->
                                        <input type="hidden" id="selectedDoctorId" />

                                    </div>
                                    <div class="w-full hidden mt-1 mb-2">
                                        <div class="mr-2 w-full">
                                            <div class="w-full">
                                                <label class="text-[13px] font-poppins font-semibold pb-1">Nama
                                                    Dokter</label>

                                            </div>
                                            <input id="doctorname" type="text" name="doctorname" readonly
                                                placeholder="Nama Pasien"
                                                class="w-full rounded-xl readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                                autocomplete="off" />
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                    <div class="mr-2 w-[100%] mt-1 flex gap-2">
                        @if ($transaction->transaction_type == 'KREDIT' || $transaction->transaction_type == 'RESEP TUNAI')

                        <div class="w-[40%]">
                            <div>
                                <label class="text-[13px] font-poppins font-semibold pb-1">Embalase</label>

                            </div>
                            <input id="embalase" tabindex="-1" readonly type="text" name="embalase"
                                placeholder="Embalase"
                                class="w-full rounded-xl my-1 readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                autocomplete="off" />
                        </div>
                        @endif
                        <div class="w-full">
                            <div class="w-full">
                                <label class="text-[13px] font-poppins font-semibold pb-1">Total</label>

                            </div>
                            <input id="carttotal" tabindex="-1" readonly type="text" name="carttotal"
                                placeholder="Total obat"
                                class="w-full rounded-xl my-1 readonly border border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                autocomplete="off" />
                        </div>

                    </div>
                    <div class="mr-2 flex gap-2 w-[100%] mt-1">
                        <div class="w-full">
                            <label class="text-[13px] font-poppins font-semibold pb-1">Bayar</label>
                            <input id="pay" onkeyup="pay(this.value)" type="text" name="pay"
                                placeholder="Bayar obat"
                                class="w-full rounded-xl border my-1 border-gray-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                autocomplete="off" />


                        </div>
                        <div class="w-full">
                            <label class="text-[13px] font-poppins font-semibold pb-1">Kembalian</label>
                            <input id="change" tabindex="-1" readonly type="text" name="change"
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

    {{-- ============================================================== Patient Invoice  ============================================================== --}}

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        // ===============================
        // Konstanta & Variabel Global
        // ===============================

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

        // Variabel kerja
        var transaction_id = trx_id;
        var total_discount = "";
        var total_item = "";
        var medicine_id = "";
        // var debtor_id = "";
        // var patient_id = "";
        // var doctor_id = "";

        var price2 = "";
        var subtotal = "";
        var final_price = "";
        let items = [];
        let activeIndex = -1;
        let closeTimeout;

        // Set nilai awal
        cartTotalInput.value = formatRupiah(totaltransaction);

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
            if (transaction_type == 'KREDIT' || transaction_type == 'RESEP TUNAI') {
                dosage.value = it.dosage;
            }
            console.log("Harga : " + it.net_price + "Parameter : " + parameters + "Pembulatan : " + rounding);
            let raw = (+it.net_price * +parameters) + +rounding;
            let rounded = Math.floor(raw / 1000) * 1000;
            price.value = formatRupiah(rounded);
            console.log("harga Total : " + raw);
            price2 = rounded;
            if (transaction_type == 'RESEP TUNAI') {
                discountInput.focus();
            } else {
                quantity.focus();
            }
            quantity.select();
            closeBox();
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
            patientname.value = it.name;
            document.getElementById('patient_id').value = it.id;
            if (transaction_type == 'RESEP TUNAI' || transaction_type == 'KREDIT') {
                document.getElementById('doctorSearch').focus();
            } else {
                document.getElementById('debtorSearch').focus();
            }
            patientSearch.value = it.name;
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
            doctorname.value = it.name;

            if (transaction_type == 'RESEP TUNAI') {
                document.getElementById('pay').focus();
            } else {
                document.getElementById('debtorSearch').focus();
            }
            document.getElementById('doctor_id').value = it.id;
            document.getElementById('doctorSearch').value = it.name;


            closedoctorBox();
        }


        // ===============================
        // Pencarian Debitur
        // ===============================

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
        // ===============================
        // Perhitungan Harga & Diskon
        // ===============================
        function count(val) {
            total_item = val;
            subtotal = price2 * val;
            totalprice.value = formatRupiah(subtotal);
        }

        function countDiscount(val) {
            if (val > 100) {
                final_price = subtotal - val;
                total_discount = val;
            } else {
                const d = subtotal * val / 100;
                final_price = subtotal - d;
                total_discount = `${val}%`;
            }
            totalprice.value = formatRupiah(final_price);
        }

        // ===============================
        // Cart Actions
        // ===============================
        axios.defaults.headers.common['X-CSRF-TOKEN'] = '{{ csrf_token() }}';

        function addToCart(medicine_id, transaction_id, quantity, discount, total_price) {
            axios.post("{{ route('transaction.addToCart') }}", {
                medicine_id,
                transaction_id,
                quantity,
                discount,
                total_price
            }).then(response => {
                let item = response.data;
                totaltransaction += total_price;
                cartTotalInput.value = formatRupiah(totaltransaction);

                // Reset form input
                stock.value = "";
                unit.value = "";
                quantity.value = "";
                price.value = "";
                name.value = "";
                totalprice.value = "";
                document.getElementById('pay').value = "";
                document.getElementById('change').value = "";
                discountInput.value = "";

                document.getElementById('productSearch').focus();
                closeBox();

                // Append ke cart
                document.getElementById('carts').innerHTML += `
                    <div id="itemincart${item.id}">
                        <div class="flex justify-between mx-[15px] mt-[5px] py-[20px] mb-[8px] rounded-lg bg-[#fff]">
                            <div>
                                <div class="px-[20px] font-poppins font-semibold">${item.name}</div>
                                <div class="px-[20px] font-poppins text-[10px]">${formatRupiah(item.total_price)}</div>
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
            }).catch(error => {
                console.error("❌ Error adding to cart:", error.response ? error.response.data : error.message);
            });
        }

        function submit() {
            addToCart(medicine_id, transaction_id, total_item, total_discount, final_price);
        }

        function removeItem(id) {
            document.getElementById("itemincart" + id).remove();

            axios.post("{{ route('transaction.removeItem') }}", {
                    id
                })
                .then(response => {
                    let item = response.data;
                    totaltransaction -= item.total_price;
                    cartTotalInput.value = formatRupiah(totaltransaction);
                    document.getElementById('pay').value = "";
                    document.getElementById('change').value = "";
                }).catch(error => {
                    console.error("❌ Error removing item:", error.response ? error.response.data : error.message);
                });
        }

        // ===============================
        // Checkout & Invoice
        // ===============================
        function checkoutItem() {
            const paid = document.getElementById('pay').value;
            const changes = document.getElementById('change').value;
            const doctor_id = document.getElementById('doctor_id').value;
            if (transaction_type == 'RESEP TUNAI') {
                const debtor_id = 0;
            } else {
                const debtor_id = document.getElementById('debtor_id').value;
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

        function pay() {
            const paidInput = document.getElementById('paid');
            const changesInput = document.getElementById('changes');
            let raw = document.getElementById('pay').value.replace(/\D/g, "");
            let bayar = parseInt(raw) || 0;
            document.getElementById('pay').value = "Rp. " + bayar.toLocaleString("id-ID");

            if (bayar < totaltransaction) {
                document.getElementById('change').value = "Duitnya Kurang";
                resetButton();
            } else {
                if (totaltransaction > 0) activeButton();
                paidInput.value = totaltransaction;
                changesInput.value = bayar - totaltransaction;
                document.getElementById('change').value = formatRupiah(bayar - totaltransaction);
            }
        }

        // ===============================
        // Keyboard Shortcut
        // ===============================
        function onF1Key(e) {
            const isF1 = e.key === 'F1' || e.keyCode === 112;
            if (isF1) {
                e.preventDefault();
                document.getElementById('pay').focus();
            }
        }
        // ===============================
        function onF2Key(e) {
            const isF2 = e.key === 'F2' || e.keyCode === 113;
            if (isF2) {
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

        // ===============================
        // Global Event Listeners
        // ===============================
        window.addEventListener('keydown', onF1Key, {
            capture: true
        });
        window.addEventListener('keydown', onF2Key, {
            capture: true
        });
        document.addEventListener('click', (e) => {
            if (!box.contains(e.target) && e.target !== input) closeBox();
            if (!box.contains(e.target) && e.target !== input) closedebtorBox();

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
        // Resep
        // ===============================
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                packageInput.removeAttribute('readonly');
                dosageRInput.removeAttribute('readonly');
                packageInput.classList.remove('readonly');
                dosageRInput.classList.remove('readonly');
                quantity.setAttribute('readonly', true);
                quantity.classList.add('readonly');
                packageInput.focus();
            } else {
                packageInput.setAttribute('readonly', true);
                dosageRInput.setAttribute('readonly', true);
                packageInput.classList.add('readonly');
                dosageRInput.classList.add('readonly');
            }
        });

        function calculatePackage() {
            const dosage = parseFloat(dosageInput.value) || 0;
            const dosageR = parseFloat(dosageRInput.value) || 0;
            const package = parseFloat(packageInput.value) || 0;

            if (dosageR > 0) {
                const result = dosageR / dosage * package;
                quantity.value = result; // rounded to 2 decimals
                count(quantity.value);
            } else {
                quantity.value = '';
            }
        }
    </script>

    {{-- ------------------- Fixed Cart Card --------------------- --}}
@endsection
