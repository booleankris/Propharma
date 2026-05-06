@extends('layouts.app')
@section('style')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
@endsection
@section('content')
    {{-- Section Product Preview --}}
    <div class="mx-4 max-w-full grid grid-cols-12 gap-6">
        <!-- LEFT COLUMN -->
        <section class="col-span-12 lg:col-span-12">
            <!-- Header Card -->


            {{-- <div class="flex flex-col justify-center items-center h-[100vh] pt-4">
                
                <p class="font-normal text-navy-700 mt-20 mx-auto w-max">Profile Card component from <a
                        href="https://horizon-ui.com?ref=tailwindcomponents.com" target="_blank"
                        class="text-brand-500 font-bold">Horizon UI Tailwind React</a></p>
            </div> --}}
            <div class="flex items-center justify-start"><span
                    class="inline-flex justify-center items-center w-12 h-12 rounded-full bg-white text-[#008bff]  mr-3"><svg
                        viewBox="0 0 24 24" width="24" height="24" class="inline-block">
                        <path fill="currentColor"
                            d="M3,14L3.5,14.07L8.07,9.5C7.89,8.85 8.06,8.11 8.59,7.59C9.37,6.8 10.63,6.8 11.41,7.59C11.94,8.11 12.11,8.85 11.93,9.5L14.5,12.07L15,12C15.18,12 15.35,12 15.5,12.07L19.07,8.5C19,8.35 19,8.18 19,8A2,2 0 0,1 21,6A2,2 0 0,1 23,8A2,2 0 0,1 21,10C20.82,10 20.65,10 20.5,9.93L16.93,13.5C17,13.65 17,13.82 17,14A2,2 0 0,1 15,16A2,2 0 0,1 13,14L13.07,13.5L10.5,10.93C10.18,11 9.82,11 9.5,10.93L4.93,15.5L5,16A2,2 0 0,1 3,18A2,2 0 0,1 1,16A2,2 0 0,1 3,14Z">
                        </path>
                    </svg></span>
                <h1 class="text-2xl font-montserrat font-black text-[#008bff] capitalize leading-tight">Dashboard Kasir
                </h1>
            </div>
            <div
                class="px-[30px] py-[5px] my-[18px] bg-[#ebf8ff] rounded-[20px] shadow-[0px_12px_24px_#8f8f8f1c] border border-[#73e2ff]">
                <div class="py-5 space-y-3 w-full md:w-[70%]">

                    {{-- TRANSAKSI --}}
                    <div>
                        <p class="text-[10.5px] font-semibold uppercase tracking-widest mb-2.5 px-0.5"
                            style="color:#7aa3c0; letter-spacing:0.09em;">
                            Transaksi
                        </p>
                        <div class="grid grid-cols-2 gap-2 w-full md:w-auto md:inline-grid"
                            style="grid-template-columns: repeat(3, minmax(80px, 224px))">

                            {{-- Penjualan --}}
                            <a href="{{ url('transaction/upds') }}"
                                class="group flex flex-col items-center gap-2 rounded-2xl px-3 py-4 text-center transition-all duration-200
          bg-[#1d7ed8] text-white shadow-[0px_6px_16px_#1d7ed866]
          hover:-translate-y-0.5 hover:bg-[#1669b2] active:scale-95">

                                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/20">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2">
                                        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                                        <line x1="3" y1="6" x2="21" y2="6" />
                                        <path d="M16 10a4 4 0 0 1-8 0" />
                                    </svg>
                                </div>

                                <span class="text-[12px] font-semibold">Penjualan</span>
                            </a>

                            {{-- Pembelian --}}
                            <a href="{{ route('receiving.index') }}"
                                class="group flex flex-col items-center gap-2 rounded-2xl px-3 py-4 text-center transition-all duration-200
          bg-[#2e7d32] text-white shadow-[0px_6px_16px_#2e7d3266]
          hover:-translate-y-0.5 hover:bg-[#256628] active:scale-95">

                                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/20">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2">
                                        <circle cx="9" cy="21" r="1" />
                                        <circle cx="20" cy="21" r="1" />
                                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                                    </svg>
                                </div>

                                <span class="text-[12px] font-semibold">Pembelian</span>
                            </a>
                        </div>
                    </div>

                    <hr style="border-color:#cce0f0;">

                    {{-- DATA & LAPORAN --}}
                    <div>
                        <p class="text-[10.5px] font-semibold uppercase tracking-widest mb-2.5 px-0.5"
                            style="color:#7aa3c0; letter-spacing:0.09em;">
                            Data &amp; Laporan
                        </p>
                        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2">

                            {{-- Data Penjualan --}}
                            <a onclick="openModal('salesModal')"
                                class="group flex flex-col items-center gap-2 shadow-[0px_2px_10px_#218aeb38] rounded-2xl bg-white px-2 py-4 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#f7fbff] active:scale-95 cursor-pointer"
                                style="border: 1px solid #d4e8f7;">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl transition-colors duration-200"
                                    style="background:#fef0d6; color:#7a4a08;" onmouseover="this.style.background='#fde5b8'"
                                    onmouseout="this.style.background='#fef0d6'">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <line x1="12" y1="20" x2="12" y2="10" />
                                        <line x1="18" y1="20" x2="18" y2="4" />
                                        <line x1="6" y1="20" x2="6" y2="16" />
                                        <polyline points="1 20 23 20" />
                                    </svg>
                                </div>
                                <span class="text-[11.5px] font-medium leading-tight" style="color:#2d4a62;">Data
                                    Penjualan</span>
                            </a>

                            {{-- Data Pembelian --}}
                            <a onclick="openModal('receivingModal')"
                                class="group flex flex-col items-center gap-2 shadow-[0px_2px_10px_#218aeb38] rounded-2xl bg-white px-2 py-4 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#f7fbff] active:scale-95 cursor-pointer"
                                style="border: 1px solid #d4e8f7;">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl transition-colors duration-200"
                                    style="background:#eeecfd; color:#4c41b5;" onmouseover="this.style.background='#e0ddfb'"
                                    onmouseout="this.style.background='#eeecfd'">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <rect x="1" y="3" width="15" height="13" rx="2" />
                                        <path d="M16 8h4l4 5v4h-8V8z" />
                                        <circle cx="5.5" cy="18.5" r="2.5" />
                                        <circle cx="18.5" cy="18.5" r="2.5" />
                                    </svg>
                                </div>
                                <span class="text-[11.5px] font-medium leading-tight" style="color:#2d4a62;">Data
                                    Pembelian</span>
                            </a>

                            {{-- Laporan Penjualan --}}
                            <a onclick="openModal('reportModal')"
                                class="group flex flex-col items-center gap-2 shadow-[0px_2px_10px_#218aeb38] rounded-2xl bg-white px-2 py-4 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#f7fbff] active:scale-95 cursor-pointer"
                                style="border: 1px solid #d4e8f7;">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl transition-colors duration-200"
                                    style="background:#fde8e8; color:#9b2020;"
                                    onmouseover="this.style.background='#fcd4d4'"
                                    onmouseout="this.style.background='#fde8e8'">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                        <polyline points="14 2 14 8 20 8" />
                                        <line x1="16" y1="13" x2="8" y2="13" />
                                        <line x1="16" y1="17" x2="8" y2="17" />
                                    </svg>
                                </div>
                                <span class="text-[11.5px] font-medium leading-tight" style="color:#2d4a62;">Laporan
                                    Penjualan</span>
                            </a>

                            {{-- Laporan Pembelian --}}
                            <a onclick="openModal('orderReportModal')"
                                class="group flex flex-col items-center gap-2 shadow-[0px_2px_10px_#218aeb38] rounded-2xl bg-white px-2 py-4 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#f7fbff] active:scale-95 cursor-pointer"
                                style="border: 1px solid #d4e8f7;">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl transition-colors duration-200"
                                    style="background:#fdeae2; color:#8c3418;"
                                    onmouseover="this.style.background='#fcd8cc'"
                                    onmouseout="this.style.background='#fdeae2'">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" />
                                        <line x1="3" y1="6" x2="21" y2="6" />
                                        <path d="M16 10a4 4 0 01-8 0" />
                                    </svg>
                                </div>
                                <span class="text-[11.5px] font-medium leading-tight" style="color:#2d4a62;">Laporan
                                    Pembelian</span>
                            </a>

                            {{-- Pareto --}}
                            <a href="{{ route('pareto.index') }}"
                                class="group flex flex-col items-center gap-2 shadow-[0px_2px_10px_#218aeb38] rounded-2xl bg-white px-2 py-4 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#f7fbff] active:scale-95 cursor-pointer"
                                style="border: 1px solid #d4e8f7;">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl transition-colors duration-200"
                                    style="background:#fce8f3; color:#8b2857;"
                                    onmouseover="this.style.background='#f9d4e8'"
                                    onmouseout="this.style.background='#fce8f3'">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M3 3v18h18" />
                                        <path d="M3 6h18" />
                                        <path d="M3 8c10 0 8 9 18 9" />
                                    </svg>
                                </div>
                                <span class="text-[11.5px] font-medium leading-tight" style="color:#2d4a62;">Pareto</span>
                            </a>

                        </div>
                    </div>

                    <hr style="border-color:#cce0f0;">

                    {{-- MANAJEMEN --}}
                    <div>
                        <p class="text-[10.5px] font-semibold uppercase tracking-widest mb-2.5 px-0.5"
                            style="color:#7aa3c0; letter-spacing:0.09em;">
                            Manajemen
                        </p>
                        <div class="grid grid-cols-3 gap-2 w-full md:w-auto md:inline-grid"
                            style="grid-template-columns: repeat(3, minmax(80px, 110px))">

                            {{-- Master Data --}}
                            <a onclick="openModal('masterModal')"
                                class="group flex flex-col items-center gap-2 shadow-[0px_2px_10px_#218aeb38] rounded-2xl bg-white px-2 py-4 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#f7fbff] active:scale-95 cursor-pointer"
                                style="border: 1px solid #d4e8f7;">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl transition-colors duration-200"
                                    style="background:#d8f2eb; color:#0d6b55;"
                                    onmouseover="this.style.background='#c2ece1'"
                                    onmouseout="this.style.background='#d8f2eb'">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <ellipse cx="12" cy="5" rx="9" ry="3" />
                                        <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" />
                                        <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5" />
                                    </svg>
                                </div>
                                <span class="text-[11.5px] font-medium leading-tight" style="color:#2d4a62;">Master
                                    Data</span>
                            </a>

                            {{-- Persediaan --}}
                            <a onclick="openModal('logModal')"
                                class="group flex flex-col items-center gap-2 shadow-[0px_2px_10px_#218aeb38] rounded-2xl bg-white px-2 py-4 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#f7fbff] active:scale-95 cursor-pointer"
                                style="border: 1px solid #d4e8f7;">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl transition-colors duration-200"
                                    style="background:#fef0d6; color:#7a4a08;"
                                    onmouseover="this.style.background='#fde5b8'"
                                    onmouseout="this.style.background='#fef0d6'">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path
                                            d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                        <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                        <line x1="12" y1="22.08" x2="12" y2="12" />
                                    </svg>
                                </div>
                                <span class="text-[11.5px] font-medium leading-tight"
                                    style="color:#2d4a62;">Persediaan</span>
                            </a>
                            {{-- Mutasi --}}
                            <a onclick="openModal('transfersModal')"
                                class="group flex flex-col items-center gap-2 rounded-2xl shadow-[0px_2px_10px_#218aeb38] bg-white px-2 py-4 text-center transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#f7fbff] active:scale-95 cursor-pointer"
                                style="border: 1px solid #d4e8f7;">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl transition-colors duration-200"
                                    style="background:#d8f2eb; color:#0d6b55;"
                                    onmouseover="this.style.background='#c2ece1'"
                                    onmouseout="this.style.background='#d8f2eb'">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <polyline points="17 1 21 5 17 9" />
                                        <path d="M3 11V9a4 4 0 0 1 4-4h14" />
                                        <polyline points="7 23 3 19 7 15" />
                                        <path d="M21 13v2a4 4 0 0 1-4 4H3" />
                                    </svg>
                                </div>
                                <span class="text-[11.5px] font-medium leading-tight" style="color:#2d4a62;">Mutasi</span>
                            </a>

                        </div>
                    </div>

                </div>
            </div>
            
            <div
                class="min-w-[375px] md:min-w-[700px] xl:min-w-[800px] mt-3 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-3 3xl:grid-cols-6">
                <div
                    class="relative flex flex-grow !flex-row flex-col items-center rounded-[10px] rounded-[10px] border-[1px] border-gray-200 bg-white bg-clip-border shadow-md shadow-[#F3F3F3] dark:border-[#ffffff33] dark:!bg-navy-800 text-dark font-poppins dark:shadow-none">
                    <div class="ml-[18px] flex h-[90px] w-auto flex-row items-center">
                        <div class="rounded-full bg-[#ebf3ff] p-3">
                            <span class="flex items-center text-brand-500 text-[#422afb] font-poppins">
                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 24 24"
                                    class="h-7 w-7" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                    <path fill="none" d="M0 0h24v24H0z"></path>
                                    <path d="M4 9h4v11H4zM16 13h4v7h-4zM10 4h4v16h-4z"></path>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div class="h-50 ml-4 flex w-auto flex-col justify-center">
                        <p class="font-dm text-sm font-medium text-gray-600">Total Penjualan</p>
                        <h4 class="text-xl font-bold text-navy-700 text-dark font-poppins">{{ $total_sales_rp }}</h4>
                    </div>
                </div>

                <div
                    class="relative flex flex-grow !flex-row flex-col items-center rounded-[10px] rounded-[10px] border-[1px] border-gray-200 bg-white bg-clip-border shadow-md shadow-[#F3F3F3] dark:border-[#ffffff33] dark:!bg-navy-800 text-dark font-poppins dark:shadow-none">
                    <div class="ml-[18px] flex h-[90px] w-auto flex-row items-center">
                        <div class="rounded-full bg-[#ebf3ff] p-3">
                            <span class="flex items-center text-brand-500 text-[#422afb] font-poppins">
                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 512 512"
                                    class="h-6 w-6" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M298.39 248a4 4 0 002.86-6.8l-78.4-79.72a4 4 0 00-6.85 2.81V236a12 12 0 0012 12z">
                                    </path>
                                    <path
                                        d="M197 267a43.67 43.67 0 01-13-31v-92h-72a64.19 64.19 0 00-64 64v224a64 64 0 0064 64h144a64 64 0 0064-64V280h-92a43.61 43.61 0 01-31-13zm175-147h70.39a4 4 0 002.86-6.8l-78.4-79.72a4 4 0 00-6.85 2.81V108a12 12 0 0012 12z">
                                    </path>
                                    <path
                                        d="M372 152a44.34 44.34 0 01-44-44V16H220a60.07 60.07 0 00-60 60v36h42.12A40.81 40.81 0 01231 124.14l109.16 111a41.11 41.11 0 0111.83 29V400h53.05c32.51 0 58.95-26.92 58.95-60V152z">
                                    </path>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div class="h-50 ml-4 flex w-auto flex-col justify-center">
                        <p class="font-dm text-sm font-medium text-gray-600">Total Pembelian</p>
                        <h4 class="text-xl font-bold text-navy-700 text-dark font-poppins">{{ $total_orders_rp }}</h4>
                    </div>
                </div>
                <div
                    class="relative flex flex-grow !flex-row flex-col items-center rounded-[10px] rounded-[10px] border-[1px] border-gray-200 bg-white bg-clip-border shadow-md shadow-[#F3F3F3] dark:border-[#ffffff33] dark:!bg-navy-800 text-dark font-poppins dark:shadow-none">
                    <div class="ml-[18px] flex h-[90px] w-auto flex-row items-center">
                        <div class="rounded-full bg-[#ebf3ff] p-3">
                            <span class="flex items-center text-brand-500 text-[#422afb] font-poppins">
                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 24 24"
                                    class="h-7 w-7" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                    <path fill="none" d="M0 0h24v24H0z"></path>
                                    <path d="M4 9h4v11H4zM16 13h4v7h-4zM10 4h4v16h-4z"></path>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div class="h-50 ml-4 flex w-auto flex-col justify-center">
                        <p class="font-dm text-sm font-medium text-gray-600">Total Ditolak</p>
                        <h4 class="text-xl font-bold text-navy-700 text-dark font-poppins">{{ $total_reject_rp }}</h4>
                    </div>
                </div>
            </div>
            <div
                class="min-w-[375px] md:min-w-[700px] xl:min-w-[800px] mt-3 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-3 3xl:grid-cols-6">
                <div
                    class="relative flex flex-grow !flex-row flex-col items-center rounded-[10px] rounded-[10px] border-[1px] border-gray-200 bg-white bg-clip-border shadow-md shadow-[#F3F3F3] dark:border-[#ffffff33] dark:!bg-navy-800 text-dark font-poppins dark:shadow-none">
                    <div id="chart" style="width: 600px; height:400px;"></div>
                </div>
                <div
                    class="relative flex flex-grow !flex-row flex-col items-center rounded-[10px] rounded-[10px] border-[1px] border-gray-200 bg-white bg-clip-border shadow-md shadow-[#F3F3F3] dark:border-[#ffffff33] dark:!bg-navy-800 text-dark font-poppins dark:shadow-none">
                    <div id="distribution" style="width: 600px; height:400px;"></div>
                </div>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/echarts/dist/echarts.min.js"></script>
    <script>
        var chartDom = document.getElementById('chart');
        var distributionDom = document.getElementById('distribution');
        var myChart = echarts.init(chartDom);
        var distributionChart = echarts.init(distributionDom);

        var option = {
            title: {
                text: 'Penjualan Bulanan'
            },
            tooltip: {},
            xAxis: {
                type: 'category',
                data: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun']
            },
            yAxis: {
                type: 'value'
            },
            series: [{
                data: [120, 200, 150, 80, 70, 110],
                type: 'line'
            }]
        };

        var distribution = {
            title: {
                text: 'Distribusi Penjualan'
            },
            tooltip: {
                trigger: 'item'
            },
            series: [{
                name: 'Penjualan',
                type: 'pie',
                radius: '50%',
                data: [{
                        value: 1048,
                        name: 'UPDS'
                    },
                    {
                        value: 735,
                        name: 'HV/OTC'
                    },
                    {
                        value: 580,
                        name: 'RESEP'
                    },
                    {
                        value: 484,
                        name: 'RESEP KREDIT'
                    }
                ]
            }]
        };

        myChart.setOption(option);

        distributionChart.setOption(distribution);
    </script>




    {{-- ------------------- Fixed Cart Card --------------------- --}}
@endsection
