@extends('layouts.admin')

@section('title', 'Tambah Produk')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/css/dropify.min.css"
        integrity="sha512-EZSUkJWTjzDlspOoPSpUFR0o0Xy7jdzW//6qhUkoZ9c4StFkVsp9fbbd0O06p9ELS3H486m4wmrCELjza4JEog=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endsection

@section('content')
    <section class="section">
        <div class="section-header">
            <h1>Tambah Produk</h1>
        </div>

        <div class="section-body">
            {!! Form::open([
                'route' => 'adminitems.store',
                'files' => true,
                'autocomplete' => 'off',
                'class' => 'needs-validation',
                'novalidate' => '',
            ]) !!}
            <div class="card">
                <div class="card-body">

                    <div class="form-group">
                        <label for="item_name">Nama Produk</label>
                        {!! Form::text('item_name', null, ['class' => 'form-control', 'required']) !!}
                    </div>

                    <div class="form-group">
                        <label for="item_desc">Deskripsi</label>
                        {!! Form::textarea('item_desc', null, ['class' => 'form-control', 'required']) !!}
                    </div>
                    <div class="form-group">
                        <label> User / UMKM</label>
                        {!! Form::select('user_id', $user_id->pluck('name', 'id'), null, [
                            'class' => 'form-control select2',
                            'id' => 'user_id',
                            'placeholder' => '-- Pilih User --',
                        ]) !!}

                    </div>
                    <div class="form-group">
                        <label for="item_price">Harga</label>
                        {!! Form::number('item_price', null, ['class' => 'form-control', 'required']) !!}
                    </div>

                    <div class="form-group">
                        <label for="image">Foto Produk</label>
                        <input type="file" name="image" id="image" class="dropify"
                            data-allowed-file-extensions="jpg jpeg png" data-max-file-size="5M">
                    </div>

                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </div>
            {!! Form::close() !!}
        </div>
    </section>
@endsection

@section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/js/dropify.min.js"
        integrity="sha512-8QFTrG0oeOiyWo/VM9Y8kgxdlCryqhIxVeRpWSezdRRAvarxVtwLnGroJgnVW9/XBRduxO/z1GblzPrMQoeuew=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: "Pilih user",
                allowClear: true
            });
        });
    </script>
    <script type="text/javascript">
        $(function() {
            $('.dropify').dropify();
        });
    </script>
@endsection
