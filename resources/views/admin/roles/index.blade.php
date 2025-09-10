@extends('layouts.admin')

@section('title', 'Level Administrator')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
@endsection

@section('content')
    <section class="section">
        <div class="section-header">
            <h1>Level Administrator</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Level Administrator</a></div>
                <div class="breadcrumb-item"><a href="#">index</a></div>
            </div>
            <div class="section-header-button ml-auto">
                @can('roles-create')
                    <a class="btn btn-primary" href="{{ route('roles.create') }}">
                        Buat Level Administrator
                    </a>
                @endcan
            </div>
        </div>

        <div class="section-body">
            <div class="row">
                <div class="col-12">
        
                    <div class="card">
                        <div class="card-body p-0">
                            
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <tr>
                                        <th width="50px">No.</th>
                                        <th>Nama</th>
                                        <th width="30%">&nbsp;</th>
                                    </tr>
                                    @foreach ($roles as $key => $role)
                                    <tr>
                                        <td>{{ ++$i }}</td>
                                        <td>
                                            @if($role->fixed == 1)
                                                <div class="badge badge-primary">{{ ucfirst($role->name) }}</div>
                                            @else
                                                {{ ucfirst($role->name) }}
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-toolbar" role="toolbar">
                                                <div class="btn-group mr-2" role="group">
                                                    <a class="btn btn-outline-primary" href="{{ route('roles.show',$role->id) }}">Detail</a>
                                                    @can('roles-edit')
                                                        <a class="btn btn-primary" href="{{ route('roles.edit', $role->id) }}">Edit</a>
                                                    @endcan
                                                </div>
                                                @if($role->fixed == 0)
                                                    @can('roles-delete')
                                                        <div class="btn-group">
                                                            {!! Form::open(['method' => 'DELETE','route' => ['roles.destroy', $role->id], 'style' => 'display:inline', 'id' => 'delete-'. $role->id]) !!}
                                                            {!! Form::submit('Delete', ['class' => 'btn btn-danger', 'data-confirm'=> 'Perhatian!|Anda yakin ingin menghapus data ini?', 'data-confirm-yes'=>'document.getElementById(\'delete-'. $role->id .'\').submit()']) !!}
                                                            {!! Form::close() !!}
                                                        </div>
                                                    @endcan
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </table>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <nav class="d-inline-block">
                                {!! $roles->render() !!}
                            </nav>
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
        @if ($message = Session::get('success'))
            iziToast.success({
                title: 'Berhasil!',
                message: '{{ $message }}',
                position: 'topRight'
            });
        @elseif($message = Session::get('error'))
            iziToast.error({
                title: 'Gagal!',
                message: '{{ $message }}',
                position: 'topRight'
            });
        @endif
    </script>
@endsection
