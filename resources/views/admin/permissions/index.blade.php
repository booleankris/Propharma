@extends('layouts.admin')

@section('title', 'Hak Akses')

@section('content')
    <section class="section">
        <div class="section-header">
            <div class="section-header-back">
                <a href="{{ route('roles.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
            </div>
            <h1>Hak Akses</h1>
            <div class="section-header-button ml-auto">
                <button type="button" class="btn btn-primary" id="button-new-modal">
                    Buat Modul Baru
                </button>
            </div>
        </div>

        <div class="section-body">
            <div class="row sortable">
                @foreach($permission as $perms)
                <div class="col-12 col-md-6 col-lg-4 col-sortable" data-id="{{ $perms->id }}">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h4>{{ $perms->label }}</h4>
                            <div class="card-header-action">
                                <div class="dropdown">
                                    <a class="btn dropdown-toggle text-primary" data-toggle="dropdown">Opsi</a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <button type="button" class="dropdown-item d-flex justify-content-between button-edit-modal" data-id="{{ $perms->id }}">
                                            Edit
                                        </button>
                                        <button type="button" class="dropdown-item d-flex justify-content-between" data-confirm="Warning!|Do you want to delete this route?" data-confirm-yes="document.getElementById('delete-label-{{ $perms->id }}').submit()">
                                            Hapus
                                        </a>
                                    </div>
                                </div>
                                {!! Form::open(['method' => 'DELETE','route' => ['permissions.destroy', $perms->id], 'id' => 'delete-label-'. $perms->id, 'class' => 'hide']) !!}
                                {!! Form::close() !!}
                            </div>
                        </div>
                        <ul class="list-group list-group-flush">
                            @foreach($perms->permission as $value)
                                <li class="list-group-item">
                                    @if(preg_match('/(-(L|l)ist)$/', $value->name))
                                        <i class="mr-1 fas fa-fw fa-list"></i>
                                    @elseif(preg_match('/(-(C|c)reate)$/', $value->name))
                                        <i class="mr-1 fas fa-fw fa-folder-plus"></i>
                                    @elseif(preg_match('/(-(E|e)dit)$/', $value->name))
                                        <i class="mr-1 fas fa-fw fa-edit"></i>
                                    @elseif(preg_match('/(-(D|d)elete)$/', $value->name))
                                        <i class="mr-1 fas fa-fw fa-trash-alt"></i>
                                    @endif
                                    {{ ucwords(str_replace('-', ' ', $value->name)) }}
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="modal fade" id="form-edit-modal-{{ $perms->id }}" tabindex="-1" role="dialog" aria-labelledby="form-edit-modal-label-{{ $perms->id }}" aria-hidden="true">
                        {!! Form::model($perms, ['method'=>'PATCH', 'route' => ['permissions.update', $perms->id], 'autocomplete'=>'off', 'class'=> 'needs-validation', 'novalidate'=> '']) !!}
                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="form-edit-modal-label-{{ $perms->id }}">Edit Modul</h5>
                                    <button type="button" class="close close-edit-modal" data-dismiss="modal" aria-label="Close" data-id="{{ $perms->id }}">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label for="module">Modul Label</label>
                                        {!! Form::text('label', null, array('id' => 'module', 'placeholder' => 'Module Label', 'class' => 'form-control', 'required')) !!}
                                    </div>
                                    <div class="form-group">
                                        <label for="route">Route</label>
                                        {!! Form::text('route', null, array('id' => 'route', 'placeholder' => 'Route Name', 'class' => 'form-control', 'required')) !!}
                                    </div>
                                    <div class="form-group">
                                        <label>Route List</label>
                                        <div class="row">
                                            <div class="col-6">
                                                @php($routeCreate = Spatie\Permission\Models\Permission::where('name', $perms->route .'-create')->first())
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input" id="create-{{ $perms->id }}" name="create" {{ $routeCreate ? 'checked' : null }}>
                                                    <label class="custom-control-label" for="create-{{ $perms->id }}">Create</label>
                                                </div>
                                                @php($routeDelete = Spatie\Permission\Models\Permission::where('name', $perms->route .'-delete')->first())
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input" id="delete-{{ $perms->id }}" name="delete" {{ $routeDelete ? 'checked' : null }}>
                                                    <label class="custom-control-label" for="delete-{{ $perms->id }}">Delete</label>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                @php($routeEdit = Spatie\Permission\Models\Permission::where('name', $perms->route .'-edit')->first())
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input" id="edit-{{ $perms->id }}" name="edit" {{ $routeEdit ? 'checked' : null }}>
                                                    <label class="custom-control-label" for="edit-{{ $perms->id }}">Edit</label>
                                                </div>
                                                @php($routeList = Spatie\Permission\Models\Permission::where('name', $perms->route .'-list')->first())
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input" id="list-{{ $perms->id }}" name="list" {{ $routeList ? 'checked' : null }}>
                                                    <label class="custom-control-label" for="list-{{ $perms->id }}">List</label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-primary">Save</button>
                                    <button type="button" class="btn btn-secondary close-edit-modal" data-dismiss="modal" data-id="{{ $perms->id }}">Close</button>
                                </div>
                            </div>
                        </div>
                        {!! Form::close() !!}
                    </div>

                </div>
                @endforeach
            </div>
        </div>
    </section>

    <div class="modal fade" id="form-new-modal" tabindex="-1" role="dialog" aria-labelledby="form-new-modal-label" aria-hidden="true">
        {!! Form::open(['method'=>'POST', 'route' => 'permissions.store', 'autocomplete'=>'off', 'class'=> 'needs-validation', 'novalidate'=> '']) !!}
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="form-new-modal-label">Buat Modul Baru</h5>
                    <button type="button" class="close close-new-modal" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="module">Modul Label</label>
                        {!! Form::text('label', null, array('id' => 'module', 'placeholder' => 'Module Label', 'class' => 'form-control', 'required')) !!}
                    </div>
                    <div class="form-group">
                        <label for="route">Route</label>
                        {!! Form::text('route', null, array('id' => 'route', 'placeholder' => 'Route Name', 'class' => 'form-control', 'required')) !!}
                    </div>
                    <div class="form-group">
                        <label>Route List</label>
                        <div class="row">
                            <div class="col-6">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="create" name="create">
                                    <label class="custom-control-label" for="create">Create</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="delete" name="delete">
                                    <label class="custom-control-label" for="delete">Delete</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="edit" name="edit">
                                    <label class="custom-control-label" for="edit">Edit</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="list" name="list">
                                    <label class="custom-control-label" for="list">List</label>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-secondary close-new-modal" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
        {!! Form::close() !!}
    </div>

@endsection

@section('javascript')
<script src="{{ asset('templates/js/page/jquery.sortable.min.js') }}"></script>
<script type="text/javascript">
    var sortable;
    $(function(){
        @if ($message = Session::get('success'))
            toastr.success('{{ $message }}', 'Sukses');
        @elseif($message = Session::get('error'))
            toastr.error('{{ $message }}', 'Gagal');
        @endif

        sortable = $('.sortable').sortable({
            placeholderClass: 'col-12 col-md-6 col-lg-4'
        }).bind('sortupdate', function(event, ui){
            var moduleSort = [];
            $('.sortable>.col-sortable').each(function(i, item) {
                var moduleId = $(item).data('id');
                moduleSort.push({
                    id: moduleId,
                    position: i
                });
            });
            updateOrder(moduleSort);
        });

        $('#button-new-modal').click(function() {
            $('#form-new-modal').appendTo('body');
            $('#form-new-modal').modal('show');
        });
        $('.close-new-modal').click(function() {
            $('#form-new-modal').modal('hide');
        });

        $('.button-edit-modal').click(function() {
            let permsId = $(this).data('id');
            $('#form-edit-modal-'+ permsId).appendTo('body');
            $('#form-edit-modal-'+ permsId).modal('show');
        });
        $('.close-edit-modal').click(function() {
            let permsId = $(this).data('id');
            $('#form-edit-modal-'+ permsId).modal('hide');
        });
    });

    function updateOrder(data) {
        sortable.sortable('disable');
        $.ajax({
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            type: 'PATCH',
            url : '{{ route('permissions.sort-module') }}',
            dataType: 'JSON',
            data: {module: data},
            success: function(res)
            {
                if(res.status == true) {
                    toastr.success(res.message, 'Sukses');
                    sortable.sortable('enable');
                }
            },
            error: function (jqXHR, textStatus, errorThrown)
            {
                toastr.error('Error sort module. Please contact admin!', 'Error');
            }
        });
    }
</script>
@endsection