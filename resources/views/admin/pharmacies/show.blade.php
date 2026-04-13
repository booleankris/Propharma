@extends('layouts.admin')

@section('title', 'Detail Administrator')

@section('style')
@endsection

@section('content')
    <section class="section">
        <div class="section-header">
            <div class="section-header-back">
                <a href="{{ route('users.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
            </div>
            <h1>Administrator</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Administrator</a></div>
                <div class="breadcrumb-item"><a href="#">Detail</a></div>
            </div>
            <div class="section-header-button ml-auto">
                @can('users-edit')
                    @if(Auth::user()->id == $user->id || $user->is_fixed == 0)
                        <a class="btn btn-primary" href="{{ route('users.edit', $user->id) }}">
                            Edit
                        </a>
                    @endif
                @endcan
            </div>
        </div>

        <div class="section-body">
            <div class="row mt-sm-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
        
                            <ul class="list-unstyled list-unstyled-border mt-4">
                                <li class="media">
                                    <div class="media-icon">
                                        <i class="ion ion-ios-circle-outline"></i>
                                    </div>
                                    <div class="media-body">
                                        <h6>Nama Lengkap</h6>
                                        <p>{{ $user->name }}</p>
                                    </div>
                                </li>
        
                                <li class="media">
                                    <div class="media-icon">
                                        <i class="ion ion-ios-circle-outline"></i>
                                    </div>
                                    <div class="media-body">
                                        <h6>Email</h6>
                                        <p>{{ $user->email }}</p>
                                    </div>
                                </li>
        
                                <li class="media">
                                    <div class="media-icon">
                                        <i class="ion ion-ios-circle-outline"></i>
                                    </div>
                                    <div class="media-body">
                                        <h6>Level</h6>
                                        @if(!empty($user->getRoleNames()))
                                            @foreach($user->getRoleNames() as $role)
                                                <label class="badge badge-primary">{{ ucwords($role) }}</label>
                                            @endforeach
                                        @endif
                                    </div>
                                </li>
                            </ul>
                        
                        </div>
                    </div>
        
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
@endsection
