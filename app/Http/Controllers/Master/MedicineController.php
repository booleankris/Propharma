<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Creditor;
use App\Models\MedicineCreditor;
use App\Models\MedicinePriceHistory;
use App\Models\Medicines;
use Illuminate\Http\Request;
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
            'content'              => 'nullable|string|max:255',
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

        // Handle checkbox: if checked, set content to 1, otherwise use the input value
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
            'raw_price'            => $request->raw_price,
            'pharmacy_net_price'   => $request->pharmacy_net_price,
            'net_price'            => $request->net_price,
            'het_price'            => $request->het_price ?? 0,
            'minimal_stock'        => $request->minimal_stock,
            'stock'                => $request->stock ?? 0,
            'psychotropic'         => $request->boolean('psychotropic') ? 1 : 0,
            'preparations'         => $request->preparations,
            'whole'                => $request->boolean('whole') ? 1 : 0,
            'precursor'            => $request->boolean('precursor') ? 1 : 0,
            'receipt'              => $request->boolean('receipt') ? 1 : 0,
            'etalase'              => $request->input('etalase'),
            'location'             => $request->input('location'),
            'type'                 => $request->type,
            'strip'                 => $request->strip,
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
            'content'              => 'nullable|string|max:255',
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

        // Check if checkbox is checked, if so, set content to 1, otherwise use the input value
        $contentValue = $request->has('is_active') ? ($request->input('content') ?: null) : 1;

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
            'raw_price'            => $request->raw_price,
            'pharmacy_net_price'   => $request->pharmacy_net_price,
            'net_price'            => $request->net_price,
            'het_price'            => $request->het_price ?? 0,
            'minimal_stock'        => $request->minimal_stock,
            'stock'                => $request->stock ?? 0,
            'psychotropic'         => $request->boolean('psychotropic') ? 1 : 0,
            'preparations'         => $request->preparations,
            'whole'                => $request->boolean('whole') ? 1 : 0,
            'precursor'            => $request->boolean('precursor') ? 1 : 0,
            'receipt'              => $request->boolean('receipt') ? 1 : 0,
            'etalase'              => $request->input('etalase'),
            'location'             => $request->input('location'),
            'type'                 => $request->type,
            'strip'                => $request->strip,
            'status'               => $request->has('status') ? (int) $request->status : 1,
        ]);
        // Add Medicine Price History

        $history = MedicinePriceHistory::create([
            'user_id'      => auth()->user()->id,
            'medicine_id'  => $id,
            'new_price'    => $request->pharmacy_net_price
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
}
