<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Creditor;
use App\Models\MedicineCreditor;
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

            $data = Medicines::with([
                'composition',
                'category',
                'factory',
                'creditor',
                'etalases',
                'locations',
            ])
                ->select('medicines.*')
                ->where('status', 1)
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
            'generic'              => 'nullable|string|max:255',
            'pharmacy_id'          => 'required|integer',
            'medicine_category_id' => 'required|integer',
            'composition_id'       => 'required|integer',
            'factory_id'           => 'required|integer',
            'name'                 => 'required|string|max:255',
            'packaging'            => 'nullable|string|max:255',
            'unit'                 => 'nullable|string|max:100',
            'content'              => 'nullable|string|max:255',
            'dosage'               => 'nullable|string|max:255',
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

        $insert = Medicines::create([
            'code'                 => Medicines::generateCode(),
            'barcode'              => $request->barcode,
            'generic'              => $request->generic,
            'pharmacy_id'          => $request->pharmacy_id,
            'medicine_category_id' => $request->medicine_category_id,
            'composition_id'       => $request->composition_id,
            'factory_id'           => $request->factory_id,
            'creditors_id'         => null,
            'name'                 => $request->name,
            'packaging'            => $request->packaging,
            'unit'                 => $request->unit,
            'content'              => $request->content,
            'dosage'               => $request->dosage,
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
            'status'               => 1,
        ]);

        // Attach creditors via pivot
        $codes = array_filter(explode(',', $request->get('creditor_ids', '')));
        foreach ($codes as $code) {
            MedicineCreditor::create([
                'medicine_id'   => $insert->id,
                'creditor_code' => trim($code),
            ]);
        }

        return response()->json(['message' => 'Obat Berhasil Ditambahkan']);
    }

    /**
     * Update Medicine
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'generic'              => 'nullable|string|max:255',
            'pharmacy_id'          => 'required|integer',
            'medicine_category_id' => 'required|integer',
            'composition_id'       => 'required|integer',
            'name'                 => 'required|string|max:255',
            'packaging'            => 'nullable|string|max:255',
            'unit'                 => 'nullable|string|max:100',
            'content'              => 'nullable|string|max:255',
            'dosage'               => 'nullable|string|max:255',
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

        $medicine = Medicines::findOrFail($id);

        $medicine->update([
            'barcode'              => $request->barcode,
            'generic'              => $request->generic,
            'pharmacy_id'          => $request->pharmacy_id,
            'medicine_category_id' => $request->medicine_category_id,
            'composition_id'       => $request->composition_id,
            'factory_id'           => $request->factory_id,
            'creditors_id'         => null,
            'name'                 => $request->name,
            'packaging'            => $request->packaging,
            'unit'                 => $request->unit,
            'content'              => $request->content,
            'dosage'               => $request->dosage,
            'raw_price'            => $request->raw_price,
            'pharmacy_net_price'   => $request->pharmacy_net_price,
            'net_price'            => $request->net_price,
            'het_price'            => $request->het_price ?? 0,
            'minimal_stock'        => $request->minimal_stock,
            'stock'                => $request->stock ?? 0,
            'psychotropic'         => $request->boolean('psychotropic') ? 1 : 0,
            // FIX: was incorrectly storing boolean for preparations (a text field)
            'preparations'         => $request->preparations,
            'whole'                => $request->boolean('whole') ? 1 : 0,
            'precursor'            => $request->boolean('precursor') ? 1 : 0,
            'receipt'              => $request->boolean('receipt') ? 1 : 0,
            'etalase'              => $request->input('etalase'),
            'location'             => $request->input('location'),
            'type'                 => $request->type,
            'status'               => 1,
        ]);

        // FIX: was calling findOrFail + update twice (duplicate). Now sync creditors cleanly.
        $codes = array_filter(array_map('trim', explode(',', $request->get('creditor_ids', ''))));
        $medicine->creditors()->sync($codes);

        return response()->json(['message' => 'Obat Berhasil Di-Update.']);
    }

    /**
     * Soft Delete
     */
    public function destroy($id)
    {
        Medicines::findOrFail($id)->update(['status' => 0]);

        return response()->json(['message' => 'Obat Berhasil Dihapus']);
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
                'id'   => $c->id,
                'name' => $c->name,
                'code' => $c->code,
            ]),
        ]);
    }
}
