@extends('layouts.app')

@section('title', 'Edit Data Matchday')

@section('style')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/css/dropify.min.css" integrity="sha512-EZSUkJWTjzDlspOoPSpUFR0o0Xy7jdzW//6qhUkoZ9c4StFkVsp9fbbd0O06p9ELS3H486m4wmrCELjza4JEog==" crossorigin="anonymous" referrerpolicy="no-referrer" />
@endsection

@section('content')
<section class="section">
    <div class="section-header">
        <div class="section-header-back">
            <a href="{{ route('matchday.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
        </div>
        <h1>Edit Data MatchDay</h1>
        <div class="section-header-breadcrumb">
            <div class="breadcrumb-item active"><a href="#">MatchDay</a></div>
            <div class="breadcrumb-item"><a href="#">Edit</a></div>
        </div>
    </div>

    <div class="section-body">
        <div class="row">
            <div class="col-12">
                
                {!! Form::model($matchDay, ['method' => 'PATCH', 'route' => ['matchday.update', $matchDay->id  ], 'autocomplete'=>'off', 'class'=> 'needs-validation', 'novalidate'=> '', 'files' => true]) !!}
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
                            <label for="hari" class="col-sm-3 col-form-label">
                                Hari
                                <i class="text-danger">*</i>
                            </label>
                            <div class="col-sm-9">
                                {!! Form::text('day', null, array('id' => 'day', 'placeholder' => 'Hari Pertandingan', 'class' => 'form-control', 'required', 'autofocus')) !!}
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="place" class="col-sm-3 col-form-label">
                                Venue
                                <i class="text-danger">*</i>
                            </label>
                            <div class="col-sm-9">
                                {!! Form::text('place', null, array('id' => 'place', 'placeholder' => 'Venue Pertandingan', 'class' => 'form-control', 'required')) !!}
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="date" class="col-sm-3 col-form-label">
                                Tanggal Pelaksanaan
                                <i class="text-danger">*</i>
                            </label>
                            <div class="col-sm-9">
                                {!! Form::date('date', null, array('id' => 'date', 'placeholder' => 'Tanggal Pertandingan', 'class' => 'form-control', 'required')) !!}
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
