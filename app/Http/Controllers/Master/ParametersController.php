<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Debtors;
use App\Models\Parameter;
use App\Models\TransactionParameter;
use Illuminate\Http\Request;
use DataTables;

class ParametersController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = TransactionParameter::with('debtor')
                ->select('transaction_parameter.*');

            return DataTables::of($data)
                ->addIndexColumn()

                // 1. Failsafe for the row index crash
                ->filterColumn('DT_RowIndex', function ($query, $keyword) {
                    // Do nothing
                })

                // 2. Map the search functionality for debtor_name to the database relationship
                ->filterColumn('debtor_name', function ($query, $keyword) {
                    $query->whereHas('debtor', function ($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })

                ->addColumn('debtor_name', fn($row) => $row->debtor?->name ?? '-')

                ->addColumn(
                    'status_label',
                    fn($row) =>
                    $row->status
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-secondary">Inactive</span>'
                )

                ->escapeColumns([])
                ->toJson();
        }

        $debtors = Debtors::pluck('name', 'id');
        return view('master.parameters.index', compact('debtors'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'debtor_id' => 'required|exists:debtors,id',
            'receipt'  => 'required|numeric',
            'pdu'      => 'required|numeric',
            'otc'      => 'required|numeric',
            'credit'   => 'required|numeric',
            'embalas'  => 'required|numeric',
            'service'  => 'required|numeric',
            'rounding' => 'required|numeric',
        ]);

        TransactionParameter::create([
            ...$data,
            'status' => 0
        ]);

        return response()->json(['message' => 'Parameter saved']);
    }

    public function update(Request $request, TransactionParameter $parameter)
    {
        $data = $request->validate([
            'debtor_id' => 'required|exists:debtors,id',
            'receipt'  => 'required|numeric',
            'pdu'      => 'required|numeric',
            'otc'      => 'required|numeric',
            'credit'   => 'required|numeric',
            'embalas'  => 'required|numeric',
            'service'  => 'required|numeric',
            'rounding' => 'required|numeric',
        ]);

        $parameter->update($data);

        return response()->json(['message' => 'Parameter updated']);
    }

    public function destroy(TransactionParameter $parameter)
    {
        $parameter->delete();

        return response()->json(['message' => 'Parameter deleted']);
    }
}
