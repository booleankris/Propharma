<?php

namespace App\Http\Controllers;

use App\Models\MedicineCart;
use App\Models\Debtors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoicesController extends Controller
{
    public function index()
    {
        $debtors = Debtors::orderBy('name')->get();
        return view('statistic.invoices', compact('debtors'));
    }

    /**
     * Server-side DataTables endpoint.
     * Returns one row per MedicineCart item (medicine line),
     * but includes transaction-level data (transaction_code, doctor, jumlah)
     * so JS can group rows visually and show one Klaim button per transaction.
     */
    public function getInvoices(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
        $debtorId  = $request->input('debtor_id');
        $search    = $request->input('search_medicine');

        // ── Base query ────────────────────────────────────────────────────────
        $query = MedicineCart::with([
                'medicine',
                'transactions.doctors',
                'transactions.debtors',
            ])
            ->where('medicine_cart.medicine_type', 'KREDIT');

        // ── Filters ───────────────────────────────────────────────────────────
        if ($startDate && $endDate) {
            $query->whereBetween('medicine_cart.updated_at', [
                $startDate . ' 00:00:00',
                $endDate   . ' 23:59:59',
            ]);
        }

        if ($debtorId) {
            $query->whereHas('transactions', function ($q) use ($debtorId) {
                $q->where('debtor_id', $debtorId);
            });
        }

        if ($search) {
            $query->whereHas('medicine', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // ── DataTables paging ─────────────────────────────────────────────────
        $totalRecords    = (clone $query)->count();
        $filteredRecords = $totalRecords;

        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);

        $items = $query
            ->orderBy('medicine_cart.updated_at', 'desc')
            ->orderBy('medicine_cart.transaction_id')
            ->skip($start)
            ->take($length)
            ->get();

        // ── Pre-compute SUM(final_price) per transaction_id ───────────────────
        $transactionIds = $items->pluck('transaction_id')->unique()->values();

        $jumlahMap = MedicineCart::whereIn('transaction_id', $transactionIds)
            ->where('medicine_type', 'KREDIT')
            ->select('transaction_id', DB::raw('SUM(final_price) as total_jumlah'))
            ->groupBy('transaction_id')
            ->get()
            ->keyBy('transaction_id');

        // ── Build rows ────────────────────────────────────────────────────────
        $claimRendered = [];
        $rowNumber     = $start + 1;

        $data = $items->map(function ($cart) use (&$claimRendered, &$rowNumber, $jumlahMap) {
            $transaction    = $cart->transactions;
            $transId        = $cart->transaction_id;
            $isFirstInGroup = !in_array($transId, $claimRendered);

            if ($isFirstInGroup) {
                $claimRendered[] = $transId;
            }

            $jumlah = $jumlahMap[$transId]->total_jumlah ?? 0;

            return [
                'DT_RowIndex'       => $rowNumber++,
                'DT_RowAttr'        => ['data-transaction-id' => $transId],
                'tanggal'           => $cart->updated_at
                                            ? $cart->updated_at->format('d/m/Y H:i')
                                            : '-',
                'nomor_resep'       => $transaction->transaction_code ?? '-',
                'dokter'            => $transaction->doctors->name ?? '-',
                'debtor'            => $transaction->debtors->name ?? '-',
                'nama_obat'         => $cart->medicine->name ?? '-',
                'qty'               => $cart->quantity,
                'harga'             => 'Rp ' . number_format($cart->final_price, 0, ',', '.'),
                'jumlah'            => 'Rp ' . number_format($jumlah, 0, ',', '.'),
                'status'            => $cart->status,
                'is_first_in_group' => $isFirstInGroup,
                'transaction_id'    => $transId,
            ];
        });

        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data->values(),
        ]);
    }

    /**
     * Klaim: set status = 2 for ALL MedicineCart items under this transaction_id
     */
    public function klaim($transactionId)
    {
        $updated = MedicineCart::where('transaction_id', $transactionId)
            ->where('medicine_type', 'KREDIT')
            ->where('status', '!=', 2)
            ->update(['status' => 2]);

        if ($updated === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada item yang dapat diklaim.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengklaim {$updated} item untuk resep ini.",
        ]);
    }
}