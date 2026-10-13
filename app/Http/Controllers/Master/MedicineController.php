<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Creditor;
use App\Models\MedicineCreditor;
use App\Models\MedicinePriceHistory;
use App\Models\Medicines;
use App\Services\MedicineImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class MedicineController extends Controller
{
    /**
     * Display list for DataTables
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $statusFilter = $request->input('status_filter', '1');

            $data = Medicines::with([
                'composition',
                'category',
                'factory',
                'creditor',
                'etalases',
                'locations',
            ])
                ->select('medicines.*')
                ->when($statusFilter !== 'all', function ($q) use ($statusFilter) {
                    $q->where('status', (int) $statusFilter);
                })
                ->orderBy('id', 'DESC');

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('composition_name', fn($row) => $row->composition?->name)
                ->addColumn('category_name',    fn($row) => $row->category?->name)
                ->addColumn('factory_name',     fn($row) => $row->factory?->name)
                ->addColumn('location_name',    fn($row) => $row->locations?->name)
                ->addColumn('etalase_name',     fn($row) => $row->etalases?->name)
                ->addColumn('creditor_name',    fn($row) => $row->creditor?->name)
                ->addColumn('status_label', function ($row) {
                    return $row->status
                        ? '<span class="badge-active">Active</span>'
                        : '<span class="badge-inactive">Inactive</span>';
                })
                ->filter(function ($query) use ($request) {
                    $search = $request->input('search.value');
                    if (!empty($search)) {
                        $search = trim($search);
                        $tokens = array_values(array_filter(explode(' ', $search)));

                        $query->where(function ($q) use ($tokens) {
                            foreach ($tokens as $token) {
                                $q->where(function ($tq) use ($token) {
                                    $tq->where('medicines.name', 'like', "%{$token}%")
                                        ->orWhere('medicines.code', 'like', "%{$token}%")
                                        ->orWhere('medicines.barcode', 'like', "%{$token}%")
                                        ->orWhere('medicines.dosage', 'like', "%{$token}%")
                                        ->orWhere('medicines.generic', 'like', "%{$token}%")
                                        ->orWhereHas('composition', fn($cq) => $cq->where('name', 'like', "%{$token}%"))
                                        ->orWhereHas('factory', fn($fq) => $fq->where('name', 'like', "%{$token}%"))
                                        ->orWhereHas('category', fn($catq) => $catq->where('name', 'like', "%{$token}%"));
                                });
                            }
                        });
                    }
                })
                ->rawColumns(['status_label'])
                ->make(true);
        }

        $creditors = Creditor::orderBy('name')->get(['id', 'code', 'name']);
        return view('master.medicines.index', compact('creditors'));
    }

    /**
     * Store Medicine
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'                 => 'nullable|string|max:255',
            'generic'              => 'nullable|string|max:255',
            'pharmacy_id'          => 'required|integer',
            'medicine_category_id' => 'required|integer',
            'composition_id'       => 'required|integer',
            'component'            => 'nullable|string|max:255',
            'factory_id'           => 'required|integer',
            'name'                 => 'required|string|max:255',
            'packaging'            => 'nullable|string|max:255',
            'unit'                 => 'nullable|string|max:100',
            'content'              => $request->has('is_active') ? 'required|numeric|min:1' : 'nullable|string|max:255',
            'dosage'               => 'nullable|string|max:255',
            'strip'               => 'nullable|string|max:255',
            'raw_price'            => 'nullable|numeric',
            'pharmacy_net_price'   => 'nullable|numeric',
            'net_price'            => 'nullable|numeric',
            'het_price'            => 'nullable|numeric',
            'minimal_stock'        => 'nullable|numeric',
            'stock'                => 'nullable|numeric',
            'psychotropic'         => 'nullable|boolean',
            'preparations'         => 'nullable|string|max:255',
            'whole'                => 'nullable|boolean',
            'precursor'            => 'nullable|boolean',
            'receipt'              => 'nullable|boolean',
            'type'                 => 'required|string|max:255',
            'status'               => 'nullable|boolean',
        ]);

        $priceMultiplier = $request->boolean('is_active') ? max((float) $request->input('content', 1), 1) : 1;
        $enteredHna = (float) $request->input('pharmacy_net_price', 0);
        $unitHna = $enteredHna / $priceMultiplier;
        $unitNetPrice = floor($unitHna * 1.11);

        // Utuh stores the selected pack content; otherwise the medicine is priced per unit.
        $contentValue = $request->has('is_active') ? ($request->input('content') ?: null) : 1;

        // Insert the new medicine record
        $insert = Medicines::create([
            'code'                 => $request->filled('code') ? $request->code : Medicines::generateCode(),
            'barcode'              => $request->barcode,
            'generic'              => $request->generic,
            'pharmacy_id'          => $request->pharmacy_id,
            'medicine_category_id' => $request->medicine_category_id,
            'composition_id'       => $request->composition_id,
            'component'            => $request->component,
            'factory_id'           => $request->factory_id,
            'creditors_id'         => null,
            'name'                 => $request->name,
            'packaging'            => $request->packaging,
            'unit'                 => $request->unit,
            'content'              => $contentValue,  // Store content value (either 1 or user input)
            'dosage'               => $request->dosage,
            'strip'                => $request->strip,
            'raw_price'            => $unitHna,
            'pharmacy_net_price'   => $unitHna,
            'net_price'            => $unitNetPrice,
            'het_price'            => $request->het_price ?? 0,
            'minimal_stock'        => $request->minimal_stock ?? 0,
            'stock'                => $request->stock ?? 0,
            'psychotropic'         => $request->boolean('psychotropic') ? 1 : 0,
            'preparations'         => $request->preparations,
            'whole'                => $request->boolean('is_active') ? 1 : 0,
            'precursor'            => $request->boolean('precursor') ? 1 : 0,
            'receipt'              => $request->boolean('receipt') ? 1 : 0,
            'etalase'              => $request->input('etalase'),
            'location'             => $request->input('location'),
            'type'                 => $request->type,
            'status'               => $request->has('status') ? (int) $request->status : 1,
        ]);

        $creditorPayload = json_decode($request->get('creditor_ids', '[]'), true) ?: [];
        foreach ($creditorPayload as $row) {
            if (empty($row['code'])) continue;
            MedicineCreditor::create([
                'medicine_id'   => $insert->id,
                'creditor_code' => trim($row['code']),
                'discount'      => $row['discount'] ?? 0,
            ]);
        }

        return response()->json(['message' => 'Obat Berhasil Ditambahkan']);
    }

    /**
     * Update Medicine
     */
    public function update(Request $request, $id)
    {
        // Validation rules
        $request->validate([
            'code'                 => 'nullable|string|max:255',
            'generic'              => 'nullable|string|max:255',
            'pharmacy_id'          => 'required|integer',
            'medicine_category_id' => 'required|integer',
            'composition_id'       => 'required|integer',
            'component'            => 'nullable|string|max:255',
            'name'                 => 'required|string|max:255',
            'packaging'            => 'nullable|string|max:255',
            'unit'                 => 'nullable|string|max:100',
            'content'              => $request->has('is_active') ? 'required|numeric|min:1' : 'nullable|string|max:255',
            'dosage'               => 'nullable|string|max:255',
            'strip'                => 'nullable|string|max:255',
            'raw_price'            => 'nullable|numeric',
            'pharmacy_net_price'   => 'nullable|numeric',
            'net_price'            => 'nullable|numeric',
            'het_price'            => 'nullable|numeric',
            'minimal_stock'        => 'nullable|numeric',
            'stock'                => 'nullable|numeric',
            'psychotropic'         => 'nullable|boolean',
            'preparations'         => 'nullable|string|max:255',
            'whole'                => 'nullable|boolean',
            'precursor'            => 'nullable|boolean',
            'receipt'              => 'nullable|boolean',
            'type'                 => 'required|string|max:255',
            'status'               => 'nullable|boolean',
        ]);

        // Find the existing medicine record
        $medicine = Medicines::findOrFail($id);

        // Utuh stores the selected pack content; otherwise the medicine is priced per unit.
        $contentValue = $request->has('is_active') ? ($request->input('content') ?: null) : 1;
        $oldPrice = $medicine->pharmacy_net_price;
        $priceMultiplier = $request->boolean('is_active') ? max((float) $request->input('content', 1), 1) : 1;
        $enteredHna = (float) $request->input('pharmacy_net_price', 0);
        $unitHna = $enteredHna / $priceMultiplier;
        $unitNetPrice = floor($unitHna * 1.11);

        // Update the medicine record
        $medicine->update([
            'code'                 => $request->filled('code') ? $request->code : $medicine->code,
            'barcode'              => $request->barcode,
            'generic'              => $request->generic,
            'pharmacy_id'          => $request->pharmacy_id,
            'medicine_category_id' => $request->medicine_category_id,
            'composition_id'       => $request->composition_id,
            'component'            => $request->component,
            'factory_id'           => $request->factory_id,
            'creditors_id'         => null,
            'name'                 => $request->name,
            'packaging'            => $request->packaging,
            'unit'                 => $request->unit,
            'content'              => $contentValue,  // Store the content value (either 1 or user input)
            'dosage'               => $request->dosage,
            'strip'                => $request->strip,
            'raw_price'            => $unitHna,
            'pharmacy_net_price'   => $unitHna,
            'net_price'            => $unitNetPrice,
            'het_price'            => $request->het_price ?? 0,
            'minimal_stock'        => $request->minimal_stock ?? 0,
            'stock'                => $request->stock ?? 0,
            'psychotropic'         => $request->boolean('psychotropic') ? 1 : 0,
            'preparations'         => $request->preparations,
            'whole'                => $request->boolean('is_active') ? 1 : 0,
            'precursor'            => $request->boolean('precursor') ? 1 : 0,
            'receipt'              => $request->boolean('receipt') ? 1 : 0,
            'etalase'              => $request->input('etalase'),
            'location'             => $request->input('location'),
            'type'                 => $request->type,
            'status'               => $request->has('status') ? (int) $request->status : 1,
        ]);
        // Add Medicine Price History

        $history = MedicinePriceHistory::create([
            'user_id'      => auth()->user()->id,
            'medicine_id'  => $id,
            'old_price'    => $oldPrice,
            'new_price'    => $unitHna
        ]);

        // Sync creditors (if applicable)
        $creditorPayload = json_decode($request->get('creditor_ids', '[]'), true) ?: [];
        $syncData = [];
        foreach ($creditorPayload as $row) {
            if (empty($row['code'])) continue;
            $syncData[trim($row['code'])] = ['discount' => $row['discount'] ?? 0];
        }
        $medicine->creditors()->sync($syncData);

        return response()->json(['message' => 'Obat Berhasil Di-Update.']);
    }

    /**
     * Soft Delete (Deactivate)
     */
    public function destroy($id)
    {
        Medicines::findOrFail($id)->update(['status' => 0]);

        return response()->json(['message' => 'Obat Berhasil Dinonaktifkan']);
    }

    /**
     * Restore (Activate)
     */
    public function restore($id)
    {
        Medicines::findOrFail($id)->update(['status' => 1]);

        return response()->json(['message' => 'Obat Berhasil Diaktifkan Kembali']);
    }

    /**
     * Return medicine + its creditors for the edit form
     */
    public function editCreditor($id)
    {
        $medicine = Medicines::with('creditors')->findOrFail($id);

        return response()->json([
            'medicine'  => $medicine,
            'creditors' => $medicine->creditors->map(fn($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'code'     => $c->code,
                'discount' => $c->pivot->discount ?? 0,
            ]),
        ]);
    }

    // Orders Page - Modifying Creditors on order page

    public function syncCreditors(Request $request, $id)
    {
        $request->validate([
            'creditors'            => 'array',
            'creditors.*.code'     => 'exists:creditors,code',
            'creditors.*.discount' => 'nullable|numeric|min:0|max:100',
        ]);

        Medicines::findOrFail($id);

        MedicineCreditor::where('medicine_id', $id)->delete();

        foreach ($request->creditors ?? [] as $row) {
            MedicineCreditor::create([
                'medicine_id'   => $id,
                'creditor_code' => $row['code'],
                'discount'      => $row['discount'] ?? 0,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function getAll()
    {
        $creditors = Creditor::where('status', 1)
            ->select('code', 'name')
            ->orderBy('name')
            ->get();

        return response()->json(['creditors' => $creditors]);
    }

    /**
     * Import medicines master from Excel file.
     */
    public function import(Request $request, MedicineImportService $service)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:102400',
        ], [
            'file.required' => 'File Excel wajib dipilih.',
            'file.mimes'    => 'Format file harus berupa .xlsx, .xls, atau .csv.',
            'file.max'      => 'Ukuran file melebihi batas maksimal (100MB).',
        ]);

        $file = $request->file('file');
        foreach ($request->session()->get('medicine_import_previews', []) as $oldPreviewPath) {
            Storage::disk('local')->delete($oldPreviewPath);
        }
        $request->session()->forget('medicine_import_previews');

        $token = Str::random(48);
        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $storedPath = $file->storeAs('medicine-import-previews', "{$token}.{$extension}", 'local');

        try {
            $summary = $service->preview(Storage::disk('local')->path($storedPath));
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($storedPath);
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'File tidak dapat dianalisis. Pastikan format dan susunan kolom Excel sesuai panduan.',
            ], 422);
        }

        if ($summary['total_rows'] === 0) {
            Storage::disk('local')->delete($storedPath);
            return response()->json([
                'success' => false,
                'message' => 'Tidak ditemukan baris data obat pada file (kolom Nama Obat mulai baris 2).',
            ], 422);
        }

        $request->session()->put("medicine_import_previews.{$token}", $storedPath);

        return response()->json([
            'success' => true,
            'preview_token' => $token,
            'summary' => $summary,
            'message' => 'Ringkasan file siap ditinjau sebelum import.',
        ]);
    }

    public function executeImport(Request $request, MedicineImportService $service)
    {
        $request->validate([
            'preview_token' => 'required|string|size:48',
        ]);

        $token = $request->input('preview_token');
        $sessionKey = "medicine_import_previews.{$token}";
        $storedPath = $request->session()->pull($sessionKey);

        if (!$storedPath || !Storage::disk('local')->exists($storedPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Preview import sudah kedaluwarsa. Silakan pilih file dan tampilkan ringkasan lagi.',
            ], 422);
        }

        try {
            $result = $service->import(Storage::disk('local')->path($storedPath));
        } finally {
            Storage::disk('local')->delete($storedPath);
        }

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }
}
