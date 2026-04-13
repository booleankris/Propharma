<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pharmacies;
use DataTables;

class PharmacyController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Pharmacies::query();

            // default sorting (optional but recommended)
            if (!$request->has('order')) {
                $data->orderBy('name', 'asc');
            }

            return DataTables::of($data)
                ->addIndexColumn()

                // FILTER (SEARCH)
                ->filter(function ($query) use ($request) {
                    if ($request->filled('name')) {
                        $query->where('name', 'like', "%{$request->name}%");
                    }
                    if ($request->filled('city')) {
                        $query->where('city', 'like', "%{$request->city}%");
                    }
                    if ($request->filled('phone')) {
                        $query->where('phone', 'like', "%{$request->phone}%");
                    }
                })

                // STATUS BADGE (IMPORTANT for your blade)
                ->addColumn('status', function ($row) {
                    if ($row->status == 1) {
                        return '<span class="badge badge-success">Aktif</span>';
                    }
                    return '<span class="badge badge-danger">Nonaktif</span>';
                })

                // ACTION BUTTONS
                ->addColumn('action', function ($row) {
                    return '
                    <div class="d-flex justify-content-center">
                        <a href="' . route('pharmacies.edit', $row->id) . '" 
                        class="btn btn-sm btn-icon btn-primary mr-1" 
                        title="Edit">Edit
                            <i class="fas fa-edit"></i>
                        </a>

                        <button onclick="delete_data(' . $row->id . ')" 
                            class="btn btn-sm btn-icon btn-danger" 
                            title="Hapus">Hapus
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                ';
                })

                // IMPORTANT: allow HTML render
                ->rawColumns(['status', 'action'])

                ->make(true);
        }

        return view('admin.pharmacies.index');
    }

    public function create()
    {
        return view('admin.pharmacies.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'address' => 'required',
        ]);

        Pharmacies::create($request->all());

        return redirect()->route('pharmacies.index')
            ->with('success', 'Data apotek berhasil ditambahkan');
    }

    public function show($id)
    {
        $pharmacy = Pharmacies::findOrFail($id);
        return view('admin.pharmacies.show', compact('pharmacy'));
    }

    public function edit($id)
    {
        $pharmacy = Pharmacies::findOrFail($id);
        return view('admin.pharmacies.edit', compact('pharmacy'));
    }

    public function update(Request $request, $id)
    {
        $pharmacy = Pharmacies::findOrFail($id);
        $pharmacy->update($request->all());

        return redirect()->route('pharmacies.index')
            ->with('success', 'Data apotek berhasil diupdate');
    }

    public function destroy($id)
    {
        $pharmacy = Pharmacies::findOrFail($id);
        $pharmacy->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihapus'
        ]);
    }
}
