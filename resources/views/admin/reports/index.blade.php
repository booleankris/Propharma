@extends('layouts.admin')

@section('title', 'Administrator')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css"
        integrity="sha512-MQXduO8IQnJVq1qmySpN87QQkiR1bZHtorbJBD0tzy7/0U9+YIC93QWHeGTEoojMVHWWNkoCp8V6OzVSYrX0oQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <span class="font-montserrat font-semibold uppercase text-[14px] text-[#000]">Laporan Transaksi</span>
        </div>
        <div class="p-3">
            <input type="text" id="searchInput" placeholder="Search..."
                class="p-2 border border-gray-300 rounded w-full" onkeyup="searchCards()" />
        </div>
        <div class="px-3 pb-3">
           
            <div class="flatpickr">
                <div class="mb-2">
                    Cari Berdasarkan Tanggal
                </div>
                <form method="POST" action="{{ route('searchreport') }}" id="dateForm">
                    @csrf
                    <input id="datepicker" name="date" onchange="document.getElementById('dateForm').submit();"
                    class="border border-gray-300 rounded px-3 w-full" type="text"
                        placeholder="Select a date">
                </form>
            </div>
        </div>

    </div>
    <div id="cardsContainer">
        @foreach ($umkm as $umkm)
            <div class="card cardItem px-8 py-3 !mb-4 w-[96%] mx-auto">

                <div class="activity-detail items-center flex justify-start">
                    <div class="mr-3">
                        <div class="h-11 w-11 rounded">
                            <img src="{{ asset('uploads/items/default.png') }}"
                                class="w-full h-full object-cover rounded-md mb-3">
                        </div>
                    </div>
                    <div>
                        <span class="text-job !text-[15px]">{{ $umkm['name'] }}</span>
                        <div class="font-montserrat text-[10px] items-center flex">
                            {{ $umkm['transaction_count'] }} Transaksi Berhasil
                            <a href=""
                                class="ml-1 py-[2px] cursor-pointer rounded-md font-semibold px-[8px] text-[#000] bg-[#bce9dd]">
                                Lihat
                            </a>
                        </div>
                    </div>
                    <div class="font-montserrat text-[10px]">

                    </div>
                </div>
                <div class="flex justify-between space-x-6 mt-6">
                    <div class="text-center">
                        <span class="text-xs font-bold font-montserrat text-gray-800">{{ $umkm['product_count'] }}</span>
                        <p class="text-xs mb-0 text-gray-500">Produk</p>
                    </div>
                    <div class="text-center">
                        <span class="text-xs font-bold font-montserrat text-gray-800">Rp.
                            {{ number_format($umkm['revenue'], 0, ',', '.') }}</span>
                        <p class="text-xs mb-0 text-gray-500">Pendapatan</p>
                    </div>
                    <div class="text-center">
                        <span class="text-xs font-bold font-montserrat text-gray-800">Rp.
                            {{ number_format($umkm['net_revenue'], 0, ',', '.') }}</span>
                        <p class="text-xs mb-0 text-gray-500">Pendapatan NET</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Pagination controls -->
    <div class="flex justify-center mt-4 gap-2">
        <button onclick="prevPage()" class="px-3 py-1 bg-gray-200 rounded">Prev</button>
        <button onclick="nextPage()" class="px-3 py-1 bg-gray-200 rounded">Next</button>
    </div>

@section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"
        integrity="sha512-K/oyQtMXpxI4+K0W7H25UopjM8pzq0yrVdFdG21Fh5dBe91I40pDd9A4lzNlHPHBIP2cwZuoxaUSX0GJSObvGA=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        flatpickr("#datepicker", {
            // Configuration options for Flatpickr
            // You can customize the appearance and behavior here
        });
        function searchdate() { 
            alert('ss')
         }
    </script>
@endsection

<script>
    const cards = document.querySelectorAll('.cardItem');
    const cardsPerPage = 5;
    let currentPage = 1;

    function showPage(page) {
        const start = (page - 1) * cardsPerPage;
        const end = start + cardsPerPage;
        cards.forEach((card, i) => {
            card.style.display = (i >= start && i < end) ? 'block' : 'none';
        });
    }

    function searchCards() {
        const query = document.getElementById('searchInput').value.toLowerCase();

        let visibleCards = 0;
        cards.forEach(card => {
            const text = card.innerText.toLowerCase();
            const match = text.includes(query);

            card.style.display = match ? 'block' : 'none';

            if (match) visibleCards++;
        });

        // Reset Paginasi
        if (query) {
            currentPage = 1;
        } else {
            showPage(currentPage);
        }
    }

    function nextPage() {
        const totalPages = Math.ceil(cards.length / cardsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            showPage(currentPage);
        }
    }

    function prevPage() {
        if (currentPage > 1) {
            currentPage--;
            showPage(currentPage);
        }
    }

    // Initialize
    showPage(currentPage);
</script>
@endsection
