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

                            @if (session('error'))
                                <div class="alert alert-danger alert-dismissible show fade">
                                    <div class="alert-body">
                                        <button class="close" data-dismiss="alert"><span>&times;</span></button>
                                        <strong><i class="fas fa-exclamation-triangle mr-1"></i> Terjadi Kesalahan!</strong>
                                        {{ session('error') }}
                                    </div>
                                </div>
                            @endif

                            @if (count($errors) > 0)
                                <div class="alert alert-danger alert-dismissible show fade">
                                    <div class="alert-body">
                                        <button class="close" data-dismiss="alert"><span>&times;</span></button>
                                        <strong><i class="fas fa-exclamation-circle mr-1"></i> Periksa Inputan Anda:</strong>
                                        <ul class="mb-0 mt-2 pl-3">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            @endif

                            <div class="form-group row">
                                <label for="name" class="col-sm-3 col-form-label font-weight-bold">Nama Lengkap <span class="text-danger">*</span></label>
                                <div class="col-sm-9">
                                    {!! Form::text('name', old('name'), [
                                        'id' => 'name',
                                        'placeholder' => 'Nama Lengkap',
                                        'class' => 'form-control ' . ($errors->has('name') ? 'is-invalid' : ''),
                                        'required',
                                        'autofocus',
                                    ]) !!}
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="username" class="col-sm-3 col-form-label font-weight-bold">Username <span class="text-danger">*</span></label>
                                <div class="col-sm-9">
                                    {!! Form::text('username', old('username'), [
                                        'id' => 'username',
                                        'placeholder' => 'Username',
                                        'class' => 'form-control ' . ($errors->has('username') ? 'is-invalid' : ''),
                                        'required',
                                    ]) !!}
                                    @error('username')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="password" class="col-sm-3 col-form-label font-weight-bold">Password / PIN Rahasia <span class="text-danger">*</span></label>
                                <div class="col-sm-9">
                                    {!! Form::password('password', [
                                        'id' => 'password',
                                        'placeholder' => 'Password / PIN Rahasia (min. 4 karakter)',
                                        'class' => 'form-control pwstrength ' . ($errors->has('password') ? 'is-invalid' : ''),
                                        'data-indicator' => 'pwindicator',
                                        'required',
                                    ]) !!}
                                    <div id="pwindicator" class="pwindicator">
                                        <div class="bar"></div>
                                        <div class="label"></div>
                                    </div>
                                    @error('password')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="confirm-password" class="col-sm-3 col-form-label font-weight-bold">Konfirmasi Password / PIN <span class="text-danger">*</span></label>
                                <div class="col-sm-9">
                                    {!! Form::password('confirm-password', [
                                        'id' => 'confirm-password',
                                        'placeholder' => 'Konfirmasi Password / PIN Rahasia',
                                        'class' => 'form-control ' . ($errors->has('confirm-password') ? 'is-invalid' : ''),
                                        'required',
                                    ]) !!}
                                    @error('confirm-password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="pharmacy_id" class="col-sm-3 col-form-label font-weight-bold">Apotek <span class="text-danger">*</span></label>
                                <div class="col-sm-9">
                                    {!! Form::select('pharmacy_id', $pharmacies, old('pharmacy_id'), [
                                        'id' => 'pharmacy_id',
                                        'class' => 'form-control custom-select select2 ' . ($errors->has('pharmacy_id') ? 'is-invalid' : ''),
                                        'required',
                                    ]) !!}
                                    @error('pharmacy_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="roles" class="col-sm-3 col-form-label font-weight-bold">Level / Role <span class="text-danger">*</span></label>
                                <div class="col-sm-9">
                                    {!! Form::select(
                                        'roles[]',
                                        $roles,
                                        old('roles', []),
                                        ['id' => 'roles', 'class' => 'form-control custom-select select2 ' . ($errors->has('roles') ? 'is-invalid' : ''), 'required'],
                                    ) !!}
                                    @error('roles')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('users.index') }}" class="btn btn-lg btn-secondary mr-2">Batal</a>
                            <button type="submit" class="btn btn-lg btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
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
            @if ($message = Session::get('error'))
                toastr.error('{{ $message }}', 'Error');
            @endif
            @if ($message = Session::get('warning'))
                toastr.warning('{{ $message }}', 'Peringatan');
            @endif

            $(".pwstrength").pwstrength();
        });
    </script>
@endsection
