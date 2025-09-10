@extends('layouts.app')

@section('title', 'Detail Artikel')

@section('style')
@endsection

@section('content')
    <section class="section">
        <div class="section-header">
            <div class="section-header-back">
                <a href="{{ route('articles.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
            </div>
            <h1>Artikel</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Artikel</a></div>
                <div class="breadcrumb-item"><a href="#">Detail</a></div>
            </div>
            <div class="section-header-button ml-auto">
                {{-- @can('articles-edit') --}}
                    <a class="btn btn-primary" href="{{ route('articles.edit', $article->id) }}">
                        Edit
                    </a>
                {{-- @endcan --}}
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
                                        <h6>Judul Artikel</h6>
                                        <p>{{ $article->title }}</p>
                                    </div>
                                </li>

                                <li class="media">
                                    <div class="media-icon">
                                        <i class="ion ion-ios-circle-outline"></i>
                                    </div>
                                    <div class="media-body">
                                        <h6>Thumbnail Artikel</h6>
                                        <div class="gallery gallery-fw" data-item-height="200">
                                            <div class="gallery-item"
                                                data-image="{{ asset('uploads/articles/'. $article->thumbnail) }}"
                                                data-title="Image 1">
                                            </div>
                                        </div>
                                        <span>{{ $article->thumbnail_info }}</span>
                                    </div>
                                </li>
        
                                <li class="media">
                                    <div class="media-icon">
                                        <i class="ion ion-ios-circle-outline"></i>
                                    </div>
                                    <div class="media-body">
                                        <h6>Level</h6>
                                        {!! $article->content !!}
                                    </div>
                                </li>
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
