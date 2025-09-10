@extends('layouts.app')

@section('title', 'Buat Match')

@section('style')
@endsection

@section('content')
<section class="section">
    <div class="section-header">
        <div class="section-header-back">
            <a href="{{ route('matchday.matches.index' , [$matchDay->id]) }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
        </div>
        <h1>Buat Match</h1>
        <div class="section-header-breadcrumb">
            <div class="breadcrumb-item"><a href="#">MatchDay</a></div>
            <div class="breadcrumb-item active"><a href="#">Matches</a></div>
            <div class="breadcrumb-item"><a href="#">Create</a></div>
        </div>
    </div>

    <div class="section-body">
        <div class="row">
            <div class="col-12">
                
                {!! Form::open(['method'=>'POST', 'route' => ['matchday.matches.store', $matchDay->id], 'autocomplete'=>'off', 'class'=> 'needs-validation', 'novalidate'=> '', 'files' => true]) !!}
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

                        <div class="form-group row">
                            <label for="team_home" class="col-sm-3 col-form-label">
                                Team Home
                                <i class="text-danger">*</i>
                            </label>
                            <div class="col-sm-9">
                                {!! Form::select('team_home', $teams, null, array('id' => 'team_home', 'placeholder' => 'Masukkan Team Home', 'class' => 'form-control')) !!}
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="team_away" class="col-sm-3 col-form-label">
                                Team Away
                                <i class="text-danger">*</i>
                            </label>
                            <div class="col-sm-9">
                                {!! Form::select('team_away', $teams, null, array('id' => 'team_away', 'placeholder' => 'Masukkan Team Away', 'class' => 'form-control')) !!}
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="date" class="col-sm-3 col-form-label">
                                Waktu Pelaksanaan
                                <i class="text-danger">*</i>
                            </label>
                            <div class="col-sm-9">
                                {!! Form::time('time', null, array('id' => 'time', 'placeholder' => 'Waktu Pelaksanaan', 'class' => 'form-control', 'required')) !!}
                            </div>
                        </div>

                        

                        

                    </div>
                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-lg btn-primary">Simpan</button>
                    </div>
                </div>
                {!! Form::close() !!}

            </div>
        </div>
    </div>
</section>
@endsection
@section('scripts')

<script>
    document.addEventListener('DOMContentLoaded', function () {
        let homeSelect = document.getElementById('team_home');
        let awaySelect = document.getElementById('team_away');

        // Disable placeholder
        if (homeSelect) homeSelect.options[0].disabled = true;
        if (awaySelect) awaySelect.options[0].disabled = true;

        function disableSameOption() {
            let homeVal = homeSelect.value;

            // Enable all options first
            Array.from(awaySelect.options).forEach(opt => opt.disabled = false);

            // If a team is selected as home, disable it in away
            if (homeVal) {
                let optionToDisable = awaySelect.querySelector(`option[value="${homeVal}"]`);
                if (optionToDisable) optionToDisable.disabled = true;

                // Auto reset if selected away is same as home
                if (awaySelect.value === homeVal) {
                    awaySelect.value = '';
                }
            }
        }

        // Run when home team changes
        if (homeSelect && awaySelect) {
            homeSelect.addEventListener('change', disableSameOption);
        }

        // Optional: initial run
        disableSameOption();
    });
</script>

@endsection
