@extends('layouts.app')

@section('title', 'Edit Debitur')

@section('content')
    <section class="section">
        <div class="section-header">
            <h1>Edit Debitur</h1>
        </div>

        <div class="section-body">
            <form id="form-debtor">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-4">
                        <label>Kode</label>
                        <input type="text" class="form-control" value="{{ $debtor->code }}" readonly>
                    </div>

                    <div class="col-md-8">
                        <label>Nama Debitur <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ $debtor->name }}" required>
                    </div>

                    <div class="col-md-12 mt-3">
                        <label>Alamat</label>
                        <input type="text" name="address" class="form-control" value="{{ $debtor->address }}">
                    </div>

                    <div class="col-md-4 mt-3">
                        <label>Kota</label>
                        <input type="text" name="city" class="form-control" value="{{ $debtor->city }}">
                    </div>

                    <div class="col-md-4 mt-3">
                        <label>Telepon</label>
                        <input type="text" name="phone" class="form-control" value="{{ $debtor->phone }}">
                    </div>

                    <div class="col-md-4 mt-3">
                        <label>Kontak Person</label>
                        <input type="text" name="contact" class="form-control" value="{{ $debtor->contact }}">
                    </div>

                    <div class="col-md-6 mt-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="{{ $debtor->email }}">
                    </div>

                    <div class="col-md-6 mt-4">
                        <label>Status</label><br>
                        <label class="custom-switch">
                            <input type="checkbox" name="status" value="1" class="custom-switch-input"
                                {{ $debtor->status == 1 ? 'checked' : '' }}>
                            <span class="custom-switch-indicator"></span>
                            <span class="custom-switch-description">Active</span>
                        </label>
                    </div>

                    <div class="col-md-12 mt-4">
                        <button class="btn btn-primary" id="btn-update" type="submit">Update</button>
                        <a href="{{ route('debtors.index') }}" class="btn btn-secondary">Kembali</a>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection

@section('script')
    <script>
        $('#form-debtor').on('submit', function(e) {
            e.preventDefault();

            $.ajax({
                url: "{{ route('debtors.update', $debtor->id) }}",
                type: "POST",
                data: new FormData(this),
                processData: false,
                contentType: false,
                success: function(res) {
                    alert('Data berhasil diperbarui');
                    window.location.href = "{{ route('debtors.index') }}";
                },
                error: function(err) {
                    alert('Periksa kembali input data');
                }
            });
        });
    </script>
@endsection
