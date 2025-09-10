@extends('layouts.app')
@section('content')
    <div class="m-[22px] mb-[10px]">
        <h2 class="text-lg poppins-thin font-bold mt-4 mb-1">Produk Saya</h2>
    </div>
    <div class="px-5">
        <input type="text" id="searchInput" placeholder="Cari Produk..." class="mb-3 mb-4 px-4 py-2 border rounded w-full">

    </div>
    <div class="m-[12px] bg-[#f5f5f5] rounded-md px-[20px] overflow-scroll h-[250px]" id="items-container">
        @foreach ($items as $item)
            <div class="item-card flex items-center my-4 py-2 px-3 bg-white rounded-xl shadow-lg">
                <div class="h-16 w-16 rounded">
                    <img src="{{ asset('uploads/items/' . $item->item_photo) }}"
                        class="w-full h-full object-cover rounded-md mb-3">
                </div>
                <div class="flex-grow flex flex-col ml-4">
                    <span class="item-name text-sm font-bold">{{ $item->item_name }}</span>
                    <div class="flex justify-between">
                        <span class="text-sm text-green-500">Rp {{ number_format($item->item_price, 0, ',', '.') }}</span>
                    </div>
                </div>

                @php
                    $inCart = $item->cart_status->isNotEmpty();
                @endphp
                @if($inCart)
                    @foreach ($item->cart_status as $cartItem)
                        <div class="flex items-center justify-between md:order-3 md:justify-end">
                            <div class="flex items-center">
                                <form action="{{ route('sales.lessItem') }}" onsubmit="showLoading(event)" method="POST">
                                    @csrf
                                    <input name="id2" class="hidden" type="text" readonly id="counter-input2"
                                        data-input-counter
                                        class="w-10 shrink-0 border-0 bg-transparent text-center text-sm font-medium text-gray-900 focus:outline-none focus:ring-0 "
                                        placeholder="" value="{{ $cartItem->id }}" required />
                                    <button type="submit" id="decrement-button"
                                      
                                        class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-md border border-gray-300 bg-gray-600 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-100   dark:focus:ring-gray-700">
                                        <svg class="h-2.5 w-2.5 text-white" aria-hidden="true"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 18 2">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                                stroke-width="2" d="M1 1h16" />
                                        </svg>
                                    </button>
                                </form>

                                <form action="{{ route('sales.moreItem') }}" onsubmit="showLoading(event)" method="POST">
                                    @csrf
                                    <input name="id" class="hidden" type="text" readonly id="counter-input"
                                        data-input-counter
                                        class="w-10 shrink-0 border-0 bg-transparent text-center text-sm font-medium text-gray-900 focus:outline-none focus:ring-0 "
                                        placeholder="" value="{{ $cartItem->id }}" required />
                                    <input name="quantity" type="text" readonly id="counter-input" data-input-counter
                                        class="w-8 shrink-0 border-0 bg-transparent text-center text-sm font-medium text-gray-900 focus:outline-none focus:ring-0 "
                                        placeholder="" value="{{ $cartItem->quantity }}" required />
                                    <button type="submit" id="increment-button"
                                      
                                        class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-md border border-gray-300 bg-gray-600 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-100 dark:focus:ring-gray-700">
                                        <svg class="h-2.5 w-2.5 text-white" aria-hidden="true"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 18 18">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                                stroke-width="2" d="M9 1v16M1 9h16" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                @else
                    <form method="POST" onsubmit="showLoadingCart(event)"  action="{{ route('sales.addToCart') }}">
                        @csrf
                        <input type="text" name="item_id" class="hidden" value="{{ $item->id }}">
                        <button id="cartbtn" type="submit" 
                            class="text-[10px] flex justify-center items-center mr-1 px-2 py-1 text-center text-[#4caf50] border border-[#4caf50] rounded hover:bg-[#4caf50] hover:text-white active:bg-indigo-500 focus:outline-none focus:ring">
                            <svg class="w-5 h-5 mr-1 text-[#4caf50]" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M14 17h6m-3 3v-6M4.857 4h4.286c.473 0 .857.384.857.857v4.286a.857.857 0 0 1-.857.857H4.857A.857.857 0 0 1 4 9.143V4.857C4 4.384 4.384 4 4.857 4Zm10 0h4.286c.473 0 .857.384.857.857v4.286a.857.857 0 0 1-.857.857h-4.286A.857.857 0 0 1 14 9.143V4.857c0-.473.384-.857.857-.857Zm-10 10h4.286c.473 0 .857.384.857.857v4.286a.857.857 0 0 1-.857.857H4.857A.857.857 0 0 1 4 19.143v-4.286c0-.473.384-.857.857-.857Z" />
                            </svg>

                            <span>Masukkan Keranjang</span>
                        </button>
                    </form>
                @endif
                {{-- Kalau Sudah Ada Dalam Keranjang --}}
            </div>
        @endforeach
    </div>
    <div class="m-[22px]">
        @if ($total_item > 0)
            <h2 class="text-lg poppins-thin font-bold mt-4 mb-1">Keranjang</h2>
        @endif
        <div class="space-y-4">

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                @foreach ($carts as $cart)
                    <div class="rounded-lg border border-gray-200 bg-[#fdfdfd] p-4 ps-4 dark:border-gray-700">
                        <div class="flex justify-between items-center">

                            <div class="text-sm flex items-center">
                                <div class="mr-3">
                                    <div class="h-16 w-16 rounded">
                                        <img src="{{ asset('uploads/items/' . $cart->item->item_photo) }}"
                                            class="w-full h-full object-cover rounded-md mb-3">
                                    </div>
                                </div>
                                <div>
                                    <label for="dhl" class="font-medium leading-none text-[#099a0f]">
                                        {{ $cart->item->item_name }} </label>
                                    <p id="dhl-text" class="mt-1 text-xs font-normal text-gray-500 dark:text-gray-400">
                                        Rp.{{ number_format($cart->item->item_price, 0, ',', '.') }}
                                    </p>
                                </div>
                            </div>

                            <div class="font-montserrat flex items-center">
                                <div class="mr-2">
                                    x {{ $cart->quantity }}
                                </div>
                                <form action="{{ route('sales.removeItem', $cart->id) }}" method="POST"
                                    onsubmit="return confirm('Hapus Produk?')" style="display: inline;">
                                    @csrf
                                    <button type="submit"
                                        class="flex justify-center items-center mr-1 px-2 py-1 text-[#DF1463] border border-[#DF1463] rounded hover:bg-[#DF1463] hover:text-white">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                                            class="w-5 h-5">
                                            <path
                                                d="M8 6V4.41421C8 3.63317 8.63317 3 9.41421 3H14.5858C15.3668 3 16 3.63317 16 4.41421V6"
                                                stroke="#DF1463" stroke-width="1.7" stroke-linecap="round" />
                                            <path
                                                d="M5.7372 6.54395V18.9857C5.7372 19.7449 6.35269 20.3604 7.11194 20.3604H16.8894C17.6487 20.3604 18.2642 19.7449 18.2642 18.9857V6.54395M2.90918 6.54395H21.091"
                                                stroke="#c60653" stroke-width="1.7" stroke-linecap="round" />
                                        </svg>
                                    </button>
                                </form>
                            </div>

                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="mt-6 w-full space-y-6 sm:mt-8 lg:mt-0 lg:max-w-xs xl:max-w-md">
            <div class="flow-root">
                <div class="-my-3 divide-y divide-gray-200 dark:divide-gray-800">
                    <dl class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-base font-normal text-gray-500">Kode Transaksi</dt>
                        <dd class="text-base font-medium text-green-500">{{ $transaction_code }}</dd>
                    </dl>
                    <dl class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-base font-normal text-gray-500">Jumlah Beli</dt>
                        <dd class="text-base font-medium text-green-500">{{ $total_item }}</dd>
                    </dl>
                    <dl class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-base font-normal text-gray-500">Nama Tenant</dt>
                        <dd class="text-base font-medium text-green-500">{{ Auth::user()->name }}</dd>
                    </dl>
                    <dl class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-base font-bold text-gray-900">Total</dt>

                        <dd class="text-base font-bold text-gray-900">Rp{{ number_format($subtotal, 0, ',', '.') }}</dd>
                    </dl>
                </div>
            </div>
            @if ($total_item > 0)
                <form action="{{ route('sales.checkout') }}" method="post">
                    @csrf
                    <div class="space-y-3">
                        <button type="submit"
                            class="flex w-full items-center justify-center rounded-lg bg-[#4caf50] px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-4  focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">Lanjutkan
                            Ke Pembayaran</button>


                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- <div class="px-[5px] py-[15px] overflow-scroll h-[200px]">
            @foreach ($carts as $cart)
                <div class="flex items-center mb-2 py-2 px-3 bg-white rounded-xl shadow-lg">
                    <div class="h-16 w-16 rounded">
                        <img src="{{ asset('uploads/items/' . $item->item_photo) }}"
                            class="w-full h-full object-cover rounded-md mb-3">

                    </div>
                    <div class="flex-grow flex flex-col ml-4">
                        <span class="text-md font-bold">{{ $item->item_name }}</span>
                        <div class="flex justify-between">
                            <span class="text-[14px] text-green-500">Rp. {{ $item->item_price }}</span>
                        </div>
                    </div>
                    <div class="flex-grow flex justify-end text-center fml-4">
                        <div
                            class="text-[10px] cursor-pointer flex justify-center items-center mr-1 px-2 py-1 text-center text-[#4caf50] border border-[#4caf50] rounded hover:bg-[#4caf50] hover:text-white active:bg-indigo-500 focus:outline-none focus:ring">

                            <span>Masukkan Keranjang</span>
                        </div>
                        <form action="{{ route('items.destroy', $item->id) }}" method="POST"
                            onsubmit="return confirm('Hapus Produk?')" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="flex justify-center items-center mr-1 px-2 py-1 text-[#DF1463] border border-[#DF1463] rounded hover:bg-[#DF1463] hover:text-white">
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5">
                                    <path
                                        d="M8 6V4.41421C8 3.63317 8.63317 3 9.41421 3H14.5858C15.3668 3 16 3.63317 16 4.41421V6"
                                        stroke="#DF1463" stroke-width="1.7" stroke-linecap="round" />
                                    <path
                                        d="M5.7372 6.54395V18.9857C5.7372 19.7449 6.35269 20.3604 7.11194 20.3604H16.8894C17.6487 20.3604 18.2642 19.7449 18.2642 18.9857V6.54395M2.90918 6.54395H21.091"
                                        stroke="#c60653" stroke-width="1.7" stroke-linecap="round" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div> --}}
    {{-- <div class="flex items-center my-4 py-2 px-3 bg-white rounded-xl shadow-lg">
            <div class="h-16 w-16 rounded">
                <img src="https://picsum.photos/400" class="w-full h-full object-cover rounded-md mb-3">

            </div>
            <div class="flex-grow flex flex-col ml-4">
                <span class="text-md font-bold">Mie Goreng</span>
                <div class="flex items-center justify-between">
                    <span class="text-[14px] text-green-500">Rp. 413,323</span>
                </div>
            </div>
            <div class="flex-grow flex justify-end text-center fml-4">
                <div
                    class="flex justify-center items-center mr-1 px-2 py-1 text-center text-[#DF1463] border border-[#DF1463] rounded hover:bg-[#DF1463] hover:text-white active:bg-[#DF1463] focus:outline-none focus:ring">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier">
                            <path d="M8 6V4.41421C8 3.63317 8.63317 3 9.41421 3H14.5858C15.3668 3 16 3.63317 16 4.41421V6"
                                stroke="#DF1463" stroke-width="1.7" stroke-linecap="round"></path>
                            <path
                                d="M5.7372 6.54395V18.9857C5.7372 19.7449 6.35269 20.3604 7.11194 20.3604H16.8894C17.6487 20.3604 18.2642 19.7449 18.2642 18.9857V6.54395M2.90918 6.54395H21.091"
                                stroke="#c60653" stroke-width="1.7" stroke-linecap="round"></path>
                        </g>
                    </svg>

                </div>
                <div
                    class="flex justify-center items-center mr-1 px-2 py-1 text-center text-violet-600 border border-violet-600 rounded hover:bg-violet-600 hover:text-white active:bg-indigo-500 focus:outline-none focus:ring">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 0 0 2.25-2.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v2.25A2.25 2.25 0 0 0 6 10.5Zm0 9.75h2.25A2.25 2.25 0 0 0 10.5 18v-2.25a2.25 2.25 0 0 0-2.25-2.25H6a2.25 2.25 0 0 0-2.25 2.25V18A2.25 2.25 0 0 0 6 20.25Zm9.75-9.75H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75h-2.25A2.25 2.25 0 0 0 13.5 6v2.25a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>

                </div>
                <div
                    class="text-[10px] flex justify-center items-center mr-1 px-2 py-1 text-center text-[#4caf50] border border-[#4caf50] rounded hover:bg-[#4caf50] hover:text-white active:bg-indigo-500 focus:outline-none focus:ring">

                    <span>Unlist</span>
                </div>
            </div>
        </div> --}}
    </div>
@endsection
