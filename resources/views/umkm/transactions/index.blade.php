@extends('layouts.app')
@section('content')
    <div class="m-[19px]">
        <div>
            <h2 class="text-lg poppins-thin font-bold mt-4 mb-1">Riwayat Transaksi</h2>
        </div>
        <div class="max-w-full  bg-[#aefdd8] rounded-lg shadow-md p-4 md:p-6">
            <div class="flex justify-between mb-5">
                <div class="p-3">
                    <h5 class="leading-none text-3xl font-bold text-gray-900 pb-2">Rp. {{ number_format($subtotal, 0, ',', '.') }}</h5>
                    <p class="text-base font-normal :text-gray-400">Total Pendapatan</p>
                </div>
                <div
                    class="flex items-center px-2.5 py-0.5 text-base font-semibold text-green-500 dark:text-green-500 text-center">
                    
                 
                </div>
            </div>
            <div id="main-chart"></div>
            <div class="grid grid-cols-1 items-center border-gray-200 border-t dark:border-gray-700 justify-between mt-5">
                <div class="flex justify-between items-center pt-5">
                    <!-- Button -->
                    <button id="dropdownDefaultButton" data-dropdown-toggle="lastDaysdropdown"
                        data-dropdown-placement="bottom"
                        class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-900 text-center inline-flex items-center dark:hover:text-white"
                        type="button">
                        Pendapatan Bersih
                      
                    </button>
                    <!-- Dropdown menu -->
                    <div id="lastDaysdropdown"
                        class="z-10 hidden bg-white divide-y divide-gray-100 rounded-lg shadow-sm w-44 dark:bg-gray-700">
                        
                    </div>
                    <a href="#"
                        class="uppercase text-sm font-semibold inline-flex items-center rounded-lg text-blue-600 hover:text-blue-700 dark:hover:text-blue-500  hover:bg-gray-100 dark:hover:bg-gray-700 dark:focus:ring-gray-700 dark:border-gray-700 px-3 py-2">
                        Rp. {{ number_format($revenue, 0, ',', '.') }}
                    </a>
                </div>
            </div>
        </div>
        @foreach ($transaction as $transaction)
            @if ($transaction->payment)
                <div class="flex items-center my-4 py-2 px-3 bg-white rounded-xl shadow-lg">
                    <div class="flex-grow flex flex-col ml-4">
                        <span class="text-md font-bold">{{ $transaction->transaction_code }}</span>
                        <div class="flex justify-between">
                            <span class="text-[14px] text-green-500">
                                Rp. {{ number_format($transaction->payment->total_price, 0, ',', '.') }}
                            </span>
                            @if ($transaction->payment->status == 'SETTLEMENT')
                                <span class="text-sm text-gray-600">SUCCESS</span>
                            @else
                                <span class="text-sm text-gray-600">{{ ucfirst($transaction->payment->status) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="flex items-center my-4 py-2 px-3 bg-white rounded-xl shadow-lg">
                    <div class="flex-grow flex flex-col ml-4">
                        <span class="text-md font-bold">{{ $transaction->transaction_code }}</span>
                        <div class="flex justify-between">
                            <span class="text-red-500 text-[14px]">Belum ada pembayaran</span>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
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
