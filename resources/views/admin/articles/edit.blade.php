@extends('layouts.app')

@section('title', 'Edit Artikel')

@section('style')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/css/dropify.min.css" integrity="sha512-EZSUkJWTjzDlspOoPSpUFR0o0Xy7jdzW//6qhUkoZ9c4StFkVsp9fbbd0O06p9ELS3H486m4wmrCELjza4JEog==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="{{ asset('templates/library/summernote/dist/summernote-bs4.css') }}">
@endsection

@section('content')
<section class="section">
    <div class="section-header">
        <div class="section-header-back">
            <a href="{{ route('articles.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
        </div>
        <h1>Edit Artikel</h1>
        <div class="section-header-breadcrumb">
            <div class="breadcrumb-item active"><a href="#">Artikel</a></div>
            <div class="breadcrumb-item"><a href="#">Edit</a></div>
        </div>
    </div>

    <div class="section-body">
        <div class="row">
            <div class="col-12">
                
                {!! Form::model($article, ['method' => 'PATCH', 'route' => ['articles.update', $article->id], 'autocomplete'=>'off', 'class'=> 'needs-validation', 'novalidate'=> '', 'files' => true]) !!}
                <div class="card">
                    <div class="card-body">

                        @if (count($errors) > 0)
                        <div class="alert alert-danger">
                            <strong>Whoops!</strong> Ada beberapa masalah dengan inputan/masukan Anda.<br><br>
                            <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                            </ul>
                        </div>
                        @endif

                        <div class="form-group row">
                            <label for="title" class="col-sm-3 col-form-label">Judul Artikel</label>
                            <div class="col-sm-9">
                                {!! Form::text('title', null, array('id' => 'title', 'placeholder' => 'Judul Artikel', 'class' => 'form-control', 'required', 'autofocus')) !!}
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="image" class="col-sm-3 col-form-label">Thumbnail</label>
                            <div class="col-sm-9">
                                <input type="file" name="image" class="dropify" data-height="150" data-max-file-size="1M" data-allowed-file-extensions="jpg jpeg png" data-default-file="{{ asset('uploads/articles/'.$article->thumbnail) }}">
                                <span class="text-muted">Maks. ukuran file 1 MB | Ekstensi png, jpg, jpeg</span>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="thumbnail_info" class="col-sm-3 col-form-label">Deskripsi Thumbnail</label>
                            <div class="col-sm-9">
                                {!! Form::text('thumbnail_info', null, array('id' => 'thumbnail_info', 'placeholder' => 'Deskripsi Artikel', 'class' => 'form-control')) !!}
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="content" class="col-sm-3 col-form-label">Konten</label>
                            <div class="col-sm-9">
                                <textarea name="content" class="summernote-id" required>{!! $article->content !!}</textarea>
                            </div>
                        </div>

                    </div>
                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-lg btn-primary">Simpan</button>
                    </div>
                </div>
                {!! Form::close() !!}

            </div>
        </div>
    </div>
</section>
@endsection

@section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/js/dropify.min.js" integrity="sha512-8QFTrG0oeOiyWo/VM9Y8kgxdlCryqhIxVeRpWSezdRRAvarxVtwLnGroJgnVW9/XBRduxO/z1GblzPrMQoeuew==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="{{ asset('templates/library/summernote/dist/summernote-bs4.js') }}"></script>
    <script type="text/javascript">
    $(function(){
        $('.summernote-id').summernote({
            height: 150,  
            placeholder: 'Masukan Konten Artikel',
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', []],
                ['view', []]
            ]
        });

        $('.dropify').dropify();
    });
    </script>
@endsection