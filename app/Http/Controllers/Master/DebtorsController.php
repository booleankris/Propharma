<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Debtors;
use Illuminate\Http\Request;
use DataTables;
use Form;

class DebtorsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $debtors = Debtors::select('id', 'code', 'name', 'address', 'city', 'phone', 'email', 'status', 'created_at');

            if (!$request->has('order')) {
                $debtors = $debtors->orderBy('created_at', 'ASC');
            }

            return DataTables::of($debtors)
                ->addIndexColumn()
                ->addColumn('status_label', function ($debtor) {
                    return $debtor->status == 1
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-secondary">Inactive</span>';
                })
                ->addColumn('action', function ($debtor) {
                    $btn = '<div class="btn-toolbar" role="toolbar">
                                <div class="btn-group m-1 mr-2" role="group">';
                    $btn .= '<a class="btn btn-primary btn-sm" href="' . route('debtors.edit', $debtor->id) . '">Edit</a>';
                    $btn .= '</div><div class="btn-group m-1" role="group">';
                    $btn .= Form::button('Delete', [
                        'id' => 'button-delete-' . $debtor->id,
                        'class' => 'btn btn-danger btn-sm',
                        'data-route' => route('debtors.destroy', $debtor->id),
                        'onclick' => 'delete_data(' . $debtor->id . ')'
                    ]);
                    $btn .= '</div></div>';

                    return $btn;
                })
                ->escapeColumns([])
                ->toJson();
        }

        return view('master.debtors.index');
    }

    public function create()
    {
        return view('master.debtors.create');
    }

    private function generateDebtorCode()
    {
        $last = Debtors::orderBy('id', 'desc')->first();

        if (!$last || !$last->code) {
            return 'DB0001';
        }

        $number = (int) substr($last->code, 2);
        $nextNumber = $number + 1;

        return 'DB' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function store(Request $request)
    {
        $code = $this->generateDebtorCode();

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'city'    => 'nullable|string|max:100',
            'phone'   => 'nullable|string|max:50',
            'contact' => 'nullable|string|max:100',
            'email'   => 'nullable|email|max:255',
        ]);

        $debtor = Debtors::create([
            'code'    => $code,
            'name'    => $validated['name'],
            'address' => $validated['address'] ?? null,
            'city'    => $validated['city'] ?? null,
            'phone'   => $validated['phone'] ?? null,
            'contact' => $validated['contact'] ?? null,
            'email'   => $validated['email'] ?? null,
            'status'  => $request->status ?? 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data debitur berhasil disimpan.',
                'data'    => $debtor
            ]);
        }

        return redirect()->route('debtors.index')
            ->with('success', 'Data debitur berhasil disimpan.');
    }

    public function edit($id)
    {
        $debtor = Debtors::findOrFail($id);
        return view('master.debtors.edit', compact('debtor'));
    }

    public function update(Request $request, Debtors $debtor)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'city'    => 'nullable|string|max:100',
            'phone'   => 'nullable|string|max:50',
            'contact' => 'nullable|string|max:100',
            'email'   => 'nullable|email|max:255',
        ]);

        $debtor->update([
            'name'    => $validated['name'],
            'address' => $validated['address'] ?? null,
            'city'    => $validated['city'] ?? null,
            'phone'   => $validated['phone'] ?? null,
            'contact' => $validated['contact'] ?? null,
            'email'   => $validated['email'] ?? null,
            'status'  => $request->status ?? 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data debitur berhasil diperbarui.',
                'data'    => $debtor
            ]);
        }

        return redirect()->route('debtors.index')
            ->with('success', 'Data debitur berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $debtor = Debtors::findOrFail($id);
        $debtor->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Debtor successfully deleted!'
        ]);
    }
}
