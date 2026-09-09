<?php

namespace App\Http\Controllers;

use App\Models\OrderItems;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class OrdersTrackingController extends Controller
{
    public function consolidate(Request $request, \App\Services\OrderConsolidation $service)
    {
        $validated = $request->validate([
            'request_key' => 'required|uuid',
            'items' => 'required|array|min:2|max:500',
            'items.*.id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|numeric|min:0.0001',
        ]);
        $order = $service->create($validated['items'], getPurchasingPharmacyId(), (int) $request->user()->id, $validated['request_key']);
        return response()->json(['redirect' => route('receiving.receive', $order->id)]);
    }

    public function cancelConsolidation(Request $request, \App\Services\OrderConsolidation $service)
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
        ]);
        $service->cancel((int) $validated['order_id'], getPurchasingPharmacyId(), (int) $request->user()->id);
        return response()->json([
            'success' => true,
            'message' => 'Konsolidasi BPBA berhasil dibatalkan. Kuantitas pesanan telah dikembalikan ke BPBA asal.',
            'redirect' => route('orders-tracking.index'),
        ]);
    }

    public function index()
    {
        $creditors = \App\Models\Creditor::orderBy('name')->get();
        return view('orders.tracking', compact('creditors'));
    }

    public function data(Request $request)
    {
        $query = OrderItems::with(['medicines', 'creditors', 'orders', 'receivingItems', 'outgoingMovements.targetItem.orders', 'incomingMovement.sourceItem.orders'])
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->select('order_items.*')
            ->when($request->creditor_code, function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('order_items.creditor_code', $request->creditor_code)
                        ->orWhere('order_items.branch_creditor_code', $request->creditor_code);
                });
            })
            ->when($request->order_code, function ($q) use ($request) {
                $q->where('orders.code', $request->order_code);
            })
            ->when($request->order_id, function ($q) use ($request) {
                $q->where('order_items.order_id', $request->order_id);
            })
            ->when($request->filled('medicine_name'), function ($q) use ($request) {
                $term = '%' . trim($request->medicine_name) . '%';
                $q->whereHas('medicines', function ($mq) use ($term) {
                    $mq->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term);
                });
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                if ($request->status == '2') {
                    // Diterima: Item ini memiliki catatan penerimaan dengan batch yang tersimpan di stok
                    $q->whereHas('receivingItems', function ($rq) {
                        $rq->whereNotNull('batches_id');
                    });
                } elseif ($request->status == '0') {
                    // Dipesan (Belum Diterima): Item ini BELUM memiliki catatan penerimaan batch di stok
                    $q->whereDoesntHave('receivingItems', function ($rq) {
                        $rq->whereNotNull('batches_id');
                    })->where('order_items.quantity', '>', 0);
                } else {
                    $q->where('order_items.status', $request->status);
                }
            })
            ->when($request->date_from && $request->date_to, function ($q) use ($request) {
                $q->whereBetween('orders.updated_at', [
                    $request->date_from . ' 00:00:00',
                    $request->date_to . ' 23:59:59',
                ]);
            })
            ->where('orders.pharmacy_id', getPurchasingPharmacyId())
            ->orderBy('orders.updated_at', 'desc');

        return DataTables::of($query)
            ->addColumn('remaining', fn ($row) => max(0, (float) $row->quantity - (float) $row->receivingItems->sum('qty_received')))
            ->addColumn('can_consolidate', fn ($row) => in_array((int) $row->orders->status, [1, 2]) && $row->creditor_code && !$row->receivingItems->contains(fn ($item) => $item->batches_id === null) && (float) $row->quantity > (float) $row->receivingItems->sum('qty_received'))
            ->addColumn('movement_note', function ($row) {
                $notes = $row->outgoingMovements->map(fn ($m) => 'Dipindahkan ' . (float) $m->quantity . ' ke ' . $m->targetItem->orders->code)->all();
                if ($row->incomingMovement) {
                    $notes[] = 'Asal: ' . $row->incomingMovement->sourceItem->orders->code;
                }
                if ($row->original_quantity !== null) {
                    $notes[] = 'Qty awal: ' . $row->original_quantity;
                }
                return implode('; ', $notes);
            })
            ->filterColumn('medicine_name', function ($q, $keyword) {
                $q->whereHas('medicines', function ($mq) use ($keyword) {
                    $mq->where('name', 'like', "%{$keyword}%")
                        ->orWhere('code', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('creditor_name', function ($q, $keyword) {
                $q->whereHas('creditors', function ($cq) use ($keyword) {
                    $cq->where('name', 'like', "%{$keyword}%")
                        ->orWhere('code', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('sp_code', function ($q, $keyword) {
                $q->where('order_items.order_items_code', 'like', "%{$keyword}%");
            })
            ->filterColumn('order_code', function ($q, $keyword) {
                $q->where('orders.code', 'like', "%{$keyword}%");
            })
            ->addColumn('sp_code', fn($row) => $row->order_items_code ?? '-')
            ->addColumn('order_code', fn($row) => $row->orders->code ?? '-')
            ->addColumn('order_date', fn($row) => $row->orders->date ?? '-')
            ->addColumn('medicine_name', fn($row) => $row->medicines->name ?? '-')
            ->addColumn('creditor_name', fn($row) => $row->creditors->name ?? '-')
            ->addColumn('status_label', function ($row) {
                if ((float) $row->quantity === 0 && $row->outgoingMovements->isNotEmpty()) {
                    return '<span class="tp-badge pending">Dipindahkan</span>';
                }
                $isReceived = $row->receivingItems && $row->receivingItems->whereNotNull('batches_id')->isNotEmpty();
                if ($isReceived && (float) $row->quantity > (float) $row->receivingItems->whereNotNull('batches_id')->sum('qty_received')) {
                    return '<span class="tp-badge pending">Diterima sebagian</span>';
                }

                return $isReceived
                    ? '<span class="tp-badge received"><span class="dot"></span>Diterima</span>'
                    : '<span class="tp-badge pending"><span class="dot"></span>Dipesan</span>';
            })
            ->addColumn('action', function ($row) {
                if ((float) $row->quantity === 0 && $row->outgoingMovements->isNotEmpty()) {
                    return 'Lihat BPBA tujuan pada kolom kode order';
                }
                $isReceived = $row->receivingItems && $row->receivingItems->whereNotNull('batches_id')->isNotEmpty();

                $url = route('receiving.receive', $row->order_id);
                if ($isReceived) {
                    return '<a href="' . $url . '" class="text-[12px] font-medium text-gray-600 border border-gray-200 bg-white hover:bg-gray-50 px-3 py-1.5 rounded-lg shadow-sm transition-colors inline-flex items-center gap-1.5 whitespace-nowrap">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                Rincian
                            </a>';
                }

                if (!empty($row->orders->is_consolidation)) {
                    return '<div class="inline-flex items-center gap-1.5 flex-wrap">
                                <a href="' . $url . '" class="text-[12px] font-medium bg-blue-600 text-white border border-blue-600 px-2.5 py-1.5 rounded-lg shadow-sm hover:bg-blue-700 hover:border-blue-700 transition-colors inline-flex items-center gap-1 whitespace-nowrap">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                    Terima
                                </a>
                                <button type="button" onclick="cancelConsolidation(' . $row->order_id . ')" class="text-[12px] font-medium text-red-600 hover:text-red-700 border border-red-200 bg-red-50 hover:bg-red-100 px-2.5 py-1.5 rounded-lg shadow-sm transition-colors inline-flex items-center gap-1 whitespace-nowrap" title="Batalkan konsolidasi dan kembalikan kuantitas ke BPBA asal">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18m-2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    Batalkan
                                </button>
                            </div>';
                }

                return '<a href="' . $url . '" class="text-[12px] font-medium bg-blue-600 text-white border border-blue-600 px-3 py-1.5 rounded-lg shadow-sm hover:bg-blue-700 hover:border-blue-700 transition-colors inline-flex items-center gap-1.5 whitespace-nowrap">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            Terima Pesanan
                        </a>';
            })
            ->rawColumns(['status_label', 'action'])
            ->make(true);
    }
}
