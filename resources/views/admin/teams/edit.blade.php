@extends('layouts.app')

@section('title', 'Edit Data TIM')

@section('style')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/css/dropify.min.css" integrity="sha512-EZSUkJWTjzDlspOoPSpUFR0o0Xy7jdzW//6qhUkoZ9c4StFkVsp9fbbd0O06p9ELS3H486m4wmrCELjza4JEog==" crossorigin="anonymous" referrerpolicy="no-referrer" />
@endsection

@section('content')
<section class="section">
    <div class="section-header">
        <div class="section-header-back">
            <a href="{{ route('teams.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
        </div>
        <h1>Edit Data TIM</h1>
        <div class="section-header-breadcrumb">
            <div class="breadcrumb-item active"><a href="#">TIM</a></div>
            <div class="breadcrumb-item"><a href="#">Edit</a></div>
        </div>
    </div>

    <div class="section-body">
        <div class="row">
            <div class="col-12">
                
                {!! Form::model($team, ['method' => 'PATCH', 'route' => ['teams.update', $team->id], 'autocomplete'=>'off', 'class'=> 'needs-validation', 'novalidate'=> '', 'files' => true]) !!}
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

                        @if (\Session::has('message'))
                            <div class="alert alert-danger">
                                <ul>
                                    <li>{!! \Session::get('message') !!}</li>
                                </ul>
                            </div>
                        @endif

                        <div class="form-group row">
                            <label for="name" class="col-sm-3 col-form-label">
                                Nama Tim
                                <i class="text-danger">*</i>
                            </label>
                            <div class="col-sm-9">
                                {!! Form::text('name', null, array('id' => 'name', 'placeholder' => 'Nama Tim', 'class' => 'form-control', 'required', 'autofocus')) !!}
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="image" class="col-sm-3 col-form-label">Logo</label>
                            <div class="col-sm-9">
                                <input type="file" name="image" class="dropify" data-height="150" data-max-file-size="1M" data-allowed-file-extensions="jpg jpeg png" data-default-file="{{ $team->logo_url }}" accept="image/*">
                                <span class="text-muted">Maks. ukuran file 1 MB | Ekstensi png, jpg, jpeg</span>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="manager_name" class="col-sm-3 col-form-label">
                                Nama Manajer
                                <i class="text-danger">*</i>
                            </label>
                            <div class="col-sm-9">
                                {!! Form::text('manager_name', null, array('id' => 'manager_name', 'placeholder' => 'Nama Manajer', 'class' => 'form-control', 'required')) !!}
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="manager_email" class="col-sm-3 col-form-label">
                                Email Manajer
                                <i class="text-danger">*</i>
                            </label>
                            <div class="col-sm-9">
                                {!! Form::email('manager_email', null, array('id' => 'manager_email', 'placeholder' => 'Email Manajer', 'class' => 'form-control', 'required')) !!}
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="manager_phone" class="col-sm-3 col-form-label">
                                Nomor HP Manajer
                                <i class="text-danger">*</i>
                            </label>
                            <div class="col-sm-9">
                                {!! Form::text('manager_phone', null, array('id' => 'manager_phone', 'placeholder' => 'Nomor HP Manajer', 'class' => 'form-control', 'required')) !!}
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
    <script type="text/javascript">
    $(function(){
        $('.dropify').dropify();
    });
    </script>
@endsection