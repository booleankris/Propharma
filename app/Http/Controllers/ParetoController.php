<?php

namespace App\Http\Controllers;

use App\Exports\Export\ParetoExport;
use App\Jobs\ProcessParetoExport;
use App\Models\ExportJob;
use App\Models\MedicineCart;
use App\Models\Pareto;
use App\Models\Pharmacies;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use DataTables;
use Maatwebsite\Excel\Facades\Excel;

class ParetoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user || (!$user->hasRole('Finance') && !$user->hasRole('General Manager') && !$user->hasRole('administrator') && !$user->hasRole('Koordinator') && !$user->hasRole('HO'))) {
                abort(403, 'Akses ditolak: Menu ini hanya dapat diakses oleh role Finance, General Manager, dan Koordinator.');
            }
            return $next($request);
        });
    }

    public function index()
    {
        return view('pareto.index');
    }

    public function salesPareto(Request $request)
    {
        $pharmacyId = $request->filled('pharmacy_id') ? (int) $request->pharmacy_id : getActivePharmacyId();
        if ($pharmacyId <= 0) {
            $pharmacyId = (int) (auth()->user()?->pharmacy_id ?: 1);
        }

        // Base query for completed sales in the active pharmacy.
        // Filters on transaction completion date (medicine_transactions.created_at)
        // rather than cart insertion time, ensuring 100% reconciliation with financial sales.
        $baseQuery = DB::table('medicine_cart')
            ->join('medicine_transactions', 'medicine_transactions.id', '=', 'medicine_cart.transaction_id')
            ->join('medicines', 'medicines.id', '=', 'medicine_cart.medicine_id')
            ->where('medicine_transactions.pharmacy_id', $pharmacyId)
            ->where('medicine_cart.status', 1)
            ->where(function ($q) {
                $q->where('medicine_transactions.status', 1)
                    ->orWhere(function ($unpaid) {
                        $unpaid->whereNotNull('medicine_transactions.transaction_code')
                            ->where(function ($unpaidType) {
                                $unpaidType->whereRaw("UPPER(TRIM(COALESCE(medicine_transactions.payment_method, ''))) = 'BELUM BAYAR'")
                                    ->orWhereIn('medicine_transactions.transaction_type', ['KREDIT', 'RESEP KREDIT']);
                            });
                    });
            });

        // ── Filter by date range on transaction completion date ──
        if ($request->filled('start_date')) {
            $start = Carbon::parse($request->start_date)->startOfDay();
            $baseQuery->where(function ($q) use ($start) {
                $q->where('medicine_transactions.created_at', '>=', $start)
                    ->orWhere(function ($sub) use ($start) {
                        $sub->whereNull('medicine_transactions.created_at')
                            ->where('medicine_transactions.updated_at', '>=', $start);
                    });
            });
        }
        if ($request->filled('end_date')) {
            $end = Carbon::parse($request->end_date)->endOfDay();
            $baseQuery->where(function ($q) use ($end) {
                $q->where('medicine_transactions.created_at', '<=', $end)
                    ->orWhere(function ($sub) use ($end) {
                        $sub->whereNull('medicine_transactions.created_at')
                            ->where('medicine_transactions.updated_at', '<=', $end);
                    });
            });
        }

        // Aggregation per medicine across the entire period (unfiltered by medicine search)
        // so that Pareto percentages and rankings represent the true store distribution.
        $allAggQuery = (clone $baseQuery)
            ->select([
                'medicines.id     as medicine_id',
                'medicines.code   as medicine_code',
                'medicines.name   as medicine_name',
                'medicines.unit   as medicine_unit',
                DB::raw('SUM(CAST(COALESCE(medicine_cart.quantity, 0) AS DECIMAL(12,2))) as total_qty'),
                DB::raw('SUM(CAST(COALESCE(medicine_cart.final_price, 0) AS DECIMAL(16,2))) as total_jumlah'),
                DB::raw('COUNT(DISTINCT medicine_cart.transaction_id) as freq'),
            ])
            ->groupBy('medicines.id', 'medicines.code', 'medicines.name', 'medicines.unit')
            ->orderByDesc('total_jumlah')
            ->orderByDesc('total_qty')
            ->orderBy('medicines.name');

        $allRows = $allAggQuery->get();
        $grandTotal = (float) $allRows->sum('total_jumlah');

        $paretoMap  = [];
        $runningSum = 0.0;
        $rank       = 1;

        foreach ($allRows as $row) {
            $jumlah = (float) $row->total_jumlah;
            $persen = $grandTotal > 0 ? round(($jumlah / $grandTotal) * 100, 2) : 0.0;
            $runningSum += $jumlah;
            $kumulatif = $grandTotal > 0 ? min(100.0, round(($runningSum / $grandTotal) * 100, 2)) : 0.0;

            $data = [
                'rank'      => $rank++,
                'persen'    => $persen,
                'kumulatif' => $kumulatif,
            ];

            $paretoMap[$row->medicine_id] = $data;
            if (!empty($row->medicine_code)) {
                $paretoMap[$row->medicine_code] = $data;
            }
        }

        // Now prepare query for DataTables display
        $displayQuery = clone $baseQuery;

        // ── Filter by medicine name or code if searched ──
        if ($request->filled('search_medicine')) {
            $kw = $request->search_medicine;
            $displayQuery->where(function ($q) use ($kw) {
                $q->where('medicines.name', 'like', "%{$kw}%")
                    ->orWhere('medicines.code', 'like', "%{$kw}%");
            });
        }

        $displayAgg = $displayQuery
            ->select([
                'medicines.id     as medicine_id',
                'medicines.code   as medicine_code',
                'medicines.name   as medicine_name',
                'medicines.unit   as medicine_unit',
                DB::raw('SUM(CAST(COALESCE(medicine_cart.quantity, 0) AS DECIMAL(12,2))) as total_qty'),
                DB::raw('SUM(CAST(COALESCE(medicine_cart.final_price, 0) AS DECIMAL(16,2))) as total_jumlah'),
                DB::raw('COUNT(DISTINCT medicine_cart.transaction_id) as freq'),
            ])
            ->groupBy('medicines.id', 'medicines.code', 'medicines.name', 'medicines.unit')
            ->orderByDesc('total_jumlah')
            ->orderByDesc('total_qty')
            ->orderBy('medicines.name');

        // Wrap as subquery so Yajra DataTables can reliably count and paginate
        $sub = DB::table(DB::raw("({$displayAgg->toSql()}) as pareto_sub"))
            ->mergeBindings($displayAgg);

        return DataTables::of($sub)
            ->addIndexColumn()
            ->addColumn('rank', function ($row) use ($paretoMap) {
                return $paretoMap[$row->medicine_id]['rank'] ?? ($paretoMap[$row->medicine_code]['rank'] ?? '-');
            })
            ->addColumn('persen', function ($row) use ($paretoMap) {
                return $paretoMap[$row->medicine_id]['persen'] ?? ($paretoMap[$row->medicine_code]['persen'] ?? 0);
            })
            ->addColumn('kumulatif', function ($row) use ($paretoMap) {
                return $paretoMap[$row->medicine_id]['kumulatif'] ?? ($paretoMap[$row->medicine_code]['kumulatif'] ?? 0);
            })
            ->addColumn('total_jumlah_fmt', function ($row) {
                return 'Rp ' . number_format($row->total_jumlah, 0, ',', '.');
            })
            ->make(true);
    }

    public function ordersPareto(Request $request)
    {
        $pharmacyId = $request->filled('pharmacy_id') ? (int) $request->pharmacy_id : getActivePharmacyId();
        if ($pharmacyId <= 0) {
            $pharmacyId = (int) (auth()->user()?->pharmacy_id ?: 1);
        }
        $targetPharmacyIds = in_array((int) $pharmacyId, [1, 6, 9]) ? [9, 1] : [(int) $pharmacyId];

        $baseQuery = DB::table('receiving_items')
            ->join('order_items', 'order_items.id', '=', 'receiving_items.order_items_id')
            ->join('medicines', 'medicines.id', '=', 'order_items.medicine_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.pharmacy_id', $targetPharmacyIds);

        // ── Filter by date range ──
        if ($request->filled('start_date')) {
            $start = Carbon::parse($request->start_date)->startOfDay();
            $baseQuery->where('receiving_items.created_at', '>=', $start);
        }
        if ($request->filled('end_date')) {
            $end = Carbon::parse($request->end_date)->endOfDay();
            $baseQuery->where('receiving_items.created_at', '<=', $end);
        }

        // Full aggregation across period for true Pareto percentages
        $allAggQuery = (clone $baseQuery)
            ->select([
                'medicines.id     as medicine_id',
                'medicines.code   as medicine_code',
                'medicines.name   as medicine_name',
                'medicines.unit   as medicine_unit',
                DB::raw('SUM(CAST(COALESCE(receiving_items.qty, 0) AS DECIMAL(12,2)))   as total_qty'),
                DB::raw('SUM(CAST(COALESCE(receiving_items.total, 0) AS DECIMAL(16,2))) as total_jumlah'),
                DB::raw('COUNT(DISTINCT receiving_items.receiving_details_id) as freq'),
            ])
            ->groupBy('medicines.id', 'medicines.code', 'medicines.name', 'medicines.unit')
            ->orderByDesc('total_jumlah')
            ->orderByDesc('total_qty')
            ->orderBy('medicines.name');

        $allRows = $allAggQuery->get();
        $grandTotal = (float) $allRows->sum('total_jumlah');

        $paretoMap  = [];
        $runningSum = 0.0;
        $rank       = 1;

        foreach ($allRows as $row) {
            $jumlah = (float) $row->total_jumlah;
            $persen = $grandTotal > 0 ? round(($jumlah / $grandTotal) * 100, 2) : 0.0;
            $runningSum += $jumlah;
            $kumulatif = $grandTotal > 0 ? min(100.0, round(($runningSum / $grandTotal) * 100, 2)) : 0.0;

            $data = [
                'rank'      => $rank++,
                'persen'    => $persen,
                'kumulatif' => $kumulatif,
            ];

            $paretoMap[$row->medicine_id] = $data;
            if (!empty($row->medicine_code)) {
                $paretoMap[$row->medicine_code] = $data;
            }
        }

        $displayQuery = clone $baseQuery;

        // ── Filter by medicine name or code ──
        if ($request->filled('search_medicine')) {
            $kw = $request->search_medicine;
            $displayQuery->where(function ($q) use ($kw) {
                $q->where('medicines.name', 'like', "%{$kw}%")
                    ->orWhere('medicines.code', 'like', "%{$kw}%");
            });
        }

        $displayAgg = $displayQuery
            ->select([
                'medicines.id     as medicine_id',
                'medicines.code   as medicine_code',
                'medicines.name   as medicine_name',
                'medicines.unit   as medicine_unit',
                DB::raw('SUM(CAST(COALESCE(receiving_items.qty, 0) AS DECIMAL(12,2)))   as total_qty'),
                DB::raw('SUM(CAST(COALESCE(receiving_items.total, 0) AS DECIMAL(16,2))) as total_jumlah'),
                DB::raw('COUNT(DISTINCT receiving_items.receiving_details_id) as freq'),
            ])
            ->groupBy('medicines.id', 'medicines.code', 'medicines.name', 'medicines.unit')
            ->orderByDesc('total_jumlah')
            ->orderByDesc('total_qty')
            ->orderBy('medicines.name');

        $sub = DB::table(DB::raw("({$displayAgg->toSql()}) as pareto_sub"))
            ->mergeBindings($displayAgg);

        return DataTables::of($sub)
            ->addIndexColumn()
            ->addColumn('rank', function ($row) use ($paretoMap) {
                return $paretoMap[$row->medicine_id]['rank'] ?? ($paretoMap[$row->medicine_code]['rank'] ?? '-');
            })
            ->addColumn('persen', function ($row) use ($paretoMap) {
                return $paretoMap[$row->medicine_id]['persen'] ?? ($paretoMap[$row->medicine_code]['persen'] ?? 0);
            })
            ->addColumn('kumulatif', function ($row) use ($paretoMap) {
                return $paretoMap[$row->medicine_id]['kumulatif'] ?? ($paretoMap[$row->medicine_code]['kumulatif'] ?? 0);
            })
            ->addColumn('total_jumlah_fmt', function ($row) {
                return 'Rp ' . number_format($row->total_jumlah, 0, ',', '.');
            })
            ->make(true);
    }

    // =============================== Pareto Penjualan & Pembelian ==========================
    public function export(Request $request)
    {
        $pharmacyId = $request->filled('pharmacy_id') ? (int) $request->pharmacy_id : getActivePharmacyId();
        if ($pharmacyId <= 0) {
            $pharmacyId = (int) (auth()->user()?->pharmacy_id ?: 1);
        }
        $pharmacy = Pharmacies::findOrFail($pharmacyId);

        if ($request->ajax() || $request->wantsJson()) {
            $job = ExportJob::create([
                'type'     => 'pareto',
                'status'   => 'pending',
                'progress' => 0,
            ]);

            dispatch(new ProcessParetoExport(
                $job->id,
                $pharmacy->id,
                $request->start_date,
                $request->end_date,
                $request->search_medicine
            ));

            return response()->json([
                'job_id'  => $job->id,
                'message' => 'Proses export pareto telah dimulai.',
            ]);
        }

        return Excel::download(
            new ParetoExport(
                $pharmacy->id,
                $request->start_date,
                $request->end_date,
                $request->search_medicine,
            ),
            'pareto-' . now()->format('Ymd') . '.xlsx'
        );
    }

    public function exportStatus($id)
    {
        $job = ExportJob::findOrFail($id);

        return response()->json([
            'status'   => $job->status,
            'progress' => (int) $job->progress,
            'file'     => ($job->status === 'completed' || $job->status === 'finished')
                ? route('pareto.export.download', $job->id)
                : null,
        ]);
    }

    public function download($id, Request $request)
    {
        $job = ExportJob::findOrFail($id);

        if ($job->file_path && Storage::disk('public')->exists($job->file_path)) {
            return Storage::disk('public')->download($job->file_path);
        }

        if ($job->file_path && file_exists(storage_path('app/public/' . $job->file_path))) {
            return response()->download(storage_path('app/public/' . $job->file_path));
        }

        // Fallback: Generate and stream directly if background file not found
        $pharmacyId = getActivePharmacyId();
        $pharmacy = $pharmacyId ? Pharmacies::find($pharmacyId) : null;
        if (!$pharmacy) {
            $pharmacy = auth()->user()?->pharmacy ?? Pharmacies::first();
        }
        return Excel::download(
            new ParetoExport(
                $pharmacy->id,
                $request->start_date,
                $request->end_date,
                $request->search_medicine,
            ),
            'pareto-' . now()->format('Ymd_His') . '.xlsx'
        );
    }
    // ================================ ================== ===================================

}
