@extends('layouts.admin')

@section('title', 'Tambah Apotek')

@section('content')
    <section class="section">
        <div class="section-header">
            <h1>Tambah Apotek</h1>
        </div>

        {!! Form::open(['route' => 'pharmacies.store']) !!}
        <div class="card">
            <div class="card-body">

                <div class="form-group">
                    <label>Nama Apotek</label>
                    {!! Form::text('name', null, ['class' => 'form-control', 'required']) !!}
                </div>

                <div class="form-group">
                    <label>Alamat</label>
                    {!! Form::text('address', null, ['class' => 'form-control']) !!}
                </div>

                <div class="form-group">
                    <label>No Telp</label>
                    {!! Form::text('phone', null, ['class' => 'form-control']) !!}
                </div>

                <div class="form-group">
                    <label>Kota</label>
                    {!! Form::text('city', null, ['class' => 'form-control']) !!}
                </div>

                <div class="form-group">
                    <label>NPWP</label>
                    {!! Form::text('npwp', null, ['class' => 'form-control']) !!}
                </div>

                <div class="form-group">
                    <label>No Izin Apotek</label>
                    {!! Form::text('permit', null, ['class' => 'form-control']) !!}
                </div>

                <div class="form-group">
                    <label>No Izin Apoteker</label>
                    {!! Form::text('pharmacist_permit', null, ['class' => 'form-control']) !!}
                </div>

                <div class="form-group">
                    <label>Apoteker</label>
                    {!! Form::text('pharmacist', null, ['class' => 'form-control']) !!}
                </div>

                <div class="form-group">
                    <label>Footer 1</label>
                    {!! Form::text('footnote1', null, ['class' => 'form-control']) !!}
                </div>

                <div class="form-group">
                    <label>Footer 2</label>
                    {!! Form::text('footnote2', null, ['class' => 'form-control']) !!}
                </div>

                <div class="form-group">
                    <label>Status</label>
                    {!! Form::select('status', ['1' => 'Aktif', '0' => 'Nonaktif'], null, ['class' => 'form-control']) !!}
                </div>

            </div>

            <div class="card-footer text-right">
                <button class="btn btn-primary">Simpan</button>
            </div>
        </div>
        {!! Form::close() !!}

    </section>
@endsection
@section('scripts')
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('templates/library/jquery-ui-dist/jquery-ui.min.js') }}"></script>
    <script>
        $(document).ready(function() {

            $('input, select').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();

                    let inputs = $('input, select, textarea');
                    let index = inputs.index(this);

                    if (index + 1 < inputs.length) {
                        inputs.eq(index + 1).focus();
                    } else {
                        $('form').submit();
                    }
                }
            });

        });
    </script>
@endsection
