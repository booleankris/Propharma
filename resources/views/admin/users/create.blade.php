@extends('layouts.admin')

@section('title', 'Buat Administrator')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/select2/dist/css/select2.min.css') }}">
@endsection

@section('content')
    <section class="section">
        <div class="section-header">
            <div class="section-header-back">
                <a href="{{ route('users.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
            </div>
            <h1>Buat User</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">User</a></div>
                <div class="breadcrumb-item"><a href="#">Create</a></div>
            </div>
        </div>

        <div class="section-body">
            <div class="row">
                <div class="col-12">

                    {!! Form::open([
                        'method' => 'POST',
                        'route' => 'users.store',
                        'autocomplete' => 'off',
                        'class' => 'needs-validation',
                        'novalidate' => '',
                    ]) !!}
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
                                <label for="name" class="col-sm-3 col-form-label">Nama Lengkap</label>
                                <div class="col-sm-9">
                                    {!! Form::text('name', null, [
                                        'id' => 'name',
                                        'placeholder' => 'Nama Lengkap',
                                        'class' => 'form-control',
                                        'required',
                                        'autofocus',
                                    ]) !!}
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="username" class="col-sm-3 col-form-label">Username</label>
                                <div class="col-sm-9">
                                    {!! Form::text('username', null, [
                                        'id' => 'username',
                                        'placeholder' => 'Username',
                                        'class' => 'form-control',
                                        'required',
                                    ]) !!}
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="password" class="col-sm-3 col-form-label">Password / PIN Rahasia</label>
                                <div class="col-sm-9">
                                    {!! Form::password('password', [
                                        'id' => 'password',
                                        'placeholder' => 'Password / PIN Rahasia',
                                        'class' => 'form-control pwstrength',
                                        'data-indicator' => 'pwindicator',
                                        'required',
                                        'maxlength' => 6,
                                    ]) !!}
                                    <div id="pwindicator" class="pwindicator">
                                        <div class="bar"></div>
                                        <div class="label"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="confirm-password" class="col-sm-3 col-form-label">Konfirmasi Password / PIN Rahasia</label>
                                <div class="col-sm-9">
                                    {!! Form::password('confirm-password', [
                                        'id' => 'confirm-password',
                                        'placeholder' => 'Konfirmasi Password / PIN Rahasia',
                                        'class' => 'form-control',
                                        'required',
                                        'maxlength' => 6,
                                    ]) !!}
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="roles" class="col-sm-3 col-form-label">Apotek</label>
                                <div class="col-sm-9">
                                    {!! Form::select('pharmacy_id', $pharmacies, null, [
                                        'id' => 'pharmacy_id',
                                        'class' => 'form-control custom-select select2',
                                        'required',
                                    ]) !!}
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="roles" class="col-sm-3 col-form-label">Level</label>
                                <div class="col-sm-9">
                                    {!! Form::select(
                                        'roles[]',
                                        $roles,
                                        [],
                                        ['id' => 'roles', 'class' => 'form-control custom-select select2', 'required'],
                                    ) !!}
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
    <script src="{{ asset('templates/library/select2/dist/js/select2.min.js') }}"></script>
    <script src="{{ asset('templates/library/jquery.pwstrength/jquery.pwstrength.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            @if ($message = Session::get('success'))
                toastr.success('{{ $message }}', 'Success');
            @endif

            $(".pwstrength").pwstrength();
        });
    </script>
@endsection
