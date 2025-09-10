@extends('layouts.admin')
@section('title', 'Produk')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
@endsection

@section('content')
    <section class="section">
        <div class="section-header">
            <h1>Data Produk</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Data Produk</a></div>
                <div class="breadcrumb-item"><a href="#">index</a></div>
            </div>
            @can('roles-create')
                <div class="section-header-button ml-auto">
                    <a class="btn btn-primary" href="{{ route('adminitems.create') }}">
                        Tambah Produk
                    </a>
                </div>
            @endcan
        </div>

        <div class="section-body">
            <div class="collapse" id="advanced-search">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="form-row">
                                    <div class="form-group col-md-6 mb-0">
                                        <label for="name">Cari Nama</label>
                                        <input type="text" class="form-control" id="name" autocomplete="off">
                                    </div>
                                    <div class="form-group col-md-6 mb-0">
                                        <label for="email">Cari Email</label>
                                        <input type="text" class="form-control" id="email" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-right">
                                <button type="button" id="reset-form" class="btn btn-icon btn-sm btn-default"><i
                                        class="fas fa-fw fa-redo"></i> Reset</button>
                                <button type="button" id="search-form" class="btn btn-icon btn-sm btn-primary"><i
                                        class="fas fa-fw fa-search"></i> Cari</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">

                    <div class="card">
                        <div class="card-body">

                            <div class="table-responsive">
                                <table id="item-table" class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Belongs To</th>
                                            <th>Photo</th>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Price</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>
@endsection
@section('scripts')
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('templates/library/jquery-ui-dist/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('templates/js/page/modules-datatables.js') }}"></script>
    <script>
        $(function() {
            $('#item-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('adminitems.index') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'users',
                        name: 'users',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'item_photo',
                        name: 'item_photo',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'item_name',
                        name: 'item_name'
                    },
                    {
                        data: 'item_desc',
                        name: 'item_desc'
                    },
                    {
                        data: 'item_price',
                        name: 'item_price'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    }
                ]
            });
        });
        function delete_data(id) {
            var formUrl = $('#button-delete-' + id).data('route');

            swal({
                    title: 'Apakah Yakin?',
                    text: 'Data produk akan dihapus permanen!',
                    buttons: {
                        cancel: 'Batal',
                        confirm: {
                            text: "Hapus!",
                            closeModal: false,
                        }
                    },
                    dangerMode: true,
                    closeOnClickOutside: false
                })
                .then((willDelete) => {
                    if (willDelete) {
                        $.ajax({
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            type: 'POST',
                            url: formUrl,
                            dataType: 'JSON',
                            data: {
                                '_method': 'DELETE',
                                'id': id
                            },
                            success: function(res) {
                                swal.stopLoading();
                                swal.close();
                                if (res.status === true) {
                                    iziToast.success({
                                        title: 'Sukses!',
                                        message: res.message,
                                        position: 'topRight'
                                    });
                                    // reload DataTables if used
                                    if (typeof tableData !== 'undefined') {
                                        tableData.ajax.reload(null, false);
                                    } else {
                                        // fallback reload
                                        location.reload();
                                    }
                                }
                            },
                            error: function() {
                                swal.stopLoading();
                                swal.close();
                                iziToast.error({
                                    title: 'Gagal!',
                                    message: 'Gagal menghapus produk!',
                                    position: 'topRight'
                                });
                            }
                        });
                    }
                });
        }
    </script>
@endsection
