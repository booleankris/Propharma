@extends('layouts.app')
@section('content')
    <div class="m-[19px]">
        <div>
            <h2 class="text-lg poppins-thin font-bold mt-4 mb-1">Produk Saya</h2>
        </div>
        <div class="my-3">

            <a href="{{ route('items.create') }}">
                <div
                    class="flex justify-center items-center mr-4 px-6 py-2 min-w-[120px] text-center text-violet-600 border border-violet-600 rounded hover:bg-violet-600 hover:text-white active:bg-indigo-500 focus:outline-none focus:ring">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 0 0 2.25-2.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v2.25A2.25 2.25 0 0 0 6 10.5Zm0 9.75h2.25A2.25 2.25 0 0 0 10.5 18v-2.25a2.25 2.25 0 0 0-2.25-2.25H6a2.25 2.25 0 0 0-2.25 2.25V18A2.25 2.25 0 0 0 6 20.25Zm9.75-9.75H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75h-2.25A2.25 2.25 0 0 0 13.5 6v2.25a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <span class="ml-2">
                        Tambah Produk
                    </span>
                </div>

            </a>
        </div>
        @foreach ($items as $item)
            <div class="flex items-center my-4 py-2 px-3 bg-white rounded-xl shadow-lg">
                <div class="h-16 w-16 rounded">
                    <img src="{{ asset('uploads/items/' . $item->item_photo) }}"
                        class="w-full h-full object-cover rounded-md mb-3">

                </div>
                <div class="flex-grow flex flex-col ml-4">
                    <span class="text-md font-bold">{{ $item->item_name }}</span>
                    <div class="flex justify-between">
                        <span class="text-[14px] text-green-500">Rp.
                            {{ number_format($item->item_price, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
                <div class="flex-grow flex justify-end text-center fml-4">
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

                    <a href="{{ route('items.edit', $item->id) }}">
                        <div
                            class="flex justify-center items-center mr-1 px-2 py-1 text-center text-[#fff] border border-[#ffad1f] rounded hover:bg-[#ffad1f] hover:text-[#fff] active:bg-[#ffad1f] focus:outline-none focus:ring">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5"
                                stroke="#ffa914">
                                <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                <g id="SVGRepo_iconCarrier">
                                    <path
                                        d="M21.2799 6.40005L11.7399 15.94C10.7899 16.89 7.96987 17.33 7.33987 16.7C6.70987 16.07 7.13987 13.25 8.08987 12.3L17.6399 2.75002C17.8754 2.49308 18.1605 2.28654 18.4781 2.14284C18.7956 1.99914 19.139 1.92124 19.4875 1.9139C19.8359 1.90657 20.1823 1.96991 20.5056 2.10012C20.8289 2.23033 21.1225 2.42473 21.3686 2.67153C21.6147 2.91833 21.8083 3.21243 21.9376 3.53609C22.0669 3.85976 22.1294 4.20626 22.1211 4.55471C22.1128 4.90316 22.0339 5.24635 21.8894 5.5635C21.7448 5.88065 21.5375 6.16524 21.2799 6.40005V6.40005Z"
                                        stroke="#ffad1f" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    </path>
                                    <path
                                        d="M11 4H6C4.93913 4 3.92178 4.42142 3.17163 5.17157C2.42149 5.92172 2 6.93913 2 8V18C2 19.0609 2.42149 20.0783 3.17163 20.8284C3.92178 21.5786 4.93913 22 6 22H17C19.21 22 20 20.2 20 18V13"
                                        stroke="#ffad1f" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    </path>
                                </g>
                            </svg>

                        </div>
                    </a>

                </div>
            </div>
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
