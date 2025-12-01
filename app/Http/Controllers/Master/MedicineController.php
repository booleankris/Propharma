<?php
namespace App\Http\Controllers\Master;
use App\Http\Controllers\Controller;

use App\Models\Medicine;
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

            $data = Medicines::orderBy('id', 'DESC')->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('status_label', function ($row) {
                    return $row->status
                        ? '<span class="px-3 py-1 bg-green-100 text-green-700 rounded-lg text-xs">Active</span>'
                        : '<span class="px-3 py-1 bg-red-100 text-red-700 rounded-lg text-xs">Inactive</span>';
                })
                ->rawColumns(['status_label'])
                ->make(true);
        }

        return view('master.medicines.index');
    }

    /**
     * Store Medicine
     */
    public function store(Request $request)
    {
        $request->validate([
            'generic'                 => 'nullable|string|max:255',
            'pharmacy_id'             => 'required|integer',
            'medicine_category_id'    => 'required|integer',
            'composition_id'          => 'required|integer',
            'factory_id'              => 'required|integer',
            'creditors_id'            => 'required|integer',
            'name'                    => 'required|string|max:255',
            'packaging'               => 'nullable|string|max:255',
            'unit'                    => 'nullable|string|max:100',
            'content'                 => 'nullable|string|max:255',
            'dosage'                  => 'nullable|string|max:255',
            'raw_price'               => 'nullable|numeric',
            'pharmacy_net_price'      => 'nullable|numeric',
            'net_price'               => 'nullable|numeric',
            'minimal_stock'           => 'nullable|numeric',
            'stock'                   => 'nullable|numeric',
            // checkboxes
            'psychotropic'            => 'nullable|boolean',
            'preparations'            => 'nullable|string|max:255',
            'whole'                   => 'nullable|boolean',
            'precursor'               => 'nullable|boolean',
            'receipt'                 => 'nullable|boolean',
            'status'                  => 'nullable|boolean',
        ]);

        // Auto-generate code
        $code = Medicines::generateCode();

        Medicines::create([
            'code'                   => $code,
            'generic'                => $request->generic,
            'pharmacy_id'            => $request->pharmacy_id,
            'medicine_category_id'   => $request->medicine_category_id,
            'composition_id'         => $request->composition_id,
            'factory_id'             => $request->factory_id,
            'creditors_id'           => $request->creditors_id,
            'name'                   => $request->name,
            'packaging'              => $request->packaging,
            'unit'                   => $request->unit,
            'content'                => $request->content,
            'dosage'                 => $request->dosage,
            'raw_price'              => $request->raw_price,
            'pharmacy_net_price'     => $request->pharmacy_net_price,
            'net_price'              => $request->net_price,
            'minimal_stock'          => $request->minimal_stock,
            'stock'                  => $request->stock,
            'psychotropic'           => $request->psychotropic ? 1 : 0,
            'preparations'           => $request->preparations,
            'whole'                  => $request->whole ? 1 : 0,
            'precursor'              => $request->precursor ? 1 : 0,
            'receipt'                => $request->receipt ? 1 : 0,
            'status'                 => $request->status ? 1 : 0,
        ]);

        return response()->json(['message' => 'Medicine created successfully.']);
    }

    /**
     * Update Medicine
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'generic'                 => 'nullable|string|max:255',
            'pharmacy_id'             => 'required|integer',
            'medicine_category_id'    => 'required|integer',
            'composition_id'          => 'required|integer',
            'factory_id'              => 'required|integer',
            'creditors_id'            => 'required|integer',
            'name'                    => 'required|string|max:255',
            'packaging'               => 'nullable|string|max:255',
            'unit'                    => 'nullable|string|max:100',
            'content'                 => 'nullable|string|max:255',
            'dosage'                  => 'nullable|string|max:255',
            'raw_price'               => 'nullable|numeric',
            'pharmacy_net_price'      => 'nullable|numeric',
            'net_price'               => 'nullable|numeric',
            'minimal_stock'           => 'nullable|numeric',
            'stock'                   => 'nullable|numeric',
            // checkboxes
            'psychotropic'            => 'nullable|boolean',
            'preparations'            => 'nullable|string|max:255',
            'whole'                   => 'nullable|boolean',
            'precursor'               => 'nullable|boolean',
            'receipt'                 => 'nullable|boolean',
            'status'                  => 'nullable|boolean',
        ]);

        $medicine = Medicines::findOrFail($id);

        $medicine->update([
            'generic'                => $request->generic,
            'pharmacy_id'            => $request->pharmacy_id,
            'medicine_category_id'   => $request->medicine_category_id,
            'composition_id'         => $request->composition_id,
            'factory_id'             => $request->factory_id,
            'creditors_id'           => $request->creditors_id,
            'name'                   => $request->name,
            'packaging'              => $request->packaging,
            'unit'                   => $request->unit,
            'content'                => $request->content,
            'dosage'                 => $request->dosage,
            'raw_price'              => $request->raw_price,
            'pharmacy_net_price'     => $request->pharmacy_net_price,
            'net_price'              => $request->net_price,
            'minimal_stock'          => $request->minimal_stock,
            'stock'                  => $request->stock,
            'psychotropic'           => $request->psychotropic ? 1 : 0,
            'preparations'           => $request->preparations ? 1 : 0,
            'whole'                  => $request->whole ? 1 : 0,
            'precursor'              => $request->precursor ? 1 : 0,
            'receipt'                => $request->receipt ? 1 : 0,
            'status'                 => $request->status ? 1 : 0,
        ]);

        return response()->json(['message' => 'Medicine updated successfully.']);
    }

    /**
     * Delete
     */
    
    public function destroy($id)
    {
        $medicine = Medicines::findOrFail($id);
        $medicine->delete();

        return response()->json([
            'message' => 'Medicine deleted successfully.'
        ]);
    }
}
