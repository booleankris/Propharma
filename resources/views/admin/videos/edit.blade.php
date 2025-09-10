@extends('layouts.app')

@section('title', 'Edit video')

@section('style')
@endsection

@section('content')
<section class="section">
    <div class="section-header">
        <div class="section-header-back">
            <a href="{{ route('videos.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
        </div>
        <h1>Edit Video</h1>
        <div class="section-header-breadcrumb">
            <div class="breadcrumb-item active"><a href="#">video</a></div>
            <div class="breadcrumb-item"><a href="#">Create</a></div>
        </div>
    </div>

    <div class="section-body">
        <div class="row">
            <div class="col-12">
                
                {!! Form::model($video, ['method' => 'PATCH', 'route' => ['videos.update', $video->id], 'autocomplete'=>'off', 'class'=> 'needs-validation', 'novalidate'=> '', 'files' => true]) !!}
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
                            <label for="title" class="col-sm-3 col-form-label">Judul Video</label>
                            <div class="col-sm-9">
                                {!! Form::text('title', null, array('id' => 'title', 'placeholder' => 'judul Video', 'class' => 'form-control')) !!}
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="link" class="col-sm-3 col-form-label">Link Video (Youtube)</label>
                            <div class="col-sm-9">
                                {!! Form::text('link', null, array('id' => 'link', 'placeholder' => 'Link Video', 'class' => 'form-control')) !!}
                                <Contoh>example : https://youtu.be/rjSlFXv0-Hc?si=XM2eiFYRy0p6zNH8</Contoh>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="link" class="col-sm-3 col-form-label">Preview Video (Youtube)</label>
                            <div class="col-sm-9">
                                <iframe width="560" id="youtube" height="315" src="{{ $video->link }}" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
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
    <script type="text/javascript">
    document.getElementById("link").addEventListener("keyup", previewYt);

    function previewYt() {
        let link = document.getElementById("link");
        var linkYoutube = link.value.replace('https://youtu.be/','https://www.youtube.com/embed/');
        console.log(linkYoutube);
        document.getElementById('youtube').src = linkYoutube;
    }
    </script>
@endsection