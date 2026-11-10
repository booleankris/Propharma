<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\DoctorService;
use Illuminate\Http\Request;
use DataTables;

/**
 * Master Jasa
 *
 * Berisi daftar jasa / tindakan dokter (dengan atau tanpa obat).
 * Harga (price) boleh 0 untuk jasa yang tidak berbayar.
 */
class DoctorServicesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $services = DoctorService::select('id', 'name', 'price', 'created_at');

            if (!$request->has('order')) {
                $services->orderBy('created_at', 'ASC');
            }

            return DataTables::of($services)
                ->addIndexColumn()
                ->addColumn('price_label', function ($service) {
                    return $this->formatPrice($service->price);
                })
                ->addColumn('price_raw', function ($service) {
                    return (float) ($service->price ?? 0);
                })
                ->addColumn('action', function ($service) {
                    $btn = '<div style="display:flex; align-items:center; justify-content:center; gap:6px;">';

                    $btn .= '<button type="button" class="ui-icon-btn"'
                        . ' onclick="edit_data(' . $service->id . ')" title="Ubah">'
                        . '<svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">'
                        . '<path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />'
                        . '</svg>'
                        . '</button>';

                    $btn .= '<button type="button" class="ui-icon-btn ui-icon-btn--danger"'
                        . ' onclick="delete_data(' . $service->id . ')" title="Hapus">'
                        . '<svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">'
                        . '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />'
                        . '</svg>'
                        . '</button>';

                    $btn .= '</div>';

                    return $btn;
                })
                ->escapeColumns([])
                ->toJson();
        }

        return view('master.doctor-services.index');
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $service = DoctorService::create([
            'name'  => $validated['name'],
            'price' => $validated['price'],
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Jasa Berhasil Ditambahkan.',
                'data'    => $service,
            ]);
        }

        return redirect()->route('doctor-services.index')
            ->with('success', 'Jasa Berhasil Ditambahkan.');
    }

    public function edit($id)
    {
        return response()->json(DoctorService::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $service = DoctorService::findOrFail($id);
        $validated = $this->validatePayload($request);

        $service->update([
            'name'  => $validated['name'],
            'price' => $validated['price'],
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Jasa Berhasil Diubah.',
                'data'    => $service,
            ]);
        }

        return redirect()->route('doctor-services.index')
            ->with('success', 'Jasa Berhasil Diubah.');
    }

    public function destroy($id)
    {
        $service = DoctorService::findOrFail($id);
        $service->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Jasa Berhasil Dihapus.',
        ]);
    }

    /**
     * Nama wajib diisi, harga boleh kosong / 0 (jasa tidak berbayar).
     */
    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:150',
            'price' => 'nullable|numeric|min:0|max:9999999999999',
        ]);

        $validated['price'] = round((float) ($validated['price'] ?? 0), 2);

        return $validated;
    }

    private function formatPrice($price): string
    {
        $value = (float) ($price ?? 0);

        return $value > 0
            ? 'Rp ' . number_format($value, 0, ',', '.')
            : 'Rp 0 (Gratis)';
    }
}
