@extends('layouts.app')

@section('title', 'Tindak Lanjut Penolakan')

@section('content')
<div class="max-w-[1800px] mx-auto px-4 sm:px-6 py-6">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600">Operasional</p>
            <h1 class="text-2xl font-bold text-slate-800">Tindak Lanjut Penolakan</h1>
            <p class="mt-1 text-sm text-slate-500">Catat sumber penolakan, ketersediaan barang, dan penyelesaian setiap item.</p>
        </div>
        <a href="{{ route('sales.reject') }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Buka Pencatatan Penolakan</a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[1800px] border-collapse text-left text-xs">
            <thead class="bg-blue-100 text-slate-700">
                <tr>
                    <th class="border border-slate-200 px-3 py-3">Tanggal</th>
                    <th class="border border-slate-200 px-3 py-3">No</th>
                    <th class="border border-slate-200 px-3 py-3">Kode Obat</th>
                    <th class="border border-slate-200 px-3 py-3 min-w-56">Nama Obat</th>
                    <th class="border border-slate-200 px-3 py-3">Satuan</th>
                    <th class="border border-slate-200 px-3 py-3">Jumlah Penolakan</th>
                    <th class="border border-slate-200 px-3 py-3">Alasan Penolakan</th>
                    <th class="border border-slate-200 px-3 py-3">Sumber</th>
                    <th class="border border-slate-200 px-3 py-3">Dokter</th>
                    <th class="border border-slate-200 px-3 py-3">Status Barang</th>
                    <th class="border border-slate-200 px-3 py-3">Persamaan</th>
                    <th class="border border-slate-200 px-3 py-3 min-w-48">Tindak Lanjut</th>
                    <th class="border border-slate-200 px-3 py-3 min-w-40">Keterangan</th>
                    <th class="border border-slate-200 px-3 py-3 min-w-40">Update</th>
                    <th class="border border-slate-200 px-3 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody class="text-slate-700">
                @forelse($rejects as $reject)
                    @php($formId = 'follow-up-' . $reject->id)
                    <tr class="align-top hover:bg-slate-50">
                        <td class="whitespace-nowrap border border-slate-200 px-3 py-3">{{ \Carbon\Carbon::parse($reject->date)->format('d/m/Y') }}</td>
                        <td class="border border-slate-200 px-3 py-3">{{ $reject->code }}</td>
                        <td class="border border-slate-200 px-3 py-3">{{ $reject->medicines?->code ?? '—' }}</td>
                        <td class="border border-slate-200 px-3 py-3 font-semibold">{{ $reject->medicines?->name ?? $reject->medicine_name ?? '—' }}</td>
                        <td class="border border-slate-200 px-3 py-3">{{ $reject->unit ?? $reject->medicines?->unit ?? '—' }}</td>
                        <td class="border border-slate-200 px-3 py-3 text-center">{{ $reject->quantity }}</td>
                        <td class="border border-slate-200 px-3 py-3">{{ $reject->reason }}</td>
                        <td class="border border-slate-200 p-2">
                            <select form="{{ $formId }}" name="source_type" class="w-28 rounded-lg border border-slate-300 px-2 py-2">
                                <option value="">Pilih</option><option value="Resep" @selected($reject->source_type === 'Resep')>Resep</option><option value="UPDS" @selected($reject->source_type === 'UPDS')>UPDS</option>
                            </select>
                        </td>
                        <td class="border border-slate-200 p-2"><input form="{{ $formId }}" name="doctor_name" value="{{ $reject->doctor_name }}" placeholder="Nama dokter" class="w-36 rounded-lg border border-slate-300 px-2 py-2"></td>
                        <td class="border border-slate-200 p-2"><input form="{{ $formId }}" name="stock_status" value="{{ $reject->stock_status }}" placeholder="Tidak stok / retur" class="w-36 rounded-lg border border-slate-300 px-2 py-2"></td>
                        <td class="border border-slate-200 p-2"><input form="{{ $formId }}" name="equivalent" value="{{ $reject->equivalent }}" placeholder="Obat pengganti" class="w-40 rounded-lg border border-slate-300 px-2 py-2"></td>
                        <td class="border border-slate-200 p-2"><textarea form="{{ $formId }}" name="follow_up" rows="2" class="w-48 rounded-lg border border-slate-300 px-2 py-2" placeholder="Contoh: pesan ke PBF">{{ $reject->follow_up }}</textarea></td>
                        <td class="border border-slate-200 p-2"><textarea form="{{ $formId }}" name="follow_up_note" rows="2" class="w-40 rounded-lg border border-slate-300 px-2 py-2" placeholder="UPDS / keterangan">{{ $reject->follow_up_note }}</textarea></td>
                        <td class="border border-slate-200 p-2"><textarea form="{{ $formId }}" name="follow_up_update" rows="2" class="w-40 rounded-lg border border-slate-300 px-2 py-2" placeholder="Hasil / tanggal update">{{ $reject->follow_up_update }}</textarea></td>
                        <td class="border border-slate-200 p-2">
                            <form id="{{ $formId }}" method="POST" action="{{ route('sales.reject.follow-up.update', $reject->id) }}">
                                @csrf @method('PUT')
                                <button class="whitespace-nowrap rounded-lg bg-blue-600 px-3 py-2 font-semibold text-white hover:bg-blue-700">Simpan</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="15" class="border border-slate-200 px-4 py-12 text-center text-slate-500">Belum ada data penolakan untuk apotek ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rejects->links() }}</div>
</div>
@endsection
