@extends('layouts.admin')

@section('title', 'Data Apotek')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
@endsection

@section('content')
    <section class="section">
        <div class="section-header">
            <h1>Data Apotek</h1>

            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Apotek</a></div>
                <div class="breadcrumb-item">Index</div>
            </div>

            <div class="section-header-button ml-auto">
                <a href="{{ route('pharmacies.create') }}" class="btn btn-primary">
                    Tambah Apotek
                </a>
            </div>
        </div>

        <div class="section-body">

            <!-- SEARCH -->
            <div class="collapse" id="advanced-search">
                <div class="card">
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Nama</label>
                                <input type="text" id="name" class="form-control">
                            </div>
                            <div class="form-group col-md-4">
                                <label>Kota</label>
                                <input type="text" id="city" class="form-control">
                            </div>
                            <div class="form-group col-md-4">
                                <label>Telepon</label>
                                <input type="text" id="phone" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="card-footer text-right">
                        <button id="reset-form" class="btn btn-secondary btn-sm">Reset</button>
                        <button id="search-form" class="btn btn-primary btn-sm">Cari</button>
                    </div>
                </div>
            </div>

            <!-- TABLE -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="table-data">
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th>Nama</th>
                                    <th>Kota</th>
                                    <th>Telepon</th>
                                    <th>Status</th>
                                    <th width="20%">Aksi</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>
@endsection

@section('scripts')
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('templates/library/jquery-ui-dist/jquery-ui.min.js') }}"></script>

    <script>
        let table;

        $(function() {

            @if ($message = Session::get('success'))
                iziToast.success({
                    title: 'Berhasil!',
                    message: '{{ $message }}',
                    position: 'topRight'
                });
            @endif

            table = $('#table-data').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('pharmacies.index') }}",
                    data: function(d) {
                        d.name = $('#name').val();
                        d.city = $('#city').val();
                        d.phone = $('#phone').val();
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'name'
                    },
                    {
                        data: 'city'
                    },
                    {
                        data: 'phone'
                    },
                    {
                        data: 'status',
                    },
                    {
                        data: 'action',
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            // SEARCH BUTTON
            $('#search-form').click(function(e) {
                e.preventDefault();
                table.draw();
            });

            // RESET BUTTON
            $('#reset-form').click(function(e) {
                e.preventDefault();
                $('#name').val('');
                $('#city').val('');
                $('#phone').val('');
                table.draw();
            });

        });

        function delete_data(id) {
            let url = "/pharmacies/" + id;

            swal({
                    title: "Yakin?",
                    text: "Data akan dihapus!",
                    icon: "warning",
                    buttons: true,
                    dangerMode: true,
                })
                .then((willDelete) => {
                    if (willDelete) {
                        $.ajax({
                            url: url,
                            type: "POST",
                            data: {
                                _method: "DELETE",
                                _token: "{{ csrf_token() }}"
                            },
                            success: function(res) {
                                iziToast.success({
                                    title: 'Berhasil!',
                                    message: res.message,
                                    position: 'topRight'
                                });
                                table.ajax.reload();
                            },
                            error: function() {
                                iziToast.error({
                                    title: 'Error!',
                                    message: 'Gagal hapus data',
                                    position: 'topRight'
                                });
                            }
                        });
                    }
                });
        }
    </script>
@endsection
