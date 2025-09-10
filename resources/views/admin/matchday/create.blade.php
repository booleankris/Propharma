@extends('layouts.app')

@section('title', 'Buat Matchday')

@section('style')
@endsection

@section('content')
<section class="section">
    <div class="section-header">
        <div class="section-header-back">
            <a href="{{ route('matchday.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
        </div>
        <h1>Buat Matchday</h1>
        <div class="section-header-breadcrumb">
            <div class="breadcrumb-item active"><a href="#">Matchday</a></div>
            <div class="breadcrumb-item"><a href="#">Create</a></div>
        </div>
    </div>

    <div class="section-body">
        <div class="row">
            <div class="col-12">
                
                {!! Form::open(['method'=>'POST', 'route' => 'matchday.store', 'autocomplete'=>'off', 'class'=> 'needs-validation', 'novalidate'=> '', 'files' => true]) !!}
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
                            <label for="hari" class="col-sm-3 col-form-label">Hari</label>
                            <div class="col-sm-9">
                                {!! Form::text('day', null, array('id' => 'day', 'placeholder' => 'Masukkan Hari', 'class' => 'form-control')) !!}
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="place" class="col-sm-3 col-form-label">
                                Venue
                                <i class="text-danger">*</i>
                            </label>
                            <div class="col-sm-9">
                                {!! Form::text('place', null, array('id' => 'place', 'placeholder' => 'Venue Pelaksanaan', 'class' => 'form-control', 'required')) !!}
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="date" class="col-sm-3 col-form-label">
                                Tanggal Pelaksanaan
                                <i class="text-danger">*</i>
                            </label>
                            <div class="col-sm-9">
                                {!! Form::date('date', null, array('id' => 'date', 'placeholder' => 'Tanggal Pelaksanaan', 'class' => 'form-control', 'required')) !!}
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
