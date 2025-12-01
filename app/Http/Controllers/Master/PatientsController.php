<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Patients;
use Illuminate\Http\Request;
use DataTables;
use Form;

class PatientsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $patients = Patients::select('id', 'code', 'name', 'address', 'city', 'phone', 'birth', 'status', 'created_at');

            if (!$request->has('order')) {
                $patients = $patients->orderBy('created_at', 'ASC');
            }

            return DataTables::of($patients)
                ->addIndexColumn()
                ->addColumn('status_label', function ($patient) {
                    return $patient->status == 1
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-secondary">Inactive</span>';
                })
                ->addColumn('action', function ($patient) {
                    $btn = '<div class="btn-toolbar" role="toolbar">
                                <div class="btn-group m-1 mr-2" role="group">';
                    $btn .= '<a class="btn btn-primary btn-sm" href="' . route('patients.edit', $patient->id) . '">Edit</a>';
                    $btn .= '</div><div class="btn-group m-1" role="group">';

                    $btn .= Form::button('Delete', [
                        'id'          => 'button_delete_' . $patient->id,
                        'class'       => 'btn btn-danger btn-sm',
                        'data-route'  => route('patients.destroy', $patient->id),
                        'onclick'     => 'delete_data(' . $patient->id . ')'
                    ]);

                    $btn .= '</div></div>';

                    return $btn;
                })
                ->escapeColumns([])
                ->toJson();
        }

        return view('master.patients.index');
    }

    public function create()
    {
        return view('master.patients.create');
    }

    private function generatePatientCode()
    {
        $last = Patients::orderBy('id', 'desc')->first();

        if (!$last || !$last->code) {
            return 'PT0001';
        }

        $number = (int) substr($last->code, 2);
        $nextNumber = $number + 1;

        return 'PT' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function store(Request $request)
    {
        $code = $this->generatePatientCode();

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'city'    => 'nullable|string|max:100',
            'phone'   => 'nullable|string|max:50',
            'birth'   => 'nullable|date',
        ]);

        $patient = Patients::create([
            'code'    => $code,
            'name'    => $validated['name'],
            'address' => $validated['address'] ?? null,
            'city'    => $validated['city'] ?? null,
            'phone'   => $validated['phone'] ?? null,
            'birth'   => $validated['birth'] ?? null,
            'status'  => $request->status ?? 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data patient berhasil disimpan.',
                'data'    => $patient
            ]); 
            
        }

        return redirect()->route('patients.index')
            ->with('success', 'Data patient berhasil disimpan.');
    }

    public function edit($id)
    {
        $patient = Patients::findOrFail($id);
        return view('master.patients.edit', compact('patient'));
    }

    public function update(Request $request, Patients $patient)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'city'    => 'nullable|string|max:100',
            'phone'   => 'nullable|string|max:50',
            'birth'   => 'nullable|date',
        ]);

        $patient->update([
            'name'    => $validated['name'],
            'address' => $validated['address'] ?? null,
            'city'    => $validated['city'] ?? null,
            'phone'   => $validated['phone'] ?? null,
            'birth'   => $validated['birth'] ?? null,
            'status'  => $request->status ?? 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data patient berhasil diperbarui.',
                'data'    => $patient
            ]);
        }

        return redirect()->route('patients.index')
            ->with('success', 'Data patient berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $patient = Patients::findOrFail($id);
        $patient->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Patient successfully deleted!'
        ]);
    }
}
