@extends('layouts.app')

@section('title', 'Edit Patient')

@section('content')
    <section class="section">
        <div class="section-header">
            <h1>Edit Patient</h1>
        </div>

        <div class="section-body">
            <div class="card">
                <div class="card-body">

                    <form id="formSubmit" method="POST" action="{{ route('patients.update', $patient->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label>Name *</label>
                            <input type="text" name="name" class="form-control" value="{{ $patient->name }}" required>
                        </div>

                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" name="address" class="form-control" value="{{ $patient->address }}">
                        </div>

                        <div class="form-group">
                            <label>City</label>
                            <input type="text" name="city" class="form-control" value="{{ $patient->city }}">
                        </div>

                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ $patient->phone }}">
                        </div>

                        <div class="form-group">
                            <label>Birth</label>
                            <input type="text" name="birth" class="form-control" value="{{ $patient->birth }}">
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="1" {{ $patient->status == 1 ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ $patient->status == 0 ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">Update</button>
                        <a href="{{ route('patients.index') }}" class="btn btn-secondary">Back</a>

                    </form>

                </div>
            </div>
        </div>
    </section>
@endsection

@section('script')
    <script>
        $('#formSubmit').on('submit', function(e) {
            e.preventDefault();

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                success: function(res) {
                    alert(res.message);
                    window.location.href = "{{ route('patients.index') }}";
                }
            });
        });
    </script>
@endsection
