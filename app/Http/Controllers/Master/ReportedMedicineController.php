<?php

namespace App\Http\Controllers\Master;

use App\Exports\Report\ReportedMedicinesExport;
use App\Http\Controllers\Controller;
use App\Models\Medicines;
use App\Models\Pharmacies;
use App\Models\ReportedMedicine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class ReportedMedicineController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:HO|administrator']);
    }

    /**
     * Tampilkan halaman utama & respon DataTables
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = ReportedMedicine::with(['medicine.category', 'medicine.factory', 'user'])
                ->select('reported_medicines.*');

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('code', function ($row) {
                    return $row->medicine ? ($row->medicine->code ?: '-') : '-';
                })
                ->addColumn('barcode', function ($row) {
                    return $row->medicine ? ($row->medicine->barcode ?: '-') : '-';
                })
                ->addColumn('name', function ($row) {
                    return $row->medicine ? $row->medicine->name : '<span class="text-danger">Obat Dihapus</span>';
                })
                ->addColumn('category', function ($row) {
                    return $row->medicine?->category ? $row->medicine->category->name : '-';
                })
                ->addColumn('unit', function ($row) {
                    return $row->medicine ? ($row->medicine->unit ?: '-') : '-';
                })
                ->addColumn('stock', function ($row) {
                    $stock = $row->medicine ? (int)$row->medicine->stock : 0;
                    return '<span style="display:inline-flex; align-items:center; padding:2px 8px; border-radius:9999px; font-weight:700; font-size:11px; background-color:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe;">' . number_format($stock) . '</span>';
                })
                ->addColumn('added_by', function ($row) {
                    return $row->user ? $row->user->name : 'Sistem';
                })
                ->addColumn('notes', function ($row) {
                    return $row->notes ?: '<span style="color:#9ca3af; font-style:italic;">-</span>';
                })
                ->addColumn('action', function ($row) {
                    $medName = htmlspecialchars($row->medicine?->name ?? 'Obat', ENT_QUOTES);
                    $notes = htmlspecialchars($row->notes ?? '', ENT_QUOTES);
                    return '<div style="display:flex; align-items:center; justify-content:center; gap:6px;">
                                <button type="button" class="btn-action-edit" onclick="openEditModal(' . $row->id . ', \'' . $medName . '\', \'' . $notes . '\')">
                                    <svg style="width:13px; height:13px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    <span>Edit</span>
                                </button>
                                <button type="button" class="btn-action-delete" onclick="deleteReportedMedicine(' . $row->id . ', \'' . $medName . '\')">
                                    <svg style="width:13px; height:13px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    <span>Hapus</span>
                                </button>
                            </div>';
                })
                ->rawColumns(['name', 'stock', 'notes', 'action'])
                ->toJson();
        }

        $pharmacies = Pharmacies::whereNotIn('id', [6, 8])->orderBy('name', 'asc')->get();
        $totalReported = ReportedMedicine::count();

        return view('master.reported-medicines.index', compact('pharmacies', 'totalReported'));
    }

    /**
     * Endpoint Select2 pencarian master obat
     */
    public function searchMedicines(Request $request)
    {
        $q = trim((string)$request->q);

        // Ambil ID obat yang sudah masuk daftar pelaporan agar tidak dipilih ganda
        $alreadyReportedIds = ReportedMedicine::pluck('medicine_id')->toArray();

        $query = Medicines::query()
            ->where('status', 1)
            ->whereNotIn('id', $alreadyReportedIds);

        if (!empty($q)) {
            $query->where(function ($b) use ($q) {
                $b->where('name', 'like', "%{$q}%")
                  ->orWhere('code', 'like', "%{$q}%")
                  ->orWhere('barcode', 'like', "%{$q}%");
            });
        }

        $items = $query->orderBy('name', 'asc')->take(40)->get(['id', 'code', 'name', 'unit', 'stock']);

        $results = [];
        foreach ($items as $item) {
            $results[] = [
                'id'    => $item->id,
                'text'  => $item->name . ' (' . ($item->code ?: 'No Code') . ')' . ($item->unit ? ' - ' . $item->unit : '') . ' [Stok: ' . $item->stock . ']',
                'stock' => $item->stock,
                'code'  => $item->code,
            ];
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Tambahkan obat ke daftar wajib lapor
     */
    public function store(Request $request)
    {
        $request->validate([
            'medicine_id' => 'required|exists:medicines,id',
            'notes'       => 'nullable|string|max:255',
        ], [
            'medicine_id.required' => 'Silakan pilih obat yang ingin ditambahkan.',
            'medicine_id.exists'   => 'Obat yang dipilih tidak valid.',
        ]);

        $exists = ReportedMedicine::where('medicine_id', $request->medicine_id)->exists();
        if ($exists) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Obat ini sudah terdaftar dalam daftar pelaporan obat.',
            ], 422);
        }

        ReportedMedicine::create([
            'medicine_id' => $request->medicine_id,
            'user_id'     => auth()->id(),
            'notes'       => $request->notes,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Obat berhasil ditambahkan ke daftar wajib lapor.',
        ]);
    }

    /**
     * Update catatan / data pelaporan obat
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'notes' => 'nullable|string|max:255',
        ]);

        $item = ReportedMedicine::findOrFail($id);
        $item->update([
            'notes' => $request->notes,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Catatan pelaporan obat berhasil diperbarui.',
        ]);
    }

    /**
     * Hapus obat dari daftar wajib lapor
     */
    public function destroy($id)
    {
        $item = ReportedMedicine::findOrFail($id);
        $item->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Obat berhasil dihapus dari daftar wajib lapor.',
        ]);
    }

    /**
     * Export Excel Pelaporan Obat (Multi-Sheet per Obat)
     */
    public function export(Request $request)
    {
        $month = (int)$request->input('month', date('n'));
        $year  = (int)$request->input('year', date('Y'));
        $pharmacyId = $request->filled('pharmacy_id') ? (int)$request->pharmacy_id : null;

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate   = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $monthName = $monthNames[$month] ?? 'Bulan_' . $month;

        $pharmacyPart = 'Semua_Cabang';
        if ($pharmacyId) {
            $pharmacy = Pharmacies::find($pharmacyId);
            if ($pharmacy) {
                $pharmacyPart = preg_replace('/[^A-Za-z0-9_]/', '_', $pharmacy->name);
            }
        }

        $fileName = "Pelaporan_Obat_{$monthName}_{$year}_{$pharmacyPart}.xlsx";

        return Excel::download(new ReportedMedicinesExport($startDate, $endDate, $pharmacyId), $fileName);
    }
}
