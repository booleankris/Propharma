@extends('layouts.app')

@section('title', 'Edit Client')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/css/dropify.min.css"
        integrity="sha512-EZSUkJWTjzDlspOoPSpUFR0o0Xy7jdzW//6qhUkoZ9c4StFkVsp9fbbd0O06p9ELS3H486m4wmrCELjza4JEog=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
@endsection

@section('content')
    <section class="section">
        <div class="section-header">
            <div class="section-header-back">
                <a href="{{ route('members.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
            </div>
            <h1>Edit Member</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Member</a></div>
                <div class="breadcrumb-item"><a href="#">Edit</a></div>
            </div>
        </div>

        <div class="section-body">
            <div class="row">
                <div class="col-12">

                    {!! Form::model($member, ['method' => 'PATCH', 'route' => ['members.update', $member->id], 'autocomplete'=>'off', 'class'=> 'needs-validation', 'novalidate'=> '', 'files' => true]) !!}
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
                                <label for="name" class="col-sm-3 col-form-label">Nama Pemain / Pelatih /
                                    Pengurus</label>
                                <div class="col-sm-9">
                                    {!! Form::text('name', null, [
                                        'id' => 'name',
                                        'placeholder' => 'Nama Pemain/Anggota',
                                        'class' => 'form-control',
                                    ]) !!}
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="image" class="col-sm-3 col-form-label">Foto Member</label>
                                <div class="col-sm-9">
                                    <input type="file" name="image" class="dropify" data-height="200"
                                        data-max-file-size="5M" data-allowed-file-extensions="jpg jpeg png"
                                        data-default-file="{{ asset('uploads/members/' . $member->image) }}">
                                    <span class="text-muted">Maks. ukuran file 5 MB | Ekstensi png, jpg, jpeg</span>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="role" class="col-sm-3 col-form-label">Jenis Member</label>
                                <div class="col-sm-9">
                                    {!! Form::select('role', ['0' => 'Pemain', '1' => 'Pengurus', '2' => 'Pelatih'], null, [
                                        'class' => 'form-control',
                                        'placeholder' => 'Pilih Jenis Member',
                                    ]) !!}
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="occupation" class="col-sm-3 col-form-label">Posisi/Jabatan</label>
                                <div class="col-sm-9">
                                    {!! Form::text('occupation', null, [
                                        'id' => 'occupation',
                                        'placeholder' => 'Ketua, Sekretaris, coach',
                                        'class' => 'form-control',
                                    ]) !!}
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="number" class="col-sm-3 col-form-label">Nomor Punggung</label>
                                <div class="col-sm-9">
                                    {!! Form::text('number', null, ['id' => 'number', 'placeholder' => '01, 12, 44, 73', 'class' => 'form-control']) !!}
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/js/dropify.min.js"
        integrity="sha512-8QFTrG0oeOiyWo/VM9Y8kgxdlCryqhIxVeRpWSezdRRAvarxVtwLnGroJgnVW9/XBRduxO/z1GblzPrMQoeuew=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script type="text/javascript">
        $(function() {
            $('.dropify').dropify();
        });
    </script>
@endsection
