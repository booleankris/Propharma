<?php

namespace App\Http\Controllers;

use App\Models\ReceivingItems;
use App\Models\Receiving;
use App\Models\Creditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class OrdersPayment extends Controller
{
    public function index()
    {
        $creditors = Creditor::orderBy('name')->get();
        return view('statistic.orders_payment', compact('creditors'));
    }

    /**
     * Server-side DataTables endpoint.
     * Returns one row per ReceivingItems entry,
     * annotated with is_first_in_group so JS renders one "Selesai" button per receiving.id
     */
    public function getOrdersPayment(Request $request)
    {
        $startDate    = $request->input('start_date');
        $endDate      = $request->input('end_date');
        $creditorCode = $request->input('creditor_code');
        $search       = $request->input('search_medicine');

        // ── Base query ────────────────────────────────────────────────────────
        // Only items whose parent receiving has status = 2
        $query = ReceivingItems::with([
            'receiving_details.receiving',
            'receiving_details.creditor',
            'order_items.medicines',
        ])
            ->whereHas('receiving_details.receiving', function ($q) {
                $q->whereIn('status', [1,2]);
            });

        // ── Filters ───────────────────────────────────────────────────────────
        if ($startDate && $endDate) {
            $query->whereHas('receiving_details.receiving', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('updated_at', [
                    $startDate . ' 00:00:00',
                    $endDate   . ' 23:59:59',
                ]);
            });
        }

        if ($creditorCode) {
            $query->whereHas('receiving_details', function ($q) use ($creditorCode) {
                $q->where('creditor_code', $creditorCode);
            });
        }

        if ($search) {
            $query->whereHas('order_items.medicines', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // ── Total records ─────────────────────────────────────────────────────
        $totalRecords = (clone $query)->count();

        // ── Paging ────────────────────────────────────────────────────────────
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);

        $items = $query
            ->orderByDesc(
                DB::table('receiving_details')
                    ->join('receiving', 'receiving.id', '=', 'receiving_details.receiving_id')
                    ->whereColumn('receiving_details.id', 'receiving_items.receiving_details_id')
                    ->select('receiving.updated_at')
                    ->limit(1)
            )
            ->orderBy('receiving_items.receiving_details_id')
            ->skip($start)
            ->take($length)
            ->get();

        // ── Pre-compute Jumlah (SUM of price * qty_received) per receiving.id ─
        $receivingIds = $items
            ->map(fn($i) => $i->receiving_details->receiving_id ?? null)
            ->filter()
            ->unique()
            ->values();

        $jumlahMap = ReceivingItems::whereHas('receiving_details', function ($q) use ($receivingIds) {
            $q->whereIn('receiving_id', $receivingIds);
        })
            ->with('order_items')
            ->get()
            ->groupBy(fn($i) => $i->receiving_details->receiving_id)
            ->map(fn($group) => $group->sum(
                fn($i) => ($i->order_items->price ?? 0) * ($i->qty_received ?? 0)
            ));

        // ── Build rows ────────────────────────────────────────────────────────
        $claimRendered = [];
        $rowNumber     = $start + 1;

        $data = $items->map(function ($item) use (&$claimRendered, &$rowNumber, $jumlahMap) {
            $detail     = $item->receiving_details;
            $receiving  = $detail->receiving ?? null;
            $receivingId = $detail->receiving_id ?? null;

            $isFirstInGroup = !in_array($receivingId, $claimRendered);
            if ($isFirstInGroup) {
                $claimRendered[] = $receivingId;
            }

            $harga  = ($item->order_items->price ?? 0) * ($item->qty_received ?? 0);
            $jumlah = $jumlahMap[$receivingId] ?? 0;

            return [
                'DT_RowIndex'       => $rowNumber++,
                'tanggal'           => $receiving?->updated_at
                    ? $receiving->updated_at->format('d/m/Y H:i')
                    : '-',
                'no_pesanan'        => $receiving->code ?? '-',
                'no_faktur'         => $detail->invoice_number ?? '-',
                'creditor'          => $detail->creditor->name ?? '-',
                'nama_obat'         => $item->order_items->medicine->name ?? '-',
                'qty'               => $item->qty_received ?? 0,
                'harga'             => 'Rp ' . number_format($harga, 0, ',', '.'),
                'jumlah'            => 'Rp ' . number_format($jumlah, 0, ',', '.'),
                'status'            => $receiving->status ?? null,
                'is_first_in_group' => $isFirstInGroup,
                'receiving_id'      => $receivingId,
            ];
        });

        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data'            => $data->values(),
        ]);
    }

    /**
     * Selesai: set receiving.status = 2 for the given receiving.id
     */
    public function selesai($receivingId)
    {
        $receiving = Receiving::where('id', $receivingId)
            ->where('status', 1)
            ->firstOrFail();

        $receiving->update(['status' => 2]);

        return response()->json([
            'success' => true,
            'message' => "Pesanan #{$receiving->code} telah ditandai selesai.",
        ]);
    }
}
