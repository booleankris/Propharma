@extends('layouts.app')

@section('title', 'Slider')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
@endsection

@section('content')
    <section class="section">
        <div class="section-header">
            <h1>Slider</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Slider</a></div>
                <div class="breadcrumb-item"><a href="#">index</a></div>
            </div>
            {{-- @can('sliders-create') --}}
                <div class="section-header-button ml-auto">
                    <a class="btn btn-primary" href="{{ route('sliders.create') }}">
                        Buat Slider
                    </a>
                </div>
            {{-- @endcan --}}
        </div>

        <div class="section-body">
        
            <div class="row">
                <div class="col-12">
        
                    <div class="card">
                        <div class="card-body">

                            <div class="table-responsive">
                                <table class="table table-striped" id="table-data">
                                    <thead>
                                        <tr>  
                                            <th width="5%">#</th>
                                            <th width="40%">Gambar</th>
                                            <th>Keterangan</th>
                                            <th width="20%">&nbsp;</th>
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
    <!-- JS Libraies -->
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('templates/library/jquery-ui-dist/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('templates/js/page/modules-datatables.js') }}"></script>

    <script type="text/javascript">
    var tableData;
    $(function() {
        @if ($message = Session::get('success'))
            iziToast.success({
                title: 'Berhasil!',
                message: '{{ $message }}',
                position: 'topRight'
            });
        @endif

        tableData = $('#table-data').DataTable({
            dom: "<'row align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 search-button-collapse'>><'row'<'col-sm-12'tr>><'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            processing: true,
            serverSide: true,
            pageLength: 10,
            ajax: {
                url: '{{ route('sliders.index') }}',
            },
            order: [],
            columns: [
                { data: 'DT_RowIndex', orderable: false, searchable: false},
                { data: 'preview', name: 'preview', orderable: false, searchable: false},
                { data: 'ket', name: 'ket', orderable: false, searchable: false},
                { data: 'action', orderable: false, searchable: false}
            ],
        });
    });

    function delete_data(id)
    {
        var formUrl = $('#button-delete-'+ id).data('route');
        swal({
            title: 'Apakah Yakin?',
            text: 'Menghapus Data ini?',
            buttons: {
                cancel: true,
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
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    type: 'POST',
                    url : formUrl,
                    dataType: 'JSON',
                    data: {
                        '_token': '{{ csrf_token() }}',
                        '_method': 'DELETE',
                        'id': id, 
                    },
                    success: function(res)
                    {
                        swal.stopLoading();
                        swal.close();
                        if(res.status == true)
                        {
                            iziToast.success({
                                title: 'Berhasil!',
                                message: res.message,
                                position: 'topRight'
                            });
                            tableData.ajax.reload(null,false);
                        }
                    },
                    error: function (jqXHR, textStatus, errorThrown)
                    {
                        swal.stopLoading();
                        swal.close();
                        iziToast.error({
                            title: 'Gagal!!!',
                            message: 'Gagal menghapus data!',
                            position: 'topRight'
                        });
                    }
                });
            }
        });
    }
    </script>
@endsection
