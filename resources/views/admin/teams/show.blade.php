@extends('layouts.app')

@section('title', 'Detail Data Tim')

@section('style')
@endsection

@section('content')
    <section class="section">
        <div class="section-header">
            <div class="section-header-back">
                <a href="{{ route('teams.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
            </div>
            <h1>Data Tim</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Tim</a></div>
                <div class="breadcrumb-item"><a href="#">Detail</a></div>
            </div>
            <div class="section-header-button ml-auto">
                <a class="btn btn-warning" href="{{ route('teams.download', $team->id) }}" target="_blank">
                    Download Data
                </a>
                <a class="btn btn-primary" href="{{ route('teams.edit', $team->id) }}">
                    Edit
                </a>
            </div>
        </div>

        <div class="section-body">
            <div class="row mt-sm-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
        
                            <ul class="list-unstyled list-unstyled-border mt-4">
                                <li class="media">
                                    <div class="media-icon">
                                        <i class="ion ion-ios-circle-outline"></i>
                                    </div>
                                    <div class="media-body">
                                        <h6>Nama Tim</h6>
                                        <p>{{ $team->name }}</p>
                                    </div>
                                </li>

                                <li class="media">
                                    <div class="media-icon">
                                        <i class="ion ion-ios-circle-outline"></i>
                                    </div>
                                    <div class="media-body">
                                        <h6>Data Manajer Tim</h6>
                                        <p>Nama : {{ $team->manager_name }}</p>
                                        <p>Email : {{ $team->manager_email }}</p>
                                        <p>NO HP : {{ $team->manager_phone }}</p>
                                    </div>
                                </li>

                                @if ($team->statement_letter != null)
                                    <li class="media">
                                        <div class="media-icon">
                                            <i class="ion ion-ios-circle-outline"></i>
                                        </div>
                                        <div class="media-body">
                                            <h6>Surat Pernyataan</h6>
                                            <a href="{{ $team->file_url }}" target="_blank" class="btn btn-info">
                                                Cek File <i class="fas fa-file-alt"></i>
                                            </a>
                                        </div>
                                    </li>
                                @endif

                                @if ($team->logo)
                                    <li class="media">
                                        <div class="media-icon">
                                            <i class="ion ion-ios-circle-outline"></i>
                                        </div>
                                        <div class="media-body">
                                            <h6>Logo</h6>
                                            <div class="gallery gallery-fw" data-item-height="200">
                                                <div class="gallery-item"
                                                    data-image="{{ $team->logo_url }}"
                                                    data-title="Logo Tim">
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @endif
    
                            </ul>
                        
                        </div>
                    </div>
        
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
@endsection
