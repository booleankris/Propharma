<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DataTables;

class ReturDataController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {

            $search     = trim($request->input('search.value'));
            $parsedDate = null;

            try {
                $parsedDate = Carbon::createFromFormat('d/m/Y', $search);
            } catch (\Exception $e) {
                $parsedDate = null;
            }

            // Wrap as subquery so aliases are real columns at the outer level
            $sub = DB::table('items_log')
                ->leftJoin('medicines', 'medicines.id', '=', 'items_log.medicine_id')
                ->leftJoin('medicine_transactions', 'medicine_transactions.transaction_code', '=', 'items_log.transaction_code')
                ->leftJoin('patients', 'patients.id', '=', 'medicine_transactions.patient_id')
                ->where('items_log.status', 3)
                ->select([
                    'items_log.id',
                    'items_log.transaction_code',
                    'items_log.qty',
                    'items_log.total',
                    'items_log.created_at',
                    'medicines.name  as medicine_name',
                    'patients.name   as patient_name',
                ]);

            // Wrap in a subquery — now patient_name & medicine_name are real columns
            $query = DB::table(DB::raw("({$sub->toSql()}) as retur_data"))
                ->mergeBindings($sub)
                ->select('*');

            if ($search) {
                $query->where(function ($q) use ($search, $parsedDate) {

                    if ($parsedDate) {
                        $q->orWhereDate('created_at', $parsedDate->format('Y-m-d'));
                    }

                    if (preg_match('/^\d{4}$/', $search)) {
                        $q->orWhereYear('created_at', $search);
                    }

                    $q->orWhere('transaction_code', 'like', "%{$search}%");
                    $q->orWhere('medicine_name',    'like', "%{$search}%");
                    $q->orWhere('patient_name',     'like', "%{$search}%");
                });
            }

            $query->orderBy('created_at', 'desc');

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('date', fn($row) =>
                    Carbon::parse($row->created_at)->format('d/m/Y')
                )
                ->addColumn('time', fn($row) =>
                    Carbon::parse($row->created_at)->format('H:i:s')
                )
                ->addColumn('qty_retur',       fn($row) => $row->qty)
                ->addColumn('total_formatted', fn($row) =>
                    '- Rp ' . number_format($row->total, 0, ',', '.')
                )
                ->make(true);
        }

        return view('sales.returdata');
    }
}
