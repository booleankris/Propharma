<?php

namespace App\Http\Controllers\Master;

use App\Exports\Report\PatientTransactionsExport;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPatientTransactionsExport;
use App\Models\ExportJob;
use App\Models\MedicineTransactions;
use App\Models\Patients;
use App\Models\Pharmacies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class PatientTransactionsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $pharmacies = Pharmacies::where('status', 1)->orderBy('name')->get();
        $activePharmacyId = function_exists('getActivePharmacyId') ? getActivePharmacyId() : null;
        $today = now()->format('Y-m-d');

        return view('master.patient-transactions.index', compact('pharmacies', 'activePharmacyId', 'today'));
    }

    public function searchPatients(Request $request)
    {
        $q = trim($request->get('q', ''));

        $query = Patients::select(['id', 'code', 'name', 'phone', 'city', 'address']);

        if (!empty($q)) {
            $query->where(function ($w) use ($q) {
                $w
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $patients = $query
            ->orderBy('name', 'ASC')
            ->limit(25)
            ->get();

        $results = [];

        // Include "Semua Pasien" option when no search term or at top of list
        if (empty($q) || stripos('semua pasien', $q) !== false || stripos('all', $q) !== false) {
            $results[] = [
                'id' => 'all',
                'text' => 'SEMUA PASIEN (SEMUA TRANSAKSI)',
                'code' => 'ALL',
                'phone' => '-',
            ];
        }

        foreach ($patients as $p) {
            $phoneStr = $p->phone ? " - Telp: {$p->phone}" : '';
            $codeStr = $p->code ? "[{$p->code}] " : '';
            $results[] = [
                'id' => $p->id,
                'text' => "{$codeStr}{$p->name}{$phoneStr}",
                'code' => $p->code ?? '-',
                'phone' => $p->phone ?? '-',
                'name' => $p->name,
            ];
        }

        return response()->json([
            'results' => $results,
        ]);
    }

    public function preview(Request $request)
    {
        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : now()->startOfDay();
        $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();
        $patientId = ($request->patient_id === 'all' || empty($request->patient_id)) ? null : $request->patient_id;
        $pharmacyId = ($request->pharmacy_id === 'all' || empty($request->pharmacy_id)) ? null : $request->pharmacy_id;
        $mode = strtolower($request->get('mode', 'rekap')) === 'detail' ? 'detail' : 'rekap';
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min(100, max(10, (int) $request->get('per_page', 50)));

        // Base query for transactions
        $baseQuery = MedicineTransactions::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 1);

        if ($patientId) {
            $baseQuery->where('patient_id', $patientId);
        }

        if ($pharmacyId) {
            $baseQuery->where('pharmacy_id', $pharmacyId);
        }

        // Summary KPI statistics (computed efficiently over entire range)
        $totalTransactions = (clone $baseQuery)->count();
        $totalNominal = (float) (clone $baseQuery)->sum('subtotal');
        $uniquePatients = (clone $baseQuery)->whereNotNull('patient_id')->distinct('patient_id')->count('patient_id');

        $rows = [];
        $pagination = [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => 0,
            'last_page' => 1,
            'from' => 0,
            'to' => 0,
        ];

        if ($mode === 'rekap') {
            $pagination['total'] = $totalTransactions;
            $pagination['last_page'] = max(1, (int) ceil($totalTransactions / $perPage));
            $pagination['from'] = $totalTransactions > 0 ? (($page - 1) * $perPage) + 1 : 0;
            $pagination['to'] = min($totalTransactions, $page * $perPage);

            $transactions = (clone $baseQuery)
                ->with(['patients', 'pharmacy'])
                ->orderBy('created_at', 'DESC')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            $no = $pagination['from'];
            foreach ($transactions as $trx) {
                $subtotal = (float) ($trx->subtotal ?? 0);
                $rows[] = [
                    'no' => $no++,
                    'patient_name' => $trx->patients?->name ?? 'Umum / Tanpa Pasien',
                    'patient_phone' => $trx->patients?->phone ?? '-',
                    'transaction_code' => $trx->transaction_code ?? '-',
                    'date' => $trx->created_at ? $trx->created_at->format('d/m/Y') : '-',
                    'time' => $trx->created_at ? $trx->created_at->format('H:i:s') : '-',
                    'pharmacy' => $trx->pharmacy?->name ?? '-',
                    'total' => $subtotal,
                    'total_formatted' => 'Rp ' . number_format($subtotal, 0, ',', '.'),
                    'payment' => $trx->payment_method ?? '-',
                    'type' => $trx->transaction_type ?? '-',
                ];
            }
        } else {
            // DETAIL MODE
            // Query transactions with cart items
            // To paginate cleanly and avoid browser crash: paginate by transaction, then flatten items
            $pagination['total'] = $totalTransactions;
            $pagination['last_page'] = max(1, (int) ceil($totalTransactions / $perPage));
            $pagination['from'] = $totalTransactions > 0 ? (($page - 1) * $perPage) + 1 : 0;
            $pagination['to'] = min($totalTransactions, $page * $perPage);

            $transactions = (clone $baseQuery)
                ->with(['patients', 'pharmacy', 'transactions.medicine'])
                ->orderBy('created_at', 'DESC')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            $no = 1;
            foreach ($transactions as $trx) {
                $patientName = $trx->patients?->name ?? 'Umum / Tanpa Pasien';
                $patientPhone = $trx->patients?->phone ?? '-';
                $pharmacyName = $trx->pharmacy?->name ?? '-';
                $dateStr = $trx->created_at ? $trx->created_at->format('d/m/Y') : '-';
                $timeStr = $trx->created_at ? $trx->created_at->format('H:i:s') : '-';
                $payMethod = $trx->payment_method ?? '-';
                $trxType = $trx->transaction_type ?? '-';

                $items = $trx->transactions ?? collect();

                if ($items->isEmpty()) {
                    $subtotal = (float) ($trx->subtotal ?? 0);
                    $rows[] = [
                        'no' => $no++,
                        'patient_name' => $patientName,
                        'patient_phone' => $patientPhone,
                        'transaction_code' => $trx->transaction_code ?? '-',
                        'date' => $dateStr,
                        'time' => $timeStr,
                        'pharmacy' => $pharmacyName,
                        'medicine_name' => '-',
                        'price' => 0,
                        'price_formatted' => 'Rp 0',
                        'qty' => 0,
                        'total' => $subtotal,
                        'total_formatted' => 'Rp ' . number_format($subtotal, 0, ',', '.'),
                        'payment' => $payMethod,
                        'type' => $trxType,
                    ];
                } else {
                    foreach ($items as $item) {
                        $medName = $item->medicine?->name ?? ($item->medicine_name ?? '-');
                        $price = (float) ($item->item_price ?? 0);
                        $qty = (float) ($item->quantity ?? 0);
                        $itemTot = (float) ($item->final_price ?? $item->total_price ?? ($price * $qty));

                        $rows[] = [
                            'no' => $no++,
                            'patient_name' => $patientName,
                            'patient_phone' => $patientPhone,
                            'transaction_code' => $trx->transaction_code ?? '-',
                            'date' => $dateStr,
                            'time' => $timeStr,
                            'pharmacy' => $pharmacyName,
                            'medicine_name' => $medName,
                            'price' => $price,
                            'price_formatted' => 'Rp ' . number_format($price, 0, ',', '.'),
                            'qty' => $qty,
                            'total' => $itemTot,
                            'total_formatted' => 'Rp ' . number_format($itemTot, 0, ',', '.'),
                            'payment' => $payMethod,
                            'type' => $trxType,
                        ];
                    }
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'mode' => $mode,
            'summary' => [
                'total_transactions' => $totalTransactions,
                'total_transactions_formatted' => number_format($totalTransactions, 0, ',', '.'),
                'total_nominal' => $totalNominal,
                'total_nominal_formatted' => 'Rp ' . number_format($totalNominal, 0, ',', '.'),
                'unique_patients' => $uniquePatients,
                'unique_patients_formatted' => number_format($uniquePatients, 0, ',', '.'),
            ],
            'pagination' => $pagination,
            'rows' => $rows,
        ]);
    }

    public function export(Request $request)
    {
        $patientId = ($request->patient_id === 'all' || empty($request->patient_id)) ? null : $request->patient_id;
        $startDate = $request->start_date ? Carbon::parse($request->start_date)->toDateString() : now()->toDateString();
        $endDate = $request->end_date ? Carbon::parse($request->end_date)->toDateString() : now()->toDateString();
        $mode = strtolower($request->get('mode', 'rekap')) === 'detail' ? 'detail' : 'rekap';
        $pharmacyId = ($request->pharmacy_id === 'all' || empty($request->pharmacy_id)) ? null : $request->pharmacy_id;

        $job = ExportJob::create([
            'type' => 'patient_transactions',
            'status' => 'pending',
            'progress' => 0,
        ]);

        dispatch(new ProcessPatientTransactionsExport(
            $job->id,
            $patientId,
            $startDate,
            $endDate,
            $mode,
            $pharmacyId
        ));

        return response()->json([
            'status' => 'success',
            'job_id' => $job->id,
            'message' => 'Antrean ekspor Excel berhasil dibuat.',
        ]);
    }

    public function exportStatus($id)
    {
        $job = ExportJob::findOrFail($id);

        $downloadUrl = null;
        if (($job->status === 'completed' || $job->status === 'finished') && $job->file_path) {
            $downloadUrl = route('master.patient-transactions.export-download', $job->id);
        }

        return response()->json([
            'status' => $job->status,
            'progress' => (int) $job->progress,
            'file' => $downloadUrl,
        ]);
    }

    public function exportDownload($id)
    {
        $job = ExportJob::findOrFail($id);

        if (!$job->file_path || !Storage::disk('public')->exists($job->file_path)) {
            abort(404, 'File export tidak ditemukan atau masih diproses.');
        }

        return Storage::disk('public')->download($job->file_path);
    }
}
