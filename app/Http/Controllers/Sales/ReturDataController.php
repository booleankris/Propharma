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

            $search = trim($request->input('search.value'));
            $parsedDate = $this->parseDate($search);
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $pharmacyId = getActivePharmacyId();

            // ======================
            // SUB QUERY
            // ======================
            $sub = DB::table('items_log')
                ->leftJoin('medicines', 'medicines.id', '=', 'items_log.medicine_id')
                ->leftJoin('medicine_transactions', 'medicine_transactions.transaction_code', '=', 'items_log.transaction_code')
                ->leftJoin('patients', 'patients.id', '=', 'medicine_transactions.patient_id')
                ->leftJoin('receiving', 'receiving.code', '=', 'items_log.transaction_code')
                ->whereIn('items_log.status', [3, 4])
                ->select([
                    'items_log.id',
                    'items_log.transaction_code',
                    'items_log.qty',
                    'items_log.total',
                    'items_log.created_at',
                    DB::raw('COALESCE(medicine_transactions.pharmacy_id, receiving.pharmacy_id) as pharmacy_id'),
                    'medicines.name  as medicine_name',
                    'patients.name   as patient_name',

                    DB::raw("
                        CASE 
                            WHEN items_log.status = 3 THEN 'RETUR PENJUALAN'
                            WHEN items_log.status = 4 THEN 'RETUR PEMBELIAN'
                        END as jenis
                    "),
                ]);

            // Apply active pharmacy filter
            if ($pharmacyId) {
                $targetPharmacyIds = in_array((int) $pharmacyId, [1, 6, 9]) ? [9, 1] : [(int) $pharmacyId];
                $sub->where(function ($q) use ($pharmacyId, $targetPharmacyIds) {
                    $q->where('medicine_transactions.pharmacy_id', $pharmacyId)
                        ->orWhereIn('receiving.pharmacy_id', $targetPharmacyIds);
                });
            }

            // ======================
            // MAIN QUERY (WRAP SUBQUERY)
            // ======================
            $query = DB::table(DB::raw("({$sub->toSql()}) as retur_data"))
                ->mergeBindings($sub)
                ->select('*');

            // ======================
            // FILTER SEARCH
            // ======================
            if ($search) {
                $query->where(function ($q) use ($search, $parsedDate) {

                    // Filter tanggal (dd/mm/yyyy)
                    if ($parsedDate) {
                        $q->orWhereDate('created_at', $parsedDate->format('Y-m-d'));
                    }

                    // Filter tahun (YYYY)
                    if (preg_match('/^\d{4}$/', $search)) {
                        $q->orWhereYear('created_at', $search);
                    }

                    // Filter umum
                    $q->orWhere('transaction_code', 'like', "%{$search}%")
                        ->orWhere('medicine_name', 'like', "%{$search}%")
                        ->orWhere('patient_name', 'like', "%{$search}%")
                        ->orWhere('jenis', 'like', "%{$search}%");
                });
            }

            if ($startDate && $endDate) {
                $query->whereDate('created_at', '>=', $startDate)
                    ->whereDate('created_at', '<=', $endDate);
            }

            // ======================
            // ORDER
            // ======================
            $query->orderBy('created_at', 'desc');

            // ======================
            // DATATABLES RESPONSE
            // ======================
            return DataTables::of($query)
                ->addIndexColumn()

                ->addColumn(
                    'date',
                    fn($row) =>
                    Carbon::parse($row->created_at)->format('d/m/Y')
                )

                ->addColumn(
                    'time',
                    fn($row) =>
                    Carbon::parse($row->created_at)->format('H:i:s')
                )

                ->addColumn('qty_retur', fn($row) => $row->qty)

                ->addColumn(
                    'total_formatted',
                    fn($row) =>
                    '- Rp ' . number_format($row->total, 0, ',', '.')
                )

                ->addColumn('jenis', function ($row) {
                    return $row->jenis === 'RETUR PENJUALAN'
                        ? '<span class="badge bg-danger">RETUR PENJUALAN</span>'
                        : '<span class="badge bg-success">RETUR PEMBELIAN</span>';
                })
                ->rawColumns(['jenis'])
                ->make(true);
        }

        return view('sales.returdata');
    }

    /**
     * Helper untuk parsing tanggal format d/m/Y
     */
    private function parseDate($search)
    {
        try {
            return Carbon::createFromFormat('d/m/Y', $search);
        } catch (\Exception $e) {
            return null;
        }
    }
}
