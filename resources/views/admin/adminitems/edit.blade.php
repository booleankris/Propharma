@extends('layouts.admin')

@section('title', 'Edit Item')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/select2/dist/css/select2.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/css/dropify.min.css"
        integrity="sha512-EZSUkJWTjzDlspOoPSpUFR0o0Xy7jdzW//6qhUkoZ9c4StFkVsp9fbbd0O06p9ELS3H486m4wmrCELjza4JEog=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
@endsection

@section('content')
    <section class="section">
        <div class="section-header">
            <div class="section-header-back">
                <a href="{{ route('adminitems.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
            </div>
            <h1>Edit Item</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Items</a></div>
                <div class="breadcrumb-item"><a href="#">Edit</a></div>
            </div>
        </div>

        <div class="section-body">
            <div class="row">
                <div class="col-12">

                    {!! Form::model($item, [
                        'method' => 'PUT',
                        'route' => ['adminitems.update', $item->id],
                        'enctype' => 'multipart/form-data',
                        'autocomplete' => 'off',
                        'class' => 'needs-validation',
                        'novalidate',
                    ]) !!}
                    <div class="card">
                        <div class="card-body">

                            @if (count($errors) > 0)
                                <div class="alert alert-danger">
                                    <strong>Whoops!</strong> Ada beberapa masalah dengan inputan Anda.<br><br>
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="form-group row">
                                <label for="item_name" class="col-sm-3 col-form-label">Item Name</label>
                                <div class="col-sm-9">
                                    {!! Form::text('item_name', null, ['id' => 'item_name', 'class' => 'form-control', 'required']) !!}
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="item_desc" class="col-sm-3 col-form-label">Description</label>
                                <div class="col-sm-9">
                                    {!! Form::textarea('item_desc', null, ['id' => 'item_desc', 'class' => 'form-control', 'rows' => 3, 'required']) !!}
                                </div>
                            </div>
                            <div class="form-group">
                                <label>User / UMKM</label>
                                {!! Form::select('user_id', $user_id->pluck('name', 'id'), $item->user_id ?? null, [
                                    'class' => 'form-control select2',
                                    'id' => 'user_id',
                                    'placeholder' => '-- Pilih User --',
                                ]) !!}
                            </div>
                            <div class="form-group row">
                                <label for="item_price" class="col-sm-3 col-form-label">Price</label>
                                <div class="col-sm-9">
                                    {!! Form::number('item_price', null, ['id' => 'item_price', 'class' => 'form-control', 'min' => 0, 'required']) !!}
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="image">Foto Produk</label>
                                <input type="file" name="image" id="image"
                                    data-default-file="{{ asset('uploads/items/' . $item->item_photo) }}" class="dropify"
                                    data-allowed-file-extensions="jpg jpeg png" data-max-file-size="5M">
                            </div>

                        </div>
                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-lg btn-primary">Update</button>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/js/dropify.min.js"
        integrity="sha512-8QFTrG0oeOiyWo/VM9Y8kgxdlCryqhIxVeRpWSezdRRAvarxVtwLnGroJgnVW9/XBRduxO/z1GblzPrMQoeuew=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script type="text/javascript">
        $(function() {

            @if ($message = Session::get('success'))
                toastr.success('{{ $message }}', 'Success');
            @endif
        });
    </script>
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
