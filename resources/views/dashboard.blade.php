@extends('layouts.admin')

@section('title', 'Dashboard')

@section('style')
    <!-- CSS Libraries -->
@endsection

@section('content')
    <section class="section">
        <div class="section-header">
            <h1>{{ __('Dashboard') }}</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active">{{ __('Dashboard') }}</div>
            </div>
        </div>

        <div class="section-body">
            <div class="col-lg-4 w-full p-0 col-md-4 col-sm-12">
                <div class="card !mb-[15px] card-statistic-2">
                    <div class="card-stats">
                        <div class="card-stats-title">Statistik Pesanan
                            {{-- <div class="dropdown d-inline">
                                <a class="font-weight-600 dropdown-toggle" data-toggle="dropdown" href="#"
                                    id="orders-month">Semua</a>
                                <ul class="dropdown-menu dropdown-menu-sm">
                                    <li class="dropdown-title">Pilih Tanggal</li>
                                    <li><a href="#" class="dropdown-item">Semua</a></li>
                                    <li><a href="#" class="dropdown-item">Tanggal 26</a></li>
                                    <li><a href="#" class="dropdown-item">Tanggal 27</a></li>
                                </ul>
                            </div> --}}
                        </div>
                        <div class="card-stats-items">
                            <div class="card-stats-item">
                                <div class="card-stats-item-count">0</div>
                                <div class="card-stats-item-label">Expired</div>
                            </div>
                            <div class="card-stats-item">
                                <div class="card-stats-item-count">0</div>
                                <div class="card-stats-item-label">Pending</div>
                            </div>
                            <div class="card-stats-item">
                                <div class="card-stats-item-count">0</div>
                                <div class="card-stats-item-label">Berhasil</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-icon shadow-primary bg-primary">
                        <i class="fas fa-archive"></i>
                    </div>
                    <div class="card-wrap">
                        <div class="card-header">
                            <h4>Total Transaksi</h4>
                        </div>
                        <div class="card-body">
0                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <div class="col-lg-4 p-0 col-md-4 col-sm-12">
        <div class="card card-statistic-2">

            <div class="card-icon shadow-primary bg-primary">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="card-wrap">
                <div class="card-header">
                    <h4>Total Pemasukan</h4>
                </div>
                <div class="card-body">
                    Rp  0
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <!-- JS Libraies -->

    <!-- Page Specific JS File -->
@endsection
