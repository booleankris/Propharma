<?php

namespace App\Http\Controllers;

use App\Models\OrderItems;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class OrdersTrackingController extends Controller
{
    public function index()
    {
        $creditors = \App\Models\Creditor::orderBy('name')->get();
        return view('orders.tracking', compact('creditors'));
    }

    public function data(Request $request)
    {
        $query = OrderItems::with(['medicines', 'creditors', 'orders', 'receiving_items.receiving_details'])
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->select('order_items.*')
            ->when($request->creditor_code, function ($q) use ($request) {
                $q->where('order_items.creditor_code', $request->creditor_code);
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('order_items.status', $request->status);
            })
            ->when($request->date_from && $request->date_to, function ($q) use ($request) {
                $q->whereBetween('orders.updated_at', [
                    $request->date_from . ' 00:00:00',
                    $request->date_to . ' 23:59:59',
                ]);
            })
            ->whereHas('orders', function ($d) {
                $d->where('pharmacy_id', getPurchasingPharmacyId());
            })
            ->orderBy('orders.updated_at', 'desc');

        return DataTables::of($query)
            ->addColumn('sp_code', fn($row) => $row->order_items_code ?? '-')
            ->addColumn('order_code', fn($row) => $row->orders->code)
            ->addColumn('order_date', fn($row) => $row->orders->date)
            ->addColumn('medicine_name', fn($row) => $row->medicines->name ?? '-')
            ->addColumn('creditor_name', fn($row) => $row->creditors->name ?? '-')
            ->addColumn('status_label', function ($row) {
                return $row->status == 2
                    ? '<span class="tp-badge received"><span class="dot"></span>Diterima</span>'
                    : '<span class="tp-badge pending"><span class="dot"></span>Dipesan</span>';
            })
            ->addColumn('action', function ($row) {
                $url = route('receiving.receive', $row->order_id);
                if ($row->status == 2) {
                    return '<a href="' . $url . '" class="text-[12px] font-medium text-gray-600 border border-gray-200 bg-white hover:bg-gray-50 px-3 py-1.5 rounded-lg shadow-sm transition-colors inline-flex items-center gap-1.5 whitespace-nowrap">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                Rincian
                            </a>';
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
