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
            ->when($request->status, function ($q) use ($request) {
                $q->where('order_items.status', $request->status);
            })
            ->when($request->date_from && $request->date_to, function ($q) use ($request) {
                $q->whereBetween('orders.updated_at', [
                    $request->date_from . ' 00:00:00',
                    $request->date_to . ' 23:59:59',
                ]);
            })
            ->whereHas('orders', function ($d) {
                $d->where('pharmacy_id', getActivePharmacyId());
            })
            ->orderBy('orders.updated_at', 'desc');

        return DataTables::of($query)
            ->addColumn('sp_code', fn($row) => $row->receiving_items->receiving_details->sp_code ?? '-')
            ->addColumn('order_code', fn($row) => $row->orders->code)
            ->addColumn('order_date', fn($row) => $row->orders->date)
            ->addColumn('medicine_name', fn($row) => $row->medicines->name ?? '-')
            ->addColumn('creditor_name', fn($row) => $row->creditors->name ?? '-')
            ->addColumn('status_label', function ($row) {
                return $row->status == 2
                    ? '<span class="tp-badge received"><span class="dot"></span>Diterima</span>'
                    : '<span class="tp-badge pending"><span class="dot"></span>Dipesan</span>';
            })
            ->rawColumns(['status_label'])
            ->make(true);
    }
}
