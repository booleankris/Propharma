@extends('layouts.admin')

@section('title', 'Detail Level Administrator')

@section('style')
@endsection

@section('content')
    <section class="section">
        <div class="section-header">
            <div class="section-header-back">
                <a href="{{ route('roles.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
            </div>
            <h1>Detail Level Administrator</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Level Administrator</a></div>
                <div class="breadcrumb-item"><a href="#">Detail</a></div>
            </div>
            <div class="section-header-button ml-auto">
                @can('roles-edit')
                    <a class="btn btn-primary" href="{{ route('roles.edit', $role->id) }}">Edit</a>
                @endcan
            </div>
        </div>

        <div class="section-body">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Level : {{ $role->name }}</h4>
                        </div>
                        <div class="card-body p-0">
                            @if(!empty($rolePermissions))
                                <ul class="list-group list-group-flush">
                                @foreach($rolePermissions as $v)
                                    <li class="list-group-item">
                                        <i class="fas fa-check mr-2"></i>
                                        {{ ucwords(str_replace('-', ' ', $v->name)) }}
                                    </li>
                                @endforeach
                                </ul>
                            @endif
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </section>
@endsection

@section('scripts')
@endsection
