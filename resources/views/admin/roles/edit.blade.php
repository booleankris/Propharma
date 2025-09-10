@extends('layouts.admin')

@section('title', 'Edit Level Administrator')

@section('style')
@endsection

@section('content')
    <section class="section">
        <div class="section-header">
            <div class="section-header-back">
                <a href="{{ route('roles.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
            </div>
            <h1>Edit Level Administrator</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="#">Level Administrator</a></div>
                <div class="breadcrumb-item"><a href="#">Edit</a></div>
            </div>
        </div>

        <div class="section-body">

            {!! Form::model($role, ['method' => 'PATCH', 'route' => ['roles.update', $role->id], 'autocomplete'=>'off', 'class'=> 'needs-validation', 'novalidate'=> '']) !!}
            <div class="row">
                <div class="col-12">
        
                    <div class="card">
                        <div class="card-body">
        
                            @if (count($errors) > 0)
                                <div class="alert alert-danger">
                                    <strong>Whoops!</strong> Ada beberapa masalah dengan inputan/masukan Anda.<br><br>
                                    <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                    </ul>
                                </div>
                            @endif
        
                            <div class="form-group row mb-0">
                                <label for="name" class="col-sm-3 col-form-label">Nama Level</label>
                                <div class="col-sm-9">
                                    @if($role->fixed == 0)
                                    {!! Form::text('name', null, array('id' => 'name', 'placeholder' => 'Role Name', 'class' => 'form-control', 'required', 'autofocus')) !!}
                                    @else
                                    {!! Form::text('name', null, array('id' => 'name', 'class' => 'form-control-plaintext', 'required')) !!}
                                    @endif
                                </div>
                            </div>

                            <div class="form-group row mb-0">
                                <label class="col-sm-3 col-form-label">Level</label>
                                <div class="col-sm-9">
                                    <br/>
                                    @foreach($permission as $value)
                                        <label>{{ Form::checkbox('permission[]', $value->id, in_array($value->id, $rolePermissions) ? true : false, array('class' => 'name')) }}
                                        {{ $value->name }}</label>
                                    <br/>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
        
                </div>
            </div>
    
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-right">
                            <button type="submit" class="btn btn-lg btn-primary">Simpan</button>
                        </div>
                    </div>
                </div>
            </div>
            {!! Form::close() !!}
        
        </div>

    </section>
@endsection

@section('scripts')
@endsection
