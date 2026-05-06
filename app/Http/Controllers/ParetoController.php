<?php

namespace App\Http\Controllers;

use App\Exports\Export\ParetoExport;
use App\Models\MedicineCart;
use App\Models\Pareto;
use App\Models\Pharmacies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DataTables;
use Maatwebsite\Excel\Facades\Excel;

class ParetoController extends Controller
{
    public function index()
    {
        return view('pareto.index');
    }
    public function salesPareto(Request $request)
    {
        $pharmacyId = auth()->user()->pharmacy_id;

        $query = DB::table('medicine_cart')
            ->join('medicine_transactions', 'medicine_transactions.id', '=', 'medicine_cart.transaction_id')
            ->join('medicines', 'medicines.id', '=', 'medicine_cart.medicine_id')
            ->where('medicine_transactions.pharmacy_id', $pharmacyId)
            ->where('medicine_cart.status', 1)
            ->where('medicine_transactions.status', 1)
            ->select([
                'medicines.code   as medicine_code',
                'medicines.name   as medicine_name',
                'medicines.unit   as medicine_unit',
                DB::raw('SUM(medicine_cart.quantity)    as total_qty'),
                DB::raw('SUM(medicine_cart.final_price) as total_jumlah'),
                DB::raw('COUNT(DISTINCT medicine_cart.transaction_id) as freq'),
            ])
            ->groupBy('medicine_cart.medicine_id', 'medicines.code', 'medicines.name', 'medicines.unit');

        // ── Filter by date range ──
        if ($request->filled('start_date')) {
            $query->whereDate('medicine_cart.created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('medicine_cart.created_at', '<=', $request->end_date);
        }

        // ── Filter by medicine name or code ──
        if ($request->filled('search_medicine')) {
            $kw = $request->search_medicine;
            $query->where(function ($q) use ($kw) {
                $q->where('medicines.name', 'like', "%{$kw}%")
                    ->orWhere('medicines.code', 'like', "%{$kw}%");
            });
        }

        $query->orderBy('total_jumlah', 'desc');

        // Wrap as subquery so aliases like total_jumlah are real columns
        $sub = DB::table(DB::raw("({$query->toSql()}) as pareto_sub"))
            ->mergeBindings($query);

        $grandTotal = $sub->sum('total_jumlah') ?: 1;

        $allRows = (clone $sub)->orderBy('total_jumlah', 'desc')->get();
        $cumulative = 0.0;
        $paretoMap  = [];

        foreach ($allRows as $row) {
            $persen      = round(($row->total_jumlah / $grandTotal) * 100, 2);
            $cumulative  = round($cumulative + $persen, 2);
            $paretoMap[$row->medicine_code] = [
                'persen'     => $persen,
                'kumulatif'  => $cumulative,
            ];
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('persen', function ($row) use ($paretoMap) {
                return $paretoMap[$row->medicine_code]['persen'] ?? 0;
            })
            ->addColumn('kumulatif', function ($row) use ($paretoMap) {
                return $paretoMap[$row->medicine_code]['kumulatif'] ?? 0;
            })
            ->addColumn('total_jumlah_fmt', function ($row) {
                return 'Rp ' . number_format($row->total_jumlah, 0, ',', '.');
            })
            ->make(true);
    }
    public function ordersPareto(Request $request)
    {
        $pharmacyId = auth()->user()->pharmacy_id;

        $query = DB::table('receiving_items')
            ->join('order_items', 'order_items.id', '=', 'receiving_items.order_items_id')
            ->join('medicines', 'medicines.id', '=', 'order_items.medicine_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.pharmacy_id', $pharmacyId)
            ->select([
                'medicines.code   as medicine_code',
                'medicines.name   as medicine_name',
                'medicines.unit   as medicine_unit',
                DB::raw('SUM(receiving_items.qty)   as total_qty'),
                DB::raw('SUM(receiving_items.total) as total_jumlah'),
                DB::raw('COUNT(DISTINCT receiving_items.receiving_details_id) as freq'),
            ])
            ->groupBy('order_items.medicine_id', 'medicines.code', 'medicines.name', 'medicines.unit');

        // ── Filter by date range ──
        if ($request->filled('start_date')) {
            $query->whereDate('receiving_items.created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('receiving_items.created_at', '<=', $request->end_date);
        }

        // ── Filter by medicine name or code ──
        if ($request->filled('search_medicine')) {
            $kw = $request->search_medicine;
            $query->where(function ($q) use ($kw) {
                $q->where('medicines.name', 'like', "%{$kw}%")
                    ->orWhere('medicines.code', 'like', "%{$kw}%");
            });
        }

        $query->orderBy('total_jumlah', 'desc');

        // ── Wrap as subquery so aliases are real columns ──
        $sub = DB::table(DB::raw("({$query->toSql()}) as pareto_sub"))
            ->mergeBindings($query);

        $grandTotal = $sub->sum('total_jumlah') ?: 1;

        $allRows   = (clone $sub)->orderBy('total_jumlah', 'desc')->get();
        $cumulative = 0.0;
        $paretoMap  = [];

        foreach ($allRows as $row) {
            $persen     = round(($row->total_jumlah / $grandTotal) * 100, 2);
            $cumulative = round($cumulative + $persen, 2);
            $paretoMap[$row->medicine_code] = [
                'persen'    => $persen,
                'kumulatif' => $cumulative,
            ];
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('persen', function ($row) use ($paretoMap) {
                return $paretoMap[$row->medicine_code]['persen'] ?? 0;
            })
            ->addColumn('kumulatif', function ($row) use ($paretoMap) {
                return $paretoMap[$row->medicine_code]['kumulatif'] ?? 0;
            })
            ->addColumn('total_jumlah_fmt', function ($row) {
                return 'Rp ' . number_format($row->total_jumlah, 0, ',', '.');
            })
            ->make(true);
    }
    // =============================== Pareto Penjualan & Pembelian ==========================
    public function export(Request $request)
    {
        $pharmacy = Pharmacies::findOrFail(auth()->user()->pharmacy_id);
        return Excel::download(
            new ParetoExport(
                $pharmacy->id,
                $request->start_date,
                $request->end_date,
            ),
            'pareto-' . now()->format('Ymd') . '.xlsx'
        );
    }
    // ================================ ================== ===================================

}
