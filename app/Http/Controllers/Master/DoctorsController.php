<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Doctors;
use App\Models\Pharmacies;
use Illuminate\Http\Request;
use DataTables;
use Form;

class DoctorsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        // AJAX: DataTables
        if ($request->ajax()) {
            $doctors = Doctors::with('pharmacies')
                ->select('id', 'pharmacy_id', 'code', 'name', 'specialist', 'address', 'city', 'phone', 'status', 'created_at');

            if (!$request->has('order')) {
                $doctors->orderBy('created_at', 'ASC');
            }

            return DataTables::of($doctors)
                ->addIndexColumn()
                ->addColumn('pharmacy_name', function ($doctor) {
                    return $doctor->pharmacy->name ?? '-';
                })
                ->addColumn('status_label', function ($doctor) {
                    return $doctor->status == 1
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-secondary">Inactive</span>';
                })
                ->addColumn('action', function ($doctor) {
                    $btn  = '<div class="btn-toolbar" role="toolbar">';
                    $btn .= '<div class="btn-group m-1 mr-2" role="group">';
                    $btn .= '<button class="btn btn-primary btn-sm" onclick="editData(' . $doctor->id . ')">Edit</button>';
                    $btn .= '</div>';

                    $btn .= '<div class="btn-group m-1" role="group">';
                    $btn .= Form::button("Delete", [
                        "id" => "button_delete_" . $doctor->id,
                        "class" => "btn btn-danger btn-sm",
                        "data-route" => route("doctors.destroy", $doctor->id),
                        "onclick" => "delete_data(" . $doctor->id . ")"
                    ]);
                    $btn .= '</div></div>';

                    return $btn;
                })
                ->escapeColumns([])
                ->toJson();
        }

        // Page render (table + form)
        $pharmacies = Pharmacies::orderBy('name')->get();

        return view('master.doctors.index', compact('pharmacies'));
    }

    private function generateDoctorCode()
    {
        $last = Doctors::orderBy('id', 'desc')->first();

        if (!$last || !$last->code) {
            return 'DR0001';
        }

        $number = (int) substr($last->code, 2);
        return 'DR' . str_pad($number + 1, 4, '0', STR_PAD_LEFT);
    }

    public function store(Request $request)
    {
        $code = $this->generateDoctorCode();

        $validated = $request->validate([
            'pharmacy_id' => 'required|',
            'name'        => 'required|string|max:255',
            'specialist'  => 'nullable|string|max:255',
            'address'     => 'nullable|string|max:255',
            'city'        => 'nullable|string|max:100',
            'phone'       => 'nullable|string|max:50',
        ]);

        $doctor = Doctors::create([
            'code'        => $code,
            'pharmacy_id' => $validated['pharmacy_id'],
            'name'        => $validated['name'],
            'specialist'  => $validated['specialist'] ?? null,
            'address'     => $validated['address'] ?? null,
            'city'        => $validated['city'] ?? null,
            'phone'       => $validated['phone'] ?? null,
            'status'      => $request->status ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data doctor berhasil disimpan.',
            'data'    => $doctor
        ]);
    }

    public function show(Doctors $doctor)
    {
        return response()->json($doctor);
    }

    public function update(Request $request, Doctors $doctor)
    {
        $validated = $request->validate([
            'pharmacy_id' => 'required|',
            'name'        => 'required|string|max:255',
            'specialist'  => 'nullable|string|max:255',
            'address'     => 'nullable|string|max:255',
            'city'        => 'nullable|string|max:100',
            'phone'       => 'nullable|string|max:50',
        ]);

        $doctor->update([
            'pharmacy_id' => $validated['pharmacy_id'],
            'name'        => $validated['name'],
            'specialist'  => $validated['specialist'] ?? null,
            'address'     => $validated['address'] ?? null,
            'city'        => $validated['city'] ?? null,
            'phone'       => $validated['phone'] ?? null,
            'status'      => $request->status ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data doctor berhasil diperbarui.',
            'data'    => $doctor
        ]);
    }

    public function destroy($id)
    {
        $doctor = Doctors::findOrFail($id);
        $doctor->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Doctor successfully deleted!'
        ]);
    }
}
